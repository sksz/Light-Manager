<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Module\ModuleRegistry;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;
use LogicException;

/**
 * Który ekran staje na dnie stosu przy starcie — i co powiedzieć, gdy nie ten,
 * o który prosiła konfiguracja.
 *
 * Do kroku 20 odpowiedź była wpisana w kod: dnem była przeglądarka plików, bo
 * `ScreenStack` miał ją w konstruktorze. Od kroku 21 wskazuje je klucz
 * `startupModule`, a kod ma tylko jedną nazwę modułu — **ostatniej szansy** —
 * i dostaje ją z zewnątrz.
 *
 * Klasa istnieje osobno, a nie jako metoda `Bootstrapu`, bo ma cztery drogi
 * awaryjne i każda z nich prowadzi do innej poprawki po stronie użytkownika.
 * Cztery gałęzie warte czterech testów nie mieszczą się w metodzie prywatnej
 * procedury startowej, której nie da się wywołać bez terminala i Imagicka.
 */
final class StartupScreen
{
    private function __construct(
        public readonly ScreenInterface $screen,
        /** Klucz komunikatu; `null`, gdy dnem został moduł, o który proszono. */
        public readonly ?string $problemKey,
        /** Identyfikator z konfiguracji — parametr `{module}` komunikatu. */
        public readonly string $requested,
    ) {
    }

    /**
     * @param string $requested  wartość klucza `startupModule`
     * @param string $lastResort identyfikator modułu, do którego się wraca
     *
     * @throws LogicException gdy moduł ostatniej szansy nie istnieje albo nie
     *                        wnosi ekranu — to błąd w liście modułów `Bootstrapu`,
     *                        czyli błąd programistyczny, nie sytuacja użytkownika
     */
    public static function choose(ModuleRegistry $modules, string $requested, string $lastResort): self
    {
        $module = $modules->find($requested);

        if ($module instanceof ProvidesScreen) {
            return new self($module->screen(), null, $requested);
        }

        return new self(
            self::lastResortScreen($modules, $lastResort),
            self::problemKey($modules, $requested),
            $requested,
        );
    }

    /**
     * Przyczyna, dla której moduł domyślny nie stanął na dnie.
     *
     * Kolejność sprawdzeń jest kolejnością przyczyn, a nie przypadkiem, i wynika
     * z tego, jak działa rejestr: moduł nieobecny na liście w `Bootstrapie` nie ma
     * jak być wyłączony, a moduł wyłączony **nie jest przez rejestr sprawdzany**,
     * więc nie ma jak być zarazem odrzucony. Przypadki się nie nakładają i dlatego
     * komunikat mówi o jednej przyczynie, a nie o dwóch naraz.
     */
    private static function problemKey(ModuleRegistry $modules, string $requested): string
    {
        if (!self::isDeclared($modules, $requested)) {
            return 'module.startup.unknown';
        }

        if (!$modules->isEnabled($requested)) {
            return 'module.startup.disabled';
        }

        if ($modules->rejectionOf($requested) !== null) {
            return 'module.startup.rejected';
        }

        return 'module.startup.screenless';
    }

    private static function isDeclared(ModuleRegistry $modules, string $id): bool
    {
        foreach ($modules->declared() as $module) {
            if ($module->id() === $id) {
                return true;
            }
        }

        return false;
    }

    private static function lastResortScreen(ModuleRegistry $modules, string $lastResort): ScreenInterface
    {
        $module = $modules->find($lastResort);

        if (!$module instanceof ProvidesScreen) {
            throw new LogicException(sprintf(
                'The last resort module "%s" is missing or provides no screen.',
                $lastResort,
            ));
        }

        return $module->screen();
    }
}
