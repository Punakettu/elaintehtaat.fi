<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\simple_sitemap\Exception\SkipElementException;

/**
 * Keeps the front page in the sitemap once, as the site root.
 *
 * The front page is a Canvas page, and Canvas pages are indexed, so without
 * this the sitemap lists it at its own alias (/front, /en/front, ...) next to
 * the "/" custom link that already stands for it.
 *
 * @see simple_sitemap.custom_links.default
 */
final class SitemapHooks {

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private AliasManagerInterface $aliasManager,
  ) {}

  /**
   * Implements hook_simple_sitemap_entity_process().
   */
  #[Hook('simple_sitemap_entity_process')]
  public function entityProcess(ContentEntityInterface $entity): void {
    if ($entity->isNew() || !$entity->hasLinkTemplate('canonical')) {
      return;
    }
    $front = (string) $this->configFactory->get('system.site')->get('page.front');
    if ($front === '') {
      return;
    }
    $front_path = $this->aliasManager->getPathByAlias($front, $entity->language()->getId());
    if ('/' . $entity->toUrl()->getInternalPath() === $front_path) {
      throw new SkipElementException();
    }
  }

}
