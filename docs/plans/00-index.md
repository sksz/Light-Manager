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
| 1 | Warstwy DDD i struktura katalogów `src/` | [01-warstwy-ddd-i-struktura-katalogow.md](archiwum/01-warstwy-ddd-i-struktura-katalogow.md) | — | Opus | high | Ukończony |
| 2 | Wzorzec Singleton i bootstrap usług | [02-wzorzec-singleton-i-bootstrap.md](archiwum/02-wzorzec-singleton-i-bootstrap.md) | 1 | Opus | high | Ukończony |
| 3 | Standardy stylu kodowania PHP | [03-standardy-stylu-kodowania.md](archiwum/03-standardy-stylu-kodowania.md) | 1 | Sonnet | medium | Ukończony |
| 4 | Dokumentacja ustaleń: docs + Claude Code Skill | [04-dokumentacja-i-ai-skill.md](archiwum/04-dokumentacja-i-ai-skill.md) | 1, 2, 3 | Sonnet | medium | Ukończony |

### Faza II — Wdrożenie aplikacji

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 5 | Szkielet projektu i wymagania | [05-szkielet-projektu.md](archiwum/05-szkielet-projektu.md) | 1, 2, 3, 4 | Sonnet | medium | Ukończony |
| 6 | Terminal I/O: tryb surowy, klawisze, sygnały | [06-terminal-io.md](archiwum/06-terminal-io.md) | 5 | Opus | high | Ukończony |
| 7 | Wykrywanie Sixela (DA1) + tryb fallback | [07-wykrywanie-sixel.md](archiwum/07-wykrywanie-sixel.md) | 6 | Opus | high | Ukończony |
| 8 | Potok renderowania: Imagick canvas → Sixel → STDOUT | [08-render-imagick-sixel.md](archiwum/08-render-imagick-sixel.md) | 5, 7 | Opus | high | Ukończony |
| 9 | Pętla główna (game loop) | [09-petla-glowna.md](archiwum/09-petla-glowna.md) | 6, 8 | Sonnet | medium | Ukończony |
| 10 | Stan i nawigacja po systemie plików | [10-nawigacja-fs.md](archiwum/10-nawigacja-fs.md) | 9 | Sonnet | medium | Ukończony |
| 11 | Renderowanie listy plików w klatce | [11-render-listy-plikow.md](archiwum/11-render-listy-plikow.md) | 8, 10 | Sonnet | medium | Ukończony |
| 12 | Podgląd miniatur obrazów | [12-podglad-miniatur.md](archiwum/12-podglad-miniatur.md) | 11 | Sonnet | medium | Ukończony |

### Faza III — Wygląd, konfiguracja i narzędzia

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 13 | Motyw graficzny: paleta Grafit i układ panelowy | [13-motyw-graficzny.md](archiwum/13-motyw-graficzny.md) | 11, 12 | Opus | high | Ukończony |
| 14 | Konfiguracja aplikacji i ekran ustawień | [14-konfiguracja-i-ekran-ustawien.md](archiwum/14-konfiguracja-i-ekran-ustawien.md) | 13 | Opus | high | Ukończony |
| 15 | Wielojęzyczność interfejsu | [15-wielojezycznosc.md](archiwum/15-wielojezycznosc.md) | 13, 14 | Sonnet | medium | Ukończony |
| 16 | Narzędzia diagnostyczne wydajności | [16-narzedzia-diagnostyczne-wydajnosci.md](archiwum/16-narzedzia-diagnostyczne-wydajnosci.md) | 13, 14 | Opus | high | Ukończony |
| 17 | Optymalizacja wydajności renderowania | [17-optymalizacja-wydajnosci.md](archiwum/17-optymalizacja-wydajnosci.md) | 13, 16 | Opus | high | Ukończony |

### Faza IV — Komponenty interfejsu i rozszerzalność

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 18 | Komponenty interfejsu i płaszczyzny | [18-komponenty-i-plaszczyzny.md](archiwum/18-komponenty-i-plaszczyzny.md) | 13, 14, 15, 16, 17 | Opus | high | Ukończony z zastrzeżeniem |
| 19 | Okno komend | [19-okno-komend.md](archiwum/19-okno-komend.md) | 18 | Opus | xhigh | Ukończony z zastrzeżeniem |
| 20 | Moduły (plugins) | [20-moduly-plugins.md](archiwum/20-moduly-plugins.md) | 14, 15, 18, 19 | Opus | high | Ukończony |
| 21 | Przeglądarka plików jako moduł domyślny | [21-przegladarka-jako-modul.md](archiwum/21-przegladarka-jako-modul.md) | 20 | Opus | xhigh | Ukończony |

### Faza V — Komponenty rdzenia i rozwój modułów

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 22 | Zwijana sekcja jako komponent rdzenia | [22-zwijana-sekcja.md](archiwum/22-zwijana-sekcja.md) | 18 | Opus | high | Ukończony |
| 23 | Pasek postępu z tekstem jako komponent rdzenia | [23-pasek-postepu.md](archiwum/23-pasek-postepu.md) | 18 | Opus | high | Ukończony |
| 24 | Podział ekranu: dwa panele w jednym ekranie | [24-podzial-ekranu.md](archiwum/24-podzial-ekranu.md) | 21 | Opus | xhigh | Ukończony |
| 25 | Pełny obraz stanu pliku w module `FileInfo` | [25-pelny-obraz-pliku.md](archiwum/25-pelny-obraz-pliku.md) | 21, 22, 23, 24 | Opus | high | Ukończony z zastrzeżeniem |

### Faza VI — Praca poza klatką

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 26 | Proces tłowy jako mechanizm rdzenia | [26-proces-tlowy.md](archiwum/26-proces-tlowy.md) | 25 | Opus | high | Ukończony |

### Faza VII — Rozbudowa rdzenia o nowe komponenty

Sześć komponentów wybranych 2026-08-11 z przeglądu braków rdzenia
([00-decyzje.md](00-decyzje.md), D48). Rytm: **jeden komponent — jeden krok**,
każdy z własnymi rozstrzygnięciami na starcie, własnym pomiarem „przed i po”
i własnym wpisem w dzienniku. Trzy pierwsze mają odbiorcę **już w kodzie**, trzy
kolejne dowożą go razem z komponentem.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 27 | Wiersz wielokolumnowy (`Table`) | [27-tabela-kolumn.md](archiwum/27-tabela-kolumn.md) | 17, 18, 21, 24 | Opus | high | Ukończony |
| 28 | Okno potwierdzenia (`ConfirmOverlay`) | [28-okno-potwierdzenia.md](archiwum/28-okno-potwierdzenia.md) | 14, 18, 19 | Opus | high | Ukończony |
| 29 | Widok tekstu (`TextView`) | [29-podglad-tekstu.md](archiwum/29-podglad-tekstu.md) | 12, 18, 25 | Opus | high | Ukończony |
| 30 | Filtrowanie i podświetlenie dopasowania | [30-filtrowanie-i-podswietlenie.md](archiwum/30-filtrowanie-i-podswietlenie.md) | 7, 8, 18, 19, 21, 27, 35 | Opus | xhigh | Ukończony |
| 31 | Drzewo (`TreeView`) | [31-drzewo-katalogow.md](archiwum/31-drzewo-katalogow.md) | 18, 21, 22, 24, 27 | Opus | xhigh | Ukończony |
| 32 | Menu kontekstowe (`MenuOverlay`) | [32-menu-kontekstowe.md](archiwum/32-menu-kontekstowe.md) | 19, 20, 21, 28 | Opus | high | Ukończony |

### Faza VIII — Okno terminala

Rozmiar okna przestaje być stałą uruchomienia. Krok od Fazy VII niezależny
w obie strony — wolno go zrobić przed nią, po niej albo pomiędzy
([00-decyzje.md](00-decyzje.md), D50).

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 33 | Reakcja na zmianę rozmiaru okna | [33-reakcja-na-zmiane-rozmiaru.md](archiwum/33-reakcja-na-zmiane-rozmiaru.md) | 6, 9, 17, 18 | Fable | xhigh | Ukończony |

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
| 34 | Okno GLFW: kontekst, wejście, pętla | [34-okno-glfw.md](archiwum/34-okno-glfw.md) | 6, 7, 9, 13, 33 | Fable | xhigh | Ukończony |
| 35 | Natywny renderer prymitywów w OpenGL | [35-renderer-opengl.md](archiwum/35-renderer-opengl.md) | 12, 13, 17, 18, 34 | Fable | xhigh | Ukończony |
| 37 | Dopracowanie okna: rozmiar, pełny ekran, ikona, skala | [37-dopracowanie-okna.md](archiwum/37-dopracowanie-okna.md) | 14, 19, 34, 35 | Opus | medium | Ukończony z zastrzeżeniem |

### Faza X — Dźwięk: odtwarzanie muzyki (`GL\Audio`)

Aplikacja dostaje dźwięk ([00-decyzje.md](00-decyzje.md), D55): moduł audio
rozszerzenia PHP-GLFW odtwarza muzykę **poza ścieżką klatki** — domyślnie
i na początek riff „Smoke On The Water”. Rozszerzenie jest w środowisku
załadowane (sprawdzone przy planowaniu); brak rozszerzenia to degradacja
z komunikatem, nie błąd. Od Fazy IX krok niezależny w obie strony — dzieli
z nią tylko rozszerzenie i stuby do analizy statycznej.

**Wykonany — i wyszedł inny, niż go zaplanowano** (D70). Dwie rzeczy odwróciły
plan: użytkownik dowiózł utwór jako **plik MP3** (więc syntezator riffu okazał
się niepotrzebny w całości), a ścieżka utworu okazała się pierwszym kluczem
rdzenia z wartością tekstową — czego ekran ustawień nie umie. Dźwięk poszedł
przez to tam, gdzie wedle reguły 15 należał od początku: **jest modułem**
(`src/Module/Audio/`), a rdzeń kosztuje jedną linię w `Bootstrapie`. Cena
zapisana w statusie kroku: **autostartu nie ma**, bo kontrakt modułu nie zna
cyklu życia — muzykę uruchamia komenda `audio.music`.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 36 | Odtwarzanie muzyki przez `GL\Audio` | [36-odtwarzanie-muzyki.md](archiwum/36-odtwarzanie-muzyki.md) | 6, 9, 14, 15, 19 | Opus | high | Ukończony z zastrzeżeniem |

### Faza XI — Diagnostyka i testy

Krok przekrojowy ([00-decyzje.md](00-decyzje.md), D61): aplikacja przez Fazy
IV–IX urosła szybciej niż jej miary, więc jeden krok wyrównuje zaległości
naraz — spis scenariuszy pomiarowych z powodami pominięć, oś zimnej klatki,
tor tekstowy w `bin/render-bench`, porównanie zrzutów i katalog nazwanych
przebiegów funkcjonalnych. Komponentów nie dokłada, więc rytm Fazy VII (D48)
zostaje nietknięty.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 38 | Rozbudowa diagnostyki, benchmarku i testów funkcjonalnych | [38-rozbudowa-diagnostyki-i-testow.md](archiwum/38-rozbudowa-diagnostyki-i-testow.md) | 16, 17, 18, 21, 26, 30, 33, 35 | Opus | high | Ukończony |

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
| 39 | Makefile: środowisko, instalacja, jakość, testy, budowa | [39-makefile.md](archiwum/39-makefile.md) | 4, 5, 7, 16, 34 | Opus | medium | Ukończony |

### Faza XIII — Podpowiedzi kontekstowe

Krok odwracający ([00-decyzje.md](00-decyzje.md), D65): pasek stanu przestaje
powtarzać cztery niezmienne klawisze rdzenia i zaczyna mówić o **elemencie,
ekranie albo module, na którym stoi ognisko**. Kroki 14 i 18 postanowiły
odwrotnie — „stopka nie jest ściągawką, tylko wskazaniem, gdzie ściągawka leży”
— i to zdanie zostaje odwołane, ale wyłącznie co do zasięgu: źródłem podpowiedzi
pozostaje `KeyBinding`, a okno pomocy pozostaje **pełnym** spisem. Krok obejmuje
przy tym formalizację ogniska, którego dziś żaden kontrakt nie nazywa, i zgadza
się na pasek stanu rosnący do dwóch wierszy — czyli na przeliczenie wszystkich
wzorców w trzech torach.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 40 | Stopka mówi, co da się zrobić tu i teraz | [40-stopka-kontekstowa.md](archiwum/40-stopka-kontekstowa.md) | 18, 19, 24, 25, 29, 30, 33, 35, 38 | Fable | xhigh | Ukończony |

### Faza XIV — Operacje na plikach

Faza domykająca pierwszą pozycję „Zakresu poza MVP”
([00-decyzje.md](00-decyzje.md), D66): przeglądarka po raz pierwszy **zmienia**
to, co pokazuje — do tej pory aplikacja umiała zapisać wyłącznie własny plik
konfiguracyjny. Wstęp dowiozły kroki **24** (dwa panele, czyli źródło
i cel) i **28** (okno potwierdzenia, bez którego usuwanie nie miało prawa
powstać). Rytm jest ten sam, co w Fazie VII: **jedna rzecz — jeden krok**, każdy
z własnymi rozstrzygnięciami na starcie i własnym rozliczeniem.

Faza bierze przy tym **jawny wyjątek od reguły 15**: operacje zapisu mieszkają
w rdzeniu jako usługa wspólna, a nie w module (D66, rozstrzygnięcie 2). Granica
tego wyjątku jest częścią zakresu kroku 41 i ma trafić do `SKILL.md` wraz
z powodem — nienazwana, otworzyłaby rdzeń na wszystko.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 41 | Fundament operacji: nazwa, nowy katalog, usunięcie | [41-operacje-fundament.md](archiwum/41-operacje-fundament.md) | 14, 15, 18, 19, 21, 24, 28, 38 | Opus | high | Ukończony z zastrzeżeniem |
| 42 | Kopiowanie i przenoszenie po kawałku, z postępem | [42-kopiowanie-i-przenoszenie.md](42-kopiowanie-i-przenoszenie.md) | 23, 24, 25, 38, 41 | Fable | xhigh | Nie rozpoczęty |
| 43 | Zaznaczenie wielokrotne jako mnożnik operacji | [43-zaznaczenie-wielokrotne.md](43-zaznaczenie-wielokrotne.md) | 21, 27, 28, 30, 38, 41 | Opus | high | Nie rozpoczęty |
| 44 | Kosz i cofnięcie ostatniej operacji | [44-kosz-i-cofanie.md](44-kosz-i-cofanie.md) | 6, 14, 15, 19, 29, 38, 41, 42 | Opus | high¹ | Nie rozpoczęty |

¹ **Fable / xhigh**, jeśli rozstrzygnięcie startowe nr 1 tamtego kroku wprowadzi
`Shift` do słownika wejścia — wtedy krok zmienia trzy tory wejścia naraz. Model
wybiera się **po** tej odpowiedzi, nie przed nią.

**Między krokiem 41 a 42 wchodzi krok 47** (Faza XVI): spłaca dług, o który
kroki 42 i 43 oprą swoje pozycje w menu i okna wywoływane komendą. Kolejność
wykonania jest tu ważniejsza od numeru — powód stoi przy opisie tamtej fazy
i w grafie zależności.

### Faza XV — Rozbudowa modułu dźwięku

Moduł z kroku 36 dostaje **okno, playlistę i efekty specjalne**
([00-decyzje.md](00-decyzje.md), D71). Faza rusza przy tym **dwa mechanizmy
rdzenia, których projekt nie ma**: takt dla modułu (bo playlista musi zauważyć,
że utwór się skończył — także wtedy, gdy okna audio nie widać) i nazwane
zdarzenia (bo efekt musi się dowiedzieć, że coś się stało). Jeden mechanizm na
krok — stąd dwa kroki, a nie jeden.

Faza **odwraca rozstrzygnięcie D70**, i to jawnie: kontrakt modułu zyskuje
zdolności, których krok 36 świadomie mu odmówił. Różnica, na której to stoi,
jest zapisana w obu plikach kroków — tam zdolność miała jednego użytkownika
i wyłącznie dla wygody, tu bez niej funkcja nie istnieje.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 45 | Ekran modułu dźwięku i playlista | [45-ekran-audio-i-playlista.md](45-ekran-audio-i-playlista.md) | 9, 14, 15, 16, 18, 20, 21, 36 | Opus | xhigh | Nie rozpoczęty |
| 46 | Efekty specjalne: zdarzenia aplikacji dostają dźwięk | [46-efekty-specjalne.md](46-efekty-specjalne.md) | 18, 19, 20, 24, 27, 36, 45 | Opus | high | Nie rozpoczęty |

### Faza XVI — Spłata długów

Faza bez ani jednej nowej funkcji ([00-decyzje.md](00-decyzje.md), D77): zbiera
trzy długi, które **nie mają właściciela** w żadnym zaplanowanym kroku —
zobowiązanie kroku 41 wobec kroku 32 (komenda nie umie otworzyć okna, D75
rozstrzygnięcie 5), skutek uboczny D76 (trzecia strefa została bez odbiorcy,
wbrew regule 13) i defekt zauważony w kroku 29 (zakładka ustawień dłuższa od okna
gubi pozycje zamiast je przewijać). Długi **z właścicielem** do fazy nie wchodzą:
szerokość okna liczona z długości napisu należy do kroku 42, autostart muzyki do
kroku 45, a skala treści czeka na sprzęt, nie na krok.

**Numer ma wyższy, wykonuje się wcześniej** — zaraz po kroku 41, **przed krokiem
42**. Powód jest mechaniczny: kroki 42 i 43 dokładają czynności, z których każda
zechce pozycji w menu i okna wywołanego komendą. Spłacony dług obsłuży je za
darmo, niespłacony każe przerabiać to samo miejsce trzy razy. Ten sam rachunek
dotyczy wzorców pomiarowych w trzech torach — przelicza się je raz.

| # | Krok | Plik | Zależy od | Model | Wysiłek | Status |
|---|------|------|-----------|-------|---------|--------|
| 47 | Spłata długów: komenda otwiera okno, strefa bez odbiorcy, zakładka bez przewijania | [47-splata-dlugow.md](47-splata-dlugow.md) | 14, 18, 19, 21, 22, 29, 32, 33, 38, 40, 41 | Fable² | xhigh | Ukończony |

² Model **rozstrzygnięty 2026-08-14** (D78, rozstrzygnięcie 4): trzecia strefa
wychodzi z `ScreenInterface`, więc krok rusza kontrakt ekranu, układ, kompozytor
klatki i wszystkie trzy renderery naraz — czyli zachodzi warunek, dla którego
przypis przewidywał `Fable / xhigh` zamiast `Opus / xhigh`.

### Dokumenty towarzyszące (praca projektowa)

| Krok | Plik | Aktualizowany | Model | Wysiłek | Status |
|------|------|----------------|-------|---------|--------|
| Dziennik decyzji i utrzymanie planu | [00-decyzje.md](00-decyzje.md) | równolegle od kroku 1 | Sonnet 5 | Extra High | W toku |

## Graf zależności

Kolejność realizacji pokrywa się z numeracją (01…47) **do kroku 26 włącznie**;
Faza VII łańcuchem już nie jest, a kroki 33–40 (Fazy VIII–XIII) stoją poza nią
zupełnie (patrz opisy na końcu tej listy). Faza XIV (41–44) jest **znowu
łańcuchem**, i to jedyna po Fazie VI — z jednym wtrąceniem: **krok 47 wchodzi
między 41 a 42**, bo spłaca dług, na którym tamte kroki się oprą (opis na końcu
tej listy). Poza prostym
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
    wiersz drzewa to wiersz listy z wcięciem. **Wykonany — i obie te zależności
    okazały się różnej mocy.** Wzorzec z kroku 22 sprawdził się co do joty, ale
    `TreeState` musiał odejść od `SectionState` w trzech miejscach naraz (D68,
    rozstrzygnięcie 5), więc powtórzeniem jest reguła, a nie klasa. Zależność od
    **27** okazała się natomiast **słabsza, niż zakładano**: wiersz drzewa jest
    `ListRow`em rysowanym przez `ListView`, a nie `TableRow`em — kolumny są
    w liście plików, nie w drzewie. Widoczny skutek: drzewo **nie podświetla
    dopasowania filtra**, bo zakresy niesie `TableRow`.
  - **32** (menu) zależy od **28** twardo: tam rozstrzyga się, **jak okno oddaje
    decyzję**, a menu jest drugim oknem, które czegoś chce od wołającego.
    Zależy od **19** podwójnie — `OverlayInterface` i `CommandRegistry`, czyli
    jedyne uczciwe źródło jego pozycji. **Wykonany — a zależność od 28 okazała
    się słabsza, niż zakładano**: menu wykonuje komendę samo, jak okno komend,
    więc domknięcie z D56 nie było mu potrzebne. Zależność od **19** za to
    podwoiła się w praktyce: rejestr jest wspólny, a nie tylko „ten sam rodzaj”.

  Krok **32** miał ponadto **zastrzeżenie rozstrzygane przed pierwszą linią
  kodu**: menu nakłada się z oknem komend i ma sens wyłącznie jako **widok na
  rejestr komend**, a nie drugi rejestr działań; gdyby potrzebowało własnej listy
  pozycji, należało go odłożyć do operacji na plikach. **Sprawdzenie potwierdziło
  obawę, a rozstrzygnięcie użytkownika poszło trzecią drogą** (D69): rejestr miał
  dla zaznaczenia jedną komendę, więc krok **dowiózł treść razem z mechanizmem** —
  cztery nazwy dla czynności, które aplikacja miała wyłącznie pod klawiszem
  (`file-info.show`, `browser.open`, `browser.hidden`, `browser.tree`). Menu
  zostało widokiem na rejestr, a nie drugą listą.

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

  **Wykonany — a trzy z tych zależności okazały się inne, niż zakładano.** Od
  **09** krok nie zależy wcale: autostartu nie ma, więc w bootstrapie nie staje
  nic. Od **14** zależy słabiej: ustawienia są **modułowe**, a nie rdzeniowe, więc
  klucze rdzenia zostały nietknięte — i to właśnie ta zależność odwróciła cały
  krok, bo pozycję tekstową ma wyłącznie zakładka modułu. Od **20 i 21** zależy
  za to **twardo**, choć plan ich nie wymieniał: krok bierze kontrakt modułu taki,
  jaki tam powstał, i jest jego sprawdzianem z drugiej strony niż krok 21 — moduł,
  **który nic nie rysuje**. Stuby `GL\*` dowiózł krok 34 i wystarczyły; jedno
  punktowe wyciszenie analizy bierze się z tego, że są **starsze od
  rozszerzenia**.

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
  (D48), nie ten. Krok **31 wykonał się później i granicy dotrzymał**:
  scenariusz `tree` przyszedł razem z komponentem, a spis „element →
  scenariusz” w `docs/pomiary/README.md` stracił przez to jedną pozycję
  pominięcia. Krok **32 rozliczył się odwrotnie i też zgodnie z regułą**:
  scenariusza `menu` nie przyniósł, bo nie dowiózł ani jednego komponentu —
  okno jest `Dialog`iem z `ListView` w środku — więc pozycja w spisie została,
  ale z **powodem pominięcia** zamiast obietnicy.

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
  Zalecana kolejność to **38 przed 39** — i tak się stało: krok 38 wykonał się
  pierwszy, a granica jest już w `phpunit.xml.dist` jako testsuite `unit`
  i `functional`. Krok 39 **dowiązuje do niej cele**, zamiast ją rozstrzygać. Od kroków **31, 32, 36 i 37** nie zależy i one
  nie zależą od niego.

  **Wykonany — a zależność od 05 okazała się mocniejsza, niż zakładano, i to
  w drugą stronę.** Plan mówił „Makefile ich nie zastępuje, tylko daje im wspólne
  wejście” i tak zostało (cele jakości wołają skrypty Composera), ale krok wrócił
  do `composer.json` po **wersję**, której plik nie niósł — pole `version`
  dopisał użytkownik przy rozstrzygnięciach startowych i to ono nazywa wynik
  budowy. Zależność od **34** okazała się natomiast **słabsza**: `glfw` jest
  w `check-env` wymogiem opcjonalnym i nie psuje żadnego celu, dokładnie jak
  zapisano — ale tor okienkowy odezwał się z innej strony, bo `make bench-window`
  ujawnił SIGSEGV, który `bin/render-bench --window` miał **sam z siebie**, przy
  sprzątaniu kontekstu (dziennik kroku, pkt 6). Cel niczego nie zepsuł — przestał
  przemilczać kod wyjścia, a usterka została naprawiona osobno (D73), razem
  z drugą, którą krok wypatrzył w testach. Wniosek na przyszłość: **wejście
  czytające kod wyjścia jest tanim wykrywaczem awarii, które narzędzie
  przemilcza** — obie usterki były starsze od tego kroku o kilka faz.

- **40** (stopka kontekstowa) zależy od **18** twardo i wielokrotnie — `KeyBinding`
  jako jedno źródło trzech rzeczy naraz, `FocusableInterface`, `StatusBar`
  i `HudLayout` to komplet tego, co krok rusza — od **19** (`OverlayInterface`
  z regułą „okno klawiszy niżej nie oddaje”, z której wynika, że okno **wypiera**
  ekran ze stopki), od **24** (`SplitState`, czyli pierwsze miejsce, w którym ekran
  wie, który jego kawałek ma ognisko) oraz od **25 i 29** wraz z **D60**:
  `FileInfoScreen` z ogniskiem wędrującym między sekcjami a podglądem tekstu jest
  **najbogatszym odbiorcą** kroku i jego właściwym sprawdzianem. Od **30** bierze
  precedens „spis pokazuje wyłącznie to, co działa tu i teraz” i rozciąga go
  z okna pomocy na pasek stanu. Od **33** zależy przez pasek rosnący do dwóch
  wierszy — reguła musi przeżyć przeliczenie układu przy zmianie rozmiaru okna —
  a od **35**, bo zmiana wysokości strefy dotyczy **wszystkich trzech torów**, nie
  samego terminala. Od **38** zależy podwójnie: przelicza wzorce tekstowe
  i obrazowe, które tam powstały, a pomiar prowadzi przez `ScenarioFactory::HINTS`
  — dziś **stałą tekstową oderwaną od aplikacji**, więc nietkniętą mierzyłby
  stopkę, której aplikacja nie ma.

  Od kroków **31, 36, 37 i 39** nie zależy i one nie zależą od niego. Z krokiem
  **32** dzieli pytanie zadane z dwóch stron — menu pyta, jakie **komendy** mają
  sens dla zaznaczenia, stopka: jakie **klawisze** działają w miejscu ogniska —
  ale zależności nie ma w żadną stronę. Krok **32 wykonał się pierwszy**, więc
  zdolność `AppliesToSelection` już istnieje i stopka może z niej skorzystać.
  Doszedł za to `F9` w klawiszach globalnych — czyli **piąta** pozycja, która ma
  się zmieścić w pasku stanu, i to jest dla kroku 40 argument, a nie drobiazg.

  **Wykonany — a dwie z tych zależności okazały się mocniejsze, niż zakładano.**
  Uwaga o `F9` sprawdziła się co do słowa i to ona przesądziła o rozstrzygnięciu
  nr 5 (drugi, krótki klucz opisu): pięć wiązań rdzenia w dzisiejszych opisach to
  78 kolumn, więc stopka znikała w oknie stu kolumn **jeszcze przed tym krokiem**,
  a nie dopiero po dołożeniu klawiszy ekranu. Od **25 i 29** krok zależy podwójnie,
  bo `FileInfoScreen` był nie tylko najbogatszym odbiorcą, ale i jedynym miejscem,
  w którym ten sam klawisz znaczy w dwóch panelach dwie różne rzeczy — czyli
  jedynym dowodem, że nazwa miejsca w stopce jest potrzebna. Od **38** zależność
  wyszła za to **z trzeciej strony**: `ScenarioFactory::HINTS` przeliczono zgodnie
  z planem, ale prawdziwym narzędziem kroku okazał się `FrameSerializer` — bez
  niego test „stopka nie kłamie” nie widziałby połowy czynności, bo kursor sekcji
  zmienia **rolę** wiersza, a nie jego treść. Od **33** i **35** krok zależał
  najsłabiej: przeliczenie wysokości strefy przeszło przez oba tory bez ani jednej
  zmiany w nich samych.

- **41–44** (Faza XIV) tworzą **łańcuch**, w odróżnieniu od Faz VII–XIII: każdy
  krok dokłada do tego, co postawił poprzedni, i osobno sensu nie ma.
  - **41** (fundament operacji) zależy od **28** twardo — `ConfirmOverlay`
    w wariancie `dangerous` jest jedyną drogą, którą usuwanie ma prawo powstać,
    a wariant ten **nie ma dziś użytkownika** — oraz od **18** (`TextInput`,
    `Dialog`, reguła 10: nowe okno to `OverlayInterface`), **19**
    (`ScreenOutcome::opens()`, kontrakt komendy), **21** (`DescribesProblem`, bez
    którego niepowodzenie operacji uczyłoby rdzeń, czym jest katalog), **24**
    (dwa panele, bo odświeżyć trzeba **oba**), **14 i 15** (ustawienie i napisy)
    oraz **38** (wzorce i przebiegi). Ze **32** styka się treścią, nie
    zależnością — i styk zmienił kierunek, bo krok 32 **nie czekał**: wykonany
    2026-08-14, dowiózł menu wraz z własną treścią (D69). Zostawało z tego
    zobowiązanie w drugą stronę: czynność kroku 41 działająca na zaznaczeniu miała
    zadeklarować `AppliesToSelection`, a wtedy pojawić się w menu **bez zmiany
    w rdzeniu**. **Zobowiązanie zostało długiem** i powód jest mechaniczny (D75,
    rozstrzygnięcie 5): `CommandOutcome` wskazuje ekran **identyfikatorem**, bo leży
    w `Application`, a okna nakładane rejestru identyfikatorów nie mają — więc żadna
    komenda nie umie otworzyć okna, a bez okna nie ma ani pytania przed usunięciem,
    ani pola na nazwę. Krok 41 dowiózł zamiast tego **pierwsze komendy z argumentem**
    (`browser.rename <nazwa>`, `browser.mkdir <nazwa>`), a menu `F9` nie zyskało ani
    jednej pozycji.
  - **42** (kopiowanie i przenoszenie) zależy od **41** całkowicie i od **25**
    wzorcowo (praca kawałkowa, D46 — kopiowanie jest jej **trzecim**
    zastosowaniem i pierwszym, które nie tylko czyta), od **23** (`ProgressBar`
    wraz z trybem „postęp nieznany”) i od **24** (skąd i dokąd). Od **26**
    zależy **odwrotnie niż zwykle**: bierze go jako wzorzec do **odrzucenia** —
    proces potomny prowadzi jedną pracę naraz i wypierałby `du`, a postępu nie
    zna.
  - **43** (zaznaczenie wielokrotne) zależy od **41** (jest mnożnikiem jego
    czynności), od **30** (reguła „zaznaczenie przenosi się po nazwie” i drugi
    widok na tę samą listę), od **27** (wiersz, w którym znacznik ma się
    zmieścić) i od **21** (`ModuleContext`, który zna **jeden** wpis — i to jest
    główne rozstrzygnięcie tamtego kroku). Z **42** dzieli zależność miękką
    w obie strony; zalecana kolejność to **42 przed 43**.
  - **44** (kosz i cofanie) zależy od **41** (druga droga usunięcia), od **42**
    (kosz na innym systemie plików to kopiowanie i usunięcie) oraz od **6, 19
    i 29** — słownika wejścia, który **nie zna `Shift`** w żadnym z trzech torów,
    a rozstrzygnięcie „droga zależna od skrótu” tego dotyka wprost.

- **45–46** (Faza XV) tworzą **łańcuch**, druga po Fazie XIV: krok 46 dokłada
  panel do okna, które powstaje w 45, i mapę do pliku, który 45 zakłada. Obydwa
  zależą od **36** całkowicie (port audio i obie jego implementacje) oraz od
  **20 i 21** twardo — biorą kontrakt modułu taki, jaki tam powstał, i są jego
  **trzecim sprawdzianem**: po module rysującym główną funkcję aplikacji (21)
  i module, który nie rysuje nic (36), przychodzi moduł, który **pracuje, gdy go
  nie widać**.
  - **45** (ekran i playlista) zależy ponadto od **09** twardo (takt modułu
    wchodzi w takt pętli i musi się zmieścić w budżecie klatki), od **16** twardo
    (oś `--loop` jest jedynym narzędziem, którym da się rozliczyć coś wołanego
    trzydzieści razy na sekundę), od **18** (`ListView`, `ScrollWindow` —
    komponentu nie dokłada) oraz od **14 i 15**. Wraca przy tym do kodu kroku
    **36**: klucz `track` znika z zakładki, a jego wartość przenosi się na
    playlistę.
  - **46** (efekty specjalne) zależy od **45** całkowicie, od **24** (okno rośnie
    do dwóch paneli — `Split` i `SplitState`, wraz z regułą „podział należy do
    modułu”), od **27** (wiersz o dwóch kolumnach: zdarzenie i plik) oraz od
    **18 i 19** (pole tekstowe i okno nakładane, jeśli przypisanie idzie
    wpisaniem ścieżki). Z **Fazą XIV** styka się treścią, nie zależnością:
    „plik skasowany” jest najciekawszym kandydatem na zdarzenie, ale krok obejdzie
    się bez niego, jeśli tamta faza wykona się później.

- **47** (spłata długów) jest jedynym krokiem planu, którego **numer nie mówi nic
  o kolejności**: idzie zaraz po **41**, przed **42**. Zależy od **41** twardo
  (dług A powstał tam i tam leży `EntryOperations`, w które menu ma trafić), od
  **32** (`MenuOverlay` i `AppliesToSelection`, czyli odbiorca długu A), od **19**
  (`CommandOutcome` i `InputHandler::openById()` — jedyne dziś tłumaczenie
  identyfikatora na obiekt) oraz od **18** (`OverlayInterface`, `ScreenInterface`,
  `ScreenOutcome::opens()` — wszystkie trzy długi leżą w tym, co tam powstało).
  Od **40** zależy nieoczywiście i to jest najciekawsza z jego zależności: próg
  dwuwierszowego paska stanu jest **wyprowadzony** z progu pasa podglądu
  (`ROWS_FOR_PREVIEW + 2`), więc usunięcie strefy wraca do rachunku tamtego kroku
  i każe mu napisać nowe uzasadnienie, nie nową liczbę. Od **12, 13, 21 i 25**
  bierze pochodzenie pasa podglądu i miejsce, w którym miniatura została
  (`PreviewPane`, `ImageBox`); od **14, 22, 29 i 33** — ekran ustawień, wzorzec
  pamięci przewijania pod kluczem, miejsce zauważenia długu C i rozmiar okna,
  bez którego dług C byłby nieosiągalny; od **38** — wzorce i przebiegi
  w trzech torach.

  Kroki **42, 43 i 44** zależą od niego **miękko, ale realnie**: wykonany
  wcześniej daje ich czynnościom drogę do menu i do okien z komend za darmo.
  Wykonany później znaczy trzykrotną przeróbkę tego samego miejsca — i to jest
  cały powód, dla którego numer i kolejność się tu rozjeżdżają.

Żaden krok nie da się sensownie zacząć przed ukończeniem swoich zależności
z tabel powyżej.

## Gdzie leżą pliki kroków

Katalog `docs/plans/` trzyma **wyłącznie kroki, przed którymi jeszcze praca**:
nierozpoczęte, w toku i zablokowane. Kroki ukończone przenoszą się do
[archiwum/](archiwum/) — dziś jest ich 41 z 47, więc bez tego podziału lista
tego, co zostało do zrobienia, ginęła w historii projektu.

Trzy rzeczy, które przy tym **nie** zmieniają miejsca, bo są dokumentami
żywymi, a nie zamkniętymi: ten indeks, [00-decyzje.md](00-decyzje.md) i tabele
poniżej. Indeks pokazuje więc dalej **wszystkie** kroki w jednym miejscu wraz
z pełnym grafem zależności — zmieniły się w nim wyłącznie ścieżki odnośników.
Wskaźniki z `CLAUDE.md`, `SKILL.md`, `README.md` i z komentarzy w `src/`
prowadzą do tych dwóch plików, więc archiwizacja kroku nie dotyka ani jednego
z nich.

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
5. **Przenieś plik kroku do [archiwum/](archiwum/)** i popraw odnośnik w tabeli
   powyżej na `archiwum/NN-nazwa.md`. Odnośniki **wewnątrz** przenoszonego pliku
   trzeba przy tym przeliczyć: do `00-index.md`, `00-decyzje.md` i do kroków
   nieukończonych sięga się odtąd przez `../`, poza katalog planów — przez
   `../../`, a odnośniki do kroków już zarchiwizowanych zostają bez zmian.
   Dotyczy to również kroków **ukończonych z zastrzeżeniem**: zastrzeżenie
   opisuje granicę dowiezionego zakresu, a nie dług do spłacenia w tym pliku.

## Zakres poza MVP (do rozważenia w kolejnych iteracjach)

- ~~Operacje na plikach (kopiuj / przenieś / usuń / zmień nazwę / nowy katalog)~~
  — weszły do planu jako **Faza XIV**, kroki **41–44** (D66); wstęp dowiozły
  kroki 24 (dwa panele) i 28 (okno potwierdzenia)
- ~~Podgląd plików tekstowych~~ — wszedł do planu jako krok **29** (D48)
  i **został wykonany**; pozycja jest zamknięta od 2026-08-12
- ~~Przewijanie ekranu ustawień~~ — zakładka dłuższa od okna gubi dziś pozycje
  zamiast je przewijać (`Slot::fixed()`, reguła 11e). Zauważone w kroku 29, gdy
  zakładka `file-info` urosła do dziesięciu pozycji (dziś ma jedenaście);
  zachowanie jest starsze. **Weszło do planu jako krok 47** (D77)
- Rozpoznawanie UTF-32 bez znacznika kolejności bajtów — świadomie pominięte
  w kroku 29 (kodowanie z BOM-em i UTF-16 bez BOM-u są obsługiwane)
- ~~Widok dwupanelowy~~ — wszedł do planu jako krok **24** (D43)
- ~~Wyszukiwanie / filtrowanie~~ — weszło do planu jako krok **30** (D48)
  i **zostało wykonane**; pozycja jest zamknięta od 2026-08-12
- Podświetlanie dopasowania w podglądzie tekstu — prymityw `TextMark` powstał
  tak, żeby było możliwe, ale odbiorcą kroku 30 była lista; `TextView` filtra nie
  ma
- Podświetlanie dopasowania w drzewie — z tego samego powodu i wykluczone
  z kroku 31: zakresy dopasowania niesie `TableRow`, a wiersz drzewa jest
  `ListRow`em. Filtr **działa** w drzewie na jego pierwszym poziomie, tylko bez
  podświetlenia
- Zakładki / historia odwiedzonych katalogów
- Kolorowanie składni w podglądzie tekstu — wyłączone z kroku 29
- Sortowanie listy po kolumnie — wyłączone z kroku 27
- ~~Zaznaczenie wielokrotne~~ — wyłączone z kroku 32, weszło do planu jako krok
  **43** (D66)
- Zaznaczanie zakresem (`Shift`+strzałki) — wyłączone z kroku 43, bo `Shift` nie
  istnieje w słowniku wejścia; wchodzi wraz z krokiem **44**, jeśli jego
  rozstrzygnięcie startowe nr 1 wybierze wariant z modyfikatorem
- Widok kosza i jego opróżnianie — wyłączone z kroku 44 (kosz jest katalogiem,
  więc `browser.jump` dowozi go za darmo)
- Prawa dostępu i właściciel (`chmod`, `chown`) — wyłączone z kroku 41
- Zdarzenia myszy w oknie GLFW — wyłączone z kroków 34–35 (aplikacja nie ma
  słownika zdarzeń myszy w żadnej warstwie; osobna decyzja, jeśli w ogóle)
- Przeliczenie komórki przez skalę treści (HiDPI) — wyłączone z kroku 37
  (rozstrzygnięcie nr 4, D67): maszyna projektu ma skalę 1.0, więc kodu nie ma
  na czym sprawdzić. Odczytana wartość jest już widoczna w zakładce „Aplikacja”
  okna pomocy — pozycja czeka na sprzęt, nie na pomysł
- Ikona podawana oknu wprost — niemożliwa, dopóki PHP-GLFW nie wystawi
  `glfwSetWindowIcon` (w 2.2.0 funkcji nie ma). Krok 37 obszedł brak wpisem
  `.desktop` dopasowywanym po `WM_CLASS`
- ~~Moduł odtwarzacza muzyki (playlisty, przeglądanie plików audio)~~ — wyłączony
  z kroku 36, wszedł do planu jako **Faza XV**, kroki **45–46** (D71); pasek
  postępu utworu i przewijanie zostają wykluczone także tam
- ~~Dźwięki interfejsu (klik, błąd, powiadomienie)~~ — wyłączone z kroku 36,
  weszły do planu jako krok **46** pod nazwą „efekty specjalne”
- Autostart muzyki — wykluczony z kroku 36 (D70, rozstrzygnięcie 5), bo kontrakt
  modułu nie znał cyklu życia. **Faza XV ten warunek znosi**: gdy moduł dostanie
  takt (krok 45), autostart będzie kosztował jedną pozycję ustawień — decyzja do
  podjęcia przy tamtym kroku
- Ściszanie muzyki na czas efektu (ducking) — wykluczone z kroku 46
- Inicjalizacja repozytorium git
