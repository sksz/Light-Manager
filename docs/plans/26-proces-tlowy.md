# Krok 26 — Proces tłowy jako mechanizm rdzenia

> **Skąd ten krok.** Powstał 2026-08-10, przy starcie kroku 25. Rozstrzygając,
> jak liczyć `sha256`, użytkownik dopisał: *„`hash_init()` + `hash_update_stream()`
> w procesie w tle, który przekazuje dane do procesu głównego. **Komponent procesu
> robimy jako osobny krok planu.** Teraz jedynie własny odczyt po kawałku”* —
> a dopytany o zakres wybrał wariant szerszy: **mechanizm rdzenia, do użytku
> każdego modułu**, nie prywatna sprawa jednego.

## Status

**Nie rozpoczęty** (2026-08-10).

## Cel

Dać aplikacji **jeden sposób uruchamiania procesu potomnego, który nie zatrzymuje
klatki**: start, doglądanie między klatkami, przerwanie, limit czasu i sprzątanie
przy wyjściu.

Miarą powodzenia jest zdanie: **`du` na katalogu domowym liczy się przez cztery
sekundy, a pętla główna w tym czasie nie gubi ani jednej klatki — i nie zostaje
po niej ani jeden osierocony proces.**

## Zależności

- **Krok 25** (pełny obraz stanu pliku) — twardo i podwójnie. Stamtąd pochodzi
  **wzorzec pracy kawałkowej** (port mówi o pracy, stan jest daną oglądaną co
  klatkę, praca ma właściciela, który ją przerywa — D46) oraz **pierwszy
  odbiorca**: wiersz „zajęte na dysku”, którego krok 25 świadomie nie pokazał.
- **Krok 23** (pasek postępu) — `du` postępu **nie zna**, więc będzie pierwszym
  prawdziwym użytkownikiem trybu „nieznany”. Do tej pory tryb ten miał wyłącznie
  test i scenariusz pomiaru.
- **Krok 20** (moduły) — jeśli mechanizm ma być rdzeniowy, port leży w
  `Application/Port`, a usługa w `Infrastructure`; moduł sięga po niego tak samo,
  jak po `ImagePreviewPort`.
- **Krok 06** (terminal i sygnały) — sprzątanie przy wyjściu musi wejść na tę samą
  ścieżkę, którą terminal wraca do trybu normalnego. To jedyne miejsce, w którym
  krok dotyka pętli.

## Model i wysiłek

**Opus / high.**

Krok jest mały w plikach i duży w tym, co może pójść źle. Proces potomny to
jedyny byt w tej aplikacji, który **przeżywa proces macierzysty**: zapomniany
`proc_close()` zostawia zombie, a zapomniany `proc_terminate()` — działającego
potomka po zamknięciu aplikacji. Do tego dochodzi czytanie potoków bez blokowania,
czyli klasa błędów, w której test przechodzi, a aplikacja zawiesza się raz na
sto uruchomień.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Module/FileInfo/Infrastructure/FileInspectorService` | `proc_open` + sondowanie co 10 ms + `proc_terminate` po limicie — **synchronicznie**, w klatce. Jedyny dzisiejszy wzorzec pracy z procesem i punkt wyjścia dla tego kroku |
| `Module/FileInfo/Application/Port/ChecksumPort` | Kształt `begin`/`advance`/`stop` — kontrakt pracy kawałkowej, który ten krok ma powtórzyć dla procesu |
| `Presentation/Ui/Component/ProgressBar` | Tryb „postęp nieznany” (wędrujące wypełnienie) gotowy od kroku 23, bez użytkownika |
| `Presentation/Cli/GameLoop` | Pętla rysuje w każdym takcie; wyjście zawsze przez `break` — normalne albo z sygnału |
| `Module/FileInfo/Application/FileInfoSettings` | Sześć pozycji; **dwie z planu kroku 25 czekają na ten krok**: `du` i `backgroundTimeout` |

## Zakres

### 1. Port i usługa

Kontrakt powtarza kształt `ChecksumPort` z kroku 25, bo to ten sam wzorzec
widziany z drugiej strony:

```php
interface BackgroundProcessPort
{
    /** Uruchamia polecenie; nie czeka na nie ani chwili. */
    public function start(string $command, int $timeoutSeconds): BackgroundHandle;

    /** Zagląda, czy coś się zmieniło. Nigdy nie blokuje. */
    public function poll(BackgroundHandle $handle): BackgroundState;

    /** Przerywa i sprząta. Wolno wołać zawsze. */
    public function stop(BackgroundHandle $handle): void;
}
```

Do rozstrzygnięcia na starcie: czy uchwyt jest obiektem, czy identyfikatorem, i
czy usługa prowadzi **jedną** pracę (jak `ChecksumPort`), czy wiele naraz.

### 2. Sprzątanie przy wyjściu

Najważniejsza część kroku i jedyna, która wychodzi poza warstwę modułu.

Potomek przeżywa proces macierzysty, więc wyjście z aplikacji — **normalne
i z sygnału** — musi go ubić. Trzy drogi do rozważenia: `register_shutdown_function`
w usłudze, destruktor Singletona, jawne sprzątanie na ścieżce, którą terminal
wraca do trybu normalnego. Pierwsze dwie nie wymagają zmian w rdzeniu, trzecia
jest jawna, ale rozszerza kontrakt pętli.

### 3. Pierwszy użytkownik: `du`

- Wiersz „zajęte na dysku” w sekcji „Rozmiar” modułu `FileInfo`.
- Dla katalogu liczy **wraz z zawartością** i to jest jego główny sens.
- Postępu nie zna → pasek w trybie „nieznany” (krok 23).
- Startuje **na żądanie klawiszem**, tak samo jak suma kontrolna (D46).
- Dwie pozycje ustawień przesunięte z kroku 25: `du` (przełącznik) i
  `backgroundTimeout` (liczba z listy, wyżej niż `timeout` — sekundy w tle bolą
  mniej).

### 4. Pomiar

Praca tłowa **nie ma prawa kosztować klatki**, a to jest twierdzenie, które da się
sprawdzić: klatka z uruchomionym procesem tłowym ma mieścić się w tym samym
budżecie, co klatka bez niego. Sam pomiar wymaga scenariusza, który proces
naprawdę uruchamia — czyli pierwszego scenariusza `bin/render-bench` sięgającego
poza PHP.

## Poza zakresem

- **Kolejka wielu prac naraz** — jeśli usługa prowadzi jedną pracę, to jedną.
- **Strumieniowanie wyjścia w trakcie** (postęp z tego, co proces już wypisał).
- **Uruchamianie procesów interaktywnych** — potomek nie dostaje wejścia.
- **Sumy kontrolne przez `sha256sum`** — krok 25 rozstrzygnął, że własny odczyt
  jest lepszy, bo zna postęp. Ten krok tego nie odwraca.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/BackgroundProcessPort.php` | Application | Nowy — start, doglądanie, przerwanie. |
| `Application/Dto/BackgroundState.php` | Application | Nowy — stan pracy jako dana oglądana co klatkę. |
| `Infrastructure/Process/BackgroundProcessService.php` | Infrastructure | Nowy — `proc_open`, potoki nieblokujące, limit czasu, sprzątanie. |
| `Module/FileInfo/**` | Moduł | Wiersz `du`, dwie pozycje ustawień, doglądanie w `FileInfoState`. |
| `Infrastructure/Diagnostics/**` | Infrastructure | Scenariusz z uruchomionym procesem tłowym. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Czwarta reguła pracy tłowej: sprzątanie przy wyjściu. |
| testy | Testy | Start bez blokowania, wynik, przerwanie, limit czasu, **zero procesów po wyjściu**, `du` dla katalogu. |

## Do rozstrzygnięcia na starcie kroku

1. **Jedna praca naraz czy wiele.**
2. **Kształt uchwytu** — obiekt czy identyfikator.
3. **Którą drogą sprzątać przy wyjściu** (punkt 2 zakresu).
4. **Czy `du` liczy się dla plików, czy tylko dla katalogów.**
5. **Co pokazuje wiersz, zanim ktoś naciśnie klawisz** — podpowiedź jak przy
   sumie kontrolnej czy coś innego.

## Kryteria ukończenia

- Proces tłowy startuje, jest doglądany i kończy się **bez zatrzymania klatki** —
  sprawdza to pomiar, nie wrażenie.
- Po zamknięciu aplikacji w trakcie pracy **nie zostaje ani jeden proces
  potomny**. Sprawdza to test.
- Limit czasu obowiązuje i jest osobny od limitu `file`.
- `du` pokazuje zajętość katalogu wraz z zawartością, paskiem w trybie „nieznany”.
- Kontrakt modułu z kroku 20 **nie zyskał ani jednej metody**.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- Dokumentacja opisuje czwartą regułę pracy tłowej — zgodnie z D46.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
