<?php

namespace Drupal\elaintehtaat_theme\Hook;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the Eläintehtaat theme.
 */
class ElaintehtaatThemeHooks {

  /**
   * Route of the full-screen media page, which is rendered without chrome.
   */
  public const string MEDIA_PAGE_ROUTE = 'elaintehtaat.media_page';

  /**
   * Constructs the theme hook implementations.
   */
  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for page templates.
   *
   * The media page has no masthead or footer, see page--media.html.twig.
   */
  #[Hook('theme_suggestions_page_alter')]
  public function themeSuggestionsPageAlter(array &$suggestions, array $variables): void {
    if ($this->isMediaPage()) {
      $suggestions[] = 'page__media';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for html templates.
   *
   * The body class lets the media page stop the document from scrolling.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if ($this->isMediaPage()) {
      $variables['attributes']['class'][] = 'page--media';
    }
  }

  /**
   * Whether the current route is the media page.
   */
  protected function isMediaPage(): bool {
    return $this->routeMatch->getRouteName() === self::MEDIA_PAGE_ROUTE;
  }

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

  /**
   * Implements hook_preprocess_HOOK() for views_view__related_albums.
   *
   * The view lists the other albums of the project the viewed album belongs
   * to. The section header names that project and links to it, so pass the
   * project node (in the current language) to the template. The block varies
   * by URL, and the rows already carry the project's cache tags through the
   * project label formatter.
   */
  #[Hook('preprocess_views_view__related_albums')]
  public function preprocessViewsViewRelatedAlbums(array &$variables): void {
    $variables['project'] = NULL;

    $album = $this->routeMatch->getParameter('node');
    if (!$album instanceof NodeInterface || !$album->hasField('field_project')) {
      return;
    }
    $project = $album->get('field_project')->entity;
    if ($project instanceof NodeInterface) {
      $variables['project'] = $this->entityRepository->getTranslationFromContext($project);
    }
  }

}
