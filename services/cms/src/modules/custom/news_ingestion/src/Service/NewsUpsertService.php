<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\news_ingestion\Mapper\CountryMapper;
use Drupal\news_ingestion\Mapper\LanguageMapper;
use Drupal\news_ingestion\Mapper\RegionMapper;
use Drupal\news_ingestion\NearDupeKeys;
use Drupal\news_ingestion\NewsArticleDto;
use Drupal\node\NodeInterface;

/**
 * Creates or updates news nodes from NewsArticleDto.
 *
 * Deduping:
 * 1. Exact ER article URI (same article) → refresh content + merge AoA.
 * 2. Near-dupe via normalized URL path or title → keep first-seen content, merge AoA only.
 */
final class NewsUpsertService {

    private const BODY_FORMAT = 'basic_html';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly LanguageMapper $languageMapper,
        private readonly CountryMapper $countryMapper,
        private readonly RegionMapper $regionMapper,
        private readonly RemoteImageMediaDownloader $mediaDownloader,
        private readonly SourcePrerequisiteService $prerequisites,
        private readonly TimeInterface $time,
    ) {
    }

    /**
     * @return array{node: \Drupal\node\NodeInterface, action: string}
     */
    public function upsert(NewsArticleDto $dto, bool $dry_run = FALSE): array {
        if ($dto->uri === '') {
            throw new \InvalidArgumentException('NewsArticleDto.uri is required.');
        }
        if ($dto->sourceMachineName === '') {
            throw new \InvalidArgumentException('NewsArticleDto.sourceMachineName is required.');
        }

        $url_key = NearDupeKeys::urlKey($dto->url);
        $title_key = NearDupeKeys::titleKey($dto->title);

        $existing = $this->loadByArticleUri($dto->uri);
        if ($existing) {
            return $this->updateExisting($existing, $dto, $dry_run, FALSE, $url_key, $title_key);
        }

        $near = $this->loadNearDupe($url_key, $title_key);
        if ($near) {
            // First-seen wins: only merge AoA (+ backfill keys); do not replace content.
            return $this->updateExisting($near, $dto, $dry_run, TRUE, $url_key, $title_key);
        }

        return $this->createNew($dto, $dry_run, $url_key, $title_key);
    }

    public function loadByArticleUri(string $uri): ?NodeInterface {
        return $this->loadFirstByField('field_article_uri', $uri);
    }

    /**
     * Prefer URL-path match, then title match. Earliest created = first-seen.
     */
    public function loadNearDupe(string $url_key, string $title_key): ?NodeInterface {
        if ($url_key !== '') {
            $node = $this->loadFirstByField('field_url_norm', $url_key, TRUE);
            if ($node) {
                return $node;
            }
        }

        if ($title_key !== '') {
            return $this->loadFirstByField('field_title_norm', $title_key, TRUE);
        }

        return NULL;
    }

    private function loadFirstByField(string $field, string $value, bool $sort_by_created = FALSE): ?NodeInterface {
        if ($value === '' || !$this->fieldExists($field)) {
            return NULL;
        }

        $query = $this->entityTypeManager->getStorage('node')->getQuery()
            ->accessCheck(FALSE)
            ->condition('type', 'news')
            ->condition($field, $value)
            ->range(0, 1);
        if ($sort_by_created) {
            $query->sort('created', 'ASC');
        }

        $nids = $query->execute();
        if (!$nids) {
            return NULL;
        }

        $node = $this->entityTypeManager->getStorage('node')->load(reset($nids));
        return $node instanceof NodeInterface ? $node : NULL;
    }

    private function fieldExists(string $field_name): bool {
        return (bool) $this->entityTypeManager->getStorage('field_storage_config')
            ->load('node.' . $field_name);
    }

    /**
     * @return array{node: \Drupal\node\NodeInterface, action: string}
     */
    private function createNew(NewsArticleDto $dto, bool $dry_run, string $url_key, string $title_key): array {
        $term = $this->prerequisites->loadTermByKey($dto->sourceMachineName);
        $user = $this->prerequisites->loadUserByName($dto->sourceMachineName);
        if (!$term || !$user) {
            throw new \RuntimeException(sprintf(
                'Missing prerequisites for source "%s". Call ensurePrerequisites() first.',
                $dto->sourceMachineName
            ));
        }

        $published = $dto->publishedAt ?? $this->time->getRequestTime();
        $values = [
            'type' => 'news',
            'title' => mb_substr($dto->title !== '' ? $dto->title : 'Untitled', 0, 255),
            'uid' => (int) $user->id(),
            'status' => 1,
            'created' => $published,
            'changed' => $this->time->getRequestTime(),
            'moderation_state' => 'published',
            'field_article_uri' => $dto->uri,
            'field_news_source' => ['target_id' => (int) $term->id()],
            'field_description' => $this->buildDescription($dto),
            'field_url' => [
                'uri' => $dto->url,
                'title' => 'View original source',
            ],
            'field_area_of_action' => $this->refs($dto->areaOfActionIds),
        ];
        if ($url_key !== '' && $this->fieldExists('field_url_norm')) {
            $values['field_url_norm'] = $url_key;
        }

        if ($title_key !== '' && $this->fieldExists('field_title_norm')) {
            $values['field_title_norm'] = $title_key;
        }
        if ($dto->publisherTitle) {
            $values['field_source'] = mb_substr($dto->publisherTitle, 0, 255);
        }

        $author = $this->formatAuthors($dto->authors);
        if ($author !== NULL) {
            $values['field_author'] = $author;
        }

        $lang_tid = $this->languageMapper->tidFromCode($dto->langCode);
        if ($lang_tid) {
            $values['field_language'] = [['target_id' => $lang_tid]];
        }

        $country_tids = $this->countryMapper->tidsFromHints($dto->countries);
        $this->applyGeoFields($values, $country_tids);

        if ($dry_run) {
            $node = $this->entityTypeManager->getStorage('node')->create($values);
            return ['node' => $node, 'action' => 'created'];
        }

        /** @var \Drupal\node\NodeInterface $node */
        $node = $this->entityTypeManager->getStorage('node')->create($values);
        if ($dto->imageUrl) {
            $media = $this->mediaDownloader->createFromUrl($dto->imageUrl, $dto->title);
            if ($media) {
                $node->set('field_image', ['target_id' => $media->id()]);
            }
        }

        $node->save();
        return ['node' => $node, 'action' => 'created'];
    }

    /**
     * @return array{node: \Drupal\node\NodeInterface, action: string}
     */
    private function updateExisting(
        NodeInterface $node,
        NewsArticleDto $dto,
        bool $dry_run,
        bool $near_dupe_only,
        string $url_key,
        string $title_key,
    ): array {
        $dirty = FALSE;

        // Always merge Areas of Action.
        $existing_aoa = [];
        foreach ($node->get('field_area_of_action') as $item) {
            if ($item->target_id) {
                $existing_aoa[(int) $item->target_id] = (int) $item->target_id;
            }
        }

        $merged = $existing_aoa;
        foreach ($dto->areaOfActionIds as $tid) {
            $tid = (int) $tid;
            if ($tid && !isset($merged[$tid])) {
                $merged[$tid] = $tid;
                $dirty = TRUE;
            }
        }

        if ($dirty) {
            $node->set('field_area_of_action', $this->refs(array_values($merged)));
        }

        // Backfill near-dupe keys when missing.
        if ($url_key !== '' && $node->hasField('field_url_norm') && $node->get('field_url_norm')->isEmpty()) {
            $node->set('field_url_norm', $url_key);
            $dirty = TRUE;
        }

        if ($title_key !== '' && $node->hasField('field_title_norm') && $node->get('field_title_norm')->isEmpty()) {
            $node->set('field_title_norm', $title_key);
            $dirty = TRUE;
        }

        // Near-dupe of another URI: keep first-seen body/title/url/etc.
        if ($near_dupe_only) {
            if (!$dirty) {
                return ['node' => $node, 'action' => 'unchanged'];
            }

            if ($dry_run) {
                return ['node' => $node, 'action' => 'updated'];
            }

            if ($node->hasField('moderation_state')) {
                $node->set('moderation_state', 'published');
            }

            $node->setPublished();
            $node->save();
            return ['node' => $node, 'action' => 'updated'];
        }

        // Same article URI: refresh content.
        $title = mb_substr($dto->title !== '' ? $dto->title : $node->label(), 0, 255);
        if ($node->label() !== $title) {
            $node->setTitle($title);
            $dirty = TRUE;
        }

        if ($title_key !== '' && $node->hasField('field_title_norm') && (string) $node->get('field_title_norm')->value !== $title_key) {
            $node->set('field_title_norm', $title_key);
            $dirty = TRUE;
        }

        $description = $this->buildDescription($dto);
        $current = $node->get('field_description')->first();
        $current_value = $current ? (string) $current->value : '';
        $current_summary = $current ? (string) ($current->summary ?? '') : '';
        if ($current_value !== $description['value'] || $current_summary !== ($description['summary'] ?? '')) {
            $node->set('field_description', $description);
            $dirty = TRUE;
        }

        if ($dto->url) {
            $url_item = $node->get('field_url')->first();
            $current_uri = $url_item ? (string) $url_item->uri : '';
            $current_title = $url_item ? (string) $url_item->title : '';
            if ($current_uri !== $dto->url || $current_title !== 'View original source') {
                $node->set('field_url', [
                    'uri' => $dto->url,
                    'title' => 'View original source',
                ]);
                $dirty = TRUE;
            }

            if ($url_key !== '' && $node->hasField('field_url_norm') && (string) $node->get('field_url_norm')->value !== $url_key) {
                $node->set('field_url_norm', $url_key);
                $dirty = TRUE;
            }
        }

        if ($dto->publisherTitle && $node->get('field_source')->value !== $dto->publisherTitle) {
            $node->set('field_source', mb_substr($dto->publisherTitle, 0, 255));
            $dirty = TRUE;
        }

        $author = $this->formatAuthors($dto->authors);
        if ($author !== NULL && $node->get('field_author')->value !== $author) {
            $node->set('field_author', $author);
            $dirty = TRUE;
        }

        $lang_tid = $this->languageMapper->tidFromCode($dto->langCode);
        if ($lang_tid) {
            $current_lang = (int) ($node->get('field_language')->target_id ?? 0);
            if ($current_lang !== $lang_tid) {
                $node->set('field_language', [['target_id' => $lang_tid]]);
                $dirty = TRUE;
            }
        }

        $country_tids = $this->countryMapper->tidsFromHints($dto->countries);
        if ($country_tids) {
            sort($country_tids);
            $existing_countries = $this->referencedTids($node, 'field_country');
            if ($existing_countries !== $country_tids) {
                $node->set('field_country', $this->refs($country_tids));
                $dirty = TRUE;
            }

            $region_tids = $this->regionMapper->tidsFromCountryTids($country_tids);
            if ($region_tids) {
                sort($region_tids);
                $existing_regions = $this->referencedTids($node, 'field_region');
                if ($existing_regions !== $region_tids) {
                    $node->set('field_region', $this->refs($region_tids));
                    $dirty = TRUE;
                }
            }
        }

        if ($dto->imageUrl && $node->get('field_image')->isEmpty() && !$dry_run) {
            $media = $this->mediaDownloader->createFromUrl($dto->imageUrl, $dto->title);
            if ($media) {
                $node->set('field_image', ['target_id' => $media->id()]);
                $dirty = TRUE;
            }
        }

        if (!$dirty) {
            return ['node' => $node, 'action' => 'unchanged'];
        }

        if ($dry_run) {
            return ['node' => $node, 'action' => 'updated'];
        }

        if ($node->hasField('moderation_state')) {
            $node->set('moderation_state', 'published');
        }

        $node->setPublished();
        $node->save();
        return ['node' => $node, 'action' => 'updated'];
    }

    /**
     * @return array{value: string, summary: string, format: string}
     */
    private function buildDescription(NewsArticleDto $dto): array {
        $body = $dto->body !== '' ? $dto->body : $dto->title;
        $value = $this->plainToBasicHtml($body);
        $summary = $dto->summary !== NULL ? trim($dto->summary) : '';
        return [
            'value' => $value,
            'summary' => $summary,
            'format' => self::BODY_FORMAT,
        ];
    }

    private function plainToBasicHtml(string $text): string {
        if (preg_match('/<[a-z][\s\S]*>/i', $text)) {
            return $text;
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $paragraphs = preg_split("/\n{2,}/", $escaped) ?: [$escaped];
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim(str_replace("\n", "<br />\n", $p));
            if ($p !== '') {
                $html .= '<p>' . $p . '</p>';
            }
        }

        return $html !== '' ? $html : '<p></p>';
    }

    /**
     * @param int[] $country_tids
     * @param array<string, mixed> $values
     */
    private function applyGeoFields(array &$values, array $country_tids): void {
        if (!$country_tids) {
            return;
        }

        $values['field_country'] = $this->refs($country_tids);
        $region_tids = $this->regionMapper->tidsFromCountryTids($country_tids);
        if ($region_tids) {
            $values['field_region'] = $this->refs($region_tids);
        }
    }

    /**
     * @return int[]
     */
    private function referencedTids(NodeInterface $node, string $field): array {
        $tids = [];
        if (!$node->hasField($field)) {
            return $tids;
        }

        foreach ($node->get($field) as $item) {
            if ($item->target_id) {
                $tids[] = (int) $item->target_id;
            }
        }

        sort($tids);
        return $tids;
    }

    /**
     * @param string[] $authors
     */
    private function formatAuthors(array $authors): ?string {
        $names = array_values(array_filter(array_map(
            static fn($a) => trim((string) $a),
            $authors
        )));
        if (!$names) {
            return NULL;
        }

        return mb_substr(implode(', ', $names), 0, 255);
    }

    /**
     * @param int[] $ids
     *
     * @return array<int, array{target_id: int}>
     */
    private function refs(array $ids): array {
        $refs = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $refs[] = ['target_id' => $id];
            }
        }

        return $refs;
    }
}
