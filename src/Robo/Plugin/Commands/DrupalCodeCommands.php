<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerCommands;
use Symfony\Component\Finder\Finder;

/**
 * Defines the commands used to interact with Drupal theme and module files.
 */
class DrupalCodeCommands extends DockworkerCommands {

  /**
   * The modules within the current repository.
   *
   * @var string[]
   */
  protected $drupalModules = [];

  /**
   * The themes within the current repository.
   *
   * @var string[]
   */
  protected $drupalThemes = [];

  /**
   * Sets up the custom modules and themes in the current repository.
   *
   * @hook init
   */
  public function getCustomModulesThemes() {
    $projects = new Finder();
    $projects->files()->in($this->repoRoot . '/custom/')->files()->name('*info.yml');;
    foreach ($projects as $project) {
      $type = explode('/', $project->getRelativePath())[0];
      $name = $project->getBasename('.info.yml');
      if ($type == 'modules') {
        $this->drupalModules[] = $project;
      }
      if ($type == 'themes') {
        $this->drupalThemes[] = $project;
      }
    }
  }

}
