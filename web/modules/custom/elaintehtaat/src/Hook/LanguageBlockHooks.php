<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Removes contextual links from the language switcher block.
 */
final class LanguageBlockHooks {

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter() for language blocks.
   */
  #[Hook('block_view_language_block_alter')]
  public function blockViewLanguageBlockAlter(array &$build, BlockPluginInterface $block): void {
    unset($build['#contextual_links']);
  }

}
