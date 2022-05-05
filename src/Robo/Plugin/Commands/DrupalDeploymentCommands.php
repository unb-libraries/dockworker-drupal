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
   * Retrieves this application's k8s deployment cron logs.
   *
   * @param string $env
   *   The environment to obtain the logs from.
   *
   * @option $all
   *   Display logs from all cron pods, not only the latest.
   *
   * @command deployment:cron:logs
   * @throws \Exception
   *
   * @usage deployment:cron:logs dev
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
   * Executes this application's k8s deployment cron.
   *
   * @param string $env
   *   The environment to execute the cron in.
   *
   * @option $no-write-logs
   *   Do not display logs after execution.
   *
   * @command deployment:cron:exec
   * @throws \Exception
   *
   * @usage deployment:cron:exec prod
   *
   * @kubectl
   */
  public function runDeploymentCronPod($env, array $options = ['no-write-logs' => FALSE]) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $logs = $this->getRunDeploymentCronPodLogs($env);
    if (!$options['no-write-logs']) {
      $this->io()->block($logs);
    }
  }

  /**
   * Executes this application's k8s deployment cron, and determines if its logs contain errors.
   *
   * @param string $env
   *   The environment to execute the cron in.
   *
   * @option $write-successful-logs
   *   Display logs even if no errors found.
   *
   * @command deployment:cron:exec:check
   * @throws \Exception
   *
   * @usage deployment:cron:exec:check prod
   *
   * @kubectl
   */
  public function runCheckDeploymentCronPod($env, array $options = ['write-successful-logs' => FALSE]) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $logs = $this->getRunDeploymentCronPodLogs($env);
    $this->checkOutputLogsForErrors($env, 'cron', $logs);
    if ($options['write-successful-logs']) {
      $this->io()->block($logs);
      $this->say("No errors found in cron.");
    }
  }

  /**
   * Determines if this application's k8s deployment cron logs contain errors.
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
    $this->checkOutputLogsForErrors($env, $pod_id, $logs);
  }

  /**
   * Runs a deployment's cron pod and lists the logs.
   *
   * @param string $env
   *   The environment to run the cron pod in.
   *
   * @return string
   *   The logs from the cron run.
   */
  protected function getRunDeploymentCronPodLogs($env) {
    $delete_job_cmd = sprintf(
      $this->kubeCtlBin . ' delete job/manual-dockworker-cron-%s --ignore-not-found=true --namespace=%s',
      $this->deploymentK8sName,
      $this->deploymentK8sNameSpace
    );
    $this->say($delete_job_cmd);
    shell_exec($delete_job_cmd);

    $create_job_cmd = sprintf(
      $this->kubeCtlBin . ' create job --from=cronjob/cron-%s manual-dockworker-cron-%s --namespace=%s',
      $this->deploymentK8sName,
      $this->deploymentK8sName,
      $this->deploymentK8sNameSpace
    );
    $this->say($create_job_cmd);
    shell_exec($create_job_cmd);

    $wait_job_cmd = sprintf(
      $this->kubeCtlBin . ' wait --for=condition=complete job/manual-dockworker-cron-%s --namespace=%s',
      $this->deploymentK8sName,
      $this->deploymentK8sNameSpace
    );
    $this->say($wait_job_cmd);
    shell_exec($wait_job_cmd);

    $get_logs_cmd = sprintf(
      $this->kubeCtlBin . ' logs job/manual-dockworker-cron-%s --namespace=%s',
      $this->deploymentK8sName,
      $this->deploymentK8sNameSpace
    );
    $this->say($get_logs_cmd);
    $logs = shell_exec($get_logs_cmd);

    $this->say($delete_job_cmd);
    shell_exec($delete_job_cmd);

    return $logs;
  }

  /**
   * Checks the provided output logs for errors.
   *
   * @param string $env
   *   The environment the logs came from.
   * @param string $pod_id
   *   The pods the logs came from.
   * @param string $logs
   *   The logs to check.
   *
   * @throws \Dockworker\DockworkerException
   */
  protected function checkOutputLogsForErrors($env, $pod_id, $logs) {
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
   * Rebuilds all caches within this application's k8s deployment.
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
   * Generates a administrative login link to this application's k8s deployment.
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
   * Displays all composer packages installed within this application's k8s deployment.
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
