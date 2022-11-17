<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalDrushSqlDumpTrait;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\DrupalLocalDockerContainerTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentDaemonCommands;

/**
 * Defines commands used to sync deployed data to the local Drupal application.
 */
class DrupalSyncCommands extends DockworkerDeploymentDaemonCommands {

  use DrupalKubernetesPodTrait;
  use DrupalLocalDockerContainerTrait;
  use DrupalDrushSqlDumpTrait;

  const POD_DATABASE_DUMP_COMPRESSED_FILENAME = 'tmpdb.sql.gz';
  const POD_DATABASE_DUMP_FILENAME = 'tmpdb.sql';
  const POD_FILES_DUMP_FILENAME = 'tmpfiles.tar.gz';
  const POD_FILES_SOURCE = '/app/html/sites/default/files';
  const POD_TEMPORARY_FILE_LOCATION = '/tmp';

  /**
   * Sets if the remote database should be compressed before transfer.
   *
   * @var bool
   */
  private $drupalRemoteCompressDatabase = TRUE;

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
  private $drupalRemoteSyncPodName;

  /**
   * Dumps deployed Drupal data into local archive(s).
   *
   * @throws \Dockworker\DockworkerException
   *
   * @github
   */
  protected function dumpDrupalDatabaseFileSystemFromLocal() {
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

    $exclude_string = '';
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
   * Exports Drupal config from this application's k8s deployment and writes it to this repository.
   *
   * @param string $env
   *   The deploy environment to synchronize from. Defaults to 'prod'.
   *
   * @command drupal:config:write:deployed
   *
   * @kubectl
   */
  public function synchronizeConfig($env = 'prod') {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'synchronization');

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
      $this->repoRoot .'/config-yml'
    );
  }

  /**
   * Checks if deployed in-memory configuration matches that on disk.
   *
   * @param string $env
   *   The deploy environment to check. Defaults to 'prod'.
   *
   * @command drupal:config:check:deployed
   *
   * @kubectl
   */
  public function checkDeployedConfig(string $env = 'prod') {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'synchronization');
    $delta_configs =$this->kubernetesPodExecCommand(
      $pod_id,
      $env,
      '/scripts/list_active_delta_stored_config_objects.sh',
      FALSE
    );

    if ($env == 'dev') {
      $delta_configs = array_values(
        array_diff(
          $delta_configs,
          [
            'samlauth.authentication'
          ]
        )
      );
    }

    if (!empty($delta_configs)) {
      $this->say("When comparing deployed active configuration and the deployed config sync directory, differences were found in the following objects:");
      $this->io()->block(print_r($delta_configs, TRUE));
      $this->say("This may indicate, amongst many things that hook_update() functions have modified configuration objects after deployment.");
      $this->say("To examine the changes, synchronize the deployed active configuration to your local lean repository via:");
      $this->io()->block("dockworker drupal:config:write:deployed $env");
      $this->say("Then, review the differences and commit them as necessary.");
      throw new DockworkerException("Config Mismatch!");
    }
    else {
      $this->say("No differences found when comparing deployed active configuration and the deployed config sync directory");
    }
  }

  /**
   * Synchronizes all Drupal data within this application's k8s deployment to this local deployment.
   *
   * @param string $env
   *   The deploy environment to synchronize from.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $no-compress-database
   *   Do not compress the drupal database.
   * @option $no-database
   *   Do not synchronize the drupal database.
   * @option $no-files
   *   Do not synchronize the drupal filesystem.
   *
   * @command sync:all:deployed:local
   * @aliases sync-from-deployed
   * @aliases sync
   *
   * @throws \Dockworker\DockworkerException
   *
   * @github
   * @kubectl
   */
  public function syncDrupalDatabaseFileSystemFromRemote($env, array $options = ['no-compress-database' => FALSE, 'no-database' => FALSE, 'no-files' => FALSE]) {
    $this->getLocalRunning();
    $this->enableCommandRunTimeDisplay();
    $this->io()->title('Deployed Data Synchronization');

    // All pods should return the same data, so simply use the first.
    $this->drupalRemoteSyncPodName = $this->k8sGetLatestPod($env, 'deployment', 'Synchronization');

    // Determine operations to perform.
    $this->drupalRemoteSyncDatabase = !$options['no-database'];
    $this->drupalRemoteCompressDatabase = !$options['no-compress-database'];
    $this->drupalRemoteSyncFiles = !$options['no-files'];

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
      $this->kubernetesPodParentResourceNamespace,
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
   * Synchronizes a deployed database into the local Drupal application.
   *
   * @throws \Exception
   */
  private function syncDrupalDatabaseFromRemote($dump_only = FALSE) {
    $this->io()->newLine();
    $this->io()->section("Synchronizing Drupal database from [{$this->drupalRemoteSyncPodName}]");
    $dump_file = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_DATABASE_DUMP_FILENAME;
    $gz_dump_file = $dump_file . '.gz';

    $this->say("[Remote] (optionally) Removing Previous Drupal database archive file...");
    $this->runRemoteCommand('rm -f '. $dump_file);
    $this->say("[Remote] (optionally) Removing Drupal database archive file...");
    $this->runRemoteCommand('rm -f '. $gz_dump_file);

    $this->say("[Remote] Clearing cache {$this->drupalRemoteSyncPodName}...");
    $this->runRemoteDrushCommand('cr');

    $this->say("[Remote] Dumping Drupal database from {$this->drupalRemoteSyncPodName}...");
    $this->runRemoteDrushCommand($this->getDrushDumpCommand() . ' --result-file=' . $dump_file);

    if ($this->drupalRemoteCompressDatabase) {
      $this->say("[Remote] Compressing Drupal database archive file...");
      $this->runRemoteCommand('gzip '. $dump_file);
      $copy_filename = $gz_dump_file;
    }
    else {
      $copy_filename = $dump_file;
    }

    $this->say("[Remote] Copying Drupal database file to Docker Host's temporary directory...");
    $this->copyRemoteFileToLocal($copy_filename, $copy_filename);

    $this->say("[Remote] Removing Drupal database archive file...");
    $this->runRemoteCommand('rm -f '. $copy_filename);

    if (!$dump_only) {
      $this->importDatabaseToLocalFromDumpFile($copy_filename, $this->drupalRemoteCompressDatabase);

      $this->say("[Docker Host] Deleting Drupal database archive file...");
      unlink($copy_filename);
    }
  }

  protected function importDatabaseToLocalFromDumpFile($archive_file, $is_gzip = TRUE) {
    $archive_file_basename = basename($archive_file);
    $container_db_archive_path = "/tmp/$archive_file_basename";

    $this->say("[Docker Host] Copying Drupal database archive file to local container...");
    $this->copyLocalFileToContainer($archive_file, $container_db_archive_path);

    // With no gzip, this will be the same!
    $dump_file = str_replace('.gz', '', $container_db_archive_path);

    if ($is_gzip) {
      $this->say("[Container] (optionally) Removing previous uncompressed database archive file...");
      $this->runLocalContainerCommand("rm -f $dump_file");

      $this->say("[Container] Decompressing Drupal database archive file...");
      $this->runLocalContainerCommand("gunzip $container_db_archive_path");
    }

    $this->say("[Container] Importing Drupal database archive file...");
    $this->runLocalContainerCommand("sh -c \"drush --root=/app/html sql-cli < $dump_file\"");

    $this->say("[Container] Removing Drupal database archive file...");
    $this->runLocalContainerCommand("rm -f $dump_file");

    $this->say("[Container] Clearing cache...");
    $this->runLocalDrushCommand("cr");
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
      $this->kubernetesPodParentResourceNamespace,
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
      $this->kubernetesPodParentResourceNamespace,
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

    $exclude_string = '';
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

    if (!$dump_only) {
      $this->importFilesToLocalFromDumpFile($files_dump_name);

      $this->say("[Docker Host] Deleting Drupal filesystem archive...");
      unlink($files_dump_name);
    }
  }

  protected function importFilesToLocalFromDumpFile($files_dump_name) {
    $file_archive_basename = basename($files_dump_name);
    $container_file_archive_path = "/tmp/$file_archive_basename";

    $this->say("[Docker Host] Copying Drupal filesystem archive to local container...");
    $this->copyLocalFileToContainer($files_dump_name, $container_file_archive_path);

    $this->say("[Container] Deleting container Drupal filesystem...");
    $this->runLocalContainerCommand('rm -rf ' . self::POD_FILES_SOURCE);

    $this->say("[Container] Extracting remote Drupal filesystem...");
    $this->runLocalContainerCommand("tar -xzf $container_file_archive_path --directory /");

    $this->say("[Container] Removing Drupal filesystem archive file...");
    $this->runLocalContainerCommand('rm -f ' . $container_file_archive_path);

    $this->say("[Container] Setting config sync permissions...");
    $this->runLocalContainerCommand('/scripts/pre-init.d/71_set_config_sync_permissions.sh');
    $this->say("[Container] Setting public file permissions...");
    $this->runLocalContainerCommand('/scripts/pre-init.d/71_set_public_file_permissions.sh');
    $this->say("[Container] Securing config sync dir...");
    $this->runLocalContainerCommand('/scripts/pre-init.d/72_secure_config_sync_dir.sh');
    $this->say("[Container] Securing filesystems...");
    $this->runLocalContainerCommand('/scripts/pre-init.d/72_secure_filesystems.sh');
  }

  /**
   * Cleans up and post-init tasks for sync.
   *
   * @see syncDrupalFileSystemFromRemote()
   * @throws \Dockworker\DockworkerException
   */
  protected function syncDrupalDatabaseFileSystemCleanup() {
    $this->io()->newLine();
    $this->io()->section('Cleaning Up');
    $this->say("Generating New ULI Link...");
    $this->say(
      $this->runLocalContainerCommand("/scripts/drupalUli.sh")[0]
    );
  }

}
