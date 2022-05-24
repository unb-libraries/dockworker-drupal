<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalLocalDockerContainerTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines the commands used to interact with a local Drupal application.
 */
class DrupalLocalCommands extends DockworkerLocalCommands {

  use DrupalLocalDockerContainerTrait;

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
   * Prepares a local database/filesystem before rebuilding.
   *
   * Local development startup involves several post-configuration-import
   * changes that conflict with a clean container restart. This hook reverts
   * those as necessary.
   *
   * @hook pre-command local:rebuild
   */
  public function prepareDrupalForRestart() {
    $this->io()->title('Reverting local development settings');
    $this->revertDevelSettings();
  }

  /**
   * Reverts devel-related settings necessary for a clean container restart.
   */
  protected function revertDevelSettings() {
    $this->setInstanceName();
    $this->say('Removing devel-related configuration...');
    $this->runLocalDrushCommand('pmu devel');
    $this->rebuildCache();
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
    $this->io()->title('Exporting Local Drupal Configuration');
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/configExport.sh')
      ->run();
    $this->setRunOtherCommand('dockworker:permissions:fix');
    $this->say('Done!');
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
        'Config language.entity.en does not exist' => 'Language entity may not exist before config import',
        'Operation CREATE USER failed' => 'Creating a local user failing is expected in deployment',
        '[notice] Synchronized extensions' => 'Modules that have "error" in their names are not errors',
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
