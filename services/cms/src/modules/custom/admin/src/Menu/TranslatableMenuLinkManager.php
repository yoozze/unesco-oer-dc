<?php

namespace Drupal\admin\Menu;

use Drupal\admin\Plugin\Menu\TranslatableMenuLinkContent;
use Drupal\Core\Menu\MenuLinkManager;

/**
 * Ensures custom menu links use the translation-aware plugin class.
 */
class TranslatableMenuLinkManager extends MenuLinkManager {

    /**
     * {@inheritdoc}
     */
    public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
        $definition = parent::getDefinition($plugin_id, $exception_on_invalid);
        if (is_array($definition) && str_starts_with((string) $plugin_id, 'menu_link_content:')) {
            $definition['class'] = TranslatableMenuLinkContent::class;
        }

        return $definition;
    }
}
