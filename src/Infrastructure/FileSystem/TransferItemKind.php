<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\FileSystem;

/**
 * Rodzaje pozycji w kolejce kopiowania (krok 42).
 *
 * Klasa jest **wewnętrzną sprawą infrastruktury** i przez port nie przechodzi:
 * `TransferState` mówi o bajtach i wpisach, a nie o tym, że praca układa sobie
 * pieczątki na końcu listy.
 *
 * Kolejność w kolejce jest **wymuszona przez system plików**, a nie wybrana:
 * katalogi muszą powstać, zanim wejdzie do nich zawartość; prawa katalogu
 * ustawiają się dopiero po niej (katalog `0555` nie przyjąłby ani jednego pliku);
 * katalog źródłowy znika na samym końcu, bo dopóki coś z niego wychodzi, nie jest
 * pusty.
 */
enum TransferItemKind
{
    /**
     * Przenieś wpis samą zmianą nazwy — w całości, jednym wywołaniem.
     *
     * Pozycja powstaje wyłącznie przy przenoszeniu w obrębie jednego systemu
     * plików i **zastępuje wtedy wszystko inne**: takiego wpisu się nie liczy, nie
     * przechodzi i nie kopiuje. Dzięki temu droga zostaje jedna (zawsze kolejka),
     * a przeniesienie katalogu o stu tysiącach plików na tym samym dysku kosztuje
     * jedną pozycję i jedną klatkę.
     */
    case Shift;

    /** Utwórz katalog w celu (albo wejdź do istniejącego — scalenie). */
    case Directory;

    /** Skopiuj plik po kawałku; przy przenoszeniu usuń źródło po zapisaniu całości. */
    case File;

    /** Odtwórz dowiązanie symboliczne — jego treść, nie to, na co wskazuje. */
    case Link;

    /** Nadaj katalogowi w celu prawa i czas zmiany oryginału. */
    case Stamp;

    /** Usuń pusty już katalog źródłowy — wyłącznie przy przenoszeniu. */
    case Drop;
}
