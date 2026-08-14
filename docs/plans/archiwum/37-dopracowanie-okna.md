# Krok 37 — Dopracowanie okna: rozmiar, pełny ekran, ikona, skala treści

> **Skąd ten krok.** Powstał 2026-08-12, na polecenie użytkownika (D57).
> Zbiera cztery rzeczy świadomie wykluczone z kroków 34 i 35 adnotacją
> „kosmetyka okna poza tytułem” i „osobna decyzja, jeśli w ogóle”. Żadna
> z nich nie jest mechanizmem — okno działa bez nich w całości — ale razem
> składają się na różnicę między oknem, które działa, a oknem, które zachowuje
> się jak reszta pulpitu.

## Status

**Ukończony z zastrzeżeniem** (2026-08-13). Trzy pozycje z czterech dowiezione
w całości i sprawdzone uruchomieniem. Czwarta — skala treści — świadomie
**tylko odczytana i pokazana**, bo maszyna projektu ma skalę 1.0 (rozstrzygnięcie
nr 4, D67); ikona idzie ponadto drogą okrężną, bo rozszerzenie nie wystawia
funkcji, na której plan ją opierał.

## Cel

Okno trybu okienkowego **pamięta, jak je ustawiono**, umie zająć cały ekran,
ma własną ikonę i nie kłamie na wyświetlaczu o gęstości innej niż 1.0.

Miarą powodzenia jest zdanie: **użytkownik ustawia okno raz, a przy następnym
starcie zastaje je takim, jakim je zostawił** — reszta kroku to dopełnienie
tego samego wrażenia.

Trzy z czterech pozycji nie mają prawa dotknąć ani pętli, ani rendererów, ani
komponentów; czwarta (skala treści) dotyka wyłącznie przeliczenia komórki
w `Infrastructure/Glfw`. Jeśli którakolwiek wymaga zmiany w `Application`,
to znak, że stoi źle.

## Zależności

- **Krok 34** (okno GLFW) — twardo: stamtąd pochodzi `GlfwWindowService`
  wraz z cyklem życia okna, klucze `windowColumns`/`windowRows` i reguła
  „okno rodzi się ukryte, pokazuje się zwymiarowane”.
- **Krok 35** (renderer OpenGL) — komórka z metryk fontu (`VgContextService`),
  bez której skala treści nie ma czego przeliczać, oraz `GlfwViewportService`
  czytający framebuffer co pytanie.
- **Krok 14** (konfiguracja) — zapamiętany rozmiar to klucze ustawień, nie
  plik obok; zapis idzie tą samą drogą, co każda inna zmiana ustawienia.
- **Krok 19** (okno komend) — przełącznik pełnego ekranu ma być komendą,
  a nie nowym skrótem globalnym (do potwierdzenia: rozstrzygnięcie nr 2).

Od Fazy VII i kroku 36 krok **nie zależy** i one nie zależą od niego.

## Model i wysiłek

**Opus / medium.**

Kodu mało i cały w `Infrastructure/Glfw` plus kilka kluczy ustawień. Ciężar
leży w dwóch miejscach: zapis rozmiaru **nie może pisać do pliku przy każdej
klatce przeciągania rogu** (a zdarzenia zmiany rozmiaru sypią się dziesiątkami
na sekundę), oraz pełny ekran musi umieć wrócić dokładnie tam, skąd wyszedł.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Infrastructure/Glfw/GlfwWindowService` | `showAtGrid()`, `resizeContent()`, `framebufferSize()`, `shouldClose()`, `close()`; okno rodzi się ukryte, hint `GLFW_VISIBLE` |
| `Infrastructure/Glfw/GlfwViewportService` | Bez stanu — dzieli framebuffer przez komórkę przy każdym pytaniu; **skala treści musiałaby wejść do komórki, nie tutaj** |
| `Infrastructure/Glfw/VgContextService` | Komórka z metryk fontu przy jednym rozmiarze pisma (`BASE_FONT_SIZE`) — miejsce, w którym skala treści ma sens |
| `Application/Dto/SettingKey`, `Settings` | Klucze `windowColumns`/`windowRows` z listami dopuszczalnych wartości; zapamiętany rozmiar dowolny wymagałby innej walidacji (rozstrzygnięcie nr 1) |
| `Presentation/Cli/Bootstrap` | Tor okienkowy: ustawienia → okno → kontekst → `showAtGrid()` → wejście |
| GLFW | `glfwSetWindowMonitor`, `glfwGetPrimaryMonitor`, `glfwGetVideoMode`, `glfwSetWindowIcon`, `glfwGetWindowContentScale`, `glfwSetWindowContentScaleCallback` — wszystkie obecne (sprawdzone przy kroku 34) |
| Środowisko | Skala treści na maszynie projektu wynosi **1.0** — czwartej pozycji nie da się tu sprawdzić inaczej niż sztucznie (patrz „Zakres”, punkt 4) |

## Zakres

### 1. Zapamiętywanie rozmiaru okna

Rozmiar ustawiony przez użytkownika (przeciągnięciem rogu albo maksymalizacją)
wraca przy następnym starcie. Trzy rzeczy do rozstrzygnięcia i wszystkie mają
konsekwencje poza samym zapisem:

- **W czym mierzyć** — w komórkach (spójne z dzisiejszymi kluczami, ale
  po zmianie fontu okno zmieni rozmiar w pikselach) czy w pikselach (wierne
  co do piksela, ale klucze przestają być listą wartości do przełączania
  strzałkami na ekranie ustawień) — rozstrzygnięcie nr 1.
- **Kiedy zapisywać** — zapis przy każdym zdarzeniu zmiany rozmiaru
  oznaczałby dziesiątki zapisów pliku na sekundę przy przeciąganiu rogu.
  Naturalne rozwiązania: zapis przy wyjściu z aplikacji (jedno miejsce, żadnej
  logiki czasowej) albo zapis po uspokojeniu się zmian.
- **Co z pozycją okna** — poza zakresem, chyba że rozstrzygnięcie nr 1 każe
  inaczej: zapamiętany rozmiar bez pozycji jest zachowaniem większości
  aplikacji, a pozycja wchodzi w spór z menedżerem okien.

### 2. Pełny ekran

Przełącznik pełnego ekranu przez `glfwSetWindowMonitor`. Wyjście z pełnego
ekranu ma wrócić **dokładnie** do poprzedniego rozmiaru i położenia, więc
usługa okna musi je zapamiętać na czas pełnego ekranu — to jedyny stan, jaki
ten krok dokłada do `GlfwWindowService`.

Sterowanie: komenda w `CommandRegistry` (wzorem kroku 36 i reguły „okno komend
już umie wszystko, czego tu trzeba”) albo skrót globalny, jeśli
rozstrzygnięcie nr 2 tak każe. Skrót globalny jest jednak drogi: wiązania
rdzenia są wspólne dla wszystkich trybów, a pełny ekran nie znaczy nic
w terminalu.

### 3. Ikona okna

`glfwSetWindowIcon` przyjmuje bitmapę w pamięci. Źródło ikony —
rozstrzygnięcie nr 3: plik PNG w repozytorium (trzeba go narysować, waży
kilka kilobajtów, jest jeden dla wszystkich rozmiarów) albo **generowanie
w kodzie** z ról motywu (zero plików binarnych w repozytorium, ikona zmienia
się razem z motywem, ale rysowanie ikony to kod, który nie ma nic wspólnego
z resztą aplikacji).

### 4. Skala treści (HiDPI)

`glfwGetWindowContentScale` mówi, ile pikseli fizycznych przypada na piksel
logiczny. Dziś komórka liczy się z metryk fontu przy stałym rozmiarze pisma,
więc na wyświetlaczu o skali 2.0 tekst byłby dwa razy mniejszy, niż być
powinien — a klatka i tak wypełniałaby okno, bo `GlfwViewportService` czyta
**framebuffer**, nie rozmiar okna (to akurat krok 34 zrobił dobrze).

**Zastrzeżenie, które musi paść przed pierwszą linią kodu:** maszyna projektu
ma skalę 1.0, więc tej pozycji **nie da się tu rzetelnie sprawdzić**. Kod
napisany na ślepo byłby zakładem, a nie krokiem. Do rozstrzygnięcia nr 4: czy
pisać go mimo to (z jawną adnotacją „niesprawdzone na sprzęcie”), czy
ograniczyć się do odczytania skali i **zgłoszenia jej w oknie pomocy**, żeby
użytkownik na innym sprzęcie mógł powiedzieć, co widzi.

## Poza zakresem

- **Pozycja okna** (zapamiętywanie i przywracanie) — patrz punkt 1.
- **Wiele monitorów** — wybór monitora dla pełnego ekranu bierze podstawowy;
  reszta to osobna sprawa, jeśli w ogóle.
- **Kursor myszy, kształt kursora, chowanie kursora** — mysz jest poza
  zakresem całej Fazy IX (D52) i ten krok tego nie zmienia.
- **Ramka i dekoracje okna** (`GLFW_DECORATED`) — okno bez ramki nie ma
  odbiorcy.
- **Zmiany w pętli, rendererach i komponentach** — jeśli krok czegoś tam
  wymaga, to znak, że stoi źle.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Infrastructure/Glfw/GlfwWindowService.php` | Infrastructure | Pełny ekran wraz z zapamiętanym rozmiarem, ikona, odczyt skali treści. |
| `Infrastructure/Glfw/VgContextService.php` | Infrastructure | Komórka mnożona przez skalę treści (zależnie od rozstrzygnięcia nr 4). |
| `Application/Dto/SettingKey.php`, `Settings.php` | Application | Klucze zapamiętanego rozmiaru (postać wg rozstrzygnięcia nr 1). |
| `Presentation/Cli/Bootstrap.php` | Presentation | Zapis rozmiaru przy wyjściu (wg rozstrzygnięcia nr 1). |
| `Presentation/Cli/Command/…` | Presentation | Komenda pełnego ekranu (wg rozstrzygnięcia nr 2). |
| `lang/pl.php`, `lang/en.php` | Napisy | Etykieta komendy, ewentualny wiersz skali w pomocy. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Zapamiętany rozmiar jako ustawienie, granice kroku. |
| testy | Testy | Przeliczenie rozmiaru w obie strony, powrót z pełnego ekranu do poprzedniego rozmiaru, skala treści w komórce. |

## Do rozstrzygnięcia na starcie kroku

1. **W czym zapamiętywać rozmiar** (komórki czy piksele) i **kiedy zapisywać**
   (przy wyjściu czy po uspokojeniu zmian) — patrz punkt 1.
2. **Czym przełączać pełny ekran** — komendą czy skrótem globalnym; skrót
   globalny obowiązywałby także tryby terminalowe, w których nie znaczy nic.
3. **Skąd ikona** — plik w repozytorium czy generowanie z ról motywu.
4. **Co ze skalą treści**, skoro nie ma jej na czym sprawdzić — pisać na ślepo
   z adnotacją, czy poprzestać na odczycie i pokazaniu wartości.

## Kryteria ukończenia

- Zmiana rozmiaru okna przeżywa restart aplikacji — potwierdzone
  uruchomieniem, nie samym testem.
- Pełny ekran włącza się i wyłącza, a wyjście wraca do poprzedniego rozmiaru
  co do piksela.
- Okno ma ikonę widoczną na pasku zadań.
- Skala treści: wedle rozstrzygnięcia nr 4 — albo przeliczona komórka, albo
  wartość widoczna w pomocy; w obu wypadkach jawna adnotacja o tym, czego nie
  dało się sprawdzić.
- Tryby terminalowe bez zmian — `bin/render-bench --compare` bez regresji
  w obu torach.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-13 — wykonanie kroku

**Rozstrzygnięcia startowe:** cztery pytania zadane przed pierwszą linią kodu,
odpowiedzi i uzasadnienia w [00-decyzje.md](../00-decyzje.md), D67. W skrócie:
rozmiar w komórkach zapisywany po uspokojeniu zmian; pełny ekran **komendą
i skrótem `F11` naraz**, ale wyłącznie w torze okienkowym; ikona drogą
`WM_CLASS` + wpis `.desktop`, rysowana z ról motywu; skala treści czytana
i pokazywana, nie stosowana.

**Sprostowanie „Stanu zastanego”.** Tabela na początku tego pliku wymieniała
`glfwSetWindowIcon` wśród funkcji obecnych. **W PHP-GLFW 2.2.0 tej funkcji nie
ma w ogóle** — jest wyłącznie `glfwIconifyWindow` (minimalizacja) i to ona
zapewne była źródłem pomyłki. Pozostałe funkcje z tamtej tabeli są obecne.
Rozstrzygnięcie nr 3 zostało więc zadane inaczej, niż przewidywał plan: nie
„skąd wziąć bitmapę”, tylko „czy jest dokąd ją podać”.

**Co powstało:**

| Plik | Zmiana |
|---|---|
| `Application/Dto/Settings.php` | Granice `WINDOW_*_MIN`/`MAX`, `allowsWindow*()`, `nextStop()` — strzałka z wartości spoza listy idzie do sąsiada w swoją stronę |
| `Application/Dto/SettingKey.php` | Opis kluczy: rozmiar startowy **i zarazem** pamięć rozmiaru |
| `Infrastructure/Config/SettingsService.php` | Rozmiar okna sprawdzany **zakresem, nie listą** |
| `Infrastructure/Glfw/WindowSizeSettle.php` | **Nowy.** Czekanie na ciszę po zmianach rozmiaru; czysty, bez GLFW, zegar z zewnątrz |
| `Infrastructure/Glfw/GlfwWindowService.php` | `rememberSize()`, `afterPollEvents()`, `saveSizeIfPending()`, `toggleFullscreen()`, `contentScale()`, podpowiedzi `GLFW_X11_CLASS_NAME`/`INSTANCE_NAME` |
| `Infrastructure/Glfw/GlfwInputService.php` | Jedna linia: czynności okna raz na takt, zaraz po `glfwPollEvents()` |
| `Infrastructure/Desktop/*` | **Nowe.** `DesktopEntryInstaller` (ikona z ról motywu w czterech rozmiarach + wpis `.desktop`), `DesktopException`, `DesktopFailure` |
| `bin/install-desktop-entry` | **Nowy.** Jedyna droga uruchomienia powyższego — nigdy z pętli |
| `Presentation/Cli/Command/FullscreenCommand.php` | **Nowa.** `core.fullscreen`; przełączenie przychodzi domknięciem, więc komenda nie zna `Infrastructure/Glfw` |
| `Presentation/Cli/InputHandler.php` | `globalBindings(bool $windowed)` i obsługa `F11` przez domknięcie (`null` w terminalu) |
| `Presentation/Cli/Bootstrap.php` | `rememberSize()` za pokazaniem okna, `fullscreenToggle()`, `contentScale()`, zapis rozmiaru w `shutdown()` |
| `Presentation/Cli/Screen/HelpScreen.php` | Wiersz gęstości wyświetlacza w zakładce „Aplikacja” — tylko gdy jest co pokazać |
| `lang/pl.php`, `lang/en.php` | Komenda i jej dwa komunikaty, `help.key.fullscreen`, `help.about.scale`, siedem napisów `desktop.*` |

**Testy** (1328 zielonych, PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag):
`WindowSizeSettleTest` (cisza i jej odsuwanie — dłuższe przeciąganie rogu nie
zapisuje po drodze ani razu), `DesktopEntryInstallerTest` (ikona, rozmiary,
kolory z motywu i **zgodność `StartupWMClass` z `WM_CLASS`**),
`FullscreenCommandTest`, `HelpScreenAboutTest` (wiersz skali pojawia się i znika
razem z torem), plus przypadki dopisane do `SettingsTest`, `SettingsServiceTest`
i `InputHandlerTest`.

**Sprawdzenie na żywo** (maszyna zwolniona na prośbę, reguła 17; `DISPLAY=:1`,
okno sterowane `xdotool`, katalog domowy podmieniony na tymczasowy):

| Kryterium | Wynik |
|---|---|
| Klasa okna | `WM_CLASS(STRING) = "light-manager", "light-manager"` |
| Rozmiar startowy | 1000×630 px = 100×30 komórek z ustawień |
| Zmiana rozmiaru → ustawienia | 900×600 px zapisane jako `windowColumns: 90`, `windowRows: 28` |
| Rozmiar przeżywa restart | okno wraca jako 900×588 px = te same 90×28 komórek |
| Pełny ekran `F11` | 2560×1440 px na monitorze podstawowym |
| Powrót z pełnego ekranu | 900×600 px w położeniu 64,496 — **co do piksela i co do położenia** |
| Pełny ekran a ustawienia | niezmienione (90×28) — rozmiar monitora nie jest wyborem użytkownika |
| Zapis przy wyjściu | `F10` **natychmiast** po zmianie na 760×480 → zapisane 76×22 |
| Ikona i wpis pulpitu | cztery pliki PNG + `.desktop` zapisane, obejrzane w 256 i 48 px |
| Pomiar sixelowy | `--compare`: „Bez regresji powyżej progu” |
| Pomiar tekstowy | `--compare`: „Bez regresji powyżej progu” |

**Co wyszło dopiero z uruchomienia** (pełny zapis: D67):

1. **Powrót z pełnego ekranu nie trafiał w te same piksele.**
   `glfwSetWindowMonitor()` oddawał obszar treści niższy o pasek tytułu (900×600
   → 900×563, okno o 37 px niżej), bo menedżer okien liczy podaną geometrię jako
   geometrię **ramki**. Osobne `glfwSetWindowSize()` naprawia to w całości, ale
   **tylko po zakończeniu przejścia** — wołane w tym samym takcie nie zmienia
   nic (sprawdzone osobnym doświadczeniem poza aplikacją). Stąd
   `restoreAfterFullscreen()` dopominające się z taktu na takt, z sufitem
   sekundy. Skutek uboczny pierwszej wersji był poważniejszy niż sam rozmiar:
   pełny ekran **zapisywał zmniejszone okno do ustawień**, czyli psuł pozycję
   nr 1 tego samego kroku.
2. **Rozmiar wraca zaokrąglony w dół do pełnych komórek** (900×600 → 900×588).
   To nie usterka, tylko cena rozstrzygnięcia nr 1 wypowiedziana do końca;
   zapisana w README.
3. **Naruszenie ochrony pamięci przy wyjściu z trybu okienkowego dotyczy także
   aplikacji**, nie tylko pomiaru — README przypisywał je dotąd samemu
   `render-bench`. Sprawdzone w osobnym drzewie roboczym na kodzie **sprzed**
   tego kroku: usterka jest starsza i pada **po** całym sprzątaniu (rozmiar okna
   i historia komend są zapisane). Zapis README poszerzony.

**Odstępstwa od planu:**

- **Zmiana w `InputHandler`, której plan nie przewidywał** — wymuszona
  rozstrzygnięciem nr 2 (komenda **i** skrót naraz). Sprowadza się do trybu
  w `globalBindings()` i domknięcia w konstruktorze; `ScreenStack`,
  `ScreenInterface` i wędrówka klawisza zostają nietknięte.
- **Ikona wymagała nowego katalogu `Infrastructure/Desktop` i nowego skryptu
  w `bin/`** zamiast jednej metody w `GlfwWindowService` — bo funkcji, na której
  plan ją opierał, w rozszerzeniu nie ma. Kod rysujący ikonę nie stoi w ścieżce
  klatki i nie uruchamia go pętla.
- **`VgContextService` został nietknięty** wbrew tabeli „Planowane zmiany
  w plikach”: rozstrzygnięcie nr 4 wybrało odczyt zamiast przeliczenia.
- **Do tabeli komend w README dopisano brakujący wiersz `core.dump`** (komenda
  istnieje od kroku 38, spis jej nie wymieniał). Poza zakresem kroku, ale
  tabela deklaruje „dziś dostępne są”, a była edytowana w tym samym miejscu.

**Do „Zakresu poza MVP”:** przeliczenie komórki przez skalę treści (HiDPI) —
wchodzi, gdy znajdzie się sprzęt o gęstości innej niż 1.0; wartość odczytana
jest już widoczna w oknie pomocy. Ikona podawana oknu wprost — wchodzi, jeśli
PHP-GLFW wystawi `glfwSetWindowIcon`.
