<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;
use Dockworker\YamlValidateTrait;

/**
 * Defines commands to validate TAML.
 */
class DrupalValidateYamlCommands extends DockworkerLocalCommands {

  use RecursivePathFileOperatorTrait;
  use YamlValidateTrait;

  const YAML_EXTENSIONS = [
    'yaml',
    'yml',
  ];

  /**
   * Validate YAML files against Drupal coding standards.
   *
   * @param string[] $files
   *   The files to validate.
   *
   * @command validate:yaml:drupal
   *
   * @return mixed
   */
  public function validateDrupalYamlFiles(array $files) {
    return $this->validateYaml(
      self::filterArrayFilesByExtension($files, self::YAML_EXTENSIONS)
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
