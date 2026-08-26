<?php

declare(strict_types=1);

namespace Drupal\eventregistry_news\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * HTTP client for EventRegistry topic-page article API.
 *
 * API key is read from $settings['eventregistry_api_key'] and sent in the
 * POST JSON body (never logged).
 */
final class EventRegistryApiClient {

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly Settings $settings,
        private readonly ConfigFactoryInterface $configFactory,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    /**
     * Fetch one page of articles for a topic page URI.
     *
     * @return array{results: array<int, array>, pages: int, page: int, totalResults: int}
     */
    public function fetchTopicPage(string $topicUri, int $page = 1, int $count = 50): array {
        $apiKey = (string) $this->settings->get('eventregistry_api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('EVENTREGISTRY_API_KEY / settings[eventregistry_api_key] is not configured.');
        }

        $config = $this->configFactory->get('eventregistry_news.settings');
        $endpoint = (string) $config->get('api.endpoint');
        if ($endpoint === '') {
            $endpoint = 'https://eventregistry.org/api/v1/article/getArticlesForTopicPage';
        }

        $payload = [
            'uri' => $topicUri,
            'dataType' => ['news', 'blog'],
            'resultType' => 'articles',
            'articlesCount' => max(1, min(100, $count)),
            'articlesPage' => max(1, $page),
            'articlesSortBy' => 'date',
            'articlesSortByAsc' => FALSE,
            'articleBodyLen' => -1,
            'apiKey' => $apiKey,
        ];

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'json' => $payload,
                'timeout' => 90,
                'http_errors' => TRUE,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            // Do not include request URL/body — may contain the API key.
            $this->loggerFactory->get('eventregistry_news')->error('EventRegistry HTTP error for topic @topic page @page: @msg', [
                '@topic' => $topicUri,
                '@page' => $page,
                '@msg' => $e->getMessage(),
            ]);
            throw new \RuntimeException('EventRegistry request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode((string) $response->getBody(), TRUE);
        if (!is_array($data)) {
            throw new \RuntimeException('EventRegistry returned invalid JSON.');
        }

        if (!empty($data['error'])) {
            throw new \RuntimeException('EventRegistry API error: ' . (is_string($data['error']) ? $data['error'] : json_encode($data['error'])));
        }

        $articles = $data['articles'] ?? [];
        return [
            'results' => is_array($articles['results'] ?? NULL) ? $articles['results'] : [],
            'pages' => (int) ($articles['pages'] ?? 1),
            'page' => (int) ($articles['page'] ?? $page),
            'totalResults' => (int) ($articles['totalResults'] ?? 0),
        ];
    }
}
