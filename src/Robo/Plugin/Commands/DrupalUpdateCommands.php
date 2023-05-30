<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Cli\CliCommandTrait;
use Dockworker\Git\GitRepoTrait;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Robo\Plugin\Commands\UpdateCommands;

/**
 * Provides commands for updating a Drupal application.
 */
class DrupalUpdateCommands extends UpdateCommands
{
    use CliCommandTrait;
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

        $this->dockworkerIO->title('Updating Drupal');
        $this->dockworkerIO->section('Checking for Updates');
        $this->executeCliCommand(
            [
                'composer',
                'update',
                '--no-autoloader',
                '--no-scripts',
                '--no-plugins',
            ],
            $this->dockworkerIO,
            $this->applicationRoot . '/build',
            '',
            "Updating composer.lock",
            true
        );

        $this->dockworkerIO->section('Checking for Changes');
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
