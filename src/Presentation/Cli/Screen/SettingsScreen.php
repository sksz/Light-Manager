<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Screen;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Dto\SettingsCursor;
use LightManager\Application\Dto\SettingsTab;
use LightManager\Application\Dto\SettingsTabKind;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ModuleSettingKind;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Rect;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Ui\AcceptsPaste;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Button;
use LightManager\Presentation\Ui\Component\Choice;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Spacer;
use LightManager\Presentation\Ui\Component\Tabs;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\Component\Toggle;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Ekran ustawień: pasek zakładek u góry, pod nim pozycje aktywnej zakładki.
 *
 * Zakładki są od kroku 18 prawdziwym komponentem, a nie napisem z nawiasami
 * kwadratowymi wokół aktywnej pozycji. Sam pasek pozostaje jednym z miejsc,
 * które odwiedza kursor — nie jest osobnym trybem — więc strzałki poziome znaczą
 * na nim co innego niż na pozycji: tam przewijają zakładki, tu zmieniają wartość.
 *
 * Krok 20 otwiera ekran na moduły i zostawia w nim **trzy drogi zamiast jednej**
 * (`SettingsTabKind`): zakładka rdzenia bierze pozycje z enuma `SettingKey`,
 * zakładka modułu — z deklaracji `ModuleSetting`, a spis „Moduły” nie jest
 * zakładką ustawień w ogóle, tylko listą samych modułów. Wszystkie trzy kończą
 * się tymi samymi komponentami, więc różnią się wyłącznie tym, skąd biorą treść.
 *
 * **Pozycja tekstowa jest jedynym miejscem, które dokłada ekranowi tryb.** Trzy
 * pozostałe rodzaje zmieniają się strzałkami, bez stanu pośredniego; tekst
 * wymaga edycji znak po znaku, zatwierdzenia i wycofania. Tryb mieszka **tutaj**,
 * a nie w `TextInput` — pole wyszło z kroku 19 jako komponent bez trybów i
 * dokładanie mu ich teraz kazałoby oknu komend trzymać je stale włączone.
 */
final class SettingsScreen implements ScreenInterface, Resettable, DeclaresFocus, AcceptsPointer, AcceptsPaste
{
    /**
     * Ile wierszy nad treścią zajmuje oprawa: pasek zakładek i odstęp pod nim.
     *
     * Stała, a nie liczba wpisana w dwóch miejscach — `draw()` układa nią
     * szczeliny, a wskaźnik odejmuje ją od wiersza, w który trafił (krok 55).
     */
    private const CHROME_ROWS = 2;

    /** Prostokąt z ostatniego rysowania — pamięć wymagana przez `AcceptsPointer` (krok 55). */
    private ?Rect $lastBounds = null;

    private SettingsCursor $cursor;

    /**
     * Pozycja tekstowa w edycji wraz z polem, w którym się ją pisze; `null`, gdy
     * ekran nie jest w trybie edycji.
     *
     * Pole powstaje przy wejściu w tryb, a nie raz na ekran, bo jego zachęta jest
     * **etykietą pozycji** — tak label zostaje widoczny, a `TextInput` nie musi
     * poznać drugiego sposobu rysowania się.
     */
    private ?ModuleSetting $editing = null;

    private ?TextInput $input = null;

    /**
     * Okno przewijania zakładki — **osobne dla każdej z nich**.
     *
     * Piąty użytkownik `ScrollWindow` i pierwszy, który korzysta z `useContext()`
     * po to, żeby pamiętać położenie **na zakładkę**: zakładki mają różną długość,
     * a wracanie do zakładki od jej początku gubiłoby miejsce, w którym się było
     * (wzorzec `SectionState` z kroku 22).
     */
    private readonly ScrollWindow $window;

    /**
     * Ile pozycji zmieściło się w ostatniej narysowanej klatce.
     *
     * `PageUp`/`PageDown` muszą wiedzieć, o ile skoczyć, a wysokość strefy zna
     * wyłącznie rysowanie — kontrakt ekranu nie niesie prostokąta do `handle()`.
     * Wartość z ostatniej klatki jest zawsze aktualna: pętla rysuje przed
     * czytaniem wejścia, a rozmiar okna zmienia się między klatkami, nie w środku
     * (reguła 11f).
     */
    private int $page = 1;

    /**
     * @param SettingsPort      $configuration źródło **położenia** pliku; wartości
     *                                         czyta się ze stanu pętli, nie stąd
     * @param list<SettingsTab> $tabs          zakładki tego uruchomienia, złożone w `Bootstrap`
     */
    public function __construct(
        private readonly LoopState $state,
        /**
         * Odczyt ustawień — **przez rejestr kwerend** (krok 53, D92 nr 3).
         *
         * Stan pętli zostaje w konstruktorze, bo ekran go **zmienia**
         * (`applySettings()` po każdej zmianie pozycji) i bo trzyma w nim
         * komunikaty. Czytać ma jednak stąd: rdzeń nie może być jedynym miejscem
         * zwolnionym z reguły, którą sam ustanowił dla modułów.
         */
        private readonly CoreReader $core,
        private readonly ChangeSettingUseCase $changeSetting,
        private readonly RestoreDefaultSettingsUseCase $restoreDefaults,
        private readonly ChangeModuleSettingUseCase $changeModuleSetting,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $configuration,
        private readonly array $tabs = [],
        private readonly ?ModuleRegistry $modules = null,
    ) {
        $this->cursor = new SettingsCursor($this->tabs);
        $this->window = new ScrollWindow();
    }

    public function id(): string
    {
        return 'settings';
    }

    public function labelKey(): string
    {
        return 'layout.zone.settings';
    }

    /**
     * Górny pas ekranu ustawień: położenie pliku konfiguracyjnego.
     *
     * Ekran, który ten plik zmienia, jest jedynym miejscem, w którym ścieżka do
     * niego naprawdę się przydaje — a użytkownik, który chce go ruszyć ręcznie,
     * nie ma skąd jej wziąć. Zakładka „Aplikacja” w pomocy podaje ją dalej,
     * bo tam stoi w towarzystwie wersji i trybu renderowania.
     */
    public function header(): ScreenZone
    {
        return new ScreenZone('layout.zone.settings.file', new Label($this->configuration->location()));
    }

    /** Wejście na ekran zaczyna go od początku — kursor wraca na pasek zakładek. */
    public function reset(): void
    {
        $this->cursor = new SettingsCursor($this->tabs);
        $this->stopEditing();
    }

    /**
     * Pasek zakładek **stoi**, treść zakładki **przewija się** (krok 47, D78).
     *
     * Do tego kroku każda pozycja była osobną szczeliną `Slot::fixed`, a szczelina,
     * której nie starczyło wiersza, po prostu **nie rysowała się wcale**
     * (`Distribution`, reguła 11e). Zakładka `file-info` ma jedenaście pozycji plus
     * pasek, dwa odstępy i przycisk — piętnaście wierszy — więc w oknie o 22
     * wierszach znikał przycisk „przywróć domyślne”, a niżej znikały ustawienia.
     * Bez śladu: ani przycięcia, ani wielokropka.
     *
     * Pasek zakładek zostaje poza przewijaniem, bo jest jedynym wskaźnikiem tego,
     * gdzie użytkownik stoi; przycisk czynności przewija się razem z pozycjami,
     * bo jest ostatnią z nich, a nie stopką zakładki.
     */
    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $tab = $this->cursor->activeTab();
        $slots = [
            Slot::fixed(new Tabs($this->tabLabels(), $this->cursor->tab, $this->cursor->isOnTabBar()), 1),
            Slot::fixed(new Spacer(), 1),
        ];

        $items = $this->rows($tab);

        if ($tab !== null && $tab->hasAction()) {
            $items[] = new Spacer();
            $items[] = $this->restoreButton();
        }

        $chrome = self::CHROME_ROWS;
        $capacity = max(0, $bounds->rows - $chrome);
        $this->page = max(1, $capacity - 1);
        $this->window->useContext((string) $this->cursor->tab);
        $offset = $this->window->keepVisible($this->cursorRow($tab), count($items), $capacity);
        $position = $this->window->position(count($items), $capacity);
        $scrolls = $position !== null && $position->isNeeded();

        foreach (array_slice($items, $offset, $capacity) as $row) {
            $slots[] = Slot::fixed($row, 1);
        }

        // Suwak dostaje **własną kolumnę**, a treść oddaje mu ją na czas
        // przewijania — wzorem `Table` (reguła 11e). Bez tego szyna wchodzi na
        // wartości wyrównane do prawej krawędzi; widać to wyłącznie w prawdziwym
        // terminalu i tam właśnie zostało zauważone.
        $content = $scrolls ? $bounds->columnsFrom(0, $bounds->columns - 1) : $bounds;
        $primitives = (new VStack($slots))->draw($content);

        if ($scrolls) {
            // Szyna stoi **przy samej treści**, a nie przy krawędzi całej strefy:
            // pasek zakładek się nie przewija, więc suwak nad nim kłamałby o tym,
            // czego dotyczy.
            $primitives[] = new Scrollbar(
                new Rect($bounds->row + $chrome, $bounds->right(), $capacity, 1),
                $position,
            );
        }

        return $primitives;
    }

    /**
     * Numer wiersza, na którym stoi ognisko — liczony w **pozycjach zakładki**,
     * bo tak samo liczy je okno przewijania.
     *
     * Kursor na pasku zakładek nie jest w treści, więc oddaje `null`: okno ma
     * wtedy zostać tam, gdzie było, a nie skakać na początek. Przycisk czynności
     * ma numer o dwa większy od ostatniej pozycji, bo między nimi stoi odstęp.
     */
    private function cursorRow(?SettingsTab $tab): ?int
    {
        if ($this->cursor->item === null || $tab === null) {
            return null;
        }

        return $this->cursor->isOnAction() ? $tab->itemCount() + 1 : $this->cursor->item;
    }

    /**
     * Wiersze aktywnej zakładki — po jednym na pozycję.
     *
     * @return list<ComponentInterface>
     */
    private function rows(?SettingsTab $tab): array
    {
        if ($tab === null) {
            return [];
        }

        return match ($tab->kind) {
            SettingsTabKind::Core => $this->coreRows($tab),
            SettingsTabKind::Module => $this->moduleRows($tab),
            SettingsTabKind::Modules => $this->moduleListRows(),
        };
    }

    /** @return list<ComponentInterface> */
    private function coreRows(SettingsTab $tab): array
    {
        $settings = $this->core->settings();
        $rows = [];

        foreach ($tab->keys as $index => $key) {
            $rows[] = $this->position($settings, $key, $index === $this->cursor->item);
        }

        return $rows;
    }

    /** @return list<ComponentInterface> */
    private function moduleRows(SettingsTab $tab): array
    {
        $settings = $this->core->settings();
        $rows = [];

        foreach ($tab->settings as $index => $setting) {
            $rows[] = $this->modulePosition($settings, $tab->moduleId, $setting, $index === $this->cursor->item);
        }

        return $rows;
    }

    /**
     * Zakładka „Moduły”: moduł domyślny, pod nim spis — nazwa, skrót otwierający
     * i przełącznik.
     *
     * Moduł domyślny stoi **nad** spisem, bo jego wartości to identyfikatory
     * z tego właśnie spisu; postawiony pod nim czytałby się jak podsumowanie,
     * a jest wyborem.
     *
     * Moduł odrzucony przy starcie stoi na liście wraz z powodem **zamiast**
     * przełącznika i włączyć się nie da — kolizji skrótu nie usunie przełącznik,
     * tylko poprawka w kodzie. Tak samo moduł ostatniej szansy: przełącznik stoi,
     * ale zablokowany wraz z powodem.
     *
     * @return list<ComponentInterface>
     */
    private function moduleListRows(): array
    {
        $rows = [$this->position($this->core->settings(), SettingKey::StartupModule, $this->cursor->item === 0)];
        $modules = $this->modules?->declared() ?? [];

        if ($modules === []) {
            $rows[] = new Label($this->translator->translate('settings.modules.empty'));

            return $rows;
        }

        foreach ($modules as $index => $module) {
            $rows[] = $this->moduleListRow($module, $index + 1 === $this->cursor->item);
        }

        return $rows;
    }

    private function moduleListRow(ModuleInterface $module, bool $selected): ComponentInterface
    {
        $label = $this->translator->translate($module->nameKey());
        $shortcut = $module->shortcut();

        if ($shortcut !== null) {
            $label .= '   ' . KeyBinding::ctrl($shortcut->character, '')->display();
        }

        $rejection = $this->modules?->rejectionOf($module->id());

        if ($rejection !== null) {
            return new Choice($label, $this->translator->translate($rejection->reasonKey), $selected);
        }

        if ($this->modules?->isEssential($module->id()) ?? false) {
            return new Choice($label, $this->translator->translate('settings.modules.essential'), $selected);
        }

        return new Toggle(
            $label,
            $this->modules?->isEnabled($module->id()) ?? true,
            $this->translator->translate('settings.value.yes'),
            $this->translator->translate('settings.value.no'),
            $selected,
        );
    }

    /**
     * Przycisk przywracania ustawień domyślnych stoi pod pozycjami każdej
     * zakładki **rdzenia**, a nie w jednej wybranej: przywraca całość ustawień
     * rdzenia, więc dowiązanie go do „Wyglądu” albo do „Grafiki” sugerowałoby, że
     * dotyczy tylko tej zakładki. Pod zakładką modułu go nie ma — obiecywałby
     * wtedy, że przywraca ustawienia modułu, czego nie robi.
     */
    /**
     * Wiersz czynności — od kroku 28 **wyłącznie etykieta z ogniskiem**.
     *
     * Czynność przeniosła się do okna potwierdzenia, więc przycisk nie ma już
     * czego wykonywać; jego akcja jest pusta, a `handle()` nie jest tu wołane
     * (`Enter` obsługuje ekran, bo tylko on może oddać `ScreenOutcome`). Zostaje
     * to, co przycisk nadal wnosi: wygląd wiersza z kursorem i deklaracja
     * klawisza dla spisu w oknie pomocy.
     */
    private function restoreButton(): Button
    {
        return new Button(
            $this->translator->translate('settings.action.restore'),
            static function (): void {
            },
            'help.key.restore',
            $this->cursor->isOnAction(),
        );
    }

    /**
     * Pytanie przed jedyną nieodwracalną czynnością w aplikacji — i dlatego
     * zadane w wariancie groźnym (D56).
     *
     * Domknięcie jest **całą** czynnością: przywraca ustawienia, wpuszcza je do
     * stanu pętli i oddaje skutek, z którym okno się zamyka. Ekran nie dowiaduje
     * się nawet, czy padła odpowiedź „tak”.
     *
     * Skutkiem jest `OverlayOutcome`, a nie sam komunikat, od kroku 41 — bo
     * pytanie umie odtąd ustąpić miejsca oknu pracy. Tutaj następnego okna nie
     * ma i nie będzie: przywrócenie ustawień kończy się w tej samej klatce.
     */
    private function restoreConfirmation(): ConfirmOverlay
    {
        return new ConfirmOverlay(
            'settings.restore.confirm',
            [],
            function (): OverlayOutcome {
                [$settings, $message] = $this->restoreDefaults->execute($this->core->settings());

                $this->state->applySettings($settings);

                return OverlayOutcome::close($message);
            },
            $this->translator,
            true,
        );
    }

    public function bindings(): array
    {
        $bindings = $this->focus()->bindings;

        // W edycji `Esc` **porzuca zmianę**, a nie zamyka ekranu — i mówi o tym
        // już wiązanie ogniska. Drugie, o powrocie, byłoby wtedy kłamstwem.
        if ($this->editing === null) {
            $bindings[] = KeyBinding::of([Key::Escape], 'help.key.back', 'help.key.back.short');
        }

        return $bindings;
    }

    /**
     * Treść schowka w edytowanej pozycji tekstowej — **tylko w trakcie edycji**
     * (krok 57).
     *
     * Pozycja z sekretem (reguła 11y) przyjmuje wklejenie jak każda inna, i to
     * jest zamierzone: maskowanie dotyczy rysowania, nie treści, a token
     * rejestru obrazów jest dokładnie tą wartością, której nikt nie przepisuje
     * z ręki. Treść schowka **nie trafia przy tym nigdzie poza pole** — do
     * `settings.json` wchodzi dopiero po `Enter`ze, tą samą drogą, co wartość
     * wpisana z klawiatury.
     */
    public function paste(string $text): bool
    {
        if ($this->editing === null) {
            return false;
        }

        return $this->input?->paste($text) ?? false;
    }

    /**
     * Ognisko ustawień ma **cztery** położenia i każde odpowiada na inne klawisze.
     *
     * Do kroku 40 spis był jeden na cały ekran i przez to nieprawdziwy w trzech
     * miejscach naraz: `←→` na wierszu czynności nie robi nic, `Enter` na pasku
     * zakładek przewija zakładki (bo jest w całej aplikacji klawiszem
     * zatwierdzania, P3), a „edycja wartości” dotyczy wyłącznie pozycji
     * tekstowych — pozostałe `Enter` po prostu przełącza na następną wartość.
     * Kursor wie o tym wszystkim od kroku 20; brakowało wyłącznie drogi na
     * zewnątrz.
     */
    public function focus(): FocusHint
    {
        if ($this->editing !== null) {
            return new FocusHint('settings.focus.edit', [
                KeyBinding::of([Key::Enter], 'help.key.commit', 'help.key.commit.short'),
                KeyBinding::of([Key::Escape], 'help.key.cancel', 'help.key.cancel.short'),
                ...($this->input?->bindings() ?? []),
            ]);
        }

        $move = KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short');

        // Przewijanie stroną działa **wszędzie w treści zakładki**, więc wszędzie
        // tam musi stać w spisie — inaczej stopka kłamie (reguła 11p). Na pasku
        // zakładek go nie ma, bo tam nie ma czego przewijać.
        $page = KeyBinding::of(
            [Key::PageUp, Key::PageDown, Key::Home, Key::End],
            'help.key.page',
            'help.key.page.short',
        );

        if ($this->cursor->isOnTabBar()) {
            return new FocusHint('settings.focus.tabs', [
                $move,
                KeyBinding::of(
                    [Key::ArrowLeft, Key::ArrowRight, Key::Enter],
                    'help.key.tab',
                    'help.key.tab.short',
                ),
            ]);
        }

        if ($this->cursor->isOnAction()) {
            return new FocusHint('settings.focus.action', [
                $move,
                $page,
                KeyBinding::of([Key::Enter], 'help.key.restore', 'help.key.restore.short'),
            ]);
        }

        if ($this->cursor->setting()?->kind === ModuleSettingKind::Text) {
            return new FocusHint('settings.focus.item', [
                $move,
                $page,
                KeyBinding::of([Key::Enter], 'help.key.edit', 'help.key.edit.short'),
            ]);
        }

        return new FocusHint('settings.focus.item', [
            $move,
            $page,
            KeyBinding::of(
                [Key::ArrowLeft, Key::ArrowRight, Key::Enter],
                'help.key.change',
                'help.key.change.short',
            ),
        ]);
    }

    /**
     * Klawisz idzie najpierw do komponentu, na którym stoi kursor, i dopiero
     * nieobsłużony wraca do ekranu. Tryb edycji jest tu wyjątkiem, i to
     * zamierzonym: pole zużywa **każdy znak**, więc dopóki trwa, ekran nie
     * dostaje ani liter, ani strzałek — inaczej `t` w argumentach polecenia
     * przewijałoby zakładki.
     */
    /**
     * Wskaźnik w ustawieniach: zakładka, pozycja i kółko (krok 55).
     *
     * Pole edycji **połyka wskaźnik w całości**: gdy pozycja jest w trakcie
     * wpisywania, ognisko należy do niej, a kliknięcie gdziekolwiek indziej
     * porzucałoby wpisany napis bez słowa.
     *
     * Geometria pochodzi z jednego miejsca z rysowaniem: pasek zakładek zajmuje
     * wiersz zerowy, wiersz pierwszy jest odstępem, a treść zaczyna się od
     * drugiego i przewija oknem — dokładnie tak, jak układa to `draw()`.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($this->editing !== null || $bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        if ($event->isScroll()) {
            $this->window->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $tab = Tabs::at($this->tabLabels(), $bounds->line(0), $event);

        if ($tab !== null) {
            $this->cursor = $this->cursor->switchedTab($tab - $this->cursor->tab);

            return ScreenOutcome::stay();
        }

        return $this->pointerInItems($event, $bounds);
    }

    /** Pozycja pod wskaźnikiem — treść zaczyna się dwa wiersze pod zakładkami. */
    private function pointerInItems(PointerEvent $event, Rect $bounds): ScreenOutcome
    {
        $row = PointerRow::of(
            $event,
            $bounds->rowsFrom(self::CHROME_ROWS, $bounds->rows - self::CHROME_ROWS),
            $this->window->offset(),
            false,
            $this->itemCount(),
        );

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $this->cursor = $this->cursor->movedBy($row - ($this->cursor->item ?? -1));

        return ScreenOutcome::stay();
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($this->editing !== null) {
            return $this->whileEditing($key);
        }

        // Do kroku 28 szło tu `restoreButton()->handle()`, a przycisk kasował
        // konfigurację w miejscu. Dziś `Enter` na wierszu czynności **otwiera
        // pytanie** i to ono wykona czynność — warunek jest ten sam, który
        // sprawdzał sam przycisk (ognisko plus `Enter`), tyle że jawny.
        if ($this->cursor->isOnAction() && $key->key === Key::Enter) {
            return ScreenOutcome::opens($this->restoreConfirmation());
        }

        return match ($key->key) {
            Key::Escape, Key::F2 => ScreenOutcome::close(),
            Key::ArrowUp => $this->moved(-1),
            Key::ArrowDown => $this->moved(1),
            // Przewijanie stroną i skok na koniec (krok 47): komplet, który
            // `FileInfoScreen` ma od kroku 29, a słownik wejścia zna od kroku 06.
            Key::PageUp => $this->moved(-$this->page),
            Key::PageDown => $this->moved($this->page),
            Key::Home => $this->moved(-($this->cursor->item ?? 0)),
            Key::End => $this->moved($this->itemCount()),
            Key::ArrowLeft => $this->shift(-1),
            Key::ArrowRight => $this->shift(1),
            Key::Enter => $this->enter(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Enter` na pozycji tekstowej wchodzi w edycję, wszędzie indziej znaczy to,
     * co strzałka w prawo — jest w całej aplikacji klawiszem **zatwierdzania**,
     * a przy wartości przełączanej zatwierdzić da się tylko następną.
     */
    private function enter(): ScreenOutcome
    {
        $setting = $this->cursor->setting();

        if ($setting === null || $setting->kind !== ModuleSettingKind::Text) {
            return $this->shift(1);
        }

        $value = $setting->valueFrom(
            $this->core->settings()->moduleValue($this->moduleId(), $setting->key),
        );

        $this->editing = $setting;
        // Pozycja z sekretem edytuje się **z zasłoną** (krok 54, D94 nr 7):
        // `TextInput` umie to od kroku 48, więc znacznik z `ModuleSetting`
        // przechodzi tu wprost i nie dokłada ani jednej gałęzi rysowania.
        $this->input = new TextInput(
            $this->translator->translate($setting->labelKey) . ': ',
            masked: $setting->masked,
        );
        $this->input->useValue(is_string($value) ? $value : '');

        return ScreenOutcome::stay();
    }

    private function whileEditing(KeyPress $key): ScreenOutcome
    {
        $setting = $this->editing;
        $input = $this->input;

        if ($setting === null || $input === null) {
            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Escape) {
            $this->stopEditing();

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Enter) {
            [$settings, $message] = $this->changeModuleSetting->set(
                $this->core->settings(),
                $this->moduleId(),
                $setting,
                $input->value(),
            );

            $this->state->applySettings($settings);
            $this->stopEditing();

            return ScreenOutcome::stay($message);
        }

        $input->handle($key);

        return ScreenOutcome::stay();
    }

    private function stopEditing(): void
    {
        $this->editing = null;
        $this->input = null;
    }

    private function moved(int $delta): ScreenOutcome
    {
        $this->cursor = $this->cursor->movedBy($delta);

        return ScreenOutcome::stay();
    }

    /**
     * Ile pozycji ma zakładka wraz z wierszem czynności — miara skoku na koniec.
     *
     * Liczba, a nie `PHP_INT_MAX`: `SettingsCursor::movedBy()` dodaje przesunięcie
     * do numeru pozycji, a dodanie największego całkowitego zamieniłoby wynik
     * w liczbę zmiennoprzecinkową, zanim zdążyłby go przyciąć.
     */
    private function itemCount(): int
    {
        $tab = $this->cursor->activeTab();

        return ($tab?->itemCount() ?? 0) + ($tab !== null && $tab->hasAction() ? 1 : 0);
    }

    /**
     * Strzałka pozioma na pasku zakładek przewija zakładki, a na pozycji —
     * wartość ustawienia. Rozstrzyga o tym kursor, nie osobny tryb.
     */
    private function shift(int $direction): ScreenOutcome
    {
        if ($this->cursor->isOnTabBar()) {
            $this->cursor = $this->cursor->switchedTab($direction);

            return ScreenOutcome::stay();
        }

        $tab = $this->cursor->activeTab();

        if ($tab === null || $this->cursor->item === null) {
            return ScreenOutcome::stay();
        }

        return match ($tab->kind) {
            SettingsTabKind::Core => $this->shiftCore($direction),
            SettingsTabKind::Module => $this->shiftModule($tab->moduleId, $direction),
            // Pierwszy wiersz zakładki „Moduły” to ustawienie **rdzenia** (moduł
            // domyślny), a dopiero pod nim zaczyna się spis — stąd przesunięcie
            // o jeden.
            SettingsTabKind::Modules => $this->cursor->item === 0
                ? $this->shiftCore($direction)
                : $this->toggleModule($this->cursor->item - 1),
        };
    }

    private function shiftCore(int $direction): ScreenOutcome
    {
        $key = $this->cursor->key();

        if ($key === null) {
            return ScreenOutcome::stay();
        }

        [$settings, $message] = $this->changeSetting->execute($this->core->settings(), $key, $direction);

        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message);
    }

    private function shiftModule(string $moduleId, int $direction): ScreenOutcome
    {
        $setting = $this->cursor->setting();

        if ($setting === null || $setting->kind === ModuleSettingKind::Text) {
            return ScreenOutcome::stay();
        }

        [$settings, $message] = $this->changeModuleSetting->shift(
            $this->core->settings(),
            $moduleId,
            $setting,
            $direction,
        );

        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message);
    }

    /**
     * Przełącznik ze spisu modułów; moduł odrzucony mówi tylko, dlaczego odpadł,
     * a moduł ostatniej szansy — dlaczego wyłączyć się nie da.
     */
    private function toggleModule(int $item): ScreenOutcome
    {
        $module = ($this->modules?->declared() ?? [])[$item] ?? null;

        if ($module === null) {
            return ScreenOutcome::stay();
        }

        $rejection = $this->modules?->rejectionOf($module->id());

        if ($rejection !== null) {
            return ScreenOutcome::stay(Message::warning($this->translator->translate($rejection->reasonKey)));
        }

        if ($this->modules?->isEssential($module->id()) ?? false) {
            return ScreenOutcome::stay(
                Message::warning($this->translator->translate('settings.modules.essential.reason')),
            );
        }

        [$settings, $message] = $this->changeModuleSetting->enable(
            $this->core->settings(),
            $module->id(),
            !($this->modules?->isEnabled($module->id()) ?? true),
        );

        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message);
    }

    private function moduleId(): string
    {
        $tab = $this->cursor->activeTab();

        return $tab === null ? '' : $tab->moduleId;
    }

    /** @return list<string> */
    private function tabLabels(): array
    {
        $labels = [];

        foreach ($this->tabs as $tab) {
            $labels[] = $this->translator->translate($tab->labelKey);
        }

        return $labels;
    }

    /**
     * Wartość gotowa do postawienia w wierszu. Składanie jej należy do ekranu,
     * a nie do `Settings`: „tak”, „nie” i nazwa języka to napisy, a obiekt
     * konfiguracji ma nieść wartości, nie ich brzmienie.
     */
    private function position(Settings $settings, SettingKey $key, bool $selected): Choice|Toggle
    {
        $label = $this->translator->translate($key->labelKey());
        $yes = $this->translator->translate('settings.value.yes');
        $no = $this->translator->translate('settings.value.no');

        return match ($key) {
            SettingKey::Language => new Choice(
                $label,
                $this->translator->translate((Language::tryFrom($settings->language) ?? Language::Auto)->labelKey()),
                $selected,
            ),
            SettingKey::Theme => new Choice($label, ucfirst($settings->theme), $selected),
            SettingKey::PaletteColors => new Choice($label, (string) $settings->paletteColors, $selected),
            // Wartość pokazujemy tak, jak leży w pliku — identyfikatorem, a nie
            // nazwą modułu: to ona jest tym, co użytkownik wpisze, gdy zechce
            // ruszyć plik ręcznie, a nazwa modułu stoi wiersz niżej, w spisie.
            SettingKey::StartupModule => new Choice($label, $settings->startupModule, $selected),
            SettingKey::TextAntialias => new Toggle($label, $settings->textAntialias, $yes, $no, $selected),
            SettingKey::StrokeAntialias => new Toggle($label, $settings->strokeAntialias, $yes, $no, $selected),
            SettingKey::WindowColumns => new Choice($label, (string) $settings->windowColumns, $selected),
            SettingKey::WindowRows => new Choice($label, (string) $settings->windowRows, $selected),
            // Jednostkę dopisujemy do wartości, a nie do etykiety: „1024" bez
            // niej jest liczbą bez znaczenia, a etykieta z jednostką w nawiasie
            // rosłaby o cztery znaki w każdym języku.
            SettingKey::BackgroundOutputKib => new Choice(
                $label,
                $this->translator->translate('settings.value.kib', ['value' => $settings->backgroundOutputKib]),
                $selected,
            ),
            SettingKey::BackgroundJobs => new Choice(
                $label,
                $this->translator->number($settings->backgroundJobs),
                $selected,
            ),
            SettingKey::Mouse => new Toggle($label, $settings->mouse, $yes, $no, $selected),
        };
    }

    /**
     * Pozycja modułu. Pozycja tekstowa **w trybie edycji** rysuje się polem
     * wpisywania, którego zachętą jest jej własna etykieta — dzięki temu widać
     * i nazwę pozycji, i karetkę, a `TextInput` zostaje taki, jaki wyszedł
     * z kroku 19.
     */
    private function modulePosition(
        Settings $settings,
        string $moduleId,
        ModuleSetting $setting,
        bool $selected,
    ): ComponentInterface {
        if ($selected && $this->editing === $setting && $this->input !== null) {
            $this->input->useTime($this->state->now());

            return $this->input;
        }

        $label = $this->translator->translate($setting->labelKey);
        $value = $setting->valueFrom($settings->moduleValue($moduleId, $setting->key));

        if ($setting->kind === ModuleSettingKind::Toggle) {
            return new Toggle(
                $label,
                (bool) $value,
                $this->translator->translate('settings.value.yes'),
                $this->translator->translate('settings.value.no'),
                $selected,
            );
        }

        // Sekret zasłania się **także w wierszu listy**, nie tylko przy edycji —
        // inaczej maskowanie broniłoby wartości wyłącznie w chwili, w której
        // użytkownik i tak na nią patrzy.
        $shown = $setting->shown($value);
        $text = is_bool($shown) ? '' : (string) $shown;

        return new Choice(
            $label,
            $text === '' ? $this->translator->translate('settings.value.empty') : $text,
            $selected,
        );
    }
}
