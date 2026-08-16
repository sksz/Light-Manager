<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;

/**
 * Zmienia jedną pozycję w zakładce ustawień modułu i od razu zapisuje całość.
 *
 * Osobny przypadek użycia obok `ChangeSettingUseCase`, a nie jego gałąź, bo
 * różni się wszystkim poza zapisem: pozycję opisuje **deklaracja** (`ModuleSetting`),
 * a nie enum, wartość leży w podprzestrzeni modułu, a nie w polu obiektu
 * konfiguracji, i dochodzi walidacja wzorca, której ustawienia rdzenia nie mają.
 *
 * **Walidację robi rdzeń według deklaracji** (P13): moduł nie dostaje wywołania
 * zwrotnego przy zmianie. Dzięki temu deklaracja pozycji pozostaje czystymi
 * danymi — dającymi się porównać, zapisać i pokazać — a nie kodem, który trzeba
 * uruchomić, żeby się dowiedzieć, co pozycja przyjmuje.
 */
final class ChangeModuleSettingUseCase
{
    public function __construct(
        private readonly SettingsPort $settings,
        private readonly TranslatorPort $translator,
    ) {
    }

    /**
     * Sąsiednia wartość — to, co robi strzałka pozioma na pozycji.
     *
     * @return array{Settings, Message|null}
     */
    public function shift(Settings $current, string $moduleId, ModuleSetting $setting, int $direction): array
    {
        $value = $setting->valueFrom($current->moduleValue($moduleId, $setting->key));

        return $this->stored($current, $moduleId, $setting->key, $setting->shifted($value, $direction));
    }

    /**
     * Wartość wpisana ręcznie — droga pozycji tekstowej.
     *
     * Wartość niezgodna z deklaracją **nie nadpisuje poprzedniej**: wraca
     * komunikatem, a ustawienie zostaje takie, jakie było. Użytkownik widzi
     * wtedy w polu to, co miał, a nie pustkę po odrzuconej próbie.
     *
     * @return array{Settings, Message|null}
     */
    public function set(Settings $current, string $moduleId, ModuleSetting $setting, string $value): array
    {
        if (!$setting->accepts($value)) {
            return [$current, Message::error($this->translator->translate('module.setting.invalid', [
                'name' => $this->translator->translate($setting->labelKey),
            ]))];
        }

        return $this->stored($current, $moduleId, $setting->key, $value);
    }

    /**
     * Wartość podana wprost — droga pozycji, którą ustawia **czynność**, a nie
     * strzałka ani pole tekstowe (krok 55: proporcja podziału po przeciągnięciu
     * granicy myszą).
     *
     * Wartość przechodzi przez `valueFrom()`, więc spoza listy wraca do
     * domyślnej — tą samą drogą, którą przechodzi wartość wczytana z pliku
     * ruszonego ręcznie. Wołający nie ma jak podać czegoś, czego deklaracja nie
     * przewiduje.
     *
     * @return array{Settings, Message|null}
     */
    public function put(Settings $current, string $moduleId, ModuleSetting $setting, bool|int|string $value): array
    {
        return $this->stored($current, $moduleId, $setting->key, $setting->valueFrom($value));
    }

    /**
     * Przełącznik „włączony” ze spisu modułów.
     *
     * Zapisuje się natychmiast, jak każde inne ustawienie, ale skutek widać
     * **po ponownym uruchomieniu**: mapa skrótów, lista ekranów i lista zakładek
     * powstają raz, przy starcie. Komunikat mówi o tym wprost, zamiast zostawiać
     * użytkownika z wrażeniem, że przełącznik nie działa.
     *
     * @return array{Settings, Message|null}
     */
    public function enable(Settings $current, string $moduleId, bool $enabled): array
    {
        [$settings, $problem] = $this->stored($current, $moduleId, ModuleRegistry::ENABLED_KEY, $enabled);

        if ($problem !== null) {
            return [$settings, $problem];
        }

        return [$settings, Message::info($this->translator->translate('module.restart'))];
    }

    /** @return array{Settings, Message|null} */
    private function stored(Settings $current, string $moduleId, string $key, bool|int|string $value): array
    {
        $changed = $current->withModuleValue($moduleId, $key, $value);

        if ($changed->equals($current)) {
            return [$current, null];
        }

        $problem = $this->settings->save($changed);

        // Nieudany zapis nie cofa zmiany: ustawienie działa do końca tego
        // uruchomienia, a użytkownik wie, że nie przetrwa następnego — ta sama
        // reguła, którą kieruje się `ChangeSettingUseCase`.
        return $problem === null ? [$changed, null] : [$changed, Message::error($problem)];
    }
}
