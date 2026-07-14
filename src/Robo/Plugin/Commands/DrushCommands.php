<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\Drupal\DrushCommandTrait;

/**
 * Provides commands for running drush in the application's deployed resources.
 */
class DrushCommands extends DockworkerDrupalCommands
{
    use DrushCommandTrait;

    /**
     * Runs a generic drush within this application.
     *
     * @param string $args
     *   The command and arguments to pass to drush.
     * @param string[] $options
     *   An array of options to pass to the command.
     *
     * @option string $env
     *   The environment to run the command in.
     *
     * @command drupal:drush
     * @aliases drush
     * @usage --env=prod -- uli --name=robyn
     */
    public function runGenericDrushCommand(
        string $args,
        array $options = [
            'env' => 'local',
        ]
    ): void {
        $args_array = explode(' ', $args);
        $this->executeDrushCommand(
            $this->dockworkerIO,
            $options['env'],
            $args_array
        );
    }
}
