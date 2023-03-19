<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerCommands;
use Dockworker\IO\DockworkerIO;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides commands for running drush in the application's deployed resources.
 */
class DockworkerDrushCommands extends DockworkerCommands
{
    use DockerContainerExecTrait;

    /**
     * Runs a generic drush command passed as arguments.
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

    /**
     * Executes a drush command in the application.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     * @param string $env
     *   The environment to run the command in.
     * @param string[] $command
     *   The command to run.
     *
     * @option string $env
     *   The environment to run the command in.
     */
    protected function executeDrushCommand(
        DockworkerIO $io,
        string $env,
        array $command
    ): void {
        $io->title('Drush');
        $cmd_base = [
            'drush',
        ];
        $this->executeContainerCommand(
            $env,
            array_merge($cmd_base, $command),
            $this->dockworkerIO,
            'Generating ULI',
            sprintf(
                "[%s] Running 'drush %s'...",
                $env,
                implode(' ', $command)
            )
        );
    }
}
