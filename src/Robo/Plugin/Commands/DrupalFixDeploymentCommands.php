<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class DrupalFixDeploymentCommands extends DockworkerDeploymentCommands {

  use DrupalKubernetesPodTrait;

  /**
   * Removes any references to a missing module in the application's k8s pod(s).
   *
   * Beware : This command has the potential to destroy your instance.
   *
   * @param string $module
   *   The environment to obtain the logs from.
   * @param string $env
   *   The environment to obtain the logs from.
   *
   * @command deployment:drupal:fix-missing-module
   * @throws \Exception
   *
   * @usage deployment:drupal:fix-missing-module devel dev
   *
   * @kubectl
   */
  public function fixDeploymentMissingModules($module, $env) {
    $this->deploymentCommandInit($this->repoRoot, $env);
    $this->kubernetesPodNamespace = $this->deploymentK8sNameSpace;
    $this->kubernetesSetupPods($this->deploymentK8sName, "Logs");

    if (!empty($this->kubernetesCurPods)) {
      $first_pod_id = reset($this->kubernetesCurPods);

      $this->kubernetesPodComposerCommand(
        $first_pod_id,
        $this->kubernetesPodNamespace,
        "require drupal/$module"
      );

      $this->kubernetesPodDrushCommand(
        $first_pod_id,
        $this->kubernetesPodNamespace,
        "en $module"
      );

      $this->kubernetesPodDrushClearCache(
        $first_pod_id,
        $this->kubernetesPodNamespace
      );

      $this->kubernetesPodDrushCommand(
        $first_pod_id,
        $this->kubernetesPodNamespace,
        'updb'
      );

      $this->kubernetesPodDrushClearCache(
        $first_pod_id,
        $this->kubernetesPodNamespace
      );

      $this->kubernetesPodDrushCommand(
        $first_pod_id,
        $this->kubernetesPodNamespace,
        "pmu $module"
      );

      $this->kubernetesPodComposerCommand(
        $first_pod_id,
        $this->kubernetesPodNamespace,
        "remove drupal/$module --update-with-dependencies"
      );

      $this->kubernetesPodDrushClearCache(
        $first_pod_id,
        $this->kubernetesPodNamespace
      );
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

}
