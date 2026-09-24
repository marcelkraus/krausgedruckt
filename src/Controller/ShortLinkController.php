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
     */
    #[Route('/s/{slug}', name: 'app_short_link', requirements: ['slug' => ShortLinkResolver::SLUG_PATTERN], methods: ['GET'])]
    public function shortLink(string $slug, ShortLinkResolver $resolver): Response
    {
        $campaign = $resolver->resolve($slug);

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
