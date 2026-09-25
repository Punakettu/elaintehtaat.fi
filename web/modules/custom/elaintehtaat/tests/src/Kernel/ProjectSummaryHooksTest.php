<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the album summary added to the view modes that list projects.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class ProjectSummaryHooksTest extends KernelTestBase {

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

    // The card view mode is site config, which a kernel test does not install,
    // so without this the card build would fall back to the default display
    // and the assertions below would pass without testing anything.
    EntityViewMode::create([
      'id' => 'node.card',
      'targetEntityType' => 'node',
      'label' => 'Card',
    ])->save();

    $displays = $this->container->get(EntityDisplayRepositoryInterface::class);
    foreach (['teaser', 'card'] as $view_mode) {
      $displays->getViewDisplay('node', Project::BUNDLE, $view_mode)
        ->setStatus(TRUE)
        ->save();
    }
  }

  /**
   * The teaser is dated by the newest album and counts them all.
   */
  public function testTeaserSummarisesAlbums(): void {
    $project = $this->createProject();
    $older = $this->createAlbumOf($project, '2022-03-01');
    $newest = $this->createAlbumOf($project, '2023-05-01');

    $build = $this->buildProject($project);

    $this->assertSame('2023', $build['year']['#plain_text']);
    $this->assertSame('2 albums', (string) $build['album_count']['#markup']);
    $this->assertCount(2, $this->covers($build));
    $this->assertContains('node_list:album', $build['#cache']['tags']);
    foreach ([$older, $newest] as $album) {
      $this->assertContains('node:' . $album->id(), $build['#cache']['tags']);
    }
  }

  /**
   * Albums of other projects, and unpublished ones, are left out.
   */
  public function testUnrelatedAlbumsAreLeftOut(): void {
    $project = $this->createProject();
    $this->createAlbumOf($project, '2024-01-01');
    $this->createAlbumOf($project, '2025-01-01', status: 0);
    $this->createAlbumOf($this->createProject(), '2025-06-01');

    $build = $this->buildProject($project);

    $this->assertSame('2024', $build['year']['#plain_text']);
    $this->assertSame('1 album', (string) $build['album_count']['#markup']);
  }

  /**
   * A project without albums keeps its count but gets no year or covers.
   */
  public function testSummaryWithoutAlbums(): void {
    $build = $this->buildProject($this->createProject());

    $this->assertArrayNotHasKey('year', $build);
    $this->assertArrayNotHasKey('covers', $build);
    $this->assertSame('0 albums', (string) $build['album_count']['#markup']);
    $this->assertContains('node_list:album', $build['#cache']['tags']);
  }

  /**
   * Only the newest albums get a cover, however many there are.
   */
  #[DataProvider('coverLimits')]
  public function testCoversAreCapped(string $view_mode, int $limit): void {
    $project = $this->createProject();
    for ($i = 1; $i <= $limit + 2; $i++) {
      $this->createAlbumOf($project, sprintf('20%02d-01-01', $i));
    }

    $build = $this->buildProject($project, $view_mode);

    $this->assertCount($limit, $this->covers($build));
  }

  /**
   * How many covers each view mode shows.
   *
   * @return iterable<string, array{string, int}>
   *   The view mode and the number of covers it caps at.
   */
  public static function coverLimits(): iterable {
    yield 'teaser' => ['teaser', 4];
    yield 'card' => ['card', 3];
  }

  /**
   * Each view mode renders the covers in the image styles its layout needs.
   */
  #[DataProvider('coverStyles')]
  public function testCoverImageStyles(string $view_mode, array $expected): void {
    $project = $this->createProject();
    for ($i = 1; $i <= 3; $i++) {
      $this->createAlbumOf($project, sprintf('20%02d-01-01', $i));
    }

    $build = $this->buildProject($project, $view_mode);

    $styles = array_column($this->covers($build), '#style_name');
    $this->assertSame($expected, $styles);
  }

  /**
   * The image style of each cover, in order, per view mode.
   *
   * @return iterable<string, array{string, list<string>}>
   *   The view mode and the styles it renders its covers in.
   */
  public static function coverStyles(): iterable {
    // The teaser covers are all the same size, so they share one style.
    yield 'teaser' => ['teaser', ['thumbnail', 'thumbnail', 'thumbnail']];
    // The card's first cover fills most of the mosaic, the rest a quarter.
    yield 'card' => ['card', ['card', 'large', 'large']];
  }

  /**
   * View modes that list no projects are left alone.
   */
  public function testOtherViewModesAreUntouched(): void {
    $project = $this->createProject();
    $this->createAlbumOf($project, '2024-01-01');

    $build = $this->buildProject($project, 'full');

    $this->assertArrayNotHasKey('album_count', $build);
  }

  /**
   * Creates a dated album of a project, holding one media item.
   */
  private function createAlbumOf(Project $project, string $date, int $status = 1): Album {
    return $this->createAlbum([
      'title' => 'Fox farm ' . $date,
      'status' => $status,
      'field_project' => $project,
      'field_date' => $date,
      'field_album_media' => [$this->createImageMedia()],
    ]);
  }

  /**
   * The cover thumbnails in a build.
   */
  private function covers(array $build): array {
    return array_filter(
      $build['covers'] ?? [],
      static fn (string|int $key): bool => is_int($key),
      ARRAY_FILTER_USE_KEY,
    );
  }

  /**
   * Builds the render array of a project, running hook_node_view().
   */
  private function buildProject(Project $project, string $view_mode = 'teaser'): array {
    $view_builder = $this->container->get(EntityTypeManagerInterface::class)->getViewBuilder('node');
    $this->assertInstanceOf(EntityViewBuilder::class, $view_builder);
    return $view_builder->build($view_builder->view($project, $view_mode));
  }

}
