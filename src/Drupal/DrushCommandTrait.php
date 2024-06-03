<?php

namespace Dockworker\Drupal;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\IO\DockworkerIO;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides methods to interact with Drush within the application.
 */
trait DrushCommandTrait
{
    use DockerContainerExecTrait;
    use DockworkerIOTrait;

   /**
     * Executes a drush command in this application.
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
        array $command,
        string $message = ''
    ): void {
        $cmd_base = [
            'drush',
        ];
        $this->executeContainerCommand(
            $env,
            array_merge($cmd_base, $command),
            $this->dockworkerIO,
            $message,
            sprintf(
                "[%s] Running 'drush %s'...",
                $env,
                implode(' ', $command)
            )
        );
    }

    /**
     * Executes a set of drush commands in this application.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     * @param string $env
     *   The environment to run the command in.
     * @param array[] $command
     *   An array of commands to run.
     *
     * @option string $env
     *   The environment to run the command in.
     */
    protected function executeDrushCommands(
        DockworkerIO $io,
        string $env,
        array $commands
    ): void {
        foreach ($commands as $message => $command) {
            $this->executeDrushCommand($io, $env, $command, $message);
        }
    }
}
