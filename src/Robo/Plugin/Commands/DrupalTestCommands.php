<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalCodeTrait;
use Dockworker\DrupalLocalDockerContainerTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalDaemonCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines commands used to test the local Drupal application.
 */
class DrupalTestCommands extends DockworkerLocalDaemonCommands {

  use DrupalCodeTrait;
  use DrupalLocalDockerContainerTrait;

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
    $this->setRunOtherCommand('local:install-test-dependencies');
  }

  /**
   * Installs each custom module's declared test_dependencies.
   *
   * @command local:install-test-dependencies
   * @aliases install-test-dependencies
   */
  public function installTestDependencies() {
    $this->getCustomModulesThemes();
    $test_dependencies = ['migrate'];
    foreach ($this->drupalModules as $drupal_module) {
      $module_info = Yaml::parseFile($drupal_module);
      if (array_key_exists('test_dependencies', $module_info)) {
        array_push($test_dependencies, ...$module_info['test_dependencies']);
      }
    }

    $test_dependencies = array_unique($test_dependencies);
    if (!empty($test_dependencies)) {
      $this->io()->text(
        $this->runLocalDrushCommand('en -y ' . implode(',', $test_dependencies)));
    }
    else {
      $this->io()->say("Nothing to install");
    }
  }

}
