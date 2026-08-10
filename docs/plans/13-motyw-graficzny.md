# Krok 13 — Motyw graficzny: paleta Grafit i układ panelowy

## Status

Ukończony

## Zależności

Krok 11 (render listy plików), Krok 12 (podgląd miniatur).

## Model i wysiłek

Opus / high — zmiana dotyka obu rendererów naraz, przesuwa granicę
odpowiedzialności między `Application` a `Infrastructure` (kto liczy
wysokość listy) i wymaga pomiarów wydajności, tak jak krok 08.

## Cel

Zastąpić placeholderowy wygląd z kroku 08 zaprojektowanym motywem: jedną
paletą z nazwanymi rolami, wyraźnie rozdzielonymi strefami ekranu i ramą
o zaokrąglonych rogach.

Dotychczasowe kolory nigdy nie były projektowane — powstały jako dziewięć
stałych w `SixelFrameEncoder`, żeby cokolwiek było widać. Efekt: jeden
błękit `#8ab4f8` pełni cztery role naraz (nagłówek, katalog, obwódka
okienka, tło paska zaznaczenia), nagłówek i stopka leżą na tym samym tle
co lista, a margines 4 px dosuwa tekst do krawędzi okna.

## Ustalenia (decyzje użytkownika, 2026-08-08)

Podjęte na podstawie porównania czterech palet i trzech układów
(makiety: artefakt „Light Manager — motyw Grafit, trzy układy
futurystyczne”). Zapis w [00-decyzje.md](00-decyzje.md), D25.

| Rozstrzygnięcie | Wybór |
|---|---|
| Paleta | **Grafit** — neutralna szarość, jeden bursztynowy akcent |
| Układ | **Wariant 1 „HUD”** — każda strefa jako panel z obwódką i zaokrąglonymi rogami |
| Tryb tekstowy | **Uproszczony** — bez ramek ze znaków; linie rozdzielające, etykiety stref i kolory ról |
| Motyw przełączalny | **Tak**, wraz z ekranem konfiguracyjnym i plikiem `~/.light-manager/settings.json` — realizacja w [kroku 14](14-konfiguracja-i-ekran-ustawien.md) |

## Paleta Grafit

Wartości docelowe wraz z rolą, jaką pełnią. Kontrast liczony wg WCAG 2.1
względem tła.

| Rola | Wartość | Kontrast | Zastosowanie |
|---|---|---|---|
| `background` | `#16181c` | — | tło całej klatki |
| `surface` | `#1f2228` | 1,1 | wypełnienie okienka |
| `border` | `#4a515e` | 2,3 | obwódki paneli, linie rozdzielające, szyna przewijania |
| `text` | `#dcdfe4` | 13,3 | nazwy plików, treść |
| `muted` | `#8d939d` | 5,8 | rozmiary, podpisy, etykiety stref, podpowiedzi klawiszy |
| `accent` | `#d9a441` | 7,9 | katalogi, nawiasy narożne, suwak, krawędź zaznaczenia |
| `selection` | `#313845` | 1,5 | tło zaznaczonego wiersza |
| `selectionText` | `#f2f4f7` | 10,7 (na `selection`) | tekst zaznaczonego wiersza |
| `warning` | `#d9a441` | 7,9 | ostrzeżenie w pasku stanu (ten sam odcień co akcent) |
| `danger` | `#e0645c` | 5,2 | błąd w pasku stanu, pusta ramka podglądu |
| `success` | `#7fb069` | 6,2 | potwierdzenie operacji |

**Korekta wobec projektu na ekranie:** obwódka i tło zaznaczenia zostały
rozjaśnione (`#2e323a` → `#4a515e`, `#2a2f38` → `#313845`) po obejrzeniu
klatki w prawdziwym oknie. Ton „ledwie jaśniejszy od tła” wygląda dobrze na
makiecie w przeglądarce, ale w terminalu ma grubość jednego piksela i
przechodzi przez kwantyzację palety — panele stawały się niewidoczne, a
ekran wyglądał jak tryb tekstowy.

Akcent celowo jest ciepły przy zimnym tle — to jedyny nasycony kolor w
całej klatce, więc niesie hierarchię sam z siebie. Katalogi w bursztynie
zrywają z konwencją „katalog = niebieski” znaną z `ls`; ukośnik na końcu
nazwy (krok 11) pozostaje, więc typ wpisu jest czytelny także bez kolorów.

## Układ „HUD”

Ekran dzieli się na cztery strefy, każda jako panel z obwódką 1 px w
kolorze `border` i zaokrąglonymi rogami:

```
╭─ ŚCIEŻKA ────────────────────────────╮
│ ~/Projects/light_manager      26/31   │
╰───────────────────────────────────────╯
╭─ PLIKI ──────────────────────────────╮
│  docs/                             ▐  │
│ ▌logo.png                  128 kB  ▐  │
│  README.md                 3,1 kB     │
╰───────────────────────────────────────╯
╭─ PODGLĄD ────────────────────────────╮
│  [miniatura]  logo.png · PNG 512×512  │
╰───────────────────────────────────────╯
│ × Brak uprawnień │ SIXEL 64 │ q wyjście │
```

Motywy składowe:

- **Nawias narożny** — krótki odcinek w kolorze `accent` w dwóch
  przeciwległych rogach panelu, zamiast obwódki w całości pomalowanej
  akcentem. Akcent zostaje rzadki, więc dalej pracuje.
- **Etykieta wpięta w krawędź** — nazwa strefy wersalikami z rozstrzeloną
  literą (`ImagickDraw::setTextKerning()`), na prostokącie w kolorze tła
  zamalowującym fragment obwódki.
- **Zaznaczenie** — zaokrąglony prostokąt w kolorze `selection` z krawędzią
  `accent` grubości 2 px po lewej; tekst w `selectionText`. Koniec z
  inwersją, która malowała pasek nasyconym błękitem.
- **Pasek przewijania** — szyna 3 px przy prawej krawędzi panelu listy,
  suwak w `accent`, wysokość i położenie z okna przewijania. Informacja
  nowa: dziś nie ma jej nigdzie.
- **Pasek stanu** — segmenty rozdzielone pionową kreską: komunikat,
  wskaźnik trybu renderowania, podpowiedzi klawiszy.
- **Promień zaokrąglenia** liczony z wysokości wiersza (`rowHeight / 2`),
  nie stały w pikselach — inaczej przy komórce 6×13 px byłby albo
  niewidoczny, albo pożerałby róg.

## Zakres

### 1. Motyw jako obiekt z nazwanymi rolami

Dziewięć stałych `#rrggbb` w `SixelFrameEncoder` i osobny zestaw kodów ANSI
w `TextFrameRenderer` zastępuje jeden obiekt. Oba renderery czytają te same
role; każdy tłumaczy je po swojemu (Imagick — `ImagickPixel`, tryb
tekstowy — kody ANSI).

Miejsce: `Infrastructure/Rendering`. Motyw jest szczegółem renderowania —
`Domain` nie ma prawa znać wartości `#16181c`, a żadna warstwa wyżej go nie
woła, więc port aplikacyjny się nie zakłada (ta sama zasada co dla
`ImagickCapabilityService` — [00-decyzje.md](00-decyzje.md), D17). Port
pojawi się dopiero w kroku 14, gdy ekran ustawień będzie musiał wymienić
motyw z poziomu `Application`.

### 2. Model układu zamiast liczenia wierszy w dwóch miejscach

Dziś `RenderCurrentFrameUseCase` odejmuje `CHROME_ROWS = 3` i
`PREVIEW_ROWS = 8`, a `SixelFrameEncoder` niezależnie zakłada, że wiersz 0
to nagłówek, a lista zaczyna się od wiersza 2. Zgodność obu rachunków jest
dziś pilnowana komentarzem („Zabezpieczenie, nie właściwy limit”). Panele z
obwódkami dokładają do chromu po 2 wiersze na strefę, więc rachunek prowadzony
w dwóch miejscach się rozjedzie.

Rozwiązanie: układ liczony **raz**, w `Infrastructure`, i udostępniony obu
stronom:

- `Application/Dto/FrameLayout` — liczby wierszy przypadające strefom.
- `Application/Port/FrameLayoutPort` — `layoutFor(int $rows, int $columns): FrameLayout`.
- `Infrastructure/Rendering/HudFrameLayoutService` — implementacja
  (Singleton, sufiks `Service` zgodnie z konwencją), znająca grubość ramek
  wybranego układu.

`RenderCurrentFrameUseCase` przestaje mieć własne stałe wysokości i pyta
port. `SixelFrameEncoder` przelicza wiersze stref na prostokąty pikselowe
przez istniejący `SixelFrameMetrics`.

### 3. Rysowanie ramy w trybie Sixel

Panele (`ImagickDraw::roundRectangle()`), nawiasy narożne, etykiety stref,
pasek przewijania, nowe zaznaczenie, segmentowany pasek stanu, margines
liczony w kolumnach siatki zamiast stałych 4 px.

Pasek przewijania potrzebuje danych, których klatka dziś nie niesie:
położenie okna i liczba wpisów. Nowy obiekt wartości
`Domain/ValueObject/ScrollPosition` (pierwszy widoczny wpis, liczba
widocznych, liczba wszystkich) wypełniany przez
`RenderCurrentFrameUseCase`, który i tak już liczy okno przewijania.

### 4. Trzy tony komunikatu

Dziś stopka ma jeden kolor — czerwień. „Skopiowano 3 pliki” i „Brak
uprawnień” wyglądają identycznie. Nowy `Domain/ValueObject/MessageTone`
(`Info` | `Warning` | `Error`) plus znak wiodący (`·`, `!`, `×`), żeby ton
był czytelny także po kwantyzacji i w trybie ośmiokolorowym.

### 5. Pomiar palety Sixela i wygładzania

`PALETTE_COLORS = 16` dzieli budżet między tło, tekst i półcienie liter.
Obwódki, wypełnienia paneli i piksele brzegowe łuków to kolejne odcienie —
przy 16 kolorach rogi wyjdą schodkami, a wygładzony tekst zostanie brudny.

Do zmierzenia, metodą z kroku 08 (płótno 1000×600, pełna lista):

| Wariant | Co mierzymy |
|---|---|
| 16 kolorów (stan dzisiejszy) | czas enkodowania, rozmiar bloba, wygląd łuków |
| 32 kolory | j.w. |
| 64 kolory | j.w. |
| 128 kolorów | j.w. |
| obrys z wygładzaniem / bez | `ImagickDraw::setStrokeAntialias()` — koszt w ms i w odcieniach |

Punkt odniesienia: krok 08 zmierzył 118 ms przy 16 kolorach i płótnie
800×600, krok 09 — 15,2 kl./s dla klatki zastępczej i ~9 kl./s dla pełnej
listy przy zadeklarowanym takcie 20 kl./s. Wybór progu należy do
użytkownika, po przedstawieniu liczb — tak samo jak przy antyaliasingu
tekstu w D18.

### 6. Tryb tekstowy — wariant uproszczony

Fallback dostaje te same **role kolorów** i te same etykiety stref, ale
**nie** odrysowuje ramek znakami. Zamiast panelu — linia rozdzielająca z
etykietą; zamiast zaokrąglonego paska zaznaczenia — znak `▌` w kolorze
akcentu plus tło wiersza.

Kolory ról idą przez kody 256-kolorowe (`ESC [ 38;5;n m`), z degradacją do
ośmiu kolorów podstawowych, gdy terminal nie deklaruje więcej. Konwersja
`#rrggbb` → indeks palety xterm-256 to czysta arytmetyka, więc trafia pod
testy jednostkowe.

## Poza zakresem tego kroku

- Plik konfiguracyjny, przełączanie motywu i ekran ustawień — [krok 14](14-konfiguracja-i-ekran-ustawien.md).
- Pozostałe trzy palety z porównania (Nordyk, Papier, Indygo) — wchodzą
  jako dane w kroku 14, gdy będzie czym je przełączać.
- Reakcja na zmianę rozmiaru okna (ograniczenie z kroku 08, niezależne od
  motywu).
- Widok dwupanelowy i inne pozycje z „Zakresu poza MVP”.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Infrastructure/Rendering/Theme.php` | Infrastructure | Nowy — role motywu jako wartości `#rrggbb`. |
| `Infrastructure/Rendering/ThemeService.php` | Infrastructure | Nowy — Singleton oddający aktywny motyw. |
| `Infrastructure/Rendering/HudFrameLayoutService.php` | Infrastructure | Nowy — podział ekranu na strefy, implementacja `FrameLayoutPort`. |
| `Infrastructure/Rendering/AnsiPalette.php` | Infrastructure | Nowy — konwersja `#rrggbb` → kod ANSI 256/8. |
| `Application/Port/FrameLayoutPort.php` | Application | Nowy — port układu. |
| `Application/Dto/FrameLayout.php` | Application | Nowy — wiersze przypadające strefom. |
| `Domain/ValueObject/ScrollPosition.php` | Domain | Nowy — dane dla paska przewijania. |
| `Domain/ValueObject/MessageTone.php` | Domain | Nowy — enum tonu komunikatu. |
| `Domain/ValueObject/Frame.php` | Domain | Nowe pola: `scroll`, ton komunikatu. |
| `Application/UseCase/RenderCurrentFrameUseCase.php` | Application | Stałe `CHROME_ROWS`/`PREVIEW_ROWS` ustępują portowi układu; wypełnianie `ScrollPosition`. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Przepisany: panele, nawiasy, etykiety, szyna przewijania, zaznaczenie, pasek stanu; stałe kolorów znikają na rzecz motywu. |
| `Infrastructure/Imagick/SixelFrameMetrics.php` | Infrastructure | Promień zaokrąglenia i margines w kolumnach siatki. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | Role zamiast kodów wpisanych na sztywno, linie stref, degradacja 256 → 8 kolorów. |
| `docs/architecture.md` | Dokumentacja | Nowe pozycje w słowniku domenowym (§2) i w tabeli portów (§3). |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | Aktualizacja, jeśli zmienią się konwencje (§ „każda zmiana odzwierciedlona w obu miejscach”). |
| testy | Testy | Układ stref, konwersja kolorów, geometria suwaka, tony komunikatu, testy strukturalne enkodera. |

## Rozstrzygnięcia ze startu kroku

Decyzje użytkownika, 2026-08-08 (zapis: [00-decyzje.md](00-decyzje.md), D27):

1. **Degradacja układu: najpierw znika pas podglądu.** Panele zostają w
   każdym oknie, w którym się mieszczą; ustępują dopiero pojedynczo, gdy
   liście groziłby brak miejsca.
2. **Pasek stanu jako osobny panel z obwódką** (3 wiersze), wiernie wobec
   makiety — nie jednowierszowa belka.
3. **Etykiety stref po angielsku** (`PATH`, `FILES`, `PREVIEW`), a
   wielojęzyczność interfejsu jako osobny krok planu —
   [krok 15](15-wielojezycznosc.md).
4. **Paleta Sixela: 64 kolory**, po pomiarach z sekcji 5.
5. **Wygładzanie domyślnie wyłączone**, z osobnymi przełącznikami dla
   tekstu i obrysów — oba do konfiguracji w [kroku 14](14-konfiguracja-i-ekran-ustawien.md).

   **Skorygowane po rozdzieleniu pomiaru:** decyzja zapadła na podstawie
   liczby „33 ms i potrojony blob”, która obejmowała oba rodzaje
   wygładzania naraz. Rozbity pomiar pokazał, że **cały ten koszt bierze
   się z tekstu**, a wygładzanie obrysów kosztuje 3 kB bloba i czas w
   granicach błędu (206 ms wobec 214 ms). Bez niego łuk o promieniu
   dziewięciu pikseli jest schodkami, czyli zaokrąglenia nie ma wcale.
   Stąd stan docelowy: **tekst bez wygładzania, obrysy z wygładzaniem**;
   oba nadal jako osobne przełączniki do kroku 14.

## Specyfikacja zrealizowana

### Powstałe i zmienione pliki

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Domain/ValueObject/Message.php` | Domain | Nowy — treść paska stanu wraz z tonem. |
| `Domain/ValueObject/MessageTone.php` | Domain | Nowy — enum `Info`/`Warning`/`Error` ze znakiem wiodącym. |
| `Domain/ValueObject/ScrollPosition.php` | Domain | Nowy — położenie okna, udział widoczny, postęp na szynie. |
| `Domain/Exception/InvalidMessageException.php` | Domain | Nowy. |
| `Domain/Exception/InvalidScrollPositionException.php` | Domain | Nowy. |
| `Domain/ValueObject/Frame.php` | Domain | Komunikat jako `Message`; nowe pola `scroll` i `hints`; rozszerzone `equals()`. |
| `Application/Dto/FrameZone.php` | Application | Nowy — strefa: `top`, `rows`, `innerTop`, `innerRows`, etykieta. |
| `Application/Dto/FrameLayout.php` | Application | Nowy — cztery strefy plus szerokość i wcięcie treści. |
| `Application/Port/FrameLayoutPort.php` | Application | Nowy — `layoutFor(rows, columns)`. |
| `Application/UseCase/RenderCurrentFrameUseCase.php` | Application | Stałe chromu ustąpiły portowi układu; wypełnianie `ScrollPosition` i podpowiedzi klawiszy. |
| `Infrastructure/Rendering/Theme.php` | Infrastructure | Nowy — jedenaście ról koloru, paleta Grafit. |
| `Infrastructure/Rendering/ThemeService.php` | Infrastructure | Nowy — Singleton wydający aktywny motyw. |
| `Infrastructure/Rendering/HudFrameLayoutService.php` | Infrastructure | Nowy — progi degradacji i podział okna na strefy. |
| `Infrastructure/Rendering/AnsiPalette.php` | Infrastructure | Nowy — `#rrggbb` → kod ANSI 256, z degradacją do szesnastu kolorów po odcieniu. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Przepisany: panele, nawiasy narożne, etykiety w krawędzi, suwak, zaznaczenie, pasek stanu; kolory wyłącznie z motywu. |
| `Infrastructure/Imagick/SixelFrameMetrics.php` | Infrastructure | Promień zaokrąglenia z wysokości wiersza, `topOf()`, `middleOf()`, `xOf()`. |
| `Infrastructure/Rendering/SixelFrameRenderer.php` | Infrastructure | Pobiera układ z portu i podaje go enkoderowi. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | Przepisany: wariant uproszczony — linie stref z etykietami, role kolorów przez ANSI. |
| `Infrastructure/Rendering/RendererService.php` | Infrastructure | Wstrzykuje motyw, układ i paletę ANSI. |
| `Presentation/Cli/LoopState.php` | Presentation | Komunikat trzymany jako `Message` (ton `Error`). |
| `Presentation/Cli/Bootstrap.php` | Presentation | Dowiązanie `FrameLayoutPort`. |
| `tests/Support/FixedLayout.php` | Testy | Nowy — układ o zadanej pojemności. |
| 8 plików testów | Testy | 74 nowe testy (łącznie 335). |

### Ustalone zachowania

| Zachowanie | Rozstrzygnięcie |
|---|---|
| Kolory | Wyłącznie przez role motywu; żadnego literału `#rrggbb` poza `Theme` |
| Strefy | Cztery panele: `PATH`, `FILES`, `PREVIEW`, pasek stanu |
| Obwódka | Zaokrąglony prostokąt biegnący **środkiem** pierwszego i ostatniego wiersza strefy |
| Promień | `rowHeight / 2` — skaluje się z komórką terminala, nie stała pikselowa |
| Nawias narożny | Ścieżka w akcencie: noga, łuk, noga — grubość 2 px, lewy górny i prawy dolny róg |
| Etykieta | Wpięta w górną krawędź, wersaliki z kerningiem, na prostokącie w kolorze tła |
| Zaznaczenie | Zaokrąglona płaszczyzna `selection` + 2 px krawędź akcentu; koniec z inwersją |
| Suwak | Szyna przy prawej krawędzi listy, wysokość z udziału widocznego, położenie z postępu |
| Komunikat | Trzy tony: kolor **i** znak wiodący (`·`, `!`, `×`) |
| Pasek stanu | Komunikat po lewej, podpowiedzi klawiszy po prawej, między nimi kreska |
| Tryb tekstowy | Bez ramek: linia z etykietą otwiera strefę, pusty wiersz ją zamyka |
| Kolory w trybie tekstowym | ANSI 256 z degradacją do szesnastu — po odcieniu, nie po odległości w RGB |

### Progi układu

Zmierzone i utrwalone w `HudFrameLayoutServiceTest`:

| Wysokość okna | Co ustępuje | Wierszy dla listy |
|---|---|---|
| ≥ 26 | nic — komplet czterech paneli | okno − 16 |
| 18–25 | pas podglądu | okno − 8 |
| 12–17 | + obwódka ścieżki (zostaje goły wiersz) | okno − 6 |
| 8–11 | + obwódka paska stanu | okno − 4 |
| 3–7 | + obwódka listy | okno − 2 |
| 1–2 | + pasek stanu, potem ścieżka | 1 |

### Pomiary (płótno 1000×600, siatka 46×166, pełna lista 30 wpisów)

| Paleta | Wygładzanie | Czas klatki (mediana z 15) | Blob | Obwódki widoczne? |
|---|---|---|---|---|
| 16 | tekst + obrys | 236 ms | 38,4 kB | **nie** |
| 32 | tekst + obrys | 220 ms | 44,5 kB | **nie** |
| 64 | tekst + obrys | 225 ms | 56,3 kB | tak |
| 128 | tekst + obrys | 254 ms | 60,4 kB | tak |
| 16 | żadne | 217 ms | 16,3 kB | tak |
| 64 | żadne | 214 ms | 19,2 kB | tak |
| **64** | **sam obrys (stan docelowy)** | **184 ms** | **23,1 kB** | tak |

Rozdzielenie wygładzania (paleta 64) — liczba, która zmieniła decyzję:

| Wygładzanie | Czas | Blob |
|---|---|---|
| żadne | 214 ms | 19,2 kB |
| sam obrys | 206 ms | 22,2 kB |
| tekst i obrys | 236 ms | 56,4 kB |

Rozkład czasu klatki (paleta 64, wygładzanie włączone): **rysowanie 162 ms,
kwantyzacja 40 ms, blob 5,5 ms**. Sam chrom (panele, nawiasy, etykiety,
suwak) kosztuje **34 ms** — 207 ms wobec 173 ms dla tej samej klatki z
wyłączonym rysowaniem ramek. Reszta to rasteryzacja tekstu: klatka bez
wierszy listy trwa 90 ms, z trzydziestoma — 209 ms, czyli ~4 ms na wiersz
o 158 znakach.

**Wniosek, który przesądził o palecie:** przy 16 i 32 kolorach kwantyzator
poświęca odcień obwódki (`#2e323a`, o stopień jaśniejszy od tła) na rzecz
liczniejszych pikseli tekstu — panele znikają z ekranu, zostają same
nawiasy narożne i etykiety. Sprawdzone na renderach, nie wywnioskowane.

### Weryfikacja

- 335 testów (74 nowe), PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag.
- Wygląd oglądany na renderach PNG klatki tuż po kwantyzacji (kopia płótna
  zapisywana przed konwersją na Sixel), w powiększeniu 3× dla narożników.
- **Sprawdzone pod XTermem** (`bin/run.sh`, okno 100×30, DejaVu Sans Mono 14,
  płótno 1104×694): cztery panele z zaokrągloną obwódką, nawiasy narożne,
  etykiety `PATH`/`FILES`/`PREVIEW`, pasek zaznaczenia z krawędzią akcentu,
  podpowiedzi klawiszy dosunięte do prawej. Zrzut wykonany przez
  `ffmpeg -f x11grab` (`xwd` odbija się od root window błędem `BadColor`).

Wygląd poprawiany pięciokrotnie, za każdym razem po obejrzeniu klatki:

1. **Etykieta stref stała zbyt blisko nawiasu narożnego** — czytały się jak
   jeden element („— PATH”). Stąd stała `LABEL_COLUMN`, odsuwająca podpis o
   siedem kolumn od krawędzi panelu.
2. **Przy paletach 16 i 32 znikały obwódki** — patrz tabela pomiarów.
3. **Pod XTermem panele nadal wyglądały jak tryb tekstowy**: obwódka
   `#2e323a` przy grubości jednego piksela była praktycznie niewidoczna, a
   bez wygładzania obrysu łuki wychodziły schodkami. Poprawka: rozjaśnienie
   obwódki i zaznaczenia oraz włączenie wygładzania obrysów (koszt
   zmierzony osobno — patrz wyżej).
4. **Narożnik nie wyglądał na zaokrąglony — i słusznie.** Nawias narożny
   był rysowany jako dwa proste odcinki zaczynające się **za** promieniem,
   więc łuk między nimi zostawał obwódce, czyli tonowi, którego w terminalu
   praktycznie nie widać. Na ekranie dawało to kreskę poziomą, dziurę i
   kreskę pionową. Poprawka: nawias rysowany jako ścieżka
   (`pathEllipticArcAbsolute`) — noga, **łuk**, noga — grubością 2 px, więc
   to akcent niesie kształt rogu. Promień jest przy okazji ograniczany do
   połowy krótszego boku, żeby łuki nie zachodziły na siebie w niskich
   panelach.
5. **Komunikat nachodził na podpowiedzi klawiszy** w pasku stanu. Teraz
   podpowiedzi ustępują: rysowane są tylko wtedy, gdy zostaje dla nich
   miejsce na prawo od komunikatu.

## Kryteria ukończenia

- Żaden literał `#rrggbb` ani kod ANSI nie zostaje w kodzie rysującym —
  wszystkie kolory pochodzą z motywu.
- Wysokość listy liczona jest w jednym miejscu; zmiana grubości ramek nie
  wymaga poprawki w `Application`.
- Klatka w trybie Sixel pokazuje cztery strefy z obwódkami, nawiasami
  narożnymi, etykietami, paskiem przewijania i nowym zaznaczeniem.
- Tryb tekstowy używa tych samych ról i etykiet, degraduje się do ośmiu
  kolorów, gdy terminal nie deklaruje 256.
- Pomiar palety i wygładzania wykonany, liczby zapisane w tym pliku,
  wybrany próg uzasadniony. ✔
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone. ✔
- Wygląd sprawdzony pod XTermem (zgodnie z zasobami z D22), zrzut opisany
  w dzienniku realizacji. ✔

## Dziennik realizacji

- **2026-08-08** — Ukończony. Motyw Grafit z nazwanymi rolami, układ „HUD”
  z czterema panelami, pasek przewijania, trzy tony komunikatu i uproszczony
  wariant trybu tekstowego. Powstało 12 nowych klas (3 w `Domain`, 3 w
  `Application`, 4 w `Infrastructure`, 1 wsparcie testowe), przepisane
  zostały oba renderery i przypadek użycia składający klatkę. 74 nowe testy
  (łącznie 335), PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag. Czas
  klatki w konfiguracji domyślnej: **184 ms, blob 23,1 kB** (płótno
  1000×600, pełna lista).

  **Decyzje użytkownika podjęte na starcie kroku** — zobacz sekcję
  „Rozstrzygnięcia ze startu kroku” oraz [00-decyzje.md](00-decyzje.md), D27.

  **Największa zmiana strukturalna:** wysokość listy przestała być liczona w
  dwóch miejscach. Przedtem `RenderCurrentFrameUseCase` miał własne stałe
  chromu, a enkoder własne założenie „nagłówek to wiersz 0”; zgodności
  pilnował komentarz. Teraz jeden port (`FrameLayoutPort`) oddaje ten sam
  podział obu stronom.

  **Czego nauczył pomiar:** dwie liczby zmieniły decyzje w trakcie kroku.
  Po pierwsze, paleta 16 i 32 kolorów zjada odcień obwódki — panele znikają
  z ekranu. Po drugie, koszt wygładzania, przedstawiony najpierw łącznie
  (33 ms), okazał się po rozdzieleniu prawie w całości kosztem **tekstu**;
  wygładzanie obrysów jest darmowe, a bez niego zaokrąglenia nie ma wcale.

  **Znane ograniczenia:** klatka nadal nie reaguje na zmianę rozmiaru okna
  (ograniczenie z kroku 08); pole `Preview::rows` stało się nadmiarowe,
  odkąd wysokość pasa podaje układ — do rozważenia przy okazji kroku 14;
  tryb tekstowy nie pokazuje paska przewijania (świadome uproszczenie);
  rasteryzacja tekstu to ~4 ms na wiersz i przy pełnym ekranie zjada 60%
  czasu klatki — pętla o stałym takcie 20 kl./s (D19) nadal jest
  nieosiągalna, realnie wychodzi ~5 kl./s.
