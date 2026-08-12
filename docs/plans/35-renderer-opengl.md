# Krok 35 — Natywny renderer prymitywów w OpenGL

> **Skąd ten krok.** Powstał 2026-08-11, razem z krokiem 34, na polecenie
> użytkownika (D52). Drugi z dwóch kroków Fazy IX: okno już stoi (krok 34),
> teraz ma pokazać prawdziwą aplikację. Rozstrzygnięcie użytkownika z D52:
> prymitywy rysowane **natywnie wywołaniami OpenGL** — Imagick znika ze
> ścieżki klatki w trybie okienkowym. Odrzucony wariant pośredni (klatka
> Imagicka wgrywana jako tekstura) opisany jest tamże.

## Status

**Ukończony.**

## Cel

Okno z kroku 34 przestaje pokazywać tło i zaczyna pokazywać **całą aplikację**:
pełny słownik prymitywów z kroku 18 (`Bar`, `TextRun`, `Bitmap`, `RoundRect`,
`Scrollbar`, `CornerBrackets` i wszystko, co doszło później) tłumaczony wprost
na wywołania OpenGL, bez Imagicka w ścieżce klatki. Miarą powodzenia jest
**parytet**: każdy ekran rdzenia i obu modułów — lista plików z podglądem,
ustawienia, pomoc, okno komend, okna nakładane — wygląda w oknie tak, jak
w trybie sixelowym: ta sama treść, ten sam układ, te same role motywu. Drugą
miarą jest koszt: klatka w oknie o stałym rozmiarze nie przelicza niczego,
co pamięci podręczne mogą pamiętać — wzorem reguł z kroku 17.

Renderer jest **trzecim tłumaczem tego samego słownika** i niczego do
słownika nie dokłada — to jego sprawdzian, dokładnie tak, jak kontrakt modułu
był sprawdzany krokiem 21. „OpenGL potrafi więcej” nie jest argumentem:
cienie, animacje i półprzezroczystości nie wchodzą, bo prymityw, którego nie
umie narysować tryb tekstowy, nie ma prawa istnieć (reguła z kroku 18).

## Zależności

- **Krok 34** — twardo i całkowicie: kontekst, w którym ten krok rysuje,
  pętla, w której żyje, i viewport, z którego bierze wymiary, powstają tam.
- **Krok 18** (komponenty i płaszczyzny) — renderer dostaje `Frame` jako stos
  płaszczyzn z prymitywami i nie wie, czym jest lista plików — jak dwaj
  poprzedni tłumacze. Stamtąd pochodzi też reguła zamkniętego słownika
  (otwieranego wyłącznie trybem z D48).
- **Krok 13** (motyw graficzny) — role motywu tłumaczą się na kolory; to
  pierwszy renderer **bez palety indeksowanej** — kwantyzacja do 16/256
  kolorów była ograniczeniem Sixela, nie motywu.
- **Krok 17** (optymalizacja) — wzorzec pamięci podręcznych kluczowanych
  zawartością i rozmiarem przenosi się na tekstury: glify i bitmapy nie mają
  prawa być budowane co klatkę. Pomiar „przed i po” obowiązuje jak wszędzie.
- **Kroki 12 i 25** (podglądy obrazów) — prymityw `Bitmap` niesie piksele do
  pokazania; tu muszą trafić do tekstury, z pamięcią podręczną wzorem
  `ThumbnailService`.
- **Krok 30** (filtrowanie), jeśli wykonany wcześniej — jego prymityw
  podświetlenia wchodzi do tabeli tłumaczeń tego kroku. Jeśli później —
  krok 30 rozszerza się o trzeciego tłumacza. Od tego kroku każdy nowy
  prymityw obowiązuje **trzy** renderery naraz.

## Model i wysiłek

**Fable / xhigh.**

To największy krok Fazy IX: renderowanie tekstu (najtrudniejsza część —
metryki fontu dyktują komórkę, a komórka cały układ), tekstury bitmap,
tłumaczenie kompletu prymitywów i pomiar wydajności nowego toru. Ciężar
rozstrzygnięć: technika rysowania (API wektorowe rozszerzenia albo surowe GL
z własnym atlasem glifów) przesądza o połowie kodu kroku.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Ui/Frame`, `Plane`, `Primitive/*` | Słownik prymitywów; współrzędne i jednostki prostokątów — do potwierdzenia (komórki vs piksele), bo od tego zależy, czy renderer dostaje własne metryki wzorem `SixelFrameMetrics` |
| `Infrastructure/Imagick/SixelFrameEncoder` | Pierwszy tłumacz: prymitywy → rysowanie Imagickiem; `SixelFrameMetrics` (komórka z podziału płótna), `RowBitmapCache` (limit 512) — wzorce do przeniesienia, nie kod do współdzielenia |
| `Infrastructure/Rendering/TextFrameRenderer` | Drugi tłumacz: prymitywy → `CellBuffer`; dowód, że słownik daje się tłumaczyć na coś zupełnie innego niż bitmapa |
| `Infrastructure/Imagick/ThumbnailService` | Pamięć podręczna przeskalowanych bitmap; wybór fontu z listy preferencji (krok 08) — precedens dla wyboru fontu TTF |
| `Infrastructure/Rendering/ThemeService`, `ThemePalette` | Role motywu → kolory; kwantyzacja palety jako właściwość toru sixelowego |
| `Infrastructure/Rendering/OpenGlFrameRenderer` | Po kroku 34: czyści tło i zamienia bufory, treść `Frame` jawnie ignoruje |
| API PHP-GLFW | Do rozpoznania na starcie: zakres modułu grafiki wektorowej rozszerzenia (tekst TTF, obrazy, kształty), ładowanie tekstur, wymagana wersja kontekstu |
| `bin/render-bench` | Działa poza terminalem i poza pętlą; scenariusze mierzą tor sixelowy i tekstowy — okienkowego nie zna |

## Zakres

### 1. Technika rysowania

Najcięższe rozstrzygnięcie kroku (lista startowa, punkt 1): **API wektorowe
rozszerzenia PHP-GLFW** (jeśli rozpoznanie potwierdzi tekst TTF, obrazy
i kształty — wtedy tabela tłumaczeń jest krótka, a antyaliasing przychodzi
w cenie) albo **surowe wywołania GL z własnym atlasem glifów** (pełna
kontrola i zero zależności od API wyższego poziomu, za cenę najtrudniejszego
kawałka — tekstu — napisanego ręcznie). Rozpoznanie na starcie kroku, decyzja
użytkownika, wynik do dziennika decyzji.

### 2. Metryki i font

Komórka przestaje być stałą zastępczą z kroku 34: font o stałej szerokości
(wybór z listy preferencji, wzorem kroku 08 — rozstrzygnięcie nr 2, czy
z systemu, czy dołączony do repozytorium) oddaje metryki, metryki dyktują
komórkę w pikselach, komórka — wiersze i kolumny viewportu. Zmiana rozmiaru
okna przelicza wiersze i kolumny tą samą drogą, którą postawił krok 34.

### 3. Tabela tłumaczeń prymitywów

Każdy prymityw słownika dostaje jawne tłumaczenie na wywołania wybranej
techniki — kompletność tabeli jest warunkiem ukończenia, „prymityw
niezaimplementowany” nie istnieje. Osobnej uwagi wymagają: `TextRun`
z wagą (`Weight`) — drugi krój albo drugie renderowanie; `Bitmap` — tekstura
z pamięcią podręczną; `Scrollbar` i `CornerBrackets` — czysta geometria;
`RoundRect` — łuki, których tryb tekstowy przybliża znakami, a tu mają być
prawdziwe.

### 4. Bitmapy i dekodowanie

Prymityw `Bitmap` (miniatury, podglądy) trafia do tekstury. Skąd piksele —
rozstrzygnięcie nr 3: **Imagick zostaje dekoderem obrazów poza ścieżką
klatki** (limity 32 MB / 50 Mpx z D24 i podpowiedź skali z kroku 12 działają
dalej bez zmian) albo ładowanie natywne rozszerzenia, jeśli je ma. Pamięć
podręczna tekstur z limitem — wzorem `RowBitmapCache`, z rozmiarem w kluczu
(D34).

### 5. Motyw i jakość

Role motywu tłumaczą się na kolory pełnej głębi. Antyaliasing — zależny od
techniki z punktu 1; przełączniki jakości z kroku 14 (`TEXT_ANTIALIAS` itd.)
mają w torze okienkowym odpowiednik albo jawną adnotację „nie dotyczy”
(rozstrzygnięcie nr 4). HiDPI: komórka liczona z framebuffera, nie z okna —
do sprawdzenia na starcie, czy środowisko w ogóle pozwala to przetestować.

### 6. Pomiar

Tor okienkowy dostaje pomiar wydajności: rozstrzygnięcie nr 5, czy
scenariusze wchodzą do `bin/render-bench` (okno ukryte na czas pomiaru — do
sprawdzenia, czy da się bez widocznego okna), czy powstaje osobne narzędzie.
Obowiązują obie reguły: klatka w oknie o stałym rozmiarze nie buduje żadnej
tekstury od nowa (pamięci działają), a tryby terminalowe nie drożeją ani
o milisekundę (`--compare` wobec ostatniego wzorca).

## Poza zakresem

- **Rozszerzanie słownika prymitywów** „bo GL potrafi” — cienie, gradienty,
  animacje, przezroczystości poza motywem. Słownik otwiera tryb z D48, nie
  renderer.
- **Mysz** — jak w kroku 34.
- **Usuwanie albo degradowanie trybów terminalowych** — Sixel i tekst zostają
  pierwszorzędne; okno jest trzecim trybem, nie następcą.
- **Zmiany w ekranach, komponentach i modułach** — jeśli którykolwiek wymaga
  poprawki, żeby dobrze wyglądać w oknie, to jest to błąd tłumaczenia w tym
  kroku albo błąd tamtego komponentu — nie zakres tego kroku.
- **Kompozycja klatki inna niż stos płaszczyzn** — renderer tłumaczy to, co
  dostaje; optymalizacje kompozycji należą do przyszłych kroków wydajności,
  jeśli pomiar je uzasadni.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Infrastructure/Rendering/OpenGlFrameRenderer.php` | Infrastructure | Z zastępczego do prawdziwego: pełne tłumaczenie `Frame`. |
| `Infrastructure/Glfw/…FrameMetrics.php` | Infrastructure | **Nowy** — font → komórka → siatka, wzorem `SixelFrameMetrics`. |
| `Infrastructure/Glfw/…TextEngine.php` / `…GlyphAtlas.php` | Infrastructure | **Nowy** — kształt zależny od rozstrzygnięcia nr 1. |
| `Infrastructure/Glfw/…TextureCache.php` | Infrastructure | **Nowy** — tekstury bitmap z limitem, wzorem `RowBitmapCache`. |
| `Infrastructure/Glfw/…ViewportService.php` | Infrastructure | Komórka z metryk fontu zamiast stałej zastępczej z kroku 34. |
| `Infrastructure/Diagnostics/…` | Infrastructure | Scenariusze toru okienkowego (rozstrzygnięcie nr 5). |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Trzeci tłumacz słownika; reguła „nowy prymityw = trzy renderery”. |
| testy | Testy | Tabela tłumaczeń kompletna wobec słownika (test strażniczy), metryki komórki, pamięć tekstur (druga klatka bez budowania), tory terminalowe bez regresji. |

## Do rozstrzygnięcia na starcie kroku

1. **Technika rysowania**: API wektorowe rozszerzenia PHP-GLFW czy surowe GL
   z własnym atlasem glifów — po rozpoznaniu realnego zakresu API na maszynie,
   nie z pamięci ani z dokumentacji.
2. **Font**: lista preferencji fontów systemowych (jak krok 08 — spójny
   z resztą pulpitu, ale niedeterministyczny między maszynami) czy plik TTF
   w repozytorium (identyczne metryki wszędzie — ale licencja i rozmiar repo).
3. **Dekoder bitmap**: Imagick poza ścieżką klatki (limity i skalowanie
   z kroków 12/25 bez zmian) czy natywne ładowanie rozszerzenia.
4. **Przełączniki jakości**: które ustawienia renderowania z kroku 14 mają
   odpowiednik w torze okienkowym, a które dostają jawne „nie dotyczy”.
5. **Pomiar**: scenariusze w `bin/render-bench` (okno ukryte) czy osobne
   narzędzie; i czy wzorzec toru okienkowego wchodzi do `docs/pomiary/`
   obok terminalowych.

## Kryteria ukończenia

- Wszystkie ekrany rdzenia i obu modułów w oknie GLFW: ta sama treść, układ
  i role motywu co w trybie sixelowym — potwierdzone zrzutami obok siebie,
  w prawdziwym środowisku (za zgodą użytkownika, wedle procedury
  z `CLAUDE.md`).
- Miniatury i podglądy obrazów widoczne w oknie; druga klatka z tą samą
  miniaturą nie dekoduje jej ponownie (pamięć tekstur — potwierdzone testem).
- Przeciągnięcie rogu okna: pełny efekt kroku 33 powtórzony w oknie — lista
  płynie za rogiem, zero strzępów, komórka bez zmian.
- Tabela tłumaczeń kompletna: test strażniczy przechodzi po każdy prymityw
  słownika.
- Koszt klatki toru okienkowego zmierzony i zapisany; klatka w oknie o stałym
  rozmiarze nie buduje tekstur ani atlasu od nowa; tory terminalowe bez
  regresji (`bin/render-bench --compare`).
- PHPStan `max` bez błędów (ze stubami), PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-12 — implementacja i weryfikacja wzrokowa

**Rozstrzygnięcia użytkownika ze startu kroku — komplet w D54**: API wektorowe
`VGContext`; font systemowy (preferencje ścieżek TTF + `fc-match`); **natywne
`Texture2D::fromDisk`** (wbrew rekomendacji — zero Imagicka także w dekodowaniu);
`strokeAntialias` działa, reszta „nie dotyczy”; pomiar w `bin/render-bench`
osią `--window`.

**Rozpoznanie przed pytaniami** (wymóg planu — „nie z pamięci ani
z dokumentacji”): `VGContext` ma pełny zakres — kształty z antyaliasingiem,
tekst TTF z wewnętrznym atlasem glifów (`createFont`, `textBounds`,
`textAlign`), obrazy przez `imageFromTexture`. To ono przesądziło
rozstrzygnięcie nr 1.

**Powstało**: `GlfwFontLocator` (lista preferencji + `fc-match`),
`GlfwFrameMetrics` (lustro `SixelFrameMetrics`), `VgContextService` (kontekst
VG, font, komórka z metryk fontu), `VgTextureCache` (LRU z limitem,
wstrzykiwany loader, limity rozmiaru postawione od nowa — D24 chroniło potok
Imagicka, którego tu nie ma), pełny `OpenGlFrameRenderer` (tabela tłumaczeń
kompletna wobec słownika), `WindowBenchmarkRunner`. Komórka zastępcza
z kroku 34 **zniknęła**: `GlfwViewportService` dzieli framebuffer przez
komórkę z metryk fontu, a okno rodzi się ukryte (`GLFW_VISIBLE`) i pokazuje
dopiero zwymiarowane — użytkownik nigdy nie widzi rozmiaru tymczasowego.

**Trzy rzeczy warte zapamiętania**:

1. **Bariera `glFinish()` w pomiarze.** Pierwszy przebieg dał 0,1–0,4 ms na
   klatkę — wynik zbyt piękny, żeby był prawdziwy. Bez vsync
   `glfwSwapBuffers()` wraca, gdy polecenia trafią do kolejki sterownika, więc
   zegar mierzył **zlecenie** klatki, nie klatkę. Po dodaniu bariery liczby
   wzrosły dwukrotnie (0,2–0,8 ms) i dopiero one są pomiarem. W aplikacji
   bariery nie ma i mieć nie powinna — tam asynchroniczność jest zyskiem.
2. **Stuby PHP-GLFW mają trzy błędy**, nie dwa: do znanych z kroku 34
   (`GLFW_TRUE` jako bool, `GLFW_RELEASE` sama przez siebie) doszła sygnatura
   `Texture2D::fromDisk` z jednym parametrem zamiast trzech. Drugi parametr
   jest **konieczny** — sprawdzone empirycznie: `VGImage` wymaga czterech
   kanałów, a bez niego JPG daje trzy, PNG dwa. Rozstrzygnięcie użytkownika:
   przypięcie `dev-main` do commita `f776ee0`, które sygnaturę naprawia; dwie
   pozostałe stałe nadal obchodzimy literałami.
3. **Renderer jest rozcięty na `drawFrame()` → `present()`** — ten sam wzorzec,
   którym D28 rozcięła potok sixelowy: instrumentacja stoi w narzędziu
   pomiarowym, w rendererze nie ma ani jednego wywołania pomiarowego.

**Weryfikacja wzrokowa na żywo** (host zwolniony przez użytkownika, wedle
procedury z `CLAUDE.md`): zrzuty okna GLFW i XTerma z Sixelem obok siebie —
**parytet potwierdzony** na przeglądarce dwupanelowej (ścieżka, tabela
z kolumnami, suwaki, zaznaczenie, nawiasy narożne, etykiety wpięte w obwódkę),
ekranie ustawień, oknie komend z karetką i pasie podglądu. Kolory zmierzone
w zrzucie zgadzają się z motywem co do bajtu (tło `#16181c`). Miniatury:
JPEG (plazma 800×600) i PNG z gradientem — oba w pełnej głębi, bez pasmowania,
którego w torze sixelowym nie da się uniknąć przy 64 kolorach. Zmiana rozmiaru
okna na żywo (1000×630 → 1400×780): klatka wypełnia całość, zero strzępów,
komórka bez zmian, a w tabeli **przybyła kolumna „Prawa”** — `Distribution`
z kroku 27 działa w oknie tak samo jak w terminalu.

**Jakość**: 1102 testy zielone (w tym `PrimitiveTranslationTableTest` —
strażnik kompletności tabeli, sprawdzony przez usunięcie prymitywu: pada
i wraca), PHPStan `max` czysty z rozszerzeniem i bez niego, PHP-CS-Fixer bez
uwag.

### 2026-08-12 — pomiar na zwolnionej maszynie

Pierwsze podejście do pomiaru wypadło przy przeglądarce i edytorze
w tle (load 1,26) i pokazało w torze sixelowym „regresje” do +33%, z czego
trzy scenariusze ze znacznikiem niestabilności. **Nie były to regresje kodu** —
`git diff` na `src/Infrastructure/Imagick/`, czyli na całym mierzonym potoku,
był pusty. Liczby odrzucono bez zapisu, wzorem kroku 22, i powtórzono po
zwolnieniu hosta przez użytkownika.

**Tory terminalowe bez regresji — i to z zapasem.** Wszystkie czternaście
scenariuszy wyszło **szybciej** od wzorca `2026-08-11-po-kroku-33.json`:
od −2,1% (same ramki) do −9,5% (okno komend). Krok nie dotknął ich ani
o linię, więc różnica jest szumem środowiska na korzyść — istotne jest to,
że nie ma ruchu w drugą stronę.

**Wzorzec toru okienkowego zapisany**: `docs/pomiary/2026-08-12-window.json`
(narzędzie go przyjęło, więc rozrzut zmieścił się w progu). Klatka w oknie
kosztuje **0,2–0,7 ms** wobec **6,3–24,7 ms** w torze sixelowym — od dwudziestu
do trzydziestu kilku razy taniej, w zależności od scenariusza:

| Scenariusz | Sixel | Okno |
|---|---|---|
| puste płótno | 6,3 ms | 0,2 ms |
| sam tekst | 10,3 ms | 0,3 ms |
| ramki z tekstem | 16,4 ms | 0,5 ms |
| klatka z miniaturą | 24,7 ms | 0,6 ms |
| okno komend | 24,2 ms | 0,6 ms |
| paski postępu | 23,2 ms | 0,7 ms |
| lista w kolumnach | 19,8 ms | 0,6 ms |

Rozkład faz mówi więcej niż suma: **rysowanie to jedna trzecia kosztu**
(0,1–0,3 ms), reszta to czekanie na GPU za barierą `glFinish()`. W aplikacji,
gdzie bariery nie ma, klatka kosztuje pętlę jeszcze mniej — a budżet taktu
30 kl./s wynosi 33 ms, więc tor okienkowy zużywa go w około dwóch procentach.

Pamięci podręczne działają i widać to w liczbach: różnica między „pustym
płótnem” a „samym tekstem” to **0,1 ms na 166×46 znaków**, co byłoby
niemożliwe, gdyby glify rasteryzowały się co klatkę — atlas NanoVG płaci za
nie raz. Miniatura nie odstaje od klatek bez bitmapy (0,6 ms wobec 0,5–0,7 ms),
bo tekstura powstaje raz na plik (`VgTextureCache`, pokryte testem).

**Przy okazji pomiaru wyszła pułapka, którą ten krok sam zastawił.** Zapisanie
wzorca okienkowego zepsuło domyślne `--compare` toru terminalowego: wybór bez
wskazanego pliku brał **najnowszy po nazwie**, czyli od tej chwili okienkowy,
a ten jest nieporównywalny — narzędzie odmawiało porównania, choć wzorzec
terminalowy leżał obok. `BaselineStore::newest()` przyjmuje więc teraz
konfigurację przebiegu i szuka najnowszego wzorca **o zgodnym podpisie**,
wracając do najnowszego w ogóle dopiero wtedy, gdy nic nie pasuje (odmowa
z wypisanymi obiema konfiguracjami niesie więcej niż „brak wzorca”). Objęte
testem w obie strony.

**Status: krok ukończony.** Wszystkie kryteria spełnione — parytet ekranów
potwierdzony zrzutami, miniatury w oknie, zmiana rozmiaru bez strzępów, tabela
tłumaczeń kompletna pod strażnikiem, koszt klatki zmierzony i zapisany, tory
terminalowe bez regresji, PHPStan `max` i PHP-CS-Fixer czyste, 1102 testy
zielone.
