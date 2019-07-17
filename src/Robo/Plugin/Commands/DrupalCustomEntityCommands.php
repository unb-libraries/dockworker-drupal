<?php
namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DrupalCodeCommands;
use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands in the DrupalCustomEntityCommand namespace.
 */
class DrupalCustomEntityCommands extends DrupalCodeCommands {

  /**
   * The custom entities to operate on.
   *
   * @var array
   */
  protected $drupalCustomEntities = [];
  protected $drupalChosenEntityClass = NULL;
  protected $drupalChosenModule = NULL;

  /**
   * Set the custom entities defined within the the current repository.
   *
   * @hook post-init
   */
  public function setCustomEntities() {
    $entities = [];
    foreach ($this->drupalModules as $drupal_module) {
      $custom_entities = new Finder();
      $module_src_path = $drupal_module->getPath() . '/src';
      $module_entity_path = $drupal_module->getPath() . '/src/Entity';
      if (file_exists($module_src_path) && file_exists($module_entity_path)) {
        $custom_entities->files()
          ->in($drupal_module->getPath() . '/src/Entity')
          ->files()
          ->name('*.php')
          ->contains('public static function baseFieldDefinitions');
        foreach ($custom_entities as $file) {
          $entities[] = $file;
        }
      }
    }

    $this->setSelectedCustomEntities($entities);
  }

  /**
   * Set the selected custom entity.
   */
  private function setSelectedCustomEntities($custom_entities) {
    $choices = [];

    foreach ($custom_entities as $custom_entity) {
      $choices[$custom_entity->getBasename()] = $custom_entity;
    }
    if (!empty($choices)) {
      $entity_key = $this->io()->choice("Which entity to modify?", array_keys($choices));
      $entity = $choices[$entity_key];
      preg_match('|.*/(.*)/src/Entity|', $entity->getPath(), $matches);
      $this->drupalChosenModule = $matches[1];
      $this->drupalChosenEntityClass = str_replace('.php', '', $entity_key);
      $real_path = $custom_entity->getRealPath();
      $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
      try {
        $ast = $parser->parse(
          file_get_contents($real_path)
        );
      } catch (Error $error) {
        echo "Parse error: {$error->getMessage()}\n";
        return;
      }
      $this->drupalCustomEntities[$real_path] = $parser;
    }
  }

}
