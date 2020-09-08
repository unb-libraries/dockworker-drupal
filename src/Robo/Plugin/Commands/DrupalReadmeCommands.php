<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerReadmeCommands;

/**
 * Defines a class to write a standardized README to a repository.
 */
class DrupalReadmeCommands extends DockworkerReadmeCommands {

  /**
   * Updates the Drupal application's README.md.
   *
   * @hook replace-command dockworker:readme:update
   */
  public function setDrupalApplicationReadme() {
    $this->readMeTemplatePaths[] = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/README';
    parent::setApplicationReadme();
  }

}
