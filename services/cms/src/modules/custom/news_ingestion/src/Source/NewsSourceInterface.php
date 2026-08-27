<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Source;

use Drupal\news_ingestion\NewsArticleDto;

/**
 * Contract for a remote news source (EventRegistry, IRCAI ES, …).
 */
interface NewsSourceInterface {

    /**
     * Machine id used by Drush --source=… (matches field_source_key).
     */
    public function id(): string;

    /**
     * Human-readable label.
     */
    public function label(): string;

    /**
     * Providing module machine name (for hook_modules_installed).
     */
    public function providerModule(): string;

    /**
     * Ensure taxonomy term + author user exist (idempotent).
     */
    public function ensurePrerequisites(): void;

    /**
     * Fetch articles from the remote API.
     *
     * @param array $options
     *   Keys may include: stream (int|string|null), limit (int|null),
     *   page_size (int), dry_run (bool).
     *
     * @return \Generator<int, \Drupal\news_ingestion\NewsArticleDto>
     */
    public function fetchArticles(array $options = []): \Generator;

    /**
     * Whether this source should be queued during Drupal cron.
     */
    public function isCronEnabled(): bool;

    /**
     * Queue payloads for cron (one item per logical job, e.g. per stream).
     *
     * Each item is merged with ['source' => $this->id()] by the scheduler.
     *
     * @return list<array<string, mixed>>
     */
    public function getCronItems(): array;
}
