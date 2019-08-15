<?php

namespace Drupal\site_builder_console\Command\Field;

use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\site_builder_console\Command\Bundle\BundleTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a field instance on a content bundle.
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
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('site_builder_console:field:create')
      ->setDescription($this->trans('commands.site_builder_console.field.create.description'))
      ->setHelp($this->trans('commands.site_builder_console.field.create.help'))
      ->addEntityTypeOption()
      ->addBundleNameOption()
      ->addFieldNameOption()
      ->addOption(
        'field-type',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.site_builder_console.field.options.type')
      )
      ->setAliases(['sfc']);
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

    $this->field = $this->fieldCreateQuestion(
      $entity_type,
      $bundle_name,
      $input->getOption('field-type'),
      $input->getOption('field-name')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($this->field['storage']) {
      $this->field['storage']->save();
    }

    $this->field['instance']->save();

    $this->getIo()->success(sprintf(
      $this->trans('commands.site_builder_console.field.messages.created'),
      $this->field['name'],
      $input->getOption('entity-type'),
      $input->getOption('bundle-name')
    ));
  }

}
