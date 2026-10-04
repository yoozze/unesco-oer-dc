<?php

declare(strict_types=1);

namespace Drupal\moderation_digest\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Runs the weekly moderation digest when due (UTC schedule).
 */
final class DigestScheduler {

    public const STATE_LAST_SENT = 'moderation_digest.last_sent';

    public function __construct(
        private readonly ConfigFactoryInterface $configFactory,
        private readonly StateInterface $state,
        private readonly TimeInterface $time,
        private readonly PendingContentQuery $pendingContentQuery,
        private readonly DigestMailer $mailer,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    /**
     * Cron entry: send when the weekly UTC window is due and not yet sent.
     *
     * @return array{status: string, detail?: string, sent?: int, total?: int}
     */
    public function runIfDue(): array {
        $config = $this->configFactory->get('moderation_digest.settings');
        if (!$config->get('enabled')) {
            return ['status' => 'disabled'];
        }

        if (!$this->isDue()) {
            return ['status' => 'not_due'];
        }

        return $this->sendNow(FALSE);
    }

    /**
     * Force-send ignoring the schedule (admin/Drush).
     *
     * @return array{status: string, detail?: string, sent?: int, total?: int}
     */
    public function sendNow(bool $force = TRUE): array {
        $logger = $this->loggerFactory->get('moderation_digest');
        $result = $this->pendingContentQuery->getPendingItems();
        $items = $result['items'];
        $total = $result['total'];

        if ($total === 0) {
            if ($force) {
                $logger->notice('Moderation digest force-send: queue empty, nothing sent.');
            } else {
                // Mark the window as handled so we do not retry every hour.
                $this->state->set(self::STATE_LAST_SENT, $this->time->getRequestTime());
                $logger->notice('Moderation digest skipped: no pending content.');
            }

            return ['status' => 'empty', 'total' => 0, 'sent' => 0];
        }

        if ($this->mailer->resolveRecipientEmails() === []) {
            $logger->warning('Moderation digest has @total pending item(s) but no valid recipients are configured.', [
                '@total' => $total,
            ]);
            return [
                'status' => 'no_recipients',
                'detail' => 'Configure recipient user IDs or emails.',
                'total' => $total,
                'sent' => 0,
            ];
        }

        $mailResult = $this->mailer->sendDigest($items, $total);
        if ($mailResult['sent'] > 0) {
            $this->state->set(self::STATE_LAST_SENT, $this->time->getRequestTime());
        }

        return [
            'status' => $mailResult['sent'] > 0 ? 'sent' : 'failed',
            'sent' => $mailResult['sent'],
            'total' => $total,
            'failed' => $mailResult['failed'],
        ];
    }

    /**
     * Whether the configured weekly UTC slot has arrived and not yet been handled.
     */
    public function isDue(): bool {
        $config = $this->configFactory->get('moderation_digest.settings');
        $weekday = (int) $config->get('weekday');
        $hour = (int) $config->get('hour');
        if ($weekday < 1 || $weekday > 7) {
            $weekday = 1;
        }

        if ($hour < 0 || $hour > 23) {
            $hour = 8;
        }

        $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
            ->setTimezone(new \DateTimeZone('UTC'));

        $windowStart = $this->currentWindowStart($now, $weekday, $hour);
        if ($now < $windowStart) {
            return FALSE;
        }

        $lastSent = (int) $this->state->get(self::STATE_LAST_SENT, 0);
        return $lastSent < $windowStart->getTimestamp();
    }

    /**
     * Most recent scheduled Monday (or configured weekday) at hour:00 UTC ≤ now,
     * or the upcoming one if we are still before this week's slot.
     */
    private function currentWindowStart(\DateTimeImmutable $now, int $weekday, int $hour): \DateTimeImmutable {
        // ISO-8601: 1 = Monday … 7 = Sunday. PHP 'N' matches.
        $currentDow = (int) $now->format('N');
        $daysBack = ($currentDow - $weekday + 7) % 7;
        $candidate = $now
            ->modify("-{$daysBack} days")
            ->setTime($hour, 0, 0);

        if ($now < $candidate) {
            $candidate = $candidate->modify('-7 days');
        }

        return $candidate;
    }
}
