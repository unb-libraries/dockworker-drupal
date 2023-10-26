<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DrupalUliCommands extends DockworkerDrupalCommands
{
    use DockerContainerExecTrait;

    /**
     * Informs the user of the ULI after a snapshot install.
     *
     * @param mixed $result
     *   The result of the command.
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *   The command data.
     *
     * @hook post-command snapshot:install
     */
    public function displayUliAfterSnapshot(
        $result,
        CommandData $commandData
    ): void {
        $env = $commandData->input()->getOption('env');
        $this->initOptions();
        $this->initDockworkerIO();
        $this->preInitDockworkerPersistentDataStorageDir();

        $this->generateDrupalUli(
            [
                'env' => $env,
                'uid' => '1',
            ]
        );
    }

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
