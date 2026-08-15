<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Module\Audio\Presentation\Component\EffectList;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Rachunek kolumn spisu efektów (krok 46).
 *
 * Test istnieje dla jednej rzeczy i warto powiedzieć wprost dla jakiej:
 * **najdłuższa nazwa zdarzenia ma się zmieścić w panelu podziału**. „Usunięcie
 * trwałe: zakończone” to 28 znaków, a panel przy progu podziału daje ich
 * niewiele więcej — więc dwie kolumny elastyczne dzieliłyby miejsce po połowie
 * i wiersz mówiłby „Usunięcie trwałe: zak…”, czyli nie odróżniałby udanego od
 * nieudanego.
 *
 * Klucz napisu jest tu **samym napisem**, bo tłumacz testowy oddaje klucz —
 * dzięki temu test mierzy szerokość, a nie brzmienie katalogu.
 */
final class EffectListTest extends TestCase
{
    /**
     * Napis o długości **budżetu kolumny**: 27 znaków.
     *
     * Nie jest to żadna prawdziwa nazwa zdarzenia i nie ma nią być — test pilnuje
     * **rachunku**, a nie brzmienia katalogu. Najdłuższa nazwa polska ma dziś 26
     * znaków („Usunięcie trwałe: nieudane"), więc mieści się z jednym znakiem
     * zapasu; napis o znak dłuższy od tego tutaj zostałby ucięty wielokropkiem
     * i test by o tym powiedział.
     */
    private const LONGEST = 'Usunięcie trwałe: nieudane!';

    /** Panel podziału w oknie stu kolumn: nazwa zdarzenia mieści się w całości. */
    public function testTheLongestEventNameFitsInASplitPanel(): void
    {
        $texts = $this->draw(new Rect(0, 0, 8, 50), framed: true);

        self::assertContains(self::LONGEST, $texts);
    }

    /**
     * Przy samym progu podziału (72 kolumny, czyli 36 na panel) **kolumna pliku
     * znika w całości**, a nazwa zostaje — dokładnie tak, jak każe reguła
     * ustępowania.
     */
    public function testAtTheSplitThresholdTheFileColumnYieldsAndTheNameStays(): void
    {
        $texts = $this->draw(new Rect(0, 0, 8, 36), framed: true);

        self::assertContains(self::LONGEST, $texts);
        self::assertNotContains('bardzo-dluga-nazwa-pliku.wav', $texts);
    }

    /**
     * **Każda nazwa zdarzenia w obu katalogach mieści się w budżecie kolumny.**
     *
     * To jest właściwy strażnik tego rachunku: powyższe testy pilnują szerokości
     * przy napisie wziętym z palca, a ten — przy napisach, które użytkownik
     * naprawdę zobaczy. Dopisanie zdarzenia o zbyt długiej nazwie skończy się tu,
     * a nie na klatce pod XTermem.
     */
    public function testEveryEventNameInTheCatalogsFitsTheBudget(): void
    {
        $root = dirname(__DIR__, 3);
        $catalogues = [
            $root . '/lang/pl.php',
            $root . '/lang/en.php',
            $root . '/src/Module/Browser/lang/pl.php',
            $root . '/src/Module/Browser/lang/en.php',
        ];

        foreach ($catalogues as $path) {
            /** @var array<string, mixed> $entries */
            $entries = require $path;

            foreach ($entries as $key => $value) {
                if (!is_string($value) || !str_contains($key, '.event.')) {
                    continue;
                }

                self::assertLessThanOrEqual(
                    mb_strlen(self::LONGEST),
                    mb_strlen($value),
                    $key . ' nie zmieści się w kolumnie spisu efektów',
                );
            }
        }
    }

    /** W oknie bez podziału widać jedno i drugie. */
    public function testWithoutASplitBothColumnsAreThere(): void
    {
        $texts = $this->draw(new Rect(0, 0, 8, 80), framed: false);

        self::assertContains(self::LONGEST, $texts);
        self::assertContains('bardzo-dluga-na…', $texts);
    }

    /**
     * @return list<string>
     */
    private function draw(Rect $bounds, bool $framed): array
    {
        $map = new EffectMap([
            'browser.delete.done' => new EffectAssignment('/dzwieki/bardzo-dluga-nazwa-pliku.wav'),
        ]);

        $primitives = (new EffectList(
            [new EventDeclaration('browser.delete.done', self::LONGEST)],
            $map,
            new ScrollWindow(),
            new StubTranslator(),
            0,
            $framed,
        ))->draw($bounds);

        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
