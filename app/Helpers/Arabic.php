<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Arabic text helpers.
 *
 * Canonical implementation of the normalization used for guide-imam
 * matching. The legacy global function normalizeArabic() in
 * includes/mosque_functions.php keeps its own copy until its last
 * legacy consumer is migrated (see cleanup phase).
 */
final class Arabic
{
    /**
     * Normalize Arabic text for matching/sorting: collapse whitespace,
     * strip diacritics, unify Alef variants, and map Teh Marbuta to Heh.
     */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = trim($text);
        $text = (string) preg_replace('/\s+/', ' ', $text);

        // Arabic diacritics in PHP PCRE format
        $text = (string) preg_replace('/[\x{064b}-\x{065f}\x{0670}]/u', '', $text);

        // Normalize Alefs
        $text = (string) preg_replace('/[أإآ]/u', 'ا', $text);

        // Normalize Teh Marbuta
        $text = (string) preg_replace('/ة/u', 'ه', $text);

        return $text;
    }
}
