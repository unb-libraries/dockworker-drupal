<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines the commands used to interact with a local Drupal application.
 */
class DrupalLocalCommands extends DockworkerLocalCommands {

  /**
   * Rebuilds all caches within this application's local deployment.
   *
   * @command drupal:cr:local
   * @aliases cr
   */
  public function rebuildCache() {
    $this->getLocalRunning();
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec(
        '/scripts/clearDrupalCache.sh'
      )
      ->run();
  }

  /**
   * Generates a clickable URL to this application's local deployment Drupal admin panel.
   *
   * @param string $user_name
   *   The user account name to generate the ULI for. Defaults to user 0.
   *
   * @command drupal:uli:local
   * @aliases uli
   * @throws \Dockworker\DockworkerException
   */
  public function uli($user_name = NULL) {
    $this->getLocalRunning();
    if (empty($user_name)) {
      $this->taskDockerExec($this->instanceName)
        ->interactive()
        ->exec(
          '/scripts/drupalUli.sh'
        )
        ->run();
    }
    else {
      $this->taskDockerExec($this->instanceName)
        ->interactive()
        ->exec(
          "/scripts/drupalUli.sh '$user_name'"
        )
        ->run();
    }
  }

  /**
   * Exports Drupal config from this application's local deployment and writes it to this repository.
   *
   * @command drupal:config:write:local
   * @aliases write-config
   * @throws \Dockworker\DockworkerException
   */
  public function writeConfig() {
    $this->getLocalRunning();
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/configExport.sh')
      ->run();
    $this->setRunOtherCommand('dockworker:permissions:fix');
  }

  /**
   * Provides log checker with ignored log exception items for local Drupal.
   *
   * @hook on-event dockworker-local-log-error-triggers
   */
  public function getErrorLogTriggers() {
    return [
      'SQLSTATE',
    ];
  }

  /**
   * Provides log checker with ignored log exception items for local Drupal.
   *
   * @hook on-event dockworker-local-log-error-exceptions
   */
  public function getErrorLogExceptions() {
    return [
        '[notice] Synchronized extensions' => 'Modules that have "error" in their names are not errors',
        'Config language.entity.en does not exist' => 'Language entity may not exist before config import',
        'error_level' => 'Drupal console development mode reports are not errors',
    ];
  }

  /**
   * Displays all composer packages installed within this application's local deployment.
   *
   * @command composer:list:local
   * @aliases ldcp
   * @throws \Dockworker\DockworkerException
   */
  public function getInstalledLocalComposerPackages() {
    $this->getLocalRunning();
    $this->io()->title('[local] Installed Composer Packages');
    $this->taskDockerExec($this->instanceName)
      ->exec(
        'composer show --working-dir=/app/html'
      )
      ->run();
  }

}
