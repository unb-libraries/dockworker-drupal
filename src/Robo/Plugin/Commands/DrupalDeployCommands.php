<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Logs\SchemaWarningFilterTrait;
use Robo\Robo;

/**
 * Provides commands for building and deploying the Drupal application locally.
 */
class DrupalDeployCommands extends DockworkerDrupalCommands
{
    use DockworkerIOTrait;
    use SchemaWarningFilterTrait;

    /**
     * Provides the Drupal log error exceptions.
     *
     * Two consumers feed off this hook:
     *
     *  - LogCheckCommands::checkLogFileForErrors() — file-based scan
     *    invoked from CI as "logs:check-file <path>". For this path
     *    DrupalLogCheckCommands also runs a pre-command hook that
     *    strips schema-warning blocks structurally; the regex
     *    exceptions returned here are belt-and-suspenders.
     *
     *  - dockworker-daemon's monitorLocalStartupProgress() — streaming
     *    scan over incremental "docker compose logs -f" chunks. There
     *    is no file to pre-process; the regex exceptions returned here
     *    are the ONLY suppression mechanism, so they must cover every
     *    schema-warning line that would otherwise match the broad
     *    error pattern.
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
        ];

        $exceptions = array_values($fixed);
        $allowlist = $this->loadSchemaAllowlist();
        foreach ($this->buildSchemaWarningExceptionPatterns($allowlist) as $p) {
            $exceptions[] = $p;
        }

        return [[], $exceptions];
    }

    /**
     * Reads the schema-warning allowlist from dockworker.yml.
     *
     * Same shape as DrupalLogCheckCommands::resolveSchemaWarningAllowlist
     * but without the misplaced-key warning (avoids double-emission;
     * the hook command is the canonical place for that note).
     *
     * @return string[]
     */
    private function loadSchemaAllowlist(): array
    {
        $raw = Robo::config()->get('dockworker.drupal.schemas.ignore_enforcement', []);
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        $seen = [];
        foreach ($raw as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ($entry === '' || isset($seen[$entry])) {
                continue;
            }
            $seen[$entry] = true;
            $clean[] = $entry;
        }
        return $clean;
    }
}
