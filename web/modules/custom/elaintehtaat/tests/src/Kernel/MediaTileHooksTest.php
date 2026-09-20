<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\media\MediaInterface;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the album link added to media tiles.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class MediaTileHooksTest extends KernelTestBase {

  use ContentModelTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = self::CONTENT_MODEL_MODULES;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContentModel();

    EntityViewMode::create(['id' => 'media.tile', 'label' => 'Tile', 'targetEntityType' => 'media'])->save();
    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('media', 'image', 'tile')
      ->setStatus(TRUE)
      ->save();
  }

  /**
   * A tile links to the published album containing the media item.
   */
  public function testTileLinksToAlbum(): void {
    $media = $this->createImageMedia();
    $album = $this->createAlbum([
      'title' => 'Broiler house',
      'field_album_media' => [$media],
    ]);

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
    $orphan = $this->createImageMedia();
    $drafted = $this->createImageMedia();
    $this->createAlbum([
      'title' => 'Draft',
      'status' => 0,
      'field_album_media' => [$drafted],
    ]);

    foreach ([$orphan, $drafted] as $media) {
      $build = $this->buildTile($media);
      $this->assertArrayNotHasKey('album', $build);
      $this->assertContains('node_list:album', $build['#cache']['tags']);
    }
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
