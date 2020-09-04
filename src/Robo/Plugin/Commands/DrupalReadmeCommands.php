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
   * @command dockworker:readme:update
   * @aliases update-readme
   *
   * @usage dockworker:readme:update
   *
   * @github
   * @readmecommand
   */
  public function setApplicationReadme() {
    $this->readMeTemplatePaths[] = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/README';
    parent::setApplicationReadme();
  }

}
