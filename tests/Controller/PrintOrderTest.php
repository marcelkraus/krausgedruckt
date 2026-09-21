<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Dto\UsageContext;
use App\Service\HostResolver;
use App\Service\PlatformFetcher;
use App\Service\SignedTimestamp;
use App\Tests\Double\FixedHostResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The assistant: what opens when, what is refused, and what goes out.
 *
 * The platform is replaced with a recording throughout, so no test reaches
 * Printables. The signed timestamp is aged by hand – a submission younger
 * than three seconds is dropped as a machine, and a test suite is always
 * younger than that.
 */
final class PrintOrderTest extends WebTestCase
{
    private const string MODEL = 'https://www.printables.com/model/1042371-tpu-lid-instert';

    private const string PATH = '/modell-drucken';

    public function testTheFirstStepIsOpenAndTheOthersAreLocked(): void
    {
        $crawler = static::createClient()->request('GET', self::PATH);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
        self::assertCount(1, $crawler->filter('#schritt-1 input[name="url"]'));
        self::assertCount(0, $crawler->filter('#schritt-2 input[name="context[]"]'));
        self::assertCount(0, $crawler->filter('#schritt-3 input[name="email"]'));

        // A locked step announces itself: no fields, but a heading that a
        // screen reader can find, so it is a step to come and not a hole.
        self::assertSame('Deine Details', trim($crawler->filter('#schritt-2 h2')->text()));
        self::assertSame('Dein Kontakt', trim($crawler->filter('#schritt-3 h2')->text()));
    }

    public function testAReadModelOpensTheRemainingSteps(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $crawler = $client->request('GET', self::PATH.'?url='.urlencode(self::MODEL));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('TPU lid instert for rugged SD card case', $crawler->filter('#schritt-1')->text());
        self::assertCount(6, $crawler->filter('#schritt-2 input[name="context[]"]'));

        // Bound to the enum rather than to a number: a case the display
        // order forgets stays valid in the validator and never reaches the
        // page, and a count would still be green.
        self::assertEqualsCanonicalizing(UsageContext::cases(), UsageContext::inDisplayOrder());
        self::assertCount(1, $crawler->filter('#schritt-3 input[name="email"]'));
    }

    /**
     * The failure path is the promise of the whole assistant: whatever the
     * platform does, the inquiry must stay sendable.
     */
    public function testAFailedLookupOpensTheStepsAnyway(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse('', ['http_code' => 503])]);

        $crawler = $client->request('GET', self::PATH.'?url='.urlencode(self::MODEL));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Schick uns den Link trotzdem', $crawler->filter('#schritt-1')->text());
        self::assertCount(1, $crawler->filter('#schritt-3 input[name="email"]'));
    }

    public function testAnAddressThatIsNoModelPageSaysSo(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $crawler = $client->request('GET', self::PATH.'?url='.urlencode('https://www.printables.com/@someone'));

        self::assertStringContainsString('nicht nach einem einzelnen Modell', $crawler->filter('#schritt-1')->text());
    }

    public function testAnAddressOnAPlatformWeDoNotReadSaysSomethingElse(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $crawler = $client->request('GET', self::PATH.'?url='.urlencode('https://www.thingiverse.com/thing:4711'));

        self::assertStringContainsString('Wir lesen aktuell nur Modelle von Printables', $crawler->filter('#schritt-1')->text());
    }

    public function testAValidInquiryRedirectsAndSendsTwoMails(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $client->request('POST', self::PATH, $this->validPayload($client));

        self::assertResponseRedirects(self::PATH);
        self::assertEmailCount(2);
    }

    /**
     * One mail reaches the workshop with the license, the other reaches the
     * customer without it – it is a fact for the calculation, not a promise
     * to the visitor.
     */
    public function testTheWorkshopMailCarriesTheLicenseAndTheCustomerMailDoesNot(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $client->request('POST', self::PATH, $this->validPayload($client));

        $workshop = self::getMailerMessage(0)->getTextBody();
        $customer = self::getMailerMessage(1)->getTextBody();

        self::assertStringContainsString('Noncommercial', $workshop);
        self::assertStringContainsString('Muss hohe Temperaturen aushalten können', $workshop);
        self::assertStringContainsString('3 Stück', $workshop);
        self::assertStringNotContainsString('Noncommercial', $customer);
        self::assertStringContainsString('Kosten entstehen dir bis zur ausdrücklichen Freigabe', $customer);
    }

    /**
     * **A dash would be read as „not given“**, and the three cases that
     * carry a sentence instead do not mean that: a page that could not be
     * read, a platform that names no license, and a visitor who clicked
     * nothing are three different starting points for the calculation. The
     * mail is where the workshop learns which one it has, and a text that
     * quietly turns into a dash breaks nothing that is visible.
     */
    public function testTheWorkshopMailSaysWhyAFieldIsEmptyRatherThanShowingADash(): void
    {
        $client = static::createClient();
        $this->replaceFetcher(array_fill(0, 4, new MockResponse('', ['http_code' => 503])));

        $payload = $this->validPayload($client);
        unset($payload['context']);

        $client->request('POST', self::PATH, $payload);

        $workshop = self::getMailerMessage(0)->getTextBody();

        self::assertStringContainsString('Modell: nicht gelesen', $workshop);
        self::assertStringContainsString('Material nach unserer Einschätzung', $workshop);
    }

    /**
     * A refused submission points at what was refused. The model card sits
     * at the top of the page and takes the focus after a lookup; over an
     * error it must not, or the visitor lands on the picture and has to
     * hunt for the field two steps below.
     */
    public function testARefusedSubmissionPointsAtTheFieldRatherThanTheCard(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $payload['email'] = 'keine-adresse';

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('email', $crawler->filter('form[data-error-focus]')->attr('data-error-focus'));
        self::assertCount(0, $crawler->filter('[role="status"][autofocus]'));
    }

    /**
     * A value the page never offered is refused – and the refusal has a
     * place to be shown. Keyed by the field rather than by an index, it
     * would otherwise fall out of the page and leave a 422 without a word.
     */
    public function testAnUnknownPurposeIsRefusedWhereTheVisitorCanSeeIt(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $payload['context'] = ['nichts-davon'];

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Diese Angabe kennen wir nicht.', trim($crawler->filter('#e_context')->text()));
        self::assertSame('context', $crawler->filter('form[data-error-focus]')->attr('data-error-focus'));
    }

    /**
     * The whole point of sending two is that they go to different people.
     * Without this the addresses could be swapped and every test would
     * still pass, with the license landing at the customer.
     */
    public function testEachMailGoesToItsOwnRecipient(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $client->request('POST', self::PATH, $this->validPayload($client));

        self::assertSame('mail@krausgedruckt.de', self::getMailerMessage(0)->getTo()[0]->getAddress());
        self::assertSame('kunde@example.com', self::getMailerMessage(0)->getReplyTo()[0]->getAddress());
        self::assertSame('kunde@example.com', self::getMailerMessage(1)->getTo()[0]->getAddress());
        self::assertSame('mail@krausgedruckt.de', self::getMailerMessage(1)->getReplyTo()[0]->getAddress());
    }

    public function testTheConfirmationTakesTheFormsPlace(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $client->request('POST', self::PATH, $this->validPayload($client));
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Deine Anfrage wurde versendet!', $crawler->filter('main')->text());
        self::assertCount(0, $crawler->filter('input[name="email"]'));
    }

    /**
     * Without a token nothing may reach outwards – not the mail, and not
     * the request to the platform that rendering a card would make.
     */
    public function testASubmissionWithoutAValidTokenIsRefusedWithoutSending(): void
    {
        $client = static::createClient();
        // No answers at all: the mock client throws on the first request,
        // so a fetch that should not happen fails loudly instead of
        // passing unnoticed.
        $this->replaceFetcher([]);

        $payload = $this->validPayload($client);
        $payload['_token'] = 'bogus';

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Sitzung ist abgelaufen', $crawler->filter('main')->text());
        self::assertEmailCount(0);
    }

    public function testAStaleFormIsAskedToResendRatherThanDropped(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $aged = (string) (time() - SignedTimestamp::LIFETIME - 1);
        $payload['ts'] = $aged;
        $payload['ts_sig'] = hash_hmac('sha256', $aged, (string) static::getContainer()->getParameter('kernel.secret'));

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('zu lange geöffnet', $crawler->filter('main')->text());
        self::assertEmailCount(0);
    }

    /**
     * „Weiter“ reads the model and must not send, however it is pressed –
     * the browser triggers it for Enter in the address field.
     */
    public function testThePressedLookupButtonReadsInsteadOfSending(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $payload['lookup'] = '1';

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('TPU lid instert for rugged SD card case', $crawler->filter('#schritt-1')->text());
        self::assertEmailCount(0);
    }

    public function testAnInvalidSubmissionIsRefusedWithoutSending(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $payload['email'] = 'keine-adresse';

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('sieht nicht gültig aus', $crawler->filter('#schritt-3')->text());
        self::assertEmailCount(0);
    }

    /**
     * A quantity that is not a number must be answered as such – a cast
     * would turn it into zero and report a field the visitor did fill in.
     */
    public function testAQuantityThatIsNoNumberIsNamedAsSuch(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $payload['quantity'] = 'drei';

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('als Zahl', $crawler->filter('#schritt-1')->text());
        self::assertEmailCount(0);
    }

    /**
     * A filled honeypot is a machine: it gets the same answer a person
     * gets, so nothing is learned from the difference, and nothing is sent.
     */
    public function testAFilledHoneypotIsDroppedSilently(): void
    {
        $client = static::createClient();

        $payload = $this->validPayload($client);
        $payload['website'] = 'https://spam.example';

        $client->request('POST', self::PATH, $payload);

        self::assertResponseRedirects(self::PATH);
        self::assertEmailCount(0);
    }

    public function testASubmissionWithoutAValidTimestampIsDroppedSilently(): void
    {
        $client = static::createClient();

        $payload = $this->validPayload($client);
        $payload['ts_sig'] = 'tampered';

        $client->request('POST', self::PATH, $payload);

        self::assertResponseRedirects(self::PATH);
        self::assertEmailCount(0);
    }

    /**
     * The reading limiter guards the posted path too, and the inquiry goes
     * out regardless – the promise is that a platform cannot stop it.
     */
    public function testAThrottledLookupStillLetsTheInquiryThrough(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->replaceFetcher($this->recordedAnswer());
        static::getContainer()->set('limiter.model_lookup', new RateLimiterFactory(
            ['id' => 'model_lookup_test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        ));

        $payload = $this->validPayload($client);

        $crawler = $client->request('POST', self::PATH, array_merge($payload, ['lookup' => '1']));
        self::assertStringContainsString('TPU lid instert', $crawler->filter('#schritt-1')->text());

        $crawler = $client->request('POST', self::PATH, array_merge($payload, ['lookup' => '1']));
        self::assertStringContainsString('waren gerade viele Anfragen', $crawler->filter('#schritt-1')->text());

        $client->request('POST', self::PATH, $payload);
        self::assertResponseRedirects(self::PATH);
        self::assertEmailCount(2);
    }

    /**
     * The limit is raised out of the way in the test environment, so it is
     * checked against a limiter of its own – otherwise the one guard that
     * keeps this route from being an open relay would be untested.
     */
    public function testAnInquiryBeyondTheLimitIsRefusedWithoutSending(): void
    {
        $client = static::createClient();
        // The kernel keeps running between the two requests, otherwise the
        // replaced limiter – and its count – would be gone by the second.
        $client->disableReboot();
        $this->replaceFetcher($this->recordedAnswer());
        static::getContainer()->set('limiter.print_order', new RateLimiterFactory(
            ['id' => 'print_order_test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        ));

        // The same payload twice: after the first one the page shows the
        // confirmation and carries no token to read a second time – which
        // is also how a replay would look.
        $payload = $this->validPayload($client);

        $client->request('POST', self::PATH, $payload);
        self::assertResponseRedirects(self::PATH);
        $client->followRedirect();

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('zu viele Anfragen', $crawler->filter('main')->text());
        // The count is per request: the refused one sent nothing.
        self::assertEmailCount(0);
    }

    /**
     * The token is taken from a rendered page, as the contact form's test
     * does – it lives in the session, which only a real request opens.
     *
     * @return array<string, mixed>
     */
    private function validPayload(KernelBrowser $client): array
    {
        // **Without this the recording is thrown away.** The browser boots
        // a fresh kernel per request, and the fetcher put into the
        // container before this GET would not survive into the POST – the
        // submission then asked the real platform over the network.
        $client->disableReboot();

        $crawler = $client->request('GET', self::PATH);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $secret = (string) static::getContainer()->getParameter('kernel.secret');
        $aged = (string) (time() - SignedTimestamp::MINIMUM_AGE - 1);

        return [
            '_token' => $token,
            'ts' => $aged,
            'ts_sig' => hash_hmac('sha256', $aged, $secret),
            'url' => self::MODEL,
            'quantity' => '3',
            'context' => ['heat', 'load'],
            'color' => 'Schwarz',
            'message' => 'Bitte ohne sichtbare Stützstrukturen.',
            'email' => 'kunde@example.com',
        ];
    }

    /**
     * @return list<MockResponse>
     */
    private function recordedAnswer(): array
    {
        $body = (string) file_get_contents(__DIR__.'/../fixtures/platforms/printables-print.json');

        return array_fill(0, 4, new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]));
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function replaceFetcher(array $responses): void
    {
        static::getContainer()->set(PlatformFetcher::class, new PlatformFetcher(
            new MockHttpClient($responses),
            new FixedHostResolver(),
        ));
    }
}
