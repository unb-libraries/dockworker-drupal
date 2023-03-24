<?php

namespace Dockworker;

use Dockworker\DockworkerDaemonCommands;

/**
 * Defines a base abstract class for all dockworker-drupal commands.
 *
 * This is not a command class. It should not contain any hooks or commands.
 */
class DockworkerDrupalCommands extends DockworkerDaemonCommands
{
    /**
     * DockworkerCommands constructor.
     *
     * @throws \Dockworker\DockworkerException
     */
    public function __construct()
    {
        parent::__construct();
    }
}
