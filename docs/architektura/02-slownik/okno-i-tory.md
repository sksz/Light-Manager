# 2. Słownik domenowy — Okno terminala, tryb okienkowy i trzeci tłumacz

> Część rozdziału 2. Pojęcia i wstęp: [slownik.md](slownik.md).
> Spis rozdziałów: [docs/architecture.md](../../architecture.md).

Rozmiar okna jako wielkość zmienna w czasie, prezentacja poza terminalem i renderer, który tłumaczy ten sam słownik prymitywów na OpenGL.

## Rozmiar okna terminala nie jest stałą uruchomienia (od kroku 33)

**O rozmiar okna pyta się co klatkę i niczego się z niego nie zapamiętuje.**
`SIGWINCH` ustawia w `TerminalService` znacznik — tym samym wzorcem, co sygnały
zamknięcia — a `TerminalSizeService` zdejmuje go przy najbliższym odczycie
i mierzy ponownie: komórki ze `stty size`, piksele zapytaniem `ESC [ 14 t`
(ponawianym wyłącznie u terminala, który odpowiedział przy starcie; milczącemu
rozmiar komórki liczy się z poprzedniego pomiaru). Kontrakty `ViewportPort`
i `InputPort` (do kroku 34 pod nazwą `TerminalPort`) są nietknięte: składanie
klatki i renderer pytały co klatkę już wcześniej, więc świeżą odpowiedź
dostają, nie wiedząc, że coś zaszło.

Konsekwencje dla piszącego kod:

- **Nie zapamiętuj wierszy, kolumn ani pikseli między klatkami.** Prostokąty
  liczą się w każdej klatce od nowa z `ViewportPort`; stan żyjący między
  klatkami (`ScrollWindow`, `SectionState`, `SplitState`, `TreeState`) ścina się
  do pojemności przy rysowaniu i to wystarcza.
- **Pamięć podręczna zależna od rozmiaru ma rozmiar w kluczu** (D34) — wtedy
  zmiana okna unieważnia ją sama, bez ścieżki unieważnienia. Krok 33 niczego
  w pamięciach nie zmienił i to była teza tego wzorca, sprawdzona w praktyce.
- **Renderer sixelowy czyści ekran raz po zmianie** — jawny wyjątek od reguły
  „czyszczenie daje migotanie” (krok 08): reguła „kolejna klatka zamalowuje
  poprzednią” stoi na płótnie o stałym rozmiarze.
- **Okno zwężone poniżej sensu rysuje, co się zmieści** — strefy i kolumny
  ustępują wedle swoich reguł (`HudLayout`, `Distribution`), planszy zastępczej
  nie ma.

## Prezentacja poza terminalem: tryb okienkowy (od kroku 34)

**Trzeci tryb renderowania otwiera natywne okno przez PHP-GLFW zamiast rysować
w terminalu** — wybierany flagą CLI `--window`, zanim cokolwiek dotknie
terminala, więc detekcja DA1 w ogóle nie startuje. Tryby terminalowe zostają
pierwszorzędne: `ext-glfw` nie wchodzi do `require` (jest w `suggest`),
a bez flagi nie ma żadnego wymogu.

Tor okienkowy to te same trzy porty z innymi implementacjami — i **nic ponad
to**: pętla, ekrany, moduły i komponenty nie wiedzą, że cokolwiek się zmieniło.

- **`InputPort`** (do kroku 34 `TerminalPort` — nazwa przeszła na neutralną,
  gdy kontrakt dostał drugie źródło, D53) → `GlfwInputService`: zdarzenia
  klawiszy i znaków GLFW wpadają do kolejki jako te same `KeyPress`,
  z pominięciem `KeySequenceParser`. Mapowanie kodów na `Key` żyje w czystym
  `GlfwKeyMapper` — bez jednego wywołania GLFW, testowalne bez okna.
  `Ctrl` i `Alt` przychodzą polem `mods`, nie bajtem sterującym ani sekwencją
  escape. Od kroku 55 do **tej samej** kolejki wpadają zdarzenia wskaźnika
  z trzech dalszych wywołań zwrotnych (przycisk, położenie, kółko), tłumaczone
  równie czystym `GlfwPointerMapper`; ruch bez wciśniętego przycisku jest
  odrzucany w wywołaniu zwrotnym, żeby tor okienkowy zachowywał się dokładnie
  jak tryb `1002` w terminalu, a nie podobnie.
- **`ViewportPort`** → `GlfwViewportService`: framebuffer podzielony przez
  komórkę zastępczą (stałą do kroku 35, w którym zastąpią ją metryki fontu).
  Rozmiar czyta się co pytanie, **bez znacznika i bez ponownego pomiaru** —
  to uproszczenie wzorca z kroku 33, bo GLFW oddaje rozmiar tanio i w procesie.
- **`FrameRendererPort`** → `OpenGlFrameRenderer`: do kroku 35 **zastępczy**
  (tło w kolorze roli motywu + zamiana buforów, treść `Frame` świadomie
  ignorowana); pełne tłumaczenie prymitywów dowozi krok 35.

Reguły, których pilnują testy (`WindowedModeTouchesNoTerminalTest`):

- **Tor okienkowy nie dotyka terminala** — kod w `Infrastructure/Glfw`
  i renderer okienkowy nie mają prawa wymienić usług terminalowych, `STDIN`,
  `STDOUT` ani sekwencji sterujących. Terminal, z którego padło polecenie,
  zostaje nietknięty (sprawdzalne przekierowaniem STDOUT — zero bajtów).
- **Zamknięcie okna i sygnał zbiegają się w jednym miejscu taktu** — obie
  drogi prowadzą do tego samego `break`, a sprzątanie (`glfwTerminate`)
  idzie jak wszędzie dwiema drogami: jawnie w `Bootstrap::shutdown()`
  i funkcją zamknięcia procesu.
- **Zasób OpenGL ginie przed kontekstem, nie razem z procesem** (poprawka
  z kroku 39). Obiekty rozszerzenia zwalniają zasoby GL w destruktorach, a te
  wołają się przy sprzątaniu procesu — czyli **po** `glfwTerminate()`, kiedy
  kontekstu już nie ma; skutkiem jest naruszenie ochrony pamięci po ostatniej
  linii kodu. Dlatego twórca zasobu zamawia jego zwolnienie przez
  `GlfwWindowService::releaseBeforeClose()`, a `close()` wykonuje zamówienia
  w odwrotnej kolejności, zanim zniszczy okno. Dziś zamawia jeden —
  `VgContextService` ze swoim `VGContext`; `VGImage` z pamięci tekstur
  przeżywa kontekst bez szkody (sprawdzone). Reguła na przyszłość: **nowy
  długo żyjący obiekt rozszerzenia to nowe zamówienie**, a nie założenie, że
  zdąży zginąć sam.
- **Rozmiar startowy okna pochodzi z ustawień** (`windowColumns` ×
  `windowRows`, domyślnie 100×30 komórek, D53) — dlatego w torze okienkowym
  konfiguracja czyta się **przed** otwarciem okna; pułapki znanej z toru
  terminalowego tu nie ma, bo odczyt pliku terminala nie dotyka.
- Kontekst OpenGL to **3.3 core** (D53) — pod obie techniki rysowania
  rozważane w kroku 35; rytm klatek zostaje stałym taktem pętli
  z `glfwSwapInterval(0)`, żeby oba tory zachowywały się identycznie.

## Okno pamięta, jak je ustawiono (od kroku 37)

Te same dwa klucze rdzenia (`windowColumns`/`windowRows`) są od kroku 37
**zarazem pamięcią rozmiaru**: okno zapisuje pod nie siatkę nadaną
przeciągnięciem rogu albo maksymalizacją, więc następny start zastaje je
takim, jakim je zostawiono (D67).

- **Rozmiar mierzy się w komórkach, nie w pikselach.** Klucze zostają jedne,
  a ekran ustawień nadal przełącza je strzałkami. Ceną jest okno zmieniające
  rozmiar w pikselach po zmianie fontu — świadoma, bo siatka jest tym, co
  użytkownik ustawiał.
- **Lista `WINDOW_*_CHOICES` przestała być zakresem dopuszczalnych wartości**
  i jest wyłącznie **przystankami strzałek**; wartości pilnują odtąd granice
  `WINDOW_*_MIN`/`MAX`. Strzałka z wartości spoza listy idzie do sąsiada
  w swoją stronę (`Settings::nextStop()`), a nie na początek listy — po
  przeciągnięciu rogu wartość spoza listy jest stanem zwykłym, nie awaryjnym.
- **Zapis następuje po uspokojeniu zmian, nie przy każdym zdarzeniu.**
  Przeciąganie rogu sypie zdarzeniami dziesiątkami na sekundę;
  `WindowSizeSettle` (czysty, bez GLFW) odnotowuje chwilę zmiany, a pytanie
  „czy już cisza” pada raz na takt, zaraz po `glfwPollEvents()`. Zmianę, która
  nie zdążyła się uspokoić, dopisuje `Bootstrap::shutdown()`.
- **Pamiętanie włącza się jawnie** (`GlfwWindowService::rememberSize()`, za
  pokazaniem okna). `bin/render-bench --window` przemierza w jednym przebiegu
  kilkanaście rozmiarów okna ukrytego i żaden z nich nie jest wyborem
  użytkownika — narzędzie pomiarowe nie ma prawa zapisać niczego do ustawień.
- **Pełny ekran** (`core.fullscreen` oraz `F11`) zapamiętuje położenie
  i rozmiar okna, bo `glfwSetWindowMonitor()` ich nie przechowuje. Powrót
  wymaga **dwóch rzeczy, nie jednej**: samo `glfwSetWindowMonitor()` oddaje
  obszar treści niższy o pasek tytułu (menedżer okien liczy podaną geometrię
  jako geometrię ramki), a poprawiające to `glfwSetWindowSize()` działa
  **dopiero po zakończeniu przejścia** — więc dopominanie się o właściwy
  rozmiar idzie z taktu na takt (`restoreAfterFullscreen()`, sufit sekundy).
  Rozmiar narzucony pełnym ekranem **nie jest** wyborem użytkownika i do
  ustawień nie trafia — ani w trakcie, ani w czasie powrotu. `F11` jest
  pierwszym klawiszem rdzenia, którego obecność zależy od trybu — w terminalu
  nie znaczyłby nic, a spis klawiszy pokazuje to, co działa tu i teraz.
- **Ikona okna idzie okrężną drogą, bo prostej nie ma**: rozszerzenie
  PHP-GLFW 2.2 nie wystawia `glfwSetWindowIcon`. Okno przedstawia się klasą
  (`WM_CLASS` z podpowiedzi `GLFW_X11_CLASS_NAME`), a wpis `.desktop` wraz
  z ikoną zakłada `bin/install-desktop-entry` — ikonę **rysuje z ról
  włączonego motywu**, więc w repozytorium nie leży ani jeden plik binarny.
  Warunkiem powodzenia jest zgodność `StartupWMClass` z `WM_CLASS`; pilnuje
  jej test.
- **Skala treści jest czytana i pokazywana, a nie stosowana** (rozstrzygnięcie
  nr 4 kroku 37): `glfwGetWindowContentScale` trafia do zakładki „Aplikacja”
  okna pomocy, bo maszyna projektu ma skalę 1.0 i przeliczanie komórki byłoby
  kodem, którego nie da się tu sprawdzić.

## Trzeci tłumacz słownika: renderer OpenGL (od kroku 35)

**Ten sam słownik prymitywów tłumaczy się teraz na trzy sposoby**: Imagick →
Sixel, `CellBuffer` → ANSI, oraz — od kroku 35 — wprost na wywołania API
wektorowego rozszerzenia PHP-GLFW (NanoVG na GL3, D54). W trybie okienkowym
**Imagicka nie ma w ścieżce klatki wcale**, także w dekodowaniu podglądów:
piksele wchodzą natywnie przez `Texture2D::fromDisk()`.

Renderer niczego do słownika nie dokłada i to jest jego sprawdzian — jak
krok 21 był sprawdzianem kontraktu modułu. Zasady, które z tego wynikają:

- **Nowy prymityw obowiązuje odtąd trzy renderery naraz.** Kompletności
  tabeli tłumaczeń pilnuje `PrimitiveTranslationTableTest` — dla renderera
  okienkowego i sixelowego wymaga wpisu na **każdy** prymityw słownika.
  Tekstowy jest z tego wymogu zwolniony świadomie: nawias narożny i suwak
  nie mają odpowiednika w siatce znakowej, więc degraduje je do niczego.
- **Geometria jest lustrem toru sixelowego, nie nowym pomysłem.**
  `GlfwFrameMetrics` powtarza reguły `SixelFrameMetrics` — rozmiar pisma
  jako udział wysokości wiersza, obwódka biegnąca środkiem skrajnych
  wierszy, prawa krawędź liczona od prawej strony framebuffera. Rozjazd
  któregokolwiek z nich widać w klatce natychmiast.
- **Komórkę dyktuje font.** `VgContextService` mierzy szerokość znaku fontu
  o stałej szerokości (lista preferencji ścieżek TTF + `fc-match`, wzorem
  kroku 08) i z niej liczy komórkę; `GlfwViewportService` dzieli przez nią
  framebuffer. Dlatego okno rodzi się **ukryte**: rozmiar startowy z ustawień
  da się przeliczyć na piksele dopiero po zmierzeniu fontu, więc `Bootstrap`
  wymiaruje okno i pokazuje je raz, już poprawne.
- **Pamięć podręczna przenosi się na tekstury.** `VgTextureCache` trzyma
  zdekodowane podglądy z limitem i porządkiem LRU, kluczowane ścieżką wraz
  z czasem i rozmiarem pliku (wzorem `ThumbnailService`); wpisem jest także
  **nieudane dekodowanie**, inaczej pętla próbowałaby go 30 razy na sekundę.
  Atlas glifów utrzymuje NanoVG wewnętrznie — to okienny odpowiednik pamięci
  bitmap napisów z kroku 17.
- **Z przełączników jakości kroku 14 obowiązuje jeden**: `strokeAntialias`
  (→ `shapeAntiAlias`). `textAntialias` i `paletteColors` **nie dotyczą**
  toru okienkowego — NanoVG wygładza tekst zawsze, a palety indeksowanej nie
  ma wcale; to pierwszy renderer rysujący w pełnej głębi kolorów.

Pomiar wchodzi do `bin/render-bench` osią `--window` (okno ukryte hintem
`GLFW_VISIBLE`): te same scenariusze, inne fazy — „rysowanie” i „bufory”
zamiast trzech faz Sixela, bez kolumny kwantyzacji i bez bajtów. Podpis
konfiguracji niesie słowo `window`, więc wzorzec okienkowy nie ma jak zostać
porównany z sixelowym. **Pomiar toru okienkowego stawia barierę `glFinish()`
po zamianie buforów** — bez niej zegar mierzy czas *zlecenia* klatki
sterownikowi, a nie jej wykonania (różnica dwukrotna, zmierzona).
