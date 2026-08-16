<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
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
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Zajętość katalogu na dysku — pierwszy odbiorca pracy tłowej (krok 26).
 *
 * Test pilnuje tego samego, co zestaw sumy kontrolnej w kroku 25, i jednej rzeczy
 * ponadto: **że po pracy nie zostaje właściciel bez sprzątacza**. Praca w tle
 * różni się od odczytu własnego tym, że zapomniane przerwanie zostawia proces,
 * a nie uchwyt — więc `stopCount` atrapy jest tu asercją o tej samej wadze, co
 * treść wiersza.
 */
final class FileDescriptionFlowTest extends TestCase
{
    private const DIRECTORY = '/home/dokumenty';

    private const FILE = '/home/notatka.txt';

    /** Wiersz istnieje od pierwszej klatki i mówi, którym klawiszem go policzyć. */
    public function testTheRowStandsInTheSizeSectionWithAHintBeforeAnyoneAsks(): void
    {
        [$screen] = $this->screen();
        $screen->useContext($this->directoryContext());

        $texts = implode("\n", self::textsOf($screen->draw(self::panel())));

        self::assertStringContainsString('module.file-info.row.diskUsage', $texts);
        self::assertStringContainsString('module.file-info.diskUsage.idle', $texts);
    }

    /**
     * Dla zwykłego pliku wiersza nie ma wcale — odpowiedź stoi już obok, w blokach
     * i-węzła, a proces potomny po znaną liczbę byłby kosztem bez treści.
     */
    public function testThereIsNoSuchRowForARegularFile(): void
    {
        [$screen] = $this->screen();
        $screen->useContext($this->fileContext());

        $texts = implode("\n", self::textsOf($screen->draw(self::panel())));

        self::assertStringNotContainsString('module.file-info.row.diskUsage', $texts);
        self::assertStringContainsString('module.file-info.row.blocks', $texts);
    }

    /** Praca **nie startuje sama** — zaznaczenie zmienia się 30 razy na sekundę. */
    public function testWorkStartsOnlyOnDemand(): void
    {
        [$screen, $processes] = $this->screen();
        $screen->useContext($this->directoryContext());

        for ($frame = 0; $frame < 5; ++$frame) {
            $screen->draw(self::panel());
        }

        self::assertSame([], $processes->startedCommands, 'pięć klatek i ani jednego procesu');

        $screen->handle(KeyPress::character('d'));

        self::assertCount(1, $processes->startedCommands);
    }

    /**
     * `-s` sumuje zamiast wypisywać drzewo, `-B1` każe podać wynik w bajtach,
     * a ścieżka idzie przez `escapeshellarg()` — nazwa z odstępem albo myślnikiem
     * ma zostać jednym argumentem, a nie drugim poleceniem.
     */
    public function testTheCommandAsksDuForTheDirectoryTotalInBytes(): void
    {
        [$screen, $processes] = $this->screen();
        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        $command = $processes->startedCommands[0] ?? '';

        self::assertStringContainsString('du -sB1', $command);
        self::assertStringContainsString(escapeshellarg(self::DIRECTORY), $command);
    }

    /** Limit czasu pracy tłowej jest osobny od limitu polecenia `file`. */
    public function testTheTimeoutComesFromItsOwnSetting(): void
    {
        [$screen, $processes] = $this->screen(settings: (new Settings())
            ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::DISK_USAGE, true)
            ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::TIMEOUT, 1)
            ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::BACKGROUND_TIMEOUT, 60));

        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        self::assertSame([60], $processes->timeouts);
    }

    /**
     * Postępu `du` nie zna, więc pasek chodzi w trybie „nieznany” — pierwszy raz
     * w aplikacji od kroku 23. Wypełnienie ma być, ale **nie od lewej krawędzi**:
     * wędruje, a zegar przychodzi z zewnątrz.
     */
    public function testAWanderingProgressBarShowsThatSomethingIsHappening(): void
    {
        // Praca ma trwać dłużej niż dwie klatki, bo o dwie klatki właśnie chodzi.
        [$screen] = $this->screen(processes: new StubBackgroundProcess(pollsUntilDone: 10));
        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));
        $screen->useTime(0.0);

        $atStart = self::fillOf($screen->draw(self::panel()));

        self::assertNotNull($atStart, 'pasek postępu stoi pod sekcjami, gdy praca trwa');

        $screen->useTime(0.6);
        $later = self::fillOf($screen->draw(self::panel()));

        self::assertNotNull($later);
        self::assertNotSame($atStart->bounds->column, $later->bounds->column, 'wypełnienie wędruje wraz z czasem');
    }

    /** Wynik wchodzi do wiersza, a pasek znika — praca skończona nic nie pokazuje. */
    public function testTheMeasuredValueReplacesTheHintAndTheBarGoesAway(): void
    {
        [$screen, $processes] = $this->screen(processes: new StubBackgroundProcess(
            pollsUntilDone: 2,
            output: "5242880\t/home/dokumenty",
        ));

        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        $primitives = [];

        for ($frame = 0; $frame < 3; ++$frame) {
            $primitives = $screen->draw(self::panel());
        }

        $texts = implode("\n", self::textsOf($primitives));

        self::assertStringContainsString('5.0 MB', $texts);
        self::assertStringNotContainsString('module.file-info.diskUsage.idle', $texts);
        self::assertNull(self::fillOf($primitives), 'po skończonej pracy paska nie ma');
        self::assertSame(0, $processes->stopCount, 'praca, która skończyła się sama, nie jest przerywana');
    }

    /**
     * Kod wyjścia różny od zera nie unieważnia wyniku: `du` kończy się jedynką za
     * każdy nieprzeczytany katalog, a mimo to podaje sumę tego, co przeczytało.
     */
    public function testAPartialResultIsShownEvenWhenTheCommandComplains(): void
    {
        [$screen] = $this->screen(processes: new StubBackgroundProcess(
            pollsUntilDone: 1,
            output: "1024\t/home/dokumenty",
            exitCode: 1,
        ));

        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        self::assertStringContainsString('1.0 kB', implode("\n", self::textsOf($screen->draw(self::panel()))));
    }

    /** Wyjście bez liczby to jedyne prawdziwe niepowodzenie pomiaru. */
    public function testOutputWithoutANumberIsAFailure(): void
    {
        [$screen] = $this->screen(processes: new StubBackgroundProcess(
            pollsUntilDone: 1,
            output: 'du: nie znaleziono polecenia',
            exitCode: 127,
        ));

        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        self::assertStringContainsString(
            'module.file-info.diskUsage.failed',
            implode("\n", self::textsOf($screen->draw(self::panel()))),
        );
    }

    /** Powód przerwania po limicie czasu należy do rdzenia i przechodzi bez zmian. */
    public function testTheCoreReasonReachesTheRow(): void
    {
        [$screen] = $this->screen(processes: new StubBackgroundProcess(problemKey: 'process.timedOut'));

        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        self::assertStringContainsString(
            'process.timedOut',
            implode("\n", self::textsOf($screen->draw(self::panel()))),
        );
    }

    public function testRefusalWhenTheSettingIsOff(): void
    {
        [$screen] = $this->screen(settings: new Settings());
        $screen->useContext($this->directoryContext());

        $outcome = $screen->handle(KeyPress::character('d'));

        self::assertSame('module.file-info.diskUsage.disabled', $outcome->message?->text);
    }

    public function testRefusalOnAnEntryThatIsNotADirectory(): void
    {
        [$screen, $processes] = $this->screen();
        $screen->useContext($this->fileContext());

        $outcome = $screen->handle(KeyPress::character('d'));

        self::assertSame('module.file-info.diskUsage.notADirectory', $outcome->message?->text);
        self::assertSame([], $processes->startedCommands);
    }

    /**
     * Najważniejsza asercja tego zestawu: zmiana zaznaczenia **przerywa** pracę.
     * Bez tego przewinięcie listy zostawiałoby po jednym `du` na każdy katalog,
     * przez który kursor przeszedł.
     */
    public function testChangingTheSelectionStopsTheWork(): void
    {
        [$screen, $processes] = $this->screen();
        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        $screen->useContext(new ModuleContext('/home', 'zdjecia', ContextEntryKind::Directory));

        self::assertSame(1, $processes->stopCount);
    }

    public function testClosingTheScreenStopsTheWork(): void
    {
        [$screen, $processes] = $this->screen();
        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));

        $screen->reset();

        self::assertSame(1, $processes->stopCount);
    }

    /** Drugie zamówienie przerywa pierwsze — jedna praca naraz, także tutaj. */
    public function testAskingTwiceStopsThePreviousWork(): void
    {
        [$screen, $processes] = $this->screen();
        $screen->useContext($this->directoryContext());
        $screen->handle(KeyPress::character('d'));
        $screen->handle(KeyPress::character('d'));

        self::assertSame(1, $processes->stopCount);
        self::assertCount(2, $processes->startedCommands);
    }

    /**
     * @return array{FileInfoScreen, StubBackgroundProcess}
     */
    private function screen(?Settings $settings = null, ?StubBackgroundProcess $processes = null): array
    {
        $processes ??= new StubBackgroundProcess();
        $stats = (new StubFileStat())
            ->add(self::DIRECTORY, StubFileStat::directory(7))
            ->add(self::FILE);

        $settings ??= (new Settings())->withModuleValue(FileInfoSettings::ID, FileInfoSettings::DISK_USAGE, true);

        $state = new LoopState($settings);
        $module = new FileInfoModule(
            $state,
            new StubTranslator(),
            new InMemorySettings($settings),
            new StubImagePreview(),
            $processes,
            new StubFileInspector('ASCII text'),
            $stats,
            new StubChecksums(),
        );

        // Kwerendy modułu w rejestrze — tak, jak robi to `Bootstrap` (krok 53).
        $state->queries()->useModules([$module]);

        $screen = $module->screen();

        self::assertInstanceOf(FileInfoScreen::class, $screen);

        return [$screen, $processes];
    }

    private function directoryContext(): ModuleContext
    {
        return new ModuleContext('/home', 'dokumenty', ContextEntryKind::Directory);
    }

    private function fileContext(): ModuleContext
    {
        return new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File);
    }

    /** Ten sam prostokąt, którym ogląda ekran zestaw kroku 25 — 16 wierszy na 70 kolumn. */
    private static function panel(): Rect
    {
        return new Rect(0, 2, 16, 70);
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

    /** @param list<Primitive> $primitives */
    private static function fillOf(array $primitives): ?Bar
    {
        foreach ($primitives as $primitive) {
            // Wypełnienie paska, a nie obwódka: akcentem rysuje się także kursor
            // sekcji, a on stoi w klatce niezależnie od tego, czy coś trwa.
            if ($primitive instanceof Bar && $primitive->role === Role::Accent && $primitive->weight === Weight::Fill) {
                return $primitive;
            }
        }

        return null;
    }
}
