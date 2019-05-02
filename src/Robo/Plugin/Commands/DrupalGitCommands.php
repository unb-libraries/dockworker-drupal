<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerCommands;

/**
 * Git commands.
 */
class DrupalGitCommands extends DockworkerCommands {

  /**
   * Setup git hooks.
   *
   * @hook post-command git:setup-hooks
   */
  public function setupHooks() {
    $source_dir = $this->repoRoot . "/vendor/unb-libraries/dockworker-drupal/scripts/git-hooks";
    $target_dir = $this->repoRoot . "/.git/hooks";
    $this->_copy("$source_dir/pre-commit", "$target_dir/pre-commit");
  }

}
