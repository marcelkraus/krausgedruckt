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
        return $this->render('index.html.twig');
    }

    #[Route('/demo', name: 'app_demo')]
    public function demo(): Response
    {
        return $this->render('demo.html.twig');
    }
}
