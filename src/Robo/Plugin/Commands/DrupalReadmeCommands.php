<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerReadmeCommands;

/**
 * Defines a class to write a standardized README to a repository.
 */
class DrupalReadmeCommands extends DockworkerReadmeCommands {

  /**
   * Provides additional twig templates for README.md.
   *
   * @hook on-event populate-readme-templates
   */
  public function getAdditionalDrupalApplicationReadmeTemplates() {
    return [$this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/README'];
  }

}
