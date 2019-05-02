<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;
use Dockworker\TwigValidateTrait;

/**
 * Defines commands to validate Twig.
 */
class DrupalValidateTwigCommands extends DockworkerApplicationCommands {

  use RecursivePathFileOperatorTrait;
  use TwigValidateTrait;

  const TWIG_EXTENSIONS = [
    'twig',
  ];

  /**
   * Validate twig files against Drupal coding standards.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @command validate:twig:drupal
   */
  public function validateDrupalTwigFiles(array $files) {
    return $this->validateTwig(
      $files
    );
  }

  /**
   * Validate all twig inside the Drupal custom path.
   *
   * @command validate:drupal:custom:twig
   * @aliases validate-custom-twig
   */
  public function validateCustom() {
    $this->addRecursivePathFilesFromPath(
      ["{$this->repoRoot}/custom"],
      self::TWIG_EXTENSIONS
    );
    return $this->setRunOtherCommand("validate:twig:drupal {$this->getRecursivePathStringFileList()}");
  }

}
