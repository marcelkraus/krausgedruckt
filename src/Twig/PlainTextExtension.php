<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Markup out of kongtent as plain text, for the structured data: tags gone,
 * entities decoded, every run of white space one space.
 */
final class PlainTextExtension
{
    #[AsTwigFilter('plain_text')]
    public function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
