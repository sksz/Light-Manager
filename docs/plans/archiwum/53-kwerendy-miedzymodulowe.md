# Krok 53 — Kwerendy: mechanizm, okno i wszystkie źródła danych rdzenia oraz trzech modułów

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 51 i 52, jako ostatnia
> trzecia Fazy XVIII ([00-decyzje.md](../00-decyzje.md), D85). To on jest powodem,
> dla którego faza ma trzy kroki, a nie dwa: **moduły mają się wzajemnie
> używać**, a reguła 15 mówi, że moduł nigdy nie sięga do innego modułu.

> **Uzupełniony 2026-08-15, tego samego dnia i przed pierwszą linią kodu**
> ([00-decyzje.md](../00-decyzje.md), D86). W pierwotnym brzmieniu krok wnosił
> mechanizm rdzenia o zasięgu na wszystkie moduły, ale kwerendy dawał **wyłącznie
> dwóm modułom, które sam dopiero powołuje**. Rozstrzygnięciem użytkownika
> kwerendy dostały także `browser`, `file-info` i `audio`, a odbiorcą tych bez
> konsumenta w kodzie został **użytkownik** — czyli krok dowozi **okno kwerend**.

> **Rozszerzony i podzielony 2026-08-16, na starcie kroku**
> ([00-decyzje.md](../00-decyzje.md), D92). Trzecie rozszerzenie tego samego kroku
> i najszersze z nich: kwerendę dostaje **wszystko, co da się przeczytać** —
> rdzeń wraz z własnym samoopisem i **sześć** modułów, a nie trzy. Rejestr
> kwerend przestaje przy tym być kanałem między modułami i staje się **jedyną
> drogą odczytu w całej aplikacji**, także wewnątrz rdzenia i wewnątrz modułu.
> Ciężar tego rozstrzygnięcia kazał krok podzielić: **tutaj zostaje mechanizm,
> okno, rdzeń i trzy moduły sprzed Fazy XVIII**, a kwerendy `ssh`, `docker`
> i `k8s` wraz z czynnością `k8s.deploy-image` przechodzą do
> [kroku 54](../54-kwerendy-modulow-kontenerowych.md).

## Status

**Ukończony** (2026-08-16). Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md),
D92; poprawki z realizacji: D93.

## Cel

Rdzeń i każdy moduł mają **oddawać to, o czym wiedzą**, jednym kanałem — takim,
którego użycie nie wymaga znajomości tego, kto odpowiada. Kanał jest przy tym
**jedyny**: nie ma drugiej drogi do danej, ani dla obcego modułu, ani dla
właściciela, ani dla rdzenia.

Miary powodzenia są trzy:

1. **Każde źródło danych rdzenia i trzech modułów tego kroku ma kwerendę**,
   a użytkownik widzi ich spis i umie zapytać sam — oknem kwerend spod `F12`.
2. **Odczyt idzie przez rejestr także wewnątrz**: żaden ekran i żaden komponent
   nie sięga po stan wprost. Właściciel dostaje przy tym **ładunek typowany**,
   więc jedna droga nie znaczy rysowania z tablic napisów.
3. **Routing nie kosztuje w klatce**: odczyt niezmienionego źródła to jedno
   wyszukanie w tablicy, a wiersze danych pierwotnych powstają dopiero wtedy, gdy
   ktoś o nie zapyta. Oś `--loop` bez regresji wobec wzorca po kroku 52.

## Zastrzeżenie pierwsze — reguła 15 nie zostaje złamana, tylko dopowiedziana

Reguła 15 brzmi: **moduł nigdy nie sięga do innego modułu.** Ten krok jej **nie
odwołuje** i odwołać nie ma prawa — to ona trzyma cały podział, na którym stoi
sześć modułów i pięć faz planu.

Dopowiedzenie brzmi: moduł sięga do **rdzenia**, a rdzeń trzyma rejestr, do
którego wpisał się ktoś inny. Dokładnie tak działa to od kroku 19 przy komendach
i od 46 przy zdarzeniach — nikt nie nazwał tego wtedy współpracą modułów, ale
nią było: `MenuOverlay` wywołuje komendę przeglądarki, nie znając przeglądarki.
Nowe jest **jedno**: kanał, którym wraca **dana**, a nie skutek dla interfejsu.

Granica, poza którą to przestaje być dopowiedzeniem, a staje się wyłomem, jest
częścią zakresu i ma trafić do `SKILL.md` wraz z powodem:

- moduł zna **nazwę** cudzej komendy i kwerendy (napis), nigdy jej typ;
- kwerenda oddaje obcym **dane pierwotne** (napisy, liczby, wartości logiczne) —
  ta sama zasada, którą kieruje się `ModuleContext` (D40 P5);
- **ładunek typowany wydaje się wyłącznie właścicielowi** — pilnuje tego
  `QueryResult::payloadFor()` w czasie działania i test wzorem
  `CoreKnowsNothingAboutFilesTest`;
- moduł pytający **musi umieć żyć bez odpowiedzi**, bo ten drugi bywa wyłączony,
  odrzucony albo nieobecny.

## Zastrzeżenie drugie — „jedyna droga odczytu” ma policzalną cenę

Rozstrzygnięcie D92 nr 3 dotyka miejsc, których w kodzie jest dużo, i to
w ścieżce rysowanej trzydzieści razy na sekundę. Policzone przy starcie kroku:
**29** miejsc czyta ustawienia, **18** — kontekst sesji, **68** — stan
przeglądarki, **23** — odtwarzacz playlisty.

Cena zostaje zapłacona **konstrukcją**, a nie wyjątkiem od reguły:

- **znacznik pokolenia** (`generation(): int`) — rejestr przelicza wynik
  wyłącznie po zmianie źródła; odczyt niezmienionego to jedno wyszukanie
  w tablicy;
- **leniwe wiersze** — dane pierwotne powstają przy pierwszym pytaniu o nie, więc
  właściciel czytający ładunek typowany nie płaci za budowę tablic, których nikt
  nie obejrzy;
- **ładunek typowany dla właściciela** — `BrowserScreen` nadal dostaje
  `Directory`, więc PHPStan `max` zostaje bez ani jednego wyciszenia.

Za to `QueryResult` **nie jest obiektem wartości w rozumieniu reguły 6**: jest
`final`, ale nie `readonly`, bo pamięta zbudowane wiersze. Powód stoi w jego
docblocku i w D92.

## Zastrzeżenie trzecie — odwołane

> **Odwołane 2026-08-16** (D92 nr 8). W brzmieniu z D86 mówiło: „kontekst mówi,
> gdzie użytkownik stoi; kwerenda mówi, co u mnie jest” — czyli `browser.cwd`
> i `browser.selection` nie powstają, bo `ModuleContext` rozdaje tę daną co
> klatkę za darmo. Zastrzeżenie broniło jednej rzeczy: żeby nie było **dwóch
> dróg** do tej samej danej. Po rozstrzygnięciu nr 3 druga droga nie istnieje
> — każdy odczyt idzie rejestrem, więc kontekst przestaje być wyjątkiem od kanału
> i staje się jednym z jego źródeł. `browser.cwd`, `browser.selection`
> i `core.context` **powstają**. Zdanie graniczne z D86 **nie wchodzi do
> `SKILL.md`**.

## Zależności

- **Krok 19** twardo i **potrójnie**: `CommandRegistry` jest wzorem, wedle
  którego powstaje rejestr kwerend, `CommandInterface` — wzorem kontraktu,
  a `CommandOverlay` (`F12`) — oknem, którego **drugim trybem** zostaje okno
  kwerend (D92 nr 7). W rejestrze komend nic zmieniać nie trzeba.
- **Krok 20** — `ProvidesQueries` staje obok `ProvidesCommands`
  i `ProvidesSettingsTab` w `Application/Module`, bo nie wymienia ani jednego
  typu z `Presentation` (kryterium podziału z D38 P2).
- **Krok 21** wzorcowo: `ModuleContext` jest precedensem na dane pierwotne
  przechodzące między modułami — i to on rozstrzyga, co kwerenda oddaje obcym.
- **Kroki 25 i 26** — kwerendy modułu opisu pliku oddają **stan pracy tłowej**
  (`ChecksumStage`, `DiskUsageStage`), a nie jej wynik po czekaniu. To jedyny
  w projekcie istniejący precedens na regułę nr 4 kwerendy.
- **Krok 43** — `browser.marked` oddaje **nazwy** zaznaczonych wpisów.
- **Krok 24** — kwerendy przeglądarki mówią o **obu panelach**; panel podaje się
  argumentem.
- **Krok 45** — `audio.playlist` i `audio.now-playing` czytają `PlaylistPlayer`
  i `Playlist`; kwerenda niczego w module dźwięku nie zmienia i nie dokłada.
- **Krok 46** — `EventRegistry` jest wzorem zamknięcia słownika i regułą „nie
  rzuca, nie zna wołającego, nie rodzi siebie samego”; `core.events` oddaje ten
  słownik jako daną.
- **Krok 27** — wynik kwerendy w oknie rysuje się `Table`, bo `QueryResult` to
  wiersze rekordów, a nie zdanie.

## Model i wysiłek

**Opus / xhigh.** Warunek `Fable` z przypisów planu (zmiana słownika wejścia albo
kontraktu ekranu i trzech tłumaczy naraz) **nie zachodzi**: okno jest drugim
trybem istniejącego, prymitywów nie przybywa, a przebudowa odczytów nie dotyka
ani jednego renderera.

## Zakres

### 1. Kwerendy jako mechanizm rdzenia

Nowy katalog `src/Application/Query/`:

- `QueryInterface` — nazwa w przestrzeni właściciela (`core.settings`,
  `browser.entries`), opis kluczem katalogu napisów, deklarowane argumenty
  (wzorem `CommandArgument`), **tani `generation(): int`** i wykonanie oddające
  `QueryResult`;
- `QueryArgument`, `QueryArgumentKind`, `QueryInput` — bliźniaczo do komend;
- `QueryResult` — wiersze `list<array<string, string|int|bool>>` **budowane
  leniwie**, opcjonalny ładunek typowany wydawany wyłącznie właścicielowi
  (`payloadFor(string $owner): ?object`) i opcjonalny powód niepowodzenia;
  kwerenda **nie rzuca** (zasada portu, reguła 8);
- `QueryRegistry` — `add(owner, queries)`, `find(name)`, `ask(name, input)`,
  `all()`, `matching()`, `commonPrefix()`, odsiew nazw spoza przestrzeni
  właściciela oraz **pamięć wyniku pod kluczem `nazwa+argumenty`, unieważniana
  znacznikiem pokolenia**;
- `QueryRejection` — powód odrzucenia jako dana, wzorem `CommandRejection`.

Zdolność `Application\Module\ProvidesQueries`.

Cztery reguły, wszystkie wykonane w rejestrze albo w kontrakcie:

1. **Kwerenda czyta i nie zmienia.** Co zmienia — jest komendą.
2. **Kwerenda nie zna wołającego** i wygląda tak samo przy zerze pytających.
   Wyjątkiem jest ładunek typowany, który jest wydawany po **nazwie
   właściciela**, a nie po tożsamości pytającego — rejestr pytającego nadal nie
   zna.
3. **Kwerenda nie woła kwerendy** — jak „zdarzenie nie rodzi zdarzenia”.
4. **Kwerenda odpowiada w klatce albo nie odpowiada wcale.** Praca dłuższa od
   klatki idzie komendą i pracą kawałkową; kwerenda oddaje **stan tej pracy**.

### 2. Brak odpowiedzi jest zwykłym stanem

`find()` oddaje `null`, gdy moduł jest wyłączony, odrzucony albo nieobecny.
Wołający **musi to obsłużyć zdaniem dla użytkownika**, a nie wyjątkiem.

### 3. Kwerendy rdzenia — źródła, które dotąd nie miały żadnego kanału

Rdzeń nie ma dziś ani jednej kwerendy, a jego dane czyta się wprost z usług. Spis
(przestrzeń `core.`):

| Kwerenda | Oddaje |
|---|---|
| `core.settings` | Klucze ustawień rdzenia wraz z wartościami i wartością domyślną |
| `core.module-settings` | Ustawienia modułu podanego argumentem (podprzestrzeń `modules.<id>`) |
| `core.modules` | Moduły: identyfikator, stan (przyjęty/wyłączony/odrzucony), skrót, powód odrzucenia |
| `core.commands` | Spis komend: nazwa, właściciel, klucz opisu, liczba argumentów |
| `core.queries` | Spis kwerend — samoopis rejestru, źródło listy w oknie |
| `core.events` | Słownik zdarzeń wraz z właścicielem (krok 46) |
| `core.jobs` | Prace tłowe: uchwyt, etap, przeczytane bajty, kod wyjścia |
| `core.viewport` | Wiersze, kolumny, tryb renderowania, tor klatki |
| `core.theme` | Nazwa motywu czynnego i lista nazw dostępnych |
| `core.language` | Język czynny i lista dostępnych |
| `core.version` | Wersja aplikacji, wersja PHP, obecność rozszerzeń |
| `core.status` | Ostatni komunikat wraz z tonem |
| `core.context` | Kontekst sesji (D92 nr 8 — dawne zastrzeżenie trzecie odwołane) |

### 4. Kwerendy trzech modułów tego kroku

**Przeglądarka (`browser`)**

| Kwerenda | Oddaje |
|---|---|
| `browser.entries` | Wpisy katalogu: `name`, `kind`, `bytes`, `modified`, `hidden`; argumenty: panel, ścieżka |
| `browser.marked` | Nazwy i ścieżki zaznaczonych wpisów |
| `browser.cwd` | Ścieżka panelu — **obu**, nie tylko czynnego (D92 nr 8) |
| `browser.selection` | Wpis pod kursorem wraz z rodzajem i rozmiarem |
| `browser.panes` | Układ: podział, panel czynny, widok (lista/drzewo), filtr |
| `browser.undo` | Stos cofnięć: czynność, cel, czy odwracalna |
| `browser.operation` | Stan pracy na plikach: etap, postęp, cel |

**Opis pliku (`file-info`)**

| Kwerenda | Oddaje |
|---|---|
| `file-info.usage` | Zajętość policzoną przez `du` wraz z etapem — **nigdy nie czeka** |
| `file-info.digest` | `sha256` wraz z etapem (`ChecksumStage`) |
| `file-info.description` | Sekcje i wiersze opisu wpisu (rodzaj, prawa, czasy, rozmiar) |
| `file-info.preview` | Stan podglądu: rodzaj, wymiary miniatury albo okno tekstu |

**Dźwięk (`audio`)**

| Kwerenda | Oddaje |
|---|---|
| `audio.now-playing` | Tytuł, numer pozycji, tryb, czy gra, czy silnik dostępny |
| `audio.playlist` | Pozycje: `index`, `title`, `path`, `playable` |
| `audio.effects` | Mapa hooków: zdarzenie → plik, przełącznik, głośność |

### 5. Odczyt przez rejestr także wewnątrz

Każdy moduł dostaje **typowaną fasadę czytającą przez rejestr** (`BrowserQueries`,
`FileInfoQueries`, `AudioQueries`; rdzeń — `CoreQueries`). Fasada woła
`QueryRegistry::ask()` i rozpakowuje ładunek właściciela **w jednym miejscu**,
więc ekrany i komponenty zmieniają jedną linię odczytu, a nie sposób rysowania.

Zakaz do zapisania w `SKILL.md` i pilnowany testem: **poza kwerendą i jej fasadą
nikt nie trzyma referencji do obiektu stanu cudzego ani własnego modułu**.

### 6. Okno kwerend: drugi tryb okna komend

- **spis wszystkich kwerend** z opisem z katalogu napisów i właścicielem;
- **wykonanie kwerendy z argumentami** — ten sam `CommandLineParser`;
- **wynik jako wiersze** — `Table` z kroku 27;
- **brak wykonawcy i brak wymaganego argumentu** — zdanie, nie wyjątek;
- **kwerenda modułu wyłączonego nie stoi w spisie** — spis jest widokiem na
  rejestr, a nie drugą listą.

### 7. Pomiar

Oś `--loop` „przed i po”, punkt odniesienia:
`docs/pomiary/2026-08-16-po-kroku-52-loop.json`. Miara jest inna niż zapowiadał
plan: rejestr **jest** wołany w klatce, i to wielokrotnie, więc mierzy się
**koszt routingu z pamięcią pokoleń**, a nie „zero pytań”.

Scenariusza klatki krok nie dokłada — okno kwerend jest drugim trybem okna
komend, więc scenariusz `command` rysuje tę samą klatkę. Rachunek kolumn okna
przelicza się **jak w kroku 46**: najdłuższa nazwa kwerendy wraz z najdłuższym
opisem z obu katalogów napisów ma się zmieścić, a pilnuje tego test czytający
`pl` i `en`.

## Poza zakresem

- **Kwerendy `ssh`, `docker` i `k8s` oraz czynność `k8s.deploy-image`** —
  [krok 54](../54-kwerendy-modulow-kontenerowych.md).
- **Kwerendy zmieniające cokolwiek** — to są komendy (reguła nr 1).
- **Kwerendy asynchroniczne, kolejki, obietnice** — kwerenda odpowiada w klatce
  albo oddaje stan pracy.
- **Rejestr zdolności ogólnego przeznaczenia** i **automatyczne wykrywanie, kto
  co umie** — nazwa kwerendy wystarcza.
- **Historia kwerend i zapamiętane wyniki** — okno pyta i pokazuje; pamięta okno
  komend, bo tam pada czynność.

## Kryteria ukończenia

- **Rdzeń i trzy moduły oddają komplet kwerend**, a okno kwerend pokazuje je
  wszystkie wraz z opisami — po polsku i po angielsku.
- **Żaden ekran ani komponent nie czyta stanu z pominięciem rejestru** — pilnuje
  tego test.
- **Właściciel dostaje ładunek typowany, obcy — wiersze**; próba odebrania
  cudzego ładunku oddaje `null`.
- Kwerenda spoza przestrzeni właściciela zostaje odrzucona wraz z powodem.
- Kwerenda wołająca kwerendę zostaje zignorowana, a nie zapętla pętli.
- **Kwerenda modułu wyłączonego nie stoi w oknie.**
- **`file-info.usage` i `file-info.digest` odpowiadają w klatce także wtedy, gdy
  praca tłowa trwa**; żaden test nie mierzy czasu zegarem.
- **Odczyt niezmienionego źródła nie przelicza wyniku** — sprawdzane licznikiem
  wywołań w atrapie kwerendy, nie zegarem.
- `bin/render-bench --loop` „przed i po” bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

### 2026-08-16 — mechanizm, okno, rdzeń i trzy moduły

**Zrobione i zielone (`make qa`: 2111 testów).**

- **Mechanizm**: `src/Application/Query/` — `QueryInterface`, `QueryResult`,
  `QueryRegistry`, `QueryLine`, `QueryLineParser`, `QueryRejection`, `Generation`,
  `Owner`; zdolność `Application\Module\ProvidesQueries`; rejestr w `LoopState`,
  składany jedną linią w `Bootstrapie`.
- **Trzynaście kwerend rdzenia** (`Presentation/Cli/Query/`) wraz ze spisem
  w `CoreQueries` — tym samym, którego używa zestaw testowy.
- **Okno kwerend** jako drugi tryb okna komend (`F12`, `Tab` przy pustym polu).
- **Piętnaście kwerend modułów**: `browser` (7 — wraz z `browser.tree`, która
  doszła przy przebudowie odczytów), `file-info` (4), `audio` (3) — plus fasady
  `BrowserQueries`, `FileInfoQueries`, `AudioQueries`.
- **Napisy** w obu katalogach dla wszystkich kwerend i argumentów; pilnują ich
  trzy testy (`QueryCatalogueTest`).
- **Testy**: `QueryRegistryTest`, `QueryResultTest`, `QueryLineParserTest`,
  `QueryWindowFlowTest`, `QueryCatalogueTest` (34 nowe przypadki).
- **Dokumentacja**: `docs/architecture.md` (rozdział „Kwerendy: jedyna droga
  odczytu"), `SKILL.md` (reguły 11w i 15g).

**Trzy rzeczy wyszły inaczej, niż je zaplanowano.**

1. **Pamięć wyniku dla kwerend ulotnych została wycofana — wymusił to test.**
   Pierwsza wersja pamiętała odpowiedź `VOLATILE` **na jedną klatkę** (rejestr znał
   numer taktu z `LoopState`). Wyglądało to na oszczędność, a było pułapką: odczyt
   **po zmianie w tej samej klatce** oddawał stan sprzed niej, więc `audio.music`
   po pauzie widziało, że nadal gra, i wznawiało utwór. Kwerendy ulotne nie są
   odtąd pamiętane w ogóle, a `LoopState` stracił licznik klatek. Cena jest mała
   dzięki leniwym wierszom: `ask()` zbiera kilka pól i wraca.
2. **`browser.operation` nie powstała, bo nie ma źródła.** Stan operacji na
   plikach żyje **wewnątrz okna**, które ją prowadzi (`RunsWork`, kroki 41–42),
   a nie w obiekcie przeżywającym klatkę. Wystawienie go wymagałoby dorobienia
   stanu wyłącznie po to, żeby było co przeczytać — czyli mechanizmu bez odbiorcy.
   Prace potomne widać za to w `core.jobs`.
3. **Podgląd tekstu został poza rejestrem — z reguły nr 1, nie z przeoczenia.**
   `FileInfoState::textWindow()` **zmienia stan przy odczycie** (rozlicza zamówione
   przewinięcie, przesuwa kotwicę w pliku, D60), a kwerenda czyta i nie zmienia.
   Rzecz, która przy czytaniu przestawia to, co czyta, jest negocjacją z geometrią
   panelu, a nie źródłem danych. Zapisane w docblocku `PreviewQuery`.

**Przebudowa odczytów w `BrowserScreen` — zrobiona, i to ona dołożyła siódmą
kwerendę.** Przez rejestr idą odtąd **wszystkie** odczyty rysowanej treści:
ścieżka pasa, etykiety obwódek obu paneli, listy wpisów, drzewa, filtr, wpisy
ukryte, zaznaczenie i jego podsumowanie, ognisko oraz pełna liczba wpisów
(dołożona do `browser.panes` jako `full`, bo podsumowanie zbioru liczy katalog
**przed** filtrem — D80 nr 4). W `BrowserPanes` zostały wyłącznie wywołania
**zmieniające** (`enter`, `toggleMark`, `invertMarks`, `toggleTree`, `moveFocus`,
`useSplit`, `publishFocused`, `clearFilter`, `clearMarks`, `selectionChanged`)
oraz `ScrollWindow` panelu — a ten jest stanem między klatkami (reguła 11a),
nie daną.

Dwie rzeczy wyszły przy tym na jaw i obie zostały naprawione u źródła:
**`browser.tree` musiała powstać**, bo widok drzewa jest osobnym źródłem danych
(co jest rozwinięte i gdzie stoi kursor — a nie co leży w katalogu), oraz
**reguła „drzewo zbioru nie widzi" (D80 nr 9) przeniosła się do jednego miejsca**
— `BrowserPanes::markedOf()`, z którego korzystają i pas ścieżki, i kwerenda.
Bez tego kwerenda odpowiadałaby inaczej niż to, co widać w klatce, i wyszło to
w teście `MarkedEntriesFlowTest`.

**Pomiar osi `--loop` — bez regresji.** Wzorzec odniesienia:
`2026-08-16-po-kroku-52-loop.json`, nowy: `2026-08-16-po-kroku-53-loop.json`
(60 przebiegów, 5 rozgrzewkowych).

| Przebieg | Obciążenie/rdzeń | Praca w tle | Komplet prac |
|---|---|---|---|
| po mechanizmie i rdzeniu | 0,09 (= wzorzec) | −1,1% | −0,0% |
| po przebudowie przeglądarki | 0,27 | **+70,2% ▲** | +9,2% |
| powtórzony | 0,20 | −1,1% | −1,6% |
| powtórzony, 60 przebiegów | 0,17 | −1,8% | +0,4% |

**Wiersz z alarmem jest szumem i widać to z metryczki, a nie z domysłu**: takt
pętli kosztuje **0,1 ms**, więc procent liczy się na trzeciej cyfrze znaczącej,
a obciążenie maszyny w tamtym przebiegu było trzykrotnie wyższe od wzorcowego
(0,27 wobec 0,09 na rdzeń) — dokładnie sytuacja, przed którą ostrzega sam
strażnik narzędzia. Dwa powtórzenia przy niższym obciążeniu dały wartości
w granicach ±2%, a przebieg czterokrotnie dłuższy — ±1,8%. Wzorzec zapisany
z tego ostatniego.

**Klatka obejrzana we wszystkich trzech torach** (2026-08-16, po przebudowie
przeglądarki). Aplikacja uruchamiana wejściami projektu, okna zrzucane `xwd`
i oglądane:

| Tor | Jak uruchomiony | Co sprawdzone |
|---|---|---|
| Sixel | `bin/run.sh 110x34` (XTerm `-ti vt340`) | spis 17 pozycji z opisami i suwakiem, `core.viewport` jako pary `pole: wartość` (rows 34 / columns 110 / renderer Sixel — zgodne z geometrią okna), `browser.entries` jako tabela z nagłówkiem, `core.jobs` przy zerze prac → „brak danych do pokazania" |
| Tekstowy | XTerm bez `-ti vt340`, ten sam `bin/light-manager` | `core.modules` — wszystkie sześć modułów przyjętych wraz z literami skrótów; tabela i obwódki rysują się znakami ramek |
| Okienkowy | `bin/light-manager --window` (OpenGL) | `audio.playlist` jako rekord pól, ścieżka odmowy przy nazwie niepełnej („nieznane źródło danych: `audio.pl`") — okno zostaje otwarte, zdanie idzie do paska stanu |

**Kolumny mieszczą się w 110 znakach we wszystkich trzech torach**; przy dłuższych
nazwach plików tabela ucina wartość wielokropkiem, tak jak lista wpisów.
**Regresji wizualnej nie ma**: `--png-compare` (Sixel) i `--window --png-compare`
— dziewiętnaście scenariuszy po **0 różnych pikseli**; tor tekstowy pilnują złote
klatki (`GoldenFrameTest`, 20 przypadków). `make test-functional`: 262 testy
zielone.

**Jedna niespójność wyszła dopiero z klatki i została poprawiona**: w trybie
kwerend stopka mówiła „Esc zamknij **okno komend**", choć tytuł okna brzmiał już
„ŹRÓDŁA DANYCH". Napis `command.key.close` jest odtąd neutralny („zamknij okno" /
„close the window") — jeden klawisz obsługuje oba tryby, więc jego opis nie ma
prawa nazywać jednego z nich.

**Czas w wierszach kwerendy — rozstrzygnięty przy odbiorze.** `browser.entries`
oddawało `modified` jako **liczbę sekund epoki** (`1786266864`), bo taka jest dana
pierwotna — i taka, której człowiek w oknie kwerend nie umie przeczytać.
Rozstrzygnięciem użytkownika pole jest odtąd **datą i godziną czasu lokalnego**:
`2026-08-09 09:14:24`. Bez przesunięcia strefy i **bez litery `T`** — dokładnie
w tym zapisie, którym mówi kolumna „Zmieniony" na liście wpisów obok, bo jedna
aplikacja ma mówić o czasie jednym głosem. Napis pozostaje daną pierwotną
i porównuje się leksykograficznie tak samo, jak liczba numerycznie; nieznany czas
oddaje pusty napis, a nie `-1`. Pilnuje tego `BrowserQueriesTest`, a klatka pod
XTermem potwierdziła, że dziewiętnaście znaków mieści się w kolumnie tabeli.

**Rozmiar poszedł tą samą drogą, i to z tego samego powodu.** Pole `bytes`
(`80847`) zamieniło się w `size` (`79,0 kB`): przy gigabajtach liczba bajtów jest
nie do przeczytania, a wiersz kwerendy ogląda także człowiek. Zapis liczy
**`EntrySize`** — ta sama klasa, którą podają rozmiar lista wpisów i drzewo
(krok 31), więc trzeci rachunek tej samej rzeczy nie powstał. Katalog oddaje
**pusty napis**, a nie `0 B`, bo zajętość katalogu wraz z zawartością zna
wyłącznie `du` (krok 26) — lista wpisów robi w tym miejscu dokładnie to samo.

**Cena obu poprawek jest jedna i zapisana wprost**: napisu z jednostką nie da się
porównać liczbowo, więc moduł szukający „plików większych niż gigabajt" nie
zrobi tego wierszem tej kwerendy. Przy czasie porządek leksykograficzny ocalał,
przy rozmiarze — nie. Pierwszeństwo ma czytelność; pole liczbowe dojdzie wtedy,
gdy zjawi się odbiorca, który go potrzebuje, a nie na zapas.

Pozostałe pola tego rodzaju — `browser.selection` (`bytes`, `modified`)
i `core.context` (`selectionBytes`, `selectionModifiedAt`) — **zostają danymi
surowymi**: nie były przedmiotem rozstrzygnięcia, a zmiana ich kontraktu przy
okazji byłaby zmianą na zapas.

### Domknięcie: rdzeń przestał być zwolniony z własnej reguły

Przy pytaniu o archiwizację wyszło, że jedno kryterium nie jest spełnione:
„rejestr jedyną drogą odczytu **także wewnątrz rdzenia**" obowiązywało moduły,
a `SettingsScreen` czytał ustawienia wprost ze stanu pętli — nikt nie sprawdzał
tego maszynowo, bo strażnika nie było. Domknięcie (D93 nr 4):

- **`CoreReader`** — czwarta fasada, dla rdzenia; `core.settings` i `core.context`
  oddają odtąd **ładunek typowany** (`Settings`, `ModuleContext`), więc ekran
  ustawień dostaje obiekt, a nie wiersze napisów;
- **`QueryIsTheOnlyReadPathTest`** — strażnik, który wymienia zakazane wyrażenia
  z nazwy i mówi, czym każde zastąpić. Uruchomiony pierwszy raz znalazł
  **piętnaście odczytów z pominięciem rejestru**: w `OpenCommand`, `JumpCommand`,
  `VolumeCommand`, `EntryOperations`, `EntryTransfer`, `EntryTrash`,
  `HiddenEntries`, `BrowserModule`, `BrowserScreen` i `FileInfoScreen`. Wszystkie
  przepięte;
- **trzy rachunki wróciły do jednego miejsca**: „drzewo zbioru nie widzi" do
  `BrowserPanes::markedOf()`, „katalog wskazywany" (lista vs. drzewo) do
  `BrowserQueries::pointedDirectory()`, a reguła pustego zbioru **została**
  w `BrowserPanes::focusedOperands()`, którą fasada wyłącznie podaje;
- **dwie zależności wypadły** jako nieużywane — `EntryOperations` i `EntryTrash`
  nie dotykają już paneli, `BrowserScreen` i `EntryTrash` nie dotykają stanu
  pętli. Wykrył to PHPStan;
- **kolejność w `Bootstrapie`** jest odtąd wymuszona trzy razy: pusty rejestr
  komend → kwerendy rdzenia → moduły → okna komend. Powód każdego kroku stoi
  w komentarzu przy kodzie.

Sprawdzone po zmianie w prawdziwej klatce: ekran ustawień czyta wartości przez
rejestr, a zmiana języka strzałką przestawia **cały interfejs w następnej
klatce** — czyli odczyt po zapisie w tej samej klatce działa (to jest ta sama
pułapka, która wywróciła pamięć kwerend ulotnych, tylko od drugiej strony).

Pomiar powtórzony po domknięciu: **+2,4% / +3,5%** wobec wzorca sprzed niego,
przy porównywalnym obciążeniu maszyny (0,16 wobec 0,17 na rdzeń) — poniżej progu
i w granicach rozrzutu taktu kosztującego 0,1 ms. Wzorzec
`2026-08-16-po-kroku-53-loop.json` zapisany z ostatniego przebiegu, czyli
z kodem, który poszedł do archiwum.
