<?php

declare(strict_types=1);

namespace Drupal\eventregistry_news\Client;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\news_ingestion\NewsArticleDto;

/**
 * Maps EventRegistry article payloads to NewsArticleDto.
 */
final class EventRegistryNormalizer {

    public const SOURCE_KEY = 'eventregistry';

    public function __construct(
        private readonly EntityRepositoryInterface $entityRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $article
     *   Raw ER article array.
     * @param string[] $areaOfActionUuids
     *   AoA term UUIDs for this stream.
     */
    public function normalize(array $article, array $areaOfActionUuids): ?NewsArticleDto {
        $uri = isset($article['uri']) ? (string) $article['uri'] : '';
        if ($uri === '') {
            return NULL;
        }

        $title = trim((string) ($article['title'] ?? ''));
        $body = (string) ($article['body'] ?? '');
        $url = trim((string) ($article['url'] ?? ''));
        if ($url === '') {
            // Without an original URL the CTA is useless; still ingest with a placeholder path.
            $url = 'https://eventregistry.org/article/' . rawurlencode($uri);
        }

        $image = $article['image'] ?? NULL;
        $imageUrl = is_string($image) && $image !== '' ? $image : NULL;

        $publishedAt = $this->parseTimestamp(
            (string) ($article['dateTimePub'] ?? ''),
            (string) ($article['dateTime'] ?? '')
        );

        $lang = isset($article['lang']) ? strtolower(trim((string) $article['lang'])) : NULL;
        if ($lang === '') {
            $lang = NULL;
        }

        $authors = [];
        foreach ($article['authors'] ?? [] as $author) {
            if (is_array($author) && !empty($author['name'])) {
                $authors[] = (string) $author['name'];
            } elseif (is_string($author) && $author !== '') {
                $authors[] = $author;
            }
        }

        $publisher = NULL;
        if (isset($article['source']) && is_array($article['source'])) {
            $publisher = trim((string) ($article['source']['title'] ?? ''));
            $publisher = $publisher !== '' ? $publisher : NULL;
        }

        $countries = $this->extractCountries($article['concepts'] ?? []);
        $aoaIds = $this->resolveAreaOfActionIds($areaOfActionUuids);

        return new NewsArticleDto(
            uri: $uri,
            title: $title !== '' ? $title : 'Untitled',
            body: $body,
            url: $url,
            sourceMachineName: self::SOURCE_KEY,
            imageUrl: $imageUrl,
            publishedAt: $publishedAt,
            langCode: $lang,
            authors: $authors,
            countries: $countries,
            areaOfActionIds: $aoaIds,
            publisherTitle: $publisher,
        );
    }

    /**
     * @param string[] $uuids
     *
     * @return int[]
     */
    private function resolveAreaOfActionIds(array $uuids): array {
        $ids = [];
        foreach ($uuids as $uuid) {
            $uuid = trim((string) $uuid);
            if ($uuid === '') {
                continue;
            }

            $entity = $this->entityRepository->loadEntityByUuid('taxonomy_term', $uuid);
            if ($entity) {
                $ids[] = (int) $entity->id();
            }
        }

        return $ids;
    }

    /**
     * @param mixed $concepts
     *
     * @return string[]
     */
    private function extractCountries(mixed $concepts): array {
        if (!is_array($concepts)) {
            return [];
        }

        $labels = [];
        foreach ($concepts as $concept) {
            if (!is_array($concept) || ($concept['type'] ?? '') !== 'loc') {
                continue;
            }

            $location = $concept['location'] ?? NULL;
            if (!is_array($location)) {
                continue;
            }

            $type = (string) ($location['type'] ?? '');
            if ($type === 'country') {
                $label = $this->engLabel($concept['label'] ?? NULL) ?? $this->engLabel($location['label'] ?? NULL);
                if ($label) {
                    $labels[$label] = $label;
                }

                continue;
            }

            // Places nest the country object.
            if (isset($location['country']) && is_array($location['country'])) {
                $label = $this->engLabel($location['country']['label'] ?? NULL)
                    ?? $this->engLabel($location['country'] ?? NULL);
                if ($label) {
                    $labels[$label] = $label;
                }
            }
        }
        return array_values($labels);
    }

    private function engLabel(mixed $label): ?string {
        if (is_string($label)) {
            $label = trim($label);
            return $label !== '' ? $label : NULL;
        }

        if (!is_array($label)) {
            return NULL;
        }

        foreach (['eng', 'en', 'eng-US'] as $key) {
            if (!empty($label[$key]) && is_string($label[$key])) {
                return trim($label[$key]);
            }
        }

        // Fallback: first non-empty string value.
        foreach ($label as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return NULL;
    }

    private function parseTimestamp(string $dateTimePub, string $dateTime): ?int {
        foreach ([$dateTimePub, $dateTime] as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            try {
                return (new \DateTimeImmutable($raw))->getTimestamp();
            } catch (\Exception) {
                continue;
            }
        }

        return NULL;
    }
}
