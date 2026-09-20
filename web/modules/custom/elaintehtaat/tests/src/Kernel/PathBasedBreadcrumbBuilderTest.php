<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\elaintehtaat\Breadcrumb\PathBasedBreadcrumbBuilder;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests path-based breadcrumbs on language-prefixed URLs.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class PathBasedBreadcrumbBuilderTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

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
    'language',
  ];

  /**
   * The album node whose breadcrumb is built.
   */
  protected Node $album;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node', 'language']);

    ConfigurableLanguage::createFromLangcode('fi')->save();
    $this->config('language.negotiation')->set('url.prefixes', ['en' => 'en', 'fi' => ''])->save();
    $this->config('system.site')->set('page.front', '/front')->save();
    // The language path processor is set up from the languages known when the
    // container was built.
    $this->container->get('kernel')->rebuildContainer();

    $this->setUpCurrentUser(permissions: ['access content']);

    $this->createContentType(['type' => 'page']);
    $front = Node::create(['type' => 'page', 'title' => 'Front page']);
    $front->save();
    $project = Node::create(['type' => 'page', 'title' => 'Project']);
    $project->save();
    $this->album = Node::create(['type' => 'page', 'title' => 'Album']);
    $this->album->save();
    PathAlias::create(['path' => '/node/' . $front->id(), 'alias' => '/front'])->save();
    PathAlias::create(['path' => '/node/' . $project->id(), 'alias' => '/project'])->save();
    PathAlias::create(['path' => '/node/' . $this->album->id(), 'alias' => '/project/album'])->save();

    $this->container->get(RouteBuilderInterface::class)->rebuild();
  }

  /**
   * The module's builder is the one the breadcrumb manager uses.
   */
  public function testBuilderIsUsed(): void {
    $manager = $this->container->get('breadcrumb');
    $builders = (new \ReflectionProperty($manager, 'builders'))->getValue($manager);
    $this->assertArrayHasKey(10, $builders);
    $this->assertInstanceOf(PathBasedBreadcrumbBuilder::class, $builders[10][0]);
  }

  /**
   * The trail ends with the current page, which is not a link.
   */
  public function testCurrentPage(): void {
    $breadcrumb = $this->buildBreadcrumb('/project/album');
    $this->assertSame(['Home', 'Project', 'Album'], $this->linkTitles($breadcrumb));
    $links = $breadcrumb->getLinks();
    $this->assertSame('/project', $links[1]->getUrl()->toString());
    $this->assertSame('', $links[2]->getUrl()->toString());
    $this->assertContains('url.path', $breadcrumb->getCacheContexts());
    $this->assertContains('node:' . $this->album->id(), $breadcrumb->getCacheTags());
  }

  /**
   * The front page has no breadcrumb.
   */
  public function testFrontPage(): void {
    $this->assertSame([], $this->linkTitles($this->buildBreadcrumb('/')));
  }

  /**
   * The front page is not repeated after Home on a language-prefixed URL.
   */
  public function testLanguagePrefixedPath(): void {
    $this->assertSame(['Home', 'Project', 'Album'], $this->linkTitles($this->buildBreadcrumb('/en/project/album')));
  }

  /**
   * Builds the breadcrumb for a request to the given path.
   */
  protected function buildBreadcrumb(string $path): Breadcrumb {
    $request = Request::create($path);
    $this->container->get('request_stack')->push($request);
    $this->container->get(RequestContext::class)->fromRequest($request);
    // Match the request the way the HTTP kernel does, so the route match and
    // the request attributes carry the upcast node.
    $processed = $this->container->get('path_processor_manager')->processInbound($path, $request);
    $this->container->get('path.current')->setPath($processed, $request);
    $request->attributes->add($this->container->get('router.no_access_checks')->matchRequest($request));
    $breadcrumb = $this->container->get('breadcrumb')->build(RouteMatch::createFromRequest($request));
    $this->container->get('request_stack')->pop();
    return $breadcrumb;
  }

  /**
   * Returns the link titles of a breadcrumb in order.
   *
   * @return list<string>
   *   The titles.
   */
  protected function linkTitles(Breadcrumb $breadcrumb): array {
    return array_map(static fn (Link $link) => (string) $link->getText(), $breadcrumb->getLinks());
  }

}
