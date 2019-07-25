<?php

namespace Drupal\site_builder_console\Command\Bundle;

use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\Console\Utils\Validator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Creates an entity bundle on the current site.
 *
 * @DrupalCommand(
 *   extension="site_builder_console",
 *   extensionType="module"
 * )
 */
class CreateCommand extends Command {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The field type manager to define field.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The string converter utility.
   *
   * @var \Drupal\Console\Core\Utils\StringConverter
   */
  protected $stringConverter;

  /**
   * The validator utility.
   *
   * @var \Drupal\Console\Utils\Validator
   */
  protected $validator;

  /**
   * Constructs a new CreateCommand object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The 'field type' plugin manager.
   * @param \Drupal\Console\Core\Utils\StringConverter $string_converter
   *   The string converter utility.
   * @param \Drupal\Console\Utils\Validator $validator
   *   The validator utility.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, FieldTypePluginManagerInterface $field_type_manager, StringConverter $string_converter, Validator $validator) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->fieldTypeManager = $field_type_manager;
    $this->stringConverter = $string_converter;
    $this->validator = $validator;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('site_builder_console:bundle:create')
      ->setDescription($this->trans('commands.site_builder_console.bundle.create.description'))
      ->setHelp($this->trans('commands.site_builder_console.bundle.create.help'))
      ->addOption(
        'entity-type',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.site_builder_console.bundle.options.entity-type')
      )
      ->addOption(
        'bundle-name',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.site_builder_console.bundle.options.bundle-name')
      )
      ->addOption(
        'bundle-label',
        NULL,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.site_builder_console.bundle.options.bundle-label')
      )
      ->addOption(
        'fields',
        NULL,
        InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
        $this->trans('commands.site_builder_console.bundle.options.fields')
      )
      ->setAliases(['sbc']);
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $entity_type = $input->getOption('entity-type');
    if (!$entity_type) {
      $entity_type = $this->getIo()->choiceNoList(
        $this->trans('commands.site_builder_console.bundle.questions.entity-type'),
        $this->getContentEntityTypes()
      );
      $input->setOption('entity_type', $entity_type);
    }

    $bundle_name = $input->getOption('bundle-name');
    if (!$bundle_name) {
      $bundle_name = $this->getIo()->ask(
        $this->trans('commands.site_builder_console.bundle.questions.bundle-name'),
        'custom_bundle',
        function ($bundle) use ($entity_type) {
          return $this->validateNewBundleName($entity_type, $bundle);
        }
      );
      $input->setOption('bundle-name', $bundle_name);
    }

    if (!$input->getOption('bundle-label')) {
      $bundle_label = $this->getIo()->ask(
        $this->trans('commands.site_builder_console.bundle.questions.bundle-label'),
        $this->stringConverter->camelCaseToHuman(
          $this->stringConverter->underscoreToCamelCase($bundle_name)
        )
      );
      $input->setOption('bundle-label', $bundle_label);
    }

    $input->setOption('fields', $this->fieldQuestion($entity_type, $bundle_name));
  }

  /**
   * Asks IO questions to create fields for a bundle.
   *
   * @param string $entity_type
   *   The entity type the field will be attached to.
   * @param string $bundle
   *   The bundle the field will be attached to.
   *
   * @return array
   *   A list of field parameters, each containing:
   *   - type: The field type.
   *   - name: The field machine name.
   *   - storage: Unsaved \Drupal\field\FieldStorageConfigInterface if it will
   *     be created from this operation, NULL otherwise.
   *   - instance: Unsaved \Drupal\Core\Field\FieldConfigBase for the field.
   */
  public function fieldQuestion($entity_type, $bundle) {
    $fields = [];

    while (TRUE) {
      $continue = $this->getIo()->confirm(
        $this->trans('commands.site_builder_console.bundle.questions.new-field')
      );
      if (!$continue) {
        break;
      }

      $field['type'] = $this->getIo()->choiceNoList(
        $this->trans('commands.site_builder_console.bundle.questions.field.type'),
        $this->getFieldTypes()
      );
      $field['name'] = $this->getIo()->ask(
        $this->trans('commands.site_builder_console.bundle.questions.field.name'),
        "field_$field[type]",
        function ($name) {
          return $this->validator->validateMachineName($name);
        }
      );

      $field['storage'] = $this->fieldStorageQuestion($entity_type, $field);
      $field['instance'] = $this->fieldInstanceQuestion($entity_type, $bundle, $field);

      $fields[] = $field;
    }

    return $fields;
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
    $matched_definitions = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->loadByProperties(['id' => "$entity_type.$field[name]"]);
    if (!empty($matched_definitions)) {
      return NULL;
    }

    /** @var \Drupal\field\FieldStorageConfigInterface $storage */
    $storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->create([
        'type' => $field['type'],
        'field_name' => $field['name'],
        'entity_type' => $entity_type,
      ]);

    $type_definition = $this->fieldTypeManager->getDefinition($field['type']);
    if (!isset($type_definition['cardinality'])) {
      $storage->setCardinality(
        $this->getIo()->ask(
          $this->trans('commands.site_builder_console.bundle.questions.field.cardinality'),
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
   * @return \Drupal\Core\Field\FieldConfigBase|null
   *   The new, unsaved field storage definition or NULL if it already exists.
   */
  protected function fieldInstanceQuestion($entity_type, $bundle, array $field) {
    /** @var \Drupal\Core\Field\FieldConfigBase $instance */
    $instance = $this->entityTypeManager
      ->getStorage('field_config')
      ->create([
        'entity_type' => $entity_type,
        'field_name' => $field['name'],
        'bundle' => $bundle,
      ]);

    $instance->setLabel(
      $this->getIo()->ask(
        $this->trans('commands.site_builder_console.bundle.questions.field.label'),
        $this->stringConverter->camelCaseToHuman(
          $this->stringConverter->underscoreToCamelCase(
            preg_replace('/^field_/', '', $field['name'])
          )
        )
      )
    );

    $instance->setDescription(
      $this->getIo()->askEmpty(
        $this->trans('commands.site_builder_console.bundle.questions.field.description')
      )
    );

    $instance->setRequired(
      $this->getIo()->confirm(
        $this->trans('commands.site_builder_console.bundle.questions.field.required'),
        FALSE
      )
    );

    $settings = $instance->getSettings() + $this->fieldTypeManager->getDefaultFieldSettings($field['type']);
    $instance->setSettings($this->settingsQuestion($settings));

    return $instance;
  }

  /**
   * Asks IO questions for settings.
   *
   * @param array $settings
   *   Settings to ask values for. Each element contains the default for the
   *   value, keyed by the setting key. Pass an array as an element to be
   *   recursive.
   *
   * @todo Handle non-associative arrays.
   *
   * @return array
   *   The settings.
   */
  protected function settingsQuestion(array $settings) {
    $values = [];

    $recursing = FALSE;
    foreach ($settings as $key => $default) {
      if (is_array($default)) {
        if (!empty($default)) {
          $recursing = $key;
          $this->getIo()->comment(sprintf('Recursing into "%s" setting hash.', $key));
          $values[$key] = $this->settingsQuestion($default);
        }

        continue;
      }

      if ($recursing) {
        $this->getIo()->comment(sprintf('"%s" setting hash recursing end.', $recursing));
        $recursing = FALSE;
      }

      $values[$key] = $this->getIo()->askEmpty(
        sprintf('Value for "%s" setting', $key),
        $default
      );
    }

    if ($recursing) {
      $this->getIo()->comment(sprintf('"%s" setting hash recursing end.', $recursing));
      $recursing = FALSE;
    }

    return $values;
  }

  /**
   * Gets a list of content entity types.
   *
   * @return string[]
   *   Array of content entity type machine names.
   */
  protected function getContentEntityTypes() {
    static $types;

    if (!isset($types)) {
      foreach ($this->entityTypeManager->getDefinitions() as $key => $definition) {
        if ($definition instanceof ContentEntityTypeInterface) {
          $types[] = $key;
        }
      }
    }

    return $types;
  }

  /**
   * Gets a list of content entity types.
   *
   * @return string[]
   *   Array of content entity type machine names.
   */
  protected function getFieldTypes() {
    return array_keys($this->fieldTypeManager->getUiDefinitions());
  }

  /**
   * Validates a proposed bundle name.
   *
   * @param string $entity_type
   *   The entity type of which the proposed bundle would be created for.
   * @param string $bundle
   *   The proposed machine name of the bundle.
   *
   * @return string
   *   The passed bundle name if it passes validation.
   *
   * @throws InvalidArgumentException
   *   When the bundle name already exists for the current entity type.
   */
  public function validateNewBundleName($entity_type, $bundle) {
    $bundle = $this->validator->validateMachineName($bundle);

    $existing_bundles = array_keys(
      $this->entityTypeBundleInfo->getBundleInfo($entity_type)
    );
    if (!in_array($bundle, $existing_bundles)) {
      return $bundle;
    }

    throw new \InvalidArgumentException(
      sprintf('There is already a "%s" bundle.', $bundle)
    );
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
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $bundle_entity_id = $this->entityTypeManager
      ->getDefinition($input->getOption('entity-type'))
      ->getBundleEntityType();

    $bundle_definition = $this->entityTypeManager->getDefinition($bundle_entity_id);

    $this->entityTypeManager
      ->getStorage($bundle_entity_id)
      ->create([
        $bundle_definition->getKey('id') => $input->getOption('bundle-name'),
        $bundle_definition->getKey('label') => $input->getOption('bundle-label'),
      ])
      ->save();

    foreach ($input->getOption('fields') as $field) {
      if ($field['storage']) {
        $field['storage']->save();
      }

      $field['instance']->save();
    }

    $output->writeln(sprintf(
      $this->trans('commands.site_builder_console.bundle.messages.created'),
      $input->getOption('bundle-label')
    ));
  }

}
