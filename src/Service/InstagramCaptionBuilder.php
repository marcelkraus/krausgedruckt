<?php

namespace App\Service;

use App\Entity\Reference;

/**
 * Builds the caption for an Instagram post from a reference. The result is
 * meant to be copied into Instagram as it is.
 */
class InstagramCaptionBuilder
{
    /**
     * Hashtags that every post carries, independent of the reference.
     *
     * @var string[]
     */
    public const GLOBAL_HASHTAGS = [
        '#3ddruck',
        '#3ddrucker',
        '#3ddruckvorort',
        '#dienstleistervorort',
        '#erftstadt',
        '#krausgedruckt',
        '#rheinerftkreis',
    ];

    public function build(Reference $reference): string
    {
        $paragraphs = [$this->buildIntroduction($reference)];

        $source = $this->buildSource($reference);
        if ($source !== null) {
            $paragraphs[] = $source;
        }

        $paragraphs[] = implode(' ', $this->buildHashtags($reference));

        return implode("\n\n", $paragraphs);
    }

    private function buildIntroduction(Reference $reference): string
    {
        $introduction = sprintf(
            'Am heutigen #ModellMontag stellen wir euch das Modell „%s“ vor',
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
    private function buildHashtags(Reference $reference): array
    {
        $hashtags = self::GLOBAL_HASHTAGS;

        $printer = $reference->getPrinter();
        if ($printer !== null) {
            $hashtags = array_merge($hashtags, $printer->getHashtags());
        }

        $material = $reference->getMaterial();
        if ($material !== null) {
            $hashtags = array_merge($hashtags, $material->getHashtags());
        }

        $hashtags = array_values(array_unique($hashtags));
        sort($hashtags);

        return $hashtags;
    }
}
