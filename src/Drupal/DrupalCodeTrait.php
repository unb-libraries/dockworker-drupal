<?php

namespace Dockworker\Drupal;

use Symfony\Component\Finder\Finder;

/**
 * Provides methods to interact with a Drupal codebase in a lean repository.
 */
trait DrupalCodeTrait
{
    /**
     * The modules within the current repository.
     *
     * @var \Symfony\Component\Finder\SplFileInfo[]
     */
    protected array $drupalModules = [];

    /**
     * The themes within the current repository.
     *
     * @var \Symfony\Component\Finder\SplFileInfo[]
     */
    protected array $drupalThemes = [];

    /**
     * Sets up the custom modules and themes in the current repository.
     */
    public function getCustomModulesThemes(): void
    {
        $projects = new Finder();
        $projects->files()->in($this->applicationRoot . '/custom/')->files()->name('*info.yml');
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
