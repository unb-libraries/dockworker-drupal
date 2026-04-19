<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\IO\DockworkerIOTrait;
use Robo\Robo;

/**
 * Provides commands for building and deploying the Drupal application locally.
 */
class DrupalDeployCommands extends DockworkerDrupalCommands
{
    use DockworkerIOTrait;

    /**
     * Process-wide one-shot guard for the schema-ignore info note.
     *
     * Prevents repeated output if a future refactor ever calls
     * getAllLogErrorStrings() in a loop.
     */
    private static bool $schemaIgnoreNoteEmitted = false;

    /**
     * Provides the Drupal log error exceptions.
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

        $schema_pattern = $this->buildSchemaIgnoreExceptionPattern();
        if ($schema_pattern !== null) {
            $exceptions['Ignored Drupal config-schema warnings']
                = $schema_pattern;
        }

        return [[], array_values($exceptions)];
    }

    /**
     * Builds a regex alternative that suppresses configured schema warnings.
     *
     * Reads dockworker.drupal.schemas.ignore_enforcement from
     * .dockworker/dockworker.yml. Each entry is a glob against the
     * <config_name> slot in "[warning] Schema errors for <config_name> with
     * the following errors: ...". Supports '*' as the only wildcard (matches
     * any non-whitespace run); all other characters are literal.
     *
     * Emits a one-time informational note listing the active patterns.
     *
     * @return string|null
     *   The regex alternative, or null when no patterns are configured.
     */
    private function buildSchemaIgnoreExceptionPattern(): ?string
    {
        $raw = Robo::config()->get(
            'dockworker.drupal.schemas.ignore_enforcement',
            []
        );
        $patterns = $this->normaliseSchemaIgnoreConfig($raw);
        if ($patterns === []) {
            return null;
        }

        $fragments = [];
        foreach ($patterns as $entry) {
            // preg_quote escapes '.' and '*'; turn the escaped '\*' back
            // into a whitespace-terminated wildcard.
            $fragments[] = str_replace(
                '\*',
                '[^\s]*',
                preg_quote($entry, '/')
            );
        }

        $this->noteActiveSchemaIgnore($patterns);

        // Header match. Drush renders the LenientConfigSchemaChecker
        // exception as '[warning] Message: Schema errors for X ...', and
        // other renderers may inject different tokens between [warning]
        // and 'Schema errors for'. Accept any non-newline content there.
        $header = '\[warning\][^\n]*?Schema errors for (?:'
            . implode('|', $fragments)
            . ')(?=\s|:|$)';

        // Boilerplate continuation match. Drush wraps the full schema-
        // warning message across multiple lines at ~80 cols. Each wrapped
        // line contains at least one error-keyword (errors, error, fatal)
        // and would otherwise surface as a separate "error" under the
        // line-level scanner. The boilerplate text is identical across
        // every schema warning and carries no config-specific
        // information, so suppressing it wholesale when the allowlist is
        // active is safe: an un-allowlisted schema warning still fails
        // the build via its primary header line.
        $boilerplate = [
            '\|\s+errors:\s*$',
            'These errors mean there',
            'is configuration that does not comply with its schema',
            'not a fatal error, but it is recommended',
            'recommended to fix these issues',
            'For more information on configuration schemas',
        ];

        return $header . '|' . implode('|', $boilerplate);
    }

    /**
     * Normalises the schema-ignore config value into a list of patterns.
     *
     * Accepts a YAML sequence (array), a CSV string convenience form, or
     * anything else (treated as empty). Trims whitespace, drops empties and
     * non-string entries, and deduplicates while preserving first-seen order.
     *
     * @param mixed $raw
     *   The raw configured value.
     *
     * @return string[]
     *   The normalised pattern list.
     */
    private function normaliseSchemaIgnoreConfig(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $seen = [];
        foreach ($raw as $v) {
            if (!is_string($v)) {
                continue;
            }
            $t = trim($v);
            if ($t !== '' && !isset($seen[$t])) {
                $seen[$t] = true;
            }
        }
        return array_keys($seen);
    }

    /**
     * Emits a one-shot informational note when schema-ignore is active.
     *
     * @param string[] $patterns
     *   The active patterns to list.
     */
    private function noteActiveSchemaIgnore(array $patterns): void
    {
        if (self::$schemaIgnoreNoteEmitted) {
            return;
        }
        self::$schemaIgnoreNoteEmitted = true;
        // Typed-property safe: isset() returns false on uninitialized
        // typed properties without throwing.
        if (!isset($this->dockworkerIO)) {
            return;
        }
        $this->dockworkerIO->writeln(sprintf(
            '[info] Drupal schema-enforcement ignore active: %d pattern(s)',
            count($patterns)
        ));
        foreach ($patterns as $p) {
            $this->dockworkerIO->writeln('       - ' . $p);
        }
    }
}
