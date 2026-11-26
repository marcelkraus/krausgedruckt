<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShortLinkController extends AbstractController
{
    /**
     * Prefix => campaign.
     *
     * @var array<string, string>
     */
    public const PRINT_CAMPAIGNS = [
        'bc' => 'business-card',
        'fl' => 'flyer',
        'sm' => 'signature-model',
    ];

    /**
     * Prefix => codes in print.
     *
     * @var array<string, list<string>>
     */
    public const PRINT_EDITIONS = [
        'bc' => ['2612'],
        'fl' => ['2612'],
    ];

    /**
     * Code => slug of a signature model in print.
     *
     * @var array<string, string>
     */
    public const SIGNATURE_MODELS = [
        'd13' => 'dummy-13',
        'jpjp' => 'jurassic-print',
        'mrln' => 'marlin',
        'trtn' => 'triton-racer',
    ];

    // 302, because the target of something already printed must be free to change.
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

        return $query + ['mtm_kwd' => $code];
    }
}
