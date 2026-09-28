<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\elaintehtaat\Entity\Project;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the tokens and the route entity the meta tags are built from.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class MetatagHooksTest extends KernelTestBase {

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
    $this->setUpCurrentUser(permissions: ['access content', 'view media']);
  }

  /**
   * An album is represented by its first published media item.
   */
  public function testAlbumCover(): void {
    $album = $this->createAlbum([
      'field_album_media' => [
        $this->createImageMedia('Hidden', ['status' => 0]),
        $this->createImageMedia('First'),
        $this->createImageMedia('Second'),
      ],
    ]);

    $this->assertSame('First / First', $this->replace('[node:cover] / [node:cover:name]', ['node' => $album]));
  }

  /**
   * A project is represented by the cover of its newest album that has one.
   */
  public function testProjectCover(): void {
    $project = $this->createProject();
    $this->createAlbumOf($project, '2021-01-01', 'Oldest');
    $this->createAlbumOf($project, '2022-01-01', 'Older');
    $this->createAlbum(['field_project' => $project, 'field_date' => '2023-01-01']);

    $metadata = new BubbleableMetadata();
    $this->assertSame('Older', $this->replace('[node:cover:name]', ['node' => $project], $metadata));
    $this->assertContains('node_list:album', $metadata->getCacheTags());
  }

  /**
   * Nodes that are neither albums nor projects have no cover.
   */
  public function testNoCover(): void {
    $this->createContentType(['type' => 'page']);
    $page = $this->createNode(['type' => 'page']);

    $this->assertSame('', $this->replace('[node:cover:name]', ['node' => $page]));
  }

  /**
   * A media page knows its album, and hands Metatag its media item.
   */
  public function testMediaPage(): void {
    $media = $this->createImageMedia('Frame');
    $album = $this->createAlbum(['title' => 'Fox farm', 'field_album_media' => [$media]]);
    // A second album holding the same item must not be picked.
    $this->createAlbum(['title' => 'Other farm', 'field_album_media' => [$media]]);

    $this->visit('/node/' . $album->id() . '/media/' . $media->id());

    $metadata = new BubbleableMetadata();
    $this->assertSame('Fox farm', $this->replace('[current-page:album:title]', [], $metadata));
    $this->assertContains('route', $metadata->getCacheContexts());

    $route_match = $this->container->get('current_route_match');
    $entities = $this->container->get('module_handler')->invokeAll('metatag_route_entity', [$route_match]);
    $this->assertCount(1, $entities);
    $this->assertSame($media->id(), reset($entities)->id());
  }

  /**
   * Other pages have no album, and leave Metatag to find its own entity.
   */
  public function testOtherPage(): void {
    $album = $this->createAlbum();

    $this->visit('/node/' . $album->id());

    $this->assertSame('', $this->replace('[current-page:album:title]'));
    $route_match = new RouteMatch('entity.node.canonical', $this->container->get('current_route_match')->getRouteObject(), ['node' => $album]);
    $this->assertSame([], $this->container->get('module_handler')->invokeAll('metatag_route_entity', [$route_match]));
  }

  /**
   * Creates an album of a project dated $date, holding one image.
   */
  private function createAlbumOf(Project $project, string $date, string $media_name): void {
    $this->createAlbum([
      'field_project' => $project,
      'field_date' => $date,
      'field_album_media' => [$this->createImageMedia($media_name)],
    ]);
  }

  /**
   * Replaces tokens, clearing the ones that have no value.
   */
  private function replace(string $text, array $data = [], ?BubbleableMetadata $metadata = NULL): string {
    return (string) $this->container->get('token')->replace($text, $data, ['clear' => TRUE], $metadata ?? new BubbleableMetadata());
  }

  /**
   * Makes a request to the path the current one, matched to its route.
   */
  private function visit(string $path): void {
    $request = Request::create($path);
    $stack = $this->container->get('request_stack');
    // Kernel test teardown reads the session off the current request.
    $request->setSession($stack->getCurrentRequest()->getSession());
    $stack->push($request);
    $this->container->get(RequestContext::class)->fromRequest($request);
    $request->attributes->add($this->container->get('router.no_access_checks')->matchRequest($request));
    $this->container->get('current_route_match')->resetRouteMatch();
  }

}
