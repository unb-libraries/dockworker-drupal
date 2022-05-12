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
   * @command drupal:module:fix-missing:deployed
   * @throws \Exception
   *
   * @usage drupal:module:fix-missing:deployed devel dev
   *
   * @kubectl
   */
  public function fixDeploymentMissingModules($module, $env) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Open Shell');

    $this->kubernetesPodComposerCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      "require drupal/$module"
    );

    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      "en $module"
    );

    $this->kubernetesPodDrushClearCache(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace
    );

    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      'updb'
    );

    $this->kubernetesPodDrushClearCache(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace
    );

    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      "pmu $module"
    );

    $this->kubernetesPodComposerCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      "remove drupal/$module --update-with-dependencies"
    );

    $this->kubernetesPodDrushClearCache(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace
    );
  }

}
