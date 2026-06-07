<?php

use Drupal\node\NodeInterface;

/**
 * OER Observatory configuration from CMS content.
 */

/**
 * Loads the OER Observatory node by URL alias.
 *
 * @return \Drupal\node\NodeInterface|null
 */
function unesco_oer_dc_observatory_get_node(): ?NodeInterface {
    static $cached = NULL;

    if ($cached !== NULL) {
        return $cached ?: NULL;
    }

    $path = \Drupal::service('path_alias.manager')->getPathByAlias('/oer-observatory');
    if (!preg_match('#^/node/(\d+)$#', $path, $matches)) {
        $cached = FALSE;
        return NULL;
    }

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($matches[1]);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'oer_observatory') {
        $cached = FALSE;
        return NULL;
    }

    $cached = $node;
    return $node;
}

/**
 * Builds observatory config from a node for Twig and JavaScript.
 *
 * @param \Drupal\node\NodeInterface $node
 *   OER Observatory node.
 *
 * @return array
 *   Observatory configuration.
 */
function unesco_oer_dc_observatory_build_config(NodeInterface $node): array {
    $intro_item = $node->get('field_areas_of_action_intro')->first();
    $intro = [
        'summary' => $intro_item ? trim($intro_item->summary ?? '') : '',
        'body' => $intro_item ? $intro_item->processed : '',
    ];

    $views = [];
    $view_paragraphs = $node->get('field_observatory_views')->referencedEntities();

    foreach ($view_paragraphs as $view_paragraph) {
        $view_id = trim($view_paragraph->get('field_view_id')->value ?? '');
        if ($view_id === '') {
            continue;
        }

        $areas = [];
        $embed_paragraphs = $view_paragraph->get('field_area_embeds')->referencedEntities();

        foreach ($embed_paragraphs as $embed_paragraph) {
            $url = trim($embed_paragraph->get('field_iframe_url')->value ?? '');
            if ($url === '') {
                continue;
            }

            $term = $embed_paragraph->get('field_area')->entity;
            if (!$term) {
                continue;
            }

            $areas[] = [
                'tid' => (int) $term->id(),
                'name' => $term->getName(),
                'url' => $url,
                'weight' => (int) $term->getWeight(),
            ];
        }

        usort($areas, static function (array $a, array $b): int {
            return $a['weight'] <=> $b['weight'];
        });

        $description_item = $view_paragraph->get('field_description')->first();
        $description = $description_item ? $description_item->processed : '';

        $views[$view_id] = [
            'label' => $view_paragraph->get('field_view_label')->value ?? $view_id,
            'aspectRatio' => $view_paragraph->get('field_aspect_ratio')->value ?: '1440/900',
            'description' => $description,
            'areas' => $areas,
        ];
    }

    if ($views === []) {
        return [
            'areasOfActionIntro' => ['summary' => '', 'body' => ''],
            'defaultView' => '',
            'defaultArea' => 0,
            'views' => [],
        ];
    }

    $default_view = array_key_first($views);
    $default_area = $views[$default_view]['areas'][0]['tid'] ?? 0;

    return [
        'areasOfActionIntro' => $intro,
        'defaultView' => $default_view,
        'defaultArea' => $default_area,
        'views' => $views,
    ];
}

/**
 * Builds observatory config for data-options (JS), without HTML descriptions.
 *
 * @param array $observatory
 *   Full observatory config.
 *
 * @return array
 *   JS-safe observatory config.
 */
function unesco_oer_dc_observatory_js_config(array $observatory): array {
    $views = [];

    foreach ($observatory['views'] as $view_id => $view) {
        $views[$view_id] = [
            'label' => $view['label'],
            'aspectRatio' => $view['aspectRatio'],
            'areas' => array_map(static function (array $area): array {
                return [
                    'tid' => $area['tid'],
                    'name' => $area['name'],
                    'url' => $area['url'],
                ];
            }, $view['areas']),
        ];
    }

    return [
        'defaultView' => $observatory['defaultView'],
        'defaultArea' => $observatory['defaultArea'],
        'views' => $views,
    ];
}

/**
 * Resolves current view and area from the request query string.
 *
 * @param array $observatory
 *   Observatory configuration.
 *
 * @return array{view: string, area: int}
 */
function unesco_oer_dc_observatory_resolve_selection(array $observatory): array {
    $view = \Drupal::request()->query->get('view');
    if (!isset($observatory['views'][$view])) {
        $view = $observatory['defaultView'];
    }

    $areas = $observatory['views'][$view]['areas'] ?? [];
    $area_tids = array_column($areas, 'tid');
    $area = (int) (\Drupal::request()->query->get('area') ?? $observatory['defaultArea']);

    if (!in_array($area, $area_tids, TRUE)) {
        $area = $area_tids[0] ?? 0;
    }

    return [
        'view' => $view,
        'area' => $area,
    ];
}

/**
 * Finds iframe settings for the current view and area.
 *
 * @param array $observatory
 *   Observatory configuration.
 * @param string $view
 *   View ID.
 * @param int $area_tid
 *   Area taxonomy term ID.
 *
 * @return array{src: string, aspectRatio: string}
 */
function unesco_oer_dc_observatory_iframe_for_selection(array $observatory, string $view, int $area_tid): array {
    $view_config = $observatory['views'][$view] ?? [];
    $iframe = [
        'src' => '',
        'aspectRatio' => $view_config['aspectRatio'] ?? '1440/900',
    ];

    foreach ($view_config['areas'] ?? [] as $area) {
        if ($area['tid'] === $area_tid) {
            $iframe['src'] = $area['url'];
            break;
        }
    }

    return $iframe;
}

/**
 * Returns observatory config for the OER Observatory page.
 *
 * @return array
 *   Observatory config passed to Twig and JavaScript.
 */
function unesco_oer_dc_observatory_config(): array {
    $node = unesco_oer_dc_observatory_get_node();
    if (!$node) {
        return [
            'defaultView' => '',
            'defaultArea' => 0,
            'views' => [],
        ];
    }

    return unesco_oer_dc_observatory_build_config($node);
}
