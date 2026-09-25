<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Hook\AlbumMetaHooks;
use Drupal\node\NodeInterface;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the metadata card of the album page.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class AlbumMetaHooksTest extends KernelTestBase {

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
    // The card links to projects and terms, so the routes have to be there.
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * The card holds every fact the album has, in the order of the design.
   */
  public function testCardHoldsAlbumMetadata(): void {
    $project = $this->createProject(['title' => 'Pig farms 2022']);
    $licence = $this->createLicence();
    $pig = $this->createTerm(self::SPECIES_VOCABULARY, 'Pig');
    $cow = $this->createTerm(self::SPECIES_VOCABULARY, 'Cow');
    $meat = $this->createTerm(self::USE_VOCABULARY, 'Meat production');
    $album = $this->createAlbum([
      'field_project' => $project,
      'field_date' => '2022-05-14',
      'field_license' => $licence,
      'field_species' => [$pig, $cow],
      'field_use' => [$meat],
    ]);

    $card = $this->card($album);

    $this->assertSame('elaintehtaat:meta-card', $card['#component']);
    $this->assertSame([
      [
        'label' => 'Date',
        'items' => [['text' => $this->formatted('2022-05-14')]],
      ],
      [
        'label' => 'Species',
        'items' => [
          ['text' => 'Pig', 'url' => AlbumMetaHooks::browseUrl(AlbumMetaHooks::SPECIES_FACET, (int) $pig->id())],
          ['text' => 'Cow', 'url' => AlbumMetaHooks::browseUrl(AlbumMetaHooks::SPECIES_FACET, (int) $cow->id())],
        ],
      ],
      [
        'label' => 'Use',
        'items' => [
          [
            'text' => 'Meat production',
            'url' => AlbumMetaHooks::browseUrl(AlbumMetaHooks::USE_FACET, (int) $meat->id()),
          ],
        ],
      ],
      [
        'label' => 'Project',
        'items' => [
          ['text' => 'Pig farms 2022', 'url' => $project->toUrl()->toString()],
          ['text' => '2022'],
        ],
        'separator' => ' · ',
      ],
      [
        'label' => 'License',
        'items' => [[
          'text' => 'CC BY 4.0',
          'chip' => TRUE,
          'url' => 'https://creativecommons.org/licenses/by/4.0/',
          'rel' => 'license',
        ],
        ],
      ],
    ], $card['#props']['rows']);
  }

  /**
   * Terms link to the browse page with the term as the active facet value.
   */
  public function testTermsLinkToBrowsePage(): void {
    $meat = $this->createTerm(self::USE_VOCABULARY, 'Meat production');
    $album = $this->createAlbum(['field_use' => [$meat]]);

    $url = $this->card($album)['#props']['rows'][0]['items'][0]['url'];

    $this->assertStringEndsWith('/selaa?f%5B0%5D=kaytto%3A' . $meat->id(), $url);
  }

  /**
   * The card depends on everything it names.
   */
  public function testCardDependsOnWhatItNames(): void {
    $project = $this->createProject();
    $licence = $this->createLicence();
    $species = $this->createTerm(self::SPECIES_VOCABULARY, 'Pig');
    $album = $this->createAlbum([
      'field_project' => $project,
      'field_license' => $licence,
      'field_species' => [$species],
    ]);

    $build = $this->build($album);

    $this->assertContains('node:' . $project->id(), $build['#cache']['tags']);
    $this->assertContains('taxonomy_term:' . $licence->id(), $build['#cache']['tags']);
    $this->assertContains('taxonomy_term:' . $species->id(), $build['#cache']['tags']);
  }

  /**
   * A licence with nowhere to link to stays a plain chip.
   */
  public function testLicenceWithoutLink(): void {
    $album = $this->createAlbum([
      'field_license' => $this->createLicence('Editorial use only', NULL),
    ]);

    $rows = $this->card($album)['#props']['rows'];

    $this->assertSame([
      ['label' => 'License', 'items' => [['text' => 'Editorial use only', 'chip' => TRUE]]],
    ], $rows);
  }

  /**
   * Fields the album has no value for get no row of their own.
   */
  public function testEmptyFieldsAreLeftOut(): void {
    $album = $this->createAlbum(['field_project' => $this->createProject()]);

    $rows = $this->card($album)['#props']['rows'];

    $this->assertCount(1, $rows);
    $this->assertSame('Project', $rows[0]['label']);
    // No album date, so nothing dates the project either.
    $this->assertCount(1, $rows[0]['items']);
  }

  /**
   * An album with nothing to say gets no card at all.
   */
  public function testAlbumWithoutMetadataGetsNoCard(): void {
    $build = $this->build($this->createAlbum());

    $this->assertArrayNotHasKey(AlbumMetaHooks::EXTRA_FIELD, $build);
  }

  /**
   * The card belongs to the album page, not to listings.
   */
  public function testOtherViewModesAreUntouched(): void {
    $album = $this->createAlbum([
      'field_project' => $this->createProject(),
      'field_date' => '2022-05-14',
    ]);

    $build = $this->build($album, 'teaser');

    $this->assertArrayNotHasKey(AlbumMetaHooks::EXTRA_FIELD, $build);
  }

  /**
   * The card of an album.
   */
  private function card(Album $album): array {
    $build = $this->build($album);
    $this->assertArrayHasKey(AlbumMetaHooks::EXTRA_FIELD, $build);

    return $build[AlbumMetaHooks::EXTRA_FIELD];
  }

  /**
   * Builds the render array of an album, running hook_node_view().
   */
  private function build(NodeInterface $album, string $view_mode = AlbumMetaHooks::VIEW_MODE): array {
    $view_builder = $this->container->get(EntityTypeManagerInterface::class)->getViewBuilder('node');
    $this->assertInstanceOf(EntityViewBuilder::class, $view_builder);

    return $view_builder->build($view_builder->view($album, $view_mode));
  }

  /**
   * A date as the card writes it.
   *
   * A date-only field stores its value at noon UTC, which is the timestamp the
   * card formats.
   *
   * @see \Drupal\Core\Datetime\DrupalDateTime::setDefaultDateTime()
   */
  private function formatted(string $date): string {
    return $this->container->get(DateFormatterInterface::class)
      ->format((int) strtotime($date . ' 12:00:00 UTC'), AlbumMetaHooks::DATE_FORMAT);
  }

}
