<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\Robo\Plugin\Commands\DrushCommands;
use Dockworker\IO\DockworkerIO;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides commands for running drush in the application's deployed resources.
 */
class DrushSqlDumpCommands extends DrushCommands
{
    /**
     * Runs a drush sql-dump command within this application.
     *
     * @param string[] $options
     *   An array of options to pass to the command.
     *
     * @option string $env
     *   The environment to run the command in.
     *
     * @command drupal:drush:sql-dump
     * @aliases sql-dump
     * @usage --env=prod
     */
    public function runDrushSqlDumpCommand(
        array $options = [
            'env' => 'local',
        ]
    ): void {
        $this->executeDrushCommand(
            $this->dockworkerIO,
            $options['env'],
            [
                'sql-dump',
                '--extra-dump=--no-tablespaces',
                '--structure-tables-list="accesslog,batch,cache,cache_*,ctools_css_cache,ctools_object_cache,flood,search_*,history,queue,semaphore,sessions,watchdog"',
            ]
        );
    }
}
