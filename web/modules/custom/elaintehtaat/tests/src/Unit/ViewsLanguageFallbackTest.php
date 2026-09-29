<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the site's listings show content missing a translation.
 *
 * Most content is only in Finnish. A listing filtered on the content language
 * shows nothing of it on the English and Swedish pages, so the listings take
 * one row per entity, its original, and render it in the page's language,
 * which falls back to the original where there is no translation. Saving one
 * of these views in the UI can bring the language filter back; this catches it.
 *
 * @see \Drupal\elaintehtaat\Hook\FallbackLanguageHooks
 */
#[Group('elaintehtaat')]
class ViewsLanguageFallbackTest extends UnitTestCase {

  /**
   * The rendering language that falls back to the original.
   */
  private const string INTERFACE_LANGUAGE = '***LANGUAGE_language_interface***';

  /**
   * The filter value that drops rows not in the page's language.
   */
  private const string CONTENT_LANGUAGE = '***LANGUAGE_language_content***';

  /**
   * The front-end views listing translatable content.
   */
  public static function providerViews(): array {
    return [
      ['albums'],
      ['projects'],
      ['project_albums'],
      ['recent_media'],
      ['related_albums'],
      ['taxonomy_term'],
    ];
  }

  /**
   * A view takes the original of each entity and renders it translated.
   */
  #[DataProvider('providerViews')]
  public function testViewFallsBackToOriginal(string $id): void {
    $file = dirname($this->root) . "/config/sync/views.view.$id.yml";
    $this->assertFileExists($file);
    $view = Yaml::decode((string) file_get_contents($file));

    $default = $view['display']['default']['display_options'];
    $this->assertSame(self::INTERFACE_LANGUAGE, $default['rendering_language'] ?? NULL, "$id: default display renders in the interface language");
    $this->assertTrue($this->hasDefaultTranslationFilter($default['filters']), "$id: default display filters on the default translation");

    foreach ($view['display'] as $display_id => $display) {
      $options = $display['display_options'] ?? [];
      if (isset($options['rendering_language'])) {
        $this->assertSame(self::INTERFACE_LANGUAGE, $options['rendering_language'], "$id.$display_id: renders in the interface language");
      }
      if (isset($options['filters'])) {
        $this->assertTrue($this->hasDefaultTranslationFilter($options['filters']), "$id.$display_id: filters on the default translation");
      }
      $this->assertStringNotContainsString(self::CONTENT_LANGUAGE, serialize($options['filters'] ?? []), "$id.$display_id: does not filter on the content language");
    }
  }

  /**
   * Whether the filters keep only the default translation of the base table.
   */
  private function hasDefaultTranslationFilter(array $filters): bool {
    foreach ($filters as $filter) {
      if ($filter['field'] === 'default_langcode' && $filter['relationship'] === 'none' && $filter['value'] === '1') {
        return TRUE;
      }
    }
    return FALSE;
  }

}
