<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gives media pages pretty URLs nested under their album alias.
 *
 * The media page route is /node/{album}/media/{media}. Albums already have a
 * pathauto alias of the form /<project>/<album>, so a media page is exposed as
 * /<project>/<album>/<media id> without storing any aliases of its own.
 *
 * Inbound, this runs after the core alias processor (priority 100) so it only
 * sees paths that no alias matched. Outbound, it runs before the language
 * prefix is added (priority 100) so the prefix ends up in front of the alias.
 */
final class MediaPagePathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  public function __construct(
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    if (str_starts_with($path, '/node/') || !preg_match('#^(/.+)/(\d+)$#', $path, $matches)) {
      return $path;
    }
    $system_path = $this->aliasManager->getPathByAlias($matches[1]);
    if ($system_path !== $matches[1] && preg_match('#^/node/(\d+)$#', $system_path, $node)) {
      return '/node/' . $node[1] . '/media/' . $matches[2];
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (!empty($options['alias']) || !preg_match('#^/node/(\d+)/media/(\d+)$#', $path, $matches)) {
      return $path;
    }
    $node_path = '/node/' . $matches[1];
    $langcode = isset($options['language']) ? $options['language']->getId() : NULL;
    $alias = $this->aliasManager->getAliasByPath($node_path, $langcode);
    if ($alias === $node_path) {
      return $path;
    }
    return $alias . '/' . $matches[2];
  }

}
