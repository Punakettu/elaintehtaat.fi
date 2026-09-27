<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\file\FileInterface;

/**
 * Bundle class for image media.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 */
class Image extends AlbumMedia {

  /**
   * The media type this class is the bundle class of.
   */
  public const string BUNDLE = 'image';

  /**
   * The file behind the media source field, if any.
   */
  public function getSourceFile(): ?FileInterface {
    $file = $this->getSourceItem()?->get('entity')->getValue();

    return $file instanceof FileInterface ? $file : NULL;
  }

}
