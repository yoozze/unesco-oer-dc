<?php

namespace Drupal\admin\Plugin\Menu;

use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Menu link plugin that resolves URLs from the active content translation.
 *
 * Core only loads title/description from the translated entity; the URL comes
 * from the single-language menu_tree definition. When Link is translatable,
 * read it from the entity in the current language context instead.
 *
 * @see https://www.drupal.org/project/drupal/issues/2867764
 * @see https://www.drupal.org/project/drupal/issues/3625171
 */
class TranslatableMenuLinkContent extends MenuLinkContent {

    /**
     * {@inheritdoc}
     */
    public function getUrlObject($title_attribute = TRUE) {
        if (!$this->languageManager->isMultilingual()) {
            return parent::getUrlObject($title_attribute);
        }

        $url = $this->getEntity()->getUrlObject();
        if ($title_attribute && ($description = $this->getDescription())) {
            $attributes = $url->getOption('attributes') ?: [];
            $attributes['title'] = $description;
            $url->setOption('attributes', $attributes);
        }

        return $url;
    }
}
