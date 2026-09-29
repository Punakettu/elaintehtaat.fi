<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\elaintehtaat\Album\AlbumLookup;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Feeds the meta tags of albums, projects and media pages.
 *
 * Metatag only knows the entity of canonical entity routes, so the media page
 * hands it its media item, which picks the media defaults. The defaults then
 * need two things plain field tokens cannot reach:
 * - [node:cover], the media item an album or a project is represented by, for
 *   the share image. A project has no image of its own.
 * - [current-page:album], the album a media page is shown in. A media item may
 *   sit in several albums, so only the route knows which one.
 *
 * Both are typed, so they chain, e.g. [node:cover:thumbnail:og_image:url].
 *
 * Canvas pages carry their own canonical URL, which is relative and, on the
 * front page, points at the page's alias rather than the site root.
 *
 * @see metatag.metatag_defaults.node__album
 * @see metatag.metatag_defaults.node__project
 * @see metatag.metatag_defaults.media
 */
final class MetatagHooks {

  use StringTranslationTrait;

  /**
   * The route of the media page.
   */
  public const string MEDIA_PAGE_ROUTE = 'elaintehtaat.media_page';

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly Token $token,
    private readonly AlbumLookup $albumLookup,
    private readonly PathMatcherInterface $pathMatcher,
  ) {}

  /**
   * Implements hook_metatag_route_entity().
   */
  #[Hook('metatag_route_entity')]
  public function routeEntity(RouteMatchInterface $route_match): ?MediaInterface {
    if ($route_match->getRouteName() !== self::MEDIA_PAGE_ROUTE) {
      return NULL;
    }
    $media = $route_match->getParameter('media');

    return $media instanceof MediaInterface ? $media : NULL;
  }

  /**
   * Implements hook_metatags_alter().
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$metatags, array &$context): void {
    $entity = $context['entity'] ?? NULL;
    if (!$entity instanceof ContentEntityInterface || $entity->getEntityTypeId() !== 'canvas_page') {
      return;
    }
    $front = $this->pathMatcher->isFrontPage();
    // Leave a canonical URL an editor has set by hand alone.
    if (($metatags['canonical_url'] ?? NULL) === '[canvas_page:url]') {
      $metatags['canonical_url'] = $front ? '[site:url]' : '[canvas_page:url:absolute]';
    }
    // The page's empty description would hide the front page default.
    if ($front && $entity->hasField('description') && $entity->get('description')->isEmpty()) {
      $metatags['description'] = '[site:slogan]';
    }
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'tokens' => [
        'node' => [
          'cover' => [
            'name' => $this->t('Cover'),
            'description' => $this->t('The media item an album is represented by (its first published item), or a project (the cover of its newest album).'),
            'type' => 'media',
          ],
        ],
        'current-page' => [
          'album' => [
            'name' => $this->t('Album'),
            'description' => $this->t('The album the media item of a media page is shown in.'),
            'type' => 'node',
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    if ($type === 'node' && ($data['node'] ?? NULL) instanceof NodeInterface) {
      return $this->entityTokens('cover', 'media', $tokens, fn () => $this->cover($data['node'], $bubbleable_metadata), $options, $bubbleable_metadata);
    }
    if ($type === 'current-page') {
      return $this->entityTokens('album', 'node', $tokens, fn () => $this->currentAlbum($bubbleable_metadata), $options, $bubbleable_metadata);
    }

    return [];
  }

  /**
   * Replaces a token standing for an entity, and the tokens chained off it.
   *
   * The token itself becomes the entity label.
   *
   * @param string $name
   *   The name of the token.
   * @param string $entity_type
   *   The token type of the entity.
   * @param array<string, string> $tokens
   *   The tokens to replace, keyed by name.
   * @param callable(): (\Drupal\Core\Entity\EntityInterface|null) $load
   *   Loads the entity; only called when a token asks for it.
   * @param array<string, mixed> $options
   *   The token replacement options.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Collects the cacheability of the replacements.
   *
   * @return array<string, string>
   *   The replacements, keyed by the original token.
   */
  private function entityTokens(string $name, string $entity_type, array $tokens, callable $load, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $chained = $this->token->findWithPrefix($tokens, $name);
    if (!isset($tokens[$name]) && !$chained) {
      return [];
    }
    $entity = $load();
    if ($entity === NULL) {
      return [];
    }

    $replacements = [];
    if (isset($tokens[$name])) {
      $replacements[$tokens[$name]] = (string) $entity->label();
    }
    if ($chained) {
      $replacements += $this->token->generate($entity_type, $chained, [$entity_type => $entity], $options, $bubbleable_metadata);
    }

    return $replacements;
  }

  /**
   * The media item an album or a project is represented by.
   */
  private function cover(NodeInterface $node, BubbleableMetadata $bubbleable_metadata): ?MediaInterface {
    if ($node instanceof Album) {
      return $this->albumCover($node, $bubbleable_metadata);
    }
    if (!$node instanceof Project) {
      return NULL;
    }

    // Saving any album may add it to this project or take it out again.
    $bubbleable_metadata->addCacheTags(['node_list:album']);
    foreach ($this->albumLookup->albumsOfProject($node) as $album) {
      $bubbleable_metadata->addCacheableDependency($album);
      $cover = $this->albumCover($album, $bubbleable_metadata);
      if ($cover !== NULL) {
        return $cover;
      }
    }

    return NULL;
  }

  /**
   * The first published media item of an album.
   */
  private function albumCover(Album $album, BubbleableMetadata $bubbleable_metadata): ?MediaInterface {
    foreach ($album->getMedia() as $media) {
      $bubbleable_metadata->addCacheableDependency($media);
      if ($media->isPublished()) {
        return $media;
      }
    }

    return NULL;
  }

  /**
   * The album of the media page being viewed, if a media page is.
   */
  private function currentAlbum(BubbleableMetadata $bubbleable_metadata): ?Album {
    $bubbleable_metadata->addCacheContexts(['route']);
    if ($this->routeMatch->getRouteName() !== self::MEDIA_PAGE_ROUTE) {
      return NULL;
    }
    $album = $this->routeMatch->getParameter('node');

    return $album instanceof Album ? $album : NULL;
  }

}
