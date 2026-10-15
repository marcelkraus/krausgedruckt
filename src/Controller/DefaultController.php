<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContactRequest;
use App\Service\SignedTimestamp;
use App\Service\TimestampState;
use Krausgebaut\KongtentBundle\Block\Block;
use Krausgebaut\KongtentBundle\Block\HeadingBlock;
use Krausgebaut\KongtentBundle\Client;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DefaultController extends AbstractController
{
    private const HOMEPAGE_POST_LIMIT = 3;

    protected Serializer $serializer;

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly SignedTimestamp $signedTimestamp,
        #[Autowire('%env(CONTACT_TO)%')]
        private readonly string $contactTo,
        #[Autowire('%env(CONTACT_FROM)%')]
        private readonly string $contactFrom,
        #[Autowire(service: 'limiter.contact_form')]
        private readonly RateLimiterFactoryInterface $contactFormLimiter,
    ) {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );
    }

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    public function homepage(Client $kongtent): Response
    {
        return $this->render('content/homepage.html.twig', [
            'posts' => array_slice($kongtent->all(), 0, self::HOMEPAGE_POST_LIMIT),
        ]);
    }

    #[Route('/advintage', name: 'app_landing_page_advintage', methods: ['GET'])]
    public function advintage(): Response
    {
        // A relative path would resolve against the working directory of the process.
        $printableModels = $this->serializer->deserialize(
            file_get_contents($this->getParameter('kernel.project_dir') . '/config/advintage-landing-page.json'),
            'App\Entity\PrintableModel[]',
            'json'
        );

        return $this->render('content/advintage-landing-page.html.twig', [
            'printableModels' => $printableModels,
        ]);
    }

    #[Route('/kontakt', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        if ($request->isMethod('POST') === false) {
            $discountCode = (string) $request->query->get('discount-code', '');

            return $this->renderContactPage(['discountCode' => $discountCode]);
        }

        $timestampState = $this->signedTimestamp->state(
            (string) $request->request->get('ts', ''),
            (string) $request->request->get('ts_sig', ''),
        );

        // A clear bot signal gets a fake success, so the bot learns nothing.
        $honeypot = trim((string) $request->request->get('website', ''));
        if ($honeypot !== '' || $timestampState === TimestampState::Invalid || $timestampState === TimestampState::TooFast) {
            $this->addFlash('contact_success', true);

            return $this->redirectToRoute('app_contact');
        }

        $contactRequest = new ContactRequest();
        $contactRequest->name = trim((string) $request->request->get('name', ''));
        $contactRequest->email = trim((string) $request->request->get('email', ''));
        $contactRequest->phone = trim((string) $request->request->get('phone', ''));
        $contactRequest->discountCode = trim((string) $request->request->get('discountCode', ''));
        $contactRequest->message = trim((string) $request->request->get('message', ''));

        // The constraints are written in order of relevance; the first one wins.
        $errors = [];
        foreach ($this->validator->validate($contactRequest) as $violation) {
            $errors[$violation->getPropertyPath()] ??= $violation->getMessage();
        }

        if ($this->isCsrfTokenValid('contact', (string) $request->request->get('_token')) === false) {
            $errors['form'] = 'Deine Sitzung ist abgelaufen. Bitte sende das Formular noch einmal ab.';
        }

        // A stale signature is a real visitor whose form sat open too long.
        if ($timestampState === TimestampState::Expired) {
            $errors['form'] = 'Das Formular war zu lange geöffnet. Bitte sende es noch einmal ab.';
        }

        if ($errors !== []) {
            return $this->renderContactErrors($request, $errors);
        }

        // Per IP, so a scraped token cannot be replayed into a flood.

        if ($this->contactFormLimiter->create($request->getClientIp() ?? 'anonymous')->consume(1)->isAccepted() === false) {
            return $this->renderContactErrors($request, [
                'form' => 'Es sind zu viele Nachrichten eingegangen. Bitte versuche es später noch einmal.',
            ]);
        }

        try {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->contactFrom, 'krausgedruckt von Marcel Kraus'))
                    ->to($this->contactTo)
                    ->replyTo(new Address($contactRequest->email, $contactRequest->name))
                    ->subject(sprintf('Nachricht von %s', $contactRequest->name))
                    ->textTemplate('content/contact.txt.twig')
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
                'form' => 'Die Nachricht konnte gerade nicht zugestellt werden. Bitte versuche es später noch einmal oder schreibe mir per E-Mail.',
            ]);
        }

        // The confirmation takes the form's place; a reload cannot send twice.
        $this->addFlash('contact_success', true);

        return $this->redirectToRoute('app_contact');
    }

    /**
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
        $issued = $this->signedTimestamp->reissueOrKeep($timestamp ?? '', $signature ?? '');

        return $this->render('content/contact.html.twig', [
            'contact_errors' => $errors,
            'contact_focus' => $focus,
            'contact_old' => $old,
            'contact_timestamp' => $issued['timestamp'],
            'contact_timestamp_signature' => $issued['signature'],
        ], new Response('', $status));
    }

    /**
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

        // A fresh timestamp would make a quick fix-and-resend look too fast.
        return $this->renderContactPage(
            $request->request->all(),
            $errors,
            $focus,
            (string) $request->request->get('ts', ''),
            (string) $request->request->get('ts_sig', ''),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    #[Route('/kontakt-per-email', name: 'app_contact_email', methods: ['GET'])]
    public function emailRedirect(): Response {
        return $this->redirect('mailto:' . $this->getParameter('app.contact_email_address'));
    }

    #[Route('/kontakt-per-whats-app', name: 'app_contact_whats_app', methods: ['GET'])]
    public function whatsAppRedirect(): Response {
        return $this->redirect($this->getParameter('app.whats_app_url'));
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $disallowed = [
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
    public function sitemap(Client $kongtent): Response
    {
        // The landing page is reached through its own link only.
        $locations = [
            [$this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), '1.0'],
            [$this->generateUrl('app_print_order', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.9'],
            [$this->generateUrl('app_blog', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.8'],
            [$this->generateUrl('app_faq', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.8'],
            [$this->generateUrl('app_app', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.6'],
            [$this->generateUrl('app_contact', [], UrlGeneratorInterface::ABSOLUTE_URL), '0.6'],
        ];

        foreach ($kongtent->all() as $post) {
            $locations[] = [
                $this->generateUrl(
                    'app_blog_post',
                    ['year' => $post->getYear(), 'slug' => $post->slug],
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
    public function review(): Response {
        return $this->redirect($this->getParameter('app.google_review_url'));
    }

    #[Route('/app', name: 'app_app', methods: ['GET'])]
    public function app(): Response
    {
        return $this->render('content/app.html.twig', [
            'appStoreUrlMobile' => $this->getParameter('app.app_store_url_mobile'),
        ]);
    }

    // A heading without an answer and whatever precedes the first heading drop out.
    #[Route('/haeufig-gestellte-fragen', name: 'app_faq', methods: ['GET'])]
    public function faq(Client $kongtent): Response
    {
        $page = $kongtent->one('haeufig-gestellte-fragen');

        if (null === $page) {
            throw $this->createNotFoundException();
        }

        /** @var list<array{question: HeadingBlock, answer: list<Block>}> $entries */
        $entries = [];
        foreach ($page->blocks as $block) {
            if ($block instanceof HeadingBlock) {
                $entries[] = ['question' => $block, 'answer' => []];
            } elseif ([] !== $entries) {
                $entries[\count($entries) - 1]['answer'][] = $block;
            }
        }

        return $this->render('content/faq.html.twig', [
            'page' => $page,
            'faqEntries' => array_values(array_filter($entries, static fn (array $entry): bool => [] !== $entry['answer'])),
        ]);
    }

    #[Route('/datenschutz', name: 'app_data_privacy', methods: ['GET'])]
    public function dataPrivacy(): Response
    {
        return $this->render('content/data-privacy.html.twig');
    }

    #[Route('/impressum', name: 'app_imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->render('content/imprint.html.twig');
    }
}
