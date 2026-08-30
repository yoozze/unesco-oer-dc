<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Ensures a news_sources term and author user for a source machine name.
 */
final class SourcePrerequisiteService {

    private const AVATAR_DIRECTORY = 'public://pictures/source-avatars';

    private const ICON_DIRECTORY = 'public://news-sources';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly PasswordGeneratorInterface $passwordGenerator,
        private readonly FileSystemInterface $fileSystem,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly ModuleExtensionList $moduleExtensionList,
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
     *   avatar_module?: string,
     *   avatar_path?: string,
     *   icon_module?: string,
     *   icon_path?: string,
     * } $definition
     *
     * @return array{term: \Drupal\taxonomy\TermInterface, user: \Drupal\user\UserInterface}
     */
    public function ensure(array $definition): array {
        $term = $this->ensureSourceTerm($definition);
        $user = $this->ensureUser($definition);
        return ['term' => $term, 'user' => $user];
    }

    /**
     * Ensures the news_sources term (and optional icon) without creating a user.
     *
     * @param array{
     *   key: string,
     *   name: string,
     *   description?: string,
     *   uuid?: string,
     *   icon_module?: string,
     *   icon_path?: string,
     * } $definition
     */
    public function ensureSourceTerm(array $definition): TermInterface {
        return $this->ensureTerm($definition);
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
     * @param array{
     *   key: string,
     *   name: string,
     *   description?: string,
     *   uuid?: string,
     *   icon_module?: string,
     *   icon_path?: string,
     * } $definition
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
            $this->ensureTermIcon($term, $definition);
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

        if ($this->ensureTermIcon($term, $definition)) {
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
     *   avatar_module?: string,
     *   avatar_path?: string,
     * } $definition
     */
    private function ensureUser(array $definition): UserInterface {
        $username = (string) ($definition['username'] ?? $definition['key']);
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

            $user = User::create($values);
            if ($user->hasField('field_share_profile')) {
                $user->set('field_share_profile', 0);
            }

            $this->ensureUserAvatar($user, $definition);

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

        if ($this->ensureUserAvatar($user, $definition)) {
            $dirty = TRUE;
        }

        if ($dirty) {
            $user->save();
        }

        return $user;
    }

    /**
     * @param array{
     *   key: string,
     *   icon_module?: string,
     *   icon_path?: string,
     * } $definition
     */
    private function ensureTermIcon(TermInterface $term, array $definition): bool {
        if (!$term->hasField('field_icon')) {
            return FALSE;
        }

        $module = trim((string) ($definition['icon_module'] ?? ''));
        $relative_path = trim((string) ($definition['icon_path'] ?? ''));
        if ($module === '' || $relative_path === '') {
            return FALSE;
        }

        $file = $this->copyModuleAsset($module, $relative_path, self::ICON_DIRECTORY, $definition['key'] . '-icon.svg');
        if (!$file) {
            return FALSE;
        }

        $current = $term->get('field_icon')->target_id;
        if ((int) $current === (int) $file->id()) {
            return FALSE;
        }

        $term->set('field_icon', [
            'target_id' => $file->id(),
            'alt' => $term->label(),
        ]);
        return TRUE;
    }

    /**
     * @param array{
     *   key: string,
     *   avatar_module?: string,
     *   avatar_path?: string,
     * } $definition
     */
    private function ensureUserAvatar(UserInterface $user, array $definition): bool {
        if (!$user->hasField('user_picture') || !$user->get('user_picture')->isEmpty()) {
            return FALSE;
        }

        $module = trim((string) ($definition['avatar_module'] ?? ''));
        $relative_path = trim((string) ($definition['avatar_path'] ?? ''));
        if ($module === '' || $relative_path === '') {
            return FALSE;
        }

        $file = $this->copyModuleAsset($module, $relative_path, self::AVATAR_DIRECTORY, $definition['key'] . '-avatar.svg');
        if (!$file) {
            return FALSE;
        }

        $user->set('user_picture', ['target_id' => $file->id()]);
        return TRUE;
    }

    private function copyModuleAsset(string $module, string $relative_path, string $directory, string $filename): ?FileInterface {
        $module_path = $this->moduleExtensionList->getPath($module);
        if ($module_path === '') {
            return NULL;
        }

        $source = DRUPAL_ROOT . '/' . $module_path . '/' . ltrim($relative_path, '/');
        if (!is_readable($source)) {
            return NULL;
        }

        $contents = file_get_contents($source);
        if ($contents === FALSE || $contents === '') {
            return NULL;
        }

        $this->fileSystem->prepareDirectory(
            $directory,
            FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
        );

        $destination = $directory . '/' . $filename;
        return $this->fileRepository->writeData($contents, $destination, FileExists::Replace);
    }

}
