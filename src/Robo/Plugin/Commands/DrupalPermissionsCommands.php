<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
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
  public function fixDrupalPermissions($result, CommandData $commandData) {
    $opts = $commandData->options();
    if (empty($opts['path'])) {
      $paths = [
        'custom',
        'config-yml',
        'tests',
      ];
    }
    else {
      $paths = [$opts['path']];
    }

    foreach ($paths as $path) {
      $this->setPermissions($path);
    }
  }

}
