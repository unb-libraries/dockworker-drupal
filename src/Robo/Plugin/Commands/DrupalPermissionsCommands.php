<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\ApplicationPermissionsCommands;

/**
 * Defines commands used to fix repository permissions local Drupal application.
 */
class DrupalPermissionsCommands extends ApplicationPermissionsCommands {

  /**
   * Sets the correct repository file permissions. Requires sudo.
   *
   * @hook post-command dockworker:permissions:fix
   */
  public function fixPermissions() {
    $paths = [
      'custom',
      'config-yml',
      'tests',
    ];
    foreach ($paths as $path) {
      $this->setPermissions($path);
    }
  }

}
