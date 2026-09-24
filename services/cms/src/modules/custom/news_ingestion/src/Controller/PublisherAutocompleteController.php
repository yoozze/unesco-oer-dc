<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete suggestions for publisher (field_source) values.
 */
final class PublisherAutocompleteController extends ControllerBase {

    private const LIMIT = 15;

    private const MIN_LENGTH = 1;

    /**
     * Bundles that store field_source and may be requested via ?type=.
     */
    private const ALLOWED_TYPES = [
        'news',
        'resource',
    ];

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
     *
     * Query params:
     * - q: search string (required)
     * - type: optional bundle (news|resource). When omitted, defaults to
     *   published news only (public news listing). When set, filters by that
     *   bundle; unpublished values are included only for users with
     *   "access content overview".
     */
    public function autocomplete(Request $request): JsonResponse {
        $string = trim((string) $request->query->get('q', ''));
        if (mb_strlen($string) < self::MIN_LENGTH) {
            return new JsonResponse([]);
        }

        $type_param = trim((string) $request->query->get('type', ''));
        $type = in_array($type_param, self::ALLOWED_TYPES, TRUE) ? $type_param : NULL;

        // Invalid explicit type → no suggestions (do not fall back to all).
        if ($type_param !== '' && $type === NULL) {
            return new JsonResponse([]);
        }

        $bundle = $type ?? 'news';
        $published_only = $type === NULL
            || !$this->currentUser()->hasPermission('access content overview');

        $like = '%' . $this->database->escapeLike($string) . '%';

        $query = $this->database->select('node__field_source', 'fs');
        $query->fields('fs', ['field_source_value']);
        $query->distinct();
        $query->join(
            'node_field_data',
            'n',
            'n.nid = fs.entity_id AND n.langcode = fs.langcode',
        );
        $query->condition('n.type', $bundle);
        if ($published_only) {
            $query->condition('n.status', 1);
        }

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
