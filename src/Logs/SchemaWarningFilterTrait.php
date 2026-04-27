<?php

namespace Dockworker\Logs;

/**
 * Strips Drupal config-schema warning blocks from a captured log stream.
 *
 * Drush emits schema warnings as multi-line wrapped prose blocks. The
 * upstream LogCheckerTrait scans line-by-line for "error|fail|fatal|..."
 * substrings; because schema warnings include those substrings in prose
 * ("Schema errors for X", "not a fatal error"), every wording or
 * wrapping change in Drupal/Drush forced a new pattern. This trait
 * recognises a schema-warning block by its stable grammar — a header
 * naming a config plus a docs-URL terminator — and removes the entire
 * block before the line scanner sees it.
 *
 * Block grammar:
 *   header     — line containing '[warning]' AND
 *                'Schema errors for <name>' or 'No schema for <name>'
 *                (with optional 'Message: ' prefix from Drush's
 *                exception renderer)
 *   body       — zero or more continuation lines
 *   terminator — first line at or after the header containing the
 *                substring 'configuration-schemametadata' (the docs
 *                URL fragment Drupal core has linked to since Drupal
 *                8; appears inline as <a href> in single-line form
 *                and as a [1] footnote in wrapped form)
 *
 * Drop policy:
 *   - allowlisted config name → entire block dropped (silent)
 *   - non-allowlisted        → header passes through (build still
 *                              fails on a new schema problem); body
 *                              and terminator dropped
 */
trait SchemaWarningFilterTrait
{
    /**
     * Maximum number of body lines the parser will consume before
     * declaring the block malformed and bailing out. A schema-warning
     * block is normally <15 lines; 20 gives margin without risking
     * runaway suppression if Drupal moves the docs URL.
     */
    private int $schemaWarningBlockLineCap = 20;

    /**
     * Strips schema-warning blocks from a captured log stream.
     *
     * @param string $log
     *   The raw log content.
     * @param string[] $allowlist
     *   Config-name allowlist entries. Trailing '.*' matches the bare
     *   name OR any descendant; mid-string '*' is treated as
     *   '[^.\s]*'. Exact matches (no wildcard) match only that config
     *   name. Non-string and empty entries are ignored.
     *
     * @return string
     *   The cleaned log content.
     */
    public function stripSchemaWarningBlocks(string $log, array $allowlist): string
    {
        $lines = explode("\n", $log);
        $output = [];

        $inBlock = false;
        $blockBodyLines = 0;
        $blockHeaderLineNum = 0;

        foreach ($lines as $index => $line) {
            $bare = $this->stripAnsi($line);
            $headerName = $this->detectSchemaHeader($bare);

            if ($headerName !== null) {
                // A new header always starts a fresh block, even if a
                // previous block hadn't reached its terminator. This
                // preserves the tripwire signal for the new warning
                // and guards against malformed framing.
                $inBlock = true;
                $blockBodyLines = 0;
                $blockHeaderLineNum = $index + 1;

                if (!$this->matchesSchemaAllowlist($headerName, $allowlist)) {
                    // Tripwire: header passes through so the build
                    // still fails on a config the user hasn't opted
                    // into suppressing.
                    $output[] = $line;
                }

                // Single-line warning: header itself contains the
                // terminator (the inline <a href> form).
                if ($this->isBlockTerminator($bare)) {
                    $inBlock = false;
                }
                continue;
            }

            if (!$inBlock) {
                $output[] = $line;
                continue;
            }

            // Inside a block, consuming body.
            $blockBodyLines++;

            if ($blockBodyLines > $this->schemaWarningBlockLineCap) {
                fwrite(
                    STDERR,
                    sprintf(
                        "[dockworker] schema-warning block exceeded %d lines without terminator (header at line %d); emitting passthrough — likely a Drupal docs-URL change.\n",
                        $this->schemaWarningBlockLineCap,
                        $blockHeaderLineNum
                    )
                );
                $output[] = $line;
                $inBlock = false;
                continue;
            }

            if ($this->isBlockTerminator($bare)) {
                $inBlock = false;
            }
            // (Body lines drop silently regardless of allowlist state —
            // body prose carries no actionable signal.)
        }

        return implode("\n", $output);
    }

    /**
     * Tests a config name against an allowlist with wildcard support.
     *
     * @param string $configName
     *   The config name extracted from a Drush warning header.
     * @param string[] $allowlist
     *   Allowlist entries. See stripSchemaWarningBlocks() for syntax.
     *
     * @return bool
     *   TRUE iff the config name matches at least one entry.
     */
    public function matchesSchemaAllowlist(string $configName, array $allowlist): bool
    {
        foreach ($allowlist as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (str_ends_with($entry, '.*')) {
                $prefix = substr($entry, 0, -2);
                if (
                    $configName === $prefix
                    || str_starts_with($configName, $prefix . '.')
                ) {
                    return true;
                }
                continue;
            }

            if (str_contains($entry, '*')) {
                // Mid-string wildcard. Each '*' translates to one
                // dot-bounded segment match, preserving back-compat
                // with the previous (regex-based) implementation.
                $regex = '/^' . str_replace(
                    '\*',
                    '[^.\s]*',
                    preg_quote($entry, '/')
                ) . '$/';
                if (preg_match($regex, $configName) === 1) {
                    return true;
                }
                continue;
            }

            if ($configName === $entry) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the config name from a schema-warning header line, or null.
     *
     * Handles all four observed Drush header forms:
     *   [warning] Schema errors for <name> with the following errors: ...
     *   [warning] Message: Schema errors for <name> with the following ...
     *   [warning] No schema for <name>.
     *   [warning] Message: No schema for <name>.
     */
    private function detectSchemaHeader(string $line): ?string
    {
        $matched = preg_match(
            '/\[warning\][^\n]*?(?:Message:\s*)?(?:Schema errors for|No schema for)\s+([A-Za-z0-9_.\-]+)/',
            $line,
            $m
        );
        if ($matched !== 1) {
            return null;
        }
        // Strip a trailing '.' from the "No schema for X." form
        // (sentence-terminating period, not part of the config name).
        return rtrim($m[1], '.');
    }

    /**
     * Tests whether a line terminates a schema-warning block.
     *
     * The substring 'configuration-schemametadata' is the Drupal core
     * docs-URL fragment that anchors every schema warning. Stable for
     * 5+ years.
     */
    private function isBlockTerminator(string $line): bool
    {
        return str_contains($line, 'configuration-schemametadata');
    }

    /**
     * Strips ANSI SGR escape codes from a single line.
     *
     * Defensive: the current Drush capture path strips colour, but if
     * a future change captures a TTY-attached stream the regex stack
     * would otherwise break silently.
     */
    private function stripAnsi(string $line): string
    {
        return preg_replace('/\x1b\[[\d;]*m/', '', $line) ?? $line;
    }
}
