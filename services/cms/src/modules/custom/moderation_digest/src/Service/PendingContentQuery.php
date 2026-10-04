<?php

declare(strict_types=1);

namespace Drupal\moderation_digest\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Loads pending moderated revisions for the digest email.
 *
 * Matches /admin/content/moderated intent: latest revision per node+langcode
 * in draft or review (includes pending revisions of published nodes).
 * Archived and published latest revisions are excluded.
 */
final class PendingContentQuery {

    public const STATES = ['draft', 'review'];

    public function __construct(
        private readonly Connection $database,
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly DateFormatterInterface $dateFormatter,
        private readonly ConfigFactoryInterface $configFactory,
    ) {
    }

    /**
     * Returns pending items and total count.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function getPendingItems(): array {
        $maxItems = (int) $this->configFactory->get('moderation_digest.settings')->get('max_items');
        if ($maxItems < 1) {
            $maxItems = 50;
        }

        $rows = $this->fetchLatestPendingRows();
        $total = count($rows);
        $rows = array_slice($rows, 0, $maxItems);

        $items = [];
        $nodeStorage = $this->entityTypeManager->getStorage('node');
        $bundleLabels = $this->bundleLabels();

        foreach ($rows as $row) {
            $nid = (int) $row['content_entity_id'];
            $vid = (int) $row['content_entity_revision_id'];
            $langcode = (string) $row['langcode'];
            $state = (string) $row['moderation_state'];

            /** @var \Drupal\node\NodeInterface|null $revision */
            $revision = $nodeStorage->loadRevision($vid);
            if (!$revision instanceof NodeInterface) {
                continue;
            }

            if ($revision->hasTranslation($langcode)) {
                $revision = $revision->getTranslation($langcode);
            }

            $author = $revision->getOwner();
            $items[] = [
                'nid' => $nid,
                'vid' => $vid,
                'title' => $revision->label(),
                'type' => $bundleLabels[$revision->bundle()] ?? $revision->bundle(),
                'bundle' => $revision->bundle(),
                'moderation_state' => $state,
                'moderation_state_label' => ucfirst($state),
                'langcode' => $langcode,
                'author' => $author ? $author->getDisplayName() : '',
                'changed' => (int) $revision->getChangedTime(),
                'changed_formatted' => $this->dateFormatter->format((int) $revision->getChangedTime(), 'short'),
                // Edit form loads the latest revision under content moderation.
                'url' => $revision->toUrl('edit-form', ['absolute' => TRUE])->toString(),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @return list<array{content_entity_id: string|int, content_entity_revision_id: string|int, langcode: string, moderation_state: string}>
     */
    private function fetchLatestPendingRows(): array {
        $latest = $this->database->select('content_moderation_state_field_data', 'cms');
        $latest->addField('cms', 'content_entity_id');
        $latest->addField('cms', 'langcode');
        $latest->addExpression('MAX(cms.content_entity_revision_id)', 'max_vid');
        $latest->condition('cms.content_entity_type_id', 'node');
        $latest->condition('cms.workflow', 'editorial');
        $latest->groupBy('cms.content_entity_id');
        $latest->groupBy('cms.langcode');

        $query = $this->database->select('content_moderation_state_field_data', 'cms');
        $query->fields('cms', [
            'content_entity_id',
            'content_entity_revision_id',
            'langcode',
            'moderation_state',
        ]);
        $query->innerJoin(
            $latest,
            'latest',
            'cms.content_entity_id = latest.content_entity_id AND cms.langcode = latest.langcode AND cms.content_entity_revision_id = latest.max_vid'
        );
        $query->condition('cms.content_entity_type_id', 'node');
        $query->condition('cms.workflow', 'editorial');
        $query->condition('cms.moderation_state', self::STATES, 'IN');
        $query->orderBy('cms.content_entity_revision_id', 'DESC');

        return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return array<string, string>
     */
    private function bundleLabels(): array {
        $labels = [];
        foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $type) {
            $labels[$type->id()] = (string) $type->label();
        }
        return $labels;
    }
}
