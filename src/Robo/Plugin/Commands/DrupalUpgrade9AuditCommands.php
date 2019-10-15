<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines commands to audit local Drupal application code for the 9 upgrade.
 */
class DrupalUpgrade9AuditCommands extends DockworkerLocalCommands {

  const AUDIT_DOCKER_IMAGE = 'jacobsanford/drupal-check:latest';

  /**
   * Audits all code in modules/themes against Drupal 9 standards.
   *
   * @param string $module_theme_root
   *   The relative path to the module/theme root.
   *
   * @command validate:drupal:9-upgrade
   * @link https://github.com/JacobSanford/docker-drupal-check
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  public function auditCode($module_theme_root = '/custom') {
    return $this->taskDockerRun(self::AUDIT_DOCKER_IMAGE)
      ->volume($this->repoRoot . $module_theme_root, '/drupal/web/modules/custom')
      ->exec("/drupal/web/modules/custom")
      ->run();
  }

}
