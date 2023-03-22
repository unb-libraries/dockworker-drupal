<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerCommands;
use Dockworker\Git\GitRepoTrait;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\PhpCsTrait;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DockworkerDrupalPhpValidateCommands extends DockworkerCommands
{
    use DockworkerIOTrait;
    use GitRepoTrait;
    use PhpCsTrait;

    const PHPCS_EXTENSIONS = [
        'inc',
        'install',
        'lib',
        'module',
        'php',
        'theme',
    ];

    const PHPCS_STANDARDS = [
        'Drupal',
        'DrupalPractice',
    ];

    /**
     * Validates the staged PHP files.
     *
     * @command drupal:php:validate
     */
    public function validateDrupalPhp(
        array $options = [
            'staged' => false,
            'changed' => false,
        ]
    ): void {
        if ($options['staged'] && $options['changed']) {
            $this->dockworkerIO->error('Cannot use both --staged and --changed');
            exit(1);
        }
        if ($options['staged']) {
            $title = 'Validating Staged PHP';
            $files = $this->getApplicationGitRepoStagedFiles(
                '/.*\.{' .
                implode('|', self::PHPCS_EXTENSIONS) .
                '}/'
            );
        } elseif ($options['changed']) {
            $title = 'Validating Changed PHP';
            $files = $this->getApplicationGitRepoChangedFiles(
                '/.*\.{' .
                implode('|', self::PHPCS_EXTENSIONS) .
                '}/'
            );
        } else {
            $title = 'Validating PHP';
            $files = ['custom/'];
        }

        $this->dockworkerIO->title($title);
        $process = $this->validatePhpFiles(
            $this->dockworkerIO,
            $files,
            self::PHPCS_STANDARDS,
            self::PHPCS_EXTENSIONS
        );
        exit(
            $process->getExitCode()
        );
    }
}
