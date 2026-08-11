<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Presentation\Component\EntryList;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Lista plików w kolumnach — krok 27, widziana od strony użytkownika.
 *
 * `TableTest` pilnuje rachunku, ten zestaw pilnuje **decyzji**: że data i prawa
 * naprawdę są, że w wąskim panelu ustępują we właściwej kolejności, że nazwa nie
 * ustępuje nigdy i że katalog nadal nie udaje, jakby miał rozmiar.
 */
final class EntryColumnsTest extends TestCase
{
    /** 2026-08-11 18:45 czasu lokalnego — data w wierszu ma być dokładnie ta. */
    private const MODIFIED_AT = 1786819500;

    public function testTheListShowsNameSizeDateAndPermissions(): void
    {
        $texts = self::textsOf($this->list()->draw(new Rect(0, 0, 4, 70)));

        self::assertContains('notatka.txt', $texts);
        self::assertContains('1.0 kB', $texts);
        self::assertContains(date('Y-m-d H:i', self::MODIFIED_AT), $texts);
        self::assertContains('rw-r--r--', $texts);
    }

    /** Katalog ma ukośnik i nie ma rozmiaru — tak samo, jak przed krokiem 27. */
    public function testADirectoryKeepsItsSlashAndShowsNoSize(): void
    {
        $texts = self::textsOf($this->list()->draw(new Rect(0, 0, 4, 70)));

        self::assertContains('dokumenty/', $texts);
        self::assertNotContains('0 B', $texts);
    }

    /**
     * Wąski panel: ustępują prawa, potem data — a nazwa i rozmiar zostają.
     * To jest ta sama drabinka, którą krok 13 opisał dla stref klatki.
     */
    public function testDetailColumnsYieldFromTheRightInANarrowPane(): void
    {
        $wide = self::textsOf($this->list()->draw(new Rect(0, 0, 4, 70)));
        $narrow = self::textsOf($this->list()->draw(new Rect(0, 0, 4, 45)));
        $narrowest = self::textsOf($this->list()->draw(new Rect(0, 0, 4, 24)));

        self::assertContains('rw-r--r--', $wide);
        self::assertNotContains('rw-r--r--', $narrow, 'prawa ustępują pierwsze');
        self::assertNotContains(date('Y-m-d H:i', self::MODIFIED_AT), $narrow, 'a zaraz po nich data');
        self::assertContains('1.0 kB', $narrow, 'rozmiar zostaje najdłużej');

        self::assertContains('notatka.txt', $narrowest, 'nazwa nie ustępuje nigdy');
        self::assertNotContains('1.0 kB', $narrowest);
    }

    /** Wyłączone szczegóły znaczą dwie kolumny — czyli listę sprzed kroku 27. */
    public function testTurningDetailsOffLeavesNameAndSize(): void
    {
        $texts = self::textsOf($this->list(details: false)->draw(new Rect(0, 0, 4, 70)));

        self::assertContains('notatka.txt', $texts);
        self::assertContains('1.0 kB', $texts);
        self::assertNotContains('rw-r--r--', $texts);
        self::assertNotContains(date('Y-m-d H:i', self::MODIFIED_AT), $texts);
    }

    /**
     * Nazwy kolumn widać wyłącznie po włączeniu przełącznika.
     *
     * Sprawdzamy je po **wierszu**, a nie po treści, i to nie z lenistwa: dubler
     * tłumacza oddaje klucz zamiast napisu, a klucz `module.browser.column.size`
     * ma dwadzieścia pięć znaków przy kolumnie szerokiej na dziewięć — więc
     * w klatce ląduje przycięty. W prawdziwym katalogu napisów stoi tam „Rozmiar”
     * i mieści się z zapasem.
     */
    public function testColumnNamesAppearOnlyWithTheHeaderTurnedOn(): void
    {
        self::assertSame([], self::headerRunsOf($this->list()->draw(new Rect(0, 0, 4, 70))));

        $header = self::headerRunsOf($this->list(header: true)->draw(new Rect(0, 0, 4, 70)));

        self::assertCount(4, $header, 'cztery nazwy kolumn w pierwszym wierszu');
        self::assertSame('module.browser.column.name', $header[0]->text);
        self::assertStringStartsWith('module.b', $header[3]->text);
    }

    /**
     * Napisy pierwszego wiersza — ale tylko wtedy, gdy jest nagłówkiem.
     *
     * Bez nagłówka w wierszu zerowym stoi pierwszy wpis katalogu, więc test
     * rozpoznaje nagłówek po tym, po czym rozpoznaje go oko: po tym, że nie ma
     * tam nazwy żadnego pliku.
     *
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function headerRunsOf(array $primitives): array
    {
        $runs = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun && $primitive->row === 0 && str_starts_with($primitive->text, 'module.')) {
                $runs[] = $primitive;
            }
        }

        return $runs;
    }

    /**
     * Nagłówek zabiera wiersz **listy**, a nie dokłada się do niej: w panelu
     * wysokim na trzy wiersze widać dwa wpisy zamiast trzech. Gdyby okno
     * przewijania o tym nie wiedziało, ostatni wpis chowałby się pod krawędzią.
     */
    public function testTheHeaderCostsOneEntryOfTheVisibleList(): void
    {
        $bounds = new Rect(0, 0, 3, 70);

        self::assertContains('trzeci.txt', self::textsOf($this->list()->draw($bounds)));
        self::assertNotContains('trzeci.txt', self::textsOf($this->list(header: true)->draw($bounds)));
    }

    public function testAnEmptyDirectorySaysSoInsteadOfDrawingColumns(): void
    {
        $list = new EntryList(
            new Directory(new DirectoryPath('/pusty'), []),
            new ScrollWindow(),
            new StubTranslator(),
        );

        self::assertSame(['module.browser.empty'], self::textsOf($list->draw(new Rect(0, 0, 4, 70))));
    }

    private function list(bool $details = true, bool $header = false): EntryList
    {
        $settings = (new Settings())
            ->withModuleValue(BrowserSettings::ID, BrowserSettings::DETAILS, $details)
            ->withModuleValue(BrowserSettings::ID, BrowserSettings::COLUMN_HEADER, $header);

        return new EntryList(
            $this->directory(),
            new ScrollWindow(),
            new StubTranslator(),
            details: BrowserSettings::details($settings),
            header: BrowserSettings::columnHeader($settings),
        );
    }

    private function directory(): Directory
    {
        return new Directory(new DirectoryPath('/home'), [
            Entry::directory('dokumenty', self::MODIFIED_AT, 0o755),
            Entry::file('notatka.txt', 1024, self::MODIFIED_AT, 0o644),
            Entry::file('trzeci.txt', 7, self::MODIFIED_AT, 0o644),
        ]);
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
