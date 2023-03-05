<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DockworkerShellCommands;

/**
 * Provides commands for generating an admin ULI link within a Drupal application.
 */
class DockworkerDrupalUliCommands extends DockworkerShellCommands
{
    /**
     * Generates a ULI link for the Drupal application.
     *
     * @option string $env
     *   The environment to generate the ULI link in.
     * @option string $name
     *   The user name of the account to generate the ULI for. Defaults to uid0
     *
     * @command drupal:uli
     * @aliases uli
     * @usage --env=prod
     */
    public function generateDrupalUli(
      array $options = [
        'env' => 'local',
        'name' => '',
      ]
    ): void
    {
        $this->initShellCommand($options['env']);
        $container = $this->getDeployedContainer($options['env']);
        $this->dockworkerIO->title('Generating ULI');
        $this->dockworkerIO->info(
          sprintf(
            'Generating ULI in %s/%s',
            $options['env'],
            $container->getContainerName()
          )
        );
        $cmd = ['/scripts/drupalUli.sh'];
        if (!empty($options['name'])) {
          $cmd[] = "--name={$options['name']}";
        }
        $container->run(
          $cmd,
          $this->dockworkerIO
        );
    }
}
