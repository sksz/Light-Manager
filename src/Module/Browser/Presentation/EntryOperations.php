<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Dto\RemovalStage;
use LightManager\Application\Dto\RemovalState;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\OpenStartingDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\ReloadDirectoryUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\EntryName;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Trzy czynności zmieniające dysk: zmiana nazwy, nowy katalog, usunięcie
 * (krok 41).
 *
 * Klasa istnieje z tego samego powodu, co `HiddenEntries` w kroku 32: **czynność
 * o dwóch wejściach mieszka w jednym miejscu**. Zmianę nazwy i nowy katalog
 * wywołuje klawisz (`F6`, `F7`) i komenda (`browser.rename`, `browser.mkdir`),
 * a dwie implementacje rozjechałyby się przy pierwszej poprawce — tym razem
 * z ceną wyższą niż rozjechany komunikat, bo po drugiej stronie jest `unlink()`.
 *
 * Leży w warstwie `Presentation` modułu, bo składa okna nakładane i zna
 * `LoopState` — ta sama zasada, która postawiła tu komendy modułu (D41).
 *
 * **Trzy różne drogi niepowodzenia** i każda ma powód:
 *
 * 1. Zmiana nazwy i nowy katalog **wypuszczają wyjątek** — `InputHandler` łapie
 *    go i zostawia okno nazwy otwarte wraz z tym, co użytkownik wpisał, bo po
 *    zdaniu „nazwa jest już zajęta” ma on dokładnie jedną rzecz do zrobienia:
 *    poprawić nazwę.
 * 2. Usunięcie pojedynczego wpisu **łapie wyjątek i oddaje zdanie** — pytanie
 *    zamyka się, bo w oknie potwierdzenia nie ma czego poprawiać.
 * 3. Usuwanie rekurencyjne **niepowodzenia nie rzuca wcale**: jest pracą
 *    kawałkową, a powód niesie w swoim stanie (`RemovalState`).
 */
final class EntryOperations
{
    /**
     * Ile wpisów policzyć w jednym takcie.
     *
     * Liczby wzięte z budżetu klatki (33 ms przy 30 kl./s), nie z okrągłości:
     * `scandir()` jest tani, więc liczenie bierze więcej, a `unlink()` bywa
     * kilkudziesięciomikrosekundowy, więc usuwanie mniej — 256 wywołań to
     * kilka milisekund, czyli ułamek taktu.
     */
    private const SCAN_PER_TICK = 512;

    private const DELETE_PER_TICK = 256;

    /**
     * Nazwa, na której ma stanąć kursor po skończonym usuwaniu — policzona
     * **przed** czynnością, bo potem tego wpisu już nie ma.
     *
     * Pole, a nie parametr wleczony przez cztery metody: praca jest jedna naraz
     * (reguła 11d), więc jest to stan tej jednej pracy — jak `successor` w liście,
     * na którego kursor ma spaść.
     */
    private ?string $successor = null;

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly FileOperationsPort $operations,
        private readonly ReloadDirectoryUseCase $reload,
        private readonly OpenStartingDirectoryUseCase $fallback,
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** `F6` — okno z nazwą bieżącą jako treścią początkową. */
    public function renamePrompt(): ScreenOutcome
    {
        $selection = $this->selection();

        if ($selection === null) {
            return ScreenOutcome::stay($this->info('module.browser.problem.noSelection'));
        }

        [, $entry] = $selection;

        return ScreenOutcome::opens(new PromptOverlay(
            'module.browser.rename.title',
            ['name' => $entry->name],
            $entry->name,
            fn (string $value): ?Message => $this->rename($value),
            $this->translator,
        ));
    }

    /**
     * `F7` — okno z pustą treścią.
     *
     * Katalog powstaje w **katalogu panelu**, także wtedy, gdy panel pokazuje
     * drzewo i kursor stoi kilka poziomów niżej: „nowy katalog” dotyczy miejsca,
     * na które użytkownik patrzy jako na swoje bieżące, a nie węzła pod kursorem.
     *
     * Ścieżki w tytule **nie ma** i to jest poprawka po obejrzeniu okna
     * w prawdziwym terminalu: katalog panelu czynnego stoi w górnym pasie klatki,
     * więc tytuł powtarzałby to, co widać — a przy długiej ścieżce rozdmuchiwał
     * okno na całą szerokość.
     */
    public function directoryPrompt(): ScreenOutcome
    {
        return ScreenOutcome::opens(new PromptOverlay(
            'module.browser.mkdir.title',
            [],
            '',
            fn (string $value): Message => $this->createDirectory($value),
            $this->translator,
        ));
    }

    /**
     * Zmiana nazwy wpisu pod kursorem — wejście dla klawisza i dla komendy.
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\InvalidEntryNameException
     * @throws FileOperationException
     * @throws DirectoryNotReadableException
     */
    public function rename(string $value): ?Message
    {
        $selection = $this->selection();

        if ($selection === null) {
            return $this->info('module.browser.problem.noSelection');
        }

        [$directory, $entry] = $selection;
        $name = new EntryName($value);

        // Nazwa ta sama co dotąd nie jest błędem, tylko brakiem zmiany: `rename()`
        // na siebie samego udałby się i zostawiłby po sobie zdanie o czynności,
        // której nie było.
        if ($name->value === $entry->name) {
            return null;
        }

        $this->operations->rename($directory->path()->child($entry->name)->value, $name->value);
        $this->refresh($directory->path(), $name->value);

        return $this->info('module.browser.rename.done', ['name' => $name->value]);
    }

    /**
     * Nowy katalog w katalogu panelu czynnego — wejście dla klawisza i dla komendy.
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\InvalidEntryNameException
     * @throws FileOperationException
     * @throws DirectoryNotReadableException
     */
    public function createDirectory(string $value): Message
    {
        $name = new EntryName($value);
        $path = $this->panes->focused()->directory()->path();

        $this->operations->createDirectory($path->child($name->value)->value);
        $this->refresh($path, $name->value);

        return $this->info('module.browser.mkdir.done', ['name' => $name->value]);
    }

    /**
     * `F8` albo `Delete` — usunięcie wpisu pod kursorem.
     *
     * Plik idzie **krótszą drogą**: pytanie i jedno wywołanie. Katalog wymaga
     * policzenia zawartości, bo pytanie ma podać liczbę wpisów, które znikną —
     * a policzenie bywa dłuższe od klatki (D75, rozstrzygnięcia 4 i 10).
     */
    public function deleteRequest(): ScreenOutcome
    {
        $selection = $this->selection();

        if ($selection === null) {
            return ScreenOutcome::stay($this->info('module.browser.problem.noSelection'));
        }

        [$directory, $entry] = $selection;
        $this->successor = self::successorOf($directory, $entry->name);

        if (!$entry->isDirectory()) {
            if (!$this->asks()) {
                return ScreenOutcome::stay($this->deleteOne($directory, $entry->name));
            }

            return ScreenOutcome::opens(new ConfirmOverlay(
                'module.browser.delete.confirm.file',
                ['name' => $entry->name],
                fn (): OverlayOutcome => OverlayOutcome::close($this->deleteOne($directory, $entry->name)),
                $this->translator,
                true,
            ));
        }

        return $this->counted($directory, $entry->name);
    }

    /**
     * Liczenie zawartości katalogu — z oknem albo bez.
     *
     * Pierwszy kawałek idzie **od razu**: katalog o kilku wpisach policzy się
     * w całości, a wtedy okno liczenia nie ma czego pokazać i nie powstaje wcale.
     * Reguła wynika z rozstrzygnięcia nr 10 („plik i pusty katalog bez okien
     * pracy”) rozciągniętego na przypadek, którego z góry rozpoznać nie sposób.
     */
    private function counted(Directory $directory, string $name): ScreenOutcome
    {
        $state = $this->operations->beginRemoval($directory->path()->child($name)->value);

        if ($state->stage === RemovalStage::Scanning) {
            $state = $this->operations->advanceRemoval(self::SCAN_PER_TICK);
        }

        if ($state->stage === RemovalStage::Scanning) {
            return ScreenOutcome::opens($this->countingOverlay($directory, $name, $state));
        }

        [$overlay, $message] = $this->afterCounting($directory, $name, $state);

        return $overlay === null ? ScreenOutcome::stay($message) : ScreenOutcome::opens($overlay);
    }

    /** Okno liczenia: sama nazwa wpisu dokładanego do listy, bez paska (nie ma z czego). */
    private function countingOverlay(Directory $directory, string $name, RemovalState $state): ProgressOverlay
    {
        return new ProgressOverlay(
            'module.browser.delete.counting',
            ['name' => $name],
            $state->progress(),
            fn (): WorkProgress => $this->operations->advanceRemoval(self::SCAN_PER_TICK)->progress(),
            function () use ($directory, $name): OverlayOutcome {
                [$overlay, $message] = $this->afterCounting($directory, $name, $this->operations->removalState());

                return $overlay === null
                    ? OverlayOutcome::close($message)
                    : OverlayOutcome::replace($overlay, $message);
            },
            function (): Message {
                // Przerwane liczenie nie dotknęło dysku i to jest cała treść
                // zdania: użytkownik ma wiedzieć, że nic się nie stało.
                $this->operations->stopRemoval();

                return $this->info('module.browser.delete.abandoned');
            },
            $this->translator,
        );
    }

    /**
     * Co po policzeniu: pytanie z liczbą, od razu usuwanie (gdy pytać nie trzeba)
     * albo zdanie o niepowodzeniu.
     *
     * @return array{?OverlayInterface, ?Message} okno, które ma stanąć, albo samo zdanie
     */
    private function afterCounting(Directory $directory, string $name, RemovalState $state): array
    {
        if ($state->stage !== RemovalStage::Ready) {
            $failure = $state->stage === RemovalStage::Failed ? $this->reason($state) : null;
            $this->operations->stopRemoval();

            return [null, $failure];
        }

        if (!$this->asks()) {
            return $this->started($directory, $name);
        }

        $total = $state->total ?? 1;

        return [new ConfirmOverlay(
            // Jeden wpis do usunięcia znaczy „sam katalog” — pusty albo dowiązanie.
            // Pytanie o zawartość byłoby wtedy pytaniem o coś, czego nie ma.
            $total > 1 ? 'module.browser.delete.confirm.tree' : 'module.browser.delete.confirm.file',
            ['name' => $name, 'count' => $this->translator->number((float) $total)],
            function () use ($directory, $name): OverlayOutcome {
                [$overlay, $message] = $this->started($directory, $name);

                return $overlay === null
                    ? OverlayOutcome::close($message)
                    : OverlayOutcome::replace($overlay, $message);
            },
            $this->translator,
            true,
            function (): void {
                // Odmowa porzuca policzoną listę — przy drzewie o stu tysiącach
                // wpisów jest to megabajt pamięci trzymany bez powodu.
                $this->operations->stopRemoval();
            },
        ), null];
    }

    /**
     * Zgoda: praca przechodzi do usuwania — z oknem postępu albo bez, tą samą
     * regułą pierwszego kawałka, co liczenie.
     *
     * @return array{?OverlayInterface, ?Message}
     */
    private function started(Directory $directory, string $name): array
    {
        $state = $this->operations->confirmRemoval();

        if ($state->stage === RemovalStage::Deleting) {
            $state = $this->operations->advanceRemoval(self::DELETE_PER_TICK);
        }

        if ($state->stage === RemovalStage::Deleting) {
            return [$this->deletingOverlay($directory, $name, $state), null];
        }

        return [null, $this->finished($directory, $state)];
    }

    /** Okno usuwania: nazwa, licznik „N z M” w pasku i sam pasek. */
    private function deletingOverlay(Directory $directory, string $name, RemovalState $state): ProgressOverlay
    {
        return new ProgressOverlay(
            'module.browser.delete.deleting',
            ['name' => $name],
            $state->progress(),
            fn (): WorkProgress => $this->operations->advanceRemoval(self::DELETE_PER_TICK)->progress(),
            fn (): OverlayOutcome => OverlayOutcome::close(
                $this->finished($directory, $this->operations->removalState()),
            ),
            fn (): Message => $this->stopped($directory),
            $this->translator,
        );
    }

    /** Koniec pracy: zdanie o skutku i odświeżenie paneli. */
    private function finished(Directory $directory, RemovalState $state): Message
    {
        $message = $state->stage === RemovalStage::Failed
            ? $this->reason($state)
            : Message::info($this->translator->plural('module.browser.delete.done', $state->done));

        $this->operations->stopRemoval();
        $this->refreshAfterRemoval($directory);

        return $message;
    }

    /**
     * `Esc` w trakcie usuwania: praca staje na najbliższym kawałku, a zdanie mówi
     * **ile z ilu** zniknęło.
     *
     * To jest jedyne miejsce, w którym cel kroku („operacja dzieje się w całości
     * albo wcale”) ma zapisany wyjątek — usunięcia połowy drzewa nie da się
     * cofnąć, więc pozostaje powiedzieć prawdę (D75, rozstrzygnięcie 13).
     */
    private function stopped(Directory $directory): Message
    {
        $state = $this->operations->removalState();
        $this->operations->stopRemoval();
        $this->refreshAfterRemoval($directory);

        return Message::info($this->translator->plural(
            'module.browser.delete.stopped',
            $state->done,
            ['total' => $this->translator->number((float) ($state->total ?? 0))],
        ));
    }

    private function refreshAfterRemoval(Directory $directory): void
    {
        $successor = $this->successor;
        $this->successor = null;

        $this->refresh($directory->path(), $successor);
    }

    /** Usunięcie jednego wpisu — pliku, dowiązania albo pustego katalogu. */
    private function deleteOne(Directory $directory, string $name): Message
    {
        $successor = $this->successor;
        $this->successor = null;

        try {
            $this->operations->delete($directory->path()->child($name)->value);
        } catch (FileOperationException $problem) {
            return $this->problem($problem);
        }

        $this->refresh($directory->path(), $successor);

        return $this->info('module.browser.delete.doneOne', ['name' => $name]);
    }

    /**
     * Odświeżenie po zmianie na dysku — **obu paneli**, jeśli oba jej dotyczą.
     *
     * Panel odświeża się w dwóch przypadkach: patrzy dokładnie na zmieniony
     * katalog albo leży **w środku** niego. Drugi jest tym, o którym łatwo
     * zapomnieć: usunięcie katalogu wyciąga panelowi ziemię pod nogami, a wtedy
     * ponowny odczyt się nie udaje i panel wchodzi do najbliższego czytelnego
     * wyżej — tą samą drogą, którą aplikacja otwiera katalog startowy.
     *
     * Drzewa tracą zapamiętane gałęzie bezwarunkowo: zmiana mogła dotyczyć
     * dowolnego poziomu, a gałęzie wracają po jednej na takt (D46), więc
     * zapomnienie ich kosztuje najwyżej kilka odczytów rozwiniętych węzłów.
     */
    private function refresh(DirectoryPath $changed, ?string $select): void
    {
        foreach ($this->panes->all() as $pane) {
            $path = $pane->directory()->path();

            if ($path->equals($changed)) {
                $this->reloadPane($pane, $select);

                continue;
            }

            if (self::liesInside($path, $changed)) {
                $this->reloadPane($pane, null);
            }
        }

        $this->panes->forgetBranches();
    }

    private function reloadPane(BrowserState $pane, ?string $select): void
    {
        $hidden = $pane->showsHiddenEntries();

        try {
            $pane->refresh($this->reload->execute($pane->directory(), $hidden, $select), $select);
        } catch (DirectoryNotReadableException) {
            $pane->enter($this->fallback->execute($pane->directory()->path(), $hidden));
        }
    }

    /** Czy ścieżka leży wewnątrz katalogu — rachunek tekstowy, bez pytania dysku. */
    private static function liesInside(DirectoryPath $path, DirectoryPath $root): bool
    {
        return str_starts_with($path->value, $root->isRoot() ? '/' : $root->value . '/');
    }

    /**
     * Wpis, na który ma spaść kursor po usunięciu wskazanego: następny na liście,
     * a gdy usuwamy ostatni — poprzedni. `null` znaczy „lista zostanie pusta”.
     */
    private static function successorOf(Directory $directory, string $name): ?string
    {
        $entries = $directory->entries();

        foreach ($entries as $index => $entry) {
            if ($entry->name !== $name) {
                continue;
            }

            return $entries[$index + 1]->name ?? $entries[$index - 1]->name ?? null;
        }

        return null;
    }

    /**
     * Katalog i wpis, na który wskazuje panel czynny — z listy albo z drzewa.
     *
     * @return ?array{Directory, Entry}
     */
    private function selection(): ?array
    {
        $directory = $this->panes->focusedDirectory();
        $entry = $directory->selectedEntry();

        return $entry === null ? null : [$directory, $entry];
    }

    private function asks(): bool
    {
        return BrowserSettings::asksBeforeDelete($this->state->settings());
    }

    /** @param array<string, string> $parameters */
    private function info(string $key, array $parameters = []): Message
    {
        return Message::info($this->translator->translate($key, $parameters));
    }

    private function reason(RemovalState $state): Message
    {
        return Message::error($this->translator->translate(
            $state->problemKey ?? 'problem.unexpected',
            $state->problemParameters,
        ));
    }

    /** Zdanie, które wyjątek podaje o sobie sam (`DescribesProblem`, D42). */
    private function problem(DescribesProblem $problem): Message
    {
        return Message::error($this->translator->translate(
            $problem->problemKey(),
            $problem->problemParameters(),
        ));
    }
}
