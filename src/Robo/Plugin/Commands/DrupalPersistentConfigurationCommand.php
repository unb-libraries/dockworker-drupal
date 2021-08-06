<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;
use Robo\Robo;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Defines the commands used to interact with persistent config in Drupal.
 */
class DrupalPersistentConfigurationCommand extends DockworkerDeploymentCommands {

  use DrupalKubernetesPodTrait;

  protected $drupalPersistentConfigElements;
  protected $drupalPersistentConfigHasChanges = FALSE;

  /**
   * Sets the Drupal persistent configuration elements from config.
   *
   * @hook pre-init
   */
  public function setDrupalPersistentConfigElements() {
    $this->drupalPersistentConfigElements = Robo::Config()->get('dockworker.drupal.persistent_config');
  }

  /**
   * Synchronize persistent configurations elements from live instances.
   *
   * @param string $env
   *   The environment to obtain the logs from. Defaults to 'prod'.
   *
   * @command deployment:drupal:sync-persistent-config
   * @usage deployment:drupal:sync-persistent-config
   *
   * @throws \Exception
   *
   * @kubectl
   */
  public function synchronizeCommitPersistentConfigElementsFromLive($env = 'prod') {
    if (!empty($this->drupalPersistentConfigElements)) {
      $this->setRunOtherCommand("local:config:remote-sync $env");
      foreach($this->drupalPersistentConfigElements as $persistent_config_mask => $persistent_config_description) {
        $this->commitPersistentConfigChanges($persistent_config_mask, $persistent_config_description);
      }
    }
  }

  /**
   * Commits any changes in the local config-yml directory to persistent config.
   *
   * @param string $persistent_config_mask
   *   The configuration file mask corresponding to the configuration.
   * @param string $persistent_config_description
   *   A short description of the configuration elements.
   */
  protected function commitPersistentConfigChanges($persistent_config_mask, $persistent_config_description) {
    $this->stageConfigurationMatchingMask($persistent_config_mask, $persistent_config_description);
    if ($this->drupalPersistentConfigHasChanges) {
      $this->say("Committing Changes...");
      $this->repoGit->commit("Update persistent config for $persistent_config_description [skip ci]", ['--no-verify']);
    }
  }

  /**
   * Stages any changes in the local repository to persistent config files.
   *
   * @param string $persistent_config_mask
   *   The configuration file mask corresponding to the configuration.
   * @param string $persistent_config_description
   *   A short description of the configuration elements.
   */
  protected function stageConfigurationMatchingMask($persistent_config_mask, $persistent_config_description) {
    $this->say("Searching for $persistent_config_description configuration objects...");
    $persistent_config_files = glob("{$this->repoRoot}/config-yml/$persistent_config_mask");
    if (!empty($persistent_config_files)) {
      $this->drupalPersistentConfigHasChanges = TRUE;
      $this->say("Adding new/changed $persistent_config_description configuration objects...");
      $progressBar = new ProgressBar($this->output, count($persistent_config_files));
      $progressBar->start();
      foreach ($persistent_config_files as $persistent_config_file) {
        $this->repoGit->addFile($persistent_config_file);
        $progressBar->advance();
      }
      $progressBar->finish();
      $this->io()->newLine();
    }
  }

}
