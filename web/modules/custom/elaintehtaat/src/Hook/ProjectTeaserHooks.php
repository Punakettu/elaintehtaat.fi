<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\node\NodeInterface;

/**
 * Summarises the albums of a project on its teaser.
 *
 * @see templates/content/node--project--teaser.html.twig
 */
final class ProjectTeaserHooks {

  use StringTranslationTrait;

  /**
   * The view mode that gets the album summary.
   */
  public const string VIEW_MODE = 'teaser';

  /**
   * How many album covers the teaser shows.
   */
  public const int COVER_LIMIT = 4;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($view_mode !== self::VIEW_MODE || !$node instanceof Project) {
      return;
    }

    $cache = new CacheableMetadata();
    // Saving any album may add it to this project or take it out again.
    $cache->addCacheTags(['node_list:album']);

    $albums = $this->loadAlbums($node);
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
    foreach (array_slice($albums, 0, self::COVER_LIMIT) as $album) {
      $cover = $this->cover($album, $cache);
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
   * Loads the published albums of a project, newest first.
   *
   * @return list<\Drupal\elaintehtaat\Entity\Album>
   *   The albums.
   */
  private function loadAlbums(Project $project): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', Album::BUNDLE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_project.target_id', $project->id())
      ->sort('field_date', 'DESC')
      ->sort('created', 'DESC')
      ->execute();
    if (!$ids) {
      return [];
    }

    // loadMultiple() ignores the order the IDs come in, so restore it.
    $albums = $storage->loadMultiple($ids);
    $ordered = [];
    foreach ($ids as $id) {
      if (($albums[$id] ?? NULL) instanceof Album) {
        $ordered[] = $albums[$id];
      }
    }

    return $ordered;
  }

  /**
   * Builds the thumbnail of an album's cover.
   *
   * The covers repeat what the title and the album count already say, so they
   * are decorative: the template hides them from assistive technology.
   */
  private function cover(Album $album, CacheableMetadata $cache): ?array {
    $media = $album->getCover();
    if (!$media instanceof Image || !$media->isPublished()) {
      return NULL;
    }
    $file = $media->getThumbnailFile();
    if ($file === NULL) {
      return NULL;
    }

    $cache->addCacheableDependency($media);
    $cache->addCacheableDependency($file);

    return [
      '#theme' => 'image_style',
      '#style_name' => 'thumbnail',
      '#uri' => $file->getFileUri(),
      '#alt' => '',
      '#attributes' => ['loading' => 'lazy'],
    ];
  }

}
