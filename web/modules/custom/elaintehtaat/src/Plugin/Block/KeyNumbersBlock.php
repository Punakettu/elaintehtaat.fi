<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows the size of the archive.
 */
#[Block(
  id: 'elaintehtaat_key_numbers',
  admin_label: new TranslatableMarkup('Key numbers'),
  category: new TranslatableMarkup('Eläintehtaat'),
)]
final class KeyNumbersBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['label_display' => '0'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $albums = $this->countPublished('node', 'album');
    $media = $this->countPublished('media');
    $projects = $this->countPublished('node', 'project');

    $cache = new CacheableMetadata();
    $cache->setCacheContexts(['languages:language_interface']);
    $cache->setCacheTags([
      'node_list:album',
      'node_list:project',
      'media_list',
    ]);

    $build = [
      '#type' => 'component',
      '#component' => 'elaintehtaat:key-numbers',
      '#props' => [
        'items' => [
          [
            'value' => $albums,
            'label' => (string) $this->formatPlural($albums, 'album', 'albums'),
          ],
          [
            'value' => $media,
            'label' => (string) $this->formatPlural($media, 'media item', 'media'),
          ],
          [
            'value' => $projects,
            'label' => (string) $this->formatPlural($projects, 'project', 'projects'),
          ],
        ],
      ],
    ];

    $cache->applyTo($build);

    return $build;
  }

  /**
   * Counts published entities, optionally limited to one bundle.
   */
  protected function countPublished(string $entity_type_id, ?string $bundle = NULL): int {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery()
      ->accessCheck(FALSE)
      ->condition($entity_type->getKey('published'), 1);
    if ($bundle !== NULL) {
      $query->condition($entity_type->getKey('bundle'), $bundle);
    }
    return (int) $query->count()->execute();
  }

}
