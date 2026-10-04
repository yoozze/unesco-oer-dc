<?php

declare(strict_types=1);

namespace Drupal\moderation_digest\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Resolves recipients and sends the moderation digest email.
 */
final class DigestMailer {

    public function __construct(
        private readonly MailManagerInterface $mailManager,
        private readonly ConfigFactoryInterface $configFactory,
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
        private readonly LanguageManagerInterface $languageManager,
    ) {
    }

    /**
     * Unique recipient email addresses from configured UIDs and emails.
     *
     * @return list<string>
     */
    public function resolveRecipientEmails(): array {
        $config = $this->configFactory->get('moderation_digest.settings');
        $emails = [];

        $uids = array_filter(array_map('intval', (array) $config->get('recipient_uids')));
        if ($uids) {
            $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
            foreach ($users as $user) {
                if (!$user instanceof UserInterface || !$user->isActive()) {
                    continue;
                }

                $mail = trim((string) $user->getEmail());
                if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $emails[$mail] = $mail;
                }
            }
        }

        foreach ((array) $config->get('recipient_emails') as $email) {
            $mail = trim((string) $email);
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $emails[$mail] = $mail;
            }
        }

        return array_values($emails);
    }

    /**
     * Sends the digest to all configured recipients.
     *
     * @param list<array<string, mixed>> $items
     *   Pending content rows.
     * @param int $total
     *   Total pending count (may exceed listed items).
     *
     * @return array{sent: int, failed: int, recipients: list<string>}
     */
    public function sendDigest(array $items, int $total): array {
        $recipients = $this->resolveRecipientEmails();
        $logger = $this->loggerFactory->get('moderation_digest');

        if ($recipients === []) {
            $logger->warning('Moderation digest skipped: no valid recipients configured.');
            return ['sent' => 0, 'failed' => 0, 'recipients' => []];
        }

        $params = [
            'items' => $items,
            'total' => $total,
            'moderated_content_url' => Url::fromRoute('view.moderated_content.moderated_content', [], [
                'absolute' => TRUE,
            ])->toString(),
        ];

        $langcode = $this->languageManager->getDefaultLanguage()->getId();
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $to) {
            $result = $this->mailManager->mail(
                'moderation_digest',
                'unpublished_digest',
                $to,
                $langcode,
                $params,
            );
            if (!empty($result['result'])) {
                $sent++;
            } else {
                $failed++;
                $logger->error('Failed to send moderation digest to @mail.', ['@mail' => $to]);
            }
        }

        if ($sent > 0) {
            $logger->notice('Moderation digest sent to @count recipient(s); @total pending item(s).', [
                '@count' => $sent,
                '@total' => $total,
            ]);
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'recipients' => $recipients,
        ];
    }
}
