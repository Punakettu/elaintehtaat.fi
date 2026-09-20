<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\elaintehtaat\Hook\ProjectTeaserHooks;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the album summary added to project teasers.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class ProjectTeaserHooksTest extends KernelTestBase {

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

    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('node', Project::BUNDLE, 'teaser')
      ->setStatus(TRUE)
      ->save();
  }

  /**
   * The teaser is dated by the newest album and counts them all.
   */
  public function testTeaserSummarisesAlbums(): void {
    $project = $this->createProject();
    $older = $this->createAlbumOf($project, '2022-03-01');
    $newest = $this->createAlbumOf($project, '2023-05-01');

    $build = $this->buildTeaser($project);

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

    $build = $this->buildTeaser($project);

    $this->assertSame('2024', $build['year']['#plain_text']);
    $this->assertSame('1 album', (string) $build['album_count']['#markup']);
  }

  /**
   * A project without albums keeps its count but gets no year or covers.
   */
  public function testTeaserWithoutAlbums(): void {
    $build = $this->buildTeaser($this->createProject());

    $this->assertArrayNotHasKey('year', $build);
    $this->assertArrayNotHasKey('covers', $build);
    $this->assertSame('0 albums', (string) $build['album_count']['#markup']);
    $this->assertContains('node_list:album', $build['#cache']['tags']);
  }

  /**
   * Only the newest albums get a cover, however many there are.
   */
  public function testCoversAreCapped(): void {
    $project = $this->createProject();
    for ($i = 1; $i <= ProjectTeaserHooks::COVER_LIMIT + 2; $i++) {
      $this->createAlbumOf($project, sprintf('20%02d-01-01', $i));
    }

    $build = $this->buildTeaser($project);

    $this->assertCount(ProjectTeaserHooks::COVER_LIMIT, $this->covers($build));
  }

  /**
   * Other view modes are left alone.
   */
  public function testOtherViewModesAreUntouched(): void {
    $project = $this->createProject();
    $this->createAlbumOf($project, '2024-01-01');

    $build = $this->buildTeaser($project, 'default');

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
   * The cover thumbnails in a teaser build.
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
  private function buildTeaser(Project $project, string $view_mode = 'teaser'): array {
    $view_builder = $this->container->get(EntityTypeManagerInterface::class)->getViewBuilder('node');
    $this->assertInstanceOf(EntityViewBuilder::class, $view_builder);
    return $view_builder->build($view_builder->view($project, $view_mode));
  }

}
