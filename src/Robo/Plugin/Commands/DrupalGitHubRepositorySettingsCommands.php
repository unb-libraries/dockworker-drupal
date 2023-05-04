<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;

/**
 * Provides methods for generating repository topics for Drupal Applications in GitHub.
 */
class DrupalGitHubRepositorySettingsCommands extends DockworkerDrupalCommands
{
    /**
     * Provides repository topics for Drupal Applications in GitHub.
     *
     * @hook on-event dockworker-github-topics
     */
    public function provideGitHubRepositoryTopics()
    {
        return [
            'drupal',
        ];
    }
}
