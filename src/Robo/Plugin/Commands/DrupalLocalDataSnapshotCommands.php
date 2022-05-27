<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\ItemListSelectorTrait;
use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DrupalSyncCommands;
use Robo\Symfony\ConsoleIO;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines commands used to snapshot the state of a local Drupal application.
 */
class DrupalLocalDataSnapshotCommands extends DrupalSyncCommands {

  use ItemListSelectorTrait;
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
   * Restores a previously written local content 'snapshot' to this application's local deployment.
   *
   * @command snapshot:restore
   * @aliases restore-snapshot
   *
   * @throws \Dockworker\DockworkerException
   */
  public function restoreLocalDrupalContentSnapshot(ConsoleIO $io) {
    $this->getLocalRunning();
    $this->setLocalDrupalSnapshotDir();
    $this->populateAvailableDrupalSnapshots();
    if (!empty($this->drupalLocalAvailableSnapshots)) {
      $snapshot_path = $this->selectValueFromTable(
          $io,
          $this->drupalLocalAvailableSnapshots,
          'path',
          "Available $this->instanceName Snapshots:",
          "Which snapshot would you like to restore from?",
          ['Snapshot Date', 'Label', 'Info', 'DB Size', 'Files Size', 'Path'],
      );
      if (!empty($snapshot_path)) {
        if (
          $this->confirm(
            sprintf(
              "Warning! Are you sure you want to restore the [%s] snapshot? This WILL delete all content in your %s local instance.",
              $snapshot_path,
              $this->instanceName
            )
          )
        ) {
          $this->drupalCurLocalSnapshotDir = $snapshot_path;
          $this->restoreSelectedSnapshotToLocal();
          $this->syncDrupalDatabaseFileSystemCleanup();
        }
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
      unset($yaml_data['instance']);
      $this->drupalLocalAvailableSnapshots[] = $yaml_data;
    };
  }

  /**
   * Writes a content 'snapshot' of this application's local deployment into this local development system's persistent storage.
   *
   * @command snapshot:write
   * @aliases snapshot
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

  /**
   * Determines the human readable size of a file.
   *
   * @param string $path
   *   The path to the file.
   * @param $decimals
   *   The number of decimals to display.
   *
   * @return string
   *   The human readable file size.
   */
  private function getHumanFileSize($path, $decimals = 2) {
    $bytes = filesize($path);
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[(int)$factor];
  }

}
