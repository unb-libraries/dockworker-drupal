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
   * @hook post-command test:all
   * @throws \Dockworker\DockworkerException
   */
  public function runDrupalTests() {
    $this->setRunOtherCommand('test:behat');
  }

  /**
   * Runs the Behat tests defined for the local Drupal application.
   *
   * @command test:behat
   * @aliases behat
   * @throws \Dockworker\DockworkerException
   *
   * @return mixed
   *   The testing command result.
   */
  public function runDrupalBehatTests() {
    $this->getLocalRunning();
    return $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/runTests.sh')
      ->run();
  }

}
