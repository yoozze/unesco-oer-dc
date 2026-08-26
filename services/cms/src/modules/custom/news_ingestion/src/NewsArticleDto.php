<?php

declare(strict_types=1);

namespace Drupal\news_ingestion;

/**
 * Canonical, API-agnostic news article payload for upsert.
 */
final class NewsArticleDto {

    /**
     * @param string $uri
     *   Stable remote article id (dedupe key for field_article_uri).
     * @param string $title
     *   Article title.
     * @param string $body
     *   Full body HTML or plain text.
     * @param string $url
     *   Original article URL.
     * @param string $sourceMachineName
     *   news_sources field_source_key (e.g. eventregistry).
     * @param string|null $imageUrl
     *   Remote image URL, if any.
     * @param int|null $publishedAt
     *   Unix timestamp from source publish date.
     * @param string|null $langCode
     *   ISO 639-1 or 639-2/3 language code from source.
     * @param string[] $authors
     *   Author display names.
     * @param string[] $countries
     *   Country labels or ISO hints for mapping.
     * @param int[] $areaOfActionIds
     *   Drupal taxonomy term IDs for this fetch/stream.
     * @param string|null $publisherTitle
     *   Outlet / publisher name (e.g. ER source.title).
     * @param string|null $summary
     *   Optional teaser/summary from the source. Never auto-generated.
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $sourceMachineName,
        public readonly ?string $imageUrl = NULL,
        public readonly ?int $publishedAt = NULL,
        public readonly ?string $langCode = NULL,
        public readonly array $authors = [],
        public readonly array $countries = [],
        public readonly array $areaOfActionIds = [],
        public readonly ?string $publisherTitle = NULL,
        public readonly ?string $summary = NULL,
    ) {
    }
}
