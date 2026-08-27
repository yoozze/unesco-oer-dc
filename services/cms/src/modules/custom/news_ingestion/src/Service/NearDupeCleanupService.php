<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\news_ingestion\NearDupeKeys;
use Drupal\node\NodeInterface;

/**
 * Backfills near-dupe keys and collapses existing near-duplicate news nodes.
 *
 * Keeps the earliest-created node in each group (first-seen) and merges AoA.
 */
final class NearDupeCleanupService {

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly SourcePrerequisiteService $prerequisites,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    /**
     * @return array{backfilled: int, groups: int, deleted: int, kept: int}
     */
    public function cleanup(?string $sourceKey = 'eventregistry', bool $dry_run = FALSE): array {
        $stats = ['backfilled' => 0, 'groups' => 0, 'deleted' => 0, 'kept' => 0];
        $storage = $this->entityTypeManager->getStorage('node');

        $query = $storage->getQuery()
            ->accessCheck(FALSE)
            ->condition('type', 'news');
        if ($sourceKey) {
            $term = $this->prerequisites->loadTermByKey($sourceKey);
            if (!$term) {
                throw new \RuntimeException("Source term \"$sourceKey\" not found.");
            }

            $query->condition('field_news_source', (int) $term->id());
        }

        $nids = $query->execute();
        if (!$nids) {
            return $stats;
        }

        /** @var \Drupal\node\NodeInterface[] $nodes */
        $nodes = $storage->loadMultiple($nids);

        // Backfill keys.
        foreach ($nodes as $node) {
            if ($this->backfillKeys($node, $dry_run)) {
                $stats['backfilled']++;
            }
        }

        // Reload after backfill so groupings see saved keys.
        if (!$dry_run && $stats['backfilled']) {
            $nodes = $storage->loadMultiple($nids);
        }

        $by_url = [];
        $by_title = [];
        foreach ($nodes as $nid => $node) {
            $url_key = $node->hasField('field_url_norm') ? (string) ($node->get('field_url_norm')->value ?? '') : '';
            $title_key = $node->hasField('field_title_norm') ? (string) ($node->get('field_title_norm')->value ?? '') : '';
            if ($url_key !== '') {
                $by_url[$url_key][$nid] = $node;
            }

            if ($title_key !== '') {
                $by_title[$title_key][$nid] = $node;
            }
        }

        $deleted_ids = [];
        foreach ([$by_url, $by_title] as $groups) {
            foreach ($groups as $group) {
                if (count($group) < 2) {
                    continue;
                }

                // Skip nodes already deleted in a previous group.
                $group = array_filter($group, static fn(NodeInterface $n) => !isset($deleted_ids[(int) $n->id()]));
                if (count($group) < 2) {
                    continue;
                }

                $stats['groups']++;
                $result = $this->collapseGroup($group, $dry_run);
                $stats['kept'] += $result['kept'];
                $stats['deleted'] += $result['deleted'];
                foreach ($result['deleted_ids'] as $id) {
                    $deleted_ids[$id] = TRUE;
                }
            }
        }

        $this->loggerFactory->get('news_ingestion')->notice(
            'Near-dupe cleanup: backfilled=@b groups=@g kept=@k deleted=@d dry_run=@dry',
            [
                '@b' => $stats['backfilled'],
                '@g' => $stats['groups'],
                '@k' => $stats['kept'],
                '@d' => $stats['deleted'],
                '@dry' => $dry_run ? 'yes' : 'no',
            ]
        );

        return $stats;
    }

    private function backfillKeys(NodeInterface $node, bool $dry_run): bool {
        $dirty = FALSE;
        $url = '';
        if ($node->hasField('field_url') && !$node->get('field_url')->isEmpty()) {
            $url = (string) $node->get('field_url')->uri;
        }

        $url_key = NearDupeKeys::urlKey($url);
        $title_key = NearDupeKeys::titleKey($node->label());

        if ($url_key !== '' && $node->hasField('field_url_norm') && (string) ($node->get('field_url_norm')->value ?? '') !== $url_key) {
            $node->set('field_url_norm', $url_key);
            $dirty = TRUE;
        }

        if ($title_key !== '' && $node->hasField('field_title_norm') && (string) ($node->get('field_title_norm')->value ?? '') !== $title_key) {
            $node->set('field_title_norm', $title_key);
            $dirty = TRUE;
        }

        if ($dirty && !$dry_run) {
            $node->save();
        }

        return $dirty;
    }

    /**
     * @param array<int, \Drupal\node\NodeInterface> $group
     *
     * @return array{kept: int, deleted: int, deleted_ids: int[]}
     */
    private function collapseGroup(array $group, bool $dry_run): array {
        uasort($group, static function (NodeInterface $a, NodeInterface $b): int {
            $cmp = $a->getCreatedTime() <=> $b->getCreatedTime();
            return $cmp !== 0 ? $cmp : ((int) $a->id() <=> (int) $b->id());
        });
        /** @var \Drupal\node\NodeInterface $keeper */
        $keeper = reset($group);
        $dupes = array_slice($group, 1, NULL, TRUE);

        // Merge AoA from dupes into keeper.
        $aoa = [];
        foreach ($keeper->get('field_area_of_action') as $item) {
            if ($item->target_id) {
                $aoa[(int) $item->target_id] = (int) $item->target_id;
            }
        }

        foreach ($dupes as $dupe) {
            foreach ($dupe->get('field_area_of_action') as $item) {
                if ($item->target_id) {
                    $aoa[(int) $item->target_id] = (int) $item->target_id;
                }
            }
        }

        $deleted_ids = [];
        if (!$dry_run) {
            $refs = [];
            foreach ($aoa as $tid) {
                $refs[] = ['target_id' => $tid];
            }

            $keeper->set('field_area_of_action', $refs);
            if ($keeper->hasField('moderation_state')) {
                $keeper->set('moderation_state', 'published');
            }

            $keeper->setPublished();
            $keeper->save();

            $storage = $this->entityTypeManager->getStorage('node');
            foreach ($dupes as $dupe) {
                $deleted_ids[] = (int) $dupe->id();
            }

            $storage->delete($dupes);
        } else {
            foreach ($dupes as $dupe) {
                $deleted_ids[] = (int) $dupe->id();
            }
        }

        return [
            'kept' => 1,
            'deleted' => count($dupes),
            'deleted_ids' => $deleted_ids,
        ];
    }
}
