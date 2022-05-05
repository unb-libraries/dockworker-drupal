<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines commands used to test the local Drupal application.
 */
class DrupalTestCommands extends DockworkerLocalCommands {

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

}
