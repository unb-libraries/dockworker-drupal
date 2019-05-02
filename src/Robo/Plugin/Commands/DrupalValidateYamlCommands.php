<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;
use Dockworker\YamlValidateTrait;

/**
 * Defines commands to validate PHP .
 */
class DrupalValidateYamlCommands extends DockworkerApplicationCommands {

  use RecursivePathFileOperatorTrait;
  use YamlValidateTrait;

  const YAML_EXTENSIONS = [
    'yaml',
    'yml',
  ];

  /**
   * Validate YAML intended for Drupal.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @command validate:yaml:drupal
   */
  public function validateDrupalYamlFiles(array $files) {
    return $this->validateYaml(
      $files
    );
  }

  /**
   * Validate all YAML inside the Drupal custom path.
   *
   * @command validate:drupal:custom:yaml
   * @aliases validate-custom-yaml
   */
  public function validateCustom() {
    $this->addRecursivePathFilesFromPath(
      ["{$this->repoRoot}/custom"],
      self::YAML_EXTENSIONS
    );
    return $this->setRunOtherCommand("validate:yaml:drupal {$this->getRecursivePathStringFileList()}");
  }

}
