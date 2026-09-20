<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Links media tiles to the album they belong to.
 *
 * Media items have no page of their own, so a tile in a listing opens
 * the album that contains the item.
 *
 * @TODO: change this wen we add standalone media route.
 */
final readonly class MediaTileHooks {

  /**
   * The media view mode that gets the album link.
   */
  public const string VIEW_MODE = 'tile';

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for media entities.
   */
  #[Hook('media_view')]
  public function mediaView(array &$build, MediaInterface $media, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($view_mode !== self::VIEW_MODE) {
      return;
    }

    $cache = new CacheableMetadata();

    // Saving any album may add this item to an album or remove it from one.
    $cache->addCacheTags(['node_list:album']);

    $album = $this->findAlbum($media);
    if ($album instanceof NodeInterface) {
      $album = $this->entityRepository->getTranslationFromContext($album);
      $cache->addCacheableDependency($album);
      $build['album'] = [
        '#type' => 'link',
        '#title' => $album->label(),
        '#url' => $album->toUrl(),
        '#weight' => 10,
      ];
    }

    $cache->applyTo($build);
  }

  /**
   * Finds the newest published album containing the media item.
   */
  private function findAlbum(MediaInterface $media): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'album')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_album_media.target_id', $media->id())
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $album = $storage->load(reset($ids));
    return $album instanceof NodeInterface ? $album : NULL;
  }

}
