<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerCommands;

/**
 * Provides commands for rebuilding the cache in a drupal application.
 */
class DockworkerDrupalCacheRebuildCommands extends DockworkerCommands
{
    use DockerContainerExecTrait;

    /**
     * Rebuilds the cache in a Drupal application.
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
