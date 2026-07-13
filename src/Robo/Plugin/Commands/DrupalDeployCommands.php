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
     * The log classifier (LogCheckerTrait::partitionLogLines) demotes every
     * Drupal '[warning]'-level line to non-fatal and collects it for an
     * end-of-deploy summary, and never scans '[notice]/[success]/...' lines.
     * The exceptions below only need to cover BENIGN lines that reach the fatal
     * scan: markerless output that happens to contain an error-pattern
     * substring. Consumed by both the streaming monitor
     * (monitorLocalStartupProgress) and the file scan (logs:check-file).
     *
     * @hook on-event dockworker-logs-errors-exceptions
     *
     * @return mixed[]
     *   The error log exceptions.
     */
    public function provideErrorLogConfiguration(): array
    {
        $fixed = [
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

            // Drush proceeding past a non-fatal requirements check: it
            // auto-answers "yes" and continues, so this is informational.
            'Drush requirements auto-continue' => 'Do you wish to continue\?: yes',

            // Stable Drupal-core config-schema warning body prose. Schema
            // warnings are '[warning]'-level and are demoted by the classifier,
            // but Drush wraps long ones across lines (e.g. its end-of-run
            // "Message:" summary). Wrapping co-locates denylist words
            // ("errors"/"fatal error") with otherwise-benign prose, so we
            // suppress the FULL set of stable core phrases - not just the ones
            // that themselves carry a denylist word. These are fixed Drupal-core
            // strings. The wrap-tail is anchored to "following errors:$" (not a
            // bare "errors:$") so a genuine "...failed with errors:" line is
            // never excepted - a stray wrap fails loudly rather than hiding an
            // error.
            'Schema warning wrap-tail' => 'following errors:\s*$',
            // Drush also wraps "...with the following\nerrors:", leaving a bare
            // continuation line of just "errors:" (optionally behind the
            // docker-compose "<slug> |" prefix) that reaches the fatal scan.
            // Anchor to a line whose entire message is "errors:" so a genuine
            // "...failed with errors:" line is never excepted.
            'Schema warning bare wrap-tail' => '(\||^)\s*errors:\s*$',
            'Schema warning prose 1' => 'These errors mean there',
            'Schema warning prose 2' => 'is configuration that does not comply with its schema',
            'Schema warning prose 3' => 'does not comply with its schema',
            'Schema warning prose 4' => 'not a fatal error, but it is',
            'Schema warning prose 5' => 'recommended to fix these issues',
            'Schema warning prose 6' => 'missing schema',
        ];

        return [[], array_values($fixed)];
    }
}
