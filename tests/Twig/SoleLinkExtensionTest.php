<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\SoleLinkExtension;
use PHPUnit\Framework\TestCase;

final class SoleLinkExtensionTest extends TestCase
{
    public function testALoneExternalLinkIsRecognizedAndDecoded(): void
    {
        self::assertSame(
            ['href' => 'https://example.com/?a=1&b=2', 'label' => 'Bewertung & mehr'],
            (new SoleLinkExtension())->soleExternalLink("<p><a href=\"https://example.com/?a=1&amp;b=2\">Bewertung &amp; mehr</a></p>\n", 'www.krausgedruckt.de'),
        );
    }

    public function testEverythingElseIsRunningText(): void
    {
        $extension = new SoleLinkExtension();

        foreach ([
            '<p><a href="/kontakt">Kontakt</a></p>',
            '<p><a href="https://www.krausgedruckt.de/kontakt">Kontakt</a></p>',
            '<p><a href="https://krausgedruckt.de/kontakt">Kontakt</a></p>',
            '<p>Siehe <a href="https://example.com">hier</a></p>',
            '<p><a href="https://example.com"><strong>fett</strong></a></p>',
            '<p>Kein Link</p>',
        ] as $html) {
            self::assertNull($extension->soleExternalLink($html, 'www.krausgedruckt.de'), $html);
        }
    }
}
