<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the key numbers block.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class KeyNumbersBlockTest extends KernelTestBase {

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

    $this->createContentType(['type' => 'album']);
    $this->createContentType(['type' => 'project']);
    $this->createContentType(['type' => 'page']);
    $this->createMediaType('test', ['id' => 'image']);
  }

  /**
   * Counts only published albums, media and projects.
   */
  public function testCountsPublishedContent(): void {
    $this->createNodes('album', published: 2, unpublished: 1);
    $this->createNodes('project', published: 1, unpublished: 1);
    // Pages are not part of the archive and must not be counted.
    $this->createNodes('page', published: 3, unpublished: 0);
    for ($i = 0; $i < 2; $i++) {
      Media::create(['bundle' => 'image', 'name' => "Media $i", 'status' => 1])->save();
    }
    Media::create(['bundle' => 'image', 'name' => 'Draft', 'status' => 0])->save();

    $block = $this->container->get('plugin.manager.block')->createInstance('elaintehtaat_key_numbers');
    $build = $block->build();

    $this->assertSame('component', $build['#type']);
    $this->assertSame('elaintehtaat:key-numbers', $build['#component']);
    $this->assertSame([
      ['value' => 2, 'label' => 'albums'],
      ['value' => 2, 'label' => 'media'],
      ['value' => 1, 'label' => 'project'],
    ], $build['#props']['items']);
  }

  /**
   * Creates published and unpublished nodes of a type.
   */
  protected function createNodes(string $type, int $published, int $unpublished): void {
    for ($i = 0; $i < $published; $i++) {
      Node::create(['type' => $type, 'title' => "$type $i", 'status' => 1])->save();
    }
    for ($i = 0; $i < $unpublished; $i++) {
      Node::create(['type' => $type, 'title' => "$type draft $i", 'status' => 0])->save();
    }
  }

}
