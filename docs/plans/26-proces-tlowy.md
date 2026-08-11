# Krok 26 — Proces tłowy jako mechanizm rdzenia

> **Skąd ten krok.** Powstał 2026-08-10, przy starcie kroku 25. Rozstrzygając,
> jak liczyć `sha256`, użytkownik dopisał: *„`hash_init()` + `hash_update_stream()`
> w procesie w tle, który przekazuje dane do procesu głównego. **Komponent procesu
> robimy jako osobny krok planu.** Teraz jedynie własny odczyt po kawałku”* —
> a dopytany o zakres wybrał wariant szerszy: **mechanizm rdzenia, do użytku
> każdego modułu**, nie prywatna sprawa jednego.

## Status

**Ukończony** (2026-08-11).

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

## Rozstrzygnięcia startu kroku

Wszystkie pięć rozstrzygnął użytkownik 2026-08-11; uzasadnienia w
[00-decyzje.md](00-decyzje.md), D47.

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Jedna praca naraz czy wiele | **Jedna** — nowe zamówienie przerywa poprzednie |
| 2 | Kształt uchwytu | **Obiekt** `BackgroundHandle` |
| 3 | Którą drogą sprzątać przy wyjściu | **Obiema**: jawnie w `Bootstrap::shutdown()` + `register_shutdown_function` |
| 4 | Czy `du` liczy się dla plików | **Tylko dla katalogów** — dla pliku mówią o tym bloki i-węzła |
| 5 | Co pokazuje wiersz przed klawiszem | **Podpowiedź**, jak przy sumie kontrolnej; klawisz `d` |

## Dziennik realizacji

### 2026-08-11 — mechanizm, pierwszy odbiorca i scenariusz pomiaru

**Co powstało w rdzeniu.** `Application\Port\BackgroundProcessPort`
(`start`/`poll`/`stop`), `Application\Dto\BackgroundHandle`,
`BackgroundState` i `BackgroundStage` oraz
`Infrastructure\Process\BackgroundProcessService` za nimi. Usługa prowadzi
**jedną pracę naraz**, czyta oba potoki co doglądanie (strumień błędów czytany
i wyrzucany — nieczytany potok zatrzymałby potomka), pilnuje limitu czasu
i ubija potomka `SIGKILL`em. Wyjście przycina do 64 KiB, a nadmiar czyta
i wyrzuca — przestać czytać znaczyłoby zatrzymać potomka.

**Sprzątanie idzie dwiema drogami.** `Bootstrap::shutdown()` woła `shutdown()`
usługi przed zapisem historii i przywróceniem terminala; niezależnie od tego
usługa rejestruje **leniwie**, przy pierwszym uruchomieniu pracy, funkcję
zamknięcia procesu. Druga droga nie jest ostrożnością teoretyczną:
`bin/light-manager` wychodzi z błędu startowego przez `exit(1)` **bez** wołania
`Bootstrap::shutdown()`, więc bez niej ta ścieżka zostawiałaby potomka.

**Pierwszy odbiorca.** Wiersz „zajęte na dysku” w sekcji „Rozmiar” modułu
`FileInfo`, liczony `du -sB1` na żądanie klawiszem `d`, **tylko dla katalogów**.
Do modułu weszły: `MeasureDiskUsageUseCase` (składa polecenie, tłumaczy stan
procesu na `DiskUsageState`), `DiskUsageState`/`DiskUsageStage` oraz dwie
pozycje ustawień odłożone w kroku 25 — `diskUsage` i `backgroundTimeout`
(5/15/30/60 s, domyślnie 15). Uchwyt pracy trzyma `FileInfoState`, bo to on wie,
kiedy zaznaczenie się zmienia i kiedy ekran się zamyka.

**`ProgressBar` dostał wreszcie użytkownika trybu „nieznany”** — tego, który od
kroku 23 miał wyłącznie test i scenariusz pomiaru. `FileInfoScreen` zadeklarował
w tym celu `NeedsTime`: wędrujące wypełnienie potrzebuje zegara, a ten przychodzi
z pętli, nie z `microtime()` w środku.

**Odstępstwo od planu, drobne i nazwane.** Plan przewidywał moduł sięgający po
port „tak samo, jak po `ImagePreviewPort`” — i tak jest — ale **nie powstał
osobny port modułu** w rodzaju `DiskUsagePort`. Powód jest twardy: usługa
modułu byłaby Singletonem, a Singleton nie przyjmuje zależności przez
konstruktor, więc musiałaby sięgnąć po `BackgroundProcessService::getInstance()`
— czyli po `Infrastructure` **rdzenia**, czego reguła 15 zabrania wprost. Rolę
kontraktu pracy pełni więc bezstanowy przypadek użycia, a właścicielem pracy
jest stan ekranu. Kontrakt modułu z kroku 20 nie zyskał ani jednej metody.

**Dług spłacony przy okazji.** Rachunek zamieniający bajty na czytelny zapis
wyszedł z prywatnej metody `InspectSelectedEntryUseCase` do
`Module\FileInfo\Application\SizeText` — wiersz `du` byłby jego trzecim
wołającym w tym samym module. Wyprowadzenie **nie sięga do przeglądarki**: ona
ma własny, bliźniaczy rachunek w `EntryList` i tam zostaje (reguła 15).

**Testy.** 29 nowych: `BackgroundProcessServiceTest` (11, na **prawdziwych**
procesach — start bez czekania, doglądanie bez blokowania, wynik i kod wyjścia,
strumień błędów poza wynikiem, limit czasu, przerwanie, wyparcie pracy, dwie
drogi sprzątania) i `DiskUsageTest` (15, na atrapie portu) plus poprawki
istniejących. Najważniejszy z nich uruchamia **osobny proces PHP**, który zamawia
pracę i kończy się bez sprzątania — a potem sprawdza, czy potomek zniknął.
Potomek podaje swój numer sam (`echo $$ > plik; exec sleep`), bo usługa go nie
ujawnia; `exec` jest tam konieczne, bo bez niego numer należałby do powłoki,
a `sleep` przeżyłby ubicie rodzica — czyli test powielałby błąd, który ma łapać.

**Kontrola jakości.** PHPUnit 946 zielonych, PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag.

### Pomiar — praca tłowa nie kosztuje klatki

Przebieg na zwolnionej maszynie (rozstrzygnięcie użytkownika, reguła 17),
15 przebiegów mierzonych po 3 rozgrzewkowych, konfiguracja odniesienia
1000×600 px / 166×46 / paleta 64 / motyw grafit. Wzorzec:
`docs/pomiary/2026-08-11-po-kroku-26.json`, porównanie z wzorcem kroku 25.
**Ani jeden wiersz nie dostał znacznika rozrzutu.**

| Scenariusz | Rysowanie | Kwantyzacja | Kodowanie | Razem | Rozrzut | Blob |
|---|---|---|---|---|---|---|
| ramki z tekstem | 5,6 ms | 7,3 ms | 4,3 ms | **17,5 ms** | 16,4–18,7 | 23,6 kB |
| klatka z pracą w tle | 5,5 ms | 7,2 ms | 4,4 ms | **17,0 ms** | 16,5–18,0 | 23,6 kB |

Klatka z uruchomionym procesem potomnym wyszła **o 0,5 ms szybsza** od klatki bez
niego — co znaczy dokładnie tyle, że koszt pracy tłowej leży **poniżej
rozdzielczości pomiaru**: zakresy rozrzutu obu wierszy nakładają się niemal
w całości, a w trzech przebiegach kontrolnych znak różnicy zmieniał się (17,2 vs
17,7; 18,5 vs 18,0; 17,2 vs 17,4). Identyczny rozmiar bloba w obu wierszach
potwierdza to, na czym cały ten scenariusz stoi: **klatka jest ta sama co do
prymitywu**, więc porównywane są dwa razy te same piksele — raz z sąsiadem obok
pętli, raz bez niego.

Twierdzenie z celu kroku — *„`du` liczy się cztery sekundy, a pętla nie gubi ani
jednej klatki”* — jest więc **sprawdzone, a nie zadeklarowane**. Przy budżecie
33 ms (30 kl./s) klatka z pracą tłową zostawia niecałe 16 ms zapasu, dokładnie
tyle samo, co przed tym krokiem.

**Reszta scenariuszy bez zmian.** Porównanie z wzorcem kroku 25 nie pokazało
regresji nigdzie: różnice mieszczą się w ±2,5%, a `zwijane sekcje` i `paski
postępu` — czyli scenariusze, których krok w ogóle nie dotyka — wypadły
w granicach ±0,4%. To jest ta sama kontrola co zwykle, tylko że tym razem
potwierdza rzecz negatywną: **rdzeń renderowania nie zauważył, że coś do niego
dopisano.**

### Zaobserwowane przy okazji (nie naprawiane w tym kroku)

`--compare` bez wartości wybiera wzorzec **najnowszy po nazwie**, a nazwy
w `docs/pomiary/` mieszają dwie konwencje: `po-kroku-NN` i `przed-krokiem-NN`.
Alfabetycznie `2026-08-10-przed-krokiem-21.json` stoi **za**
`2026-08-10-po-kroku-25.json`, więc porównanie bez podanej ścieżki sięgnęło po
wzorzec sprzed czterech kroków, zamiast po najświeższy. Data w nazwie ratuje
sytuację między dniami, ale nie w obrębie jednego. Naprawa należy do narzędzia
pomiarowego, a nie do tego kroku — zapisane, żeby nie zginęło.
