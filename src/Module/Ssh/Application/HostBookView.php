<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Wszystko, co ekran spisu hostów wie o książce — **jednym obiektem** (krok 54).
 *
 * Powstał, bo „rejestr jedyną drogą odczytu" (D92 nr 3) zderzyło się z tym, że
 * ekran czyta o książce **trzy rzeczy z trzech metod**: sam spis, ścieżkę pliku
 * i powód, dla którego pliku nie dało się przeczytać. Trzy kwerendy na trzy
 * pytania o jedną rzecz byłyby trzema pokoleniami do pilnowania i trzema
 * wpisami w oknie kwerend — a odpowiedź i tak pochodzi z jednego odczytu dysku.
 *
 * Ładunek wychodzi **wyłącznie do właściciela**, więc obcy modułowi ścieżka pliku
 * książki się nie należy: w wierszach stoją same wpisy (`HostsQuery::describe()`).
 */
final readonly class HostBookView
{
    public function __construct(
        public HostBook $book,
        /** Ścieżka pliku, w którym książka mieszka — pokazuje ją stopka ekranu. */
        public string $location,
        /** Klucz katalogu z powodem, gdy pliku nie dało się przeczytać. */
        public ?string $problemKey = null,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self(new HostBook(), '');
    }
}
