<?php

/**
 * OER Observatory page configuration.
 */

/**
 * Returns embed configuration for the OER Observatory page.
 *
 * @return array
 *   Observatory config passed to Twig and JavaScript via data-options.
 */
function unesco_oer_dc_observatory_config(): array {
    $news_keys = [
        'f23b14f7-60d1-4786-9ca4-f8fdd56682ec',
        '9365e3e0-4914-41b5-822a-9e373e2fd3fa',
        '1ebf4034-deab-452e-a0e4-edf356eb2139',
        '50c9b223-8eec-49ee-b6cb-46631ed37dcf',
        'f9d34ef5-0d04-4e2e-8fc7-ade59aef9801',
    ];

    $dashboard_keys = [
        'c0f2ee30-a7da-11ef-bb8e-094d83929987',
        '0dbc27c0-a46d-11ef-bb8e-094d83929987',
        '37487260-a46d-11ef-bb8e-094d83929987',
        '5dab4270-a46d-11ef-bb8e-094d83929987',
        '7f6bb390-a46d-11ef-bb8e-094d83929987',
    ];

    $metrics_pilots = ['OER1', 'OER2', 'OER3', 'OER4', 'OER5'];

    $dashboard_embed_suffix = '?embed=true&_g=(refreshInterval:(pause:!t,value:60000),time:(from:now-150y,to:now))&_a=()';

    return [
        'defaultView' => 'news',
        'defaultArea' => 1,
        'views' => [
            'news' => [
                'aspectRatio' => '1440/1280',
                'embeds' => array_map(
                    static fn(string $key): string => "https://news-widget.pages.dev/news?sdg=4&topicKey={$key}",
                    $news_keys
                ),
            ],
            'dashboard' => [
                'aspectRatio' => '1440/900',
                'embeds' => array_map(
                    static fn(string $key): string => "https://public.midas.ijs.si/kibana-sgd/app/dashboards#/view/{$key}{$dashboard_embed_suffix}",
                    $dashboard_keys
                ),
            ],
            'metrics' => [
                'aspectRatio' => '1440/900',
                'embeds' => array_map(
                    static fn(string $pilot): string => "https://news-widget.pages.dev/education/radial?pilot={$pilot}",
                    $metrics_pilots
                ),
            ],
        ],
    ];
}
