<?php

namespace Drupal\custom_routes\Routing;

// use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase /* implements ContainerInjectionInterface */ {
    protected function alterRoutes(RouteCollection $collection) {
        // Reserved for future route alterations.
    }
}
