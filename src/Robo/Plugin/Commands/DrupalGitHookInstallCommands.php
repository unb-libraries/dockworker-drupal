<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\GitHookInstallCommands;

/**
 * Provides commands to copy git hooks into a drupal application repository.
 */
class DrupalGitHookInstallCommands extends GitHookInstallCommands
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
