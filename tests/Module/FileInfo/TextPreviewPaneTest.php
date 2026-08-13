<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\FileInfo;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Module\FileInfo\Presentation\FileInfoScreen;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubBackgroundProcess;
use LightManager\Tests\Support\StubChecksums;
use LightManager\Tests\Support\StubFileInspector;
use LightManager\Tests\Support\StubFileStat;
use LightManager\Tests\Support\StubImagePreview;
use LightManager\Tests\Support\StubTextPreview;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Prawy panel opisu z trzecią odpowiedzią: treścią pliku (krok 29).
 *
 * Test sprawdza **drogę**, a nie samo rysowanie: zaznaczenie → stan → przypadek
 * użycia → komponent rdzenia. Sam odczyt z dysku ma osobny sprawdzian
 * (`TextPreviewTest`), a sam komponent trzeci (`TextViewTest`) — tutaj chodzi
 * o to, czy te trzy rzeczy się ze sobą łączą i czy klawisze trafiają tam,
 * gdzie powinny.
 */
final class TextPreviewPaneTest extends TestCase
{
    /** Panel dość szeroki, żeby powstał podział, a więc i prawy panel (próg z kroku 24). */
    private const WIDE = 120;

    public function testRightPanelShowsTheFileContentInsteadOfTheNoPreviewSentence(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['<?php', 'echo 1;']));

        $texts = self::textsOf($screen->draw(new Rect(0, 0, 16, self::WIDE)));

        self::assertContains('<?php', $texts);
        self::assertContains('echo 1;', $texts);
        self::assertNotContains('module.file-info.preview.none', $texts);
    }

    public function testRefusalIsShownInsteadOfContent(): void
    {
        [$screen] = $this->screen(new StubTextPreview([], 'module.file-info.preview.binary'));

        $texts = self::textsOf($screen->draw(new Rect(0, 0, 16, self::WIDE)));

        self::assertContains('module.file-info.preview.binary', $texts);
    }

    /** Wyłączony podgląd zostawia dawną odpowiedź panelu — bez niespodzianek. */
    public function testDisabledSettingBringsBackTheNoPreviewSentence(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['<?php']), textPreview: false);

        $texts = self::textsOf($screen->draw(new Rect(0, 0, 16, self::WIDE)));

        self::assertContains('module.file-info.preview.none', $texts);
        self::assertNotContains('<?php', $texts);
    }

    /**
     * Obraz przed tekstem: `.svg` jest jednym i drugim naraz, a w panelu chce
     * się widzieć rysunek, nie jego zapis XML.
     */
    public function testImagePreviewWinsOverTextPreview(): void
    {
        [$screen] = $this->screen(
            new StubTextPreview(['<svg>']),
            images: StubImagePreview::withImage(),
            name: 'rysunek.svg',
        );

        $primitives = $screen->draw(new Rect(0, 0, 16, self::WIDE));

        self::assertNotSame([], array_filter($primitives, static fn ($p): bool => $p instanceof Bitmap));
        self::assertNotContains('<svg>', self::textsOf($primitives));
    }

    public function testPageDownScrollsThePreviewAndHomeComesBack(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9', 'a10']));

        $bounds = new Rect(0, 0, 8, self::WIDE);
        $screen->draw($bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::PageDown, ''));
        $scrolled = self::textsOf($screen->draw($bounds));

        self::assertNotContains('a1', $scrolled, 'okno zeszło niżej');
        self::assertContains('a8', $scrolled);

        $screen->handle(KeyPress::special(Key::Home, ''));
        $home = self::textsOf($screen->draw($bounds));

        self::assertContains('a1', $home, 'Home wraca na początek pliku');
    }

    public function testPageUpAfterPageDownReturnsToTheStart(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8']));

        $bounds = new Rect(0, 0, 8, self::WIDE);
        $screen->draw($bounds);
        $this->focusPreview($screen, $bounds);
        $screen->handle(KeyPress::special(Key::PageDown, ''));
        $screen->draw($bounds);
        $screen->handle(KeyPress::special(Key::PageUp, ''));

        self::assertContains('a1', self::textsOf($screen->draw($bounds)));
    }

    /** Zmiana zaznaczenia zaczyna oglądanie od początku — jak `ScrollWindow`. */
    public function testChangingTheSelectionRewindsThePreview(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8']));

        $bounds = new Rect(0, 0, 8, self::WIDE);
        $screen->draw($bounds);
        $this->focusPreview($screen, $bounds);
        $screen->handle(KeyPress::special(Key::PageDown, ''));
        $screen->draw($bounds);

        $screen->useContext(new ModuleContext('/home', 'inny.txt', ContextEntryKind::File));

        self::assertContains('a1', self::textsOf($screen->draw($bounds)));
    }

    /**
     * `Alt`+`z` przełącza zawijanie, a samo `z` nie — modyfikator jest częścią
     * klawisza, nie ozdobą jego opisu.
     */
    public function testAltZTogglesWrappingAndPlainZDoesNot(): void
    {
        $long = str_repeat('x', 80);
        [$screen] = $this->screen(new StubTextPreview([$long]));

        $bounds = new Rect(0, 0, 16, self::WIDE);
        $wrapped = self::previewLines($screen->draw($bounds));

        $screen->handle(KeyPress::character('z'));
        self::assertSame($wrapped, self::previewLines($screen->draw($bounds)), 'sama litera nie przełącza');

        $screen->handle(KeyPress::alt('z'));
        $trimmed = self::previewLines($screen->draw($bounds));

        self::assertGreaterThan(1, count($wrapped), 'przy zawijaniu wiersz zajmuje kilka linijek');
        self::assertCount(1, $trimmed, 'bez zawijania — dokładnie jedną');
        self::assertStringEndsWith('…', $trimmed[0], 'a ta jedna jest przycięta');
    }

    public function testLineNumbersAppearOnlyWhenTheSettingIsOn(): void
    {
        $bounds = new Rect(0, 0, 8, self::WIDE);

        [$without] = $this->screen(new StubTextPreview(['alfa', 'beta']));
        [$with] = $this->screen(new StubTextPreview(['alfa', 'beta']), lineNumbers: true);

        self::assertNotContains('1', self::textsOf($without->draw($bounds)));
        self::assertContains('1', self::textsOf($with->draw($bounds)));
    }

    /**
     * Spis klawiszy **zależy od ogniska** i pokazuje wyłącznie to, co działa tu
     * i teraz — tą samą regułą, którą przeglądarka pokazuje `Tab` dopiero przy
     * włączonym podziale.
     */
    public function testTheKeyListingFollowsTheFocus(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['alfa']));
        $bounds = new Rect(0, 0, 8, self::WIDE);
        $screen->draw($bounds);

        $sections = self::displaysOf($screen);

        self::assertSame('Alt+Z', $sections['module.file-info.help.wrap'] ?? null, 'zawijanie działa zawsze');
        self::assertSame('Tab', $sections['module.file-info.help.focus'] ?? null);
        self::assertSame('↑ / ↓', $sections['help.key.move'] ?? null, 'strzałki chodzą po sekcjach');
        self::assertArrayNotHasKey('module.file-info.help.scrollPreview', $sections, 'podgląd nie ma ogniska');

        $this->focusPreview($screen, $bounds);
        $preview = self::displaysOf($screen);

        self::assertSame('↑ / ↓', $preview['module.file-info.help.scrollLine'] ?? null);
        self::assertSame('PgUp / PgDn', $preview['module.file-info.help.scrollPreview'] ?? null);
        self::assertSame('Home / End', $preview['module.file-info.help.edges'] ?? null);
        self::assertArrayNotHasKey('help.key.collapse', $preview, 'sekcje nie mają ogniska');
    }

    /** Bez podziału nie ma dokąd przenieść ogniska, więc `Tab` się nie pokazuje. */
    public function testTheFocusKeyIsHiddenWithoutTheSecondPanel(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['alfa']));
        $screen->draw(new Rect(0, 0, 8, 40));

        self::assertArrayNotHasKey('module.file-info.help.focus', self::displaysOf($screen));
    }

    /** Strzałka przewija podgląd o **jedną linijkę**, a nie o panel. */
    public function testArrowsScrollThePreviewByASingleRow(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9']));
        $bounds = new Rect(0, 0, 5, self::WIDE);
        $screen->draw($bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::ArrowDown, ''));
        $texts = self::textsOf($screen->draw($bounds));

        self::assertNotContains('a1', $texts, 'pierwszy wiersz zszedł ponad krawędź');
        self::assertContains('a2', $texts, 'a drugi stanął na jego miejscu');

        $screen->handle(KeyPress::special(Key::ArrowUp, ''));

        self::assertContains('a1', self::textsOf($screen->draw($bounds)), 'i wraca tą samą drogą');
    }

    /** `End` skacze na koniec pliku, `Home` wraca. */
    public function testEndJumpsToTheEndOfTheFile(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9']));
        $bounds = new Rect(0, 0, 4, self::WIDE);
        $screen->draw($bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::End, ''));
        $texts = self::textsOf($screen->draw($bounds));

        self::assertContains('a9', $texts, 'widać ostatni wiersz pliku');
        self::assertNotContains('a1', $texts);

        $screen->handle(KeyPress::special(Key::Home, ''));

        self::assertContains('a1', self::textsOf($screen->draw($bounds)));
    }

    /** Strzałki z ogniskiem po lewej nadal chodzą po sekcjach, a nie po treści. */
    public function testArrowsMoveSectionsWhileTheDescriptionHasFocus(): void
    {
        [$screen] = $this->screen(new StubTextPreview(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9']));
        $bounds = new Rect(0, 0, 8, self::WIDE);
        $screen->draw($bounds);

        $screen->handle(KeyPress::special(Key::ArrowDown, ''));

        self::assertContains('a1', self::textsOf($screen->draw($bounds)), 'podgląd stoi w miejscu');
    }

    /** @return array<string, string> klucz opisu → zapis klawisza */
    private static function displaysOf(FileInfoScreen $screen): array
    {
        $displays = [];

        foreach ($screen->bindings() as $binding) {
            $displays[$binding->descriptionKey] = $binding->display();
        }

        return $displays;
    }

    /** `Tab` przenosi ognisko na podgląd — po klatce, bo o podziale wie rysowanie. */
    private function focusPreview(FileInfoScreen $screen, Rect $bounds): void
    {
        $screen->draw($bounds);
        $screen->handle(KeyPress::special(Key::Tab, ''));
    }

    /**
     * **Wiersz dłuższy niż cały panel też się zawija** — i to jest usterka
     * zgłoszona 2026-08-12, na `.php-cs-fixer.cache`: czterdzieści kilobajtów
     * JSON-a w jednej linii.
     *
     * Poprzedni test zawijania nie mógł jej złapać, bo używa wiersza o osiemdziesięciu
     * znakach — a taki mieścił się pod dawnym progiem i zawijał poprawnie. Nie
     * zawijały się wyłącznie wiersze **dłuższe od całego prostokąta**, czyli
     * dokładnie te, dla których zawijanie istnieje.
     */
    public function testALineLongerThanTheWholePanelWrapsAndFillsIt(): void
    {
        [$screen] = $this->screen(new StubTextPreview([str_repeat('x', 40_000)]));

        $lines = self::previewLines($screen->draw(new Rect(0, 0, 16, self::WIDE)));

        self::assertGreaterThan(1, count($lines), 'jedna długa linia wypełnia panel, a nie jedną linijkę');
        self::assertStringNotContainsString('…', implode('', $lines), 'zawinięty wiersz nie jest przycinany');
    }

    /**
     * Przełączenie zawijania **przeżywa uruchomienie**: `Alt`+`Z` zapisuje tę samą
     * pozycję ustawień, którą pokazuje zakładka modułu.
     *
     * Do poprawki z 2026-08-12 zawijanie było prywatną flagą w pamięci, więc ani
     * nie dawało się ustawić na ekranie ustawień, ani nie dożywało następnego
     * uruchomienia.
     */
    public function testAltZStoresTheChoiceInTheModuleSettings(): void
    {
        [$screen, , $settings] = $this->screen(new StubTextPreview(['alfa']));

        self::assertTrue(FileInfoSettings::textWrap($settings->current()), 'domyślnie zawija');

        $screen->handle(KeyPress::alt('z'));

        self::assertFalse(FileInfoSettings::textWrap($settings->current()), 'skrót zapisał ustawienie');

        $screen->handle(KeyPress::alt('z'));

        self::assertTrue(FileInfoSettings::textWrap($settings->current()), 'i przełącza w obie strony');
    }

    /**
     * @return array{FileInfoScreen, StubChecksums, InMemorySettings}
     */
    private function screen(
        StubTextPreview $texts,
        bool $textPreview = true,
        bool $lineNumbers = false,
        ?StubImagePreview $images = null,
        string $name = 'notatka.txt',
    ): array {
        $checksums = new StubChecksums();
        $settings = new InMemorySettings(
            (new Settings())
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::TEXT_PREVIEW, $textPreview)
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::LINE_NUMBERS, $lineNumbers),
        );

        $module = new FileInfoModule(
            new LoopState($settings->current()),
            new StubTranslator(),
            $settings,
            $images ?? StubImagePreview::unreadable(),
            new StubBackgroundProcess(),
            new StubFileInspector('ASCII text'),
            new StubFileStat(),
            $checksums,
            $texts,
        );

        $screen = $module->screen();

        self::assertInstanceOf(FileInfoScreen::class, $screen);
        $screen->useContext(new ModuleContext('/home', $name, ContextEntryKind::File));

        return [$screen, $checksums, $settings];
    }

    /**
     * Same linijki podglądu, odsiane od opisu z lewego panelu — treść testowa
     * składa się wyłącznie z „x”, więc rozpoznaje się bez znajomości układu.
     *
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function previewLines(array $primitives): array
    {
        return array_values(array_filter(
            self::textsOf($primitives),
            static fn (string $text): bool => preg_match('/^x+…?$/u', $text) === 1,
        ));
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
