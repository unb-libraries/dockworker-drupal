<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\PhpValidateTrait;
use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalDaemonCommands;

/**
 * Defines commands to validate PHP code for the local Drupal application.
 */
class DrupalValidatePhpCommands extends DockworkerLocalDaemonCommands {

  use PhpValidateTrait;
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
    'DrupalPractice',
  ];

  /**
   * Sets the Drupal coding symlink for phpcs.
   *
   * @hook pre-command validate:drupal:php
   */
  public function setPhpCsCoderSymlink() {
    foreach (self::PHPCS_STANDARDS as $standard) {
      $target = $this->repoRoot . "/vendor/drupal/coder/coder_sniffer/$standard";
      $link = $this->repoRoot . "/vendor/squizlabs/php_codesniffer/src/Standards/$standard";
      if (!file_exists($link)) {
        symlink(
          $target,
          $link
        );
        $this->logger->info(self::INFO_CREATE_PHPCS_SYMLINK);
      };
    }
  }

  /**
   * Validates one or more PHP files within this repository against Drupal coding standards.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @option $no-warnings
   *   Do not output warnings.
   *
   * @command validate:drupal:php
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  public function validateDrupalPhpFiles(array $files, array $options = ['no-warnings' => FALSE]) {
    return $this->validatePhp(
      self::filterArrayFilesByExtension($files, self::PHPCS_EXTENSIONS),
      self::PHPCS_STANDARDS,
      $options['no-warnings']
    );
  }

  /**
   * Validates all PHP files within this repository's 'custom' path against Drupal coding standards.
   *
   * @option $no-warnings
   *   Do not output warnings.
   *
   * @command validate:drupal:php:custom
   * @aliases validate-custom-php
   * @throws \Dockworker\DockworkerException
   *
   * @return int
   *   The return code from the validation command.
   */
  public function validateCustom(array $options = ['no-warnings' => FALSE]) {
    $this->addRecursivePathFilesFromPath(
      ["{$this->repoRoot}/custom"],
      self::PHPCS_EXTENSIONS
    );
    $cmd = "validate:drupal:php {$this->getRecursivePathStringFileList()}";
    if ($options['no-warnings']) {
      $cmd = "$cmd --no-warnings";
    }
    return $this->setRunOtherCommand($cmd);
  }

}
