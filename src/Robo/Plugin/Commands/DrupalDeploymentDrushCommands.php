<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines commands to interact via Drush with a deployed Drupal application.
 */
class DrupalDeploymentDrushCommands extends DockworkerDeploymentCommands {

  use DrupalKubernetesPodTrait;

  /**
   * Executes a drush command in the application's k8s pod(s).
   *
   * @param string $cmd
   *   The drush command to run.
   * @param string $env
   *   The environment to run the command in.
   *
   * @command deployment:drupal:drush
   * @throws \Exception
   *
   * @usage deployment:drupal:drush 'sql-cli' dev
   *
   * @kubectl
   */
  public function setRunDrushCommand($cmd, $env) {
    $pods = $this->getDeploymentExecPodIds($env);
    $pod_id = array_shift($pods);
    $response = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      $cmd
    );
    $this->io()->block(implode("\n", $response));
  }

}
