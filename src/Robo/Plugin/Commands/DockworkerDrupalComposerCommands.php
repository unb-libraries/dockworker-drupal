<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerComposeTrait;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DockworkerDrupalComposerCommands extends DockworkerApplicationDeployCommands
{
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
        if ($options['env'] === 'local') {
            $this->composeApplicationCopyFile(
                "$this->applicationName:/app/html/composer.lock",
                "$this->applicationRoot/build/composer.lock",
            );
        } else {
            // @TODO Add deployed support.
            $this->dockworkerIO->say(
                'Lockfile export is currently only supported for local environments.'
            );
            exit(1);
        }
    }
}
