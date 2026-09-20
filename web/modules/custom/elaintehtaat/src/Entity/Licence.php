<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for licence terms.
 *
 * A licence names the terms media may be used on, and links to them.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 */
class Licence extends Term {

  /**
   * The vocabulary this class is the bundle class of.
   */
  public const string BUNDLE = 'licence';

  /**
   * Where the licence is written out in full, if anywhere.
   */
  public function getUrl(): ?Url {
    if (!$this->hasField('field_licence_link')) {
      return NULL;
    }
    $link = $this->get('field_licence_link')->first();

    return $link instanceof LinkItemInterface && !$link->isEmpty() ? $link->getUrl() : NULL;
  }

}
