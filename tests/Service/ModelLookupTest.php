<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\ModelPreview;
use App\Service\LookupFailedException;
use App\Service\LookupFailure;
use App\Service\ModelLookup;
use App\Service\ModelReader;
use PHPUnit\Framework\TestCase;

/**
 * The choice between the readers, and what every answer passes through on
 * its way out.
 */
final class ModelLookupTest extends TestCase
{
    public function testAsksTheReaderThatServesThePlatform(): void
    {
        $lookup = new ModelLookup([
            $this->reader('https://a.example/', 'wrong one'),
            $this->reader('https://www.printables.com/', 'right one'),
        ]);

        self::assertSame('right one', $lookup->lookup('https://www.printables.com/model/1')->title);
    }

    public function testRefusesAnAddressNoReaderServes(): void
    {
        $lookup = new ModelLookup([$this->reader('https://www.printables.com/', 'never')]);

        try {
            $lookup->lookup('https://cults3d.com/en/3d-model/tool/clip');
            self::fail('An address without a reader was looked up.');
        } catch (LookupFailedException $exception) {
            self::assertSame(LookupFailure::UnsupportedAddress, $exception->failure);
        }
    }

    /**
     * Whatever a platform hands over is a stranger's text; the card has to
     * survive a description with line breaks and one of any length.
     */
    public function testCollapsesWhitespaceAndShortensWhatIsTooLong(): void
    {
        $lookup = new ModelLookup([
            $this->reader('https://www.printables.com/', "A  clip\n   for cables.", str_repeat('x', 700)),
        ]);

        $preview = $lookup->lookup('https://www.printables.com/model/1');

        self::assertSame('A clip for cables.', $preview->title);
        self::assertSame(600, mb_strlen($preview->description));
        self::assertStringEndsWith('…', $preview->description);
    }

    /**
     * The license is what the workshop calculates with, and it passes
     * through here on its way to the mail – untouched but for the
     * shortening every field gets.
     */
    public function testCarriesTheLicenseThrough(): void
    {
        $lookup = new ModelLookup([
            $this->reader('https://www.printables.com/', 'Clip', '', 'Creative Commons Attribution-Noncommercial'),
        ]);

        self::assertSame(
            'Creative Commons Attribution-Noncommercial',
            $lookup->lookup('https://www.printables.com/model/1')->license
        );
    }

    private function reader(string $prefix, string $title, string $description = '', ?string $license = null): ModelReader
    {
        return new class($prefix, $title, $description, $license) implements ModelReader {
            public function __construct(
                private readonly string $prefix,
                private readonly string $title,
                private readonly string $description,
                private readonly ?string $license,
            ) {
            }

            public function supports(string $url): bool
            {
                return str_starts_with($url, $this->prefix);
            }

            public function read(string $url): ModelPreview
            {
                return new ModelPreview($this->title, $this->description, null, $url, 'Test', $this->license);
            }
        };
    }
}
