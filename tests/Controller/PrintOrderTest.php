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

        // Bound to the enum: a count would stay green when the display order forgets a case.
        self::assertEqualsCanonicalizing(UsageContext::cases(), UsageContext::inDisplayOrder());
        self::assertCount(1, $crawler->filter('#schritt-3 input[name="email"]'));
    }

    public function testAFailedLookupOpensTheStepsAnyway(): void
    {
        $client = static::createClient();
        $this->replaceFetcher([new MockResponse('', ['http_code' => 503])]);

        $crawler = $client->request('GET', self::PATH.'?url='.urlencode(self::MODEL));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Schick mir den Link trotzdem', $crawler->filter('#schritt-1')->text());
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

        self::assertStringContainsString('Ich lese aktuell nur Modelle von Printables', $crawler->filter('#schritt-1')->text());
    }

    public function testAValidInquiryRedirectsAndSendsTwoMails(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $client->request('POST', self::PATH, $this->validPayload($client));

        self::assertResponseRedirects(self::PATH);
        self::assertEmailCount(2);
    }

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

    public function testTheWorkshopMailSaysWhyAFieldIsEmptyRatherThanShowingADash(): void
    {
        $client = static::createClient();
        $this->replaceFetcher(array_fill(0, 4, new MockResponse('', ['http_code' => 503])));

        $payload = $this->validPayload($client);
        unset($payload['context']);

        $client->request('POST', self::PATH, $payload);

        $workshop = self::getMailerMessage(0)->getTextBody();

        self::assertStringContainsString('Modell: nicht gelesen', $workshop);
        self::assertStringContainsString('Material nach meiner Einschätzung', $workshop);
    }

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

    public function testAnUnknownPurposeIsRefusedWhereTheVisitorCanSeeIt(): void
    {
        $client = static::createClient();
        $this->replaceFetcher($this->recordedAnswer());

        $payload = $this->validPayload($client);
        $payload['context'] = ['nichts-davon'];

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Diese Angabe kenne ich nicht.', trim($crawler->filter('#e_context')->text()));
        self::assertSame('context', $crawler->filter('form[data-error-focus]')->attr('data-error-focus'));
    }

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

    public function testASubmissionWithoutAValidTokenIsRefusedWithoutSending(): void
    {
        $client = static::createClient();
        // The mock client throws on the first request, so an unwanted fetch fails loudly.
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

    public function testAnInquiryBeyondTheLimitIsRefusedWithoutSending(): void
    {
        $client = static::createClient();
        // Without it the replaced limiter would be gone by the second request.
        $client->disableReboot();
        $this->replaceFetcher($this->recordedAnswer());
        static::getContainer()->set('limiter.print_order', new RateLimiterFactory(
            ['id' => 'print_order_test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        ));

        $payload = $this->validPayload($client);

        $client->request('POST', self::PATH, $payload);
        self::assertResponseRedirects(self::PATH);
        $client->followRedirect();

        $crawler = $client->request('POST', self::PATH, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('zu viele Anfragen', $crawler->filter('main')->text());
        self::assertEmailCount(0);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(KernelBrowser $client): array
    {
        // A fresh kernel per request would drop the recording and ask the real platform.
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
