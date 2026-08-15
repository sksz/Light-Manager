<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\TransferState;

/**
 * Kopiowanie i przenoszenie wpisów — praca dłuższa od klatki (krok 42, D46).
 *
 * **Port stoi obok `FileOperationsPort`, a nie w nim** (D79, rozstrzygnięcie 1),
 * i nie jest to podział z wygody. Powód, którym plan kroku uzasadniał osobność
 * („czynność natychmiastowa nie ma stanu”), zdezaktualizował się w kroku 41 —
 * tamten port dostał własną pracę kawałkową. Został drugi, mocniejszy: stan
 * kopiowania jest nieporównanie większy od stanu usuwania — lista źródeł, cel,
 * dwa otwarte uchwyty, pozycja w pliku, kolejka wpisów i **pamięć odpowiedzi
 * o kolizjach** — a jedna klasa prowadząca dwie takie prace naraz byłaby klasą,
 * która robi wszystko.
 *
 * Granica wiedzy rdzenia jest **ta sama**, co przy porcie z kroku 41, i szersza
 * być nie ma prawa: ścieżka bezwzględna jako napis, nazwa jako napis, czynność
 * i stan pracy. Wpisu katalogu, sortowania, zaznaczenia ani podglądu port nie
 * zna — pilnuje tego `CoreKnowsNothingAboutFilesTest`. Wyjątek od reguły 15
 * obejmuje przez ten krok **katalog `Infrastructure/FileSystem` jako całość**,
 * a nie jedną klasę; zasada „wszystko, co pisze po dysku, idzie przez port
 * rdzenia” zostaje nietknięta.
 *
 * **Jedna praca naraz** (reguła 11d) — z tego samego powodu, co w obu
 * poprzednikach: okno postępu jest jedno, a stos okien ma jedno piętro.
 */
interface FileTransferPort
{
    /**
     * Zaczyna pracę — od **liczenia**, nie od kopiowania.
     *
     * Poprzednia praca, jeśli trwała, zostaje przerwana wraz ze sprzątnięciem
     * niedokończonego pliku.
     *
     * **Przeniesienie w obrębie jednego systemu plików kończy się tutaj**: idzie
     * `rename()`em i oddaje od razu `Done`. Rozpoznanie idzie przez numer
     * urządzenia, a nie przez próbę i odczytanie błędu — PHP obsługuje `EXDEV`
     * dla zwykłych plików sam, kopiując je **w środku wywołania**, czyli
     * dokładnie tak, jak tej pracy kopiować nie wolno.
     *
     * @param list<string> $sources ścieżki bezwzględne; lista, nie jeden wpis, także wtedy, gdy ma jeden element (krok 43 doda resztę)
     * @param string       $target  katalog docelowy, ścieżka bezwzględna
     * @param bool         $move    czy źródło ma zniknąć po **potwierdzonym** zapisaniu celu
     */
    public function begin(array $sources, string $target, bool $move): TransferState;

    /**
     * Posuwa pracę o jeden kawałek i oddaje stan po nim. Wywołanie przy pracy,
     * która nie trwa, niczego nie zmienia — wołający nie musi pilnować, czy ma
     * o co pytać.
     *
     * Budżet znaczy co innego na każdym etapie i tak ma być: przy liczeniu to
     * **wpisy**, przy kopiowaniu **bajty**. Wołający zna etap ze stanu i podaje
     * liczbę dobraną do niego — wzorem `advanceRemoval()` z kroku 41, gdzie
     * liczenie i usuwanie mają własne stałe.
     */
    public function advance(int $budget): TransferState;

    /**
     * Odpowiedź na kolizję: praca rusza dalej albo staje.
     *
     * Wywołanie poza etapem `Colliding` niczego nie zmienia. `Rename` bez nazwy
     * też nie — nazwa jest treścią odpowiedzi, a nie jej ozdobą.
     *
     * @param ?string $newName sama nazwa, bez ścieżki; wyłącznie dla `TransferChoice::Rename`
     */
    public function resolve(TransferChoice $choice, ?string $newName = null): TransferState;

    public function state(): TransferState;

    /**
     * Przerywa pracę i zapomina kolejkę.
     *
     * **Usuwa przy tym plik zapisany w połowie** — cel zapisuje się wprost, więc
     * to jest jedyne miejsce, które pilnuje, żeby przerwanie nie zostawiło na
     * dysku pliku wyglądającego na gotowy (D79, rozstrzygnięcie 5). Wolno wołać
     * zawsze.
     */
    public function stop(): void;
}
