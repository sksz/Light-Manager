<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\RemovalState;

/**
 * Czynności zmieniające zawartość dysku (krok 41).
 *
 * **Port istnieje wbrew regule 15 i jest to jawny, nazwany wyjątek** (D66,
 * rozstrzygnięcie 2). Nowa funkcja należy w tym projekcie do modułu, a rdzeń od
 * kroku 21 nie wie, czym jest plik ani katalog — tutaj dostaje jednak port zapisu,
 * bo druga reguła tej samej pary („moduł nigdy nie sięga do innego modułu”)
 * znaczyłaby przy dwóch odbiorcach **dwie kopie kodu piszącego po dysku**.
 * Powtórzony rachunek praw dostępu kosztował dziesięć linii bez skutków ubocznych;
 * powtórzone `unlink()` kosztuje utratę danych w dwóch miejscach zamiast w jednym.
 *
 * Granica tej wiedzy jest wąska i **szersza być nie ma prawa**:
 *
 * - port zna **ścieżkę bezwzględną jako napis**, nazwę jako napis i stan pracy;
 * - port **nie zna** wpisu katalogu, sortowania, ukrywania, zaznaczenia ani
 *   podglądu — `Entry`, `Directory`, `DirectoryPath` i `EntryType` nie mają prawa
 *   pojawić się w żadnej sygnaturze w `src/Application` ani `src/Domain`, czego
 *   pilnuje `CoreKnowsNothingAboutFilesTest`;
 * - port **nie sprawdza, czy nazwa jest poprawna** (D75, rozstrzygnięcie 2) — wie
 *   o tym wołający, bo to on pokazuje pole, w które użytkownik pisze.
 *
 * Trzy pierwsze czynności są **natychmiastowe**: kończą się w tej samej klatce,
 * w której się zaczęły, a niepowodzenie rzucają wyjątkiem, który sam podaje
 * zdanie dla użytkownika (`FileOperationException`). Czwarta — usunięcie katalogu
 * wraz z zawartością — jest **pracą kawałkową** (D46), bo drzewa o stu tysiącach
 * wpisów nie da się usunąć w jednej klatce; nie rzuca więc niczego, tylko oddaje
 * stan, a powód niepowodzenia niesie w nim.
 */
interface FileOperationsPort
{
    /**
     * Zmiana nazwy wpisu w miejscu — katalog zostaje ten sam.
     *
     * @param string $path    ścieżka bezwzględna wpisu
     * @param string $newName sama nazwa, bez ścieżki; sprawdzona przez wołającego
     *
     * @throws \LightManager\Domain\Exception\FileOperationException
     */
    public function rename(string $path, string $newName): void;

    /**
     * Nowy, pusty katalog pod wskazaną ścieżką. Katalogów pośrednich nie tworzy —
     * jedna czynność to jeden poziom.
     *
     * @throws \LightManager\Domain\Exception\FileOperationException
     */
    public function createDirectory(string $path): void;

    /**
     * Usunięcie **jednego** wpisu: pliku, dowiązania albo pustego katalogu.
     *
     * Katalog z zawartością odmawia (`notEmpty`) — jego usunięcie jest pracą
     * kawałkową i idzie czterema metodami niżej.
     *
     * @throws \LightManager\Domain\Exception\FileOperationException
     */
    public function delete(string $path): void;

    /**
     * Zaczyna usuwanie katalogu wraz z zawartością — od **liczenia**, nie od
     * usuwania. Poprzednia praca, jeśli trwała, zostaje przerwana: port prowadzi
     * jedną (reguła 11d).
     */
    public function beginRemoval(string $path): RemovalState;

    /**
     * Posuwa pracę o jeden kawałek i oddaje stan po nim. Wywołanie przy pracy,
     * która nie trwa, niczego nie zmienia — wołający nie musi pilnować, czy ma
     * o co pytać.
     *
     * @param int $entries ile najwyżej wpisów obsłużyć w tym wywołaniu
     */
    public function advanceRemoval(int $entries): RemovalState;

    /**
     * Zgoda użytkownika: praca przechodzi z `Ready` do usuwania.
     *
     * Osobna metoda, bo między policzeniem a usuwaniem stoi pytanie — a pytanie
     * ma prawo skończyć się odmową, po której nie wolno usunąć ani jednego wpisu.
     */
    public function confirmRemoval(): RemovalState;

    public function removalState(): RemovalState;

    /** Przerywa pracę i zapomina listę. Wolno wołać zawsze. */
    public function stopRemoval(): void;
}
