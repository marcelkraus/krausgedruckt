<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reference;
use App\Enum\Material;
use App\Enum\Printer;
use App\Service\InstagramCaptionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Pins the split of an Instagram post into its two texts.
 *
 * Instagram accepts five hashtags per post, which is the whole reason the
 * caption and the comment are built apart. The last test of this class is the
 * one that matters: it asks whether a tag can reach Instagram twice.
 */
final class InstagramCaptionBuilderTest extends TestCase
{
    private InstagramCaptionBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new InstagramCaptionBuilder();
    }

    public function testTheCaptionEndsOnThePostHashtagsInTheirPublishedOrder(): void
    {
        $caption = $this->builder->buildCaption($this->buildReference());

        self::assertStringEndsWith(
            '#krausgedruckt #3ddruck #erftstadt #rheinerftkreis',
            $caption
        );
    }

    public function testThePostCarriesTheFiveHashtagsInstagramAccepts(): void
    {
        $caption = $this->builder->buildCaption($this->buildReference());

        self::assertSame(5, preg_match_all('/#\w+/u', $caption));
    }

    public function testTheIntroductionCarriesTheSummary(): void
    {
        $caption = $this->builder->buildCaption($this->buildReference());

        self::assertStringContainsString(
            'Am heutigen #ModellMontag stellen wir euch das Modell „Mondlampe“ vor: Ein Mond, der leuchtet.',
            $caption
        );
    }

    public function testTheIntroductionEndsAfterTheModelWithoutASummary(): void
    {
        $reference = $this->buildReference();
        $reference->setSummary(null);

        self::assertStringContainsString(
            'stellen wir euch das Modell „Mondlampe“ vor.',
            $this->builder->buildCaption($reference)
        );
    }

    public function testTheSourceSentenceStaysAwayWhileTheSourceIsIncomplete(): void
    {
        $reference = $this->buildReference();
        $reference->getSource()->setTitle('Moon Lamp');

        self::assertStringNotContainsString('Originalmodell', $this->builder->buildCaption($reference));
    }

    public function testTheSourceSentenceAppearsWithEveryFieldSet(): void
    {
        $reference = $this->buildReference();
        $reference->getSource()->setTitle('Moon Lamp');
        $reference->getSource()->setAuthor('Jemand');
        $reference->getSource()->setUrl('https://example.com/moon');

        self::assertStringContainsString(
            'Das Originalmodell „Moon Lamp“ von „Jemand“ findest du auf https://example.com/moon.',
            $this->builder->buildCaption($reference)
        );
    }

    public function testTheCommentCarriesNothingButItsOwnHashtagsWhileTheReferenceIsBare(): void
    {
        $comment = $this->builder->buildHashtagComment(new Reference());

        self::assertSame('#3d #3ddrucker #fdm #prusa', $comment);
    }

    public function testTheCommentUnitesPrinterMaterialAndTheOwnHashtags(): void
    {
        $reference = $this->buildReference();
        $reference->setPrinter(Printer::MK4S_MMU3);
        $reference->setMaterial(Material::PETG);
        $reference->setHashtags('#mond, #lampe');

        self::assertSame(
            '#3d #3ddrucker #fdm #filament #lampe #mond #petg #prusa #prusamk4 #prusamk4s #prusammu3',
            $this->builder->buildHashtagComment($reference)
        );
    }

    public function testTheCommentSortsAnUmlautNextToItsBaseLetter(): void
    {
        $reference = $this->buildReference();
        $reference->setHashtags('#zebra, #öl');

        self::assertSame('#3d #3ddrucker #fdm #öl #prusa #zebra', $this->builder->buildHashtagComment($reference));
    }

    public function testTheCommentDropsWhatTheCaptionAlreadyCarries(): void
    {
        $reference = $this->buildReference();
        // The introduction spends a hashtag of its own, and Instagram reads a
        // hashtag without regard to its case.
        $reference->setHashtags('#modellmontag, #3ddruck, #erftstadt, #mond');

        self::assertSame('#3d #3ddrucker #fdm #mond #prusa', $this->builder->buildHashtagComment($reference));
    }

    public function testNoHashtagReachesInstagramTwice(): void
    {
        $reference = $this->buildReference();
        $reference->setPrinter(Printer::CORE_ONE_INDX);
        $reference->setMaterial(Material::PLA);
        $reference->setHashtags('#modellmontag, #krausgedruckt, #3ddruck, #erftstadt, #rheinerftkreis, #mond');

        preg_match_all('/#\w+/u', $this->builder->buildCaption($reference), $inPost);
        preg_match_all('/#\w+/u', $this->builder->buildHashtagComment($reference), $inComment);

        self::assertSame([], array_intersect(
            array_map('mb_strtolower', $inPost[0]),
            array_map('mb_strtolower', $inComment[0])
        ));
    }

    private function buildReference(): Reference
    {
        $reference = new Reference();
        $reference->setTitle('Mondlampe');
        $reference->setSummary('Ein Mond, der leuchtet.');

        return $reference;
    }
}
