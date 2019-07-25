<?php

namespace Drupal\site_builder_console\Command\Bundle;

use Symfony\Component\Console\Input\InputOption;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Trait for dealing with bundles.
 */
trait BundleTrait {

  /**
   * Adds the entity type option to the command.
   *
   * @return self
   *   The command object for chaining.
   */
  protected function addEntityTypeOption() {
    return $this->addOption(
      'entity-type',
      NULL,
      InputOption::VALUE_REQUIRED,
      $this->trans('commands.site_builder_console.bundle.options.entity-type')
    );
  }

  /**
   * Adds the bundle name option to the command.
   *
   * @return self
   *   The command object for chaining.
   */
  protected function addBundleNameOption() {
    return $this->addOption(
      'bundle-name',
      NULL,
      InputOption::VALUE_REQUIRED,
      $this->trans('commands.site_builder_console.bundle.options.bundle-name')
    );
  }

  /**
   * Asks IO question for the entity type option.
   *
   * @return string
   *   The entity type ID.
   */
  protected function entityTypeQuestion() {
    return $this->getIo()->choiceNoList(
      $this->trans('commands.site_builder_console.bundle.questions.entity-type'),
      $this->getContentEntityTypes()
    );
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
      foreach ($this->get('entity_type.manager')->getDefinitions() as $key => $definition) {
        if ($definition instanceof ContentEntityTypeInterface) {
          $types[] = $key;
        }
      }
    }

    return $types;
  }

}
