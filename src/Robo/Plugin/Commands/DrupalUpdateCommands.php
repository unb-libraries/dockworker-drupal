<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Core\CommandLauncherTrait;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\Git\GitRepoTrait;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Robo\Plugin\Commands\UpdateCommands;

/**
 * Provides commands for updating a Drupal application.
 */
class DrupalUpdateCommands extends UpdateCommands
{
    use CommandLauncherTrait;
    use DockerContainerExecTrait;
    use DockworkerIOTrait;
    use GitRepoTrait;

    /**
     * Updates this application to the latest available packages.
     *
     * @hook post-command dockworker:update
     *
     * @throws \CzProject\GitPhp\GitException
     */
    public function updateDrupalModulesAndDependencies(): void
    {
        // Hooks don't fire for other hooks, so we have to initialize resources.
        $this->initOptions();
        $this->initDockworkerIO();

        [$container, $command] = $this->executeContainerCommand(
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

      $this->dockworkerIO->title('Copying Lockfile');
      $container->copyFrom(
        $this->dockworkerIO,
        '/app/html/composer.lock',
        $this->applicationRoot . '/build/composer.lock'
      );
      $this->dockworkerIO->say('Done!');

        $this->dockworkerIO->section("Checking for Changes");
        if ($this->repoFileHasChanges('build/composer.lock')) {
            $this->dockworkerIO->say(
                'Changes to build/composer.lock detected. Application has updates. Commit as needed.'
            );
        } else {
            $this->dockworkerIO->say(
                'No changes to build/composer.lock detected. Application has no updates.'
            );
        }
    }
}
