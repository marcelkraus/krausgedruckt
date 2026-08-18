<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reference;

/**
 * Builds the two texts an Instagram post needs: the caption that goes into the
 * post itself and the hashtag block that follows it as the first comment. Both
 * are meant to be copied into Instagram as they are.
 */
final class InstagramCaptionBuilder
{
    /**
     * Hashtags that every caption carries. Instagram accepts five per post and
     * the introduction already spends one of them on #ModellMontag, so four
     * are left. The order is the published one and therefore not alphabetical.
     *
     * @var string[]
     */
    public const POST_HASHTAGS = [
        '#krausgedruckt',
        '#3ddruck',
        '#erftstadt',
        '#rheinerftkreis',
    ];

    /**
     * Hashtags that every comment carries, independent of the reference. The
     * technique and the printer brand never vary, so they belong here rather
     * than next to each single case.
     *
     * @var string[]
     */
    /**
     * The hashtag the introduction carries. It counts against the five
     * Instagram accepts, so it is named here rather than only written into
     * the sentence.
     */
    public const INTRODUCTION_HASHTAG = '#ModellMontag';

    public const COMMENT_HASHTAGS = [
        '#3d',
        '#3ddrucker',
        '#fdm',
        '#prusa',
    ];

    public function buildCaption(Reference $reference): string
    {
        $paragraphs = [$this->buildIntroduction($reference)];

        $source = $this->buildSource($reference);
        if ($source !== null) {
            $paragraphs[] = $source;
        }

        $paragraphs[] = implode(' ', self::POST_HASHTAGS);

        return implode("\n\n", $paragraphs);
    }

    /**
     * Builds the hashtag block for the first comment. It never repeats what the
     * caption already carries, so caption and comment together stay free of
     * duplicates.
     */
    public function buildHashtagComment(Reference $reference): string
    {
        return implode(' ', $this->buildCommentHashtags($reference));
    }

    private function buildIntroduction(Reference $reference): string
    {
        $introduction = sprintf(
            'Am heutigen %s stellen wir euch das Modell „%s“ vor',
            self::INTRODUCTION_HASHTAG,
            $reference->getTitle()
        );

        $summary = $reference->getSummary();

        if ($summary === null || $summary === '') {
            return $introduction . '.';
        }

        return $introduction . ': ' . $summary;
    }

    private function buildSource(Reference $reference): ?string
    {
        if ($reference->hasSource() === false) {
            return null;
        }

        $source = $reference->getSource();

        return sprintf(
            'Das Originalmodell „%s“ von „%s“ findest du auf %s.',
            $source->getTitle(),
            $source->getAuthor(),
            $source->getUrl()
        );
    }

    /**
     * @return string[]
     */
    private function buildCommentHashtags(Reference $reference): array
    {
        $hashtags = self::COMMENT_HASHTAGS;

        $printer = $reference->getPrinter();
        if ($printer !== null) {
            $hashtags = array_merge($hashtags, $printer->getHashtags());
        }

        $material = $reference->getMaterial();
        if ($material !== null) {
            $hashtags = array_merge($hashtags, $material->getHashtags());
        }

        $hashtags = array_merge($hashtags, $reference->getHashtagList());

        // Instagram reads a hashtag without regard to its case, so the
        // comparison has to ignore it as well.
        $reserved = array_map(
            static fn (string $hashtag): string => mb_strtolower($hashtag),
            array_merge(self::POST_HASHTAGS, [self::INTRODUCTION_HASHTAG])
        );

        $hashtags = array_filter(
            array_unique($hashtags),
            static fn (string $hashtag): bool => in_array(mb_strtolower($hashtag), $reserved, true) === false
        );

        return HashtagSorter::sort($hashtags);
    }
}
