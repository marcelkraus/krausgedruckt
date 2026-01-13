<?php

namespace App\DataFixtures;

use App\Entity\FaqEntry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FaqEntryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faqData = [
            [
                'question' => 'Was kostet mich ein 3D-Druck?',
                'answer' => 'Kontaktiere mich gerne [per Kontaktformular](/kontakt), [per E-Mail](/kontakt-per-email) oder [per WhatsApp](/kontakt-per-whats-app) und nenne mir ein paar Details, ich unterbreite dir gerne ein Angebot.',
            ],
            [
                'question' => 'Kann ich auch ohne 3D-Modell bei dir bestellen?',
                'answer' => 'Ja, das ist kein Problem. Beschreibe mir einfach, was du benötigst, und ich suche ein passendes Modell für dich — oder erstelle dir ein 3D-Modell nach deinen Vorgaben.',
            ],
            [
                'question' => 'Woher bekomme ich weitere 3D-Modelle?',
                'answer' => 'Gute Adressen sind [www.printables.com](https://www.printables.com), [www.thingiverse.com](https://www.thingiverse.com) und [www.thangs.com](https://www.thangs.com). Bitte beachte bei der Auswahl und Verwendung der gedruckten Bauteile die Nutzungsrechte der jeweiligen Autoren.',
            ],
            [
                'question' => 'Welche Materialien kannst du drucken?',
                'answer' => 'PLA, PETG und ASA sind immer auf Lager — auch andere Materialien sind nach Rücksprache kurzfristig zu beschaffen und zu verarbeiten. Ich empfehle dir gerne ein passendes Material.',
            ],
            [
                'question' => 'Was ist der Unterschied zwischen PLA und PETG?',
                'answer' => 'PLA ist ideal für dekorative Objekte und Prototypen, es ist einfach zu drucken und biologisch abbaubar. PETG ist robuster, hitzebeständiger und eignet sich besser für funktionale Teile, die mechanisch beansprucht werden oder Witterung ausgesetzt sind.',
            ],
            [
                'question' => 'Welche Farben sind möglich?',
                'answer' => 'Materialien wie PLA liegen immer in den Standardfarben (u.a. Schwarz, Weiß, Grau, Blau, Rot, Grün) auf Lager. Generell sind aber alle Farben möglich, in denen es Material gibt — hier kann es nur ggf. zu Aufpreisen und/oder Verzögerungen kommen, wenn spezielle Farben beschafft werden müssen.',
            ],
            [
                'question' => 'Kannst du auch mehrfarbig drucken?',
                'answer' => 'Ja, ein gleichzeitiger Druck deines Bauteils in mehreren Farben ist möglich. Siehe [meine Referenzen](/referenzen) für einige Beispiele.',
            ],
            [
                'question' => 'Auf was für Druckern druckst du?',
                'answer' => 'Ich drucke ausschließlich auf Druckern von Prusa Research aus Tschechien, konkret auf den Modellen Prusa MK4S und Prusa CORE One, letzter mit angeschlossener MMU3 für mehrfarbige Drucke.',
            ],
            [
                'question' => 'Wie groß kannst du drucken?',
                'answer' => 'Der verfügbare Bauraum beträgt auf dem Prusa MK4S 250 x 210 x 220 mm und auf dem Prusa CORE One 250 x 220 x 270 mm (jeweils Länge x Breite x Höhe). Brauchst du es noch größer, kann ich dein Bauteil in mehreren Teilen drucken.',
            ],
            [
                'question' => 'Wie lange dauert die Fertigung?',
                'answer' => 'Die Drucker laufen bei Bedarf rund um die Uhr, auch am Wochenende. Je nach Auslastung und benötigter Menge kann ich deinen Druckwunsch daher in der Regel binnen weniger Tage erfüllen.',
            ],
            [
                'question' => 'Bietest du auch Nachbearbeitung an?',
                'answer' => 'Grundlegende Nachbearbeitung wie das Entfernen von Stützstrukturen ist bereits im Preis enthalten. Weitere Arbeiten wie Schleifen, Kleben mehrteiliger Modelle oder Lackieren sind nach Absprache möglich.',
            ],
            [
                'question' => 'Gibt es Mindest-Abnahmemengen?',
                'answer' => 'Nein, Drucke sind generell ab einem Stück möglich. Nicht unerwähnt bleiben sollte aber, dass mit steigenden Stückzahlen gleicher 3D-Modelle indirekt auch der Preis pro Stück sinkt, der grundsätzliche Aufwand für die einmalige Vorbereitung bleibt ja konstant.',
            ],
            [
                'question' => 'Wie erhalte ich meine gedruckten Teile?',
                'answer' => 'Entweder kommst du deine gedruckten Teile abholen oder ich sende dir diese ganz einfach zu. Du erhältst eine Rechnung mit ausgewiesener Mehrwertsteuer und zahlst bequem per Überweisung.',
            ],
            [
                'question' => 'Druckst du auch für gewerbliche Kunden?',
                'answer' => 'Ja, ich arbeite sowohl mit Privatkunden als auch mit Gewerbetreibenden zusammen. Für gewerbliche Anfragen erstelle ich gerne individuelle Angebote — auch für größere Serien.',
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
