<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ShortLinkResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShortLinkController extends AbstractController
{
    /**
     * The address behind the QR code of a signature card. It stays short so
     * the code stays coarse enough to scan from a table, and it answers 302
     * because the target of something already printed must be free to
     * change.
     *
     * The cards of the Gangelt Games Festival 2026 carry codes in which the
     * addresses of the cards before them stand chained, each ending in the
     * card's own. The path therefore takes anything, and the last short
     * link in it is the one that counts.
     */
    #[Route('/s/{slug}', name: 'app_short_link', requirements: ['slug' => '.+'], methods: ['GET'])]
    public function shortLink(string $slug, ShortLinkResolver $resolver): Response
    {
        $campaign = $resolver->resolve((string) preg_replace('~^.*/s/~', '', $slug));

        if (null === $campaign) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('app_homepage', $campaign);
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
