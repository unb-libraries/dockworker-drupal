<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RepoGithubActionsWorkflowWriterTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerGithubActionsWorkflowCommands;

/**
 * Defines a class to write a standardized build file to a repository.
 */
class DrupalGithubActionsWorkflowCommands extends DockworkerGithubActionsWorkflowCommands {

  /**
   * Updates the application's GitHub actions workflow file.
   *
   * @hook replace-command dockworker:gh-actions:update
   *
   * @actionsworkflowcommand
   */
  public function setDrupalApplicationGithubActionsWorkflowFile() {
    $this->githubActionsWorkflowSourcePath = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/gh-actions/test-suite.yaml';
    $this->writeApplicationGithubActionsWorkflowFile();
  }

}
