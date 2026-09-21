<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\elaintehtaat\Plugin\search_api\processor\AlbumData;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that editing an album marks its media for re-indexing.
 *
 * @see \Drupal\elaintehtaat\Hook\AlbumIndexHooks
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class AlbumIndexHooksTest extends KernelTestBase {

  use ContentModelTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [...self::CONTENT_MODEL_MODULES, 'search_api_db'];

  /**
   * The index that holds album data.
   */
  private IndexInterface $index;

  /**
   * An index over the same media that does not hold album data.
   */
  private IndexInterface $plainIndex;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContentModel();
    $this->installEntitySchema('search_api_task');
    // Saving or deleting a node rebuilds the node access records.
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    // The datasource reads tracking_page_size off it when it tracks items.
    $this->installConfig(['search_api']);

    // An index with no enabled server disables itself on save.
    Server::create([
      'id' => 'test_server',
      'name' => 'Test server',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default'],
    ])->save();

    $this->index = $this->createIndex('album_media', [AlbumData::PLUGIN_ID => []]);
    $this->plainIndex = $this->createIndex('plain_media', []);
  }

  /**
   * Creating an album marks the items it holds as changed.
   */
  public function testInsertMarksTheAlbumsMedia(): void {
    $media = $this->createImageMedia();
    $other = $this->createImageMedia('Other');
    $this->indexEverything();

    $this->createAlbum(['field_album_media' => [$media]]);

    $this->assertChanged([$media], [$other]);
  }

  /**
   * Editing an album marks its items, including the ones taken out of it.
   */
  public function testUpdateMarksAddedAndRemovedMedia(): void {
    $kept = $this->createImageMedia('Kept');
    $removed = $this->createImageMedia('Removed');
    $added = $this->createImageMedia('Added');
    $album = $this->createAlbum(['field_album_media' => [$kept, $removed]]);
    $this->indexEverything();

    $album->set('field_album_media', [$kept, $added]);
    $album->save();

    $this->assertChanged([$kept, $removed, $added], []);
  }

  /**
   * Deleting an album marks every item it held.
   */
  public function testDeleteMarksTheAlbumsMedia(): void {
    $media = $this->createImageMedia();
    $other = $this->createImageMedia('Other');
    $album = $this->createAlbum(['field_album_media' => [$media]]);
    $this->indexEverything();

    $album->delete();

    $this->assertChanged([$media], [$other]);
  }

  /**
   * Saving a node that is not an album marks nothing.
   */
  public function testOtherNodeTypesAreIgnored(): void {
    $media = $this->createImageMedia();
    $this->createAlbum(['field_album_media' => [$media]]);
    $this->indexEverything();

    $this->createProject();

    $this->assertChanged([], [$media]);
  }

  /**
   * An index without the processor is left alone.
   */
  public function testIndexWithoutTheProcessorIsLeftAlone(): void {
    $media = $this->createImageMedia();
    $this->indexEverything();

    $this->createAlbum(['field_album_media' => [$media]]);

    $this->assertSame(0, $this->plainIndex->getTrackerInstance()->getRemainingItemsCount());
  }

  /**
   * Asserts which items of the album index are waiting to be re-indexed.
   *
   * @param list<\Drupal\media\MediaInterface> $changed
   *   The items that must be marked as changed.
   * @param list<\Drupal\media\MediaInterface> $unchanged
   *   The items that must not be.
   */
  private function assertChanged(array $changed, array $unchanged): void {
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();

    foreach ($changed as $media) {
      $this->assertContains('entity:media/' . $media->id() . ':en', $remaining, (string) $media->label());
    }
    foreach ($unchanged as $media) {
      $this->assertNotContains('entity:media/' . $media->id() . ':en', $remaining, (string) $media->label());
    }
    $this->assertCount(count($changed), $remaining);
  }

  /**
   * Marks every tracked item of both indexes as indexed.
   */
  private function indexEverything(): void {
    foreach ([$this->index, $this->plainIndex] as $index) {
      $tracker = $index->getTrackerInstance();
      $remaining = $tracker->getRemainingItems();
      if ($remaining) {
        $tracker->trackItemsIndexed($remaining);
      }
    }
  }

  /**
   * An index over image media, with the given processors enabled.
   */
  private function createIndex(string $id, array $processors): IndexInterface {
    $index = Index::create([
      'id' => $id,
      'name' => $id,
      'status' => TRUE,
      'field_settings' => [
        'album_species' => [
          'label' => 'Species',
          'datasource_id' => 'entity:media',
          'property_path' => 'album:field_species',
          'type' => 'integer',
        ],
      ],
      'datasource_settings' => [
        'entity:media' => [
          'bundles' => ['default' => FALSE, 'selected' => ['image']],
          'languages' => ['default' => TRUE, 'selected' => []],
        ],
      ],
      'processor_settings' => $processors,
      'tracker_settings' => ['default' => []],
      'server' => 'test_server',
      // The test asserts what the hooks track, not what reaches the server.
      'options' => ['index_directly' => FALSE],
    ]);
    $index->save();

    return $index;
  }

}
