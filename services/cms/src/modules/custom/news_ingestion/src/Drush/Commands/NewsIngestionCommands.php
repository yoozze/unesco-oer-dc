<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Drush\Commands;

use Drupal\news_ingestion\Service\NewsIngestRunner;
use Drupal\news_ingestion\Source\NewsSourceLocator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for external news ingestion.
 */
final class NewsIngestionCommands extends DrushCommands {

    public function __construct(
        private readonly NewsIngestRunner $runner,
        private readonly NewsSourceLocator $sourceLocator,
    ) {
        parent::__construct();
    }

    /**
     * Ingest news from a registered source.
     */
    #[CLI\Command(name: 'news:ingest', aliases: ['news-ingest'])]
    #[CLI\Option(name: 'source', description: 'Source machine name (e.g. eventregistry).')]
    #[CLI\Option(name: 'stream', description: 'Optional stream number (1–5 for EventRegistry).')]
    #[CLI\Option(name: 'limit', description: 'Max articles to process (across selected streams).')]
    #[CLI\Option(name: 'page-size', description: 'Remote page size (EventRegistry articlesCount).')]
    #[CLI\Option(name: 'dry-run', description: 'Fetch and map without saving nodes.')]
    #[CLI\Usage(name: 'drush news:ingest --source=eventregistry --stream=2 --limit=5', description: 'Ingest 5 articles from OER 2.')]
    #[CLI\Usage(name: 'drush news:ingest --source=eventregistry --dry-run', description: 'Dry-run all streams.')]
    public function ingest(
        array $options = [
            'source' => NULL,
            'stream' => NULL,
            'limit' => NULL,
            'page-size' => 50,
            'dry-run' => FALSE,
        ],
    ): void {
        $source = $options['source'] ?? NULL;
        if (!$source) {
            $known = implode(', ', array_keys($this->sourceLocator->all())) ?: '(none enabled)';
            throw new \InvalidArgumentException("--source is required. Known sources: $known");
        }

        $stats = $this->runner->ingest((string) $source, [
            'stream' => $options['stream'] !== NULL && $options['stream'] !== '' ? $options['stream'] : NULL,
            'limit' => $options['limit'] !== NULL && $options['limit'] !== '' ? (int) $options['limit'] : NULL,
            'page_size' => (int) ($options['page-size'] ?? 50),
            'dry_run' => !empty($options['dry-run']),
        ]);

        $this->io()->success(sprintf(
            'Ingest complete (%s): processed=%d created=%d updated=%d unchanged=%d errors=%d%s',
            $source,
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['unchanged'],
            $stats['errors'],
            !empty($options['dry-run']) ? ' [dry-run]' : ''
        ));
    }

    /**
     * Delete ingested news for a source (retest helper).
     */
    #[CLI\Command(name: 'news:purge', aliases: ['news-purge'])]
    #[CLI\Option(name: 'source', description: 'Source machine name (e.g. eventregistry).')]
    #[CLI\Option(name: 'article-uri', description: 'Optional field_article_uri to delete a single item.')]
    #[CLI\Option(name: 'dry-run', description: 'Count matches without deleting.')]
    #[CLI\Usage(name: 'drush news:purge --source=eventregistry', description: 'Delete all EventRegistry news nodes.')]
    public function purge(
        array $options = [
            'source' => NULL,
            'article-uri' => NULL,
            'dry-run' => FALSE,
        ],
    ): void {
        $source = $options['source'] ?? NULL;
        if (!$source) {
            throw new \InvalidArgumentException('--source is required.');
        }

        $uri = $options['article-uri'] !== NULL && $options['article-uri'] !== '' ? (string) $options['article-uri'] : NULL;
        $dry = !empty($options['dry-run']);

        if (!$dry && !$this->io()->confirm(sprintf(
            'Delete news nodes for source "%s"%s?',
            $source,
            $uri ? " (article-uri=$uri)" : ''
        ), FALSE)) {
            $this->io()->warning('Aborted.');
            return;
        }

        $result = $this->runner->purge((string) $source, $uri, $dry);
        $this->io()->success(sprintf(
            '%s %d news node(s) for source %s.',
            $dry ? 'Would delete' : 'Deleted',
            $result['deleted'],
            $source
        ));
    }

    /**
     * List registered news sources.
     */
    #[CLI\Command(name: 'news:sources', aliases: ['news-sources'])]
    public function sources(): void {
        $rows = [];
        foreach ($this->sourceLocator->all() as $id => $source) {
            $rows[] = [$id, $source->label(), $source->providerModule()];
        }

        if (!$rows) {
            $this->io()->warning('No news sources registered. Enable a source module (e.g. eventregistry_news).');
            return;
        }

        $this->io()->table(['ID', 'Label', 'Module'], $rows);
    }
}
