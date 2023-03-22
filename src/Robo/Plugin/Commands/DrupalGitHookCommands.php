<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerGitHookCommands;

/**
 * Provides commands to copy git hooks into a drupal application repository.
 */
class DrupalGitHookCommands extends DockworkerGitHookCommands
{
    /**
     * Sets up the required git hooks for dockworker-drupal.
     *
     * @hook post-init git:setup-hooks
     */
    public function setupDrupalGitHooks(): void
    {
        $this->copyGitHookFiles('dockworker-drupal');
    }
}
