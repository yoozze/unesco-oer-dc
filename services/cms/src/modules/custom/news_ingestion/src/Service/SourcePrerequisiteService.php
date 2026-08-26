<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Ensures a news_sources term and author user for a source machine name.
 */
final class SourcePrerequisiteService {

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly PasswordGeneratorInterface $passwordGenerator,
    ) {
    }

    /**
     * @param array{
     *   key: string,
     *   name: string,
     *   description?: string,
     *   uuid?: string,
     *   username?: string,
     *   first_name?: string,
     *   last_name?: string,
     * } $definition
     *
     * @return array{term: \Drupal\taxonomy\TermInterface, user: \Drupal\user\UserInterface}
     */
    public function ensure(array $definition): array {
        $term = $this->ensureTerm($definition);
        $user = $this->ensureUser($definition);
        return ['term' => $term, 'user' => $user];
    }

    /**
     * Load news_sources term by field_source_key.
     */
    public function loadTermByKey(string $key): ?TermInterface {
        $existing = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'news_sources',
            'field_source_key' => $key,
        ]);
        $term = $existing ? reset($existing) : NULL;
        return $term instanceof TermInterface ? $term : NULL;
    }

    /**
     * Load author user by account name.
     */
    public function loadUserByName(string $name): ?UserInterface {
        $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
            'name' => $name,
        ]);
        $user = $users ? reset($users) : NULL;
        return $user instanceof UserInterface ? $user : NULL;
    }

    /**
     * @param array{key: string, name: string, description?: string, uuid?: string} $definition
     */
    private function ensureTerm(array $definition): TermInterface {
        $key = $definition['key'];
        $uuid = $definition['uuid'] ?? NULL;

        $term = NULL;
        if ($uuid) {
            $loaded = $this->entityRepository->loadEntityByUuid('taxonomy_term', $uuid);
            if ($loaded instanceof TermInterface) {
                $term = $loaded;
            }
        }

        if (!$term) {
            $term = $this->loadTermByKey($key);
        }

        if (!$term) {
            $values = [
                'vid' => 'news_sources',
                'name' => $definition['name'],
                'description' => $definition['description'] ?? '',
                'field_source_key' => $key,
                'status' => 1,
            ];
            if ($uuid) {
                $values['uuid'] = $uuid;
            }

            $term = Term::create($values);
            $term->save();
            return $term;
        }

        $dirty = FALSE;
        if ($term->bundle() !== 'news_sources') {
            throw new \RuntimeException("Term for source \"$key\" exists but is not news_sources.");
        }

        if ($term->get('field_source_key')->value !== $key) {
            $term->set('field_source_key', $key);
            $dirty = TRUE;
        }

        if ($term->label() !== $definition['name']) {
            $term->setName($definition['name']);
            $dirty = TRUE;
        }

        if ($dirty) {
            $term->save();
        }

        return $term;
    }

    /**
     * @param array{
     *   key: string,
     *   name: string,
     *   username?: string,
     *   first_name?: string,
     *   last_name?: string,
     * } $definition
     */
    private function ensureUser(array $definition): UserInterface {
        $username = (string) ($definition['username'] ?? $definition['key']);
        // Prefer explicit first/last; fall back to source display name for first name.
        $first_name = trim((string) ($definition['first_name'] ?? $definition['name'] ?? ''));
        $last_name = trim((string) ($definition['last_name'] ?? ''));

        $user = $this->loadUserByName($username);
        if (!$user) {
            $values = [
                'name' => $username,
                'mail' => $username . '@localhost.invalid',
                'status' => 1,
                'pass' => $this->passwordGenerator->generate(32),
            ];
            if ($first_name !== '') {
                $values['field_first_name'] = $first_name;
            }

            if ($last_name !== '') {
                $values['field_last_name'] = $last_name;
            }

            // System author account — authenticated only, no elevated roles,
            // profile not shared (custom_access_control hides /user/{uid}).
            $user = User::create($values);
            if ($user->hasField('field_share_profile')) {
                $user->set('field_share_profile', 0);
            }

            $user->save();
            return $user;
        }

        $dirty = FALSE;
        if (!$user->isActive()) {
            $user->activate();
            $dirty = TRUE;
        }

        if ($user->hasField('field_first_name') && (string) $user->get('field_first_name')->value !== $first_name) {
            $user->set('field_first_name', $first_name);
            $dirty = TRUE;
        }

        if ($user->hasField('field_last_name') && (string) ($user->get('field_last_name')->value ?? '') !== $last_name) {
            $user->set('field_last_name', $last_name);
            $dirty = TRUE;
        }

        if ($user->hasField('field_share_profile') && (bool) $user->get('field_share_profile')->value) {
            $user->set('field_share_profile', 0);
            $dirty = TRUE;
        }

        if ($dirty) {
            $user->save();
        }

        return $user;
    }
}
