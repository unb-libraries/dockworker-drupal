<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerApplicationInfoCommands;
use DateInterval;
use Dockworker\TemporaryDirectoryTrait;

/**
 * Defines a class to write a standardized cron file to a repository.
 */
class DrupalKubernetesCronjobCommands extends DockworkerApplicationInfoCommands {

  use TemporaryDirectoryTrait;

  protected $drupalCronjobSourcePath;

  /**
   * Writes a standardized k8s deployment file defining this application's recurring cronjob to this repository.
   *
   * @command drupal:cronjob:update
   * @aliases update-drupal-cronjob
   *
   * @usage drupal:cronjob:update
   */
  public function setApplicationDrupalCronFiles() {
    $drupalCronjobSourcePath = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/drupal-cron/cron.yaml';

    $cronjob_file_source = file_get_contents($drupalCronjobSourcePath);
    $cronjob_file_source = str_replace('INSTANCE_SLUG', $this->instanceSlug, $cronjob_file_source);
    $cronjob_file_source = str_replace('CRON_TIMINGS', $this->getApplicationFifteenCronString(), $cronjob_file_source);

    if (!empty($_SERVER['KUBERNETES_METADATA_REPO_PATH'])) {
      $tmp_dir = $_SERVER['KUBERNETES_METADATA_REPO_PATH'] . "/services/{$this->instanceSlug}";
    }
    else {
      $tmp_dir = TemporaryDirectoryTrait::tempdir();
    }

    foreach(['dev', 'prod'] as $deploy_env) {
      file_put_contents(
        $this->repoRoot . "/.dockworker/deployment/k8s/$deploy_env/cron.yaml",
        str_replace(['CRON_DEPLOY_ENV', 'DEPLOY_IMAGE'], [$deploy_env, '||DEPLOYMENTIMAGE||'], $cronjob_file_source)
      );

      $filename = "{$this->instanceSlug}.CronJob.$deploy_env.yaml";
      if (!empty($_SERVER['KUBERNETES_METADATA_REPO_PATH'])) {
        $tmp_file = "$tmp_dir/$deploy_env/$filename";
      }
      else {
        $tmp_file = "$tmp_dir/$filename";
      }
      file_put_contents(
        $tmp_file,
        str_replace(['CRON_DEPLOY_ENV', 'DEPLOY_IMAGE'], [$deploy_env, "ghcr.io/unb-libraries/{$this->instanceName}:$deploy_env"], $cronjob_file_source)
      );
    }
    $this->say("Cron files have been updated in lean repo and written to $tmp_dir");
  }

}
