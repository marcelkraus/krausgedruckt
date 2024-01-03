<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Form\Type\ContactRequestType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/', methods: ['POST'])]
    function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactRequestType::class, new ContactRequest());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactRequest = $form->getData();
            $message = (new TemplatedEmail())
                ->from($_SERVER['CONTACT_FORM_SENDER_ADDRESS'])
                ->to($_SERVER['CONTACT_FORM_RECIPIENT_ADDRESS'])
                ->replyTo($contactRequest->getEmail())
                ->subject('Neue Kontaktanfrage erhalten')
                ->textTemplate('contact.txt.twig')
                ->context([
                    'name' => $contactRequest->getName(),
                    'emailAddress' => $contactRequest->getEmail(),
                    'message' => $contactRequest->getMessage(),
                ]);

            $mailer->send($message);

            return $this->redirectToRoute('app_contact_confirmation');
        }
    }

    #[Route('/vielen-dank-fuer-deine-anfrage', name: 'app_contact_confirmation', methods: ['GET'])]
    function contactConfirmation(): Response
    {
        return $this->render('contact/confirmation.html.twig');
    }
}
