<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\news_ingestion\Source\NewsSourceLocator;

/**
 * Orchestrates ingest and purge across registered sources.
 */
final class NewsIngestRunner {

    public function __construct(
        private readonly NewsSourceLocator $sourceLocator,
        private readonly NewsUpsertService $upsert,
        private readonly SourcePrerequisiteService $prerequisites,
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    /**
     * @param array{
     *   stream?: int|string|null,
     *   limit?: int|null,
     *   page_size?: int,
     *   dry_run?: bool,
     * } $options
     *
     * @return array{created: int, updated: int, unchanged: int, errors: int, processed: int}
     */
    public function ingest(string $sourceId, array $options = []): array {
        $source = $this->sourceLocator->get($sourceId);
        $source->ensurePrerequisites();

        $dry_run = !empty($options['dry_run']);
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'processed' => 0,
        ];

        $logger = $this->loggerFactory->get('news_ingestion');

        foreach ($source->fetchArticles($options) as $dto) {
            $stats['processed']++;
            try {
                $result = $this->upsert->upsert($dto, $dry_run);
                $action = $result['action'];
                if (isset($stats[$action])) {
                    $stats[$action]++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                // Never log the DTO URL query or API credentials — message only.
                $logger->error('Upsert failed for uri @uri: @msg', [
                    '@uri' => $dto->uri,
                    '@msg' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Delete news nodes for a source (optional single article URI).
     *
     * @return array{deleted: int}
     */
    public function purge(string $sourceId, ?string $articleUri = NULL, bool $dry_run = FALSE): array {
        $source = $this->sourceLocator->get($sourceId);
        // Prerequisites needed so we can resolve the term even if empty.
        $source->ensurePrerequisites();

        $term = $this->prerequisites->loadTermByKey($source->id());
        if (!$term) {
            throw new \RuntimeException('Source term missing after ensurePrerequisites().');
        }

        $storage = $this->entityTypeManager->getStorage('node');
        $query = $storage->getQuery()
            ->accessCheck(FALSE)
            ->condition('type', 'news')
            ->condition('field_news_source', (int) $term->id());
        if ($articleUri) {
            $query->condition('field_article_uri', $articleUri);
        }

        $nids = $query->execute();
        if (!$nids) {
            return ['deleted' => 0];
        }

        if ($dry_run) {
            return ['deleted' => count($nids)];
        }

        $nodes = $storage->loadMultiple($nids);
        $storage->delete($nodes);
        return ['deleted' => count($nodes)];
    }
}
