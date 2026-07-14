<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerComposeTrait;
use Dockworker\DockworkerDrupalCommands;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides commands for deploying a mailhog service container.
 */
class DrupalMailhogCommands extends DockworkerDrupalCommands
{
    use DockerComposeTrait;
    use DockworkerIOTrait;

    /**
     * Enables Mailhog for his instance.
     *
     * @command drupal:mailhog:enable
     * @aliases mailhog
     */
    public function startMailHogContainer(): void
    {
        $this->registerDockerCliTool($this->dockworkerIO);
        if ($this->isMailhogEnabled()) {
            $this->say('Mailhog is already enabled.');
            $this->showMailHogUri();
            return;
        }
        $this->startComposeApplication('mailhog');
        $this->showMailHogUri();
    }

    /**
     * Enables Mailhog for his instance.
     *
     * @command drupal:mailhog:disable
     */
    public function stopMailHogContainer(): void
    {
        $this->registerDockerCliTool($this->dockworkerIO);
        if (!$this->isMailhogEnabled()) {
            $this->say('Mailhog is already disabled.');
            return;
        }
        $this->stopComposeApplication('mailhog');
    }

    /**
     * Shows the Mailhog URI.
     */
    protected function showMailHogUri(): void
    {
        $this->dockworkerIO->block(
            sprintf(
                'Visit the mailhog instance at: http://local-%s:%s/',
                $this->applicationName,
                ((int) $this->applicationUuid) + 1000
            )
        );
    }

    /**
     * Checks if Mailhog is enabled.
     *
     * @return bool
     *   TRUE if Mailhog is enabled, FALSE otherwise.
     */
    protected function isMailhogEnabled(): bool
    {
        return $this->composeServiceIsRunning('mailhog');
    }
}
