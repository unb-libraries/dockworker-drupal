<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RepoFinder;

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
    public function setupDrupalGitHooks(): void {
        $this->applicationRoot = RepoFinder::findRepoRoot();
        $this->copyGitHookFiles('dockworker-drupal');
    }
}
