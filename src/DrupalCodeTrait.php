<?php

namespace Dockworker;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;
use Symfony\Component\Finder\Finder;

/**
 * Provides methods to interact with a Drupal codebase.
 */
trait DrupalCodeTrait {

  /**
   * The modules within the current repository.
   *
   * @var array
   */
  protected $drupalModules = [];

  /**
   * The themes within the current repository.
   *
   * @var array
   */
  protected $drupalThemes = [];

  /**
   * Sets the current custom modules and themes in the current repository.
   *
   * @hook init
   */
  public function getCustomModulesThemes() {
    $projects = new Finder();
    $projects->files()->in($this->repoRoot . '/custom/')->files()->name('*info.yml');;

    foreach ($projects as $project) {
      $type = explode('/', $project->getRelativePath())[0];
      if ($type == 'modules') {
        $this->drupalModules[] = $project;
      }
      if ($type == 'themes') {
        $this->drupalThemes[] = $project;
      }
    }
  }

}
