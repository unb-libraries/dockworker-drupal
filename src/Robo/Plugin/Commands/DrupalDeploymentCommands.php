<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class DrupalDeploymentCommands extends DockworkerDeploymentCommands {

  /**
   * Provides log checker with ignored log exception items for deployed Drupal.
   *
   * @hook on-event dockworker-deployment-log-error-exceptions
   */
  public function getErrorLogDeploymentExceptions() {
    return [
      '[notice] Synchronized extensions' => 'Ignore installation of modules that have "error" in their names',
      'config_importer is already importing' => 'Ignore errors when only one pod imports config',
    ];
  }

}
