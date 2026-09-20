<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\media\MediaInterface;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the node bundle classes and the readings on them.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class BundleClassTest extends KernelTestBase {

  use ContentModelTrait;
  use UserCreationTrait;

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
   * Each of the site's own node types is loaded as its bundle class.
   */
  public function testNodesUseTheirBundleClass(): void {
    $album = $this->createNode(['type' => Album::BUNDLE]);
    $project = $this->createNode(['type' => Project::BUNDLE]);
    $page = $this->createNode(['type' => 'page']);

    $this->assertInstanceOf(Album::class, $album);
    $this->assertInstanceOf(Project::class, $project);
    $this->assertNotInstanceOf(Album::class, $page);
    $this->assertNotInstanceOf(Project::class, $page);

    // Storage applies the class on load as well as on create.
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node');
    $storage->resetCache();
    $this->assertInstanceOf(Album::class, $storage->load($album->id()));
    $this->assertInstanceOf(Project::class, $storage->load($project->id()));
  }

  /**
   * The album getters read the fields they are named after.
   */
  public function testAlbumReadsItsFields(): void {
    $project = $this->createProject();
    $first = $this->createImageMedia();
    $second = $this->createImageMedia();

    $album = $this->createAlbum([
      'field_project' => $project,
      'field_date' => '2023-05-14',
      'field_album_media' => [$first, $second],
    ]);

    $this->assertSame($project->id(), $album->getProject()?->id());
    $this->assertSame('2023-05-14', $album->getDate()?->format('Y-m-d'));
    $this->assertSame('2023', $album->getYear());
    $this->assertSame($first->id(), $album->getCover()?->id());
    $this->assertSame(
      [$first->id(), $second->id()],
      array_map(static fn (MediaInterface $item): ?string => $item->id(), $album->getMedia()),
    );
    $this->assertSame(2, $album->getMediaCount());
  }

  /**
   * Media the user may not view is left out, and its cacheability collected.
   */
  public function testAlbumViewableMedia(): void {
    $this->setUpCurrentUser(permissions: ['view media']);
    $visible = $this->createImageMedia('Kuva');
    $hidden = $this->createImageMedia('Piilotettu', ['status' => 0]);

    $album = $this->createAlbum(['field_album_media' => [$hidden, $visible]]);

    $cache = new CacheableMetadata();
    $items = $album->getViewableMedia($cache);

    $this->assertSame(
      [$visible->id()],
      array_map(static fn (MediaInterface $item): ?string => $item->id(), $items),
    );
    $this->assertContains('user.permissions', $cache->getCacheContexts());
  }

  /**
   * An empty album reads as empty rather than failing.
   */
  public function testAlbumWithoutValues(): void {
    $album = $this->createAlbum();

    $this->assertNull($album->getProject());
    $this->assertNull($album->getDate());
    $this->assertNull($album->getYear());
    $this->assertNull($album->getCover());
    $this->assertSame([], $album->getMedia());
    $this->assertSame([], $album->getViewableMedia());
    $this->assertSame(0, $album->getMediaCount());
  }

}
