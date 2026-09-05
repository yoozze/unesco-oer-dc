<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Mapper;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Derives UNESCO region terms from country taxonomy term IDs.
 *
 * Uses UNESCO-world-regions-sdg.csv (ISO 3166 alpha-3 → UNESCO region name).
 * Multiple countries yield the union of their regions.
 */
final class RegionMapper {

    /**
     * UNESCO CSV region labels → regions vocabulary term names.
     */
    private const UNESCO_TO_TERM_LABEL = [
        'Africa' => 'Africa (AFR)',
        'Arab States' => 'Arab States (ARB)',
        'Asia and the Pacific' => 'Asia and the Pacific (APAC)',
        'Europe and Northern America' => 'Europe and North America (ENA)',
        'Latin America and the Caribbean' => 'Latin America and the Caribbean (LAC)',
    ];

    /**
     * @var array<string, string>|null
     *   ISO 3166 alpha-3 (lowercase) → UNESCO region label.
     */
    private ?array $iso3ToUnesco = NULL;

    /**
     * @var array<string, int>|null
     *   Lowercase region term label → term ID.
     */
    private ?array $termByLabel = NULL;

    /**
     * @var array<int, string>|null
     *   Country term ID → ISO 3166 alpha-3 (lowercase).
     */
    private ?array $countryIso3ByTid = NULL;

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly string $unescoCsvPath,
    ) {
    }

    /**
     * @param int[] $country_tids
     *   countries taxonomy term IDs.
     *
     * @return int[]
     *   Unique regions taxonomy term IDs.
     */
    public function tidsFromCountryTids(array $country_tids): array {
        $this->ensureTermMap();
        $this->ensureCountryIsoMap();
        $this->ensureUnescoMap();

        $unesco_regions = [];
        foreach ($country_tids as $tid) {
            $tid = (int) $tid;
            if ($tid <= 0) {
                continue;
            }

            $iso3 = $this->countryIso3ByTid[$tid] ?? NULL;
            if ($iso3 === NULL) {
                continue;
            }

            $unesco = $this->iso3ToUnesco[$iso3] ?? NULL;
            if ($unesco !== NULL) {
                $unesco_regions[$unesco] = TRUE;
            }
        }

        $tids = [];
        foreach (array_keys($unesco_regions) as $unesco_label) {
            $term_label = self::UNESCO_TO_TERM_LABEL[$unesco_label] ?? NULL;
            if ($term_label === NULL) {
                continue;
            }

            $tid = $this->termByLabel[strtolower($term_label)] ?? NULL;
            if ($tid !== NULL) {
                $tids[$tid] = $tid;
            }
        }

        return array_values($tids);
    }

    private function ensureUnescoMap(): void {
        if ($this->iso3ToUnesco !== NULL) {
            return;
        }

        if (!is_readable($this->unescoCsvPath)) {
            throw new \RuntimeException(sprintf('UNESCO region CSV is not readable: %s', $this->unescoCsvPath));
        }

        $this->iso3ToUnesco = [];
        $handle = fopen($this->unescoCsvPath, 'r');
        if ($handle === FALSE) {
            throw new \RuntimeException(sprintf('Unable to open UNESCO region CSV: %s', $this->unescoCsvPath));
        }

        $header = fgetcsv($handle);
        if ($header === FALSE) {
            fclose($handle);
            throw new \RuntimeException(sprintf('UNESCO region CSV is empty: %s', $this->unescoCsvPath));
        }

        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) < 5) {
                continue;
            }

            $iso3 = strtolower(trim((string) $row[1]));
            $unesco = trim((string) $row[4]);
            if ($iso3 === '' || $unesco === '') {
                continue;
            }

            $this->iso3ToUnesco[$iso3] = $unesco;
        }

        fclose($handle);
    }

    private function ensureTermMap(): void {
        if ($this->termByLabel !== NULL) {
            return;
        }

        $this->termByLabel = [];
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'regions',
        ]);
        foreach ($terms as $term) {
            $this->termByLabel[strtolower(trim($term->label()))] = (int) $term->id();
        }
    }

    private function ensureCountryIsoMap(): void {
        if ($this->countryIso3ByTid !== NULL) {
            return;
        }

        $this->countryIso3ByTid = [];
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'countries',
        ]);
        foreach ($terms as $term) {
            if (!$term->hasField('field_iso3166_alpha3') || $term->get('field_iso3166_alpha3')->isEmpty()) {
                continue;
            }

            $iso3 = strtolower(trim((string) $term->get('field_iso3166_alpha3')->value));
            if ($iso3 !== '') {
                $this->countryIso3ByTid[(int) $term->id()] = $iso3;
            }
        }
    }

}
