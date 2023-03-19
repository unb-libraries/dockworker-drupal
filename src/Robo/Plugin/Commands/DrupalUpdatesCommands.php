<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Cli\DockerCliTrait;
use Dockworker\Core\CommandLauncherTrait;
use Dockworker\Docker\DeployedLocalResourcesTrait;
use Dockworker\Git\GitRepoTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerUpdateCommands;
use Dockworker\IO\DockworkerIOTrait;
use Robo\Robo;

/**
 * Provides commands for updating a Drupal application.
 */
class DrupalUpdatesCommands extends DockworkerUpdateCommands
{
    use CommandLauncherTrait;
    use DeployedLocalResourcesTrait;
    use DockerCliTrait;
    use DockworkerIOTrait;
    use GitRepoTrait;

    /**
     * Performs Drupal updates.
     *
     * @hook post-command dockworker:update
     */
    public function updateDrupalModulesAndDependencies(): void {
        $this->registerDockerCliTool($this->dockworkerIO);
        $this->checkPreflightChecks($this->dockworkerIO);
        $this->enableLocalResourceDiscovery();
        $this->discoverDeployedResources(
            $this->dockworkerIO,
            Robo::config(),
            'local'
        );
        $container = $this->getDeployedContainer(
            $this->dockworkerIO,
            'local'
        );
        $this->dockworkerIO->title("Checking for Updates");
        $container->run(
            [
                'composer',
                '--working-dir=/app/html',
                'update',
            ],
            $this->dockworkerIO
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
