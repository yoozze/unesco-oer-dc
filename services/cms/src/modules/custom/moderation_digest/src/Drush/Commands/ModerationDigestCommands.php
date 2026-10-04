<?php

declare(strict_types=1);

namespace Drupal\moderation_digest\Drush\Commands;

use Drupal\moderation_digest\Service\DigestScheduler;
use Drupal\moderation_digest\Service\PendingContentQuery;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the moderation digest.
 */
final class ModerationDigestCommands extends DrushCommands {

    public function __construct(
        private readonly DigestScheduler $scheduler,
        private readonly PendingContentQuery $pendingContentQuery,
    ) {
        parent::__construct();
    }

    /**
     * List pending moderated items that would appear in the digest.
     */
    #[CLI\Command(name: 'moderation-digest:list', aliases: ['md-list'])]
    #[CLI\Usage(name: 'drush moderation-digest:list', description: 'Show pending draft/review items.')]
    public function listPending(): void {
        $result = $this->pendingContentQuery->getPendingItems();
        $this->io()->writeln(sprintf('Pending items: %d (showing up to max_items)', $result['total']));
        foreach ($result['items'] as $item) {
            $this->io()->writeln(sprintf(
                '  [%s] %s (%s) %s — %s',
                $item['moderation_state'],
                $item['title'],
                $item['type'],
                $item['langcode'],
                $item['url'],
            ));
        }
    }

    /**
     * Send the digest immediately (ignores weekly schedule).
     */
    #[CLI\Command(name: 'moderation-digest:send', aliases: ['md-send'])]
    #[CLI\Usage(name: 'drush moderation-digest:send --uri=https://example.org', description: 'Force-send the digest (pass --uri for correct absolute links).')]
    public function send(): void {
        $result = $this->scheduler->sendNow(TRUE);
        match ($result['status']) {
            'sent' => $this->io()->success(sprintf(
                'Sent to %d recipient(s); %d pending item(s).',
                $result['sent'] ?? 0,
                $result['total'] ?? 0,
            )),
            'empty' => $this->io()->success('No pending content; nothing sent.'),
            'no_recipients' => $this->io()->error('No valid recipients configured.'),
            default => $this->io()->error('Digest could not be sent. Check the site log.'),
        };
    }

    /**
     * Show whether the weekly digest is currently due.
     */
    #[CLI\Command(name: 'moderation-digest:status', aliases: ['md-status'])]
    public function status(): void {
        $due = $this->scheduler->isDue();
        $this->io()->writeln($due ? 'Digest is due.' : 'Digest is not due.');
    }
}
