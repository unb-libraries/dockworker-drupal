<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerShellCommands;

/**
 * Provides commands for manipulating configuration in a Drupal application.
 */
class DockworkerDrupalConfigurationCommands extends DockworkerShellCommands
{
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
                    ],
                    [
                        'command' => [
                            'chmod',
                            '-R',
                            'g+w',
                            '/app/configuration',
                        ],
                        'message' => 'Adding group write permissions',
                    ],
                ],
                $this->dockworkerIO,
                'Exporting Configuration',
            );
        } else {
            // @TODO Add deployed support.
            $this->dockworkerIO->say(
                'Configuration export is currently only supported for local environments.'
            );
            exit(1);
        }
    }
}
