<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Dockworker\DockworkerDrupalCommands;
use Dockworker\Logs\SchemaWarningFilterTrait;
use Robo\Robo;

/**
 * Hooks into logs:check-file to strip Drupal config-schema warning blocks
 * before the upstream line-level scanner runs.
 *
 * Reads the user's allowlist from
 *   dockworker.drupal.schemas.ignore_enforcement
 * and rewrites the log file in place before the scanner sees it. The
 * allowlist may be empty: even then, schema-warning bodies (cosmetic
 * boilerplate that triggers false positives in the line scanner) are
 * stripped while the header line passes through, preserving the tripwire
 * for un-allowlisted configs.
 */
class DrupalLogCheckCommands extends DockworkerDrupalCommands
{
    use SchemaWarningFilterTrait;

    /**
     * Set of warning messages already emitted this process.
     *
     * Robo dispatches hooks on a fresh instance, so a private static
     * set is the simplest way to keep each distinct warning to one
     * emission per command run.
     */
    private static array $emittedWarnings = [];

    /**
     * Strips Drupal config-schema warning blocks before logs:check-file scans.
     *
     * Runs at pre-command time so the file argument has been validated by
     * Robo but the upstream scanner has not yet read the file. Mutating
     * the file in place is acceptable: log captures are transient (one
     * file per CI run), and a re-run of logs:check-file on the same path
     * is idempotent because the cleaned file no longer contains schema
     * warning blocks.
     *
     * @hook pre-command logs:check-file
     */
    public function stripDrupalSchemaWarningsBeforeLogCheck(
        CommandData $commandData
    ): void {
        $path = $commandData->input()->getArgument('file_path');
        if (
            !is_string($path)
            || !is_readable($path)
            || !is_writable($path)
        ) {
            return;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return;
        }

        $allowlist = $this->resolveSchemaWarningAllowlist();
        $cleaned = $this->stripSchemaWarningBlocks($contents, $allowlist);
        if ($cleaned !== $contents) {
            @file_put_contents($path, $cleaned);
        }
    }

    /**
     * Reads, validates, and normalises the schema-warning allowlist.
     *
     * Surfaces one known silent-failure mode loudly to STDERR: the
     * allowlist landing at the wrong YAML key (e.g. 'schemas:' as a
     * sibling of 'drupal:' instead of a child). That mis-nesting was
     * the original "sometimes works" bug for unbherbarium.
     *
     * @return string[]
     *   Normalised allowlist of config names. May be empty.
     */
    private function resolveSchemaWarningAllowlist(): array
    {
        $config = Robo::config();
        $raw = $config->get('dockworker.drupal.schemas.ignore_enforcement', null);

        if ($raw === null) {
            $misplaced = $config->get('dockworker.schemas.ignore_enforcement', null);
            if ($misplaced !== null) {
                $this->warnOnce(
                    "[dockworker] dockworker.yml: 'schemas:' is at the root of 'dockworker:' but the loader expects 'dockworker.drupal.schemas:'. Re-indent 'schemas:' under 'drupal:'. The allowlist is currently being IGNORED."
                );
            }
            $raw = [];
        }

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

    /**
     * Emits a warning to STDERR exactly once per process per distinct
     * message (keyed by the message's first line).
     */
    private function warnOnce(string $message): void
    {
        $key = strtok($message, "\n") ?: $message;
        if (isset(self::$emittedWarnings[$key])) {
            return;
        }
        self::$emittedWarnings[$key] = true;
        fwrite(STDERR, $message . "\n");
    }
}
