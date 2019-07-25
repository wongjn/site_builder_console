<?php

namespace Drupal\site_builder_console\Command\Bundle;

use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\Console\Utils\Validator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
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
   * @param \Drupal\Console\Core\Utils\StringConverter $string_converter
   *   The string converter utility.
   * @param \Drupal\Console\Utils\Validator $validator
   *   The validator utility.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, StringConverter $string_converter, Validator $validator) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
