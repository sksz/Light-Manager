# Light Manager

Menadżer plików w terminalu napisany w PHP. Cała klatka ekranu jest budowana
jako jeden obraz przez Imagick i wypychana do terminala protokołem Sixel, w
architekturze pętli głównej znanej z gier. Od kroku 34 aplikacja ma także
**tryb okienkowy** (`--window`): natywne okno z kontekstem OpenGL przez
rozszerzenie [PHP-GLFW](https://phpgl.net), bez dotykania terminala.

## Wymagania

- PHP `^8.3` (weryfikowane: 8.3.11)
- Rozszerzenia PHP: `imagick`, `pcntl`
- Opcjonalnie rozszerzenie `glfw` ([PHP-GLFW](https://phpgl.net), instalowane
  ze źródeł — nie ma go w PECL; weryfikowane: 2.2.0 z GLFW 3.3.8 pod X11) —
  wyłącznie dla trybu okienkowego `--window`; tryby terminalowe działają
  bez niego
- Zewnętrzne polecenie `stty` — stąd założenie **Linux/macOS**; Windows nie
  jest wspierany
- Opcjonalnie klient OpenSSH (`ssh`, `ssh-keyscan`, `ssh-keygen`, `sftp`;
  weryfikowane: 9.6p1) — wyłącznie dla modułu **sesji zdalnej** (`Ctrl`+`S`,
  kroki 48–49). Bez niego moduł **znika ze spisu wraz z powodem**, a reszta
  aplikacji działa jak dotąd; rozszerzenia PHP do SSH nie potrzeba żadnego
- Interaktywny terminal na standardowym wejściu — uruchomienie z potoku lub
  przekierowania z pliku kończy się czytelnym błędem
- ImageMagick z wkompilowanym koderem `SIXEL` — bez niego aplikacja startuje,
  ale zejdzie do trybu tekstowego (fallback, krok 07 planu)
- Terminal obsługujący Sixel (np. XTerm z `-ti vt340`, WezTerm, foot, mlterm) —
  wykrywanie odbywa się w runtime. **gnome-terminal odpada**: VTE nie ma
  Sixela od wersji 0.75.90 (zobacz „Znane ograniczenia”)
- Composer 2.x

## Instalacja

```bash
make check-env   # czy ta maszyna udźwignie projekt — działa przed instalacją
make install     # composer install; powtórzony nie robi nic
```

`make check-env` rozróżnia trzy rodzaje wymogów: **twarde** (PHP, `imagick`,
`pcntl`, `stty`, Composer) kończą się kodem błędu, brak kodera `SIXEL` jest
**ostrzeżeniem** (aplikacja zejdzie do trybu tekstowego), a `glfw`, `intl`
i `xterm` — informacją. Jednego sprawdzić nie potrafi i mówi to wprost:
obsługi Sixela przez sam terminal, bo odpowiedź DA1 wymaga interaktywnej sesji
w trybie surowym — od tego jest `make probe`.

Jeżeli Composer wywraca się naruszeniem ochrony pamięci, zobacz
[Znane ograniczenie środowiska](#znane-ograniczenie-środowiska) i `make install-safe`.

`make` bez argumentów wypisuje spis wszystkich wejść do procesów projektu;
kolejność pracy i opis każdego z nich:
[przewodnik, rozdz. 6](docs/pl/przewodnik/06-workflow.md).

## Uruchomienie

```bash
make run          # to samo co ./bin/light-manager
make run-window   # tryb okienkowy (--window)
make run-xterm    # XTerm z zasobami trybu graficznego
```

Aplikacja przechodzi na osobny ekran, rysuje klatkę w stałym takcie i czeka na
wejście. Wyjście: klawisz `F10` albo `Ctrl`+`C` — w obu przypadkach terminal
wraca do stanu sprzed uruchomienia.

**Dalej prowadzi podręcznik użytkownika**, a nie ten plik: tryb okienkowy,
sterowanie, mysz, schowek, operacje na plikach, moduły, ustawienia i zasoby
XTerma opisuje [docs/pl/podrecznik/](docs/pl/podrecznik/README.md)
(po angielsku: [docs/en/manual/](docs/en/manual/README.md)). Gdy coś nie
działa — [rozdział 2 podręcznika](docs/pl/podrecznik/02-instalacja.md), sekcja
„Gdy coś nie działa”.

## Dokumentacja

**Zaczynasz? Tędy: [onboarding](docs/pl/onboarding/README.md)**
([English](docs/en/onboarding/README.md)) — pięć przystanków, trzydzieści minut,
od `git clone` do własnej zmiany z zieloną bramką jakości.

Jedno wejście: **[docs/README.md](docs/README.md) — mapa dokumentacji.** Mówi,
który dokument za co odpowiada i czego w danym miejscu pisać nie wolno, jednym
zdaniem granicznym: *architektura mówi, jak jest; przewodnik — jak to zrobić;
podręcznik — jak tego użyć; dziennik — dlaczego tak wyszło.*

| Szukasz | Idź do |
|---|---|
| jak tego używać | [docs/pl/podrecznik/](docs/pl/podrecznik/README.md) · [English](docs/en/manual/README.md) |
| **jak dołożyć swoją rzecz** | [docs/pl/przewodnik/](docs/pl/przewodnik/README.md) · [English](docs/en/guide/README.md) |
| gdzie co leży w kodzie | [przewodnik, rozdz. 1](docs/pl/przewodnik/01-mapa-kodu.md) |
| kolejność procesów, bramka, testy, budowa | [przewodnik, rozdz. 6](docs/pl/przewodnik/06-workflow.md) |
| jak mierzyć wydajność | [docs/pomiary/README.md](docs/pomiary/README.md) |
| jak jest zbudowana i dlaczego tak | [docs/architecture.md](docs/architecture.md) |
| co jest zrobione, co w planie | [docs/plans/00-index.md](docs/plans/00-index.md) |
| dlaczego tak, a nie inaczej | [docs/plans/00-decyzje.md](docs/plans/00-decyzje.md) |
| co aplikacja dostała i kiedy | [CHANGELOG.md](CHANGELOG.md) |


## Znane ograniczenia

Tryb renderowania jest wykrywany raz, przy starcie: aplikacja pyta terminal
o możliwości (Primary Device Attributes) i czeka na odpowiedź do 300 ms.
Multipleksery (tmux, screen) potrafią tę odpowiedź odfiltrować — aplikacja
zejdzie wtedy do trybu tekstowego mimo terminala obsługującego Sixel.

**gnome-terminal nie nadaje się do trybu graficznego** i nie da się tego
naprawić konfiguracją. VTE usunęło obsługę Sixela z gałęzi stabilnej w wersji
0.75.90 (commit `e264c6e`, 2024-02-10, „SIXEL support is not in a releasable
state”); w 0.76 zostały same zaślepki ABI — `vte_terminal_get_enable_sixel()`
zwraca zaszyte `false`, a setter nic nie zapisuje. Klucz `enable-sixel`
w profilu gnome-terminala jest wobec tego bezczynny.

**Tor okienkowy kończy się naruszeniem ochrony pamięci przy wyjściu** — zarówno
pomiar, jak i sama aplikacja (sprawdzone w kroku 37; wcześniej znane wyłącznie
z pomiaru). Dzieje się to **po** wykonaniu całego sprzątania: wynik pomiaru jest
wypisany, a historia komend i zapamiętany rozmiar okna zapisane — sprawdzone
wyjściem `F10` tuż po zmianie rozmiaru. Usterka siedzi w sprzątaniu GLFW i jest
starsza od kroku 37 (sprawdzone na kodzie sprzed niego, w osobnym drzewie
roboczym). Kod wyjścia procesu jest przez to bezwartościowy — `./bin/render-bench
--window` nie nadaje się do bramki automatycznej, a same wyniki i pliki
pozostają kompletne.

**Gęstość wyświetlacza (HiDPI) jest odczytywana, ale nie stosowana.** Na
wyświetlaczu o skali innej niż 1.0 tekst w oknie będzie odpowiednio mniejszy,
niż być powinien — klatka wypełni okno w całości, bo rozmiar liczy się
z framebuffera, ale komórka nie rośnie razem ze skalą. Maszyna, na której
powstał krok 37, ma skalę 1.0, więc przeliczenia nie dało się na niej rzetelnie
sprawdzić, a kod pisany na ślepo byłby zakładem. Odczytaną wartość pokazuje
zakładka „Aplikacja” w oknie pomocy (`F1`) — jeśli widzisz tam coś innego niż
`1,00 × 1,00`, jest to sytuacja, której ta wersja nie obsługuje.

**Ikona okna nie ustawia się z aplikacji.** Rozszerzenie PHP-GLFW 2.2 nie
wystawia `glfwSetWindowIcon`, więc jedyną drogą jest wpis pulpitu zakładany
przez `./bin/install-desktop-entry` (zobacz [podręcznik, rozdz. 2](docs/pl/podrecznik/02-instalacja.md)).
W środowiskach,
które nie dopasowują okien po `WM_CLASS`, okno zostanie z ikoną zastępczą.

Ostatni wiersz okna zostaje w trybie graficznym pusty. To rezerwa: obraz
sięgający ostatniego wiersza wypycha ekran o wiersz w górę, bo terminal stawia
kursor pod obrazem.

Terminal jest przywracany do stanu sprzed uruchomienia na trzech ścieżkach:
przez obsługę sygnałów (SIGINT, SIGTERM, SIGHUP, SIGQUIT), przez funkcję
zamknięcia procesu (również przy niezłapanym wyjątku) i przez jawne
`restore()`. Jedynym wyjątkiem jest **SIGKILL** (`kill -9`), którego nie da
się przechwycić — po nim terminal zostaje w trybie surowym i trzeba go
naprawić poleceniem `stty sane`.

## Znane ograniczenie środowiska

Composer potrafi zakończyć się naruszeniem ochrony pamięci (SIGSEGV) przy
równoległym pobieraniu wielu paczek, gdy załadowane są rozszerzenia `imagick`
i `openswoole`. Obejście — uruchomienie Composera z ich pominięciem:

```bash
make install-safe COMPOSER_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick
```

Cel robi to samo, co wywołanie ręczne:

```bash
PHP_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick \
  composer update --ignore-platform-req=ext-imagick
```

Dotyczy wyłącznie samego Composera; uruchomienie aplikacji wymaga `imagick`
włączonego normalnie.
