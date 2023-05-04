<?php

namespace Dockworker;

use Consolidation\AnnotatedCommand\AnnotationData;
use Dockworker\DockworkerDaemonCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Defines a base abstract class for all dockworker-drupal commands.
 *
 * This is not a command class. It should not contain any hooks or commands.
 */
class DockworkerDrupalCommands extends DockworkerDaemonCommands
{
    /**
     * @hook pre-init
     */
    public function initOptions(InputInterface $input, AnnotationData $annotationData)
    {
        parent::initOptions($input, $annotationData);
    }
}
