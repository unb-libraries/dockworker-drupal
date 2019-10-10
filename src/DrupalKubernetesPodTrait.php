<?php

namespace Dockworker;

use Dockworker\KubernetesPodTrait;

/**
 * Provides methods to interact with Drush in a deployed k8s Drupal application.
 */
trait DrupalKubernetesPodTrait {

  use KubernetesPodTrait;

  /**
   * Executes a drush command in a remote k8s Drupal pod.
   *
   * @param string $pod
   *   The pod name to check.
   * @param string $namespace
   *   The namespace to target the pod in.
   * @param string $command
   *   The drush command to execute.
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return string
   *   The STDOUT of the Drush command.
   */
  protected function kubernetesPodDrushCommand($pod, $namespace, $command) {
    return $this->kubernetesPodExecCommand(
      $pod,
      $namespace,
      sprintf('drush --yes --root=/app/html %s',
        $command
      )
    );
  }

}
