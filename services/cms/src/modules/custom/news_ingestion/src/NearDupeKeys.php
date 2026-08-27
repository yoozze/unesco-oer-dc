<?php

declare(strict_types=1);

namespace Drupal\news_ingestion;

/**
 * Builds stable near-duplicate keys from URL / title.
 */
final class NearDupeKeys {

    /**
     * Host-agnostic URL path key (empty if not useful).
     */
    public static function urlKey(string $url): string {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return '';
        }

        $path = mb_strtolower(rawurldecode($path));
        $path = rtrim($path, '/');
        // Ignore tiny/generic paths that would over-match.
        if (mb_strlen($path) < 12) {
            return '';
        }

        return mb_substr($path, 0, 255);
    }

    /**
     * Normalized title key (empty if too short).
     */
    public static function titleKey(string $title): string {
        $t = mb_strtolower(trim($title));
        if ($t === '') {
            return '';
        }

        // Drop wire/outlet suffixes: "… | Newswise", "… | Reuters", etc.
        $t = preg_replace('/\s*\|\s*.+$/u', '', $t) ?? $t;
        // Keep letters/numbers/spaces only.
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', trim($t)) ?? $t;
        if (mb_strlen($t) < 16) {
            return '';
        }

        return mb_substr($t, 0, 255);
    }
}
