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
   * Removes orphaned references to an uninstalled module within this application's k8s deployment.
   *
   * Beware : This command has the potential to destroy your instance.
   *
   * @param string $module
   *   The module to remove all references from.
   * @param string $env
   *   The environment to operate in.
   *
   * @command deployment:drupal:fix-missing-module
   * @throws \Exception
   *
   * @usage deployment:drupal:fix-missing-module devel dev
   *
   * @kubectl
   */
  public function fixDeploymentMissingModules($module, $env) {
    $pods = $this->getDeploymentExecPodIds($env);
    $pod_id = array_shift($pods);

    $this->kubernetesPodComposerCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      "require drupal/$module"
    );

    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      "en $module"
    );

    $this->kubernetesPodDrushClearCache(
      $pod_id,
      $this->kubernetesPodNamespace
    );

    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      'updb'
    );

    $this->kubernetesPodDrushClearCache(
      $pod_id,
      $this->kubernetesPodNamespace
    );

    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      "pmu $module"
    );

    $this->kubernetesPodComposerCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      "remove drupal/$module --update-with-dependencies"
    );

    $this->kubernetesPodDrushClearCache(
      $pod_id,
      $this->kubernetesPodNamespace
    );
  }

}
