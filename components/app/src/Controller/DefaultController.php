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

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(Request $request, MailerInterface $mailer): Response
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
                ->textTemplate('email/contact.txt.twig')
                ->context([
                    'name' => $contactRequest->getName(),
                    'emailAddress' => $contactRequest->getEmail(),
                    'message' => $contactRequest->getMessage(),
                ]);

            $mailer->send($message);

            return $this->redirectToRoute('app_contact_confirmation');
        }

        return $this->render('content/homepage.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/vielen-dank-fuer-deine-anfrage', name: 'app_contact_confirmation')]
    public function contactConfirmation(): Response
    {
        return $this->render('content/contact-confirmation.html.twig');
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
}
