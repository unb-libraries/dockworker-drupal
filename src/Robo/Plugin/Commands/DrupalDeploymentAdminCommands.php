<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;
use Robo\Symfony\ConsoleIO;

/**
 * Defines the commands used to interact with Kubernetes deployment resources.
 */
class DrupalDeploymentAdminCommands extends DockworkerDeploymentCommands {

  /**
   * Destroys all data in the deployment and starts over.
   *
   * @param string $env
   *   The environment to destroy.
   *
   * @command deployment:reset:all
   * @throws \Dockworker\DockworkerException
   * @throws \Exception
   *
   * @usage deployment:reset:all dev
   * @hidden
   *
   * @kubectl
   */
  public function destroyRestartDeployment(ConsoleIO $io, $env) {
    $this->warnConfirmExitDestructiveAction(
      $io,
      "This command will destroy all data in the $this->instanceName/$env. Continue?"
    );
    $this->setRunOtherCommand("solr:data:clear $env");
    $this->setRunOtherCommand("drupal:cr:deployed $env");
    $this->setRunOtherCommand("drupal:drush:deployed sql:drop $env");
    $this->deleteEntireDrupalFileSystem($env);
    $this->setRunOtherCommand("k8s:deployment:delete:default $env");
    $this->setRunOtherCommand("k8s:deployment:create:default $env");
  }

}
