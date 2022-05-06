<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerApplicationInfoCommands;
use DateInterval;
use Dockworker\TemporaryDirectoryTrait;

/**
 * Defines a class to write a standardized backup file to a repository.
 */
class DrupalKubernetesBackupCommands extends DockworkerApplicationInfoCommands {

  use TemporaryDirectoryTrait;

  protected $drupalBackupSourcePath;
  protected $drupalBackupDatabaseName;

  /**
   * Writes a standardized k8s deployment file defining this application's recurring database backup to this repository.
   *
   * @command backup:deployment:file:write
   * @aliases update-drupal-backup
   *
   * @usage backup:deployment:file:write
   */
  public function setApplicationBackupFiles() {
    $drupalBackupSourcePath = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/drupal-backup';
    $backup_frequencies = [
      'hourly',
      'daily',
      'weekly',
      'monthly',
    ];
    $frequency_string = implode(',', $backup_frequencies);

    $frequency = $this->askDefault("What backup frequency? ($frequency_string):", 'hourly');

    $db_guess = shell_exec('cat Dockerfile | grep DRUPAL_SITE_ID | cut -d " " -f 3 | tr -d "\n"');
    $this->drupalBackupDatabaseName = $this->askDefault("Database name?", $db_guess . '_db');
    $backup_file_source = "$drupalBackupSourcePath/$frequency.yaml";

    if (!file_exists($backup_file_source)) {
      $this->say("Invalid backup frequency. Valid frequencies: ($frequency_string)");
      return;
    }

    if (!empty($_SERVER['KUBERNETES_METADATA_REPO_PATH'])) {
      $tmp_dir = $_SERVER['KUBERNETES_METADATA_REPO_PATH'] . "/services/{$this->instanceSlug}/prod";
    }
    else {
      $tmp_dir = TemporaryDirectoryTrait::tempdir();
    }

    $this->writeTokenizedBackupFileToDir(
      $backup_file_source,
      'backup.yaml',
      $this->repoRoot . '/.dockworker/deployment/k8s/prod/'
    );

    $this->writeTokenizedBackupFileToDir(
      $backup_file_source,
      "backup-{$this->instanceSlug}.CronJob.prod.yaml",
      $tmp_dir
    );
    $this->writeTokenizedBackupFileToDir(
      "$drupalBackupSourcePath/volume.yaml",
      "backup-{$this->instanceSlug}.PersistentVolume.prod.yaml",
      $tmp_dir
    );
    $this->writeTokenizedBackupFileToDir(
      "$drupalBackupSourcePath/volumeclaim.yaml",
      "backup-{$this->instanceSlug}.PersistentVolumeClaim.prod.yaml",
      $tmp_dir
    );
    $this->say("Backup files have been updated in lean repo and written to $tmp_dir");
  }

  private function writeTokenizedBackupFileToDir($tokenized_file, $filename, $path) {
    $file_contents = file_get_contents($tokenized_file);
    $file_contents = str_replace('INSTANCE_SLUG', $this->instanceSlug, $file_contents);
    $file_contents = str_replace('INSTANCE_DB_NAME', $this->drupalBackupDatabaseName, $file_contents);
    $file_contents = str_replace('HOURLY_CRON_TIMINGS', $this->getHourlyCycleCronString(3), $file_contents);
    $file_contents = str_replace('DAILY_CRON_TIMINGS', $this->getApplicationDailyCronString(), $file_contents);
    $file_contents = str_replace('WEEKLY_CRON_TIMINGS', $this->getApplicationWeeklyCronString(), $file_contents);
    $file_contents = str_replace('MONTHLY_CRON_TIMINGS', $this->getApplicationMonthlyCronString(), $file_contents);
    file_put_contents(
      "$path/$filename",
      $file_contents
    );
  }

}
