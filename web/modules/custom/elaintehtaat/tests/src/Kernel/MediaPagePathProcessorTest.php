<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Url;
use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the pretty URLs of media pages.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class MediaPagePathProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'file',
    'image',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Saving an alias runs AlbumAliasHooks, which queries nodes.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    // The alias prefix list only considers path roots known to the router.
    $this->container->get('router.builder')->rebuild();
    PathAlias::create([
      'path' => '/node/5',
      'alias' => '/tehotuotanto/sikala-2024',
    ])->save();
  }

  /**
   * Aliased album paths with a trailing id resolve to the media page route.
   */
  public function testInbound(): void {
    $manager = $this->container->get('path_processor_manager');
    $request = Request::create('/');

    $this->assertSame('/node/5/media/42', $manager->processInbound('/tehotuotanto/sikala-2024/42', $request));
    // The album alias itself is untouched.
    $this->assertSame('/node/5', $manager->processInbound('/tehotuotanto/sikala-2024', $request));
    // System paths and paths without a matching alias are untouched.
    $this->assertSame('/node/5/media/42', $manager->processInbound('/node/5/media/42', $request));
    $this->assertSame('/node/9/media/1', $manager->processInbound('/node/9/media/1', $request));
    $this->assertSame('/user/1', $manager->processInbound('/user/1', $request));
    $this->assertSame('/tehotuotanto/muu/7', $manager->processInbound('/tehotuotanto/muu/7', $request));
  }

  /**
   * Media page URLs are generated under the album alias.
   */
  public function testOutbound(): void {
    $manager = $this->container->get('path_processor_manager');
    $options = [];

    $this->assertSame('/tehotuotanto/sikala-2024/42', $manager->processOutbound('/node/5/media/42', $options));
    $this->assertSame('/node/9/media/42', $manager->processOutbound('/node/9/media/42', $options));

    $url = Url::fromRoute('elaintehtaat.media_page', ['node' => 5, 'media' => 42]);
    $this->assertSame('/tehotuotanto/sikala-2024/42', $url->toString());
  }

}
