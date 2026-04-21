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

        $exceptions['Drupal config-schema warning envelope']
            = $this->schemaBoilerplateExceptionPattern();

        $header = $this->buildSchemaIgnoreHeaderPattern();
        if ($header !== null) {
            $exceptions['Ignored Drupal config-schema warning headers']
                = $header;
        }

        return [[], array_values($exceptions)];
    }

    /**
     * Returns the always-on boilerplate pattern for schema-warning envelopes.
     *
     * These phrases are stable Drupal/Drush strings surrounding every
     * config-schema warning, independent of the specific config named.
     * Suppressing them is safe: the warning is cosmetic per Drupal's own
     * "this is not a fatal error" note, and the primary header line is
     * still visible in the stream.
     *
     * @return string
     *   A pipe-joined list of regex alternatives.
     */
    private function schemaBoilerplateExceptionPattern(): string
    {
        return implode('|', [
            // Wrap-tail anchor on the Drush "with the following | errors:"
            // fragment when the header line itself wraps.
            '\|\s+errors:\s*$',
            // Single-line, most specific form.
            'is configuration that does not comply with its schema',
            // Shorter form: survives word-wrap that splits the
            // "is configuration that" prefix onto the prior line.
            'does not comply with its schema',
            'not a fatal error, but it is',
            'recommended to fix these issues',
            'For more information on configuration schemas',
            'check out the documentation',
            // Stable docs-URL fragment. Immune to any rewording; only
            // changes if Drupal moves the docs page.
            'configuration-schemametadata',
            'These errors mean there',
            // Per-field continuation line of a "Schema errors for X"
            // summary: "X:path missing schema" or "... missing schema, ...".
            // Distinctive enough that it only appears in schema-warning
            // output, so always-on suppression is safe.
            'missing schema',
        ]);
    }

    /**
     * Builds header-only regex alternatives for the configured allowlist.
     *
     * Reads dockworker.drupal.schemas.ignore_enforcement from
     * .dockworker/dockworker.yml. Each entry names a Drupal config (e.g.
     * "views.settings", "system.authorize") and is matched against the
     * <config_name> slot in the two Drush schema-warning headers:
     *
     *   [warning] Schema errors for <config_name> with the following ...
     *   [warning] Message: No schema for <config_name>.
     *
     * A trailing ".*" wildcard matches the bare config name OR any
     * non-whitespace descendant (so "system.authorize.*" matches both
     * "system.authorize" and "system.authorize.foo"). Mid-string "*" is
     * retained as a non-whitespace wildcard for back-compat.
     *
     * Generic boilerplate that trails every schema warning is handled
     * separately by schemaBoilerplateExceptionPattern() and is always-on,
     * so this function only contributes header matches.
     *
     * Emits a one-time informational note listing the active patterns.
     *
     * @return string|null
     *   The header regex alternative, or null when no patterns are
     *   configured.
     */
    private function buildSchemaIgnoreHeaderPattern(): ?string
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
            foreach ($this->schemaEntryFragments($entry) as $frag) {
                $fragments[$frag] = true;
            }
        }
        $fragments = array_keys($fragments);

        $this->noteActiveSchemaIgnore($patterns);

        $group = '(?:' . implode('|', $fragments) . ')';

        // Format 1: the LenientConfigSchemaChecker single-line form
        // emitted during drush config-import. Drush may inject tokens
        // between '[warning]' and 'Schema errors for', so accept any
        // non-newline content there. The lookahead disallows '\.' so
        // a 2-part prefix "system.file" cannot bind inside
        // "system.file.foo".
        $header_schema_errors = '\[warning\][^\n]*?Schema errors for '
            . $group . '(?=[\s:]|$)';

        // Format 2: the Drush exception-renderer form, e.g.
        // '[warning] Message: No schema for system.authorize.'. The
        // trailing period is sentence punctuation. Accept '\.' in the
        // lookahead only when followed by whitespace or end-of-line,
        // so a 2-part prefix like "views.view" cannot bind inside
        // "views.view.front".
        $header_no_schema = '\[warning\][^\n]*?(?:Message:\s*)?'
            . 'No schema for ' . $group . '(?=[\s:]|\.\s|\.$|$)';

        return $header_schema_errors . '|' . $header_no_schema;
    }

    /**
     * Expands a single ignore-list entry into one or more regex fragments.
     *
     * Users may list either a config name (e.g. "system.file") or a schema
     * PATH into that config (e.g. "system.file.path.temporary"). Drush's
     * warning header prints only the top-level config name, so for schema-
     * path entries we also emit a fragment covering the 2-segment config-
     * name prefix ("system.file") so the allowlist binds regardless of
     * which form the user wrote.
     *
     * Trailing ".*" wildcard matches bare config name OR non-whitespace
     * descendant. Mid-string "*" is kept as a non-whitespace wildcard for
     * back-compat.
     *
     * @param string $entry
     *   The user-supplied allowlist entry.
     *
     * @return string[]
     *   Regex fragments for use inside a '(?:…|…)' alternation.
     */
    private function schemaEntryFragments(string $entry): array
    {
        $out = [];
        $quoted = preg_quote($entry, '/');
        if (str_ends_with($quoted, '\.\*')) {
            $out[] = substr($quoted, 0, -4) . '(?:\.[^\s]*)?';
        } else {
            $out[] = str_replace('\*', '[^\s]*', $quoted);
        }

        // Auto-derive a 2-segment config-name prefix for schema-path
        // entries so headers that print only the top-level config name
        // still bind. Skipped for wildcard entries — the wildcard
        // expansion already covers bare-name + descendant.
        if (str_contains($entry, '*')) {
            return $out;
        }
        $parts = explode('.', $entry);
        if (count($parts) > 2) {
            $out[] = preg_quote($parts[0] . '.' . $parts[1], '/');
        }
        return $out;
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
     * Robo dispatches @hook on-event handlers on a fresh instance of the
     * host class where $this->dockworkerIO is not initialised. Falling
     * back to STDERR keeps the note visible in that context.
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

        $lines = [sprintf(
            '[info] Drupal schema-enforcement ignore active: %d pattern(s)',
            count($patterns)
        )];
        foreach ($patterns as $p) {
            $lines[] = '       - ' . $p;
        }

        // Typed-property safe: isset() returns false on uninitialized
        // typed properties without throwing.
        if (isset($this->dockworkerIO)) {
            foreach ($lines as $line) {
                $this->dockworkerIO->writeln($line);
            }
            return;
        }
        fwrite(STDERR, implode("\n", $lines) . "\n");
    }
}
