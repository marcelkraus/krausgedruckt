<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * An outstanding print shown at events, behind a printed table card. Every
 * figure is text, because an estimate often carries a „ca.“.
 */
final readonly class SignatureModel
{
    /**
     * @param string $license the license that permits the print, e.g. „CC BY 4.0“; empty where the designer released it in person
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $designer,
        public string $sourceUrl,
        public bool $permitted,
        public string $printTime,
        public string $parts,
        public string $printer,
        public string $materials,
        public string $colors,
        public string $text = '',
        public string $license = '',
    ) {
    }
}
