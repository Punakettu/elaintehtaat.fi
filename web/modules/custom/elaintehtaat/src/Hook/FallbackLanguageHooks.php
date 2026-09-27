<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\elaintehtaat\Language\FallbackLanguage;

/**
 * Marks text shown in place of a missing translation with its language.
 *
 * An entity without a translation in the page's language is shown in its
 * original language, mostly Finnish. Its translatable text fields then get a
 * lang attribute, and so does the page title when the entity is the page's
 * own. UI strings the templates print around the fields stay in the page's
 * language, so the attribute is not put on the whole entity.
 *
 * @see \Drupal\elaintehtaat\Language\FallbackLanguage
 */
final readonly class FallbackLanguageHooks {

  /**
   * The types of the fields that hold an entity's text.
   *
   * Other translatable fields, such as the author and the dates, are metadata
   * that reads the same in every language.
   */
  private const array TEXT_FIELD_TYPES = [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ];

  /**
   * The route of the media page, whose own entity is the media item.
   */
  private const string MEDIA_PAGE_ROUTE = 'elaintehtaat.media_page';

  public function __construct(
    private FallbackLanguage $fallbackLanguage,
    private EntityRepositoryInterface $entityRepository,
    private RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof FieldableEntityInterface || !$entity instanceof TranslatableInterface || !$entity->isTranslatable()) {
      return;
    }

    // The render cache keys an entity build by the entity's own language
    // only, so the same Finnish build would otherwise serve the Finnish page
    // and the English one, with or without the attribute.
    // Merge instead of applyTo(): applyTo() would wipe the tags and contexts
    // the view builder has already put on $build.
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts([FallbackLanguage::CACHE_CONTEXT])
      ->applyTo($build);

    $langcode = $this->fallbackLanguage->fallbackLangcode($entity);
    if ($langcode === NULL) {
      return;
    }
    foreach (Element::children($build) as $name) {
      if (($build[$name]['#theme'] ?? NULL) !== 'field' || !$entity->hasField($name)) {
        continue;
      }
      $definition = $entity->get($name)->getFieldDefinition();
      if ($definition->isTranslatable() && in_array($definition->getType(), self::TEXT_FIELD_TYPES, TRUE)) {
        $build[$name]['#attributes']['lang'] = $langcode;
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for page_title.
   *
   * The title block is rendered per URL, and the URL carries the language, so
   * the attribute needs no cache context of its own.
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(array &$variables): void {
    $entity = $this->pageEntity();
    if ($entity === NULL) {
      return;
    }
    $langcode = $this->fallbackLanguage->fallbackLangcode($this->entityRepository->getTranslationFromContext($entity));
    if ($langcode !== NULL) {
      $variables['title_attributes']['lang'] = $langcode;
    }
  }

  /**
   * The entity whose page is being shown, if any.
   */
  private function pageEntity(): ?EntityInterface {
    $route_name = (string) $this->routeMatch->getRouteName();
    if ($route_name === self::MEDIA_PAGE_ROUTE) {
      $parameter = 'media';
    }
    elseif (preg_match('/^entity\.([a-z_]+)\.canonical$/', $route_name, $matches)) {
      $parameter = $matches[1];
    }
    else {
      return NULL;
    }
    $entity = $this->routeMatch->getParameter($parameter);

    return $entity instanceof EntityInterface ? $entity : NULL;
  }

}
