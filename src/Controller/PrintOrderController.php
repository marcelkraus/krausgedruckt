<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ModelPreview;
use App\Dto\PrintOrderRequest;
use App\Dto\UsageContext;
use App\Service\LookupFailedException;
use App\Service\LookupFailure;
use App\Service\ModelLookup;
use App\Service\PlatformFetcher;
use App\Service\SignedTimestamp;
use App\Service\TimestampState;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The print order by model link: the assistant and the image relay behind
 * it.
 *
 * **The assistant works without JavaScript.** One form with two submit
 * buttons: „Weiter“ reads the model and renders again, „Anfrage senden“
 * sends. Enter in the address field triggers the first one, which is the
 * reading. Nothing is kept on the server – no session state, no draft
 * record – and the page can also be reached with `?url=`, so a campaign
 * link carries a model straight into it.
 *
 * **Every request that reaches outwards passes a limiter, whichever way
 * in.** Reading and sending both leave this host on a stranger's word, and
 * a route that does that without counting is an amplifier.
 */
final class PrintOrderController extends AbstractController
{
    public function __construct(
        private readonly ModelLookup $modelLookup,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly SignedTimestamp $signedTimestamp,
        private readonly UriSigner $uriSigner,
        #[Autowire('%env(CONTACT_TO)%')]
        private readonly string $contactTo,
        #[Autowire('%env(CONTACT_FROM)%')]
        private readonly string $contactFrom,
        #[Autowire(service: 'limiter.model_lookup')]
        private readonly RateLimiterFactoryInterface $lookupLimiter,
        #[Autowire(service: 'limiter.model_image')]
        private readonly RateLimiterFactoryInterface $imageLimiter,
        #[Autowire(service: 'limiter.print_order')]
        private readonly RateLimiterFactoryInterface $orderLimiter,
    ) {
    }

    #[Route('/modell-drucken', name: 'app_print_order', methods: ['GET', 'POST'])]
    public function assistant(Request $request): Response
    {
        if ($request->isMethod('POST') === false) {
            $url = trim((string) $request->query->get('url', ''));

            return $url === ''
                ? $this->renderAssistant()
                : $this->renderAssistant(['url' => $url], ...$this->look($request, $url));
        }

        // The token is checked before a submission reaches outwards, so a
        // forged post cannot spend the lookup budget or send a mail. The
        // GET path carries no token by design – a campaign link has none –
        // and stands on the rate limiter alone.
        if ($this->isCsrfTokenValid('print_order', (string) $request->request->get('_token')) === false) {
            return $this->renderAssistant(
                $request->request->all(),
                null,
                null,
                ['form' => 'Deine Sitzung ist abgelaufen. Bitte sende das Formular noch einmal ab.'],
                (string) $request->request->get('ts', ''),
                (string) $request->request->get('ts_sig', ''),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($request->request->has('lookup')) {
            return $this->renderAssistant(
                $request->request->all(),
                ...$this->look($request, trim((string) $request->request->get('url', '')))
            );
        }

        return $this->submit($request);
    }

    /**
     * Passes the platform's preview image through instead of embedding it
     * directly.
     *
     * An `img src` pointing at the platform hands it the address of every
     * visitor who opens the page and makes it a third party in the privacy
     * policy. Relaying keeps that out without storing a copy, which is what
     * would turn an embedded picture into a reproduction of our own.
     *
     * **The address is signed, so only one this site produced is fetched.**
     * Without the signature the route is a fetch service for the platform,
     * open to anyone: the host list would keep the request inside it, but
     * nothing would tie it to a page a visitor actually asked for.
     */
    #[Route('/modell-bild', name: 'app_model_image', methods: ['GET'])]
    public function image(Request $request, PlatformFetcher $fetcher): Response
    {
        // Checked against path and query alone, because that is what was
        // signed: an absolute signature carries the host, and the host
        // differs behind a tunnel or a proxy while the site is the same.
        if ($this->uriSigner->check($request->getRequestUri()) === false) {
            throw $this->createNotFoundException();
        }

        $limit = $this->imageLimiter->create($request->getClientIp() ?? 'anonymous')->consume();

        if ($limit->isAccepted() === false) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        try {
            $image = $fetcher->fetchImage((string) $request->query->get('url', ''));
        } catch (LookupFailedException) {
            throw $this->createNotFoundException();
        }

        $response = new Response($image['body'], Response::HTTP_OK, [
            'Content-Type' => $image['type'],
            'Content-Disposition' => 'inline',
        ]);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }

    /**
     * Reads the model, and turns a failure into a sentence.
     *
     * **A failure never closes the assistant.** Whatever went wrong, the
     * remaining steps open and the inquiry can be sent: the address alone
     * is enough for the workshop, and the preview is a convenience, not the
     * product.
     *
     * Every path that fetches goes through here, so the limiter cannot be
     * stepped around by choosing a different method.
     *
     * @return array{0: ?ModelPreview, 1: ?string}
     */
    private function look(Request $request, string $url): array
    {
        if ($url === '') {
            return [null, null];
        }

        if ($this->lookupLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted() === false) {
            return [null, 'Das waren gerade viele Anfragen. Schick uns den Link trotzdem – wir sehen selbst nach.'];
        }

        try {
            return [$this->modelLookup->lookup($url), null];
        } catch (LookupFailedException $exception) {
            return [null, match ($exception->failure) {
                LookupFailure::NotAModelPage => 'Das sieht nach einer Übersicht aus, nicht nach einem einzelnen Modell. Nimm die Seite des Modells selbst – oder schick uns den Link trotzdem.',
                LookupFailure::UnsupportedAddress => 'Wir lesen aktuell nur Modelle von Printables. Von anderen Seiten können wir diese leider (noch) nicht laden – schick uns den Link gerne trotzdem, wir sehen uns das gerne an.',
                LookupFailure::NoMetadata, LookupFailure::Unreachable => 'Wir konnten die Seite gerade nicht lesen. Schick uns den Link trotzdem – wir sehen selbst nach.',
            }];
        }
    }

    private function submit(Request $request): Response
    {
        $timestampState = $this->signedTimestamp->state(
            (string) $request->request->get('ts', ''),
            (string) $request->request->get('ts_sig', ''),
        );

        // Silent drop for clear bot signals, with a fake success so nothing
        // is learned from the difference – the contact form does the same.
        $honeypot = trim((string) $request->request->get('website', ''));

        if ($honeypot !== '' || $timestampState === TimestampState::Invalid || $timestampState === TimestampState::TooFast) {
            $this->addFlash('print_order_success', true);

            return $this->redirectToRoute('app_print_order');
        }

        $order = new PrintOrderRequest();
        $order->url = trim((string) $request->request->get('url', ''));
        // Not a cast: „abc“ would become 0 and be answered as a missing
        // quantity rather than as one that is not a number.
        $quantity = trim((string) $request->request->get('quantity', ''));
        $order->quantity = ctype_digit($quantity) ? (int) $quantity : null;
        $order->context = array_values(array_filter(
            (array) $request->request->all('context'),
            static fn (mixed $value): bool => is_string($value),
        ));
        $order->color = trim((string) $request->request->get('color', ''));
        $order->message = trim((string) $request->request->get('message', ''));
        $order->email = trim((string) $request->request->get('email', ''));

        $errors = [];
        foreach ($this->validator->validate($order) as $violation) {
            $errors[$violation->getPropertyPath()] ??= $violation->getMessage();
        }

        // A valid but stale signature is a real person whose form sat open
        // too long – never silently drop it, ask them to resend instead.
        if ($timestampState === TimestampState::Expired) {
            $errors['form'] = 'Das Formular war zu lange geöffnet. Bitte sende es noch einmal ab.';
        }

        if ($errors !== []) {
            return $this->renderErrors($request, $errors);
        }

        // Throttle per address: an inquiry sends two mails, one of them to
        // an address the sender picks and with text the sender writes, so
        // an unthrottled route is an open relay.
        if ($this->orderLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted() === false) {
            return $this->renderErrors($request, [
                'form' => 'Es sind zu viele Anfragen eingegangen. Bitte versuche es später noch einmal.',
            ]);
        }

        try {
            $this->send($order, $this->look($request, $order->url)[0]);
        } catch (TransportExceptionInterface) {
            return $this->renderErrors($request, [
                'form' => 'Die Anfrage konnte gerade nicht zugestellt werden. Bitte versuche es später noch einmal oder schreibe uns per E-Mail.',
            ]);
        }

        $this->addFlash('print_order_success', true);

        return $this->redirectToRoute('app_print_order');
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function send(PrintOrderRequest $order, ?ModelPreview $preview): void
    {
        $context = [
            'color' => $order->color,
            'emailAddress' => $order->email,
            'message' => $order->message,
            'preview' => $preview,
            'quantity' => $order->quantity,
            'url' => $order->url,
            'usage' => array_map(
                static fn (string $value): string => UsageContext::from($value)->label(),
                $order->context,
            ),
        ];

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->contactFrom, 'krausgedruckt von Marcel Kraus'))
                ->to($this->contactTo)
                ->replyTo(new Address($order->email))
                ->subject(sprintf('Druckanfrage über „%s“', $preview?->title ?? $order->url))
                ->textTemplate('content/print-order.txt.twig')
                ->context($context)
        );

        // The confirmation is where the instruction behind the license
        // question is written down – it reaches the customer and stays with
        // him, which a sentence on a page he has left does not. It answers
        // to the workshop, because „Bis gleich“ invites a reply.
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->contactFrom, 'krausgedruckt von Marcel Kraus'))
                ->to($order->email)
                ->replyTo(new Address($this->contactTo))
                ->subject('Deine Anfrage ist bei uns angekommen')
                ->textTemplate('content/print-order-confirmation.txt.twig')
                ->context($context)
        );
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderErrors(Request $request, array $errors): Response
    {
        [$preview, $lookupError] = $this->look($request, trim((string) $request->request->get('url', '')));

        $focus = null;
        foreach (array_keys($errors) as $field) {
            if ($field !== 'form') {
                $focus = $field;
                break;
            }
        }

        return $this->renderAssistant(
            $request->request->all(),
            $preview,
            $lookupError,
            $errors,
            (string) $request->request->get('ts', ''),
            (string) $request->request->get('ts_sig', ''),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $focus,
        );
    }

    /**
     * @param array<string, mixed>  $old
     * @param array<string, string> $errors
     */
    private function renderAssistant(
        array $old = [],
        ?ModelPreview $preview = null,
        ?string $lookupError = null,
        array $errors = [],
        string $timestamp = '',
        string $signature = '',
        int $status = Response::HTTP_OK,
        ?string $focus = null,
    ): Response {
        $issued = $this->signedTimestamp->reissueOrKeep($timestamp, $signature);

        return $this->render('content/print-order.html.twig', [
            'errors' => $errors,
            'focus' => $focus,
            'imageUrl' => $preview?->imageUrl !== null ? $this->signedImageUrl($preview->imageUrl) : null,
            'lookupError' => $lookupError,
            'old' => $old,
            'preview' => $preview,
            'timestamp' => $issued['timestamp'],
            'timestampSignature' => $issued['signature'],
            'usageContexts' => UsageContext::inDisplayOrder(),
        ], new Response('', $status));
    }

    /**
     * Signed without a host, so the same page works under whichever name
     * it is reached – the site's own, a tunnel, a proxy. The signature
     * covers path and query, and the host is ours in every case.
     */
    private function signedImageUrl(string $imageUrl): string
    {
        return $this->uriSigner->sign($this->generateUrl('app_model_image', ['url' => $imageUrl]));
    }
}
