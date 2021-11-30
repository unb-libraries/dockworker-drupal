<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RepoCIServicesWorkflowWriterTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerCIServicesWorkflowCommands;
use Robo\Robo;

/**
 * Defines a class to update the CI workflow file in a lean repository.
 */
class DrupalCIServicesWorkflowCommands extends DockworkerCIServicesWorkflowCommands {

  /**
   * Updates the application's CI Services workflow file.
   *
   * @hook replace-command ci:update-workflow-file
   *
   * @actionsworkflowcommand
   */
  public function setDrupalApplicationCIServicesWorkflowFile() {
    $major = Robo::Config()->get('dockworker.drupal.major');
    if (!empty($major) && $major == '9') {
      $this->CIServicesWorkflowSourcePath = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/gh-actions/9-test-suite.yaml';
    }
    else {
      $this->CIServicesWorkflowSourcePath = $this->repoRoot . '/vendor/unb-libraries/dockworker-drupal/data/gh-actions/test-suite.yaml';
    }
    $this->writeApplicationCIServicesWorkflowFile();
  }

}