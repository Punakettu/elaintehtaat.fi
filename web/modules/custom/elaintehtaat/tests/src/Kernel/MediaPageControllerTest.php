<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Url;
use Drupal\elaintehtaat\Controller\MediaPageController;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the media page controller.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class MediaPageControllerTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use MediaTypeCreationTrait;
  use UserCreationTrait;

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
    'taxonomy',
    'link',
  ];

  /**
   * The project node.
   */
  protected NodeInterface $project;

  /**
   * The album node.
   */
  protected NodeInterface $album;

  /**
   * The licence term.
   */
  protected Term $licence;

  /**
   * The album's media, in album order.
   *
   * @var list<\Drupal\media\MediaInterface>
   */
  protected array $media = [];

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
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'node', 'image']);

    $this->createContentType(['type' => 'project']);
    $this->createContentType(['type' => 'album']);
    $this->createMediaType('image', ['id' => 'image']);

    FieldStorageConfig::create([
      'field_name' => 'field_project',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_project',
      'entity_type' => 'node',
      'bundle' => 'album',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_album_media',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_album_media',
      'entity_type' => 'node',
      'bundle' => 'album',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_author',
      'entity_type' => 'media',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_author',
      'entity_type' => 'media',
      'bundle' => 'image',
    ])->save();

    Vocabulary::create(['vid' => 'licence', 'name' => 'Licence'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_licence_link',
      'entity_type' => 'taxonomy_term',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_licence_link',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'licence',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_licence',
      'entity_type' => 'media',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_licence',
      'entity_type' => 'media',
      'bundle' => 'image',
    ])->save();
    $this->licence = Term::create([
      'vid' => 'licence',
      'name' => 'CC BY 4.0',
      'field_licence_link' => ['uri' => 'https://creativecommons.org/licenses/by/4.0/'],
    ]);
    $this->licence->save();

    $this->setUpCurrentUser(permissions: ['access content', 'view media']);

    $this->project = Node::create(['type' => 'project', 'title' => 'Tehotuotanto']);
    $this->project->save();

    for ($i = 1; $i <= 3; $i++) {
      $this->media[] = $this->createImageMedia("Kuva $i", $i === 2 ? 'Jane Doe' : '', licence: $i === 2);
    }
    // Unpublished media is not shown to users without special permissions.
    $hidden = $this->createImageMedia('Piilotettu', '', FALSE);

    $this->album = Node::create([
      'type' => 'album',
      'title' => 'Sikala 2024',
      'field_project' => $this->project,
      'field_album_media' => [$this->media[0], $hidden, $this->media[1], $this->media[2]],
    ]);
    $this->album->save();
  }

  /**
   * Builds the page for the middle item and checks its props.
   */
  public function testBuild(): void {
    $build = $this->controller()->build($this->album, $this->media[1]);

    $this->assertSame('component', $build['#type']);
    $this->assertSame('elaintehtaat:media-page', $build['#component']);

    $props = $build['#props'];
    $this->assertSame(2, $props['position']);
    $this->assertSame(3, $props['total']);
    $this->assertSame($this->mediaUrl($this->media[0]), $props['prev_url']);
    $this->assertSame($this->mediaUrl($this->media[2]), $props['next_url']);
    $this->assertSame($this->album->toUrl()->toString(), $props['close_url']);
    $this->assertSame($this->album->toUrl()->toString(), $props['album_url']);
    $this->assertSame('Sikala 2024', $props['album_title']);
    $this->assertSame('Tehotuotanto', $props['project_title']);
    $this->assertSame('Jane Doe', $props['author']);
    $this->assertSame('Photo: Jane Doe / Eläintehtaat', $props['attribution']);
    $this->assertSame('Photograph', $props['type_label']);
    $this->assertSame('CC BY 4.0', $props['licence_name']);
    $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $props['licence_url']);
    $this->assertStringContainsString('image-test.png', $props['download_url']);
    $this->assertSame('image-test.png', $props['download_filename']);

    $this->assertSame('image_style', $build['#slots']['image']['#theme']);
    $this->assertSame(MediaPageController::IMAGE_STYLE, $build['#slots']['image']['#style_name']);
    $this->assertSame(MediaPageController::THUMBNAIL_STYLE, $build['#slots']['album_thumbnail']['#style_name']);
    $this->assertSame([], $build['#slots']['caption']);

    $this->assertContains('node:' . $this->album->id(), $build['#cache']['tags']);
    $this->assertContains('media:' . $this->media[1]->id(), $build['#cache']['tags']);
    $this->assertContains('taxonomy_term:' . $this->licence->id(), $build['#cache']['tags']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
  }

  /**
   * Navigation wraps around at both ends of the album.
   */
  public function testNavigationWraps(): void {
    $controller = $this->controller();

    $first = $controller->build($this->album, $this->media[0])['#props'];
    $this->assertSame(1, $first['position']);
    $this->assertSame($this->mediaUrl($this->media[2]), $first['prev_url']);
    $this->assertSame($this->mediaUrl($this->media[1]), $first['next_url']);
    $this->assertSame('Photo: Eläintehtaat', $first['attribution']);
    $this->assertSame('', $first['licence_name']);
    $this->assertSame('', $first['licence_url']);

    $last = $controller->build($this->album, $this->media[2])['#props'];
    $this->assertSame(3, $last['position']);
    $this->assertSame($this->mediaUrl($this->media[1]), $last['prev_url']);
    $this->assertSame($this->mediaUrl($this->media[0]), $last['next_url']);
  }

  /**
   * An album with a single item has no previous/next links.
   */
  public function testSingleItem(): void {
    $album = Node::create([
      'type' => 'album',
      'title' => 'Yksi',
      'field_project' => $this->project,
      'field_album_media' => [$this->media[0]],
    ]);
    $album->save();

    $props = $this->controller()->build($album, $this->media[0])['#props'];
    $this->assertSame(1, $props['total']);
    $this->assertSame('', $props['prev_url']);
    $this->assertSame('', $props['next_url']);
  }

  /**
   * Media that is not part of the album is not found.
   */
  public function testMediaNotInAlbum(): void {
    $other = $this->createImageMedia('Muu');
    $this->expectException(NotFoundHttpException::class);
    $this->controller()->build($this->album, $other);
  }

  /**
   * Only albums have media pages.
   */
  public function testAccess(): void {
    $controller = $this->controller();
    $account = $this->container->get('current_user');

    $this->assertTrue($controller->access($this->album, $this->media[0], $account)->isAllowed());
    $this->assertFalse($controller->access($this->project, $this->media[0], $account)->isAllowed());
  }

  /**
   * Instantiates the controller through the container.
   */
  protected function controller(): MediaPageController {
    return $this->container->get('class_resolver')->getInstanceFromDefinition(MediaPageController::class);
  }

  /**
   * URL of the media page of one album item.
   */
  protected function mediaUrl(MediaInterface $media): string {
    return Url::fromRoute('elaintehtaat.media_page', [
      'node' => $this->album->id(),
      'media' => $media->id(),
    ])->toString();
  }

  /**
   * Creates an image media item backed by a test image.
   */
  protected function createImageMedia(string $name, string $author = '', bool $published = TRUE, bool $licence = FALSE): MediaInterface {
    $this->container->get('file_system')->copy(
      $this->root . '/core/tests/fixtures/files/image-test.png',
      'public://image-test.png',
    );
    $file = File::create(['uri' => 'public://image-test.png', 'filename' => 'image-test.png']);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => $name,
      'status' => $published,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $name,
        'width' => 40,
        'height' => 20,
      ],
      'field_author' => $author,
      'field_licence' => $licence ? $this->licence : NULL,
    ]);
    $media->save();
    return $media;
  }

}
