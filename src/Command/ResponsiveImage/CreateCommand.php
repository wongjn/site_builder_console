<?php

namespace Drupal\site_builder_console\Command\ResponsiveImage;

use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a responsive image style.
 *
 * @DrupalCommand(
 *   extension="site_builder_console",
 *   extensionType="module"
 * )
 */
class CreateCommand extends ContainerAwareCommand {

  /**
   * The list of widths to use for image style sizes.
   *
   * @var int[]
   */
  const WIDTH_MARKS = [
    1920,
    1600,
    1280,
    800,
    400,
  ];

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('site_builder_console:responsive_image:create')
      ->setDescription($this->trans('commands.site_builder_console.responsive-image.create.description'))
      ->setHelp($this->trans('commands.site_builder_console.responsive-image.create.help'))
      ->addOption(
        'id',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.site_builder_console.responsive-image.options.id')
      )
      ->addOption(
        'label',
        NULL,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.site_builder_console.responsive-image.options.label')
      )
      ->addOption(
        'width',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.site_builder_console.responsive-image.options.width')
      )
      ->addOption(
        'height',
        NULL,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.site_builder_console.responsive-image.options.height')
      )
      ->setAliases(['src']);
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $id = $input->getOption('id');
    if (!$id) {
      $id = $this->getIo()->ask(
        $this->trans('commands.site_builder_console.responsive-image.questions.id'),
        'style',
        [$this, 'validateNewResponsiveImageId']
      );
      $input->setOption('id', $id);
    }

    if (!$input->getOption('label')) {
      $label = $this->getIo()->ask(
        $this->trans('commands.site_builder_console.responsive-image.questions.label'),
        $this->get('console.string_converter')->camelCaseToHuman(
          $this->get('console.string_converter')->underscoreToCamelCase($id)
        )
      );
      $input->setOption('label', $label);
    }

    if (!$input->getOption('width')) {
      $width = $this->getIo()->ask(
        $this->trans('commands.site_builder_console.responsive-image.questions.width'),
        NULL,
        [$this, 'validateDimensionLength']
      );
      $input->setOption('width', $width);
    }

    if (!$input->getOption('height')) {
      $height = $this->getIo()->askEmpty(
        $this->trans('commands.site_builder_console.responsive-image.questions.height'),
        NULL,
        [$this, 'validateDimensionLength']
      );
      $input->setOption('height', $height);
    }
  }

  /**
   * Validates a proposed responsive image name does not exist.
   *
   * @param string $id
   *   The responsive image style ID.
   *
   * @return string
   *   The given ID.
   *
   * @throws InvalidArgumentException
   *   When a responsive image style already exists with the given ID.
   */
  public function validateNewResponsiveImageId($id) {
    // Check valid macine name first.
    $this->get('console.validator')->validateMachineName($id);

    $maybe_existing = $this->get('entity_type.manager')
      ->getStorage('responsive_image_style')
      ->load($id);
    if ($maybe_existing) {
      throw new \InvalidArgumentException(
        sprintf($this->trans('commands.site_builder_console.responsive-image.messages.exists-error'), $id)
      );
    }

    return $id;
  }

  /**
   * Validates a proposed dimension length.
   *
   * @param mixed $length
   *   The dimension length.
   *
   * @return int
   *   The validated dimension length.
   *
   * @throws InvalidArgumentException
   *   When a given length cannot be casted to a positive integer.
   */
  public function validateDimensionLength($length) {
    $length = intval($length);

    if ($length > 1) {
      return $length;
    }

    throw new \InvalidArgumentException(
      $this->trans('commands.site_builder_console.responsive-image.messages.length-error')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $width  = intval($input->getOption('width'));
    $height = intval($input->getOption('height'));
    $id     = $input->getOption('id');
    $label  = $input->getOption('label');

    $aspect_ratio = $height ? $height / $width : NULL;

    $image_sizes = [];
    foreach (array_unique([$width] + self::WIDTH_MARKS) as $style_width) {
      // Do not create derivatives larger than the passed width.
      if ($style_width > $width) {
        continue;
      }

      $style_id     = "${id}_$style_width";
      $style_height = $height ? intval($aspect_ratio * $style_width) : NULL;

      $this->createImageStyle(
        $style_id,
        sprintf("$label (${style_width}×%s)", $height ? $style_height : 'h'),
        $style_width,
        $style_height
      );

      $image_sizes[] = $style_id;
    }

    // Create lazy image style derivative.
    if ($this->get('module_handler')->moduleExists('lazy_image')) {
      $lazy_width = 5;
      $lazy_height = NULL;

      if ($height) {
        $best_diff = PHP_INT_MAX;

        // Find the best height that is closest to a integer.
        for ($i = 1; $i <= 10; $i++) {
          $precise_height = $i * $aspect_ratio;
          $rounded_height = round($precise_height);
          $difference = abs($rounded_height - $precise_height);

          if ($difference < $best_diff) {
            $best_diff = $difference;

            $lazy_width = $i;
            $lazy_height = $rounded_height;
          }
        }
      }

      $this->createImageStyle("${id}_lazy", "$label (lazy)", $lazy_width, $lazy_height);
    }

    $this
      ->get('entity_type.manager')
      ->getStorage('responsive_image_style')
      ->create(['id' => $id, 'label' => $label])
      ->setFallbackImageStyle(reset($image_sizes))
      ->setBreakpointGroup('responsive_image')
      ->addImageStyleMapping(
        'responsive_image.viewport_sizing',
        '1x',
        [
          'image_mapping_type' => 'sizes',
          'image_mapping' => [
            'sizes' => '100vw',
            'sizes_image_styles' => $image_sizes,
          ],
        ]
      )
      ->save();

    $this->getIo()->success(
      sprintf(
        $this->trans('commands.site_builder_console.responsive-image.messages.created'),
        $label,
        $id
      )
    );
  }

  /**
   * Create and save an image style configuration entity.
   *
   * @param string $id
   *   Image style machine name.
   * @param string $name
   *   Image style label.
   * @param int $width
   *   Style width.
   * @param int|null $height
   *   (optional) Style height, or null for the style to scale.
   */
  protected function createImageStyle($id, $name, $width, $height = NULL) {
    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this
      ->get('entity_type.manager')
      ->getStorage('image_style')
      ->create(['label' => $name, 'name' => $id]);

    $image_style->addImageEffect([
      'id' => $height ? 'image_scale_and_crop' : 'image_scale',
      'weight' => 0,
      'data' => ['width' => $width, 'height' => $height],
    ]);

    $image_style->save();
  }

}
