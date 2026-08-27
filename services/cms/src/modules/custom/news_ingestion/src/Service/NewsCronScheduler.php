<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\news_ingestion\Source\NewsSourceLocator;

/**
 * Enqueues per-source ingest jobs for Drupal cron / queue workers.
 */
final class NewsCronScheduler {

    public const QUEUE_ID = 'news_ingestion_ingest';

    public function __construct(
        private readonly NewsSourceLocator $sourceLocator,
        private readonly QueueFactory $queueFactory,
        private readonly ConfigFactoryInterface $configFactory,
        private readonly StateInterface $state,
        private readonly TimeInterface $time,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    /**
     * Queue due ingest jobs for all cron-enabled sources.
     *
     * @return int
     *   Number of queue items created.
     */
    public function enqueueDueJobs(): int {
        if (!$this->configFactory->get('news_ingestion.settings')->get('cron.enabled')) {
            return 0;
        }

        $queue = $this->queueFactory->get(self::QUEUE_ID);
        $created = 0;
        $logger = $this->loggerFactory->get('news_ingestion');

        foreach ($this->sourceLocator->all() as $source) {
            if (!$source->isCronEnabled()) {
                continue;
            }

            $items = $source->getCronItems();
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $payload = $item + ['source' => $source->id()];
                $queue->createItem($payload);
                $created++;
            }

            $this->state->set(
                'news_ingestion.cron_last_queued.' . $source->id(),
                $this->time->getRequestTime()
            );
            $logger->notice('Queued @count ingest job(s) for source @source.', [
                '@count' => count($items),
                '@source' => $source->id(),
            ]);
        }

        return $created;
    }
}
