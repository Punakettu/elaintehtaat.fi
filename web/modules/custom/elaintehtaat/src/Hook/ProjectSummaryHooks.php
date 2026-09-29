<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\elaintehtaat\Album\AlbumLookup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\AlbumMedia;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\node\NodeInterface;

/**
 * Summarises the albums of a project on the view modes that list projects.
 *
 * A project has no date and no image of its own: both are read off its albums,
 * so every listing of projects needs this summary.
 *
 * @see templates/content/node--project--teaser.html.twig
 * @see templates/content/node--project--card.html.twig
 */
final class ProjectSummaryHooks {

  use StringTranslationTrait;

  /**
   * The view modes that get the album summary, and how they show the covers.
   *
   * The teaser is a row of the front page index, where the covers are a line
   * of small thumbnails. The card is a tile of the projects page, where the
   * first cover fills most of a mosaic and needs the resolution to match, so
   * it takes the responsive style of the media tiles, which are as wide; the
   * others are a quarter of the tile, so they get a smaller image style.
   */
  private const array MODES = [
    'teaser' => ['limit' => 4, 'lead_style' => 'thumbnail', 'lead_responsive' => FALSE, 'style' => 'thumbnail'],
    'card' => ['limit' => 3, 'lead_style' => 'media_tile', 'lead_responsive' => TRUE, 'style' => 'large'],
  ];

  public function __construct(
    private readonly AlbumLookup $albumLookup,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    $mode = self::MODES[$view_mode] ?? NULL;
    if ($mode === NULL || !$node instanceof Project) {
      return;
    }

    $cache = new CacheableMetadata();
    // Saving any album may add it to this project or take it out again.
    $cache->addCacheTags(['node_list:album']);

    $albums = $this->albumLookup->albumsOfProject($node);
    foreach ($albums as $album) {
      $cache->addCacheableDependency($album);
    }

    // The newest album dates the project.
    $year = ($albums[0] ?? NULL)?->getYear();
    if ($year !== NULL) {
      $build['year'] = [
        '#plain_text' => $year,
        '#weight' => -10,
      ];
    }

    $covers = [];
    foreach (array_slice($albums, 0, $mode['limit']) as $album) {
      // The lead style belongs to the first cover there actually is, which is
      // not the first album when that album has no usable cover.
      $lead = $covers === [];
      $style = $lead ? $mode['lead_style'] : $mode['style'];
      $cover = $this->cover($album, $style, $lead && $mode['lead_responsive'], $cache);
      if ($cover !== NULL) {
        $covers[] = $cover;
      }
    }
    if ($covers) {
      $covers['#weight'] = 10;
      $build['covers'] = $covers;
    }

    $count = count($albums);
    $build['album_count'] = [
      '#markup' => $this->formatPlural($count, '1 album', '@count albums'),
      '#weight' => 11,
    ];

    // applyTo() would wipe node's cache tags.
    CacheableMetadata::createFromRenderArray($build)->merge($cache)->applyTo($build);
  }

  /**
   * Builds the thumbnail of an album's cover in an image style.
   *
   * The covers repeat what the title and the album count already say, so they
   * are decorative: the templates hide them from assistive technology.
   *
   * @param \Drupal\elaintehtaat\Entity\Album $album
   *   The album.
   * @param string $style
   *   The image style, or the responsive image style when $responsive is set.
   * @param bool $responsive
   *   Whether $style names a responsive image style.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the cacheability of the cover.
   */
  private function cover(Album $album, string $style, bool $responsive, CacheableMetadata $cache): ?array {
    $media = $album->getCover();
    if (!$media instanceof AlbumMedia || !$media->isPublished()) {
      return NULL;
    }
    $file = $media->getThumbnailFile();
    if ($file === NULL) {
      return NULL;
    }

    $cache->addCacheableDependency($media);
    $cache->addCacheableDependency($file);

    $item = $media->getThumbnailItem();
    $width = $item?->get('width')->getValue();
    $height = $item?->get('height')->getValue();

    if ($responsive) {
      return [
        '#theme' => 'responsive_image',
        '#responsive_image_style_id' => $style,
        '#uri' => $file->getFileUri(),
        '#width' => $width,
        '#height' => $height,
        '#attributes' => ['alt' => '', 'loading' => 'lazy'],
      ];
    }

    return [
      '#theme' => 'image_style',
      '#style_name' => $style,
      '#uri' => $file->getFileUri(),
      '#alt' => '',
      '#width' => $width,
      '#height' => $height,
      '#attributes' => ['loading' => 'lazy'],
    ];
  }

}
