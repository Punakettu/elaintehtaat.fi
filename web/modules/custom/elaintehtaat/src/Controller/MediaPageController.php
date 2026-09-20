<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\link\LinkItemInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows one media item of an album full screen.
 *
 * The page is reached at /<project>/<album>/<media id>, see
 * \Drupal\elaintehtaat\PathProcessor\MediaPagePathProcessor.
 */
final class MediaPageController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  /**
   * Image style used for the stage image.
   */
  public const string IMAGE_STYLE = 'media_page';

  /**
   * Image style used for the album thumbnail in the sidebar.
   */
  public const string THUMBNAIL_STYLE = 'thumbnail';

  /**
   * Name shown in the attribution line.
   */
  public const string ORGANISATION = 'Eläintehtaat';

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Checks access: the node must be a viewable album and the media viewable.
   */
  public function access(NodeInterface $node, MediaInterface $media, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($node->bundle() === 'album')
      ->addCacheableDependency($node)
      ->andIf($node->access('view', $account, TRUE))
      ->andIf($media->access('view', $account, TRUE));
  }

  /**
   * Page title: the media label in the current language.
   */
  public function title(NodeInterface $node, MediaInterface $media): string {
    $media = $this->entityRepository->getTranslationFromContext($media);
    return (string) $media->label();
  }

  /**
   * Builds the media page.
   */
  public function build(NodeInterface $node, MediaInterface $media): array {
    $album = $this->entityRepository->getTranslationFromContext($node);
    $media = $this->entityRepository->getTranslationFromContext($media);

    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['languages:language_interface', 'languages:language_content', 'user.permissions']);
    $cache->addCacheableDependency($album);
    $cache->addCacheableDependency($media);

    $items = $this->albumMedia($album, $cache);
    $index = NULL;
    foreach ($items as $delta => $item) {
      if ($item->id() === $media->id()) {
        $index = $delta;
        break;
      }
    }
    if ($index === NULL) {
      throw new NotFoundHttpException();
    }

    $total = count($items);
    $prev_url = '';
    $next_url = '';
    if ($total > 1) {
      $prev_url = $this->mediaUrl($album, $items[($index - 1 + $total) % $total]);
      $next_url = $this->mediaUrl($album, $items[($index + 1) % $total]);
    }

    $project = $album->hasField('field_project') ? $album->get('field_project')->entity : NULL;
    if ($project instanceof NodeInterface) {
      $project = $this->entityRepository->getTranslationFromContext($project);
      $cache->addCacheableDependency($project);
    }

    $slots = [
      'image' => $this->image($media, self::IMAGE_STYLE, $cache, ['loading' => 'eager', 'fetchpriority' => 'high']),
      'album_thumbnail' => $this->image($items[0], self::THUMBNAIL_STYLE, $cache),
      'caption' => [],
    ];
    if ($media->hasField('field_caption') && !$media->get('field_caption')->isEmpty()) {
      $slots['caption'] = $media->get('field_caption')->view(['label' => 'hidden', 'type' => 'text_default']);
    }

    $date = '';
    if ($media->hasField('field_date') && !$media->get('field_date')->isEmpty()) {
      $date_value = $media->get('field_date')->first()?->get('date')->getValue();
      if ($date_value !== NULL) {
        $date = $this->dateFormatter->format($date_value->getTimestamp(), 'custom', 'j.n.Y');
      }
    }

    $author = $media->hasField('field_author') ? (string) ($media->get('field_author')->value ?? '') : '';
    $attribution = $author === ''
      ? $this->t('Photo: @organisation', ['@organisation' => self::ORGANISATION])
      : $this->t('Photo: @author / @organisation', ['@author' => $author, '@organisation' => self::ORGANISATION]);

    $licence_name = '';
    $licence_url = '';
    $term = $media->hasField('field_licence') ? $media->get('field_licence')->entity : NULL;
    if ($term instanceof TermInterface) {
      $term = $this->entityRepository->getTranslationFromContext($term);
      $cache->addCacheableDependency($term);
      $licence_name = (string) $term->label();
      $link = $term->hasField('field_licence_link') ? $term->get('field_licence_link')->first() : NULL;
      if ($link instanceof LinkItemInterface && !$link->isEmpty()) {
        $licence_url = $link->getUrl()->toString();
      }
    }

    $download_url = '';
    $download_filename = '';
    $file = $this->sourceFile($media);
    if ($file !== NULL) {
      $download_url = $this->fileUrlGenerator->generateString($file->getFileUri());
      $download_filename = (string) $file->getFilename();
    }

    $build = [
      '#type' => 'component',
      '#component' => 'elaintehtaat:media-page',
      '#props' => [
        'close_url' => $album->toUrl()->toString(),
        'prev_url' => $prev_url,
        'next_url' => $next_url,
        'position' => $index + 1,
        'total' => $total,
        'type_label' => (string) $this->t('Photograph'),
        'date' => $date,
        'album_title' => (string) $album->label(),
        'album_url' => $album->toUrl()->toString(),
        'project_title' => $project instanceof NodeInterface ? (string) $project->label() : '',
        'author' => $author,
        'licence_name' => $licence_name,
        'licence_url' => $licence_url,
        'attribution' => (string) $attribution,
        'download_url' => $download_url,
        'download_filename' => $download_filename,
      ],
      '#slots' => $slots,
    ];
    $cache->applyTo($build);

    return $build;
  }

  /**
   * The album's media the current user may view, in album order.
   *
   * @return list<\Drupal\media\MediaInterface>
   *   The media items, keyed by their position in the album.
   */
  private function albumMedia(NodeInterface $album, CacheableMetadata $cache): array {
    if (!$album->hasField('field_album_media')) {
      return [];
    }
    $items = [];
    foreach ($album->get('field_album_media')->referencedEntities() as $item) {
      if (!$item instanceof MediaInterface) {
        continue;
      }
      $access = $item->access('view', NULL, TRUE);
      $cache->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $items[] = $item;
      }
    }
    return $items;
  }

  /**
   * URL of the media page of one album item.
   */
  private function mediaUrl(NodeInterface $album, MediaInterface $media): string {
    return Url::fromRoute('elaintehtaat.media_page', [
      'node' => $album->id(),
      'media' => $media->id(),
    ])->toString();
  }

  /**
   * Renders the media's source image in an image style.
   */
  private function image(MediaInterface $media, string $style_name, CacheableMetadata $cache, array $attributes = []): array {
    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? NULL;
    if ($source_field === NULL || !$media->hasField($source_field) || $media->get($source_field)->isEmpty()) {
      return [];
    }
    $item = $media->get($source_field)->first();
    $file = $item?->get('entity')->getValue();
    if (!$file instanceof FileInterface) {
      return [];
    }
    $cache->addCacheableDependency($file);
    $style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
    if ($style !== NULL) {
      $cache->addCacheableDependency($style);
    }
    $values = $item->getValue();

    return [
      '#theme' => 'image_style',
      '#style_name' => $style_name,
      '#uri' => $file->getFileUri(),
      '#alt' => $values['alt'] ?? '',
      '#title' => !empty($values['title']) ? $values['title'] : NULL,
      '#width' => $values['width'] ?? NULL,
      '#height' => $values['height'] ?? NULL,
      '#attributes' => $attributes,
    ];
  }

  /**
   * The file behind the media's source field, if any.
   */
  private function sourceFile(MediaInterface $media): ?FileInterface {
    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? NULL;
    if ($source_field === NULL || !$media->hasField($source_field)) {
      return NULL;
    }
    $file = $media->get($source_field)->first()?->get('entity')->getValue();
    return $file instanceof FileInterface ? $file : NULL;
  }

}
