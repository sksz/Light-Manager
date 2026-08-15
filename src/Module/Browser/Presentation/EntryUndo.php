<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Port\FileOperationsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Port\TrashPort;
use LightManager\Application\Ui\Role;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserEvent;
use LightManager\Module\Browser\Application\BrowserEvents;
use LightManager\Module\Browser\Application\Undo\UndoEntry;
use LightManager\Module\Browser\Application\Undo\UndoJournal;
use LightManager\Module\Browser\Application\Undo\UndoKind;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Presentation\Overlay\UndoOverlay;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Wykonawca cofnięć: droga powrotna każdej operacji odwracalnej (krok 44).
 *
 * Trzeci bliźniak w rodzinie `EntryOperations`/`EntryTransfer` i z tego samego
 * powodu: cofnięcie ma dwa wejścia (`Alt`+`u` bierze najnowsze odwracalne,
 * widok `F3` — wskazane kursorem), a obie drogi muszą prowadzić w to samo.
 *
 * Cztery drogi powrotne i każda inna:
 *
 * - zmiana nazwy cofa się zmianą nazwy — natychmiast;
 * - nowy katalog cofa się usunięciem, **dopóki pozostał pusty** (D81, nr 10) —
 *   port odmawia `notEmpty` i ta odmowa jest właściwą odpowiedzią;
 * - kosz cofa się przywróceniem wpisów — natychmiast, bo przywrócenie jest
 *   zmianą nazwy; przy zbiorze niepowodzenie w środku **wymienia zapis na
 *   pomniejszony** o to, co już wróciło, zamiast go zdjąć;
 * - przeniesienie cofa się przeniesieniem z powrotem — tą samą pracą kawałkową
 *   z oknami, którą jechało (`EntryTransfer::beginRestore()`), bo drzewo mogło
 *   być wielkie w obie strony.
 *
 * **Cofnięcie nieudane mówi dlaczego i nie zdejmuje zapisu** — inaczej
 * użytkownik traci jedyną informację o tym, co się stało. Zdanie o skutku
 * mówi **co** cofnięto, nie „cofnięto”: kursor staje na wpisie przywróconym,
 * bo to on jest odpowiedzią na pytanie „czy się udało”.
 */
final class EntryUndo
{
    public function __construct(
        private readonly FileOperationsPort $operations,
        private readonly TrashPort $trash,
        private readonly EntryTransfer $transfers,
        private readonly PaneRefresh $refresh,
        private readonly TranslatorPort $translator,
        private readonly UndoJournal $journal,
        private readonly BrowserEvents $events,
    ) {
    }

    /** `Alt`+`u` — cofnięcie najnowszej operacji odwracalnej, bez okna. */
    public function undoLatest(): ScreenOutcome
    {
        $index = $this->journal->latestReversibleIndex();

        if ($index === null) {
            return ScreenOutcome::stay($this->info('module.browser.undo.empty'));
        }

        return self::forScreen($this->execute($index));
    }

    /** `F3` — widok stosu; pusty stos to zdanie, nie puste okno. */
    public function viewPrompt(): ScreenOutcome
    {
        if ($this->journal->isEmpty()) {
            return ScreenOutcome::stay($this->info('module.browser.undo.empty'));
        }

        $rows = [];
        $selectable = [];

        foreach ($this->journal->entries() as $entry) {
            $rows[] = new ListRow(
                $this->display($entry),
                '',
                $entry->reversible() ? Role::Text : Role::Muted,
            );
            $selectable[] = $entry->reversible();
        }

        return ScreenOutcome::opens(new UndoOverlay(
            $rows,
            $selectable,
            fn (int $index): OverlayOutcome => $this->execute($index),
            $this->translator,
        ));
    }

    /**
     * Cofnięcie wpisu o tym numerze — wykonalność sprawdza się **tutaj i teraz**,
     * bo między operacją a cofnięciem dysk żył własnym życiem.
     */
    private function execute(int $index): OverlayOutcome
    {
        $entry = $this->journal->at($index);

        if ($entry === null) {
            return OverlayOutcome::close();
        }

        return match ($entry->kind) {
            UndoKind::Rename => $this->announced($this->undoRename($entry)),
            UndoKind::MakeDirectory => $this->announced($this->undoMakeDirectory($entry)),
            UndoKind::Trash => $this->announced($this->undoTrash($entry)),
            // Bez `announced()` i to jest jedyna gałąź, której zdarzenie **nie
            // pada tutaj**: przeniesienie wraca pracą kawałkową, więc mówi o sobie
            // dopiero wtedy, gdy się skończy — a mówi za nie `EntryTransfer`,
            // który jako jedyny wie, czy dojechała.
            UndoKind::Move => $this->undoMove($entry),
            // Nieosiągalne z widoku (pozycja niewybieralna) i z klawisza
            // (najnowsze **odwracalne**) — ale spis odwracalnych mieszka
            // w `reversible()`, a nie w założeniu, że nikt tu nie trafi.
            UndoKind::Copy, UndoKind::PermanentDelete => OverlayOutcome::close(
                $this->info('module.browser.undo.irreversible'),
            ),
        };
    }

    private function undoRename(UndoEntry $entry): OverlayOutcome
    {
        $directory = new DirectoryPath($entry->directory);
        $from = $entry->from ?? '';

        try {
            $this->operations->rename($directory->child($entry->names[0])->value, $from);
        } catch (FileOperationException $problem) {
            return OverlayOutcome::close($this->problem($problem));
        }

        $this->journal->drop($entry);
        $this->refresh->after($directory, $from);

        return OverlayOutcome::close($this->info('module.browser.undo.done.rename', ['name' => $from]));
    }

    private function undoMakeDirectory(UndoEntry $entry): OverlayOutcome
    {
        $directory = new DirectoryPath($entry->directory);
        $name = $entry->names[0];

        try {
            $this->operations->delete($directory->child($name)->value);
        } catch (FileOperationException $problem) {
            // Najczęściej `notEmpty`: do katalogu coś przybyło, więc cofnięcie
            // usuwałoby rzeczy, których tamta operacja nie stworzyła.
            return OverlayOutcome::close($this->problem($problem));
        }

        $this->journal->drop($entry);
        $this->refresh->after($directory);

        return OverlayOutcome::close($this->info('module.browser.undo.done.mkdir', ['name' => $name]));
    }

    private function undoTrash(UndoEntry $entry): OverlayOutcome
    {
        $directory = new DirectoryPath($entry->directory);
        $trashDirectory = $entry->trashDirectory ?? $this->trash->defaultDirectory();
        $remaining = $entry->trashNames;
        $restored = 0;
        $first = null;
        $problem = null;

        foreach ($entry->trashNames as $name => $trashName) {
            try {
                $this->trash->restore($trashName, $trashDirectory);
            } catch (FileOperationException $failure) {
                $problem = $failure;

                break;
            }

            unset($remaining[$name]);
            $first ??= $name;
            ++$restored;
        }

        if ($problem !== null) {
            // Zapis zostaje — pomniejszony o to, co już wróciło, żeby nie
            // obiecywał ponownego przywrócenia rzeczy przywróconych.
            if ($restored > 0) {
                $this->journal->replace($entry, $entry->withTrashNames($remaining));
                $this->refresh->after($directory, $first);
            }

            return OverlayOutcome::close($this->problem($problem));
        }

        $this->journal->drop($entry);
        $this->refresh->after($directory, $first);

        return OverlayOutcome::close($restored === 1
            ? $this->info('module.browser.undo.done.trashOne', ['name' => $first ?? ''])
            : Message::info($this->translator->plural('module.browser.undo.done.trash', $restored)));
    }

    /** Przeniesienie wraca pracą kawałkową — z liczeniem, postępem i kolizjami. */
    private function undoMove(UndoEntry $entry): OverlayOutcome
    {
        return $this->transfers->beginRestore(
            new DirectoryPath($entry->directory),
            new DirectoryPath($entry->from ?? $entry->directory),
            $entry->names,
            function () use ($entry): void {
                $this->journal->drop($entry);
            },
        );
    }

    /** Wiersz widoku: zdanie o operacji, z liczbą przy zbiorze. */
    private function display(UndoEntry $entry): string
    {
        $count = count($entry->names);

        return match ($entry->kind) {
            UndoKind::Rename => $this->translator->translate('module.browser.undo.entry.rename', [
                'from' => $entry->from ?? '',
                'to' => $entry->names[0],
            ]),
            UndoKind::MakeDirectory => $this->translator->translate('module.browser.undo.entry.mkdir', [
                'name' => $entry->names[0],
            ]),
            UndoKind::Trash => $count === 1
                ? $this->translator->translate('module.browser.undo.entry.trash', ['name' => $entry->names[0]])
                : $this->translator->plural('module.browser.undo.entry.trash.many', $count),
            UndoKind::Move => $count === 1
                ? $this->translator->translate('module.browser.undo.entry.move', ['name' => $entry->names[0]])
                : $this->translator->plural('module.browser.undo.entry.move.many', $count),
            UndoKind::Copy => $count === 1
                ? $this->translator->translate('module.browser.undo.entry.copy', ['name' => $entry->names[0]])
                : $this->translator->plural('module.browser.undo.entry.copy.many', $count),
            UndoKind::PermanentDelete => $count === 1
                ? $this->translator->translate('module.browser.undo.entry.delete', ['name' => $entry->names[0]])
                : $this->translator->plural('module.browser.undo.entry.delete.many', $count),
        };
    }

    /**
     * Skutek cofnięcia ogłoszony reszcie aplikacji (krok 46).
     *
     * Pyta o **ton zdania**, które zostało po czynności, bo drugiej odpowiedzi na
     * to samo pytanie nie ma: cofnięcie nieudane mówi dlaczego, udane mówi co
     * wróciło, a nic poza tym stąd nie wychodzi.
     */
    private function announced(OverlayOutcome $outcome): OverlayOutcome
    {
        $this->events->outcome(BrowserEvent::UndoDone, BrowserEvent::UndoFailed, $outcome->message);

        return $outcome;
    }

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

    private function problem(FileOperationException $problem): Message
    {
        return Message::error($this->translator->translate(
            $problem->problemKey(),
            $problem->problemParameters(),
        ));
    }
}
