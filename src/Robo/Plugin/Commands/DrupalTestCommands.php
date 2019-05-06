<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;

/**
 * Defines drupal instance testing commands.
 */
class DrupalTestCommands extends DockworkerApplicationCommands {

  /**
   * Run all tests defined for the Drupal instance.
   *
   * @hook post-command test:all
   */
  public function runDrupalTests() {
    $this->setRunOtherCommand('test:behat');
  }

  /**
   * Run the Behat tests defined for the Drupal instance.
   *
   * @command test:behat
   * @aliases behat
   */
  public function runDrupalBehatTests() {
    $this->getApplicationRunning();
    return $this->taskDockerExec($this->instanceName)
      ->interactive()
      ->exec('/scripts/runTests.sh')
      ->run();
  }

}
