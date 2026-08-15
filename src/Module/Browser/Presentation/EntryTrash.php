<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\TransferStage;
use LightManager\Application\Dto\TransferState;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\FileTransferPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Port\TrashPort;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\Undo\UndoEntry;
use LightManager\Module\Browser\Application\Undo\UndoJournal;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Presentation\Component\EntrySize;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Usunięcie z rozdrożem: do kosza albo trwale (krok 44, D81).
 *
 * Klasa jest **punktem wejścia obu dróg** — klawisz i komenda pytają ją, a ona
 * rozstrzyga wedle ustawienia i modyfikatora: `F8`/`Delete` robi to, co mówi
 * pozycja „usuwaj do kosza” (domyślnie: kosz), `Shift`+`F8`/`Shift`+`Delete` —
 * zawsze to drugie (rozstrzygnięcia 1 i 2). Droga trwała prowadzi przez
 * `EntryOperations` nietkniętą; tu mieszka wyłącznie kosz.
 *
 * Kosz jest **zmianą nazwy, nigdy kopiowaniem** (rozstrzygnięcie 4) — dopóki
 * wpis leży na tym samym systemie plików, co kosz. Wpis, który nie leży,
 * dostaje ostrzeżenie i pytanie o trzech odpowiedziach (rozstrzygnięcie 5):
 * skopiować do kosza pracą kawałkową z kroku 42, usunąć trwale (pytanie groźne
 * pada wtedy osobno — zgoda na kosz nie jest zgodą na rzecz nieodwracalną),
 * albo przerwać. Pytanie obejmuje **całą operację**: przy zbiorze zmieszanym
 * nic nie rusza się przed odpowiedzią, bo operacja dzieje się w całości albo
 * wcale.
 *
 * Droga kopiowania honoruje regułę kosza „plik informacyjny przed
 * przeniesieniem”: nazwy rezerwuje się w koszu **przed** startem pracy
 * (`TrashPort::reserve()`), a praca dostaje je mapą `targetNames` — bez niej
 * wpis o zajętej nazwie wtopiłby się w cudzy, bo kolizja katalogów jest w tej
 * pracy scaleniem. Praca przerwana zostawia w koszu to, co zdążyło dojechać
 * (z poprawnym plikiem informacyjnym — wpis częściowy też da się przywrócić),
 * a rezerwacje bez wpisu sprząta `releaseUnused()`.
 */
final class EntryTrash
{
    /** Budżety taktu — te same, co w `EntryTransfer`, bo praca jest ta sama. */
    private const SCAN_PER_TICK = 512;

    private const BYTES_PER_TICK = 4 * 1024 * 1024;

    /** Katalog operacji — na czas trwającej pracy kopiowania do kosza. */
    private ?Directory $directory = null;

    private ?string $successor = null;

    /** @var array<string, string> nazwa wpisu → nazwa w koszu; rośnie w miarę pracy */
    private array $trashNames = [];

    /** @var array<string, string> nazwa zarezerwowana w koszu → ścieżka źródła */
    private array $pending = [];

    private string $trashDirectory = '';

    private int $count = 1;

    private string $firstName = '';

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly EntryOperations $operations,
        private readonly TrashPort $trash,
        private readonly FileTransferPort $transfers,
        private readonly PaneRefresh $refresh,
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly UndoJournal $journal,
    ) {
    }

    /** `F8`/`Delete` — droga wedle ustawienia; `$other` odwraca ją (`Shift`). */
    public function deletePrompt(bool $other = false): ScreenOutcome
    {
        return self::forScreen($this->deleteRequest(null, $other));
    }

    /**
     * To samo dla komendy `browser.delete [nazwa]` — komenda idzie drogą
     * klawisza domyślnego, bo modyfikator nie ma w wierszu komend postaci.
     */
    public function deleteRequest(?string $name = null, bool $other = false): OverlayOutcome
    {
        $toTrash = BrowserSettings::deleteToTrash($this->state->settings()) !== $other;

        if (!$toTrash) {
            return $this->operations->deleteRequest($name);
        }

        if ($name !== null) {
            $target = $this->entryNamed($name);

            return $target === null
                ? OverlayOutcome::close(Message::error($this->translator->translate(
                    'module.browser.problem.noEntry',
                    ['name' => $name],
                )))
                : $this->trashRequest($target, [$name]);
        }

        $operands = $this->panes->focusedOperands();

        if ($operands === null) {
            return OverlayOutcome::close($this->info('module.browser.problem.noSelection'));
        }

        [$directory, $names] = $operands;

        return $this->trashRequest($directory, $names);
    }

    /**
     * Rozdroże kosza: wpisy spoza systemu plików kosza dostają pytanie
     * o trzech odpowiedziach, reszta — pytanie zwykłe albo od razu drogę.
     *
     * Pytanie zwykłe podlega ustawieniu „pytaj przed usunięciem” z kroku 41:
     * od tego kroku rządzi ono **czynnością odwracalną**, bo trwała pyta
     * zawsze (plan kroku, punkt 2).
     *
     * @param list<string> $names nigdy pusta
     */
    private function trashRequest(Directory $directory, array $names): OverlayOutcome
    {
        $trashDirectory = $this->resolvedTrashDirectory();
        $foreign = [];

        foreach ($names as $name) {
            if (!$this->trash->accepts($directory->path()->child($name)->value, $trashDirectory)) {
                $foreign[] = $name;
            }
        }

        if ($foreign !== []) {
            return $this->foreignQuestion($directory, $names, $foreign, $trashDirectory);
        }

        if (!BrowserSettings::asksBeforeDelete($this->state->settings())) {
            return OverlayOutcome::close($this->trashNow($directory, $names, $trashDirectory));
        }

        [$key, $parameters, $count] = $this->question($names);

        return OverlayOutcome::replace(new ConfirmOverlay(
            $key,
            $parameters,
            fn (): OverlayOutcome => OverlayOutcome::close($this->trashNow($directory, $names, $trashDirectory)),
            $this->translator,
            false,
            null,
            $count,
        ));
    }

    /**
     * Pytanie przed koszem: nazwa przy jednym wpisie, liczba przy zbiorze —
     * ten sam podział, co przy usuwaniu trwałym (krok 43).
     *
     * @param list<string> $names
     *
     * @return array{string, array<string, string>, ?int}
     */
    private function question(array $names): array
    {
        if (count($names) === 1) {
            return ['module.browser.trash.confirm.file', ['name' => $names[0]], null];
        }

        return [
            'module.browser.trash.confirm.many',
            ['count' => $this->translator->number((float) count($names))],
            count($names),
        ];
    }

    /**
     * Ostrzeżenie i pytanie o trzech odpowiedziach (rozstrzygnięcie 5).
     *
     * `abort` stoi ostatni, bo `ChoiceOverlay` znaczy `Esc` jako odpowiedź
     * ostatnią — wycofanie się jest jedyną, która ma prawo paść przez
     * przypadek. Odpowiedź „usuń trwale” prowadzi w drogę z kroku 41 wraz
     * z jej groźnym pytaniem: zdanie tutaj mówi o koszu, a nie o zgodzie na
     * czynność nieodwracalną.
     *
     * @param list<string> $names   pełna lista operacji
     * @param list<string> $foreign wpisy spoza systemu plików kosza
     */
    private function foreignQuestion(
        Directory $directory,
        array $names,
        array $foreign,
        string $trashDirectory,
    ): OverlayOutcome {
        [$key, $parameters] = count($foreign) === 1
            ? ['module.browser.trash.foreign', ['name' => $foreign[0]]]
            : ['module.browser.trash.foreign.many', ['count' => $this->translator->number((float) count($foreign))]];

        return OverlayOutcome::replace(new ChoiceOverlay(
            $key,
            $parameters,
            [
                'copy' => 'module.browser.trash.foreign.copy',
                'delete' => 'module.browser.trash.foreign.delete',
                'abort' => 'module.browser.trash.foreign.abort',
            ],
            fn (string $id): OverlayOutcome => match ($id) {
                'copy' => $this->copyToTrash($directory, $names, $trashDirectory),
                'delete' => $this->operations->deleteNamed($directory, $names),
                default => OverlayOutcome::close($this->info('module.browser.trash.abandoned')),
            },
            $this->translator,
        ));
    }

    /**
     * Kosz zmianą nazwy — cała droga w jednej klatce, bo `rename()` nie zależy
     * od rozmiaru wpisu. Katalog z zawartością jedzie w całości, więc liczenia
     * ani okien pracy tu nie ma — i to jest główny zysk kosza nad usuwaniem.
     *
     * Niepowodzenie w środku zbioru zatrzymuje drogę, ale **nie cofa** tego, co
     * zdążyło przejechać: wpisy w koszu zostają w zapisie cofnięcia, a zdanie
     * mówi, na czym stanęło.
     *
     * @param list<string> $names
     */
    private function trashNow(Directory $directory, array $names, string $trashDirectory): Message
    {
        $successor = EntryOperations::successorOf($directory, $names);
        $moved = [];
        $problem = null;

        foreach ($names as $name) {
            try {
                $moved[$name] = $this->trash->moveToTrash(
                    $directory->path()->child($name)->value,
                    $trashDirectory,
                );
            } catch (FileOperationException $failure) {
                $problem = $failure;

                break;
            }
        }

        if ($moved !== []) {
            $this->journal->record(UndoEntry::trashed($directory->path()->value, $moved, $trashDirectory));
            $this->refresh->after($directory->path(), $problem === null ? $successor : null);
        }

        if ($problem !== null) {
            return Message::error($this->translator->translate(
                $problem->problemKey(),
                $problem->problemParameters(),
            ));
        }

        return count($names) === 1
            ? $this->info('module.browser.trash.doneOne', ['name' => $names[0]])
            : Message::info($this->translator->plural('module.browser.trash.done', count($names)));
    }

    /**
     * Kosz kopiowaniem — dla całej operacji, gdy choć jeden wpis leży na innym
     * systemie plików. Wpisy z systemu kosza i tak jadą zmianą nazwy (są za
     * darmo); reszta pracą kawałkową z kroku 42, pod nazwami zarezerwowanymi
     * **przed** pierwszym bajtem.
     *
     * @param list<string> $names
     */
    private function copyToTrash(Directory $directory, array $names, string $trashDirectory): OverlayOutcome
    {
        $this->directory = $directory;
        $this->successor = EntryOperations::successorOf($directory, $names);
        $this->trashDirectory = $trashDirectory;
        $this->trashNames = [];
        $this->pending = [];
        $this->count = count($names);
        $this->firstName = $names[0];

        $sources = [];
        $targetNames = [];

        try {
            foreach ($names as $name) {
                $path = $directory->path()->child($name)->value;

                if ($this->trash->accepts($path, $trashDirectory)) {
                    $this->trashNames[$name] = $this->trash->moveToTrash($path, $trashDirectory);

                    continue;
                }

                $reserved = $this->trash->reserve($path, $trashDirectory);
                $this->pending[$reserved] = $path;
                $sources[] = $path;
                $targetNames[$path] = $reserved;
            }
        } catch (FileOperationException $problem) {
            return OverlayOutcome::close($this->finishCopy(null, $problem));
        }

        if ($sources === []) {
            // Wszystko weszło zmianą nazwy — system plików zdążył się zgodzić
            // między pytaniem a odpowiedzią. Praca kawałkowa nie ma tu nic do
            // roboty.
            return OverlayOutcome::close($this->finishCopy(null, null));
        }

        $state = $this->transfers->begin($sources, $trashDirectory . '/files', true, $targetNames);

        if ($state->stage === TransferStage::Scanning) {
            $state = $this->transfers->advance(self::SCAN_PER_TICK);
        }

        if ($state->stage === TransferStage::Scanning) {
            return OverlayOutcome::replace($this->countingOverlay($state));
        }

        return $this->afterStep($state);
    }

    /** Co po kawałku pracy — bliźniak rachunku z `EntryTransfer`, o dwóch różnicach: kolizja rozstrzyga się sama, a koniec sprząta rezerwacje. */
    private function afterStep(TransferState $state): OverlayOutcome
    {
        if ($state->stage === TransferStage::Working) {
            $state = $this->transfers->advance(self::BYTES_PER_TICK);
        }

        if ($state->stage === TransferStage::Working) {
            return OverlayOutcome::replace($this->workingOverlay($state));
        }

        if ($state->stage === TransferStage::Colliding) {
            return $this->afterStep($this->resolveCollision($state));
        }

        return OverlayOutcome::close($this->finishCopy($state, null));
    }

    /**
     * Kolizja pod nazwą zarezerwowaną — możliwa wyłącznie wtedy, gdy coś obce
     * stanęło w `files/` między rezerwacją a kopiowaniem, z pominięciem plików
     * informacyjnych (spoza specyfikacji). Odpowiedź jest automatyczna, bo
     * użytkownik nie ma tu czego rozstrzygać: wpis dostaje świeżą rezerwację
     * i jedzie dalej, a osieroconą sprzątnie `releaseUnused()`.
     */
    private function resolveCollision(TransferState $state): TransferState
    {
        $path = $this->pending[$state->current] ?? null;

        if ($path === null) {
            // Kolizja w głębi drzewa nie ma prawa się zdarzyć (katalog docelowy
            // powstał przed chwilą); pominięcie jest najbezpieczniejszą odpowiedzią
            // na stan, którego nie umiemy nazwać.
            return $this->transfers->resolve(TransferChoice::Skip);
        }

        try {
            $fresh = $this->trash->reserve($path, $this->trashDirectory);
        } catch (FileOperationException) {
            return $this->transfers->resolve(TransferChoice::Skip);
        }

        unset($this->pending[$state->current]);
        $this->pending[$fresh] = $path;

        return $this->transfers->resolve(TransferChoice::Rename, $fresh);
    }

    /**
     * Koniec drogi kopiowania: rezerwacje bez wpisu znikają, wpisy dojechane
     * (także częściowo — z poprawnym plikiem informacyjnym) wchodzą do zapisu
     * cofnięcia, a zdanie mówi prawdę o tym, ile przejechało.
     */
    private function finishCopy(?TransferState $state, ?FileOperationException $problem): Message
    {
        $directory = $this->directory;
        $successor = $this->successor;
        $arrived = $this->trash->releaseUnused(array_keys($this->pending), $this->trashDirectory);

        foreach ($arrived as $reserved) {
            $path = $this->pending[$reserved] ?? null;

            if ($path !== null) {
                $this->trashNames[basename($path)] = $reserved;
            }
        }

        if ($this->trashNames !== [] && $directory !== null) {
            $this->journal->record(UndoEntry::trashed(
                $directory->path()->value,
                $this->trashNames,
                $this->trashDirectory,
            ));
        }

        $count = $this->count;
        $first = $this->firstName;
        $moved = count($this->trashNames);
        $fullyDone = $problem === null
            && ($state === null || ($state->stage !== TransferStage::Failed && !$state->wasStoppedEarly()));

        $this->transfers->stop();
        $this->directory = null;
        $this->successor = null;
        $this->trashNames = [];
        $this->pending = [];
        $this->trashDirectory = '';
        $this->count = 1;
        $this->firstName = '';

        if ($directory !== null && ($moved > 0 || $fullyDone)) {
            $this->refresh->after($directory->path(), $fullyDone ? $successor : null);
        }

        if ($problem !== null) {
            return Message::error($this->translator->translate(
                $problem->problemKey(),
                $problem->problemParameters(),
            ));
        }

        if ($state !== null && $state->stage === TransferStage::Failed) {
            return Message::error($this->translator->translate(
                $state->problemKey ?? 'problem.unexpected',
                $state->problemParameters,
            ));
        }

        if (!$fullyDone) {
            return Message::info($this->translator->plural(
                'module.browser.trash.stopped',
                $moved,
                ['total' => $this->translator->number((float) $count)],
            ));
        }

        return $count === 1
            ? $this->info('module.browser.trash.doneOne', ['name' => $first])
            : Message::info($this->translator->plural('module.browser.trash.done', $count));
    }

    /** Okno liczenia — jak w `EntryTransfer`, z tytułem mówiącym o koszu. */
    private function countingOverlay(TransferState $state): ProgressOverlay
    {
        [$key, $parameters] = $this->workTitle('module.browser.trash.counting');

        return new ProgressOverlay(
            $key,
            $parameters,
            $this->progress($state),
            fn (): WorkProgress => $this->progress($this->transfers->advance(self::SCAN_PER_TICK)),
            fn (): OverlayOutcome => $this->afterStep($this->transfers->state()),
            fn (): Message => $this->finishCopy($this->transfers->state(), null),
            $this->translator,
        );
    }

    /** Okno pracy: nazwa wpisu, licznik w bajtach i pasek. */
    private function workingOverlay(TransferState $state): ProgressOverlay
    {
        [$key, $parameters] = $this->workTitle('module.browser.trash.progress');

        return new ProgressOverlay(
            $key,
            $parameters,
            $this->progress($state),
            fn (): WorkProgress => $this->progress($this->transfers->advance(self::BYTES_PER_TICK)),
            fn (): OverlayOutcome => $this->afterStep($this->transfers->state()),
            fn (): Message => $this->finishCopy($this->transfers->state(), null),
            $this->translator,
        );
    }

    /** @return array{string, array<string, string>} */
    private function workTitle(string $key): array
    {
        if ($this->count === 1) {
            return [$key, ['name' => $this->firstName]];
        }

        return [$key . '.many', ['count' => $this->translator->number((float) $this->count)]];
    }

    /**
     * Stan pracy w języku okna postępu — z licznikiem w rozmiarach, tym samym
     * co przy kopiowaniu (klucze `module.browser.transfer.counter*`), bo miara
     * jest ta sama i użytkownik nie ma powodu czytać jej inaczej.
     */
    private function progress(TransferState $state): WorkProgress
    {
        $counter = '';

        if ($state->totalBytes !== null) {
            $counter = $this->translator->translate('module.browser.transfer.counter.size', [
                'done' => EntrySize::of($this->translator, $state->doneBytes),
                'total' => EntrySize::of($this->translator, $state->totalBytes),
            ]);
        }

        return new WorkProgress(
            $state->isRunning(),
            $state->current,
            $state->doneBytes,
            $state->totalBytes,
            $counter,
        );
    }

    /** Katalog kosza: pozycja ustawień, a pusta — kosz środowiska graficznego. */
    private function resolvedTrashDirectory(): string
    {
        $configured = BrowserSettings::trashDirectory($this->state->settings());

        return $configured === '' ? $this->trash->defaultDirectory() : $configured;
    }

    /**
     * Wpis o tej nazwie w katalogu panelu czynnego — bliźniak rachunku
     * z `EntryOperations`, bo komenda z argumentem idzie tu inną drogą.
     */
    private function entryNamed(string $name): ?Directory
    {
        $directory = $this->panes->focusedDirectory();

        foreach ($directory->entries() as $entry) {
            if ($entry->name === $name) {
                return $directory;
            }
        }

        return null;
    }

    /** Skutek okna przełożony na skutek ekranu — wzorem `EntryOperations`. */
    private static function forScreen(OverlayOutcome $outcome): ScreenOutcome
    {
        return $outcome->next === null
            ? ScreenOutcome::stay($outcome->message)
            : ScreenOutcome::opens($outcome->next);
    }

    /** @param array<string, string> $parameters */
    private function info(string $key, array $parameters = []): Message
    {
        return Message::info($this->translator->translate($key, $parameters));
    }
}
