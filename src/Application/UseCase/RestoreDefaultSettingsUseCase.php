<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;

/**
 * Przywraca wartości domyślne wszystkich ustawień i zapisuje je na dysk.
 *
 * Pierwsza czynność aplikacji uruchamiana **przyciskiem**, a nie zmianą wartości
 * pod kursorem (krok 18, P12). Powstała po to, by komponent `Button` miał
 * prawdziwego użytkownika, zamiast czekać na hipotetycznego — ale sama w sobie
 * jest funkcją, której ekranowi ustawień brakowało: po zabłądzeniu w motywach
 * i palecie nie było jak wrócić do stanu wyjściowego inaczej niż przez skasowanie
 * pliku konfiguracyjnego.
 *
 * Motyw wchodzi w życie od razu, tak samo jak przy zwykłej zmianie ustawienia.
 */
final class RestoreDefaultSettingsUseCase
{
    public function __construct(
        private readonly SettingsPort $settings,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** @return array{Settings, Message} nowe ustawienia i komunikat do paska stanu */
    public function execute(Settings $current): array
    {
        $defaults = new Settings();

        if ($defaults->equals($current)) {
            return [$current, Message::info($this->translator->translate('settings.restore.unchanged'))];
        }

        // Motyw i wygładzanie wchodzą w życie same: `RenderingOptions` czyta je
        // z ustawień przy każdej klatce, więc nie ma tu czego przełączać.
        $problem = $this->settings->save($defaults);

        if ($problem !== null) {
            // Nieudany zapis nie cofa zmiany: ustawienia działają do końca tego
            // uruchomienia, a użytkownik wie, że nie przetrwają następnego.
            return [$defaults, Message::error($problem)];
        }

        return [$defaults, Message::info($this->translator->translate('settings.restore.done'))];
    }
}
