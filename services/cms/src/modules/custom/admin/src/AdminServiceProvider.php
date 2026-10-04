<?php

namespace Drupal\admin;

use Drupal\admin\Menu\TranslatableMenuLinkManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Swaps the menu link manager for translation-aware URL resolution.
 */
class AdminServiceProvider extends ServiceProviderBase {

    /**
     * {@inheritdoc}
     */
    public function alter(ContainerBuilder $container): void {
        if ($container->hasDefinition('plugin.manager.menu.link')) {
            $container->getDefinition('plugin.manager.menu.link')
                ->setClass(TranslatableMenuLinkManager::class);
        }
    }
}
