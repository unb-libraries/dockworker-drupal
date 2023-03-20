<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerComposeTrait;
use Dockworker\Docker\DockerContainerExecTrait;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DockworkerDrupalComposerCommands extends DockworkerApplicationDeployCommands
{
    use DockerContainerExecTrait;
    use DockerComposeTrait;

    /**
     * Copies the composer lockfile from the application.
     *
     * @option string $env
     *   The environment to copy the file from.
     *
     * @command drupal:composer:write-lock
     * @aliases write-lock
     * @usage --env=prod
     */
    public function copyDrupalComposerLockfile(
        array $options = [
            'env' => 'local',
        ]
    ): void {
        $container = $this->initGetDeployedContainer(
            $this->dockworkerIO,
            $options['env']
        );
        $container->copyFrom(
            $this->dockworkerIO,
            '/app/html/composer.lock',
            $this->applicationRoot . '/build/composer.lock'
        );
    }
}
