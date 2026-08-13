# Menadżer plików w terminalu (Imagick + Sixel) — plan wdrożenia

## Cel

Aplikacja konsolowa w PHP realizująca prosty menadżer plików, w której cała
klatka ekranu jest rysowana jako bitmapa przez Imagick i wypychana do
terminala protokołem Sixel, w architekturze pętli głównej znanej z gier
(nieskończona pętla, wyjście przez `break`).

## Architektura w skrócie

- Nieskończona pętla główna: odczyt wejścia (nieblokujący, tryb surowy) →
  aktualizacja stanu → pełne przerysowanie klatki → powtórz. Wyjście przez
  `break` wywołany zdarzeniem wejścia (np. klawisz „q”) lub sygnałem.
- Cała klatka ekranu (tło, tekst, ramki, miniatury) budowana jest jako jeden
  obraz Imagick, konwertowany do formatu `sixel` i wypisywany na STDOUT;
  kursor jest repozycjonowany przed każdym przerysowaniem, tak by nadpisać
  poprzednią klatkę w tym samym miejscu, zamiast przewijać ekran.
- Terminal jest przełączany w tryb surowy (raw) na czas działania pętli;
  stan terminala jest gwarantowanie przywracany przy każdym wyjściu
  (normalnym i awaryjnym/sygnały).
- Możliwości terminala (obsługa Sixela) są wykrywane w czasie startu
  (zapytanie DA1); brak wsparcia → degradacja do prostego renderera
  tekstowego (fallback).
- Kod źródłowy w `src/`, ułożony wg Domain-Driven Design: `Domain` /
  `Application` / `Infrastructure` / `Presentation`, z regułą zależności
  „strzałki tylko do środka” (szczegóły: krok 01).
- Usługi spoza pętli aplikacji (dostęp do terminala, wykrywanie Sixela,
  renderowanie) są klasycznymi Singletonami (każda usługa — osobny
  Singleton), co ma dać łatwy bootstrap i wstrzykiwanie zależności bez
  centralnego kontenera (szczegóły: krok 02).

## Dokumentacja architektury (obowiązujące odniesienie od kroku 05)

Ustalenia z Fazy I (kroki 01–04) zostały utrwalone poza samymi plikami
planu:

- [docs/architecture.md](../architecture.md) — pełny, spójny dokument
  źródłowy (warstwy DDD, wzorzec Singleton, standardy PHP, konwencje).
- [.claude/skills/light-manager-conventions/SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md)
  — operacyjny skrót dla Claude Code, ładowany wg dopasowania opisu przy
  pracy nad kodem w `src/`/`tests/`.
- [CLAUDE.md](../../CLAUDE.md) — krótki, bezwarunkowo ładowany wskaźnik na
  powyższe dwa miejsca (zabezpieczenie na wypadek, gdy opis Skilla nie
  dopasuje się do nietypowego polecenia — [00-decyzje.md](00-decyzje.md), D13).

Kroki od 05 wzwyż mają się kierować tymi materiałami, nie odtwarzać ustaleń od
zera z pamięci.

## Decyzje wstępne

Pełne uzasadnienia i odrzucone alternatywy: zobacz [00-decyzje.md](00-decyzje.md).

- Zakres pierwszej iteracji: **Minimalny** (nawigacja + lista + podgląd
  miniatur obrazów + wyjście).
- Wejście: **tryb surowy, pojedyncze klawisze** (nie linia + Enter).
- Obsługa Sixela: **wykrywanie w runtime + fallback tekstowy**.
- Git: **pominięty na razie** — śledzenie postępu odbywa się w plikach planów.
- Architektura: **pełne DDD** w `src/` (encje, obiekty wartości, agregaty,
  repozytoria-interfejsy, serwisy domenowe/aplikacyjne) — D6.
- Usługi: **każda usługa to osobny Singleton** (bez centralnego kontenera)
  — D7.
- Ustalenia z kroków 01–04 trafiają do `docs/architecture.md` **oraz** do
  dedykowanego Claude Code Skill w `.claude/skills/` tego repozytorium — D8.
- Plan „architektura i styl” wstawiony na początku całej sekwencji, z
  przenumerowaniem dotychczasowych kroków — D9.

## Zasady przydziału modelu i wysiłku

- **Każdy krok (od 01):** minimalny wysiłek to **medium** — żaden krok nie
  schodzi poniżej tego progu, niezależnie od pozornej prostoty zadania.
- **Prace projektowe** (ten dokument oraz [00-decyzje.md](00-decyzje.md) —
  czyli utrzymanie samego planu i dziennika decyzji, w odróżnieniu od
  kroków, w których powstaje kod/dokumentacja merytoryczna) wykonywane są
  modelem **Sonnet 5** z wysiłkiem **Extra High**.
- Uzasadnienie i data zmiany: [00-decyzje.md](00-decyzje.md), D5.

## Kroki planu

### Faza I — Architektura i styl kodowania (prerekwizyt)

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 1 | Warstwy DDD i struktura katalogów `src/` | [01-warstwy-ddd-i-struktura-katalogow.md](01-warstwy-ddd-i-struktura-katalogow.md) | — | Opus | high | Ukończony |
| 2 | Wzorzec Singleton i bootstrap usług | [02-wzorzec-singleton-i-bootstrap.md](02-wzorzec-singleton-i-bootstrap.md) | 1 | Opus | high | Ukończony |
| 3 | Standardy stylu kodowania PHP | [03-standardy-stylu-kodowania.md](03-standardy-stylu-kodowania.md) | 1 | Sonnet | medium | Ukończony |
| 4 | Dokumentacja ustaleń: docs + Claude Code Skill | [04-dokumentacja-i-ai-skill.md](04-dokumentacja-i-ai-skill.md) | 1, 2, 3 | Sonnet | medium | Ukończony |

### Faza II — Wdrożenie aplikacji

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 5 | Szkielet projektu i wymagania | [05-szkielet-projektu.md](05-szkielet-projektu.md) | 1, 2, 3, 4 | Sonnet | medium | Ukończony |
| 6 | Terminal I/O: tryb surowy, klawisze, sygnały | [06-terminal-io.md](06-terminal-io.md) | 5 | Opus | high | Ukończony |
| 7 | Wykrywanie Sixela (DA1) + tryb fallback | [07-wykrywanie-sixel.md](07-wykrywanie-sixel.md) | 6 | Opus | high | Ukończony |
| 8 | Potok renderowania: Imagick canvas → Sixel → STDOUT | [08-render-imagick-sixel.md](08-render-imagick-sixel.md) | 5, 7 | Opus | high | Ukończony |
| 9 | Pętla główna (game loop) | [09-petla-glowna.md](09-petla-glowna.md) | 6, 8 | Sonnet | medium | Ukończony |
| 10 | Stan i nawigacja po systemie plików | [10-nawigacja-fs.md](10-nawigacja-fs.md) | 9 | Sonnet | medium | Ukończony |
| 11 | Renderowanie listy plików w klatce | [11-render-listy-plikow.md](11-render-listy-plikow.md) | 8, 10 | Sonnet | medium | Ukończony |
| 12 | Podgląd miniatur obrazów | [12-podglad-miniatur.md](12-podglad-miniatur.md) | 11 | Sonnet | medium | Ukończony |

### Faza III — Wygląd, konfiguracja i narzędzia

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 13 | Motyw graficzny: paleta Grafit i układ panelowy | [13-motyw-graficzny.md](13-motyw-graficzny.md) | 11, 12 | Opus | high | Ukończony |
| 14 | Konfiguracja aplikacji i ekran ustawień | [14-konfiguracja-i-ekran-ustawien.md](14-konfiguracja-i-ekran-ustawien.md) | 13 | Opus | high | Ukończony |
| 15 | Wielojęzyczność interfejsu | [15-wielojezycznosc.md](15-wielojezycznosc.md) | 13, 14 | Sonnet | medium | Ukończony |
| 16 | Narzędzia diagnostyczne wydajności | [16-narzedzia-diagnostyczne-wydajnosci.md](16-narzedzia-diagnostyczne-wydajnosci.md) | 13, 14 | Opus | high | Ukończony |
| 17 | Optymalizacja wydajności renderowania | [17-optymalizacja-wydajnosci.md](17-optymalizacja-wydajnosci.md) | 13, 16 | Opus | high | Ukończony |

### Faza IV — Komponenty interfejsu i rozszerzalność

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 18 | Komponenty interfejsu i płaszczyzny | [18-komponenty-i-plaszczyzny.md](18-komponenty-i-plaszczyzny.md) | 13, 14, 15, 16, 17 | Opus | high | Ukończony z zastrzeżeniem |
| 19 | Okno komend | [19-okno-komend.md](19-okno-komend.md) | 18 | Opus | xhigh | Ukończony z zastrzeżeniem |
| 20 | Moduły (plugins) | [20-moduly-plugins.md](20-moduly-plugins.md) | 14, 15, 18, 19 | Opus | high | Ukończony |
| 21 | Przeglądarka plików jako moduł domyślny | [21-przegladarka-jako-modul.md](21-przegladarka-jako-modul.md) | 20 | Opus | xhigh | Ukończony |

### Faza V — Komponenty rdzenia i rozwój modułów

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 22 | Zwijana sekcja jako komponent rdzenia | [22-zwijana-sekcja.md](22-zwijana-sekcja.md) | 18 | Opus | high | Ukończony |
| 23 | Pasek postępu z tekstem jako komponent rdzenia | [23-pasek-postepu.md](23-pasek-postepu.md) | 18 | Opus | high | Ukończony |
| 24 | Podział ekranu: dwa panele w jednym ekranie | [24-podzial-ekranu.md](24-podzial-ekranu.md) | 21 | Opus | xhigh | Ukończony |
| 25 | Pełny obraz stanu pliku w module `FileInfo` | [25-pelny-obraz-pliku.md](25-pelny-obraz-pliku.md) | 21, 22, 23, 24 | Opus | high | Ukończony z zastrzeżeniem |

### Faza VI — Praca poza klatką

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 26 | Proces tłowy jako mechanizm rdzenia | [26-proces-tlowy.md](26-proces-tlowy.md) | 25 | Opus | high | Ukończony |

### Faza VII — Rozbudowa rdzenia o nowe komponenty

Sześć komponentów wybranych 2026-08-11 z przeglądu braków rdzenia
([00-decyzje.md](00-decyzje.md), D48). Rytm: **jeden komponent — jeden krok**,
każdy z własnymi rozstrzygnięciami na starcie, własnym pomiarem „przed i po”
i własnym wpisem w dzienniku. Trzy pierwsze mają odbiorcę **już w kodzie**, trzy
kolejne dowożą go razem z komponentem.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 27 | Wiersz wielokolumnowy (`Table`) | [27-tabela-kolumn.md](27-tabela-kolumn.md) | 17, 18, 21, 24 | Opus | high | Ukończony |
| 28 | Okno potwierdzenia (`ConfirmOverlay`) | [28-okno-potwierdzenia.md](28-okno-potwierdzenia.md) | 14, 18, 19 | Opus | high | Ukończony |
| 29 | Widok tekstu (`TextView`) | [29-podglad-tekstu.md](29-podglad-tekstu.md) | 12, 18, 25 | Opus | high | Ukończony |
| 30 | Filtrowanie i podświetlenie dopasowania | [30-filtrowanie-i-podswietlenie.md](30-filtrowanie-i-podswietlenie.md) | 7, 8, 18, 19, 21, 27, 35 | Opus | xhigh | Ukończony |
| 31 | Drzewo (`TreeView`) | [31-drzewo-katalogow.md](31-drzewo-katalogow.md) | 18, 21, 22, 24, 27 | Opus | xhigh | Nie rozpoczęty |
| 32 | Menu kontekstowe (`MenuOverlay`) | [32-menu-kontekstowe.md](32-menu-kontekstowe.md) | 19, 20, 21, 28 | Opus | high | Nie rozpoczęty |

### Faza VIII — Okno terminala

Rozmiar okna przestaje być stałą uruchomienia. Krok od Fazy VII niezależny
w obie strony — wolno go zrobić przed nią, po niej albo pomiędzy
([00-decyzje.md](00-decyzje.md), D50).

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 33 | Reakcja na zmianę rozmiaru okna | [33-reakcja-na-zmiane-rozmiaru.md](33-reakcja-na-zmiane-rozmiaru.md) | 6, 9, 17, 18 | Fable | xhigh | Ukończony |

### Faza IX — Prezentacja poza terminalem: okno OpenGL (PHP-GLFW)

Prezentacja po raz pierwszy wychodzi poza terminal
([00-decyzje.md](00-decyzje.md), D52): aplikacja uruchomiona w trybie
okienkowym otwiera natywne okno przez rozszerzenie PHP-GLFW i rysuje prymitywy
wprost wywołaniami OpenGL — **bez Imagicka w ścieżce klatki**. Tryby
terminalowe (Sixel, tekst) zostają pierwszorzędne i nietknięte. Dwa kroki:
najpierw mechanizm (okno, kontekst, wejście, pętla — z klatką zastępczą),
potem treść (natywny renderer prymitywów do pełnego parytetu, z pomiarem).

Po ukończeniu obu doszedł **trzeci** ([00-decyzje.md](00-decyzje.md), D57):
faza planowana jako „mechanizm i treść” zbiera na końcu drobiazgi świadomie
z nich wykluczone — zapamiętany rozmiar, pełny ekran, ikonę i skalę treści.
Żaden z nich nie jest mechanizmem: okno działa bez nich w całości.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 34 | Okno GLFW: kontekst, wejście, pętla | [34-okno-glfw.md](34-okno-glfw.md) | 6, 7, 9, 13, 33 | Fable | xhigh | Ukończony |
| 35 | Natywny renderer prymitywów w OpenGL | [35-renderer-opengl.md](35-renderer-opengl.md) | 12, 13, 17, 18, 34 | Fable | xhigh | Ukończony |
| 37 | Dopracowanie okna: rozmiar, pełny ekran, ikona, skala | [37-dopracowanie-okna.md](37-dopracowanie-okna.md) | 14, 19, 34, 35 | Opus | medium | Nie rozpoczęty |

### Faza X — Dźwięk: odtwarzanie muzyki (`GL\Audio`)

Aplikacja dostaje dźwięk ([00-decyzje.md](00-decyzje.md), D55): moduł audio
rozszerzenia PHP-GLFW odtwarza muzykę **poza ścieżką klatki** — domyślnie
i na początek riff „Smoke On The Water”. Rozszerzenie jest w środowisku
załadowane (sprawdzone przy planowaniu); brak rozszerzenia to degradacja
z komunikatem, nie błąd. Od Fazy IX krok niezależny w obie strony — dzieli
z nią tylko rozszerzenie i stuby do analizy statycznej.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 36 | Odtwarzanie muzyki przez `GL\Audio` | [36-odtwarzanie-muzyki.md](36-odtwarzanie-muzyki.md) | 6, 9, 14, 15, 19 | Opus | high | Nie rozpoczęty |

### Faza XI — Diagnostyka i testy

Krok przekrojowy ([00-decyzje.md](00-decyzje.md), D61): aplikacja przez Fazy
IV–IX urosła szybciej niż jej miary, więc jeden krok wyrównuje zaległości
naraz — spis scenariuszy pomiarowych z powodami pominięć, oś zimnej klatki,
tor tekstowy w `bin/render-bench`, porównanie zrzutów i katalog nazwanych
przebiegów funkcjonalnych. Komponentów nie dokłada, więc rytm Fazy VII (D48)
zostaje nietknięty.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 38 | Rozbudowa diagnostyki, benchmarku i testów funkcjonalnych | [38-rozbudowa-diagnostyki-i-testow.md](38-rozbudowa-diagnostyki-i-testow.md) | 16, 17, 18, 21, 26, 30, 33, 35 | Opus | high | Nie rozpoczęty |

### Faza XII — Wejście do projektu: Makefile

Krok narzędziowy ([00-decyzje.md](00-decyzje.md), D62): wejścia do projektu są
dziś rozsypane po README (wymagania środowiska), `composer.json` (jakość
i testy) i skryptach w `bin/` (uruchomienie, pomiar) — jednego, które by je
zbierało, nie ma, a wymagania sprawdzają się dopiero uruchomieniem. Faza dokłada
`Makefile` z celami na weryfikację środowiska, instalację, bramkę jakości, testy
(jednostkowe, funkcjonalne, wydajnościowe) i budowę aplikacji Composerem —
jedyne z tych pojęć, którego projekt dotąd w ogóle nie miał. Domyka rzecz
dokumentami (D63): `docs/architecture.md`, `SKILL.md` i `CLAUDE.md` mają odtąd
wskazywać `make` oraz narzędzia repozytorium (`bin/render-bench`,
`bin/terminal-probe`) jako drogę do procesów aplikacji — bo plik, o którym
dokumenty milczą, przegrywa z nawykiem. Kodu aplikacji nie dotyka.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 39 | Makefile: środowisko, instalacja, jakość, testy, budowa | [39-makefile.md](39-makefile.md) | 4, 5, 7, 16, 34 | Opus | medium | Nie rozpoczęty |

### Dokumenty towarzyszące (praca projektowa)

| Krok | Plik | Aktualizowany | Model | Wysiłek | Status |
|------|------|----------------|-------|---------|--------|
| Dziennik decyzji i utrzymanie planu | [00-decyzje.md](00-decyzje.md) | równolegle od kroku 1 | Sonnet 5 | Extra High | W toku |

## Graf zależności

Kolejność realizacji pokrywa się z numeracją (01…39) **do kroku 26 włącznie**;
Faza VII łańcuchem już nie jest, a kroki 33–39 (Fazy VIII–XII) stoją poza nią
zupełnie (patrz opisy na końcu tej listy). Poza prostym
łańcuchem `01→02→…→26` istnieją węzły zbiegające się z dwóch gałęzi:

- **04** (dokumentacja + Skill) zależy od **01, 02 i 03** — potrzebuje
  kompletnej treści całej Fazy I.
- **05** (szkielet projektu) zależy od **całej Fazy I (01–04)** — szkielet
  ma bezpośrednio zastosować ustaloną strukturę katalogów, wzorzec
  Singleton i standardy stylu.
- **08** (potok renderowania) zależy zarówno od **05** (szkielet), jak i od
  **07** (wynik wykrywania trybu renderowania).
- **11** (render listy plików) zależy zarówno od **08** (potok
  renderowania), jak i od **10** (stan nawigacji).
- **13** (motyw graficzny) zależy od **11 i 12** — przebudowuje wygląd
  klatki, więc potrzebuje kompletnej treści: listy i pasa podglądu.
- **14** (konfiguracja) zależy od **13** — bez motywu z nazwanymi rolami
  nie ma czego przełączać, a przełączniki `TEXT_ANTIALIAS`,
  `STROKE_ANTIALIAS` i `PALETTE_COLORS` czekają na wartości dobrane
  pomiarami kroku 13.
- **15** (wielojęzyczność) zależy od **13** (to on wprowadził napisy po
  angielsku obok polskich) i od **14**, jeśli wybór języka ma być trwały.
- **16** (narzędzia diagnostyczne) zależy od **13** (potok renderowania w
  obecnym kształcie) i od **14** (ustawienia renderowania jako obiekt, którym
  narzędzie steruje z linii poleceń). Od **15** nie zależy — stoi za nim
  wyłącznie w kolejce.
- **17** (optymalizacja) zależy od **16** twardo: każda dźwignia wymaga
  rozliczenia „przed i po”, a bez narzędzia pomiarowego takie rozliczenie
  sprowadza się do wrażeń.
- **18** (komponenty i płaszczyzny) zależy od **13** (komponent rysuje się
  rolami motywu), od **14 i 15** (ekran ustawień jest pierwszym prawdziwym
  użytkownikiem zakładek i pól wyboru, a ich etykiety idą przez katalog
  napisów), od **16** twardo (przebudowa potoku rysowania bez pomiaru „przed
  i po” byłaby zakładem) oraz od **17** twardo (komponenty wchodzą **na**
  segmentowy `FrameLine` i pamięci podręczne z D34, nie obok nich).
- **19** (okno komend) zależy wyłącznie od **18**, za to twardo: okno jest
  w całości złożone z tego, co tam powstało — lista podpowiedzi, obwódka,
  kontrakt komponentu i wiązania klawiszy jako źródło opisu klawiszy. Wraca za
  to do kodu kroku **06**: pole tekstowe jest pierwszym, które musi odróżnić
  literę od bajtu sterującego, więc `Ctrl` powstaje tutaj (D39, P17), a nie
  w kroku 20.
- **20** (moduły) zależy od **14** (jest do czego dokładać zakładkę i gdzie
  zapisać ustawienia modułu), od **15** (etykiety zakładek i treść pomocy to
  napisy widoczne dla użytkownika — mają od razu iść przez katalog napisów),
  od **18** (ekran modułu jest `ScreenInterface`, a jego treść składa się
  z komponentów — jedno i drugie powstało tam) oraz od **19**, i to
  **podwójnie**: stamtąd pochodzi kontrakt komendy, którego potrzebuje zdolność
  `ProvidesCommands`, i komponent `TextInput`, bez którego nie ma pozycji
  tekstowej w zakładce ustawień modułu (D38). Od **16** i **17** nie zależy —
  stoi za nimi wyłącznie w kolejce. Wraca za to do kodu kroku **06**:
  modułowy skrót `Ctrl`+litera wymaga, by warstwa wejścia poznała `Ctrl`.
- **21** (przeglądarka jako moduł domyślny) zależy wyłącznie od **20**, za to
  całkowicie: bierze kontrakt modułu takim, jaki tam powstanie, i **nie dokłada
  do niego niczego** — to jest jego sprawdzian. Wraca za to do kodu kroków
  **13** (`HudLayout` pyta ekran o dwie strefy zamiast o jedną flagę), **18**
  (`ScreenInterface` po raz pierwszy zmienia kształt) i **14** (klucz
  `showHiddenEntries` wychodzi z rdzenia do ustawień modułu).

- **22** (zwijana sekcja) i **23** (pasek postępu) zależą od **18** — stamtąd
  pochodzi `ComponentInterface`, `ListView` i reguła, że stan przeżywający klatkę
  mieszka **obok** komponentu, a nie w nim (`ScrollWindow`). Od siebie nawzajem
  ani od kroków 20–21 **nie zależą**: są komponentami rdzenia i o modułach nie
  wiedzą. Stoją za nimi wyłącznie w kolejce.
- **24** (podział ekranu) zależy od **21**, ale **nie tak, jak zakładano przy
  planowaniu**. Rozstrzygnięcie użytkownika ze startu kroku (D45) postawiło podział
  **wewnątrz ekranu**, a nie nad nim: widoczny ekran jest nadal jeden, więc
  `ScreenStack`, `ScreenInterface` i `InputHandler` zostały nietknięte. Wykluczenie
  z kroku 21 („dwa moduły widoczne naraz”) **zostaje w mocy** — obydwa panele
  należą do tego samego modułu. Z kroku 21 pochodzi za to zasada, która o kształcie
  tego kroku przesądziła: moduł sam składa swój interfejs z komponentów rdzenia.
- **25** (pełny obraz stanu pliku) zależy od **21** z dwóch powodów naraz.
  Pierwszy jest formalny: `file-info.jump` przeniosła się tam do przeglądarki,
  więc dopiero po kroku 21 wiadomo, co modułowi `FileInfo` zostaje. Drugi jest
  istotny: krok 21 jest sprawdzianem kontraktu modułu na **głównej funkcji
  aplikacji**, a rozbudowywać `FileInfo` warto dopiero na kontrakcie, który ten
  sprawdzian przeszedł. Zależy ponadto od **22, 23 i 24** — obraz stanu pliku
  składa się ze zwijanych sekcji, a `du` i `sha256` mówią o sobie paskiem postępu
  (D43). Odbiorca nie może wyprzedzić tego, co odbiera, i to dlatego krok nosił
  wcześniej numer 22.

- **26** (proces tłowy) zależy od **25** i to zależność podwójna: stamtąd pochodzi
  wzorzec pracy kawałkowej (D46) oraz jej **pierwszy odbiorca** — wiersz „zajęte
  na dysku”, którego krok 25 świadomie nie pokazał, bo nie miał czym go policzyć.
  Krok powstał **w trakcie** kroku 25, na rozstrzygnięcie użytkownika, który
  oddzielił odczyt własny (wchodzi od razu) od procesu potomnego (osobny krok).
  Wykonany rozliczył się z obu długów naraz: wiersz `du` stanął w sekcji „Rozmiar”,
  a dwie odłożone pozycje ustawień weszły do zakładki modułu. Sięgnął ponadto do
  kodu kroku **23** — tryb „postęp nieznany” w `ProgressBar` dostał wreszcie
  prawdziwego użytkownika, co było warunkiem zdjęcia wyjątku od reguły 13 (D44) —
  oraz do **16**: scenariusz `background` jest pierwszym w `bin/render-bench`,
  który sięga poza PHP.

- **27–32** (Faza VII) tworzą **dwie gałęzie, nie łańcuch**, i to jest ich główna
  właściwość: kroki **27**, **28** i **29** są od siebie niezależne i wolno je
  robić w dowolnej kolejności — każdy dotyka innego miejsca w kodzie (wiersz
  listy, okno nakładane, prawy panel opisu). Zbiegają się dopiero w trójce
  kolejnej:
  - **30** (filtrowanie) zależy od **27** twardo, bo podświetlenie jest
    własnością wiersza, a wiersz zmienia tam kształt — robienie odwrotnie
    znaczyłoby przepisywać tę samą klasę dwa razy. Zależy ponadto od **7 i 8**,
    i to jest zależność, której nie miał **żaden** krok od czasu kroku 18: nowy
    prymityw obowiązuje **oba renderery naraz**, więc krok sięga do sixelowego
    i do tekstowego jednocześnie. **Wykonany po kroku 35, więc rendererów było
    trzy** — uwaga zapisana niżej sprawdziła się co do słowa, a kompletności
    tabeli tłumaczeń dopilnował `PrimitiveTranslationTableTest`. Ósmym kształtem
    został `TextMark` — **napis na własnym tle**, a nie samo tło pod fragmentem,
    bo to drugie byłoby synonimem `Bar`a z `Weight::Fill` (D59).
  - **31** (drzewo) zależy od **22** wzorcowo — `SectionState` już raz rozwiązał
    „co zwinięte, przeżywa klatkę i pamięta się pod kluczem, nie pod numerem”,
    a drzewo jest tym samym problemem o wymiar głębiej. Zależy od **27**, bo
    wiersz drzewa to wiersz listy z wcięciem.
  - **32** (menu) zależy od **28** twardo: tam rozstrzyga się, **jak okno oddaje
    decyzję**, a menu jest drugim oknem, które czegoś chce od wołającego.
    Zależy od **19** podwójnie — `OverlayInterface` i `CommandRegistry`, czyli
    jedyne uczciwe źródło jego pozycji.

  Krok **32** ma ponadto **zastrzeżenie rozstrzygane przed pierwszą linią kodu**:
  menu nakłada się z oknem komend i ma sens wyłącznie jako **widok na rejestr
  komend**, a nie drugi rejestr działań. Jeśli po rozpisaniu okaże się, że
  potrzebuje własnej listy pozycji — należy go odłożyć do czasu, aż powstaną
  operacje na plikach.

- **33** (reakcja na zmianę rozmiaru) zależy od **06** twardo i podwójnie —
  `SIGWINCH` wchodzi wzorcem znacznika obok czterech obsługiwanych sygnałów,
  a ewentualne ponowne pytanie o piksele korzysta z `WindowSizeParser`
  i `pushBackBytes()` — oraz od **09** (znacznik czytany w jednym miejscu taktu,
  jak `shutdownRequested`). Od **17** i **18** zależy jako od **tez do
  sprawdzenia**, nie prac do wykonania: pamięci podręczne mają rozmiar w kluczu
  (D34), a układ i komponenty liczą się co klatkę z prostokątów — jeśli oba
  zdania są prawdziwe, krok kończy się na warstwie terminala i pętli. Od Fazy
  VII nie zależy i ona nie zależy od niego.

- **34** (okno GLFW) zależy od **06** — słownik `KeyPress`/`Key` w
  `Application/Dto` był od początku projektowany bez śladu terminala (D16)
  i dostaje tu drugie źródło, z pominięciem `KeySequenceParser` — od **07**
  (`RendererMode` dostaje trzeci wariant, a wybór okienkowy omija DA1: nie ma
  go do kogo wysłać), od **09** (pętla zostaje jedna — `glfwPollEvents()`
  wchodzi w takt, a zamknięcie okna jest drugą drogą do tego samego `break`),
  od **13** (tło zastępczej klatki z ról motywu) i od **33** jako od wzorca
  do powtórzenia: rozmiar czytany przy każdym pytaniu, pamięci kluczowane
  rozmiarem odświeżają się same (D34) — z tą różnicą, że GLFW oddaje rozmiar
  tanio i w procesie, więc znacznik i ponowny pomiar są niepotrzebne. Od Fazy
  VII nie zależy i ona nie zależy od niego.
- **35** (natywny renderer prymitywów) zależy od **34** twardo i całkowicie —
  kontekst, pętla i viewport, w których rysuje, powstają tam — oraz od **18**
  w roli sprawdzianu: jest **trzecim tłumaczem tego samego słownika
  prymitywów** i niczego do słownika nie dokłada, jak krok 21 był sprawdzianem
  kontraktu modułu. Zależy od **13** (role motywu; pierwszy renderer bez
  palety indeksowanej), od **17** (wzorzec pamięci podręcznych przenosi się
  na tekstury glifów i bitmap) i od **12/25** (bitmapy podglądów muszą trafić
  do tekstur). **Uwaga o kroku 30 — rozstrzygnięta:** krok 30 wykonał się
  później, więc rozszerzył się o trzeciego tłumacza, dokładnie jak przewidziano.
  `PrimitiveTranslationTableTest` wymusił wpis w rendererze okienkowym, zanim
  ktokolwiek zdążył o nim zapomnieć — i to jest dowód, że test pisany w kroku 35
  na zapas nie był ostrożnością teoretyczną.

- **36** (odtwarzanie muzyki) zależy od **06** (zatrzymanie silnika audio
  wchodzi do sprzątania wszystkimi trzema ścieżkami wyjścia), od **09**
  (autostart staje w bootstrapie; pętla zostaje nietknięta — silnik gra we
  własnym wątku), od **14** (autostart, głośność, zapętlenie i ścieżka utworu
  to klucze ustawień), od **15** (etykiety komend i komunikat niedostępności
  przez katalog napisów) i od **19** (sterowanie wyłącznie komendami
  w `CommandRegistry` — bez nowego ekranu i skrótów). Od **26** zależy
  warunkowo — tylko jeśli rozwiązaniem zastępczym bez rozszerzenia zostanie
  proces zewnętrzny. Od Fazy VII, kroku 33 i **Fazy IX nie zależy** i one nie
  zależą od niego; jedyny punkt styku z krokiem 34: stuby `GL\*` do analizy
  statycznej dowozi ten z dwóch kroków, który wykona się pierwszy.

- **38** (rozbudowa diagnostyki i testów) zależy od **16 i 17** twardo —
  rozbudowuje narzędzie i metodykę, które tam powstały — od **18 i 21**
  (przebiegi funkcjonalne idą przez `ScreenFixture` i prawdziwe moduły), od
  **26** (wzorzec scenariusza sięgającego poza PHP i stuby procesów), od
  **30** (ostatni scenariusz w spisie — od niego liczy się stan zastany), od
  **33** (rozstrzygnięcie „zmiana rozmiaru to zimna klatka, nie scenariusz”
  jest poprzednikiem osi zimnej klatki) i od **35** (tor okienkowy: każdy
  nowy scenariusz przechodzi przez wszystkie tory naraz). Od kroków **31,
  32, 36 i 37** nie zależy i one nie zależą od niego; z krokami 31 i 32
  dzieli tylko granicę — scenariusze `tree` i `menu` przynoszą tamte kroki
  (D48), nie ten.

- **39** (Makefile) zależy od **05** twardo — `composer.json`, skrypty jakości
  i lista wymagań w README powstały tam, a krok daje im wspólne wejście, nie
  drugą definicję — od **07** (koder `SIXEL` i degradacja do trybu tekstowego
  to wymogi, które sprawdzenie środowiska ma umieć nazwać), od **16**
  **podwójnie** (cel pomiarowy opakowuje `bin/render-bench`; stamtąd pochodzi
  zakaz bramki wydajności, którego kroku nie wolno cicho odwrócić zależnością
  `qa`, a reguła D63 czyni to narzędzie **jedyną** drogą do pomiaru — koniec
  z doraźnymi pętlami `microtime()`) i od **34** (`glfw` jako wymóg
  **opcjonalny**, wyłącznie dla `--window`). Zależy ponadto od **04**: reguła
  procesu wchodzi w ustalony tam podział ról między `docs/architecture.md`,
  `SKILL.md` i `CLAUDE.md` (D8, D13) — pełna treść do dokumentu źródłowego,
  skrót do Skilla, wskaźnik zostaje wskaźnikiem. Kodu aplikacji nie dotyka —
  `src/` zostaje nietknięte.

  Z krokiem **38** łączy go zależność miękka, jedyna tego rodzaju w całym
  planie: rozbicie `test-unit` / `test-functional` potrzebuje granicy między
  przebiegami a testami jednostkowymi, a ta powstaje w pytaniu nr 6 kroku 38.
  Zalecana kolejność to **38 przed 39**; odwrotna jest dopuszczalna, o ile
  rozstrzygnięcie nr 5 kroku 39 przesądzi podział testsuite tam, a krok 38
  tylko dołoży do niego treść. Od kroków **31, 32, 36 i 37** nie zależy i one
  nie zależą od niego.

Żaden krok nie da się sensownie zacząć przed ukończeniem swoich zależności
z tabel powyżej.

## Śledzenie postępu

Każdy plik kroku ma sekcje `## Status` i `## Dziennik realizacji`. Po
zakończeniu pracy nad krokiem:

1. Zaktualizuj `## Status` w pliku kroku (Nie rozpoczęty / W toku /
   Zablokowany / Ukończony).
2. Dopisz wpis w `## Dziennik realizacji` tego kroku (co zrobiono, jakie
   odstępstwa od planu).
3. Zaktualizuj kolumnę Status w tabelach powyżej.
4. Jeśli podjęto nietrywialną decyzję architektoniczną — dopisz ją do
   [00-decyzje.md](00-decyzje.md).

## Zakres poza MVP (do rozważenia w kolejnych iteracjach)

- Operacje na plikach (kopiuj / przenieś / usuń / zmień nazwę / nowy katalog) —
  krok 24 dowiózł do nich wstęp: dwupanelową przeglądarkę, a krok **28** dowozi
  okno potwierdzenia, bez którego usuwanie nie ma prawa powstać
- ~~Podgląd plików tekstowych~~ — wszedł do planu jako krok **29** (D48)
  i **został wykonany**; pozycja jest zamknięta od 2026-08-12
- Przewijanie ekranu ustawień — zakładka dłuższa od okna gubi dziś pozycje
  zamiast je przewijać (`Slot::fixed()`, reguła 11e). Zauważone w kroku 29, gdy
  zakładka `file-info` urosła do dziesięciu pozycji; zachowanie jest starsze
- Rozpoznawanie UTF-32 bez znacznika kolejności bajtów — świadomie pominięte
  w kroku 29 (kodowanie z BOM-em i UTF-16 bez BOM-u są obsługiwane)
- ~~Widok dwupanelowy~~ — wszedł do planu jako krok **24** (D43)
- ~~Wyszukiwanie / filtrowanie~~ — weszło do planu jako krok **30** (D48)
  i **zostało wykonane**; pozycja jest zamknięta od 2026-08-12
- Podświetlanie dopasowania w podglądzie tekstu — prymityw `TextMark` powstał
  tak, żeby było możliwe, ale odbiorcą kroku 30 była lista; `TextView` filtra nie
  ma
- Zakładki / historia odwiedzonych katalogów
- Kolorowanie składni w podglądzie tekstu — wyłączone z kroku 29
- Sortowanie listy po kolumnie — wyłączone z kroku 27
- Zaznaczenie wielokrotne — wyłączone z kroku 32
- Zdarzenia myszy w oknie GLFW — wyłączone z kroków 34–35 (aplikacja nie ma
  słownika zdarzeń myszy w żadnej warstwie; osobna decyzja, jeśli w ogóle)
- Inicjalizacja repozytorium git
