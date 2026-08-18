<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Sorts hashtags the way a German reader expects. A byte comparison puts every
 * umlaut behind the whole alphabet, so the folding happens here rather than in
 * each caller. Neither a locale nor the intl extension is involved, so every
 * machine returns the same order.
 */
final class HashtagSorter
{
    /**
     * @param string[] $hashtags
     *
     * @return string[]
     */
    public static function sort(array $hashtags): array
    {
        // Two tags can share a key — #apfel and #äpfel do — so the tags
        // themselves decide the tie. Without it the order would follow the
        // input and change with it.
        usort(
            $hashtags,
            static fn (string $first, string $second): int
                => [self::buildSortKey($first), $first] <=> [self::buildSortKey($second), $second]
        );

        return array_values($hashtags);
    }

    private static function buildSortKey(string $hashtag): string
    {
        return strtr(mb_strtolower($hashtag), [
            'ä' => 'a',
            'ö' => 'o',
            'ß' => 'ss',
            'ü' => 'u',
        ]);
    }
}
