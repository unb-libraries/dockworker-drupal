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
   * @option bool $all
   *   Display logs from all cron pods, not only the latest.
   *
   * @command deployment:logs:cron
   * @throws \Exception
   *
   * @usage deployment:logs:cron dev
   *
   * @kubectl
   */
  public function getDrupalCronLogs($env, array $options = ['all' => FALSE]) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $pods = $this->kubernetesGetMatchingCronPods();

    if (!$options['all']) {
      $pods = array_slice($pods, 0, 1);
    }

    $logs = [];

    if (!empty($pods)) {
      foreach ($pods as $pod_id) {
        $logs[$pod_id] = $this->getDeploymentCronLogs($pod_id);
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
   * Gets the application's cron pod(s) logs.
   *
   * @param string $env
   *   The environment to check.
   *
   * @throws \Exception
   *
   * @return string[]
   *   An array of logs, keyed by pod IDs.
   */
  private function getDeploymentCronLogs($pod_id) {
    return $this->kubectlExec(
          'logs',
          [
            $pod_id,
            '--namespace',
            $this->deploymentK8sNameSpace,
          ],
          FALSE
    );
  }

  /**
   * Checks the application's cron pod(s) logs for errors.
   *
   * @param string $env
   *   The environment to check the logs in.
   *
   * @command deployment:logs:cron:check
   * @throws \Exception
   *
   * @usage deployment:logs:cron:check prod
   *
   * @kubectl
   */
  public function checkDeploymentCronLogs($env) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $pods = $this->kubernetesGetMatchingCronPods();
    $pods = array_slice($pods, 0, 1);

    if (!empty($pods)) {
      foreach ($pods as $pod_id) {
        $logs = $this->getDeploymentCronLogs($pod_id);
      }
    }

    if (!empty($logs)) {
      $this->checkLogForErrors($pod_id, $logs);
    }
    else {
      $this->io()->title("No pods found. No logs!");
    }
    try {
      $this->auditStartupLogs(FALSE);
      $this->say("No errors found in cron.");
    }
    catch (DockworkerException $e) {
      $this->io()->title("Logs for cron pod [$env.$pod_id]");
      $this->io()->writeln($logs);
      $this->printStartupLogErrors();
      throw new DockworkerException("Error(s) found in deployment cron logs!");
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

  /**
   * Generates a list of installed packages for remote drupal deployment.
   *
   * @param string $env
   *   The environment to obtain the list from.
   *
   * @command deployment:drupal:composer-packages
   * @aliases ddcp
   * @throws \Dockworker\DockworkerException
   *
   * @kubectl
   */
  public function getInstalledDeployedComposerPackages($env) {
    $pods = $this->getDeploymentExecPodIds($env);
    $pod_id = array_shift($pods);
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
