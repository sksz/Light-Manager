<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidEntryNameException;

/**
 * Nazwa wpisu **wpisana przez użytkownika** (krok 41).
 *
 * Klasa istnieje dlatego, że rozstrzygnięcie startowe kroku (D75, nr 2) posadziło
 * wiedzę o poprawnej nazwie w module: port operacji bierze napisy i ufa
 * wołającemu, bo drugim jego odbiorcą będzie moduł opisu pliku, który `Entry`
 * w ogóle nie ma. Sprawdzenie musi więc mieć **jedno** miejsce po tej stronie
 * granicy — i to jest to miejsce.
 *
 * Czym różni się od sprawdzenia w `Entry`: tamto pilnuje wpisu **odczytanego
 * z dysku** (nazwa przyszła od systemu i albo jest wpisem, albo odczyt się
 * pomylił), to pilnuje napisu, który ktoś właśnie wpisał w okno — więc dokłada
 * dwie reguły, których tamto nie potrzebuje: **długość** i **bajt zerowy**.
 * System plików odrzuciłby oba, ale zrobiłby to komunikatem w języku systemu,
 * a nie zdaniem po polsku.
 *
 * **Odstępów nie obcinamy.** Nazwa `„ raport ”` jest w systemach uniksowych
 * poprawna, a obcięcie w milczeniu znaczyłoby, że aplikacja utworzyła coś innego,
 * niż użytkownik napisał — i to bez słowa.
 */
final class EntryName
{
    /** Granica z systemów plików rodziny ext i większości pozostałych — w bajtach, nie w znakach. */
    public const MAX_BYTES = 255;

    public readonly string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw InvalidEntryNameException::empty();
        }

        if ($value === '.' || $value === '..') {
            throw InvalidEntryNameException::reserved($value);
        }

        // Bajt zerowy ląduje razem z ukośnikiem, bo znaczy to samo z punktu widzenia
        // wołającego: napis, którego system plików nie przyjmie jako nazwy.
        if (str_contains($value, '/') || str_contains($value, "\0")) {
            throw InvalidEntryNameException::separator($value);
        }

        if (strlen($value) > self::MAX_BYTES) {
            throw InvalidEntryNameException::tooLong($value, self::MAX_BYTES);
        }

        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
