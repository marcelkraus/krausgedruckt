<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SignatureCardRenderer;
use App\Service\SignatureModelCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SignatureCardController extends AbstractController
{
    /**
     * The table cards of the current event as one page per model, printed
     * into a PDF by `bin/signature-cards`. Production does not know the
     * route: it needs a dev dependency and is nothing a visitor should find.
     */
    #[Route('/_signature-cards', name: 'app_signature_cards', methods: ['GET'], env: ['dev', 'test'])]
    public function cards(SignatureModelCatalog $catalog, SignatureCardRenderer $renderer): Response
    {
        return new Response($renderer->render($catalog->all(), $catalog->campaign()));
    }
}
