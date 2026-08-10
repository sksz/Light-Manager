<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Role;

/**
 * Napis postawiony w konkretnym miejscu siatki.
 *
 * Wyrównanie rozstrzyga się **przed** powstaniem prymitywu: komponent zna swój
 * prostokąt, więc wie, w której kolumnie zaczyna się napis dosunięty do prawej.
 * Renderer dostaje gotową pozycję i nie liczy niczego po raz drugi — to ten sam
 * podział pracy, który w kroku 17 oddzielił treść wiersza od jego rozmieszczenia.
 */
final class TextRun implements Primitive
{
    public function __construct(
        public readonly int $row,
        public readonly int $column,
        public readonly string $text,
        public readonly Role $role,
        /**
         * Rola, którą napis wycina sobie pod spodem, zanim się narysuje.
         *
         * Potrzebna dokładnie jednemu użytkownikowi: etykiecie wpiętej w górną
         * krawędź panelu. Leży ona **na linii obwódki**, więc bez wycięcia
         * kreska przechodziłaby przez litery. Alternatywą byłoby zamalowanie
         * całej komórki osobnym prostokątem, ale ono sięgałoby wyżej i niżej
         * niż same litery — a różnicę widać na krawędzi łuku.
         */
        public readonly ?Role $clearBehind = null,
    ) {
    }

    public function signature(): string
    {
        return 'T' . $this->row . ',' . $this->column
            . ',' . $this->role->name
            . ',' . ($this->clearBehind === null ? '-' : $this->clearBehind->name)
            . ',' . $this->text;
    }
}
