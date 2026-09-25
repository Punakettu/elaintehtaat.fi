<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\elaintehtaat\Album\AlbumLookup;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\media\MediaInterface;

/**
 * Links media tiles to the media page of the album they belong to.
 *
 * A tile in a listing opens the item full screen on the media page of the
 * album that contains it, and shows the album name as the link text. Inside an
 * album that is the album being viewed; elsewhere, where the item may belong to
 * several albums, it is the newest one.
 *
 * @see \Drupal\elaintehtaat\Controller\MediaPageController
 */
final readonly class MediaTileHooks {

  /**
   * The media view mode that gets the media page link.
   */
  public const string VIEW_MODE = 'tile';

  public function __construct(
    private AlbumLookup $albumLookup,
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

    $album = $this->referringAlbum($media);
    if ($album === NULL) {
      // Saving any album may add this item to an album or remove it from one.
      $cache->addCacheTags(['node_list:album']);
      $album = $this->albumLookup->newestAlbumOf($media);
    }
    if ($album !== NULL) {
      $album = $this->entityRepository->getTranslationFromContext($album);
      $cache->addCacheableDependency($album);
      $build['album'] = [
        '#type' => 'link',
        '#title' => $album->label(),
        '#url' => Url::fromRoute('elaintehtaat.media_page', [
          'node' => $album->id(),
          'media' => $media->id(),
        ]),
        '#weight' => 10,
      ];
    }

    // Merge instead of applyTo(): the view builder has already put the media
    // entity's own contexts and tags on $build, and applyTo() would wipe them.
    CacheableMetadata::createFromRenderArray($build)->merge($cache)->applyTo($build);
  }

  /**
   * The album the tile is being rendered inside, if it is rendered in one.
   *
   * The entity reference formatter records the field item the entity is
   * rendered for, which is how the tile knows the album it belongs to on the
   * album page itself.
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase::getEntitiesToView()
   */
  private function referringAlbum(MediaInterface $media): ?Album {
    // Not a field but a plain property the formatter sets on the entity
    // object; ContentEntityBase returns NULL for one that was never set, which
    // PHPStan's Drupal extension misses as it reads the name as a field name.
    // @phpstan-ignore nullsafe.neverNull
    $parent = $media->_referringItem?->getEntity();

    return $parent instanceof Album ? $parent : NULL;
  }

}
