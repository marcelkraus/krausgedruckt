<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Repository\FaqEntryRepository;
use App\Repository\ReferenceRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DefaultController extends AbstractController
{
    /**
     * The number of references the homepage teaser shows before it hands
     * over to the overview page.
     */
    private const HOMEPAGE_REFERENCE_LIMIT = 3;

    /**
     * A submission arriving sooner than this was not typed by a person.
     */
    private const CONTACT_FORM_MINIMUM_AGE = 3;

    /**
     * After this the signed timestamp is stale and the visitor is asked to
     * send again rather than being dropped.
     */
    private const CONTACT_FORM_LIFETIME = 7200;

    protected Serializer $serializer;

    function __construct(
        private readonly ValidatorInterface $validator,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
        #[Autowire(service: 'limiter.contact_form')]
        private readonly RateLimiterFactory $contactFormLimiter,
    ) {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );
    }

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    function homepage(ReferenceRepository $referenceRepository): Response
    {
        return $this->render('default/homepage.html.twig', [
            'references' => $referenceRepository->findAllOrdered(self::HOMEPAGE_REFERENCE_LIMIT),
        ]);
    }

    #[Route('/advintage', name: 'app_landing_page_advintage', methods: ['GET'])]
    function advintage(): Response
    {
        // Anchored to the project directory rather than to the working
        // directory: a relative path resolves against wherever the process
        // was started, which holds for the web server and breaks everywhere
        // else, the test runner included.
        $printableModels = $this->serializer->deserialize(
            file_get_contents($this->getParameter('kernel.project_dir') . '/config/advintage-landing-page.json'),
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
        if ($request->isMethod('POST') === false) {
            // A code handed over in the address pre-fills the field, so a
            // campaign link does not make the visitor copy anything.
            $discountCode = (string) $request->query->get('discount-code', '');

            return $this->renderContactPage(['discountCode' => $discountCode]);
        }

        $timestampState = $this->timestampState($request);

        // Silent drop only for clear bot signals — a filled honeypot, a
        // missing or tampered signature, or an inhumanly fast submission.
        // These get a fake success so bots learn nothing; nothing is sent.
        $honeypot = trim((string) $request->request->get('website', ''));
        if ($honeypot !== '' || $timestampState === 'invalid' || $timestampState === 'too_fast') {
            $this->addFlash('contact_success', true);

            return $this->redirectToRoute('app_contact');
        }

        $contactRequest = new ContactRequest();
        $contactRequest->name = trim((string) $request->request->get('name', ''));
        $contactRequest->email = trim((string) $request->request->get('email', ''));
        $contactRequest->phone = trim((string) $request->request->get('phone', ''));
        $contactRequest->discountCode = trim((string) $request->request->get('discountCode', ''));
        $contactRequest->message = trim((string) $request->request->get('message', ''));

        // Keep the first violation per field: the constraints are written in
        // order of relevance, so an empty message must report NotBlank rather
        // than the minimum-length rule that fires alongside it.
        $errors = [];
        foreach ($this->validator->validate($contactRequest) as $violation) {
            $errors[$violation->getPropertyPath()] ??= $violation->getMessage();
        }

        if ($this->isCsrfTokenValid('contact', (string) $request->request->get('_token')) === false) {
            $errors['form'] = 'Deine Sitzung ist abgelaufen. Bitte sende das Formular noch einmal ab.';
        }

        // A valid but stale signature is a real person whose form sat open
        // too long — never silently drop it, ask them to resend instead.
        if ($timestampState === 'expired') {
            $errors['form'] = 'Das Formular war zu lange geöffnet. Bitte sende es noch einmal ab.';
        }

        if ($errors !== []) {
            return $this->renderContactErrors($request, $errors);
        }

        // Throttle per address so a scraped token cannot be replayed into a
        // flood.
        if ($this->contactFormLimiter->create($request->getClientIp() ?? 'anonymous')->consume(1)->isAccepted() === false) {
            return $this->renderContactErrors($request, [
                'form' => 'Es sind zu viele Anfragen eingegangen. Bitte versuche es später noch einmal.',
            ]);
        }

        // A transport failure must not cost the enquiry and must not hand a
        // visitor a bare error page: the sendmail DSN has two documented ways
        // of being wrong on this host, and Apache replaces the Symfony error
        // page with its own.
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($_SERVER['CONTACT_FORM_SENDER_ADDRESS'], 'krausgedruckt von Marcel Kraus'))
                    ->to($_SERVER['CONTACT_FORM_RECIPIENT_ADDRESS'])
                    ->replyTo($contactRequest->email)
                    ->subject('Neue Kontaktanfrage erhalten')
                    ->textTemplate('default/contact.txt.twig')
                    ->context([
                        'discountCode' => $contactRequest->discountCode,
                        'emailAddress' => $contactRequest->email,
                        'message' => $contactRequest->message,
                        'name' => $contactRequest->name,
                        'phone' => $contactRequest->phone,
                    ])
            );
        } catch (TransportExceptionInterface) {
            return $this->renderContactErrors($request, [
                'form' => 'Die Anfrage konnte gerade nicht zugestellt werden. Bitte versuche es später noch einmal oder schreibe mir per E-Mail.',
            ]);
        }

        // Redirect back onto the same address, as the sister site does: the
        // confirmation takes the form's place instead of living on a page of
        // its own, and a reload cannot send the message twice.
        $this->addFlash('contact_success', true);

        return $this->redirectToRoute('app_contact');
    }

    /**
     * Renders the contact page with a fresh signed timestamp.
     *
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderContactPage(
        array $old = [],
        array $errors = [],
        ?string $focus = null,
        ?string $timestamp = null,
        ?string $signature = null,
        int $status = Response::HTTP_OK,
    ): Response {
        $timestamp ??= (string) time();

        return $this->render('default/contact.html.twig', [
            'contact_errors' => $errors,
            'contact_focus' => $focus,
            'contact_old' => $old,
            'contact_ts' => $timestamp,
            'contact_ts_sig' => $signature ?? $this->signTimestamp($timestamp),
        ], new Response('', $status));
    }

    /**
     * A rejected submission answers 422 rather than 200: the request was
     * understood and refused, and a crawler or a test can tell the two apart
     * without parsing the body.
     *
     * @param array<string, string> $errors
     */
    private function renderContactErrors(Request $request, array $errors): Response
    {
        $focus = null;
        foreach (array_keys($errors) as $field) {
            if ($field !== 'form') {
                $focus = $field;
                break;
            }
        }

        // Reuse the visitor's still-valid timestamp on re-render so a quick
        // fix-and-resend is not misclassified as a bot. Only seed a fresh one
        // when none is reusable — missing, tampered or expired.
        $timestamp = null;
        $signature = null;
        $submittedTimestamp = (string) $request->request->get('ts', '');
        $submittedSignature = (string) $request->request->get('ts_sig', '');
        if ($submittedTimestamp !== ''
            && hash_equals($this->signTimestamp($submittedTimestamp), $submittedSignature)
            && time() - (int) $submittedTimestamp <= self::CONTACT_FORM_LIFETIME
        ) {
            $timestamp = $submittedTimestamp;
            $signature = $submittedSignature;
        }

        return $this->renderContactPage(
            $request->request->all(),
            $errors,
            $focus,
            $timestamp,
            $signature,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Classifies the signed timestamp the form carries. The signature is what
     * makes the age trustworthy: without it a bot would simply post a value
     * that looks old enough.
     */
    private function timestampState(Request $request): string
    {
        $timestamp = (string) $request->request->get('ts', '');
        $signature = (string) $request->request->get('ts_sig', '');

        if ($timestamp === '' || hash_equals($this->signTimestamp($timestamp), $signature) === false) {
            return 'invalid';
        }

        $elapsed = time() - (int) $timestamp;

        if ($elapsed < self::CONTACT_FORM_MINIMUM_AGE) {
            return 'too_fast';
        }

        if ($elapsed > self::CONTACT_FORM_LIFETIME) {
            return 'expired';
        }

        return 'valid';
    }

    private function signTimestamp(string $timestamp): string
    {
        return hash_hmac('sha256', $timestamp, $this->appSecret);
    }

    #[Route('/kontakt-per-email', name: 'app_contact_email', methods: ['GET'])]
    function emailRedirect(): Response {
        return $this->redirect('mailto:' . $this->getParameter('app.contact_email_address'));
    }

    #[Route('/kontakt-per-whats-app', name: 'app_contact_whats_app', methods: ['GET'])]
    function whatsAppRedirect(): Response {
        return $this->redirect($this->getParameter('app.whats_app_url'));
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    function robots(): Response
    {
        $sitemap = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // The redirect routes carry no document, only a Location header — and
        // for the two contact routes that header holds the address. A
        // well-behaved crawler follows it and takes the address into its
        // corpus, and those corpora are where address lists come from.
        // Harvesters ignore robots.txt, but the corpora do not.
        $disallowed = [
            '/bewerten',
            '/kontakt-per-email',
            '/kontakt-per-whats-app',
        ];

        $rules = "User-agent: *\n";
        foreach ($disallowed as $route) {
            $rules .= "Disallow: {$route}\n";
        }

        return new Response(
            $rules . "\nSitemap: {$sitemap}\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    function sitemap(ReferenceRepository $referenceRepository): Response
    {
        // The public pages plus every visible reference. The legal pages and
        // the confirmation are noindex and stay out, and so does the landing
        // page, which is only meant to be reached through its own link.
        $locations = [
            [$this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), '1.0'],
            [$this->generateUrl('app_references', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.8'],
            [$this->generateUrl('app_faq', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.8'],
            [$this->generateUrl('app_app', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.6'],
            [$this->generateUrl('app_contact', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.6'],
        ];

        foreach ($referenceRepository->findAllOrdered() as $reference) {
            $locations[] = [
                $this->generateUrl(
                    'app_reference_detail',
                    ['year' => $reference->getYear(), 'slug' => $reference->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                '0.7',
            ];
        }

        $urls = '';
        foreach ($locations as [$location, $priority]) {
            $location = htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $urls .= "    <url>\n        <loc>{$location}</loc>\n        <changefreq>monthly</changefreq>\n        <priority>{$priority}</priority>\n    </url>\n";
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $urls
            . "</urlset>\n";

        return new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    #[Route('/bewerten', name: 'app_review', methods: ['GET'])]
    function review(): Response {
        return $this->redirect($_SERVER['GOOGLE_REVIEW_URL']);
    }

    #[Route('/app', name: 'app_app', methods: ['GET'])]
    function app(): Response
    {
        return $this->render('default/app.html.twig', [
            'appStoreUrlMobile' => $_SERVER['APP_STORE_URL_MOBILE'],
            'appStoreUrlDesktop' => $_SERVER['APP_STORE_URL_DESKTOP'],
        ]);
    }

    #[Route('/referenzen', name: 'app_references', methods: ['GET'])]
    function references(ReferenceRepository $referenceRepository): Response
    {
        $references = $referenceRepository->findAllOrdered();

        return $this->render('default/references.html.twig', [
            'references' => $references,
        ]);
    }

    #[Route('/referenzen/{year}/{slug}', name: 'app_reference_detail', methods: ['GET'])]
    function referenceDetail(int $year, string $slug, ReferenceRepository $referenceRepository): Response
    {
        $reference = $referenceRepository->findByYearAndSlug($year, $slug);

        if ($reference === null || $reference->isVisible() === false) {
            throw $this->createNotFoundException();
        }

        return $this->render('default/reference-detail.html.twig', [
            'reference' => $reference,
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
