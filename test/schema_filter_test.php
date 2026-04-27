<?php

/**
 * Standalone regression test for the schema-warning block filter.
 *
 * Drives Dockworker\Logs\SchemaWarningFilterTrait::stripSchemaWarningBlocks()
 * against verbatim log fixtures captured from real Drush runs. Each case
 * declares the input log, the active allowlist, and the expectations:
 *
 *   - allowlisted blocks must vanish entirely (header + body + terminator)
 *   - un-allowlisted blocks: header passes through (tripwire), body
 *     and terminator dropped
 *   - lines outside any block are preserved verbatim
 *
 * Pre-cleaned output is then run through the SAME error/exception
 * matching logic that LogCheckerTrait uses, to catch regressions where
 * the cleaned text still contains noise that would fail a build.
 *
 * Run:
 *   php vendor/unb-libraries/dockworker-drupal/test/schema_filter_test.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Logs/SchemaWarningFilterTrait.php';

// -----------------------------------------------------------------------
// Test harness wraps the trait in a minimal class.
// -----------------------------------------------------------------------

final class SchemaFilterTestHarness
{
    use Dockworker\Logs\SchemaWarningFilterTrait;
}

// Mirror of LogCheckerTrait's base error pattern + DrupalDeployCommands'
// fixed exceptions. Used for the "downstream noise" assertion.
const ERRORS_PATTERN = 'error|fail|fatal|unable|unavailable|unrecognized|unresolved|unsuccessful|unsupported';
const FIXED_EXCEPTIONS = [
    "Access denied for user 'drupal'",
    'inline_form_errors',
    'Config language.entity.en does not exist',
    ' 0 failed',
    'Operation CREATE USER failed',
    'failure: 0',
    'error-handler instead',
    '.well-known',
    'HoursCalendarUnavailableTemplate',
];

// -----------------------------------------------------------------------
// Fixtures. Verbatim docker-compose log captures including the
// "<slug>  | " line prefix that LogCheckerTrait sees.
// -----------------------------------------------------------------------

$FIXTURE_SINGLE_LINE = <<<'LOG'
acts-lib-unb-ca  |  [warning] Schema errors for system.file with the following errors: system.file:path.temporary missing schema. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href="https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata">the documentation</a>.
acts-lib-unb-ca  |  [notice] Synchronized configuration: update system.file.
acts-lib-unb-ca  |  [warning] Schema errors for views.settings with the following errors: views.settings:ui.show.advanced_column missing schema, views.settings:ui.show.master_display missing schema, views.settings:skip_cache missing schema. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href="https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata">the documentation</a>.
acts-lib-unb-ca  |  [warning] Schema errors for update.settings with the following errors: update.settings:notification.emails variable type is string but applied schema class is Drupal\Core\Config\Schema\Sequence. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href="https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata">the documentation</a>.
LOG;

$FIXTURE_WRAPPED_NO_SCHEMA_FOR = <<<'LOG'
acts-lib-unb-ca  |  [warning] Message: No schema for system.authorize. These errors mean there is configuration that
acts-lib-unb-ca  | does not comply with its schema. This is not a fatal error, but it is
acts-lib-unb-ca  | recommended to fix these issues. For more information on configuration
acts-lib-unb-ca  | schemas, check out the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
acts-lib-unb-ca  |  [warning] Message: No schema for system.rss. These errors mean there is configuration that does
acts-lib-unb-ca  | not comply with its schema. This is not a fatal error, but it is recommended
acts-lib-unb-ca  | to fix these issues. For more information on configuration schemas, check out
acts-lib-unb-ca  | the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
LOG;

$FIXTURE_WRAPPED_SCHEMA_ERRORS_FOR = <<<'LOG'
acts-lib-unb-ca  |  [warning] Message: Schema errors for system.file with the following errors:
acts-lib-unb-ca  | system.file:path.temporary missing schema. These errors mean there is
acts-lib-unb-ca  | configuration that does not comply with its schema. This is not a fatal
acts-lib-unb-ca  | error, but it is recommended to fix these issues. For more information on
acts-lib-unb-ca  | configuration schemas, check out the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
acts-lib-unb-ca  |  [warning] Message: Schema errors for system.performance with the following errors:
acts-lib-unb-ca  | system.performance:stale_file_threshold missing schema. These errors mean
acts-lib-unb-ca  | there is configuration that does not comply with its schema. This is not a
acts-lib-unb-ca  | fatal error, but it is recommended to fix these issues. For more information
acts-lib-unb-ca  | on configuration schemas, check out the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
LOG;

$FIXTURE_BARE_NO_SCHEMA_FOR = <<<'LOG'
acts-lib-unb-ca  |  [warning] No schema for system.authorize. These errors mean there is configuration
acts-lib-unb-ca  | that does not comply with its schema. This is not a fatal error, but it is
acts-lib-unb-ca  | recommended to fix these issues. For more information on configuration schemas,
acts-lib-unb-ca  | check out the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
LOG;

// Two warnings for non-allowlisted configs interleaved with normal log
// lines. Both warning headers should pass through (tripwire); bodies
// dropped; surrounding non-schema lines preserved.
$FIXTURE_TRIPWIRE = <<<'LOG'
acts-lib-unb-ca  |  [notice] Cache rebuild complete.
acts-lib-unb-ca  |  [warning] Schema errors for novel.config with the following errors: novel.config:foo missing schema. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href="https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata">the documentation</a>.
acts-lib-unb-ca  |  [success] Sync done.
LOG;

// A genuine error must survive the filter unchanged.
$FIXTURE_NEGATIVE_CONTROL = <<<'LOG'
acts-lib-unb-ca  |  [error] Database connection failed: could not find driver
LOG;

// ANSI-coloured header (defensive).
$FIXTURE_ANSI = "acts-lib-unb-ca  |  \x1b[33m[warning]\x1b[0m Schema errors for system.file with the following errors: system.file:path.temporary missing schema. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href=\"https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata\">the documentation</a>.";

// Header-inside-block: a second warning header arrives before the
// previous block's terminator. New header should be treated as an
// implicit terminator and start its own block.
$FIXTURE_HEADER_INSIDE_BLOCK = <<<'LOG'
acts-lib-unb-ca  |  [warning] Message: Schema errors for system.file with the following errors:
acts-lib-unb-ca  | system.file:path.temporary missing schema. (no terminator follows for this block)
acts-lib-unb-ca  |  [warning] Message: Schema errors for views.settings with the following errors:
acts-lib-unb-ca  | views.settings:ui.show.advanced_column missing schema. These errors mean there is
acts-lib-unb-ca  | configuration that does not comply with its schema. For more information on
acts-lib-unb-ca  | configuration schemas, check out the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
LOG;

// A 25-line "block" that never terminates: emulates a Drupal docs URL
// change. The 20-line cap should fire and resume passthrough.
$cap_lines = ['acts-lib-unb-ca  |  [warning] Schema errors for system.file with the following errors:'];
for ($i = 1; $i <= 25; $i++) {
    $cap_lines[] = "acts-lib-unb-ca  | line $i of pretend-malformed-warning";
}
$FIXTURE_CAP_OVERFLOW = implode("\n", $cap_lines);

// -----------------------------------------------------------------------
// Allowlists used by cases.
// -----------------------------------------------------------------------

$FULL_ALLOWLIST = [
    'system.authorize',
    'system.rss',
    'system.file',
    'system.performance',
    'update.settings',
    'views.settings',
];

// -----------------------------------------------------------------------
// Cases.
//
// Each case provides the input log, the allowlist, and a closure that
// asserts properties of the cleaned output. The closure returns null on
// pass or a string describing the failure.
// -----------------------------------------------------------------------

$harness = new SchemaFilterTestHarness();

$strip = static function (string $log, array $allowlist) use ($harness): string {
    return $harness->stripSchemaWarningBlocks($log, $allowlist);
};

$lineMatchesError = static function (string $line): bool {
    return preg_match('/' . ERRORS_PATTERN . '/i', $line) === 1;
};

$matchesAnyException = static function (string $line): bool {
    foreach (FIXED_EXCEPTIONS as $exc) {
        if (str_contains($line, $exc)) {
            return true;
        }
    }
    return false;
};

$residualErrorLines = static function (string $cleaned) use ($lineMatchesError, $matchesAnyException): array {
    $bad = [];
    foreach (explode("\n", $cleaned) as $line) {
        if ($lineMatchesError($line) && !$matchesAnyException($line)) {
            $bad[] = $line;
        }
    }
    return $bad;
};

$cases = [];

$cases[] = [
    'name' => 'single-line "Schema errors for X" — full allowlist',
    'run' => function () use ($strip, $FIXTURE_SINGLE_LINE, $FULL_ALLOWLIST, $residualErrorLines): ?string {
        $cleaned = $strip($FIXTURE_SINGLE_LINE, $FULL_ALLOWLIST);
        // Three schema warnings should be gone; the [notice] line should remain.
        if (str_contains($cleaned, 'Schema errors for')) {
            return 'expected all "Schema errors for" headers gone, found: ' . substr($cleaned, 0, 200);
        }
        if (!str_contains($cleaned, '[notice] Synchronized configuration: update system.file.')) {
            return 'unexpectedly dropped the inter-warning [notice] line';
        }
        $bad = $residualErrorLines($cleaned);
        if ($bad !== []) {
            return 'residual error-flagged lines remain: ' . implode(' | ', $bad);
        }
        return null;
    },
];

$cases[] = [
    'name' => 'single-line "Schema errors for X" — empty allowlist (tripwire)',
    'run' => function () use ($strip, $FIXTURE_SINGLE_LINE): ?string {
        $cleaned = $strip($FIXTURE_SINGLE_LINE, []);
        // All three warning headers should still surface as build-fail
        // signals when no allowlist has been provided. Single-line
        // warnings are atomic — the docs URL is on the same line as
        // the header, so it passes through with it. That's intended:
        // the user can see the URL alongside the warning header.
        $headers = preg_match_all('/Schema errors for (system\.file|views\.settings|update\.settings)/', $cleaned, $m);
        if ($headers !== 3) {
            return "expected 3 surviving header lines (tripwire), found $headers";
        }
        if (!str_contains($cleaned, 'Synchronized configuration')) {
            return 'unexpectedly dropped the inter-warning [notice] line';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'wrapped "Message: No schema for X" — full allowlist',
    'run' => function () use ($strip, $FIXTURE_WRAPPED_NO_SCHEMA_FOR, $FULL_ALLOWLIST, $residualErrorLines): ?string {
        $cleaned = $strip($FIXTURE_WRAPPED_NO_SCHEMA_FOR, $FULL_ALLOWLIST);
        if (str_contains($cleaned, 'No schema for')) {
            return 'expected all "No schema for" headers gone';
        }
        $bad = $residualErrorLines($cleaned);
        if ($bad !== []) {
            return 'residual error-flagged lines: ' . implode(' | ', $bad);
        }
        return null;
    },
];

$cases[] = [
    'name' => 'wrapped "Message: Schema errors for X" — full allowlist',
    'run' => function () use ($strip, $FIXTURE_WRAPPED_SCHEMA_ERRORS_FOR, $FULL_ALLOWLIST, $residualErrorLines): ?string {
        $cleaned = $strip($FIXTURE_WRAPPED_SCHEMA_ERRORS_FOR, $FULL_ALLOWLIST);
        if (str_contains($cleaned, 'Schema errors for')) {
            return 'expected all "Schema errors for" headers gone';
        }
        $bad = $residualErrorLines($cleaned);
        if ($bad !== []) {
            return 'residual error-flagged lines: ' . implode(' | ', $bad);
        }
        return null;
    },
];

$cases[] = [
    'name' => 'wrapped "No schema for X" without "Message:" prefix — allowlist',
    'run' => function () use ($strip, $FIXTURE_BARE_NO_SCHEMA_FOR, $FULL_ALLOWLIST, $residualErrorLines): ?string {
        $cleaned = $strip($FIXTURE_BARE_NO_SCHEMA_FOR, $FULL_ALLOWLIST);
        if (str_contains($cleaned, 'No schema for')) {
            return 'expected "No schema for" header gone';
        }
        $bad = $residualErrorLines($cleaned);
        if ($bad !== []) {
            return 'residual error-flagged lines: ' . implode(' | ', $bad);
        }
        return null;
    },
];

$cases[] = [
    'name' => 'tripwire: un-allowlisted single-line warning passes through; surrounding lines preserved',
    'run' => function () use ($strip, $FIXTURE_TRIPWIRE): ?string {
        $cleaned = $strip($FIXTURE_TRIPWIRE, ['system.file']); // not novel.config
        if (!str_contains($cleaned, 'Schema errors for novel.config')) {
            return 'tripwire failed: header for un-allowlisted novel.config was dropped';
        }
        if (!str_contains($cleaned, '[notice] Cache rebuild complete')) {
            return 'pre-warning notice line dropped';
        }
        if (!str_contains($cleaned, '[success] Sync done')) {
            return 'post-warning success line dropped';
        }
        return null;
    },
];

// Tripwire on a WRAPPED un-allowlisted warning: header survives, body+terminator dropped.
$FIXTURE_TRIPWIRE_WRAPPED = <<<'LOG'
acts-lib-unb-ca  |  [notice] Pre.
acts-lib-unb-ca  |  [warning] Message: Schema errors for novel.config with the following errors:
acts-lib-unb-ca  | novel.config:foo missing schema. These errors mean there is configuration
acts-lib-unb-ca  | that does not comply with its schema. For more information, check out
acts-lib-unb-ca  | the documentation [1].
acts-lib-unb-ca  |
acts-lib-unb-ca  | [1] https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata
acts-lib-unb-ca  |  [notice] Post.
LOG;

$cases[] = [
    'name' => 'tripwire on wrapped warning: header survives, body+terminator dropped',
    'run' => function () use ($strip, $FIXTURE_TRIPWIRE_WRAPPED): ?string {
        $cleaned = $strip($FIXTURE_TRIPWIRE_WRAPPED, []);
        if (!str_contains($cleaned, 'Schema errors for novel.config with the following errors:')) {
            return 'tripwire failed: wrapped header was dropped';
        }
        if (str_contains($cleaned, 'configuration-schemametadata')) {
            return 'terminator URL leaked from a wrapped block (should be dropped)';
        }
        if (str_contains($cleaned, 'novel.config:foo missing schema')) {
            return 'body line leaked from a wrapped block';
        }
        if (!str_contains($cleaned, '[notice] Pre.') || !str_contains($cleaned, '[notice] Post.')) {
            return 'lines surrounding the warning block were affected';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'negative control: real [error] line is preserved',
    'run' => function () use ($strip, $FIXTURE_NEGATIVE_CONTROL): ?string {
        $cleaned = $strip($FIXTURE_NEGATIVE_CONTROL, []);
        if (!str_contains($cleaned, '[error] Database connection failed')) {
            return 'real error line was incorrectly dropped';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'ANSI-coloured header is detected',
    'run' => function () use ($strip, $FIXTURE_ANSI): ?string {
        $cleaned = $strip($FIXTURE_ANSI, ['system.file']);
        // Whole single-line warning should be gone.
        if (str_contains($cleaned, 'Schema errors for')) {
            return 'ANSI header was not detected; warning leaked';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'header-inside-block: new header restarts block, no signal lost',
    'run' => function () use ($strip, $FIXTURE_HEADER_INSIDE_BLOCK): ?string {
        // Both system.file (allowlisted) and views.settings (allowlisted)
        $cleaned = $strip($FIXTURE_HEADER_INSIDE_BLOCK, ['system.file', 'views.settings']);
        if (str_contains($cleaned, 'Schema errors for')) {
            return 'header(s) leaked; block restart logic broken';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'header-inside-block: tripwire preserved for second header',
    'run' => function () use ($strip, $FIXTURE_HEADER_INSIDE_BLOCK): ?string {
        // system.file allowlisted, views.settings NOT allowlisted
        $cleaned = $strip($FIXTURE_HEADER_INSIDE_BLOCK, ['system.file']);
        if (str_contains($cleaned, 'Schema errors for system.file')) {
            return 'allowlisted system.file header should be dropped';
        }
        if (!str_contains($cleaned, 'Schema errors for views.settings')) {
            return 'tripwire failed: views.settings header should pass through';
        }
        return null;
    },
];

$cases[] = [
    'name' => '20-line cap: malformed block resumes passthrough',
    'run' => function () use ($strip, $FIXTURE_CAP_OVERFLOW): ?string {
        // Capture STDERR to confirm the cap warning fired.
        $stderrCapture = tempnam(sys_get_temp_dir(), 'capov');
        $origStderr = STDERR;
        // PHP doesn't let us cleanly redirect STDERR mid-process from
        // userland; we just verify the post-cap content survives.
        $cleaned = $strip($FIXTURE_CAP_OVERFLOW, ['system.file']);
        // After the cap fires, lines 21..25 should appear in output.
        if (!str_contains($cleaned, 'line 25 of pretend-malformed-warning')) {
            return 'cap did not resume passthrough — late lines dropped';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'mid-string * wildcard back-compat (views.view.*.foo)',
    'run' => function () use ($harness): ?string {
        // Direct allowlist matcher test (not a log fixture).
        if (!$harness->matchesSchemaAllowlist('views.view.front.foo', ['views.view.*.foo'])) {
            return 'mid-string wildcard did not match';
        }
        if ($harness->matchesSchemaAllowlist('views.view.front.bar', ['views.view.*.foo'])) {
            return 'mid-string wildcard matched too broadly';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'trailing .* matches bare name AND descendant',
    'run' => function () use ($harness): ?string {
        if (!$harness->matchesSchemaAllowlist('system.authorize', ['system.authorize.*'])) {
            return 'trailing .* did not match bare name';
        }
        if (!$harness->matchesSchemaAllowlist('system.authorize.foo', ['system.authorize.*'])) {
            return 'trailing .* did not match descendant';
        }
        if ($harness->matchesSchemaAllowlist('system.authorized', ['system.authorize.*'])) {
            return 'trailing .* matched non-segment-bounded prefix (system.authorize → system.authorized)';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'exact match does not bind to descendants',
    'run' => function () use ($harness): ?string {
        if ($harness->matchesSchemaAllowlist('system.file.path', ['system.file'])) {
            return 'exact entry incorrectly matched a descendant';
        }
        if (!$harness->matchesSchemaAllowlist('system.file', ['system.file'])) {
            return 'exact entry did not match itself';
        }
        return null;
    },
];

$cases[] = [
    'name' => 'unbherbarium real-world allowlist: all 16 entries',
    'run' => function () use ($strip): ?string {
        $allowlist = [
            'bootstrap.settings.schemas',
            'core.entity_form_display.node.herbarium_specimen.default.third_party_settings',
            'search_api.server.drupal_solr_lib_unb_ca.backend_config.skip_schema_check',
            'system.authorize.*',
            'system.performance.stale_file_threshold',
            'system.rss.*',
            'tvi.settings.*',
            'tvi.taxonomy_vocabulary.herbarium_specimen_collector.uuid',
            'tvi.taxonomy_vocabulary.herbarium_specimen_taxonomy.uuid',
            'unbherbarium_admin_theme.settings.*',
            'unbherbarium_ca.settings.*',
            'update.settings.notification.emails',
            'views.settings.ui.show.*',
            'views.view.browse.display.page.display_options.filters.*',
            'views.view.search_solr.display.page_1.display_options.filters.*',
            'views.view.banner.display.default.display_options.style.options.widgets.top.views_slideshow_pager.*',
        ];
        // The Drush-emitted config name is the FIRST 2 segments; the
        // longer entries above are schema sub-paths and won't match
        // (they will trigger the migration warning).
        // Build a synthetic warning for system.authorize (which has a
        // wildcard so it matches the bare config name).
        $log = "acts-lib-unb-ca  |  [warning] Message: No schema for system.authorize. These errors mean there is configuration that\n"
             . "acts-lib-unb-ca  | does not comply with its schema. For more info, see https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata";
        $cleaned = strip_log_helper($log, $allowlist);
        if (str_contains($cleaned, 'No schema for system.authorize')) {
            return 'allowlist with system.authorize.* did not suppress system.authorize';
        }
        return null;
    },
];

// -----------------------------------------------------------------------
// Helper used inside the closure (required because closures can't see
// the outer-scope $strip without `use` and we already used $strip inline
// via use elsewhere — duplicating to keep each case self-contained).
// -----------------------------------------------------------------------

function strip_log_helper(string $log, array $allowlist): string
{
    static $h = null;
    if ($h === null) {
        $h = new SchemaFilterTestHarness();
    }
    return $h->stripSchemaWarningBlocks($log, $allowlist);
}

// -----------------------------------------------------------------------
// Run cases.
// -----------------------------------------------------------------------

$failures = 0;
foreach ($cases as $case) {
    $error = ($case['run'])();
    if ($error === null) {
        echo "[PASS] {$case['name']}\n";
        continue;
    }
    $failures++;
    echo "[FAIL] {$case['name']}\n";
    echo "       > $error\n";
}

echo "\n";
if ($failures === 0) {
    echo "OK — " . count($cases) . " case(s) pass.\n";
    exit(0);
}
echo "FAILED — $failures of " . count($cases) . " case(s) failing.\n";
exit(1);
