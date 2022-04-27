<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DrupalSyncCommands;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines commands used to snapshot the state of a local Drupal application.
 */
class DrupalLocalDataSnapshotCommands extends DrupalSyncCommands {

  use RecursivePathFileOperatorTrait;

  const LOCAL_SNAPSHOT_BASE_DIR = 'snapshots';
  const LOCAL_SNAPSHOT_DATABASE_FILENAME = 'db.sql.gz';
  const LOCAL_SNAPSHOT_FILES_FILENAME = 'files.tar.gz';
  const LOCAL_SNAPSHOT_METADATA_FILENAME = 'metadata.yaml';

  /**
   * Provides the local snapshot base dir.
   *
   * @var string
   */
  private string $drupalLocalSnapshotDir;

  /**
   * The available snapshots for restore.
   *
   * @var string[]
   */
  private array $drupalLocalAvailableSnapshots;

  /**
   * The local snapshot dir for this command run.
   *
   * @var string
   */
  private string $drupalCurLocalSnapshotDir;

  /**
   * Restores content from a local snapshot into the running container.
   *
   * @command local:content:snapshot:restore
   *
   * @throws \Dockworker\DockworkerException
   */
  public function restoreLocalDrupalContentSnapshot() {
    $this->getLocalRunning();
    $this->setLocalDrupalSnapshotDir();
    $this->populateAvailableDrupalSnapshots();
    if (!empty($this->drupalLocalAvailableSnapshots)) {
      $this->listAvailableDrupalSnapshots();
      $desired_restore_id = $this->ask("Which snapshot would you like to restore? (enter c to cancel)");
      if (empty($desired_restore_id) || $desired_restore_id == 'c') {
        $this->io()->note('No snapshot chosen for restore. No changes made.');
        return 0;
      }
      // This nonsense is to avoid PHP typing a 0 response as empty.
      $modified_restore_id = $desired_restore_id - 1;
      if (empty($this->drupalLocalAvailableSnapshots[$modified_restore_id]['path'])) {
        $this->io()->warning("Invalid ID ($desired_restore_id) chosen for restore. No changes made.");
        return 0;
      }
      if ($this->confirm("Warning! Are you sure you want to restore snapshot $desired_restore_id? This will delete all local content in your instance.")) {
        $this->drupalCurLocalSnapshotDir = $this->drupalLocalAvailableSnapshots[$modified_restore_id]['path'];
        $this->restoreSelectedSnapshotToLocal();
        $this->syncDrupalDatabaseFileSystemCleanup();
      }
    }
    else {
      $this->io()->note('No local snapshots found for this instance!');
      return 0;
    }
  }

  /**
   * Restores the currently selected snapshot to the local instance.
   *
   * @return void
   */
  protected function restoreSelectedSnapshotToLocal() {
    $this->io()->title("Restoring snapshot from $this->drupalCurLocalSnapshotDir");
    $this->importDatabaseToLocalFromDumpFile(
      implode(
        '/',
        [
          $this->drupalCurLocalSnapshotDir,
          self::LOCAL_SNAPSHOT_DATABASE_FILENAME,
        ]
      )
    );
    $this->importFilesToLocalFromDumpFile(
      implode(
        '/',
        [
          $this->drupalCurLocalSnapshotDir,
          self::LOCAL_SNAPSHOT_FILES_FILENAME,
        ]
      )
    );
  }

  /**
   * Lists the snapshots that are available for restore to the local container.
   *
   * @return void
   */
  protected function listAvailableDrupalSnapshots() {
    $this->io()->title("[$this->instanceName] Available Snapshots:");
    $table = new Table($this->io());
    $table
      ->setHeaders(['ID', 'Snapshot Date', 'Label', 'Info', 'DB Size', 'Files Size', 'Path'])
      ->setRows($this->drupalLocalAvailableSnapshots);
    $table->render();
  }

  /**
   * Populates the list of available Drupal snapshots.
   *
   * @return void
   */
  protected function populateAvailableDrupalSnapshots() {
    $this->drupalLocalAvailableSnapshots = [];
    $this->addRecursivePathFilesFromPath(
      [$this->drupalLocalSnapshotDir],
      ['yaml']
    );
    foreach ($this->getRecursivePathFiles() as $snapshot_id => $snapshot_metadata_file) {
      $snapshot_dir = dirname($snapshot_metadata_file);
      $yaml_data = Yaml::parse(file_get_contents($snapshot_metadata_file));
      $yaml_data['db_size'] = $this->getHumanFileSize(
        implode('/', [$snapshot_dir, self::LOCAL_SNAPSHOT_DATABASE_FILENAME]),
        1
      );
      $yaml_data['file_size'] = $this->getHumanFileSize(
        implode('/', [$snapshot_dir, self::LOCAL_SNAPSHOT_FILES_FILENAME]),
        1
      );
      $yaml_data['path'] = $snapshot_dir;
      $yaml_data['timestamp'] = date("F j, Y, g:i a", $yaml_data['timestamp']);
      $yaml_data_id['id'] = $snapshot_id + 1;
      unset($yaml_data['instance']);
      $yaml_data = array_merge($yaml_data_id, $yaml_data);
      $this->drupalLocalAvailableSnapshots[] = $yaml_data;
    };
  }

  /**
   * Snapshots the local instance content into file archive(s).
   *
   * @command local:content:snapshot:write
   *
   * @throws \Dockworker\DockworkerException
   */
  public function snapshotLocalDrupalContent() {
    $this->getLocalRunning();
    $this->setLocalDrupalSnapshotDir();
    $this->setCreateDrupalCurSnapshotDir();
    $this->dumpDrupalDatabaseFileSystemFromLocal();
    $this->copyTemporaryDumpFilesToSnapshotDir();
    $this->io()->newLine();
    $this->writeCurSnapshotMetadataFile();
    $this->io()->newLine();
    $this->io()->note("Local snapshot written to $this->drupalCurLocalSnapshotDir");
  }

  /**
   * Copies the temporary files (from upstream methods) to the snapshot dir.
   *
   * @return void
   */
  protected function copyTemporaryDumpFilesToSnapshotDir() {
    $files_dump_name = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_FILES_DUMP_FILENAME;
    $db_dump_name = self::POD_TEMPORARY_FILE_LOCATION . '/' . self::POD_DATABASE_DUMP_COMPRESSED_FILENAME;
    copy(
      $files_dump_name,
      implode(
        '/',
        [
          $this->drupalCurLocalSnapshotDir,
          self::LOCAL_SNAPSHOT_FILES_FILENAME
        ]
      )
    );
    copy(
      $db_dump_name,
      implode(
        '/',
        [
          $this->drupalCurLocalSnapshotDir,
          self::LOCAL_SNAPSHOT_DATABASE_FILENAME
        ]
      )
    );
  }

  /**
   * Sets up the local snapshot dir for this instance.
   *
   * @return void
   */
  public function setLocalDrupalSnapshotDir() {
    $this->drupalLocalSnapshotDir = implode('/', [$this->dockworkerApplicationDataDir, self::LOCAL_SNAPSHOT_BASE_DIR]);
  }

  /**
   * Creates the local snapshot dir for this specific command run.
   *
   * @return void
   */
  protected function setCreateDrupalCurSnapshotDir() {
    $this->drupalCurLocalSnapshotDir = implode('/', [$this->drupalLocalSnapshotDir, $this->commandStartTime]);
    if (!file_exists($this->drupalCurLocalSnapshotDir)) {
      mkdir($this->drupalCurLocalSnapshotDir, 0755, TRUE);
    }
  }

  /**
   * Writes the snapshot metadata file.
   *
   * @return void
   */
  protected function writeCurSnapshotMetadataFile() {
    $this->writeSnapshotMetadataFile($this->drupalCurLocalSnapshotDir);
  }

  /**
   * Writes the snapshot metadata file.
   *
   * @param $snapshot_path
   *
   * @return void
   */
  protected function writeSnapshotMetadataFile($snapshot_path) {
    file_put_contents(
      implode(
        '/',
        [
          $snapshot_path,
          self::LOCAL_SNAPSHOT_METADATA_FILENAME
        ]
      ),
      Yaml::dump(
        [
          'instance' => $this->instanceName,
          'timestamp' => $this->commandStartTime,
          'label' => $this->ask('Data written. Please enter a label for the snapshot:'),
          'info' => trim($this->getLocalDrupalVersionString()),
        ]
      )
    );
  }

  private function getHumanFileSize($path, $decimals = 2) {
    $bytes = filesize($path);
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[(int)$factor];
  }

}
