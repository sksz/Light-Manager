<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Container;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Układa dzieci jedno pod drugim i rozdziela między nie wiersze.
 *
 * Rozdział ma dwa tryby. Gdy miejsca starcza, szczeliny o stałej wysokości
 * dostają swoje, a elastyczne dzielą resztę. Gdy nie starcza — zaczyna się
 * **ustępowanie**: szczeliny oddają wiersze w kolejności `yieldOrder`, każda aż
 * do swojego minimum, a dopiero potem ustępuje następna. To ta sama drabinka,
 * którą do kroku 18 rozpisywał `HudFrameLayoutService` w pętli z czterema
 * warunkami — tyle że wyrażona regułą, a nie ciągiem przypadków.
 *
 * Kolejność ustępowania nie ma nic wspólnego z kolejnością na ekranie: pas
 * podglądu leży pośrodku klatki, a ustępuje pierwszy.
 *
 * **Sam rachunek wyprowadził się w kroku 27 do `Distribution`** i kontener go
 * odtąd tylko woła. Powodem była druga oś: kolumny tabeli dzielą miejsce tą samą
 * regułą, a reguła zapisana dwa razy rozjeżdża się przy pierwszej zmianie. Co
 * zostało tutaj, to jedyna rzecz naprawdę pionowa — zamiana wyliczonych miar na
 * prostokąty wierszy.
 */
final class VStack implements ComponentInterface
{
    /** @param list<Slot> $slots */
    public function __construct(
        private readonly array $slots,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $primitives = [];
        $row = 0;

        foreach ($this->distribute($bounds->rows) as $index => $rows) {
            if ($rows > 0) {
                foreach ($this->slots[$index]->child->draw($bounds->rowsFrom($row, $rows)) as $primitive) {
                    $primitives[] = $primitive;
                }
            }

            $row += $rows;
        }

        return $primitives;
    }

    /**
     * Wysokości szczelin przy zadanej wysokości kontenera.
     *
     * Wystawione publicznie, bo jest to jedyna liczbowa treść tej klasy i
     * jedyne, co da się sprawdzić testem bez rysowania czegokolwiek — a przy
     * przenoszeniu drabinki z kroku 13 właśnie ten wynik musi się zgadzać co do
     * wiersza z wyrocznią. Od kroku 27 rachunek stoi w `Distribution`, więc te
     * same testy pilnują odtąd obu osi naraz.
     *
     * @return list<int> wysokość każdej szczeliny, w kolejności zadeklarowania
     */
    public function distribute(int $rows): array
    {
        return Distribution::of(array_map(static fn (Slot $slot): Span => $slot->span, $this->slots), $rows);
    }
}
