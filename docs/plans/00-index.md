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
| 26 | Proces tłowy jako mechanizm rdzenia | [26-proces-tlowy.md](26-proces-tlowy.md) | 25 | Opus | high | Nie rozpoczęty |

### Dokumenty towarzyszące (praca projektowa)

| Krok | Plik | Aktualizowany | Model | Wysiłek | Status |
|------|------|----------------|-------|---------|--------|
| Dziennik decyzji i utrzymanie planu | [00-decyzje.md](00-decyzje.md) | równolegle od kroku 1 | Sonnet 5 | Extra High | W toku |

## Graf zależności

Kolejność realizacji pokrywa się z numeracją (01…26). Poza prostym
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
  krok 24 dowiózł do nich wstęp: dwupanelową przeglądarkę
- Podgląd plików tekstowych
- ~~Widok dwupanelowy~~ — wszedł do planu jako krok **24** (D43)
- Wyszukiwanie / filtrowanie
- Zakładki / historia odwiedzonych katalogów
- Inicjalizacja repozytorium git
