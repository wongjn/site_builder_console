<?php

namespace Drupal\site_builder_console\Command;

/**
 * Trait for dealing with general settings data structures.
 */
trait SettingsTrait {

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

}
