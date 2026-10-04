<?php

declare(strict_types=1);

namespace Drupal\moderation_digest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\moderation_digest\Service\DigestScheduler;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the weekly moderation digest.
 */
final class DigestSettingsForm extends ConfigFormBase {

    private DigestScheduler $scheduler;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static {
        $instance = parent::create($container);
        $instance->scheduler = $container->get('moderation_digest.scheduler');
        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
        return 'moderation_digest_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array {
        return ['moderation_digest.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array {
        $config = $this->config('moderation_digest.settings');

        $form['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable weekly digest'),
            '#default_value' => (bool) $config->get('enabled'),
            '#description' => $this->t('When enabled, cron sends the digest after the scheduled UTC time if it has not already been sent that week. Empty queues are skipped.'),
        ];

        $form['schedule'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Schedule (UTC)'),
        ];

        $form['schedule']['weekday'] = [
            '#type' => 'select',
            '#title' => $this->t('Day of week'),
            '#options' => [
                1 => $this->t('Monday'),
                2 => $this->t('Tuesday'),
                3 => $this->t('Wednesday'),
                4 => $this->t('Thursday'),
                5 => $this->t('Friday'),
                6 => $this->t('Saturday'),
                7 => $this->t('Sunday'),
            ],
            '#default_value' => (int) $config->get('weekday') ?: 1,
        ];

        $form['schedule']['hour'] = [
            '#type' => 'number',
            '#title' => $this->t('Hour (UTC)'),
            '#min' => 0,
            '#max' => 23,
            '#default_value' => (int) $config->get('hour'),
            '#description' => $this->t('Default: Monday 08:00 UTC. Requires Drupal cron (hourly in this project).'),
        ];

        $form['max_items'] = [
            '#type' => 'number',
            '#title' => $this->t('Maximum items in email'),
            '#min' => 1,
            '#max' => 500,
            '#default_value' => (int) $config->get('max_items') ?: 50,
        ];

        $form['recipients'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Recipients'),
            '#description' => $this->t('Only users and addresses listed here receive the digest (not every moderator).'),
        ];

        $defaultUsers = [];
        foreach ((array) $config->get('recipient_uids') as $uid) {
            $user = User::load((int) $uid);
            if ($user) {
                $defaultUsers[] = $user;
            }
        }

        $form['recipients']['recipient_uids'] = [
            '#type' => 'entity_autocomplete',
            '#title' => $this->t('Users'),
            '#target_type' => 'user',
            '#tags' => TRUE,
            '#selection_settings' => [
                'include_anonymous' => FALSE,
            ],
            '#default_value' => $defaultUsers,
            '#description' => $this->t('Active users with a valid email address.'),
        ];

        $form['recipients']['recipient_emails'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Additional email addresses'),
            '#default_value' => implode("\n", (array) $config->get('recipient_emails')),
            '#description' => $this->t('One email address per line.'),
            '#rows' => 4,
        ];

        $form['scope'] = [
            '#type' => 'item',
            '#title' => $this->t('Content included'),
            '#markup' => '<p>' . $this->t('Pending moderation in states <em>Draft</em> and <em>Review</em> (including pending revisions of published nodes). Archived content is excluded. Same idea as <a href=":url">Moderated content</a>.', [
                ':url' => Url::fromRoute('view.moderated_content.moderated_content')->toString(),
            ]) . '</p>',
        ];

        $form = parent::buildForm($form, $form_state);

        $form['actions']['send_now'] = [
            '#type' => 'submit',
            '#value' => $this->t('Send digest now'),
            '#submit' => ['::submitSendNow'],
            '#limit_validation_errors' => [],
            '#button_type' => 'secondary',
            '#weight' => 20,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void {
        parent::validateForm($form, $form_state);

        $emails = $this->parseEmails((string) $form_state->getValue('recipient_emails'));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $form_state->setErrorByName('recipient_emails', $this->t('Invalid email address: @mail', [
                    '@mail' => $email,
                ]));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void {
        $uids = [];
        foreach ((array) $form_state->getValue('recipient_uids') as $item) {
            if (is_array($item) && isset($item['target_id'])) {
                $uids[] = (int) $item['target_id'];
            }
        }

        $uids = array_values(array_unique(array_filter($uids)));

        $this->config('moderation_digest.settings')
            ->set('enabled', (bool) $form_state->getValue('enabled'))
            ->set('weekday', (int) $form_state->getValue('weekday'))
            ->set('hour', (int) $form_state->getValue('hour'))
            ->set('max_items', (int) $form_state->getValue('max_items'))
            ->set('recipient_uids', $uids)
            ->set('recipient_emails', $this->parseEmails((string) $form_state->getValue('recipient_emails')))
            ->save();

        parent::submitForm($form, $form_state);
    }

    /**
     * Force-send submit handler (uses saved config, not unsaved form values).
     */
    public function submitSendNow(array &$form, FormStateInterface $form_state): void {
        $result = $this->scheduler->sendNow(TRUE);
        match ($result['status']) {
            'sent' => $this->messenger()->addStatus($this->t('Digest sent to @sent recipient(s). @total pending item(s).', [
                '@sent' => $result['sent'] ?? 0,
                '@total' => $result['total'] ?? 0,
            ])),
            'empty' => $this->messenger()->addStatus($this->t('No pending content; nothing sent.')),
            'no_recipients' => $this->messenger()->addError($this->t('No valid recipients configured. Save recipients first, then try again.')),
            default => $this->messenger()->addError($this->t('Digest could not be sent. Check the site log.')),
        };
    }

    /**
     * @return list<string>
     */
    private function parseEmails(string $raw): array {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if ($email !== '') {
                $emails[$email] = $email;
            }
        }

        return array_values($emails);
    }
}
