<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Source;

/**
 * Locates tagged news source plugins by id.
 */
final class NewsSourceLocator {

    /**
     * @var array<string, \Drupal\news_ingestion\Source\NewsSourceInterface>
     */
    private array $sources = [];

    /**
     * @param iterable<\Drupal\news_ingestion\Source\NewsSourceInterface> $sources
     */
    public function __construct(iterable $sources) {
        foreach ($sources as $source) {
            $this->sources[$source->id()] = $source;
        }
    }

    /**
     * @return array<string, \Drupal\news_ingestion\Source\NewsSourceInterface>
     */
    public function all(): array {
        return $this->sources;
    }

    public function has(string $id): bool {
        return isset($this->sources[$id]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function get(string $id): NewsSourceInterface {
        if (!$this->has($id)) {
            $known = implode(', ', array_keys($this->sources)) ?: '(none)';
            throw new \InvalidArgumentException("Unknown news source \"$id\". Known: $known");
        }

        return $this->sources[$id];
    }
}
