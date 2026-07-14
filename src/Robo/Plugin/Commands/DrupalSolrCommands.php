<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;
use Dockworker\Drupal\DrushCommandTrait;

/**
 * Provides commands for interacting with a Drupal solr stack.
 */
class DrupalSolrCommands extends DockworkerDrupalCommands
{
    use DrushCommandTrait;

    /**
     * Reindexes the Solr index after a snapshot install.
     *
     * @param mixed $result
     *   The result of the command.
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *   The command data.
     *
     * @hook post-command snapshot:install
     */
    public function reindexSolrAfterSnapshot(
        $result,
        CommandData $commandData
    ): void {
        if (!$this->instanceHasSolr()) {
            return;
        }
        $env = $commandData->input()->getOption('target-env');
        $this->initOptions();
        $this->initDockworkerIO();
        $this->preInitDockworkerPersistentDataStorageDir();

        $this->dockworkerIO->block('Often after a snapshot install, the Solr indices within need to be rebuilt and reindexed.');
        if (
            $this->dockworkerIO->confirm('Would you like to rebuild and reindex all Solr indices now?')
        ) {
            $this->rebuildReindexSolrIndices(['env' => $env]);
        }
    }

    /**
     * Rebuilds tracking data and reindexes all solr indices.
     *
     * @param mixed[] $options
     *   The options passed to the command.
     *
     * @option string $env
     *   The environment to rebuild and reindex in.
     *
     * @command solr:rebuild-reindex
     * @aliases solr-rebuild-reindex
     * @usage --env=prod
     */
    public function rebuildReindexSolrIndices(
        array $options = [
            'env' => 'local',
        ]
    ): void {
        if (!$this->instanceHasSolr()) {
            $this->dockworkerIO->warning('No Solr instances found for this application.');
            return;
        }
        $this->dockworkerIO->title("Rebuilding Solr Indices");
        $this->executeDrushCommands(
            $this->dockworkerIO,
            $options['env'],
            [
                'Rebuilding Tracker Data' => ['search-api:rebuild-tracker'],
                'Indexing Data' => ['search-api:index'],
            ]
        );
    }

    /**
     * Checks if the instance has a Solr service.
     *
     * @return bool
     *   TRUE if the instance has a Solr service, FALSE otherwise.
     */
    protected function instanceHasSolr(): bool
    {
        if (!empty($this->config->get('dockworker.endpoints.deployments.drupal-solr-lib-unb-ca'))) {
            return true;
        }
        return false;
    }
}
