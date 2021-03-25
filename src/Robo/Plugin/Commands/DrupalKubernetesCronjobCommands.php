<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerApplicationInfoCommands;
use DateInterval;

/**
 * Defines a class to write a standardized cron file to a repository.
 */
class DrupalKubernetesCronjobCommands extends DockworkerApplicationInfoCommands {

  protected $drupalCronjobSourcePath = NULL;

  /**
   * Updates the application's Drupal cronjob file.
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

    foreach(['dev', 'prod'] as $deploy_env) {
      file_put_contents(
        $this->repoRoot . "/.dockworker/deployment/k8s/$deploy_env/cron.yaml",
        str_replace('CRON_DEPLOY_ENV', $deploy_env, $cronjob_file_source)
      );
    }
  }

}
