<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Czynność wywoływana po nazwie w oknie komend.
 *
 * Komenda jest **daną plus wykonaniem** i dlatego mieszka w `Application`:
 * nie wie nic o oknie, które ją wywołało, ani o klatce, w której to okno stoi.
 * Skutek oddaje `CommandOutcome` — łącznie z otwarciem ekranu, wyrażonym
 * **identyfikatorem**, a nie obiektem, bo `ScreenInterface` leży w warstwie
 * dostarczania (D39, P24).
 *
 * Argumenty deklaruje, a nie rozbiera: wiersz dzieli, mapuje i sprawdza jeden
 * parser w rdzeniu, więc każda komenda tłumaczy się użytkownikowi tak samo.
 */
interface CommandInterface
{
    /**
     * Pełna nazwa wraz z przestrzenią właściciela: `core.settings`,
     * `file-info.jump`. Przedrostek pilnuje rejestr.
     */
    public function name(): string;

    /** Klucz katalogu napisów z opisem czynności — nie sam napis (krok 15). */
    public function descriptionKey(): string;

    /**
     * Argumenty w kolejności, w jakiej padają w wierszu.
     *
     * @return list<CommandArgument>
     */
    public function arguments(): array;

    public function execute(CommandInput $input): CommandOutcome;
}
