<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {
        return $this->render('content/homepage.html.twig');
    }

    #[Route('/impressum', name: 'app_imprint')]
    public function imprint(): Response
    {
        return $this->render('content/imprint.html.twig');
    }

    #[Route('/datenschutz', name: 'app_data_privacy')]
    public function dataPrivacy(): Response
    {
        return $this->render('content/data-privacy.html.twig');
    }

    #[Route('/demo', name: 'app_demo')]
    public function demo(): Response
    {
        return $this->render('demo.html.twig');
    }
}
