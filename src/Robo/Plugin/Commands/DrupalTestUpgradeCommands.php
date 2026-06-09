<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Dockworker\Cli\DockerCliTrait;
use Dockworker\Core\CommandLauncherTrait;
use Dockworker\Deployment\LocalDeploymentMonitorTrait;
use Dockworker\Docker\DockerComposeTrait;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;
use Dockworker\Logs\LogCheckerTrait;
use Dockworker\Snapshot\SnapshotTrait;
use Dockworker\Storage\ApplicationLocalDataStorageTrait;
use Dockworker\Storage\TemporaryStorageTrait;
use Robo\Robo;

/**
 * Provides a command to test local code upgrades against a production snapshot.
 */
class DrupalTestUpgradeCommands extends DockworkerDrupalCommands implements CustomEventAwareInterface
{
    use ApplicationLocalDataStorageTrait;
    use CommandLauncherTrait;
    use CustomEventAwareTrait;
    use DockerCliTrait;
    use DockerComposeTrait;
    use DockerContainerExecTrait;
    use LocalDeploymentMonitorTrait;
    use LogCheckerTrait;
    use SnapshotTrait;
    use TemporaryStorageTrait;

    /**
     * The environment a test upgrade always targets.
     *
     * @var string
     */
    protected string $testUpgradeEnv = 'local';

    /**
     * Registers the docker CLI tool and runs preflight checks.
     *
     * @hook post-init
     */
    public function initTestUpgradeRequirements(): void
    {
        $this->registerDockerCliTool($this->dockworkerIO);
        $this->checkPreflightChecks($this->dockworkerIO);
    }

    /**
     * Tests local code changes by upgrading a production snapshot in place.
     *
     * Loads the latest production snapshot into the already-running local stack
     * (a raw import - no cache rebuild), then rebuilds and recreates only the
     * application (and Redis) container so the image's own startup sequence runs
     * the upgrade (database updates + configuration import) against production
     * data. The startup logs are watched for errors exactly as 'start' does, and
     * a user login link (ULI) is generated on success.
     *
     * The local stack must already be running (run 'dockworker start' first):
     * this guarantees an import target and that the filesystem is "live" so the
     * container's update hooks actually fire on the upgrade start.
     *
     * @param mixed[] $options
     *   The options passed to the command.
     *
     * @option bool $files
     *   Also import the production public files, not just the database.
     * @option bool $no-build
     *   Skip rebuilding the application image. Faster, but tests stale contrib
     *   code if composer.lock/Dockerfile changed; bind-mounted custom code and
     *   configuration are picked up regardless.
     * @option bool $reindex
     *   Rebuild and reindex Solr after the upgrade. Skipped by default.
     * @option string $uid
     *   The uid to generate the post-upgrade login link for. Defaults to uid 1.
     * @option int $timeout
     *   Seconds to wait for the upgrade startup to complete before failing.
     *   Large production datasets can take a while to update.
     *
     * @command drupal:test-upgrade
     * @aliases test-upgrade
     * @usage --files --uid=1
     */
    public function testUpgrade(
        array $options = [
            'files' => false,
            'no-build' => false,
            'reindex' => false,
            'uid' => '1',
            'timeout' => 1800,
        ]
    ): void {
        $env = $this->testUpgradeEnv;
        $this->dockworkerIO->title("Testing $this->applicationName Upgrade Against a Production Snapshot");

        // The import must run inside a live application container, and the
        // upgrade only fires when the filesystem is already 'live'. Both are
        // guaranteed by requiring a running stack.
        if (!$this->composeServiceIsRunning($this->applicationSlug)) {
            $this->dockworkerIO->error(
                'The local application stack is not running. Start it first with "dockworker start", then run this command.'
            );
            exit(1);
        }

        $files_to_skip = $options['files'] ? [] : ['files.tar.gz'];
        if (!$options['files']) {
            $this->dockworkerIO->say('Importing the database only (use --files to also import production files).');
        }
        $this->initSnapshotCommand('prod', $files_to_skip);
        $this->initContainerExecCommand($this->dockworkerIO, $env);
        $this->renderAllSnapshotFiles('prod');

        $tmp_path = self::createTemporaryLocalStorage();
        $this->validateDiskSpaceOnDevices(
            $tmp_path,
            $this->getDeployedContainer($this->dockworkerIO, $env),
            $env
        );

        $this->dockworkerIO->warning(
            'This will overwrite the local database' .
            ($options['files'] ? ' and public files' : '') .
            ' with the production snapshot, then run the upgrade with your local code.'
        );
        if (
            !$this->dockworkerIO->confirm(
                'Are you sure you want to test the upgrade with the above-listed production snapshot?'
            )
        ) {
            $this->dockworkerIO->say('Test upgrade aborted.');
            // Do not fire hooks.
            exit(0);
        }

        // Raw import (no cache rebuild / no permission wrappers) into the
        // running application container.
        $this->copySnapshotsToLocalTmp($tmp_path);
        $container = $this->moveSnapshotsToContainer($tmp_path, $env);
        // Remove the local tmp archive files.
        $this->executeCliCommand(
            ['rm', '-rf', "$tmp_path/*.gz"],
            $this->dockworkerIO,
            null,
            '',
            'Remove Local Archive Files',
            false,
            null
        );
        $this->executeImportScript($container, '/scripts/importDataRaw.sh');
        // Delete any remaining files in the container dir.
        $this->executeContainerCommand(
            $env,
            ['rm', '-rf', '/tmp/snapshot'],
            $this->dockworkerIO,
            '',
            'Remove Container Archive Files',
            false,
            false
        );

        // Rebuild (unless skipped) and recreate only the application container,
        // plus Redis. Redis is ephemeral, so recreating it clears the previous
        // site's cached state - the container startup skips its own Redis flush
        // when the database and filesystem are already "live", which they now
        // are. The database and Solr containers are left running untouched.
        if (!$options['no-build']) {
            $this->buildComposeApplication($this->applicationSlug);
        }
        $services = [$this->applicationSlug];
        $redis_service = $this->getDeploymentServiceNameByAlias('redis');
        if (!empty($redis_service)) {
            $services[] = $redis_service;
        }
        $this->recreateComposeServices($services);

        // Watch the upgrade (database updates + configuration import) for errors.
        $this->monitorLocalStartupProgress((int) $options['timeout']);
        $this->monitorLocalDaemonReadiness();

        if ($options['reindex']) {
            $this->setRunOtherCommand(
                $this->dockworkerIO,
                ['solr:rebuild-reindex', '--env=local']
            );
        }
    }

    /**
     * Resolves a docker compose service name from its deployment alias.
     *
     * @param string $alias
     *   The deployment alias (the 'name' field in dockworker.endpoints.deployments).
     *
     * @return string
     *   The matching compose service name, or an empty string if none is found.
     */
    protected function getDeploymentServiceNameByAlias(string $alias): string
    {
        $deployments = $this->getConfigItem(
            Robo::config(),
            'dockworker.endpoints.deployments',
            []
        );
        foreach ($deployments as $id => $deployment) {
            if (($deployment['name'] ?? '') === $alias) {
                return $id;
            }
        }
        return '';
    }
}
