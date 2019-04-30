<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\ApplicationPermissionsCommands;

/**
 * Defines commands to set proper permissions for a drupal repository.
 */
class DrupalPermissionsCommands extends ApplicationPermissionsCommands {

  /**
   * Fix repository file permissions. Requires sudo.
   *
   * @hook post-command application:permissions:fix
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
