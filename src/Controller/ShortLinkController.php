<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShortLinkController extends AbstractController
{
    /**
     * Prefix => campaign. A printed short link reads `<prefix>-<code>`, and
     * the code carries no hyphen of its own.
     *
     * @var array<string, string>
     */
    public const PRINT_CAMPAIGNS = [
        'ad' => 'advertisement',
        'bc' => 'business-card',
        'fl' => 'flyer',
        'sm' => 'signature-model',
    ];

    /**
     * Prefix => codes in print: the edition, the year and month of the print
     * run – for an advertisement behind the two letters of its medium.
     *
     * @var array<string, list<string>>
     */
    public const PRINT_EDITIONS = [
        'ad' => ['ea2610'],
        'bc' => ['2610'],
        'fl' => ['2610'],
    ];

    /**
     * The medium an advertisement appears in => the source it is counted
     * under.
     *
     * @var array<string, string>
     */
    public const PRINT_SOURCES = [
        'ea' => 'erftstadt-aktuell',
    ];

    /**
     * Code => slug of the signature models in print, counted with the slug
     * as the keyword.
     *
     * @var array<string, string>
     */
    public const SIGNATURE_MODELS = [
        'd13' => 'dummy-13',
        'jpjp' => 'jurassic-print',
        'mrln' => 'marlin',
        'trtn' => 'triton-racer',
    ];

    /**
     * The address behind the QR code of a printed piece. It stays short so
     * the code stays coarse enough to scan, and it answers 302 because the
     * target of something already printed must be free to change. Every
     * scan is counted as print, under the piece's campaign; an
     * advertisement under its medium as the source.
     */
    #[Route('/s/{slug}', name: 'app_short_link', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function shortLink(string $slug): Response
    {
        $query = $this->countedAs($slug);

        if ($query === null) {
            throw $this->createNotFoundException(sprintf('Short link "%s" not found.', $slug));
        }

        return $this->redirectToRoute('app_homepage', $query);
    }

    /**
     * What a scan of the short link is counted as, or null where nothing is
     * printed under it.
     *
     * @return array<string, string>|null
     */
    private function countedAs(string $slug): ?array
    {
        $parts = explode('-', $slug);

        if (\count($parts) !== 2 || !isset(self::PRINT_CAMPAIGNS[$parts[0]])) {
            return null;
        }

        [$prefix, $code] = $parts;
        $query = ['mtm_campaign' => self::PRINT_CAMPAIGNS[$prefix], 'mtm_medium' => 'print'];

        if ($prefix === 'sm') {
            return isset(self::SIGNATURE_MODELS[$code]) ? $query + ['mtm_kwd' => self::SIGNATURE_MODELS[$code]] : null;
        }

        if (!\in_array($code, self::PRINT_EDITIONS[$prefix] ?? [], true)) {
            return null;
        }

        if ($prefix === 'ad') {
            $source = self::PRINT_SOURCES[substr($code, 0, 2)] ?? null;

            return $source === null ? null : $query + ['mtm_source' => $source, 'mtm_kwd' => substr($code, 2)];
        }

        return $query + ['mtm_kwd' => $code];
    }

    /**
     * A readable address for print only. The site itself links the shop
     * directly, so that Matomo counts the click as an outlink – a click on an
     * address of the site that is redirected on the server is never seen.
     */
    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function shop(): Response
    {
        return $this->redirect($this->getParameter('app.etsy_url'));
    }
}
