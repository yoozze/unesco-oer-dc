<?php

declare(strict_types=1);

namespace Drupal\eventregistry_news;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\eventregistry_news\Client\EventRegistryApiClient;
use Drupal\eventregistry_news\Client\EventRegistryNormalizer;
use Drupal\news_ingestion\NewsArticleDto;
use Drupal\news_ingestion\Service\SourcePrerequisiteService;
use Drupal\news_ingestion\Source\NewsSourceInterface;

/**
 * EventRegistry news source: topic pages OER 1–5 → NewsArticleDto.
 */
final class EventRegistryNewsSource implements NewsSourceInterface {

    public function __construct(
        private readonly EventRegistryApiClient $apiClient,
        private readonly EventRegistryNormalizer $normalizer,
        private readonly SourcePrerequisiteService $prerequisites,
        private readonly ConfigFactoryInterface $configFactory,
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    public function id(): string {
        return EventRegistryNormalizer::SOURCE_KEY;
    }

    public function label(): string {
        return (string) ($this->configFactory->get('eventregistry_news.settings')->get('source.name') ?: 'EventRegistry');
    }

    public function providerModule(): string {
        return 'eventregistry_news';
    }

    public function ensurePrerequisites(): void {
        $config = $this->configFactory->get('eventregistry_news.settings');
        $source = $config->get('source') ?? [];
        $this->prerequisites->ensure([
            'key' => (string) ($source['key'] ?? EventRegistryNormalizer::SOURCE_KEY),
            'name' => (string) ($source['name'] ?? 'EventRegistry'),
            'description' => (string) ($source['description'] ?? ''),
            'uuid' => (string) ($source['uuid'] ?? ''),
            'username' => (string) ($source['username'] ?? EventRegistryNormalizer::SOURCE_KEY),
            'first_name' => (string) ($source['first_name'] ?? $source['name'] ?? 'EventRegistry'),
            'last_name' => (string) ($source['last_name'] ?? ''),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchArticles(array $options = []): \Generator {
        $config = $this->configFactory->get('eventregistry_news.settings');
        $streams = $config->get('streams') ?? [];
        if (!is_array($streams) || !$streams) {
            throw new \RuntimeException('eventregistry_news.settings streams are not configured.');
        }

        // Index by stream id for filtering.
        $byId = [];
        foreach ($streams as $stream) {
            if (!is_array($stream) || !isset($stream['id'])) {
                continue;
            }

            $byId[(string) $stream['id']] = $stream;
        }

        $streamFilter = $options['stream'] ?? NULL;
        if ($streamFilter !== NULL && $streamFilter !== '') {
            $streamFilter = (string) $streamFilter;
            if (!isset($byId[$streamFilter])) {
                throw new \InvalidArgumentException('Unknown EventRegistry stream "' . $streamFilter . '". Valid: ' . implode(', ', array_keys($byId)));
            }

            $byId = [$streamFilter => $byId[$streamFilter]];
        }

        $limit = isset($options['limit']) && $options['limit'] !== NULL ? (int) $options['limit'] : NULL;
        $pageSize = (int) ($options['page_size'] ?? $config->get('api.default_page_size') ?? 50);
        if ($pageSize < 1) {
            $pageSize = 50;
        }

        // When limiting, avoid fetching more than needed per request.
        if ($limit !== NULL && $limit > 0) {
            $pageSize = min($pageSize, $limit);
        }

        $yielded = 0;
        $logger = $this->loggerFactory->get('eventregistry_news');

        foreach ($byId as $streamId => $stream) {
            $topicUri = (string) ($stream['topic_uri'] ?? '');
            $aoaUuid = (string) ($stream['area_of_action_uuid'] ?? '');
            if ($topicUri === '' || $aoaUuid === '') {
                $logger->warning('Skipping stream @id: missing topic_uri or area_of_action_uuid.', [
                    '@id' => $streamId,
                ]);
                continue;
            }

            // Validate AoA UUID early so we fail clearly.
            if (!$this->entityRepository->loadEntityByUuid('taxonomy_term', $aoaUuid)) {
                throw new \RuntimeException("Area of action UUID $aoaUuid for stream $streamId not found.");
            }

            $page = 1;
            $pages = 1;
            do {
                try {
                    $response = $this->apiClient->fetchTopicPage($topicUri, $page, $pageSize);
                } catch (\Throwable $e) {
                    $logger->error('Failed fetching stream @id page @page: @msg', [
                        '@id' => $streamId,
                        '@page' => $page,
                        '@msg' => $e->getMessage(),
                    ]);
                    break;
                }

                $pages = max(1, $response['pages']);
                foreach ($response['results'] as $article) {
                    if (!is_array($article)) {
                        continue;
                    }

                    $dto = $this->normalizer->normalize($article, [$aoaUuid]);
                    if (!$dto instanceof NewsArticleDto) {
                        continue;
                    }

                    yield $dto;
                    $yielded++;
                    if ($limit !== NULL && $yielded >= $limit) {
                        return;
                    }
                }

                $page++;
            } while ($page <= $pages);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isCronEnabled(): bool {
        return (bool) $this->configFactory->get('eventregistry_news.settings')->get('cron.enabled');
    }

    /**
     * {@inheritdoc}
     */
    public function getCronItems(): array {
        $streams = $this->configFactory->get('eventregistry_news.settings')->get('streams') ?? [];
        if (!is_array($streams)) {
            return [];
        }

        $items = [];
        foreach ($streams as $stream) {
            if (!is_array($stream) || !isset($stream['id'])) {
                continue;
            }

            $items[] = ['stream' => $stream['id']];
        }

        return $items;
    }
}
