<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle class for album nodes.
 *
 * An album is a set of media items belonging to a project.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 */
class Album extends Node {

  use DateFieldTrait;

  /**
   * The node type this class is the bundle class of.
   */
  public const string BUNDLE = 'album';

  /**
   * The project the album belongs to, if any.
   */
  public function getProject(): ?Project {
    if (!$this->hasField('field_project')) {
      return NULL;
    }
    $project = $this->get('field_project')->entity;

    return $project instanceof Project ? $project : NULL;
  }

  /**
   * The licence the album is published under, if any.
   */
  public function getLicence(): ?Licence {
    if (!$this->hasField('field_license')) {
      return NULL;
    }
    $licence = $this->get('field_license')->entity;

    return $licence instanceof Licence ? $licence : NULL;
  }

  /**
   * The species the album documents.
   *
   * @return list<\Drupal\taxonomy\TermInterface>
   *   The species terms.
   */
  public function getSpecies(): array {
    return $this->terms('field_species');
  }

  /**
   * The kinds of animal production the album documents.
   *
   * @return list<\Drupal\taxonomy\TermInterface>
   *   The use terms.
   */
  public function getUse(): array {
    return $this->terms('field_use');
  }

  /**
   * The media items of the album, in album order.
   *
   * Items whose media entity is gone are skipped, so the keys are renumbered
   * and the list can be shorter than getMediaCount().
   *
   * @return list<\Drupal\media\MediaInterface>
   *   The media items.
   */
  public function getMedia(): array {
    if (!$this->hasField('field_album_media')) {
      return [];
    }
    $items = [];
    foreach ($this->get('field_album_media')->referencedEntities() as $item) {
      if ($item instanceof MediaInterface) {
        $items[] = $item;
      }
    }

    return $items;
  }

  /**
   * The media items of the album the account may view, in album order.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Collects the cacheability of the access checks, if given.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account to check for, or NULL for the current user.
   *
   * @return list<\Drupal\media\MediaInterface>
   *   The media items, keyed by their position among the viewable ones.
   */
  public function getViewableMedia(?CacheableMetadata $cache = NULL, ?AccountInterface $account = NULL): array {
    $items = [];
    foreach ($this->getMedia() as $item) {
      $access = $item->access('view', $account, TRUE);
      $cache?->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $items[] = $item;
      }
    }

    return $items;
  }

  /**
   * The media item the album is represented by: its first one.
   */
  public function getCover(): ?MediaInterface {
    if (!$this->hasField('field_album_media')) {
      return NULL;
    }
    $cover = $this->get('field_album_media')->entity;

    return $cover instanceof MediaInterface ? $cover : NULL;
  }

  /**
   * How many media items the album holds.
   */
  public function getMediaCount(): int {
    return $this->hasField('field_album_media') ? $this->get('field_album_media')->count() : 0;
  }

  /**
   * The terms an entity reference field of the album points at.
   *
   * @return list<\Drupal\taxonomy\TermInterface>
   *   The terms, in field order.
   */
  private function terms(string $field): array {
    if (!$this->hasField($field)) {
      return [];
    }
    $terms = [];
    foreach ($this->get($field)->referencedEntities() as $term) {
      if ($term instanceof TermInterface) {
        $terms[] = $term;
      }
    }

    return $terms;
  }

}
