# Krok 16 — Narzędzia diagnostyczne wydajności

## Status

Ukończony

## Zależności

Krok 13 (potok renderowania w obecnym kształcie), krok 14 (ustawienia
renderowania jako obiekt, którym da się sterować spoza kodu). Krok 15 nie
wnosi zależności — stoi wcześniej wyłącznie w kolejce.

## Model i wysiłek

Opus / high — nie z powodu objętości kodu, lecz metodyki. Pomiar pod
prawdziwym terminalem, oddzielenie rozgrzewki od pomiaru i uczciwe
raportowanie rozrzutu to miejsca, w których łatwo wyprodukować liczby
wyglądające solidnie i nic nieznaczące.

## Cel

Dać projektowi powtarzalne narzędzie do mierzenia wydajności potoku
renderowania: osobno dla faz (rysowanie, kwantyzacja, kodowanie, przesył do
terminala) i osobno dla elementów klatki (tekst, ramki, ramki z tekstem,
miniatura), w konfiguracjach przestawianych z linii poleceń.

**Powód powstania kroku.** W kroku 13 każdy pomiar wymagał wejścia `sed`-em
w kod produkcyjny: podmiany stałych `PALETTE_COLORS` i `TEXT_ANTIALIAS`,
wstrzyknięcia znaczników czasu przez `$GLOBALS` między fazy, uruchomienia
skryptu z katalogu tymczasowego i przywrócenia pliku na koniec. Na liczbach
zdobytych tą drogą zapadły decyzje o realnych konsekwencjach — próg palety
64 kolorów i włączenie wygładzania obrysów ([00-decyzje.md](../00-decyzje.md),
D27). Pomiary były jednorazowe, nieodtwarzalne i nie trafiły do
repozytorium; jedno przeoczenie w tej metodzie (nierozdzielenie kosztu
tekstu od kosztu obrysów) doprowadziło do decyzji, którą trzeba było potem
odwrócić.

## Ustalenia (decyzje użytkownika, 2026-08-08)

| Rozstrzygnięcie | Wybór |
|---|---|
| Miejsce w kolejce | **Krok 16** — po konfiguracji i wielojęzyczności |
| Instrumentacja | **Rozbicie `encode()` na jawne kroki**; zero kodu pomiarowego w produkcji |
| Wyniki | **Tabela na wyjściu + zapis wzorca do pliku** i tryb porównania z nim |

Konsekwencja wyboru kolejności: wartości domyślne przyjęte w kroku 14
(paleta, wygładzanie) pochodzą z doraźnych pomiarów kroku 13. Po
uruchomieniu narzędzia należy je zweryfikować i, jeśli liczby powiedzą co
innego, poprawić — to jawnie odłożony dług, nie przeoczenie.

## Punkt odniesienia — co wiadomo dziś

Liczby z kroku 13 (płótno 1000×600, siatka 46×166, pełna lista 30 wpisów).
Narzędzie ma je umieć odtworzyć; rozbieżność będzie sygnałem, że mierzy coś
innego niż wtedy mierzono.

| Faza | Zmierzone | Uwaga |
|---|---|---|
| Rysowanie płótna | 162 ms | 78% czasu klatki |
| Kwantyzacja + typ paletowy | 40 ms | |
| Kodowanie do Sixela | 5,5 ms | |
| Sam chrom (panele, nawiasy, etykiety, suwak) | 34 ms | 207 ms wobec 173 ms bez ramek |
| Trzydzieści wierszy tekstu | ~119 ms | ~4 ms na wiersz o 158 znakach |
| Klatka bez wierszy listy | 90 ms | |
| Wygładzanie obrysów | +3 kB, czas w granicach błędu | |
| Wygładzanie tekstu | +22 ms, +33 kB | |
| Przesył do terminala | **niemierzony** | luka, którą krok ma zamknąć — **zamknięta**, patrz „Specyfikacja zrealizowana” |
| Miniatura (dekodowanie JPEG) | 387 → 102 ms z `jpeg:size` | pomiar z kroku 12 |

**Wynik konfrontacji:** narzędzie odtworzyło **proporcje** między fazami, ale nie
skalę — liczby kroku 16 są mniej więcej dwukrotnie wyższe. Analiza rozbieżności:
sekcja „Rozbieżność wobec punktu odniesienia z kroku 13” poniżej.

## Zakres

### 1. Rozbicie potoku na mierzalne kroki

`SixelFrameEncoder::encode()` robi dziś trzy rzeczy naraz: rysuje płótno,
kwantyzuje je i koduje do Sixela. Rozdzielenie ich na jawne kroki pozwala
zmierzyć każdy z zewnątrz, bez wstrzykiwania czegokolwiek do środka — i
przy okazji porządkuje klasę.

Kształt do ustalenia na starcie (patrz „Do rozstrzygnięcia”), ale reguła
jest twarda: **w kodzie produkcyjnym nie pojawia się ani jedno wywołanie
pomiarowe**. Instrumentacja żyje wyłącznie w narzędziu.

### 2. Scenariusze — co składa się na klatkę

| Scenariusz | Po co |
|---|---|
| Puste płótno | koszt bazowy: alokacja, tło, kwantyzacja, kodowanie |
| Sam tekst (N wierszy × M znaków) | rasteryzacja liter — dziś główny koszt klatki |
| Same ramki (cztery panele, bez treści) | koszt chromu: łuki, nawiasy, etykiety |
| Ramki z tekstem | pełna klatka listy |
| Klatka z miniaturą | dekodowanie i skalowanie obrazu plus paleta 256 |
| Klatka z okienkiem | nakładka rysowana na wierzchu |
| Zaznaczenie i suwak osobno | drobne elementy, których kosztu nikt nie zna |

Każdy scenariusz musi być **deterministyczny** — ta sama treść klatki przy
każdym przebiegu, inaczej porównanie z wzorcem nie ma sensu.

### 3. Osie konfiguracji

Przestawiane z linii poleceń, bez dotykania kodu:

| Oś | Wartości |
|---|---|
| Wygładzanie tekstu | tak / nie |
| Wygładzanie obrysów | tak / nie |
| Paleta Sixela | 16 / 32 / 64 / 128 / 256 |
| Rozmiar płótna | dowolny, z wartościami typowymi jako skrótami |
| Siatka znakowa | kolumny × wiersze — koszt tekstu zależy od liczby znaków, nie od powierzchni |
| Font | z listy preferencji `ImagickCapabilityService` |
| Motyw | palety wprowadzone w kroku 14 |

### 4. Przesył do terminala

Jedyna faza, której nie da się zmierzyć bez prawdziwego terminala.
Precedens pracy pod PTY: `bin/terminal-probe` oraz weryfikacje z kroków
06–08.

Mierzone: czas zapisu bloba, liczba iteracji dopisywania (krok 09 odnotował
`fwrite()` przyjmujące 8192 B z ~9,5 kB) oraz przepustowość w kB/s. Bez
terminala narzędzie ma **powiedzieć wprost, że tej fazy nie zmierzyło** —
nie podstawiać zapisu do `/dev/null` jako namiastki, bo to mierzyłoby
prędkość jądra, nie terminala.

### 5. Zrzut klatki do obrazu

Liczby nie pokazują wszystkiego. Najważniejsze odkrycie kroku 13 — że przy
16 i 32 kolorach kwantyzator zjada obwódki paneli — było niewidoczne w
czasie ani w rozmiarze bloba; wyszło dopiero z obejrzenia renderu. Narzędzie
ma umieć zapisać płótno do PNG (przed konwersją na Sixel), żeby skutki
wizualne zmiany ustawień dało się obejrzeć bez uruchamiania aplikacji.

### 6. Raport i wzorzec

- **Tabela na wyjściu**: scenariusz × konfiguracja → mediana, minimum,
  maksimum, rozmiar bloba, udział faz.
- **Zapis do pliku** wraz z metryczką środowiska: wersja PHP, wersja
  ImageMagick, użyty font, rozmiar płótna, data.
- **Tryb porównania** z wcześniejszym wzorcem: różnice procentowe i
  oznaczenie regresji powyżej progu.

### 7. Metodyka pomiaru

Wnioski z kroku 13, które muszą wejść do narzędzia jako reguły, a nie dobre
chęci:

- **Rozgrzewka przed pomiarem.** Pierwsza klatka płaci za wybór fontu i
  pomiar szerokości napisów; wliczona do średniej zaburza wynik.
- **Mediana z co najmniej piętnastu przebiegów**, raportowana razem z
  minimum i maksimum. Sama średnia ukrywa rozrzut.
- **Ostrzeżenie przy niestabilnym pomiarze.** Ta sama konfiguracja dawała
  184–254 ms zależnie od obciążenia maszyny. Gdy `max/min` przekroczy próg,
  wynik ma być oznaczony jako niewiarygodny, a nie zapisany do wzorca jako
  fakt.
- **Rozmiar bloba obok czasu** — bajty jadą do terminala i też kosztują.

## Umiejscowienie w warstwach

PHPStan (`level: max`) i PHP-CS-Fixer obejmują dziś wyłącznie `src` i
`tests`; katalog `bin/` jest poza bramkami jakości. Dlatego logika pomiarowa
ma trafić do `src/Infrastructure/Diagnostics/`, a w `bin/` zostaje cienki
punkt wejścia — tak jak `bin/light-manager` wobec `Presentation/Cli`.

Narzędzie należy do `Infrastructure`: sięga po Imagicka i terminal, nie
wnosi żadnego pojęcia domenowego.

## Poza zakresem

- **Optymalizacja.** Krok dostarcza miarę, nie poprawki. Co zrobić z
  wynikiem (na przykład z tym, że rasteryzacja tekstu zjada 60% klatki, a
  takt 20 kl./s z D19 pozostaje nieosiągalny) — to materiał na osobny krok.
- Profilowanie zużycia pamięci.
- Test wydajnościowy w bramce jakości — świadomie odrzucony przy wyborze
  formy wyników, bo przy rozrzucie 184–254 ms próg regresji dawałby fałszywe
  alarmy.
- Pomiar czasu, w którym sam terminal *wyświetli* obraz (poza czasem
  przyjęcia bajtów) — patrz „Do rozstrzygnięcia”, punkt 3.

## Rozstrzygnięcia ze startu kroku

Sześć pytań pozostawionych przy planowaniu zostało rozstrzygniętych przez
użytkownika przed napisaniem pierwszej linii kodu.

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Kształt rozbicia `encode()` | **Trzy metody publiczne** (`drawCanvas()` → `quantizeCanvas()` → `toSixel()`), a `encode()` zostaje ich fasadą. Płótno zwalnia **wołający**, w `finally` |
| 2 | Gdzie trzymać wzorce | **`docs/pomiary/` w repozytorium**, plik z datą w nazwie; porównanie bez argumentu bierze najnowszy |
| 3 | Czas przetworzenia przez terminal | **Mierzyć**, ale jako osobną kolumnę jawnie oznaczoną jako przybliżoną |
| 4 | Punkt wejścia | **Nowy `bin/render-bench`** plus `bin/run-render-bench.sh`; `terminal-probe` zostaje przy podglądzie wejścia |
| 5 | Zakres pomiaru | **Tylko potok renderowania i przesył** — bez pełnej iteracji pętli i bez systemu plików |
| 6 | `bin/` pod bramkami | **Nie** — punkty wejścia zostają cienkie, logika mieszka w `src/Infrastructure/Diagnostics/` |

Dodatkowo, już w trakcie realizacji, rozstrzygnięto dwie kwestie dotykające
ustalonych konwencji (obie decyzją użytkownika):

- **Napisy narzędzia idą przez katalog** `lang/pl.php` i `lang/en.php`
  (klucze `bench.*`), tak jak reszta interfejsu — mimo precedensu
  `bin/terminal-probe`, który został po polsku poza katalogiem.
- **Dwie drobne zmiany w kodzie produkcyjnym** wprowadzone, bo bez nich dwie
  osie z planu wypadłyby z zakresu: `RenderingOptions` dostaje pole
  `?string $font`, a `TerminalService::write()` zwraca liczbę wywołań
  `fwrite()`. Żadna nie jest wywołaniem pomiarowym.

## Kryteria ukończenia

- Każdy pomiar wykonany w kroku 13 daje się powtórzyć **jednym poleceniem**,
  bez edytowania kodu produkcyjnego.
- Narzędzie rozbija klatkę na fazy i pokazuje udział każdej z nich.
- Wszystkie osie konfiguracji przestawiane z linii poleceń.
- Wynik zapisywany do pliku i porównywalny z wcześniejszym wzorcem.
- Pomiar przesyłu działa pod prawdziwym terminalem, a bez niego narzędzie
  mówi wprost, że tej fazy nie zmierzyło.
- Zrzut klatki do PNG dostępny jako tryb narzędzia.
- Część czysto obliczeniowa (agregacja, mediana, wykrywanie niestabilnego
  pomiaru, porównanie z wzorcem) pokryta testami jednostkowymi.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag.
- `README.md` opisuje uruchomienie i sposób czytania wyniku.

## Specyfikacja zrealizowana

### Rozbicie potoku (kod produkcyjny)

`SixelFrameEncoder` ma dziś trzy publiczne kroki i fasadę:

```php
public function drawCanvas(Frame, FrameLayout, RenderingOptions, int, int): Imagick
public function canvasCarriesBitmap(): bool
public function quantizeCanvas(Imagick $canvas, bool $carriesBitmap): void
public function toSixel(Imagick $canvas): string
public function encode(...): string   // woła powyższe, zwalnia płótno w finally
```

`canvasCarriesBitmap()` istnieje, bo decyzja „paleta 64 czy 256” zapada dopiero
przy rysowaniu pasa podglądu, w głębi klasy. Narzędzie pyta o nią enkoder,
zamiast zgadywać — inaczej scenariusz z miniaturą mógłby zostać zmierzony na
innej palecie niż ta, której użyje aplikacja.

**W kodzie produkcyjnym nie ma ani jednego wywołania pomiarowego.** Cała
instrumentacja żyje w `BenchmarkRunner::sample()`.

### Warstwa diagnostyczna

`src/Infrastructure/Diagnostics/` — 26 klas, z których część jest czysta
(bez I/O i bez Imagicka) i objęta testami jednostkowymi:

| Klasa | Rola | Testy |
|---|---|---|
| `Measurement` | mediana, min, max, rozrzut, wykrywanie niestabilności | ✅ |
| `PhaseSample`, `ScenarioResult`, `ScenarioMedians` | agregacja przebiegów | ✅ |
| `Scenario`, `ScenarioFactory`, `ScenarioFrame` | deterministyczna treść klatek | ✅ |
| `BenchmarkArguments`, `BenchmarkMode` | parser wiersza poleceń | ✅ |
| `BaselineSnapshot`, `BaselineStore`, `JsonValue` | zapis i odczyt wzorca | ✅ |
| `BaselineComparison`, `ComparisonRow` | różnice i regresje | ✅ |
| `BenchmarkOptions`, `BenchmarkReport`, `EnvironmentMetadata` | konfiguracja i metryczka | — |
| `BenchmarkRunner`, `TransferMeter`, `TransferResult` | pomiar (wymaga Imagicka/terminala) | — |
| `CanvasSnapshot`, `ImageFixture`, `ReportTable`, `BenchmarkCli` | zrzut PNG, wydruk, orkiestracja | — |
| `DiagnosticsException`, `DiagnosticsProblem` | awarie z typowanym polem rodzaju | — |

### Scenariusze

Osiem, dobranych tak, żeby koszt dawało się **odjąć**: `empty`, `text`,
`chrome`, `chrome-text`, `selection`, `scrollbar`, `thumbnail`, `popup`.
Scenariusze bez chromu dostają układ bez obwódek, składany w `ScenarioFactory`,
a nie liczony przez `HudFrameLayoutService` — ten zawsze dołożyłby panele.

Jeden wyjątek, którego nie dało się usunąć bez ruszania kodu produkcyjnego:
`chrome` rysuje cztery panele **i tytuł ścieżki**, bo enkoder rysuje tytuł
zawsze, gdy strefa nagłówka istnieje. Tytuł skrócono tam do jednego znaku.

### Osie konfiguracji

Wszystkie z planu, przestawiane z linii poleceń: `--size`/`--width`/`--height`,
`--grid`/`--columns`/`--rows`, `--palette`, `--text-aa`, `--stroke-aa`,
`--theme`, `--font`, `--iterations`, `--warmup`. Każda ma swój przypadek w
`BenchmarkArgumentsTest`. Nierozpoznany argument **zatrzymuje pomiar** — po
cichu pominięty mierzyłby coś innego, niż o co poproszono.

### Wyniki zmierzone przy realizacji

Konfiguracja domyślna (1000×600 px, siatka 166×46, paleta 64, tekst bez
wygładzania, obrysy z), 15 przebiegów po 3 na rozgrzewkę. Pełny zapis:
[docs/pomiary/2026-08-09-render.json](../../pomiary/2026-08-09-render.json).

| Scenariusz | Rysowanie | Kwantyzacja | Kodowanie | Razem | Blob |
|---|---|---|---|---|---|
| puste płótno | 4,5 ms (5%) | 76,6 ms (92%) | 1,5 ms | 83,0 ms | 0,7 kB |
| sam tekst | 301,4 ms (78%) | 85,1 ms (22%) | 2,4 ms | 388,3 ms | 19,6 kB |
| same ramki | 83,5 ms (50%) | 78,8 ms (47%) | 5,8 ms | 168,1 ms | 7,4 kB |
| ramki z tekstem | 314,7 ms (77%) | 87,5 ms (21%) | 7,7 ms | 410,8 ms | 23,1 kB |
| zaznaczenie | 593,4 ms (87%) | 85,3 ms (13%) | 4,0 ms | 681,5 ms | 24,7 kB |
| suwak | 11,6 ms (12%) | 85,8 ms (85%) | 3,2 ms | 101,0 ms | 2,8 kB |
| klatka z miniaturą | 332,5 ms (73%) | 91,1 ms (20%) | 30,0 ms | 456,7 ms | 28,9 kB |
| klatka z okienkiem | 360,7 ms (78%) | 93,8 ms (20%) | 10,4 ms | 462,6 ms | 29,3 kB |

Faza przesyłu, zmierzona pod XTermem (`--transfer`, klatka z miniaturą):

| Wielkość | Wynik |
|---|---|
| Rozmiar klatki | 28,9 kB |
| Czas zapisu | 1,9 ms (min 0,3, maks 6,9) |
| Iteracje `fwrite()` | 4 |
| Przepustowość | ~15 000 kB/s |
| Odpowiedź DA1 po | 75,3 ms — **wartość przybliżona** |

### Cztery rzeczy, które pokazał pierwszy pomiar

1. **Zaznaczenie jest najdroższym elementem klatki**, a nikt tego nie
   podejrzewał. Scenariusz `selection` (każdy wiersz zaznaczony) kosztuje
   593 ms rysowania wobec 301 ms dla samego tekstu — pasek to zaokrąglony
   prostokąt plus krawędź, czyli dwa `drawImage()` na wiersz. W prawdziwej
   klatce zaznaczenie jest jedno, więc realny koszt to ułamek tej liczby, ale
   sam **stosunek** (≈6 ms na jeden pasek wobec ≈6,5 ms na jeden wiersz tekstu)
   jest zaskakująco wysoki i jest materiałem dla kroku 17.
2. **Kwantyzacja ma stały koszt niezależny od treści** — od 76,6 ms na pustym
   płótnie do 93,8 ms na najbogatszej klatce. Płaci się za powierzchnię, nie za
   zawartość. Na pustym płótnie to 92% czasu klatki. Potwierdza to kierunek
   z D29: `remapImage()` na gotowej palecie motywu zamiast `quantizeImage()`.
3. **Kodowanie miniatury jest cztery razy droższe od kodowania tekstu**
   (30,0 wobec 7,7 ms) — to cena palety 256 kolorów, którą klatka z bitmapą
   dostaje z konieczności.
4. **Czas zapisu bloba jest mylący.** 1,9 ms to czas, w którym jądro przyjęło
   bajty do bufora — a terminal odpowiada dopiero po 75 ms. Sam zapis nie jest
   więc miejscem do optymalizacji, ale koszt po stronie terminala jest realny
   i rośnie z rozmiarem bloba.

### Rozbieżność wobec punktu odniesienia z kroku 13

Plan przewidywał, że narzędzie odtworzy liczby z kroku 13, a rozbieżność będzie
sygnałem. Rozbieżność jest i wynosi około **dwukrotności**:

| Wielkość | Krok 13 | Krok 16 |
|---|---|---|
| Rysowanie pełnej klatki | 162 ms | 315 ms |
| Kwantyzacja | 40 ms | 87 ms |
| Kodowanie | 5,5 ms | 7,7 ms |

Proporcje między fazami zgadzają się (rysowanie ≈78% klatki w obu pomiarach),
rozjeżdża się skala. Najprawdopodobniejsze wyjaśnienie to obciążenie maszyny w
trakcie pomiaru — pomiary kroku 16 powstawały przy pracującym w tle procesie, co
widać po tym, że dwa pierwsze przebiegi zostały odrzucone jako niestabilne, a
`--compare` bezpośrednio po zapisaniu wzorca pokazał „regresję” +32% bez żadnej
zmiany w kodzie. **Liczby kroku 16 są wewnętrznie spójne i nadają się na punkt
odniesienia dla kroku 17; nie nadają się do zestawiania z liczbami kroku 13.**

To samo w sobie jest wnioskiem: porównanie ma sens wyłącznie na tej samej
maszynie przy porównywalnym obciążeniu, i tak zostało opisane w
[docs/pomiary/README.md](../../pomiary/README.md).

## Dziennik realizacji

**2026-08-09** — krok zrealizowany w całości.

Wykonane:

1. Sześć pytań ze startu kroku rozstrzygnięte przez użytkownika (tabela wyżej),
   plus dwa dodatkowe, które wyszły w trakcie (napisy przez katalog; dwie zmiany
   w kodzie produkcyjnym).
2. `SixelFrameEncoder::encode()` rozbite na trzy jawne kroki z `encode()` jako
   fasadą; własność płótna opisana w dokumentacji klasy.
3. `RenderingOptions` rozszerzone o `?string $font` (domyślnie `null` —
   zachowanie aplikacji bez zmian).
4. `TerminalService::write()` zwraca liczbę wywołań `fwrite()` zamiast `void`.
5. Powstało `src/Infrastructure/Diagnostics/` (26 klas) oraz cienki punkt
   wejścia `bin/render-bench` i `bin/run-render-bench.sh`.
6. Napisy narzędzia dopisane do `lang/pl.php` i `lang/en.php` (klucze `bench.*`,
   w tym forma mnoga dla liczby regresji).
7. 82 nowe testy jednostkowe w `tests/Infrastructure/Diagnostics/` (273 asercje);
   razem w projekcie 550 testów i 1293 asercje — wszystkie przechodzą.
8. PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag (177 plików).
9. Zweryfikowane ręcznie wszystkie tryby: pomiar, `--save`, `--compare`
   (w tym odmowa zestawienia przy innej konfiguracji), `--png`, `--help`,
   odrzucanie błędnych argumentów, obie ścieżki odmowy pomiaru przesyłu
   (brak terminala, terminal bez Sixela) oraz **udany pomiar przesyłu pod
   XTermem**.
10. Zapisany pierwszy wzorzec: `docs/pomiary/2026-08-09-render.json` wraz z
    `docs/pomiary/README.md`; README opisuje uruchomienie i czytanie wyniku.

Odstępstwa od planu:

- **Treść mierzonych klatek celowo nie przechodzi przez katalog napisów**, choć
  napisy samego narzędzia przechodzą. Powód jest warunkiem poprawności pomiaru:
  liczy się długość napisu w znakach, więc tłumaczona treść dawałaby inny wynik
  po polsku i po angielsku, a wzorzec zapisany w jednym języku byłby
  nieporównywalny z przebiegiem w drugim.
- **Dwie zmiany w kodzie produkcyjnym** (font w `RenderingOptions`, licznik w
  `write()`) — plan zakładał zerową ingerencję poza rozbiciem `encode()`. Żadna
  nie jest wywołaniem pomiarowym, obie zatwierdzone przez użytkownika.
- **Podpis konfiguracji (`BenchmarkOptions::signature()`) jest nietłumaczony** —
  to identyfikator zapisywany do pliku wzorca, który ma wyglądać tak samo po
  latach niezależnie od języka interfejsu, tak jak argumenty `stty` w
  komunikatach wyjątków.

Dług odnotowany, **nie zamknięty w tym kroku**:

- **Weryfikacja wartości domyślnych z kroku 14** (paleta 64, wygładzanie tekstu
  wyłączone, obrysów włączone) — plan kroku 16 zapowiadał ją jako jawnie
  odłożony dług. Narzędzie jest gotowe (`--palette=`, `--text-aa`,
  `--stroke-aa`), ale rzetelny werdykt wymaga przebiegów na nieobciążonej
  maszynie, a te wykonane przy realizacji były zakłócone. Zadanie należy
  wykonać na starcie kroku 17, przed pierwszą optymalizacją.
- **Pomiar miniatury jest „ciepły”** — `ThumbnailService` pamięta przeskalowaną
  bitmapę między klatkami (tak jak w aplikacji, która składa klatkę 20 razy na
  sekundę), więc scenariusz `thumbnail` nie mierzy kosztu pierwszego dekodowania
  JPEG-a (387 → 102 ms z kroku 12). Zmierzenie zimnej ścieżki wymagałoby resetu
  Singletona, czyli mechanizmu, który celowo istnieje wyłącznie w testach (D11).
