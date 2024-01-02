<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Form\Type\ContactRequestType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticController extends AbstractController
{
    #[Route('/', name: 'app_static_homepage', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('static/homepage.html.twig', [
            'contactForm' => $this->createForm(ContactRequestType::class, new ContactRequest()),
        ]);
    }

    #[Route('/impressum', name: 'app_static_imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->render('static/imprint.html.twig');
    }

    #[Route('/datenschutz', name: 'app_static_data_privacy', methods: ['GET'])]
    public function dataPrivacy(): Response
    {
        return $this->render('static/data-privacy.html.twig');
    }
}
