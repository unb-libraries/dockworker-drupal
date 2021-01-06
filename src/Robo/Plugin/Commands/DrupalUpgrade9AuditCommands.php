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
   * @command validate:drupal:9-upgrade
   * @link https://github.com/JacobSanford/docker-drupal-check
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  public function auditCode() {
    $cmd = $this->taskDockerRun(self::AUDIT_DOCKER_IMAGE)
      ->volume($this->repoRoot . '/build/composer.json', '/drupal/composer.json');

    $custom_modules_path = "$this->repoRoot/custom/modules";
    $custom_themes_path = "$this->repoRoot/custom/themes";

    $custom_modules_directories = glob("$custom_modules_path/*" , GLOB_ONLYDIR);
    if (!empty($custom_modules_directories)) {
      $cmd->volume($this->repoRoot . '/custom/modules', '/modules');
    }

    $custom_themes_directories = glob("$custom_themes_path/*" , GLOB_ONLYDIR);
    if (!empty($custom_themes_directories)) {
      $cmd->volume($this->repoRoot . '/custom/themes', '/themes');
    }

    return $cmd->run();
  }

}
