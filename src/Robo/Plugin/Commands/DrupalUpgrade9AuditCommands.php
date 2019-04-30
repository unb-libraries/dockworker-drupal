<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;

/**
 * Defines commands to audit custom Drupal code for a 9 upgrade.
 */
class DrupalUpgrade9AuditCommands extends DockworkerApplicationCommands {

  /**
   * Audit all code in modules/themes in anticipation of a Drupal 9 upgrade.
   *
   * @param string $module_theme_root
   *   The relative path to the module/theme root.
   *
   * @command drupal:audit:9-upgrade
   *
   * @link https://github.com/JacobSanford/docker-drupal-check
   */
  public function auditCode($module_theme_root = '/custom') {
    return $this->taskDockerRun('jacobsanford/drupal-check:latest')
      ->volume($this->repoRoot . $module_theme_root, '/drupal/web/modules/custom')
      ->exec("/drupal/web/modules/custom")
      ->run();
  }

}
