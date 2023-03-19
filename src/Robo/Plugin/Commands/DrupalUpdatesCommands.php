<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Core\CommandLauncherTrait;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\Git\GitRepoTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerUpdateCommands;

/**
 * Provides commands for updating a Drupal application.
 */
class DrupalUpdatesCommands extends DockworkerUpdateCommands
{
    use CommandLauncherTrait;
    use DockerContainerExecTrait;
    use GitRepoTrait;

    /**
     * Performs Drupal updates.
     *
     * @hook post-command dockworker:update
     */
    public function updateDrupalModulesAndDependencies(): void {
        $this->executeContainerCommand(
            'local',
            [
                'composer',
                '--working-dir=/app/html',
                'update',
            ],
            $this->dockworkerIO,
            'Updating Application',
            'Checking for Updates'
        );
        $this->setRunOtherCommand(
            $this->dockworkerIO,
            ['write-lock']
        );
        $this->dockworkerIO->section("Checking for Changes");
        if ($this->repoFileHasChanges('build/composer.lock')) {
            $this->dockworkerIO->say('Changes to build/composer.lock detected. Application has updates. Commit as needed.');
        } else {
            $this->dockworkerIO->say('No changes to build/composer.lock detected. Application has no updates.');
        }
    }
}
