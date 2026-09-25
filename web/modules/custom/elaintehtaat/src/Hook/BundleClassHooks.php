<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Entity\Licence;
use Drupal\elaintehtaat\Entity\Project;

/**
 * Registers the bundle classes of the site's own bundles.
 */
final class BundleClassHooks {

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    $classes = [
      'node' => [
        Album::BUNDLE => Album::class,
        Project::BUNDLE => Project::class,
      ],
      'media' => [
        Image::BUNDLE => Image::class,
      ],
      'taxonomy_term' => [
        Licence::BUNDLE => Licence::class,
      ],
    ];
    foreach ($classes as $entity_type => $bundle_classes) {
      foreach ($bundle_classes as $bundle => $class) {
        if (isset($bundles[$entity_type][$bundle])) {
          $bundles[$entity_type][$bundle]['class'] = $class;
        }
      }
    }
  }

}
