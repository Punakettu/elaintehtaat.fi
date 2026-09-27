<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

/**
 * Bundle class for video media, hosted on Bunny Stream.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 */
class Video extends AlbumMedia {

  /**
   * The media type this class is the bundle class of.
   */
  public const string BUNDLE = 'video';

}
