<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Reference;
use App\Entity\Source;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

class ReferenceFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private SluggerInterface $slugger
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $fixtureImagesPath = $this->projectDir . '/public/images/references/fixtures';
        $imagesPath = $this->projectDir . '/public/images/references';

        if (!is_dir($imagesPath)) {
            mkdir($imagesPath, 0777, true);
        }

        $referencesData = [
            [
                'legacyId' => 1,
                'title' => 'Ein Klassiker zu Beginn',
                'summary' => 'Der erste Druck auf einem neuen Prusa MK4 — natürlich ein Schlüsselanhänger als standesgemäßes Beispielmodell.',
                'description' => "Ein neuer Drucker, ein neuer Schlüsselanhänger.\n\nNatürlich ist der erste Druck auf einem neuen Prusa MK4 standesgemäß genau dieses Beispielmodell.",
                'category' => CategoryFixtures::CATEGORY_3D_DRUCKER,
                'source' => [
                    'title' => 'Prusa Keychain - sample model',
                    'url' => 'https://www.printables.com/model/436740-prusa-keychain-sample-model',
                    'author' => 'Prusa Research',
                ],
            ],
            [
                'legacyId' => 2,
                'title' => 'Ein unscheinbares Helferlein',
                'summary' => 'Ein praktisches Werkzeug zum Sortieren von Schrauben und Muttern — und ein Test für den Farbwechsel am Prusa MK4.',
                'description' => "Mein Freund James und ich haben eine Werkstatt, und dort gibt es jede Menge Schrauben und Muttern.\n\nDieses unscheinbare Werkzeug ist ein hervorragendes Helferlein beim Sortieren eben dieser Schrauben und Muttern. Die Skala ermöglicht es, Schrauben bis 50 mm Länge und Muttern bis M5 schnell zuzuordnen.\n\nNeben der Nützlichkeit wurde das Modell allerdings vor allem gedruckt, um den Farbwechsel am Prusa MK4 auszuprobieren.",
                'category' => CategoryFixtures::CATEGORY_WERKSTATT,
                'source' => [
                    'title' => 'Rapid Metric Screw Measuring Tool (M2-M5, up to 50mm)',
                    'url' => 'https://www.printables.com/model/208880-rapid-metric-screw-measuring-tool-m2-m5-up-to-50mm',
                    'author' => 'fivesixzero',
                ],
            ],
            [
                'legacyId' => 3,
                'title' => 'Ein Abfallbehälter mit Akzenten',
                'summary' => 'Ein Abfallbehälter für den Prusa MK4, der Filamentreste auffängt — mit orangem Farbakzent.',
                'description' => "Der Abfallbehälter spart mir eine Menge Zeit und Nerven, er wird an der Seite meines Hauptdruckers, dem Prusa MK4, montiert und nimmt alle Reste auf, der beim Hantieren mit Filamenten so anfällt.\n\nNun landet der ganze Kram nicht mehr auf dem Boden, und mir bleibt mehr Zeit zum drucken!\n\nAls kleines optisches Highlight wurden die oberen 2 mm des schwarzen Behälters in orange abgesetzt, das Ergebnis kann sich sehen lassen!",
                'category' => CategoryFixtures::CATEGORY_3D_DRUCKER,
                'source' => [
                    'title' => 'Prusa MK4 bin',
                    'url' => 'https://www.printables.com/model/526044-prusa-mk4-bin',
                    'author' => 'mkoistinen',
                ],
            ],
            [
                'legacyId' => 4,
                'title' => 'Werkzeuge für 3D-Drucker aus dem 3D-Drucker',
                'summary' => '3D-Drucker drucken Werkzeuge für andere 3D-Drucker — hier zwei Tools zum Nozzle-Wechsel am Prusa MK4.',
                'description' => "Die Idee ist so simpel wie genial: 3D-Drucker drucken Werkzeuge (und Teile) für andere 3D-Drucker.\n\nHier als Beispiel zwei Werkzeuge zum Wechsel der Nozzle (= Düse) eines Prusa MK4, den ich für meine Kunden einsetze.",
                'category' => CategoryFixtures::CATEGORY_3D_DRUCKER,
                'source' => [
                    'title' => 'MK4 Nextruder Nozzle Change Heater Block Clamp',
                    'url' => 'https://www.printables.com/model/624027-mk4-nextruder-nozzle-change-heater-block-clamp',
                    'author' => 'IBNobody',
                ],
            ],
            [
                'legacyId' => 5,
                'title' => 'Ein Halter für Visitenkarten',
                'summary' => 'Ein Visitenkartenhalter im Jurassic-Park-Stil — passend zum Postkarten-Aufsteller.',
                'description' => "Analog zum Postkarten-Aufsteller (siehe oben) gibt es das passende Modell natürlich auch für Visitenkarten.\n\nAuch hier war der \"Jurassic Jeep\" der Grund, ein solches Modell zu entwicklen und der Allgemeinheit bereitzustellen.",
                'category' => CategoryFixtures::CATEGORY_AUTOS,
                'source' => [
                    'title' => 'Plain (but Jurassic) Business Card Holder',
                    'url' => 'https://www.printables.com/model/585117-plain-but-jurassic-business-card-holder',
                    'author' => 'krausgedruckt',
                ],
            ],
            [
                'legacyId' => 6,
                'title' => 'Eine Postkarte in Ehren',
                'summary' => 'Ein Postkarten-Aufsteller im Jurassic-Park-Design — inspiriert von den Betonblöcken der Zäune.',
                'description' => "Als großer Fan von Postkarten musste ich natürlich eine Möglichkeit finden, die Postkarten meines \"Jurassic Jeep\" zu präsentieren.\n\nDas simple Design ist schnell zu drucken und orientiert sich in der Optik passenderweise an den Betonblöcken der Zäune aus \"Jurassic Park\".",
                'category' => CategoryFixtures::CATEGORY_AUTOS,
                'source' => [
                    'title' => 'Plain (but Jurassic) Postcard Holder',
                    'url' => 'https://www.printables.com/model/585122-plain-but-jurassic-postcard-holder',
                    'author' => 'krausgedruckt',
                ],
            ],
            [
                'legacyId' => 7,
                'title' => 'Bequemes Schrauben am Tamiya BBX',
                'summary' => 'Ein 3D-gedruckter Ständer für den Tamiya BBX Buggy — für bequemes Arbeiten am RC-Car.',
                'description' => "Als großer Fan von RC-Cars aller Art lässt sich dieses Hobby auch mit dem 3D-Druck verbinden.\n\nFür den neuen Buggy von Tamiya, dem Tamiya BBX, habe ich einen passenden Ständer gedruckt, so lässt sich bequem daran arbeiten.\n\nSobald die MMU-Einheit für den Mehrfarbdruck verfügbar ist, erfolgt ein Neuausdruck mit farbigem Tamiya-Logo.",
                'category' => CategoryFixtures::CATEGORY_RC_MODELLBAU,
                'source' => [
                    'title' => 'Stand for Tamiya BBX buggy',
                    'url' => 'https://www.printables.com/model/724690-stand-for-tamiya-bbx-buggy',
                    'author' => 'PW555',
                ],
            ],
        ];

        $baseDate = new \DateTime('2026-01-01 00:00:00');
        foreach ($referencesData as $index => $data) {
            $reference = new Reference();
            $reference->setTitle($data['title']);
            $reference->setSummary($data['summary']);
            $reference->setDescription($data['description']);
            $reference->setIsVisible(true);

            /** @var Category $category */
            $category = $this->getReference($data['category'], Category::class);
            $reference->setCategory($category);

            $createdAt = clone $baseDate;
            $createdAt->modify('+' . ($index * 1) . ' days');
            $reference->setCreatedAt($createdAt);

            $source = new Source(
                $data['source']['title'],
                $data['source']['url'],
                $data['source']['author']
            );
            $reference->setSource($source);

            $manager->persist($reference);
            $manager->flush();

            $legacyId = $data['legacyId'];
            $oldImagePath = "{$fixtureImagesPath}/{$legacyId}.jpg";

            if (file_exists($oldImagePath)) {
                $slug = $this->slugger->slug($reference->getTitle())->lower()->toString();
                $uuid = str_replace('-', '', $reference->getId()->toRfc4122());
                $newFileName = "{$slug}-{$uuid}.jpg";
                $newImagePath = "{$imagesPath}/{$newFileName}";

                if (copy($oldImagePath, $newImagePath)) {
                    $reference->setImage($newFileName);
                    $manager->persist($reference);
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }
}
