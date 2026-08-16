<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu klastra (krok 52).
 *
 * **Cztery pozycje i każda z rozstrzygnięcia**, nie z upodobania: limit czasu
 * (D91 nr 6), odstęp odświeżania (nr 7), liczba wierszy logu trzymanych
 * w pamięci oraz zapamiętane miejsce — kontekst i przestrzeń nazw, których plan
 * kroku żąda wprost („oba zapamiętywane w ustawieniach modułu”).
 *
 * Liczby są **wartościami z listy przystanków**, bo `ModuleSetting::valueFrom()`
 * sprowadza wartość spoza listy do domyślnej — wpisane ręcznie 7 sekund
 * przepadłoby przy pierwszym odczycie. Kontekst i przestrzeń są napisami i to
 * jedyne dwie pozycje tego modułu, których użytkownik nie przestawia strzałkami
 * w ustawieniach, tylko **wyborem na ekranie**; zakładka pokazuje je, bo
 * pokazanie zapamiętanego miejsca jest tańsze niż tłumaczenie, gdzie ono siedzi.
 */
final class KubernetesSettings
{
    public const ID = 'k8s';

    /** Po ilu sekundach `kubectl` przestaje czekać — na serwer i w ogóle. */
    public const TIMEOUT = 'timeoutSeconds';

    /** Co ile sekund odświeża się lista, gdy ekran modułu jest widoczny. */
    public const REFRESH = 'refreshSeconds';

    /** Ile wierszy logu trzymamy, zanim najstarsze zaczną wypadać. */
    public const LOG_LINES = 'logLines';

    public const CONTEXT = 'context';

    public const NAMESPACE = 'namespace';

    /**
     * Przystanki limitu czasu.
     *
     * Zaczynają się od dwóch sekund, bo poniżej tego progu odpowiedź nie zdąży
     * przyjść nawet z klastra stojącego na tej samej maszynie (zmierzone
     * w kroku 49 na pętli zwrotnej: samo otwarcie kanału to blisko sekunda).
     *
     * @var list<int>
     */
    public const TIMEOUT_CHOICES = [2, 5, 10, 30, 60];

    public const DEFAULT_TIMEOUT = 10;

    /**
     * Przystanki odstępu odświeżania.
     *
     * Najkrótszy jest **dwa razy dłuższy niż w module Dockera** (tam pięć sekund,
     * D90 nr 7) i to nie z ostrożności: tam odświeżenie jest nieblokującym
     * pytaniem po gnieździe unixowym, tutaj — **procesem potomnym**, który trzeba
     * uruchomić, poczekać na odpowiedź klastra i pochować.
     *
     * @var list<int>
     */
    public const REFRESH_CHOICES = [10, 30, 60, 300];

    public const DEFAULT_REFRESH = 30;

    /** @var list<int> */
    public const LOG_LINES_CHOICES = [500, 1000, 2000, 5000];

    public const DEFAULT_LOG_LINES = 1000;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            self::timeoutDeclaration(),
            self::refreshDeclaration(),
            self::logLinesDeclaration(),
            ModuleSetting::text(self::CONTEXT, 'module.' . self::ID . '.setting.' . self::CONTEXT, ''),
            ModuleSetting::text(self::NAMESPACE, 'module.' . self::ID . '.setting.' . self::NAMESPACE, ''),
        ];
    }

    public static function timeoutFrom(Settings $settings): int
    {
        $value = self::timeoutDeclaration()->valueFrom($settings->moduleValue(self::ID, self::TIMEOUT));

        return is_int($value) ? $value : self::DEFAULT_TIMEOUT;
    }

    public static function refreshFrom(Settings $settings): int
    {
        $value = self::refreshDeclaration()->valueFrom($settings->moduleValue(self::ID, self::REFRESH));

        return is_int($value) ? $value : self::DEFAULT_REFRESH;
    }

    public static function logLinesFrom(Settings $settings): int
    {
        $value = self::logLinesDeclaration()->valueFrom($settings->moduleValue(self::ID, self::LOG_LINES));

        return is_int($value) ? $value : self::DEFAULT_LOG_LINES;
    }

    /** Zapamiętany kontekst; pusty napis znaczy „ten, który wskazuje `kubeconfig`”. */
    public static function contextFrom(Settings $settings): string
    {
        $value = $settings->moduleValue(self::ID, self::CONTEXT);

        return is_string($value) ? $value : '';
    }

    /** Zapamiętana przestrzeń nazw; pusty napis znaczy „ta z kontekstu”. */
    public static function namespaceFrom(Settings $settings): string
    {
        $value = $settings->moduleValue(self::ID, self::NAMESPACE);

        return is_string($value) ? $value : '';
    }

    private static function timeoutDeclaration(): ModuleSetting
    {
        return ModuleSetting::number(
            self::TIMEOUT,
            'module.' . self::ID . '.setting.' . self::TIMEOUT,
            self::TIMEOUT_CHOICES,
            self::DEFAULT_TIMEOUT,
        );
    }

    private static function refreshDeclaration(): ModuleSetting
    {
        return ModuleSetting::number(
            self::REFRESH,
            'module.' . self::ID . '.setting.' . self::REFRESH,
            self::REFRESH_CHOICES,
            self::DEFAULT_REFRESH,
        );
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
