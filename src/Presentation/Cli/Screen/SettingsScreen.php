<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Screen;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Dto\SettingsCursor;
use LightManager\Application\Dto\SettingsTab;
use LightManager\Application\Dto\SettingsTabKind;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ModuleSettingKind;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Cli\LoopState;
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
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;

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
final class SettingsScreen implements ScreenInterface, Resettable
{
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
     * Komunikat wystawiony przez czynność przycisku.
     *
     * Przycisk oddaje `bool` — „zużyłem klawisz” — a nie wynik czynności, bo
     * czynność jest wywoływalnym obiektem i nie ma jak niczego zwrócić. Pole
     * jest więc jedyną drogą, którą komunikat trafia do paska stanu; żyje przez
     * jedno naciśnięcie klawisza i zeruje się na wejściu do `handle()`.
     */
    private ?Message $pending = null;

    /** @param list<SettingsTab> $tabs zakładki tego uruchomienia, złożone w `Bootstrap` */
    public function __construct(
        private readonly LoopState $state,
        private readonly ChangeSettingUseCase $changeSetting,
        private readonly RestoreDefaultSettingsUseCase $restoreDefaults,
        private readonly ChangeModuleSettingUseCase $changeModuleSetting,
        private readonly TranslatorPort $translator,
        private readonly array $tabs = [],
        private readonly ?ModuleRegistry $modules = null,
    ) {
        $this->cursor = new SettingsCursor($this->tabs);
    }

    public function id(): string
    {
        return 'settings';
    }

    public function labelKey(): string
    {
        return 'layout.zone.settings';
    }

    public function usesPreview(): bool
    {
        return false;
    }

    public function headerSuffix(): string
    {
        return '';
    }

    /** Wejście na ekran zaczyna go od początku — kursor wraca na pasek zakładek. */
    public function reset(): void
    {
        $this->cursor = new SettingsCursor($this->tabs);
        $this->stopEditing();
    }

    public function draw(Rect $bounds): array
    {
        $tab = $this->cursor->activeTab();
        $slots = [
            Slot::fixed(new Tabs($this->tabLabels(), $this->cursor->tab, $this->cursor->isOnTabBar()), 1),
            Slot::fixed(new Spacer(), 1),
        ];

        foreach ($this->rows($tab) as $row) {
            $slots[] = Slot::fixed($row, 1);
        }

        if ($tab !== null && $tab->hasAction()) {
            $slots[] = Slot::fixed(new Spacer(), 1);
            $slots[] = Slot::fixed($this->restoreButton(), 1);
        }

        return (new VStack($slots))->draw($bounds);
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
        $settings = $this->state->settings();
        $rows = [];

        foreach ($tab->keys as $index => $key) {
            $rows[] = $this->position($settings, $key, $index === $this->cursor->item);
        }

        return $rows;
    }

    /** @return list<ComponentInterface> */
    private function moduleRows(SettingsTab $tab): array
    {
        $settings = $this->state->settings();
        $rows = [];

        foreach ($tab->settings as $index => $setting) {
            $rows[] = $this->modulePosition($settings, $tab->moduleId, $setting, $index === $this->cursor->item);
        }

        return $rows;
    }

    /**
     * Spis modułów: nazwa, skrót otwierający i przełącznik.
     *
     * Moduł odrzucony przy starcie stoi na liście wraz z powodem **zamiast**
     * przełącznika i włączyć się nie da — kolizji skrótu nie usunie przełącznik,
     * tylko poprawka w kodzie.
     *
     * @return list<ComponentInterface>
     */
    private function moduleListRows(): array
    {
        $modules = $this->modules?->declared() ?? [];

        if ($modules === []) {
            return [new Label($this->translator->translate('settings.modules.empty'))];
        }

        $rows = [];

        foreach ($modules as $index => $module) {
            $rows[] = $this->moduleListRow($module, $index === $this->cursor->item);
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
    private function restoreButton(): Button
    {
        return new Button(
            $this->translator->translate('settings.action.restore'),
            function (): void {
                [$settings, $message] = $this->restoreDefaults->execute($this->state->settings());

                $this->state->applySettings($settings);
                $this->pending = $message;
            },
            'help.key.restore',
            $this->cursor->isOnAction(),
        );
    }

    public function bindings(): array
    {
        if ($this->editing !== null) {
            return [
                KeyBinding::of([Key::Enter], 'help.key.commit'),
                KeyBinding::of([Key::Escape], 'help.key.cancel'),
                ...($this->input?->bindings() ?? []),
            ];
        }

        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of([Key::ArrowLeft, Key::ArrowRight], 'help.key.change'),
            KeyBinding::of([Key::Enter], 'help.key.edit'),
            KeyBinding::of([Key::Escape], 'help.key.back'),
        ];
    }

    /**
     * Klawisz idzie najpierw do komponentu, na którym stoi kursor, i dopiero
     * nieobsłużony wraca do ekranu. Tryb edycji jest tu wyjątkiem, i to
     * zamierzonym: pole zużywa **każdy znak**, więc dopóki trwa, ekran nie
     * dostaje ani liter, ani strzałek — inaczej `t` w argumentach polecenia
     * przewijałoby zakładki.
     */
    public function handle(KeyPress $key): ScreenOutcome
    {
        $this->pending = null;

        if ($this->editing !== null) {
            return $this->whileEditing($key);
        }

        if ($this->cursor->isOnAction() && $this->restoreButton()->handle($key)) {
            return ScreenOutcome::stay($this->pending);
        }

        return match ($key->key) {
            Key::Escape, Key::F2 => ScreenOutcome::close(),
            Key::ArrowUp => $this->moved(-1),
            Key::ArrowDown => $this->moved(1),
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
            $this->state->settings()->moduleValue($this->moduleId(), $setting->key),
        );

        $this->editing = $setting;
        $this->input = new TextInput($this->translator->translate($setting->labelKey) . ': ');
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
                $this->state->settings(),
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
            SettingsTabKind::Modules => $this->toggleModule($this->cursor->item),
        };
    }

    private function shiftCore(int $direction): ScreenOutcome
    {
        $key = $this->cursor->key();

        if ($key === null) {
            return ScreenOutcome::stay();
        }

        [$settings, $message] = $this->changeSetting->execute($this->state->settings(), $key, $direction);

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
            $this->state->settings(),
            $moduleId,
            $setting,
            $direction,
        );

        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message);
    }

    /** Przełącznik ze spisu modułów; moduł odrzucony mówi tylko, dlaczego odpadł. */
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

        [$settings, $message] = $this->changeModuleSetting->enable(
            $this->state->settings(),
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
            SettingKey::ShowHiddenEntries => new Toggle($label, $settings->showHiddenEntries, $yes, $no, $selected),
            SettingKey::TextAntialias => new Toggle($label, $settings->textAntialias, $yes, $no, $selected),
            SettingKey::StrokeAntialias => new Toggle($label, $settings->strokeAntialias, $yes, $no, $selected),
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

        $text = is_bool($value) ? '' : (string) $value;

        return new Choice(
            $label,
            $text === '' ? $this->translator->translate('settings.value.empty') : $text,
            $selected,
        );
    }
}
