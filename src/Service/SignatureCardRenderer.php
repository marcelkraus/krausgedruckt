<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SignatureModel;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Twig\Environment;

/**
 * Renders the printed table cards of the signature models, one A4 landscape
 * page each, for Chrome to print into a PDF.
 *
 * The QR code library is a dev dependency and is only touched inside
 * render(): production installs without it, and a type in the constructor
 * would break the container there.
 */
final class SignatureCardRenderer
{
    /**
     * What the cards print. Always the production address without `www`,
     * never the host of the request – the cards are rendered under ddev.
     */
    public const PUBLIC_HOST = 'krausgedruckt.de';

    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param list<SignatureModel>               $models
     * @param array{name: string, slug: string} $campaign
     */
    public function render(array $models, array $campaign): string
    {
        $qrOptions = new QROptions([
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);

        $cards = [];
        foreach ($models as $model) {
            $shortUrl = 'https://' . self::PUBLIC_HOST . '/s/' . ShortLinkResolver::SIGNATURE_PREFIX . $model->slug;
            $cards[] = [
                'model' => $model,
                // A fresh instance per card: render() adds its data to what the
                // instance already holds, so a shared one chains the addresses.
                'qrCode' => (new QRCode($qrOptions))->render($shortUrl),
                'shortUrl' => $shortUrl,
                'sourceUrlPieces' => $this->sourceUrlPieces($model->sourceUrl),
                'text' => $this->text($model),
            ];
        }

        return $this->twig->render('print/signature-cards.html.twig', [
            'campaign' => $campaign,
            'cards' => $cards,
            'publicHost' => self::PUBLIC_HOST,
        ]);
    }

    /**
     * The text, escaped, with what must not break kept on one line: a word
     * such as „3D-Druck“ after its „3D-“, a short name in quotation marks,
     * and the name of the printer. The text is cut into pieces first and
     * every piece escaped on its own, so no pattern ever sees an entity or
     * markup.
     */
    private function text(SignatureModel $model): string
    {
        $keep = [
            '\b3D-\p{L}+',
            '„[^“]{1,30}“',
        ];
        if ('' !== $model->printer) {
            $keep[] = '(?<![\p{L}\p{N}])' . preg_quote($model->printer, '/') . '(?![\p{L}\p{N}])';
        }

        $pieces = preg_split('/(' . implode('|', $keep) . ')/u', $model->text, -1, \PREG_SPLIT_DELIM_CAPTURE);

        $html = '';
        foreach (false === $pieces ? [$model->text] : $pieces as $index => $piece) {
            $html .= 1 === $index % 2
                ? '<span class="whitespace-nowrap">' . htmlspecialchars($piece) . '</span>'
                : htmlspecialchars($piece);
        }

        return $html;
    }

    /**
     * The source address in pieces that may break after a slash, never
     * between the two of a scheme.
     *
     * @return list<string>
     */
    private function sourceUrlPieces(string $url): array
    {
        return preg_split('~(?<=/)(?!/)~', $url, -1, \PREG_SPLIT_NO_EMPTY) ?: [$url];
    }
}
