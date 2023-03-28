<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Dockworker\Docker\DockerComposeTrait;
use Dockworker\DockworkerDaemonCommands;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides commands for generating local links for a Drupal application.
 */
class DrupalLocalLinksCommands extends DockworkerDaemonCommands
{
    use DockerComposeTrait;
    use DockworkerIOTrait;

    /**
     * Informs the user of useful information after a successful deployment.
     *
     * @hook post-command application:deploy
     */
    public function displayDrupalLocalLinks(
        $result,
        CommandData $commandData
    ): void {
        // Hooks don't fire for other hooks, so we have to initialize resources.
        $this->initDockworkerIO();
        $this->preInitDockworkerPersistentDataStorageDir();
        $this->registerDockerCliTool($this->dockworkerIO);

        $this->dockworkerIO->title('Deployment Success!');
        $cmd = $this->dockerComposeRun(
            [
                'exec',
                $this->applicationName,
                '/scripts/drupalUli.sh'
            ],
            '',
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
     * @return array
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
