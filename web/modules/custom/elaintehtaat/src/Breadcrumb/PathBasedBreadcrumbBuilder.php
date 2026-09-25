<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Breadcrumb;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\system\PathBasedBreadcrumbBuilder as CoreBreadcrumbBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Path-based breadcrumbs that end with the current page and skip the front.
 *
 * The current page is appended as an unlinked last item, as in the design.
 *
 * Core walks the raw request path, so on a language-prefixed URL such as
 * /en/project/album it also visits /en. Inbound processing turns that into
 * "/", then into the configured front page, and finally into the front page's
 * system path. Core only excludes the configured front page path itself, so
 * the front page shows up as a crumb between Home and the first real parent.
 * Excluding the resolved system path as well drops that crumb.
 */
final class PathBasedBreadcrumbBuilder extends CoreBreadcrumbBuilder {

  public function __construct(
    RequestContext $context,
    AccessManagerInterface $access_manager,
    AccessAwareRouterInterface $router,
    InboundPathProcessorInterface $path_processor,
    ConfigFactoryInterface $config_factory,
    TitleResolverInterface $title_resolver,
    AccountInterface $current_user,
    CurrentPathStack $current_path,
    PathMatcherInterface $path_matcher,
    private readonly AliasManagerInterface $aliasManager,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($context, $access_manager, $router, $path_processor, $config_factory, $title_resolver, $current_user, $current_path, $path_matcher);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = parent::build($route_match);
    // The front page has no breadcrumb at all.
    if (!$breadcrumb->getLinks()) {
      return $breadcrumb;
    }
    // The last item is the current page, so the result depends on the whole
    // path, not only on its parents.
    $breadcrumb->addCacheContexts(['url.path']);
    $request = $this->requestStack->getCurrentRequest();
    $route = $route_match->getRouteObject();
    if (!$request || !$route) {
      return $breadcrumb;
    }
    $title = $this->titleResolver->getTitle($request, $route);
    if ($title === NULL || $title === '') {
      return $breadcrumb;
    }
    if ($title instanceof \Stringable && !$title instanceof MarkupInterface) {
      $title = (string) $title;
    }
    // The title usually comes from a route parameter, such as the node.
    foreach ($route_match->getParameters()->all() as $parameter) {
      if ($parameter instanceof CacheableDependencyInterface) {
        $breadcrumb->addCacheableDependency($parameter);
      }
    }
    return $breadcrumb->addLink(Link::createFromRoute($title, '<none>'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestForPath($path, array $exclude) {
    $front = $this->config->get('page.front');
    if (is_string($front) && $front !== '') {
      $exclude[$this->aliasManager->getPathByAlias($front)] = TRUE;
    }
    return parent::getRequestForPath($path, $exclude);
  }

}
