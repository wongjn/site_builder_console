<?php

namespace Drupal\site_builder_console\Command\Field;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\site_builder_console\Command\SettingsTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait for dealing with fields.
 */
trait FieldTrait {

  use SettingsTrait;

  /**
   * Adds the field name option to the command.
   *
   * @return $this
   *   The command object for chaining.
   */
  protected function addFieldNameOption() {
    return $this->addOption(
      'field-name',
      NULL,
      InputOption::VALUE_REQUIRED,
      $this->trans('commands.site_builder_console.field.options.name')
    );
  }

  /**
   * Asks IO questions to create a field for a bundle.
   *
   * @param string $entity_type
   *   The entity type the field will be attached to.
   * @param string $bundle
   *   The bundle the field will be attached to.
   * @param string $field_type
   *   (optional) The field type.
   * @param string $field_name
   *   (optional) The name of the field.
   *
   * @return array
   *   A list of field parameters containing:
   *   - type: The field type.
   *   - name: The field machine name.
   *   - storage: Unsaved \Drupal\field\FieldStorageConfigInterface if it will
   *     be created from this operation, NULL otherwise.
   *   - instance: Unsaved \Drupal\Core\Field\FieldConfigBase for the field.
   */
  protected function fieldCreateQuestion($entity_type, $bundle, $field_type = '', $field_name = '') {
    $field = [];

    $field['type'] = $field_type ?: $this->getIo()->choiceNoList(
      $this->trans('commands.site_builder_console.field.questions.type'),
      array_keys($this->get('plugin.manager.field.field_type')->getUiDefinitions())
    );

    $field['name'] = $field_name ?: $this->getIo()->ask(
      $this->trans('commands.site_builder_console.field.questions.name'),
      "field_$field[type]",
      function ($name) use ($entity_type, $bundle) {
        return $this->validateFieldInstanceNotExists(
          $entity_type,
          $bundle,
          $this->get('console.validator')->validateMachineName($name)
        );
      }
    );

    $field['storage'] = $this->fieldStorageQuestion($entity_type, $field);
    $field['instance'] = $this->fieldInstanceQuestion($entity_type, $bundle, $field);

    return $field;
  }

  /**
   * Asks IO questions to create a storage definition for a field.
   *
   * @param string $entity_type
   *   The entity type the field will be attached to.
   * @param array $field
   *   A list of field parameters, containing:
   *   - type: The field type.
   *   - name: The field machine name.
   *
   * @return \Drupal\field\FieldStorageConfigInterface|null
   *   The new, unsaved field storage definition or NULL if it already exists.
   */
  protected function fieldStorageQuestion($entity_type, array $field) {
    // Find matching definition first.
    $matched_definitions = $this->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->loadByProperties(['id' => "$entity_type.$field[name]"]);
    if (!empty($matched_definitions)) {
      return NULL;
    }

    /** @var \Drupal\field\FieldStorageConfigInterface $storage */
    $storage = $this->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->create([
        'type' => $field['type'],
        'field_name' => $field['name'],
        'entity_type' => $entity_type,
      ]);

    $type_definition = $this->get('plugin.manager.field.field_type')->getDefinition($field['type']);
    if (!isset($type_definition['cardinality'])) {
      $storage->setCardinality(
        $this->getIo()->ask(
          $this->trans('commands.site_builder_console.field.questions.cardinality'),
          $storage->getCardinality(),
          [$this, 'validateCardinality']
        )
      );
    }

    $storage->setSettings($this->settingsQuestion($storage->getSettings()));

    return $storage;
  }

  /**
   * Asks IO questions to create a instance definition for a field.
   *
   * @param string $entity_type
   *   The entity type the field will be attached to.
   * @param string $bundle
   *   The bundle the field will be attached to.
   * @param array $field
   *   A list of field parameters, containing:
   *   - type: The field type.
   *   - name: The field machine name.
   *   - storage: Unsaved \Drupal\field\FieldStorageConfigInterface if it will
   *     be created from this operation, NULL otherwise.
   *
   * @return \Drupal\Core\Field\FieldConfigBase
   *   The new, unsaved field instance.
   */
  protected function fieldInstanceQuestion($entity_type, $bundle, array $field) {
    /** @var \Drupal\Core\Field\FieldConfigBase $instance */
    $instance = $this->get('entity_type.manager')
      ->getStorage('field_config')
      ->create([
        'field_storage' => $field['storage'],
        'entity_type' => $entity_type,
        'field_name' => $field['name'],
        'bundle' => $bundle,
      ]);

    $instance->setLabel(
      $this->getIo()->ask(
        $this->trans('commands.site_builder_console.field.questions.label'),
        $this->get('console.string_converter')->camelCaseToHuman(
          $this->get('console.string_converter')->underscoreToCamelCase(
            preg_replace('/^field_/', '', $field['name'])
          )
        )
      )
    );

    $instance->setDescription(
      $this->getIo()->askEmpty(
        $this->trans('commands.site_builder_console.field.questions.description')
      )
    );

    $instance->setRequired(
      $this->getIo()->confirm(
        $this->trans('commands.site_builder_console.field.questions.required'),
        FALSE
      )
    );

    $settings = $instance->getSettings() + $this->get('plugin.manager.field.field_type')->getDefaultFieldSettings($field['type']);
    $instance->setSettings($this->settingsQuestion($settings));

    return $instance;
  }

  /**
   * Validates a given field instance does not exist on a content bundle.
   *
   * @param string $entity_type
   *   The entity type to search in.
   * @param string $bundle
   *   The bundle of the entity type to search in.
   * @param string $field_name
   *   The name of the field to search for.
   *
   * @return string
   *   The field name that has not been found.
   *
   * @throws InvalidArgumentException
   *   When the field exists on the bundle.
   */
  public function validateFieldInstanceNotExists($entity_type, $bundle, $field_name) {
    $field_instance = $this->get('entity_type.manager')
      ->getStorage('field_config')
      ->load("$entity_type.$bundle.$field_name");

    if (!$field_instance) {
      return $field_name;
    }

    throw new \InvalidArgumentException(sprintf(
      'The "%s" field already exists on the "%s" "%s" bundle.',
      $field_name,
      $entity_type,
      $bundle
    ));
  }

  /**
   * Validates a given field cardinality value.
   *
   * @param mixed $value
   *   The field cardinality value to validate.
   *
   * @return int
   *   The field cardinality value that has passed validation.
   *
   * @throws InvalidArgumentException
   *   When the value is not a positive integer or -1.
   */
  public function validateCardinality($value) {
    $parsed = intval($value);

    if ($parsed === 0 || $parsed < -1) {
      throw new \InvalidArgumentException('Cardinality must be a positive integer or -1.');
    }

    return $parsed;
  }

  /**
   * Gets the field names of all configurable fields on a bundle.
   *
   * @param string $entity_type
   *   The entity type to search in.
   * @param string $bundle
   *   The bundle of the entity type to search in.
   *
   * @return string[]
   *   The names of the fields.
   */
  protected function getConfigurableFields($entity_type, $bundle) {
    $definitions = $this->get('entity_type.manager')
      ->getStorage('field_config')
      ->loadByProperties([
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ]);

    return array_values(array_map(
      function (FieldConfigInterface $definition) {
        return $definition->getName();
      },
      $definitions
    ));
  }

}
