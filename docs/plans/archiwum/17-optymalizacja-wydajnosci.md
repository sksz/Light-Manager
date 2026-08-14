# Krok 17 — Optymalizacja wydajności renderowania

## Status

Ukończony

## Zależności

Krok 13 (potok renderowania w obecnym kształcie), krok 16 (narzędzia
pomiarowe — bez nich nie da się rozliczyć „przed i po”, a każda dźwignia w
tym kroku wymaga takiego rozliczenia).

**Stan zależności: spełniona.** Krok 16 dostarczył `bin/render-bench` wraz z
wzorcem [docs/pomiary/2026-08-09-render.json](../../pomiary/2026-08-09-render.json).
Rozliczenie dźwigni robi się poleceniem `./bin/render-bench --compare`; przed
rozpoczęciem prac przeczytaj [docs/pomiary/README.md](../../pomiary/README.md),
bo porównanie ma sens wyłącznie przy porównywalnym obciążeniu maszyny.

**Dwie rzeczy do zrobienia na starcie tego kroku**, przeniesione z kroku 16
([00-decyzje.md](../00-decyzje.md), D33):

1. **Zweryfikować wartości domyślne z kroku 14** (paleta 64, wygładzanie tekstu
   wyłączone, obrysów włączone). Pochodzą z doraźnych pomiarów kroku 13 i miały
   zostać sprawdzone powtarzalnym narzędziem — to dług zaciągnięty świadomie
   w D28 i wciąż otwarty. Narzędzie ma gotowe osie: `--palette=`, `--text-aa`,
   `--stroke-aa`.
2. **Zapisać własny wzorzec odniesienia na nieobciążonej maszynie.** Wzorzec z
   kroku 16 powstał przy pracującym w tle procesie i jest około dwukrotnie
   wyższy od liczb kroku 13 (proporcje między fazami się zgadzają, skala nie).
   Rozliczenie optymalizacji wymaga punktu wyjścia zmierzonego w tych samych
   warunkach co wynik.

Pierwszy pomiar narzędziem przyniósł też trzy obserwacje, które wchodzą wprost
do „Dźwigni” tego kroku — patrz sekcja „Cztery rzeczy, które pokazał pierwszy
pomiar” w [16-narzedzia-diagnostyczne-wydajnosci.md](16-narzedzia-diagnostyczne-wydajnosci.md):
zaznaczenie jako najdroższy pojedynczy element klatki, kwantyzacja o koszcie
niezależnym od treści (potwierdza dźwignię 2) oraz czas zapisu bloba, który jest
mylący wobec 75 ms po stronie terminala.

## Model i wysiłek

Opus / high — zmiany sięgają rdzenia renderowania i obiektu wartości w
`Domain`, a ich skutki uboczne są wizualne, więc łatwo je przeoczyć w
testach. Do tego każdą dźwignię trzeba zmierzyć osobno, inaczej nie wiadomo,
która działa.

## Cel

Potanić klatkę o rząd wielkości, nie zmieniając tego, co widać na ekranie.

Stan wyjściowy (płótno 1000×600, siatka 46×166, pełna lista 30 wpisów,
ustawienia domyślne z kroku 13): **184 ms na klatkę**. Przy takcie 20 kl./s
z D19 budżet wynosi 50 ms, więc pętla nigdy nie zasypia — zajmuje rdzeń w
całości niezależnie od tego, czy cokolwiek się dzieje.

## Ustalenia (decyzje użytkownika, 2026-08-08)

| Rozstrzygnięcie | Wybór |
|---|---|
| Takt pętli | **D19 zostaje nietknięte** — każdy takt przerysowuje klatkę, także identyczną |
| Kształt wiersza | **`FrameLine` niesie segmenty** (lewy i prawy) zamiast jednego napisu dopchniętego spacjami |
| Cel kroku | **Rząd wielkości, bez twardego progu**; każda dźwignia zmierzona przed i po |

**Konsekwencja pierwszej decyzji, przyjęta świadomie:** pomijanie
identycznych klatek dałoby na nieruchomym ekranie zejście ze 184 ms do zera
i było najtańszą pojedynczą zmianą w całym kroku. Zostaje odrzucone, żeby
nie ruszać modelu odświeżania z kroku 09. Zysk musi więc pochodzić wyłącznie
z potanienia samej klatki.

Warto jednak odnotować efekt uboczny, który się z tym wiąże: **jeśli klatka
zejdzie poniżej 50 ms, pętla zacznie zasypiać między taktami** (`usleep` w
`GameLoop::waitForNextTick()`), więc zużycie procesora spadnie samo — bez
zmiany decyzji o stałym takcie.

## Punkt wyjścia — na co idzie 184 ms

| Faza | Koszt | Udział |
|---|---|---|
| Rysowanie tekstu listy (30 wierszy) | ~119 ms | 65% |
| Chrom (panele, nawiasy, etykiety, suwak) | ~34 ms | 18% |
| Kwantyzacja i typ paletowy | ~42 ms | 23% |
| Kodowanie do Sixela | ~5 ms | 3% |
| Przesył do terminala | niemierzony | — |

(Udziały nie sumują się do 100%, bo pochodzą z osobnych pomiarów
różnicowych; dokładny rozkład ma dać narzędzie z kroku 16.)

## Dźwignie

Wszystkie cztery zmierzone wstępnie przed napisaniem tego planu, na
osobnych próbkach. Liczby są punktem wyjścia, nie obietnicą — potwierdzenie
należy do kroku.

### 1. Wiersz bez dopychania spacjami — **104 ms → 39 ms** (30 wierszy)

Wiersz listy jest dziś jednym napisem: nazwa, długi ciąg spacji, rozmiar.
Spacje powstały po to, żeby pasek zaznaczenia sięgał krawędzi — a od kroku
13 pasek jest prostokątem rysowanym osobno, więc dopychanie nie służy już
niczemu w trybie graficznym. Rasteryzacja płaci za każdą z nich.

`FrameLine` dostaje segment lewy i prawy; renderer pozycjonuje je sam.
Tryb tekstowy nadal potrzebuje wypełnienia (tło zaznaczenia rysuje tam ANSI,
nie prostokąt), więc dopychanie przenosi się do niego — z warstwy aplikacji
do renderera, który go faktycznie potrzebuje.

### 2. Mapowanie na paletę motywu zamiast kwantyzacji — **42 ms → 4,6 ms**

`quantizeImage()` analizuje obraz i dobiera paletę adaptacyjnie. Tymczasem
klatka bez miniatury zawiera **wyłącznie kolory motywu** — paleta jest znana
z góry. `remapImage()` z gotowym obrazkiem palety i bez ditheringu robi to
samo dziewięć razy szybciej.

Dotyczy tylko klatek bez bitmapy; z miniaturą zostaje ścieżka adaptacyjna z
paletą 256 (krok 12, D24).

**Korekta z 2026-08-09 ([00-decyzje.md](../00-decyzje.md), D37):** to zawężenie
okazało się błędem — i to nie wydajnościowym. Ścieżka adaptacyjna odziedziczyła
razem z `quantizeImage()` przesuwanie kolorów interfejsu, więc zaznaczenie pliku
graficznego przemalowywało całą aplikację. Klatka z miniaturą dostaje dziś
**paletę hybrydową**: wpisy motywu bez zmiany plus barwy policzone z samego
zdjęcia. `remapImage()` obsługuje odtąd obie drogi, a dźwignia 2 dotyczy całego
potoku, nie tylko klatek bez bitmapy.

Ta dźwignia ma mocne kryterium poprawności: skoro wszystkie kolory klatki
należą do palety motywu, **wynikowy blob Sixela powinien być identyczny co
do bajtu** przed zmianą i po niej. Jeśli nie jest — mapowanie coś zgubiło.

### 3. Pamięć podręczna bitmap wierszy — **25 ms → 8,2 ms**

Przy przewijaniu listy o jeden wiersz dwadzieścia dziewięć z trzydziestu
wierszy jest znakowo identycznych z poprzednią klatką. Rasteryzujemy je od
nowa dwadzieścia razy na sekundę.

Wiersz narysowany raz do własnej bitmapy (klucz: treść, styl, rozmiar pisma)
i składany na płótno przy kolejnych klatkach kosztuje trzykrotnie mniej.
Unieważnienie: zmiana rozmiaru okna, motywu albo fontu.

### 4. Pamięć podręczna warstwy chromu — szacowane **34 ms → koszt jednego złożenia**

Panele, nawiasy narożne i etykiety stref są **identyczne w każdej klatce**,
dopóki nie zmieni się rozmiar okna ani motyw. Narysowane raz do bitmapy tła
i składane na płótno zamiast rysowane od nowa.

Do zmierzenia w kroku: czy złożenie bitmapy 1000×600 jest tańsze od
narysowania kilkunastu kształtów. Przy dźwigni 3 pomiar wypadł korzystnie,
ale tam składane były wąskie paski, nie całe płótno.

## Czego nie robimy — i dlaczego

Zmierzone ślepe uliczki, zapisane po to, żeby nikt do nich nie wracał:

| Pomysł | Wynik pomiaru | Wniosek |
|---|---|---|
| Złożenie całego tekstu w jeden `ImagickDraw` (wiele `annotation()`, jedno `drawImage()`) | **154 ms wobec 110 ms** przy trzydziestu wierszach | Wolniejsze. Koszt siedzi w rasteryzacji glifów, nie w liczbie wywołań |
| `getImageColors()` jako wejście do decyzji „kwantyzować czy nie” | **27,8 ms** | Droższe niż część tego, co miałoby oszczędzić; o trybie decyduje obecność bitmapy, nie zliczanie kolorów |
| Sam `setImageType(PALETTE)` bez wcześniejszej kwantyzacji | **153 ms wobec 42 ms** | Wolniejsze; kolejność z kroku 08 była słuszna |

Poza zakresem kroku:

- **Pomijanie identycznych klatek** — decyzja użytkownika, D19 zostaje.
- Rezygnacja z Sixela, zmiana fontu, zmiana modelu rysowania na
  bezpośrednie sterowanie terminalem.
- Optymalizacja trybu tekstowego — jest tani i nie leży na ścieżce
  krytycznej.
- Wielowątkowość i procesy pomocnicze.

## Kolejność prac

1. **Wzorzec przed zmianami** — pełny przebieg narzędziem z kroku 16,
   zapisany jako punkt odniesienia.
2. **Dźwignia 2 (paleta)** — najtańsza w robocie i z najmocniejszym
   kryterium poprawności (identyczny blob).
3. **Dźwignia 1 (segmenty wiersza)** — dotyka `Domain` i obu rendererów.
4. **Dźwignia 3 (cache wierszy)**.
5. **Dźwignia 4 (cache chromu)** — ostatnia, bo jej opłacalność jest
   najmniej pewna; jeśli pomiar nie potwierdzi zysku, zostaje odrzucona
   wraz z liczbą w dzienniku.
6. **Wzorzec po zmianach** i porównanie z punktem 1.

Każdy krok osobno mierzony i osobno odwracalny — inaczej przy spadku
wydajności nie będzie wiadomo, która zmiana zawiniła.

## Kontrola regresji wizualnej

Optymalizacja renderowania psuje wygląd po cichu, a testy strukturalne tego
nie wyłapią. Dlatego:

- Dla klatek bez bitmapy: **blob Sixela identyczny co do bajtu** przed i po
  (dotyczy dźwigni 2 i 3 — mają nie zmieniać ani jednego piksela).
- Dla pozostałych: zrzuty PNG z narzędzia kroku 16, porównane obejrzeniem
  oraz różnicą obrazów.
- Zestaw scenariuszy z kroku 16 przechodzi przed każdą dźwignią i po niej.

## Rozstrzygnięcia ze startu kroku

Sześć pytań z planowania rozstrzygnięte przez użytkownika przed napisaniem
kodu, plus jedno wymuszone przez pomiar.

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Kształt segmentów w `FrameLine` | **Lista segmentów z wyrównaniem** (`list<FrameSegment>` + enum `Alignment`), nie dwa pola |
| 2 | Gdzie trafia dopychanie spacjami | **Wspólny pomocnik** `Infrastructure\Rendering\SegmentLayout`, wołany przez renderer tekstowy |
| 3 | Klucz pamięci wierszy | **Klucz niesie wszystko, co wpływa na piksele** — nie istnieje ścieżka unieważnienia, o której można zapomnieć |
| 4 | Postać pamięci chromu | **Gotowe płótno tła klonowane co klatkę** — zastępuje naraz tworzenie płótna i rysowanie chromu |
| 5 | `Preview::rows` | **Usunąć** — martwe od kroku 13, dotyka D24 |
| 6 | Takt po optymalizacji | **Podnieść do 30 kl./s** |
| + | Kryterium poprawności dźwigni 2 | **Zastąpione wiernością kolorów** — patrz niżej |

### Dlaczego kryterium „identyczny blob” musiało zniknąć

Plan zakładał, że skoro klatka zawiera wyłącznie kolory motywu, mapowanie na
paletę motywu da blob identyczny co do bajtu. **Pomiar pokazał, że założenie
było fałszywe**: `quantizeImage()` buduje paletę adaptacyjnie, przez
klastrowanie, i przesuwa kolory nawet wtedy, gdy budżet palety jest większy
niż liczba kolorów w obrazie.

Zmierzone na klatce listy (63 kolory przed kwantyzacją), odległość RGB roli
motywu od najbliższego koloru, który przeżył:

| rola | bez kwantyzacji | paleta 16 | 32 | 64 | 128 |
|---|---|---|---|---|---|
| obwódka `#4a515e` | 0 | 12 | 12 | 12 | 12 |
| zaznaczenie `#313845` | 0 | 31 | 31 | 31 | 31 |
| akcent `#d9a441` | 0 | **95** | 95 | 95 | 95 |
| tekst `#dcdfe4` | 0 | 13 | 13 | 13 | 13 |
| przygaszony `#8d939d` | 0 | 72 | 72 | 72 | 72 |

Innymi słowy: kolory dobrane w kroku 13 nigdy nie trafiały na ekran w takiej
postaci, w jakiej je zaprojektowano — tło ciemniało, akcent tracił nasycenie.
Nowe kryterium brzmi więc: **po `remapImage()` każda rola ma odległość 0**.
Sprawdzone i spełnione przy każdym budżecie palety.

## Weryfikacja wartości domyślnych z kroku 14

Dług z D28, spłacony na starcie tego kroku. Pomiary na scenariuszu
`chrome-text`, po 15 przebiegów:

| Ustawienie | Wynik pomiaru | Werdykt |
|---|---|---|
| Paleta 16 / 32 / 64 / 128 / 256 | 209,2 / 214,0 / 211,2 / 212,9 / 212,9 ms | **Czas nie zależy od palety w całym zakresie.** Blob rośnie 20,1 → 23,1 kB. Wybór 64 zostaje — decyduje wygląd, nie czas |
| Wygładzanie tekstu wyłączone | 207,4 ms, 23,1 kB | **Domyślna wartość potwierdzona** |
| Wygładzanie tekstu włączone | 243,4 ms, 63,1 kB | +36 ms i +40 kB — krok 13 szacował +22 ms i +33 kB |
| Wygładzanie obrysów wyłączone | 88,5 ms, 3,9 kB | |
| Wygładzanie obrysów włączone | 91,5 ms, 7,4 kB | **Domyślna wartość potwierdzona** — +3 ms mieści się w rozrzucie, +3,5 kB to cena zaokrąglonych narożników |

Wszystkie trzy wartości domyślne z kroku 14 zostają bez zmian. **Uwaga na
przyszłość:** po dźwigni 2 ustawienie palety nie steruje już kwantyzacją, lecz
liczbą wpisów w palecie motywu — role mieszczą się w niej zawsze, a budżet
rozstrzyga tylko o liczbie półcieni (patrz `ThemePalette`).

## Kryteria ukończenia

- Klatka tańsza wielokrotnie wobec wzorca sprzed zmian; wynik każdej
  dźwigni zmierzony osobno i zapisany w tym pliku.
- Dźwignie, które nie potwierdziły zysku, **odrzucone wraz z liczbą** —
  ślepa uliczka opisana tak samo starannie jak sukces.
- Klatki bez bitmapy dają blob identyczny co do bajtu wobec stanu sprzed
  optymalizacji palety i pamięci wierszy.
- Wygląd sprawdzony pod XTermem, zrzut opisany w dzienniku realizacji.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- Wzorzec pomiarów po zmianach zapisany obok wzorca sprzed nich.

## Specyfikacja zrealizowana

### Rozliczenie dźwigni — każda zmierzona osobno

Płótno 1000×600, siatka 166×46, scenariusz `chrome-text` (pełna klatka listy),
mediana z 15–21 przebiegów. Kolumna „po” to stan po **tej** dźwigni, każda
wdrażana na stanie poprzedniej.

| Dźwignia | Przed | Po | Zysk |
|---|---|---|---|
| Punkt wyjścia | — | 212,4 ms | — |
| **2. `remapImage()` zamiast kwantyzacji** | 212,4 ms | 176,1 ms | −17% |
| **1. Wiersz bez dopychania spacjami** | 176,1 ms | 106,0 ms | −40% |
| **3. Pamięć bitmap wierszy** | 106,0 ms | 76,0 ms | −28% |
| **4. Pamięć warstwy chromu** | 76,0 ms | 41,0 ms | −46% |
| **5. Pamięć paska zaznaczenia** (spoza planu) | 41,0 ms | 34,2 ms | −17% |

Wynik końcowy wobec wzorca sprzed zmian
([2026-08-09-17-przed.json](../../pomiary/2026-08-09-17-przed.json) →
[2026-08-09-17-po.json](../../pomiary/2026-08-09-17-po.json)):

| Scenariusz | Przed | Po | Zysk |
|---|---|---|---|
| puste płótno | 46,5 ms | 7,5 ms | **6,2×** |
| sam tekst | 207,7 ms | 21,3 ms | **9,8×** |
| same ramki | 90,5 ms | 13,8 ms | **6,6×** |
| **ramki z tekstem** | **212,4 ms** | **34,2 ms** | **6,2×** |
| zaznaczenie | 338,6 ms | 32,1 ms | **10,6×** |
| suwak | 50,0 ms | 12,5 ms | **4,0×** |
| klatka z miniaturą | 226,1 ms | 78,2 ms | **2,9×** |
| klatka z okienkiem | 233,1 ms | 48,8 ms | **4,8×** |

**Cel kroku — rząd wielkości — osiągnięty dla klatek bez bitmapy.** Klatka
z miniaturą została w tyle (2,9×) i to celowo: kwantyzacja adaptacyjna jest tam
jedyną poprawną drogą (D24), a po optymalizacji odpowiada za 54% jej czasu.

**Korekta z 2026-08-09 ([00-decyzje.md](../00-decyzje.md), D37):** zdanie
„kwantyzacja adaptacyjna jest tam jedyną poprawną drogą” było fałszywe, a jej
koszt okazał się drugim w kolejności zarzutem — pierwszym było to, że
przemalowywała interfejs. Paleta hybrydowa zdjęła z tej klatki 55% czasu
(60,0 → 26,8 ms; sama kwantyzacja 40,0 → 5,1 ms, pomiar A/B na scenariuszu
`thumbnail`), więc **cel kroku jest dziś osiągnięty także dla klatki
z miniaturą**: mieści się ona w budżecie 33 ms taktu 30 kl./s, z którego
wypadała niemal dwukrotnie. Liczby w tabelach wyżej zostają nietknięte jako
zapis stanu z dnia zamknięcia kroku.

### Piąta dźwignia, której plan nie przewidywał

Pomiar z kroku 16 odnotował, że **zaznaczenie jest najdroższym pojedynczym
elementem klatki**, i wskazał to jako materiał dla kroku 17. Potwierdziło się:
pasek był rysowany dwoma `drawImage()` na pełnej szerokości klatki, co dawało
~2,8 ms na wiersz. Jego kształt jest identyczny w każdym wierszu — zmienia się
tylko położenie w pionie — więc dostał to samo traktowanie co wiersze listy:
bitmapę zapamiętaną i składaną na płótno. Scenariusz `selection` spadł ze
138,8 do 30,0 ms.

### Zmiany w kodzie

| Plik | Zmiana |
|---|---|
| `Domain/ValueObject/FrameLine` | niesie `list<FrameSegment>` zamiast napisu; konstruktory `of()` i `justified()` |
| `Domain/ValueObject/FrameSegment`, `Alignment` | **nowe** — treść segmentu i krawędź, do której przylega |
| `Domain/ValueObject/Preview` | usunięte pole `rows` wraz z walidacją i `InvalidPreviewException::forTooFewRows()` |
| `Infrastructure/Rendering/SegmentLayout`, `PlacedSegment` | **nowe** — wspólny rachunek „co gdzie stoi”; `compose()` dopycha spacjami dla trybu tekstowego |
| `Infrastructure/Imagick/ThemePalette` | **nowe** — paleta z kolorów motywu plus rampy półcieni, z budżetem z konfiguracji |
| `Infrastructure/Imagick/RowBitmapCache` | **nowe** — zapamiętane bitmapy wierszy i paska zaznaczenia |
| `Infrastructure/Imagick/SixelFrameEncoder` | `remapImage()` dla klatek bez bitmapy, klonowana warstwa chromu, wiersze i pasek z bitmap |
| `Infrastructure/Imagick/SixelFrameMetrics` | `baselineWithinRow()`, `rowBitmapHeight()` |
| `Presentation/Cli/GameLoop` | takt 20 → 30 kl./s |
| `Infrastructure/Diagnostics/Measurement` | próg niestabilności wymaga **obu** warunków: ilorazu i różnicy bezwzględnej |

### Kontrola regresji wizualnej

- **Zrzut PNG przed i po: 0 różniących się pikseli z 600 000** — dla klatki
  listy i dla klatki z miniaturą. Dotyczy dźwigni 1, 3, 4 i 5, bo zrzut
  powstaje przed kwantyzacją.
- **Dźwignia 2 zmienia obraz celowo** i została rozliczona tabelą wierności
  kolorów (wyżej): po zmianie każda rola motywu ma odległość 0, czyli klatka
  wreszcie pokazuje kolory, które zaprojektowano w kroku 13.
- Aplikacja sprawdzona pod XTermem: strumień zawiera poprawne klatki Sixel,
  bufor alternatywny włączany i wyłączany. **95 klatek w 5 s wobec 26 przed
  krokiem** (19,0 wobec 5,2 kl./s), przy identycznym oknie i metodzie pomiaru.

### Poprawka w narzędziu z kroku 16

Reguła „pomiar jest niewiarygodny, gdy `max/min` przekroczy 1,35” była czystym
ilorazem i po optymalizacji zaczęła zapalać się na scenariuszach trwających
kilka milisekund: rozrzut 2,4 ms przy klatce 7 ms to drgnięcie planisty, nie
informacja o kodzie — a znakował cały przebieg jako niewiarygodny i blokował
zapis wzorca. Próg wymaga teraz **obu** warunków naraz: ilorazu powyżej 1,35
**i** różnicy bezwzględnej powyżej 3 ms. To poprawka wykryta przez używanie
narzędzia, a nie dopasowanie reguły do wyniku: sam iloraz nie miał
zabezpieczenia od dołu, a sama różnica bezwzględna przepuszczałaby wahanie
40 ms przy klatce trwającej 45.

## Dziennik realizacji

**2026-08-09** — krok zrealizowany w całości.

Kolejność prac zgodna z planem: wzorzec przed zmianami → dźwignia 2 → 1 → 3 →
4 → (5) → wzorzec po zmianach. Każda dźwignia mierzona osobno, z bramkami
jakości uruchamianymi między nimi.

Wykonane:

1. **Oba zadania startowe z kroku 16 zamknięte.** Wzorzec odniesienia zapisany
   na spokojnej maszynie; wartości domyślne z kroku 14 zweryfikowane (tabela
   wyżej) i pozostawione bez zmian.
2. Sześć pytań ze startu kroku rozstrzygniętych przez użytkownika, plus siódme
   wymuszone pomiarem (kryterium dźwigni 2).
3. Pięć dźwigni wdrożonych i rozliczonych osobno.
4. `Preview::rows` usunięte wraz z konsekwencjami w `PreviewSelectedEntryUseCase`
   (parametr `$rows` w `execute()` zniknął, bo przestał na cokolwiek wpływać).
5. Takt pętli podniesiony do 30 kl./s.
6. 562 testy, 1365 asercji — wszystkie przechodzą. PHPStan `max` bez błędów,
   PHP-CS-Fixer bez uwag (184 pliki).

Odstępstwa od planu:

- **Kryterium „identyczny blob” dla dźwigni 2 odrzucone jako fałszywe**
  i zastąpione wiernością kolorów — decyzja użytkownika po przedstawieniu
  pomiaru. Uzasadnienie w sekcji wyżej.
- **Piąta dźwignia (pasek zaznaczenia)** dodana poza listą z planu. Została
  wskazana przez pomiar z kroku 16 jako materiał dla tego kroku, więc mieści
  się w jego celu, ale nie w jego literalnym zakresie.
- **Poprawka progu niestabilności w narzędziu z kroku 16** — zmiana w kodzie
  spoza zakresu tego kroku, wymuszona tym, że bez niej nie dało się spełnić
  kryterium ukończenia „wzorzec po zmianach zapisany”.
- **Enum `Alignment` ma dwa przypadki, nie trzy.** Przy wyborze wariantu
  „lista segmentów z wyrównaniem” padło sformułowanie „lewo/prawo/środek”;
  wyśrodkowania nie ma dziś czym uzasadnić — żadna kolumna go nie używa, a
  nieużywany przypadek enuma to kod, którego nikt nie sprawdził. Dopisanie
  go razem z gałęzią w `SegmentLayout` to kilka linii.

Odnotowane, **nie zrobione**:

- **Klatka z miniaturą pozostaje najdroższa** (78,2 ms, w tym 42 ms
  kwantyzacji). Przy zaznaczonym obrazie pętla nadal nie mieści się w budżecie
  30 kl./s. Potanienie tej ścieżki wymagałoby ruszenia D24, czyli decyzji
  o palecie dla klatek z bitmapą.
  **Zamknięte 2026-08-09 poprawką D37** — decyzja o palecie zapadła, ale
  wymusiło ją zgłoszenie błędu wyglądu, nie rachunek wydajności. Ta sama zmiana
  załatwiła jedno i drugie; szczegóły w korekcie przy rozliczeniu dźwigni wyżej.
- **Wąskim gardłem przestało być rysowanie.** Aplikacja pod XTermem daje
  19 kl./s, choć sama klatka kosztuje ~20 ms w tym oknie — resztę zjada
  przyjęcie i wyrysowanie Sixela przez terminal (krok 16 zmierzył ~75 ms do
  odpowiedzi DA1 po klatce). Dalsze przyspieszanie potoku nie podniesie już
  liczby klatek na sekundę.
- **Pomiar dźwigni 3 i 5 jest „ciepły”.** Narzędzie przerysowuje tę samą
  klatkę, więc pamięci podręczne trafiają w 100%. Odpowiada to dominującemu
  przypadkowi aplikacji (D19: każdy takt przerysowuje klatkę, także
  identyczną), ale zawyża zysk tuż po zmianie katalogu, gdy pamięć jest pusta.

**2026-08-09 (uzupełnienie po zamknięciu kroku)** — pozycja „klatka z miniaturą
pozostaje najdroższa” zamknięta poprawką D37, opisaną w
[00-decyzje.md](../00-decyzje.md). Wyszła nie z listy zadań tego kroku, lecz
ze zgłoszenia użytkownika: najechanie kursorem na plik graficzny zmieniało
kolory całej aplikacji. Przyczyną była jedyna ścieżka, której dźwignia 2 nie
objęła, więc poprawka jest domknięciem tej dźwigni — kwantyzacja adaptacyjna
zniknęła z potoku klatki w całości, a `remapImage()` na palecie zbudowanej
z góry obsługuje odtąd każdy przypadek.

Zysk zmierzony przebiegiem A/B (25 iteracji, ten sam sprzęt, oba stany jeden po
drugim — porównanie z zapisanym wzorcem odrzucone, bo wszystkie osiem
scenariuszy wyszło szybciej o 10–20%, także nietknięte poprawką, co znaczy, że
maszyna była tym razem mniej obciążona):

| faza | przed | po |
|---|---|---|
| rysowanie | 6,7 ms | 7,2 ms |
| kwantyzacja | 40,0 ms | 5,1 ms |
| kodowanie | 13,2 ms | 14,6 ms |
| **razem** | **60,0 ms** | **26,8 ms** |

Rysowanie i kodowanie drgnęły w górę: pierwsze o odczyt miniatury z pamięci
podręcznej, drugie o bogatszą paletę wynikowej klatki (blob 29,8 → 30,4 kB).
Kryterium wierności kolorów z dźwigni 2 obowiązuje odtąd również dla klatek
z podglądem i jest pilnowane testem.
