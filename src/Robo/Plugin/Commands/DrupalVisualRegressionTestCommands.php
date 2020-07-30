<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\Robo\Plugin\Commands\DrupalLocalCommands;
use Robo\Robo;

/**
 * Defines commands used to test the instance for Visual Regression.
 */
class DrupalVisualRegressionTestCommands extends DrupalLocalCommands {

  const MESSAGE_INFO_INIT_NEXT_STEPS = 'Backstop tests initialized for the homepage! Next, add some new scenarios for the site\'s componenets in in %s...';
  const MESSAGE_WARNING_UPSTREAM_CONTENT_CONFIRM = 'Have you done so immediately before running this command?';
  const MESSAGE_WARNING_UPSTREAM_CONTENT_NEEDED = 'Warning! Updating or testing backstop references requires content from the production deployment.';
  const MESSAGE_WARNING_UPSTREAM_CONTENT_SYNC_NEEDED = 'You can synchronize this content with the \'dockworker local:content:remote-sync prod\' command.';
  const MESSAGE_WARNING_VISUAL_REGRESSION_TESTS_EXIST = 'Warning! visual regression tests already exist. Would you like to delete them?';
  const VISUAL_REGRESSION_TEST_FOLDER = 'backstop';

  protected $backstopFileContents = NULL;
  protected $backstopFilePath = NULL;
  protected $backstopPath = NULL;
  protected $opts = [];


  /**
   * Setup the backstop paths.
   *
   * @hook init
   */
  public function setUpBackstopInfo() {
    $this->backstopPath = $this->repoRoot . '/tests/' . self::VISUAL_REGRESSION_TEST_FOLDER;
    $this->backstopFilePath = $this->backstopPath . '/backstop.json';
  }

  /**
   * Initializes visual regression tests for this instance.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @command visreg:init
   * @throws \Dockworker\DockworkerException
   *
   * @usage visreg:init
   */
  public function drupalInitVisualRegressionTests($options = ['yes' => FALSE]) {
    $this->opts = $options;
    $this->checkDeleteExistingVisualRegressionTests();
    $this->runBackstopCommand('init', "initializing");
    $this->replaceBackstopFileDefaultValues();
    $this->infoUserAboutNextBackstopSteps();
  }

  protected function warnUserAboutContentIssues() {
    $this->io()->warning(self::MESSAGE_WARNING_UPSTREAM_CONTENT_NEEDED);
    $this->say(self::MESSAGE_WARNING_UPSTREAM_CONTENT_SYNC_NEEDED);
    if (!$this->opts['yes'] && !$this->confirm(self::MESSAGE_WARNING_UPSTREAM_CONTENT_CONFIRM)) {
      throw new DockworkerException(
        'User Aborted!'
      );
    }
  }

  protected function infoUserAboutNextBackstopSteps() {
    $this->say(
      sprintf(
        self::MESSAGE_INFO_INIT_NEXT_STEPS,
        $this->backstopFilePath
      )
    );
  }

  protected function replaceBackstopFileDefaultValues() {
    $this->readBackstopFileContents();
    $this->backstopFileContents->id = $this->instanceName;
    $this->replaceBackstopDefaultScenarios();
    $this->replaceBackstopDefaultViewports();
    $this->writeBackstopFileContents();
  }

  protected function replaceBackstopDefaultScenarios() {
    $this->backstopFileContents->scenarios[0]->label = "{$this->instanceName} Homepage";
    $local_uri = "http://{$this->instanceName}/";
    $this->backstopFileContents->scenarios[0]->url = $local_uri;
  }

  protected function replaceBackstopDefaultViewports() {
    $this->backstopFileContents->viewports = [
      [
        'label' => 'sm',
        'width' => 576,
      ],
      [
        'label' => 'md',
        'width' => 768,
        'height' => 1152,
      ],
      [
        'label' => 'lg',
        'width' => 992,
        'height' => 1152,
      ],
      [
        'label' => 'xl',
        'width' => 1200,
        'height' => 1152,
      ],
    ];
  }

  /**
   * Generate visual regression references for this instance.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @command visreg:update
   * @throws \Dockworker\DockworkerException
   *
   * @usage visreg:update
   */
  public function drupalGenerateVisualRegressionReferences($options = ['yes' => FALSE]) {
    $this->opts = $options;
    if ($this->drupalInstanceHasVisualRegressionTests()) {
      $this->getLocalRunning();
      $this->warnUserAboutContentIssues();
      $this->runBackstopCommand('reference', "generating reference images");
    }
    else {
      $this->say('No tests defined. Have you run dockworker visreg:init?');
    }
  }

  /**
   * Generate visual regression references for this instance.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @command visreg:test
   * @throws \Dockworker\DockworkerException
   *
   * @usage visreg:test
   */
  public function drupalVisualRegressionTest($options = ['yes' => FALSE]) {
    $this->opts = $options;
    if ($this->drupalInstanceHasVisualRegressionTests()) {
      $this->getLocalRunning();
      $this->warnUserAboutContentIssues();
      $this->runBackstopCommand('test', "testing");
    }
    else {
      $this->say('No tests defined. Have you run dockworker visreg:init?');
    }
  }

  private function runBackstopCommand($command, $action = 'testing') {
    $result = $this->taskDockerRun('backstopjs/backstopjs')
      ->exec($command)
      ->volume($this->backstopPath, '/src')
      ->options(['rm' => NULL, 'network' => $this->instanceName])
      ->run();
    $this->setRunOtherCommand('pfix --path=tests/backstop');
    if ($result->getExitCode() > 0) {
      throw new DockworkerException(
        "The backstop process reported an error while $action."
      );

    }
  }

  private function drupalInstanceHasVisualRegressionTests() {
    if (file_exists($this->backstopFilePath)) {
      return TRUE;
    }
    return FALSE;
  }

  private function checkDeleteExistingVisualRegressionTests() {
    if ($this->drupalInstanceHasVisualRegressionTests()) {
      if (!$this->opts['yes'] && $this->confirm(self::MESSAGE_WARNING_VISUAL_REGRESSION_TESTS_EXIST)) {
        $this->rrmdir($this->backstopPath);
      }
      else {
        throw new DockworkerException(
          sprintf(
            'Visual regression tests already exist at %s',
            $backstop_file
          )
        );
      }
    }
  }

  private function rrmdir($dir) {
    if (is_dir($dir) && !empty($dir) && $dir != '/') {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != '.' && $object != '..') {
          if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
            $this->rrmdir($dir. DIRECTORY_SEPARATOR .$object);
          else
            unlink($dir. DIRECTORY_SEPARATOR .$object);
        }
      }
      rmdir($dir);
    }
  }

  /**
   *
   */
  protected function readBackstopFileContents() {
    $this->backstopFileContents = json_decode(
      file_get_contents($this->backstopFilePath)
    );
  }

  /**
   *
   */
  protected function writeBackstopFileContents() {
    file_put_contents(
      $this->backstopFilePath,
      json_encode($this->backstopFileContents, JSON_PRETTY_PRINT)
    );
  }

}
