<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines the commands used to update dockworker-drupal.
 */
class DrupalDockworkerUpdateCommands extends DockworkerLocalCommands {

  /**
   * Self-updates dockworker-drupal.
   *
   * @hook post-command dockworker:update
   */
  public function getDockworkerDrupalUpdates() {
    $this->say('Checking for updates to unb-libraries/dockworker-drupal...');
    $this->taskExec('composer')
      ->dir($this->repoRoot)
      ->arg('update')
      ->arg('unb-libraries/dockworker-drupal')
      ->silent(TRUE)
      ->run();
  }

}
