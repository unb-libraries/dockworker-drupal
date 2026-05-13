<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\Regression\RegressionCheckContext;
use Dockworker\Regression\RegressionCheckResult;

/**
 * Provides regression checks specific to Drupal applications.
 *
 * Subscribes to the dockworker-regression-checks event dispatched by
 * dockworker-application's RegressionCheckCommands.
 */
class DrupalRegressionCheckCommands extends DockworkerDrupalCommands
{
    /**
     * The docker-compose service name expected to host the MariaDB database
     * for managed Drupal 11+ applications.
     */
    private const EXPECTED_MYSQL_SERVICE = 'drupal-mysql-lib-unb-ca';

    /**
     * The pinned database image expected on the MariaDB service.
     */
    private const EXPECTED_MYSQL_IMAGE = 'ghcr.io/unb-libraries/mariadb:12.0';

    /**
     * The minimum Drupal core version for which these checks apply.
     */
    private const MINIMUM_DRUPAL_VERSION = 11;

    /**
     * Verifies the canonical MariaDB service and image pin for Drupal 11+.
     *
     * Skips silently for applications whose framework is not Drupal or whose
     * declared major version is below MINIMUM_DRUPAL_VERSION.
     *
     * @hook on-event dockworker-regression-checks
     *
     * @return \Dockworker\Regression\RegressionCheckResult[]
     */
    public function checkDrupalMysqlService(RegressionCheckContext $ctx): array
    {
        if (strtolower((string) ($ctx->frameworkName ?? '')) !== 'drupal') {
            return [];
        }
        if (
            !preg_match('/^(\d+)/', (string) ($ctx->frameworkVersion ?? ''), $matches)
            || (int) $matches[1] < self::MINIMUM_DRUPAL_VERSION
        ) {
            return [];
        }

        $compose = $ctx->getDockerComposeConfig();
        if ($compose === null) {
            return [
                RegressionCheckResult::fail(
                    'drupal:compose:missing',
                    'docker-compose.yml not found at repository root, but framework is Drupal ' . self::MINIMUM_DRUPAL_VERSION . '+.',
                ),
            ];
        }

        $services = $ctx->getDockerComposeServices();
        if (!isset($services[self::EXPECTED_MYSQL_SERVICE])) {
            return [
                RegressionCheckResult::fail(
                    'drupal:compose:missing-mysql-service',
                    sprintf(
                        'docker-compose.yml is missing required Drupal %d+ service "%s".',
                        self::MINIMUM_DRUPAL_VERSION,
                        self::EXPECTED_MYSQL_SERVICE,
                    ),
                    sprintf(
                        'Add a "%s" service to docker-compose.yml backed by image "%s".',
                        self::EXPECTED_MYSQL_SERVICE,
                        self::EXPECTED_MYSQL_IMAGE,
                    ),
                ),
            ];
        }

        $results = [];
        $service = $services[self::EXPECTED_MYSQL_SERVICE];
        $actualImage = is_array($service) && isset($service['image']) && is_string($service['image'])
            ? $service['image']
            : null;
        if ($actualImage !== self::EXPECTED_MYSQL_IMAGE) {
            $results[] = RegressionCheckResult::warn(
                'drupal:compose:mysql-image-mismatch',
                sprintf(
                    'docker-compose.yml service "%s" uses image "%s"; expected "%s".',
                    self::EXPECTED_MYSQL_SERVICE,
                    $actualImage ?? '(none)',
                    self::EXPECTED_MYSQL_IMAGE,
                ),
                sprintf(
                    'Pin the image to "%s" unless this site has an intentional override.',
                    self::EXPECTED_MYSQL_IMAGE,
                ),
            );
        }
        return $results;
    }
}
