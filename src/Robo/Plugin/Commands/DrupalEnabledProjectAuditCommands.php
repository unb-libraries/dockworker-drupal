<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\IO\DockworkerIOTrait;

/**
 * Defines commands to validate Drupal projects in the repository.
 *
 * @TODO Migrated from Dockworker 5.x. Needs review.
 */
class DrupalEnabledProjectAuditCommands extends DockworkerDrupalCommands
{
    use DockworkerIOTrait;

    /**
     * The projects defined in the build/composer.json file.
     *
     * @var string[]
     */
    protected array $buildProjects = [];

    /**
     * The projects enabled in core.extension.yml.
     *
     * @var string[]
     */
    protected array $enabledProjects = [];

    /**
     * The projects enabled in core.extension.yml bu not defined in build/composer.json.
     *
     * @var string[]
     */
    protected array $extraneousProjects = [];

    /**
     * The build/composer.json file object.
     *
     * @var mixed
     */
    protected mixed $buildFile;

    /**
     * The path to the build/composer.json file.
     *
     * @var string
     */
    protected string $buildFilePath;

    /**
     * The core.extension.yml file object.
     *
     * @var mixed
     */
    protected mixed $coreExtensionsFile;

    /**
     * The path to the core.extension.yml file.
     *
     * @var string
     */
    protected string $coreExtensionsFilePath;

    /**
     * Validate projects listed in './build/composer.json' against those enabled in core.extension.
     *
     * @command validate:drupal:composer-orphans
     */
    public function validateEnabledProjectFiles(): void
    {
        $this->buildFilePath = $this->applicationRoot . '/build/composer.json';
        $this->coreExtensionsFilePath = $this->applicationRoot . '/configuration/core.extension.yml';
        $this->setBuildProjects();
        $this->setEnabledProjects();
        $this->setExtraneousProjects();
        $this->reportExtraneousProjects($this->dockworkerIO);
    }

    /**
     * Retrieves a list of projects and versions defined in a composer file.
     *
     * @param object $file_obj
     *   The composer file object.
     *
     * @return string[]
     *   All projects and versions defined in the file.
     */
    protected function getProjectsFromFile($file_obj): array
    {
        $projects = [];
        $file_array = (array) $file_obj;
        foreach (['require', 'require-dev'] as $require_type) {
            if (isset($file_array[$require_type])) {
                $projects = array_merge($projects, (array) $file_array[$require_type]);
            }
        }
        return $projects;
    }

    /**
     * Sets the enabled projects.
     */
    protected function setEnabledProjects(): void
    {
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
    protected function setBuildProjects(bool $strip_prefixes = true): void
    {
        $this->buildFile = json_decode(
            file_get_contents(
                $this->buildFilePath
            )
        );
        if (!empty($this->buildFile->require)) {
            foreach ($this->buildFile->require as $project_name => $project_version) {
                if (
                    str_starts_with($project_name, "drupal/") &&
                    !str_starts_with($project_name, 'drupal/core')
                ) {
                    if ($strip_prefixes) {
                        $project_name = str_replace(
                            'drupal/',
                            '',
                            $project_name
                        );
                    }
                    $this->buildProjects[] = $project_name;
                }
            }
        }
    }

    /**
     * Sets the extraneous projects list.
     */
    protected function setExtraneousProjects(): void
    {
        foreach ($this->buildProjects as $build_project) {
            if (!in_array($build_project, $this->enabledProjects)) {
                $this->extraneousProjects[] = $build_project;
            }
        }
    }

    /**
     * Reports mismatches or problems to the user.
     */
    protected function reportExtraneousProjects($io): void
    {
        if (!empty($this->extraneousProjects)) {
            $io->title('Potentially Extraneous Projects:');
            $io->block(implode("\n", $this->extraneousProjects));
            $io
                ->block('The above Drupal projects are built into the container per build/composer.json, but are not detected as enabled in core.extension.yml. This does not necessarily mean they are extraneous! Some examples:');
            $io->listing([
                'Themes (ex: bootstrap) may serve the base theme for a custom theme (subthemes do not need the parent theme enabled).',
                'Projects (ex: ldap) may contain submodules that are different than their project name, and those modules are enabled.',
                'Projects may be non-modules or have codebase portions required by other projects, but the modules are not enabled in Drupal.',
            ]);
        }
        else {
            $io->say('Hooray! No projects detected as built, but not enabled.');
        }
    }
}
