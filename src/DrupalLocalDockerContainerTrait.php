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

}
