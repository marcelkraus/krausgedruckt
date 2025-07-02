<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Form\Type\ContactRequestType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticController extends AbstractController
{
    #[Route('/datenschutz', name: 'app_data_privacy', methods: ['GET'])]
    function dataPrivacy(): Response
    {
        return $this->render('static/data-privacy.html.twig');
    }

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    function homepage(): Response
    {
        return $this->render('static/homepage.html.twig', [
            'contactForm' => $this->createForm(ContactRequestType::class, new ContactRequest(), [
                'antispam_profile' => 'default',
            ]),
        ]);
    }

    #[Route('/impressum', name: 'app_imprint', methods: ['GET'])]
    function imprint(): Response
    {
        return $this->render('static/imprint.html.twig');
    }
}
