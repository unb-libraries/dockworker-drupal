<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DrupalComposerCommands extends DockworkerDrupalCommands
{
    use DockerContainerExecTrait;

    /**
     * Copies the composer lockfile from an application container.
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
        $this->dockworkerIO->title('Copying Lockfile');
        $container->copyFrom(
            $this->dockworkerIO,
            '/app/html/composer.lock',
            $this->applicationRoot . '/build/composer.lock'
        );
        $this->dockworkerIO->say('Done!');
    }
}
