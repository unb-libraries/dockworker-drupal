<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\Regression\RegressionCheckContext;
use Dockworker\Regression\RegressionCheckResult;
use Dockworker\Regression\RegressionCheckSeverity;

/**
 * Provides regression checks specific to Drupal applications.
 *
 * Subscribes to the dockworker-regression-checks event dispatched by
 * dockworker-application's RegressionCheckCommands. Each @hook method is its
 * own handler so the orchestrator's per-handler try/catch isolates failures:
 * a throw in one check does not suppress results from the others.
 *
 * The Drupal 11+ canonical stack is asserted via four service handlers
 * (mariadb / redis / mailpit / solr) plus a sentinel that reports a missing
 * docker-compose.yml exactly once. Service-specific handlers silently no-op
 * when compose is absent so the user sees a single "compose missing" result
 * rather than one per registered service.
 */
class DrupalRegressionCheckCommands extends DockworkerDrupalCommands
{
    /**
     * The minimum Drupal core version for which these checks apply.
     */
    private const MINIMUM_DRUPAL_VERSION = 11;

    /**
     * Canonical Drupal 11+ MariaDB compose service and image pin.
     */
    private const EXPECTED_MYSQL_SERVICE = 'drupal-mysql-lib-unb-ca';
    private const EXPECTED_MYSQL_IMAGE = 'ghcr.io/unb-libraries/mariadb:12.0';

    /**
     * Canonical Drupal 11+ Redis compose service and image pin.
     */
    private const EXPECTED_REDIS_SERVICE = 'drupal-redis-lib-unb-ca';
    private const EXPECTED_REDIS_IMAGE = 'ghcr.io/unb-libraries/redis:8.2-alpine';

    /**
     * Canonical Drupal 11+ Mailpit compose service and image pin.
     *
     * The compose service key is "mailhog" for legacy reasons; the image is
     * mailpit. Check IDs use the canonical "mailpit" identifier while message
     * text names the literal "mailhog" compose service.
     */
    private const EXPECTED_MAILPIT_SERVICE = 'mailhog';
    private const EXPECTED_MAILPIT_IMAGE = 'ghcr.io/unb-libraries/mailpit:v1.29';

    /**
     * Canonical Drupal 11+ Solr compose service and image pin.
     *
     * Solr is optional: sites without search legitimately omit this service.
     */
    private const EXPECTED_SOLR_SERVICE = 'drupal-solr-lib-unb-ca';
    private const EXPECTED_SOLR_IMAGE = 'ghcr.io/unb-libraries/solr-drupal:9.x-11.x';

    /**
     * Verifies that docker-compose.yml exists at the repository root.
     *
     * The single canonical source of the "compose missing" FAIL: service-level
     * handlers silently no-op on null compose so this result appears at most
     * once per run regardless of how many service checks are registered.
     *
     * @hook on-event dockworker-regression-checks
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    public function checkDrupalComposeFilePresent(RegressionCheckContext $ctx): array
    {
        if (!$this->gateDrupal11Plus($ctx)) {
            return [];
        }
        if ($ctx->getDockerComposeConfig() !== null) {
            return [];
        }
        return [
            RegressionCheckResult::fail(
                'drupal:compose:missing',
                sprintf(
                    'docker-compose.yml not found at repository root, but framework is Drupal %d+.',
                    self::MINIMUM_DRUPAL_VERSION,
                ),
            ),
        ];
    }

    /**
     * Verifies the canonical MariaDB service and image pin for Drupal 11+.
     *
     * @hook on-event dockworker-regression-checks
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    public function checkDrupalMysqlService(RegressionCheckContext $ctx): array
    {
        if (!$this->gateDrupal11Plus($ctx)) {
            return [];
        }
        if ($ctx->getDockerComposeConfig() === null) {
            return [];
        }
        return $this->assertComposeServiceImage(
            $ctx->getDockerComposeServices(),
            self::EXPECTED_MYSQL_SERVICE,
            self::EXPECTED_MYSQL_IMAGE,
            'mysql',
            RegressionCheckSeverity::Fail,
        );
    }

    /**
     * Verifies the canonical Redis service and image pin for Drupal 11+.
     *
     * @hook on-event dockworker-regression-checks
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    public function checkDrupalRedisService(RegressionCheckContext $ctx): array
    {
        if (!$this->gateDrupal11Plus($ctx)) {
            return [];
        }
        if ($ctx->getDockerComposeConfig() === null) {
            return [];
        }
        return $this->assertComposeServiceImage(
            $ctx->getDockerComposeServices(),
            self::EXPECTED_REDIS_SERVICE,
            self::EXPECTED_REDIS_IMAGE,
            'redis',
            RegressionCheckSeverity::Warn,
        );
    }

    /**
     * Verifies the canonical Mailpit service and image pin for Drupal 11+.
     *
     * The compose service key is "mailhog" (legacy); the image is mailpit.
     *
     * @hook on-event dockworker-regression-checks
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    public function checkDrupalMailpitService(RegressionCheckContext $ctx): array
    {
        if (!$this->gateDrupal11Plus($ctx)) {
            return [];
        }
        if ($ctx->getDockerComposeConfig() === null) {
            return [];
        }
        return $this->assertComposeServiceImage(
            $ctx->getDockerComposeServices(),
            self::EXPECTED_MAILPIT_SERVICE,
            self::EXPECTED_MAILPIT_IMAGE,
            'mailpit',
            RegressionCheckSeverity::Warn,
        );
    }

    /**
     * Verifies the canonical Solr service image pin for Drupal 11+ if present.
     *
     * Solr is an optional service; the missing-service case is silently
     * skipped so sites without search do not produce a warning.
     *
     * @hook on-event dockworker-regression-checks
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    public function checkDrupalSolrService(RegressionCheckContext $ctx): array
    {
        if (!$this->gateDrupal11Plus($ctx)) {
            return [];
        }
        if ($ctx->getDockerComposeConfig() === null) {
            return [];
        }
        return $this->assertComposeServiceImage(
            $ctx->getDockerComposeServices(),
            self::EXPECTED_SOLR_SERVICE,
            self::EXPECTED_SOLR_IMAGE,
            'solr',
            null,
        );
    }

    /**
     * Returns true if the managed application is Drupal 11 or later.
     *
     * Single source of truth for the framework + version gate shared by every
     * regression handler in this class. The version match uses a leading-int
     * regex (not version_compare()) so non-numeric versions like "latest"
     * safely no-op rather than behaving unpredictably.
     */
    private function gateDrupal11Plus(RegressionCheckContext $ctx): bool
    {
        if (strtolower((string) ($ctx->frameworkName ?? '')) !== 'drupal') {
            return false;
        }
        if (
            !preg_match('/^(\d+)/', (string) ($ctx->frameworkVersion ?? ''), $matches)
            || (int) $matches[1] < self::MINIMUM_DRUPAL_VERSION
        ) {
            return false;
        }
        return true;
    }

    /**
     * Asserts that $serviceName exists in $services and is pinned to $expectedImage.
     *
     * Emits at most one result:
     *   - missing service: severity $missingSeverity (or nothing if null);
     *   - service present but image mismatch: WARN;
     *   - service present and image correct: no result.
     *
     * Check IDs:
     *   - "drupal:compose:missing-{idSlug}-service"
     *   - "drupal:compose:{idSlug}-image-mismatch"
     *
     * @param array<string, mixed> $services
     *   Parsed services map from docker-compose.yml.
     * @param string $serviceName
     *   The compose service key to look for (e.g. "drupal-mysql-lib-unb-ca",
     *   "mailhog"). Used verbatim in user-facing message text.
     * @param string $expectedImage
     *   The canonical image string (e.g. "ghcr.io/unb-libraries/mariadb:12.0").
     * @param string $idSlug
     *   Short identifier for check IDs ("mysql", "redis", "mailpit", "solr").
     * @param ?RegressionCheckSeverity $missingSeverity
     *   Severity for the missing-service case. Pass null to skip silently when
     *   the service block is absent (used for optional services like solr).
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    private function assertComposeServiceImage(
        array $services,
        string $serviceName,
        string $expectedImage,
        string $idSlug,
        ?RegressionCheckSeverity $missingSeverity,
    ): array {
        if (!isset($services[$serviceName])) {
            return match ($missingSeverity) {
                null => [],
                RegressionCheckSeverity::Fail => [
                    RegressionCheckResult::fail(
                        'drupal:compose:missing-' . $idSlug . '-service',
                        sprintf(
                            'docker-compose.yml is missing required Drupal %d+ service "%s".',
                            self::MINIMUM_DRUPAL_VERSION,
                            $serviceName,
                        ),
                        sprintf(
                            'Add a "%s" service to docker-compose.yml backed by image "%s".',
                            $serviceName,
                            $expectedImage,
                        ),
                    ),
                ],
                RegressionCheckSeverity::Warn => [
                    RegressionCheckResult::warn(
                        'drupal:compose:missing-' . $idSlug . '-service',
                        sprintf(
                            'docker-compose.yml is missing the canonical Drupal %d+ service "%s".',
                            self::MINIMUM_DRUPAL_VERSION,
                            $serviceName,
                        ),
                        sprintf(
                            'Add a "%s" service to docker-compose.yml backed by image "%s".',
                            $serviceName,
                            $expectedImage,
                        ),
                    ),
                ],
            };
        }

        $service = $services[$serviceName];
        $actualImage = is_array($service) && isset($service['image']) && is_string($service['image'])
            ? $service['image']
            : null;
        if ($actualImage === $expectedImage) {
            return [];
        }
        return [
            RegressionCheckResult::warn(
                'drupal:compose:' . $idSlug . '-image-mismatch',
                sprintf(
                    'docker-compose.yml service "%s" uses image "%s"; expected "%s".',
                    $serviceName,
                    $actualImage ?? '(none)',
                    $expectedImage,
                ),
                sprintf(
                    'Pin the image to "%s" unless this site has an intentional override.',
                    $expectedImage,
                ),
            ),
        ];
    }
}
