<?php

namespace Drupal\site_builder_console\Command\Bundle;

use Drupal\block_content\BlockContentTypeInterface;
use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\Console\Utils\Validator;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\site_builder_console\Command\Field\FieldTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates an entity bundle on the current site.
 *
 * @DrupalCommand(
 *   extension="site_builder_console",
 *   extensionType="module"
 * )
 */
class CreateCommand extends ContainerAwareCommand {

  use BundleTrait;
  use FieldTrait;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Console\Core\Utils\StringConverter $string_converter
   *   The string converter utility.
   * @param \Drupal\Console\Utils\Validator $validator
   *   The validator utility.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, StringConverter $string_converter, Validator $validator) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
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
      ->addEntityTypeOption()
      ->addBundleNameOption()
      ->addOption(
        'bundle-label',
        NULL,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.site_builder_console.bundle.options.bundle-label')
      )
      ->addOption(
        'bundle-description',
        NULL,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.site_builder_console.bundle.options.bundle-description')
      )
      ->addOption(
        'bundle-options',
        NULL,
        InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
        $this->trans('commands.site_builder_console.bundle.options.bundle-options')
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
      $entity_type = $this->entityTypeQuestion();
      $input->setOption('entity-type', $entity_type);
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

    if (!$input->getOption('bundle-description')) {
      $bundle_description = $this->getIo()->askEmpty(
        $this->trans('commands.site_builder_console.bundle.questions.bundle-description')
      );
      $input->setOption('bundle-description', $bundle_description);
    }

    if ($entity_type == 'node' && !$input->getOption('bundle-options')) {
      $input->setOption('bundle-options', $this->nodeBundleOptionsQuestion());
    }

    if (!$input->getOption('fields')) {
      $fields = [];

      while (TRUE) {
        $continue = $this->getIo()->confirm(
          $this->trans('commands.site_builder_console.bundle.questions.new-field')
        );
        if (!$continue) {
          break;
        }

        $fields[] = $this->fieldCreateQuestion($entity_type, $bundle_name);
      }

      $input->setOption('fields', $fields);
    }
  }

  /**
   * Asks IO questions about node-specfic options.
   *
   * @return array
   *   Array of options containing:
   *   - form_display: Base/extra fields to hide in the node bundle's form
   *     display.
   *   - submitted: the value for display_submitted.
   */
  protected function nodeBundleOptionsQuestion() {
    $options = [];

    $form_display = [
      'created' => !$this->getIo()->confirm(
        $this->trans('commands.site_builder_console.bundle.questions.show-created')
      ),
      'path' => !$this->getIo()->confirm(
        $this->trans('commands.site_builder_console.bundle.questions.show-path')
      ),
    ];
    $options['form_display'] = array_keys(array_filter($form_display));

    $options['submitted'] = $this->getIo()->confirm(
      $this->trans('commands.site_builder_console.bundle.questions.show-submitted')
    );

    $options['full_crud'] = $this->getIo()->confirm(
      $this->trans('commands.site_builder_console.bundle.questions.full-crud')
    );

    return $options;
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
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $entity_type = $input->getOption('entity-type');
    $bundle_name = $input->getOption('bundle-name');
    $bundle_label = $input->getOption('bundle-label');

    // Get the ID of the bundle entity.
    $bundle_entity_id = $this->entityTypeManager
      ->getDefinition($entity_type)
      ->getBundleEntityType();

    // Get the bundle definition for its entity keys.
    $bundle_definition = $this->entityTypeManager->getDefinition($bundle_entity_id);

    // Create the bundle.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle */
    $bundle = $this->entityTypeManager
      ->getStorage($bundle_entity_id)
      ->create([
        $bundle_definition->getKey('id') => $bundle_name,
        $bundle_definition->getKey('label') => $bundle_label,
      ]);
    if (in_array('description', $bundle_definition->get('config_export'))) {
      $bundle->set('description', $input->getOption('bundle-description'));
    }
    $bundle->save();

    // Save the fields.
    foreach ($input->getOption('fields') as $field) {
      if ($field['storage']) {
        $field['storage']->save();
      }

      $field['instance']->save();
    }

    // Shared parameters for both form and view displays.
    $display_create_parameters = [
      'targetEntityType' => $entity_type,
      'bundle' => $bundle_name,
      'mode' => 'default',
      'status' => TRUE,
    ];

    // Create the form display.
    $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->create($display_create_parameters)
      ->save();

    // Create the view display.
    $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->create($display_create_parameters)
      ->save();

    $bundle_options = $input->getOption('bundle-options');
    switch ($entity_type) {
      case 'node':
        $this->applyNodeBundleSettings($bundle, $bundle_options);
        break;
      case 'block_content':
        $this->applyBlockContentBundleSettings($bundle, $bundle_options);
        break;
    }

    $output->writeln(sprintf(
      $this->trans('commands.site_builder_console.bundle.messages.created'),
      $entity_type,
      $bundle_label
    ));
  }

  /**
   * Applies settings and personal defaults to a node bundle.
   *
   * @param \Drupal\node\NodeTypeInterface $bundle
   *   The node bundle entity.
   * @param array $options
   *   Array of additional options containing:
   *   - form_display: A string array list of component keys to remove from
   *     the form display.
   *   - submitted: Option value for the 'display_submitted' key.
   *   - full_crud: Whether to give full CRUD permission of this bundle to
   *     the Editor role (if it exists). This includes creation and deletion;
   *     otherwise only editing is allowed.
   */
  protected function applyNodeBundleSettings(NodeTypeInterface $bundle, array $options) {
    $options += [
      'form_display' => [],
      'submitted' => TRUE,
      'full_crud' => TRUE,
    ];

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load("node.{$bundle->id()}.default")
      ->removeComponent('promote')
      ->removeComponent('sticky')
      ->removeComponent('uid');
    foreach ($options['form_display'] as $component) {
      $form_display->removeComponent($component);
    }
    $form_display->save();

    // Remove links from view display.
    $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load("node.{$bundle->id()}.default")
      ->removeComponent('links')
      ->save();

    // Change some bundle settings.
    $bundle->setPreviewMode(DRUPAL_DISABLED);
    $bundle->setDisplaySubmitted($options['submitted']);
    $bundle->save();

    // Change default value of "Promoted to front page" option.
    $this->entityFieldManager
      ->getFieldDefinitions('node', $bundle->id())['promote']
      ->getConfig($bundle->id())
      ->setDefaultValue(FALSE)
      ->save();

    /** @var \Drupal\user\RoleInterface $editor_role */
    $editor_role = $this->entityTypeManager
      ->getStorage('user_role')
      ->load('editor');
    if ($editor_role) {
      // Editors can edit the nodes of the bundle.
      $editor_role->grantPermission("edit any {$bundle->id()} content");

      // Editors can view any unpublished nodes of the bundle.
      if ($this->get('module_handler')->moduleExists('view_unpublished')) {
        $editor_role->grantPermission("view any unpublished {$bundle->id()} content");
      }

      // Editors may be given permissions to create and delete.
      if ($options['full_crud']) {
        $editor_role
          ->grantPermission("create {$bundle->id()} content")
          ->grantPermission("delete any {$bundle->id()} content");
      }

      $editor_role->save();
    }
  }

  /**
   * Applies settings and personal defaults to a block_content bundle.
   *
   * @param \Drupal\block_content\BlockContentTypeInterface $bundle
   *   The block_content bundle entity.
   */
  protected function applyBlockContentBundleSettings(BlockContentTypeInterface $bundle) {
    /** @var \Drupal\user\RoleInterface $editor_role */
    $editor_role = $this->entityTypeManager
      ->getStorage('user_role')
      ->load('editor');
    if ($editor_role) {
      $editor_role
        ->grantPermission("update any {$bundle->id()} block content")
        ->save();
    }
  }

}
