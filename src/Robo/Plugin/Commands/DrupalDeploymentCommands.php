<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\KubernetesDeploymentTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class DrupalDeploymentCommands extends DockworkerDeploymentCommands {

  use KubernetesDeploymentTrait;

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
   * Removes any references to missing modules in application's k8s pod(s).
   *
   * Beware : This command has the potential to destroy your instance.
   *
   * @param string $env
   *   The environment to obtain the logs from.
   *
   * @command deployment:drupal:fix-missing-modules
   * @throws \Exception
   *
   * @usage deployment:drupal:fix-missing-modules dev
   *
   * @kubectl
   */
  public function fixDeploymentMissingModules($env) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $this->kubernetesPodNamespace = $this->deploymentK8sNameSpace;
    $this->kubernetesSetupPods($this->deploymentK8sName, "Logs");

    if (!empty($this->kubernetesCurPods)) {
      $first_pod_id = reset($this->kubernetesCurPods);

      $this->podComposerCommand(
        $first_pod_id,
        [
          'require',
          'drupal/module_missing_message_fixer',
        ]
      );

      $this->podDrushCommand(
        $first_pod_id,
        [
          'en',
          'module_missing_message_fixer',
        ]
      );

      $this->podDrushClearCache($first_pod_id);

      $this->podDrushCommand(
        $first_pod_id,
        [
          'mmmff',
          '--all',
        ]
      );

      $this->podDrushCommand(
        $first_pod_id,
        [
          'pmu',
          'module_missing_message_fixer',
        ]
      );

      $this->podComposerCommand(
        $first_pod_id,
        [
          'remove',
          'drupal/module_missing_message_fixer',
        ]
      );

      $this->podDrushClearCache($first_pod_id);
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_PODS_IN_DEPLOYMENT,
          $this->deploymentK8sName,
          $this->deploymentK8sNameSpace
        )
      );
    }
  }

  /**
   * Executes a composer command inside a k8s pod.
   *
   * @param $pod_id
   *   The pod to target.
   * @param array $args
   *   The composer command to execute.
   *
   * @return \Robo\Result
   */
  private function podComposerCommand($pod_id, $args = []) {
    $task = $this->taskExec($this->kubeCtlBin)
      ->arg('exec')->arg('-it')->arg($pod_id)
      ->arg("--namespace={$this->kubernetesPodNamespace}")
      ->arg('--')
      ->arg('composer')
      ->arg('--working-dir=/app/html');

    foreach ($args as $arg) {
      $task->arg($arg);
    }
    return $task->run();
  }

  /**
   * Executes a Drush command inside a k8s pod.
   *
   * @param $pod_id
   *   The pod to target.
   * @param array $args
   *   The drush command to execute.
   *
   * @return \Robo\Result
   */
  private function podDrushCommand($pod_id, $args = []) {
    $task = $this->taskExec($this->kubeCtlBin)
      ->arg('exec')->arg('-it')->arg($pod_id)
      ->arg("--namespace={$this->kubernetesPodNamespace}")
      ->arg('--')
      ->arg('drush')
      ->arg('--root=/app/html')
      ->arg('--yes');

    foreach ($args as $arg) {
      $task->arg($arg);
    }
    return $task->run();
  }

  /**
   * Clears the Drupal cache inside a k8s pod.
   *
   * @param $pod_id
   *   The pod to target.
   *
   * @return \Robo\Result
   */
  private function podDrushClearCache($pod_id) {
    $this->podDrushCommand(
      $pod_id,
      [
        'cr',
      ]
    );
  }

}
