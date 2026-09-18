<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete suggestions for news publisher (field_source) values.
 */
final class PublisherAutocompleteController extends ControllerBase {

    private const LIMIT = 15;

    private const MIN_LENGTH = 1;

    public function __construct(
        private readonly Connection $database,
    ) {
    }

    public static function create(ContainerInterface $container): static {
        return new static(
            $container->get('database'),
        );
    }

    /**
     * Returns distinct publisher titles matching the typed string.
     */
    public function autocomplete(Request $request): JsonResponse {
        $string = trim((string) $request->query->get('q', ''));
        if (mb_strlen($string) < self::MIN_LENGTH) {
            return new JsonResponse([]);
        }

        $like = '%' . $this->database->escapeLike($string) . '%';

        $query = $this->database->select('node__field_source', 'fs');
        $query->fields('fs', ['field_source_value']);
        $query->distinct();
        $query->join(
            'node_field_data',
            'n',
            'n.nid = fs.entity_id AND n.langcode = fs.langcode',
        );
        $query->condition('n.type', 'news');
        $query->condition('n.status', 1);
        $query->condition('fs.deleted', 0);
        $query->where('TRIM(fs.field_source_value) <> :empty', [':empty' => '']);
        $query->condition('fs.field_source_value', $like, 'LIKE');
        $query->orderBy('fs.field_source_value');
        $query->range(0, self::LIMIT);

        $matches = [];
        foreach ($query->execute() as $row) {
            $value = trim((string) $row->field_source_value);
            if ($value === '') {    
                continue;
            }

            $matches[] = [
                'value' => $value,
                'label' => $value,
            ];
        }

        return new JsonResponse($matches);
    }
}
