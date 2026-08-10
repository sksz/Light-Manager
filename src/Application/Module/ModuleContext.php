<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Kontekst sesji: gdzie użytkownik stoi i co ma zaznaczone.
 *
 * Kontekst niesie **dane pierwotne** — napis, napis i enum — a nie `Directory`
 * (D40, P5). Zdolność w pierwotnym brzmieniu (`useDirectory(Directory)`)
 * działałaby dopóty, dopóki katalog należy do rdzenia; w kroku 21 schodzi on do
 * modułu przeglądarki, a wtedy każdy inny moduł czytałby typ cudzego modułu —
 * czyli koniec zasady „moduły się nie znają”.
 *
 * Kontekst trzyma stan pętli, a publikuje go ekran, który zna bieżące miejsce.
 * **Brak wydawcy daje kontekst pusty, nie `null`**: odbiorca ma czytać, a nie
 * sprawdzać istnienie. `selection` bywa za to `null` i to jest zwykły stan —
 * katalog pusty albo nieczytelny — który każdy ekran modułu musi umieć pokazać.
 */
final class ModuleContext
{
    public function __construct(
        /** Ścieżka bieżącego miejsca; pusta, dopóki nikt kontekstu nie opublikował. */
        public readonly string $path = '',
        /** Nazwa zaznaczenia albo `null`, gdy nic nie jest zaznaczone. */
        public readonly ?string $selection = null,
        public readonly ContextEntryKind $kind = ContextEntryKind::None,
    ) {
    }

    /**
     * Pełna ścieżka zaznaczenia albo `null`, gdy nie ma czego wskazać.
     *
     * Sklejenie stoi tutaj, a nie w każdym module z osobna: to jedyna rzecz,
     * którą odbiorcy kontekstu robią z jego dwoma pierwszymi polami, a zrobiona
     * na własną rękę różniłaby się między modułami traktowaniem korzenia.
     */
    public function selectionPath(): ?string
    {
        if ($this->selection === null || $this->path === '') {
            return null;
        }

        return rtrim($this->path, '/') . '/' . $this->selection;
    }
}
