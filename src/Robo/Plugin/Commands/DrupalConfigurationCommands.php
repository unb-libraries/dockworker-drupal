<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;

/**
 * Provides commands for manipulating configuration in a Drupal application.
 */
class DrupalConfigurationCommands extends DockworkerDrupalCommands
{
    use DockerContainerExecTrait;

    /**
     * Exports configuration from the running application to the repository.
     *
     * @option string $env
     *   The environment to export the configuration from.
     *
     * @command drupal:config:export
     * @aliases write-config
     * @usage --env=prod
     */
    public function exportDrupalConfiguration(
        array $options = [
            'env' => 'local',
        ]
    ): void {
        if ($options['env'] === 'local') {
            $this->executeContainerCommandSet(
                'local',
                [
                    [
                        'command' => [
                            '/scripts/configExport.sh'
                        ],
                        'message' => 'Exporting configuration from local',
                    ],
                    [
                        'command' => [
                            'chgrp',
                            '-R',
                            $this->userGid,
                            '/app/configuration',
                        ],
                        'message' => 'Assigning ownership to local user group',
                        'use_tty' => false,
                    ],
                    [
                        'command' => [
                            'chmod',
                            '-R',
                            'g+w',
                            '/app/configuration',
                        ],
                        'message' => 'Adding group write permissions',
                        'use_tty' => false,
                    ],
                ],
                $this->dockworkerIO,
                'Exporting Configuration',
            );
        } else {
            $container = $this->executeContainerCommand(
                $options['env'],
                ['/scripts/configExport.sh'],
                $this->dockworkerIO,
                'Exporting Configuration',
                sprintf(
                    'Exporting configuration from %s',
                    $options['env']
                )
            );
            $container->copyFrom(
                $this->dockworkerIO,
                '/app/configuration',
                $this->applicationRoot . '/config-yml'
            );
        }
    }
}
