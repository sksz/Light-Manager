# Krok 34 — Okno GLFW: kontekst OpenGL, wejście i pętla poza terminalem

> **Skąd ten krok.** Powstał 2026-08-11, na polecenie użytkownika (D52).
> Prezentacja po raz pierwszy wychodzi poza terminal: aplikacja uruchomiona
> w trybie okienkowym ma otworzyć natywne okno systemowe przez rozszerzenie
> [PHP-GLFW](https://phpgl.net) i w nim działać. To pierwszy z dwóch kroków
> Fazy IX — stawia okno, kontekst, wejście i pętlę; rysowanie prymitywów
> dowozi krok 35.

## Status

**Ukończony.**

## Cel

Aplikacja uruchomiona z flagą trybu okienkowego **otwiera natywne okno
z kontekstem OpenGL, czyta klawiaturę ze zdarzeń GLFW jako ten sam słownik
`KeyPress`, reaguje na zamknięcie okna i na zmianę jego rozmiaru** — a pętla
główna, ekrany, moduły i komponenty nie wiedzą, że cokolwiek się zmieniło.
Terminal, z którego padło polecenie, zostaje nietknięty: bez trybu surowego,
bez zapytania DA1, bez alternatywnego bufora, bez jednej sekwencji sterującej
na STDOUT.

Klatka w tym kroku jest **treścią zastępczą** — jednolite tło w kolorze tła
motywu (wzór z kroku 09: „treść zastępcza, mechanizm docelowy”). Miarą
powodzenia jest mechanizm: okno żyje i odświeża się w takcie pętli, `q` je
zamyka, przycisk zamknięcia okna kończy aplikację tą samą ścieżką co sygnał,
a przeciągnięcie rogu zmienia rozmiar widziany przez `ViewportPort` od
następnej klatki.

## Zależności

- **Krok 06** (terminal I/O) — źródło słownika: `KeyPress` i enum `Key` leżą
  w `Application/Dto` i od początku nie niosą śladu terminala (D16) — GLFW
  mapuje swoje kody klawiszy wprost na ten słownik, **z pominięciem**
  `KeySequenceParser`, bo sekwencje escape są problemem, którego w oknie nie
  ma. Modyfikator `Ctrl` (dołożony w kroku 19) przychodzi z GLFW jako pole
  `mods` zamiast bajtu sterującego.
- **Krok 07** (wykrywanie trybu renderowania) — `RendererMode` dostaje trzeci
  wariant, a wybór trybu okienkowego **wyprzedza** detekcję: DA1 nie zostaje
  wysłane, bo nie ma go do kogo wysłać.
- **Krok 09** (pętla główna) — pętla zostaje **jedna**: `glfwPollEvents()`
  wchodzi w takt jako źródło zdarzeń, a zamknięcie okna staje się drugą drogą
  do tego samego `break`, którym dziś wychodzi się po `q` i po sygnale.
- **Krok 13** (motyw graficzny) — kolor tła zastępczej klatki pochodzi z ról
  motywu, nie z gołej stałej.
- **Krok 33** (reakcja na zmianę rozmiaru) — wzorzec do powtórzenia, nie do
  wymyślenia: rozmiar czyta się przy każdym pytaniu, a pamięci podręczne
  kluczowane rozmiarem odświeżą się same (D34). GLFW oddaje rozmiar
  framebuffera tanim wywołaniem w procesie, więc cała maszyneria znacznika
  i ponownego pomiaru z kroku 33 jest tu **niepotrzebna** — to uproszczenie
  wzorca, nie odstępstwo od niego.

Od Fazy VII krok **nie zależy** i ona nie zależy od niego.

## Model i wysiłek

**Fable / xhigh.**

Kodu będzie umiarkowanie dużo, ale prawie cały stanie w nowych plikach
`Infrastructure` — ciężar leży gdzie indziej: w rozgałęzieniu bootstrapu,
który dziś **zaczyna** od efektu ubocznego trybu surowego terminala
(`TerminalService::getInstance()` w pierwszej linii `boot()`), oraz
w rozstrzygnięciach o kontraktach — czy `TerminalPort` może pod swoją nazwą
obsłużyć źródło zdarzeń, które terminalem nie jest. Błąd w tych miejscach
rozlałby się na oba tory naraz.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Dto/Key`, `KeyPress` | Słownik klawiszy bez śladu terminala; niesie surowe bajty i (od kroku 19) `Ctrl` — do sprawdzenia, czy pole bajtów zniesie źródło, które bajtów nie ma |
| `Application/Port/TerminalPort` | `readKey()` + `shutdownRequested()`; nazwa i komentarze mówią „terminal”, kontrakt — nie |
| `Domain/ValueObject/RendererMode` | Dwa warianty; komentarz klasowy zakłada wybór „na podstawie możliwości terminala” |
| `Presentation/Cli/Bootstrap` | `boot()` w twardej kolejności: tryb surowy → detekcja Sixela → ustawienia → renderer (przejęcie ekranu). W trybie okienkowym dwa z czterech efektów ubocznych nie mają prawa zajść |
| `Presentation/Cli/GameLoop` | Stały takt; klawisze zbierane do wyczerpania w każdej iteracji, znacznik zamknięcia czytany między klatkami — gotowe miejsca na drugie źródło |
| `Infrastructure/Rendering/RendererService` | Konstruktor wykrywa tryb i przejmuje ekran — w trybie okienkowym ma nie dotykać terminala |
| `bin/light-manager` | Preflight `ext-imagick`, bez parsowania argumentów (do potwierdzenia) |
| Rozszerzenie `glfw` | Nieobecne w środowisku; instalowane ze źródeł, poza PECL (do potwierdzenia na starcie kroku, razem z wersją PHP-GLFW i zakresem jego API) |

## Zakres

### 1. Trzeci tryb i jego wybór

`RendererMode` dostaje trzeci wariant, a wybór zapada **przed pierwszym
dotknięciem terminala** — flaga CLI jest naturalnym kandydatem, bo klucz
ustawień czyta się dziś już po starcie sekwencji terminalowej (rozstrzygnięcie
nr 1). Preflight w `bin/light-manager` sprawdza rozszerzenie `glfw` tylko przy
wybranym trybie okienkowym — czytelny komunikat i kod 1, wzorem `ext-imagick`.
`ext-glfw` **nie wchodzi** do `require` w `composer.json`, bo tryby terminalowe
mają działać bez niego (wzorem `intl` z D20: co najwyżej `suggest`). PHPStan
`max` musi przejść bez załadowanego rozszerzenia — stuby (dostarczane przez
projekt PHP-GLFW albo własne, minimalne) są częścią tego kroku, nie
usprawiedliwieniem dla `@phpstan-ignore`.

### 2. Okno i kontekst

Nowa usługa okna w `Infrastructure` (Singleton, wzorem pozostałych usług
z efektem ubocznym): `glfwInit()`, okno z kontekstem OpenGL, tytuł, sprzątanie
przy wyjściu wszystkimi trzema ścieżkami z kroku 06 (normalną, wyjątkiem,
sygnałem). Wersja i profil kontekstu — rozstrzygnięcie nr 6, bo wybrana tu
wartość musi udźwignąć technikę rysowania z kroku 35; otwieranie okna „na
nowo, inaczej” w następnym kroku byłoby porażką tego.

### 3. Wejście

Usługa wejścia implementująca kontrakt portu wejściowego (dzisiejszy
`TerminalPort` — czy pod tą nazwą, rozstrzyga nr 3): zdarzenia klawiszy
i znaków z GLFW trafiają do kolejki i wychodzą jako `KeyPress`, jedno zdarzenie
na klawisz, jak dziś. Mapowanie kodów GLFW na enum `Key` (strzałki,
Home/End/PageUp/PageDown, Delete, Enter, Backspace, Tab, Escape, litery,
`Ctrl`+litera) żyje w **czystym maperze bez jednego wywołania GLFW** — wzorem
`KeySequenceParser`: najbardziej błędogenna część kroku ma być testowalna
w PHPUnit bez okna. `shutdownRequested()` odpowiada „tak” po sygnale
(pcntl zostaje — proces nadal dostaje SIGINT z powłoki) **albo** po zamknięciu
okna — obie drogi zbiegają się w jednym miejscu taktu, jak w kroku 09.

### 4. Pętla

`glfwPollEvents()` wchodzi w takt pętli; zamiana buforów kończy klatkę.
Rytm — rozstrzygnięcie nr 4: dzisiejszy stały takt z wyłączonym vsync
zachowuje się identycznie w obu torach, vsync oddaje rytm monitorowi, ale
rozjeżdża tryb okienkowy z terminalowym. `GameLoop` ma pozostać jeden;
jeśli różnica torów nie mieści się w portach, to sygnał, że rozgałęzienie
stoi w złym miejscu.

### 5. Rozmiar

Implementacja `ViewportPort` dla okna: wiersze i kolumny z rozmiaru
framebuffera podzielonego przez rozmiar komórki. Prawdziwa komórka wyjdzie
z metryk fontu dopiero w kroku 35 — do tego czasu stała, jawnie opisana jako
zastępcza (rozstrzygnięcie nr 5). Odczyt rozmiaru co pytanie, bez pamięci
i bez znacznika — patrz zależność od kroku 33.

### 6. Klatka zastępcza

Renderer okienkowy implementuje `FrameRendererPort`: czyści tło kolorem roli
tła motywu i zamienia bufory. Treść `Frame` **świadomie ignoruje** — z jawnym
komentarzem wskazującym krok 35. Dzięki temu cała reszta potoku
(`FrameComposer`, ekrany, moduły) pracuje naprawdę i od pierwszego dnia,
a różnica jest wyłącznie w ostatnim kroku tłumaczenia.

### 7. Bootstrap

`Bootstrap::boot()` się rozgałęzia: tor terminalowy **nie zmienia się ani
o linię kosztu ani kolejności**, tor okienkowy dostaje własną sekwencję
(okno → ustawienia → renderer), w której usługi terminalowe nie są ani
tworzone, ani dotykane. `createGameLoop()` i `shutdown()` analogicznie —
przywracanie terminala zamienia się w zamknięcie okna.

## Poza zakresem

- **Rysowanie prymitywów** — w całości krok 35; ten krok kończy się tłem.
- **Mysz** — GLFW ją oddaje, ale aplikacja nie ma słownika zdarzeń myszy
  w żadnej warstwie; to osobna decyzja projektowa i osobny krok, jeśli w ogóle.
- **Pełny ekran, brak ramki, ikona okna** — kosmetyka okna poza tytułem.
- **Oba tory naraz w jednym procesie** (okno + terminal jednocześnie) — tryb
  wybiera się raz, przy starcie, jak dziś Sixel wobec tekstu.
- **Inne platformy niż X11** — środowisko projektu; Wayland/macOS/Windows bez
  sprawdzania, dopóki nie ma gdzie sprawdzić.
- **Migracja `bin/render-bench` na tor okienkowy** — pomiar wchodzi z prawdziwym
  rysowaniem w kroku 35; mierzenie czyszczenia tła nie mówi nic.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Domain/ValueObject/RendererMode.php` | Domain | Trzeci wariant; komentarz klasowy przestaje zakładać terminal. |
| `Application/Port/TerminalPort.php` | Application | Ewentualnie — nazwa/komentarze (rozstrzygnięcie nr 3). |
| `Infrastructure/Glfw/…WindowService.php` | Infrastructure | **Nowy** — `glfwInit`, okno, kontekst, sprzątanie. |
| `Infrastructure/Glfw/…InputService.php` | Infrastructure | **Nowy** — kolejka zdarzeń → `KeyPress`, znacznik zamknięcia. |
| `Infrastructure/Glfw/…KeyMapper.php` | Infrastructure | **Nowy** — czyste mapowanie kodów GLFW na `Key`, testowalne bez okna. |
| `Infrastructure/Glfw/…ViewportService.php` | Infrastructure | **Nowy** — `ViewportPort` z framebuffera i komórki zastępczej. |
| `Infrastructure/Rendering/OpenGlFrameRenderer.php` | Infrastructure | **Nowy, zastępczy** — tło motywu + zamiana buforów. |
| `Infrastructure/Rendering/RendererService.php` | Infrastructure | Trzeci tryb; w torze okienkowym zero sekwencji na terminal. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Rozgałęzienie `boot()`/`createGameLoop()`/`shutdown()`. |
| `Presentation/Cli/GameLoop.php` | Presentation | Ewentualnie — `pollEvents` w takcie (rozstrzygnięcie nr 4). |
| `bin/light-manager` | bin | Flaga trybu, preflight `glfw` pod flagą. |
| `composer.json` | — | `suggest: ext-glfw`; stuby do analizy statycznej w `require-dev`, jeśli są publikowane. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Trzeci tryb prezentacji; instalacja PHP-GLFW. |
| testy | Testy | Maper klawiszy (pełna tabela), viewport z rozmiaru okna, tryb okienkowy nie dotyka terminala, tor terminalowy bez regresji. |

Nazwy plików `Glfw/…` są zapowiedzią, nie decyzją — przedrostek i nazwa
katalogu to rozstrzygnięcie nr 2 (precedens: `Infrastructure/Imagick` nazwany
po bibliotece).

## Do rozstrzygnięcia na starcie kroku

1. **Jak wybiera się tryb okienkowy** — flaga CLI (np. `--window`), zmienna
   środowiskowa, czy klucz ustawień. Klucz ustawień ma pułapkę: konfiguracja
   czyta się dziś **po** wejściu w sekwencję terminalową, więc wymagałby
   przestawienia kolejności bootstrapu — do sprawdzenia, czy bez szkody.
2. **Nazewnictwo** — wariant `RendererMode` (`OpenGl` / `Window` / `Glfw`)
   i katalog usług (`Infrastructure/Glfw` po bibliotece, jak `Imagick`, czy po
   roli). Nazwa niesie decyzję: „Glfw” mówi, czym rysujemy, „Window” — gdzie.
3. **Czy `TerminalPort` zostaje pod swoją nazwą** — GLFW może go implementować
   mimo nazwy (zero zmian w `Application` i u konsumentów), albo port
   przechodzi na neutralną nazwę (uczciwiej, ale zmiana dotyka wszystkich
   miejsc wstrzyknięcia). Pokrewne: co z polem surowych bajtów w `KeyPress`,
   których zdarzenie GLFW nie ma.
4. **Rytm klatek w oknie** — dzisiejszy stały takt z `glfwSwapInterval(0)`
   (identyczne zachowanie obu torów) czy vsync (rytm monitora, mniejsze zużycie,
   inny takt niż w terminalu).
5. **Rozmiar startowy okna i komórka zastępcza** — sztywne piksele czy
   przeliczenie z typowego 100×30; komórka ze stałej czy od razu z metryk
   fontu systemowego (uprzedzając krok 35).
6. **Wersja i profil kontekstu OpenGL** — pod wymagania techniki rysowania
   z kroku 35 (API wektorowe rozszerzenia albo surowe GL), żeby okna nie
   otwierać w następnym kroku inaczej.

## Kryteria ukończenia

- Uruchomienie z flagą otwiera okno z tłem motywu; `q` **i** przycisk
  zamknięcia okna kończą aplikację czysto, jedną ścieżką; terminal, z którego
  wystartowano, pozostaje nietknięty (zero sekwencji sterujących, zero trybu
  surowego — sprawdzalne przekierowaniem STDOUT).
- Przeciągnięcie rogu okna: `ViewportPort` widzi nowy rozmiar od następnej
  klatki — potwierdzone testem na dublerze; pełny efekt wzrokowy należy do
  kroku 35.
- Uruchomienie bez flagi: zachowanie i koszt jak przed krokiem — tryby
  terminalowe nietknięte, `bin/render-bench --compare` bez regresji.
- Brak rozszerzenia `glfw` przy fladze → komunikat i kod 1; bez flagi brak
  jakiegokolwiek wymogu.
- Mapowanie klawiszy pokryte testami jednostkowymi bez okna (czysty maper).
- PHPStan `max` bez błędów (ze stubami, bez załadowanego rozszerzenia),
  PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-11 — implementacja (bez weryfikacji na żywo)

**Rozstrzygnięcia użytkownika ze startu kroku — komplet w D53**: flaga CLI
`--window`; `RendererMode::OpenGl` + katalog `Infrastructure/Glfw`;
`TerminalPort` → **`InputPort`** (neutralna nazwa); stały takt
z `glfwSwapInterval(0)`; rozmiar startowy **konfigurowalny w ustawieniach**
(rozszerzenie ponad rekomendację — nowe klucze rdzenia
`windowColumns`/`windowRows`, domyślnie 100×30, na zakładce „Wygląd”);
kontekst **3.3 core**.

**Stan zastany skorygowany**: rozszerzenie `glfw` było już zainstalowane
(PHP-GLFW 2.2.0, API `gl` 4.1, GLFW 3.3.8, X11; API wektorowe `VGContext`
wkompilowane — ważne dla rozstrzygnięcia nr 1 kroku 35). Stuby publikowane
na Packagist: `phpgl/ide-stubs` (weszły do `require-dev`, w PHPStan przez
`scanFiles`).

**Powstało**: `Infrastructure/Glfw/` — `GlfwKeyMapper` (czysty, bez wywołań
GLFW; pełna tabela klawiszy pod testami), `GlfwWindowService` (glfwInit, okno
3.3 core, sprzątanie dwiema drogami), `GlfwInputService` (`InputPort`;
kolejka z wywołań zwrotnych, `glfwPollEvents()` przy pierwszym pytaniu
w takcie, sygnały pcntl **albo** `glfwWindowShouldClose` → jedno
`shutdownRequested()`), `GlfwViewportService` (`ViewportPort`; framebuffer /
komórka zastępcza 10×20 px, bez stanu), `GlfwException` + `GlfwProblem`;
`Infrastructure/Rendering/OpenGlFrameRenderer` (zastępczy: tło motywu +
zamiana buforów). Bootstrap rozgałęziony (tor okienkowy: ustawienia → okno →
wejście; terminalowy niezmieniony co do linii), `bin/light-manager` z flagą
i preflight'em pod flagą, `ProblemPresenter` zna `GlfwException`, napisy
`problem.missingGlfw`, `problem.glfw.*`, `window.title` w obu językach.

**Odstępstwa od tabeli planu, wszystkie w duchu D51 („mniej zmian, niż
przewidywano”)**:

- **`RendererService` nie zmienił się wcale** — rozgałęzienie stoi
  w `Bootstrap` (jedynym konsumencie): tor okienkowy dostaje
  `OpenGlFrameRenderer` jako zwykły obiekt, wzorem strategii
  `SixelFrameRenderer`/`TextFrameRenderer` tworzonych `new`-em. Mocniejsza
  gwarancja niż planowana: usługa terminalowa w torze okienkowym **w ogóle
  nie powstaje**, zamiast „nie dotykać terminala”.
- **`GameLoop` nie zmienił się wcale** — `glfwPollEvents()` wszedł w takt
  przez `readKey()` (pierwsze pytanie w takcie pompuje kolejkę), więc pętla
  została jedna i nietknięta, jak chciał plan.
- Klawiszem wyjścia jest **`F10`** (i komenda `quit`) — jak w torze
  terminalowym; „`q`” z celu planu było skrótem myślowym, słownik jest
  wspólny i wiązania nie zmieniły się.
- `Ctrl+H/I/J/M` w oknie **są** odróżnialne od Backspace/Tab/Enter (GLFW
  daje osobne zdarzenia) — w terminalu nie były, bo bajt jest ten sam.
  Zachowane naturalne zachowanie okna; różnica bez konsekwencji, bo żaden
  skrót ich dziś nie używa.
- Stuby `phpgl/ide-stubs` definiują dwie stałe błędnie (`GLFW_TRUE` jako
  bool, `GLFW_RELEASE` sama przez siebie) — kod i test porównują literały,
  z komentarzem.

**Testy**: pełna tabela mapera (91 testów w katalogu Glfw), arytmetyka
i bezstanowość viewportu, strażnik `WindowedModeTouchesNoTerminalTest`
(źródła toru okienkowego nie wymieniają usług terminalowych, `STDIN`/
`STDOUT`, `stty` ani `ESC [` — komentarze wyłączone tokenizerem). Razem
**1087 testów zielonych**; PHPStan `max` czysty **z rozszerzeniem i bez
niego** (`PHPRC=/dev/null`); PHP-CS-Fixer bez uwag. Smoke testy ścieżek
błędów: `--window` bez wyświetlacza → komunikat i kod 1 przy **zerze bajtów
na STDOUT**; `--window` bez rozszerzenia → `problem.missingGlfw` i kod 1;
bez flagi → zachowanie jak przed krokiem.

### 2026-08-11 — weryfikacja na żywo (host zwolniony przez użytkownika)

**Okno na żywo**: `./bin/light-manager --window` otworzyło okno „Light
Manager” (klient 1000×600 + dekoracje WM, zgodnie ze 100×30 × komórka
10×20 px). Zrzut `xwd` zdekodowany Imagickiem: wszystkie cztery próbkowane
punkty klatki mają **dokładnie `#16181c`** — kolor roli tła motywu Grafit.
Pętla żyła 47 s przy ~3,9% CPU (stały takt z `usleep`). **SIGTERM** zakończył
proces czysto: okno zniknęło (`glfwTerminate` zadziałał), proces wyszedł,
a **STDOUT i STDERR miały po 0 bajtów** — terminal, z którego padło
polecenie, nietknięty, dokładnie wedle kryterium.

**Dogrywka po doinstalowaniu `xdotool` przez użytkownika** — trzy
weryfikacje początkowo niewykonalne maszynowo przeszły na żywo:

- **`F10`** wstrzyknięte XTEST-em → kod wyjścia 0, okno i proces znikają,
  strumienie po 0 B.
- **Zamknięcie okna protokołem WM** (`Alt+F4` → `WM_DELETE_WINDOW`, ta sama
  droga co przycisk „X”) → `glfwWindowShouldClose` → ten sam `break` co
  sygnał: kod wyjścia 0, strumienie po 0 B.
- **Zmiana rozmiaru na żywo**: okno klienta miało na starcie **dokładnie
  1000×600** (potwierdzone w drzewie X — 100×30 × komórka 10×20);
  `xdotool windowsize` → 1440×812, a zrzut po zmianie pokazał `#16181c`
  w środku i we wszystkich czterech rogach nowej powierzchni — viewport
  podąża za framebufferem, klatka zamalowuje całość, zero strzępów.
  Po zmianie `F10` zakończyło czysto. (Nową siatkę 144×40 zobaczy dopiero
  krok 35 — klatka zastępcza nie ma czym jej pokazać; na poziomie portu
  potwierdza ją test na dublerze, jak żądało kryterium.)

**Pomiar**: `bin/render-bench --compare` wobec wzorca
`2026-08-11-po-kroku-33.json` — **bez regresji powyżej progu**, wszystkie
scenariusze w granicach ±4,6% (szum). Tor terminalowy nie zdrożał.
