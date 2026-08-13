<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Wiersz tekstu rozpięty między krawędziami prostokąta: treść po lewej,
 * wartość po prawej.
 *
 * Odpowiednik dawnego `FrameLine` z dwoma segmentami — z tą różnicą, że
 * rozmieszczenie liczy się **tutaj**, a nie w rendererze. Etykieta zna swój
 * prostokąt, więc wie, w której kolumnie zaczyna się napis dosunięty do prawej;
 * renderer dostaje gotową pozycję i nie powtarza rachunku.
 *
 * Przycięcie zbyt długiej treści też należy do etykiety: to reguła prezentacji
 * treści, nie rozmieszczenia pikseli. Do kroku 18 ten sam rachunek stał
 * w trzech przypadkach użycia naraz.
 */
final class Label implements ComponentInterface
{
    public function __construct(
        private readonly string $left,
        private readonly string $right = '',
        private readonly Role $role = Role::Text,
        /** Rola wycinana pod napisem — dla etykiet leżących na linii obwódki. */
        private readonly ?Role $clearBehind = null,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $primitives = [];
        $room = $bounds->columns;

        if ($this->right !== '') {
            // Wartość też się przycina, i to nie jest symetria dla symetrii.
            // Do poprawki z 2026-08-12 przycinała się wyłącznie treść po lewej,
            // a wartość szła na płótno w całości: opis od polecenia `file`,
            // długi na 128 znaków, rysował się w czterdziestokolumnowym panelu
            // jako napis kończący się osiemdziesiąt osiem kolumn **za** jego
            // krawędzią — czyli po sąsiednim panelu.
            $right = self::fit($this->right, $bounds->columns);
            $width = mb_strlen($right);
            $primitives[] = new TextRun(
                $bounds->row,
                $bounds->column + max(0, $bounds->columns - $width),
                $right,
                $this->role,
                $this->clearBehind,
            );
            $room = max(1, $bounds->columns - $width);
        }

        if ($this->left !== '') {
            array_unshift($primitives, new TextRun(
                $bounds->row,
                $bounds->column,
                self::fit($this->left, $room),
                $this->role,
                $this->clearBehind,
            ));
        }

        return $primitives;
    }

    /** Zbyt długa treść kończy się wielokropkiem, nie urwanym w pół znaku słowem. */
    public static function fit(string $text, int $columns): string
    {
        if ($columns < 1) {
            return '';
        }

        return mb_strlen($text) > $columns
            ? mb_substr($text, 0, max(0, $columns - 1)) . '…'
            : $text;
    }

    /**
     * To samo, ale ucięte od **początku**: wielokropek staje z przodu, a koniec
     * treści zostaje.
     *
     * Dla ścieżki jest to jedyne sensowne cięcie — `…/projekty/lm/src` mówi, gdzie
     * się stoi, a `/home/uzytkownik/pro…` nie mówi nic. Metoda leży obok `fit()`,
     * bo obie są tą samą regułą prezentacji treści widzianą z dwóch stron, a dwa
     * warianty tego rachunku w dwóch plikach rozjechałyby się co do znaku
     * wielokropka.
     */
    public static function fitEnd(string $text, int $columns): string
    {
        if ($columns < 1) {
            return '';
        }

        return mb_strlen($text) > $columns
            ? '…' . mb_substr($text, -max(0, $columns - 1))
            : $text;
    }
}
