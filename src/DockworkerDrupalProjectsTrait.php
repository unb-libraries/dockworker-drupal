<?php

namespace Dockworker;

/**
 * Provides methods to determine available Drupal projects in a dockworker repo.
 */
trait DockworkerDrupalProjectsTrait {

  /**
   * The projects defined in the application's composer build file.
   *
   * @var string[]
   */
  protected $buildProjects = [];

  /**
   * The application's composer build file structure.
   *
   * @var object
   */
  protected $drupalComposerBuildFile;

  /**
   * The application's core extension file structure.
   *
   * @var string[]
   */
  protected $drupalCoreExtensionsFile;

  /**
   * The projects enabled in the core extension file.
   *
   * @var string[]
   */
  protected $enabledProjects = [];

  /**
   * Sets the list of projects built via composer.
   *
   * @param string $repo_root
   *   The path to the dockworker repository root.
   * @param bool $strip_prefixes
   *   Should the 'drupal/' prefix be stripped from project name?
   */
  protected function setBuildProjects($repo_root, $strip_prefixes = TRUE) {
    $this->buildProjects = [];
    $this->drupalComposerBuildFile = json_decode(
      file_get_contents(
        $repo_root . '/build/composer.json'
      )
    );
    // Projects should not live in require-dev!
    if (!empty($this->drupalComposerBuildFile->require)) {
      foreach ($this->drupalComposerBuildFile->require as $project_name => $project_version) {
        if (substr( $project_name, 0, 7 ) === "drupal/" && $project_name != 'drupal/core') {
          if ($strip_prefixes) {
            $project_name = str_replace('drupal/', '', $project_name);
          }
          $this->buildProjects[] = $project_name;
        }
      }
    }
  }

  /**
   * Sets the list of enabled projects from core.extension.
   *
   * @param string $repo_root
   *   The path to the dockworker repository root.
   */
  protected function setEnabledProjects($repo_root) {
    $this->drupalCoreExtensionsFile = yaml_parse(
      file_get_contents(
        $repo_root . '/config-yml/core.extension.yml'
      )
    );
    if (!empty($this->drupalCoreExtensionsFile['module'])) {
      $this->enabledProjects = array_keys($this->drupalCoreExtensionsFile['module']);
    }
  }

}
