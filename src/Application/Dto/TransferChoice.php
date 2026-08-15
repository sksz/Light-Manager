<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Odpowiedzi na kolizję nazw przy kopiowaniu i przenoszeniu (krok 42, D79
 * rozstrzygnięcie 4).
 *
 * Sześć wartości, nie cztery z przełącznikiem „do wszystkich”: przełącznik
 * w oknie wyboru byłby siódmym stanem do obsłużenia w klawiszach, a „nadpisz
 * wszystkie” jest po prostu **inną odpowiedzią** niż „nadpisz”. Okno pokazuje je
 * jako sześć wierszy listy i o żadnej z nich nie wie nic ponad klucz napisu.
 *
 * „Do wszystkich” pamięta się **do końca pracy**, a nie do końca katalogu:
 * scalenie drzewa o dwudziestu kolizjach ma być jednym pytaniem, a nie
 * dwudziestoma.
 */
enum TransferChoice
{
    /** Nadpisz ten jeden wpis. */
    case Overwrite;

    /** Nadpisz ten i każdy następny, o który praca miałaby zapytać. */
    case OverwriteAll;

    /** Zostaw wpis w celu nietknięty, źródło pomiń — przy przenoszeniu **także nie usuwaj** źródła. */
    case Skip;

    /** Pomiń ten i każdy następny. */
    case SkipAll;

    /** Zapisz pod inną nazwą, podaną w odpowiedzi. */
    case Rename;

    /** Przerwij całą pracę; to, co już przeniesione, zostaje przeniesione. */
    case Abort;
}
