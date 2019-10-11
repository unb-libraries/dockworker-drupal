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
   * @command drupal:cr
   * @aliases cr
   */
  public function resetCache() {
    $this->runDrush('cr');
  }

  /**
   * Executes a drush command in the local Drupal application.
   *
   * @param string $command
   *   The command to run.
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
   * @command drupal:entup
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
   * @command drupal:uli
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
   * @command drupal:write-config
   * @aliases write-config
   * @throws \Dockworker\DockworkerException
   */
  public function writeConfig() {
    $this->getLocalRunning();
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/configExport.sh')
      ->run();
    $this->setRunOtherCommand('permissions:fix');
  }

  /**
   * Self-updates dockworker.
   *
   * @hook post-command dockworker:update
   */
  public function getDockworkerDrupalUpdates() {
    $this->say('Checking for updates to unb-libraries/dockworker-drupal...');
    $this->taskExec('composer')
      ->dir($this->repoRoot)
      ->arg('update')
      ->arg('unb-libraries/dockworker-drupal')
      ->silent(TRUE)
      ->run();
  }

  /**
   * Checks the local Drupal application logs for errors.
   *
   * @param string[] $opts
   *   An array of options to pass to the builder.
   *
   * @hook replace-command local:logs:check
   * @throws \Dockworker\DockworkerException
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  public function localDrupalLogsCheck(array $opts = ['all' => FALSE]) {
    $exceptions = [
      '[notice] Synchronized extensions' => 'Modules that have "error" in their names are not errors',
    ];
    $this->logErrorExceptions = array_merge($this->logErrorExceptions, $exceptions);
    return parent::localLogsCheck($opts);
  }

  /**
   * Builds the local Drupal application from scratch and runs all tests.
   *
   * @hook replace-command local:build-test
   * @throws \Dockworker\DockworkerException
   */
  public function buildAndTestDrupal() {
    $this->_exec('docker-compose kill');
    $this->setRunOtherCommand('local:rm');
    $this->setRunOtherCommand('local:start --no-cache --no-tail-logs');
    $this->setRunOtherCommand('test:all');
  }

}
