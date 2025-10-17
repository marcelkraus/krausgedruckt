<?php

namespace App\DataFixtures;

use App\Entity\PortfolioPiece;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $fixtures = [
            [
                'title' => 'Bequemes Schrauben am Tamiya BBX',
                'description' => "Als großer Fan von RC-Cars aller Art lässt sich dieses Hobby auch mit dem 3D-Druck verbinden.\n\nFür den neuen Buggy von Tamiya, dem Tamiya BBX, habe ich einen passenden Ständer gedruckt – so lässt sich bequem daran arbeiten.\n\nSobald die MMU-Einheit für den Mehrfarbdruck verfügbar ist, erfolgt ein Neuausdruck mit farbigem Tamiya-Logo.",
                'sourceTitle' => 'Stand for Tamiya BBX buggy',
                'sourceUrl' => 'https://www.printables.com/model/724690-stand-for-tamiya-bbx-buggy',
                'sourceAuthor' => 'PW555',
            ],
            [
                'title' => 'Eine Postkarte in Ehren',
                'description' => "Als großer Fan von Postkarten musste ich natürlich eine Möglichkeit finden, die Postkarten meines „Jurassic Jeep“ zu präsentieren.\n\nDas simple Design ist schnell zu drucken und orientiert sich in der Optik passenderweise an den Betonblöcken der Zäune aus „Jurassic Park“.",
                'sourceTitle' => 'Plain (but Jurassic) Postcard Holder',
                'sourceUrl' => 'https://www.printables.com/model/585122-plain-but-jurassic-postcard-holder',
                'sourceAuthor' => 'krausgedruckt',
            ],
            [
                'title' => 'Ein Halter für Visitenkarten',
                'description' => "Analog zum Postkarten-Aufsteller (siehe oben) gibt es das passende Modell natürlich auch für Visitenkarten.\n\nAuch hier war der „Jurassic Jeep“ der Grund, ein solches Modell zu entwicklen und der Allgemeinheit bereitzustellen.",
                'sourceTitle' => 'Plain (but Jurassic) Business Card Holder',
                'sourceUrl' => 'https://www.printables.com/model/585117-plain-but-jurassic-business-card-holder',
                'sourceAuthor' => 'krausgedruckt',
            ],
            [
                'title' => 'Werkzeuge für 3D-Drucker aus dem 3D-Drucker',
                'description' => "Die Idee ist so simpel wie genial: 3D-Drucker drucken Werkzeuge (und Teile) für andere 3D-Drucker.\n\nHier als Beispiel zwei Werkzeuge zum Wechsel der Nozzle (= Düse) eines Prusa MK4, den ich für meine Kunden einsetze.",
                'sourceTitle' => 'MK4 Nextruder Nozzle Change Heater Block Clamp',
                'sourceUrl' => 'https://www.printables.com/model/624027-mk4-nextruder-nozzle-change-heater-block-clamp',
                'sourceAuthor' => 'IBNobody',
            ],
            [
                'title' => 'Ein Abfallbehälter mit Akzenten',
                'description' => "Der Abfallbehälter spart mir eine Menge Zeit und Nerven – er wird an der Seite meines Hauptdruckers, dem Prusa MK4, montiert und nimmt alle Reste auf, der beim Hantieren mit Filamenten so anfällt.\n\nNun landet der ganze Kram nicht mehr auf dem Boden — und mir bleibt mehr Zeit zum drucken!\n\nAls kleines optisches Highlight wurden die oberen 2 mm des schwarzen Behälters in orange abgesetzt – das Ergebnis kann sich sehen lassen!",
                'sourceTitle' => 'Prusa MK4 bin',
                'sourceUrl' => 'https://www.printables.com/model/526044-prusa-mk4-bin',
                'sourceAuthor' => 'mkoistinen',
            ],
            [
                'title' => 'Ein unscheinbares Helferlein',
                'description' => "Mein Freund James und ich haben eine Werkstatt – und dort gibt es jede Menge Schrauben und Muttern.\n\nDieses unscheinbare Werkzeug ist ein hervorragendes Helferlein beim Sortieren eben dieser Schrauben und Muttern. Die Skala ermöglicht es, Schrauben bis 50 mm Länge und Muttern bis M5 schnell zuzuordnen.\n\nNeben der Nützlichkeit wurde das Modell allerdings vor allem gedruckt, um den Farbwechsel am Prusa MK4 auszuprobieren. 😉",
                'sourceTitle' => 'Rapid Metric Screw Measuring Tool (M2-M5, up to 50mm)',
                'sourceUrl' => 'https://www.printables.com/model/208880-rapid-metric-screw-measuring-tool-m2-m5-up-to-50mm',
                'sourceAuthor' => 'fivesixzero',
            ],
            [
                'title' => 'Ein Klassiker zu Beginn',
                'description' => "Ein neuer Drucker, ein neuer Schlüsselanhänger.\n\nNatürlich ist der erste Druck auf einem neuen Prusa MK4 standesgemäß genau dieses Beispielmodell.",
                'sourceTitle' => 'Prusa Keychain - sample model',
                'sourceUrl' => 'https://www.printables.com/model/436740-prusa-keychain-sample-model',
                'sourceAuthor' => 'Prusa Research',
            ],
        ];

        foreach (array_reverse($fixtures) as $fixture) {
            $portfolioPiece = new PortfolioPiece();
            $portfolioPiece->setTitle($fixture['title']);
            $portfolioPiece->setDescription($fixture['description']);
            $portfolioPiece->setSourceTitle($fixture['sourceTitle']);
            $portfolioPiece->setSourceUrl($fixture['sourceUrl']);
            $portfolioPiece->setSourceAuthor($fixture['sourceAuthor']);

            $manager->persist($portfolioPiece);
        }

        $manager->flush();
    }
}
