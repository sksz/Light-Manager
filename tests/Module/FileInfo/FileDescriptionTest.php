<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\FileInfo;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
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
 * Pełny obraz stanu wpisu — krok 25.
 *
 * Test patrzy na dwie rzeczy naraz i obie są treścią kroku: **co opis mówi**
 * (sekcje, katalogi, dowiązania, właściciel bez `posix`) oraz **czego opis nie
 * robi** — nie uruchamia polecenia dla katalogu, nie liczy sumy kontrolnej,
 * dopóki nikt o nią nie poprosi, i nie zostawia po sobie pracy, gdy zaznaczenie
 * się zmienia.
 */
final class FileDescriptionTest extends TestCase
{
    private const FILE = '/home/notatka.txt';

    public function testDescriptionHasFourSectionsInAFixedOrder(): void
    {
        $description = $this->inspect()->execute($this->fileContext());

        self::assertNotNull($description);
        self::assertSame(
            ['identity', 'size', 'permissions', 'times'],
            array_map(static fn ($section): string => $section->key, $description->sections),
        );
    }

    /** Kolejność ma powód: to, co znikome, stoi wyżej niż to, co drogie. */
    public function testIdentityCarriesTheNameKindAndContent(): void
    {
        $description = $this->inspect()->execute($this->fileContext());

        self::assertNotNull($description);
        self::assertSame(
            [
                'module.file-info.row.name',
                'module.file-info.row.kind',
                'module.file-info.row.content',
            ],
            array_map(static fn ($row): string => $row->labelKey, $description->sections[0]->rows),
        );
    }

    /** Katalog jest opisywany (P3), ale **bez** polecenia `file`. */
    public function testDirectoryIsDescribedWithoutRunningTheInspector(): void
    {
        $inspector = new StubFileInspector();
        $stats = (new StubFileStat())->add('/home/dokumenty', StubFileStat::directory(7));

        $description = $this->inspect($inspector, $stats)
            ->execute(new ModuleContext('/home', 'dokumenty', ContextEntryKind::Directory));

        self::assertNotNull($description);
        self::assertSame(EntryKind::Directory, $description->kind);
        self::assertSame([], $inspector->inspectedPaths);
        self::assertSame(
            ['module.file-info.row.name', 'module.file-info.row.kind', 'module.file-info.row.entries'],
            array_map(static fn ($row): string => $row->labelKey, $description->sections[0]->rows),
        );
    }

    public function testSymlinkShowsItsTargetAndWhetherItExists(): void
    {
        $stats = (new StubFileStat())->add(self::FILE, StubFileStat::symlink('../gdzies/indziej', false));
        $rows = $this->inspect(stats: $stats)->execute($this->fileContext())?->sections[0]->rows ?? [];
        $values = [];

        foreach ($rows as $row) {
            $values[$row->labelKey] = $row->value;
        }

        self::assertSame('../gdzies/indziej', $values['module.file-info.row.target'] ?? null);
        self::assertSame('module.file-info.target.missing', $values['module.file-info.row.targetState'] ?? null);
    }

    /**
     * Brak rozszerzenia `posix` daje sam numer **wraz z powodem**, a nie pustkę:
     * to informacja o systemie, nie o pliku.
     */
    public function testOwnerFallsBackToTheNumericIdWithAReason(): void
    {
        $stats = (new StubFileStat())->add(self::FILE, StubFileStat::withoutNames());
        $rows = $this->inspect(stats: $stats)->execute($this->fileContext())?->sections[2]->rows ?? [];

        self::assertStringContainsString('module.file-info.principal.numeric', $rows[1]->value);
        self::assertStringContainsString('1000', $rows[1]->value);
    }

    /** I-węzeł i dowiązania to szum dla większości plików — stąd przełącznik. */
    public function testInodeRowsAppearOnlyWhenTheSettingIsOn(): void
    {
        $without = $this->inspect()->execute($this->fileContext());
        $with = $this->inspect(settings: new InMemorySettings(
            (new Settings())->withModuleValue(FileInfoSettings::ID, FileInfoSettings::INODE, true),
        ))->execute($this->fileContext());

        self::assertCount(3, $without?->sections[2]->rows ?? []);
        self::assertCount(5, $with?->sections[2]->rows ?? []);
    }

    public function testRelativeTimeFormatReplacesTheAbsoluteOne(): void
    {
        $absolute = $this->inspect()->execute($this->fileContext());
        $relative = $this->inspect(settings: new InMemorySettings(
            (new Settings())->withModuleValue(
                FileInfoSettings::ID,
                FileInfoSettings::TIME_FORMAT,
                FileInfoSettings::TIME_FORMAT_RELATIVE,
            ),
        ))->execute($this->fileContext());

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $absolute?->sections[3]->rows[0]->value ?? '');
        self::assertStringContainsString('module.file-info.ago.', $relative?->sections[3]->rows[0]->value ?? '');
    }

    /** Suma kontrolna **nie startuje sama** — dopiero klawisz `s` o nią prosi. */
    public function testChecksumStartsOnlyOnDemand(): void
    {
        [$screen, $checksums] = $this->screen(checksumEnabled: true);
        $screen->useContext($this->fileContext());

        for ($frame = 0; $frame < 5; ++$frame) {
            $screen->draw(self::panel());
        }

        self::assertSame([], $checksums->startedPaths, 'pięć klatek i ani jednego odczytu');

        $screen->handle(KeyPress::character('s'));

        self::assertSame([self::FILE], $checksums->startedPaths);
    }

    /** Po naciśnięciu `s` praca posuwa się o jeden kawałek na klatkę. */
    public function testChecksumAdvancesOnceEveryFrameAndShowsAProgressBar(): void
    {
        [$screen] = $this->screen(checksumEnabled: true);
        $screen->useContext($this->fileContext());
        $screen->handle(KeyPress::character('s'));

        $primitives = $screen->draw(self::panel());
        $fills = array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof Bar && $primitive->role === Role::Accent,
        );

        self::assertNotSame([], $fills, 'pasek postępu z kroku 23 stoi pod sekcjami');

        // Cztery kroki dublera: po czwartej klatce suma jest gotowa, a pasek znika.
        for ($frame = 0; $frame < 4; ++$frame) {
            $primitives = $screen->draw(self::panel());
        }

        // Suma ma sześćdziesiąt cztery znaki i od poprawki z 2026-08-12 **zawija
        // się** na kolejny wiersz sekcji, zamiast wychodzić poza panel. Sklejona
        // z powrotem musi być w całości — i to jest treść tej asercji.
        $joined = (string) preg_replace('/\s+/', '', implode('', self::textsOf($primitives)));

        self::assertStringContainsString(StubChecksums::DIGEST, $joined);
    }

    /**
     * Długi opis od polecenia `file` **zawija się na kolejne wiersze sekcji**
     * zamiast wychodzić poza panel.
     *
     * Usterka zgłoszona 2026-08-12 na zdjęciu, którego `file` opisuje stu
     * dwudziestoma ośmioma znakami: wartość szła na płótno w całości, więc
     * kończyła się osiemdziesiąt osiem kolumn za krawędzią panelu — czyli po
     * sąsiednim. Dziś nie wychodzi ani o kolumnę, a treść jest cała.
     */
    public function testALongFileDescriptionWrapsInsteadOfLeavingThePanel(): void
    {
        $long = 'JPEG image data, JFIF standard 1.01, aspect ratio, density 1x1, '
            . 'segment length 16, baseline, precision 8, 940x1256, components 3';
        [$screen] = $this->screen(inspector: new StubFileInspector($long));
        $screen->useContext($this->fileContext());

        $primitives = $screen->draw(self::panel());

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                self::assertLessThanOrEqual(
                    self::panel()->right(),
                    $primitive->column + mb_strlen($primitive->text) - 1,
                    'napis wychodzi poza panel: ' . $primitive->text,
                );
            }
        }

        // Zawijanie łamie **po znaku**, więc sklejenie kawałków bez separatora
        // odtwarza treść co do znaku; białe znaki znikają po obu stronach, bo
        // ostatni kawałek jest dopełniony spacjami do równej szerokości bloku.
        $squashed = (string) preg_replace('/\s+/', '', implode('', self::textsOf($primitives)));

        self::assertStringContainsString(
            (string) preg_replace('/\s+/', '', $long),
            $squashed,
            'opis jest w klatce w całości',
        );
    }

    /** Zmiana zaznaczenia przerywa pracę — inaczej przewinięcie listy byłaby wyciekiem. */
    public function testChangingTheSelectionStopsTheChecksum(): void
    {
        [$screen, $checksums] = $this->screen(checksumEnabled: true);
        $screen->useContext($this->fileContext());
        $screen->handle(KeyPress::character('s'));

        self::assertTrue($checksums->state()->isRunning());

        $screen->useContext(new ModuleContext('/home', 'inny.txt', ContextEntryKind::File));

        self::assertFalse($checksums->state()->isRunning());
        self::assertGreaterThanOrEqual(1, $checksums->stopCount);
    }

    /** Zamknięcie ekranu też sprząta — `reset()` woła je przy każdym otwarciu. */
    public function testResetStopsTheChecksum(): void
    {
        [$screen, $checksums] = $this->screen(checksumEnabled: true);
        $screen->useContext($this->fileContext());
        $screen->handle(KeyPress::character('s'));
        $screen->reset();

        self::assertFalse($checksums->state()->isRunning());
    }

    /** Trzy odmowy, każda ze zdaniem mówiącym dlaczego. */
    public function testChecksumRefusesWithAReason(): void
    {
        [$disabled, $checksums] = $this->screen(checksumEnabled: false);
        $disabled->useContext($this->fileContext());
        $outcome = $disabled->handle(KeyPress::character('s'));

        self::assertSame([], $checksums->startedPaths);
        self::assertNotNull($outcome->message);
        self::assertStringContainsString('module.file-info.checksum.disabled', $outcome->message->text);

        [$tooLarge, $bigChecksums] = $this->screen(
            checksumEnabled: true,
            stats: (new StubFileStat())->add(self::FILE, StubFileStat::file(2 * 1024 * 1024 * 1024)),
        );
        $tooLarge->useContext($this->fileContext());
        $refusal = $tooLarge->handle(KeyPress::character('s'));

        self::assertSame([], $bigChecksums->startedPaths);
        self::assertNotNull($refusal->message);
        self::assertStringContainsString('module.file-info.checksum.tooLarge', $refusal->message->text);
    }

    /** Sekcje zwija się `Enter`em — tym samym klawiszem, co w oknie pomocy (krok 22). */
    public function testEnterCollapsesTheSectionUnderTheCursor(): void
    {
        [$screen] = $this->screen();
        $screen->useContext($this->fileContext());

        // Kursor stoi na pierwszej sekcji, więc znika jej treść, a nie nagłówek.
        self::assertContains('module.file-info.row.kind', self::textsOf($screen->draw(self::panel())));

        $screen->handle(KeyPress::special(Key::Enter, "\r"));
        $texts = self::textsOf($screen->draw(self::panel()));

        self::assertNotContains('module.file-info.row.kind', $texts, 'zwinięta sekcja pokazuje sam nagłówek');
        self::assertContains('module.file-info.row.mode', $texts, 'pozostałe sekcje zostają');
    }

    /**
     * @return array{FileInfoScreen, StubChecksums}
     */
    private function screen(
        bool $checksumEnabled = false,
        ?StubFileStat $stats = null,
        ?StubFileInspector $inspector = null,
    ): array {
        $checksums = new StubChecksums();
        $settings = new InMemorySettings(
            (new Settings())->withModuleValue(FileInfoSettings::ID, FileInfoSettings::CHECKSUM, $checksumEnabled),
        );

        $state = new LoopState($settings->current());
        $module = new FileInfoModule(
            $state,
            new StubTranslator(),
            $settings,
            new StubImagePreview(),
            new StubBackgroundProcess(),
            $inspector ?? new StubFileInspector('ASCII text'),
            $stats ?? new StubFileStat(),
            $checksums,
        );

        // Kwerendy modułu w rejestrze — tak, jak robi to `Bootstrap` (krok 53).
        // Bez tej linii ekran nie ma jak przeczytać własnego opisu, bo rejestr
        // jest jedyną drogą odczytu.
        $state->queries()->useModules([$module]);

        $screen = $module->screen();

        self::assertInstanceOf(FileInfoScreen::class, $screen);

        return [$screen, $checksums];
    }

    private function inspect(
        ?StubFileInspector $inspector = null,
        ?StubFileStat $stats = null,
        ?InMemorySettings $settings = null,
        ?TranslatorPort $translator = null,
    ): InspectSelectedEntryUseCase {
        return new InspectSelectedEntryUseCase(
            $inspector ?? new StubFileInspector('ASCII text'),
            $stats ?? new StubFileStat(),
            $settings ?? new InMemorySettings(),
            $translator ?? new StubTranslator(),
        );
    }

    private function fileContext(): ModuleContext
    {
        return new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File);
    }

    /**
     * Panel na tyle szeroki, żeby etykiety nie były ucinane, i na tyle wąski,
     * żeby **nie** powstał podział (próg z kroku 24 to 72 kolumny).
     */
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
}
