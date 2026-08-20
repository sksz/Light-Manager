<?php

declare(strict_types=1);

namespace LightManager\Examples\ZadanieKwerenda\Rozwiazanie\Presentation;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Examples\ZadanieKwerenda\Rozwiazanie\Presentation\Query\CzasDzialaniaQuery;
use LightManager\Presentation\Cli\LoopState;

/**
 * Moduł zadania ćwiczebnego z onboardingu — **rozwiązanie**
 * (`docs/pl/onboarding/04-pierwsza-zmiana.md`).
 *
 * Wobec pliku startowego ([`start/`](../../start/)) **ten plik nie zmienił się
 * ani o znak** — bo luka zadania stała w kwerendzie, nie w module. Leży tutaj
 * po to, żeby rozwiązanie dało się skopiować w całości, a nie składać z dwóch
 * katalogów.
 *
 * Trzy rzeczy, które warto tu zobaczyć:
 *
 * 1. **Moduł bez ekranu oddaje `null` ze `shortcut()`.** Skrót bez okna, które
 *    miałby otworzyć, byłby obietnicą bez pokrycia — a rejestr modułów odrzuca
 *    moduł, którego litera koliduje z cudzą, w całości.
 * 2. **Czas przychodzi z zewnątrz.** Moduł nie woła `microtime()` sam: moment
 *    startu dostaje w konstruktorze, a chwilę bieżącą oddaje domknięciem.
 *    Kwerenda z własnym zegarem przestaje być testowalna, a projekt trzyma tę
 *    zasadę od komponentów po pasek postępu.
 * 3. **Napisów w kodzie nie ma ani jednego.** `nameKey()` i `descriptionKey()`
 *    oddają klucze katalogu; katalog leży w [`../lang/`](../lang/).
 *
 * Pełny wzorzec modułu — z komendą, ustawieniem i drugą zdolnością — stoi
 * w [`examples/modul-przykladowy/`](../../../modul-przykladowy/).
 */
final class CzasModule implements ModuleInterface, ProvidesQueries
{
    /**
     * Jeden napis w trzech rolach: klucz konfiguracji (`modules.czas`),
     * przedrostek napisów (`module.czas.`) i przestrzeń nazw kwerend (`czas.`).
     */
    public const ID = 'czas';

    public function __construct(
        private readonly LoopState $state,
        /** Moment startu aplikacji — podaje go `Bootstrap`, bo to on wie, kiedy start nastąpił. */
        private readonly float $started,
    ) {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . self::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . self::ID . '.description';
    }

    /** Modułu bez ekranu nie ma czym otworzyć — i to jest poprawna odpowiedź. */
    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function queries(): array
    {
        return [new CzasDzialaniaQuery($this->started, fn (): float => $this->state->now())];
    }
}
