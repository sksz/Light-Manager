<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu Dockera (krok 51).
 *
 * Deklaracja i odczyt stoją obok siebie, wzorem `SshSettings` i `AudioSettings`
 * — są dwiema stronami tej samej umowy i rozdzielone rozjechałyby się przy
 * pierwszej zmianie listy wartości.
 *
 * **Pozycja jest jedna** i to jest liczba wynikająca z rozstrzygnięć startowych,
 * a nie z oszczędności: granicę bufora logów użytkownik ustawił jako pozycję
 * (D90 nr 3), a częstotliwość odświeżania — świadomie **nie** (D90 nr 7),
 * bo druga pozycja w kroku, który i tak jest największy w projekcie, kupowałaby
 * niewiele. Ścieżki pliku compose tu nie ma z trzeciego powodu: bierze się
 * z kontekstu i z pola tekstowego (D90 nr 5), a wartość zapisana w ustawieniach
 * byłaby **trzecią drogą do tej samej rzeczy**.
 */
final class DockerSettings
{
    public const ID = 'docker';

    /** Ile wierszy logu trzymamy w pamięci, zanim najstarsze zaczną wypadać. */
    public const LOG_LINES = 'logLines';

    /**
     * Przystanki, po których chodzą strzałki.
     *
     * Wartość jest **liczbą z listy, a nie dowolną z zakresu**, i wynika to
     * wprost z kontraktu ustawień modułu: `ModuleSetting::valueFrom()` sprowadza
     * wartość spoza listy do domyślnej, więc zapisane 1500 przepadłoby przy
     * pierwszym odczycie.
     *
     * @var list<int>
     */
    public const LOG_LINES_CHOICES = [500, 1000, 2000, 5000, 10000];

    public const DEFAULT_LOG_LINES = 2000;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [self::logLinesDeclaration()];
    }

    public static function logLinesFrom(Settings $settings): int
    {
        $value = self::logLinesDeclaration()->valueFrom($settings->moduleValue(self::ID, self::LOG_LINES));

        return is_int($value) ? $value : self::DEFAULT_LOG_LINES;
    }

    private static function logLinesDeclaration(): ModuleSetting
    {
        return ModuleSetting::number(
            self::LOG_LINES,
            'module.' . self::ID . '.setting.' . self::LOG_LINES,
            self::LOG_LINES_CHOICES,
            self::DEFAULT_LOG_LINES,
        );
    }
}
