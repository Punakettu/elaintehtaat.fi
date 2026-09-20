<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Traits;

use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Entity\Licence;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Sets up the site's content model in kernel tests, and creates content in it.
 *
 * @see \Drupal\elaintehtaat\Entity\Album
 * @see \Drupal\elaintehtaat\Entity\Image
 * @see \Drupal\elaintehtaat\Entity\Licence
 * @see \Drupal\elaintehtaat\Entity\Project
 */
trait ContentModelTrait {

  use ContentTypeCreationTrait;
  use MediaTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * The modules the content model depends on.
   *
   * Spread into the test's module list: [...self::CONTENT_MODEL_MODULES, ...].
   */
  public const array CONTENT_MODEL_MODULES = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'datetime',
    'node',
    'media',
    'taxonomy',
    'link',
  ];

  /**
   * Installs the schemas and config the content model needs, then builds it.
   */
  protected function installContentModel(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('media');
    $this->installEntitySchema('taxonomy_term');
    // Saving a node runs the alias hooks.
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'field', 'filter', 'file', 'image', 'media', 'node']);

    // Like the site's own types, none of these carries a body field.
    $this->createContentType(['type' => Project::BUNDLE], create_body: FALSE);
    $this->createContentType(['type' => Album::BUNDLE], create_body: FALSE);
    // A type of the site that is neither an album nor a project.
    $this->createContentType(['type' => 'page'], create_body: FALSE);
    $this->createMediaType('image', ['id' => Image::BUNDLE]);

    $this->createField('node', Album::BUNDLE, 'field_project', 'entity_reference', ['target_type' => 'node']);
    $this->createField('node', Album::BUNDLE, 'field_album_media', 'entity_reference', ['target_type' => 'media'], FieldStorageConfig::CARDINALITY_UNLIMITED);
    $this->createField('node', Album::BUNDLE, 'field_date', 'datetime', ['datetime_type' => 'date']);

    Vocabulary::create(['vid' => Licence::BUNDLE, 'name' => 'Licence'])->save();
    $this->createField('taxonomy_term', Licence::BUNDLE, 'field_licence_link', 'link');

    $this->createField('media', Image::BUNDLE, 'field_caption', 'text');
    $this->createField('media', Image::BUNDLE, 'field_date', 'datetime', ['datetime_type' => 'date']);
    $this->createField('media', Image::BUNDLE, 'field_author', 'string');
    $this->createField('media', Image::BUNDLE, 'field_licence', 'entity_reference', ['target_type' => 'taxonomy_term']);
  }

  /**
   * Creates a configurable field, storage included, on one bundle.
   */
  protected function createField(string $entity_type, string $bundle, string $name, string $type, array $settings = [], int $cardinality = 1): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $settings,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ])->save();
  }

  /**
   * Creates a project, published unless $values says otherwise.
   */
  protected function createProject(array $values = []): Project {
    $project = $this->createNode($values + ['type' => Project::BUNDLE, 'title' => 'Fur farming']);
    $this->assertInstanceOf(Project::class, $project);
    return $project;
  }

  /**
   * Creates an album, published and empty unless $values says otherwise.
   *
   * Pass the project, date and media items as field_project, field_date and
   * field_album_media.
   */
  protected function createAlbum(array $values = []): Album {
    $album = $this->createNode($values + ['type' => Album::BUNDLE, 'title' => 'Fox farm']);
    $this->assertInstanceOf(Album::class, $album);
    return $album;
  }

  /**
   * Creates a published image media item backed by a copy of a core test image.
   *
   * The name doubles as the image's alt text. Pass other media values, such as
   * the status or the site's own media fields, in $values.
   */
  protected function createImageMedia(string $name = 'Frame', array $values = []): Image {
    $uri = 'public://image-test.png';
    $this->assertNotFalse(@copy($this->root . '/core/tests/fixtures/files/image-test.png', $uri));
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();

    $media = Media::create($values + [
      'bundle' => Image::BUNDLE,
      'name' => $name,
      'status' => 1,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => $name],
    ]);
    $media->save();
    $this->assertInstanceOf(Image::class, $media);
    return $media;
  }

  /**
   * Creates a licence term, with a link to the licence unless told otherwise.
   */
  protected function createLicence(string $name = 'CC BY 4.0', ?string $uri = 'https://creativecommons.org/licenses/by/4.0/'): Licence {
    $licence = Term::create([
      'vid' => Licence::BUNDLE,
      'name' => $name,
      'field_licence_link' => $uri === NULL ? [] : ['uri' => $uri],
    ]);
    $licence->save();
    $this->assertInstanceOf(Licence::class, $licence);
    return $licence;
  }

}
