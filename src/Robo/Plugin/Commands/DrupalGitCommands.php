<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerCommands;

/**
 * Defines the commands used to setup the git hooks for a Drupal repository.
 */
class DrupalGitCommands extends DockworkerCommands {

  /**
   * Sets up the required git hooks.
   *
   * @hook post-command git:setup-hooks
   */
  public function setupHooks() {
    $source_dir = $this->repoRoot . "/vendor/unb-libraries/dockworker-drupal/scripts/git-hooks";
    $target_dir = $this->repoRoot . "/.git/hooks";
    $this->_copy("$source_dir/pre-commit", "$target_dir/pre-commit");
  }

}
