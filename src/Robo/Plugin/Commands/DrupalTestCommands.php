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
    $this->setRunOtherCommand('tests:behat');
    $this->setRunOtherCommand('tests:phpunit');
  }

  /**
   * Runs the Behat tests defined for the local Drupal application.
   *
   * @command tests:behat
   * @aliases behat
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The testing command result.
   */
  public function runDrupalBehatTests() {
    $this->output->title("Running Behat Tests");
    $this->getLocalRunning();
    return $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/runBehatTests.sh')
      ->run();
  }

  /**
   * Runs the PHPUnit tests defined for the local Drupal application.
   *
   * @command tests:phpunit
   * @aliases phpunit
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The testing command result.
   */
  public function runDrupalUnitTests() {
    $this->output->title("Running PHPUnit Tests");
    $this->getLocalRunning();
    return $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/runUnitTests.sh')
      ->run();
  }

}
