<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DrupalUliCommands extends DockworkerDrupalCommands
{
    use DockerContainerExecTrait;

    /**
     * Generates a Drupal user login link for this application.
     *
     * @param mixed[] $options
     *   The options passed to the command.
     *
     * @option string $env
     *   The environment to generate the ULI link in.
     * @option string $uid
     *   The uid of the account to generate the ULI for. Defaults to uid 1
     *
     * @command drupal:uli
     * @aliases uli
     * @usage --env=prod
     */
    public function generateDrupalUli(
        array $options = [
            'env' => 'local',
            'uid' => '1',
        ]
    ): void {
        $cmd = [
            '/scripts/drupalUli.sh',
            $options['uid']
        ];
        $this->executeContainerCommand(
            $options['env'],
            $cmd,
            $this->dockworkerIO,
            'Generating ULI',
            sprintf(
                'Generating ULI in %s for UID %s',
                $options['env'],
                $options['uid']
            )
        );
    }
}
