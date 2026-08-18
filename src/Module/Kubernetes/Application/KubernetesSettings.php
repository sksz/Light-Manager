<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu klastra (krok 52).
 *
 * **Każda pozycja z rozstrzygnięcia**, nie z upodobania: limit czasu (D91 nr 6),
 * odstęp odświeżania (nr 7), liczba wierszy logu trzymanych w pamięci i limit
 * czekania na cudzą budowę (D94 nr 5).
 *
 * Liczby są **wartościami z listy przystanków**, bo `ModuleSetting::valueFrom()`
 * sprowadza wartość spoza listy do domyślnej — wpisane ręcznie 7 sekund
 * przepadłoby przy pierwszym odczycie.
 *
 * **Zapamiętane miejsce wyszło stąd w kroku 59**: kontekst i przestrzeń nazw
 * mieszkają w książce klastrów, bo miejsce ma dwie współrzędne i własną
 * tożsamość. Klucze zostają jako źródło jednorazowej migracji.
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

    /** Klucz zapamiętanego kontekstu — **wyłącznie źródło migracji** (krok 59). */
    public const CONTEXT = 'context';

    /** Klucz zapamiętanej przestrzeni — **wyłącznie źródło migracji** (krok 59). */
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

    /** Ile sekund czekać na koniec cudzej budowy — `k8s.deploy-image` (krok 54, D94 nr 5). */
    public const BUILD_WAIT = 'buildWaitSeconds';

    /**
     * Przystanki limitu czekania.
     *
     * Zaczynają się od **minuty**, bo budowa obrazu krótsza od minuty zdarza się
     * wyłącznie wtedy, gdy wszystko jest w pamięci podręcznej demona, a kończą na
     * **pół godzinie**, bo dłuższa budowa jest zwykle pomyłką w `Dockerfile`,
     * a nie cierpliwością wartą nagrody. Upływ limitu **nie przerywa budowy** —
     * ta należy do modułu Dockera i trwa dalej u siebie (D94 nr 5); kończy się
     * wyłącznie czekanie.
     *
     * @var list<int>
     */
    public const BUILD_WAIT_CHOICES = [60, 300, 600, 1800];

    public const DEFAULT_BUILD_WAIT = 600;

    /**
     * Cztery pozycje — **dwie mniej niż do kroku 59**.
     *
     * `context` i `namespace` wyszły z zakładki do książki klastrów (plan
     * punkt 7): miejsce ma dwie współrzędne i własną tożsamość, więc dwie
     * pozycje, których użytkownik nie przestawiał strzałkami, były obejściem
     * braku książki, a nie ustawieniami. Klucze **zostają w kodzie** jako
     * źródło jednorazowej migracji — czyta je `Clusters::migrate()`.
     *
     * @return list<ModuleSetting>
     */
    public static function declarations(): array
    {
        return [
            self::timeoutDeclaration(),
            self::refreshDeclaration(),
            self::logLinesDeclaration(),
            self::buildWaitDeclaration(),
        ];
    }

    public static function buildWaitFrom(Settings $settings): int
    {
        $value = self::buildWaitDeclaration()->valueFrom($settings->moduleValue(self::ID, self::BUILD_WAIT));

        return is_int($value) ? $value : self::DEFAULT_BUILD_WAIT;
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

    /** Zapamiętany kontekst sprzed kroku 59; pusty napis — nie było czego pamiętać. */
    public static function contextFrom(Settings $settings): string
    {
        $value = $settings->moduleValue(self::ID, self::CONTEXT);

        return is_string($value) ? $value : '';
    }

    /** Zapamiętana przestrzeń sprzed kroku 59; pusty napis — nie było czego pamiętać. */
    public static function namespaceFrom(Settings $settings): string
    {
        $value = $settings->moduleValue(self::ID, self::NAMESPACE);

        return is_string($value) ? $value : '';
    }

    private static function buildWaitDeclaration(): ModuleSetting
    {
        return ModuleSetting::number(
            self::BUILD_WAIT,
            'module.' . self::ID . '.setting.' . self::BUILD_WAIT,
            self::BUILD_WAIT_CHOICES,
            self::DEFAULT_BUILD_WAIT,
        );
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
