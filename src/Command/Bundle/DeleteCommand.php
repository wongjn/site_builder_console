<?php

namespace Drupal\site_builder_console\Command\Bundle;

use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Deletes an entity bundle on the current site.
 *
 * @DrupalCommand(
 *   extension="site_builder_console",
 *   extensionType="module"
 * )
 */
class DeleteCommand extends ContainerAwareCommand {

  use BundleTrait;

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
   * Constructs a new DeleteCommand object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('site_builder_console:bundle:delete')
      ->setDescription($this->trans('commands.site_builder_console.bundle.delete.description'))
      ->setHelp($this->trans('commands.site_builder_console.bundle.delete.help'))
      ->addEntityTypeOption()
      ->addBundleNameOption()
      ->setAliases(['sbd']);
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
      $bundle_name = $this->getIo()->choiceNoList(
        $this->trans('commands.site_builder_console.bundle.questions.bundle-name'),
        array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type))
      );
      $input->setOption('bundle-name', $bundle_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $bundle_entity_id = $this->entityTypeManager
      ->getDefinition($input->getOption('entity-type'))
      ->getBundleEntityType();

    $this->entityTypeManager
      ->getStorage($bundle_entity_id)
      ->load($input->getOption('bundle-name'))
      ->delete();

    $output->writeln(sprintf(
      $this->trans('commands.site_builder_console.bundle.messages.deleted'),
      $input->getOption('bundle-name')
    ));
  }

}
