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
use LightManager\Presentation\Ui\Component\Tabs;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

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

    public function __construct(
        private readonly SettingsPort $settings,
        private readonly TranslatorPort $translator,
        private readonly string $version,
        private readonly string $rendererMode,
    ) {
        $this->window = new ScrollWindow();
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

    public function usesPreview(): bool
    {
        return false;
    }

    public function headerSuffix(): string
    {
        return '';
    }

    public function reset(): void
    {
        $this->tab = self::TAB_KEYS;
        $this->window->useContext('');
    }

    public function draw(Rect $bounds): array
    {
        $rows = $this->rows();
        $capacity = max(0, $bounds->rows - 2);

        $this->window->clamp(count($rows), $capacity);

        return (new VStack([
            Slot::fixed(new Tabs($this->tabLabels(), $this->tab, true), 1),
            Slot::fixed(new Label(''), 1),
            Slot::flexible(new ListView(
                array_slice($rows, $this->window->offset(), max(0, $capacity)),
                null,
                $this->window->position(count($rows), $capacity),
            )),
        ]))->draw($bounds);
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.scroll'),
            KeyBinding::of([Key::ArrowLeft, Key::ArrowRight], 'help.key.tab'),
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
                $this->window->scrollBy(-1);

                return ScreenOutcome::stay();
            case Key::ArrowDown:
                $this->window->scrollBy(1);

                return ScreenOutcome::stay();
            case Key::ArrowLeft:
                return $this->switchedTab(-1);
            case Key::ArrowRight:
                return $this->switchedTab(1);
            default:
                return ScreenOutcome::stay();
        }
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
     * Treść aktywnej zakładki.
     *
     * @return list<ListRow>
     */
    private function rows(): array
    {
        if ($this->tab === self::TAB_KEYS) {
            return $this->keyRows();
        }

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
     * @return list<ListRow>
     */
    private function keyRows(): array
    {
        $rows = [];

        foreach ($this->global as $binding) {
            $rows[] = $this->keyRow($binding);
        }

        foreach ($this->screens as $screen) {
            $rows[] = new ListRow('', '', Role::Muted);
            $rows[] = new ListRow(
                $this->translator->translate($screen->labelKey()),
                '',
                Role::Accent,
            );

            foreach ($screen->bindings() as $binding) {
                $rows[] = $this->keyRow($binding);
            }
        }

        foreach ($this->sections as $labelKey => $bindings) {
            $rows[] = new ListRow('', '', Role::Muted);
            $rows[] = new ListRow($this->translator->translate($labelKey), '', Role::Accent);

            foreach ($bindings as $binding) {
                $rows[] = $this->keyRow($binding);
            }
        }

        return $rows;
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
     * @return list<ListRow>
     */
    private function aboutRows(): array
    {
        return [
            new ListRow($this->translator->translate('help.about.version'), $this->version),
            new ListRow($this->translator->translate('help.about.renderer'), $this->rendererMode),
            new ListRow('', '', Role::Muted),
            new ListRow($this->translator->translate('help.settings.location'), '', Role::Muted),
            new ListRow($this->settings->location()),
        ];
    }
}
