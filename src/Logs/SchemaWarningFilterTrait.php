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
        // Buffer for the verb-only header line when the config name
        // wraps to the following line (Drush 13 / Symfony Console wraps
        // long config names after the verb). Held until the name lands
        // on a subsequent line so the allowlist decision can be made
        // atomically.
        $pendingHeaderLine = null;

        foreach ($lines as $index => $line) {
            $bare = $this->stripAnsi($line);

            // Verb-then-name wrap: previous line was a bare verb. Try
            // to extract the config name from the start of this line.
            if ($pendingHeaderLine !== null) {
                if ($this->isContentlessLine($bare)) {
                    // Drush sometimes leaves a blank between verb and
                    // name. Drop it silently and keep waiting. Also
                    // covers docker-compose prefix-only lines like
                    // "nbbib-lib-unb-ca  |" with no content after the
                    // pipe.
                    continue;
                }
                $headerName = $this->detectWrappedHeaderName($bare);
                $inBlock = true;
                $blockBodyLines = 0;
                $blockHeaderLineNum = $index;
                if (
                    $headerName === null
                    || !$this->matchesSchemaAllowlist($headerName, $allowlist)
                ) {
                    // Tripwire — emit both the buffered verb line and
                    // this line so the build still fails on configs
                    // the user hasn't opted into suppressing (or on a
                    // future Drush format we can't parse).
                    $output[] = $pendingHeaderLine;
                    $output[] = $line;
                }
                $pendingHeaderLine = null;
                if ($this->isBlockTerminator($bare)) {
                    $inBlock = false;
                }
                continue;
            }

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

            // Bare-verb form: verb on this line, name will arrive on
            // the next non-blank line.
            if ($this->isPendingHeader($bare)) {
                $pendingHeaderLine = $line;
                $inBlock = false;
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
     * Builds regex exception patterns for the streaming log scanner.
     *
     * The block parser (stripSchemaWarningBlocks) only runs for the
     * file-based logs:check-file path. The local deploy monitor in
     * dockworker-daemon scans streaming log chunks via logsHaveErrors()
     * and never sees a complete file. For that path we still need
     * regex-based line suppression so allowlisted schema-warning lines
     * don't trip the build mid-deploy.
     *
     * Returns:
     *  - one allowlist-header pattern (or null if allowlist is empty)
     *  - a fixed set of boilerplate body-line patterns whose substrings
     *    contain error-pattern keywords ("errors", "fatal", etc.) and
     *    therefore would otherwise be flagged
     *
     * @param string[] $allowlist
     *   Config-name allowlist as accepted by stripSchemaWarningBlocks().
     *
     * @return string[]
     *   Regex alternatives suitable for use as exception strings by
     *   LogCheckerTrait::logsHaveErrors().
     */
    public function buildSchemaWarningExceptionPatterns(array $allowlist): array
    {
        $patterns = [
            // Wrap-tail anchor: any line ending with "errors:" or
            // "error:" (Drush wraps "Schema errors for X with the
            // following errors:" at varying points depending on the
            // length of X).
            '\berrors?:\s*$',
            // Bare-verb form: long config names get wrapped onto the
            // next line after the verb, leaving "[warning] Message:
            // Schema errors for" alone (plus trailing whitespace) on
            // the header line. logsHaveErrors is line-by-line and
            // can't correlate with the next-line name, so suppression
            // here is unconditional. Tripwire fidelity for this wrap
            // shape lives in the file-scan block parser, which has
            // the whole log in hand.
            '\[warning\][^\n]*?(?:Message:\s*)?(?:Schema errors for|No schema for)\s*$',
            // Body-prose lines that contain error-pattern substrings.
            'These errors mean there',
            'is configuration that does not comply with its schema',
            'does not comply with its schema',
            'not a fatal error, but it is',
            'recommended to fix these issues',
            'missing schema',
        ];

        $headerPattern = $this->buildAllowlistHeaderPattern($allowlist);
        if ($headerPattern !== null) {
            $patterns[] = $headerPattern;
        }

        return $patterns;
    }

    /**
     * Builds a regex matching "[warning] Schema errors for X" / "No
     * schema for X" headers where X is in the allowlist.
     *
     * Used only by the streaming exception path; the block parser
     * matches on headers structurally, not via regex.
     *
     * @return string|null
     *   The regex alternation, or null if the allowlist is empty.
     */
    private function buildAllowlistHeaderPattern(array $allowlist): ?string
    {
        $fragments = [];
        foreach ($allowlist as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            // Reuse the same wildcard semantics as matchesSchemaAllowlist.
            if (str_ends_with($entry, '.*')) {
                $prefix = preg_quote(substr($entry, 0, -2), '/');
                $fragments[$prefix . '(?:\.[^\s]*)?'] = true;
                continue;
            }
            if (str_contains($entry, '*')) {
                $fragments[str_replace('\*', '[^.\s]*', preg_quote($entry, '/'))] = true;
                continue;
            }
            // Exact entry: bind tightly so "system.file" doesn't bind
            // inside "system.file.foo" via the streaming regex.
            $fragments[preg_quote($entry, '/') . '(?=[\s:.]|$)'] = true;
        }
        if ($fragments === []) {
            return null;
        }
        $group = '(?:' . implode('|', array_keys($fragments)) . ')';
        // Either header verb, with optional "Message:" prefix.
        return '\[warning\][^\n]*?(?:Message:\s*)?(?:Schema errors for|No schema for)\s+' . $group;
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
     * Tests whether a line has no content after stripping a possible
     * docker-compose log prefix ("<slug>  | ").
     *
     * Used to skip blank continuation lines between a bare verb and
     * the wrapped name without mis-classifying them as the name line.
     */
    private function isContentlessLine(string $line): bool
    {
        $stripped = preg_replace('/^[^|]*\|\s*/', '', $line) ?? $line;
        return trim($stripped) === '';
    }

    /**
     * Tests whether a line is a bare-verb header awaiting a wrapped name.
     *
     * Drush 13 / Symfony Console wraps schema warnings whose config
     * name pushes the line past the wrap column right after the verb,
     * yielding a header line whose only content after "Schema errors
     * for" / "No schema for" is whitespace. The config name lands at
     * the start of the next non-blank line.
     */
    private function isPendingHeader(string $line): bool
    {
        return preg_match(
            '/\[warning\][^\n]*?(?:Message:\s*)?(?:Schema errors for|No schema for)\s*$/',
            $line
        ) === 1;
    }

    /**
     * Extracts a config name from the start of a wrapped continuation line.
     *
     * Used only when the previous line was a bare verb. The bracketed
     * "[warning]" prefix is on the previous line, so we look for the
     * first config-name-shaped token, after optionally consuming a
     * docker-compose log prefix ("<slug>  | "). Without the prefix
     * skip, the slug itself (which is a valid name token) would be
     * captured instead of the config name.
     */
    private function detectWrappedHeaderName(string $line): ?string
    {
        if (preg_match('/^(?:[^|]*\|\s*)?([A-Za-z0-9_.\-]+)/', $line, $m) !== 1) {
            return null;
        }
        return rtrim($m[1], '.');
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
