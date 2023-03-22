<?php

namespace Dockworker\PhpCs;

use Dockworker\Cli\CliCommand;
use Dockworker\Cli\CliCommandTrait;
use Dockworker\IO\DockworkerIO;

/**
 * Provides methods to validate PHP code via phpcs.
 */
trait PhpCsTrait
{
    use CliCommandTrait;

    /**
     * Validates files using phpcs.
     *
     * @param string $files
     *   The files to validate.
     * @param string[] $lint_standards
     *   An array of linting standards to enforce.
     * @param string[] $extensions
     *   An array of file extensions to include.
     * @param bool $no_warnings
     *  Only report errors, not warnings.
     *
     * @return \Dockworker\Cli\CliCommand|null
     *   The CLI command object.
     */
    protected function validatePhpFiles(
        DockworkerIO $io,
        array $files,
        array $lint_standards = ['PSR12'],
        array $extensions = ['php'],
        $no_warnings = FALSE
    ): CliCommand|null {
        if (!empty($files)) {
            $cmd = [
                'vendor/bin/phpcs',
                '--colors',
                '--standard=' . implode(',', $lint_standards),
                '--extensions=' . implode(',', $extensions),
                '--runtime-set',
                'ignore_warnings_on_exit',
                '1',
            ];
            if ($no_warnings) {
                $cmd[] = '--warning-severity=0';
            }
            $cmd[] = '--';
            $cmd = array_merge($cmd, $files);
            return $this->executeCliCommand(
                $cmd,
                $io,
                null,
                '',
                '',
                true
            );
        }
    }
}
