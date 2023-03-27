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
     * Exports this application's Drupal configuration to the repository.
     *
     * @option string $env
     *   The environment to export the configuration from.
     * @option string $no-devel-clear
     *   Do not remove devel from the exported configuration.
     *
     * @command drupal:config:export
     * @aliases write-config
     * @usage --env=prod
     */
    public function exportDrupalConfiguration(
        array $options = [
            'env' => 'local',
            'no-dev-clear' => false,
        ]
    ): void {
        if ($options['env'] === 'local') {
            $cmd_set = [
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
            ];
            if (!$options['no-dev-clear']) {
                $cmd_set[] = [
                    'command' => [
                        'rm',
                        '-rf',
                        '/app/configuration/devel.settings.yml',
                        '/app/configuration/devel.toolbar.settings.yml',
                        '/app/configuration/system.menu.devel.yml',
                    ],
                    'message' => 'Removing devel configuration',
                    'use_tty' => false,
                ];
                $cmd_set[] = [
                    'command' => [
                        'sed',
                        '-i',
                        '/devel\: 0/d',
                        '/app/configuration/core.extension.yml',
                    ],
                    'message' => '',
                    'use_tty' => false,
                ];
            }
            $this->executeContainerCommandSet(
                'local',
                $cmd_set,
                $this->dockworkerIO,
                'Exporting Configuration',
            );
        } else {
            [$container, $cmd] = $this->executeContainerCommand(
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
