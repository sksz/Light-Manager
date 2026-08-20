# 2. Instalacja i pierwsze uruchomienie

> Podręcznik użytkownika, część 2 z 7. [Spis](README.md) ·
> [English](../../en/manual/02-installation.md)

## Czego potrzebujesz

| Rzecz | Wymagana? | Bez niej |
|---|---|---|
| PHP `^8.3` | **tak** | aplikacja nie startuje |
| Rozszerzenia `imagick` i `pcntl` | **tak** | aplikacja nie startuje |
| Polecenie `stty` | **tak** | terminala nie da się przełączyć w tryb surowy (stąd: Linux albo macOS, nie Windows) |
| Composer 2.x | **tak** | nie ma czym zainstalować zależności |
| ImageMagick z koderem `SIXEL` | zalecana | aplikacja startuje w torze tekstowym |
| Terminal umiejący Sixel | zalecany | jak wyżej |
| Rozszerzenie `glfw` | opcjonalna | brak trybu `--window` i brak dźwięku |
| Klient OpenSSH | opcjonalna | moduł sesji zdalnej znika ze spisu wraz z powodem |
| Rozszerzenie `curl` | opcjonalna | moduł Dockera znika ze spisu wraz z powodem |
| `kubectl` | opcjonalna | moduł Kubernetesa nie ma z czym rozmawiać |
| Rozszerzenie `intl` | opcjonalna | gorsze sortowanie i formatowanie liczb |

**Brak rzeczy opcjonalnej jest degradacją, nie awarią.** Aplikacja startuje,
mówi w pasku stanu, czego zabrakło, i działa dalej bez tej części.

## Instalacja

```bash
make check-env   # czy ta maszyna udźwignie projekt — działa przed instalacją
make install     # composer install; powtórzony nie robi nic
```

`make check-env` rozróżnia trzy rodzaje wymogów: **twarde** kończą się kodem
błędu, brak kodera `SIXEL` jest **ostrzeżeniem**, a `glfw`, `intl` i `xterm` —
informacją. Jednej rzeczy sprawdzić nie potrafi i mówi to wprost: **czy sam
terminal umie Sixel**, bo odpowiedź na to pytanie wymaga interaktywnej sesji
w trybie surowym. Od tego jest `make probe`.

Jeżeli Composer wywraca się naruszeniem ochrony pamięci — zobacz „Gdy coś nie
działa”, na końcu tego rozdziału.

## Pierwsze uruchomienie

```bash
make run          # to samo co ./bin/light-manager
make run-window   # tryb okienkowy (--window)
make run-xterm    # XTerm z kompletem zasobów trybu graficznego
```

Aplikacja przechodzi na osobny ekran, rysuje klatkę i czeka na wejście.
**Wyjście: `F10`** — albo `Ctrl`+`C`; w obu przypadkach terminal wraca do stanu
sprzed uruchomienia.

Powinieneś zobaczyć listę plików katalogu domowego, ścieżkę w górnym pasie
i pasek stanu z klawiszami. Jeśli tak jest — przejdź do
[rozdziału 3](03-ekran-i-sterowanie.md).

### Skąd wiadomo, w którym torze jesteś

`F1`, zakładka **Aplikacja**. Jeśli spodziewałeś się obrazu, a widzisz znaki,
tor jest tekstowy — powód jest zawsze jeden z trzech: terminal nie umie Sixela,
ImageMagick nie ma kodera, albo odpowiedź terminala nie dotarła (multiplekser).

## Tryb okienkowy

```bash
./bin/light-manager --window
```

Zamiast rysować w terminalu aplikacja otwiera **natywne okno** z kontekstem
OpenGL. Terminal, z którego padło polecenie, zostaje nietknięty. Klawiatura
działa tym samym słownikiem, `F10` kończy, a przeciągnięcie rogu okna zmienia
siatkę od następnej klatki.

Rozmiar okna ustawia się na ekranie ustawień (pozycje „Kolumny/Wiersze okna”,
domyślnie 100×30 komórek). **Okno pamięta, jak je ustawiono**: rozmiar nadany
przeciągnięciem albo maksymalizacją zapisuje się pół sekundy po ostatniej
zmianie, więc następny start zastaje okno takim, jakim je zostawiłeś. Mierzy się
go **w komórkach**, więc zmiana fontu zmieni okno w pikselach, a siatkę zostawi.

**Pełny ekran**: `F11` albo komenda `core.fullscreen`. Obie drogi istnieją
wyłącznie w tym trybie — w terminalu `F11` nie robi nic i nie ma go w spisie
klawiszy. Rozmiar narzucony pełnym ekranem **nie** trafia do ustawień.

**Ikona na pasku zadań** wymaga jednorazowego `./bin/install-desktop-entry`.
Droga jest okrężna, bo prostej nie ma: rozszerzenie PHP-GLFW nie wystawia
`glfwSetWindowIcon`, więc okno przedstawia się pulpitowi klasą `WM_CLASS`,
a ikonę bierze pulpit z wpisu.

## XTerm — trzy zasoby, każdy z innego powodu

Najprościej: **`make run-xterm`**, które podaje komplet samo. Ręcznie —
w `~/.Xresources`, a potem `xrdb -merge ~/.Xresources`:

```
XTerm*decTerminalID: 340
XTerm*maxGraphicSize: 4000x4000
XTerm*metaSendsEscape: true
XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop
```

| Zasób | Bez niego |
|---|---|
| `decTerminalID: 340` | XTerm nie zgłasza Sixela i aplikacja schodzi do toru tekstowego |
| `maxGraphicSize: 4000x4000` | klatka większa niż limit **nie rysuje się w ogóle**; okno 200×50 już limit przekracza |
| `metaSendsEscape: true` | `Alt`+litera nie dochodzi do aplikacji (`Alt`+`c` przychodzi jako `ã`) |
| `disallowedWindowOps` bez `14` | aplikacja musi zgadywać rozmiar komórki i zostawia margines przy krawędzi |
| `disallowedWindowOps` bez `GetSelection`/`SetSelection` | schowek nie działa |

Inne emulatory (WezTerm, foot, mlterm) nie wymagają niczego.

## Gdy coś nie działa

Sekcja pisana **objawem**, bo objaw jest tym, co widzisz.

### Widzę znaki zamiast obrazu

Tor tekstowy zamiast sixelowego. Trzy możliwe powody:

1. **Terminal nie umie Sixela.** `gnome-terminal` nie nadaje się i nie da się
   tego naprawić konfiguracją — VTE usunęło obsługę Sixela z gałęzi stabilnej
   w 0.75.90; klucz `enable-sixel` w profilu jest bezczynny. Użyj XTerma
   (`-ti vt340`), WezTerma, foota albo mltermu.
2. **Jesteś w multiplekserze.** `tmux` i `screen` potrafią odfiltrować
   odpowiedź terminala na pytanie o możliwości (aplikacja czeka na nią 300 ms).
   Uruchom poza multiplekserem.
3. **ImageMagick nie ma kodera `SIXEL`.** Powie o tym `make check-env`.

Sprawdzić, co odpowiada twój terminal, można poleceniem **`make probe`**.

### Klatka jest ucięta albo pusta pod XTermem

`maxGraphicSize` — zobacz tabelę wyżej. Domyślne `1000x1000` nie wystarcza już
dla okna 200×50.

### `Alt`+`c` i `Alt`+`v` nie działają

Pod XTermem: brak `metaSendsEscape: true`. W innych terminalach: odczyt schowka
(OSC 52) bywa domyślnie wyłączony — poszukaj ustawienia „allow OSC 52 clipboard
read”. Aplikacja czyta schowek wyłącznie po `Alt`+`v` albo komendzie
`core.clipboard.paste`, nigdy przy starcie i nigdy w tle.

Gdy terminal **milczy** zamiast odmówić, `Alt`+`v` kończy się po ćwierć sekundy
zdaniem „Ten terminal nie oddaje zawartości schowka”. W trybie `--window`
pytanie nie zachodzi.

### `--window` nie startuje

Brak rozszerzenia `glfw`. Bez flagi `--window` rozszerzenie **nie jest potrzebne
w ogóle** — tory terminalowe działają bez niego.

### Modułu nie ma na liście

To jest zachowanie zamierzone, a powód stoi w pasku stanu i na zakładce
„Moduły” w ustawieniach. Moduł odpada, gdy: brakuje mu czegoś w środowisku
(klient OpenSSH, rozszerzenie `curl`), jego skrót koliduje z innym modułem,
jego identyfikator się powtarza, albo wyłączyłeś go sam.

### Muzyka milczy

Silnik audio pochodzi z rozszerzenia `glfw` — bez niego komendy muzyczne
odpowiadają zdaniem o niedostępności. Okna nie potrzebuje: muzyka gra także
w obu torach terminalowych.

### Composer kończy się naruszeniem ochrony pamięci

Zdarza się przy równoległym pobieraniu paczek, gdy załadowane są `imagick`
i `openswoole`. Obejście:

```bash
make install-safe COMPOSER_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick
```

Dotyczy **wyłącznie Composera** — uruchomienie aplikacji wymaga `imagick`
włączonego normalnie.

### Terminal został w trybie surowym

Zdarza się wyłącznie po `kill -9`, którego nie da się przechwycić. Napraw:

```bash
stty sane
```

Po `F10`, `Ctrl`+`C` i po każdym innym sygnale terminal wraca sam.

## Dokąd dalej

- [3. Ekran i sterowanie](03-ekran-i-sterowanie.md) — co nacisnąć
- [7. Scenariusze](07-scenariusze.md) — pierwsze zadanie od początku do końca
