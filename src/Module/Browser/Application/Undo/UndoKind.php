<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\Undo;

/**
 * Rodzaje operacji w stosie cofnięć (krok 44).
 *
 * **Spis tego, co odwracalne, mieszka w kodzie** — w `UndoEntry::reversible()`
 * — a nie w napisie, który przy pierwszej nowej operacji skłamie. Enum wylicza
 * wszystkie operacje, także nieodwracalne: widok stosu pokazuje je wyszarzone
 * (D81, nr 8), bo lista odpowiada na dwa pytania naraz — „co mogę cofnąć”
 * i „co się właściwie wydarzyło”.
 */
enum UndoKind
{
    /** Zmiana nazwy — cofa się zmianą nazwy z powrotem. */
    case Rename;

    /** Nowy katalog — cofa się usunięciem, **dopóki pozostał pusty** (D81, nr 10). */
    case MakeDirectory;

    /** Przeniesienie do kosza — cofa się przywróceniem z niego. */
    case Trash;

    /** Przeniesienie — cofa się przeniesieniem z powrotem, tą samą pracą kawałkową. */
    case Move;

    /**
     * Kopiowanie — **nieodwracalne z rozmysłem**: jego cofnięciem byłoby
     * usunięcie kopii, czyli operacja nieodwracalna udająca powrót.
     */
    case Copy;

    /** Usunięcie trwałe — nie da się cofnąć w ogóle. */
    case PermanentDelete;
}
