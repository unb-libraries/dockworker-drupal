<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalCodeTrait;
use Dockworker\PhpCsValidateTrait;
use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;

/**
 * Defines commands to validate PHP .
 */
class DrupalValidatePhpCommands extends DockworkerApplicationCommands {

  use DrupalCodeTrait;
  use PhpCsValidateTrait;
  use RecursivePathFileOperatorTrait;

  const INFO_CREATE_PHPCS_SYMLINK = 'Created symlink for Drupal coding standard to phpcs directory';
  const PHPCS_EXTENSIONS = [
    'inc',
    'install',
    'lib',
    'module',
    'php',
    'theme',
  ];
  const PHPCS_STANDARDS = [
    'Drupal',
  ];

  /**
   * Set the Drupal coding symlink for phpcs.
   *
   * @hook pre-command validate:php
   */
  public function setPhpCsCoderSymlink() {
    $target = $this->repoRoot . '/vendor/drupal/coder/coder_sniffer/Drupal';
    $link = $this->repoRoot . '/vendor/squizlabs/php_codesniffer/CodeSniffer/Standards/Drupal';
    if (!file_exists($link)) {
      symlink(
        $target,
        $link
      );
      $this->logger->info(self::INFO_CREATE_PHPCS_SYMLINK);
    };
  }

  /**
   * Validate PHP written for Drupal.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @command validate:php
   */
  public function validateDrupalFiles(array $files) {
    return $this->validate(
      $files,
      self::PHPCS_STANDARDS
    );
  }

  /**
   * Validate all PHP inside the Drupal custom path.
   *
   * @command validate:php:custom
   * @aliases validate-custom-php
   */
  public function validateCustom() {
    $this->addRecursivePathFilesFromPath(
      ["{$this->repoRoot}/custom"],
      self::PHPCS_EXTENSIONS
    );
    return $this->setRunOtherCommand("drupal:validate:php {$this->getRecursivePathStringFileList()}");
  }

}
