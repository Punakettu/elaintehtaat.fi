<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the key numbers block.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class KeyNumbersBlockTest extends KernelTestBase {

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
  }

  /**
   * Counts only published albums, media and projects.
   */
  public function testCountsPublishedContent(): void {
    $this->createNodes('album', published: 2, unpublished: 1);
    $this->createNodes('project', published: 1, unpublished: 1);
    // Other node types are not part of the archive and must not be counted.
    $this->createNodes('other', published: 3, unpublished: 0);
    for ($i = 0; $i < 2; $i++) {
      $this->createImageMedia("Media $i");
    }
    $this->createImageMedia('Draft', ['status' => 0]);

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
      $this->createNode(['type' => $type, 'status' => 1]);
    }
    for ($i = 0; $i < $unpublished; $i++) {
      $this->createNode(['type' => $type, 'status' => 0]);
    }
  }

}
