<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\ThemePort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;

/**
 * Zmienia jedno ustawienie i od razu zapisuje całość na dysk.
 *
 * Zapis po każdej zmianie, a nie przy wyjściu z ekranu: ustawienie ma przeżyć
 * zabicie procesu sygnałem, a plik jest mały i zapisywany niepodzielnie, więc
 * częstotliwość niczego nie kosztuje.
 *
 * Wynik niesie nie tylko nowe wartości, ale i komunikat do paska stanu —
 * ostrzeżenie o palecie poniżej progu albo powód, dla którego zapis się nie
 * udał. To jedyne miejsce, w którym te dwa zdarzenia się spotykają, więc tu
 * rozstrzyga się, które z nich jest ważniejsze.
 */
final class ChangeSettingUseCase
{
    public function __construct(
        private readonly SettingsPort $settings,
        private readonly ThemePort $themes,
        private readonly TranslatorPort $translator,
    ) {
    }

    /**
     * @param int $direction kierunek zmiany: dodatni w prawo, ujemny w lewo
     *
     * @return array{Settings, Message|null} nowe ustawienia i komunikat, jeśli jest o czym mówić
     */
    public function execute(Settings $current, SettingKey $key, int $direction): array
    {
        $changed = $current->shifted($key, $direction, $this->themes->names());

        if ($changed->equals($current)) {
            return [$current, null];
        }

        $problem = $this->settings->save($changed);

        if ($problem !== null) {
            // Nieudany zapis nie cofa zmiany: ustawienie działa do końca tego
            // uruchomienia, a użytkownik wie, że nie przetrwa następnego.
            return [$changed, Message::error($problem)];
        }

        return [$changed, $this->warningFor($changed, $key)];
    }

    /**
     * Ustawia wartość **wskazaną**, a nie sąsiednią — droga dla komend
     * (`core.theme grafit`), które nie mają jak „przesunąć o krok”.
     *
     * Wartość spoza zakresu nie zmienia niczego i wraca komunikatem: komenda
     * dostaje nazwę od użytkownika, więc literówka jest tu regułą, a nie
     * wyjątkiem — inaczej niż na ekranie ustawień, gdzie strzałka nie potrafi
     * wyjść poza listę.
     *
     * @return array{Settings, Message|null}
     */
    public function set(Settings $current, SettingKey $key, string $value): array
    {
        $changed = $this->applied($current, $key, $value);

        if ($changed === null) {
            return [$current, Message::error(
                $this->translator->translate('settings.value.unknown', ['value' => $value]),
            )];
        }

        if ($changed->equals($current)) {
            return [$current, null];
        }

        $problem = $this->settings->save($changed);

        return $problem === null
            ? [$changed, $this->warningFor($changed, $key)]
            : [$changed, Message::error($problem)];
    }

    /**
     * `null` znaczy „wartość spoza zakresu”.
     *
     * Klucze prawda/fałsz i liczbowe nie mają dziś komendy, a ich brzmienie
     * w wierszu („tak”? „1”? „true”?) nie jest niczym ustalone — doczekają
     * swojego użytkownika, zamiast dostać zapis wymyślony na zapas.
     */
    private function applied(Settings $current, SettingKey $key, string $value): ?Settings
    {
        return match ($key) {
            SettingKey::Language => Language::tryFrom($value) === null ? null : $current->withLanguage($value),
            SettingKey::Theme => in_array($value, $this->themes->names(), true)
                ? $current->withTheme($value)
                : null,
            default => null,
        };
    }

    /**
     * Paleta poniżej progu zostaje dostępna, ale nie po cichu: przy 16 i 32
     * kolorach kwantyzator poświęca odcień obwódki i panele znikają z klatki
     * ([00-decyzje.md](../../../docs/plans/00-decyzje.md), D27). Użytkownik ma
     * prawo tak wybrać — ma tylko wiedzieć, co kupuje.
     */
    private function warningFor(Settings $settings, SettingKey $key): ?Message
    {
        if ($key !== SettingKey::PaletteColors || $settings->paletteColors >= Settings::SAFE_PALETTE_COLORS) {
            return null;
        }

        return Message::warning(
            $this->translator->translate(
                'settings.palette.warning',
                ['colors' => Settings::SAFE_PALETTE_COLORS],
            ),
        );
    }
}
