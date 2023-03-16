<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\IO\DockworkerIO;

/**
 * Provides commands for running drush in the application's deployed resources.
 */
class DockworkerDrushCommands extends DockworkerShellCommands
{
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
        $this->executeDrushCommand(
            $options['env'],
            $args,
            $this->dockworkerIO
        );
    }

    /**
     * Executes a drush command in the application.
     *
     * @param string $env
     *   The environment to run the command in.
     * @param string $command
     *   The command to run.
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     *
     * @option string $env
     *   The environment to run the command in.
     */
    protected function executeDrushCommand(
        string $env,
        string $command,
        DockworkerIO $io
    ): void {
        $this->initShellCommand($env);
        $container = $this->getDeployedContainer($env);
        $io->title('Drush');
        $io->say("[$env] Running 'drush $command'...");
        $cmd_base = [
            'drush',
        ];
        $args = explode(' ', $command);
        $container->run(
            array_merge($cmd_base, $args),
            $io
        );
    }
}
