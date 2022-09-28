<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalLocalDockerContainerTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalDaemonCommands;

/**
 * Defines the commands used to interact with a local Drupal redis application.
 */
class DrupalRedisCommands extends DockworkerLocalDaemonCommands {

  use DrupalLocalDockerContainerTrait;

  /**
   * Deletes data in this application's local redis deployment, and restarts it.
   *
   * @command redis:redeploy:local
   * @throws \Dockworker\DockworkerException
   */
  public function getInstalledLocalComposerPackages() {
    $this->getLocalRunning();
    $this->io()->title("Redeploying Redis For $this->instanceName");
    $this->say('Removing redis container...');
    $this->taskExec('docker-compose')
      ->dir($this->repoRoot)
      ->arg('rm')
      ->arg('-v')
      ->arg('--stop')
      ->arg('--force')
      ->arg('drupal-redis-lib-unb-ca')
      ->run();

    $this->say('Deploying new redis container...');
    $this->taskExec('docker-compose')
      ->dir($this->repoRoot)
      ->arg('up')
      ->arg('-d')
      ->arg('drupal-redis-lib-unb-ca')
      ->run();
  }

}
