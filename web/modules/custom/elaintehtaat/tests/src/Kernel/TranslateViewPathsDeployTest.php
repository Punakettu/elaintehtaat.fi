<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deploy hook that translates the view page paths.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class TranslateViewPathsDeployTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    \Drupal::moduleHandler()->loadInclude('elaintehtaat', 'php', 'elaintehtaat.deploy');
  }

  /**
   * Tests that each path gets an English and a Swedish alias, once.
   */
  public function testAliasesAreCreatedOnce(): void {
    PathAlias::create([
      'path' => '/selaa',
      'alias' => '/kept',
      'langcode' => 'sv',
    ])->save();

    elaintehtaat_deploy_translate_view_paths();
    elaintehtaat_deploy_translate_view_paths();

    $aliases = [];
    foreach (PathAlias::loadMultiple() as $alias) {
      $aliases[$alias->getPath()][$alias->get('langcode')->value] = $alias->getAlias();
    }
    $this->assertSame([
      '/selaa' => ['sv' => '/kept', 'en' => '/browse'],
      '/projektit' => ['en' => '/projects', 'sv' => '/projekt'],
    ], $aliases);
  }

}
