<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;

/**
 * Provides commands for building and deploying the Drupal application locally.
 */
class DrupalDeployCommands extends DockworkerDrupalCommands
{
    /**
     * The following function curently does nothing, but provides an example.
     *
     * @hook on-event dockworker-logs-errors-exceptions
     *
     * @return mixed[]
     *   The error log exceptions.
     */
    public function provideErrorLogConfiguration(): array
    {
        return [
            [],
            array_values(
                [
                    // Drupal 11 local exceptions.
                    'Expected Drupal 11 exception' => 'Triage : Database connection issue: ERROR 1045 (28000): Access denied for user \'drupal\'',

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
                ]
            ),
        ];
    }
}
