<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the meta card component.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class MetaCardComponentTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Rows print their label against their values.
   */
  public function testRendersRows(): void {
    $html = $this->renderCard([
      ['label' => 'Date', 'items' => [['text' => '14.5.2022']]],
      ['label' => 'Species', 'items' => [['text' => 'Pig', 'url' => '/taxonomy/term/1']]],
    ]);

    $this->assertStringContainsString('class="meta-card"', $html);
    $this->assertStringContainsString('<dt>Date</dt>', $html);
    $this->assertStringContainsString('14.5.2022', $html);
    $this->assertStringContainsString('<a href="/taxonomy/term/1">Pig</a>', $html);
  }

  /**
   * Several values of a row are joined, by a comma unless told otherwise.
   */
  public function testJoinsValues(): void {
    $html = $this->renderCard([
      ['label' => 'Species', 'items' => [['text' => 'Pig'], ['text' => 'Cow']]],
      ['label' => 'Project', 'items' => [['text' => 'Pig farms'], ['text' => '2022']], 'separator' => ' · '],
    ]);

    $this->assertStringContainsString('Pig, Cow', $html);
    $this->assertStringContainsString('Pig farms · 2022', $html);
  }

  /**
   * A chip is set off as a pill, and links out when it has somewhere to go.
   */
  public function testRendersChip(): void {
    $html = $this->renderCard([
      [
        'label' => 'License',
        'items' => [
          [
            'text' => 'CC BY 4.0',
            'url' => 'https://creativecommons.org/licenses/by/4.0/',
            'rel' => 'license',
            'chip' => TRUE,
          ],
        ],
      ],
    ]);

    $this->assertStringContainsString('<a class="meta-card__chip" href="https://creativecommons.org/licenses/by/4.0/" rel="license">CC BY 4.0</a>', $html);
  }

  /**
   * A chip without a URL stays plain text in the pill.
   */
  public function testRendersChipWithoutUrl(): void {
    $html = $this->renderCard([
      ['label' => 'License', 'items' => [['text' => 'Editorial use only', 'chip' => TRUE]]],
    ]);

    $this->assertStringContainsString('<span class="meta-card__chip">Editorial use only</span>', $html);
    $this->assertStringNotContainsString('<a', $html);
  }

  /**
   * A note qualifies the values of its row.
   */
  public function testRendersNote(): void {
    $html = $this->renderCard([
      ['label' => 'Location', 'items' => [['text' => 'Jokioinen']], 'note' => 'Coarse location'],
    ]);

    $this->assertStringContainsString('<span class="meta-card__note">Coarse location</span>', $html);
  }

  /**
   * Values are escaped.
   */
  public function testEscapesValues(): void {
    $html = $this->renderCard([
      ['label' => 'Species', 'items' => [['text' => 'Pig <script>']]],
    ]);

    $this->assertStringNotContainsString('<script>', $html);
  }

  /**
   * Renders the component with the given rows.
   */
  private function renderCard(array $rows): string {
    $build = [
      '#type' => 'component',
      '#component' => 'elaintehtaat:meta-card',
      '#props' => ['rows' => $rows],
    ];

    return (string) $this->container->get('renderer')->renderRoot($build);
  }

}
