<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Url;
use Drupal\elaintehtaat\Controller\MediaPageController;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Entity\Licence;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
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

  use ContentModelTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = self::CONTENT_MODEL_MODULES;

  /**
   * The project node.
   */
  protected Project $project;

  /**
   * The album node.
   */
  protected Album $album;

  /**
   * The licence term.
   */
  protected Licence $licence;

  /**
   * The album's media, in album order.
   *
   * @var list<\Drupal\elaintehtaat\Entity\Image>
   */
  protected array $media = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContentModel();

    $this->licence = $this->createLicence();

    $this->setUpCurrentUser(permissions: ['access content', 'view media']);

    $this->project = $this->createProject(['title' => 'Tehotuotanto']);

    for ($i = 1; $i <= 3; $i++) {
      $credits = $i === 2 ? [
        'field_author' => 'Jane Doe',
        'field_licence' => $this->licence,
        'field_date' => '2024-03-07',
        'field_caption' => ['value' => 'Emakko häkissä.', 'format' => 'plain_text'],
      ] : [];
      $this->media[] = $this->createImageMedia("Kuva $i", $credits);
    }
    // Unpublished media is not shown to users without special permissions.
    $hidden = $this->createImageMedia('Piilotettu', ['status' => 0]);

    $this->album = $this->createAlbum([
      'title' => 'Sikala 2024',
      'field_project' => $this->project,
      'field_album_media' => [$this->media[0], $hidden, $this->media[1], $this->media[2]],
    ]);
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
    $this->assertSame('7.3.2024', $props['date']);
    $this->assertSame('CC BY 4.0', $props['licence_name']);
    $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $props['licence_url']);
    $this->assertStringContainsString('image-test.png', $props['download_url']);
    $this->assertSame('image-test.png', $props['download_filename']);

    $this->assertSame('image_style', $build['#slots']['image']['#theme']);
    $this->assertSame(MediaPageController::IMAGE_STYLE, $build['#slots']['image']['#style_name']);
    $this->assertSame(MediaPageController::THUMBNAIL_STYLE, $build['#slots']['album_thumbnail']['#style_name']);
    $this->assertStringContainsString('Emakko häkissä.', (string) $this->container->get('renderer')->renderInIsolation($build['#slots']['caption']));

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
   * Media without a caption renders the album fallback text.
   */
  public function testRenderWithoutCaption(): void {
    ImageStyle::create(['name' => MediaPageController::IMAGE_STYLE, 'label' => 'Media page'])->save();

    $build = $this->controller()->build($this->album, $this->media[0]);
    $this->assertArrayNotHasKey('caption', $build['#slots']);

    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('No separate caption.', $html);
  }

  /**
   * An album with a single item has no previous/next links.
   */
  public function testSingleItem(): void {
    $album = $this->createAlbum([
      'title' => 'Yksi',
      'field_project' => $this->project,
      'field_album_media' => [$this->media[0]],
    ]);

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
  protected function mediaUrl(Image $media): string {
    return Url::fromRoute('elaintehtaat.media_page', [
      'node' => $this->album->id(),
      'media' => $media->id(),
    ])->toString();
  }

}
