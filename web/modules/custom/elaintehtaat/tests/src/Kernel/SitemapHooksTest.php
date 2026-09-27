<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\simple_sitemap\Exception\SkipElementException;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the front page is left to the "/" custom link of the sitemap.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class SitemapHooksTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'simple_sitemap',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'filter', 'node']);
    $this->createContentType(['type' => 'page']);
  }

  /**
   * Tests that the front page entity is skipped and other entities are not.
   */
  public function testFrontPageIsSkipped(): void {
    $front = Node::create(['type' => 'page', 'title' => 'Front page']);
    $front->save();
    $other = Node::create(['type' => 'page', 'title' => 'About']);
    $other->save();
    PathAlias::create(['path' => '/node/' . $front->id(), 'alias' => '/front'])->save();
    $this->config('system.site')->set('page.front', '/front')->save();

    $module_handler = $this->container->get('module_handler');
    $module_handler->invokeAll('simple_sitemap_entity_process', [$other]);

    $this->expectException(SkipElementException::class);
    $module_handler->invokeAll('simple_sitemap_entity_process', [$front]);
  }

}
