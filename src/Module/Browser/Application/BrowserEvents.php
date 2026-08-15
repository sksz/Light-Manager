<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application;

use LightManager\Application\Event\EventRegistry;
use LightManager\Domain\ValueObject\Message;
use LightManager\Domain\ValueObject\MessageTone;

/**
 * Publikowanie zdarzeń przeglądarki — jedno miejsce zamiast czternastu
 * powtórzonych warunków (krok 46).
 *
 * Klasa jest cienka i to nie jest przeoczenie: niesie **regułę**, a nie kod.
 * Reguła brzmi „o tym, czy czynność się udała, rozstrzyga **ton zdania**, które
 * po niej zostało" — bo zdanie i tak powstaje, a druga odpowiedź na to samo
 * pytanie zaczęłaby się z nim rozjeżdżać przy pierwszej poprawce. Ta sama miara,
 * którą kierowały się `HiddenEntries` i `EntryOperations`: czynność o dwóch
 * wejściach mieszka w jednym miejscu.
 *
 * `outcome()` **oddaje to, co dostało**, więc miejsce publikacji jest dopiskiem
 * w istniejącym `return`, a nie osobną linią przed nim — z osobnej linii łatwo
 * wyjść wcześniejszym `return`em i nikt tego nie zauważy.
 */
final class BrowserEvents
{
    public function __construct(
        private readonly EventRegistry $events,
    ) {
    }

    public function fire(BrowserEvent $event): void
    {
        $this->events->publish($event->value);
    }

    /**
     * Skutek czynności: zdanie w tonie błędu znaczy niepowodzenie, każde inne —
     * powodzenie.
     *
     * Brak zdania (`null`) nie jest ani jednym, ani drugim: czynność, która nic
     * nie powiedziała, niczego nie zmieniła — jak zmiana nazwy na tę samą.
     */
    public function outcome(BrowserEvent $done, BrowserEvent $failed, ?Message $message): ?Message
    {
        if ($message !== null) {
            $this->fire($message->tone === MessageTone::Error ? $failed : $done);
        }

        return $message;
    }
}
