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
     *
     * @return string[]
     *   The repository topics.
     */
    public function provideGitHubRepositoryTopics(): array
    {
        return [
            'drupal',
        ];
    }
}
