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

  const ERROR_NO_CRON_PODS_IN_DEPLOYMENT = 'No cron pods were found for the deployment [%s:%s].';

  /**
   * Provides log checker with ignored log exception items for deployed Drupal.
   *
   * @hook on-event dockworker-deployment-log-error-triggers
   */
  public function getErrorLogDeploymentTriggers() {
    return [
      'SQLSTATE',
    ];
  }

  /**
   * Provides log checker with ignored log exception items for deployed Drupal.
   *
   * @hook on-event dockworker-deployment-log-error-exceptions
   */
  public function getErrorLogDeploymentExceptions() {
    return [
      'Operation CREATE USER failed' => 'Creating a local user failing is expected in deployment',
      '[notice] Synchronized extensions' => 'Ignore installation of modules that have "error" in their names',
      'config_importer is already importing' => 'Ignore errors when only one pod imports config',
      'Use symfony/error-handler instead' => 'Symfony component names are not errors',
    ];
  }



  /**
   * Rebuilds all caches within this application's k8s deployment.
   *
   * @param string $env
   *   The environment to obtain the logs from.
   *
   * @command drupal:cr:deployed
   * @throws \Exception
   *
   * @usage drupal:cr:deployed dev
   *
   * @kubectl
   */
  public function rebuildRemoteDrupalCache($env) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Open Shell');
    $this->kubernetesPodExecCommand(
      $pod_id,
      $env,
      '/scripts/clearDrupalCache.sh'
    );
  }

  /**
   * Generates a clickable URL to this application's k8s deployment Drupal admin panel.
   *
   * @param string $env
   *   The environment to obtain the login link from.
   *
   * @command drupal:uli:deployed
   * @aliases ruli
   * @throws \Exception
   *
   * @usage drupal:uli:deployed prod
   *
   * @kubectl
   */
  public function generateRemoteDrupalUli($env) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Open Shell');
    $this->io()->text(
      $this->kubernetesPodExecCommand(
        $pod_id,
        $env,
        '/scripts/drupalUli.sh'
      )
    );
  }

  /**
   * Displays all composer packages installed within this application's k8s deployment.
   *
   * @param string $env
   *   The environment to obtain the list from.
   *
   * @command composer:list:deployed
   * @aliases ddcp
   * @throws \Dockworker\DockworkerException
   *
   * @kubectl
   */
  public function getInstalledDeployedComposerPackages($env) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Open Shell');
    $this->io()->title("[$env][$pod_id] Installed Composer Packages");
    $this->io()->text(
      $this->kubernetesPodExecCommand(
        $pod_id,
        $env,
        'composer show --working-dir=/app/html'
      )
    );
  }

}
