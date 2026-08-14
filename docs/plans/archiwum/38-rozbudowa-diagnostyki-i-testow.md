# Krok 38 — Rozbudowa diagnostyki, benchmarku i testów funkcjonalnych

> **Skąd ten krok.** Żądanie użytkownika z 2026-08-13
> ([00-decyzje.md](../00-decyzje.md), D61): rozbudować narzędzia diagnostyczne,
> pomiar wydajności i testy funkcjonalne — wraz ze scenariuszami obu rodzajów,
> pomiarowymi i funkcjonalnymi.

## Status

**Ukończony** (2026-08-13). Kod, testy, wzorce i dokumentacja gotowe: PHPStan
`max` bez błędów, PHP-CS-Fixer bez uwag, **1303 testy** (3381 asercji) zielone,
wzorce trzech torów zapisane na odciążonej maszynie, istniejące scenariusze bez
regresji. Rozstrzygnięcia startowe zapadły przed pierwszą linią kodu — pełna
treść w [00-decyzje.md](../00-decyzje.md), D64, skrót niżej w sekcji
„Rozstrzygnięcia”.

Jedna rzecz **nie** została naprawiona i jest zapisana świadomie: tor okienkowy
kończy przebieg naruszeniem ochrony pamięci przy sprzątaniu GLFW. Usterka jest
starsza od tego kroku (sprawdzone na kodzie sprzed niego), dzieje się **po**
wypisaniu pełnego wyniku i nie zniekształca żadnej liczby.

## Cel

Wyrównać zaległości narzędzi wobec aplikacji, która przez Fazy IV–IX urosła
szybciej niż jej miary: domknąć spis scenariuszy pomiarowych, zmierzyć to, co
dziś zimne i niemierzone (pierwsza klatka, tor tekstowy), dać regresji
wizualnej wykrywacz lepszy niż ludzkie oko i zebrać rozproszone testy
przepływów w nazwany katalog przebiegów funkcjonalnych.

Miarą powodzenia jest zdanie: **każdy element interfejsu ma scenariusz
pomiarowy albo zapisany powód pominięcia; dług „ciepłej miniatury” z kroku 16
jest zamknięty; wszystkie trzy renderery mierzy to samo narzędzie; a
najważniejsze przebiegi użytkownika są testami z nazwami, nie skutkiem
ubocznym testów jednostkowych.**

## Zależności

- **Kroki 16 i 17** twardo — krok rozbudowuje narzędzie i metodykę, które tam
  powstały (D28, D33); nic tu nie powstaje od zera.
- **Krok 18** — komponenty rdzenia oraz `ScreenFixture`, przez który idą
  przebiegi funkcjonalne.
- **Krok 21** — przebiegi idą przez **prawdziwe moduły** w `ScreenFixture`,
  nie przez sobowtóry.
- **Krok 26** — wzorzec scenariusza sięgającego poza PHP (`background`)
  i stuby procesów w testach.
- **Krok 30** — ostatni, który dołożył scenariusz (`highlight`); od niego
  liczy się stan spisu.
- **Krok 33** — rozstrzygnięcie nr 5 tamtego kroku („zmiana rozmiaru to zimna
  klatka, nie scenariusz”) jest wprost poprzednikiem osi zimnej klatki.
- **Krok 35** — tor okienkowy pomiaru; każdy nowy scenariusz musi przejść
  przez wszystkie tory naraz.

Od kroków **31, 32, 36 i 37** nie zależy i one nie zależą od niego. Z krokami
31 i 32 umawia się tylko co do granicy: scenariusze `tree` i `menu` przynoszą
tamte kroki (rytm D48), nie ten.

## Model i wysiłek

**Opus / high** — z tego samego powodu, co krok 16: metodyka, nie objętość.
Najłatwiejszy błąd tego kroku to pomiar, który wygląda solidnie i nie znaczy
nic (zimna klatka mierzona ciepłym procesem, złota klatka regenerowana bez
czytania), oraz test funkcjonalny, który dubluje jednostkowy zamiast sprawdzać
przebieg.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `bin/render-bench` | tryby pomiar / `--png` / pomoc; osie `--size`, `--grid`, `--palette`, `--text-aa`, `--stroke-aa`, `--theme`, `--font`, `--iterations`, `--warmup`; wzorce `--save` / `--compare` / `--threshold`; przesył `--transfer`; tor okienkowy `--window` |
| Scenariusze | **szesnaście**, od `empty` do `highlight`; od kroku 22 każdy krok komponentowy dokłada własny |
| Tor tekstowy | `TextFrameRenderer` jest **jedynym z trzech tłumaczy słownika prymitywów bez toru w narzędziu** |
| Rozgrzewka | próbki rozgrzewkowe są **odrzucane**, nie raportowane — zimny koszt pierwszej klatki nigdzie nie trafia |
| Dług kroku 16 | pomiar miniatury jest „ciepły”: zimne dekodowanie JPEG (387 → 102 ms z kroku 12) pozostaje niemierzone |
| Zrzut PNG | jest (`--png`); **porównania zrzutów nie ma** — regresję wizualną wykrywa człowiek oglądający obraz |
| Wzorce | `docs/pomiary/*.json` — siedemnaście plików, trzy schematy nazw; metryczka środowiska **bez obciążenia maszyny**, choć to ono unieważniło pomiary w krokach 16 i 22 |
| Testy przepływów | `ScreenFixture` (komplet ekranów i modułów bez FS, terminala i Imagicka), `ScriptedTerminal`, `GameLoopTest`, `CommandWindowFlowTest`, `BrowserSplitTest`, `TextScrollTest` — przebiegi **istnieją, ale rozproszone** i bez wspólnego spisu |
| Pominięcia świadome | krok 28: okno potwierdzenia mieści się w koszcie `popup`; krok 33: zmianę rozmiaru mierzy zimna klatka, nie osobny scenariusz — **wzorzec zapisu powodu, który ten krok ma upowszechnić** |

## Zakres

### 1. Spis „element → scenariusz” i domknięcie luk

Przejrzeć komponenty rdzenia, okna nakładane i ekrany przeciwko liście
scenariuszy `bin/render-bench`. Wynik ma dwie dopuszczalne postaci: **nowy
scenariusz** albo **zapisany powód pominięcia** — wzorem kroku 28 (okno
potwierdzenia mieści się w koszcie `popup`) i kroku 33 (zmiana rozmiaru to
zimna klatka). Kandydaci do sprawdzenia, bez przesądzania wyniku:

| Kandydat | Czego dziś nie widać |
|---|---|
| ekran ustawień | zakładki i pola wyboru z kroku 18 — ekran rdzenia bez scenariusza |
| pasek filtra | `highlight` mierzy znaczniki dopasowania, nie panel filtra z polem tekstowym (krok 30) |
| ekran pomocy | sekcje mierzy `sections`; czy reszta ekranu mieści się w ich koszcie — do zapisania |

Reguła rozdzielności obowiązuje bez wyjątku: nowy scenariusz musi dać się
**rozliczyć w parze** z istniejącym, jak `highlight` z `columns`.

### 2. Oś „zimna klatka”

Rozgrzewka słusznie oddziela pomiar ustalony od pierwszej klatki — ale koszt
pierwszej klatki też jest prawdziwy: płaci go każdy start aplikacji i każda
zmiana rozmiaru okna (krok 33 zapisał wprost, że przebudowę po `SIGWINCH`
mierzy właśnie zimna klatka). Dziś ten koszt jest odrzucany razem z próbkami
rozgrzewki. Oś zimnej klatki ma go raportować **osobno, obok mediany, nie
zamiast niej** — i tym samym zamknąć dług kroku 16: zimne dekodowanie JPEG
w scenariuszu `thumbnail`, niewidoczne, bo `ThumbnailService` pamięta bitmapę
między klatkami. Sposób osiągnięcia zimna (osobny proces na próbkę albo
pierwsza próbka rozgrzewki raportowana osobno) — pytanie nr 2.

### 3. Tor tekstowy pomiaru

`PrimitiveTranslationTableTest` pilnuje, żeby każdy kształt słownika miał
tłumaczenie we wszystkich trzech rendererach — ale mierzone są dwa. Tor
tekstowy domyka parytet: te same scenariusze, fazy nazwane po swojemu
(tłumaczenie prymitywów na bufor ANSI; kwantyzacji nie ma i zero w kolumnie
mówi to wprost, jak w torze okienkowym), przesył mierzalny pod prawdziwym
terminalem tą samą drogą, co sixelowy. Tryb zapasowy przestaje być jedynym,
o którego koszcie nikt nic nie wie — a regresja w nim przestaje być
niewidzialna.

### 4. Porównanie zrzutów — regresja wizualna

Najważniejsze odkrycie kroku 13 (kwantyzator zjadający obwódki przy 16 i 32
kolorach) było niewidoczne w czasie i rozmiarze bloba — wyszło z obejrzenia
obrazu. Krok 16 dał zrzut (`--png`), ale oglądanie zostało człowiekowi. Tryb
porównania zrzutów robi z obrazu miarę: wzorcowy PNG per scenariusz,
porównanie metryką różnicy Imagicka z progiem, a przy przekroczeniu — obraz
różnicy zapisany obok i wynik „niezgodny” ze wskazaniem plików. Bez GUI i bez
raportów: narzędzie ma odmówić i wskazać, nie rysować.

### 5. Zrzut diagnostyczny z żywej aplikacji (wejście warunkowe — pytanie nr 5)

`render-bench` mierzy treść syntetyczną i deterministyczną — i taka ma
zostać. Ale dwie pomyłki kroku 29 wyszły dopiero na zrzucie **prawdziwej**
klatki spod XTerma (D60), robionym ręcznie narzędziem systemowym. Komenda
diagnostyczna w oknie komend — zapis bieżącej klatki (prymitywy i/lub PNG) do
pliku — dałaby ten sam dowód bez sprzętu i bez ceremonii. Wchodzi tylko,
jeśli użytkownik uzna, że jest warta miejsca w rejestrze komend.

### 6. Katalog przebiegów funkcjonalnych

Testy przepływów istnieją i są dobre — ale są **skutkiem kroków, nie spisem
zachowań**: okno komend ma swój przebieg, bo krok 19 go napisał; podział ma
swój, bo napisał go krok 24. Nikt nie wie, których przebiegów **nie ma**.
Krok zbiera je w katalog nazwanych scenariuszy funkcjonalnych — każdy jest
sekwencją klawiszy przez `ScreenFixture` z asercjami w punktach kontrolnych —
i uzupełnia brakujące. Kandydaci (lista do przycięcia na starcie kroku):

| Przebieg | Co utrwala |
|---|---|
| start i dno stosu | `StartupScreen` → ekran wybrany z konfiguracji (D42) |
| podróż po katalogach | wejścia, cofnięcia, wpisy ukryte, zaznaczenie po powrocie (D20) |
| filtr od litery do `Esc` | zawężanie, podświetlenie, `Esc` odmawia przy niepustym (D59) |
| dwa panele | ognisko, niezależne ścieżki, powrót do jednego panelu (D45) |
| opis pliku z pracą tłową | sekcje, pasek postępu, `du` przez stub (D46, D47) |
| podgląd tekstu | ognisko `Tab`em, przewijanie linijkami, `End` bez numerów (D60) |
| okno komend z historią | podpowiedzi, argumenty, historia, przejścia (D39) |
| potwierdzenie | obie drogi decyzji, `Esc` znaczy „nie” (D56) |
| zmiana rozmiaru w trakcie | klatka po zmianie rozmiaru z otwartym oknem nakładanym (krok 33) |
| ustawienia i język | zmiana pozycji, przywrócenie domyślnych, przełączenie języka (D31, D32) |

### 7. Złote klatki na prymitywach (kształt do rozstrzygnięcia)

`ScenarioFactory` buduje klatki deterministycznie — bajt w bajt te same przy
każdym uruchomieniu — więc ten sam katalog scenariuszy może służyć testom:
serializacja prymitywów klatki porównana ze złotym plikiem łapie każdą
niezamierzoną zmianę treści, niezależnie od renderera. To jest wprost wspólny
mianownik scenariuszy pomiarowych i funkcjonalnych — i zarazem miejsce,
w którym najłatwiej przesadzić: złoty plik łapie też każdą zamierzoną zmianę,
a regenerowany bez czytania przestaje być testem. Kształt (złote pliki czy
asercje celowane) — pytanie nr 7.

### 8. Higiena pomiaru i wzorców

Dwa drobiazgi, oba z blizn:

- **Obciążenie maszyny w metryczce wzorca.** Pomiary kroku 16 zakłócił proces
  w tle (+32% „regresji” bez zmiany w kodzie), w kroku 22 `--save` odmówił
  zapisu — a `CLAUDE.md` każe przed każdym pomiarem prosić użytkownika
  o zwolnienie mocy hosta. Narzędzie ma zapisywać obciążenie (loadavg) do
  metryczki i pokazywać je przy `--compare`; czy przy wysokim obciążeniu
  `--save` odmawia, czy ostrzega — pytanie nr 9.
- **Konwencja nazw wzorców.** `docs/pomiary/` ma siedemnaście plików w trzech
  schematach nazw; README pomiarów dostaje konwencję i spis.

## Poza zakresem

- **Scenariusze `tree` i `menu`** — należą do kroków 31 i 32 (D48).
- **Bramka wydajności w CI** — odrzucona w kroku 16; rozrzut, który ją
  pogrzebał, nie zniknął.
- **Profilowanie** (flame graph, XDebug, zużycie pamięci funkcja po funkcji)
  — narzędzie mierzy fazy, nie funkcje; ewentualny szczyt pamięci jako
  kolumna to pytanie nr 8, nie profilowanie.
- **Pomiar pełnej iteracji pętli z prawdziwym systemem plików** —
  rozstrzygnięcie nr 5 kroku 16 obowiązuje, dopóki użytkownik go nie otworzy
  (pytanie nr 1).
- **Zrzuty spod prawdziwego terminala w testach** — weryfikacja pod XTermem
  zostaje ręczna, na odciążonej maszynie, jak każe `CLAUDE.md`.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Infrastructure/Diagnostics/**` | Infrastructure | nowe scenariusze, oś zimnej klatki, tor tekstowy, porównanie PNG, obciążenie w metryczce |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | ewentualne jawne kroki dla pomiaru — wyłącznie za zgodą (zero kodu pomiarowego w produkcji, D28) |
| `Presentation/**` | Presentation | ewentualna komenda zrzutu klatki (pytanie nr 5) |
| `lang/pl.php`, `lang/en.php` | — | klucze `bench.*` nowych trybów i kolumn |
| `tests/**` | Testy | katalog przebiegów funkcjonalnych (miejsce i nazwa — pytanie nr 6); testy części czystych nowych klas |
| `docs/pomiary/README.md` | Dokumentacja | konwencja nazw wzorców, czytanie porównań zrzutów |
| `README.md`, `docs/architecture.md`, `SKILL.md` | Dokumentacja | nowe tryby; konwencja testów funkcjonalnych, jeśli powstanie |

## Rozstrzygnięcia (decyzje użytkownika, 2026-08-13)

Pełne uzasadnienia: [00-decyzje.md](../00-decyzje.md), D64. Numeracja odpowiada
pytaniom z sekcji niżej; dwa ostatnie wiersze to doprecyzowania, których
pytania nie przewidziały.

| # | Rozstrzygnięcie |
|---|---|
| 1 | Potok i przesył zostają główną miarą; takt pętli wchodzi **dodatkowo**, jako osobny tryb `--loop` z własną tabelą i własnym wzorcem |
| 2 | Zimna klatka = **pierwsza próbka rozgrzewki raportowana osobno**, kolumna wchodzi do wzorca, ale nigdy nie podnosi alarmu regresji |
| 3 | `TextFrameRenderer` dostaje **jawne kroki pomiarowe** (zmiana produkcyjna, wzorem enkodera z kroku 16; zegar zostaje po stronie narzędzia) |
| 4 | Wzorcowe PNG **w repozytorium** (`docs/pomiary/wzorce-png/`), metryka **AE** z progiem, obraz różnicy zapisywany przy przekroczeniu |
| 4b | Zrzuty robią **oba tory**, wzorce okienkowe również w repozytorium (z luźniejszym progiem, bo rysuje sterownik GPU) |
| 5 | Komenda zrzutu **wchodzi**: prymitywy **i** PNG, **wiernie każdemu torowi** — płótno (Sixel), bufor GPU (okno), rasteryzacja bufora ANSI (tekst) |
| 6 | Przebiegi funkcjonalne w **`tests/Functional/`**, osobna testsuite; start i zmiana rozmiaru idą przez `GameLoop` ze `ScriptedTerminal`, reszta przez `ScreenFixture` |
| 7 | **Złote pliki** serializacji prymitywów; różnica wskazuje pierwszy różniący się prymityw, regeneracja wyłącznie jawnym poleceniem |
| 8 | Szczyt pamięci **wchodzi** jako kolumna (jedna liczba na scenariusz) |
| 9 | `--save` przy obciążeniu **ostrzega, nie odmawia**; loadavg na rdzeń wchodzi do metryczki i pokazuje się przy `--compare` |

## Do rozstrzygnięcia na starcie kroku

*(rozstrzygnięte 2026-08-13 — patrz tabela wyżej; pytania zostają dla
kontekstu)*

1. **Granica zakresu pomiaru**: zostaje „tylko potok renderowania i przesył”
   (rozstrzygnięcie nr 5 kroku 16), czy krok otwiera także pomiar taktu
   pełnej pętli na sztucznym stanie, bez systemu plików?
2. **Zimna klatka**: osobny proces na próbkę (zimna z konstrukcji, wolna) czy
   pierwsza próbka rozgrzewki raportowana osobno (tania, ale singletony
   pozostają ciepłe)? I czy zimna kolumna wchodzi do wzorca, czy jest tylko
   wypisywana?
3. **Tor tekstowy**: czy `TextFrameRenderer` dostaje jawne kroki jak enkoder
   w kroku 16 (zmiana produkcyjna), czy pomiar jedną fazą wystarczy?
4. **Regresja wizualna**: metryka i próg porównania; czy wzorcowe PNG wchodzą
   do repozytorium (rozmiar!) i dokąd; czy tor okienkowy też robi zrzuty
   (odczyt bufora GPU), czy tylko sixelowy?
5. **Zrzut z żywej aplikacji**: czy komenda wchodzi, co zapisuje (prymitywy,
   PNG, jedno i drugie) i dokąd.
6. **Przebiegi funkcjonalne**: gdzie mieszkają (nowy katalog w `tests/`?),
   jak się nazywają i czy któreś idą też przez `GameLoop` ze
   `ScriptedTerminal`, a nie tylko przez `ScreenFixture`.
7. **Złote klatki**: serializacja prymitywów scenariuszy jako złote pliki czy
   wyłącznie asercje celowane?
8. **Szczyt pamięci**: czy wchodzi jako kolumna raportu (krok 16 zostawił
   profilowanie pamięci poza zakresem)?
9. **`--save` na obciążonej maszynie**: odmowa czy ostrzeżenie — i od jakiego
   progu obciążenia?

## Kryteria ukończenia

- Spis „element → scenariusz” istnieje i jest kompletny: nowe scenariusze
  wchodzą, pominięcia mają zapisany powód.
- Każdy nowy scenariusz przechodzi przez **wszystkie tory pomiaru naraz**
  (sixelowy, okienkowy — i tekstowy, jeśli powstanie) i rozlicza się w parze
  z istniejącym scenariuszem.
- Dług „ciepłej miniatury” z kroku 16 zamknięty: zimny koszt pierwszej klatki
  zmierzony i zapisany.
- Regresja wizualna wykrywalna bez człowieka: porównanie zrzutów wskazuje
  różnicę, której nie widać w czasach ani w rozmiarze bloba.
- Przebiegi funkcjonalne z listy zakresu przechodzą jako nazwane testy — bez
  systemu plików, terminala i Imagicka.
- W kodzie produkcyjnym **zero nowych wywołań pomiarowych** (D28); ewentualne
  szwy — jawnie rozstrzygnięte i zapisane w dzienniku.
- Wzorzec „po kroku 38” zapisany na odciążonej maszynie (za potwierdzeniem
  użytkownika, jak każe `CLAUDE.md`); istniejące scenariusze **bez
  regresji**, bo krok nie dotyka potoku renderowania.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, wszystkie testy zielone;
  `README.md` i `docs/pomiary/README.md` opisują nowe tryby.

## Dziennik realizacji

### 2026-08-13 — rozstrzygnięcia startowe i cała treść kroku

**Rozstrzygnięcia** (dziewięć pytań planu plus dwa doprecyzowania, które
wyszły dopiero z odpowiedzi) zapadły przed pierwszą linią kodu i są zapisane
w [00-decyzje.md](../00-decyzje.md), D64, oraz w tabeli wyżej.

**Co powstało:**

1. **Spis „element → scenariusz” domknięty** ([docs/pomiary/README.md](../../pomiary/README.md)).
   Przegląd dał **jeden** nowy scenariusz i cztery zapisane pominięcia:
   - `settings` — ekran ustawień; `Tabs`, `Choice`, `Toggle`, `Button`,
     `Spacer` i `VStack` okazały się **jedynymi komponentami rdzenia bez
     jakiegokolwiek pomiaru**. Rozlicza się w parze z `chrome-text`: obwódki,
     wiersz ścieżki i pasek stanu są w obu **co do prymitywu takie same**
     (pilnuje tego test porównujący podpisy płaszczyzn), więc różnica jest ceną
     treści ekranu ustawień;
   - **pasek filtra** pominięty z powodem: `Panel` plus `TextInput` to okno
     komend bez listy podpowiedzi, czyli podzbiór `command`, a zawężona lista
     pod spodem jest treścią `highlight`. Nie dałby się rozliczyć w parze
     z niczym;
   - **ekran pomocy** pominięty: jego treść to `SectionList` (mierzy
     `sections`), a oprawa — `chrome-text`; scenariusz powtarzałby sumę dwóch
     istniejących. Podobnie `MessageOverlay` (to `Dialog`, czyli `popup`)
     i `StartupScreen` (nie rysuje klatki).
2. **Oś zimnej klatki i kolumna pamięci.** Pierwsza próbka rozgrzewki przestała
   być odrzucana i stoi w kolumnie „Zimna” obok mediany; szczyt pamięci liczy
   się od zera dla każdego scenariusza (`memory_reset_peak_usage()`). Dług
   „ciepłej miniatury” z kroku 16 **spłacony**: różnica jest dramatyczna
   i widoczna od razu — ekran ustawień kosztuje 17 ms ustalone wobec **174 ms
   zimnych**, puste płótno 7,8 ms wobec 22,8 ms.
3. **Tor tekstowy pomiaru** (`--text`). `TextFrameRenderer` dostał dwa publiczne
   kroki (`composeBuffer()`, `encode()`) — jedyna zmiana produkcyjna tego kroku,
   przyznana rozstrzygnięciem nr 3 i zbudowana wzorem enkodera z kroku 16: zegar
   został po stronie narzędzia, w rendererze nie ma ani jednego wywołania
   pomiarowego. Tryb zapasowy przestał być jedynym tłumaczem słownika prymitywów
   bez pomiaru.
4. **Cztery tory zamiast przełącznika.** `windowed: bool` zamienił się w enum
   `BenchmarkTrack` (sixel / window / text / loop). Przyrostek toru wchodzi do
   podpisu konfiguracji, a **tor sixelowy nie dokłada nic** — dzięki temu
   wszystkie siedemnaście wzorców sprzed tego kroku zostaje porównywalnych.
   Odczyt wzorca rozumie też stary klucz `windowed`.
5. **Tryb `--loop`** — takt pętli na sztucznym stanie: odczyt wejścia →
   aktualizacja stanu → złożenie klatki przez `FrameComposer`, bez renderera
   i bez przesyłu. Treść daje `LoopScenarioScreen` (siostra `ScenarioFactory`,
   te same trzy reguły), ujściem klatki jest `SinkFrameRenderer`. Wynik jest
   sam w sobie odpowiedzią: **0,1 ms na takt** wobec 7–17 ms potoku, czyli
   składanie klatki jest w budżecie 33 ms nieobecne — i to jest pierwsza liczba,
   która to potwierdza zamiast zakładać.
6. **Regresja wizualna bez człowieka.** `--png-save` / `--png-compare`, metryka
   AE, próg w promilach (0 ‰ dla Sixela, 5 ‰ dla okna), obraz różnicy zapisywany
   przy niezgodności, kod wyjścia 1. Wzorce leżą w `docs/pomiary/wzorce-png/`
   — **34 pliki, 1,4 MB**, oba tory. Każdy niesie w metadanych PNG podpis
   konfiguracji, więc porównanie z klatką w innym motywie kończy się słowem
   „nieporównywalny”, a nie fałszywą regresją; to ta sama reguła, którą wzorce
   liczbowe mają od kroku 16.
7. **Obciążenie maszyny w metryczce** (`loadPerCore`), pokazywane w raporcie
   i przy `--compare`. Przy zapisie **ostrzega, ale nie odmawia** — narzędzie
   zna tu przesłankę, a nie skutek; odmowa zostaje strażnikowi rozrzutu.
8. **Zrzut z żywej aplikacji**: komenda `core.dump` zamawia **następną** klatkę
   (wykonuje się przed jej powstaniem, więc okna komend na zrzucie nie ma),
   a zbiera ją `DumpingFrameRenderer` — dekorator, dzięki któremu `FrameComposer`
   został nietknięty, a koszt w ścieżce klatki to sprawdzenie jednego pola.
   Obraz jest **wierny torowi**: płótno po kwantyzacji (Sixel), bufor karty
   (okno), rasteryzacja bajtów ANSI (tekst). `AnsiRasterizer` powstał dla tego
   ostatniego i rasteryzuje **bajty**, a nie bufor komórek — dopiero w bajtach
   kolory przeszły przez zaokrąglenie do palety terminala.
9. **Złote klatki**: `tests/Golden/` (17 plików, 1054 wiersze), porównywane
   przez `GoldenFrameTest`, który przy różnicy wskazuje **pierwszy różniący się
   prymityw**. Regeneracja wyłącznie przez `./bin/render-bench --golden-save`.
10. **Katalog przebiegów funkcjonalnych** `tests/Functional/` (72 testy)
    i rozbicie testsuite na `unit` / `functional` — granica, której potrzebuje
    krok 39, powstała tym samym po stronie 38. Przeniesione: okno komend, filtr,
    dwa panele, podgląd tekstu, opis pliku z pracą tłową. Dopisane: **start
    i dno stosu** (jedyny przebieg przez `GameLoop` ze `ScriptedTerminal`, wraz
    ze zmianą rozmiaru przy otwartym oknie), **podróż po katalogach**
    i **ustawienia z potwierdzeniem**.

**Odstępstwa i rzeczy warte zapamiętania:**

- **Wspólna podstawa przebiegów.** Torów zrobiło się cztery, a pętla pomiaru
  była w nich co do słowa ta sama, więc metodyka (rozgrzewka, zimna klatka,
  szczyt pamięci, proces towarzyszący, sprzątanie) przeniosła się do
  `AbstractBenchmarkRunner`. Bez tego reguła „pierwsza próbka rozgrzewki jest
  zimną klatką” mieszkałaby w czterech miejscach naraz.
- **Tor tekstowy nie robi zrzutów w `bin/render-bench`** — `--text` z `--png*`
  kończy się odmową. Obraz z tego toru daje wyłącznie żywa aplikacja
  (`core.dump`), bo tylko tam wiadomo, jak wygląda bufor ANSI w chwili zrzutu.
- **Pomyłka złapana przez własne narzędzie:** pierwsza wersja rasteryzatora
  ANSI wypisywała w obrazie „[H [2J”, bo parser znał wyłącznie sekwencje
  koloru. Wyszło to na zrzucie obejrzanym gołym okiem — czyli dokładnie tą
  drogą, dla której ten krok w ogóle powstał.
- **Segfault toru okienkowego przy wyjściu (istniejący wcześniej).**
  `--window` kończy się naruszeniem ochrony pamięci **po** wypisaniu pełnego
  wyniku i po zapisaniu wszystkich plików. Sprawdzone przez odłożenie zmian
  (`git stash`): kod sprzed tego kroku zachowuje się tak samo, więc to usterka
  sprzątania GLFW, a nie skutek kroku 38. Zapisana jako znany brak — jej
  naprawa należy do kroku 37 albo do osobnego zgłoszenia.

**Bramka jakości:** PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag,
**1303 testy** (3381 asercji) zielone — w tym 72 funkcjonalne i 17 złotych
klatek.

**Wzorce zapisane na odciążonej maszynie** (za potwierdzeniem użytkownika, jak
każe `CLAUDE.md`; obciążenie 0,10–0,12 na rdzeń zapisane w metryczkach):

| Plik | Tor | Zawartość |
|---|---|---|
| `2026-08-13-po-kroku-38.json` | sixelowy | 17 scenariuszy |
| `2026-08-13-po-kroku-38-text.json` | tekstowy | 17 scenariuszy — **pierwszy wzorzec tego toru w historii projektu** |
| `2026-08-13-po-kroku-38-loop.json` | takt pętli | 1 wiersz |
| `wzorce-png/` | sixelowy + okienkowy | 34 wzorcowe zrzuty, 1,4 MB |

**Rozliczenie „przed i po”** wobec `2026-08-13-po-poprawkach-podgladu.json`:
**bez regresji powyżej progu**. Wszystkie szesnaście wspólnych scenariuszy
mieści się w +9,9%…−1,0%, przy czym drgnięcie jest **równomierne dla całego
przebiegu** — czyli dokładnie ten wzorzec, który `docs/pomiary/README.md` opisuje
jako różnicę środowiska, a nie kodu. I tak być musi: krok nie tknął ani jednej
klasy potoku rysowania. Jedyny wiersz bez punktu odniesienia to nowy scenariusz
`settings` (16,2 ms).
