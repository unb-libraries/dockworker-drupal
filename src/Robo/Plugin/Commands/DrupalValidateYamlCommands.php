<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;
use Dockworker\YamlValidateTrait;

/**
 * Defines commands to validate YAML files for the local Drupal application.
 */
class DrupalValidateYamlCommands extends DockworkerLocalCommands {

  use RecursivePathFileOperatorTrait;
  use YamlValidateTrait;

  const YAML_EXTENSIONS = [
    'yaml',
    'yml',
  ];

  /**
   * Validates one or more YAML files within this repository against Drupal coding standards.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @command validate:drupal:yaml
   *
   * @return int
   *   The return value of the command.
   */
  public function validateDrupalYamlFiles(array $files) {
    return $this->validateYaml(
      self::filterArrayFilesByExtension($files, self::YAML_EXTENSIONS)
    );
  }

  /**
   * Validates all YAML files within this repository's 'custom' path against Drupal coding standards.
   *
   * @command validate:drupal:yaml:custom
   * @aliases validate-custom-yaml
   * @throws \Dockworker\DockworkerException
   *
   * @return int
   *   The return value of the command.
   */
  public function validateCustom() {
    $this->addRecursivePathFilesFromPath(
      ["{$this->repoRoot}/custom"],
      self::YAML_EXTENSIONS
    );
    return $this->setRunOtherCommand("validate:drupal:yaml {$this->getRecursivePathStringFileList()}");
  }

}
