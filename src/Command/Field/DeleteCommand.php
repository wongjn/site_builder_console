<?php

namespace Drupal\site_builder_console\Command\Field;

use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\site_builder_console\Command\Bundle\BundleTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Deletes a field instance on the current site.
 *
 * @DrupalCommand(
 *   extension="site_builder_console",
 *   extensionType="module"
 * )
 */
class DeleteCommand extends ContainerAwareCommand {

  use BundleTrait;
  use FieldTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DeleteCommand object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('site_builder_console:field:delete')
      ->setDescription($this->trans('commands.site_builder_console.field.delete.description'))
      ->setHelp($this->trans('commands.site_builder_console.field.delete.help'))
      ->addEntityTypeOption()
      ->addBundleNameOption()
      ->addOption(
        'field-type',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.site_builder_console.field.options.type')
      )
      ->addFieldNameOption()
      ->setAliases(['sfd']);
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
      $bundle_name = $this->bundleChoiceQuestion($entity_type);
      $input->setOption('bundle-name', $bundle_name);
    }

    $field_name = $input->getOption('field-name');
    if (!$field_name) {
      $field_name = $this->getIo()->choiceNoList(
        $this->trans('commands.site_builder_console.field.questions.name'),
        $this->getConfigurableFields($entity_type, $bundle_name)
      );

      $input->setOption('field-name', $field_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->entityTypeManager
      ->getStorage('field_config')
      ->load(sprintf(
        '%s.%s.%s',
        $input->getOption('entity-type'),
        $input->getOption('bundle-name'),
        $input->getOption('field-name')
      ))
      ->delete();

    $output->writeln(sprintf(
      $this->trans('commands.site_builder_console.field.messages.deleted'),
      $input->getOption('field-name'),
      $input->getOption('entity-type'),
      $input->getOption('bundle-name')
    ));
  }

}
