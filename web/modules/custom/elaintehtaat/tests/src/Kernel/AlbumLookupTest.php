<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\elaintehtaat\Album\AlbumLookup;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests finding the albums a media item belongs to.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class AlbumLookupTest extends KernelTestBase {

  use ContentModelTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [...self::CONTENT_MODEL_MODULES, 'language', 'content_translation'];

  /**
   * The service under test.
   */
  private AlbumLookup $lookup;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContentModel();
    $this->installConfig(['language']);
    // Saving a node translation rebuilds the node access records.
    $this->installSchema('node', ['node_access']);
    $this->lookup = $this->container->get(AlbumLookup::class);
  }

  /**
   * Every published album holding the item is found, newest first.
   */
  public function testAlbumsOfReturnsPublishedAlbumsNewestFirst(): void {
    $media = $this->createImageMedia();
    $older = $this->createAlbum([
      'title' => 'First sighting',
      'created' => 100,
      'field_album_media' => [$media],
    ]);
    $newer = $this->createAlbum([
      'title' => 'Later sighting',
      'created' => 200,
      'field_album_media' => [$media],
    ]);
    // Neither of these should turn up.
    $this->createAlbum([
      'title' => 'Draft',
      'status' => 0,
      'created' => 300,
      'field_album_media' => [$media],
    ]);
    $this->createAlbum(['title' => 'Elsewhere', 'field_album_media' => []]);

    $albums = $this->lookup->albumsOf($media);

    $this->assertSame(
      [$newer->id(), $older->id()],
      array_map(static fn ($album): string => (string) $album->id(), $albums),
    );
    $this->assertSame($newer->id(), $this->lookup->newestAlbumOf($media)?->id());
  }

  /**
   * An item in no album at all yields nothing.
   */
  public function testAlbumsOfWithoutAnyAlbum(): void {
    $media = $this->createImageMedia();

    $this->assertSame([], $this->lookup->albumsOf($media));
    $this->assertNull($this->lookup->newestAlbumOf($media));
  }

  /**
   * The status of the asked-for translation decides, not any translation's.
   */
  public function testAlbumsOfChecksTheStatusOfTheTranslation(): void {
    ConfigurableLanguage::createFromLangcode('fi')->save();

    $media = $this->createImageMedia();
    $album = $this->createAlbum([
      'title' => 'Published in English only',
      'field_album_media' => [$media],
    ]);
    $translation = $album->addTranslation('fi', ['title' => 'Vain englanniksi']);
    $translation->setUnpublished();
    $translation->save();

    $this->assertCount(1, $this->lookup->albumsOf($media, 'en'));
    $this->assertSame([], $this->lookup->albumsOf($media, 'fi'));
  }

  /**
   * The media IDs of an album are read off the field, in album order.
   */
  public function testMediaIdsOf(): void {
    $first = $this->createImageMedia('First');
    $second = $this->createImageMedia('Second');
    $album = $this->createAlbum(['field_album_media' => [$first, $second]]);

    $this->assertSame(
      [(string) $first->id(), (string) $second->id()],
      $this->lookup->mediaIdsOf($album),
    );
    // A node that is not an album holds no media, whatever it references.
    $this->assertSame([], $this->lookup->mediaIdsOf($this->createProject()));
  }

}
