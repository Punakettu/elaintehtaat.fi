<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\elaintehtaat\Plugin\search_api\processor\AlbumData;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\MediaInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the album data the processor adds to indexed media items.
 *
 * @see \Drupal\elaintehtaat\Plugin\search_api\processor\AlbumData
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class AlbumDataProcessorTest extends KernelTestBase {

  use ContentModelTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [...self::CONTENT_MODEL_MODULES, 'language', 'content_translation'];

  /**
   * The index the items are built for.
   */
  private IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContentModel();
    $this->installConfig(['language']);
    // Saving a node translation rebuilds the node access records.
    $this->installSchema('node', ['node_access']);
    // Saving an index queues a tracking task.
    $this->installEntitySchema('search_api_task');
    ConfigurableLanguage::createFromLangcode('fi')->save();

    $this->index = $this->createIndex();
  }

  /**
   * An item is indexed with the species and use of every album holding it.
   */
  public function testValuesOfEveryAlbumAreIndexed(): void {
    $pig = $this->createTerm(self::SPECIES_VOCABULARY, 'Pig');
    $fox = $this->createTerm(self::SPECIES_VOCABULARY, 'Fox');
    $meat = $this->createTerm(self::USE_VOCABULARY, 'Meat');

    $media = $this->createImageMedia();
    $this->createAlbum([
      'title' => 'Pig farm',
      'field_album_media' => [$media],
      'field_species' => [$pig],
      'field_use' => [$meat],
      'field_date' => '2024-05-14',
    ]);
    $this->createAlbum([
      'title' => 'Fox farm',
      'field_album_media' => [$media],
      'field_species' => [$fox],
      'field_date' => '2023-09-02',
    ]);

    $item = $this->itemFor($media);

    $this->assertEqualsCanonicalizing(
      [(int) $pig->id(), (int) $fox->id()],
      $item->getField('album_species')->getValues(),
    );
    $this->assertSame([(int) $meat->id()], $item->getField('album_use')->getValues());
    $this->assertEqualsCanonicalizing(
      ['Pig farm', 'Fox farm'],
      array_map('strval', $item->getField('album_title')->getValues()),
    );
    $this->assertCount(2, $item->getField('album_date')->getValues());
  }

  /**
   * An unpublished album contributes nothing to its items.
   */
  public function testUnpublishedAlbumIsLeftOut(): void {
    $pig = $this->createTerm(self::SPECIES_VOCABULARY, 'Pig');

    $media = $this->createImageMedia();
    $this->createAlbum([
      'title' => 'Draft',
      'status' => 0,
      'field_album_media' => [$media],
      'field_species' => [$pig],
    ]);

    $item = $this->itemFor($media);

    $this->assertSame([], $item->getField('album_species')->getValues());
    $this->assertSame([], $item->getField('album_title')->getValues());
  }

  /**
   * Each language is indexed with the album as it reads in that language.
   */
  public function testAlbumIsReadInTheLanguageOfTheItem(): void {
    $pig = $this->createTerm(self::SPECIES_VOCABULARY, 'Pig');

    $media = $this->createImageMedia();
    $album = $this->createAlbum([
      'title' => 'Pig farm',
      'field_album_media' => [$media],
      'field_species' => [$pig],
    ]);
    $album->addTranslation('fi', ['title' => 'Lihasikala'])->save();

    // The species field is not translatable, so both languages share its
    // value; only the title differs.
    $this->assertSame(
      ['Pig farm'],
      array_map('strval', $this->itemFor($media, 'en')->getField('album_title')->getValues()),
    );
    $this->assertSame(
      ['Lihasikala'],
      array_map('strval', $this->itemFor($media, 'fi')->getField('album_title')->getValues()),
    );
    $this->assertSame(
      [(int) $pig->id()],
      $this->itemFor($media, 'fi')->getField('album_species')->getValues(),
    );
  }

  /**
   * An item in no album gets no album values, and does not blow up.
   */
  public function testItemWithoutAlbum(): void {
    $item = $this->itemFor($this->createImageMedia());

    $this->assertSame([], $item->getField('album_species')->getValues());
  }

  /**
   * Builds an index item for a media entity.
   *
   * Reading the fields of a fresh item is what runs the processor over it, so
   * nothing has to call addFieldValues() here.
   *
   * @see \Drupal\search_api\Item\Item::getFields()
   */
  private function itemFor(MediaInterface $media, string $langcode = 'en'): ItemInterface {
    $id = Utility::createCombinedId('entity:media', $media->id() . ':' . $langcode);
    $item = $this->container->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $media->getTypedData(), $id);
    // The language of the item decides which album translation is read.
    $item->setLanguage($langcode);
    $item->getFields();

    return $item;
  }

  /**
   * An index over image media with all of the album fields the site uses.
   */
  private function createIndex(): IndexInterface {
    $fields = [
      'album_species' => ['property_path' => 'album:field_species', 'type' => 'integer'],
      'album_use' => ['property_path' => 'album:field_use', 'type' => 'integer'],
      'album_date' => ['property_path' => 'album:field_date', 'type' => 'date'],
      'album_title' => ['property_path' => 'album:title', 'type' => 'text'],
    ];
    $field_settings = [];
    foreach ($fields as $id => $field) {
      $field_settings[$id] = $field + [
        'label' => $id,
        'datasource_id' => 'entity:media',
      ];
    }

    $index = Index::create([
      'id' => 'test_media',
      'name' => 'Test media',
      'status' => TRUE,
      'field_settings' => $field_settings,
      'datasource_settings' => [
        'entity:media' => [
          'bundles' => ['default' => FALSE, 'selected' => ['image']],
          'languages' => ['default' => TRUE, 'selected' => []],
        ],
      ],
      'processor_settings' => [AlbumData::PLUGIN_ID => []],
      'tracker_settings' => ['default' => []],
    ]);
    $index->save();

    return $index;
  }

}
