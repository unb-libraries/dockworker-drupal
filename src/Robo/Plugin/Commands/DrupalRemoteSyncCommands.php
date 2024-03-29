<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalDrushSqlDumpTrait;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\DrupalLocalDockerContainerTrait;
use Dockworker\DockworkerDrupalProjectsTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines commands used to sync deployed data to another deployed environment.
 */
class DrupalRemoteSyncCommands extends DockworkerDeploymentCommands {

  use DrupalKubernetesPodTrait;
  use DrupalLocalDockerContainerTrait;
  use DrupalDrushSqlDumpTrait;
  use DockworkerDrupalProjectsTrait;

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
   * The environment to sync from.
   *
   * @var string
   */
  private $drupalRemoteSyncSourceEnv;

  /**
   * The pod name to sync from.
   *
   * @var string
   */
  private $drupalRemoteSyncSourcePod;

  /**
   * The environment to sync to.
   *
   * @var string
   */
  private $drupalRemoteSyncTargetEnv;

  /**
   * The pod name to sync to.
   *
   * @var string
   */
  private $drupalRemoteSyncTargetPod;

  /**
   * Synchronizes all Drupal data within this application's k8s deployment from one environment to another.
   *
   * @param string $source_env
   *   The deploy environment to synchronize from.
   * @param string $target_env
   *   The deploy environment to synchronize to.
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
   * @command sync:all:deployed:deployed
   *
   * @throws \Dockworker\DockworkerException
   *
   * @usage prod dev
   *
   * @github
   * @kubectl
   */
  public function syncDrupalDatabaseFileSystemFromRemote($source_env, $target_env, array $options = ['no-compress-database' => FALSE, 'no-database' => FALSE, 'no-files' => FALSE]) {
    $this->initSyncPods($source_env, $target_env);
    $this->io()->title("Synchronizing deployed data : {$this->drupalRemoteSyncSourceEnv}[{$this->drupalRemoteSyncSourcePod}] -> {$this->drupalRemoteSyncTargetEnv}[{$this->drupalRemoteSyncTargetPod}]");
    $this->initSyncOperations($options);
    $this->checkDangerousOperation();
    $this->drupalRemoteCompressDatabase = !$options['no-compress-database'];

    if ($this->drupalRemoteSyncDatabase) {
      $this->syncDrupalRemoteDrupalDatabases();
    }

    if ($this->drupalRemoteSyncFiles) {
      $this->syncDrupalRemoteDrupalFilesystems();
    }

    if ($this->drupalRemoteSyncDatabase || $this->drupalRemoteSyncFiles) {
      $this->syncDrupalDatabaseFileSystemCleanup();
    }
  }

  /**
   * Initializes the parameters required to synchronize data between pods.
   *
   * @param string $source_env
   *   The source environment to copy the data from.
   * @param $target_env
   *   The target environment to copy the data to.
   *
   * @throws \Exception
   */
  private function initSyncPods($source_env, $target_env) {
    $this->drupalRemoteSyncSourceEnv = $source_env;
    $this->drupalRemoteSyncTargetEnv = $target_env;

    $this->kubernetesSetupPods($this->instanceSlug, 'deployment', $this->drupalRemoteSyncSourceEnv, "Synchronization");
    $this->drupalRemoteSyncSourcePod = $this->kubernetesCurPods[0];
    $this->kubernetesSetupPods($this->instanceSlug, 'deployment', $this->drupalRemoteSyncTargetEnv, "Synchronization");
    $this->drupalRemoteSyncTargetPod = $this->kubernetesCurPods[0];
  }

  /**
   * Initializes the operations to perform during the synchronization.
   *
   * @param string[] $options
   *   An array of options passed to the original command.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function initSyncOperations($options) {
    // Determine operations to perform.
    $this->drupalRemoteSyncDatabase = !$options['no-database'];
    $this->drupalRemoteSyncFiles = !$options['no-files'];

    // Dump out no-op users.
    if (!$this->drupalRemoteSyncDatabase && !$this->drupalRemoteSyncFiles) {
      throw new DockworkerException("--no-database and --no-files both specified. Nothing to do!");
    }

    // Check Drupal versions.
    $this->checkPodDrush($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv);
    $this->checkPodDrush($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv);
    $this->compareSourceTargetDrupalVersions();
  }

  /**
   * Checks if the remote kubernetes pod responds to drush commands.
   *
   * @param string $pod_name
   *   The pod to check drush in.
   * @param string $namespace
   *   The namespace to check drush in.
   *
   * @throws \Exception
   */
  private function checkPodDrush($pod_name, $namespace) {
    $output = $this->runRemoteDrushCommand($pod_name, $namespace, 'status');
    $this->checkDrushCommandOutput($output);
  }

  /**
   * Runs a drush command in a remote kubernetes pod.
   *
   * @param string $pod_name
   *   The pod to run the command in.
   * @param string $namespace
   *   The namespace to run the command in.
   * @param string $command
   *   The drush command to run.
   *
   * @throws \Exception
   *
   * @return mixed
   *   The command result.
   */
  private function runRemoteDrushCommand($pod_name, $namespace, $command) {
    return $this->kubernetesPodDrushCommand(
      $pod_name,
      $namespace,
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
   * Compares the source and target deployed drupal versions.
   *
   * @throws \Dockworker\DockworkerException
   * @throws \Exception
   */
  private function compareSourceTargetDrupalVersions() {
    $source_output= $this->runRemoteDrushCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'status');
    $target_output = $this->runRemoteDrushCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, 'status');
    if ($source_output[0] != $target_output[0]) {
      $this->say(
        sprintf(
          'The target instance [%s] Drupal version [%s] does not match the source instance [%s] Drupal version [%s].',
          $this->drupalRemoteSyncTargetEnv,
          $target_output[0],
          $this->drupalRemoteSyncSourceEnv,
          $source_output[0]
        )
      );
      $this->say('To sync, the remote instances must be running the same version of Drupal Core');
      throw new DockworkerException("Source-Target Drupal version mismatch.");
    }
  }

  /**
   * Checks if any potentially dangerous operations are queued, and warns user.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function checkDangerousOperation() {
    $warn = FALSE;
    if ($this->drupalRemoteSyncTargetEnv == 'prod') {
      $this->io()->warning('You are about to synchronize ALL content from another environment to prod. This is destructive and likely NOT what you want to do!');
      $warn = TRUE;
    }

    if ($warn == TRUE && !$this->confirm('Do you want to continue anyhow?')) {
      throw new DockworkerException("User cancelled dangerous operation.");
    }
  }

  /**
   * Synchronizes a deployed database into the target environment.
   *
   * @throws \Exception
   */
  private function syncDrupalRemoteDrupalDatabases() {
    $this->io()->newLine();
    $this->io()->section("Synchronizing Drupal database");
    $dump_file = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_DATABASE_DUMP_FILENAME;
    $gz_dump_file = $dump_file . '.gz';

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] (optionally) Removing Drupal database file...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'rm -f '. $dump_file);
    $this->say("[{$this->drupalRemoteSyncSourceEnv}] (optionally) Removing Drupal database archive file...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'rm -f '. $gz_dump_file);

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Clearing cache...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, '/scripts/clearDrupalCache.sh');

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Dumping Drupal database...");
    $this->runRemoteDrushCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, $this->getDrushDumpCommand() . ' --result-file=' . $dump_file);

    if ($this->drupalRemoteCompressDatabase) {
      $this->say("[{$this->drupalRemoteSyncSourceEnv}] Compressing Drupal database archive file...");
      $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'gzip '. $dump_file);
      $transfer_file = $gz_dump_file;
    }
    else {
      $transfer_file = $dump_file;
    }

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Copying Drupal database archive file to a LOCAL temporary directory... Wish you could copy directly between pods? See https://github.com/kubernetes/kubectl/issues/551?");
    $this->copyRemoteFileToLocal($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, $transfer_file, $transfer_file);

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Removing Drupal database archive file...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'rm -f '. $transfer_file);

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Copying Drupal database archive file from LOCAL to pod...");
    $this->copyLocalFileToRemote($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, $transfer_file, $transfer_file);

    $this->say("[LOCAL] Deleting Drupal database archive file...");
    unlink($transfer_file);

    if ($this->drupalRemoteCompressDatabase) {
      $this->say("[{$this->drupalRemoteSyncTargetEnv}] (optionally) Removing Drupal database archive file...");
      $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, "rm -f $dump_file");

      $this->say("[{$this->drupalRemoteSyncTargetEnv}] Decompressing Drupal database archive file...");
      $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, "gunzip $gz_dump_file");
    }

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Importing Drupal database archive file...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, "sh -c \"drush --root=/app/html sql-cli < $dump_file\"");

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Removing Drupal database archive file...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, "rm -f $dump_file");

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Clearing cache...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, '/scripts/clearDrupalCache.sh');
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
  private function runRemoteCommand($pod_id, $namespace, $command) {
    return $this->kubernetesPodExecCommand(
      $pod_id,
      $namespace,
      $command
    );
  }

  /**
   * Copies a remote file to the local (host) filesystem.
   *
   * @param string $pod_id
   *   The pod to copy the file from.
   * @param string $target_env
   *   The env to copy the file from.
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
  private function copyRemoteFileToLocal($pod_id, $namespace, $remote_filename, $local_filename) {
    return $this->kubernetesPodFileCopyCommand(
      $namespace,
      $pod_id . ':' . $remote_filename,
      $local_filename
    );
  }

  /**
   * Copies a local file to a remote filesystem.
   *
   * @param string $pod_id
   *   The pod to copy the file to.
   * @param string $target_env
   *   The env to copy the file to.
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
  private function copyLocalFileToRemote($pod_id, $namespace, $remote_filename, $local_filename) {
    return $this->kubernetesPodFileCopyCommand(
      $namespace,
      $local_filename,
      $pod_id . ':' . $remote_filename
    );
  }

  /**
   * Synchronizes deployed Drupal filesystem into the target environment.
   *
   * @throws \Exception
   */
  private function syncDrupalRemoteDrupalFilesystems() {
    $this->io()->newLine();
    $this->io()->section("Synchronizing Drupal filesystem");
    $files_dump_name = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_FILES_DUMP_FILENAME;

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] (optionally) Removing Drupal filesystem archive...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'rm -f '. $files_dump_name);

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Creating Drupal filesystem archive...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, "tar -cvpzf  $files_dump_name " . self::POD_FILES_SOURCE);

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Copying Drupal filesystem archive to LOCAL temporary directory... Wish you could copy directly between pods? See https://github.com/kubernetes/kubectl/issues/551?");
    $this->copyRemoteFileToLocal($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, $files_dump_name, $files_dump_name);

    $this->say("[{$this->drupalRemoteSyncSourceEnv}] Removing Drupal filesystem archive file...");
    $this->runRemoteCommand($this->drupalRemoteSyncSourcePod, $this->drupalRemoteSyncSourceEnv, 'rm -f '. $files_dump_name);

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Copying Drupal filesystem archive file from LOCAL to pod...");
    $this->copyLocalFileToRemote($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, $files_dump_name, $files_dump_name);

    $this->say("[LOCAL] Deleting Drupal filesystem archive...");
    unlink($files_dump_name);

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Deleting container Drupal filesystem...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, 'rm -rf ' . self::POD_FILES_SOURCE);

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Extracting remote Drupal filesystem...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, "tar -xzf $files_dump_name --directory /");

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Removing Drupal filesystem archive file...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, 'rm -f '. $files_dump_name);

    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Setting config sync permissions...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, '/scripts/setConfigDirPermissions.sh');
    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Setting public file permissions...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, '/scripts/pre-init.d/71_set_public_file_permissions.sh');
    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Securing config sync dir...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, '/scripts/pre-init.d/72_secure_config_sync_dir.sh');
    $this->say("[{$this->drupalRemoteSyncTargetEnv}] Securing filesystems...");
    $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, '/scripts/pre-init.d/72_secure_filesystems.sh');
  }

  /**
   * Cleans up and performs post-init tasks for the synchronization.
   *
   * @see syncDrupalFileSystemFromRemote()
   * @throws \Dockworker\DockworkerException
   */
  private function syncDrupalDatabaseFileSystemCleanup() {
    $this->io()->newLine();
    $this->io()->section('Cleaning Up');
    if ($this->drupalRemoteSyncTargetEnv == 'dev' && $this->getDrupalHasEnabledModule('samlauth', $this->repoRoot)) {
      $this->say("Resetting SAML Entity ID...");
      $this->runRemoteCommand($this->drupalRemoteSyncTargetPod, $this->drupalRemoteSyncTargetEnv, '/scripts/pre-init.d/80_saml_entity_id.sh');
    }
    $this->say("Generating New ULI Link...");
    $this->setRunOtherCommand("drupal:uli:deployed {$this->drupalRemoteSyncTargetEnv}");
  }

}
