<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\node\NodeInterface;

/**
 * Gathers the metadata of an album onto a card of its own.
 *
 * The date, species, use, project and licence of an album are small facts that
 * read as a block rather than as separate fields, so they are taken off the
 * album page as fields and put back as one card between the description and
 * the media grid. The card is a display extra field, so where it sits stays a
 * matter of the view display.
 *
 * The reference design has a location and a photographer on the card as well;
 * neither has a field on the album yet.
 *
 * @see https://punakettu.github.io/elaintehtaat.fi/
 * @see \Drupal\elaintehtaat\Entity\Album
 */
final class AlbumMetaHooks {

  use StringTranslationTrait;

  /**
   * The view mode that gets the card.
   */
  public const string VIEW_MODE = 'full';

  /**
   * The name of the extra field the card is rendered as.
   */
  public const string EXTRA_FIELD = 'album_meta';

  /**
   * The date format the album date is written in.
   */
  public const string DATE_FORMAT = 'medium';

  public function __construct(
    private readonly DateFormatterInterface $dateFormatter,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    return [
      'node' => [
        Album::BUNDLE => [
          'display' => [
            self::EXTRA_FIELD => [
              'label' => $this->t('Metadata card'),
              'description' => $this->t('The date, species, use, project and licence of the album, as one card.'),
              // Between the description and the media grid.
              'weight' => 102,
              'visible' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($view_mode !== self::VIEW_MODE || !$node instanceof Album) {
      return;
    }
    if ($display->getComponent(self::EXTRA_FIELD) === NULL) {
      return;
    }

    $cache = new CacheableMetadata();
    $rows = $this->rows($node, $cache);
    if ($rows === []) {
      return;
    }

    $build[self::EXTRA_FIELD] = [
      '#type' => 'component',
      '#component' => 'elaintehtaat:meta-card',
      '#props' => ['rows' => $rows],
    ];

    // Merge instead of applyTo(): the view builder has already put the node's
    // own contexts and tags on $build, and applyTo() would wipe them.
    CacheableMetadata::createFromRenderArray($build)->merge($cache)->applyTo($build);
  }

  /**
   * The rows of the card, in the order the reference design has them.
   *
   * Rows without a value are left out.
   *
   * @return list<array<string, mixed>>
   *   The rows, as the meta-card component takes them.
   */
  private function rows(Album $album, CacheableMetadata $cache): array {
    $rows = [];

    $date = $album->getDate();
    if ($date !== NULL) {
      $rows[] = [
        'label' => (string) $this->t('Date'),
        'items' => [['text' => $this->dateFormatter->format($date->getTimestamp(), self::DATE_FORMAT)]],
      ];
    }

    $species = $this->termItems($album->getSpecies(), $cache);
    if ($species !== []) {
      $rows[] = [
        'label' => (string) $this->t('Species'),
        'items' => $species,
      ];
    }

    $use = $this->termItems($album->getUse(), $cache);
    if ($use !== []) {
      $rows[] = [
        'label' => (string) $this->t('Use'),
        'items' => $use,
      ];
    }

    $project = $album->getProject();
    if ($project !== NULL) {
      $project = $this->translated($project, $cache);
      $items = [[
        'text' => (string) $project->label(),
        'url' => $project->toUrl()->toString(),
      ],
      ];
      // The album dates the project the way the album card does.
      $year = $album->getYear();
      if ($year !== NULL) {
        $items[] = ['text' => $year];
      }
      $rows[] = [
        'label' => (string) $this->t('Project'),
        'items' => $items,
        'separator' => ' · ',
      ];
    }

    $licence = $album->getLicence();
    if ($licence !== NULL) {
      $licence = $this->translated($licence, $cache);
      $item = ['text' => (string) $licence->label(), 'chip' => TRUE];
      $url = $licence->getUrl();
      if ($url !== NULL) {
        $item['url'] = $url->toString();
        $item['rel'] = 'license';
      }
      $rows[] = [
        'label' => (string) $this->t('License'),
        'items' => [$item],
      ];
    }

    return $rows;
  }

  /**
   * Turns terms into card items linking to them.
   *
   * @param list<\Drupal\taxonomy\TermInterface> $terms
   *   The terms.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the cacheability of the terms.
   *
   * @return list<array<string, string>>
   *   The items.
   */
  private function termItems(array $terms, CacheableMetadata $cache): array {
    $items = [];
    foreach ($terms as $term) {
      $term = $this->translated($term, $cache);
      $items[] = [
        'text' => (string) $term->label(),
        'url' => $term->toUrl()->toString(),
      ];
    }

    return $items;
  }

  /**
   * The translation of an entity to show, counted as a cache dependency.
   *
   * @param T $entity
   *   The entity.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the cacheability of the translation.
   *
   * @return T
   *   Its translation in the current context.
   *
   * @template T of \Drupal\Core\Entity\EntityInterface
   */
  private function translated(EntityInterface $entity, CacheableMetadata $cache): EntityInterface {
    $translation = $this->entityRepository->getTranslationFromContext($entity);
    $cache->addCacheableDependency($translation);

    return $translation;
  }

}
