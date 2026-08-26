<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Mapper;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Maps ISO language codes to languages taxonomy terms.
 */
final class LanguageMapper {

    /**
     * @var array<string, int>|null
     */
    private ?array $map = NULL;

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    /**
     * Resolve a language term ID from an ISO-639-1 or ISO-639-2/3 code.
     */
    public function tidFromCode(?string $code): ?int {
        if ($code === NULL || $code === '') {
            return NULL;
        }

        $normalized = strtolower(trim($code));
        $map = $this->getMap();
        return $map[$normalized] ?? NULL;
    }

    /**
     * @return array<string, int>
     */
    private function getMap(): array {
        if ($this->map !== NULL) {
            return $this->map;
        }

        $this->map = [];
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'languages',
        ]);
        foreach ($terms as $term) {
            $tid = (int) $term->id();
            if ($term->hasField('field_iso639_1') && !$term->get('field_iso639_1')->isEmpty()) {
                $iso1 = strtolower(trim((string) $term->get('field_iso639_1')->value));
                if ($iso1 !== '') {
                    $this->map[$iso1] = $tid;
                }
            }

            if ($term->hasField('field_iso639_2') && !$term->get('field_iso639_2')->isEmpty()) {
                $iso2 = strtolower(trim((string) $term->get('field_iso639_2')->value));
                if ($iso2 !== '') {
                    $this->map[$iso2] = $tid;
                }
            }
        }

        return $this->map;
    }
}
