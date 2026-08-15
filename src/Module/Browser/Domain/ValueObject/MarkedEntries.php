<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidEntryException;

/**
 * Zbiór wpisów zaznaczonych w panelu — mnożnik operacji (krok 43).
 *
 * **Zbiór trzyma nazwy, nie numery**, i to jest ta sama reguła, którą krok 30
 * postawił dla zaznaczenia przenoszonego przez filtr: zawężenie listy zmienia
 * numery wszystkim wpisom naraz, a nazwa wpisu zostaje nazwą. Dzięki temu wpis
 * zaznaczony przed wpisaniem fragmentu jest tym samym wpisem po jego wpisaniu —
 * i zostaje w zbiorze także wtedy, gdy filtr wypchnął go poza widok
 * (rozstrzygnięcie 4 tego kroku).
 *
 * **Rozmiar jest `null`-owalny i to jest cała treść rozstrzygnięcia 7.** Katalog
 * wolno zaznaczyć na równi z plikiem — bez tego zaznaczenie przestawałoby być
 * mnożnikiem najcięższych operacji — ale rozmiaru katalogu nie znamy: liczba
 * z `stat()` mówi o i-węźle, a zajętość wraz z zawartością umie policzyć dopiero
 * `du` z kroku 26. Katalog niesie więc `null`, suma go pomija, a napis
 * podsumowania **musi to powiedzieć**, bo inaczej kłamie.
 *
 * Klasa leży w `Domain` modułu, a nie w jego `Presentation`, choć jej jedynym
 * właścicielem jest stan panelu: reguła zbioru („nazwy, nie numery”, „katalog
 * waży zero”) jest regułą dziedziny przeglądarki i daje się sprawdzić bez ani
 * jednego wywołania systemowego.
 *
 * **Pułapka, o której trzeba wiedzieć przed pierwszą poprawką:** nazwa wpisu
 * jest kluczem tablicy, a PHP sprowadza klucz wyglądający jak liczba całkowita
 * do `int`-a — plik nazwany `2026` daje klucz `2026`, nie `'2026'`. Wyszukiwanie
 * działa mimo to (`array_key_exists('2026', …)` znajduje klucz liczbowy), ale
 * **przeglądanie już nie**: przy `strict_types` klucz podany funkcji napisowej
 * kończy się `TypeError`-em. Stąd rzutowanie na napis przy każdym `foreach`
 * po kluczach — trzy miejsca w tej klasie i ani jedno poza nią.
 */
final readonly class MarkedEntries
{
    /**
     * @param array<string, ?int> $sizes nazwa wpisu → jego rozmiar w bajtach;
     *                                   `null` znaczy „katalog, rozmiaru nie ma”
     */
    public function __construct(
        public array $sizes = [],
    ) {
        foreach ($sizes as $key => $size) {
            $name = (string) $key;

            if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/')) {
                throw InvalidEntryException::forName($name);
            }

            if ($size !== null && $size < 0) {
                throw InvalidEntryException::forNegativeSize($name, $size);
            }
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public function isEmpty(): bool
    {
        return $this->sizes === [];
    }

    public function count(): int
    {
        return count($this->sizes);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->sizes);
    }

    /** Suma rozmiarów **plików**; katalogi jej nie powiększają, bo nie mają rozmiaru. */
    public function bytes(): int
    {
        $total = 0;

        foreach ($this->sizes as $size) {
            $total += $size ?? 0;
        }

        return $total;
    }

    /** Ile w zbiorze katalogów — liczba, o którą podsumowanie musi się zastrzec. */
    public function directories(): int
    {
        $directories = 0;

        foreach ($this->sizes as $size) {
            if ($size === null) {
                ++$directories;
            }
        }

        return $directories;
    }

    /**
     * Nazwy w kolejności zaznaczania.
     *
     * Kolejność wpisywania, a nie alfabetyczna: to ona rozstrzyga, w jakiej
     * kolejności operacja przejdzie po zbiorze, a użytkownik, który zaznaczył
     * dwanaście plików od góry do dołu, spodziewa się dokładnie tej kolejności
     * w oknie postępu.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];

        foreach (array_keys($this->sizes) as $key) {
            $names[] = (string) $key;
        }

        return $names;
    }

    /** Zbiór z przełączonym wpisem: był — znika, nie było — dochodzi. */
    public function toggled(string $name, ?int $size): self
    {
        $sizes = $this->sizes;

        if (array_key_exists($name, $sizes)) {
            unset($sizes[$name]);

            return new self($sizes);
        }

        $sizes[$name] = $size;

        return new self($sizes);
    }

    /**
     * Zbiór zawężony do wpisów, które nadal istnieją.
     *
     * Wołane po zmianie na dysku (krok 41 i 42): wpisy, które zniknęły, znikają
     * ze zbioru, a te, których operacja nie dotknęła, **zostają zaznaczone** —
     * to jedyna droga, którą użytkownik dowie się, czego nie udało się zrobić.
     * Rozmiar bierze się przy tym na nowo z odczytanego katalogu, bo plik
     * skopiowany do siebie samego bywa dłuższy niż przed chwilą.
     *
     * @param array<string, ?int> $available nazwa → rozmiar, wedle świeżego odczytu
     */
    public function keptFrom(array $available): self
    {
        $sizes = [];

        foreach ($this->sizes as $key => $size) {
            $name = (string) $key;

            if (array_key_exists($name, $available)) {
                $sizes[$name] = $available[$name];
            }
        }

        return new self($sizes);
    }

    /**
     * Zbiór odwrócony na podanej liście — zaznaczone znikają, reszta dochodzi.
     *
     * Lista jest **widoczna**, nie pełna (rozstrzygnięcie 8), więc wpisy spoza
     * niej zostają w zbiorze w niezmienionym stanie: `*` dotyczy tego, na co
     * użytkownik patrzy, a nie tego, co filtr właśnie schował.
     *
     * @param array<string, ?int> $visible nazwa → rozmiar
     */
    public function invertedOn(array $visible): self
    {
        $sizes = $this->sizes;

        foreach ($visible as $name => $size) {
            if (array_key_exists($name, $sizes)) {
                unset($sizes[$name]);

                continue;
            }

            $sizes[$name] = $size;
        }

        return new self($sizes);
    }

    public function equals(self $other): bool
    {
        return $this->sizes === $other->sizes;
    }
}
