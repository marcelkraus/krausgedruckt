<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Form\Type\ContactRequestType;
use App\Repository\FaqEntryRepository;
use App\Repository\ReferenceRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DefaultController extends AbstractController
{
    protected Serializer $serializer;

    function __construct() {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );
    }

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    function homepage(): Response
    {
        return $this->render('default/homepage.html.twig');
    }

    #[Route('/advintage', name: 'app_landing_page_advintage', methods: ['GET'])]
    function advintage(): Response
    {
        $printableModels = $this->serializer->deserialize(
            file_get_contents("../config/advintage-landing-page.json"),
            'App\Entity\PrintableModel[]',
            'json'
        );

        return $this->render('default/advintage-landing-page.html.twig', [
            'printableModels' => $printableModels,
        ]);
    }

    #[Route('/kontakt', name: 'app_contact', methods: ['GET', 'POST'])]
    function contact(Request $request, MailerInterface $mailer): Response
    {
        $contactRequest = new ContactRequest();
        if ($discountCode = $request->query->get('discount-code', null)) {
            $contactRequest->setDiscountCode($discountCode);
        }

        $contactForm = $this->createForm(ContactRequestType::class, $contactRequest, [
            'antispam_profile' => 'default',
        ]);
        $contactForm->handleRequest($request);

        if ($contactForm->isSubmitted() && $contactForm->isValid()) {
            $contactRequest = $contactForm->getData();
            $message = (new TemplatedEmail())
                ->from($_SERVER['CONTACT_FORM_SENDER_ADDRESS'])
                ->to($_SERVER['CONTACT_FORM_RECIPIENT_ADDRESS'])
                ->replyTo($contactRequest->getEmail())
                ->subject('Neue Kontaktanfrage erhalten')
                ->textTemplate('default/contact.txt.twig')
                ->context([
                    'name' => $contactRequest->getName(),
                    'emailAddress' => $contactRequest->getEmail(),
                    'message' => $contactRequest->getMessage(),
                    'discountCode' => $contactRequest->getDiscountCode(),
                ]);

            $mailer->send($message);

            return $this->redirectToRoute('app_contact_confirmation');
        }

        return $this->render('default/contact.html.twig', [
            'contactForm' => $contactForm,
        ]);
    }

    #[Route('/kontakt-per-email', name: 'app_contact_email', methods: ['GET'])]
    function emailRedirect(): Response {
        return $this->redirect('mailto:hallo@krausgedruckt.de');
    }

    #[Route('/kontakt-per-whats-app', name: 'app_contact_whats_app', methods: ['GET'])]
    function whatsAppRedirect(): Response {
        return $this->redirect('https://wa.me/message/TKVKBR7RPHA7B1');
    }

    #[Route('/bewerten', name: 'app_review', methods: ['GET'])]
    function review(): Response {
        return $this->redirect($_SERVER['GOOGLE_REVIEW_URL']);
    }

    #[Route('/kontakt/bestaetigung', name: 'app_contact_confirmation', methods: ['GET'])]
    function contactConfirmation(): Response
    {
        return $this->render('default/contact-confirmation.html.twig');
    }

    #[Route('/referenzen', name: 'app_references', methods: ['GET'])]
    function references(ReferenceRepository $referenceRepository): Response
    {
        $references = $referenceRepository->findAllOrdered();

        return $this->render('default/references.html.twig', [
            'references' => $references,
        ]);
    }

    #[Route('/haeufig-gestellte-fragen', name: 'app_faq', methods: ['GET'])]
    function faq(FaqEntryRepository $faqEntryRepository): Response
    {
        $faqEntries = $faqEntryRepository->findAllOrdered();

        return $this->render('default/faq.html.twig', [
            'faqEntries' => $faqEntries,
        ]);
    }

    #[Route('/datenschutz', name: 'app_data_privacy', methods: ['GET'])]
    function dataPrivacy(): Response
    {
        return $this->render('default/data-privacy.html.twig');
    }

    #[Route('/impressum', name: 'app_imprint', methods: ['GET'])]
    function imprint(): Response
    {
        return $this->render('default/imprint.html.twig');
    }
}
