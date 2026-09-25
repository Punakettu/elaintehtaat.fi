<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Render\Markup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the text component.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class TextComponentTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Renders the formatted text with style and tone classes.
   */
  public function testRendersStyleAndTone(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'elaintehtaat:text',
      '#props' => [
        'text' => Markup::create('<p>Hei <strong>maailma</strong></p>'),
        'style' => 'lede',
        'tone' => 'muted',
      ],
    ];
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringContainsString('class="text text--lede text--tone-muted"', $html);
    $this->assertStringContainsString('<p>Hei <strong>maailma</strong></p>', $html);
  }

}
