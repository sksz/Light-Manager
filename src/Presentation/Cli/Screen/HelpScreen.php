<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Screen;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use LightManager\Presentation\Ui\Component\Tabs;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SectionState;

/**
 * Okno pomocy: zakładka ze spisem klawiszy i zakładka o aplikacji.
 *
 * Spis klawiszy **nie jest już przepisywany ręcznie**. Do kroku 18 stała tu
 * tablica `KEYS` z ośmioma wierszami, którą trzeba było pamiętać przy każdej
 * zmianie wiązania — i która milczała o tym, że wiązanie się zmieniło. Dziś
 * pochodzi z `bindings()` ekranów, czyli z tego samego miejsca, z którego
 * pochodzą same wiązania; skłamać nie ma jak.
 *
 * Pasek zakładek jest tym, co krok 14 był temu ekranowi winien: powstał wtedy
 * bez niego, bo zakładki miały przyjść wraz z modułami (D33). Moduły przyszły
 * w kroku 20 — i wtedy okazało się, że pasek przełączał zakładki **binarnie**
 * (dwie, na przemian), a musi chodzić po liście dowolnej długości, cyklicznie,
 * jak `Tabs` w ustawieniach.
 *
 * Od kroku 22 zakładka „Sterowanie” jest **listą zwijanych sekcji**, a nie jednym
 * strumieniem wierszy. Powód jest w niej samej: grupy już tam były — po jednej na
 * ekran plus „spoza ekranów” — a po dołożeniu modułów spis urósł ponad wysokość
 * okna. Kursor chodzi po **nagłówkach**, `Enter` zwija i rozwija. Klawisze `←`/`→`,
 * które w innych ekranach będą zwijać i rozwijać wprost, są tu zajęte przez zmianę
 * zakładki i dlatego zostaje sam `Enter`.
 *
 * Pozostałe zakładki zostają płaskie i to nie jest niedokończona robota: „Aplikacja”
 * ma pięć wierszy, a zakładka modułu — kilkanaście, więc nie ma tam czego chować.
 *
 * Zakładka modułu składa się z dwóch części (P8). Część **automatyczna** powstaje
 * z deklaracji: opis, skrót otwierający, klawisze jego ekranu i pozycje jego
 * zakładki ustawień. Nie ma prawa skłamać po zmianie wiązania, bo pochodzi z tego
 * samego miejsca, co samo wiązanie. Część **własna** to wiersze z `helpKeys()` —
 * klucze katalogu, nie napisy, więc tłumaczą się jak reszta interfejsu.
 */
final class HelpScreen implements ScreenInterface, Resettable
{
    private const TAB_KEYS = 0;

    private const TAB_ABOUT = 1;

    /** Ile zakładek ma rdzeń; zakładki modułów zaczynają się za nimi. */
    private const CORE_TABS = 2;

    /** Szerokość kolumny z klawiszem — na tyle szeroka, by zmieścił się „Backspace / ←”. */
    private const KEY_COLUMNS = 16;

    private int $tab = self::TAB_KEYS;

    private readonly ScrollWindow $window;

    /** Co zwinięte i na której sekcji stoi kursor — wyłącznie dla zakładki „Sterowanie”. */
    private readonly SectionState $sectionState;

    /** @var list<ScreenInterface> ekrany, których wiązania trafiają do spisu */
    private array $screens = [];

    /** @var list<KeyBinding> wiązania rdzenia — te same, które obsługuje `InputHandler` */
    private array $global = [];

    /**
     * Sekcje spoza ekranów: klucz etykiety → wiązania.
     *
     * Dziś jest jedna — okno komend. Nie jest ekranem, bo staje **nad** ekranem,
     * a mimo to ma klawisze, o których użytkownik ma prawo przeczytać w tym
     * samym miejscu co o pozostałych.
     *
     * @var array<string, list<KeyBinding>>
     */
    private array $sections = [];

    /** @var list<ModuleInterface> moduły przyjęte — po jednej zakładce na każdy */
    private array $modules = [];

    /**
     * @param ?string $contentScale gęstość wyświetlacza gotowa do pokazania albo
     *                              `null`, gdy nie ma jej kto zmierzyć — pomoc
     *                              dostaje **napis**, bo o `glfwGetWindowContentScale`
     *                              wie wyłącznie `Bootstrap` (krok 37)
     */
    public function __construct(
        private readonly SettingsPort $settings,
        private readonly TranslatorPort $translator,
        private readonly string $version,
        private readonly string $rendererMode,
        private readonly ?string $contentScale = null,
    ) {
        $this->window = new ScrollWindow();
        $this->sectionState = new SectionState();
    }

    /**
     * Spis powstaje z wiązań, więc ekrany muszą się w nim zameldować. Robi to
     * `Bootstrap` po zbudowaniu wszystkich ekranów — wcześniej nie ma czego
     * meldować, bo pomoc powstaje jako jeden z nich.
     *
     * @param list<ScreenInterface>            $screens
     * @param list<KeyBinding>                 $global
     * @param array<string, list<KeyBinding>>  $sections dodatkowe sekcje: klucz etykiety → wiązania
     */
    public function knowAbout(array $screens, array $global, array $sections = []): void
    {
        $this->screens = $screens;
        $this->global = $global;
        $this->sections = $sections;
    }

    /**
     * Moduły, które dostają własną zakładkę. Melduje je `Bootstrap` po zbudowaniu
     * rejestru — zakładkę dostaje **każdy przyjęty moduł**, także taki, który nie
     * wnosi ani ekranu, ani własnych wierszy pomocy: jego nazwa i opis też są
     * czymś, o czym użytkownik ma prawo przeczytać.
     *
     * @param list<ModuleInterface> $modules
     */
    public function knowAboutModules(array $modules): void
    {
        $this->modules = $modules;
    }

    public function id(): string
    {
        return 'help';
    }

    public function labelKey(): string
    {
        return 'layout.zone.help';
    }

    /**
     * Górny pas ekranu pomocy: nazwa aplikacji wraz z wersją.
     *
     * Do kroku 20 stała tu ścieżka bieżącego katalogu, bo rdzeń rysował ją
     * bezwarunkowo dla każdego ekranu. Po przenosinach katalogu do modułu nie ma
     * z czego — a zostawienie pustego pasa byłoby zmianą wyglądu klatki. Pomoc
     * stawia więc w nim to, co ma własnego i co pasuje do jej roli.
     */
    public function header(): ScreenZone
    {
        return new ScreenZone(
            'layout.zone.about',
            new Label($this->translator->translate('app.name') . '  ' . $this->version),
        );
    }

    public function preview(): ?ScreenZone
    {
        return null;
    }

    public function reset(): void
    {
        $this->tab = self::TAB_KEYS;
        $this->window->useContext('');
        $this->sectionState->useContext('');
    }

    public function draw(Rect $bounds): array
    {
        $capacity = max(0, $bounds->rows - 2);

        return (new VStack([
            Slot::fixed(new Tabs($this->tabLabels(), $this->tab, true), 1),
            Slot::fixed(new Label(''), 1),
            Slot::flexible($this->content($capacity)),
        ]))->draw($bounds);
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.scroll'),
            KeyBinding::of([Key::ArrowLeft, Key::ArrowRight], 'help.key.tab'),
            KeyBinding::of([Key::Enter], 'help.key.collapse'),
            KeyBinding::of([Key::Escape], 'help.key.back'),
        ];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        switch ($key->key) {
            case Key::Escape:
            case Key::F1:
                return ScreenOutcome::close();
            case Key::ArrowUp:
                return $this->moved(-1);
            case Key::ArrowDown:
                return $this->moved(1);
            case Key::Enter:
                return $this->toggled();
            case Key::ArrowLeft:
                return $this->switchedTab(-1);
            case Key::ArrowRight:
                return $this->switchedTab(1);
            default:
                return ScreenOutcome::stay();
        }
    }

    /**
     * Treść aktywnej zakładki jako komponent.
     *
     * Rozgałęzienie jest **jedno i jest tutaj**: zakładka „Sterowanie” składa się
     * z sekcji, reszta zostaje płaską listą. Rozsypanie tego warunku po `handle()`
     * i `draw()` rozjechałoby się przy pierwszej nowej zakładce.
     */
    private function content(int $capacity): ComponentInterface
    {
        if ($this->tab === self::TAB_KEYS) {
            return $this->keyList($capacity);
        }

        $rows = $this->rows();
        $this->window->clamp(count($rows), $capacity);

        return new ListView(
            array_slice($rows, $this->window->offset(), max(0, $capacity)),
            null,
            $this->window->position(count($rows), $capacity),
        );
    }

    /**
     * Zakładka „Sterowanie”: sekcje wraz z oknem podążającym za kursorem.
     *
     * `keepVisible()` wołane jest **dwa razy i to nie jest pomyłka**. Pierwsze
     * ściąga okno tak, by widać było **koniec** sekcji pod kursorem — inaczej
     * rozwinięta sekcja pokazywałaby sam nagłówek, a treść zostawałaby pod dolną
     * krawędzią. Drugie pilnuje **nagłówka** i wygrywa, gdy sekcja jest wyższa od
     * okna: lepiej stracić jej koniec niż miejsce, w którym stoi kursor.
     */
    private function keyList(int $capacity): SectionList
    {
        $this->sectionState->useContext((string) $this->tab);

        $sections = $this->keySections();
        $this->sectionState->moveBy(0, count($sections));

        $cursor = $this->sectionState->cursor();
        $total = SectionList::rowCount($sections);
        $current = $sections[$cursor] ?? null;

        if ($current !== null) {
            $first = SectionList::rowOf($sections, $cursor);
            $this->window->keepVisible($first + $current->height() - 1, $total, $capacity);
            $this->window->keepVisible($first, $total, $capacity);
        }

        return new SectionList(
            $sections,
            $this->window->offset(),
            $current === null ? null : $cursor,
            $this->window->position($total, $capacity),
        );
    }

    /**
     * `↑`/`↓` znaczą co innego na każdej zakładce i tak ma być: tam, gdzie są
     * sekcje, przenoszą kursor między nimi, a tam, gdzie jest płaska lista —
     * przewijają ją. Opis wiązania mówi „przewijanie” w obu przypadkach, bo dla
     * użytkownika skutek jest ten sam: treść jedzie w górę albo w dół.
     */
    private function moved(int $delta): ScreenOutcome
    {
        if ($this->tab === self::TAB_KEYS) {
            $this->sectionState->moveBy($delta, count($this->keySections()));

            return ScreenOutcome::stay();
        }

        $this->window->scrollBy($delta);

        return ScreenOutcome::stay();
    }

    private function toggled(): ScreenOutcome
    {
        if ($this->tab !== self::TAB_KEYS) {
            return ScreenOutcome::stay();
        }

        $sections = $this->keySections();
        $current = $sections[$this->sectionState->cursor()] ?? null;

        if ($current !== null) {
            $this->sectionState->toggle($current->key);
        }

        return ScreenOutcome::stay();
    }

    /**
     * Zakładki chodzą cyklicznie i po liście dowolnej długości — z dwiema
     * rdzeniowymi i jedną na moduł „na przemian” przestało wystarczać.
     * Przewijanie wraca na początek, bo nowa zakładka ma inną treść i inną
     * długość.
     */
    private function switchedTab(int $direction): ScreenOutcome
    {
        $count = self::CORE_TABS + count($this->modules);
        $this->tab = ($this->tab + $direction + $count) % $count;
        $this->window->scrollBy(-PHP_INT_MAX);

        return ScreenOutcome::stay();
    }

    /** @return list<string> */
    private function tabLabels(): array
    {
        $labels = [
            $this->translator->translate('help.tab.keys'),
            $this->translator->translate('help.tab.about'),
        ];

        foreach ($this->modules as $module) {
            $labels[] = $this->translator->translate($module->nameKey());
        }

        return $labels;
    }

    /**
     * Treść aktywnej zakładki **płaskiej**. Zakładka „Sterowanie” tu nie trafia —
     * ma sekcje i własną drogę (`keySections()`).
     *
     * @return list<ListRow>
     */
    private function rows(): array
    {
        if ($this->tab === self::TAB_ABOUT) {
            return $this->aboutRows();
        }

        $module = $this->modules[$this->tab - self::CORE_TABS] ?? null;

        return $module === null ? [] : $this->moduleRows($module);
    }

    /**
     * Zakładka jednego modułu: część automatyczna z deklaracji, pod nią wiersze
     * własne.
     *
     * @return list<ListRow>
     */
    private function moduleRows(ModuleInterface $module): array
    {
        $rows = [new ListRow($this->translator->translate($module->descriptionKey()), '', Role::Muted)];
        $shortcut = $module->shortcut();

        if ($shortcut !== null) {
            $rows[] = new ListRow('', '', Role::Muted);
            $rows[] = new ListRow($this->translator->translate('help.module.shortcut'), '', Role::Accent);
            $rows[] = $this->keyRow(KeyBinding::ctrl($shortcut->character, 'help.module.open'));
        }

        foreach ($this->moduleKeyRows($module) as $row) {
            $rows[] = $row;
        }

        foreach ($this->moduleSettingRows($module) as $row) {
            $rows[] = $row;
        }

        if (!$module instanceof ProvidesHelpTab || $module->helpKeys() === []) {
            return $rows;
        }

        $rows[] = new ListRow('', '', Role::Muted);

        foreach ($module->helpKeys() as $key) {
            $rows[] = new ListRow($this->translator->translate($key));
        }

        return $rows;
    }

    /** @return list<ListRow> */
    private function moduleKeyRows(ModuleInterface $module): array
    {
        if (!$module instanceof ProvidesScreen) {
            return [];
        }

        $bindings = $module->screen()->bindings();

        if ($bindings === []) {
            return [];
        }

        $rows = [
            new ListRow('', '', Role::Muted),
            new ListRow($this->translator->translate('help.module.keys'), '', Role::Accent),
        ];

        foreach ($bindings as $binding) {
            $rows[] = $this->keyRow($binding);
        }

        return $rows;
    }

    /** @return list<ListRow> */
    private function moduleSettingRows(ModuleInterface $module): array
    {
        if (!$module instanceof ProvidesSettingsTab) {
            return [];
        }

        $settings = $module->settingsTab()->settings;

        if ($settings === []) {
            return [];
        }

        $rows = [
            new ListRow('', '', Role::Muted),
            new ListRow($this->translator->translate('help.module.settings'), '', Role::Accent),
        ];

        foreach ($settings as $setting) {
            $rows[] = new ListRow(
                mb_str_pad('', self::KEY_COLUMNS) . $this->translator->translate($setting->labelKey),
            );
        }

        return $rows;
    }

    /**
     * Spis klawiszy: najpierw wiązania rdzenia, potem każdego ekranu osobno pod
     * jego własną nazwą. Podział jest po ekranach, bo ten sam klawisz znaczy na
     * nich co innego — i właśnie o tym użytkownik przychodzi się dowiedzieć.
     *
     * Do kroku 22 grupy były płaskie: nagłówek w akcencie i pusty wiersz przed
     * nim. Zmieniło się jedno — grupa jest teraz `Section`, więc **da się ją
     * zwinąć**. Wiązania rdzenia dostały przy tym własny nagłówek, którego
     * wcześniej nie miały: sekcja bez etykiety nie ma czego pokazać na znaczniku,
     * a „Wszędzie” mówi o nich prawdę, której płaski spis nie mówił.
     *
     * Klucz sekcji jest **trwały** — identyfikator ekranu albo klucz etykiety —
     * więc zwinięcie przeżywa zmianę zakładki i wyłączenie modułu.
     *
     * @return list<Section>
     */
    private function keySections(): array
    {
        $sections = [$this->section('global', 'help.section.global', $this->global)];

        foreach ($this->screens as $screen) {
            $sections[] = $this->section(
                'screen.' . $screen->id(),
                $screen->labelKey(),
                $screen->bindings(),
            );
        }

        foreach ($this->sections as $labelKey => $bindings) {
            $sections[] = $this->section($labelKey, $labelKey, $bindings);
        }

        return $sections;
    }

    /** @param list<KeyBinding> $bindings */
    private function section(string $key, string $labelKey, array $bindings): Section
    {
        return new Section(
            $key,
            $this->translator->translate($labelKey),
            array_map($this->keyRow(...), $bindings),
            $this->sectionState->isCollapsed($key),
        );
    }

    private function keyRow(KeyBinding $binding): ListRow
    {
        return new ListRow(
            mb_str_pad($binding->display(), self::KEY_COLUMNS)
                . $this->translator->translate($binding->descriptionKey),
        );
    }

    /**
     * Zakładka „Aplikacja”: wersja, tryb renderowania i ścieżka pliku
     * konfiguracyjnego. To ostatnie jest jedynym miejscem w aplikacji, które
     * mówi, gdzie ten plik leży — a użytkownik, który chce go ruszyć ręcznie,
     * nie ma skąd tego wiedzieć.
     *
     * Od kroku 37 dochodzi czwarty wiersz i tylko w torze okienkowym: gęstość
     * wyświetlacza. Stoi tu, bo jest jedyną rzeczą w tym kroku, której **nie
     * dało się sprawdzić na maszynie projektu** — pokazana, przestaje wymagać
     * wiary na słowo.
     *
     * @return list<ListRow>
     */
    private function aboutRows(): array
    {
        $rows = [
            new ListRow($this->translator->translate('help.about.version'), $this->version),
            new ListRow($this->translator->translate('help.about.renderer'), $this->rendererMode),
        ];

        if ($this->contentScale !== null) {
            $rows[] = new ListRow($this->translator->translate('help.about.scale'), $this->contentScale);
        }

        return [
            ...$rows,
            new ListRow('', '', Role::Muted),
            new ListRow($this->translator->translate('help.settings.location'), '', Role::Muted),
            new ListRow($this->settings->location()),
        ];
    }
}
