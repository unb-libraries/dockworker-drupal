<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Robo\Plugin\Commands\DrupalCustomEntityCommands;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines the commands used to generate entity field boilerplate.
 */
class DrupalGenerateContentEntityFieldCommands extends DrupalCustomEntityCommands {

  /**
   * The path to the entity template data directory.
   */
  const ENTITY_TEMPLATE_PATH = '/vendor/unb-libraries/dockworker-drupal/data/entity_fields';

  /**
   * The entity templates available.
   *
   * @var string[]
   */
  protected $drupalEntityTemplates = [];

  /**
   * The chosen entity widget.
   *
   * @var string[]
   */
  protected $drupalEntityChosenWidget = [];

  /**
   * The tokens to replace from the templates.
   *
   * @var string[]
   */
  protected $drupalEntityTemplateTokens = [];

  /**
   * Generates the boilerplate necessary to add a field to an entity.
   *
   * @command drupal:generate:entity-field
   * @aliases gef
   */
  public function generateContentEntityField() {
    if (!empty($this->drupalCustomEntities)) {
      $this->setTemplates();
      $this->setChosenTemplate();
      $this->getTokenizedTemplateOutputs();
    }
    else {
      $this->say('No modules containing custom entities found.');
    }
  }

  /**
   * Sets the templates that are available.
   *
   * @hook post-init
   */
  private function setTemplates() {
    try {
      $field_definitions = $this->repoRoot . self::ENTITY_TEMPLATE_PATH . '/entity_fields.yml';
      $field_definitions = Yaml::parse(
        file_get_contents($field_definitions)
      );
      $this->drupalEntityTemplates = $field_definitions['entity_fields'];
    } catch (ParseException $exception) {
      printf('Unable to parse the YAML string: %s', $exception->getMessage());
    }
  }

  /**
   * Sets the chosen entity template.
   */
  private function setChosenTemplate() {
    $this->listTemplates();
    $value_chosen = FALSE;
    while ($value_chosen == FALSE) {
      $widget_chosen = $this->ask('Enter the template ID to use');
      if (!empty($widget_chosen)) {
        foreach ($this->drupalEntityTemplates as $type) {
          foreach ($type['widgets'] as $widget) {
            if ($widget['id'] == $widget_chosen) {
              $this->drupalEntityChosenWidget = $widget;
              break 3;
            }
          }
        }
        $this->say('Error: Invalid template ID.');
      }
      else {
        $this->say('Error: No template ID entered.');
      }
    }
  }

  /**
   * Outputs a formatted list of templates available.
   */
  protected function listTemplates() {
    $wrapped_rows = [];
    foreach($this->drupalEntityTemplates as $type_label => $type) {
      foreach ($type['widgets'] as $widget) {
        $wrapped_rows[] = [
          $widget['id'],
          $type['name'],
          $widget['name'],
        ];
      }
    }
    $table = new Table($this->output());
    $table->setHeaders(['ID', 'Field Type', 'Widget'])
      ->setRows($wrapped_rows);
    $table->setStyle('borderless');
    $table->render();
  }

  /**
   * Generates the output from all files in this template.
   */
  private function getTokenizedTemplateOutputs() {
    $this->setEntityTemplateTokens();
    foreach ($this->drupalEntityChosenWidget['templates'] as $template) {
      $this->getOutputTemplateFile($template);
    }
  }

  /**
   * Sets the tokens necessary for generating the templates.
   */
  private function setEntityTemplateTokens() {
    $this->setStandardEntityTemplateTokens();
    switch ($this->drupalEntityChosenWidget['id']) {
      case 'string':
      case 'text':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setTranslatableFieldTokens();
        $this->setTextTypeFieldTemplateTokens();
        $this->setShortTextTypeFieldTemplateTokens();
        break;
      case 'string_long':
      case 'text_long':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setTranslatableFieldTokens();
        $this->setTextTypeFieldTemplateTokens();
        $this->setLongFieldTemplateTokens();
        break;
      case 'taxonomy_reference_select':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setTaxonomyTermTemplateTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'taxonomy_reference_autocomplete':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setTaxonomyTermTemplateTokens();
        $this->setEntityRefAutocompleteTemplateTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'custom_entity_reference_select':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setEntityReferenceTemplateTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'custom_entity_reference_autocomplete':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setEntityReferenceTemplateTokens();
        $this->setEntityRefAutocompleteTemplateTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'file_upload':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setFileTemplateTokens('pdf doc docx');
        $this->setFileUploadTemplateTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'image_upload':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setFileTemplateTokens('jpg gif png');
        $this->setImageTemplateTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'boolean_checkbox':
        $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CARDINALITY'] = 1;
        $this->setBooleanTemplateTokens();
        break;
      case 'paragraph':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setParagraphsFieldTokens();
        $this->setLeadinglessNamespaceToken();
        break;
      case 'date_picker':
        $this->setCardinalityTokens();
        $this->setRequiredFieldTokens();
        $this->setDateFieldTokens();
        break;
    }
  }

  /**
   * Sets the tokens necessary for all templates.
   */
  private function setStandardEntityTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_MACHINE_NAME'] =
      $this->askDefault('Enter the *new field* key for the $fields array: ', 'user_name');
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_REVISIONABLE'] =
      $this->confirm('Is this *new field* revisionable?') ? 'TRUE' : 'FALSE';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_LABEL'] =
      $this->askDefault('Enter the *new field* label for forms:', 'User Name');
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_WEIGHT'] =
      $this->askDefault('Enter the *new field* weight for forms:', '0');
    $field_class_guess = preg_replace(
      "/[^A-Za-z0-9]/",
      '',
      ucwords(
        strtolower($this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_LABEL'])
      )
    );
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CAPSCASE'] =
      $this->askDefault('Enter the *new field* pseudo-ClassName:', $field_class_guess);
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_DESCRIPTION'] =
      $this->askDefault('Enter the *new field* description (form call to action):', 'Enter the user name');
    $parent_entity_type_guess = preg_replace('/(?<! )(?<!^)[A-Z]/', ' $0', $this->drupalChosenEntityClass);
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_ENTITY_TYPE'] =
      $this->askDefault('Enter a label for the *parent entity* this field is being added to:', $parent_entity_type_guess);
    $full_parent_entity_namespace_guess = "\Drupal\\$this->drupalChosenModule\Entity\\$this->drupalChosenEntityClass";
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_NAMEDSPACED_CLASS'] =
      $this->askDefault('Enter the fully namespaced class for the *parent entity* this field is being added to:', $full_parent_entity_namespace_guess);
  }

  /**
   * Sets the tokens necessary for cardinality configurable field templates.
   */
  private function setCardinalityTokens() {
    $cardinality = $this->askDefault('Enter the *new field* cardinality (0 for unlimited):', '1');
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CARDINALITY'] = $cardinality == 0 ? 'BaseFieldDefinition::CARDINALITY_UNLIMITED' : $cardinality;
  }

  /**
   * Sets the tokens necessary for required field templates.
   */
  private function setRequiredFieldTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_REQUIRED'] =
      $this->confirm('Is this *new field* required?') ? 'TRUE' : 'FALSE';
  }

  /**
   * Sets the tokens necessary for translatable field templates.
   */
  private function setTranslatableFieldTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_TRANSLATABLE'] =
      $this->confirm('Is this *new field* translatable?') ? 'TRUE' : 'FALSE';
  }

  /**
   * Sets the tokens necessary for the string_long and text_long templates.
   */
  private function setTextTypeFieldTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_DEFAULT_VALUE'] =
      $this->askDefault('Enter the *new field* default value (empty for no default):', '');
  }

  /**
   * Sets the tokens necessary for the string_long and text_long templates.
   */
  private function setShortTextTypeFieldTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_MAX_LENGTH'] =
      $this->askDefault('Enter the *new field* maximum length for storage:', 512);
  }

  /**
   * Sets the tokens necessary for the string_long and text_long templates.
   */
  private function setLongFieldTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_LONG_TEXT_FIELD_ROWS'] =
      $this->askDefault('Enter the *new field* number of input rows to display on forms:', 4);
  }

  /**
   * Sets the tokens necessary for taxonomy term reference templates.
   */
  private function setTaxonomyTermTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_CLASS'] = 'Term';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'] = '\Drupal\taxonomy\TermInterface';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE'] = 'TermInterface';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_TAXONOMY_VID'] =
      $this->askDefault('Enter the *new field* target taxonomy VID:', '');
  }

  /**
   * Transforms the interface namespace for an entity into a leadingless one.
   */
  private function setLeadinglessNamespaceToken() {
    if (!empty($this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'])) {
      $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_LEADINGLESS_INTERFACE_NAMESPACE'] = ltrim(
        $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'],
        '\\'
      );
    }
  }

  /**
   * Sets the tokens necessary for taxonomy term reference templates.
   */
  private function setEntityRefAutocompleteTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_TAXONOMY_AUTO_CREATE'] =
      $this->confirm('Should new entities entered in this field be auto-created?') ? 'TRUE' : 'FALSE';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_TAXONOMY_AUTOCOMPLETE_SIZE'] =
      $this->askDefault('Enter the *new field* autocomplete widget width for forms:', 60);
  }

  /**
   * Sets the tokens necessary for taxonomy term reference templates.
   */
  private function setEntityReferenceTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_NAME'] =
      $this->askDefault('Enter the *target entity* machine name:', 'reference_contributor');
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'] =
      $this->askDefault('Enter the *target entity* full interface namespace:', '\Drupal\yabrm\Entity\JournalArticleReferenceInterface');
    $interface_namespace = explode('\\', $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE']);
    $interface_short_guess = array_pop($interface_namespace);
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE'] =
      $this->askDefault('Enter the *target entity* unnamespaced interface name:', $interface_short_guess);
    $entity_short_guess = str_replace('Interface', '', $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE']);
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_CLASS'] =
      $this->askDefault('Enter the *target entity* unnamespaced class name:', $entity_short_guess);
  }

  /**
   * Sets the tokens necessary for file reference templates.
   *
   * @param string $permitted_extensions
   *   The permitted extensions.
   */
  private function setFileTemplateTokens($permitted_extensions) {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FILE_FIELD_EXTENSIONS'] =
      $this->askDefault('Enter the *new field* file extensions permitted (space separated):', $permitted_extensions);
    $storage_path_guess = $this->drupalChosenModule . '/' . $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_MACHINE_NAME'];
    $this->drupalEntityTemplateTokens['DOCKWORKER_FILE_FIELD_DIRECTORY'] =
      $this->askDefault('Enter the *new field* storage directory name (Leave empty for default):', $storage_path_guess);
  }

  /**
   * Sets the tokens necessary for taxonomy term reference templates.
   */
  private function setFileUploadTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_CLASS'] = 'File';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'] = '\Drupal\file\FileInterface';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE'] = 'FileInterface';
  }

  /**
   * Sets the tokens necessary for image reference templates.
   */
  private function setImageTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_CLASS'] = 'Image';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'] = '\Drupal\Core\Image\ImageInterface';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE'] = 'ImageInterface';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FILE_FIELD_ALT_REQUIRED'] =
      $this->confirm('Should the ALT field for the image(s) be required?') ? 'TRUE' : 'FALSE';
  }

  /**
   * Sets the tokens necessary for boolean type field templates.
   */
  private function setBooleanTemplateTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_BOOLEAN_FIELD_DEFAULT_VALUE'] =
      $this->confirm('Should the checkbox be checked by default?') ? 1 : 0;
  }

  /**
   * Sets the tokens necessary for taxonomy term reference templates.
   */
  private function setParagraphsFieldTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_TARGET_PARAGRAPH_NAME'] =
      $this->askDefault('Enter the *target paragraph* machine name:', 'reference_contributor');
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_CLASS'] = 'Paragraph';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE_NAMESPACE'] = '\Drupal\paragraphs\Entity\ParagraphInterface';
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CUSTOM_ENTITY_INTERFACE'] = 'ParagraphInterface';
  }

  /**
   * Sets the tokens necessary for date field templates.
   */
  private function setDateFieldTokens() {
    $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_DATETIME_TYPE'] =
      $this->confirm('Should the *new field* also store a time (Date is default)?') ? 'DateTimeItem::DATETIME_TYPE_DATETIME' : 'DateTimeItem::DATETIME_TYPE_DATE';
  }

  /**
   * Outputs the tokenized version of templates.
   *
   * @param string[] $template
   *   The template file paths to output.
   */
  private function getOutputTemplateFile(array $template) {
    $contents = file_get_contents(
      $this->getAbsoluteTemplateFile($template)
    );
    $this->io->newLine();
    $this->say($template['name']);
    foreach ($this->drupalEntityTemplateTokens as $token => $output_value) {
      $contents = str_replace($token, $output_value, $contents);
    }
    $this->io->text($contents);
  }

  /**
   * Determines the absolute filepath to a template.
   *
   * @param string[] $template
   *   The template to build the filepath for.
   *
   * @return string
   *   The absolute filepath to the template.
   */
  private function getAbsoluteTemplateFile(array $template) {
    $file_index = $this->drupalEntityTemplateTokens['DOCKWORKER_FIELD_CARDINALITY'] == 'BaseFieldDefinition::CARDINALITY_UNLIMITED'
      ? 'multiple'
      : 'single';
    return $this->repoRoot . self::ENTITY_TEMPLATE_PATH . '/' . $template['template_files'][$file_index];
  }

}
