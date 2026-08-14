<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\FileInfo;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Presentation\Command\ShowCommand;
use LightManager\Module\FileInfo\Presentation\FileInfoScreen;
use LightManager\Presentation\Ui\Transition;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubFileInspector;
use LightManager\Tests\Support\StubFileStat;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Pierwszy moduł projektu po przeprowadzce z rdzenia.
 *
 * Test pilnuje trzech rzeczy naraz: że opis pliku działa jak wcześniej, że
 * moduł deklaruje wszystkie pięć punktów zaczepienia i że jego komenda —
 * pierwsza w projekcie z podpowiedziami liczonymi na żądanie — naprawdę czyta
 * dysk, a nie listę policzoną przy starcie.
 */
final class FileInfoModuleTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('dokumenty'), Entry::directory('dane'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048)])
            ->add('/home/dane', []);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    public function testModuleDeclaresEveryHookOfTheContract(): void
    {
        $module = $this->app->module('file-info');

        self::assertNotNull($module);
        self::assertSame('file-info', $module->id());

        $shortcut = $module->shortcut();

        self::assertNotNull($shortcut);
        self::assertSame('d', $shortcut->character);
        self::assertTrue($shortcut->ctrl);
        self::assertDirectoryExists((string) $module->translations(), 'napisy leżą w katalogu modułu');
    }

    public function testSettingsTabDeclaresEveryPosition(): void
    {
        $module = $this->app->module('file-info');

        self::assertInstanceOf(\LightManager\Application\Module\ProvidesSettingsTab::class, $module);

        $keys = array_map(
            static fn (\LightManager\Application\Module\ModuleSetting $setting): string => $setting->key,
            $module->settingsTab()->settings,
        );

        self::assertSame(
            [
                'timeout', 'arguments', 'timeFormat', 'inode', 'checksum', 'checksumLimit',
                'diskUsage', 'backgroundTimeout', 'textPreview', 'lineNumbers', 'textWrap',
            ],
            $keys,
            'krok 26 domknął listę dwiema pozycjami odłożonymi w kroku 25, krok 29 dołożył podgląd tekstu, '
            . 'a poprawka z 2026-08-12 — zawijanie, które do tej pory żyło wyłącznie pod Alt+Z',
        );
    }

    /**
     * Moduł wnosi jedną komendę i **nie jest to skok**.
     *
     * `file-info.jump` przeniosła się w kroku 21 do modułu przeglądarki — po
     * wyprowadzeniu nawigacji tylko ona umie zmienić katalog — i lista komend
     * została pusta. Krok 32 wypełnił ją `file-info.show`: nazwą dla czynności,
     * którą moduł umiał od kroku 20, ale wyłącznie pod skrótem `Ctrl`+`D`.
     */
    public function testBringsTheShowCommandAndNotTheJumpThatMoved(): void
    {
        $module = $this->app->module('file-info');

        self::assertInstanceOf(ProvidesCommands::class, $module);
        self::assertNull($this->app->commandRegistry->find('file-info.jump'));

        self::assertInstanceOf(ShowCommand::class, $this->app->commandRegistry->find('file-info.show'));

        $commands = $module->commands();

        self::assertCount(1, $commands);
        self::assertInstanceOf(ShowCommand::class, $commands[0]);
    }

    /**
     * Opis pliku: cztery sekcje i zawartość od polecenia `file`.
     *
     * Test idzie przez **moduł**, a nie przez ręcznie złożony ekran, i to jest
     * po kroku 25 jedyna droga: wnętrze modułu składa on sam, a `Bootstrap` widzi
     * z niego jedną klasę.
     */
    public function testScreenDescribesAFileInFourSections(): void
    {
        $screen = $this->screen();
        $screen->useContext(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File));

        $texts = implode("\n", self::textsOf($screen->draw(self::panel())));

        self::assertStringContainsString('notatka.txt', $texts);
        self::assertStringContainsString('PDF document, version 1.7', $texts, 'opis od polecenia file');
        self::assertStringContainsString('module.file-info.section.identity', $texts);
        self::assertStringContainsString('module.file-info.section.size', $texts);
        self::assertSame(['/home/notatka.txt'], $this->app->inspector->inspectedPaths);
    }

    /** Wpis, którego `lstat` nie widzi, nie ma opisu — i tylko on. */
    public function testMissingEntryHasNoDescription(): void
    {
        $this->app->stats->deny('/home/znikl.txt');

        $screen = $this->screen();
        $screen->useContext(new ModuleContext('/home', 'znikl.txt', ContextEntryKind::File));

        self::assertContains('module.file-info.nothing', self::textsOf($screen->draw(self::panel())));
    }

    /**
     * Zdanie „nie zaznaczono wpisu“ stoi **wewnątrz lewego panelu**, a nie na
     * linii jego obwódki.
     *
     * Usterka zgłoszona 2026-08-12: przy dwóch panelach napis siadał na wierszu
     * zerowym prostokąta ekranu — czyli na kresce — i nakładał się na etykietę
     * „Opis pliku“, litera na literę. Widać ją było dopiero wtedy, gdy opisu nie
     * ma **i** okno jest dość szerokie na podział; filtrowanie z kroku 30 dołożyło
     * drugą drogę do tego stanu.
     */
    public function testTheNoSelectionSentenceStaysInsideTheLeftPanel(): void
    {
        $this->app->stats->deny('/home/znikl.txt');

        $screen = $this->screen();
        $screen->useContext(new ModuleContext('/home', 'znikl.txt', ContextEntryKind::File));

        // Szeroko, żeby ekran naprawdę narysował własną oprawę (próg z kroku 24).
        $bounds = new Rect(0, 0, 16, 120);
        $runs = array_values(array_filter(
            $screen->draw($bounds),
            static fn (Primitive $primitive): bool => $primitive instanceof TextRun,
        ));

        self::assertCount(1, $runs);
        self::assertGreaterThan($bounds->row, $runs[0]->row, 'napis leży pod górną kreską, nie na niej');
        self::assertGreaterThan($bounds->column, $runs[0]->column, 'i za lewą kreską, nie na niej');
    }

    /**
     * Polecenie `file` uruchamia się przy zmianie zaznaczenia, a nie co klatkę:
     * trzydzieści procesów potomnych na sekundę kosztowałoby więcej niż cała
     * reszta klatki.
     */
    public function testDescriptionIsComputedOncePerSelection(): void
    {
        $screen = $this->screen();
        $inspector = $this->app->inspector;
        $context = new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File);

        for ($frame = 0; $frame < 5; ++$frame) {
            $screen->useContext($context);
            $screen->draw(self::panel());
        }

        self::assertCount(1, $inspector->inspectedPaths);

        // Ponowne otwarcie ekranu liczy opis od nowa — po to, żeby zmiana
        // ustawień modułu była widoczna od razu.
        $screen->reset();
        $screen->useContext($context);

        self::assertCount(2, $inspector->inspectedPaths);
    }

    public function testModuleSettingsReachTheInspector(): void
    {
        $inspector = new StubFileInspector();
        $store = new InMemorySettings(
            (new Settings())
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::TIMEOUT, 5)
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::ARGUMENTS, '-k'),
        );

        (new InspectSelectedEntryUseCase($inspector, new StubFileStat(), $store, new StubTranslator()))
            ->execute(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File));

        self::assertSame([[5, '-k']], $inspector->options);
    }

    public function testEmptyContextDescribesNothing(): void
    {
        $description = (new InspectSelectedEntryUseCase(
            new StubFileInspector(),
            new StubFileStat(),
            new InMemorySettings(),
            new StubTranslator(),
        ))->execute(new ModuleContext());

        self::assertNull($description);
    }

    /** Ekran modułu wraca `Esc`em — tak samo, jak każdy ekran rdzenia. */
    public function testEscapeClosesTheModuleScreen(): void
    {
        $outcome = $this->app->fileInfo->handle(KeyPress::special(Key::Escape, ''));

        self::assertSame(Transition::Close, $outcome->transition);
    }

    /**
     * Ekran modułu widziany **jego** typem.
     *
     * Zestaw ekranów trzyma go pod kontraktem, bo tak widzi go aplikacja; test
     * napędza za to `useContext()` i `reset()`, czyli zdolności deklarowane
     * osobno — i stąd to jedno zawężenie, zrobione raz zamiast w pięciu miejscach.
     */
    private function screen(): FileInfoScreen
    {
        $screen = $this->app->fileInfo;

        self::assertInstanceOf(FileInfoScreen::class, $screen);

        return $screen;
    }

    private static function panel(): Rect
    {
        return new Rect(0, 2, 10, 40);
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
