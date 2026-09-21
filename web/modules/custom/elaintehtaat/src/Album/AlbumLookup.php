<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Album;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Finds the albums a media item belongs to.
 *
 * Albums reference their media, not the other way round, so the only way from
 * a media item back to its album is this reverse query. An item may sit in
 * several albums.
 *
 * @see \Drupal\elaintehtaat\Hook\MediaTileHooks
 * @see \Drupal\elaintehtaat\Plugin\search_api\processor\AlbumData
 */
final readonly class AlbumLookup {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * The published albums holding a media item, newest first.
   *
   * The status is checked on the translation that is asked for, not in the
   * query: status is translatable on nodes, so a query condition would match
   * an album that is published in any language at all.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item to find the albums of.
   * @param string|null $langcode
   *   The language to read the albums in, or NULL for their default language.
   *
   * @return list<\Drupal\elaintehtaat\Entity\Album>
   *   The albums, newest created first.
   */
  public function albumsOf(MediaInterface $media, ?string $langcode = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', Album::BUNDLE)
      ->condition('field_album_media.target_id', $media->id())
      ->sort('created', 'DESC')
      ->execute();
    if (!$ids) {
      return [];
    }

    $albums = [];
    foreach ($storage->loadMultiple($ids) as $album) {
      if (!$album instanceof Album) {
        continue;
      }
      if ($langcode !== NULL && $album->hasTranslation($langcode)) {
        $album = $album->getTranslation($langcode);
      }
      if ($album->isPublished()) {
        $albums[] = $album;
      }
    }

    return $albums;
  }

  /**
   * The newest published album holding a media item, if there is one.
   */
  public function newestAlbumOf(MediaInterface $media, ?string $langcode = NULL): ?Album {
    return $this->albumsOf($media, $langcode)[0] ?? NULL;
  }

  /**
   * The IDs of the media items an album holds, in album order.
   *
   * Reads the field values rather than the referenced entities, so it also
   * works on the original of an album being saved.
   *
   * @return list<string>
   *   The media IDs.
   */
  public function mediaIdsOf(NodeInterface $album): array {
    if ($album->bundle() !== Album::BUNDLE || !$album->hasField('field_album_media')) {
      return [];
    }

    return array_map(
      static fn (mixed $id): string => (string) $id,
      array_column($album->get('field_album_media')->getValue(), 'target_id'),
    );
  }

}
