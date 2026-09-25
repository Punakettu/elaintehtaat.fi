<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\elaintehtaat\Album\AlbumLookup;
use Drupal\elaintehtaat\Plugin\search_api\processor\AlbumData;
use Drupal\node\NodeInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;

/**
 * Re-indexes the media of an album whenever the album changes.
 *
 * The album fields a media item is indexed by are added by a processor, and
 * search_api tracks changes in referenced entities only for properties it
 * finds on the datasource itself. Processor properties are invisible to that
 * tracking, so nothing would mark the items of an edited album as changed and
 * the index would quietly go stale.
 *
 * Any edit to an album changes the indexed data of every item it holds — its
 * title, species, use, project, date and published status all end up on the
 * items — so the whole album is re-indexed rather than the changed field
 * worked out. Albums hold tens of items.
 *
 * @see \Drupal\elaintehtaat\Plugin\search_api\processor\AlbumData
 * @see \Drupal\search_api\Utility\TrackingHelper::getForeignEntityRelationsMap()
 */
final readonly class AlbumIndexHooks {

  public function __construct(
    private AlbumLookup $albumLookup,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    $this->track($this->albumLookup->mediaIdsOf($node));
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    $ids = $this->albumLookup->mediaIdsOf($node);

    // Items taken out of the album have to be re-indexed as well, so they
    // stop being found by its species and use.
    $original = $node->getOriginal();
    if ($original instanceof NodeInterface) {
      $ids = array_merge($ids, $this->albumLookup->mediaIdsOf($original));
    }

    $this->track($ids);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for node entities.
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    $this->track($this->albumLookup->mediaIdsOf($node));
  }

  /**
   * Marks media items as changed on every index that holds album data.
   *
   * @param list<string> $media_ids
   *   The IDs of the media items to re-index. May repeat and may be empty.
   */
  private function track(array $media_ids): void {
    $media_ids = array_unique($media_ids);
    $indexes = $this->indexes();
    if ($media_ids === [] || $indexes === []) {
      return;
    }

    $item_ids = $this->itemIds($media_ids);
    if ($item_ids === []) {
      return;
    }

    foreach ($indexes as $index) {
      foreach ($index->getDatasources() as $datasource_id => $datasource) {
        if ($datasource->getEntityTypeId() !== AlbumData::DATASOURCE_ENTITY_TYPE) {
          continue;
        }
        // These hooks run once the album has been written, so an index that
        // indexes directly can pick the items up straight away: there is no
        // pre-save data left to read.
        $index->trackItemsUpdated($datasource_id, $item_ids);
      }
    }
  }

  /**
   * The index item IDs of media items, one per translation they have.
   *
   * An index holds one item per translation, so the IDs are built from the
   * translations the items actually have: an ID for a language a media item
   * was never translated into names an item that does not exist, and indexing
   * it only logs a warning.
   *
   * @param list<string> $media_ids
   *   The IDs of the media items.
   *
   * @return list<string>
   *   The item IDs, in the format the content entity datasource uses.
   */
  private function itemIds(array $media_ids): array {
    $storage = $this->entityTypeManager->getStorage(AlbumData::DATASOURCE_ENTITY_TYPE);

    $item_ids = [];
    foreach ($storage->loadMultiple($media_ids) as $media_id => $media) {
      foreach (array_keys($media->getTranslationLanguages()) as $langcode) {
        $item_ids[] = ContentEntity::formatItemId(AlbumData::DATASOURCE_ENTITY_TYPE, $media_id, $langcode);
      }
    }

    return $item_ids;
  }

  /**
   * The enabled indexes that add album data to their items.
   *
   * @return list<\Drupal\search_api\IndexInterface>
   *   The indexes.
   */
  private function indexes(): array {
    $indexes = [];
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
      if ($index->status() && $index->isValidProcessor(AlbumData::PLUGIN_ID)) {
        $indexes[] = $index;
      }
    }

    return $indexes;
  }

}
