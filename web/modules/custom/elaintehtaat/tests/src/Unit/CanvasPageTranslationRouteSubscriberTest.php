<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Unit;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\elaintehtaat\Routing\CanvasPageTranslationRouteSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests that canvas page translation routes become admin routes.
 */
#[Group('elaintehtaat')]
class CanvasPageTranslationRouteSubscriberTest extends UnitTestCase {

  /**
   * Only the canvas page translation routes are altered.
   */
  public function testAlterRoutes(): void {
    $collection = new RouteCollection();
    $translation_routes = [
      'entity.canvas_page.content_translation_overview',
      'entity.canvas_page.content_translation_add',
      'entity.canvas_page.content_translation_edit',
      'entity.canvas_page.content_translation_delete',
    ];
    foreach ($translation_routes as $name) {
      $collection->add($name, new Route('/page/{canvas_page}/translations', [], [], ['_admin_route' => FALSE]));
    }
    $collection->add('entity.canvas_page.canonical', new Route('/page/{canvas_page}', [], [], ['_admin_route' => FALSE]));
    $collection->add('entity.node.content_translation_overview', new Route('/node/{node}/translations', [], [], ['_admin_route' => FALSE]));

    $subscriber = new CanvasPageTranslationRouteSubscriber();
    $subscriber->onAlterRoutes(new RouteBuildEvent($collection));

    foreach ($translation_routes as $name) {
      $this->assertTrue($collection->get($name)->getOption('_admin_route'), $name);
    }
    $this->assertFalse($collection->get('entity.canvas_page.canonical')->getOption('_admin_route'));
    $this->assertFalse($collection->get('entity.node.content_translation_overview')->getOption('_admin_route'));
  }

  /**
   * The subscriber runs after content translation's own subscriber.
   */
  public function testPriority(): void {
    $events = CanvasPageTranslationRouteSubscriber::getSubscribedEvents();
    $this->assertLessThan(-210, $events[RoutingEvents::ALTER][1]);
  }

}
