<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;
use Dockworker\TwigValidateTrait;

/**
 * Defines commands to validate twig files for the local Drupal application.
 */
class DrupalValidateTwigCommands extends DockworkerLocalCommands {

  use RecursivePathFileOperatorTrait;
  use TwigValidateTrait;

  const TWIG_EXTENSIONS = [
    'twig',
  ];

  /**
   * Validates one or more Twig files within this repository against Drupal coding standards.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @command validate:drupal:twig
   * @throws \Dockworker\DockworkerException
   */
  public function validateDrupalTwigFiles(array $files) {
    $this->validateTwig(
      $files
    );
  }

  /**
   * Validates all twig files within this repository's 'custom' path against Drupal coding standards.
   *
   * @command validate:drupal:twig:custom
   * @aliases validate-custom-twig
   *
   * @throws \Dockworker\DockworkerException
   *
   * @return int
   *   The result of the validation command.
   */
  public function validateCustom() {
    $this->addRecursivePathFilesFromPath(
      ["{$this->repoRoot}/custom"],
      self::TWIG_EXTENSIONS
    );
    return $this->setRunOtherCommand("validate:drupal:twig {$this->getRecursivePathStringFileList()}");
  }

}
