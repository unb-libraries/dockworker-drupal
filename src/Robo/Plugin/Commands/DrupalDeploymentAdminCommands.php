<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalProjectsTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;
use Robo\Symfony\ConsoleIO;

/**
 * Defines the commands used to interact with Kubernetes deployment resources.
 */
class DrupalDeploymentAdminCommands extends DockworkerDeploymentCommands {

  use DockworkerDrupalProjectsTrait;

  /**
   * Destroys all data in the deployment and starts over.
   *
   * @param string $env
   *   The environment to destroy.
   *
   * @command deployment:reset:all
   * @throws \Dockworker\DockworkerException
   * @throws \Exception
   *
   * @usage deployment:reset:all dev
   * @hidden
   *
   * @kubectl
   */
  public function destroyRestartDeployment(ConsoleIO $io, $env) {
    $this->warnConfirmExitDestructiveAction(
      $io,
      "This command will destroy all data in the $this->instanceName/$env. Continue?"
    );
    if (
      $this->getDrupalHasEnabledModule(
        'search_api_solr',
        $this->repoRoot
      )
    ) {
      $io->title('Removing solr Data');
      $this->setRunOtherCommand("solr:data:clear $env");
    }
    $io->title('Clearing Drupal Cache');
    try {
      $this->setRunOtherCommand("drupal:cr:deployed $env");
    }
    catch (\Exception $e) {
      // Pass.
    }
    $io->title('Dropping Tables');
    $this->setRunOtherCommand("drupal:drush:deployed sql:drop $env");
    $this->deleteEntireDrupalFileSystem($env, $io);
    $io->title('Deleting k8s Deployment');
    $this->setRunOtherCommand("k8s:deployment:delete:default $env");
    $io->title('Creating k8s Deployment');
    $this->setRunOtherCommand("k8s:deployment:create:default $env");
  }

  /**
   * Deletes all files from a Drupal filesystem.
   *
   * @param string $env
   *   The environment to obtain the login link from.
   * @param \Robo\Symfony\ConsoleIO $io
   *   The IO to output with.
   *
   * @throws \Exception
   *
   * @kubectl
   */
  protected function deleteEntireDrupalFileSystem($env, ConsoleIO $io) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Open Shell');
    $io->title('Removing Drupal Filesystem');
    $this->kubernetesPodExecCommand(
      $pod_id,
      $env,
      'rm -rf $DRUPAL_ROOT/sites/default/*'
    );
    $io->say('Done!');
  }

}
