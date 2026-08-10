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
     * wiersza z wyrocznią.
     *
     * @return list<int> wysokość każdej szczeliny, w kolejności zadeklarowania
     */
    public function distribute(int $rows): array
    {
        $rows = max(0, $rows);
        $heights = [];
        $flexible = [];

        foreach ($this->slots as $index => $slot) {
            if ($slot->preferred === null) {
                $flexible[] = $index;
                $heights[$index] = $slot->minimum;

                continue;
            }

            $heights[$index] = max($slot->minimum, $slot->preferred);
        }

        $used = array_sum($heights);

        if ($used <= $rows) {
            return $this->spread($heights, $flexible, $rows - $used);
        }

        return $this->yieldRows($heights, $rows, $used - $rows);
    }

    /**
     * Nadmiar wierszy idzie do szczelin elastycznych — po równo, a reszta
     * z dzielenia do pierwszych z nich. Gdy elastycznej nie ma, nadmiar zostaje
     * niewykorzystany: kontener nie rozciąga dzieci, które o to nie prosiły.
     *
     * @param array<int, int> $heights
     * @param list<int>       $flexible
     *
     * @return list<int>
     */
    private function spread(array $heights, array $flexible, int $spare): array
    {
        $count = count($flexible);

        if ($count > 0 && $spare > 0) {
            $each = intdiv($spare, $count);
            $extra = $spare - $each * $count;

            foreach ($flexible as $position => $index) {
                $heights[$index] += $each + ($position < $extra ? 1 : 0);
            }
        }

        return array_values($heights);
    }

    /**
     * Odbiera wiersze szczelinom w kolejności ustępowania — **albo tyle, ile
     * trzeba, albo wszystko**.
     *
     * Szczelina, której po oddaniu zostałoby mniej niż jej minimum, znika
     * w całości zamiast skurczyć się do resztki. To nie jest szczegół
     * arytmetyczny: pas podglądu wysoki na dwa wiersze pokazuje pasek obwódki
     * i nic więcej, więc jest gorszy niż jego brak. Tak właśnie zachowywała się
     * drabinka z kroku 13 i tak ma zachowywać się dalej.
     *
     * Zwolnione w ten sposób wiersze wracają do szczelin elastycznych.
     *
     * @param array<int, int> $heights
     *
     * @return list<int>
     */
    private function yieldRows(array $heights, int $rows, int $excess): array
    {
        foreach ($this->byYieldOrder() as $index) {
            if ($excess <= 0) {
                break;
            }

            $spare = $heights[$index] - $this->slots[$index]->minimum;

            if ($spare >= $excess) {
                $heights[$index] -= $excess;
                $excess = 0;

                continue;
            }

            $excess -= $heights[$index];
            $heights[$index] = 0;
        }

        return $this->spread($heights, $this->flexibleIndexes(), max(0, $rows - array_sum($heights)));
    }

    /** @return list<int> */
    private function flexibleIndexes(): array
    {
        $indexes = [];

        foreach ($this->slots as $index => $slot) {
            if ($slot->preferred === null) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /** @return list<int> indeksy szczelin od ustępującej najchętniej */
    private function byYieldOrder(): array
    {
        $order = array_keys($this->slots);

        usort($order, fn (int $a, int $b): int => $this->slots[$a]->yieldOrder <=> $this->slots[$b]->yieldOrder);

        return $order;
    }
}
