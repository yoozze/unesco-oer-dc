<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\news_ingestion\Service\NewsIngestRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes a single news ingest job (one source, optional stream).
 *
 * @QueueWorker(
 *   id = "news_ingestion_ingest",
 *   title = @Translation("News ingestion"),
 *   cron = {"time" = 180}
 * )
 */
final class NewsIngestQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        private readonly NewsIngestRunner $runner,
        private readonly StateInterface $state,
        private readonly TimeInterface $time,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
        return new self(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('news_ingestion.runner'),
            $container->get('state'),
            $container->get('datetime.time'),
            $container->get('logger.factory'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem($data): void {
        if (!is_array($data) || empty($data['source'])) {
            throw new \InvalidArgumentException('Queue item must include a source id.');
        }

        $source = (string) $data['source'];
        $options = [];
        if (array_key_exists('stream', $data) && $data['stream'] !== NULL && $data['stream'] !== '') {
            $options['stream'] = $data['stream'];
        }

        if (!empty($data['limit'])) {
            $options['limit'] = (int) $data['limit'];
        }

        if (!empty($data['page_size'])) {
            $options['page_size'] = (int) $data['page_size'];
        }

        $logger = $this->loggerFactory->get('news_ingestion');
        try {
            $stats = $this->runner->ingest($source, $options);
        } catch (\Throwable $e) {
            $logger->error('Cron ingest failed for source @source stream @stream: @msg', [
                '@source' => $source,
                '@stream' => $options['stream'] ?? 'all',
                '@msg' => $e->getMessage(),
            ]);
            // Re-throw so the item can be retried / logged by the queue system.
            throw $e;
        }

        $this->state->set('news_ingestion.cron_last_run.' . $source, $this->time->getRequestTime());
        $logger->notice('Cron ingest @source stream @stream: processed=@processed created=@created updated=@updated unchanged=@unchanged errors=@errors', [
            '@source' => $source,
            '@stream' => $options['stream'] ?? 'all',
            '@processed' => $stats['processed'],
            '@created' => $stats['created'],
            '@updated' => $stats['updated'],
            '@unchanged' => $stats['unchanged'],
            '@errors' => $stats['errors'],
        ]);
    }
}
