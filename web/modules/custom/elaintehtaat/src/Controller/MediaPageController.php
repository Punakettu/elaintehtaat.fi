<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\AlbumMedia;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Entity\Video;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows one media item of an album full screen.
 *
 * The page is reached at /<project>/<album>/<media id>.
 *
 * @see \Drupal\elaintehtaat\PathProcessor\MediaPagePathProcessor
 */
final class MediaPageController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  /**
   * Responsive image style used for the stage image.
   */
  public const string STAGE_STYLE = 'media_stage';

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
    return AccessResult::allowedIf($node instanceof Album)
      ->addCacheableDependency($node)
      ->andIf($node->access('view', $account, TRUE))
      ->andIf($media->access('view', $account, TRUE));
  }

  /**
   * Page title: the media label in the current language.
   */
  public function title(NodeInterface $node, MediaInterface $media): string {
    return (string) $this->translated($media)->label();
  }

  /**
   * Builds the media page.
   */
  public function build(NodeInterface $node, MediaInterface $media): array {
    $album = $this->translated($node);
    $media = $this->translated($media);
    if (!$album instanceof Album || !$media instanceof AlbumMedia) {
      throw new NotFoundHttpException();
    }

    $cache = new CacheableMetadata();
    $cache->addCacheContexts([
      'languages:language_interface',
      'languages:language_content',
      'user.permissions',
    ]);
    $cache->addCacheableDependency($album);
    $cache->addCacheableDependency($media);

    $items = $album->getViewableMedia($cache);
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

    $project = $album->getProject();
    if ($project !== NULL) {
      $project = $this->translated($project);
      $cache->addCacheableDependency($project);
    }

    $caption = $media->getCaption();
    // The component element rejects empty arrays, so leave empty slots out.
    $slots = array_filter([
      'image' => $this->stage($media, $cache),
      'album_thumbnail' => $this->image($items[0], self::THUMBNAIL_STYLE, $cache),
      'caption' => $caption?->view(['label' => 'hidden', 'type' => 'text_default']) ?? [],
    ]);

    $taken = $media->getDate();
    $date = $taken === NULL ? '' : $this->dateFormatter->format($taken->getTimestamp(), 'custom', 'j.n.Y');

    $author = $media->getAuthor();
    $args = ['@author' => $author, '@organisation' => self::ORGANISATION];
    if ($media instanceof Video) {
      $type_label = $this->t('Video');
      $author_label = $this->t('Filmed by');
      $attribution = $author === '' ? $this->t('Video: @organisation', $args) : $this->t('Video: @author / @organisation', $args);
    }
    else {
      $type_label = $this->t('Photograph');
      $author_label = $this->t('Photographer');
      $attribution = $author === '' ? $this->t('Photo: @organisation', $args) : $this->t('Photo: @author / @organisation', $args);
    }

    $licence_name = '';
    $licence_url = '';
    $licence = $media->getLicence();
    if ($licence !== NULL) {
      $licence = $this->translated($licence);
      $cache->addCacheableDependency($licence);
      $licence_name = (string) $licence->label();
      $licence_url = $licence->getUrl()?->toString() ?? '';
    }

    $download_url = '';
    $download_filename = '';
    // Videos stream from Bunny and have no file to download.
    $file = $media instanceof Image ? $media->getSourceFile() : NULL;
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
        'type_label' => (string) $type_label,
        'date' => $date,
        'album_title' => (string) $album->label(),
        'album_url' => $album->toUrl()->toString(),
        'project_title' => $project === NULL ? '' : (string) $project->label(),
        'author' => $author,
        'author_label' => (string) $author_label,
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
   * URL of the media page of one album item.
   */
  private function mediaUrl(NodeInterface $album, MediaInterface $media): string {
    return Url::fromRoute('elaintehtaat.media_page', [
      'node' => $album->id(),
      'media' => $media->id(),
    ])->toString();
  }

  /**
   * Builds the stage: the image in the stage style, or the video player.
   */
  private function stage(AlbumMedia $media, CacheableMetadata $cache): array {
    if ($media instanceof Video) {
      // The source field shows as the Bunny player, configured on the
      // media's default display.
      return $media->getSourceField()?->view('default') ?? [];
    }

    return $this->image($media, self::STAGE_STYLE, $cache, ['loading' => 'eager', 'fetchpriority' => 'high'], TRUE);
  }

  /**
   * Renders the media's image in an image style or a responsive image style.
   *
   * An image shows its source image, other media their thumbnail.
   */
  private function image(MediaInterface $media, string $style_name, CacheableMetadata $cache, array $attributes = [], bool $responsive = FALSE): array {
    if (!$media instanceof AlbumMedia) {
      return [];
    }
    [$item, $file] = $media instanceof Image
      ? [$media->getSourceItem(), $media->getSourceFile()]
      : [$media->getThumbnailItem(), $media->getThumbnailFile()];
    if (!$item instanceof FieldItemInterface || $file === NULL) {
      return [];
    }
    $cache->addCacheableDependency($file);
    $style = $this->entityTypeManager->getStorage($responsive ? 'responsive_image_style' : 'image_style')->load($style_name);
    if ($style !== NULL) {
      $cache->addCacheableDependency($style);
    }
    $values = $item->getValue();

    if ($responsive) {
      // The responsive image reads its alt and title off the attributes.
      $attributes['alt'] = $values['alt'] ?? '';
      if (!empty($values['title'])) {
        $attributes['title'] = $values['title'];
      }

      return [
        '#theme' => 'responsive_image',
        '#responsive_image_style_id' => $style_name,
        '#uri' => $file->getFileUri(),
        '#width' => $values['width'] ?? NULL,
        '#height' => $values['height'] ?? NULL,
        '#attributes' => $attributes,
      ];
    }

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
   * The entity as it reads in the language the page is shown in.
   *
   * @param T $entity
   *   The entity to translate.
   *
   * @return T
   *   The translation, or the entity itself when it has none.
   *
   * @template T of \Drupal\Core\Entity\EntityInterface
   */
  private function translated(EntityInterface $entity): EntityInterface {
    /** @var T $translation */
    $translation = $this->entityRepository->getTranslationFromContext($entity);

    return $translation;
  }

}
