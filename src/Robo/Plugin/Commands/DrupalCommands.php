<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;

/**
 * Defines core Drupal instance operations.
 */
class DrupalCommands extends DockworkerApplicationCommands {

  use \Boedah\Robo\Task\Drush\loadTasks;

  /**
   * Rebuild the cache in the Drupal container.
   *
   * @command drupal:cr
   * @aliases cr
   */
  public function resetCache() {
    $this->runDrush('cr');
  }

  /**
   * Run a drush command in the Drupal container.
   *
   * @param string $command
   *   The command to run.
   */
  private function runDrush($command) {
    $this->getApplicationRunning();
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec(
        $this->taskDrushStack()
          ->drupalRootDirectory('/app/html')
          ->uri('default')
          ->drush($command)
      )
      ->run();
  }

  /**
   * Perform any required entity updates in the instance.
   *
   * @command drupal:entup
   * @aliases entup
   */
  public function updateEntities() {
    $this->runDrush('entup');
  }

  /**
   * Get a ULI from the Drupal container.
   *
   * @param string $user_name
   *   The user account name to generate the ULI for. Defaults to user 0.
   *
   * @command drupal:uli
   * @aliases uli
   */
  public function uli($user_name = NULL) {
    $this->getApplicationRunning();
    if (empty($user_name)) {
      $this->taskDockerExec($this->instanceName)
        ->interactive()
        ->exec(
          '/scripts/drupalUli.sh'
        )
        ->run();
    }
    else {
      $this->taskDockerExec($this->instanceName)
        ->interactive()
        ->exec(
          "/scripts/drupalUli.sh '$user_name'"
        )
        ->run();
    }
  }

  /**
   * Write out the configuration from the instance.
   *
   * @command drupal:write-config
   * @aliases write-config
   */
  public function writeConfig() {
    $this->getApplicationRunning();
    $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/configExport.sh')
      ->run();
    $this->setRunOtherCommand('permissions:fix');
  }

  /**
   * Self-update.
   *
   * @hook post-command dockworker:update
   */
  public function getDockworkerUpdates() {
    $this->say('Checking for dockworker updates...');
    $this->taskExec('composer')
      ->dir($this->repoRoot)
      ->arg('update')
      ->arg('unb-libraries/dockworker-drupal')
      ->run();
  }

  /**
   * Setup git hooks.
   *
   * @command drupal:setup-git-hooks
   * @aliases git:setup-hooks
   */
  public function setupHooks() {
    $source_dir = $this->repoRoot . "/vendor/unb-libraries/dockworker/scripts/git-hooks";
    $target_dir = $this->repoRoot . "/.git/hooks";
    $this->_copy("$source_dir/commit-msg", "$target_dir/commit-msg");
    $this->_copy("$source_dir/pre-commit", "$target_dir/pre-commit");
  }

}
