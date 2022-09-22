<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalDaemonCommands;

/**
 * Defines commands used to test the local Drupal application.
 */
class DrupalTestCommands extends DockworkerLocalDaemonCommands {

  /**
   * Runs all tests defined for the local Drupal application.
   *
   * @hook post-command tests:all
   * @throws \Dockworker\DockworkerException
   */
  public function runDrupalTests() {
    $this->setRunOtherCommand('tests:phpunit');
  }

  /**
   * Executes PHPUnit tests within this application's local deployment.
   *
   * @command tests:phpunit
   * @aliases phpunit
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The testing command result.
   */
  public function runDrupalUnitTests() {
    $this->io()->title("Running PHPUnit Tests");
    $this->getLocalRunning();
    return $this->taskDockerExec($this->instanceName)
      ->exec('/scripts/runUnitTests.sh')
      ->run();
  }

  /**
   * Prepares the environment for end-to-end testing.
   *
   * @hook pre-command @e2e
   */
  public function setupE2eTestEnvironment() {
    $this->io()->title("Preparing Test Environment");
    $this->setRunOtherCommand('drupal:migrate:import --tags=e2e');

  }

  /**
   * Cleans the environment after end-to-end testing.
   *
   * @hook post-command @e2e
   */
  public function cleanupE2eTestEnvironment() {
    $this->io()->title("Cleaning Test Environment");
    $this->setRunOtherCommand('drupal:migrate:rollback --tags=e2e');
  }

}
