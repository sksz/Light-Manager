<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Presentation\Ui\SplitState;

/**
 * Proporcja podziału jako pozycja ustawień modułu (krok 55).
 *
 * Klasa istnieje, żeby pięć modułów z podziałem nie przepisało pięć razy tego
 * samego: deklaracji pozycji, odczytu wartości i zapisu po przeciągnięciu
 * granicy. Reguła 11c mówi, że **ustawienia podziału należą do modułu** — i tak
 * zostaje: klucz leży w podprzestrzeni `modules.<id>`, napis w katalogu modułu,
 * a wartość domyślna bywa różna (moduł Kubernetesa dzieli 40/60, bo drzewo
 * rodzajów zasobów jest węższe od listy). Wspólny jest sam **mechanizm**, a nie
 * pozycja — dokładnie tak, jak każe reguła 15e.
 *
 * Leży w `Presentation/Cli`, a nie w `Presentation/Ui`, bo zna `LoopState`
 * i przypadek użycia zapisu — czyli te same rzeczy, które znają komendy modułów.
 */
final class SplitSetting
{
    /** Klucz w podprzestrzeni `modules.<id>`. */
    public const KEY = 'splitFraction';

    /**
     * Przystanki co jeden procent.
     *
     * Krok co pięć byłby krótszą listą i został odrzucony: przy najwęższym
     * dopuszczalnym podziale (`Split::MINIMUM_COLUMNS` = 72) pięć procent to
     * blisko cztery kolumny, więc granica skakałaby pod ręką zamiast za nią iść.
     * Strzałka w ustawieniach przesuwa przez to o procent — i to jest cena
     * gładkiego przeciągania, przyjęta świadomie.
     */
    private const MINIMUM_PERCENT = 20;

    private const MAXIMUM_PERCENT = 80;

    public static function declaration(string $moduleId, int $defaultPercent): ModuleSetting
    {
        return ModuleSetting::number(
            self::KEY,
            'module.' . $moduleId . '.setting.' . self::KEY,
            range(self::MINIMUM_PERCENT, self::MAXIMUM_PERCENT),
            $defaultPercent,
        );
    }

    /**
     * Stan podziału gotowy do użycia: proporcja wczytana z ustawień plus zapis
     * po zwolnieniu przycisku.
     */
    public static function state(
        string $moduleId,
        int $defaultPercent,
        LoopState $state,
        ChangeModuleSettingUseCase $change,
    ): SplitState {
        $declaration = self::declaration($moduleId, $defaultPercent);

        return new SplitState(
            self::fraction($state->settings(), $moduleId, $defaultPercent),
            static function (int $percent) use ($moduleId, $declaration, $state, $change): void {
                [$settings] = $change->put($state->settings(), $moduleId, $declaration, $percent);
                $state->applySettings($settings);
            },
        );
    }

    /**
     * Proporcja zapisana w ustawieniach — czytana **co klatkę**, bo tę samą
     * pozycję zmieniają też strzałki w zakładce ustawień. `SplitState` pomija
     * ją w trakcie przeciągania, więc jedno nie walczy z drugim.
     */
    public static function fraction(Settings $settings, string $moduleId, int $defaultPercent): float
    {
        $percent = self::declaration($moduleId, $defaultPercent)
            ->valueFrom($settings->moduleValue($moduleId, self::KEY));

        return (is_int($percent) ? $percent : $defaultPercent) / 100;
    }
}
