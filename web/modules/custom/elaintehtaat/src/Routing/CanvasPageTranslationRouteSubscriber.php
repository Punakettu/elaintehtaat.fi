<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Renders the canvas page translation UI in the admin theme.
 *
 * Content translation copies the admin status of an entity's edit route onto
 * its translation routes. The canvas page edit route is the Canvas editor,
 * which is not an admin route, so /page/{id}/translations and the add, edit
 * and delete translation forms end up in the front-end theme. The front-end
 * theme does not style these pages, while the admin theme already does.
 */
final class CanvasPageTranslationRouteSubscriber extends RouteSubscriberBase {

  public const string ROUTE_PREFIX = 'entity.canvas_page.content_translation_';

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection->all() as $name => $route) {
      if (str_starts_with($name, self::ROUTE_PREFIX)) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Content translation sets the admin status at priority -210, so this has
    // to run after it.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -220];
    return $events;
  }

}
