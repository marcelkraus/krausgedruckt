<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\SignatureCardController;
use App\Entity\SignatureModel;
use App\Service\SignatureCardRenderer;
use App\Service\SignatureModelCatalog;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The printed table cards: one page per model, a code that leads to the
 * model's short link on the production host, and the line that says whether
 * the visitor may order the model itself.
 */
final class SignatureCardTest extends WebTestCase
{
    private const CAMPAIGN = ['name' => 'Testmesse 2026', 'slug' => 'testmesse-2026'];

    public function testRouteRendersOneCardPerModel(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/_signature-cards');

        self::assertResponseIsSuccessful();
        self::assertCount(\count(static::getContainer()->get(SignatureModelCatalog::class)->all()), $crawler->filter('.signature-card'));
    }

    public function testCodeLeadsToTheShortLinkOnTheProductionHost(): void
    {
        $card = $this->render([$this->model('jurassic-print', true)])->filter('.signature-card');

        self::assertSame('https://krausgedruckt.de/s/signature-jurassic-print', $card->filter('[data-qr-url]')->attr('data-qr-url'));
        self::assertCount(1, $card->filter('.signature-card__qr svg'));
    }

    public function testModelReleasedInPersonIsOffered(): void
    {
        $card = $this->render([$this->model('frei', true)])->filter('.signature-card');

        self::assertStringContainsString('dank dem Designer, der das Modell ausdrücklich dafür freigegeben hat', $card->filter('.signature-card__line')->text());
        self::assertStringNotContainsString('Lizenz', $card->text());
    }

    public function testLicensedModelIsOfferedByItsLicense(): void
    {
        $card = $this->render([$this->model('lizenz', true, license: 'CC BY 4.0')])->filter('.signature-card');

        self::assertStringContainsString('die Lizenz erlaubt es', $card->filter('.signature-card__line')->text());
        self::assertStringNotContainsString('CC BY 4.0', $card->text());
    }

    public function testReferenceIsNotOffered(): void
    {
        $card = $this->render([$this->model('referenz', false)])->filter('.signature-card');

        self::assertStringContainsString('dieses Modell dürfen wir dir leider nicht anbieten', $card->filter('.signature-card__line')->text());
    }

    public function testCardLeavesTheEventAndAnEmptyTextOut(): void
    {
        $card = $this->render([$this->model('ohne-text', false)])->filter('.signature-card');

        self::assertStringNotContainsString('Testmesse 2026', $card->text());
        self::assertStringContainsString('https://www.example.com/model/1', $card->text());
        self::assertCount(0, $card->filter('.signature-card__text'));
    }

    public function testTextKeepsWhatMustNotBreakTogetherAndIsEscaped(): void
    {
        $model = $this->model('text', false, printer: 'Prusa CORE One+', text: 'Mit 3D-Druck auf dem Prusa CORE One+ wie im „Jurassic Park“ <b>fett</b>.');
        $text = $this->render([$model])->filter('.signature-card__text');

        self::assertSame(['3D-Druck', 'Prusa CORE One+', '„Jurassic Park“'], $text->filter('span.whitespace-nowrap')->each(static fn (Crawler $span): string => $span->text()));
        self::assertCount(0, $text->filter('b'));
        self::assertStringContainsString('<b>fett</b>', $text->text());
    }

    /**
     * No pattern sees an entity: a printer's name that is part of one, or
     * carries a character escaping changes, leaves the text as written.
     */
    public function testTextSurvivesSpecialCharacters(): void
    {
        $model = $this->model('zeichen', false, printer: 'amp', text: 'Tom & Jerry sagen "hallo" zu A & B.');
        self::assertSame('Tom & Jerry sagen "hallo" zu A & B.', $this->render([$model])->filter('.signature-card__text')->text());

        $model = $this->model('drucker', false, printer: 'A & B', text: 'Gedruckt auf A & B.');
        self::assertSame(['A & B'], $this->render([$model])->filter('.signature-card__text span.whitespace-nowrap')->each(static fn (Crawler $span): string => $span->text()));
    }

    public function testLongQuotationAndPartOfAWordAreNotHeldTogether(): void
    {
        $model = $this->model('lang', false, printer: 'MK4', text: 'Ein „Name, der deutlich länger als dreißig Zeichen ist“ auf dem MK4S.');

        self::assertCount(0, $this->render([$model])->filter('.signature-card__text span'));
    }

    public function testSourceAddressBreaksAfterASlashOnly(): void
    {
        $pieces = $this->render([$this->model('adresse', false)])->filter('.signature-card p.font-mono span.whitespace-nowrap')->each(static fn (Crawler $span): string => $span->text());

        self::assertSame(['https://', 'www.example.com/', 'model/', '1'], $pieces);
    }

    /**
     * Production installs without the QR code library. It stays reachable
     * only through render(), behind a route production does not have – a
     * constructor type from it would break the container on the server,
     * where the suite never looks.
     */
    public function testProductionNeverReachesTheDevDependency(): void
    {
        foreach ((new \ReflectionMethod(SignatureCardRenderer::class, '__construct'))->getParameters() as $parameter) {
            self::assertStringStartsNotWith('chillerlan\\', ltrim((string) $parameter->getType(), '?'));
        }

        $route = (new \ReflectionMethod(SignatureCardController::class, 'cards'))->getAttributes(Route::class)[0]->newInstance();
        self::assertNotEmpty($route->envs);
        self::assertNotContains('prod', $route->envs);
    }

    /**
     * @param list<SignatureModel> $models
     */
    private function render(array $models): Crawler
    {
        static::bootKernel();

        return new Crawler(static::getContainer()->get(SignatureCardRenderer::class)->render($models, self::CAMPAIGN));
    }

    private function model(string $slug, bool $permitted, string $license = '', string $printer = 'Drucker', string $text = ''): SignatureModel
    {
        return new SignatureModel(
            slug: $slug,
            name: 'Modell',
            designer: 'Designer',
            sourceUrl: 'https://www.example.com/model/1',
            permitted: $permitted,
            printTime: '10 h',
            parts: '3',
            printer: $printer,
            materials: 'PLA',
            colors: '2',
            text: $text,
            license: $license,
        );
    }
}
