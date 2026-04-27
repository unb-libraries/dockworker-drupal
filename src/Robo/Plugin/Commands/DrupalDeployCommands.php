<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Provides commands for building and deploying the Drupal application locally.
 */
class DrupalDeployCommands extends DockworkerDrupalCommands
{
    use DockworkerIOTrait;

    /**
     * Provides the Drupal log error exceptions.
     *
     * Schema-warning suppression has moved to a pre-command hook on
     * logs:check-file (see DrupalLogCheckCommands). This hook now only
     * carries the small set of fixed exceptions for unrelated false
     * positives in the upstream line-level scanner.
     *
     * @hook on-event dockworker-logs-errors-exceptions
     *
     * @return mixed[]
     *   The error log exceptions.
     */
    public function provideErrorLogConfiguration(): array
    {
        $exceptions = [
            // Drupal 11 local exceptions.
            'Expected Drupal 11 exception' => 'Access denied for user \'drupal\'',

            // Drupal 10 exceptions.
            'Module, not an error.' => 'inline_form_errors',
            'Expected error' => 'Config language.entity.en does not exist',
            'Migrate processes report 0 failed' =>  ' 0 failed',

            // Drupal 9 exceptions.
            'Expected in Local.' => 'Operation CREATE USER failed',
            'Expected composer summary output' => 'failure: 0',
            'Expected composer suggest output' => 'error-handler instead',

            // Generic exceptions.
            'Ignore .well-known trolling' => '.well-known',

            // Calendar exception.
            'Calendar template name' => 'HoursCalendarUnavailableTemplate',
        ];

        return [[], array_values($exceptions)];
    }
}
