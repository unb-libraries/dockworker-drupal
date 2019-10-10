<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class DrupalDeploymentCommands extends DockworkerDeploymentCommands {

  /**
   * Checks the remote deployment logs for errors.
   *
   * @param string $env
   *   The deploy environment to check.
   *
   * @hook replace-command deployment:logs:check
   * @throws \Exception
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  public function checkDrupalDeploymentLogs($env) {
    $exceptions = [
      '[notice] Synchronized extensions' => 'Ignore installation of modules that have "error" in their names',
      'config_importer is already importing' => 'Ignore errors when only one pod imports config',
    ];
    $this->logErrorExceptions = array_merge($this->logErrorExceptions, $exceptions);
    return parent::checkDeploymentLogs($env);
  }

}
