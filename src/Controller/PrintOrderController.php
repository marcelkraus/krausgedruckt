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
 * Every request that reaches outwards passes a limiter, whichever way in;
 * otherwise the route is an amplifier.
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

        // Checked before anything reaches outwards; a campaign GET carries no token.
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
     * Relayed, so the platform never sees a visitor; signed, so only an address
     * this site produced is fetched.
     */
    #[Route('/modell-bild', name: 'app_model_image', methods: ['GET'])]
    public function image(Request $request, PlatformFetcher $fetcher): Response
    {
        // Path and query only: the host differs behind a tunnel or a proxy.
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
     * A failure never closes the assistant: the address alone is enough.
     *
     * @return array{0: ?ModelPreview, 1: ?string}
     */
    private function look(Request $request, string $url): array
    {
        if ($url === '') {
            return [null, null];
        }

        if ($this->lookupLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted() === false) {
            return [null, 'Das waren gerade viele Anfragen. Schick mir den Link trotzdem – ich sehe selbst nach.'];
        }

        try {
            return [$this->modelLookup->lookup($url), null];
        } catch (LookupFailedException $exception) {
            return [null, match ($exception->failure) {
                LookupFailure::NotAModelPage => 'Das sieht nach einer Übersicht aus, nicht nach einem einzelnen Modell. Nimm die Seite des Modells selbst – oder schick mir den Link trotzdem.',
                LookupFailure::UnsupportedAddress => 'Ich lese aktuell nur Modelle von Printables. Von anderen Seiten kann ich diese leider (noch) nicht laden – schick mir den Link gerne trotzdem, ich sehe mir das gerne an.',
                LookupFailure::NoMetadata, LookupFailure::Unreachable => 'Ich konnte die Seite gerade nicht lesen. Schick mir den Link trotzdem – ich sehe selbst nach.',
            }];
        }
    }

    private function submit(Request $request): Response
    {
        $timestampState = $this->signedTimestamp->state(
            (string) $request->request->get('ts', ''),
            (string) $request->request->get('ts_sig', ''),
        );

        // A clear bot signal gets a fake success, so the bot learns nothing.
        $honeypot = trim((string) $request->request->get('website', ''));

        if ($honeypot !== '' || $timestampState === TimestampState::Invalid || $timestampState === TimestampState::TooFast) {
            $this->addFlash('print_order_success', true);

            return $this->redirectToRoute('app_print_order');
        }

        $order = new PrintOrderRequest();
        $order->url = trim((string) $request->request->get('url', ''));
        // Not a cast: “abc” would become 0, a missing quantity.
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

        // A stale signature is a real visitor whose form sat open too long.
        if ($timestampState === TimestampState::Expired) {
            $errors['form'] = 'Das Formular war zu lange geöffnet. Bitte sende es noch einmal ab.';
        }

        if ($errors !== []) {
            return $this->renderErrors($request, $errors);
        }

        // Unthrottled, a route that mails an address the sender picks is an open relay.
        if ($this->orderLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted() === false) {
            return $this->renderErrors($request, [
                'form' => 'Es sind zu viele Anfragen eingegangen. Bitte versuche es später noch einmal.',
            ]);
        }

        try {
            $this->send($order, $this->look($request, $order->url)[0]);
        } catch (TransportExceptionInterface) {
            return $this->renderErrors($request, [
                'form' => 'Die Anfrage konnte gerade nicht zugestellt werden. Bitte versuche es später noch einmal oder schreibe mir per E-Mail.',
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

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->contactFrom, 'krausgedruckt von Marcel Kraus'))
                ->to($order->email)
                ->replyTo(new Address($this->contactTo))
                ->subject('Deine Anfrage ist bei mir angekommen')
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

    private function signedImageUrl(string $imageUrl): string
    {
        return $this->uriSigner->sign($this->generateUrl('app_model_image', ['url' => $imageUrl]));
    }
}
