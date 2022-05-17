<?php

namespace Dockworker;

use Dockworker\LocalDockerContainerTrait;

/**
 * Provides methods to interact with Drush in a local Drupal application.
 */
trait DrupalLocalDockerContainerTrait {

  use LocalDockerContainerTrait;

  /**
   * Executes a drush command in a local Drupal application.
   *
   * @param string $name
   *   The name of the container to execute the command in.
   * @param string $command
   *   The drush command to execute.
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return string
   *   The STDOUT of the Drush command.
   */
  protected function localDockerContainerDrushCommand($name, $command) {
    return $this->localDockerContainerExecCommand(
      $name,
      sprintf('drush --yes --root=/app/html %s',
        $command
      )
    );
  }

  /**
   * Gets the string that represents the local Drupal version.
   *
   * @return string
   *
   * @throws \Exception
   */
  protected function getLocalDrupalVersionString() {
    $local_output = $this->runLocalDrushCommand('status');
    return $local_output[0];
  }

  /**
   * Runs a drush command in the local Drupal application.
   *
   * @param string $command
   *   The command string to execute.
   *
   * @throws \Exception
   *
   * @return mixed
   *   The command result.
   */
  private function runLocalDrushCommand($command) {
    return $this->localDockerContainerDrushCommand(
      $this->instanceName,
      $command
    );
  }

}
