<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;

/**
 * Defines commands to validate Drupal projects in the repository.
 */
class DrupalValidateProjectsCommands extends DockworkerLocalCommands {

  protected $buildFile;
  protected $buildFilePath;
  protected $buildProjects = [];
  protected $coreExtensionsFile;
  protected $coreExtensionsFilePath;
  protected $enabledProjects = [];
  protected $extraneousProjects = [];

  /**
   * Validate the drupal projects in ./build/composer.json for extraneous.
   *
   * @command validate:projects:enabled
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  public function validateEnabledProjectFiles() {
    $this->buildFilePath = $this->repoRoot . '/build/composer.json';
    $this->coreExtensionsFilePath = $this->repoRoot . '/config-yml/core.extension.yml';
    $this->setBuildProjects();
    $this->setEnabledProjects();
    $this->setExtraneousProjects();
    $this->reportExtraneousProjects();
  }

  /**
   * Sets the enabled projects.
   */
  protected function setEnabledProjects() {
    $this->coreExtensionsFile = yaml_parse(
      file_get_contents(
        $this->coreExtensionsFilePath
      )
    );
    if (!empty($this->coreExtensionsFile['module'])) {
      $this->enabledProjects = array_keys($this->coreExtensionsFile['module']);
    }
  }

  /**
   * Sets the projects built via composer.
   */
  protected function setBuildProjects() {
    $this->buildFile = json_decode(
      file_get_contents(
        $this->buildFilePath
      )
    );
    if (!empty($this->buildFile->require)) {
      foreach ($this->buildFile->require as $project_name => $project_version) {
        if (substr( $project_name, 0, 7 ) === "drupal/" && $project_name != 'drupal/core') {
          $this->setProjectAsBuilt(str_replace('drupal/', '', $project_name));
        }
      }
    }
  }

  /**
   * Determines if the project should be added to the build list, then adds it.
   *
   * @param $project_name
   *   The name of the project.
   */
  protected function setProjectAsBuilt($project_name) {
    $this->buildProjects[] = $project_name;
  }

  /**
   * Sets the extraneous projects list.
   */
  protected function setExtraneousProjects() {
    foreach($this->buildProjects as $build_project) {
      if (!in_array($build_project, $this->enabledProjects)) {
        $this->extraneousProjects[] = $build_project;
      }
    }
  }

  /**
   * Reports mismatches or problems.
   */
  protected function reportExtraneousProjects() {
    if (!empty($this->extraneousProjects)) {
      $this->io()->title('Potentially Extraneous Projects:');
      $this->io()->block(implode("\n", $this->extraneousProjects));
      $this->io()
        ->block('The above Drupal projects are built into the container per build/composer.json, but are not detected as enabled in core.extension.yml. This does not necessarily mean they are extraneous! Some examples:');
      $this->io()->listing([
        'Themes (ex: bootstrap) may serve the base theme for a custom theme (subthemes do not need the parent theme enabled).',
        'Projects (ex: ldap) may contain submodules that are different than their project name, and those modules are enabled.',
        'Projects may be non-modules or have codebase portions required by other projects, but the modules are not enabled in Drupal.',
      ]);
    }
    else {
      $this->say('Hooray! No projects detected as built, but not enabled.');
    }
  }

}
