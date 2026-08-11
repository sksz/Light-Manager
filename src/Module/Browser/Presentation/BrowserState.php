<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\LoopState;

/**
 * Bieżący katalog modułu przeglądarki wraz z publikacją kontekstu sesji.
 *
 * Do kroku 20 katalog leżał w `LoopState` — czyli w stanie **powłoki**, mimo że
 * jest stanem jednej konkretnej funkcji. Krok 21 wyprowadza go tutaj i zostawia
 * powłoce to, co naprawdę do niej należy: ustawienia, komunikat, okna nakładane,
 * czas klatki i kontekst sesji.
 *
 * **Publikowanie kontekstu ma jedno miejsce i to jest cały powód istnienia tej
 * klasy.** Katalog zmieniają dwie rzeczy: klawisze ekranu i komenda `browser.jump`.
 * Gdyby ekran publikował kontekst w `draw()`, a komenda w `execute()`, kontekst
 * zacząłby się rozjeżdżać o klatkę przy pierwszym module o dwóch wejściach.
 * Zmiana przechodzi więc przez ten obiekt, a publikacja jest tam, gdzie zmiana.
 *
 * Klasa leży w warstwie `Presentation` modułu, bo dostaje `LoopState` — obiekt
 * warstwy dostarczania. Ta sama zasada, która w kroku 20 postawiła `JumpCommand`
 * w `Presentation` modułu, a nie w jego `Application` (D41).
 */
final class BrowserState
{
    public function __construct(
        private readonly LoopState $state,
        private Directory $directory,
    ) {
        $this->publish();
    }

    public function directory(): Directory
    {
        return $this->directory;
    }

    /** Wejście do innego katalogu — jedyna droga, którą katalog się zmienia. */
    public function enter(Directory $directory): void
    {
        $this->directory = $directory;
        $this->publish();
    }

    /**
     * Zaznaczenie zmieniło się **w** katalogu, więc obiekt jest ten sam, a
     * kontekst już nie. Agregat jest mutowalny w miejscu, więc bez tego wywołania
     * nikt by się o zmianie nie dowiedział.
     */
    public function selectionChanged(): void
    {
        $this->publish();
    }

    /**
     * Widoczność wpisów ukrytych czytana z ustawień, nie z własnego pola: jedno
     * miejsce prawdy zamiast dwóch, które musiałyby się pilnować nawzajem.
     */
    public function showsHiddenEntries(): bool
    {
        return BrowserSettings::showHidden($this->state->settings());
    }

    private function publish(): void
    {
        $entry = $this->directory->selectedEntry();

        $this->state->publishContext(new ModuleContext(
            $this->directory->path()->value,
            $entry?->name,
            self::kindOf($entry),
        ));
    }

    private static function kindOf(?Entry $entry): ContextEntryKind
    {
        if ($entry === null) {
            return ContextEntryKind::None;
        }

        return $entry->isDirectory() ? ContextEntryKind::Directory : ContextEntryKind::File;
    }
}
