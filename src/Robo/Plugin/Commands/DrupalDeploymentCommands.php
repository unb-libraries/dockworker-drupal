<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\KubernetesDeploymentTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class DrupalDeploymentCommands extends DockworkerDeploymentCommands {

  use DrupalKubernetesPodTrait;

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

  /**
   * Clears the cache(s) in a remote drupal Deployment.
   *
   * @param string $env
   *   The environment to obtain the logs from.
   *
   * @command deployment:drupal:cr
   * @throws \Exception
   *
   * @usage deployment:drupal:cr dev
   *
   * @kubectl
   */
  public function rebuildRemoteDrupalCache($env) {
    $pods = $this->getDeploymentExecPodIds($env);
    $pod_id = array_shift($pods);
    $this->kubernetesPodExecCommand(
      $pod_id,
      $env,
      '/scripts/clearDrupalCache.sh'
    );
  }

  /**
   * Generates a ULI link for a remote drupal deployment.
   *
   * @param string $env
   *   The environment to obtain the login link from.
   *
   * @command deployment:drupal:uli
   * @aliases ruli
   * @throws \Exception
   *
   * @usage deployment:drupal:uli prod
   *
   * @kubectl
   */
  public function generateRemoteDrupalUli($env) {
    $pods = $this->getDeploymentExecPodIds($env);
    $pod_id = array_shift($pods);
    $this->io()->text(
      $this->kubernetesPodExecCommand(
        $pod_id,
        $env,
        '/scripts/drupalUli.sh'
      )
    );
  }

}
