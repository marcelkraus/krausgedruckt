<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class SoleLinkExtension
{
    /**
     * The host is compared without `www.`, so both spellings count as this site.
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
