<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalDrushSqlDumpTrait;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\DrupalLocalDockerContainerTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines commands used to sync deployed data to the local Drupal application.
 */
class DrupalSyncCommands extends DockworkerLocalCommands {

  use DrupalKubernetesPodTrait;
  use DrupalLocalDockerContainerTrait;
  use DrupalDrushSqlDumpTrait;

  const POD_DATABASE_DUMP_COMPRESSED_FILENAME = 'tmpdb.sql.gz';
  const POD_DATABASE_DUMP_FILENAME = 'tmpdb.sql';
  const POD_FILES_DUMP_FILENAME = 'tmpfiles.tar.gz';
  const POD_FILES_SOURCE = '/app/html/sites/default/files';
  const POD_TEMPORARY_FILE_LOCATION = '/tmp';

  /**
   * Sets if the remote database be synchronized.
   *
   * @var bool
   */
  private $drupalRemoteSyncDatabase = TRUE;

  /**
   * Sets if the remote filesystem should be synchronized.
   *
   * @var bool
   */
  private $drupalRemoteSyncFiles = TRUE;

  /**
   * The name of the kubernetes pod to synchronize from.
   *
   * @var string
   */
  private $drupalRemoteSyncPodName = NULL;

  /**
   * Encrypt local Drupal data into dockworker content archive(s).
   *
   * @command repo:test-content:update
   *
   * @throws \Dockworker\DockworkerException
   *
   * @github
   */
  public function encryptDrupalDatabaseFileSystemFromLocal() {
    $passphrase = $this->ask('Passphrase to encrypt content with?');
    $this->dumpDrupalDatabaseFileSystemFromLocal();

    exec("mkdir -p {$this->repoRoot}/data/content/");

    $tmp_db_path = self::POD_TEMPORARY_FILE_LOCATION  . '/' . self::POD_DATABASE_DUMP_COMPRESSED_FILENAME;
    $repo_db_path = "{$this->repoRoot}/data/content/db.sql.gz.gpg";
    exec("gpg --symmetric --batch --cipher-algo AES256 --passphrase='$passphrase' $tmp_db_path");
    exec("mv $tmp_db_path.gpg $repo_db_path");

    $tmp_file_path = self::POD_TEMPORARY_FILE_LOCATION  . '/' . self::POD_FILES_DUMP_FILENAME;
    $repo_file_path = "{$this->repoRoot}/data/content/files.tar.gz.gpg";
    exec("gpg --symmetric --batch --cipher-algo AES256 --passphrase='$passphrase' $tmp_file_path");
    exec("mv $tmp_file_path.gpg $repo_file_path");
  }

  /**
   * Dumps deployed Drupal data into local archive(s).
   *
   * @command local:content:dump
   *
   * @throws \Dockworker\DockworkerException
   *
   * @github
   */
  public function dumpDrupalDatabaseFileSystemFromLocal() {
    $this->io()->newLine();
    $this->io()->section("Dumping Drupal filesystem from local...");
    $files_dump_name = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_FILES_DUMP_FILENAME;

    $this->say("[Local] (optionally) Removing Drupal filesystem archive...");
    $this->runLocalContainerCommand('rm -f '. $files_dump_name);

    $this->say("[Local] Creating Drupal filesystem archive...");

    $excludes = [
      '*.css',
      '*.css.gz',
      '*.js',
      '*.js.gz',
      'app/html/sites/default/files/php',
      'app/html/sites/default/files/styles'
    ];

    $exclude_string = NULL;
    foreach ($excludes as $exclude) {
      $exclude_string .= "--exclude='$exclude' ";
    }

    $archive_command = "tar -cvpzf $files_dump_name $exclude_string" . self::POD_FILES_SOURCE;
    $this->runLocalContainerCommand($archive_command);

    $this->say("[Local] Copying Drupal filesystem archive to Docker Host's temporary directory...");
    $this->copyContainerFileToLocal($files_dump_name, $files_dump_name);

    $this->say("[Remote] Removing Drupal filesystem archive file...");
    $this->runLocalContainerCommand('rm -f '. $files_dump_name);

    // Database
    $this->io()->newLine();
    $this->io()->section("Synchronizing Drupal database from local...");
    $dump_file = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_DATABASE_DUMP_FILENAME;
    $gz_dump_file = $dump_file . '.gz';

    $this->say("[Local] (optionally) Removing Drupal database archive file...");
    $this->runLocalContainerCommand('rm -f '. $gz_dump_file);

    $this->say("[Local] Clearing cache {$this->drupalRemoteSyncPodName}...");
    $this->runLocalContainerCommand('$DRUSH cr');

    $this->say("[Local] Dumping Drupal database from {$this->drupalRemoteSyncPodName}...");
    $this->runLocalContainerCommand('$DRUSH ' . $this->getDrushDumpCommand() . ' --result-file=' . $dump_file);

    $this->say("[Local] Compressing Drupal database archive file...");
    $this->runLocalContainerCommand('gzip '. $dump_file);

    $this->say("[Local] Copying Drupal database archive file to Docker Host's temporary directory...");
    $this->copyContainerFileToLocal($gz_dump_file, $gz_dump_file);

    $this->say("[Local] Removing Drupal database archive file...");
    $this->runLocalContainerCommand('rm -f '. $gz_dump_file);
  }

  /**
   * Synchronizes deployed Drupal config data into the local config-yml path.
   *
   * @param string $env
   *   The deploy environment to synchronize from. Defaults to 'prod'.
   *
   * @command local:config:remote-sync
   *
   * @kubectl
   */
  public function synchronizeConfig($env = 'prod') {
    $this->kubernetesPodNamespace = $env;
    $this->kubernetesSetupPods($this->instanceName, "Synchronization");
    $pod_id = array_shift($this->kubernetesCurPods);

    $this->say("Exporing live config from $env/$pod_id...");
    $this->kubernetesPodExecCommand(
      $pod_id,
      $env,
      '/scripts/configExport.sh'
    );

    $this->say("Copying config from $env/$pod_id to local...");
    $this->kubernetesCopyFromPodCommand(
      $pod_id,
      $env,
      '/app/configuration',
      './config-yml'
    );
  }

  /**
   * Synchronizes deployed Drupal data into the local Drupal application.
   *
   * @param string $env
   *   The deploy environment to synchronize from.
   * @param string[] $opts
   *   An array of options to pass to the builder.
   *
   * @option bool $no-database
   *   Do not synchronize the drupal database.
   * @option bool $no-files
   *   Do not synchronize the drupal filesystem.
   *
   * @command local:content:remote-sync
   *
   * @throws \Dockworker\DockworkerException
   *
   * @github
   * @kubectl
   */
  public function syncDrupalDatabaseFileSystemFromRemote($env, $opts = ['no-database' => FALSE, 'no-files' => FALSE]) {
    $this->getLocalRunning();

    $this->io()->title('Deployed Data Synchronization');

    $this->kubernetesPodNamespace = $env;
    $this->kubernetesSetupPods($this->instanceName, "Synchronization");

    // All pods should return the same data, so simply use the first.
    $this->drupalRemoteSyncPodName = $this->kubernetesCurPods[0];

    // Determine operations to perform.
    $this->drupalRemoteSyncDatabase = !$opts['no-database'];
    $this->drupalRemoteSyncFiles = !$opts['no-files'];

    // Dump out no-op users.
    if (!$this->drupalRemoteSyncDatabase && !$this->drupalRemoteSyncFiles) {
      $this->say('No operations requested. Exiting...');
      return;
    }

    // Error Checking.
    $this->checkRemoteDrush();
    $this->checkLocalDrush();
    $this->compareLocalRemoteDrupalVersions();

    if ($this->drupalRemoteSyncDatabase) {
      $this->syncDrupalDatabaseFromRemote();
    }

    if ($this->drupalRemoteSyncFiles) {
      $this->syncDrupalFileSystemFromRemote();
    }

    if ($this->drupalRemoteSyncDatabase || $this->drupalRemoteSyncFiles) {
      $this->syncDrupalDatabaseFileSystemCleanup();
    }
  }

  /**
   * Checks if the remote kubernetes pod responds to drush commands.
   *
   * @throws \Exception
   */
  private function checkRemoteDrush() {
    $output = $this->runRemoteDrushCommand('status');
    $this->checkDrushCommandOutput($output);
  }

  /**
   * Runs a drush command in the remote kubernetes pod.
   *
   * @param string $command
   *   The drush command to run.
   *
   * @throws \Exception
   *
   * @return mixed
   *   The command result.
   */
  private function runRemoteDrushCommand($command) {
    return $this->kubernetesPodDrushCommand(
      $this->drupalRemoteSyncPodName,
      $this->kubernetesPodNamespace,
      $command
    );
  }

  /**
   * Validates drush status command output to determine if Drupal bootstrapped.
   *
   * @param string[] $output
   *
   * @throws \Dockworker\DockworkerException
   */
  private function checkDrushCommandOutput(array $output) {
    if (stristr($output[0], '8.') || stristr($output[0], '9.')) {
      return;
    }
    throw new DockworkerException("Remote drush could not bootstrap Drupal instance.");
  }

  /**
   * Checks the local Drupal application responds as expected to drush commands.
   *
   * @throws \Dockworker\DockworkerException
   * @throws \Exception
   */
  private function checkLocalDrush() {
    $output = $this->runRemoteDrushCommand('status');
    $this->checkDrushCommandOutput($output);
  }

  /**
   * Compares the local Drupal application and deployed drush versions.
   *
   * @throws \Dockworker\DockworkerException
   * @throws \Exception
   */
  private function compareLocalRemoteDrupalVersions() {
    $remote_output = $this->runRemoteDrushCommand('status');
    $local_output = $this->runLocalDrushCommand('status');
    if ($remote_output[0] != $local_output[0]) {
      $this->say(
        sprintf(
          'Your local drupal instance version [%s] does not match the remote [%s].',
          $local_output[0],
          $remote_output[0]
        )
      );
      $this->say('To sync, your local instance must be built with the exact same Drupal Core version as remote');
      throw new DockworkerException("Remote-local version mismatch.");
    }
  }

  /**
   * Runs a drush command in the local Drupal application.
   *
   * @param string $command
   *   The command string to execute.
   *
   * @throws \Exception
   *
   * @return mixed
   *   The command result.
   */
  private function runLocalDrushCommand($command) {
    return $this->localDockerContainerDrushCommand(
      $this->instanceName,
      $command
    );
  }

  /**
   * Synchronizes a deployed database into the local Drupal application.
   *
   * @throws \Exception
   */
  private function syncDrupalDatabaseFromRemote($dump_only = FALSE) {
    $this->io()->newLine();
    $this->io()->section("Synchronizing Drupal database from [{$this->drupalRemoteSyncPodName}]");
    $dump_file = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_DATABASE_DUMP_FILENAME;
    $gz_dump_file = $dump_file . '.gz';

    $this->say("[Remote] (optionally) Removing Drupal database archive file...");
    $this->runRemoteCommand('rm -f '. $gz_dump_file);

    $this->say("[Remote] Clearing cache {$this->drupalRemoteSyncPodName}...");
    $this->runRemoteDrushCommand('cr');

    $this->say("[Remote] Dumping Drupal database from {$this->drupalRemoteSyncPodName}...");
    $this->runRemoteDrushCommand($this->getDrushDumpCommand() . ' --result-file=' . $dump_file);

    $this->say("[Remote] Compressing Drupal database archive file...");
    $this->runRemoteCommand('gzip '. $dump_file);

    $this->say("[Remote] Copying Drupal database archive file to Docker Host's temporary directory...");
    $this->copyRemoteFileToLocal($gz_dump_file, $gz_dump_file);

    $this->say("[Remote] Removing Drupal database archive file...");
    $this->runRemoteCommand('rm -f '. $gz_dump_file);

    if (!$dump_only) {
      $this->say("[Docker Host] Copying Drupal database archive file to local container...");
      $this->copyLocalFileToContainer($gz_dump_file, $gz_dump_file);

      $this->say("[Docker Host] Deleting Drupal database archive file...");
      unlink($gz_dump_file);

      $this->say("[Container] (optionally) Removing Drupal database archive file...");
      $this->runLocalContainerCommand("rm -f $dump_file");

      $this->say("[Container] Decompressing Drupal database archive file...");
      $this->runLocalContainerCommand("gunzip $gz_dump_file");

      $this->say("[Container] Importing Drupal database archive file...");
      $this->runLocalContainerCommand("sh -c \"drush --root=/app/html sql-cli < $dump_file\"");

      $this->say("[Container] Removing Drupal database archive file...");
      $this->runLocalContainerCommand("rm -f $dump_file");

      $this->say("[Container] Clearing cache...");
      $this->runLocalDrushCommand("cr");
    }
  }

  /**
   * Runs a command in the remote kubernetes pod.
   *
   * @param string $command
   *   The command to run.
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The command result.
   */
  private function runRemoteCommand($command) {
    return $this->kubernetesPodExecCommand(
      $this->drupalRemoteSyncPodName,
      $this->kubernetesPodNamespace,
      $command
    );
  }

  /**
   * Copies a remote file to the local (host) filesystem.
   *
   * @param string $remote_filename
   *   The remote filename to copy.
   * @param string $local_filename
   *   The local filename to write to.
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The command result.
   */
  private function copyRemoteFileToLocal($remote_filename, $local_filename) {
    return $this->kubernetesPodFileCopyCommand(
      $this->kubernetesPodNamespace,
      $this->drupalRemoteSyncPodName . ':' . $remote_filename,
      $local_filename
    );
  }

  /**
   * Copies a local (host) file to the local Drupal application.
   *
   * @param string $local_filename
   *   The local filename to copy.
   * @param string $container_filename
   *   The container filename to write to.
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The command result.
   */
  private function copyLocalFileToContainer($local_filename, $container_filename) {
    return $this->localDockerContainerCopyCommand(
      $local_filename,
      $this->instanceName . ':' . $container_filename
    );
   }

  private function copyContainerFileToLocal($local_filename, $container_filename) {
    return $this->localDockerContainerCopyCommand(
      $this->instanceName . ':' . $container_filename,
      $local_filename
    );
  }

  /**
   * Runs a command in the local Drupal application.
   *
   * @param string $command
   *   The command to run.
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The command result.
   */
  private function runLocalContainerCommand($command) {
    return $this->localDockerContainerExecCommand(
      $this->instanceName,
      $command
    );
  }

  /**
   * Synchronizes deployed Drupal filesystem into the local Drupal application.
   *
   * @throws \Exception
   */
  private function syncDrupalFileSystemFromRemote($dump_only = FALSE, $exclude_files = []) {
    $this->io()->newLine();
    $this->io()->section("Synchronizing Drupal filesystem from [{$this->drupalRemoteSyncPodName}]");
    $files_dump_name = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_FILES_DUMP_FILENAME;

    $this->say("[Remote] (optionally) Removing Drupal filesystem archive...");
    $this->runRemoteCommand('rm -f '. $files_dump_name);

    $this->say("[Remote] Creating Drupal filesystem archive...");

    $exclude_string = NULL;
    if (!empty($exclude_files)) {
      foreach ($exclude_files as $exclude_path) {
        $exclude_string .= "--exclude='$exclude_path'";
      }
    }

    $archive_command = "tar -cvpzf $exclude_string $files_dump_name " . self::POD_FILES_SOURCE;
    $this->runRemoteCommand("tar -cvpzf  $files_dump_name " . self::POD_FILES_SOURCE);

    $this->say("[Remote] Copying Drupal filesystem archive to Docker Host's temporary directory...");
    $this->copyRemoteFileToLocal($files_dump_name, $files_dump_name);

    $this->say("[Remote] Removing Drupal filesystem archive file...");
    $this->runRemoteCommand('rm -f '. $files_dump_name);

    $this->say("[Docker Host] Copying Drupal filesystem archive to local container...");
    $this->copyLocalFileToContainer($files_dump_name, $files_dump_name);

    if (!$dump_only) {
      $this->say("[Docker Host] Deleting Drupal filesystem archive...");
      unlink($files_dump_name);

      $this->say("[Container] Deleting container Drupal filesystem...");
      $this->runLocalContainerCommand('rm -rf ' . self::POD_FILES_SOURCE);

      $this->say("[Container] Extracting remote Drupal filesystem...");
      $this->runLocalContainerCommand("tar -xzf $files_dump_name --directory /");

      $this->say("[Container] Removing Drupal filesystem archive file...");
      $this->runLocalContainerCommand('rm -f ' . $files_dump_name);

      $this->say("[Container] Setting overall Drupal filesystem permissions...");
      $this->runLocalContainerCommand('/scripts/pre-init.d/70_set_drupal_tree_permissions.sh');
      $this->say("[Container] Setting config sync permissions...");
      $this->runLocalContainerCommand('/scripts/pre-init.d/71_set_config_sync_permissions.sh');
      $this->say("[Container] Setting public filesystem permissions...");
      $this->runLocalContainerCommand('/scripts/pre-init.d/71_set_public_file_permissions.sh');
    }
  }

  /**
   * Cleans up and post-init tasks for sync.
   *
   * @see syncDrupalFileSystemFromRemote()
   * @throws \Dockworker\DockworkerException
   */
  private function syncDrupalDatabaseFileSystemCleanup() {
    $this->io()->newLine();
    $this->io()->section('Cleaning Up');
    $this->say("Generating New ULI Link...");
    $this->say(
      $this->runLocalContainerCommand("/scripts/drupalUli.sh")[0]
    );
  }

}
