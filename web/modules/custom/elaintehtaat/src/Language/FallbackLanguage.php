<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Language;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Tells when an entity is shown in a language other than the page's.
 *
 * Most content is only in Finnish. On the English and Swedish pages the
 * entity repository falls back to the Finnish original, and the text shown
 * must then say it is Finnish with a lang attribute.
 *
 * @see \Drupal\Core\Entity\EntityRepositoryInterface::getTranslationFromContext()
 */
final readonly class FallbackLanguage {

  /**
   * The cache context a build using fallbackLangcode() varies by.
   */
  public const string CACHE_CONTEXT = 'languages:' . LanguageInterface::TYPE_CONTENT;

  public function __construct(
    private LanguageManagerInterface $languageManager,
  ) {}

  /**
   * The language of an entity shown in place of a missing translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity, already translated from context.
   *
   * @return string|null
   *   The entity's langcode when it differs from the content language of the
   *   page, NULL when it is in the page's language or has no language.
   */
  public function fallbackLangcode(EntityInterface $entity): ?string {
    // Not translatable also covers entities in a locked language such as
    // "Not specified", and a site with one language.
    if (!$entity instanceof TranslatableInterface || !$entity->isTranslatable()) {
      return NULL;
    }
    $langcode = $entity->language()->getId();
    $page_langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    return $langcode === $page_langcode ? NULL : $langcode;
  }

}
