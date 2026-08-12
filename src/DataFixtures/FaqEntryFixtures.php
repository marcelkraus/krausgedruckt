<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\FaqEntry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class FaqEntryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faqData = [
            [
                'question' => 'Was kostet mich ein 3D-Druck?',
                'answer' => 'Kontaktiere uns gerne [per Kontaktformular](/kontakt), [per E-Mail](/kontakt-per-email) oder [per WhatsApp](/kontakt-per-whats-app) und nenne uns ein paar Details, wir unterbreiten dir gerne ein Angebot.',
            ],
            [
                'question' => 'Kann ich auch ohne 3D-Modell bei euch bestellen?',
                'answer' => 'Ja, das ist kein Problem. Beschreibe uns einfach, was du benötigst, und wir suchen ein passendes Modell für dich – oder erstellen dir ein 3D-Modell nach deinen Vorgaben.',
            ],
            [
                'question' => 'Kann ich auch fertige Produkte kaufen?',
                'answer' => 'Ja. Ausgewählte Drucke bieten wir fertig in unserem [Etsy-Shop](https://www.etsy.com/shop/krausgedrucktDE) an – ohne Anfrage und ohne Wartezeit. Alles, was du dort nicht findest, fertigen wir dir auf Anfrage individuell.',
            ],
            [
                'question' => 'Wo sehe ich, was ihr gerade druckt?',
                'answer' => 'Am schnellsten auf [Instagram](https://www.instagram.com/krausgedruckt), dort zeigen wir laufend neue Drucke aus der Werkstatt. Eine ausgewählte Übersicht findest du außerdem unter [Referenzen](/referenzen).',
            ],
            [
                'question' => 'Woher bekomme ich weitere 3D-Modelle?',
                'answer' => 'Gute Adressen sind [www.printables.com](https://www.printables.com), [www.thingiverse.com](https://www.thingiverse.com) und [www.thangs.com](https://www.thangs.com). Bitte beachte bei der Auswahl und Verwendung der gedruckten Bauteile die Nutzungsrechte der jeweiligen Autoren.',
            ],
            [
                'question' => 'Welche Materialien könnt ihr drucken?',
                'answer' => 'PLA, PETG und ASA sind immer auf Lager – auch andere Materialien sind nach Rücksprache kurzfristig zu beschaffen und zu verarbeiten. Wir empfehlen dir gerne ein passendes Material.',
            ],
            [
                'question' => 'Was ist der Unterschied zwischen PLA und PETG?',
                'answer' => 'PLA ist ideal für dekorative Objekte und Prototypen, es ist einfach zu drucken und biologisch abbaubar. PETG ist robuster, hitzebeständiger und eignet sich besser für funktionale Teile, die mechanisch beansprucht werden oder Witterung ausgesetzt sind.',
            ],
            [
                'question' => 'Welche Farben sind möglich?',
                'answer' => 'Materialien wie PLA liegen immer in den Standardfarben (u.a. Schwarz, Weiß, Grau, Blau, Rot, Grün) auf Lager. Generell sind aber alle Farben möglich, in denen es Material gibt – hier kann es nur ggf. zu Aufpreisen und/oder Verzögerungen kommen, wenn spezielle Farben beschafft werden müssen.',
            ],
            [
                'question' => 'Könnt ihr auch mehrfarbig drucken?',
                'answer' => 'Ja, ein gleichzeitiger Druck deines Bauteils in mehreren Farben ist möglich. Siehe [unsere Referenzen](/referenzen) für einige Beispiele.',
            ],
            [
                'question' => 'Auf was für Druckern druckt ihr?',
                'answer' => 'Wir drucken ausschließlich auf Druckern von Prusa Research aus Tschechien, konkret auf den Modellen Prusa MK4S und Prusa CORE One, letzter mit angeschlossener MMU3 für mehrfarbige Drucke.',
            ],
            [
                'question' => 'Wie groß könnt ihr drucken?',
                'answer' => 'Der verfügbare Bauraum beträgt auf dem Prusa MK4S 250 x 210 x 220 mm und auf dem Prusa CORE One 250 x 220 x 270 mm (jeweils Länge x Breite x Höhe). Brauchst du es noch größer, können wir dein Bauteil in mehreren Teilen drucken.',
            ],
            [
                'question' => 'Wie lange dauert die Fertigung?',
                'answer' => 'Die Drucker laufen bei Bedarf rund um die Uhr, auch am Wochenende. Je nach Auslastung und benötigter Menge können wir deinen Druckwunsch daher in der Regel binnen weniger Tage erfüllen.',
            ],
            [
                'question' => 'Bietet ihr auch Nachbearbeitung an?',
                'answer' => 'Grundlegende Nachbearbeitung wie das Entfernen von Stützstrukturen ist bereits im Preis enthalten. Weitere Arbeiten wie Schleifen, Kleben mehrteiliger Modelle oder Lackieren sind nach Absprache möglich.',
            ],
            [
                'question' => 'Gibt es Mindest-Abnahmemengen?',
                'answer' => 'Nein, Drucke sind generell ab einem Stück möglich. Nicht unerwähnt bleiben sollte aber, dass mit steigenden Stückzahlen gleicher 3D-Modelle indirekt auch der Preis pro Stück sinkt, der grundsätzliche Aufwand für die einmalige Vorbereitung bleibt ja konstant.',
            ],
            [
                'question' => 'Wie erhalte ich meine gedruckten Teile?',
                'answer' => 'Entweder kommst du deine gedruckten Teile abholen oder wir senden dir diese ganz einfach zu. Du erhältst eine Rechnung mit ausgewiesener Mehrwertsteuer und zahlst bequem per Überweisung.',
            ],
            [
                'question' => 'Druckt ihr auch für gewerbliche Kunden?',
                'answer' => 'Ja, wir arbeiten sowohl mit Privatkunden als auch mit Gewerbetreibenden zusammen. Für gewerbliche Anfragen erstellen wir gerne individuelle Angebote – auch für größere Serien.',
            ],
        ];

        foreach ($faqData as $index => $data) {
            $faqEntry = new FaqEntry();
            $faqEntry->setQuestion($data['question']);
            $faqEntry->setAnswer($data['answer']);
            $faqEntry->setIsVisible(true);
            $faqEntry->setSortOrder($index);

            $manager->persist($faqEntry);
        }

        $manager->flush();
    }
}
