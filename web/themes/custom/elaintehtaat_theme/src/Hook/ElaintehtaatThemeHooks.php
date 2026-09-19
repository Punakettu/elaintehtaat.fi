<?php

namespace Drupal\elaintehtaat_theme\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;

/**
 * Hook implementations for the Eläintehtaat theme.
 */
class ElaintehtaatThemeHooks {
  /**
   * @file
   * Functions to support theming.
   */

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for links__language_block.
   *
   * Shows each language as its uppercase langcode (FI / EN) while keeping the
   * full language name for assistive technology.
   */
  #[Hook('preprocess_links__language_block')]
  public function preprocessLinksLanguageBlock(array &$variables): void {
    foreach ($variables['links'] as $key => &$item) {
      if (!isset($item['link'])) {
        continue;
      }
      $language = $item['link']['#options']['language'] ?? NULL;
      $langcode = $language instanceof LanguageInterface ? $language->getId() : (string) $key;
      $item['link']['#title'] = [
        '#type' => 'inline_template',
        '#template' => '<span aria-hidden="true">{{ code }}</span><span class="visually-hidden">{{ name }}</span>',
        '#context' => [
          'code' => mb_strtoupper($langcode),
          'name' => $item['link']['#title'],
        ],
      ];
      $item['link']['#options']['attributes']['class'][] = 'lang-toggle__link';
    }
  }

}
