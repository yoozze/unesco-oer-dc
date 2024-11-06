<?php

namespace Drupal\public_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\node\Entity\Node;

/**
 * Provides a resource for public API.
 *
 * @RestResource(
 *   id = "public_api",
 *   label = @Translation("Public API"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/report"
 *   }
 * )
 */
class PublicApiResource extends ResourceBase {

    /**
     * Responds to GET requests.
     *
     * Returns a report.
     *
     * @return \Drupal\rest\ResourceResponse
     *   The response containing the report.
     */
    public function get() {
        $report = [
            'title' => 'UNESCO OER Dynamic Coalition Portal Report',
            'ts' => time(),
            'content' => [],
        ];

        $content_type_ids = [
            'resource',
            'event',
            'news',
            'activity'
        ];
        $content_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();

        // Get url parameter 'from' timestamp.
        $from = \Drupal::request()->query->get('from');
        if ($from) {
            $from = (int) $from;
        } else {
            $from = 0;
        }

        // Get url parameter 'to' timestamp.
        $to = \Drupal::request()->query->get('to');
        if ($to) {
            $to = (int) $to;
        } else {
            $to = time();
        }

        foreach ($content_type_ids as $content_type_id) {
            $content_type = $content_types[$content_type_id];

            // Count nodes.
            $count = \Drupal::entityQuery('node')
                ->accessCheck(FALSE)
                ->condition('type', $content_type_id)
                ->count()
                ->execute();

            // Get nodes.
            $query = \Drupal::entityQuery('node')
                ->accessCheck(FALSE)
                ->condition('type', $content_type_id)
                ->condition('created', $from, '>=')
                ->condition('created', $to, '<=')
                ->sort('created', 'DESC')
                // ->range(0, 5)
                ->execute();
            $nodes = Node::loadMultiple($query);

            $items = [];
            foreach ($nodes as $node) {

                // Get country.
                $country = NULL;
                $country_id = $node->get('field_country')->target_id;
                if ($country_id) {
                    $country_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($country_id);
                    if ($country_term) {
                        $country = $country_term->get('name')->value;
                    }
                }

                // Get area of action items.
                $areas_of_action = [];
                $areas_of_action_ids = $node->get('field_area_of_action')->getValue();
                foreach ($areas_of_action_ids as $area_of_action_id) {
                    $area_of_action_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($area_of_action_id['target_id']);
                    if ($area_of_action_term) {
                        $areas_of_action[] = $area_of_action_term->get('name')->value;
                    }
                }

                $items[] = [
                    'id' => $node->id(),
                    'title' => $node->getTitle(),
                    'description' => $node->get('field_description')->value,
                    'country' => $country,
                    'area_of_action' => $areas_of_action,
                    'created' => $node->getCreatedTime(),
                    'url' => $node->toUrl()->setAbsolute()->toString(),
                ];
            }

            $report['content'][] = [
                'type' => $content_type->id(),
                'name' => $content_type->label(),
                'count' => $count,
                'items' => $items,
            ];
        }

        return new ResourceResponse($report);
    }
}
