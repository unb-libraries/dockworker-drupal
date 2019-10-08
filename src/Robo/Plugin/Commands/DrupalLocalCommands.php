<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines core Drupal instance operations.
 */
class DrupalLocalCommands extends DockworkerLocalCommands {

  use \Boedah\Robo\Task\Drush\loadTasks;

  /**
   * Rebuild the cache in the Drupal container.
   *
   * @command drupal:cr
   * @aliases cr
   */
  public function resetCache() {
    $this->runDrush('cr');
  }

  /**
   * Run a drush command in the Drupal container.
   *
   * @param string $command
   *   The command to run.
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
   * Perform any required entity updates in the instance.
   *
   * @command drupal:entup
   * @aliases entup
   */
  public function updateEntities() {
    $this->runDrush('entup');
  }

  /**
   * Get a ULI from the Drupal container.
   *
   * @param string $user_name
   *   The user account name to generate the ULI for. Defaults to user 0.
   *
   * @command drupal:uli
   * @aliases uli
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
   * Write out the configuration from the instance.
   *
   * @command drupal:write-config
   * @aliases write-config
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
   * Self-update.
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
   * Check the local instance logs for errors.
   *
   * @param array $opts
   *   An array of options to pass to the builder.
   *
   * @hook replace-command local:logs:check
   * @throws \Exception
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
   * Build the instance from scratch and run tests.
   *
   * @hook replace-command local:build-test
   * @throws \Exception
   */
  public function buildAndTestDrupal() {
    $this->_exec('docker-compose kill');
    $this->setRunOtherCommand('local:rm');
    $this->setRunOtherCommand('local:start --no-cache --no-tail-logs');
    $this->setRunOtherCommand('test:all');
  }

}
