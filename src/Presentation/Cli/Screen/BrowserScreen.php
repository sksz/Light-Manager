<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Screen;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\MoveSelectionUseCase;
use LightManager\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Application\UseCase\NavigateUpUseCase;
use LightManager\Application\UseCase\ToggleHiddenEntriesUseCase;
use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Przeglądarka plików — ekran, który aplikacja pokazuje domyślnie.
 *
 * Przejmuje treść dawnego `RenderCurrentFrameUseCase` wraz z formatowaniem
 * wiersza wpisu, a obsługę klawiszy — z gałęzi `inBrowser()` dawnego
 * `InputHandler`. Rozdzielenie ich na dwie warstwy nie miało uzasadnienia:
 * i jedno, i drugie jest wiedzą o tym, jak wygląda i zachowuje się jeden
 * konkretny ekran.
 */
final class BrowserScreen implements ScreenInterface
{
    /** Ile wierszy zapasu zostawić między zaznaczeniem a krawędzią listy. */
    private const SCROLL_MARGIN = 2;

    private const EMPTY_DIRECTORY_KEY = 'browser.empty';

    private const HIDDEN_MARKER_KEY = 'browser.hidden';

    /** Skróty jednostek są międzynarodowe — nie przechodzą przez katalog napisów. */
    private const SIZE_UNITS = ['B', 'kB', 'MB', 'GB', 'TB'];

    private readonly ScrollWindow $window;

    public function __construct(
        private readonly LoopState $state,
        private readonly MoveSelectionUseCase $moveSelection,
        private readonly NavigateIntoDirectoryUseCase $navigateInto,
        private readonly NavigateUpUseCase $navigateUp,
        private readonly ToggleHiddenEntriesUseCase $toggleHidden,
        private readonly ChangeSettingUseCase $changeSetting,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow(self::SCROLL_MARGIN);
    }

    /**
     * Ogłasza modułom, gdzie użytkownik stoi i co ma zaznaczone.
     *
     * Publikuje ekran, a nie stan pętli, bo to ekran wie, co znaczy „bieżące
     * miejsce” — i dlatego w kroku 21, gdy katalog zejdzie do modułu
     * przeglądarki, obowiązek przejdzie razem z nim, bez zmiany kontraktu
     * (D40, P5).
     *
     * `Bootstrap` woła to raz, przy starcie: ekran modułu otwarty pierwszym
     * naciśnięciem skrótu ma zastać kontekst wypełniony, a nie pusty.
     */
    public function publishContext(): void
    {
        $directory = $this->state->directory();
        $entry = $directory->selectedEntry();

        $this->state->publishContext(new ModuleContext(
            $directory->path()->value,
            $entry?->name,
            self::kindOf($entry),
        ));
    }

    private static function kindOf(?Entry $entry): ContextEntryKind
    {
        if ($entry === null) {
            return ContextEntryKind::None;
        }

        return $entry->isDirectory() ? ContextEntryKind::Directory : ContextEntryKind::File;
    }

    public function id(): string
    {
        return 'browser';
    }

    public function labelKey(): string
    {
        return 'layout.zone.files';
    }

    public function usesPreview(): bool
    {
        return true;
    }

    /** Numer zaznaczenia i znacznik wpisów ukrytych — dopisek do ścieżki u góry. */
    public function headerSuffix(): string
    {
        $suffix = '';
        $directory = $this->state->directory();
        $selection = $directory->selection();

        if ($selection !== null) {
            $suffix .= sprintf('  —  %d/%d', $selection->index + 1, count($directory->entries()));
        }

        if ($this->state->showsHiddenEntries()) {
            $suffix .= '  ' . $this->translator->translate(self::HIDDEN_MARKER_KEY);
        }

        return $suffix;
    }

    public function draw(Rect $bounds): array
    {
        $directory = $this->state->directory();

        if ($directory->isEmpty()) {
            return (new Label($this->translator->translate(self::EMPTY_DIRECTORY_KEY)))->draw($bounds);
        }

        $this->window->useContext($directory->path()->value);

        $entries = $directory->entries();
        $selected = $directory->selection()?->index;
        $offset = $this->window->keepVisible($selected, count($entries), $bounds->rows);
        $rows = [];

        foreach (array_slice($entries, $offset, $bounds->rows) as $entry) {
            $rows[] = new ListRow(
                $entry->name . ($entry->isDirectory() ? '/' : ''),
                $entry->isDirectory() ? '' : $this->formatSize($entry->sizeInBytes),
                $entry->isDirectory() ? Role::Accent : Role::Text,
            );
        }

        return (new ListView(
            $rows,
            $selected === null ? null : $selected - $offset,
            $this->window->position(count($entries), min($bounds->rows, count($rows))),
        ))->draw($bounds);
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of([Key::Enter, Key::ArrowRight], 'help.key.open'),
            KeyBinding::of([Key::Backspace, Key::ArrowLeft], 'help.key.up'),
            KeyBinding::character('.', 'help.key.hidden'),
        ];
    }

    /**
     * Każdy klawisz kończy się publikacją kontekstu, bo prawie każdy może go
     * zmienić: ruch zaznaczenia, wejście do katalogu, powrót wyżej i przełączenie
     * wpisów ukrytych. Publikacja jest przypisaniem trzech pól, więc taniej jest
     * ją powtórzyć, niż wyliczać, po którym klawiszu naprawdę zaszła zmiana.
     */
    public function handle(KeyPress $key): ScreenOutcome
    {
        $directory = $this->state->directory();

        $outcome = match (true) {
            $key->key === Key::ArrowUp => $this->moved($directory, up: true),
            $key->key === Key::ArrowDown => $this->moved($directory, up: false),
            $key->key === Key::Enter, $key->key === Key::ArrowRight => $this->open($directory),
            $key->key === Key::Backspace, $key->key === Key::ArrowLeft => $this->goUp($directory),
            $key->key === Key::Character && $key->raw === '.' => $this->toggleHidden(),
            default => ScreenOutcome::stay(),
        };

        $this->publishContext();

        return $outcome;
    }

    private function moved(Directory $directory, bool $up): ScreenOutcome
    {
        $up ? $this->moveSelection->up($directory) : $this->moveSelection->down($directory);

        return ScreenOutcome::stay();
    }

    /**
     * Katalog otwieramy; na pliku `Enter` **nie robi nic**.
     *
     * Do kroku 20 otwierał okno z opisem pliku. Dziś opis należy do modułu
     * `FileInfo` i ma własny skrót (`Ctrl+D`), a `Enter` staje się w całej
     * aplikacji klawiszem **zatwierdzania** (P3): na katalogu wchodzi do środka,
     * w polu tekstowym zatwierdza wartość, w oknie komend uruchamia komendę.
     * Na pliku nie ma czego zatwierdzić — tak samo, jak na pustym katalogu.
     */
    private function open(Directory $directory): ScreenOutcome
    {
        $entered = $this->navigateInto->execute($directory, $this->state->showsHiddenEntries());

        if ($entered !== null) {
            $this->state->enterDirectory($entered);
        }

        return ScreenOutcome::stay();
    }

    private function goUp(Directory $directory): ScreenOutcome
    {
        $parent = $this->navigateUp->execute($directory, $this->state->showsHiddenEntries());

        if ($parent !== null) {
            $this->state->enterDirectory($parent);
        }

        return ScreenOutcome::stay();
    }

    /**
     * Widoczność wpisów ukrytych wymaga ponownego odczytu katalogu — i to
     * **przed** zapisem konfiguracji, nie po nim: nieudany odczyt rzuca wyjątek,
     * więc ustawienie zostaje wtedy takie, jakie było, i lista nie rozjeżdża się
     * z plikiem na dysku.
     */
    private function toggleHidden(): ScreenOutcome
    {
        $this->state->enterDirectory(
            $this->toggleHidden->execute($this->state->directory(), !$this->state->showsHiddenEntries()),
        );

        [$settings, $message] = $this->changeSetting->execute(
            $this->state->settings(),
            SettingKey::ShowHiddenEntries,
            1,
        );

        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message);
    }

    private function formatSize(int $bytes): string
    {
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024.0 && $unit < count(self::SIZE_UNITS) - 1) {
            $value /= 1024.0;
            ++$unit;
        }

        if ($unit === 0) {
            return $bytes . ' B';
        }

        return $this->translator->number($value, 1) . ' ' . self::SIZE_UNITS[$unit];
    }
}
