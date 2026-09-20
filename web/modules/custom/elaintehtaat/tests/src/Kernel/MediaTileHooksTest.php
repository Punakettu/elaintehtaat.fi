<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the album link added to media tiles.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class MediaTileHooksTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'file',
    'image',
    'node',
    'media',
    'media_test_source',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('media');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system']);

    $this->createContentType(['type' => 'album']);
    $this->createMediaType('test', ['id' => 'image']);

    // Test media items have no thumbnail file, so the image formatter cannot
    // render them. Only the hook output matters here.
    EntityViewMode::create(['id' => 'media.tile', 'label' => 'Tile', 'targetEntityType' => 'media'])->save();
    $display_repository = $this->container->get(EntityDisplayRepositoryInterface::class);
    foreach (['default', 'tile'] as $view_mode) {
      $display_repository->getViewDisplay('media', 'image', $view_mode)
        ->setStatus(TRUE)
        ->removeComponent('thumbnail')
        ->save();
    }

    FieldStorageConfig::create([
      'field_name' => 'field_album_media',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_album_media',
      'entity_type' => 'node',
      'bundle' => 'album',
    ])->save();
  }

  /**
   * A tile links to the published album containing the media item.
   */
  public function testTileLinksToAlbum(): void {
    $media = $this->createMedia();
    $album = Node::create([
      'type' => 'album',
      'title' => 'Broiler house',
      'status' => 1,
      'field_album_media' => [$media],
    ]);
    $album->save();

    $build = $this->buildTile($media);

    $this->assertSame('Broiler house', $build['album']['#title']);
    $this->assertSame($album->id(), $build['album']['#url']->getRouteParameters()['node']);
    $this->assertContains('node_list:album', $build['#cache']['tags']);
    $this->assertContains('node:' . $album->id(), $build['#cache']['tags']);
  }

  /**
   * Items outside any published album get no link but stay invalidatable.
   */
  public function testTileWithoutAlbum(): void {
    $orphan = $this->createMedia();
    $drafted = $this->createMedia();
    Node::create([
      'type' => 'album',
      'title' => 'Draft',
      'status' => 0,
      'field_album_media' => [$drafted],
    ])->save();

    foreach ([$orphan, $drafted] as $media) {
      $build = $this->buildTile($media);
      $this->assertArrayNotHasKey('album', $build);
      $this->assertContains('node_list:album', $build['#cache']['tags']);
    }
  }

  /**
   * Creates a published media item.
   */
  private function createMedia(): MediaInterface {
    $media = Media::create(['bundle' => 'image', 'name' => 'Frame', 'status' => 1]);
    $media->save();
    return $media;
  }

  /**
   * Builds the render array of a media item, running hook_media_view().
   */
  private function buildTile(MediaInterface $media, string $view_mode = 'tile'): array {
    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder('media');
    $this->assertInstanceOf(EntityViewBuilder::class, $view_builder);
    return $view_builder->build($view_builder->view($media, $view_mode));
  }

}
