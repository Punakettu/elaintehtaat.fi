<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\elaintehtaat\Album\AlbumLookup;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\media\MediaInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets media items be indexed by the fields of the albums holding them.
 *
 * Albums reference their media and not the other way round, so a media item
 * cannot reach the species, use or date of its album through any field of its
 * own. This processor adds the albums as one "album" property, which the index
 * then drills into with property paths such as "album:field_species".
 *
 * Only published albums are added: the values feed the public browse page.
 * That is the reason for not using search_api's own "reverse_entity_references"
 * processor, which adds every referencing entity regardless of bundle and
 * status.
 *
 * The property is an entity property rather than a set of flat scalar ones
 * because facets needs it: TranslateEntityProcessor only accepts a facet whose
 * field resolves to an entity reference, and it is what turns the indexed term
 * IDs into labels in the language the page is read in.
 *
 * Nothing re-indexes a media item when its album changes, so the index would
 * go stale without the tracking hooks.
 *
 * @see \Drupal\facets\Plugin\facets\processor\TranslateEntityProcessor::supportsFacet()
 * @see \Drupal\elaintehtaat\Hook\AlbumIndexHooks
 */
#[SearchApiProcessor(
  id: 'elaintehtaat_album_data',
  label: new TranslatableMarkup('Album data'),
  description: new TranslatableMarkup('Lets media items be indexed by the fields of the published albums holding them.'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class AlbumData extends ProcessorPluginBase {

  /**
   * The ID of this processor.
   */
  public const string PLUGIN_ID = 'elaintehtaat_album_data';

  /**
   * The name of the property the albums are added as.
   */
  public const string PROPERTY = 'album';

  /**
   * The entity type the processor adds the albums to.
   */
  public const string DATASOURCE_ENTITY_TYPE = 'media';

  /**
   * Finds the albums of a media item.
   *
   * Not private: DependencySerializationTrait, which plugins use, cannot
   * restore private properties.
   */
  protected AlbumLookup $albumLookup;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->albumLookup = $container->get(AlbumLookup::class);

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === self::DATASOURCE_ENTITY_TYPE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource?->getEntityTypeId() !== self::DATASOURCE_ENTITY_TYPE) {
      return [];
    }

    $property = new EntityProcessorProperty([
      'label' => $this->t('Album'),
      'description' => $this->t('The published albums this media item belongs to.'),
      'type' => 'entity:node',
      'processor_id' => $this->getPluginId(),
      // An item may belong to several albums.
      'is_list' => TRUE,
    ]);
    $property->setEntityTypeId('node');
    // Keeps the field picker to album fields.
    $property->setBundles([Album::BUNDLE]);

    return [self::PROPERTY => $property];
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    try {
      $media = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }
    if (!$media instanceof MediaInterface) {
      return;
    }

    $to_extract = $this->albumFields($item);
    if ($to_extract === []) {
      return;
    }

    $langcode = $item->getLanguage();
    $albums = $this->albumLookup->albumsOf($media, $langcode);

    // A field on the album itself, with nothing nested under it, gets the
    // album entities, the way Views fields want them.
    if (isset($to_extract[''])) {
      foreach ($to_extract[''] as $field) {
        $field->setValues($albums);
      }
      unset($to_extract['']);
    }

    // Values of every album accumulate on the same fields, so an item in two
    // albums is found by the species of either. Repeated values do no harm:
    // the database backend stores each distinct value once.
    foreach ($albums as $album) {
      $this->getFieldsHelper()->extractFields($album->getTypedData(), $to_extract, $langcode);
    }
  }

  /**
   * The fields of the item that read from the album property.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item being indexed.
   *
   * @return array<string, list<\Drupal\search_api\Item\FieldInterface>>
   *   The fields, keyed by the property path below "album" they read, as
   *   extractFields() takes them.
   */
  private function albumFields(ItemInterface $item): array {
    $fields = [];
    foreach ($item->getFields() as $field) {
      if ($field->getDatasourceId() !== $item->getDatasourceId()) {
        continue;
      }
      // With the last argument FALSE the first property is separated off, and
      // the rest of the path may be NULL: that is a field on the album itself.
      [$direct, $nested] = Utility::splitPropertyPath($field->getPropertyPath(), FALSE);
      if ($direct === self::PROPERTY) {
        $fields[(string) $nested][] = $field;
      }
    }

    return $fields;
  }

}
