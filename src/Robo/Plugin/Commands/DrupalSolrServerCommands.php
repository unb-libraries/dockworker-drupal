<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DrupalKubernetesPodTrait;
use Dockworker\GitHubPackageDownloadTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;
use Robo\Symfony\ConsoleIO;

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
  const MSG_INITIALIZING_PODS = 'Discovering Solr and Drupal pods for %s [%s]';
  const MSG_REINDEXING_INDEX = 'Indexing all unindexed items in all SOLR indices on %s - %s [%s]';
  const MSG_CLEARING_REINDEXING_INDEX = 'Clearing and re-indexing all SOLR indices on %s - %s [%s]';
  const MSG_SOLR_TUNNELING = 'Opening tunnel to pod %s';
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
   * @var string
   */
  private $drupalSolrServerPodEnv;

  /**
   * The solr server pod ID.
   *
   * @var string
   */
  private $drupalSolrServerPodId;

  /**
   * The local Drupal solr config directory.
   *
   * @var bool
   */
  private $drupalLocalSolrConfigDir;

  /**
   * The remote Drupal solr config directory.
   *
   * @var string
   */
  private $drupalRemoteSolrConfigDir;

  /**
   * Clears all data within this application's deployment solr instance, and reindexes all content on its k8s deployment.
   *
   * @param string $env
   *   The environment to clear and reindex.
   *
   * @command solr:reindex:deployed
   * @throws \Exception
   *
   * @usage dev
   *
   * @kubectl
   */
  public function clearReindexAllIndices($env) {
    $this->initDrupalPodInstances($env);
    if (!empty($this->kubernetesCurPods)) {
      $first_drupal_pod_id = reset($this->kubernetesCurPods);
      $this->setUpInstanceIndices($first_drupal_pod_id);
      if (!empty($this->drupalSolrServerIndices)) {
        $this->io()->title(sprintf(
          self::MSG_CLEARING_REINDEXING_INDEX,
          $index_name,
          $this->deployedK8sResourceName,
          $this->deployedK8sResourceNameSpace
        ));
        $this->reindexSolrIndex($first_drupal_pod_id);
      }
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_PODS_IN_DEPLOYMENT,
          $this->deployedK8sResourceName,
          $this->deployedK8sResourceNameSpace
        )
      );
    }
  }

  /**
   * Indexes all unindexed content on the application's k8s deployment.
   *
   * @param string $env
   *   The environment to index.
   *
   * @command solr:index:deployed
   * @throws \Exception
   *
   * @usage dev
   *
   * @kubectl
   */
  public function indexAllIndices($env) {
    $this->initDrupalPodInstances($env);
    if (!empty($this->kubernetesCurPods)) {
      $first_drupal_pod_id = reset($this->kubernetesCurPods);
      $this->setUpInstanceIndices($first_drupal_pod_id);
      if (!empty($this->drupalSolrServerIndices)) {
        $this->io()->title(sprintf(
          self::MSG_REINDEXING_INDEX,
          $index_name,
          $this->deployedK8sResourceName,
          $this->deployedK8sResourceNameSpace
        ));
        $this->indexAllUnindexedItems($first_drupal_pod_id);
      }
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_PODS_IN_DEPLOYMENT,
          $this->deployedK8sResourceName,
          $this->deployedK8sResourceNameSpace
        )
      );
    }
  }

  /**
   * Generates a clickable URL to this application's k8s deployment solr admin panel.
   *
   * This allows local use of the solr admin interface, which would not be
   * accessible otherwise.
   *
   * @param string $env
   *   The environment to update.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $solr-deployment-name
   *   The k8s deploy name of solr. Defaults to drupal-solr-lib-unb-ca.
   *
   * @command solr:uli:deployed
   * @throws \Exception
   *
   * @usage prod
   *
   * @kubectl
   */
  public function openTunnelToSolrDeployment($env, array $options = ['solr-deployment-name' => 'drupal-solr-lib-unb-ca']) {
    $this->initDrupalPodInstances($env);
    $first_drupal_pod_id = reset($this->kubernetesCurPods);
    $this->setUpInstanceIndices($first_drupal_pod_id);

    if (!empty($this->drupalSolrServerIndices)) {
      foreach ($this->drupalSolrServerIndices as $sapi_index) {
        $this->say("Found sapi index for $this->instanceName: $sapi_index");
      }
      $this->setSolrServerPodId($options);
      $this->solrServerInit();
      $this->io()->title(sprintf(
        self::MSG_SOLR_TUNNELING,
        $this->drupalSolrServerPodId
      ));
      $this->say('Opening tunnel with kubectl...');
      $this->say('Click to launch the solr admin panel : http://localhost:18983/solr');
      $this->say('When finished, press CTRL-C to exit.');
      $this->io()->newLine();
      $command_string = "{$this->kubeCtlBin} port-forward $this->drupalSolrServerPodId 18983:8983 --namespace=$this->deployedK8sResourceNameSpace";
      passthru($command_string);
    }
    else {
      $this->say("No solr indices found in Drupal configuration for $this->deployedK8sResourceNameSpace. Doing nothing.");
    }
  }

  /**
   * Clears all data within this application's deployment solr instance, updates its solr configuration, and reindexes all content on its k8s deployment.
   *
   * This command pulls config from unb-libraries/docker-solr-drupal:8.x-4.x.
   * Beware : This command removes the solr data and re-builds it.
   *
   * @param string $env
   *   The environment to update.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $config-repo-name
   *   The github repository name that contains the deployment configuration.
   * @option $config-repo-owner
   *   The github repository owner that contains the deployment configuration.
   * @option $config-repo-path
   *   The github repository path that contains the deployment configuration.
   * @option $config-repo-refspec
   *   The github repository refspec that contains the deployment configuration.
   * @option $no-reindex
   *   Do not reindex the drupal instance after updating the configuration.
   * @option $solr-deployment-name
   *   The k8s deployment name of the solr server.
   *
   * @command solr:config:update:deployed
   * @throws \Exception
   *
   * @usage dev
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
        $this->setRunOtherCommand("solr:reindex:deployed $env");
      }
    }
    else {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_PODS_IN_DEPLOYMENT,
          $this->deployedK8sResourceName,
          $this->deployedK8sResourceNameSpace
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
    $this->io()->title(sprintf(
      self::MSG_INITIALIZING_PODS,
      $this->deployedK8sResourceName,
      $this->deployedK8sResourceNameSpace
    ));
    $this->k8sInitSetupPods($env, 'deployment', 'Reindex');
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
      $this->kubernetesPodParentResourceNamespace,
      'sapi-l --field=name'
    );

    if (empty($this->drupalSolrServerIndices)) {
      throw new DockworkerException(
        sprintf(
          self::ERROR_NO_INDICES_IN_INSTANCE,
          $this->deployedK8sResourceName,
          $this->deployedK8sResourceNameSpace
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
  private function reindexSolrIndex($pod_id) {
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      'search-api:clear'
    );
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      'search-api:reset-tracker'
    );
    $this->indexAllUnindexedItems($pod_id);
  }

  /**
   * Indexes all unindexed items in solr indices defined in the Drupal instance.
   *
   * @param string $pod_id
   *   The Drupal k8s pod ID to target.
   */
  private function indexAllUnindexedItems($pod_id) {
    $this->drupalSolrServerIndices = $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      "search-api:index"
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
    $this->kubernetesSetupPods(
      $options['solr-deployment-name'],
      'deployment',
      $this->deployedK8sResourceNameSpace,
      'SOLR Pod Setup'
    );
    $this->drupalSolrServerPodId = $this->kubernetesGetLatestPod();
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

  /**
   * Clears all data within this application's deployment solr instance.
   *
   * @param string $env
   *   The environment to update.
   *
   * @command solr:data:clear
   * @throws \Exception
   *
   * @usage dev
   *
   * @kubectl
   */
  public function deleteAllDrupalSolrIndexData(ConsoleIO $io, string $env) {
    $pod_id = $this->k8sGetLatestPod($env, 'deployment', 'Delete Data');
    $this->setUpInstanceIndices($pod_id);
    $io->title('Clearing All Indices');
    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      'search-api:clear'
    );
    $this->kubernetesPodDrushCommand(
      $pod_id,
      $this->kubernetesPodParentResourceNamespace,
      'search-api:reset-tracker'
    );
    $io->say('Done!');
    }

}
