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
      '[notice] Synchronized extensions' => 'Ignore installation of modules that have "error" in their names',
      'config_importer is already importing' => 'Ignore errors when only one pod imports config',
    ];
  }

  /**
   * Gets the cron logs for the drupal pod.
   *
   * @param string $env
   *   The environment to obtain the logs from.
   *
   * @command deployment:logs:cron
   * @throws \Exception
   *
   * @usage deployment:logs:cron dev
   *
   * @kubectl
   */
  public function getDrupalCronLogs($env) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $pods = $this->kubernetesGetMatchingCronPods();

    $logs = [];
    if (!empty($pods)) {
      foreach ($pods as $pod_id) {
        $logs[$pod_id] = $this->kubectlExec(
          'logs',
          [
            $pod_id,
            '--namespace',
            $env,
            '--timestamps'
          ],
          FALSE
        );
      }
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_CRON_PODS_IN_DEPLOYMENT,
          $this->deploymentK8sName,
          $env
        )
      );
    }

    $pod_counter = 0;
    if (!empty($logs)) {
      $num_pods = count($logs);
      $this->io()->title("$num_pods previous cron pods found for $env environment.");
      foreach ($logs as $pod_id => $log) {
        $pod_counter++;
        $this->io()->title("Logs for cron pod #$pod_counter [$env.$pod_id]");
        $this->io()->writeln($log);
      }
    }
    else {
      $this->io()->title("No cron pods found. No logs!");
    }
  }

  /**
   * @param $deployment_name
   * @param $namespace
   *
   * @return false|string[]
   */
  protected function kubernetesGetMatchingCronPods() {
    $get_pods_cmd = sprintf(
      $this->kubeCtlBin . " get pods --namespace=%s --sort-by=.status.startTime --no-headers | grep '^cron-%s' | grep 'Completed\|Error' | sed '1!G;h;$!d' | awk '{ print $1 }'",
      $this->deploymentK8sNameSpace,
      $this->deploymentK8sName
    );

    $pod_list = trim(
      shell_exec($get_pods_cmd)
    );

    return explode(PHP_EOL, $pod_list);
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
