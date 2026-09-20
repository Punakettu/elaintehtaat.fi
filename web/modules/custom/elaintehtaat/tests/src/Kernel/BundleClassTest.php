<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Entity\Licence;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
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
    $other = $this->createNode(['type' => 'other']);

    $this->assertInstanceOf(Album::class, $album);
    $this->assertInstanceOf(Project::class, $project);
    $this->assertNotInstanceOf(Album::class, $other);
    $this->assertNotInstanceOf(Project::class, $other);

    // Storage applies the class on load as well as on create.
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node');
    $storage->resetCache();
    $this->assertInstanceOf(Album::class, $storage->load($album->id()));
    $this->assertInstanceOf(Project::class, $storage->load($project->id()));
  }

  /**
   * Image media and licence terms are loaded as their bundle class too.
   */
  public function testMediaAndTermsUseTheirBundleClass(): void {
    Vocabulary::create(['vid' => 'species', 'name' => 'Species'])->save();
    $species = Term::create(['vid' => 'species', 'name' => 'Fox']);
    $species->save();

    $media = $this->createImageMedia();
    $licence = $this->createLicence();

    // createImageMedia() and createLicence() assert the class on create; the
    // storages below prove it holds on load as well.
    $this->assertNotInstanceOf(Licence::class, $species);

    $entity_type_manager = $this->container->get(EntityTypeManagerInterface::class);
    $media_storage = $entity_type_manager->getStorage('media');
    $media_storage->resetCache();
    $this->assertInstanceOf(Image::class, $media_storage->load($media->id()));

    $term_storage = $entity_type_manager->getStorage('taxonomy_term');
    $term_storage->resetCache();
    $this->assertInstanceOf(Licence::class, $term_storage->load($licence->id()));
    $this->assertNotInstanceOf(Licence::class, $term_storage->load($species->id()));
  }

  /**
   * The image getters read the fields they are named after.
   */
  public function testImageReadsItsFields(): void {
    $licence = $this->createLicence();
    $media = $this->createImageMedia('Emakko', [
      'field_caption' => ['value' => 'Emakko häkissä.', 'format' => 'plain_text'],
      'field_date' => '2024-03-07',
      'field_author' => 'Jane Doe',
      'field_licence' => $licence,
    ]);

    $this->assertSame('Emakko häkissä.', $media->getCaption()?->value);
    $this->assertSame('2024-03-07', $media->getDate()?->format('Y-m-d'));
    $this->assertSame('2024', $media->getYear());
    $this->assertSame('Jane Doe', $media->getAuthor());
    $this->assertSame($licence->id(), $media->getLicence()?->id());
    // The alt text of the source item is the name createImageMedia() gave it.
    $this->assertSame('Emakko', $media->getSourceItem()?->get('alt')->getValue());
    $this->assertSame('image-test.png', $media->getSourceFile()?->getFilename());
    $this->assertNotNull($media->getThumbnailFile());
  }

  /**
   * An image without values of its own reads as empty rather than failing.
   */
  public function testImageWithoutValues(): void {
    $media = $this->createImageMedia();

    $this->assertNull($media->getCaption());
    $this->assertNull($media->getDate());
    $this->assertNull($media->getYear());
    $this->assertSame('', $media->getAuthor());
    $this->assertNull($media->getLicence());
  }

  /**
   * A licence links to its terms when it has a link, and reads NULL when not.
   */
  public function testLicenceUrl(): void {
    $licence = $this->createLicence();
    $this->assertSame(
      'https://creativecommons.org/licenses/by/4.0/',
      $licence->getUrl()?->toString(),
    );

    $this->assertNull($this->createLicence('All rights reserved', NULL)->getUrl());
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
