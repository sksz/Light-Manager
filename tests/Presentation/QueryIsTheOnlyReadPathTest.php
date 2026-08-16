<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * **Główne kryterium kroku 53, sprawdzane maszynowo: dane czyta się przez
 * rejestr kwerend, a nie przez obiekt stanu.**
 *
 * Test chodzi po **treści** plików, a nie po przestrzeniach nazw, i to jest tu
 * konieczne, w odróżnieniu od `CoreKnowsNothingAboutFilesTest`: ekran nadal
 * **trzyma** obiekt stanu, bo przez niego działa (`enter()`, `toggleMark()`,
 * `clearFilter()`). Zakazane jest nie posiadanie referencji, tylko **czytanie
 * nią** — a to widać wyłącznie w wywołaniu.
 *
 * Spis zakazanych wyrażeń jest **wypisany wprost**, a nie zgadywany wzorcem, bo
 * granica biegnie po znaczeniu metody, nie po jej nazwie: `focused()->enter()`
 * jest czynnością i wolno je wołać, `focused()->directory()` jest odczytem
 * i nie wolno. Każda pozycja mówi, czym ją zastąpić — inaczej test byłby
 * zakazem bez drogi wyjścia.
 *
 * Trzy grupy plików są **poza zasięgiem** i każda z innego powodu: fasady
 * (`*Queries.php`, `CoreReader`) czytają stan z definicji — to one są tą jedyną
 * drogą; kwerendy (`Presentation/Query/`) czytają go, bo są jego źródłem;
 * moduły Fazy XVII i XVIII (`Ssh`, `Docker`, `Kubernetes`) dostaną kwerendy
 * w kroku 54 i do tego czasu czytają po staremu.
 */
final class QueryIsTheOnlyReadPathTest extends TestCase
{
    /**
     * Odczyt zakazany → czym go zastąpić.
     *
     * @var array<string, string>
     */
    private const FORBIDDEN = [
        '$this->state->settings()' => 'CoreReader::settings()',
        '$this->state->context()' => 'CoreReader::context()',
        '$this->panes->focused()->directory()' => 'BrowserQueries::directory()',
        '$this->panes->focused()->marked()' => 'BrowserQueries::marked()',
        '$this->panes->focused()->filter()' => 'BrowserQueries::filter()',
        '$this->panes->focused()->fullCount()' => 'BrowserQueries::fullCount()',
        '$this->panes->focused()->showsHiddenEntries()' => 'BrowserQueries::showsHidden()',
        '$this->panes->focusedDirectory()' => 'BrowserQueries::directory()',
        '$this->panes->focusedMarked()' => 'BrowserQueries::marked()',
        '$this->panes->focusedSelection()' => 'BrowserQueries::selection()',
        '$this->panes->focusedTree()' => 'BrowserQueries::treeOf()',
        '$this->panes->focusedOperands()' => 'BrowserQueries::operands()',
        '$this->panes->focusShowsTree()' => 'BrowserQueries::showsTree()',
        '$this->panes->focusesSecond()' => 'BrowserQueries::focusesSecond()',
        '$this->panes->showsTree(' => 'BrowserQueries::showsTree()',
        '$this->panes->tree(' => 'BrowserQueries::treeOf()',
        '$this->player->playlist()' => 'AudioQueries::playlist()',
        '$this->player->nowPlaying()' => 'AudioQueries::nowPlaying()',
        '$this->player->isPlaying()' => 'AudioQueries::nowPlaying()->playing',
        '$this->player->mode()' => 'AudioQueries::nowPlaying()->mode',
        '$this->player->problem()' => 'AudioQueries::nowPlaying()->problem',
        '$this->effects->map()' => 'AudioQueries::effects()',
        '$this->state->description()' => 'FileInfoQueries::description()',
        '$this->state->checksum()' => 'FileInfoQueries::checksum()',
        '$this->state->diskUsage()' => 'FileInfoQueries::diskUsage()',
        '$this->state->preview()' => 'FileInfoQueries::preview()',

        // Moduł sesji zdalnej (krok 54). Czynności zostają wolne: `enter()`,
        // `goUp()`, `refresh()`, `open()`, `close()`, `useFilter()`, `putCursor()`.
        '$this->session->book()' => 'SshQueries::book()',
        '$this->session->state()' => 'SshQueries::session()',
        '$this->session->location()' => 'SshQueries::hostBook()->location',
        '$this->browser->state()' => 'SshQueries::remote()',
        '$this->browser->entries()' => 'SshQueries::remote()->entries',
        '$this->browser->cursor()' => 'SshQueries::remote()->cursor',
        '$this->browser->count()' => 'SshQueries::remote()->count()',
        '$this->browser->selected()' => 'SshQueries::selected()',
        '$this->browser->path()' => 'SshQueries::path()',
        '$this->browser->host()' => 'SshQueries::host()',
        '$this->browser->filter()' => 'SshQueries::remote()->filter',
        '$this->browser->showsHidden()' => 'SshQueries::remote()->showsHidden',
        '$this->browser->hasListing()' => 'SshQueries::hasListing()',
        '$this->browser->directory()' => 'SshQueries::remote()',

        // Moduł Dockera (krok 54). Czynności zostają wolne: `refresh()`,
        // `remove()`, `begin()`, `narrowTo()`, `moveTo()`, `tick()`, `stop()`.
        '$this->images->entries()' => 'DockerQueries::images()->entries',
        '$this->images->selected()' => 'DockerQueries::images()->selected()',
        '$this->images->cursor()' => 'DockerQueries::images()->cursor',
        '$this->images->isLoaded()' => 'DockerQueries::images()->loaded',
        '$this->images->problemKey()' => 'DockerQueries::images()->problemKey',
        '$this->containers->entries()' => 'DockerQueries::containers()->entries',
        '$this->containers->selected()' => 'DockerQueries::containers()->selected()',
        '$this->containers->cursor()' => 'DockerQueries::containers()->cursor',
        '$this->containers->isLoaded()' => 'DockerQueries::containers()->loaded',
        '$this->containers->problemKey()' => 'DockerQueries::containers()->problemKey',
        '$this->containers->project()' => 'DockerQueries::containers()->project',
        '$this->containers->projects()' => 'DockerQueries::containers()->projects',
        '$this->compose->state()' => 'DockerQueries::compose()',

        // Moduł Kubernetesa (krok 54). Czynności zostają wolne: `begin()`,
        // `load()`, `useContext()`, `useNamespace()`, `advance()`, `stop()`.
        '$this->cluster->stage()' => 'KubernetesQueries::cluster()->stage',
        '$this->cluster->versions()' => 'KubernetesQueries::cluster()->versions',
        '$this->cluster->contexts()' => 'KubernetesQueries::contexts()->contexts',
        '$this->cluster->problemKey()' => 'KubernetesQueries::cluster()->problemKey',
        '$this->cluster->problemParameters()' => 'KubernetesQueries::cluster()->problemParameters',
        '$this->cache->rowsOf(' => 'KubernetesQueries::rowsOf()',
        '$this->cache->knows(' => 'KubernetesQueries::knows()',
        '$this->catalog->groups()' => 'KubernetesQueries::groups()',
        '$this->catalog->kindsOf(' => 'KubernetesQueries::kindsOf()',
        '$this->catalog->find(' => 'KubernetesQueries::findKind()',
    ];

    /**
     * Katalogi objęte regułą — rdzeń i **wszystkie sześć modułów**.
     *
     * Trzy pierwsze dostały kwerendy w kroku 53, trzy kolejne w 54 — i od tego
     * kroku w aplikacji nie ma już modułu zwolnionego z reguły.
     */
    private const WATCHED = [
        'src/Presentation/Cli/Screen',
        'src/Presentation/Ui/Overlay',
        'src/Module/Browser/Presentation',
        'src/Module/FileInfo/Presentation',
        'src/Module/Audio/Presentation',
        'src/Module/Ssh/Presentation',
        'src/Module/Docker/Presentation',
        'src/Module/Kubernetes/Presentation',
    ];

    /** Kto czyta stan z urzędu: fasada, kwerenda i sam obiekt stanu. */
    private const READERS = ['Queries.php', 'CoreReader.php', '/Query/', 'State.php', 'Panes.php'];

    public function testNoScreenReadsStateBesidesThroughTheRegistry(): void
    {
        $offenders = [];

        foreach (self::watchedFiles() as $path => $contents) {
            foreach (self::FORBIDDEN as $call => $instead) {
                if (str_contains($contents, $call)) {
                    $offenders[] = $path . ' → ' . $call . ' (pytaj ' . $instead . ')';
                }
            }
        }

        self::assertSame([], $offenders, "odczyt z pominięciem rejestru kwerend:\n" . implode("\n", $offenders));
    }

    /**
     * Fasada jest **jedna na moduł** — i to jest warunek, żeby powyższy spis
     * dało się utrzymać: dwie fasady znaczyłyby dwa miejsca rozpakowujące
     * ładunek, a trzecia prędzej czy później zaczęłaby czytać stan wprost.
     */
    public function testEveryModuleHasExactlyOneReader(): void
    {
        $readers = [
            'src/Module/Browser/Presentation/BrowserQueries.php',
            'src/Module/FileInfo/Presentation/FileInfoQueries.php',
            'src/Module/Audio/Presentation/AudioQueries.php',
            'src/Module/Ssh/Presentation/SshQueries.php',
            'src/Module/Docker/Presentation/DockerQueries.php',
            'src/Module/Kubernetes/Presentation/KubernetesQueries.php',
            'src/Presentation/Cli/Query/CoreReader.php',
        ];

        foreach ($readers as $reader) {
            self::assertFileExists(dirname(__DIR__, 2) . '/' . $reader);
        }
    }

    /**
     * @return array<string, string> ścieżka względna → treść pliku
     */
    private static function watchedFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        foreach (self::WATCHED as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root . '/' . $directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = $file->getPathname();

                if ($file->getExtension() !== 'php' || self::isReader($path)) {
                    continue;
                }

                $contents = file_get_contents($path);

                if ($contents !== false) {
                    $files[substr($path, strlen($root) + 1)] = $contents;
                }
            }
        }

        return $files;
    }

    private static function isReader(string $path): bool
    {
        foreach (self::READERS as $reader) {
            if (str_contains($path, $reader)) {
                return true;
            }
        }

        return false;
    }
}
