<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerDrupalCommands;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Twig\TwigTrait;

/**
 * Provides commands for generating GitHub actions workflow files.
 */
class DrupalGitHubWorkflowFilesCommands extends DockworkerDrupalCommands
{
    use DockworkerIOTrait;
    use TwigTrait;

    /**
     * Write the default GitHub Actions workflow files for Drupal Applications.
     *
     * @hook replace-command github:workflows:write-default
     */
    public function writeBaseImageWorkflowFiles(): void
    {
        $this->initOptions();
        $this->initDockworkerIO();
        $this->dockworkerIO->title('Writing Workflow Files');

        $workflow_dir = $this->initGetPathFromPathElements(
            [
                $this->applicationRoot,
                '.github/workflows',
            ]
        );

        $deploy_branches = $this->getRequiredConfigurationItem('dockworker.endpoints.env');
        $this->writeTwig(
            $workflow_dir . '/deployment-workflow.yaml',
            'deployment-workflow.yaml.twig',
            [
                "$this->applicationRoot/vendor/unb-libraries/dockworker-drupal/data/workflows/"
            ],
            [
                'project_name' => $this->applicationName,
                'project_slug' => $this->applicationSlug,
                'image_name' => $this->getRequiredConfigurationItem('dockworker.workflows.image.name'),
                'push_branches' => json_encode($this->getRequiredConfigurationItem('dockworker.workflows.image.push-branches')),
                'deploy_branches' => json_encode($deploy_branches),
                'deploy_branch_map' => json_encode(array_combine($deploy_branches, $deploy_branches)),
            ]
        );
        $this->dockworkerIO->say("Wrote $workflow_dir/deployment-workflow.yaml");

        // This ensures that applications have the same 'random' times.
        $seed = crc32($this->applicationName);
        srand($seed);

        $this->writeTwig(
            $workflow_dir . '/update.yaml',
            'update.yaml.twig',
            [
                "$this->applicationRoot/vendor/unb-libraries/dockworker-drupal/data/workflows/"
            ],
            [
                'project_name' => $this->applicationName,
                'update_hour' => rand(5, 6),
                'update_minute' => rand(0, 59),
            ]
        );
        $this->dockworkerIO->say("Wrote $workflow_dir/update.yaml");
    }
}
