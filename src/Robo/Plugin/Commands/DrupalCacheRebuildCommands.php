<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;

/**
 * Provides commands for rebuilding the cache in a drupal application.
 */
class DrupalCacheRebuildCommands extends DockworkerDrupalCommands
{
    use DockerContainerExecTrait;

    /**
     * Rebuilds this application's Drupal cache.
     *
     * @param mixed[] $options
     *   The options passed to the command.
     *
     * @option string $env
     *   The environment to rebuild the cache in.
     *
     * @command drupal:cr
     * @aliases cr
     * @usage --env=prod
     */
    public function rebuildDrupalCache(
        array $options = [
            'env' => 'local',
        ]
    ): void {
        $cmd = ['/scripts/clearDrupalCache.sh'];
        $this->executeContainerCommand(
            $options['env'],
            $cmd,
            $this->dockworkerIO,
            'Rebuilding cache',
            sprintf(
                'Rebuilding cache in %s',
                $options['env']
            )
        );
    }
}
