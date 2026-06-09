<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Dockworker\Docker\DeployedLocalResourcesTrait;
use Dockworker\Docker\DockerComposeTrait;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDaemonCommands;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides commands for interacting with a Drupal deployment locally.
 */
class DrupalDaemonLocalDeployCommands extends DockworkerDaemonCommands
{
    use DeployedLocalResourcesTrait;
    use DockerComposeTrait;
    use DockerContainerExecTrait;
    use DockworkerIOTrait;

    /**
     * Ensure that the local application deployment is tidied up for a restart.
     *
     * @hook on-event dockworker-pre-local-restart-actions
     */
    public function preRestartDrupalActions(): void
    {
        $this->initOptions();
        $this->initDockworkerIO();
        $this->preInitDockworkerPersistentDataStorageDir();
        $this->registerDockerCliTool($this->dockworkerIO);
        $devel_modules = [
            'devel',
        ];
        $command = [
            'drush',
            '-y',
            'pmu',
        ];
        $this->executeContainerCommand(
            'local',
            array_merge($command, $devel_modules),
            $this->dockworkerIO,
            'Disabling Development Modules',
            sprintf(
                "[%s] Disabling development modules: %s...",
                'local',
                implode(',', $devel_modules)
            )
        );
    }

    /**
     * Informs the user of useful information after a successful deployment.
     *
     * This is necessary as it doesn't seem multiple hooks can be added to a method.
     *
     * @param mixed $result
     *   The result of the command.
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *   The command data.
     *
     * @hook post-command application:restart
     */
    public function displayDrupalLocalLinksAfterRestart(
        $result,
        CommandData $commandData
    ): void {
        $this->displayDrupalLocalLinks($result, $commandData, 'Restart');
    }

    /**
     * Informs the user of useful information after a successful deployment.
     *
     * For the sake of simplicity, as this is a hook (and we know it is local)
     * we bypass the container discovery process and just send the ULI command
     * to the container directly.
     *
     * @param mixed $result
     *   The result of the command.
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *   The command data.
     * @param string $action
     *   The action that was performed.
     *
     * @hook post-command application:deploy
     */
    public function displayDrupalLocalLinks(
        $result,
        CommandData $commandData,
        string $action = 'Deployment'
    ): void {
        // Hooks don't fire for other hooks, so we have to initialize resources.
        $this->initOptions();
        $this->initDockworkerIO();
        $this->preInitDockworkerPersistentDataStorageDir();
        $this->registerDockerCliTool($this->dockworkerIO);

        $this->dockworkerIO->title("$action Success!");

        $cmd = $this->dockerComposeRun(
            [
                'exec',
                $this->applicationSlug,
                '/scripts/drupalUli.sh'
            ],
            '',
            null,
            null,
            [],
            false
        );
        $uli_link = $cmd->getOutput();
        $this->dockworkerIO->block(
            file_get_contents(
                "$this->applicationRoot/vendor/unb-libraries/dockworker-drupal/data/art/complete.txt"
            )
        );
        $this->dockworkerIO->block(
            $this->formatLinksBlock($uli_link)
        );
    }

    /**
     * Formats the links block for the user.
     *
     * @TODO: This should be moved to a template.
     *
     * @param string $login_link
     *   The login link to display.
     *
     * @return string[]
     *   The formatted links block.
     */
    protected function formatLinksBlock($login_link): array
    {
        $local_links = [];
        $local_links[] = sprintf(
            'Visit the deployed site at:
http://local-%s:%s/',
            $this->applicationName,
            $this->applicationUuid
        );
        $local_links[] = sprintf(
            'Log-in to your instance via:
%s',
            $login_link
        );
        return $local_links;
    }
}
