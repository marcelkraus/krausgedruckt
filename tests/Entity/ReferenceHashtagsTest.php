<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Reference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Pins the notation of the free hashtags of a reference.
 *
 * The setter owns that notation: the field accepts what a person types and
 * stores what the Instagram comment needs. Everything the setter cannot fix
 * belongs to the constraint, and the split between the two is what this class
 * holds in place.
 */
final class ReferenceHashtagsTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ?string, 1: ?string}>
     */
    public static function provideNotations(): iterable
    {
        yield 'null stays null' => [null, null];
        yield 'an empty string becomes null' => ['', null];
        yield 'white space alone becomes null' => ['   ', null];
        yield 'separators alone become null' => [',,,', null];
        yield 'a missing hash is added' => ['lampe', '#lampe'];
        yield 'repeated hashes collapse' => ['##mond', '#mond'];
        yield 'a space behind the hash is removed' => ['# lampe', '#lampe'];
        yield 'capitals are folded' => ['#MOND', '#mond'];
        yield 'an umlaut is folded as well' => ['#ÄPFEL', '#äpfel'];
        yield 'duplicates are dropped' => ['#MOND, mond, #Mond', '#mond'];
        yield 'the order is alphabetical' => ['#mond, #lampe', '#lampe, #mond'];
        yield 'an umlaut sorts next to its base letter' => ['#zebra, #öl', '#öl, #zebra'];
        yield 'a shared sort key is broken by the tag' => ['#äpfel, #apfel', '#apfel, #äpfel'];
        yield 'the separator is normalised' => ['#lampe,#mond', '#lampe, #mond'];
    }

    #[DataProvider('provideNotations')]
    public function testTheSetterStoresTheNotation(?string $entered, ?string $expected): void
    {
        $reference = new Reference();
        $reference->setHashtags($entered);

        self::assertSame($expected, $reference->getHashtags());
    }

    public function testTheListIsEmptyWithoutHashtags(): void
    {
        $reference = new Reference();

        self::assertSame([], $reference->getHashtagList());
    }

    public function testTheListCarriesEveryStoredHashtag(): void
    {
        $reference = new Reference();
        $reference->setHashtags('#mond, #lampe');

        self::assertSame(['#lampe', '#mond'], $reference->getHashtagList());
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function provideValidations(): iterable
    {
        yield 'a plain hashtag passes' => ['#mond', 0];
        yield 'several hashtags pass' => ['#ambiente, #lampe, #mond', 0];
        yield 'an umlaut passes' => ['#öl', 0];
        // Instagram ends a hashtag at the hyphen, so #rhein-erft-kreis would
        // reach the post as #rhein.
        yield 'a hyphen is refused' => ['#rhein-erft-kreis', 1];
        yield 'a space inside a tag is refused' => ['#mond lampe', 1];
    }

    #[DataProvider('provideValidations')]
    public function testTheConstraintReportsWhatTheSetterCannotFix(string $entered, int $expected): void
    {
        $reference = new Reference();
        $reference->setHashtags($entered);

        self::assertCount($expected, $this->validateHashtags($reference));
    }

    public function testAValueLongerThanTheColumnIsRefused(): void
    {
        $reference = new Reference();
        // Distinct tags, because duplicates would collapse before the length
        // is ever reached.
        $reference->setHashtags(implode(', ', array_map(
            static fn (int $number): string => '#hashtagnummer' . $number,
            range(1, 20)
        )));

        self::assertGreaterThan(255, mb_strlen((string) $reference->getHashtags()));
        self::assertCount(1, $this->validateHashtags($reference));
    }

    private function validateHashtags(Reference $reference): \Countable
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validateProperty($reference, 'hashtags');
    }
}
