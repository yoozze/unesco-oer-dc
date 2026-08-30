<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Mapper;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Maps country labels / ISO codes to countries taxonomy terms.
 */
final class CountryMapper {

    /**
     * Normalizes common ER / English labels to ISO 3166 alpha-3 hints.
     *
     * @var array<string, string>
     */
    private const HINT_ALIASES = [
        'united states' => 'usa',
        'u.s.' => 'usa',
        'u.s.a.' => 'usa',
        'uk' => 'gbr',
        'united kingdom' => 'gbr',
        'great britain' => 'gbr',
        'russia' => 'rus',
        'south korea' => 'kor',
        'north korea' => 'prk',
        'viet nam' => 'vnm',
        'czech republic' => 'cze',
        'cote d\'ivoire' => 'civ',
        "cote d'ivoire" => 'civ',
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $byName = NULL;

    /**
     * @var array<string, int>|null
     */
    private ?array $byIso2 = NULL;

    /**
     * @var array<string, int>|null
     */
    private ?array $byIso3 = NULL;

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    /**
     * @param string[] $hints
     *   Country names and/or ISO codes from the source.
     *
     * @return int[]
     *   Unique taxonomy term IDs.
     */
    public function tidsFromHints(array $hints): array {
        $this->ensureMaps();
        $tids = [];
        foreach ($hints as $hint) {
            $key = strtolower(trim((string) $hint));
            if ($key === '') {
                continue;
            }

            $key = self::HINT_ALIASES[$key] ?? $key;

            $tid = $this->byIso2[$key]
                ?? $this->byIso3[$key]
                ?? $this->byName[$key]
                ?? NULL;
            if ($tid !== NULL) {
                $tids[$tid] = $tid;
            }
        }

        return array_values($tids);
    }

    private function ensureMaps(): void {
        if ($this->byName !== NULL) {
            return;
        }

        $this->byName = [];
        $this->byIso2 = [];
        $this->byIso3 = [];
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'countries',
        ]);
        foreach ($terms as $term) {
            $tid = (int) $term->id();
            $this->byName[strtolower(trim($term->label()))] = $tid;
            if ($term->hasField('field_iso3166_alpha2') && !$term->get('field_iso3166_alpha2')->isEmpty()) {
                $iso2 = strtolower(trim((string) $term->get('field_iso3166_alpha2')->value));
                if ($iso2 !== '') {
                    $this->byIso2[$iso2] = $tid;
                }
            }

            if ($term->hasField('field_iso3166_alpha3') && !$term->get('field_iso3166_alpha3')->isEmpty()) {
                $iso3 = strtolower(trim((string) $term->get('field_iso3166_alpha3')->value));
                if ($iso3 !== '') {
                    $this->byIso3[$iso3] = $tid;
                }
            }
        }
    }
}
