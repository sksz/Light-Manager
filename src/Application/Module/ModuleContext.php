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
 *
 * **Od kroku 43 kontekst niesie ponadto zaznaczenie wielokrotne** i jest to
 * rozstrzygnięcie użytkownika podjęte wbrew rekomendacji planu (D80,
 * rozstrzygnięcie 1). Plan proponował zostawić zbiór własnością przeglądarki, bo
 * reguła 15 każe nowej funkcji mieszkać w module; wybrano wariant przeciwny, żeby
 * pojęcie zbioru istniało w rdzeniu **raz**, a nie osobno w każdym module, który
 * kiedyś zechce o nim mówić. Warunek, pod którym ten wyjątek nie jest długiem,
 * jest ten sam co zawsze (reguła 13): mechanizm wchodzi **razem z odbiorcą** —
 * jest nim moduł opisu pliku, który przy niepustym zbiorze mówi o nim zamiast
 * o pojedynczym wpisie.
 *
 * Trzy pola zamiast obiektu zbioru, bo kontekst niesie **dane pierwotne** (D40,
 * P5) — dokładnie z tego samego powodu, dla którego niesie ścieżkę jako napis,
 * a nie `DirectoryPath`. Rozmiar w bajtach nie liczy katalogów i to jest cecha
 * danej, nie jej odbiorcy: zajętość katalogu wraz z zawartością umie policzyć
 * wyłącznie `du` z kroku 26, więc każdy, kto by ją tu doliczył, musiałby ją
 * najpierw zmyślić.
 *
 * **Od kroku 49 kontekst mówi ponadto, czyja jest ścieżka** (`origin`) i niesie
 * to, co wydawca wie o zaznaczeniu: rozmiar, czas zmiany i prawa. Jedno wynika
 * z drugiego i oba są rozstrzygnięciem użytkownika podjętym wbrew rekomendacji
 * planu. Powód pierwszego pola stoi przy `ContextOrigin`: bez niego ekran
 * zdalny publikujący `/var/log` kazałby modułowi opisu pliku pokazać **lokalny**
 * `/var/log`. Powód trzech następnych jest jego konsekwencją — odbiorcy odjęto
 * `lstat`, więc trzeba mu dać czym opisać wpis, którego nie wolno mu dotknąć.
 *
 * Atrybuty są **`null`-owalne i to jest ich treść**, a nie ostrożność: wydawca,
 * który ich nie zna, ma powiedzieć „nie wiem”, a nie podać zero. Zero jest
 * poprawnym rozmiarem pustego pliku i odbiorca nie ma jak odróżnić go od braku
 * odpowiedzi.
 */
final class ModuleContext
{
    public function __construct(
        /** Ścieżka bieżącego miejsca; pusta, dopóki nikt kontekstu nie opublikował. */
        public readonly string $path = '',
        /** Nazwa zaznaczenia albo `null`, gdy nic nie jest zaznaczone. */
        public readonly ?string $selection = null,
        public readonly ContextEntryKind $kind = ContextEntryKind::None,
        /** Ile wpisów zaznaczono wielokrotnie; `0` znaczy „zbiór jest pusty”. */
        public readonly int $markedCount = 0,
        /** Suma rozmiarów zaznaczonych **plików**; katalogi wnoszą do niej zero. */
        public readonly int $markedBytes = 0,
        /** Ile spośród zaznaczonych jest katalogami — o tyle suma powyżej milczy. */
        public readonly int $markedDirectories = 0,
        /** Czyja jest ścieżka: tej maszyny czy cudzej (krok 49). */
        public readonly ContextOrigin $origin = ContextOrigin::Local,
        /**
         * Podpis miejsca do pokazania użytkownikowi — `użytkownik@host` dla
         * zdalnego, pusty napis dla lokalnego.
         *
         * Napis, a nie profil hosta: kontekst nie ma prawa wymienić z odbiorcą
         * typu należącego do modułu, który go publikuje (D40, P5).
         */
        public readonly string $originLabel = '',
        /** Rozmiar zaznaczenia w bajtach; `null` — wydawca go nie zna. */
        public readonly ?int $selectionBytes = null,
        /** Czas ostatniej zmiany zaznaczenia; `null` — wydawca go nie zna. */
        public readonly ?int $selectionModifiedAt = null,
        /** Same bity uprawnień zaznaczenia, bez rodzaju; `null` — wydawca ich nie zna. */
        public readonly ?int $selectionPermissions = null,
    ) {
    }

    /**
     * Czy wpis leży poza tą maszyną — pytanie zadawane przez każdego, kto
     * chciałby go dotknąć wywołaniem systemowym.
     */
    public function isRemote(): bool
    {
        return $this->origin === ContextOrigin::Remote;
    }

    /** Czy zaznaczono więcej niż wpis pod kursorem — pytanie zadawane przez odbiorców. */
    public function hasMarked(): bool
    {
        return $this->markedCount > 0;
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
