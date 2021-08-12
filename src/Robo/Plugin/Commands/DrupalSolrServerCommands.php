<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\GitHubPackageDownloadTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class DrupalSolrServerCommands extends DockworkerDeploymentCommands {

  use DrupalKubernetesPodTrait;
  use GitHubPackageDownloadTrait;

  const ERROR_INDEX_NOT_FOUND_IN_SOLR_HOME = 'Index %s not found in SOLR_HOME (%s) [%s]';
  const ERROR_NO_INDICES_IN_INSTANCE = 'No solr indices found for %s [%s]';
  const ERROR_NO_SOLR_SERVER_PODS = 'No solr server pods found for %s [%s]';
  const ERROR_SOLR_HOME_NOT_SOLR_DATA_DIR = 'SOLR_HOME does not appear to be a solr data directory (%s) [%s]';
  const MSG_CONFIRMED_CORE_CREATED = 'Created SOLR core %s - %s [%s]';
  const MSG_CONFIRMED_CORE_HAS_DATA = 'Data exists in SOLR core %s - %s [%s]';
  const MSG_CONFIRMED_CORE_REMOVED = 'Removed SOLR core %s - %s [%s]';
  const MSG_DONE = 'Done!';
  const MSG_DRUPAL_REINDEXING = 'Reindexing Drupal Instance %s';
  const MSG_INITIALIZING_PODS = 'Discovering Drupal pods for %s [%s]';
  const MSG_REINDEXING_INDEX = 'Clearing and re-indexing SOLR index %s - %s [%s]';
  const MSG_SOLR_UPDATING = 'Updating SOLR server pod %s';

  /**
   * The Drupal solr indices to operate on.
   *
   * @var bool
   */
  private $drupalSolrServerIndices = [];

  /**
   * The solr server pod environment.
   *
   * @var bool
   */
  private $drupalSolrServerPodEnv = NULL;

  /**
   * The solr server pod ID.
   *
   * @var bool
   */
  private $drupalSolrServerPodId = NULL;

  /**
   * The local Drupal solr config directory.
   *
   * @var bool
   */
  private $drupalLocalSolrConfigDir = NULL;

  /**
   * The remote Drupal solr config directory.
   *
   * @var bool
   */
  private $drupalRemoteSolrConfigDir = NULL;

  /**
   * Clears all Drupal deployment solr indices and re-indexes the data.
   *
   * @param string $env
   *   The environment to clear and reindex.
   *
   * @command deployment:drupal:solr:clear-reindex-all
   * @throws \Exception
   *
   * @usage deployment:drupal:solr:clear-reindex-all dev
   *
   * @kubectl
   */
  public function clearReindexAllIndices($env) {
    $this->initDrupalPodInstances($env);
    if (!empty($this->kubernetesCurPods)) {
      $first_drupal_pod_id = reset($this->kubernetesCurPods);
      $this->setUpInstanceIndices($first_drupal_pod_id);
      foreach ($this->drupalSolrServerIndices as $index_name) {
        $this->io()->title(sprintf(
          self::MSG_REINDEXING_INDEX,
          $index_name,
          $this->deploymentK8sName,
          $this->deploymentK8sNameSpace
        ));
        $this->reindexSolrIndex($first_drupal_pod_id, $index_name);
      }
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_PODS_IN_DEPLOYMENT,
          $this->deploymentK8sName,
          $this->deploymentK8sNameSpace
        )
      );
    }
  }

  /**
   * Updates the associated solr server conf with the latest available version.
   *
   * This command pulls config from unb-libraries/docker-solr-drupal:8.x-4.x.
   * Beware : This command removes the solr data and re-builds it.
   *
   * @param string $env
   *   The environment to update.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option string $config-repo-name
   *   The github repository name that contains the deployment configuration.
   * @option string $config-repo-owner
   *   The github repository owner that contains the deployment configuration.
   * @option string $config-repo-path
   *   The github repository path that contains the deployment configuration.
   * @option string $config-repo-refspec
   *   The github repository refspec that contains the deployment configuration.
   * @option bool $no-reindex
   *   Do not reindex the drupal instance after updating the configuration.
   * @option string $solr-deployment-name
   *   The k8s deployment name of the solr server.
   *
   * @command deployment:drupal:solr:update-config
   * @throws \Exception
   *
   * @usage deployment:drupal:solr:update-config dev
   *
   * @kubectl
   */
  public function updateSolrConfigurationWithLatest($env, array $options = ['config-repo-name' => 'docker-solr-drupal', 'config-repo-owner' => 'unb-libraries', 'config-repo-path' => '/data/conf', 'config-repo-refspec' => '8.x-4.x', 'no-reindex' => FALSE, 'solr-deployment-name' => 'drupal-solr-lib-unb-ca']) {
    $this->initDrupalPodInstances($env);
    if (!empty($this->kubernetesCurPods)) {
      $first_drupal_pod_id = reset($this->kubernetesCurPods);
      $this->setUpInstanceIndices($first_drupal_pod_id);
      $this->setSolrServerPodId($options);
      $this->solrServerInit();

      $this->io()->title(sprintf(
        self::MSG_SOLR_UPDATING,
        $this->drupalSolrServerPodId
      ));

      $this->verifySolrServerIndicesExistHaveData();
      $this->initDrupalSolrConfig($options);
      $this->copyDrupalSolrConfigToPod();

      foreach ($this->drupalSolrServerIndices as $core_name) {
        $this->removeSolrCore($core_name);
        $this->createSolrCore($core_name, $this->drupalRemoteSolrConfigDir);
      }

      if ($options['no-reindex'] != TRUE) {
        $this->io()->title(sprintf(
          self::MSG_DRUPAL_REINDEXING,
          $first_drupal_pod_id
        ));
        $this->setRunOtherCommand("deployment:drupal:solr:clear-reindex-all $env");
      }
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_PODS_IN_DEPLOYMENT,
          $this->deploymentK8sName,
          $this->deploymentK8sNameSpace
        )
      );
    }
  }

  /**
   * Initializes all required parameters for related Drupal pods.
   *
   * @param string $env
   *   The k8s deploy environment to initialize.
   *
   * @throws \Exception
   */
  private function initDrupalPodInstances($env) {
    $this->drupalSolrServerPodEnv = $env;
    $this->deploymentCommandInit($this->repoRoot, $this->drupalSolrServerPodEnv);
    $this->kubernetesPodNamespace = $this->deploymentK8sNameSpace;

    $this->io()->title(sprintf(
      self::MSG_INITIALIZING_PODS,
      $this->deploymentK8sName,
      $this->deploymentK8sNameSpace
    ));
    $this->kubernetesSetupPods($this->deploymentK8sName, "Reindex");
    $this->io()->text(self::MSG_DONE);
  }

  /**
   * Sets up the solr cores to target from the Drupal pod defined solr indices.
   *
   * @param string $pod_id
   *   The Drupal instance ID to target.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function setUpInstanceIndices($pod_id) {
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      'sapi-l --field=name'
    );

    if (empty($this->drupalSolrServerIndices)) {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_INDICES_IN_INSTANCE,
          $this->deploymentK8sName,
          $this->deploymentK8sNameSpace
        )
      );
    }
  }

  /**
   * Clears and reindexes all solr indices defined in the Drupal instance.
   *
   * @param string $pod_id
   *   The Drupal k8s pod ID to target.
   */
  private function reindexSolrIndex($pod_id, $core_name) {
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      'search-api:clear'
    );
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      'search-api:reset-tracker'
    );
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodNamespace,
      'search-api:index'
    );
  }

  /**
   * Sets the solr server k8s pod ID to target in subsequent commands.
   *
   * @param string $env
   *   The k8s deploy environment to query.
   * @param string[] $options
   *   An associative array of options passed to the robo command.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function setSolrServerPodId($options) {
    $solr_pods = $this->kubernetesGetMatchingPods($options['solr-deployment-name'], $this->drupalSolrServerPodEnv);
    if (empty($solr_pods[0])) {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_SOLR_SERVER_PODS,
          $options['solr-deployment-name'],
          $this->drupalSolrServerPodEnv
        )
      );
    }
    $this->drupalSolrServerPodId = reset($solr_pods);
  }

  /**
   * Initializes requirements and constraints for the solr instance.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function solrServerInit() {
    $this->verifySolrInstalled();
  }

  /**
   * Verifies if solr is installed at $SOLR_HOME as expected.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function verifySolrInstalled() {
    $solr_home_dir_list = $this->kubernetesPodExecCommand($this->drupalSolrServerPodId, $this->drupalSolrServerPodEnv, 'ls $SOLR_HOME');
    if (!in_array('solr.xml', $solr_home_dir_list)) {
      throw new DockworkerException(
        sprintf(
          self::ERROR_SOLR_HOME_NOT_SOLR_DATA_DIR,
          $this->drupalSolrServerPodId,
          $this->drupalSolrServerPodEnv
        )
      );
    }
  }

  /**
   * Verifies if the set indices have data stored on the solr server k8s pod.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function verifySolrServerIndicesExistHaveData() {
    $solr_home_dir_list = $this->kubernetesPodExecCommand(
      $this->drupalSolrServerPodId,
      $this->drupalSolrServerPodEnv,
      'ls $SOLR_HOME'
    );
    foreach ($this->drupalSolrServerIndices as $index_name) {
      if (!in_array($index_name, $solr_home_dir_list)) {
        throw new DockworkerException(
          sprintf(
            self::ERROR_INDEX_NOT_FOUND_IN_SOLR_HOME,
            $index_name,
            $this->drupalSolrServerPodId,
            $this->drupalSolrServerPodEnv
          )
        );
      }
      $this->io()->text(
        sprintf(
          self::MSG_CONFIRMED_CORE_HAS_DATA,
          $index_name,
          $this->drupalSolrServerPodId,
          $this->drupalSolrServerPodEnv
        )
      );
    }
  }

  /**
   * Initializes the parameters used when downloading the solr configuration.
   *
   * @param string[] $options
   *   An associative array of options passed to the robo command.
   */
  private function initDrupalSolrConfig($options) {
    $this->drupalLocalSolrConfigDir = $this->downloadGithubRepositoryContents(
      $options['config-repo-owner'],
      $options['config-repo-name'],
      $options['config-repo-refspec'],
      $options['config-repo-path']
    );
    $date = new \DateTime();
    $timestamp = $date->getTimestamp();
    $this->drupalRemoteSolrConfigDir = "/tmp/drupal_solr_config-$timestamp";
  }

  /**
   * Copies the solr config from the local environment to the solr k8s pod.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function copyDrupalSolrConfigToPod() {
    $this->kubernetesPodFileCopyCommand(
      $this->drupalSolrServerPodEnv,
      $this->drupalLocalSolrConfigDir,
      "{$this->drupalSolrServerPodId}:{$this->drupalRemoteSolrConfigDir}"
    );
  }

  /**
   * Removes a solr core from the solr instance.
   *
   * @param string $core_name
   *   The core name to remove.
   */
  private function removeSolrCore($core_name) {
    $solr_home_dir_list = $this->kubernetesPodExecCommand(
      $this->drupalSolrServerPodId,
      $this->drupalSolrServerPodEnv,
      'solr delete -c ' . $core_name
    );
    $this->io()->text(
      sprintf(
        self::MSG_CONFIRMED_CORE_REMOVED,
        $core_name,
        $this->drupalSolrServerPodId,
        $this->drupalSolrServerPodEnv
      )
    );
  }

  /**
   * Creates a new core on the solr instance.
   *
   * @param string $core_name
   *   The core name to create.
   * @param string $config_source
   *   The path inside the container that contains the core config to use.
   */
  private function createSolrCore($core_name, $config_source) {
    $this->kubernetesPodExecCommand(
      $this->drupalSolrServerPodId,
      $this->drupalSolrServerPodEnv,
      'mkdir -p $SOLR_HOME/' . $core_name
    );
    $this->kubernetesPodExecCommand(
      $this->drupalSolrServerPodId,
      $this->drupalSolrServerPodEnv,
      "cp -r $config_source " . '$SOLR_HOME/' . "$core_name/conf"
    );
    $this->kubernetesPodExecCommand(
      $this->drupalSolrServerPodId,
      $this->drupalSolrServerPodEnv,
      'solr create -c ' . $core_name
    );
    $this->io()->text(
      sprintf(
        self::MSG_CONFIRMED_CORE_CREATED,
        $core_name,
        $this->drupalSolrServerPodId,
        $this->drupalSolrServerPodEnv
      )
    );
  }

}
