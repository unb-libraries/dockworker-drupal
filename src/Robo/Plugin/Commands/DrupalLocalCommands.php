<?php

namespace Dockworker\Robo\Plugin\Commands;

use Boedah\Robo\Task\Drush\loadTasks;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines the commands used to interact with a local Drupal application.
 */
class DrupalLocalCommands extends DockworkerLocalCommands {

  use loadTasks;

  /**
   * Rebuilds the cache in the local Drupal application.
   *
   * @command local:drupal:cr
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
   * Executes a drush command in the local Drupal application.
   *
   * @param string $command
   *   The command to run.
   *
   * @command local:drupal:drush
   * @aliases drush
   *
   * @throws \Dockworker\DockworkerException
   */
  private function runDrush($command) {
    $this->getLocalRunning();
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec(
        $this->taskDrushStack()
          ->drupalRootDirectory('/app/html')
          ->uri('default')
          ->drush($command)
      )
      ->run();
  }

  /**
   * Performs any required entity updates in the instance.
   *
   * @command local:drupal:entup
   * @aliases entup
   * @throws \Dockworker\DockworkerException
   */
  public function updateEntities() {
    $this->runDrush('entup');
  }

  /**
   * Generates a ULI link for the local Drupal application.
   *
   * @param string $user_name
   *   The user account name to generate the ULI for. Defaults to user 0.
   *
   * @command local:drupal:uli
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
   * Exports the configuration local Drupal application.
   *
   * @command local:drupal:write-config
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
   * Generates a list of installed composer packages for the local application.
   *
   * @command local:drupal:composer-packages
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
