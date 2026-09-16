<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Recognizes a paragraph out of kongtent that is nothing but one link to
 * another site, so the paragraph template can set it as a link with an arrow
 * rather than as running text.
 */
final class SoleLinkExtension
{
    /**
     * The address and the label of the one external link the paragraph
     * consists of, decoded to plain text, or `null` for anything else.
     *
     * The paragraph went through the bundle's converter, so its markup is
     * known: a link with inline markup inside it, a link among words, a path
     * and an address on this site's own host all answer `null`. The host is
     * compared without a leading `www.`, so both spellings count as this site.
     *
     * @return array{href: string, label: string}|null
     */
    #[AsTwigFilter('sole_external_link')]
    public function soleExternalLink(string $html, string $ownHost): ?array
    {
        if (1 !== preg_match('#^<p><a href="(https?://[^"]+)">([^<]+)</a></p>$#', trim($html), $match)) {
            return null;
        }

        $href = html_entity_decode($match[1], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $host = (string) parse_url($href, \PHP_URL_HOST);

        if (self::withoutWww($host) === self::withoutWww($ownHost)) {
            return null;
        }

        return [
            'href' => $href,
            'label' => html_entity_decode($match[2], \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
        ];
    }

    private static function withoutWww(string $host): string
    {
        return preg_replace('/^www\./', '', strtolower($host)) ?? '';
    }
}
