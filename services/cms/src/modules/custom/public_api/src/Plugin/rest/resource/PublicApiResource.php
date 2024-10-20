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

        foreach ($content_type_ids as $content_type_id) {
            $content_type = $content_types[$content_type_id];

            // Count the nodes.
            $count = \Drupal::entityQuery('node')
                ->accessCheck(FALSE)
                ->condition('type', $content_type_id)
                ->count()
                ->execute();

            // Get the latest nodes.
            $query = \Drupal::entityQuery('node')
                ->accessCheck(FALSE)
                ->condition('type', $content_type_id)
                ->sort('created', 'DESC')
                ->range(0, 5)
                ->execute();
            $nodes = Node::loadMultiple($query);

            $latest = [];
            foreach ($nodes as $node) {
                $latest[] = [
                    'id' => $node->id(),
                    'title' => $node->getTitle(),
                    'created' => $node->getCreatedTime(),
                    'url' => $node->toUrl()->setAbsolute()->toString(),
                ];
            }

            $report['content'][] = [
                'type' => $content_type->id(),
                'name' => $content_type->label(),
                'count' => $count,
                'latest' => $latest,
            ];
        }

        return new ResourceResponse($report);
    }
}
