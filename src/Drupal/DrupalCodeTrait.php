<?php

namespace Dockworker\Drupal;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;
use Symfony\Component\Finder\Finder;

/**
 * Provides methods to interact with a Drupal codebase.
 *
 * @TODO: Needs review, was migrated from Dockworker 5.x.
 */
trait DrupalCodeTrait {

    /**
     * The modules within the current repository.
     *
     * @var \Symfony\Component\Finder\SplFileInfo[]
     */
    protected $drupalModules = [];

    /**
     * The themes within the current repository.
     *
     * @var \Symfony\Component\Finder\SplFileInfo[]
     */
    protected $drupalThemes = [];


    /**
     * Sets up the custom modules and themes in the current repository.
     *
     * @hook init @drupalcode
     */
    public function getCustomModulesThemes() {
        $projects = new Finder();
        $projects->files()->in($this->applicationRoot . '/custom/')->files()->name('*info.yml');;
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
