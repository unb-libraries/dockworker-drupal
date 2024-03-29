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
   * Executes a drush command within this application's k8s deployment.
   *
   * @param string $cmd
   *   The drush command to run.
   * @param string $env
   *   The environment to run the command in.
   *
   * @command drupal:drush:deployed
   * @throws \Exception
   *
   * @usage 'sql-cli' dev
   *
   * @kubectl
   */
  public function setRunDrushCommand($cmd, $env) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Open Shell');
    $response = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      $cmd
    );
    $this->io()->block(implode("\n", $response));
  }

}
