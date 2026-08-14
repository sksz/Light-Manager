<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application\UseCase;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Port\SettingsPort;
use LightManager\Module\Audio\Application\AudioSettings;

/**
 * Zapis głośności wybranej komendą (krok 36).
 *
 * Przypadek użycia **modułu**, a nie rdzenia, i jest ku temu konkretny powód:
 * rdzeniowy `ChangeModuleSettingUseCase` zapisuje wartość pozycji tekstowej,
 * czyli **napis**, a głośność jest liczbą — zapisana napisem wróciłaby z pliku
 * jako wartość nieodpowiedniego typu i `ModuleSetting::valueFrom()` sprowadziłby
 * ją do domyślnej. Strzałki na zakładce idą dalej tamtą drogą (`shift()`), bo
 * one przesuwają się po liście, a nie wpisują wartość.
 *
 * Wartość jest sprawdzona **przed** wejściem tutaj — listą przystanków z
 * deklaracji. Tu zostaje sam zapis: podprzestrzeń modułu i dysk.
 */
final class ChangeVolumeUseCase
{
    public function __construct(
        private readonly SettingsPort $settings,
    ) {
    }

    /**
     * @return array{Settings, string|null} ustawienia po zmianie oraz opis
     *                                      problemu, gdy zapis się nie powiódł
     */
    public function execute(Settings $current, int $volume): array
    {
        $changed = $current->withModuleValue(AudioSettings::ID, AudioSettings::VOLUME, $volume);

        if ($changed->equals($current)) {
            return [$current, null];
        }

        // Nieudany zapis nie cofa zmiany: głośność działa do końca tego
        // uruchomienia, a użytkownik wie, że nie przetrwa następnego — ta sama
        // reguła, którą kieruje się zapis ustawień rdzenia.
        return [$changed, $this->settings->save($changed)];
    }
}
