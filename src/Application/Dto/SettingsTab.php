<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ModuleSettingsTab;

/**
 * Zakładka ekranu ustawień: etykieta plus pozycje, które kursor ma odwiedzić.
 *
 * Do kroku 20 była enumem z dwoma przypadkami — i to była główna przeszkoda dla
 * modułów, dokładnie jak zapowiadała D30: do enuma nie da się dołożyć przypadku
 * spoza kodu rdzenia. Dziś jest obiektem wartości, a lista zakładek powstaje
 * przy starcie: dwie rdzeniowe, spis „Moduły”, po jednej na moduł deklarujący
 * `ProvidesSettingsTab`.
 *
 * Zakładka zostaje w `Application/Dto` mimo zmiany postaci, bo niesie wyłącznie
 * **dane** — klucze etykiet i deklaracje pozycji, ani jednego typu
 * z `Presentation` — a chodzi po niej `SettingsCursor`, który mieszka tutaj.
 * Rozdzielenie ich kazałoby kursorowi sięgnąć po warstwę zewnętrzną.
 *
 * Podział zakładek rdzenia jest tematyczny, nie alfabetyczny: „Wygląd” zbiera
 * to, co widać od razu, „Grafika” — przełączniki potoku Sixela, których skutek
 * widać dopiero po przyjrzeniu się klatce.
 */
final class SettingsTab
{
    /**
     * @param list<SettingKey>    $keys     pozycje zakładki rdzenia
     * @param list<ModuleSetting> $settings pozycje zakładki modułu
     */
    private function __construct(
        public readonly SettingsTabKind $kind,
        public readonly string $labelKey,
        public readonly array $keys = [],
        public readonly array $settings = [],
        /** Identyfikator modułu — pusty dla zakładek rdzenia. */
        public readonly string $moduleId = '',
        /** Ile wierszy ma spis modułów; poza zakładką `Modules` bez znaczenia. */
        private readonly int $moduleCount = 0,
    ) {
    }

    /** @param list<SettingKey> $keys */
    public static function core(string $labelKey, array $keys): self
    {
        return new self(SettingsTabKind::Core, $labelKey, $keys);
    }

    /**
     * Spis modułów wraz z ich przełącznikami — zakładka rdzenia, ale nie
     * o ustawieniach.
     *
     * Dokładana **zawsze**, także przy zerze modułów: pusty spis mówi wprost, że
     * mechanizm istnieje, a jego brak kazałby się domyślać, czy moduły są
     * wyłączone, czy nie ma ich w tej wersji wcale.
     */
    public static function modules(int $moduleCount = 0): self
    {
        return new self(SettingsTabKind::Modules, 'settings.tab.modules', moduleCount: $moduleCount);
    }

    public static function ofModule(string $moduleId, ModuleSettingsTab $tab): self
    {
        return new self(SettingsTabKind::Module, $tab->labelKey, [], $tab->settings, $moduleId);
    }

    /**
     * Zakładki rdzenia — dwie, w kolejności, w jakiej stały od kroku 14.
     *
     * @return list<self>
     */
    public static function coreTabs(): array
    {
        return [
            self::core('settings.tab.appearance', [
                SettingKey::Language,
                SettingKey::Theme,
                SettingKey::ShowHiddenEntries,
            ]),
            self::core('settings.tab.graphics', [
                SettingKey::TextAntialias,
                SettingKey::StrokeAntialias,
                SettingKey::PaletteColors,
            ]),
        ];
    }

    /**
     * Czy pod pozycjami stoi wiersz czynności.
     *
     * Przycisk „przywróć ustawienia domyślne” przywraca **ustawienia rdzenia**,
     * więc stoi wyłącznie pod zakładkami rdzenia. Postawiony pod zakładką modułu
     * obiecywałby, że przywraca ją — czego nie robi.
     */
    public function hasAction(): bool
    {
        return $this->kind === SettingsTabKind::Core;
    }

    /** Ile pozycji ma zakładka **bez** wiersza czynności. */
    public function itemCount(): int
    {
        return match ($this->kind) {
            SettingsTabKind::Core => count($this->keys),
            SettingsTabKind::Module => count($this->settings),
            SettingsTabKind::Modules => $this->moduleCount,
        };
    }

    /** Ustawienie rdzenia na wskazanej pozycji; `null` poza zakładką rdzenia. */
    public function keyAt(int $item): ?SettingKey
    {
        return $this->keys[$item] ?? null;
    }

    /** Pozycja modułu na wskazanym miejscu; `null` poza zakładką modułu. */
    public function settingAt(int $item): ?ModuleSetting
    {
        return $this->settings[$item] ?? null;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->labelKey === $other->labelKey
            && $this->moduleId === $other->moduleId;
    }
}
