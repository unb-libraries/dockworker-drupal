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
     */
    public function provideErrorLogConfiguration()
    {
        return [
            [],
            array_values(
                [
                    'Module, not an error.' => 'inline_form_errors',
                    'Expected error' => 'Config language.entity.en does not exist',
                    ' 0 failed' => 'Migrate processes report 0 failed',
                ]
            ),
        ];
    }
}
