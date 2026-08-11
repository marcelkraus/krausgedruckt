<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The contact form is the only place where a visitor hands us data, and the
 * only route that sends mail. It shipped once with constraints that were read
 * by nothing, so these tests pin what matters: an invalid submission is
 * refused with a message and sends nothing, a valid one is accepted exactly
 * once, and the two anti-spam signals behave differently — a bot is dropped
 * silently, a person whose form went stale is asked to send again.
 */
final class ContactFormTest extends WebTestCase
{
    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidSubmissionProvider(): array
    {
        return [
            'leerer Name' => [['name' => ''], 'Bitte sag uns, wie du heißt.'],
            'leere E-Mail-Adresse' => [['email' => ''], 'Ohne E-Mail-Adresse'],
            'unsinnige E-Mail-Adresse' => [['email' => 'keine-adresse'], 'sieht nicht gültig aus'],
            'leere Nachricht' => [['message' => ''], 'Erzähl uns kurz, worum es geht.'],
            'zu kurze Nachricht' => [['message' => 'Hallo'], 'etwas ausführlicher'],
        ];
    }

    /**
     * @param array<string, string> $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSubmissionProvider')]
    public function testInvalidSubmissionIsRefused(array $overrides, string $expectedMessage): void
    {
        $client = static::createClient();
        $client->request('POST', '/kontakt', $this->payload($client, $overrides));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', $expectedMessage);
        self::assertEmailCount(0);
    }

    public function testValidSubmissionRedirectsAndSendsOneMail(): void
    {
        $client = static::createClient();
        $client->request('POST', '/kontakt', $this->payload($client));

        self::assertResponseRedirects('/kontakt');
        self::assertEmailCount(1);
    }

    public function testConfirmationTakesTheFormsPlace(): void
    {
        $client = static::createClient();
        $client->request('POST', '/kontakt', $this->payload($client));
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Danke für deine Nachricht.');
        self::assertSelectorNotExists('form[action="/kontakt"]');
    }

    public function testDiscountCodeIsPrefilledFromTheQueryString(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/kontakt?discount-code=ADVINTAGE');

        self::assertSame('ADVINTAGE', $crawler->filter('#f_discountCode')->attr('value'));
    }

    public function testFilledHoneypotIsDroppedSilently(): void
    {
        $client = static::createClient();
        $client->request('POST', '/kontakt', $this->payload($client, ['website' => 'https://example.com']));

        // A bot must not learn that it was caught, so the answer looks like
        // success — but nothing leaves the building.
        self::assertResponseRedirects('/kontakt');
        self::assertEmailCount(0);
    }

    public function testSubmissionWithoutASignatureIsDroppedSilently(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client);
        $payload['ts_sig'] = 'gefälscht';

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseRedirects('/kontakt');
        self::assertEmailCount(0);
    }

    public function testStaleFormAsksToResendInsteadOfBeingDropped(): void
    {
        $client = static::createClient();
        $client->request('POST', '/kontakt', $this->payload($client, age: 8000));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'zu lange geöffnet');
        self::assertEmailCount(0);
    }

    /**
     * Builds a POST payload with a valid token taken from a rendered form and
     * a signed timestamp aged `age` seconds — inside the valid window by
     * default, expired when a large age is passed.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function payload(KernelBrowser $client, array $overrides = [], int $age = 5): array
    {
        $crawler = $client->request('GET', '/kontakt');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $secret = static::getContainer()->getParameter('kernel.secret');

        $timestamp = (string) (time() - $age);

        return array_merge([
            'name' => 'Marta Muster',
            'email' => 'marta@example.com',
            'phone' => '0123 456789',
            'discountCode' => '',
            'message' => 'Bitte ein Angebot für ein Ersatzteil aus PETG.',
            '_token' => $token,
            'ts' => $timestamp,
            'ts_sig' => hash_hmac('sha256', $timestamp, $secret),
        ], $overrides);
    }
}
