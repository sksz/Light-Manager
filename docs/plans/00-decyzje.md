# Dziennik decyzji i uzasadnień

Dokument towarzyszący całemu planowi (zobacz [00-index.md](00-index.md)).
Aktualizowany na bieżąco — każda nietrywialna decyzja podjęta w trakcie
realizacji dowolnego kroku powinna trafić tutaj wraz z uzasadnieniem i
alternatywami, które odrzucono.

Model / wysiłek dla prowadzenia tego dokumentu: **Sonnet 5 / Extra High**
(patrz D5) — dziennik decyzji oraz plik [00-index.md](00-index.md) to
„prace projektowe” w odróżnieniu od kroków 01–08, w których powstaje kod.

## Format wpisu

Każda decyzja: **Data**, **Dotyczy kroku**, **Decyzja**, **Uzasadnienie**,
**Odrzucone alternatywy**.

Nowy wpis wymaga **dwóch** czynności, nie jednej: treści w odpowiedniej sekcji
chronologicznej **oraz wiersza w [indeksie](#indeks-decyzji)** poniżej. Numer
nadaje się kolejny wolny — a przed nadaniem sprawdza, czy nie jest już zajęty:
dziennik pisało wiele sesji równolegle i numer zdublował się już dwa razy
(patrz uwagi pod indeksem).

## Indeks decyzji

Spis wszystkich wpisów dziennika w kolejności, w jakiej stoją w pliku.
**Utrzymywany ręcznie: każda nowa decyzja dopisuje tu wiersz** — indeks bez
tego zapisu rozjeżdża się z treścią i przestaje być wart czytania.

Kolumna **Stan** mówi, co się z decyzją stało w kodzie:

| Stan | Znaczenie |
|---|---|
| **Wdrożona** | krok, którego decyzja dotyczy, jest ukończony — ustalenie leży w kodzie |
| **W realizacji** | krok jest w toku |
| **Czeka** | krok nierozpoczęty; decyzja obowiązuje, ale nie ma jeszcze śladu w kodzie |
| **Obowiązuje** | reguła stała, nieprzypisana do jednego kroku (model prac projektowych, wykluczony terminal, obejście narzędziowe) |
| **Nieaktualna** | rzeczywistość rozeszła się z decyzją — powód stoi przy samym wpisie |

| # | Decyzja | Dotyczy | Data | Stan |
|---|---------|---------|------|------|
| [D1](#d1--zakres-pierwszej-iteracji-minimalny) | Zakres pierwszej iteracji: Minimalny | całość planu | 2026-08-07 | Obowiązuje |
| [D2](#d2--wejście-z-klawiatury-tryb-surowy-single-key) | Wejście z klawiatury: tryb surowy (single-key) | krok 6 | 2026-08-07 | Wdrożona |
| [D3](#d3--obsługa-sixela-wykrywanie-w-runtime--fallback) | Obsługa Sixela: wykrywanie w runtime + fallback | kroki 7, 8 | 2026-08-07 | Wdrożona |
| [D4](#d4--brak-repozytorium-git-na-razie) | Brak repozytorium git na razie | organizacja projektu | 2026-08-07 | Nieaktualna |
| [D5](#d5--podniesienie-minimalnego-wysiłku-i-model-dla-prac-projektowych) | Podniesienie minimalnego wysiłku i model dla prac projektowych | model i wysiłek | 2026-08-07 | Obowiązuje |
| [D6](#d6--pełne-domain-driven-design) | Pełne Domain-Driven Design | krok 1 | 2026-08-07 | Wdrożona |
| [D7](#d7--wzorzec-singleton-per-usługa-bez-centralnego-kontenera) | Wzorzec Singleton per usługa, bez centralnego kontenera | krok 2 | 2026-08-07 | Wdrożona |
| [D8](#d8--ustalenia-trafiają-do-dokumentacji-projektu-i-do-claude-code-skill) | Ustalenia trafiają do dokumentacji projektu i do Claude Code Skill | krok 4 | 2026-08-07 | Wdrożona |
| [D9](#d9--plan-architektury-i-stylu-wstawiony-na-początku-z-przenumerowaniem) | Plan architektury i stylu wstawiony na początku, z przenumerowaniem | numeracja planu | 2026-08-07 | Wdrożona |
| [D10](#d10--kluczowe-klasyfikacje-taktyczne-ddd-i-struktura-portów) | Kluczowe klasyfikacje taktyczne DDD i struktura portów | krok 1 | 2026-08-07 | Wdrożona |
| [D11](#d11--kształt-wzorca-singleton-reset-w-testach-orkiestracja-bootstrapu) | Kształt wzorca Singleton, reset w testach, orkiestracja bootstrapu | krok 2 | 2026-08-07 | Wdrożona |
| [D12](#d12--wersja-php-i-hierarchia-wyjątków-domenowych) | Wersja PHP i hierarchia wyjątków domenowych | krok 3 | 2026-08-07 | Wdrożona |
| [D13](#d13--claudemd-jako-dodatkowe-zabezpieczenie-obok-skilla) | CLAUDE.md jako dodatkowe zabezpieczenie obok Skilla | poza krokami | 2026-08-07 | Obowiązuje |
| [D14](#d14--nazewnictwo-pakietuskryptu-i-zawężenie-szkieletu-do-pustych-katalogów) | Nazewnictwo pakietu/skryptu i zawężenie szkieletu do pustych katalogów | krok 5 | 2026-08-07 | Wdrożona |
| [D15](#d15--obejście-sigsegv-composera-przy-załadowanych-imagick-i-openswoole) | Obejście SIGSEGV Composera przy załadowanych `imagick` i `openswoole` | poza krokami | 2026-08-07 | Obowiązuje |
| [D16](#d16--reprezentacja-klawisza-wyjątki-infrastructure-testowalność-wejścia) | Reprezentacja klawisza, wyjątki Infrastructure, testowalność wejścia | krok 6 | 2026-08-07 | Wdrożona |
| [D17](#d17--rozdział-możliwości-imagicka-od-terminala-surowe-io-poza-portem) | Rozdział możliwości Imagicka od terminala, surowe I/O poza portem | krok 7 | 2026-08-07 | Wdrożona |
| [D18](#d18--rozmiar-płótna-przejęcie-ekranu-i-jakość-tekstu) | Rozmiar płótna, przejęcie ekranu i jakość tekstu | krok 8 | 2026-08-07 | Wdrożona |
| [D19](#d19--stały-takt-sygnał-jako-znacznik-i-kształt-punktu-wejścia) | Stały takt, sygnał jako znacznik i kształt punktu wejścia | krok 9 | 2026-08-07 | Wdrożona |
| [D20](#d20--zachowania-nawigacji-okienko-file-i-czas-życia-komunikatu) | Zachowania nawigacji, okienko `file` i czas życia komunikatu | krok 10 | 2026-08-07 | Wdrożona |
| [D21](#d21--kształt-klatki-miejsce-przewijania-i-wygląd-listy) | Kształt klatki, miejsce przewijania i wygląd listy | krok 11 | 2026-08-08 | Wdrożona |
| [D22](#d22--rozmiar-płótna-sixel-wobec-okna-terminala) | Rozmiar płótna Sixel wobec okna terminala | uruchomienie pod XTermem | 2026-08-08 | Obowiązuje |
| [D23](#d23--gnome-terminal-wykluczony-z-trybu-graficznego) | gnome-terminal wykluczony z trybu graficznego | poza krokami | 2026-08-08 | Obowiązuje |
| [D24](#d24--miejsce-warstwy-i-granice-podglądu-miniatur) | Miejsce, warstwy i granice podglądu miniatur | krok 12 | 2026-08-08 | Wdrożona |
| [D25](#d25--paleta-grafit-układ-panelowy-i-uproszczony-fallback) | Paleta Grafit, układ panelowy i uproszczony fallback | krok 13 | 2026-08-08 | Wdrożona |
| [D26](#d26--konfiguracja-w-pliku-i-ekran-ustawień-w-aplikacji) | Konfiguracja w pliku i ekran ustawień w aplikacji | krok 14 | 2026-08-08 | Wdrożona |
| [D27](#d27--progi-układu-paleta-sixela-i-wygładzanie) | Progi układu, paleta Sixela i wygładzanie | krok 13 | 2026-08-08 | Wdrożona |
| [D28](#d28--miejsce-instrumentacja-i-forma-wyników-narzędzi-pomiarowych) | Miejsce, instrumentacja i forma wyników narzędzi pomiarowych | krok 16 | 2026-08-08 | Wdrożona |
| [D29](#d29--zakres-optymalizacji-takt-pętli-kształt-wiersza-i-cel-kroku) | Zakres optymalizacji: takt pętli, kształt wiersza i cel kroku | krok 17 | 2026-08-08 | Wdrożona |
| [D30](#d30--kształt-mechanizmu-modułów-źródło-kontrakt-ekrany-i-granice) | Kształt mechanizmu modułów: źródło, kontrakt, ekrany i granice | krok 20 | 2026-08-09 | Wdrożona |
| [D31](#d31--klawisze-funkcyjne-trzy-ekrany-granica-wyjątków-konfiguracji) | Klawisze funkcyjne, trzy ekrany, granica wyjątków konfiguracji | krok 14 | 2026-08-09 | Wdrożona |
| [D32](#d32--miejsce-napisów-komunikaty-wyjątków-i-wybór-języka) | Miejsce napisów, komunikaty wyjątków i wybór języka | krok 15 | 2026-08-09 | Wdrożona |
| [D33](#d33--rozbicie-potoku-miejsce-wzorców-i-granica-co-jest-napisem) | Rozbicie potoku, miejsce wzorców i granica „co jest napisem” | krok 16 | 2026-08-09 | Wdrożona |
| [D34](#d34--segmenty-wiersza-paleta-motywu-pamięci-podręczne-i-takt-pętli) | Segmenty wiersza, paleta motywu, pamięci podręczne i takt pętli | krok 17 | 2026-08-09 | Wdrożona |
| [D35](#d35--komponenty-i-okno-komend-przed-modułami-przenumerowanie-kroków) | Komponenty i okno komend przed modułami: przenumerowanie kroków | kolejność planu | 2026-08-09 | Wdrożona |
| [D36](#d36--komponenty-interfejsu-kontrakt-płaszczyzny-układ-i-granice-warstw) | Komponenty interfejsu: kontrakt, płaszczyzny, układ i granice warstw | krok 18 | 2026-08-09 | Wdrożona |
| [D37](#d37--paleta-hybrydowa-dla-klatki-z-miniaturą) | Paleta hybrydowa dla klatki z miniaturą | korekta D34 | 2026-08-09 | Wdrożona |
| [D38](#d38--kontrakt-modułu-po-komponentach-dwie-warstwy-ctrl-w-wejściu-napisy-modułu) | Kontrakt modułu po komponentach: dwie warstwy, `Ctrl` w wejściu, napisy modułu | krok 20 | 2026-08-09 | Wdrożona |
| [D39](#d39--okno-komend-kontrakt-komendy-czynna-płaszczyzna-modalna-i-pole-tekstowe) | Okno komend: kontrakt komendy, czynna płaszczyzna modalna i pole tekstowe | krok 19 | 2026-08-09 | Wdrożona |
| [D58](#d58--karetka-wędrówka-klawisza-przy-oknie-nakładanym-i-pamięć-podpowiedzi) | Karetka, wędrówka klawisza przy oknie nakładanym i pamięć podpowiedzi | krok 19 | 2026-08-09 | Wdrożona |
| [D40](#d40--menadżer-plików-jako-moduł-domyślny-rdzeń-przestaje-wiedzieć-o-plikach) | Menadżer plików jako moduł domyślny: rdzeń przestaje wiedzieć o plikach | krok 21 | 2026-08-09 | Wdrożona |
| [D41](#d41--moduły-trzy-rodzaje-zakładek-ustawienia-surowe-w-pliku-i-miejsce-komendy-modułu) | Moduły: trzy rodzaje zakładek, ustawienia surowe w pliku i miejsce komendy modułu | krok 20 | 2026-08-09 | Wdrożona |
| [D42](#d42--przeglądarka-jako-moduł-trzy-strefy-ekranu-wyjątek-przedstawiający-się-sam-i-dno-stosu-z-konfiguracji) | Przeglądarka jako moduł: trzy strefy ekranu, wyjątek przedstawiający się sam i dno stosu z konfiguracji | krok 21 | 2026-08-10 | Wdrożona |
| [D43](#d43--pełny-obraz-pliku-wymusza-trzy-komponenty-rdzenia-przenumerowanie-kroków-2225) | Pełny obraz pliku wymusza trzy komponenty rdzenia: przenumerowanie kroków 22–25 | poza krokami | 2026-08-10 | Wdrożona |
| [D44](#d44--pasek-postępu-takt-bez-wymuszania-zegar-dla-ekranu-i-pierwszy-użytkownik-weightfill) | Pasek postępu: takt bez wymuszania, zegar dla ekranu i pierwszy użytkownik `Weight::Fill` | krok 23 | 2026-08-10 | Wdrożona |
| [D45](#d45--podział-ekranu-należy-do-modułu-dwa-panele-w-jednym-ekranie-oprawa-w-płaszczyźnie-spodniej) | Podział ekranu należy do modułu: dwa panele w jednym ekranie, oprawa w płaszczyźnie spodniej | krok 24 | 2026-08-10 | Wdrożona |
| [D46](#d46--praca-dłuższa-od-klatki-kawałek-na-klatkę-na-żądanie-z-właścicielem--a-proces-potomny-osobnym-krokiem) | Praca dłuższa od klatki: kawałek na klatkę, na żądanie, z właścicielem — a proces potomny osobnym krokiem | kroki 25, 26 | 2026-08-10 | Wdrożona |
| [D47](#d47--proces-tłowy-jedna-praca-z-uchwytem-sprzątanie-dwiema-drogami-a-du-tylko-dla-katalogów) | Proces tłowy: jedna praca z uchwytem, sprzątanie dwiema drogami, a `du` tylko dla katalogów | krok 26 | 2026-08-11 | Wdrożona |
| [D48](#d48--sześć-nowych-komponentów-rdzenia-rytm-jeden-komponent--jeden-krok-i-otwarcie-zamkniętego-słownika-prymitywów) | Sześć nowych komponentów rdzenia, rytm „jeden komponent — jeden krok” i otwarcie zamkniętego słownika prymitywów | Faza VII (27–32) | 2026-08-11 | Wdrożona |
| [D49](#d49--wiersz-wielokolumnowy-jedna-reguła-podziału-na-dwie-osie-tabela-obok-listy-i-stat-zamiast-dwóch-pytań) | Wiersz wielokolumnowy: jedna reguła podziału na dwie osie, tabela obok listy i `stat` zamiast dwóch pytań | krok 27 | 2026-08-11 | Wdrożona |
| [D50](#d50--krok-33-reakcja-na-zmianę-rozmiaru-okna-wchodzi-do-planu-jako-osobna-faza-viii) | Krok 33: reakcja na zmianę rozmiaru okna wchodzi do planu jako osobna Faza VIII | krok 33 | 2026-08-11 | Wdrożona |
| [D51](#d51--zmiana-rozmiaru-okna-prawdziwe-zapytanie-o-piksele-samoodświeżanie-bez-zmian-w-kontraktach) | Zmiana rozmiaru okna: prawdziwe zapytanie o piksele, samoodświeżanie bez zmian w kontraktach | krok 33 | 2026-08-11 | Wdrożona |
| [D52](#d52--prezentacja-poza-terminalem-natywny-opengl-przez-php-glfw-faza-ix-z-dwoma-krokami) | Prezentacja poza terminalem: natywny OpenGL przez PHP-GLFW, Faza IX z dwoma krokami | kroki 34, 35 | 2026-08-11 | Wdrożona |
| [D53](#d53--okno-glfw-flaga---window-renderermodeopengl-port-wejścia-pod-neutralną-nazwą-rozmiar-startowy-z-ustawień) | Okno GLFW: flaga `--window`, `RendererMode::OpenGl`, port wejścia pod neutralną nazwą, rozmiar startowy z ustawień | krok 34 | 2026-08-11 | Wdrożona |
| [D54](#d54--renderer-okienkowy-api-wektorowe-rozszerzenia-font-systemowy-natywne-ładowanie-tekstur-pomiar-w-render-bench) | Renderer okienkowy: API wektorowe rozszerzenia, font systemowy, natywne ładowanie tekstur, pomiar w render-bench | krok 35 | 2026-08-11 | Wdrożona |
| [D55](#d55--odtwarzanie-muzyki-przez-glaudio-osobna-faza-x-z-jednym-krokiem) | Odtwarzanie muzyki przez `GL\Audio`: osobna Faza X z jednym krokiem | krok 36 | 2026-08-11 | Czeka |
| [D56](#d56--okno-potwierdzenia-decyzja-wraca-domknięciem-esc-znaczy-nie-wariant-groźny-wchodzi-od-razu) | Okno potwierdzenia: decyzja wraca domknięciem, `Esc` znaczy „nie”, wariant groźny wchodzi od razu | krok 28 | 2026-08-12 | Wdrożona |
| [D57](#d57--faza-ix-dostaje-trzeci-krok-dopracowanie-okna) | Faza IX dostaje trzeci krok: dopracowanie okna | krok 37 | 2026-08-12 | Czeka |
| [D58](#d58--podgląd-tekstu-czyta-jak-edytor-okno-po-bajtach-kaskada-rozpoznania-alt-w-słowniku-wejścia) | Podgląd tekstu czyta jak edytor: okno po bajtach, kaskada rozpoznania, `Alt` w słowniku wejścia | krok 29 | 2026-08-12 | Wdrożona |
| [D59](#d59--ósmy-prymityw-jest-napisem-na-tle-filtr-mieszka-w-panelu-a-esc-odmawia) | Ósmy prymityw jest napisem na tle, filtr mieszka w panelu, a `Esc` odmawia | krok 30 | 2026-08-12 | Wdrożona |
| [D60](#d60--podgląd-tekstu-dostaje-ognisko-a-przewijanie-liczy-się-w-linijkach-panelu) | Podgląd tekstu dostaje ognisko, a przewijanie liczy się w linijkach panelu | kroki 25, 29 | 2026-08-12 | Wdrożona |
| [D61](#d61--diagnostyka-benchmark-i-testy-funkcjonalne-wchodzą-do-planu-jako-faza-xi-z-krokiem-38) | Diagnostyka, benchmark i testy funkcjonalne wchodzą do planu jako Faza XI z krokiem 38 | krok 38 | 2026-08-13 | Czeka |
| [D77](#d77--trzy-długi-bez-właściciela-wchodzą-do-planu-jako-faza-xvi-z-krokiem-47) | Trzy długi bez właściciela wchodzą do planu jako Faza XVI z krokiem 47 | krok 47 | 2026-08-14 | Wdrożona |
| [D78](#d78--rozstrzygnięcia-startowe-kroku-47-zdolność-zamiast-rejestru-strefa-wychodzi-z-kontraktu-granica-menu-przerysowana) | Rozstrzygnięcia startowe kroku 47: zdolność zamiast rejestru, strefa wychodzi z kontraktu, granica menu przerysowana | krok 47 | 2026-08-14 | Wdrożona |
| [D81](#d81--rozstrzygnięcia-startowe-kroku-44-shift-wchodzi-do-trzech-torów-wejścia-kosz-jest-katalogiem-konfigurowalnym-stos-cofnięć-dostaje-widok) | Rozstrzygnięcia startowe kroku 44: `Shift` wchodzi do trzech torów wejścia, kosz jest katalogiem konfigurowalnym, stos cofnięć dostaje widok | krok 44 | 2026-08-15 | Wdrożona |
| [D84](#d84--praca-na-zdalnym-hoście-wchodzi-jako-faza-xvii-moduł-ssh-ext-ssh2-w-procesie-trzy-kroki) | Praca na zdalnym hoście wchodzi jako Faza XVII: moduł `Ssh`, `ext-ssh2` w procesie, trzy kroki | kroki 48, 49, 50 | 2026-08-15 | Czeka |
| [D85](#d85--kontenery-wchodzą-jako-faza-xviii-dwa-moduły-droga-mieszana-a-współpracę-niosą-komendy-i-nowe-kwerendy) | Kontenery wchodzą jako Faza XVIII: dwa moduły, droga mieszana, a współpracę niosą komendy i nowe kwerendy | kroki 51, 52, 53 | 2026-08-15 | Czeka |
| [D86](#d86--kwerendy-dostają-wszystkie-moduły-a-odbiorcą-tych-bez-konsumenta-w-kodzie-zostaje-użytkownik) | Kwerendy dostają wszystkie moduły, a odbiorcą tych bez konsumenta w kodzie zostaje użytkownik | krok 53 | 2026-08-15 | Czeka |
| [D87](#d87--rozstrzygnięcia-startowe-kroku-48-cała-sesja-w-procesie-potomnym-przez-controlmaster-known_hosts-prowadzi-ssh-rdzeń-rośnie-o-dwie-rzeczy-zamiast-jednej-linii) | Rozstrzygnięcia startowe kroku 48: cała sesja w procesie potomnym przez `ControlMaster`, `known_hosts` prowadzi `ssh`, rdzeń rośnie o dwie rzeczy zamiast jednej linii | krok 48 (i przez nr 1 — Faza XVII w całości) | 2026-08-15 | Wdrożona |
| [D88](#d88--rozstrzygnięcia-startowe-kroku-49-jeden-ekran-w-dwóch-postaciach-kontekst-dostaje-pochodzenie-a-polecenie-którego-wyjściem-jest-treść-nie-scala-strumieni) | Rozstrzygnięcia startowe kroku 49: jeden ekran w dwóch postaciach, kontekst dostaje pochodzenie, a polecenie, którego wyjściem jest treść, nie scala strumieni | krok 49 (i przez nr 11 — rdzeniowy port pracy tłowej) | 2026-08-15 | Wdrożona |

> **Indeks jest niekompletny od D62 wzwyż.** Wpisy **D62–D76** stoją w treści
> dziennika, ale wiersza tutaj nie dostały — regułę „nowy wpis to dwie czynności”
> przestano stosować przy kroku 39 i nikt tego nie zauważył. Wiersz D77 dopisano
> zgodnie z regułą; uzupełnienie brakujących piętnastu jest osobną pracą
> porządkową, bo kolumna „Stan” wymaga przy każdym rozstrzygnięcia, a nie odpisu
> z nagłówka.

**Dwie uwagi do numeracji**, obie wynikłe z tego, że dziennik pisało wiele
sesji równolegle:

- **D2 i D3 powstały przed przenumerowaniem planu** (D9), więc w ich treści
  widnieją stare numery kroków — kolumna „Dotyczy” podaje dzisiejsze
  (stary 01–08 to obecne 05–12).
- **Dwa numery zostały nadane dwa razy i rozdzielone 2026-08-12**: D40
  (karetka przy oknie nakładanym → **D58**, bo nie miała odwołań w kodzie)
  oraz D53 (odtwarzanie muzyki → **D55**, z tego samego powodu). Numer
  D58 stoi przez to poza chronologią — wpis leży tam, gdzie powstał, przy
  kroku 19.

## Decyzje z fazy planowania (2026-08-07)

### D1 — Zakres pierwszej iteracji: Minimalny

**Dotyczy:** całości planu.
**Decyzja:** MVP obejmuje wyłącznie nawigację po katalogach, listę plików,
podgląd miniatur obrazów (Imagick + Sixel) i wyjście z aplikacji.
**Uzasadnienie:** najszybsza droga do działającej pętli gry z realnym
rysowaniem na ekranie — rdzeń architektoniczny (pętla + render) jest tu
ryzykiem technicznym, nie zakres funkcjonalny.
**Odrzucone alternatywy:** zakres Standardowy (+ operacje na plikach,
podgląd tekstu) i Rozbudowany (+ dwupanelowość, wyszukiwanie) — odłożone do
kolejnych iteracji, po zweryfikowaniu fundamentu.

### D2 — Wejście z klawiatury: tryb surowy (single-key)

**Dotyczy:** kroku 02.
**Decyzja:** terminal jest przełączany w tryb raw; wejście odczytywane jest
klawisz po klawiszu, bez czekania na Enter.
**Uzasadnienie:** zgodne z założeniem „architektura jak w grach” —
natychmiastowa reakcja na klawisze (np. strzałki) jest częścią wymagania,
nie tylko szczegółem implementacyjnym.
**Odrzucone alternatywy:** tryb liniowy (komenda + Enter) — prostszy w
implementacji, ale niezgodny z duchem „pętli gry”.

### D3 — Obsługa Sixela: wykrywanie w runtime + fallback

**Dotyczy:** kroków 03 i 04.
**Decyzja:** aplikacja wysyła zapytanie DA1 do terminala przy starcie i na
tej podstawie wybiera renderer (Sixel albo prosty tekstowy/ANSI).
**Uzasadnienie:** Sixel jest wspierany tylko przez część emulatorów
terminala — bez wykrywania aplikacja „milczy” (nic nie wyświetla) na
niewspieranym terminalu, co jest złym doświadczeniem.
**Odrzucone alternatywy:** założenie z góry konkretnego terminala bez
wykrywania — prostsze, ale przenosi ryzyko na użytkownika/dokumentację
zamiast na aplikację.

### D4 — Brak repozytorium git na razie

> **Stan: nieaktualna** (odnotowano 2026-08-12). Repozytorium git w projekcie
> **istnieje** i ma historię — rzeczywistość rozeszła się z tą decyzją bez
> osobnego wpisu. Co z niej zostało w mocy: śledzenie postępu nadal odbywa się
> w plikach `docs/plans/*.md`, a commitowanie nie jest częścią żadnego kroku —
> potwierdzone przez użytkownika 2026-08-12.

**Dotyczy:** organizacji projektu.
**Decyzja:** nie inicjalizujemy repozytorium git w tej iteracji; postęp
śledzony wyłącznie w plikach `docs/plans/*.md`.
**Uzasadnienie:** decyzja użytkownika — pliki planów wystarczają jako
mechanizm śledzenia postępu na tym etapie.
**Odrzucone alternatywy:** `git init` na starcie projektu.

## Decyzje z fazy planowania (2026-08-07, aktualizacja)

### D5 — Podniesienie minimalnego wysiłku i model dla prac projektowych

**Dotyczy:** przydziału modelu/wysiłku w [00-index.md](00-index.md) i w
tym dokumencie.
**Decyzja:** minimalny wysiłek dla kroków implementacyjnych (01–08) podniesiony
do **medium** (dotknęło to kroku 01, wcześniej `low`; kroki 02–08 już
spełniały ten próg). Prace projektowe — utrzymanie [00-index.md](00-index.md)
i tego dziennika decyzji — przydzielone do modelu **Sonnet 5** z wysiłkiem
**Extra High** (wcześniej Haiku/low).
**Uzasadnienie:** decyzja użytkownika — planowanie/dokumentacja architektury
i śledzenie decyzji ma być prowadzone najwyższym dostępnym poziomem
rozumowania (Sonnet 5, Extra High), a żaden krok implementacyjny nie
powinien być traktowany jako trywialny (stąd podłoga na poziomie medium).
**Odrzucone alternatywy:** pozostawienie zróżnicowanych, niższych poziomów
wysiłku (Haiku/low, Sonnet/low) tam, gdzie zadanie wygląda na proste —
odrzucone, bo nawet pozornie proste kroki/dokumenty są częścią fundamentu,
na którym opierają się kolejne etapy.

## Decyzje z fazy planowania architektury i stylu (2026-08-07)

### D6 — Pełne Domain-Driven Design

**Dotyczy:** kroku 01.
**Decyzja:** `src/` stosuje pełny zestaw wzorców taktycznych DDD — encje,
obiekty wartości, agregaty, repozytoria jako interfejsy (implementacje w
Infrastructure), serwisy domenowe odróżnione od aplikacyjnych, jawna reguła
zależności między warstwami.
**Uzasadnienie:** decyzja użytkownika — mimo niewielkiego rozmiaru aplikacji
(pojedyncza domena: przeglądanie/podgląd plików), pełne DDD ma dać czysty,
testowalny rdzeń domenowy odizolowany od terminala/Imagick.
**Odrzucone alternatywy:** lekka warstwa inspirowana DDD (podział
Domain/Application/Infrastructure/Presentation bez pełnego arsenału
taktycznego) — odrzucona na rzecz pełnego DDD.

### D7 — Wzorzec Singleton per usługa, bez centralnego kontenera

**Dotyczy:** kroku 02.
**Decyzja:** każda usługa spoza warstwy Domain (dostęp do terminala,
wykrywanie Sixela, renderowanie) implementuje własny, klasyczny Singleton
(prywatny konstruktor + statyczna `getInstance()`), zamiast jednego
centralnego kontenera/rejestru usług.
**Uzasadnienie:** decyzja użytkownika — świadomie wybrany klasyczny wzorzec
GoF per klasa usługi, mimo że rekomendowaliśmy scentralizowany kontener
(łatwiejsza podmiana zależności w testach).
**Odrzucone alternatywy:** jeden centralny `ServiceContainer`/rejestr
(Service Locator) przechowujący instancje wszystkich usług.
**Mitygacja znanej wady** (trudniejsze testowanie): krok 02 wprowadza
konwencję `resetInstance()` do użytku wyłącznie w testach, bez zmiany samej
decyzji.

### D8 — Ustalenia trafiają do dokumentacji projektu i do Claude Code Skill

**Dotyczy:** kroku 04.
**Decyzja:** wytyczne z kroków 01–03 zostają spisane w `docs/architecture.md`
**oraz** w dedykowanym projektowym Skill dla Claude Code
(`.claude/skills/<nazwa>/SKILL.md`), ładowanym automatycznie w przyszłych
sesjach pracy nad tym repozytorium.
**Uzasadnienie:** decyzja użytkownika — konwencje mają być nie tylko
spisane do przeczytania przez ludzi, ale też automatycznie stosowane przez
AI w kolejnych sesjach bez ręcznego przypominania.
**Odrzucone alternatywy:** wyłącznie dokumentacja projektowa, bez
dedykowanego mechanizmu automatycznego wczytywania przez AI.

### D9 — Plan architektury i stylu wstawiony na początku, z przenumerowaniem

**Dotyczy:** numeracji całego planu w `docs/plans/`.
**Decyzja:** nowy plan „Warstwy DDD, Singleton, styl kodowania, dokumentacja
+ Skill” staje się krokami 01–04, a dotychczasowe kroki 01–08 (szkielet,
terminal I/O, wykrywanie Sixela, render, pętla główna, nawigacja, lista
plików, miniatury) zostały przesunięte na 05–12. Wszystkie zależności i
odniesienia liczbowe wewnątrz plików kroków zostały zaktualizowane.
**Uzasadnienie:** decyzja użytkownika — jeden spójny, liniowy ciąg kroków
jest czytelniejszy niż równoległe tory planów; ustalenia architektoniczne
muszą logicznie poprzedzać budowę szkieletu projektu (krok 05), bo to on ma
je bezpośrednio zastosować.
**Odrzucone alternatywy:** osobny, równoległy zestaw plików referencjonowany
jako prerekwizyt bez zmiany numeracji istniejących kroków; rozszerzenie
istniejącego (wówczas) kroku 01 o te ustalenia zamiast tworzenia nowego,
odrębnego planu.

## Decyzje z realizacji kroku 01 (2026-08-07)

### D10 — Kluczowe klasyfikacje taktyczne DDD i struktura portów

**Dotyczy:** kroku 01 (pełna treść: sekcja „Specyfikacja” w
[01-warstwy-ddd-i-struktura-katalogow.md](archiwum/01-warstwy-ddd-i-struktura-katalogow.md)).
**Decyzja:**
- `Directory` jest **agregatem (korzeniem) i encją** — tożsamość przez
  `DirectoryPath`, mutowalną w miejscu (nie w pełni niemutowalną jak Value
  Objects); agreguje `Entry` (Value Objects) i `Selection`.
- `Entry`, `EntryType`, `Selection`, `RendererMode`, `Frame`,
  `ThumbnailPreview`, `DirectoryPath` to Value Objects (w tym natywne
  `enum`), niemutowalne, samowalidujące się.
- Wprowadzono katalog `Application/Port` na interfejsy portów wyjściowych
  (`TerminalPort`, `RendererModeDetectorPort`, `FrameRendererPort`,
  `ThumbnailGeneratorPort`) — Application zna tylko te interfejsy, nigdy
  konkretnych klas `Infrastructure`.
- `Domain/Repository` zawiera wyłącznie `DirectoryRepositoryInterface`
  (jedyny prawdziwie domenowy przypadek repozytorium w MVP).
- Przyjęto jeden bounded context na potrzeby MVP i najbliższych iteracji.
**Uzasadnienie:** klasyczna, podręcznikowa klasyfikacja DDD (encje bywają
mutowalne, Value Objects nie) dopasowana do jednowątkowej, jednoprocesowej
pętli gry — pełna niemutowalność `Directory` przy każdej zmianie
zaznaczenia byłaby kosztowna bez realnej korzyści w tym kontekście.
Wydzielenie `Application/Port` (zamiast wtłaczania portów I/O do
`Domain/Repository`) odzwierciedla, że renderowanie/terminal/miniatury to
mechanizmy dostarczania, nie pojęcia domenowe.
**Odrzucone alternatywy:** w pełni niemutowalny `Directory` (nowa instancja
przy każdej zmianie zaznaczenia); umieszczenie portów I/O bezpośrednio w
`Domain/Repository` obok `DirectoryRepositoryInterface`.

## Decyzje z realizacji kroku 02 (2026-08-07)

### D11 — Kształt wzorca Singleton, reset w testach, orkiestracja bootstrapu

**Dotyczy:** kroku 02 (pełna treść: sekcja „Specyfikacja” w
[02-wzorzec-singleton-i-bootstrap.md](archiwum/02-wzorzec-singleton-i-bootstrap.md)).
**Decyzja:**
- Wspólny mechanizm Singletona przez **dziedziczenie po klasie
  abstrakcyjnej** `Infrastructure/Support/AbstractSingleton` (late static
  binding, `new static()`), a nie przez trait ani przez pełnie
  niezależną implementację w każdej klasie.
- Konstruktor w `AbstractSingleton` jest **`protected`, nie `private`** —
  techniczna konieczność przy współdzielonej `getInstance()` w klasie
  bazowej; efekt na zewnątrz identyczny (blokada `new` spoza hierarchii).
- Reset instancji w testach wyłącznie przez **Reflection** w pomocniczym
  traicie `tests/Support/ResetsSingletons` — zero publicznego API
  `resetInstance()` w klasach produkcyjnych.
- Kolejność bootstrapu wymuszona jawnie przez klasę orkiestrującą
  `Presentation/Cli/Bootstrap` (nie leniwe samo-okablowanie) — usługa
  trafia do `Bootstrap::boot()` tylko wtedy, gdy jej konstruktor ma efekt
  uboczny wymagany przed pętlą gry; pozostałe (np.
  `ThumbnailGeneratorService`) inicjalizują się leniwie.
**Uzasadnienie:** decyzje użytkownika (dziedziczenie zamiast trait,
Reflection zamiast publicznej metody, jawna orkiestracja zamiast leniwego
samo-okablowania). Dodatkowa korzyść zaobserwowana przy specyfikacji:
dziedziczenie po wspólnej klasie bazowej daje **jedną** współdzieloną
właściwość `$instances`, co upraszcza mechanizm resetu z Reflection do
jednego uchwytu zamiast osobnego per klasa (jak byłoby przy trait).
**Odrzucone alternatywy:** współdzielony `SingletonTrait`; pełna,
niezależna implementacja w każdej klasie usługi; publiczna metoda
`resetInstance()` udokumentowana jako „tylko do testów”; leniwe
samo-okablowanie zależności między usługami bez osobnej klasy
orkiestrującej.

## Decyzje z realizacji kroku 03 (2026-08-07)

### D12 — Wersja PHP i hierarchia wyjątków domenowych

**Dotyczy:** kroku 03 (pełna treść: sekcja „Specyfikacja” w
[03-standardy-stylu-kodowania.md](archiwum/03-standardy-stylu-kodowania.md)).
**Decyzja:**
- Wymagana wersja PHP: **`^8.3`** — zgodna z wersją zainstalowaną lokalnie
  (potwierdzone: `php -v` → 8.3.11), zero tarcia przy starcie kroku 05.
- Wyjątki domenowe dziedziczą po **abstrakcyjnej klasie bazowej**
  `Domain\Exception\DomainException extends \RuntimeException` (nie po
  interfejsie znacznikowym) — spójne z podejściem „dziedziczenie” z kroku
  02. Klasa bazowa jest `abstract`; konkretne wyjątki preferują nazwane
  konstruktory statyczne (`::forPath()` itp.) zamiast składania stringa w
  miejscu rzucenia.
- PHPStan od startu na poziomie `max` (nie ratcheting od niskiego
  poziomu w górę), z polityką punktowych `@phpstan-ignore` jako
  mitygacją ryzyka już odnotowanego w pliku kroku.
**Uzasadnienie:** decyzje użytkownika (PHP 8.3+ zamiast szerszej
kompatybilności 8.1+ lub bardziej nowoczesnego 8.4+; klasa bazowa zamiast
interfejsu). Wersja PHP dobrana pod realne środowisko deweloperskie, żeby
uniknąć niepotrzebnej instalacji. Poziom PHPStan `max`-od-startu wynika z
rozumowania zapisanego już w „Ryzykach” kroku 03 przed jego realizacją.
**Odrzucone alternatywy:** PHP `^8.1` (szersza kompatybilność, ale
niepotrzebna przy braku znanego wymogu wdrożeniowego); PHP `^8.4`
(wymagałoby instalacji nowszego PHP przed krokiem 05); interfejs
znacznikowy `DomainExceptionInterface` zamiast wspólnej klasy bazowej.

## Decyzje z realizacji kroku 04 (2026-08-07)

### D13 — CLAUDE.md jako dodatkowe zabezpieczenie obok Skilla

**Dotyczy:** kroku 04.
**Decyzja:** oprócz `.claude/skills/light-manager-conventions/SKILL.md`
(ustalonego w D8) dodano krótki `CLAUDE.md` w korzeniu repozytorium,
wskazujący na Skill i `docs/architecture.md`.
**Uzasadnienie:** decyzja użytkownika — Skille w Claude Code są ładowane
wg dopasowania opisu do zadania, nie bezwarunkowo w każdej sesji (podobnie
jak Skille dostępne w tej rozmowie, wybierane przez model na podstawie
opisu). `CLAUDE.md` ładuje się bezwarunkowo, więc stanowi tanie
zabezpieczenie na wypadek, gdy opis Skilla nie dopasuje się do nietypowo
sformułowanego polecenia dotyczącego kodu. Technicznie wykracza poza
dosłowny zakres D8 (tam mowa była tylko o Skillu), ale koszt (kilka linii
tekstu) jest znacznie niższy niż ryzyko pominięcia konwencji.
**Odrzucone alternatywy:** wyłącznie Skill, bez `CLAUDE.md` — odrzucone ze
względu na niepewność dopasowania opisu Skilla do każdego możliwego
polecenia.

**Zastrzeżenie dot. weryfikacji — rozstrzygnięte:** krok 04 wymagał
potwierdzenia, że nowa sesja Claude Code widzi Skill na liście dostępnych
skilli, czego nie dało się sprawdzić w sesji, w której Skill powstał (lista
jest ustalana na starcie sesji). Potwierdzone w kolejnej sesji, na starcie
kroku 05: `light-manager-conventions` figuruje na liście skilli i został
poprawnie załadowany przed pracą nad kodem.

## Decyzje z realizacji kroku 05 (2026-08-07)

### D14 — Nazewnictwo pakietu/skryptu i zawężenie szkieletu do pustych katalogów

**Dotyczy:** kroku 05.
**Decyzja:**
- Nazwa pakietu Composera: **`morfeusz/light-manager`** (namespace root
  pozostaje `LightManager\`, zgodnie z krokiem 01).
- Skrypt wejściowy: **`bin/light-manager`** — bez rozszerzenia `.php`, z
  shebangiem `#!/usr/bin/env php` i bitem wykonywalności; uruchamiany
  `./bin/light-manager` (wariant `php bin/light-manager` również działa).
- Krok 05 tworzy w `src/` **wyłącznie puste katalogi**. Pliki bazowe, których
  fizyczne utworzenie kroki 02 i 03 odesłały „do kroku 05”
  (`AbstractSingleton`, `DomainException`, `tests/Support/ResetsSingletons`
  wraz z ich testami), powstaną dopiero razem z pierwszą realną usługą, czyli
  w kroku 06.
**Uzasadnienie:** decyzje użytkownika. Zawężenie zakresu utrzymuje krok 05
przy jego własnej definicji („szkielet i wymagania”, bez logiki) i pozwala
tworzyć klasy bazowe w kontekście pierwszego faktycznego użycia, zamiast
z wyprzedzeniem.
**Odrzucone alternatywy:** `sksz/light-manager` i `lightmanager/light-manager`
jako nazwa pakietu; `bin/lm` oraz `bin/light-manager.php`; utworzenie pełnego
szkieletu bazowego (z testami lub bez) już w kroku 05.
**Konsekwencje odnotowane w kroku 05:** weryfikacja Imagick/Sixel żyje w
`bin/light-manager`, nie w klasie warstwy `Presentation`; PHPStan kończy się
kodem 1 („No files found to analyse.”) do czasu pojawienia się pierwszego
pliku PHP w kroku 06 — konfiguracja została zweryfikowana jako poprawna i nie
wymaga zmiany.

### D15 — Obejście SIGSEGV Composera przy załadowanych `imagick` i `openswoole`

**Dotyczy:** kroku 05 i wszystkich kolejnych kroków instalujących zależności.
**Decyzja:** polecenia Composera pobierające paczki uruchamiamy z
`PHP_INI_SCAN_DIR` wskazującym kopię katalogu `conf.d` pozbawioną
`20-imagick.ini` i `20-openswoole.ini`, wraz z
`--ignore-platform-req=ext-imagick` (wymóg zostaje w `composer.json`,
pomijana jest wyłącznie jego weryfikacja w tym jednym uruchomieniu).
**Uzasadnienie:** w tym środowisku Composer kończy się naruszeniem ochrony
pamięci (kod 139) przy równoległym pobieraniu wielu paczek, gdy oba
rozszerzenia są aktywne — pojedyncza paczka (`phpstan/phpstan`) przechodzi,
`phpunit/phpunit` z 24 zależnościami już nie. Obejście dotyczy wyłącznie
procesu Composera; uruchomienie samej aplikacji wymaga `imagick` włączonego
normalnie.
**Odrzucone alternatywy:** trwałe wyłączenie rozszerzeń w konfiguracji PHP
CLI (zbyt inwazyjne — `imagick` jest potrzebny do działania aplikacji);
instalowanie zależności pojedynczo w nadziei, że każda zmieści się poniżej
progu awarii (zawodne i wolne).

## Decyzje z realizacji kroku 06 (2026-08-07)

### D16 — Reprezentacja klawisza, wyjątki Infrastructure, testowalność wejścia

**Dotyczy:** kroku 06 (pełna treść: sekcja „Specyfikacja zrealizowana” w
[06-terminal-io.md](archiwum/06-terminal-io.md)).
**Decyzja:**
- **Klawisz jako DTO**: `TerminalPort::readKey(): ?KeyPress`, gdzie `KeyPress`
  łączy enum `Key` (strzałki, Home/End/PageUp/PageDown/Delete, Enter,
  Backspace, Tab, Escape, Character, Unknown) z surowymi bajtami. Oba typy
  leżą w `Application/Dto`, **nie** w `Domain/ValueObject` — mimo że enum
  formalnie jest obiektem wartości, klawisz terminala nie jest pojęciem
  domenowym menadżera plików, a tabela warstw z kroku 01 przewiduje dla kroku
  06 „`Domain` nietknięty”.
- **Własna hierarchia wyjątków warstwy Infrastructure**: abstrakcyjna
  `Infrastructure/Support/InfrastructureException extends \RuntimeException`
  plus konkretna `Terminal/TerminalException` z nazwanymi konstruktorami
  statycznymi — symetrycznie do `Domain/Exception/DomainException` z kroku 03.
  Architektura definiowała hierarchię tylko dla `Domain`; to jej domknięcie.
- **Parser wydzielony z usługi**: cała wiedza o sekwencjach escape żyje w
  czystym `KeySequenceParser` (zero I/O, zero stanu), a `TerminalService`
  odpowiada wyłącznie za tryb raw, sygnały i dostarczanie bajtów. Dzięki temu
  najbardziej błędogenna część kroku ma 37 testów jednostkowych, mimo że sama
  usługa wymaga realnego terminala.
- **Brak TTY = wyjątek**: `stream_isatty(STDIN) === false` kończy się
  `TerminalException` i zatrzymaniem aplikacji, zamiast pracy w
  niezdefiniowanym stanie.
**Uzasadnienie:** decyzje użytkownika (wszystkie cztery zgodne z
rekomendacją). Wydzielenie parsera i nazwanie klawiszy już teraz przenosi
koszt do kroku, który i tak jest przydzielony do Opus/high, i zostawia krokom
09–10 gotowy słownik zamiast surowych bajtów.
**Odrzucone alternatywy:** `readKey(): ?string` z surową sekwencją i
nazywaniem klawiszy dopiero w kroku 09/10; gołe `\RuntimeException` w
Infrastructure albo wyjątki przypisane do portu w `Application`; parsowanie
wewnątrz `TerminalService` (bez testów jednostkowych); czytanie klawiszy z
`/dev/tty` przy przekierowanym STDIN; praca bez trybu raw przy braku TTY.

**Rozstrzygnięcia techniczne podjęte w trakcie realizacji** (nie były
przedmiotem osobnego pytania, odnotowane dla porządku): tryb raw jako
`-icanon -echo -ixon min 1 time 0` zamiast pełnego `stty raw`, żeby zachować
`isig` i realny SIGINT po Ctrl+C; wyciszenie ostrzeżenia EINTR z
`stream_select()`, bo trafiałoby wprost na rysowaną klatkę; dopisanie
`ext-pcntl` do `require` w `composer.json`; rozwiązanie błędu PHPStan
„Unsafe usage of new static()” adnotacją `@phpstan-consistent-constructor`
na `AbstractSingleton` zamiast `@phpstan-ignore`.

## Decyzje z realizacji kroku 07 (2026-08-07)

### D17 — Rozdział możliwości Imagicka od terminala, surowe I/O poza portem

**Dotyczy:** kroku 07 (pełna treść: sekcja „Specyfikacja zrealizowana” w
[07-wykrywanie-sixel.md](archiwum/07-wykrywanie-sixel.md)).
**Decyzja:**
- **Możliwości ImageMagick w osobnym Singletonie**
  `Infrastructure/Imagick/ImagickCapabilityService` — `SixelCapabilityService`
  (katalog `Terminal`) pyta go przez `getInstance()`, zamiast sam wywoływać
  `Imagick::queryFormats()`. Katalog `Terminal` nie zaczyna przez to zależeć
  od biblioteki graficznej, a kroki 08 i 12 mają gotowe miejsce na kolejne
  pytania o Imagick. To pierwsza usługa-Singleton, która **nie** implementuje
  żadnego portu aplikacyjnego — jest wewnętrznym współpracownikiem warstwy
  `Infrastructure`.
- **Surowe I/O jako publiczne metody `TerminalService`** (`write()`,
  `readRawBytes()`), **nie** w kontrakcie `TerminalPort`. Port pozostaje
  minimalny (`readKey()`), a `Application` nie dostaje dostępu do surowych
  bajtów, których nie potrzebuje. Rozstrzyga to kwestię pozostawioną otwartą
  na końcu kroku 06.
- **Timeout odpowiedzi DA1: 300 ms**, odpytywanie co 5 ms.
- **Brak rozszerzenia `imagick` to odpowiedź „nie”, a nie wyjątek** —
  `ImagickCapabilityService` pytany o możliwości środowiska zwraca `false`
  zamiast rzucać; twardego wymogu `ext-imagick` pilnuje punkt wejścia w
  `bin/`, więc detekcja nie musi się wywracać.
**Uzasadnienie:** decyzje użytkownika (wszystkie trzy pytania rozstrzygnięte
zgodnie z rekomendacją). Kolejność sprawdzania warunków — najpierw koder
lokalnie, potem terminal — wynika z kosztu: brak kodera przesądza wynik bez
wysyłania czegokolwiek na terminal (potwierdzone w teście: zapytanie DA1
faktycznie nie zostaje wysłane).
**Odrzucone alternatywy:** `Imagick::queryFormats()` wywołane wprost w
`SixelCapabilityService`; zwykła klasa-kolaborator bez Singletona;
rozszerzenie `TerminalPort` o surowe I/O; osobne dotykanie STDIN/STDOUT przez
`SixelCapabilityService` z pominięciem `TerminalService`; timeout 100 ms
(ryzyko fałszywych negatywów przez SSH) i 1 s (sekunda zawieszenia przy każdym
starcie na terminalu bez Sixela).

## Decyzje z realizacji kroku 08 (2026-08-07)

### D18 — Rozmiar płótna, przejęcie ekranu i jakość tekstu

**Dotyczy:** kroku 08 (pełna treść: sekcja „Specyfikacja zrealizowana” w
[08-render-imagick-sixel.md](archiwum/08-render-imagick-sixel.md)).
**Decyzja:**
- **Rozmiar płótna z zapytania o piksele** (`ESC [ 14 t`), tym samym
  mechanizmem co DA1 z kroku 07, z fallbackiem na `stty size` przemnożone przez
  typową komórkę 8×16 px (i dalej na 80×24, gdy `stty` zawiedzie).
- **Alternatywny bufor ekranu** (`ESC [ ? 1049 h`) wraz z ukryciem kursora —
  po wyjściu z aplikacji zawartość powłoki wraca nietknięta. Bufor włącza
  **renderer**, a nie konstruktor `TerminalService`: inaczej narzędzia
  korzystające z samego wejścia traciłyby swoje wypisane wyjście. Wyjście z
  bufora obsługuje `restore()`, więc wszystkie trzy ścieżki przywracania z
  kroku 06 obejmują też ekran.
- **Antyaliasing tekstu domyślnie włączony**, z przełącznikiem odłożonym na
  później — świadomie wybrana jakość kosztem 27 ms na klatkę i trzykrotnie
  większego bloba. Zrealizowane jako jedna, opisana stała `TEXT_ANTIALIAS`
  w `SixelFrameEncoder`, bez budowania systemu konfiguracji „na zapas”.
**Uzasadnienie:** decyzje użytkownika. Przy antyaliasingu użytkownik świadomie
odrzucił wariant najszybszy (92 ms, 38 kB) na rzecz czytelniejszego tekstu i
poprosił o wystawienie przełącznika dopiero wtedy, gdy w projekcie pojawi się
konfiguracja.
**Odrzucone alternatywy:** rozmiar płótna wyłącznie z `stty size` albo na
sztywno; zapis/odtworzenie pozycji kursora zamiast alternatywnego bufora; samo
`ESC [ H` bez przejmowania ekranu; antyaliasing wyłączony od startu.

**Rozstrzygnięcia techniczne kroku 08** (odnotowane dla
porządku, nie były przedmiotem osobnego pytania): kwantyzacja do 16 kolorów
**i** typ paletowy przed enkodowaniem, bo dają 3,6× przyspieszenie (425 ms →
118 ms przy 800×600); pomiar rozmiaru okna przeniesiony z pierwszego
renderowania do konstruktora `RendererService`, żeby timeout zapytania nie
doliczał się do czasu pierwszej klatki; `TerminalService::pushBackBytes()` plus
`strip()` w parserach, żeby czytnik jednego zapytania nie połykał odpowiedzi
drugiego (defekt wykryty pod PTY, opisany w pliku kroku); wybór fontu o stałej
szerokości z listy preferencji zamiast nazwy na sztywno.

## Decyzje z realizacji kroku 09 (2026-08-07)

### D19 — Stały takt, sygnał jako znacznik i kształt punktu wejścia

**Dotyczy:** kroku 09 (pełna treść: sekcja „Specyfikacja zrealizowana” w
[09-petla-glowna.md](archiwum/09-petla-glowna.md)).
**Decyzja:**
- **Stały takt 20 klatek na sekundę w obu trybach renderowania**, z
  zachowaniem „pętla się ślizga”: przekroczenie budżetu 50 ms nie powoduje
  nadrabiania zaległości ani pomijania klatek — kolejna iteracja rusza od razu.
- **Sygnał (SIGINT, SIGTERM, SIGHUP, SIGQUIT) ustawia znacznik, a nie kończy
  procesu.** Wyjście z pętli zawsze przez `break`, tą samą ścieżką co po
  klawiszu `q`. Znacznik jest wystawiony w **`TerminalPort`**
  (`shutdownRequested()`), bo to ta sama usługa rejestruje handlery sygnałów —
  port opisuje więc „terminal jako źródło zdarzeń wejściowych”, a pętla dalej
  dostaje wyłącznie wstrzyknięte porty, bez sięgania po `getInstance()`.
- **Jawny obiekt stanu** `Presentation/Cli/LoopState` trzymany poza `while` i
  podawany przypadkowi użycia — treść zastępcza, mechanizm docelowy.
- **Punkt wejścia: preflight → bootstrap → pętla.** `bin/light-manager`
  sprawdza `ext-imagick` (czytelny komunikat i kod 1), po czym oddaje
  sterowanie. Dotychczasowa diagnostyka z kroku 05 znika — i tak zasłoniłby ją
  alternatywny bufor ekranu.
- **Klawisz wyjścia wyłącznie `q`** — Escape zostaje wolny dla kroku 10, gdzie
  może anulować działanie.
- **`bin/terminal-probe` sprawdza znacznik zamknięcia**, żeby Ctrl+C nadal go
  zamykał po zmianie zachowania sygnałów.
**Uzasadnienie:** decyzje użytkownika. Przy modelu odświeżania użytkownik
świadomie wybrał stały takt mimo przedstawionego pomiaru pokazującego, że w
trybie Sixel 20 kl./s jest nieosiągalne (realnie 15,2 kl./s dla klatki
zastępczej, ~9 kl./s dla pełnej listy) i że pętla zajmie wtedy rdzeń w całości.
**Odrzucone alternatywy:** przerysowanie zdarzeniowe ze znacznikiem zmiany
albo przy każdym klawiszu; trzymanie taktu z pomijaniem zaległych klatek;
stała przerwa po każdej klatce; pozostawienie natychmiastowego `exit()` w
handlerze sygnału; wariant „drugi sygnał kończy natychmiast”; osobny
`ShutdownSignalPort` (jako nowa usługa albo drugi port tej samej usługi);
takt 10 kl./s lub zależny od trybu; stan bez własnego obiektu albo jako DTO w
`Application`; punkt wejścia bez preflightu lub z diagnostyką pod flagą;
`q` razem z Escape.

**Rozstrzygnięcia techniczne kroku 09** (nie były przedmiotem osobnego
pytania): pętla zbiera wszystkie klawisze z jednego taktu zamiast jednego na
iterację, bo przy stałym takcie odczyt pojedynczy gubiłby wejście; zapis na
terminal dopisywany w pętli do skutku po wykryciu uciętej klatki (`fwrite()`
przyjmował 8192 B z ~9,5 kB), z ograniczoną liczbą prób, żeby pętla nie mogła
utknąć w miejscu, którego sygnał już nie przerwie; sprzątanie usunięte z bloku
`catch` w punkcie wejścia, bo przy nieudanym bootstrapie samo rzucało ten sam
wyjątek raz jeszcze.

## Decyzje z realizacji kroku 10 (2026-08-07)

### D20 — Zachowania nawigacji, okienko `file` i czas życia komunikatu

**Dotyczy:** kroku 10 (pełna treść: sekcja „Specyfikacja zrealizowana” w
[10-nawigacja-fs.md](archiwum/10-nawigacja-fs.md)).
**Decyzja:**
- **Sortowanie:** katalogi przed plikami, w obu grupach alfabetycznie **bez
  rozróżniania wielkości liter**, z polskimi znakami tuż przy literach
  podstawowych.
- **Porównywanie nazw:** `Collator` z rozszerzenia `intl`, **gdy jest
  dostępne**; w przeciwnym razie ścieżka awaryjna (małe litery + odwzorowanie
  `ą→a`, `ć→c`…). `intl` **nie** staje się przez to twardym wymogiem w
  `composer.json`.
- **Wpisy ukryte:** domyślnie schowane, z **przełącznikiem na klawisz `.`**.
  Filtr działa w repozytorium, przy odczycie — przełączenie oznacza ponowne
  odczytanie katalogu. Zaznaczenie zostaje na tym samym wpisie (po nazwie), a
  gdy ten zniknął — wraca na początek listy.
- **Klawisze:** góra/dół przesuwają zaznaczenie i **zatrzymują się na krańcach**
  listy; Enter oraz strzałka w prawo wchodzą do katalogu; Backspace oraz
  strzałka w lewo cofają, zaznaczając katalog, z którego wyszliśmy.
- **Enter i strzałka w prawo na wpisie, który nie jest katalogiem:** okienko z
  wynikiem `file -b`, z nazwą wpisu w nagłówku. Okienko jest **modalne** —
  pierwszy dowolny klawisz je zamyka i nic poza tym nie robi.
- **Błąd odczytu katalogu:** nawigacja zostaje na miejscu, a w stanie ląduje
  komunikat. Gasi go naciśnięty klawisz, ale **nie wcześniej niż po
  `3 s + 0,5 s × liczba słów`** komunikatu.
- **Nieczytelny katalog startowy:** aplikacja cofa się w górę drzewa do
  pierwszego katalogu, który da się odczytać, i pokazuje komunikat — zamiast
  kończyć się błędem.
- **Krok 10 nie zmienia niczego na ekranie** — zgodnie z planem dostarcza
  wyłącznie stan i nawigację; rysowanie należy do kroku 11.
**Uzasadnienie:** decyzje użytkownika. Trzy z nich świadomie rozszerzają zakres
kroku poza jego literalny opis (przełącznik wpisów ukrytych, okienko z wynikiem
`file`, cofanie się w górę przy starcie) — plan wymieniał wyłącznie górę, dół,
Enter i cofnięcie.
**Odrzucone alternatywy:** sortowanie wg bajtów albo wspólna lista bez
rozdzielania typów; wpisy ukryte zawsze widoczne lub trwale schowane; `intl`
jako twardy wymóg albo własne uproszczenie bez `intl`; pozycja `..` na liście
zamiast klawisza cofania; sam Backspace; zawijanie zaznaczenia na drugi koniec
listy; filtr ukrytych w agregacie; zaznaczenie po przełączeniu wracające na
pierwszy wpis albo trzymające sam indeks; ciche ignorowanie błędu odczytu i
wchodzenie do nieczytelnego katalogu jako pustego; zaznaczenie po wyjściu w
górę na pierwszym wpisie albo zapamiętywane per katalog (to ostatnie leży poza
MVP); gaszenie komunikatu przy pierwszym klawiszu bez progu czasowego, po
zmianie katalogu albo zegarem; zakończenie aplikacji przy nieczytelnym
katalogu startowym oraz start w katalogu domowym; tymczasowy podgląd stanu na
ekranie i połączenie kroków 10 i 11.

**Rozstrzygnięcia techniczne kroku 10** (nie były przedmiotem osobnego
pytania): bieżący czas wstrzykiwany do `InputHandler::handle()` zamiast
odczytywany w środku, dzięki czemu reguła gaszenia komunikatu jest testowalna
bez zegara; znacznik widoczności wpisów ukrytych przestawiany dopiero po udanym
odczycie; wyjątki domenowe przechwytywane w `InputHandler` i zamieniane na
komunikat; repozytorium katalogów tworzone przez `new` w `Bootstrap`, nie jako
Singleton, bo jest bezstanowe i nie ma efektu ubocznego wymagającego miejsca w
sekwencji startowej.

## Decyzje z realizacji kroku 11 (2026-08-08)

### D21 — Kształt klatki, miejsce przewijania i wygląd listy

**Dotyczy:** kroku 11 (pełna treść: sekcja „Specyfikacja zrealizowana” w
[11-render-listy-plikow.md](archiwum/11-render-listy-plikow.md)).
**Decyzja:**
- **`Frame` niesie wiersze z jawnym stylem** (`list<FrameLine>`, enum
  `LineStyle`). Renderer tłumaczy styl na kolory albo kody ANSI i nie zna ani
  wpisów, ani zaznaczenia — o tym, co jak wygląda, decyduje `Application`.
- **Przewijanie liczone w `Application`**, przez nowy `Application/Port/ViewportPort`
  (liczba wierszy i kolumn), realizowany przez istniejący `TerminalSizeService`.
  Dzięki temu okno przewijania jest czystym, testowalnym kodem, a nie
  zachowaniem widocznym wyłącznie na ekranie.
- **Okno podąża za zaznaczeniem z zapasem 2 wierszy** od krawędzi — lista rusza
  się dopiero, gdy zaznaczenie się do niej zbliży.
- **Zaznaczenie to pasek na pełną szerokość w inwersji**, katalogi mają inny
  kolor **i** końcowy ukośnik, żeby typ wpisu był czytelny także bez kolorów.
- **Rozmiar pliku skrócony z jednostką** i polskim przecinkiem (`1,2 kB`).
- **Układ:** nagłówek u góry (ścieżka + pozycja `26/31` + znacznik `• ukryte`),
  lista pośrodku, komunikat w ostatnim wierszu. Okienko rysowane pośrodku: w
  trybie Sixel jako **prawdziwy prostokąt** z obwódką, w tekstowym jako ramka ze
  znaków rysunkowych.
**Uzasadnienie:** decyzje użytkownika, wszystkie zgodne z rekomendacją.
**Odrzucone alternatywy:** `Frame` z listą stringów i indeksem zaznaczenia albo
trzymający wprost agregat `Directory`; przewijanie w rendererze lub w obiekcie
wartości; oznaczanie zaznaczenia strzałką i katalogów nawiasami; sama inwersja
i sam kolor bez ukośnika; pełna liczba bajtów albo brak rozmiarów; komunikat
pod nagłówkiem zamiast w stopce; okienko na pełny ekran; przewijanie dopiero na
krawędzi albo z zaznaczeniem stale pośrodku; znaki ramki także w trybie Sixel;
nagłówek z samą ścieżką albo bez znacznika wpisów ukrytych.

**Rozstrzygnięcia techniczne kroku 11** (nie były przedmiotem osobnego
pytania): `Popup` przeniesiony z `Application/Dto` do `Domain/ValueObject`, bo
`Frame` jako obiekt wartości `Domain` nie może odwoływać się do `Application` —
alternatywą było zduplikowanie klasy po obu stronach granicy; wysokość wiersza
w `SixelFrameMetrics` wyliczana z podziału płótna przez liczbę wierszy
terminala, żeby lista policzona przez `ViewportPort` mieściła się w klatce;
okno przewijania trzymane jako stan widoku w przypadku użycia i zerowane przy
zmianie katalogu, bo prawdziwy margines wymaga pamiętania poprzedniego
położenia; usunięte pola zastępcze z kroku 09 (`renderedFrames`, `lastKey`).

## Decyzje spoza kroków planu (2026-08-08)

### D22 — Rozmiar płótna Sixel wobec okna terminala

**Dotyczy:** uruchomienia trybu graficznego na XTermie; poza numerowanymi
krokami planu — diagnoza wyszła przy pierwszym uruchomieniu aplikacji na
realnym terminalu, po ukończeniu kroku 11.

**Problem:** klatka przewijała się w górę, nagłówek ze ścieżką wyjeżdżał poza
ekran. Dwie niezależne przyczyny, obie zmierzone:

1. XTerm domyślnie blokuje operacje okienkowe (zasób `allowWindowOps`), a wraz
   z nimi raport `ESC [ 14 t`. Wobec milczenia terminala `TerminalSizeService`
   sięgał po fallback 8×16 px na komórkę, gdy realna komórka miała 6×13 —
   płótno wychodziło o 6 wierszy wyższe niż okno.
2. Nawet z poprawnym pomiarem płótno o wysokości dokładnie okna przewija ekran
   o jeden wiersz, bo terminal po wyrysowaniu obrazu stawia kursor pod nim.
   Zmierzone progi przy oknie 26×13 px: 342 px i 338 px przewijają, 330 px nie.

**Decyzja:**
- **Renderer Sixel rezerwuje ostatni wiersz okna** — płótno ma wysokość
  `TerminalSize::heightPixelsWithoutBottomRow()`. Liczba wierszy siatki
  przekazywana enkoderowi zostaje bez zmian, więc pojemność listy liczona przez
  `ViewportPort` nadal zgadza się z tym, co enkoder rysuje.
- **Fallback rozmiaru komórki zmniejszony z 8×16 na 6×13 px.** Zgadujemy w dół:
  płótno mniejsze od okna zostawia niewykorzystany margines, płótno większe
  zabiera wiersze z góry klatki. 6×13 to komórka domyślnego fontu XTerma.
- **Wymagane zasoby XTerma opisane w README** (`decTerminalID`,
  `maxGraphicSize`, `disallowedWindowOps` bez `14`) i zastosowane w
  `bin/run.sh`.

**Uzasadnienie:** obie poprawki są czystą arytmetyką w warstwie
`Infrastructure` — nie wysyłają nowych sekwencji sterujących i nie zależą od
tego, jak dany terminal interpretuje tryby prywatne.

**Odrzucone alternatywy:** `DECSDM` (`ESC [ ? 80 h`) wyłączający sixel
scrolling — działa (sprawdzone), ale semantyka trybu 80 zmieniała się między
wersjami XTerma i różni się między terminalami; odczyt rozmiaru okna
z `TIOCGWINSZ` (XTerm wypełnia `ws_xpixel`/`ws_ypixel` niezależnie od
`allowWindowOps`) — najdokładniejszy, ale w PHP wymaga `ext-ffi` albo procesu
pomocniczego, czyli rozszerzenia listy wymagań projektu; przeniesienie rezerwy
do `TerminalSizeService`, żeby zmniejszyć raportowaną liczbę wierszy — zabrałoby
wiersz także trybowi tekstowemu, który tego problemu nie ma.

### D23 — gnome-terminal wykluczony z trybu graficznego

**Decyzja:** gnome-terminal nie jest wspieranym terminalem dla trybu
graficznego i nie ma dla niego obejścia konfiguracyjnego.

**Uzasadnienie:** VTE usunęło obsługę Sixela z gałęzi stabilnej w 0.75.90
(commit `e264c6e` z 2024-02-10, „The SIXEL support is not in a releasable state
with important and fundamental problems still unsolved”). Usunięto wtedy m.in.
`sixel-context.cc`, `sixel-parser.hh` i samą opcję meson. W 0.76 — wersji
z Ubuntu 24.04 — zostały zaślepki ABI: `vte_terminal_get_enable_sixel()` ma
w źródle `return false`, a setter puste ciało. Potwierdzone czterema drogami:
dysasemblacją biblioteki, testem runtime przez `python3-gi`, surową odpowiedzią
DA1 (`ESC [ ? 61 ; 1 ; 21 ; 22 c` — bez parametru `4`) i lekturą źródła
pakietu.

**Odrzucone alternatywy:** przebudowa `libvte` z flagą Sixela — nie ma czego
włączać, opcja nie istnieje; podmiana VTE na wersję ≤0.74 — gnome-terminal 3.52
wymaga API ≥0.76, więc trzeba by cofnąć także gnome-terminal; snapshot z gałęzi
`main` — kod rozwojowy w miejsce biblioteki systemowej.

## Decyzje z realizacji kroku 12 (2026-08-08)

### D24 — Miejsce, warstwy i granice podglądu miniatur

**Dotyczy:** kroku 12 (pełna treść: sekcja „Specyfikacja zrealizowana”
w [12-podglad-miniatur.md](archiwum/12-podglad-miniatur.md)).

**Decyzja:**
- **Pas u dołu klatki**, wysoki na 8 wierszy, podążający za zaznaczeniem.
  Lista zachowuje pełną szerokość; pas zabiera jej wiersze, nie kolumny.
  W oknie niższym niż 16 wierszy pas znika w całości.
- **Port w `Application` plus obiekt wartości w `Domain`.** Nowy
  `Application/Port/ImagePreviewPort` odpowiada na pytanie „czy to obraz i
  jakie ma wymiary” samym nagłówkiem pliku; gdy tak — `Frame` niesie
  `Domain/ValueObject/Preview` ze ścieżką i podpisem, a piksele wczytuje
  dopiero renderer. `Domain` zostaje bez pikseli, decyzja „czy jest podgląd”
  daje się przetestować bez Imagicka.
- **Wysokość pasa podaje warstwa aplikacji** (pole `rows` w `Preview`), bo to
  ona odjęła te wiersze liście przy liczeniu przewijania. Renderer nie liczy
  jej po swojemu — wyszedłby mu inny podział niż ten, na którym oparto
  pojemność listy.
- **Rozpoznawanie dwustopniowe:** rozszerzenie jako filtr wstępny (bez I/O),
  potem `pingImage` po wymiary i format.
- **Limity 32 MB pliku i 50 Mpx rozdzielczości**, sprawdzane przed
  dekodowaniem. Po przekroczeniu albo przy błędzie — pusta ramka z powodem
  w środku. W trybie tekstowym zawsze wiersz podpisu.

**Uzasadnienie:** decyzje użytkownika. Pas u dołu wybrany zamiast panelu
bocznego, bo nie wymaga przeliczania szerokości listy, i zamiast okienka po
Enter, bo podgląd ma być widoczny bez naciskania klawisza.

**Odrzucone alternatywy:** panel boczny podążający za zaznaczeniem; okienko
modalne po Enter rozszerzające istniejący `Popup`; wykrywanie i ładowanie
obrazu w samym rendererze (decyzja „czy jest podgląd” wypadłaby z warstwy
testowalnej); `Popup` rozszerzony o ścieżkę obrazu zamiast osobnego obiektu
wartości; rozpoznawanie po samym rozszerzeniu (brak wymiarów do podpisu) albo
po samym pingu (otwieranie archiwów i filmów); sam wiersz tekstu zamiast ramki
oraz ciche pomijanie podglądu.

**Rozstrzygnięcia techniczne kroku 12** (nie były przedmiotem osobnego
pytania): paleta Sixela zależna od treści — 256 kolorów, gdy w klatce leży
bitmapa, 16 w pozostałych przypadkach, bo 16 kolorów robi ze zdjęcia plakat;
dwie osobne pamięci podręczne — wynik pinga w przypadku użycia, przeskalowana
bitmapa w `ThumbnailService` — bo pętla składa klatkę 20 razy na sekundę;
podpowiedź `jpeg:size` przed odczytem, dzięki której dekoder rozpakowuje
zdjęcie od razu w zmniejszonej skali (387 ms → 102 ms na pliku 4000×3000);
pliki otwierane jako uchwyt, nie po nazwie, bo nazwa bywa dla ImageMagicka
poleceniem (prefiks kodera, selektor klatki); wysokość pasa i próg jego
zniknięcia jako stałe w `RenderCurrentFrameUseCase`; pas bez zaznaczonego
obrazu pozostaje niewidoczny, choć jego wiersze są zarezerwowane, żeby lista
nie skakała przy zmianie zaznaczenia.

## Decyzje z planowania kroków 13–14 (2026-08-08)

### D25 — Paleta Grafit, układ panelowy i uproszczony fallback

**Dotyczy:** kroku 13 (pełna treść: [13-motyw-graficzny.md](archiwum/13-motyw-graficzny.md)).

**Kontekst:** kolory z kroku 08 nigdy nie były projektowane — powstały jako
dziewięć stałych w `SixelFrameEncoder`, żeby cokolwiek było widać. Jeden
błękit `#8ab4f8` pełnił cztery role naraz, nagłówek i stopka leżały na tym
samym tle co lista, margines wynosił 4 px. Użytkownik ocenił efekt jako
brzydki i poprosił o propozycje.

**Decyzja:**
- **Paleta „Grafit”** — neutralne, chłodne szarości (`#16181c` tło,
  `#dcdfe4` tekst) z jednym ciepłym akcentem `#d9a441`. Wybrana z czterech
  wariantów przedstawionych jako makiety (Grafit, Nordyk, Papier, Indygo).
- **Układ „HUD”** — każda strefa ekranu (ścieżka, lista, podgląd, stan) to
  osobny panel z obwódką i zaokrąglonymi rogami; nawiasy narożne w
  akcencie, etykieta strefy wpięta w krawędź obwódki, pasek przewijania
  przy liście, zaznaczenie jako stonowana płaszczyzna z akcentowaną
  krawędzią zamiast inwersji.
- **Tryb tekstowy w wariancie uproszczonym** — te same role kolorów i te
  same etykiety stref, ale bez odrysowywania ramek znakami rysunkowymi.

**Uzasadnienie:** decyzje użytkownika. Przy palecie ważył wariant „Indygo”
(najbliższy stanowi dzisiejszemu) i wybrał Grafit — neutralne tło zostawia
paletę Sixela tekstowi zamiast wydawać ją na barwy, a pojedynczy ciepły
akcent niesie hierarchię sam z siebie. Przy trybie tekstowym świadomie
odrzucił pełną parzystość z trybem graficznym (ramki ze znaków `╭ ╮ ╰ ╯`),
przyjmując, że fallback ma być czytelny, a nie identyczny.

**Odrzucone alternatywy:** palety Nordyk (chłodna, niskokontrastowa),
Papier (jasny motyw dzienny) i Indygo (dzisiejszy kierunek dopracowany);
układ „Karty” (strefy jako jaśniejsze płyty rozdzielone szczeliną, bez
obwódek) oraz „Bezel” (jedna rama wokół całego ekranu, w środku linie z
etykietami); tryb tekstowy w pełnej parzystości albo pozostawiony bez
zmian; zmiana samych wartości kolorów bez ruszania układu — odrzucona
wprost przez użytkownika („to tylko zmiana kolorów, nic w stylu nie
poprawiłeś”).

### D26 — Konfiguracja w pliku i ekran ustawień w aplikacji

**Dotyczy:** kroku 14 (pełna treść:
[14-konfiguracja-i-ekran-ustawien.md](archiwum/14-konfiguracja-i-ekran-ustawien.md)).

**Decyzja:**
- **Motyw przełączalny**, nie jeden wpisany na sztywno. Grafit domyślny,
  pozostałe trzy palety zostają w kodzie jako alternatywy.
- **Konfiguracja w pliku JSON** `~/.light-manager/settings.json` — ukryty
  katalog w katalogu domowym użytkownika.
- **Ekran konfiguracyjny w aplikacji** jako sposób zmiany ustawień; ręczna
  edycja pliku pozostaje możliwa, ale nie jest jedyną drogą.

**Uzasadnienie:** decyzja użytkownika. Zamyka jednocześnie dług z D18, gdzie
przełącznik antyaliasingu odłożono jawnie do czasu, aż w projekcie pojawi
się konfiguracja.

**Odrzucone alternatywy:** jeden motyw wpisany w kod (najtańszy, bez nowych
warstw); wybór motywu zmienną środowiskową bez pliku konfiguracyjnego.

**Rozstrzygnięcie porządkowe:** motyw i konfiguracja rozdzielone na dwa
kroki planu (13 i 14) zamiast jednego. Ekran ustawień to drugi tryb
interakcji w pętli głównej i pierwszy trwały stan poza pamięcią procesu —
w jednym kroku z przebudową obu rendererów powstałby krok nieproporcjonalnie
większy od pozostałych.

## Decyzje z realizacji kroku 13 (2026-08-08)

### D27 — Progi układu, paleta Sixela i wygładzanie

**Dotyczy:** kroku 13 (pełna treść: sekcja „Specyfikacja zrealizowana”
w [13-motyw-graficzny.md](archiwum/13-motyw-graficzny.md)).

**Decyzja:**
- **Degradacja układu ustępuje po kolei, zaczynając od pasa podglądu.**
  Panele zostają w każdym oknie, w którym się mieszczą; progi: 26 wierszy
  (pas podglądu), 18 (obwódka ścieżki), 12 (obwódka paska stanu), 8
  (obwódka listy). Lista dostaje zawsze co najmniej jeden wiersz.
- **Pasek stanu jako osobny panel z obwódką** (3 wiersze), wiernie wobec
  makiety, zamiast jednowierszowej belki.
- **Etykiety stref po angielsku** (`PATH`, `FILES`, `PREVIEW`), a
  wielojęzyczność całego interfejsu jako osobny krok planu —
  [krok 15](archiwum/15-wielojezycznosc.md).
- **Paleta Sixela: 64 kolory.**
- **Wygładzanie: tekst bez, obrysy z** — oba jako osobne przełączniki do
  konfiguracji w kroku 14.

**Uzasadnienie:** dwie pierwsze pozycje i etykiety to decyzje użytkownika.
Paleta i wygładzanie zapadły **po pomiarach i po obejrzeniu renderów**, i to
pomiar zmienił ich kształt w trakcie kroku:

- Przy 16 i 32 kolorach kwantyzator poświęca odcień obwódki na rzecz
  liczniejszych pikseli tekstu — **panele znikają z ekranu**. Czas klatki
  jest przy tym praktycznie taki sam w całym zakresie 16–128, więc niższy
  próg niczego nie kupuje.
- Koszt wygładzania, przedstawiony użytkownikowi najpierw łącznie (33 ms i
  potrojony blob), po rozdzieleniu okazał się prawie w całości kosztem
  **tekstu**. Wygładzanie samych obrysów to 3 kB i czas w granicach błędu
  pomiaru — a bez niego łuk o promieniu dziewięciu pikseli jest schodkami,
  czyli zaokrąglenia nie ma wcale. Użytkownik wyłączył wygładzanie na
  podstawie liczby łącznej; po rozdzieleniu obrysy wróciły włączone.

**Odrzucone alternatywy:** zwijanie paneli do linii z etykietą poniżej progu
(wariant „obwódki znikają”); brak degradacji; jednowierszowa belka stanu;
etykiety po polsku albo ich brak; paleta 16, 32 i 128; wygładzanie obu
rodzajów naraz albo żadnego.

**Poprawki wyglądu wykryte przez oglądanie klatki** (nie były przedmiotem
osobnego pytania, ale zmieniły projekt): nawias narożny rysowany jako dwa
proste odcinki **za** promieniem zostawiał łuk obwódce — na ekranie dawało
to kreskę, dziurę i kreskę, czyli róg bez zaokrąglenia; nawias jest teraz
ścieżką z łukiem, grubszą od obwódki, bo to akcent musi nieść kształt rogu.
Obwódka i tło zaznaczenia zostały rozjaśnione (`#2e323a` → `#4a515e`,
`#2a2f38` → `#313845`), bo ton „ledwie jaśniejszy od tła” ginie przy
grubości jednego piksela i kwantyzacji. Etykieta strefy odsunięta od
nawiasu (stała `LABEL_COLUMN`), bo postawione obok siebie czytały się jak
jeden element. Podpowiedzi klawiszy ustępują komunikatowi, gdy zabraknie
miejsca — wcześniej oba napisy nachodziły na siebie.

**Rozstrzygnięcia techniczne kroku 13** (nie były przedmiotem osobnego
pytania): podział okna na strefy przeniesiony do jednego miejsca za portem
`FrameLayoutPort`, bo panele z obwódkami rozjechałyby rachunek prowadzony
osobno w `Application` i w enkoderze; degradacja do szesnastu kolorów ANSI
liczona po **odcieniu** (HSV), a nie po odległości w RGB — euklidesowo
czerwień `#e0645c` leży bliżej średniej szarości niż czerwieni, więc
komunikat o błędzie wychodził szary; szerokości napisów mierzone przez
`queryFontMetrics` i zapamiętywane z limitem wpisów, bo pętla składa klatkę
20 razy na sekundę; promień zaokrąglenia liczony z wysokości wiersza i
ograniczany do połowy krótszego boku panelu.

## Decyzje z planowania kroku 16 (2026-08-08)

### D28 — Miejsce, instrumentacja i forma wyników narzędzi pomiarowych

**Dotyczy:** kroku 16 (pełna treść:
[16-narzedzia-diagnostyczne-wydajnosci.md](archiwum/16-narzedzia-diagnostyczne-wydajnosci.md)).

**Kontekst:** pomiary, na których oparto decyzje kroku 13 (próg palety,
wygładzanie — D27), powstawały przez podmienianie stałych `sed`-em w kodzie
produkcyjnym i wstrzykiwanie znaczników czasu przez `$GLOBALS`. Były
jednorazowe i nie trafiły do repozytorium; jedno przeoczenie tej metody
(nierozdzielenie kosztu tekstu od kosztu obrysów) doprowadziło do decyzji,
którą trzeba było odwrócić.

**Decyzja:**
- **Krok 16, po konfiguracji i wielojęzyczności.** Narzędzia dostają gotowe
  przełączniki z kroku 14 zamiast wprowadzać je same.
- **Instrumentacja przez rozbicie `SixelFrameEncoder::encode()` na jawne
  kroki** (rysowanie → kwantyzacja → kodowanie), mierzone z zewnątrz.
  W kodzie produkcyjnym nie zostaje ani jedno wywołanie pomiarowe.
- **Wyniki jako tabela plus zapisany wzorzec** z trybem porównania
  wykrywającym regresje między krokami planu.

**Uzasadnienie:** decyzje użytkownika. Przy kolejności świadomie przyjęto
dług: wartości domyślne palety i wygładzania wybrane w kroku 14 pochodzą
z doraźnych pomiarów i mają zostać zweryfikowane po kroku 16.

**Odrzucone alternatywy:** krok 14 dla narzędzi, przed konfiguracją (żeby jej
wartości domyślne pochodziły z powtarzalnego pomiaru); port profilera
wstrzykiwany do enkodera; pomiar wyłącznie całości `encode()` z rozbiciem na
fazy zostawionym Xdebugowi/XHProfowi; sama tabela bez wzorca; próg regresji
pilnowany testem — odrzucony, bo przy rozrzucie 184–254 ms dla tej samej
konfiguracji dawałby fałszywe alarmy.

## Decyzje z planowania kroku 17 (2026-08-08)

### D29 — Zakres optymalizacji: takt pętli, kształt wiersza i cel kroku

**Dotyczy:** kroku 17 (pełna treść:
[17-optymalizacja-wydajnosci.md](archiwum/17-optymalizacja-wydajnosci.md)).

**Kontekst:** klatka kosztuje 184 ms przy budżecie 50 ms wynikającym z taktu
20 kl./s (D19), więc pętla nigdy nie zasypia. Rozkład kosztu: ~65% tekst,
~23% kwantyzacja, ~18% chrom.

**Decyzja:**
- **D19 zostaje nietknięte** — każdy takt przerysowuje klatkę, także
  identyczną z poprzednią. Zysk ma pochodzić wyłącznie z potanienia samej
  klatki.
- **`FrameLine` niesie segmenty** (lewy i prawy) zamiast jednego napisu
  dopchniętego spacjami. Dopychanie przenosi się do renderera tekstowego,
  który jako jedyny go potrzebuje.
- **Cel kroku: rząd wielkości, bez twardego progu**, z pomiarem każdej
  dźwigni osobno.

**Uzasadnienie:** decyzje użytkownika. Przy pierwszej świadomie odrzucono
najtańszą pojedynczą zmianę w całym kroku — pomijanie identycznych klatek
sprowadziłoby koszt nieruchomego ekranu do zera — na rzecz zachowania modelu
odświeżania z kroku 09 w całości. Przy trzeciej odrzucono zobowiązanie do
konkretnej liczby, bo czas klatki zależy też od maszyny i rozmiaru okna.

**Odrzucone alternatywy:** pomijanie identycznych klatek przy zachowaniu
stałego taktu; pełne przerysowanie zdarzeniowe; rozbijanie napisu przez
renderer po ciągach spacji (bez zmiany w `Domain`); pozostawienie dopychania
i rezygnacja z tej dźwigni; twardy budżet 50 ms jako kryterium ukończenia;
brak celu liczbowego w ogóle.

**Pomiary wstępne wykonane przy planowaniu** (osobne próbki, płótno
1000×600): wiersze bez dopychania spacjami 104 → 39 ms na trzydzieści
wierszy; `remapImage()` na palecie motywu zamiast `quantizeImage()` 42 →
4,6 ms; składanie zapamiętanych bitmap wierszy zamiast rasteryzacji 25 →
8,2 ms. Zmierzone też trzy ślepe uliczki, opisane w pliku kroku, żeby nikt
do nich nie wracał: złożenie tekstu w jeden `ImagickDraw` (154 wobec
110 ms), `getImageColors()` jako wejście do decyzji (27,8 ms) i sam
`setImageType()` bez kwantyzacji (153 wobec 42 ms).

## Decyzje z planowania kroku 18 (2026-08-09)

> **Uwaga (2026-08-09, później tego samego dnia):** opisywany niżej krok
> „Moduły (plugins)” został przenumerowany na **20**, a przed nim stanęły dwa
> nowe kroki: 18 „Komponenty interfejsu i płaszczyzny” oraz 19 „Okno komend” —
> patrz D35. Treść D30 pozostaje bez zmian; wszystkie odniesienia „krok 18”
> w tym wpisie dotyczą dzisiejszego kroku 20.

### D30 — Kształt mechanizmu modułów: źródło, kontrakt, ekrany i granice

**Dotyczy:** kroku 20, wcześniej 18 (pełna treść:
[20-moduly-plugins.md](archiwum/20-moduly-plugins.md)).

**Kontekst:** projekt ma zyskać punkt, w którym dopisuje się funkcję bez
dotykania rdzenia. Moduł ma cztery punkty zaczepienia: zakładka w oknie
konfiguracji, skrót klawiszowy, zakładka w oknie pomocy, własne okno albo
przejęcie panelu listy plików.

**Decyzja:**

- **Moduły wbudowane w repozytorium** — `src/Module/<Nazwa>/`, jawna lista
  klas w `Bootstrap`. Zero ładowania w runtime; każdy moduł podlega PHPStanowi
  `max`, PHP-CS-Fixerowi i testom tak samo jak rdzeń.
- **Kontrakt jako interfejsy zdolności** — mały `ModuleInterface` (tożsamość i
  metadane) plus osobne, opcjonalne interfejsy. Moduł implementuje wyłącznie
  to, co naprawdę wnosi; rejestr rozpoznaje zdolności przez `instanceof`.
- **Ekrany stają się obiektami** — enum `Screen` z kroku 14 znika, a pętla
  trzyma referencję do aktywnego ekranu. Przeglądarka plików, ustawienia,
  pomoc i ekrany modułów są równorzędne.
- **Moduł ma jeden skrót globalny** otwierający własny ekran; poza nim
  obsługuje klawisze wyłącznie wewnątrz tego ekranu.
- **Kolizja klawisza to błąd modułu** — moduł nie zostaje załadowany, powód
  trafia do paska stanu, a test rejestru łapie kolizję przed użytkownikiem.
- **Ustawienia modułu w podprzestrzeni `modules.<id>`** tego samego pliku
  `~/.light-manager/settings.json`.
- **Moduł przejmuje panel listy w całości** — treść, zaznaczenie, przewijanie
  i klawisze w nim; przełącza się na niego skrótem modułu, `Esc` wraca.
- **Moduł to zwykły obiekt**, tworzony `new`-em w `Bootstrap` z wstrzykniętymi
  portami — nie Singleton i nie wołający `getInstance()`.
- **Awaria modułu w runtime nie dostaje własnej granicy `try/catch`** — moduł
  wbudowany jest tym samym kodem co rdzeń. Wyjątek dotyczy wyłącznie startu:
  nieprawidłowy zestaw modułów jest odsiewany przez rejestr.
- **Okno pomocy powstaje w kroku 18** wraz z paskiem zakładek i zakładkami
  rdzenia; zakładka modułu składa się z części generowanej przez rdzeń z
  deklaracji modułu i z wierszy dopisanych przez sam moduł.
- **Zakładka „Moduły”** w ustawieniach z przełącznikami; stan w
  `modules.<id>.enabled`, skutek po ponownym uruchomieniu.
- **Kontrakt leży w `src/Application/Module/`**, a każdy moduł powtarza
  wewnątrz podział na warstwy DDD.

**Uzasadnienie:** wszystkie pozycje to decyzje użytkownika, podjęte po
przedstawieniu wariantów. Trzy zasługują na odnotowanie skutków:

- Ekrany jako obiekty są najgłębszą zmianą kroku — przebudowują `GameLoop`,
  `InputHandler`, `LoopState` i `RenderCurrentFrameUseCase` — ale są jedynym
  wariantem, w którym rdzeń i moduł są równorzędne, a nie uprzywilejowany
  rdzeń plus doczepiony wyjątek na moduły.
- Zestawienie „jeden skrót globalny” z „moduł przejmuje panel” zwija dwa
  wymagania w jeden mechanizm: skoro ekran z definicji zajmuje środkowy panel,
  każdy ekran modułu już zastępuje treść listy plików. Różnica między własnym
  oknem a alternatywnym widokiem katalogu sprowadza się do tego, czy moduł
  chce dostawać bieżący `Directory` — stąd zdolność `ReadsCurrentDirectory`
  zamiast osobnego `ReplacesFileList`.
- Migracja okienka `file` do pierwszego modułu odbiera `Enter`owi na pliku
  dotychczasowe działanie: opis otwiera się odtąd skrótem modułu. Zmiana jest
  widoczna dla użytkownika i dlatego stoi na pierwszym miejscu listy „Do
  rozstrzygnięcia” w pliku kroku.

**Odrzucone alternatywy:** moduły ładowane w runtime z
`~/.light-manager/modules/` albo jako paczki Composera (odłożone, aż kontrakt
dojrzeje na modułach wbudowanych); jeden szeroki interfejs modułu i
abstrakcyjna klasa bazowa; `Screen` jako obiekt wartości z identyfikatorem
tekstowym oraz `Screen::Module` z osobnym polem; dowolne skróty globalne
modułu i skróty z jawnie deklarowanym zakresem; kolizja rozstrzygana na
korzyść rdzenia po cichu albo na korzyść modułu (przesłonięcie); osobny plik
konfiguracyjny na moduł i klucz modułu na najwyższym poziomie `settings.json`;
podmiana samego wiersza listy przy nawigacji zostawionej rdzeniowi; moduł jako
Singleton — z metodą `boot(ModuleContext)` albo sięgający po zależności
samodzielnie; granica `try/catch` wokół modułu (pełna albo tylko przy składaniu
klatki); okno pomocy jako zakres kroku 14; brak wyłączania modułów i zakładka
„Moduły” wyłącznie informacyjna; zakładka pomocy pisana przez moduł w całości
albo generowana wyłącznie automatycznie; moduł płaski bez wewnętrznych warstw i
`src/Module/` jako piąta warstwa najwyższego poziomu z własnym kontraktem.

**Konsekwencja dla kroku 14, przyjęta świadomie:** `SettingsTab` i
`SettingKey` są dziś enumami z zamkniętą listą przypadków, a moduł nie dołoży
do enuma nic. Krok 18 musi je otworzyć — to poprawka w kodzie kroku 14,
policzona do zakresu kroku 18.

## Decyzje z realizacji kroku 14 (2026-08-09)

### D31 — Klawisze funkcyjne, trzy ekrany, granica wyjątków konfiguracji

**Dotyczy:** kroku 14 (pełna treść: sekcje „Rozstrzygnięcia ze startu kroku”
i „Specyfikacja zrealizowana” w
[14-konfiguracja-i-ekran-ustawien.md](archiwum/14-konfiguracja-i-ekran-ustawien.md)).

**Decyzja:**

- **Ścieżka konfiguracji: jedna, `~/.light-manager/settings.json`.** XDG
  odrzucony — plik ma być zawsze w tym samym miejscu, niezależnie od
  środowiska.
- **Klatka ekranu ustawień to zwykły `Frame` z `FrameLine`.** Pozycja jest
  wierszem „etykieta … wartość”, zaznaczenie stylem `Selected`. Oba renderery
  działają bez żadnej zmiany.
- **Klawisz otwierający: `F2`, a pomoc: `F1`.** Propozycja `,` z planu
  odrzucona.
- **Zmiana liczby kolorów palety działa natychmiast**, jak podgląd motywu.
- **Ekran ustawień ma zakładki** — „Wygląd” i „Grafika”. Pasek zakładek jest
  jednym z miejsc, które odwiedza kursor: `↑`/`↓` przechodzą między nim a
  pozycjami, a `←`/`→` znaczą co innego w każdym z tych dwóch miejsc.
- **Ustawienia i pomoc podmieniają wyłącznie środkowy panel.** Ścieżka u góry i
  pasek stanu u dołu zostają.
- **Stopka przestaje być ściągawką** — `↑↓ ruch · F1 pomoc · F2 ustawienia ·
  q wyjście`. Pełna lista klawiszy mieszka na ekranie pomocy.
- **`q` kończy aplikację na każdym ekranie.** Aplikacja nie ma pola tekstowego,
  które mogłoby tę literę przechwycić, więc jedna reguła zamiast trzech.

**Uzasadnienie:** wszystkie powyższe to decyzje użytkownika, podjęte po
przedstawieniu wariantów. Cztery rozstrzygnięcia techniczne zapadły w trakcie
realizacji i zasługują na odnotowanie, bo zmieniają kod spoza planu:

- **`FrameRendererPort::render()` przyjmuje `Frame` wraz z `FrameLayout`.**
  Renderer liczył układ po raz drugi, po swojej stronie. Dopóki układ zależał
  wyłącznie od rozmiaru okna, dwa rachunki dawały ten sam wynik; od chwili, gdy
  zależy także od pokazywanego ekranu, renderer nie miał z czego go odtworzyć.
  Przy okazji zniknął podwójny rachunek na każdą klatkę.
- **Nic, co pochodzi z konfiguracji, nie jest wstrzykiwane raz przy budowie
  usługi.** Renderery pytają o motyw przy każdej klatce, a enkoder dostaje
  `RenderingOptions` parametrem `encode()` i trzyma je tylko na czas jednej
  klatki. Wstrzyknięcie przez konstruktor wymagałoby restartu po każdej zmianie
  ustawienia — czyli przeczyłoby podglądowi na żywo.
- **`SettingsPort` nie rzuca wyjątków.** Plan przewidywał
  `save(Settings): void` i `ConfigException` dziedziczący po
  `InfrastructureException`. Wyjątek infrastruktury łapany w `Application` albo
  w `Presentation` byłby złamaniem reguły zależności, a nieczytelny plik i
  nieudany zapis i tak mają skończyć jako komunikat w pasku stanu, nie jako
  przerwanie pętli. Port oddaje więc problem opisem (`LoadedSettings::problem`
  i wynik `save()`); `ConfigException` istnieje i działa, ale wyłącznie wewnątrz
  `Infrastructure/Config`.
- **`SettingsPort::load()` przyjmuje listę nazw motywów.** Zakres klucza
  `theme` zna katalog palet, a nie nośnik konfiguracji. Bez tego parametru
  `Infrastructure/Config` musiałoby sięgać do `Infrastructure/Rendering`, albo
  walidacja rozpadłaby się na dwa miejsca. Konsekwencja porządkowa: konfiguracja
  wchodzi do `Bootstrap::boot()` **przed** rendererem, bo odczytana po nim
  zostałaby zapamiętana bez sprawdzenia nazwy palety.

**Odrzucone alternatywy:** XDG (warunkowy i bezwarunkowy); osobny obiekt
wartości opisujący pozycje ustawień wraz z drugą metodą w
`FrameRendererPort`; klawisz `,` i `s`; stosowanie liczby kolorów dopiero po
restarcie; podział na trzy zakładki („Wygląd”, „Grafika”, „Lista”); `←`/`→` do
zakładek przy `Enter`/spacji do wartości; ekrany zajmujące całą klatkę wraz z
paskiem stanu; stopka bez pozycji „wyjście”; obsługa wyłącznie `F1` i `F2` w
parserze zamiast pełnego F1–F12.

**Dług świadomie zaciągnięty:** okno edycji wartości tekstowej. Ustalony model
interakcji przewiduje, że `Enter` na pozycji z polem tekstowym otwiera okienko z
bieżącą wartością (`Enter` zatwierdza, `Esc` anuluje). Żadne z pięciu
dzisiejszych ustawień nie jest polem tekstowym, więc powstałaby ścieżka bez
wywołania i bez sposobu na przetestowanie. Do zrobienia razem z pierwszym takim
ustawieniem.

**Rozbieżność do rozstrzygnięcia z [D30](#d30--kształt-mechanizmu-modułów-źródło-kontrakt-ekrany-i-granice):**
planowanie kroku 18 (tego samego dnia) przyjęło, że **okno pomocy powstaje w
kroku 18**, i wprost odrzuciło wariant „okno pomocy jako zakres kroku 14”.
Polecenie wydane przy starcie kroku 14 mówiło co innego („`F1` dla pełnego okna
pomocy”), więc okno powstało tutaj — w wersji bez paska zakładek. Krok 18 ma
wobec tego **rozbudować** istniejący ekran o pasek zakładek i zakładki modułów,
a nie tworzyć go od zera; treść D30 wymaga w tym punkcie poprawki.

## Decyzje z realizacji kroku 15 (2026-08-09)

### D32 — Miejsce napisów, komunikaty wyjątków i wybór języka

**Dotyczy:** kroku 15 (pełna treść: sekcje „Rozstrzygnięcia ze startu kroku”
i „Specyfikacja zrealizowana” w
[15-wielojezycznosc.md](archiwum/15-wielojezycznosc.md)).

**Decyzja:**

- **Komunikaty wyjątków zostają techniczne i po angielsku**, a użytkownik widzi
  osobny napis, dobierany w `Presentation` **po klasie wyjątku**. Konkrety
  (ścieżka, szczegół awarii) biorą się z **publicznych, typowanych pól**
  wyjątku, nie z rozbierania treści komunikatu. `Domain` nadal nie zna ani
  katalogu napisów, ani wybranego języka.
- **Katalog napisów to tablice PHP** (`lang/pl.php`, `lang/en.php`) z
  **płaskimi kluczami rozdzielonymi kropką** i **nazwanymi** parametrami
  w nawiasach klamrowych.
- **Mechanizm liczby mnogiej wchodzi od razu**, wraz z regułą per język
  (`PluralRule`: dwie formy dla angielskiego, trzy dla polskiego).
- **Wybór języka: ustawienie `language` o domyślnej wartości `auto`**, które
  czyta `LC_ALL`/`LC_MESSAGES`/`LANG`. Wybór zapisany w konfiguracji jest
  mocniejszy od środowiska.
- **Językiem domyślnym i zapasowym jest angielski** — brakujący klucz sięga do
  katalogu `en`, nierozpoznane środowisko również.
- **`intl` pozostaje opcjonalne**: `NumberFormatter`, gdy jest dostępny, w
  przeciwnym razie separator z katalogu. Zasada D20 bez zmian.
- **Droga do napisu zależy od warstwy:** `Domain` — nie sięga wcale;
  `Application` — wyłącznie przez wstrzyknięty `TranslatorPort`;
  `Infrastructure` i `Presentation` — przez `TranslatorService::getInstance()`
  albo wstrzyknięty port. `Application/Dto` przechowuje **klucze**, nie napisy.

**Uzasadnienie:** wszystkie pozycje to decyzje użytkownika, podjęte po
przedstawieniu wariantów. Dwie zasługują na odnotowanie skutków:

- Wariant „techniczny komunikat plus napis dobierany po klasie” jest tańszy od
  „klucza i parametrów w wyjątku”, ale nie rozróżnia awarii, które dzielą jedną
  klasę wyjątku. `TerminalException` ma cztery nazwane konstruktory,
  `ConfigException` trzy — obie dostały więc typowane pole opisujące rodzaj
  awarii (`TerminalProblem`, `ConfigFailure`). To dane, nie klucz katalogu, więc
  decyzja zostaje w mocy; alternatywą byłoby rozbicie każdej z nich na kilka
  osobnych klas.
- Angielski jako język domyślny przy polskim interfejsie i polskiej
  dokumentacji oznacza, że katalog `en` jest tym, który musi być kompletny.
  Pilnuje tego test porównujący zestawy kluczy obu języków — bez niego reguła
  „brak klucza sięga do angielskiego” byłaby obietnicą bez pokrycia.

**Odrzucone alternatywy:** wyjątek niosący klucz i parametry (najczystszy wobec
DDD, najbardziej pracochłonny) oraz wyjątek z tekstem wstrzykniętym przez
warstwę wyżej; zdania ogólne bez parametrów i zdanie ogólne z dopisanym
technicznym opisem; katalog w JSON-ie i katalog jako klasa z metodami; klucze
zagnieżdżone; odłożenie liczby mnogiej do pierwszej realnej potrzeby; wybór
języka wyłącznie z konfiguracji albo wyłącznie ze środowiska; polski jako język
domyślny; separator dziesiętny wyłącznie z katalogu oraz `intl` jako twardy
wymóg w `composer.json`.

**Rozstrzygnięcia techniczne kroku 15** (nie były przedmiotem osobnego
pytania): `SettingsService::load()` zapamiętuje ustawienia **przed** złożeniem
komunikatu o wadliwym pliku, bo napis idzie przez tłumacza, a ten pyta
konfigurację o język — odwrotna kolejność wpuszczałaby go w środek trwającego
odczytu; etykiety stref tłumaczy `HudFrameLayoutService`, a nie renderery, bo
układ powstaje raz na klatkę i trafia do obu, więc tłumaczenie po ich stronie
byłoby tą samą robotą wykonaną dwa razy; grupowanie tysięcy w `NumberFormatter`
wyłączone, żeby ścieżka z `intl` i bez niego dawały identyczny napis;
`Settings::display()` usunięte, a składanie wartości do pokazania przeniesione
do `RenderSettingsFrameUseCase` (zmiana w kodzie kroku 14, policzona do kroku
15); testy `Application` dostały `StubTranslator` oddający klucz zamiast napisu,
więc sprawdzają, **o który** napis proszą, a nie jak on brzmi;
`bin/terminal-probe` zostało po polsku jako narzędzie diagnostyczne, a nie
interfejs aplikacji.

## Decyzje z realizacji kroku 16 (2026-08-09)

### D33 — Rozbicie potoku, miejsce wzorców i granica „co jest napisem”

**Dotyczy:** kroku 16 (pełna treść: sekcje „Rozstrzygnięcia ze startu kroku”
i „Specyfikacja zrealizowana” w
[16-narzedzia-diagnostyczne-wydajnosci.md](archiwum/16-narzedzia-diagnostyczne-wydajnosci.md)).

**Decyzja:**

- **`encode()` rozbite na trzy metody publiczne** — `drawCanvas()`,
  `quantizeCanvas()`, `toSixel()` — a `encode()` zostaje ich fasadą. **Płótno
  zwalnia wołający**, w `finally`; fasada robi to za aplikację, narzędzie za
  siebie. Enkoder wystawia dodatkowo `canvasCarriesBitmap()`, bo decyzja
  „paleta 64 czy 256” zapada w głębi rysowania, a narzędzie ma ją odczytać,
  nie zgadnąć.
- **Wzorce w repozytorium**, w `docs/pomiary/`, z datą w nazwie pliku;
  `--compare` bez argumentu bierze najnowszy **po nazwie**, nie po czasie
  modyfikacji.
- **Czas przetworzenia klatki przez terminal mierzony**, ale jako osobna
  wielkość jawnie opisana jako przybliżona (zapytanie DA1 zaraz po klatce).
- **Nowy `bin/render-bench`** plus `bin/run-render-bench.sh`; `terminal-probe`
  zostaje przy swoim zadaniu.
- **Mierzony jest wyłącznie potok renderowania i przesył** — bez pełnej
  iteracji pętli i bez dotykania systemu plików, żeby scenariusze były
  deterministyczne.
- **`bin/` zostaje poza PHPStanem i CS-Fixerem**; cała logika mieszka w
  `src/Infrastructure/Diagnostics/`, a punkt wejścia jest cienki.
- **Napisy narzędzia idą przez katalog** `lang/pl.php` i `lang/en.php`
  (klucze `bench.*`), mimo precedensu `terminal-probe`, który został po polsku
  poza katalogiem (D32).
- **Treść mierzonych klatek celowo katalogu omija** — i to jest granica między
  jednym a drugim: napis narzędzia jest interfejsem, a napis w mierzonej klatce
  jest **obciążeniem**, którego długość w znakach wchodzi do wyniku.
- **Dwie zmiany w kodzie produkcyjnym**: `RenderingOptions` dostaje pole
  `?string $font` (domyślnie `null` — aplikacja bez zmian), a
  `TerminalService::write()` zwraca liczbę wywołań `fwrite()` zamiast `void`.

**Uzasadnienie:** wszystkie pozycje to decyzje użytkownika, podjęte po
przedstawieniu wariantów. Trzy zasługują na odnotowanie skutków:

- Granica „napis narzędzia kontra treść klatki” nie jest kompromisem wobec
  reguły 7, tylko warunkiem poprawności: gdyby wiersze mierzonej listy szły
  przez tłumacza, ta sama konfiguracja dawałaby inny wynik po polsku i po
  angielsku, a wzorzec zapisany w jednym języku byłby nieporównywalny z
  przebiegiem w drugim. Z tego samego powodu nietłumaczony jest podpis
  konfiguracji zapisywany do wzorca.
- Obie zmiany w produkcji istnieją dla narzędzia, ale żadna nie jest
  instrumentacją — D28 zakazuje wywołań pomiarowych, a nie parametrów i
  wartości zwracanych. Bez nich dwie osie z planu (font, liczba iteracji
  dopisywania) wypadłyby z zakresu kroku.
- Odmowa zapisania wzorca z niestabilnego przebiegu okazała się potrzebna od
  razu: dwa pierwsze przebiegi przy realizacji zostały odrzucone, a
  `--compare` uruchomione tuż po zapisaniu wzorca pokazało „regresję” +32%
  bez żadnej zmiany w kodzie. Stąd wprost opisane ograniczenie porównania
  w [docs/pomiary/README.md](../pomiary/README.md): przesłanka, nie werdykt.

**Odrzucone alternatywy:** uchwyt `FrameCanvas` z destruktorem zwalniającym
płótno oraz `encode()` bez zmian z osobnymi metodami dla narzędzia
(dwie kopie kolejności kroków); wzorce poza repozytorium
(`~/.light-manager/pomiary/`) i jeden nadpisywany plik bez historii;
rezygnacja z pomiaru czasu terminala na rzecz samego czasu zapisu; rozszerzenie
`bin/terminal-probe` o podpolecenia i nazwa `bin/light-manager-bench`; pomiar
pełnej iteracji pętli na przygotowanym katalogu; objęcie `bin/` bramkami
jakości; napisy narzędzia wpisane w `Diagnostics` jako jawny wyjątek od
reguły 7 oraz osobny katalog napisów `lang/bench.*.php`.

**Wynik merytoryczny kroku** (pełne liczby w pliku kroku): pierwszy pomiar
pokazał, że **zaznaczenie jest najdroższym pojedynczym elementem klatki**
(≈6 ms na jeden pasek — dwa `drawImage()` na wiersz), że **kwantyzacja ma koszt
niemal niezależny od treści** (76–94 ms, na pustym płótnie 92% klatki, co
potwierdza kierunek `remapImage()` z D29) i że **czas zapisu bloba jest mylący**:
jądro przyjmuje 28,9 kB w 1,9 ms, ale terminal odpowiada dopiero po 75 ms.

**Dług przeniesiony do kroku 17:** weryfikacja wartości domyślnych z kroku 14
(paleta 64, wygładzanie tekstu wyłączone, obrysów włączone) zapowiedziana w
D28 **nie została wykonana** — narzędzie jest gotowe, ale rzetelny werdykt
wymaga przebiegów na nieobciążonej maszynie, a te wykonane przy realizacji były
zakłócone. Do zrobienia na starcie kroku 17, przed pierwszą optymalizacją.

## Decyzje z realizacji kroku 17 (2026-08-09)

### D34 — Segmenty wiersza, paleta motywu, pamięci podręczne i takt pętli

**Dotyczy:** kroku 17 (pełna treść: sekcje „Rozstrzygnięcia ze startu kroku”
i „Specyfikacja zrealizowana” w
[17-optymalizacja-wydajnosci.md](archiwum/17-optymalizacja-wydajnosci.md)).

**Decyzja:**

- **`FrameLine` niesie listę segmentów z wyrównaniem** (`list<FrameSegment>`
  plus enum `Alignment`), a nie dwa pola. Rozmieszczenie liczy wspólny
  `Infrastructure\Rendering\SegmentLayout`; **dopychanie spacjami mieszka
  wyłącznie w nim** i wołane jest tylko przez renderer tekstowy.
- **Klatka bez bitmapy jest mapowana na paletę motywu** (`remapImage()`),
  a nie kwantyzowana. Paleta zawiera role motywu **plus rampy półcieni**, bo
  paleta z samych jedenastu ról zamieniłaby wygładzone łuki narożników
  w schodki, czyli cofnęłaby D27. Ustawienie `paletteColors` steruje odtąd
  liczbą półcieni, a nie kwantyzacją.
- **Klatka z miniaturą zostaje przy kwantyzacji adaptacyjnej** z paletą 256
  (D24 bez zmian).
- **Klucz pamięci podręcznej niesie wszystko, co wpływa na piksele** — nie ma
  ścieżki unieważnienia, o której można zapomnieć.
- **Warstwa chromu to gotowe płótno klonowane co klatkę**, nie bitmapa
  składana na świeże płótno.
- **`Preview::rows` usunięte** wraz z walidacją i parametrem `$rows`
  w `PreviewSelectedEntryUseCase::execute()` — korekta D24, martwa od kroku 13.
- **Takt pętli podniesiony z 20 na 30 kl./s.** D19 poza tym bez zmian.
- **Kryterium poprawności dźwigni „paleta” zmienione**: nie „blob identyczny
  co do bajtu”, lecz „każda rola motywu ma w klatce odległość 0”.

**Uzasadnienie:** wszystkie pozycje to decyzje użytkownika. Trzy zasługują na
odnotowanie skutków:

- Kryterium „identyczny blob” trzeba było odrzucić, bo pomiar wykazał, że
  **założenie planu było fałszywe**: `quantizeImage()` przesuwa kolory nawet
  przy budżecie palety większym niż liczba kolorów w obrazie (akcent
  `#d9a441` lądował o 95 jednostek RGB od zaprojektowanego odcienia, i to
  jednakowo przy palecie 16 i 128). Kolory z kroku 13 nigdy nie trafiały na
  ekran w projektowanej postaci; dźwignia 2 nie tylko przyspiesza, ale i to
  naprawia. Zmiana wyglądu jest więc **zamierzona i na korzyść**.
- Przy wyborze „listy segmentów” padło sformułowanie „lewo/prawo/środek”, ale
  `Alignment` ma dwa przypadki. Wyśrodkowania nie używa dziś żadna kolumna,
  a nieużywany przypadek enuma to kod bez pokrycia testem; dopisanie go wraz
  z gałęzią w `SegmentLayout` to kilka linii, gdy pojawi się użytkownik.
- Podniesienie taktu do 30 kl./s zdejmuje **nasz** sufit, ale nie daje 30
  klatek na sekundę: aplikacja pod XTermem osiąga 19 kl./s, bo wąskim gardłem
  przestało być rysowanie, a stało się przyjęcie i wyrysowanie Sixela przez
  terminal (krok 16 zmierzył ~75 ms do odpowiedzi DA1 po klatce). Klatka
  z miniaturą (78 ms) i tak budżetu 33 ms nie mieści.

**Wynik:** klatka listy **212,4 → 34,2 ms (6,2×)**, sam tekst 9,8×,
zaznaczenie 10,6×; klatka z miniaturą tylko 2,9×, bo kwantyzacja adaptacyjna
odpowiada w niej za 54% czasu. Zrzuty PNG przed i po różnią się **zerem
pikseli** — dźwignie rysowania nie zmieniły ani jednego piksela.

**Piąta dźwignia spoza planu:** pamięć podręczna paska zaznaczenia. Plan
wymieniał cztery, ale dziennik kroku 16 wskazał zaznaczenie jako najdroższy
pojedynczy element klatki i jako materiał dla kroku 17 — pasek był rysowany
dwoma `drawImage()` na pełnej szerokości klatki. Scenariusz `selection` spadł
ze 138,8 do 30,0 ms.

**Poprawka w kodzie kroku 16:** próg niestabilności pomiaru wymaga teraz
ilorazu `max/min` powyżej 1,35 **i** różnicy bezwzględnej powyżej 3 ms. Sam
iloraz, po zejściu scenariuszy do kilku milisekund, zapalał się na drgnięciu
planisty i blokował zapis wzorca. Bez tej poprawki nie dało się spełnić
kryterium ukończenia kroku 17.

**Odrzucone alternatywy:** dwa pola (lewy, prawy) w `FrameLine`; dopychanie
spacjami w rendererze tekstowym zamiast we wspólnym pomocniku; paleta złożona
z samych ról motywu (bez ramp — schodkowe narożniki) oraz utrzymanie
kwantyzacji, czyli rezygnacja z dźwigni 2 na rzecz niezmienionego wyglądu;
krótki klucz pamięci podręcznej z jawnym czyszczeniem przy zmianie motywu,
okna i fontu; bitmapa chromu składana na świeże płótno zamiast klonowania;
pozostawienie `Preview::rows`; utrzymanie taktu 20 kl./s oraz odłożenie
decyzji o takcie do czasu zobaczenia wyniku.

## Decyzje z planowania kroku 18 (2026-08-09, po realizacji kroku 17)

### D35 — Komponenty i okno komend przed modułami: przenumerowanie kroków

**Dotyczy:** kolejności planu oraz kroków
[18-komponenty-i-plaszczyzny.md](archiwum/18-komponenty-i-plaszczyzny.md),
[19-okno-komend.md](archiwum/19-okno-komend.md) i
[20-moduly-plugins.md](archiwum/20-moduly-plugins.md).

**Kontekst:** dotychczasowy krok 18 („Moduły”) zakładał, że ekran modułu oddaje
`list<FrameLine>` — czyli że pierwsze publiczne API projektu powstanie wobec
płaskiej listy wierszy. Tymczasem interfejs aplikacji nie ma słownika własnych
części: okienko ma dwie niezależne implementacje (49 linii w enkoderze Sixela,
28 w rendererze tekstowym), okno przewijania liczone jest w trzech przypadkach
użycia osobno, aktywna zakładka udawana jest nawiasami kwadratowymi wokół
napisu, a pola tekstowego nie ma w ogóle — na czym wprost opiera się dzisiejsza
zasada „`q` kończy aplikację wszędzie”.

**Decyzja:**

- **Krok „Moduły (plugins)” przechodzi z numeru 18 na 20** wraz z plikiem
  (`18-moduly-plugins.md` → `20-moduly-plugins.md`); D30 zostaje w mocy, a
  odniesienia „krok 18” w jego treści dotyczą odtąd kroku 20.
- **Numer 18 przejmuje nowy krok: „Komponenty interfejsu i płaszczyzny”** —
  zamknięcie elementów graficznych (okno, przycisk, etykieta, pole
  wprowadzania danych, pole wyboru opcji, lista, zakładki) w komponenty wraz
  z obsługą nakładanych płaszczyzn.
- **Numer 19 przejmuje nowy krok: „Okno komend”** — wiersz poleceń w duchu
  `vima`, otwierany `F12`, z podpowiadaniem dostępnych komend i
  autouzupełnianiem; każdy moduł wnosi do niego własne komendy.
- **Krok 20 zyskuje zależność od kroków 18 i 19**; jego model i wysiłek
  (Opus / high) zostają bez zmian, a zakres maleje o punkt „ekrany jako
  obiekty”, który przechodzi do kroku 18.

**Uzasadnienie:** decyzja użytkownika. Powód, dla którego kolejność ma
znaczenie, jest ten sam, którym uzasadniona była zależność kroku modułów od
kroku 17, tylko o piętro wyżej: kontraktu modułu nie da się tanio zmienić po
tym, jak powstaną pierwsze moduły — kosztuje wtedy tyle refaktorów, ile
modułów. Moduł wchodzący przed komponentami odziedziczyłby brak słownika
interfejsu i utrwalił go w API. Okno komend stoi między nimi z tego samego
powodu: skoro moduł ma wnosić komendy, kontrakt komendy musi być gotowy,
zanim powstanie pierwszy moduł.

**Rozstrzygnięcie, które zmieniło kształt dwóch kroków naraz:** skok do
ścieżki miał być w kroku 18 zwykłym skrótem otwierającym pole tekstowe.
Zamiast tego powstaje **okno komend** — jedno miejsce, w którym czynność
wywołuje się po nazwie, zamiast szukać dla niej wolnego klawisza. Skok do
ścieżki staje się komendą **modułu `file`** (krok 20), a nie funkcją rdzenia.
Wraz z decyzją o wyjściu przez `F10` (P6 kroku 18) daje to własność, o którą
warto dbać dalej: **rdzeń nie rezerwuje ani jednej litery** — pełny alfabet
zostaje dla komend i skrótów modułów.

**Stan:** komplet rozstrzygnięć kroku 18 zapisuje D36 poniżej. Krok 19 ma
spisane ustalenia i listę dziesięciu pytań do dokończenia planu; jego decyzje
trafią do osobnego wpisu.

### D36 — Komponenty interfejsu: kontrakt, płaszczyzny, układ i granice warstw

**Dotyczy:** kroku 18 (pełna treść:
[18-komponenty-i-plaszczyzny.md](archiwum/18-komponenty-i-plaszczyzny.md)).

**Kontekst:** interfejs aplikacji nie ma słownika własnych części. Okienko ma
dwie niezależne implementacje (49 linii w `SixelFrameEncoder::drawPopup()`, 28
w `TextFrameRenderer::overlayPopup()`), okno przewijania liczone jest osobno
w trzech przypadkach użycia, przycinanie wiersza — również trzy razy, a
aktywna zakładka udawana jest nawiasami kwadratowymi wokół napisu, bo
`FrameLine` nie potrafi powiedzieć „ten fragment jest wyróżniony”. Na tym
kształcie miały stanąć moduły; krok 18 wchodzi przed nimi właśnie po to, by
nie utrwaliły go w API (D35).

**Decyzja** (czternaście rozstrzygnięć, wszystkie użytkownika):

- **`Frame` niesie płaszczyzny**, a `FrameLine`, `FrameSegment`, `Alignment`
  i `LineStyle` znikają — jeden słownik na jedną rzecz (P1).
- **Komponent oddaje prymitywy**, renderer zna wyłącznie kształty (`TextRun`,
  `RoundRect`, `CornerBrackets`, `Bar`, `Bitmap`) wraz z rolą motywu. Nowy
  komponent nie dotyka rendererów (P2).
- **Komponenty leżą w `Presentation/Ui/`**, a składanie ekranów przenosi się
  do warstwy dostarczania; trzy klasy `Render*FrameUseCase` przestają być
  przypadkami użycia (P3).
- **Prymitywy, płaszczyzna, geometria i `Frame` leżą w `Application/Ui/`** —
  bo przechodzą przez `FrameRendererPort`, a renderer w `Infrastructure` nie
  ma prawa zobaczyć klasy z `Presentation`. `Frame` **wyprowadza się
  z `Domain`**, które przestaje w ogóle wiedzieć o rysowaniu (P11).
- **Układ wyrażają same kontenery**: `FrameLayout`, `FrameZone`,
  `FrameLayoutPort` i `HudFrameLayoutService` znikają, a drabinka ustępowania
  stref z kroku 13 przechodzi do kontenera przez trójkę „rozmiar minimalny,
  preferowany, kolejność ustępowania” (P4).
- **Katalog dwunastu komponentów** — `Panel`, `Label`, `ListView`, `Tabs`,
  `Choice`, `Toggle`, `Button`, `Dialog`, `StatusBar`, `ImageBox`,
  `Scrollbar`, `Spacer` — **każdy z prawdziwym użytkownikiem** w aplikacji
  (P5). `Button` dostaje nową funkcję: „przywróć ustawienia domyślne” (P12).
- **`TextInput` przenosi się do kroku 19** i powstaje przy oknie komend, czyli
  przy swoim pierwszym użytkowniku (P13, P14).
- **Wyjście z aplikacji przenosi się na `F10`**; `q` przestaje cokolwiek
  znaczyć (P6).
- **Wydajność bez progu liczbowego, ale z obowiązkiem pomiaru** każdego
  scenariusza przed i po oraz opisania wyniku — również niekorzystnego (P7).
- **Ekrany dostają pełny kontrakt** `ScreenInterface` wraz z `ScreenOutcome`;
  enum `Screen` znika, a `GameLoop`, `LoopState` i `InputHandler` przestają
  wybierać ekran przez `match` (P8).
- **Komponentami staje się cała klatka**, nie tylko środkowy panel (P9).
- **Nazwy: `Component`, `Plane`, `Primitive`, `Cursor`** — `Plane` zamiast
  `Layer`, żeby „warstwa” pozostała zarezerwowana dla warstw DDD (P10).

**Uzasadnienie:** trzy pozycje zasługują na odnotowanie skutków.

- **Wybór `Presentation/Ui/` (P3) rozciął model na dwa poziomy.** Komponent
  może mieszkać w warstwie dostarczania, ale prymityw i płaszczyzna — już nie,
  bo to one przekraczają port. Granica wypada dokładnie tam, gdzie klatka
  opuszcza `Presentation`, i daje się streścić jednym zdaniem, które trafi do
  `architecture.md`: **komponent wie, jak wyglądać; prymityw jest tym, co
  z tej wiedzy zostaje po przekroczeniu portu.**
- **Rezygnacja z `HudFrameLayoutService` (P4) jest największym ryzykiem
  kroku.** Drabinka ustępowania stref nie jest podziałem proporcjonalnym —
  część jej stopni to „mniej ozdoby”, a nie „mniej wierszy” (panel ścieżki
  zamienia się w goły wiersz). Dlatego stara usługa zostaje w testach jako
  **wyrocznia** dla wysokości okna od 1 do 60 wierszy i wypada z kodu dopiero
  wtedy, gdy nowy układ daje identyczny podział.
- **Wyjście przez `F10` (P6) zwolniło cały alfabet.** Rdzeń przestaje
  rezerwować jakąkolwiek literę, więc krok 19 (komendy) i krok 20 (skróty
  modułów) dostają pełną pulę, a rejestr modułów ma mniej kolizji do
  pilnowania. To skutek uboczny decyzji podjętej z zupełnie innego powodu —
  wart utrzymania świadomie.

**Odrzucone alternatywy:** `FrameLine` zachowany jako materiał wewnętrzny
komponentu listy oraz usunięcie `Frame` w całości; renderer odwiedzający
komponenty przez `match` po typie (po jednym w każdym rendererze); komponenty
w `Application/Ui/` albo w `Domain/ValueObject/`; prymitywy w `Domain`
i wariant z `Frame` pozostawionym w `Domain`; umieszczanie prostokątów wprost
przez ekran oraz układ mieszany (kontenery wewnątrz płaszczyzny, strefy dla
płaszczyzn); katalog ograniczony do komponentów mających dziś użytkownika oraz
katalog pełny z `Button` i `TextInput` bez użytkownika; `q` działające poza
polem tekstowym oraz `F10` jako drugi klawisz obok `q`; próg regresji 0% i
próg 10%; trzy klasy ekranów bez wspólnego interfejsu; komponentyzacja
zatrzymana na środkowym panelu; nazwy `Widget`/`DrawCommand`/`Focus` oraz
`Layer`; skok do ścieżki jako skrót `F3`, `/`, `g` albo `Ctrl+L`; potwierdzanie
wyjścia przyciskiem i katalog startowy jako pozycja tekstowa w ustawieniach.

## Decyzje spoza kroków planu (2026-08-09, poprawka błędu)

### D37 — Paleta hybrydowa dla klatki z miniaturą

**Dotyczy:** korekty ostatniego punktu D34 („klatka z miniaturą zostaje przy
kwantyzacji adaptacyjnej z paletą 256, D24 bez zmian”). Poza krokami planu —
wywołane zgłoszeniem błędu przez użytkownika.

**Kontekst:** najechanie kursorem na plik graficzny przemalowywało cały
interfejs, jakby aplikacja przełączała się na inny motyw. Przyczyną była
**jedyna pozostała ścieżka kwantyzacji adaptacyjnej**: klatka z podglądem szła
przez `quantizeImage(256, …)` liczone z zawartości **całego płótna**, więc to
barwy zdjęcia rozstrzygały, jakie kolory dostanie interfejs. Zmierzone na
Grafcie: akcent `#d9a441` → `#b15f0d`, tło `#16181c` → `#020203`, tekst
`#dcdfe4` → `#b7bcc6`, tło zaznaczenia `#313845` → `#080a0f`.

To ta sama pułapka, którą D27 opisała, a D34 rozbroiła dla klatek bez bitmapy.
Ścieżka podglądu została wtedy przy starym mechanizmie **świadomie** — z obawy
o jakość zdjęcia — i o tym, że dziedziczy razem z nim przesuwanie kolorów
interfejsu, wtedy nie pomyślano.

**Decyzja** (trzy rozstrzygnięcia, wszystkie użytkownika):

- **Paleta hybrydowa zamiast kwantyzacji adaptacyjnej** (P1). Klatka
  z miniaturą dostaje paletę złożoną z **wpisów motywu bez zmiany** i barw
  policzonych **wyłącznie ze zdjęcia**; całe płótno przechodzi przez
  `remapImage()`, tak samo jak klatka bez bitmapy. Obie drogi kończą się dziś
  tym samym wywołaniem i różnią wyłącznie tym, czy do palety dopisane są kolory
  zdjęcia (`ThemePalette::forThemeWithImage()`).
- **Podział sufitu 256 wpisów według ustawienia `paletteColors`** (P2). Motyw
  bierze tyle, ile już dziś znaczy to ustawienie dla klatek bez bitmapy (role
  plus rampy), reszta idzie na zdjęcie. Konfiguracja nie rośnie o żadną nową
  pozycję.
- **Kwantyzacja miniatury w `ThumbnailService`, razem z pamięcią podręczną**
  (P3). Zdjęcie sprowadzane jest do swojego sufitu kolorów **zanim trafi na
  płótno**, a listę barw, które w nim zostały, niesie nowy obiekt wartości
  `Thumbnail` (obraz + kolory).

**Uzasadnienie:** kolejność wpisów w palecie hybrydowej jest tu całym
mechanizmem. Role motywu idą pierwsze i żadna nie ustępuje kolorowi zdjęcia,
więc `remapImage()` odwzorowuje każdą z nich na siebie samą — **kryterium
poprawności z D34 („każda rola motywu ma w klatce odległość 0”) obowiązuje
odtąd również dla klatek z podglądem** i jest pilnowane testem, który bez
poprawki wywala się dokładnie na zgłoszonym objawie (`#d9a441` → `#b15f0d`).

Wybór miejsca dla kwantyzacji (P3) okazał się przy okazji dźwignią
wydajnościową: barwy zdjęcia zmieniają się razem z plikiem, a nie razem
z klatką, więc płaci się za nie **raz na najechany wpis**, a nie trzydzieści
razy na sekundę. Pomiar A/B na scenariuszu `thumbnail` (1000×600, 166×46,
grafit, paleta 64, 25 przebiegów, ta sama maszyna, jeden po drugim):

| faza | przed | po | zmiana |
| --- | --- | --- | --- |
| rysowanie | 6,7 ms | 7,2 ms | +0,5 ms |
| kwantyzacja | 40,0 ms | 5,1 ms | **−87%** |
| kodowanie | 13,2 ms | 14,6 ms | +1,4 ms |
| **razem** | **60,0 ms** | **26,8 ms** | **−55%** |
| blob | 29,8 kB | 30,4 kB | +0,6 kB |

Znaczenie ma nie tyle sam procent, co próg: przy takcie 30 kl./s budżet klatki
wynosi 33 ms, więc klatka z podglądem **przestała ten budżet przekraczać**.
Rysowanie i kodowanie drgnęły w górę — pierwsze o odczyt z pamięci podręcznej
miniatury, drugie o nieco bogatszą paletę wynikowej klatki — i obie zmiany są
w cenie. Pozostałe siedem scenariuszy jest poza zasięgiem poprawki; pomiar to
potwierdza.

Rzeczywisty podział sufitu wyszedł korzystniejszy, niż zapowiadało ustawienie,
bo `paletteColors` jest **pułapem, nie przydziałem**: rampy półcieni dają tylko
kilkadziesiąt niepowtarzalnych odcieni, więc wpisy motywu kończą się na 54–56
(zależnie od motywu) i zdjęciu zostaje 200–202 barwy przy domyślnych 64.
Uboczny wniosek, którego nikt wcześniej nie odnotował: **budżet 128 daje
dokładnie to samo co 64** — półcieni po prostu nie ma z czego dobrać. Przy
ciaśniejszych ustawieniach podział jest ścisły: 16 → 240 barw dla zdjęcia,
32 → 224.

Jakość samego zdjęcia oceniona okiem przez użytkownika — bez zastrzeżeń. Testem
nie da się jej opisać, tak samo jak kształtu łuków i grubości obwódki (D27).

**Odrzucone alternatywy:** paleta motywu użyta dla całej klatki z podglądem
(najprostsze i najszybsze, ale ze zdjęcia robi plakat na kilkunastu kolorach);
same role motywu w palecie hybrydowej, bez ramp (245 wpisów dla zdjęcia kosztem
schodkowatych narożników i liter na klatce z podglądem — cofnięcie D27 na tej
jednej ścieżce); osobne ustawienie w konfiguracji na podział sufitu (więcej
kontroli kosztem rozrostu ekranu ustawień i migracji pliku); liczenie barw
miniatury w enkoderze przy każdej klatce (`SixelFrameEncoder` bez zmian
w `ThumbnailService`, ale kwantyzacja płacona trzydzieści razy na sekundę).

## Decyzje z planowania kroku 20 (2026-08-09, po realizacji kroku 18)

### D38 — Kontrakt modułu po komponentach: dwie warstwy, `Ctrl` w wejściu, napisy modułu

**Dotyczy:** kroku 20 (pełna treść: [20-moduly-plugins.md](archiwum/20-moduly-plugins.md)).
Uzupełnia D30, która powstała przed krokiem 18 i w trzech punktach jest już
nieaktualna.

**Kontekst:** krok 18 wykonał punkt „ekrany jako obiekty” w całości i przeniósł
`ScreenInterface`, `KeyBinding` oraz komponenty do `Presentation/Ui`. Plan
kroku 20 pochodził sprzed tej zmiany — opisywał ekran modułu jako listę
`FrameLine`, a kontrakt umieszczał w `Application/Module/`. Dokończenie planu
wymagało siedemnastu rozstrzygnięć; wszystkie są decyzjami użytkownika,
podjętymi po przedstawieniu wariantów.

**Decyzja:**

- **Kontrakt dzieli się na dwie warstwy** (P2). W `Application/Module/` leżą
  dane i rejestr: `ModuleInterface`, `ModuleShortcut`, `ModuleSetting`,
  `ModuleSettingsTab`, `ProvidesSettingsTab`, `ModuleRegistry`,
  `ModuleRejection`. W `Presentation/Ui/Module/` — zdolności wymieniające typy
  interfejsu: `ProvidesScreen`, `ProvidesHelpTab`, `ReadsCurrentDirectory`
  (nazwana `ReadsContext` po D40).
- **Rejestr zostaje w całości w `Application`** (P15), więc nie może zobaczyć
  `KeyBinding`. Skrót modułu jest przez to **daną** — `ModuleShortcut` (litera
  plus `ctrl`) deklarowana przez `ModuleInterface`; `KeyBinding` do podpowiedzi
  i do pomocy składa z niej strona `Presentation`.
- **Moduł bierze `Ctrl` + literę** (P7), a `Ctrl` staje się pojęciem w warstwie
  wejścia (P9): `KeyPress` zyskuje flagę, `KeySequenceParser` rozpoznaje bajty
  `0x01`–`0x1A`, `KeyBinding` — konstruktor `ctrl()` i napis „Ctrl+D”.
- **Sygnały zostają** (P10, bez `-isig`), więc zabronionych jest sześć liter:
  `c` i `z` (sygnały), `h`, `i`, `j`, `m` (te same bajty co Backspace, Tab
  i Enter). Deklaracja litery zabronionej albo zajętej odrzuca **cały** moduł.
- **`ReadsCurrentDirectory` implementuje ekran, nie moduł** (P5). **Zmienione
  przez D40:** zdolność nazywa się `ReadsContext` i przyjmuje `ModuleContext`
  — dane pierwotne zamiast `Directory`, który w kroku 21 schodzi do modułu.
- **`version()` znika z kontraktu** (P6) — wróci, gdy moduły staną się
  zewnętrzne.
- **Wszystkie zdolności są opcjonalne** (P17); moduł bez ekranu jest legalny.
- **Zakładka ustawień modułu opisana danymi** (P4), z czterema rodzajami
  pozycji (P12): przełącznik, wybór z listy, liczba z listy i **pole tekstowe**
  na `TextInput` z kroku 19. **Walidację tekstu robi rdzeń** według wzorca
  i długości z deklaracji (P13).
- **Napisy modułu mieszkają w jego katalogu** (P16) i wchodzą do katalogu
  napisów wyłącznie pod **wymuszonym przedrostkiem `module.<id>.`** — kolizja
  z kluczem rdzenia staje się niemożliwa z konstrukcji.
- **Zakładka pomocy na moduł** (P8): część automatyczna z deklaracji plus
  wiersze modułu jako klucze katalogu.
- **`Enter` znaczy odtąd „zatwierdź”** (P3): wchodzi do katalogu, zatwierdza
  wartość w polu tekstowym, uruchamia komendę w oknie komend. **Na pliku nie
  robi nic** — opis pliku otwiera `Ctrl+D` (P11), skrót modułu `FileInfo`.
- **`FileInfo` dostaje dwie pozycje ustawień** (P14): limit czasu polecenia
  `file` (liczba z listy) i jego dodatkowe argumenty (pole tekstowe) — jeden
  moduł przeciera obie nowe ścieżki.
- **Komendy modułu zostają miejscem zarezerwowanym** (P1). Kontrakt komendy
  powstaje w kroku 19; plan 20 spisuje wyłącznie to, czego od niego potrzebuje,
  a `ProvidesCommands` nie wchodzi do kodu, dopóki krok 19 nie zapadnie.

**Uzasadnienie:** trzy pozycje zasługują na odnotowanie skutków.

- **Podział kontraktu (P2) nie jest ozdobą, tylko konsekwencją reguły
  zależności.** `ProvidesScreen` opisany w `Application` musiałby wymienić
  `ScreenInterface` z `Presentation` — strzałka na zewnątrz. Wariant „utrzymać
  D30 i cofnąć część P3 kroku 18” odrzucono jako ruszanie kodu, który dopiero
  co powstał i został zmierzony.
- **`Ctrl` w wejściu dokłada do kroku poprawkę w warstwie z kroku 06**, której
  D30 nie przewidywała. To cena decyzji P7; alternatywą było branie liter bez
  modyfikatora, co odbierałoby literę oknu komend przy każdym module.
- **Zależność od kroku 19 jest po tych decyzjach podwójna** — kontrakt komendy
  *i* `TextInput` dla pozycji tekstowej — więc kroku 20 nie da się zacząć nawet
  częściowo przed ukończeniem 19, którego plan wciąż nie istnieje.

**Odrzucone alternatywy:** kontrakt w całości w `Presentation/Ui/Module/`
(spójniejszy, ale wyprowadza dane konfiguracji z `Application`) oraz w całości
w `Application/Module/` bez zmiany postaci skrótu; rejestr rozbity na dwie klasy
po jednej na warstwę; skrót jako `KeyBinding` albo jako napis `"ctrl+d"`;
`Ctrl+I` na skrót modułu `FileInfo` (odrzucony, bo to ten sam bajt co Tab);
wyłączenie sygnałów (`-isig`) dla odzyskania `Ctrl+C`, `Ctrl+Z` i `Ctrl+\`;
rozszerzony protokół klawiatury (`modifyOtherKeys`) dla odzyskania czterech
aliasów; `Ctrl+D` zastąpione przez `Ctrl+F`, `Ctrl+E` albo `Ctrl+O`; enum `Key`
rozrastający się o dwadzieścia sześć przypadków `CtrlA`–`CtrlZ`; surowy bajt
sterujący jako „znak” w deklaracji wiązania; `ReadsCurrentDirectory` na klasie
modułu albo zastąpione wstrzyknięciem `LoopState`; zakładka ustawień opisana
gotowymi komponentami albo hybrydowo; walidacja tekstu przez wywoływalny obiekt
modułu albo brak walidacji na ekranie; napisy modułu w plikach rdzenia albo
tłumaczone przez sam moduł; kolizja klucza napisu rozstrzygana odrzuceniem
modułu albo nadpisaniem klucza rdzenia; zakładka pomocy jako jedna wspólna
„Moduły” albo brak zakładki; wymóg co najmniej jednej zdolności albo ekranu
obowiązkowego; `Enter` z komunikatem „to nie jest katalog”, ze wskazówką raz na
uruchomienie albo zachowany przez zdolność „opisz zaznaczony wpis”; drugi moduł
demonstracyjny dla zakładki ustawień; plan kroku 20 przesądzający kontrakt
komendy zamiast czekać na krok 19.

## Decyzje z planowania kroku 19 (2026-08-09, po realizacji kroku 18)

### D39 — Okno komend: kontrakt komendy, czynna płaszczyzna modalna i pole tekstowe

**Dotyczy:** kroku 19 (pełna treść: [19-okno-komend.md](archiwum/19-okno-komend.md)).
Domyka planowanie rozpoczęte przy kroku 18, którego rozstrzygnięcie P13
powołało ten krok do życia.

**Kontekst:** aplikacja miała dostać jedno miejsce, w którym wywołuje się
czynność po nazwie, zamiast szukać dla niej wolnego klawisza. Plik kroku
zawierał dotąd wyłącznie osiem ustaleń przeniesionych z kroku 18 i dziesięć
pytań otwartych. Wszystkie poniższe pozycje są decyzjami użytkownika, podjętymi
po przedstawieniu wariantów.

**Decyzja:**

- **Okno komend to czynna płaszczyzna modalna** (P9), a nie ekran i nie
  dzisiejsze bierne okienko. `LoopState` dostaje stos okien nakładanych zamiast
  pojedynczego `Dialog`, a okno może klawisz **zużyć albo przepuścić** — dziś
  pierwszy dowolny klawisz zawsze zamyka okienko. Kontraktem jest
  `OverlayInterface` wraz z `OverlayOutcome`.
- **Miejsce w klatce: pas przy dolnej krawędzi** (P10), nad paskiem stanu, z
  listą podpowiedzi wyrastającą w górę — wzorem `vima` i kosztem najmniejszej
  zasłoniętej powierzchni.
- **Komenda to interfejs z deklaracją argumentów** (P11) w `Application/Command/`
  (P24). Wynik niesie komunikat, przejście i **identyfikator ekranu** do
  otwarcia — bo kontrakt w `Application` nie może zobaczyć `ScreenInterface`,
  a `core.settings` musi ekran ustawień otworzyć.
- **Parser wiersza jest jeden, w rdzeniu** (P12): dzieli po spacjach, rozumie
  **cudzysłowy** (P14), mapuje wartości **pozycyjnie na nazwy z deklaracji**
  (P13) i sprawdza **obecność oraz rodzaj** (P15). Komenda dostaje argumenty
  z nazwą i wartością; istnienie zasobu sprawdza już sama.
- **Nazwy z przestrzenią właściciela** (P16): `core.` dla rdzenia,
  `<id modułu>.` dla modułów, przedrostek **wymuszony** przez rejestr. Kolizja
  między modułami staje się niemożliwa z konstrukcji — tak samo, jak przedrostek
  `module.<id>.` rozwiązał kolizję napisów w D38.
- **Jeden zbiór globalny komend** (P18), niezależny od aktywnego ekranu.
- **Pięć komend rdzenia** (P19): `core.settings`, `core.help`, `core.quit`,
  `core.theme <nazwa>`, `core.language <kod>`. Dwie ostatnie są jedynymi
  użytkownikami argumentów w tym kroku.
- **Uzupełnianie: lista w locie plus `Tab`** (P20). Podpowiedzi argumentów
  wystawia sama komenda (P21), w dwóch odmianach: **stałe** — liczone raz
  w `Bootstrap` i trzymane jako gotowe wiersze `ListRow` (P22) — oraz
  **liczone na żądanie**, zadeklarowane tutaj, ale implementowane dopiero
  w kroku 20 przez `file-info.jump`.
- **Historia: 20 wpisów w pamięci i tyle samo w pliku** `~/.light-manager/`
  (P23), zapisywana po zapełnieniu bufora i przy zamknięciu, obejmująca **cały
  wiersz wraz z argumentami**. Przy pustym polu stoi **na górze listy**
  podpowiedzi — dzięki temu nie potrzebuje własnego klawisza.
- **`Ctrl` w warstwie wejścia przenosi się z kroku 20 do 19** (P17): flaga
  w `KeyPress`, rozpoznanie bajtów `0x01`–`0x1A` w parserze, `KeyBinding::ctrl()`.
- **`TextInput` z edycją w wierszu** — strzałki, `Home`, `End`, `Delete`,
  `Backspace`; bez zaznaczania, kasowania słowa i wklejania.
- **Model i wysiłek: Opus / xhigh** (P25).

**Uzasadnienie:** trzy pozycje zmieniają zakres innych kroków i dlatego
zasługują na odnotowanie.

- **P17 zdejmuje `Ctrl` z kroku 20.** Praca należy tam, gdzie mieszka pierwszy
  użytkownik — a jest nim `TextInput`, który musi odróżnić literę od bajtu
  sterującego, żeby `Ctrl+D` nie wpisał się do pola. Krok 20 dokłada już tylko
  listę liter zarezerwowanych i wykrywanie kolizji między modułami.
- **P21 razem z P6 odsuwa podpowiadanie ścieżek do kroku 20.** Żadna komenda
  rdzenia nie przyjmuje ścieżki, więc mechanizm powstałby bez użytkownika —
  wbrew zasadzie P5 kroku 18. Rodzaj `OnDemand` zostaje zadeklarowany, a jego
  pierwszą implementację wnosi `file-info.jump`.
- **P9 jest tym, czym krok 18 był oknu modalnemu winien.** Płaszczyzna modalna
  została tam opisana jako „przejmuje klawisze i nie oddaje ich niżej”, ale
  jedyny jej użytkownik — okienko z opisem pliku — klawiszy nie potrzebował.
  Ten krok jest pierwszym sprawdzianem tamtej zapowiedzi.

**Odrzucone alternatywy:** okno komend jako ekran na `ScreenStack` (zasłania
listę plików, na której komenda ma działać) albo jako osobny tor
w `InputHandler` (drugi sposób obsługi klawiszy obok wędrówki z kroku 18);
okno wyśrodkowane albo przypięte do dołu środkowego panelu; komenda jako obiekt
wartości z wywoływalnym (jak `Button`) albo jako interfejs bez deklaracji
argumentów; jeden argument biorący całą resztę wiersza; argumenty nazwane
w wierszu (`klucz=wartość`) i wariant mieszany; znak ucieczki `\ ` zamiast
cudzysłowów albo obie drogi naraz; walidacja wyłącznie liczby argumentów albo
w całości po stronie komendy; gołe nazwy komend i nazwy z dwukropkiem (`:jump`);
komendy zależne od aktywnego ekranu albo wyłącznie ekranowe; rdzeń bez własnych
komend (okno byłoby do kroku 20 puste) i gołe nazwy zarezerwowane dla rdzenia;
`core.hidden` oraz `core.reload` w zestawie startowym; uzupełnianie wyłącznie po
`Tab` albo wyłącznie w locie z dopisywaniem reszty za kursorem; podpowiedzi
liczone zawsze na żądanie z pamięcią podręczną w rdzeniu albo zawsze stałe
(ścieżki bez podpowiedzi); pamięć trzymająca same napisy albo gotowe prymitywy
(niemożliwe — zależą od prostokąta); historia wyłącznie w pamięci procesu, bez
historii, bez limitu wpisów oraz z limitem 200 i rotacją; historia dostępna
przez `PageUp`/`PageDown` albo wyłącznie przez uzupełnianie; historia
zapisująca samą nazwę komendy albo pomijająca wywołania błędne; kontrakt
komendy w `Presentation/Ui/Command/` albo rozbity na dwie warstwy; wynik
komendy niosący obiekt ekranu; `Ctrl` zostawiony w kroku 20 albo podzielony
między kroki; skok do ścieżki przeniesiony do tego kroku jako `core.jump`;
`TextInput` bez ruchu kursora oraz z zaznaczaniem i `Ctrl+W`; nieznana nazwa
zamykająca okno albo proponująca najbliższą; lista podpowiedzi pokazywana
dopiero po pierwszym znaku; model Sonnet / high oraz Opus / high.

## Decyzje z realizacji kroku 19 (2026-08-09)

### D58 — Karetka, wędrówka klawisza przy oknie nakładanym i pamięć podpowiedzi

> **Uwaga o numerze.** Wpis powstał 2026-08-09 jako **drugi D40** — ten sam
> numer dostała tego samego dnia decyzja o przeglądarce jako module domyślnym.
> Kolizję rozstrzygnięto 2026-08-12 na korzyść tamtej, bo jej numer zdążył
> wejść do dziesięciu miejsc w kodzie i planach (cytowany jako „D40, P4–P8”);
> ta nie miała ani jednego odwołania. Stąd numer spoza chronologii — wpis
> stoi w pliku tam, gdzie powstał, przy kroku 19.

**Dotyczy:** kroku 19 (pełna treść: sekcje „Rozstrzygnięcia wykonawcze ze startu
kroku” i „Odstępstwa od planu” w [19-okno-komend.md](archiwum/19-okno-komend.md)).

**Decyzja** — siedem rozstrzygnięć wykonawczych i pięć odstępstw, z których trzy
zmieniają coś, co plan mówił wprost:

- **Pamięć podpowiedzi trzyma klucze, nie gotowe wiersze `ListRow`** —
  odwrócenie P22 planu, wybrane przez użytkownika przy starcie kroku. Powodem
  jest `core.language`: gotowy wiersz zostałby w poprzednim języku aż do
  restartu, a unieważnianie pamięci przy zmianie ustawienia wiązałoby okno
  komend z konfiguracją. Liczone raz zostają **wartości** podpowiedzi `Fixed`
  (`CommandOverlay::prepare()`), bo lista motywów i języków naprawdę się nie
  zmienia.
- **Karetka miga** (pół sekundy świeci, pół gaśnie) i rysuje się `Highlight`iem
  na jednej komórce — tym samym paskiem, którym lista zaznacza wiersz. Zegar
  przychodzi z `LoopState` przez nowy, jednometodowy interfejs `NeedsTime`;
  komponent nie woła `microtime()`, bo przestałby być testowalny.
- **Klawisz globalny przy otwartym oknie zamyka okno.** Plan opisywał wędrówkę
  „okno → klawisze globalne → ekran”, ale milczał o tym, co dzieje się z oknem,
  gdy zadziała klawisz globalny. `F1` z otwartym oknem komend znaczy „pokaż
  pomoc”, a nie „pokaż pomoc pod spodem”. Klawisz **przepuszczony** przez okno
  nigdy nie schodzi do ekranu — to jest reguła modalności.
- **`Enter` uruchamia wskazaną pozycję tylko po ruchu strzałkami.** Bez
  znacznika „użytkownik wskazał” każde wywołanie brałoby pierwszą pozycję listy,
  a wpisany wiersz nie miałby jak zadziałać.
- **Wpis powtórzony przesuwa się na koniec historii** zamiast dokładać kopię —
  dwadzieścia miejsc dzielonych z powtórzonym skokiem to historia bez historii.
- **`CommandInput` nie dostaje `raw()`**: komenda widzi wyłącznie sprawdzone
  argumenty. Surowy wiersz pozwalałby obejść walidację rdzenia i nie miał
  ani jednego użytkownika.
- **Powody odrzucenia komendy nie mają dziś wyjścia do interfejsu.**
  `CommandRejection` powstaje i jest testowany, ale komenda rdzenia nie ma jak
  zostać odrzucona; pierwszym prawdziwym źródłem odrzuceń będą komendy modułów
  w kroku 20 i wtedy dostaną miejsce w pasku stanu.

**Uzasadnienie:** dwie rzeczy warto odnotować poza samą listą.

- **Kontrakt komponentu z kroku 18 udźwignął pole tekstowe bez zmian.** To był
  sprawdzian, który krok 18 sam sobie zadał (P14): `handle(KeyPress): bool`
  wystarczyło komponentowi przyjmującemu **każdy znak**, a nie tylko klawisze
  sterujące. Ani `ComponentInterface`, ani `FocusableInterface` nie zostały
  tknięte.
- **Okno komend kosztuje 28,8 ms wobec 20,7 ms zwykłej klatki listy** (pomiar
  w dzienniku kroku), czyli mieści się w budżecie taktu. Cały przyrost siedzi
  w rysowaniu; kwantyzacja i kodowanie nie drgnęły, bo okno nie wprowadza do
  klatki ani jednego koloru spoza motywu i szybka ścieżka palety z D34 działa
  dalej. Migająca karetka unieważnia pamięć płaszczyzny dwa razy na sekundę —
  około 1 ms na sekundę, przyjęte świadomie.

**Poprawka po pierwszym uruchomieniu (2026-08-09):** miniatura z pasa podglądu
przebijała się przez okno komend, bo `Panel` rysuje samą obwódkę, bez tła.
Rozstrzygnięcie użytkownika: **warstwy nie są przezroczyste — mają zakrywać to,
co pod nimi**, i należy to do kontraktu klatki, a nie do dyscypliny autora okna.
`Plane` zyskało flagę `opaque` (domyślnie `fałsz`, bo `chrome` i `content`
obejmują całe okno i zakrywałyby się nawzajem), a oba renderery wymazują
prostokąt takiej płaszczyzny przed narysowaniem. Wymazanie idzie przez
zapamiętaną bitmapę i `compositeImage` — `drawImage()` kosztuje tyle, ile całe
płótno (krok 17, dźwignia 5). Zmierzony koszt: **+1,76 ms** na fazie rysowania
(A/B tej samej klatki, 25 przebiegów naprzemiennych). Odrzucone: okno rysujące
sobie tło samodzielnie (dyscyplina zamiast reguły — następne okno powtórzyłoby
błąd) oraz `Panel` zawsze z wypełnieniem (dotyka wszystkich czterech stref klatki
głównej, malując prostokąty, pod którymi i tak jest to samo tło).

**Odrzucone alternatywy:** karetka jako `Bar` (pionowa kreska między znakami —
w trybie tekstowym degraduje się do znaku zajmującego całą komórkę) oraz jako
podkreślenie; karetka nieruchoma; `useTime()` wprost w `OverlayInterface`
(pusta metoda w oknie z opisem pliku); stała wysokość listy podpowiedzi i lista
bez ograniczenia; `OverlayStack` jako pole w `LoopState`; unieważnianie pamięci
wierszy po zmianie języka oraz pogodzenie się z językiem sprzed restartu; dwie
osobne klasy na `core.help` i `core.settings`.

## Decyzje z planowania kroku 21 (2026-08-09, po realizacji kroku 19)

### D40 — Menadżer plików jako moduł domyślny: rdzeń przestaje wiedzieć o plikach

**Dotyczy:** nowego kroku 21 (pełna treść:
[21-przegladarka-jako-modul.md](archiwum/21-przegladarka-jako-modul.md)) oraz dwóch
poprawek w planie kroku 20.

**Kontekst:** przy doprecyzowaniu kroku 20 użytkownik postawił wymaganie, którego
plan nie przewidywał: **menadżer plików ma być jednym z modułów i modułem
domyślnym przy uruchomieniu, chyba że konfiguracja wskaże inny.** Dotychczasowy
plan traktował przeglądarkę jako dno aplikacji — `ScreenStack` ma ją wpisaną
w konstruktor, `LoopState` trzyma jej katalog, a `FrameComposer` rysuje z tego
katalogu pasek ścieżki i pas podglądu, niezależnie od tego, czyj ekran stoi
w środku. Wszystkie poniższe pozycje są decyzjami użytkownika, podjętymi po
przedstawieniu wariantów.

**Decyzja:**

- **Do modułu schodzi wszystko, łącznie z domeną** (P1). `Directory`,
  `DirectoryPath`, `Entry`, `EntryType`, `Selection`, repozytorium katalogów,
  cztery wyjątki katalogu, sześć przypadków użycia nawigacji i podglądu, dwie
  klasy `Infrastructure` oraz `BrowserScreen` — do `src/Module/Browser/`.
  **Rdzeń przestaje znać pojęcie pliku**, a sprawdza to test po przestrzeniach
  nazw. `Domain/` rdzenia zostaje (P11) jako słownik powłoki terminalowej:
  `Message`, `MessageTone`, `Preview`, `RendererMode`, `ScrollPosition` i korzeń
  hierarchii wyjątków, po którym dziedziczą także wyjątki modułów.
- **Osobny krok 21** (P2). Krok 20 zostaje przy `FileInfo` — kontrakt ma się
  sprawdzić na tanim module, zanim zawiśnie na nim główna funkcja aplikacji.
- **Kontekst sesji jako dane pierwotne** (P5) i **już w kroku 20** (P9).
  `ModuleContext` — ścieżka, nazwa zaznaczenia, rodzaj wpisu — zastępuje
  planowaną zdolność `ReadsCurrentDirectory`, która wymieniała `Directory`.
  Po przenosinach ten typ należy do modułu przeglądarki, więc zdolność w dawnym
  brzmieniu kazałaby modułowi `FileInfo` zobaczyć typ innego modułu i obalała
  regułę „moduły się nie znają” na pierwszym module.
- **Ekran rysuje trzy strefy** (P6): pasek ścieżki, środkowy panel i pas
  podglądu. Zasada kroku 20 „moduł dostaje środkowy panel i nic poza nim”
  **przestaje obowiązywać**, a `headerSuffix()` znika z `ScreenInterface`.
  Rdzeniowi zostają oprawa stref wraz z etykietami i pasek stanu.
- **Moduł domyślny wybiera klucz rdzenia `startupModule`** (P3), którego
  dopuszczalne wartości pochodzą **z rejestru modułów**, a nie z kodu; pozycja
  stoi na zakładce „Moduły”, a zmiana obowiązuje po ponownym uruchomieniu.
- **Przeglądarka jest modułem ostatniej szansy** (P4): nie da się jej wyłączyć
  ani odrzucić, rejestr sprawdza ją pierwszą, a rdzeń wraca do niej wraz
  z komunikatem, gdy moduł domyślny jest wyłączony, odrzucony, nieobecny albo
  bez ekranu. Identyfikator modułu ostatniej szansy podaje rejestrowi
  `Bootstrap`, więc `Application/Module` nie zna nazwy żadnego modułu.
- **Przeglądarka deklaruje skrót jak każdy moduł** (P7) — `browser`, `Ctrl+B`
  (P10). Na dnie stosu skrót znaczy to samo, co `Esc`, i wynika to z istniejącego
  `ScreenStack::toggle()`, bez przypadku szczególnego.
- **Razem z nią schodzą** (P8): ustawienie `showHiddenEntries` (→
  `modules.browser.showHidden`), komenda skoku (`file-info.jump` → `browser.jump`
  wraz z podpowiedziami `OnDemand`), katalog startowy i cztery wyjątki domenowe
  katalogu.
- **Model i wysiłek: Opus / xhigh** (P12).

**Uzasadnienie:** trzy pozycje zasługują na odnotowanie skutków.

- **Wybór P1 jest droższy od wariantów pośrednich i został podjęty świadomie.**
  Odrzucono dwa tańsze: „tylko ekran i klawisze” (katalog zostaje w `LoopState`
  jako wspólny kontekst — najmniejsza zmiana, ale przeglądarka byłaby modułem
  wyłącznie z nazwy) oraz „ekran plus przypadki użycia” (rdzeń traci umiejętność
  nawigacji, ale wciąż zna `Directory`). Cena wariantu pełnego to przebudowa
  kontraktu ekranu i stanu pętli; zapłatą jest zdanie, które da się sprawdzić
  testem: **w rdzeniu nie ma ani jednej klasy wiedzącej, czym jest plik.**
- **P6 uchyla zasadę z kroku 20, a nie ją omija.** Zasada nie była błędem —
  była prawdziwa dopóty, dopóki rdzeń miał z czego narysować ścieżkę i podgląd.
  Po P1 nie ma, więc strefy idą tam, gdzie dane. Odrzucono wariant „rdzeń rysuje
  obie strefy z kontekstu sesji”, który zachowałby zasadę nienaruszoną kosztem
  tego, że rdzeń rysowałby zawartość, której ekran nie zamawiał, a moduł
  z podglądem innym niż miniatura pliku nie miałby jak go pokazać.
- **P9 przenosi robotę do kroku 20 zawczasu i to jest jej cały sens.**
  Wprowadzenie `ModuleContext` dopiero w kroku 21 znaczyłoby, że pierwszy moduł
  projektu powstaje na publicznym kontrakcie, który ginie tydzień później —
  a kontrakt modułu jest tym rodzajem decyzji, którą po pierwszych modułach cofa
  się nie jednym refaktorem, lecz tyloma, ile modułów.

**Odrzucone alternatywy:** przeglądarka jako moduł „tylko ekranem” albo „ekranem
i przypadkami użycia”, z katalogiem pozostawionym w rdzeniu; rozszerzenie kroku
20 zamiast nowego kroku 21; podział na kroki 21 i 22 (przenosiny osobno od
modułu domyślnego); `DirectoryPath` zachowany w domenie rdzenia jako jedyne
pojęcie plikowe; rezygnacja ze wspólnego kontekstu i przerobienie `FileInfo` na
moduł „opisz plik podany komendą”; `ScreenInterface` bez zmian, z rdzeniem
rysującym obie strefy z kontekstu; pas podglądu w module przy pasku ścieżki
zostawionym rdzeniowi; moduł domyślny jako flaga `modules.<id>.default`
(dopuszcza plik z dwoma domyślnymi albo zerem); przełącznik `--module=` z linii
poleceń (aplikacja nie ma obsługi argumentów wywołania); zastępczy ekran rdzenia
zamiast modułu ostatniej szansy; „pierwszy dostępny moduł z listy” jako
zachowanie awaryjne (to, co widać po starcie, zależałoby od kolejności listy
w `Bootstrap`); przeglądarka bez skrótu, bo jest dnem; identyfikatory `files`
i `file-manager` wraz z `Ctrl+F`; przeniesienie `Message`, `Preview`
i `RendererMode` z `Domain/` do `Application/` po schudnięciu domeny rdzenia;
Opus/high dla kroku 21.

## Decyzje z realizacji kroku 20 (2026-08-09)

### D41 — Moduły: trzy rodzaje zakładek, ustawienia surowe w pliku i miejsce komendy modułu

**Dotyczy:** kroku 20 (pełna treść: sekcje „Rozstrzygnięcia wykonawcze ze startu
kroku” i „Odstępstwa od planu” w [20-moduly-plugins.md](archiwum/20-moduly-plugins.md)).

**Decyzja** — osiem rozstrzygnięć wykonawczych podjętych przez użytkownika na
starcie kroku i pięć odstępstw wynikłych z otwarcia edytora. Warte odnotowania
poza samą listą są cztery:

- **Katalog z napisami modułu deklaruje kontrakt** (`ModuleInterface::translations(): ?string`),
  a nie konwencja położenia plików. Do `Application` wchodzi przez to ścieżka na
  dysku — ale jako **dana, nie typ**, więc reguła zależności zostaje nietknięta.
  Alternatywa (wyprowadzanie ścieżki z nazwy klasy przez refleksję) zamieniała
  jawną deklarację w niepisaną regułę i nie dawała modułowi sposobu, by
  powiedzieć „nie mam napisów”.
- **Zakładka „Moduły” jest osobnym rodzajem zakładki**, nie zakładką złożoną
  z przełączników. `SettingsTab` dostaje `SettingsTabKind` z trzema przypadkami
  (`Core`, `Module`, `Modules`) — klasa spoza planu, bez której wiersz modułu
  **odrzuconego** trzeba by udawać ustawieniem: niesie powód odrzucenia zamiast
  wartości i nie da się go przełączyć. Zakładka rdzenia niesie `SettingKey`,
  zakładka modułu — `ModuleSetting`, spis — sam licznik modułów.
- **Podprzestrzeń `modules` jest w pliku trzymana surowo.** `SettingsService` nie
  zna deklaracji pozycji modułu — sprawdza wyłącznie typ wartości (skalar) —
  a znaczenie nadaje jej `ModuleSetting` przy pokazaniu i przy zapisie. To jest
  cena, którą płaci się za obietnicę „ustawienia modułu nieznanego zostają
  nietknięte”: usługa konfiguracji nie może odrzucać tego, czego nie rozumie,
  bo nie odróżnia śmiecia od ustawienia modułu, który akurat jest wyłączony.
- **Komenda modułu leży w jego warstwie `Presentation`, nie `Application`** —
  odstępstwo od tabeli plików w planie. `JumpCommand` dostaje `LoopState`, czyli
  obiekt warstwy dostarczania; postawiona w `Application` modułu łamałaby regułę
  zależności. Jest to dokładnie ta sama zasada, która w kroku 19 postawiła
  `ScreenCommand` i `SettingCommand` w `Presentation/Cli/Command`, a nie obok
  kontraktu komendy.

Ponadto: **tryb edycji pozycji tekstowej mieszka w `SettingsScreen`**, a
`TextInput` wychodzi z kroku 20 nietknięty (jego zachętą jest etykieta pozycji);
**kontekst sesji podawany jest ekranowi co klatkę**, tą samą ścieżką, którą
chodzi `NeedsTime`; **spis „Moduły” stoi przed zakładkami modułów**, bo działa
jak nagłówek sekcji; **wzorzec pozycji `arguments` odrzuca wyłącznie znaki
sterujące** — znaki specjalne wolno zacytować, bo każde słowo i tak przechodzi
przez `escapeshellarg()`, a rozbiór na słowa robi ten sam parser, co w wierszu
komend.

**Uzasadnienie:** dwie rzeczy sprawdziły się same z siebie i warto to zapisać.
Kontrakt `ScreenInterface` z kroku 18 udźwignął ekran modułu **bez jednej
zmiany** — `FileInfoScreen` implementuje go tak, jak ekrany rdzenia, a jedyne, co
doszło, to opcjonalne `ReadsContext` obok niego. Kontrakt komendy z kroku 19
udźwignął komendę modułu tak samo: `file-info.jump` nie potrzebowała ani jednej
nowej metody, a podpowiedzi `OnDemand` — zadeklarowane tam bez użytkownika —
dostały pierwszą implementację bez zmiany kontraktu.

**Odrzucone alternatywy:** konwencja położenia plików napisów wraz z refleksją
w `Bootstrap`; osobna zdolność `ProvidesTranslations` w `Presentation`
(nie wymienia ani jednego typu tej warstwy, więc kryterium P2 stawia ją
w `Application`); zakładka „Moduły” jako zwykła lista `ModuleSetting`
z dodatkowym polem „zablokowany wraz z powodem”; tryb edycji wbudowany
w `TextInput` (okno komend musiałoby trzymać go stale włączonym); przeniesienie
`SettingsTab` bliżej ekranu ustawień (`SettingsCursor` musiałby pójść za nim);
biała lista znaków w argumentach polecenia `file`; podawanie kontekstu ekranowi
dopiero po zmianie zaznaczenia; `exec()` zachowany w narzędziu opisującym plik
(limit czasu bez możliwości przerwania procesu jest limitem tylko z nazwy).

## Decyzje z realizacji kroku 21 (2026-08-10)

### D42 — Przeglądarka jako moduł: trzy strefy ekranu, wyjątek przedstawiający się sam i dno stosu z konfiguracji

**Dotyczy:** kroku 21 (pełna treść: sekcje „Rozstrzygnięcia wykonawcze ze startu
kroku” i „Odstępstwa od planu” w [21-przegladarka-jako-modul.md](archiwum/21-przegladarka-jako-modul.md)).

**Decyzja** — osiem rozstrzygnięć wykonawczych podjętych przez użytkownika na
starcie kroku i dziesięć odstępstw wynikłych z otwarcia edytora. Warte
odnotowania poza samą listą są cztery:

- **Górny pas klatki jest polem ekranu, a nie stałym paskiem ścieżki.** Plan
  pytał, co widać na ekranie pomocy i ustawień po tym, jak rdzeń straci katalog;
  użytkownik odwrócił pytanie: pas ma być **strefą do obsłużenia przez moduł**,
  a każdy ekran stawia w nim to, co ma własnego — przeglądarka ścieżkę wraz
  z numerem zaznaczenia, pomoc nazwę i wersję aplikacji, ustawienia położenie
  pliku konfiguracyjnego. Klatka nie zmienia przez to kształtu na żadnym ekranie,
  a zasada „rdzeń rysuje wyłącznie to, co ekran zamówił” obowiązuje bez wyjątku.
  Odrzucono: znikanie strefy na ekranach rdzenia (zmiana wyglądu) i wypełnianie
  jej przez rdzeń z kontekstu sesji (rdzeń rysowałby coś, czego nikt nie zamówił).
- **Wyjątek modułu przedstawia się sam** — nowy interfejs
  `Domain\Exception\DescribesProblem` (klucz katalogu plus parametry). Problemu
  nie było w planie, a blokował główne kryterium kroku: `ProblemPresenter`
  rozpoznawał wyjątki **po klasie** i wymieniał dwa wyjątki katalogu, więc rdzeń
  wciąż wiedziałby, czym jest katalog. Reguła ze SKILL-a obowiązuje odtąd
  wyłącznie dla wyjątków rdzenia. Odrzucono: nową zdolność modułu (kryterium
  kroku mówiło wprost, że kontrakt nie ma urosnąć) oraz łapanie własnych wyjątków
  przez moduł (krok 20 ustalił odwrotnie — wyjątek modułu jedzie istniejącą
  obsługą w `InputHandler`).
- **Wybór dna stosu wyszedł z `Bootstrapu` do `StartupScreen`.** Cztery drogi
  awaryjne (moduł domyślny wyłączony, odrzucony, nieobecny, bez ekranu) to cztery
  komunikaty i cztery testy, a `Bootstrap` nie daje się wywołać bez terminala
  i Imagicka. Komunikat mówi o **jednej** przyczynie i nie musi zgadywać, bo
  przypadki się nie nakładają: moduł nieobecny na liście nie ma jak być wyłączony,
  a wyłączony nie jest przez rejestr sprawdzany, więc nie ma jak być odrzucony.
- **Moduł może mieć własne komponenty, a przeglądarka składa się sama.**
  `PathLine` powstał, bo ścieżkę skraca się od lewej, a `Label::fit()` ucina
  koniec — postawiony w komponentach rdzenia przywróciłby mu `DirectoryPath`.
  `PreviewBox` powstał, bo ekran pytany o strefy **przed** podziałem okna nie wie,
  czy pas podglądu powstanie; liczenie podglądu dopiero przy rysowaniu odtwarza
  dawne zachowanie co do taktu. `BrowserModule` buduje repozytorium, przypadki
  użycia, ekran i komendę sam — inaczej `Bootstrap` poznałby
  `FilesystemDirectoryRepository` i `DirectoryPath` — a robi to **leniwie**, bo
  napisy modułu wchodzą do katalogu dopiero po zbudowaniu rejestru i moduł
  składany zachłannie wypisałby użytkownikowi surowy klucz.

Ponadto: **stary klucz `showHiddenEntries` jest przepisywany raz** do
`modules.browser.showHidden`, wbrew regule kroku 14 o milczącym pomijaniu
nieznanych kluczy — ceną są trzy stałe, przez które usługa konfiguracji zna nazwę
jednego modułu; **`FileInfo` zostaje przy `ProvidesCommands` z pustą listą**, bo
moduł ma się rozrastać (krok 25, wtedy numerowany jako 22 — D43); **klawisze
przeglądarki przeniosły się w oknie
pomocy** z ogólnego spisu na jej własną zakładkę i jest to jedyne miejsce,
w którym przenosiny widać w interfejsie.

**Uzasadnienie:** dwie rzeczy sprawdziły się same z siebie i warto to zapisać.
**Kontrakt modułu z kroku 20 udźwignął główną funkcję aplikacji bez jednej nowej
metody** — a to był cały sprawdzian tego kroku; zmienił się wyłącznie kontrakt
ekranu, i to było w planie (D40, P6). **`ChangeModuleSettingUseCase` udźwignął
ustawienie zmieniane klawiszem**, w środku klatki, wraz z ponownym odczytem
katalogu: `shift()` napisany dla strzałki na ekranie ustawień obsłużył `.`
w przeglądarce bez różnicy. Plan kazał zapisać, gdyby nie udźwignął — udźwignął.

**Odrzucone alternatywy:** kontrakt stref jako pięć metod bliższych dzisiejszemu
kształtowi (`headerLabelKey()`, `drawHeader()`, `usesPreview()`…); katalog modułu
w polu ekranu zamiast w osobnym `BrowserState` (komenda `browser.jump` zależałaby
od ekranu, a publikacja kontekstu miałaby dwa wejścia); milczące pominięcie
starego klucza `showHiddenEntries`; jeden ogólny komunikat „moduł nie mógł zostać
uruchomiony” zamiast czterech konkretnych; `FileInfo` tracący `ProvidesCommands`
po oddaniu komendy skoku; rozbudowa `FileInfo` w tym samym kroku, co przenosiny
(krok 21 zmienia naraz kontrakt ekranu, warstwę domeny, stan pętli, stos ekranów,
bootstrap i konfigurację — siódma zmienna zamieniłaby błąd w jednym w błąd
udający drugi; rozbudowa dostała numer 22).

### D43 — Pełny obraz pliku wymusza trzy komponenty rdzenia: przenumerowanie kroków 22–25

**Dotyczy:** kroków 22, 23, 24 i 25 (pełna treść: [22-zwijana-sekcja.md](archiwum/22-zwijana-sekcja.md),
[23-pasek-postepu.md](archiwum/23-pasek-postepu.md), [24-podzial-ekranu.md](archiwum/24-podzial-ekranu.md),
[25-pelny-obraz-pliku.md](archiwum/25-pelny-obraz-pliku.md)).

**Data:** 2026-08-10.

**Decyzja** — dziewięć rozstrzygnięć użytkownika na starcie kroku „pełny obraz
stanu pliku” (P1–P9 w [25-pelny-obraz-pliku.md](archiwum/25-pelny-obraz-pliku.md)), z których
**trzy wyszły poza moduł** i przewróciły kolejność planu.

Rozstrzygnięcia dotyczące samego modułu:

- **Wchodzą wszystkie cztery źródła**: `stat`/`lstat`, dowiązanie symboliczne,
  `du` i `sha256` — obok istniejącego `file`. `sha256` wchodzi mimo zastrzeżenia
  planu („koszt rośnie z rozmiarem pliku bez ograniczenia”), ale **za
  przełącznikiem domyślnie wyłączonym i z konfigurowalnym limitem rozmiaru**.
- **`du` i `sha256` liczą się w tle, a praca tłowa zostaje prywatną sprawą
  modułu.** Kontrakt modułu z kroku 20 **nie rośnie** — i to jest sprawdzian
  kroku 25, tak samo jak dla kroku 21 był nim kontrakt nietknięty przez główną
  funkcję aplikacji. Odrzucono: twardy limit czasu z blokowaniem klatki
  (`du` na katalogu domowym kończyłby się przerwaniem, nie wynikiem) oraz nową
  zdolność kontraktu (krok 20 wykluczał „moduł działający w tle” wprost, więc
  byłoby to rozszerzenie zakresu, a nie zapełnienie luki).
- **Moduł zaczyna opisywać także katalogi.** Bez tego `du` traci połowę sensu.
- **Wolne źródło ma wiersz od pierwszej klatki, z wartością „liczę…”.** Układ
  ekranu nie skacze, a użytkownik widzi różnicę między „liczy się” a „nie da się
  policzyć”. Odrzucono: wiersz pojawiający się dopiero z wynikiem (rosnąca lista
  przesuwa treść pod kursorem przewijania).
- **`Bootstrap` przestaje wiązać wnętrze `FileInfo`** — moduł składa się sam
  i leniwie, jak `BrowserModule` po kroku 21. Spłaca to dług zapisany wprost
  w dzienniku kroku 21 („do wyrównania przy okazji rozbudowy”).

**Trzy rozstrzygnięcia, które wyszły poza moduł** — i to one są istotą tego
wpisu:

- **Zwijana sekcja jest komponentem rdzenia**, nie modułu (krok 22). Pytanie planu
  brzmiało „sekcje czy jeden strumień wierszy”; użytkownik wybrał sekcje
  **zwijane** i od razu wskazał ich miejsce. Pierwszy prawdziwy użytkownik już
  istnieje i nie jest nim `FileInfo`: spis klawiszy w oknie pomocy grupuje wiersze
  po ekranach i po dołożeniu modułów urósł ponad wysokość okna.
- **Pasek postępu z tekstem jest komponentem rdzenia** (krok 23), a `du`
  i `sha256` mają nim mówić o sobie. Komponent powstaje **przed** swoim
  użytkownikiem i jest to świadoma cena; osłoną są testy przypadków brzegowych
  i scenariusz `bin/render-bench` dowożone w tym samym kroku.
- **Podział ekranu jest komponentem rdzenia** (krok 24), w znaczeniu
  najmocniejszym z trzech przedstawionych: **dwa niezależne ekrany widoczne
  naraz, każdy z własnym `handle()`**. Znosi to wykluczenie z kroku 21 („dwa
  moduły widoczne naraz”) i wyjmuje „widok dwupanelowy” z listy „Zakres poza
  MVP”. Odrzucono: sam komponent układu ze stałą granicą oraz granicę ruchomą
  bez niezależnych ekranów.

**Skutek dla numeracji: kroki zostały przenumerowane.** „Pełny obraz stanu pliku”
nosił numer 22; komponenty, których jest odbiorcą, dostały 22, 23 i 24, a on sam
— **25**. Zasada z [00-index.md](00-index.md), że kolejność realizacji pokrywa się
z numeracją, zostaje przez to prawdziwa; precedens jest dwukrotny (D9, D35).
Odrzucono: zostawienie numeru 22 i dopisanie komponentów jako 23–25 wykonywanych
przed nim — numeracja przestałaby wtedy znaczyć kolejność, a jedynym miejscem
mówiącym prawdę byłby graf zależności.

**Skutek dla podziału na kroki:** trzy komponenty to **trzy osobne kroki**, nie
jeden. Każdy ma własny kontrakt, własny pomiar klatki i własne testy, a krok 18
pokazał, że zmiana kilku kontraktów naraz jest tym rodzajem zmiany, w której błąd
w jednym udaje błąd w drugim (stąd jego status „Ukończony z zastrzeżeniem”).

**Uzasadnienie kolejności:** krok 25 czeka na **wszystkie trzy** komponenty, także
na podział ekranu, mimo że opis pliku mógłby się bez niego obejść. Powód podał
użytkownik: opis może użyć podziału (sekcje po lewej, coś innego po prawej),
a wtedy `FileInfo` jest użytkownikiem wszystkich trzech i żaden nie powstaje na
domysł. Gdyby z podziału ostatecznie nie skorzystał, dziennik kroku 25 ma
powiedzieć wprost, że zależność była wyłącznie kolejnościowa.

## Decyzje z realizacji kroku 23 (2026-08-10)

### D44 — Pasek postępu: takt bez wymuszania, zegar dla ekranu i pierwszy użytkownik `Weight::Fill`

**Dotyczy:** kroku 23 (pełna treść: [23-pasek-postepu.md](archiwum/23-pasek-postepu.md)).

**Data:** 2026-08-10.

**Decyzja** — pięć rozstrzygnięć, z których **dwa ostatnie postawił kod, a nie
plan**.

Rozstrzygnięcia użytkownika przed otwarciem edytora:

- **Postęp nieznany rysuje się wędrującym wypełnieniem** — odcinek szerokości
  ¼ toru, trójkątna fala, 1,2 s w jedną stronę. Odrzucono pulsujące tło:
  wymagałoby półcieni, a klatka bez bitmapy zawiera dziś wyłącznie czyste kolory
  motywu i na tym stoi szybka ścieżka palety (D34, D37). Odrzucono też sam tekst
  bez ruchu — pasek, który stoi, czyta się jako zawieszony.
- **Tor rysuje się rolą `Surface`, wypełnienie `Accent`, napis `Background` na
  wypełnieniu i `Text` poza nim.** Paleta nietknięta; nowa rola znaczyłaby nowy
  kolor w każdym z czterech motywów i jeden wpis więcej w liczonej palecie (D37).
- **Pasek wypełnia prostokąt, który dostał** — nie ma własnej wysokości, więc nie
  wystawia `height()`. Napis stoi wyśrodkowany w środkowym wierszu, a zbyt długi
  kończy się wielokropkiem przez istniejące `Label::fit()`.
- **Liczbę procent dokłada pasek**, nie wołający. To czyni `ProgressBar`
  **pierwszym komponentem znającym tłumacza** — dotąd napisy tłumaczyły ekrany
  i okna, a komponenty dostawały gotowe. Port jest opcjonalny i jego brak znaczy
  zapis surowy, dokładnie dla jednego wołającego: `ScenarioFactory`, którego treść
  nie przechodzi przez katalog z rozmysłu (D33).

**Rozstrzygnięcie o takcie klatki postawił stan pętli, a nie wybór.** Plan
przewidywał trzy drogi (pętla rysuje zawsze / ekran mówi „jestem żywy” /
pasek nie wymusza nic) i kazał wybrać po przeczytaniu kodu. Kod pokazał, że
pierwsza z nich **obowiązuje od kroku 09**: `GameLoop` przerysowuje klatkę
w każdym takcie, niezależnie od tego, czy cokolwiek się zmieniło. Wymuszanie nie
ma więc czego wymuszać i `GameLoop` zostaje **nietknięty**. Zostało pytanie,
którego plan nie zadał: **skąd ekran bierze zegar**. Odpowiedź — istniejącym
`NeedsTime`, o który `FrameComposer` zaczyna pytać także ekran, tą samą drogą
i w tym samym miejscu, co `ReadsContext`. `ScreenInterface` **nie rośnie po raz
drugi od kroku 18**, bo `NeedsTime` jest interfejsem deklarowanym osobno.

Cena zapisana wprost: element ruchomy z założenia **nie trafia do pamięci
podręcznej wierszy** (D34) — jego wiersz jest w każdej klatce inny. Dlatego pasek
dostał własny scenariusz pomiaru, a nie doklejkę do istniejącego.

**Pomiar znalazł błąd starszy od tego kroku.** Pierwszy przebieg pokazał
scenariusz `progress` kosztujący **85,3 ms**, z czego 73,4 ms samo rysowanie —
czterokrotność najdroższej dotąd klatki. Powód: `Weight::Fill` szedł przez
`ImagickDraw::drawImage()`, którego koszt zależy od **wielkości płótna, a nie
kształtu** — czyli wpadał w pułapkę opisaną trzy metody niżej w tym samym pliku
(krok 17, dźwignia 5; krok 18, krawędź zaznaczenia, +17 ms). Nie zauważono tego
wcześniej, bo **`Weight::Fill` nie miał w aplikacji ani jednego użytkownika**:
pasek zaznaczenia rysuje się `RoundRect`iem, przegroda w pasku stanu włosem,
krawędź zaznaczenia krawędzią. Pasek postępu jest pierwszym i od razu trafił na
minę. Naprawa jest tą samą, którą projekt stosuje od kroku 17: bitmapa
z `RowBitmapCache` składana przez `compositeImage()`. Wynik: **85,3 → 23,5 ms**,
rysowanie 73,4 → 11,5 ms, przy nietkniętych pikselach (zrzut przed i po co do
znaku ten sam).

Wniosek szerszy niż jeden prymityw: **gałąź kodu bez użytkownika nie jest
zmierzona, choćby leżała w najlepiej rozliczonym pliku projektu.** Reguła 13
(„żaden komponent bez prawdziwego użytkownika”) mówiła dotąd o projektowaniu API
na domysł; ten krok pokazuje jej drugą stronę — o wydajności takiej gałęzi też
nic nie wiadomo.

**Świadome złamanie reguły 13.** `ProgressBar` powstaje przed swoim prawdziwym
odbiorcą (krok 25: `du` i `sha256`) — plan przewidział to wprost i nazwał osłonę:
21 testów przypadków brzegowych oraz scenariusz pomiaru, dowiezione w tym samym
kroku. Zapisano w `SKILL.md` jako **jawny wyjątek, nie precedens**: następny
komponent bez użytkownika wymaga takiej samej zgody, a nie powołania się na ten.
Ta sama uwaga dotyczy `NeedsTime` na ekranie — ścieżka ma dziś wyłącznie
użytkownika testowego i istnieje po to, żeby krok 25 miał czym poruszyć pasek.

## Decyzje z realizacji kroku 24 (2026-08-10)

### D45 — Podział ekranu należy do modułu: dwa panele w jednym ekranie, oprawa w płaszczyźnie spodniej

**Dotyczy:** kroku 24 (pełna treść: [24-podzial-ekranu.md](archiwum/24-podzial-ekranu.md)).

**Data:** 2026-08-10.

**Decyzja** — podział ekranu **nie znosi zasady „jeden ekran naraz”**, tylko
dzieli prostokąt **wewnątrz** ekranu. To rozstrzygnięcie użytkownika ze startu
kroku i zdejmuje ono z planu dwie trzecie zakresu.

Pytanie brzmiało: „podział włączony, ognisko po prawej, naciskasz `F1` — gdzie
pojawi się pomoc?”. Odpowiedź: **„zastąpić ekran, na którym jest podział; `F1`
zastępuje całość i rysuje swoją treść ekranu”**, wraz z regułą ogólniejszą:
**„każdy moduł sam definiuje, jak ma wyglądać jego interfejs z dostępnych
komponentów”**. Wynika z tego wszystko pozostałe:

- **Rdzeń nie wie o podziale.** `ScreenStack` ma nadal dno i jedno piętro,
  `ScreenInterface` ma nadal sześć metod, `InputHandler` oddaje klawisz jednemu
  ekranowi, `LoopState` i `HudLayout` są nietknięte, a w `Settings` nie przybył
  ani jeden klucz. Plan przewidywał zmiany we wszystkich tych miejscach.
- **Rdzeń wnosi klocek, nie mechanizm**: `Split` (geometria dwóch prostokątów,
  obie osie, progi czytelności 72 kolumny i 14 wierszy) oraz `SplitState` (trzecia
  klasa stanu między klatkami, po `ScrollWindow` i `SectionState`).
- **Podział jest ustawieniem modułu**, w podprzestrzeni `modules.browser`, a nie
  kluczem rdzenia. Przełącznikiem, a nie wyborem z listy, bo wartości wyboru ekran
  ustawień pokazuje surowo — „vertical” zostałoby w polskim interfejsie po
  angielsku.
- **Ognisko nie przekracza granicy ekranu**, więc rozważany wcześniej `NeedsFocus`
  **nie powstał**. Wewnątrz ekranu wędrówkę klawisza obsługuje `FocusableInterface`,
  który istnieje od kroku 18.
- **Pierwszym i jedynym użytkownikiem jest przeglądarka**: dwa niezależne katalogi,
  dwa kursory, `Tab` między nimi. Reguła 13 jest spełniona bez wyjątku — inaczej
  niż w kroku 23.

**Jeden wyłom był konieczny i ma własny interfejs.** Użytkownik wybrał **dwie
osobne ramki** jako sposób pokazania ogniska, a to znaczy, że rdzeń musi
**przestać** oprawiać strefę środkową: wie o jednej strefie i nie wie, który panel
jest czynny. Powstał `Presentation\Ui\DrawsOwnFrame` — osobny interfejs
z **metodą**, bo odpowiedź zależy od ustawień i od szerokości okna. `ScreenInterface`
nie urósł po raz trzeci (po krokach 18 i 21), a wybór drogi „osobny interfejs
zamiast metody w kontrakcie” jest powtórzeniem rozstrzygnięcia, które użytkownik
podjął w tej samej rozmowie przy `NeedsFocus`.

**Pomiar wymusił drugą wersję tego interfejsu i to jest najcenniejsza treść
kroku.** Pierwsza wersja odpowiadała `bool` i kazała ekranowi rysować oprawę
w `draw()`, czyli na płaszczyźnie **treści**. Klatka podzielona kosztowała wtedy
**62,2 ms** przy budżecie 33 ms. Rozbiór przez usuwanie prymitywów pokazał, że
**dwie obwódki to 27 ms** — obrys z wygładzaniem idzie przez
`ImagickDraw::drawImage()`, którego koszt zależy od wielkości płótna, a nie
kształtu. Nikt tego wcześniej nie zmierzył, bo obwódki rysowały się **wyłącznie na
płaszczyźnie spodniej**, pamiętanej między klatkami — w tabeli pomiaru pokazywały
się jako 0,0 ms.

Poprawka nie tknęła renderera: metoda **oddaje prymitywy**, a `FrameComposer`
kładzie je na płaszczyźnie spodniej. Klatka spadła z 62,2 do 25,0 ms przy zrzucie
piksel w piksel takim samym. Stąd reguła zapisana w `architecture.md`
i w `SKILL.md`: **co się między klatkami nie zmienia, należy do płaszczyzny
spodniej — niezależnie od tego, kto to narysował.**

To jest druga z rzędu decyzja, w której pomiar znalazł koszt ukryty za pamięcią
podręczną (poprzednia: D44, `Weight::Fill`). Wspólny mianownik obu: **gałąź, która
dotąd wykonywała się raz na uruchomienie, po przeniesieniu do klatki kosztuje
tyle, ile nikt nie sprawdził.**

## Decyzje z realizacji kroku 25 (2026-08-10)

### D46 — Praca dłuższa od klatki: kawałek na klatkę, na żądanie, z właścicielem — a proces potomny osobnym krokiem

**Dotyczy:** kroków 25 i 26 (pełna treść:
[25-pelny-obraz-pliku.md](archiwum/25-pelny-obraz-pliku.md), [26-proces-tlowy.md](archiwum/26-proces-tlowy.md)).

**Data:** 2026-08-10.

**Decyzja** — praca, która nie mieści się w jednej klatce, dzieli się na kawałki
po jednym na klatkę, a **proces potomny w tle dostaje własny krok planu**.

Rozstrzygnięcie użytkownika ze startu kroku 25 brzmiało: *„`hash_init()` +
`hash_update_stream()` w procesie w tle, który przekazuje dane do procesu
głównego. Komponent procesu robimy jako osobny krok planu. Teraz jedynie własny
odczyt po kawałku.”* Rozcina to pracę tłową na dwie i obie mają inną naturę:

- **odczyt własny** (`sha256`) — dzieje się w procesie aplikacji, kawałkami
  w klatce; zna postęp co do bajta, przerywa się zamknięciem uchwytu i nie ma
  czego osierocić przy wyjściu. **Wchodzi w kroku 25.**
- **proces potomny** (`du`) — dzieje się poza aplikacją; postępu nie zna, wymaga
  doglądania, przerywania i sprzątania po sobie przy wyjściu z aplikacji.
  **Wchodzi w kroku 26.**

Skutek dla kroku 25 jest bolesny i zapisany wprost: **wiersza „zajęte na dysku”
nie ma wcale**, choć P1 planu żądał wszystkich czterech źródeł. Pokazanie go
z wartością, której nie ma jak policzyć, byłoby gorsze niż jego brak. Razem z nim
przesunęły się dwie pozycje ustawień.

**Wzorzec pracy kawałkowej** — trzy części, wszystkie obowiązkowe:

1. **Port mówi o pracy, a nie o wyniku.** Nie ma `checksum(path): string` — są
   `begin()`, `advance($bytes)` i `stop()`. Kształt kontraktu wymusza to, że
   wynik nie jest dostępny od razu, więc nikt nie napisze przypadkiem kodu, który
   na niego czeka.
2. **Stan pracy jest daną oglądaną co klatkę** (`ChecksumState`: etap, ułamek,
   wynik albo powód niepowodzenia). To z niej bierze się wypełnienie paska
   postępu — i dlatego postęp jest **prawdziwy**. Ten sam argument przesądził
   o odrzuceniu `sha256sum` jako procesu potomnego: polecenie nie mówi o sobie
   nic, aż skończy, więc pasek chodziłby w trybie „nie wiadomo ile jeszcze”
   akurat dla źródła, które postęp zna z natury.
3. **Praca ma właściciela, który ją przerywa** — `FileInfoState`, przy zmianie
   zaznaczenia i przy `reset()`.

**Praca zaczyna się na żądanie, nie sama z siebie** (rozstrzygnięcie nr 7).
Zaznaczenie zmienia się przy przewijaniu trzydzieści razy na sekundę; praca
uruchamiana odruchowo byłaby trzydziestoma pracami przerwanymi w tej samej
sekundzie. Wiersz stoi więc od pierwszej klatki z podpowiedzią, którym klawiszem
go policzyć — co jest zarazem **odstępstwem od P5** („wiersz z wartością
«liczę…»”), wprowadzonym świadomie: „liczę…” bez liczenia byłoby nieprawdą.

**Trzy porty zamiast jednego** (rozstrzygnięcie nr 1): każdy prosi o dokładnie to,
czego potrzebuje. `describe()` przyjmuje limit czasu i argumenty, bo za nim stoi
proces, który potrafi się zawiesić; `stat()` nie przyjmuje ani jednego i nie ma
po co ich znać.

**Skutek dla kroków 22–24, i to jest najcenniejsza treść tego wpisu.** Sekcje,
pasek postępu i podział ekranu powstały osobno, każdy z własnym pomiarem, a pasek
**bez użytkownika w aplikacji** — świadome złamanie reguły 13, zapisane wtedy
jako jawny wyjątek (D44). Krok 25 złożył wszystkie trzy w jednym ekranie i **nie
wymagał ani jednej poprawki w rdzeniu**. To jest dowód, którego tamte kroki nie
mogły dostarczyć same, i zarazem miara tego, ile wolno projektować na domysł:
trzy klocki naraz — tak, ale tylko dlatego, że każdy miał wcześniej rozpisany
kontrakt i pomiar.

**Dług z kroku 21 spłacony i pilnowany maszynowo.** `Bootstrap` widzi z każdego
modułu wyłącznie jego klasę główną, a sprawdza to nowy test w
`CoreKnowsNothingAboutFilesTest`.

### D47 — Proces tłowy: jedna praca z uchwytem, sprzątanie dwiema drogami, a `du` tylko dla katalogów

**Dotyczy:** kroku 26 (pełna treść: [26-proces-tlowy.md](archiwum/26-proces-tlowy.md)).

**Data:** 2026-08-11.

Krok 26 dopisał do wzorca pracy kawałkowej (D46) jego **czwartą regułę** i pięć
rozstrzygnięć, które plan zostawił na start. Wszystkie pięć rozstrzygnął
użytkownik; poniżej każde wraz z tym, co za nim stoi.

**1. Jedna praca naraz.** Usługa prowadzi jedną pracę, a nowe zamówienie
przerywa poprzednie — dokładnie jak `ChecksumPort` w kroku 25 i z tego samego
powodu: widoczny ekran jest jeden, pasek postępu jeden, a druga praca zaczęta bez
przerwania pierwszej byłaby drugim procesem, o którym nikt już nie pamięta.
Odrzucono rejestr wielu prac — kosztowałby przechodzenie po nim przy każdym
sprzątaniu i pytanie o zachowanie przy limicie, czyli dokładnie tę klasę błędów,
przed którą plan kroku ostrzegał.

**2. Uchwyt jest obiektem** (`BackgroundHandle`), choć przy jednej pracy port
mógłby go nie mieć wcale. Zarabia na siebie w jednej, konkretnej sytuacji:
mechanizm jest **rdzeniowy**, więc pracę zamawia dowolny moduł, a kolejne
zamówienie przerywa poprzednie. Bez uchwytu moduł, którego pracę wyparto,
zobaczyłby cudzy stan i wziąłby go za swój — pokazałby wynik `du` w miejscu,
w którym liczył co innego. Z uchwytem `poll()` nieaktualnej pracy oddaje `Idle`.

**3. Sprzątanie dwiema drogami naraz** — i to jest najważniejsze rozstrzygnięcie
kroku. Jawne `shutdown()` w `Bootstrap::shutdown()` **oraz**
`register_shutdown_function` rejestrowana leniwie przy pierwszym uruchomieniu
pracy. Pierwsza droga jest widoczna w kodzie i idzie tą samą ścieżką, którą
terminal wraca do trybu normalnego; druga łapie to, czego pierwsza nie dosięga —
błąd krytyczny i `exit()` z boku, czyli **dokładnie ten przypadek, w którym
`bin/light-manager` wychodzi przez `exit(1)` bez wołania `shutdown()`**. Odrzucono
destruktor Singletona: kolejność niszczenia obiektów przy zamykaniu procesu nie
jest w PHP gwarantowana, więc sprzątanie zależałoby od rzeczy, na którą nie mamy
wpływu. Układ jest zresztą **ten sam, którym `TerminalService` broni trybu
surowego** — jedna droga czytelna, druga nieomylna.

**4. `du` liczy się tylko dla katalogów.** Rozstrzygnięcie zapadło po znalezieniu
w kodzie rzeczy, której plan nie przewidywał: sekcja „Rozmiar” **już** pokazuje
wiersz „Bloki i-węzła” (`blocks × 512` z `lstat`), a dla zwykłego pliku to jest
dokładnie zajętość na dysku — policzona natychmiast i bez procesu. Uruchamianie
`du` na plik byłoby więc procesem potomnym po liczbę, którą użytkownik ma już na
ekranie. Dla katalogu wiersz mówi to, czego `lstat` nie wie i wiedzieć nie może:
sumę po całym drzewie.

**5. Wiersz stoi od pierwszej klatki z podpowiedzią** „(klawisz d liczy)” — tak
samo, jak suma kontrolna od kroku 25. Jedna zasada dla obu prac na żądanie zamiast
dwóch, a wiersz dokładany dopiero po naciśnięciu klawisza przesuwałby układ pod
kursorem przewijania — czyli robiłby to, czego krok 25 świadomie unikał, odbierając
wiersz sekcjom na rzecz paska.

**Czwarta reguła pracy kawałkowej**, zapisana w `docs/architecture.md`: *potomek
nie ma prawa przeżyć procesu, który go uruchomił*. Towarzyszą jej trzy rzeczy
niosące własne klasy błędów: **oba potoki czytane co klatkę** (nieczytany potok
zatrzymuje potomka, gdy się zapełni — `du` na katalogu domowym wypisuje setki
wierszy „brak dostępu” na strumień błędów; czytamy go i wyrzucamy, bo sklejenie
strumieni zamieniłoby liczbę do odczytania w stertę do przeszukania), **kod
wyjścia różny od zera nie jest sam z siebie niepowodzeniem** (`du` kończy się
jedynką za każdy nieprzeczytany katalog, a mimo to podaje sumę tego, co
przeczytało — niepowodzeniem jest dopiero brak liczby na wyjściu) i **`SIGKILL`
zamiast `SIGTERM`**, bo przy wyjściu z aplikacji nie ma już komu poczekać, aż
potomek rozmyśli się nad obsługą sygnału.

**Pomiar sięgnął po raz pierwszy poza PHP.** Scenariusz `background` rysuje klatkę
**co do prymitywu równą `chrome-text`** — potwierdza to identyczny rozmiar bloba —
ale przy uruchomionym procesie potomnym, doglądanym raz na klatkę wewnątrz
mierzonego czasu. Różnica między tymi dwoma wierszami jest więc w całości ceną
pracy toczącej się obok pętli. Potomek **milczy przez cały pomiar** i to nie jest
uproszczenie, tylko wierność: `du` nie mówi o sobie nic, aż skończy, więc dokładnie
tak wyglądają te cztery sekundy, o które w kroku chodzi.

**Dług spłacony przy okazji.** Rachunek zamieniający bajty na czytelny zapis stał
jako prywatna metoda w `InspectSelectedEntryUseCase`; wiersz „zajęte na dysku”
byłby jego trzecim wołającym w tym samym module, więc wyszedł do
`Module\FileInfo\Application\SizeText`. Wyprowadzenie sięga **wyłącznie w obręb
modułu** — przeglądarka ma własny, bliźniaczy rachunek w `EntryList` i on tam
zostaje, bo moduł nigdy nie sięga do innego modułu (reguła 15). Wspólny formatownik
musiałby najpierw zostać częścią rdzenia, a to osobna decyzja.

### D48 — Sześć nowych komponentów rdzenia, rytm „jeden komponent — jeden krok” i otwarcie zamkniętego słownika prymitywów

**Dotyczy:** Fazy VII planu, kroków 27–32
([00-index.md](00-index.md)).

**Data:** 2026-08-11.

Po ukończeniu kroku 26 przejrzano rdzeń pod kątem braków: 18 komponentów,
2 kontenery, 3 klasy stanu między klatkami i **zamknięty słownik 7 prymitywów**.
Z przeglądu wyszło sześć propozycji; użytkownik przyjął **wszystkie sześć**.

**Kryterium porządkujące — reguła 13, a nie atrakcyjność.** Propozycje ustawiono
wedle jedynego pytania, które w tym projekcie naprawdę boli: *czy odbiorca już
siedzi w kodzie*. Trzy pierwsze mają go dziś i to jest ich główna zaleta:

- **27 `Table`** — `ListRow` ma dokładnie dwa pola, więc data i prawa nie mają
  w liście plików gdzie stanąć; `EntryList` przechodzi na kolumny w tym samym
  kroku.
- **28 `ConfirmOverlay`** — „Przywróć ustawienia domyślne” jest **jedyną dziś
  nieodwracalną akcją bez potwierdzenia**: kasuje konfigurację po jednym
  `Enter`. Odbiorca gotowy, a przy okazji brama do operacji na plikach.
- **29 `TextView`** — prawy panel opisu pliku pokazuje dla tekstu napis
  „(brak podglądu)”, bo `ImageBox` umie tylko obraz.

Trzy kolejne (**30** filtrowanie z podświetleniem, **31** drzewo, **32** menu
kontekstowe) dowożą odbiorcę razem z komponentem i to jest **świadome odstępstwo
od reguły 13**, przyjęte tak samo, jak przy `ProgressBar`ze w kroku 23 (D44):
jawnie, z nazwiskiem i bez powoływania się na poprzedni wyjątek.

**Rytm: jeden komponent — jeden krok.** Odrzucono jeden wspólny krok na
wszystkie sześć oraz grupowanie po pokrewieństwie. Powód jest zmierzony, a nie
estetyczny: **krok 13 pomylił się dokładnie na braku rozdziału** — koszt
wygładzania podano łącznie dla tekstu i obrysów, więc obrysy wyłączono
niepotrzebnie (D27). Sześć osobnych kroków to sześć osobnych pomiarów „przed
i po”, a przy regresji od razu wiadomo, komu ją przypisać.

**Słownik prymitywów zostaje otwarty — pierwszy raz od kroku 18.** To jest
najcięższa część tego wpisu i dotyczy wyłącznie kroku **30**. Podświetlenie
fragmentu wiersza dawało się zrobić dwiema drogami: wieloma `TextRun` o różnych
rolach (słownik nietknięty, więcej prymitywów na wiersz) albo **nowym, ósmym
prymitywem**. Użytkownik wybrał drugą.

Konsekwencje, wszystkie zapisane w planie kroku 30, żeby nie zaskoczyły
w trakcie:

1. **Obowiązek dla obu rendererów naraz.** Prymityw przechodzi przez
   `FrameRendererPort`, więc `SixelFrameEncoder` musi go narysować, a
   `TextFrameRenderer` — zdegradować do atrybutu komórki. Krok 30 sięga przez to
   do kodu kroków **7 i 8**, czego nie robił żaden krok od czasu 18.
2. **Wejście do podpisu płaszczyzny.** `signature()` karmi wszystkie pamięci
   podręczne z D34; podpis nieobejmujący dopasowania podałby z pamięci klatkę
   z cudzym podświetleniem, a błąd objawiłby się nie wyjątkiem, tylko klatką,
   która nie chce się odświeżyć.
3. **Klatka bez filtra nie ma prawa zdrożeć** — i to jest najważniejsze kryterium
   ukończenia tamtego kroku, ważniejsze od samego filtrowania.

**Zastrzeżenie do kroku 32, przyjęte wbrew rekomendacji.** Menu kontekstowe
odradzano: okno komend z kroku 19 **już jest** listą działań z podpowiedziami,
a menu wnosi ponad nie dwie rzeczy — wybór bez pisania i zawężenie do
zaznaczenia. Użytkownik przyjął krok mimo to, więc plan zapisuje warunek zamiast
sprzeciwu: **menu ma być widokiem na `CommandRegistry`, a nie drugim rejestrem
działań**, a rozstrzygnięcie „czy krok w ogóle wchodzi teraz” stoi jako **pierwsze**
na jego liście startowej. Gdyby okazało się, że menu potrzebuje własnych pozycji
i własnych etykiet — krok czeka na operacje na plikach, bo dopiero one dadzą mu
treść, której okno komend nie ma.

**Faza VII nie jest łańcuchem** i to odróżnia ją od wszystkich poprzednich.
Kroki 27, 28 i 29 są wzajemnie niezależne — dotykają wiersza listy, okna
nakładanego i prawego panelu opisu, czyli trzech rozłącznych miejsc — więc wolno
je robić w dowolnej kolejności. Zbiegają się dopiero w trójce kolejnej:
30 potrzebuje wiersza z 27, 31 potrzebuje wiersza z 27 i wzorca stanu z 22,
a 32 potrzebuje drogi powrotnej decyzji z 28.

**Trzy pozycje zeszły z listy „poza MVP”**: podgląd plików tekstowych (krok 29)
i wyszukiwanie/filtrowanie (krok 30) weszły do planu, a operacje na plikach
zyskały wreszcie prerekwizyt — bez okna potwierdzenia z kroku 28 usuwanie nie ma
prawa powstać.

### D49 — Wiersz wielokolumnowy: jedna reguła podziału na dwie osie, tabela obok listy i `stat` zamiast dwóch pytań

**Dotyczy:** kroku 27 (pełna treść: [27-tabela-kolumn.md](archiwum/27-tabela-kolumn.md)).

**Data:** 2026-08-11.

Krok zdjął z listy plików sufit dwóch pól. Sześć rozstrzygnięć, wszystkie
użytkownika; poniżej te, które zmieniły kształt kodu.

**1. `Table` stoi obok `ListView`, a nie zamiast niego.** Odrzucono wariant,
w którym `ListRow` staje się przypadkiem szczególnym wiersza o N kolumnach.
Powód jest nazewniczy, a nie techniczny: opis pliku to **etykieta i wartość**,
a nie tabela o dwóch kolumnach, i zmuszanie sekcji z kroku 22 do myślenia
kolumnami byłoby nazwaniem rzeczy nie po imieniu. Cena — pętla po wierszach
w rdzeniu dwa razy — okazała się **niższa, niż zakładano przy pytaniu**: krok 18
wydzielił już `Highlight::under()` właśnie po to, żeby zaznaczenie wyglądało
identycznie u każdego, kto je rysuje, a `Scrollbar` jest prymitywem. Powtórzeniem
jest sama pętla, nie mechanizm.

**2. Rozdział miejsca wyprowadzony do `Distribution`, a miara do `Span`.**
Rachunek istniał od kroku 18 w `VStack`, a `Slot` **już wtedy** deklarował
w dokumentacji, że jego liczby są bezwymiarowe — „wiersze w kontenerze pionowym,
kolumny w poziomym”. Zapowiedź czekała półtora kroku na drugą oś. Rozdzielenie
miary od uczestnika było konieczne, bo `Slot` trzyma dziecko-komponent, a
**kolumna żadnego dziecka nie ma**: komórka jest napisem. Bez tego kolumna
musiałaby udawać szczelinę z pustym dzieckiem albo dostać własny rachunek — a ta
sama reguła w dwóch plikach rozjeżdża się przy pierwszej zmianie. `VStack`
zachował się co do wiersza, co potwierdziło 131 istniejących testów.

**Wyprowadzenie ujawniło pułapkę, której nikt wcześniej nie widział**, i to jest
najcenniejszy skutek uboczny tego kroku. `Slot::fixed()` ustawiał **minimum
zero**, więc uczestnik o „stałej” mierze kurczył się stopniowo aż do zera.
Dla pasa podglądu to jest właściwe — pas niższy o wiersz nadal jest pasem. Dla
kolumny z datą jest błędem: zwężona o trzy znaki nie jest „węższą datą”, tylko
napisem `2026-08-…`, a przy okazji zabiera te znaki nazwie. Stąd druga postać
miary — **`Span::rigid()`: tyle albo nic** — i dwa sąsiadujące testy, które
pokazują różnicę obok siebie. Bez testu pisanego wprost na rachunek różnica
wyszłaby dopiero na ekranie użytkownika.

**Druga pułapka wyszła w teście prymitywów**: przy dosunięciu do prawej odstęp
od sąsiada musi leżeć po **prawej** stronie komórki, a nie po lewej. Pierwsza
wersja dosuwała treść do brzegu kolumny, więc rozmiar sklejał się z datą
dokładnie wtedy, gdy był najdłuższy — czyli w przypadku, w którym najbardziej
potrzeba go odróżnić.

**3. `Entry` zyskał czas zmiany i prawa, a repozytorium pyta system raz zamiast
dwa razy.** To rozstrzygnięcie wyglądało na najdroższe, a okazało się **darmowe
albo tańsze od stanu poprzedniego**: `FilesystemDirectoryRepository` wołał dotąd
`is_dir()`, a potem `filesize()` — czyli dwa razy to samo `stat`. Jedno `stat()`
w ich miejsce daje rodzaj, rozmiar, czas i prawa naraz. Kolumny nie kosztowały
więc ani jednego dodatkowego wywołania systemowego, co przy katalogu
o dziesięciu tysiącach wpisów jest różnicą wartą nazwania. `stat()`, a nie
`lstat()`, i pilnuje tego osobny test: dowiązanie do katalogu ma się nadal
zachowywać jak katalog.

Nowe pola są **`null`-owalne** i to jest uczciwa odpowiedź, a nie wygoda: wpis,
o który nie da się zapytać (zerwane dowiązanie, plik zniknięty między `scandir()`
a `stat()`), naprawdę nie ma daty. Kolumna pokazuje wtedy pustkę zamiast zera
udającego 1 stycznia 1970.

**4. Kolejność ustępowania w kodzie, nie w ustawieniach.** Prawa ustępują
pierwsze, po nich data, po niej rozmiar, a nazwa nie ustępuje nigdy. Odrzucono
przełącznik na każdą kolumnę: ustępowanie i tak musi być zaprogramowane, więc
cztery przełączniki dawałyby użytkownikowi władzę nad tym, co w wąskim oknie
zniknie samo. Zostały dwa przełączniki — „kolumny szczegółów” (domyślnie
**włączone**) i „nazwy kolumn nad listą” (domyślnie **wyłączone**, bo kosztują
wiersz listy w każdym oknie).

**Minimum kolumny nazwy okazało się ważniejsze od szerokości kolumn stałych.**
To ono rozstrzyga, kiedy szczegóły zaczynają ustępować: dopóki suma minimów
mieści się w prostokącie, nikt nie ustępuje, a elastyczna dostaje tylko resztę.
Minimum równe czterem znaczyłoby „nazwa może zejść do czterech znaków, byle data
została” — czyli układ, w którym najważniejsza kolumna ustępuje najmniej ważnym.
Stąd dwadzieścia znaków i komentarz przy stałej, żeby następny czytelnik nie
wziął jej za wartość dowolną.

**5. Data zapisem `2026-08-11 18:45`, szesnaście znaków, poza katalogiem
napisów.** Zapis rok-miesiąc-dzień jest sortowalny wzrokiem, ten sam w każdym
języku i **ma stałą szerokość**, od której zależy rozdział kolumn. Zapis zależny
od języka zmieniałby szerokość kolumny wraz z ustawieniem interfejsu — a to
znaczy inny układ listy po przełączeniu na angielski.

**`Align` leży w `Presentation`, a nie w `Application/Ui` obok `Role`.** Wartość
opisująca obraz idzie do `Application/Ui` wtedy i tylko wtedy, gdy przechodzi
przez port renderowania — a wyrównanie nie przechodzi: tabela liczy z niego
dokładną kolumnę i oddaje `TextRun` gotowy do narysowania. Renderer nigdy się nie
dowiaduje, że coś było dosunięte.

### D50 — Krok 33: reakcja na zmianę rozmiaru okna wchodzi do planu jako osobna Faza VIII

**Dotyczy:** kroku 33 (pełna treść: [33-reakcja-na-zmiane-rozmiaru.md](archiwum/33-reakcja-na-zmiane-rozmiaru.md))
i struktury planu ([00-index.md](00-index.md)).

**Data:** 2026-08-11.

Na polecenie użytkownika plan dostał krok znoszący założenie, które trzymało się
od kroku 06: rozmiar okna terminala mierzony **raz, przy starcie**
(`TerminalSizeService`, pole `readonly`). Założenie było jawne i uczciwe — ale
menadżer plików to program, w którym użytkownik siedzi długo, a okno terminala
zmienia rozmiar częściej niż jakiekolwiek inne.

**Osobna faza, nie dopisek do Fazy VII.** Faza VII to sześć **komponentów**
wybranych jednym przeglądem (D48) i spiętych wspólnym rytmem „jeden komponent —
jeden krok”. Reakcja na rozmiar nie jest komponentem — jest mechanizmem rdzenia
w warstwie terminala i pętli, jak proces tłowy z Fazy VI. Wciśnięcie jej do
Fazy VII rozmyłoby granicę, którą D48 wyznaczyła; stąd Faza VIII, na razie
jednokrokowa, wzorem Fazy VI.

**Krok jest od Fazy VII niezależny w obie strony** — dotyka
`TerminalService`, `TerminalSizeService` i `GameLoop`, czyli miejsc, których
żaden z kroków 27–32 nie rusza. Kolejność względem nich pozostaje wolna.

**Rozpoznanie zrobione przy pisaniu planu** (utrwalone w sekcji „Stan zastany”
kroku): droga rozmiaru do klatki już jest per-klatkowa — `FrameComposer`
i `SixelFrameRenderer` pytają o rozmiar przy każdym rysowaniu, zamrożona jest
wyłącznie odpowiedź usługi. Pamięci podręczne z kroku 17 mają rozmiar w kluczu
(D34: „pamięć odświeża się sama, bez ścieżki unieważnienia”), a stan między
klatkami ścina się do pojemności przy rysowaniu (`ScrollWindow::clamp()`).
Krok 33 jest więc pierwszym prawdziwym sprawdzianem obu tych obietnic — i
dlatego jego najważniejszym kryterium ukończenia jest **niezdrożenie klatki
w oknie o stałym rozmiarze**, a nie sama reakcja na zmianę.

**Najcięższe rozstrzygnięcie odłożone na start kroku:** skąd wziąć piksele po
zmianie. Zapytanie `ESC [ 14 t` w środku pętli konkuruje o STDIN z klawiszami
(choć `WindowSizeParser` i `pushBackBytes()` z kroku 06 umieją oddać cudze
bajty), a przeliczenie z zapamiętanego rozmiaru komórki nie kosztuje nic, lecz
kłamie po zmianie fontu w locie. Wybór — jak wszystkie w tym projekcie —
należy do użytkownika i stoi jako punkt 1 listy startowej kroku.

### D51 — Zmiana rozmiaru okna: prawdziwe zapytanie o piksele, samoodświeżanie bez zmian w kontraktach

**Dotyczy:** kroku 33 (pełna treść: [33-reakcja-na-zmiane-rozmiaru.md](archiwum/33-reakcja-na-zmiane-rozmiaru.md)).

**Data:** 2026-08-11.

Cztery rozstrzygnięcia użytkownika ze startu kroku; trzy zgodne z rekomendacją,
pierwsze — wbrew niej, i to ono jest najciekawsze.

**1. Piksele po zmianie: ponowne `ESC [ 14 t`, nie przeliczenie z komórki.**
Rekomendacja szła za przeliczeniem (zero I/O, zero straconych klatek, kłamie
tylko po zmianie fontu w locie — a ta i tak jest poza zakresem). Użytkownik
wybrał prawdziwe zapytanie. Wykonanie dostało dwie poprawki, które zostawiają
wybór w mocy, a zdejmują jego koszt z przypadków, w których odpowiedź nie może
przyjść: **pyta się ponownie wyłącznie terminala, który odpowiedział przy
starcie** (milczenie to konfiguracja `disallowedWindowOps`, nie chwilowa
niedyspozycja — pytanie milczącego to 300 ms zamrożonej pętli za każdym razem,
za nic), a gdy pytać nie wolno, **rozmiar komórki liczy się z poprzedniego
pomiaru**, nie ze stałych 6×13 (font się nie zmienił, więc iloraz mówi prawdę
tam, gdzie stała mówi „najczęściej”). Odrzucona hybryda (przeliczenie od razu,
korekta zapytaniem po uspokojeniu sygnałów) wnosiła stan „pomiar w toku” do
pętli — najwięcej kodu za różnicę niewidoczną przy terminalu, który odpowiada
w milisekundach.

**2. Nowy rozmiar dociera do klatki samoodświeżeniem, kontrakty nietknięte.**
`TerminalSizeService` zdejmuje znacznik przy odczycie i mierzy ponownie;
`ViewportPort` i `TerminalPort` nie zmieniły się o literę, bo składanie klatki
i renderer **pytały co klatkę już od kroku 18** — zamrożona była wyłącznie
odpowiedź. Odrzucone jawne `refresh()` w pętli dawało jedną własność więcej
(rozmiar zmienia się tylko na granicy taktu) za poszerzenie kontraktu portu —
a jedyny teoretyczny rozjazd (sygnał w środku klatki między odczytem składania
a odczytem renderera) kosztuje jedną klatkę przy 30 na sekundę i naprawia się
sam. Skutek uboczny wart odnotowania: **`GameLoop` i `FrameComposer` nie
zmieniły się wcale**, choć tabela planu przewidywała w obu zmiany.

**3. Znacznik stoi w `TerminalService`**, obok `shutdownRequested` — sygnały
w jednym miejscu, jednym wzorcem: uchwyt ustawia znacznik i nic więcej, bo
pomiar w uchwycie dotykałby STDIN w nieprzewidywalnym momencie klatki.
Zdjęcie znacznika **przed** pomiarem jest częścią umowy: sygnał doręczony
w trakcie pomiaru ustawia go ponownie i następna klatka mierzy jeszcze raz —
zmiana nie ma jak się zgubić.

**4. Okno za małe rysuje, co się zmieści.** Dzisiejsze zachowanie rozciągnięte
na czas działania: `HudLayout` oddaje strefy, `Distribution` gasi kolumny,
`ScrollWindow` ścina przewinięcie — wszystko wedle reguł, które już są.
Odrzucona plansza „okno za małe” wnosiła napisy do obu katalogów i próg, który
trzeba by uzasadnić, w zamian za zakrycie klatki, która i tak mówi prawdę.

**Poza rozstrzygnięciami, przy okazji:** `queryPixelSize()` przy przekroczeniu
limitu czasu połykał zebrane bajty — dług kroku 06, niegroźny przy starcie,
groźny przy pomiarze w trakcie działania (to byłyby klawisze użytkownika).
Bajty wracają teraz przez `pushBackBytes()`.

**Sprawdzian D34 zdany**: krok nie dotknął ani jednej pamięci podręcznej.
Klucz płaszczyzny spodniej i bitmap napisów niesie rozmiar od kroku 17, więc
zmiana okna unieważnia je sama — dokładnie tak, jak obiecywał zapis „pamięć
odświeża się sama, bez ścieżki unieważnienia, o której można zapomnieć”.

## Decyzje z planowania Fazy IX (2026-08-11)

### D52 — Prezentacja poza terminalem: natywny OpenGL przez PHP-GLFW, Faza IX z dwoma krokami

**Dotyczy:** kroków 34 i 35 (pełna treść: [34-okno-glfw.md](archiwum/34-okno-glfw.md),
[35-renderer-opengl.md](archiwum/35-renderer-opengl.md)) i struktury planu
([00-index.md](00-index.md)).

**Data:** 2026-08-11.

Na polecenie użytkownika plan dostał integrację warstwy prezentacji z OpenGL
przez rozszerzenie PHP-GLFW. Dwie decyzje użytkownika z planowania — pierwsza
wbrew rekomendacji i to ona przesądza o skali przedsięwzięcia.

**1. Zakres: natywne rysowanie prymitywów w OpenGL, nie klatka-tekstura.**
Rekomendacja szła za wariantem najmniejszym: klatka nadal składana Imagickiem,
a zamiast enkodowania do Sixela wgrywana jako tekstura na pełnoekranowy
prostokąt w oknie GLFW — cały potok zostawał nietknięty, dochodził tylko
trzeci `FrameRendererPort` i alternatywa dla wejścia. Użytkownik wybrał
rysowanie natywne: prymitywy słownika z kroku 18 tłumaczone wprost na
wywołania OpenGL, **bez Imagicka w ścieżce klatki** w trybie okienkowym.
Konsekwencja architektoniczna warta jawnego zapisu: założenie z celu projektu
(„cała klatka ekranu budowana jest jako jeden obraz Imagick”) przestaje być
uniwersalne — pozostaje prawdziwe dla trybów terminalowych, a tryb okienkowy
dostaje własnego, trzeciego tłumacza tego samego słownika prymitywów.
Odrzucony został też wariant „oba” (tekstura teraz, natywnie później) — bez
wariantu pośredniego nie powstaje kod, który drugi krok by wyrzucił.

**2. Struktura: osobna Faza IX z dwoma krokami** (zgodnie z rekomendacją).
Krok 34 stawia mechanizm: okno, kontekst OpenGL, wejście z klawiatury jako ten
sam słownik `KeyPress`, zamknięcie okna i zmianę rozmiaru, wybór trybu przy
starcie — z klatką „dowód życia” (tło motywu) jako treścią zastępczą, wzorem
kroku 09. Krok 35 dowozi treść: natywny renderer pełnego słownika prymitywów
do parytetu z klatką sixelową, z pomiarem. Odrzucone: jeden krok (większy niż
18 i 21 razem — za dużo rozstrzygnięć na jeden start) oraz trzy kroki
(renderer tekstu i kształtów bez bitmap nie domyka żadnego ekranu z podglądem,
więc środkowy krok nie miałby uczciwego kryterium ukończenia). Osobna faza,
nie dopisek do VIII — wzorem rozumowania z D50: to nowy mechanizm rdzenia
o własnym rytmie, nie kolejny komponent ani nie sprawa okna terminala.

**Granice postawione już na etapie planu:** tryby terminalowe (Sixel, tekst)
zostają pierwszorzędne i nie mają prawa zdrożeć ani się zmienić; `ext-glfw`
nie wchodzi do `require` (wzorem `intl` z D20 — tryb okienkowy to możliwość,
nie wymóg); słownik prymitywów pozostaje zamknięty — „OpenGL potrafi więcej”
nie otwiera go, otwiera go wyłącznie tryb z D48; od kroku 35 każdy nowy
prymityw obowiązuje **trzy** renderery naraz, co dotyka kroku 30, jeśli
wykona się później. Rozstrzygnięcia wykonawcze (flaga wyboru trybu, nazwa
trybu i katalogu, los nazwy `TerminalPort`, technika rysowania — API wektorowe
rozszerzenia czy surowe GL z atlasem glifów, źródło fontu, dekoder bitmap,
kształt pomiaru) stoją jawnie w listach startowych obu kroków i — jak
wszystkie w tym projekcie — należą do użytkownika.

## Decyzje ze startu kroku 34 (2026-08-11)

### D53 — Okno GLFW: flaga `--window`, `RendererMode::OpenGl`, port wejścia pod neutralną nazwą, rozmiar startowy z ustawień

**Dotyczy:** kroku 34 (pełna treść: [34-okno-glfw.md](archiwum/34-okno-glfw.md)).

**Data:** 2026-08-11.

Sześć rozstrzygnięć użytkownika z listy startowej kroku; pięć zgodnych
z rekomendacją, piąte — rozszerzone ponad nią.

**Stan zastany, który zmienił założenia planu:** rozszerzenie `glfw` okazało
się **już zainstalowane i załadowane** (PHP-GLFW 2.2.0, wkompilowane API `gl`
w wersji 4.1, GLFW 3.3.8 pod X11, wraz z API wektorowym
`GL\VectorGraphics\VGContext` — istotnym dla rozstrzygnięcia nr 1 kroku 35),
a stuby do analizy statycznej są publikowane na Packagist jako
`phpgl/ide-stubs` (MIT).

**1. Tryb okienkowy wybiera flaga CLI `--window`.** Rozstrzyga się przed
jakąkolwiek sekwencją terminalową, jawnie przy każdym uruchomieniu. Odrzucone:
zmienna środowiskowa (wybór niewidoczny w poleceniu) i klucz ustawień
(wymagałby przestawienia kolejności bootstrapu, przed którą plan ostrzegał).

**2. Nazewnictwo: `RendererMode::OpenGl` + katalog `Infrastructure/Glfw`.**
Wariant enuma nazywa technikę, którą klatka trafia na ekran — jak `Sixel` —
i pokrywa się z nazwą kroku 35; katalog usług idzie po bibliotece, precedensem
`Infrastructure/Imagick`.

**3. `TerminalPort` przechodzi na neutralną nazwę `InputPort`.** Kontrakt
(`readKey()` + `shutdownRequested()`) nie mówił o terminalu niczego — mówiła
nazwa. Argument kosztowy z planu upadł po sprawdzeniu: jedno miejsce
wstrzyknięcia (`GameLoop`), jedna implementacja (`TerminalService`), jeden
dubler testowy — zmiana mechaniczna w ~4 plikach.

**4. Rytm klatek: dzisiejszy stały takt z `glfwSwapInterval(0)`.** Oba tory
zachowują się identycznie, `GameLoop` zostaje jeden i niezmieniony; vsync
(rytm monitora, mniejsze zużycie) odrzucony, bo rozjeżdżał tryb okienkowy
z terminalowym i wymagał rozgałęzienia `usleep()` w pętli.

**5. Rozmiar startowy okna: domyślnie 100×30 komórek, konfigurowalne
w ustawieniach aplikacji** — rozszerzenie ponad rekomendację (sama stała
100×30): użytkownik zażądał kluczy ustawień rdzenia na rozmiar startowy.
Komórka zastępcza pozostaje stałą jawnie opisaną jako tymczasowa — prawdziwa
wyjdzie z metryk fontu w kroku 35. Konsekwencja dla toru okienkowego
bootstrapu: ustawienia czytają się **przed** otwarciem okna (sekwencja
ustawienia → okno → renderer), co w tym torze nie ma pułapki znanej z toru
terminalowego, bo odczyt konfiguracji terminala nie dotyka.

**6. Kontekst OpenGL: 3.3 core.** Wystarcza obu technikom rysowania
rozważanym w kroku 35 (API wektorowe rozszerzenia wymaga rdzenia 3.x; atlas
glifów na surowym GL też), najszersza zgodność sprzętowa; Mesa pod X11 odda
kontekst nowszy i zgodny wstecz. Odrzucone: 4.1 core (parytet z wkompilowanym
API bez odbiorcy — krok 35 niczego powyżej 3.3 nie potrzebuje) i profil
zgodnościowy (zamykał drogę API wektorowemu).

## Decyzje ze startu kroku 35 (2026-08-11)

### D54 — Renderer okienkowy: API wektorowe rozszerzenia, font systemowy, natywne ładowanie tekstur, pomiar w render-bench

**Dotyczy:** kroku 35 (pełna treść: [35-renderer-opengl.md](archiwum/35-renderer-opengl.md)).

**Data:** 2026-08-11.

Pięć rozstrzygnięć użytkownika z listy startowej; cztery zgodne
z rekomendacją, trzecie — wbrew niej.

**Rozpoznanie na maszynie, które poprzedziło pytania** (wymóg planu: „nie
z pamięci ani z dokumentacji”): API wektorowe `GL\VectorGraphics\VGContext`
(NanoVG na GL3) ma pełny zakres — kształty z antyaliasingiem (`rect`,
`roundedRect`, `roundedRectVarying`, łuki, `strokeWidth`, nożyce), pełny
tekst TTF (`createFont` ze ścieżki pliku, `textAlign`, `textBounds`,
`textMetrics`, wewnętrzny atlas glifów) i obrazy (`Texture2D::fromDisk`/
`fromBuffer` → `imageFromTexture` → `VGImage::makePaint`). `fc-match` jest
dostępny i wskazuje `DejaVuSansMono.ttf`.

**1. Technika rysowania: API wektorowe `VGContext`.** Tabela tłumaczeń jest
krótka, antyaliasing i pamięć podręczna glifów przychodzą w cenie (font
stash NanoVG), łuki `RoundRect` są prawdziwe, a kontekst 3.3 core z D53
wystarcza. Odrzucone surowe GL z własnym atlasem: pełna kontrola za cenę
najtrudniejszego kawałka (tekst) pisanego ręcznie — bez zysku, skoro
rozpoznanie potwierdziło zakres API.

**2. Font: systemowy, lista preferencji ścieżek TTF + `fc-match` jako
ostatnia szansa** — wzorem kroku 08 (tam: lista nazw + `Imagick::queryFonts`),
spójny z resztą pulpitu. Cena znana z planu: metryki niedeterministyczne
między maszynami. Odrzucony plik TTF w repozytorium (identyczne metryki
wszędzie, ale ~340 KB w repo i odstępstwo od wzorca).

**3. Dekoder bitmap: natywne `Texture2D::fromDisk`** — **wbrew
rekomendacji** (Imagick poza ścieżką klatki, z limitami D24 i pełną paletą
formatów). Użytkownik wybrał zero Imagicka także w dekodowaniu podglądów
toru okienkowego. Konsekwencje przyjęte świadomie: zakres formatów zawęża
się do tego, co czyta stb_image (PNG/JPG/BMP/GIF statyczny/TGA/PSD/PNM —
bez WEBP/TIFF/SVG; format nieobsłużony spada do pustej ramki z podpisem,
jak dziś plik nieczytelny), a limity rozmiaru trzeba postawić od nowa po
stronie toru okienkowego, bo D24 chronił potok Imagicka.

**4. Przełączniki jakości: `strokeAntialias` działa, reszta „nie dotyczy”.**
`strokeAntialias` mapuje się na `shapeAntiAlias` kontekstu — jedyny
przełącznik, który VG naprawdę ma. `textAntialias` (NanoVG wygładza tekst
zawsze) i `paletteColors` (kwantyzacji nie ma wcale) dostają jawną adnotację
„nie dotyczy toru okienkowego”, wartości są ignorowane bez ostrzeżeń.

**5. Pomiar: `bin/render-bench` z nową osią `--window`.** Te same
scenariusze (`ScenarioFactory` buduje `Frame` — wspólny dla wszystkich
tłumaczy), okno ukryte hintem `GLFW_VISIBLE=0`, osobny tor pomiaru (czas
klatki VG + zamiana buforów zamiast faz Sixela). Wzorzec okienkowy wchodzi
do `docs/pomiary/` obok terminalowych; `--compare` porównuje w obrębie
toru. Odrzucone osobne narzędzie: drugi parser, drugi format i drugi zestaw
wzorców przy tych samych scenariuszach.

## Decyzje z planowania Fazy X (2026-08-11)

### D55 — Odtwarzanie muzyki przez `GL\Audio`: osobna Faza X z jednym krokiem

> **Uwaga o numerze.** Decyzja powstała 2026-08-11 jako D53, ale ten sam numer
> dostały równolegle rozstrzygnięcia startu kroku 34. Kolizję rozstrzygnięto
> 2026-08-12 na korzyść kroku 34, bo jego numer zdążył wejść do dwudziestu
> miejsc w kodzie i dokumentacji; treść decyzji o muzyce nie zmieniła się
> o literę.

**Dotyczy:** kroku 36 (pełna treść: [36-odtwarzanie-muzyki.md](archiwum/36-odtwarzanie-muzyki.md))
i struktury planu ([00-index.md](00-index.md)).

**Data:** 2026-08-11.

Na polecenie użytkownika plan dostał odtwarzanie muzyki — domyślnie i na
początek „Smoke On The Water” — przez moduł audio rozszerzenia PHP-GLFW
(`GL\Audio`), z rozwiązaniami zastępczymi do zaproponowania na wypadek jego
niedostępności.

**Rozpoznanie z dnia planowania** (fakty, nie założenia): rozszerzenie `glfw`
**jest załadowane** w środowisku projektu; `GL\Audio\Engine` oddaje
`start()`/`stop()`, `soundFromDisk()` i głośność główną, `GL\Audio\Sound` —
`play()`, `stop()`, `setLoop()`, `setVolume()`, fade i seek. Dwa ustalenia
przesądzają o kształcie kroku: dźwięk ładuje się **wyłącznie z pliku na
dysku** (brak konstrukcji z bufora — utwór syntezowany musi najpierw stanąć
jako WAV), a silnik audio (miniaudio) najpewniej **nie wymaga okna ani
kontekstu OpenGL** (do potwierdzenia na starcie kroku) — więc muzyka może
grać także w trybach terminalowych, a krok stoi **poza Fazą IX**, niezależny
od niej w obie strony. Jedyny punkt styku: stuby `GL\*` do analizy statycznej
dowozi ten z kroków 34/36, który wykona się pierwszy.

**Struktura: osobna Faza X, jeden krok** — wzorem rozumowania z D50/D52:
dźwięk to nowy mechanizm o własnym rytmie, nie komponent i nie sprawa okna.
Jeden krok, nie dwa, bo zakres jest domknięty: port + usługa + domyślny
utwór + komendy; ewentualny moduł odtwarzacza (playlisty, przeglądanie) to
świadome wykluczenie, zapisane w „Poza zakresem”.

**Granice postawione na etapie planu:** dźwięk gra **poza ścieżką klatki** —
koszt klatki nie ma prawa drgnąć w żadnym trybie (`bin/render-bench
--compare` w kryteriach ukończenia); `ext-glfw` nie wchodzi do `require`
(granica z D52 bez zmian) — brak rozszerzenia obsługuje pusta implementacja
portu z komunikatem, nie kod wyjścia; nagranie Deep Purple **nie wchodzi do
repozytorium** (cudze nagranie, cudze prawa) — rekomendacją jest własny,
deterministyczny syntezator riffu piszący WAV do pamięci podręcznej.

**Rozwiązania zastępcze bez rozszerzenia — zaproponowane, wybór należy do
użytkownika** (lista startowa kroku): (a) odtwarzacz zewnętrzny jako proces
potomny wzorcem kroku 26 (`paplay`/`aplay`/`ffplay`/`mpv`), (b) FFI do
miniaudio/OpenAL (odradzane — najcięższe), (c) sama degradacja z komunikatem
(rekomendowane, dopóki jedyne środowisko projektu ma rozszerzenie
załadowane). Pozostałe rozstrzygnięcia wykonawcze (zasięg trybów, źródło
utworu, domyślny stan autostartu, nazewnictwo portu i katalogu) stoją jawnie
w liście startowej kroku i — jak wszystkie w tym projekcie — należą do
użytkownika.

## Decyzje ze startu kroku 28 (2026-08-12)

### D56 — Okno potwierdzenia: decyzja wraca domknięciem, `Esc` znaczy „nie”, wariant groźny wchodzi od razu

**Dotyczy:** kroku 28 (pełna treść: [28-okno-potwierdzenia.md](archiwum/28-okno-potwierdzenia.md)).

**Data:** 2026-08-12.

Cztery rozstrzygnięcia użytkownika z listy startowej; trzy zgodne
z rekomendacją, czwarte — wbrew niej i to ono jest najciekawsze.

**Stan zastany, który zmienił obraz rozstrzygnięcia nr 1:** droga „ekran
otwiera okno nakładane” **już istnieje** — `ScreenOutcome::opens(OverlayInterface)`
wraz z obsługą w `InputHandler` — ale nie ma ani jednego użytkownika.
`ConfirmOverlay` będzie pierwszym, więc krok nie buduje drogi, tylko po raz
pierwszy nią idzie.

**1. Decyzja wraca domknięciem, które oddaje komunikat.** Okno dostaje przy
tworzeniu `Closure` wykonywaną po „tak”; zwraca ona `?Message`, a okno pakuje
go w **istniejące** `OverlayOutcome::close($message)`. Kontrakt okna
nakładanego nie rośnie **wcale** — kryterium kroku mówiło „najwyżej o jedną
rzecz”, a wyszło zero. To ten sam wzorzec, którym `Button` działa od kroku 18:
czynność przychodzi z zewnątrz jako wywoływalny obiekt, a komponent nie wie,
co uruchamia. Odrzucone: pole `confirmed` w `OverlayOutcome` (rozszerzałoby
kontrakt **każdego** okna, także dwóch, które o żadnej decyzji nie wiedzą)
oraz odpytywanie okna po zamknięciu (przenosiłoby stan do ekranu — tam, gdzie
krok 19 świadomie go nie zostawił).

**2. `Esc` znaczy „nie”.** Zamknięcie bez odpowiedzi i odmowa dają ten sam
skutek: czynność się nie wykonuje. Rozróżnianie ich nie ma dziś odbiorcy,
a okno pyta o rzecz nieodwracalną — milczenie ma znaczyć odmowę.

**3. Okno przepuszcza klawisze globalne**, jak każde inne (reguła kroku 19 bez
wyjątku). `F10` w trakcie pytania kończy aplikację **bez wykonania czynności**,
co jest bezpieczne, bo domyślną odpowiedzią jest „nie”. Odrzucone przejęcie
całej klawiatury: dawałoby okno, z którego nie da się wyjść inaczej niż
odpowiadając.

**4. Wariant „niebezpieczne” wchodzi od razu — i ma użytkownika od pierwszego
dnia.** Rekomendacja szła za odłożeniem parametru do czasu operacji na plikach,
powołując się na regułę 13 („nic nie powstaje bez prawdziwego użytkownika”).
Użytkownik wybrał wprowadzenie go teraz i **wyłomu od reguły 13 nie ma**:
przywracanie ustawień domyślnych jest jedynym miejscem w aplikacji, w którym
pomyłka kosztuje dane (tak opisuje je sam plan kroku), więc pierwsze pytanie
w historii aplikacji jest właśnie pytaniem groźnym. Parametr rodzi się
z odbiorcą, a nie na zapas — inaczej niż `ProgressBar` w kroku 23, którego
dług trzeba było spłacać przez trzy kroki.

### D57 — Faza IX dostaje trzeci krok: dopracowanie okna

**Dotyczy:** kroku 37 (pełna treść: [37-dopracowanie-okna.md](archiwum/37-dopracowanie-okna.md))
i struktury planu ([00-index.md](00-index.md)).

**Data:** 2026-08-12.

Po ukończeniu kroków 34 i 35 użytkownik zdecydował, że drobiazgi wykluczone
z obu — **zapamiętywanie rozmiaru okna, pełny ekran, ikona i skala treści
(HiDPI)** — wchodzą do planu jako osobny krok, w komplecie, zamiast czekać na
odbiorcę.

**Zmiana wobec D52, którą trzeba nazwać:** tamta decyzja opisywała Fazę IX jako
**dwa kroki** — mechanizm i treść — i takie było jej uzasadnienie („jeden krok
byłby większy niż 18 i 21 razem”). Trzeci krok tego podziału nie łamie, bo nie
dokłada ani mechanizmu, ani treści: okno działa bez niego w całości, a wszystkie
cztery pozycje są dopełnieniem wrażenia, nie warunkiem działania. Stąd
umiejscowienie w Fazie IX, a nie w nowej fazie — kosmetyka okna należy tam,
gdzie okno powstało, a osobna faza dla czterech drobiazgów byłaby przerostem
formy.

**Numer 37, nie 36**: numeracja pozostaje chronologiczna wobec kolejności
powstawania planów, a krok 36 (muzyka) był rozpisany wcześniej. Kolejność
w planie nie jest kolejnością wykonania — Fazy VIII, IX i X i tak stoją poza
łańcuchem.

**Zakres w komplecie, wbrew rekomendacji cząstkowej.** Przy wyborze zakresu
padło pytanie, czy skalę treści włączać, skoro maszyna projektu ma skalę 1.0
i **nie da się jej tu rzetelnie sprawdzić**. Użytkownik włączył wszystkie
cztery pozycje; zastrzeżenie zostaje zapisane w planie kroku jako
rozstrzygnięcie nr 4 — kod pisany na ślepo ma być jawnie oznaczony jako
niesprawdzony na sprzęcie albo zastąpiony samym odczytem wartości.

### D58 — Podgląd tekstu czyta jak edytor: okno po bajtach, kaskada rozpoznania, `Alt` w słowniku wejścia

**Dotyczy:** kroku 29 (pełna treść: [29-podglad-tekstu.md](archiwum/29-podglad-tekstu.md)),
komponentu `TextView`, modułu `FileInfo` oraz **warstwy wejścia** z kroków 06,
19 i 34.

**Data:** 2026-08-12.

Sześć pytań ze startu kroku rozstrzygnął użytkownik przed pierwszą linią kodu.
Trzy odpowiedzi przestawiły krok na tyle, że są decyzją architektoniczną, a nie
wyborem szczegółu.

**1. Odczyt idzie przesuwnym oknem, a miejsce w pliku to bajt.** Plan zakładał
wczytanie nagłówka pliku „z zapasem” i wycinanie z niego widocznego fragmentu
`ScrollWindow`em. Użytkownik rozstrzygnął inaczej: *„Ładuj jedynie te dane
z pliku, które będziesz prezentował. Poprzednie po przewinięciu ekranu należy
usunąć z bufora. Nowe doczytać. Jak w edytorach tekstu.”* Konsekwencje sięgają
dalej niż jeden komponent i dlatego są tu zapisane:

- **Kotwica liczy w bajtach** (`TextAnchor`), bo tylko bajt pozwala usiąść
  w środku pliku bez przeczytania wszystkiego przed nim. Numer wiersza jedzie
  obok i liczy się **przyrostowo** — przeliczanie go od początku pliku
  kosztowałoby przy każdym przewinięciu przejście przez całe pół gigabajta.
- **Suwak liczy się w bajtach**, nie w wierszach: liczby wierszy pliku nie znamy
  i poznać jej nie chcemy.
- **Ile czytać, wiadomo dopiero przy rysowaniu**, bo budżet bierze się
  z geometrii panelu. Przewinięcie zamówione klawiszem czeka więc na
  rozliczenie — wzorem, który `ScrollWindow` ustalił w kroku 18 (`scrollBy()`
  zapisuje, `clamp()` rozstrzyga przy znanym prostokącie) — a rozlicza się
  **jeden panel na klatkę**, wzorem pracy kawałkowej z D46.
- **`ScrollWindow` tu nie pasuje** i kryterium ukończenia „czwarta klasa stanu
  nie powstała” zostało dotrzymane inaczej, niż je zapisano: klasa nie powstała,
  ale kotwica mieszka w `FileInfoState`, czyli w stanie **modułu**, a nie
  w nowej klasie rdzenia. Rdzeń dostał komponent, nie pamięć.
- **D46 nie obowiązuje samego odczytu** i to warto rozróżniać na przyszłość:
  wzorzec pracy kawałkowej dotyczy robót, których w klatce wykonać **nie da
  się**. Jedno okno to kilkadziesiąt kilobajtów — mieści się z zapasem, więc
  dzielenie go na kawałki byłoby ceremonią bez treści.

**2. Rozpoznanie tekstowości to kaskada trzech metod, nie wybór jednej.**
Rozszerzenie → opis od polecenia `file` → podejrzenie pierwszych bajtów.
Dopowiedzenie, które wyszło przy pisaniu i jest częścią decyzji: **dwa pierwsze
stopnie rozstrzygają wyłącznie twierdząco**. Ich milczenie znaczy „nie wiem”,
a nie „binarny” — `README` nie ma rozszerzenia, a `file` bywa nieobecne albo
mówi językiem, którego wzorzec nie zna. Rozstrzyga zawsze dopiero trzeci.

Kodowanie rozpoznajemy z nagłówka i konwertujemy; brak jednoznacznej odpowiedzi
to UTF-8 z podmianą bajtów, których nie da się zdekodować. **Rozpoznanie kodowania
stoi przed kaskadą, a nie po niej**: gdyby szło po, plik `.txt` w UTF-16
przeszedłby pierwszym stopniem jako tekst i pokazał się jako śmieci.

**Kodowania szerokie wchodzą w komplecie — poprawka tego samego dnia.** Pierwsza
wersja odmawiała podglądu plikom UTF-16/32 z powodem, bo bajt zerowy co drugi
znak wywraca podział na wiersze i rachunek bajtów; użytkownik polecił dowieźć
obsługę. Rozstrzygnięcie było słuszne z powodu, który przy pisaniu umknął:
**znacznik kolejności bajtów jest dowodem, że plik jest tekstem**, więc odmowa
podglądu była najsłabszym punktem kroku. Reguła, która z tego została i obowiązuje
każdy przyszły odczyt tekstu: **bajt to nie znak** — znaku nowej linii szuka się
w kodowaniu źródła i **wyłącznie na granicy jednostki kodowej**, bo `0A 00` wypada
w UTF-16LE także w środku pary innych znaków, a kotwica przesunięta o bajt to pół
znaku. Wyrównania pilnują trzy miejsca naraz: kotwica przewijania w górę, bufor
urwany budżetem i samo szukanie. Rozpoznanie bez BOM-u jest umyślnie ciasne (żadnej
jednostki z dwóch zer, zera zawsze po tej samej stronie, wzorzec na czterech
piątych jednostek), bo pomyłka w drugą stronę wysypałaby binaria na ekran jako
tekst.

**3. Słownik wejścia dostaje `Alt` — drugi modyfikator, rozłączny z `Ctrl`.**
Przełącznik zawijania miał wisieć na `Alt`+`z`, „jak w edytorach”. Koszt został
użytkownikowi przedstawiony przed decyzją (nowa flaga w `KeyPress`, rozróżnienie
`Esc`+litera od `Alt`+litery w parserze terminalowym, bity modyfikatorów
w `GlfwKeyMapper`, `KeyBinding` i spis pomocy — czyli kod krok 06, 19 i 34)
i został przyjęty. Trzy rzeczy, które z tego wynikają:

- **`Esc` naciśnięty tuż przed literą jest nieodróżnialny od `Alt`+litery**, bo
  terminal wysyła w obu wypadkach te same dwa bajty. Rozstrzyga wyłącznie czas.
  Tak samo rozstrzygają to emulatory terminala i edytory od czasów VT100;
  rozdzielić je umie dopiero rozszerzony protokół klawiatury, wyłączony z zakresu
  już w kroku 19.
- **Kombinacji `Ctrl`+`Alt` słownik nie zna** i nie ma jej po co znać, dopóki nie
  pojawi się użytkownik. W torze okienkowym `Ctrl` wygrywa, w terminalowym taka
  para w ogóle nie powstaje.
- **Każde porównanie litery musi patrzeć na oba znaczniki.** Dodanie `Alt`
  ujawniło dwa miejsca, w których modyfikator był ignorowany: pole tekstowe
  wpisywało skrót jako zwykły znak, a ekran modułu odpowiadał na `Alt`+`s` tak
  samo jak na `s`. Do kroku 29 uchodziło to na sucho, bo `Ctrl`+litera nie
  docierała do ekranu — przechwytywały ją skróty modułów. **Nowy modyfikator
  odsłania każde niedokładne porównanie klawisza**, i to jest lekcja szersza niż
  ten krok.

**Granica „komponent nie czyta”.** `TextView` dostaje gotowe wiersze — już
zdekodowane, z rozwiniętymi tabulatorami i oznaczonymi znakami sterującymi —
i o pliku nie wie nic. To ta sama granica, co między komponentem a rendererem,
tyle że po drugiej stronie: *komponent wie, jak wyglądać*, a nie skąd wziąć
treść. Wejście-wyjście zostaje w module, bo tam mieszka wiedza o tym, co wolno
przeczytać i jak długo (reguła 15).

**Cena, zmierzona:** 6,3 ms wobec `chrome-text` przy blobie 80,5 kB zamiast
23,6 kB. Widać w niej dokładnie to, co przewidział plan: zawinięty wiersz to
kilka napisów zamiast jednego, a napisy podglądu są w każdej klatce inne, więc
pamięć podręczna wierszy (D34) trafia w nie rzadziej niż w listę plików. Osobny
scenariusz `text-view` był z tego powodu potrzebny, a nie dołożony dla porządku.

### D59 — Ósmy prymityw jest napisem na tle, filtr mieszka w panelu, a `Esc` odmawia

**Dotyczy:** kroku 30 (pełna treść:
[30-filtrowanie-i-podswietlenie.md](archiwum/30-filtrowanie-i-podswietlenie.md)),
słownika prymitywów z kroku 18, **trzech rendererów** (kroki 07, 08, 35) oraz
modułu przeglądarki.

**Data:** 2026-08-12.

Cztery pytania ze startu kroku rozstrzygnął użytkownik przed pierwszą linią
kodu; piąte (bajty czy znaki) rozstrzygnęło się samo i jest zapisane niżej dla
porządku. Dwa z nich są decyzją architektoniczną, a nie wyborem szczegółu.

**1. Kształt ósmego prymitywu: napis na własnym tle, nie samo tło.** D48 zgodziła
się na **otwarcie** zamkniętego słownika, ale kształtu nie przesądzała, a plan
kroku proponował „tło pod fragmentem”. Przegląd stanu zastanego pokazał, że ta
propozycja **nie byłaby nowym kształtem**: prostokąt wypełniony rolą motywu jest
w słowniku dwa razy — jako `Bar` z `Weight::Fill` i jako `RoundRect` bez obrysu —
a karetka `TextInput` od kroku 19 udaje nim podświetlenie fragmentu, dokładając
na wierzchu przemalowany `TextRun`. Ósmy prymityw w tej postaci byłby synonimem
siódmego, płatnym w **trzech** rendererach naraz.

Użytkownik wybrał kształt, którego w słowniku naprawdę nie ma: `TextMark` —
fragment tekstu **związany z tłem w jednej rzeczy**. Wygrana jest wymierna
w każdym z trzech torów:

- **Sixel**: jedna zapamiętana bitmapa i **jeden** `compositeImage` na
  dopasowanie zamiast dwóch. `compositeImage` kosztuje tyle, ile kształt, ale
  samo wywołanie kosztuje zawsze, a przy filtrze trafiającym w każdy wiersz
  wywołań jest tyle, ile wierszy.
- **Tekst**: tło **i** kolor pisma tej samej komórki — czyli degradacja do
  atrybutu, nie do treści. Odwracanie atrybutów, które plan dopuszczał jako
  ostateczność, okazało się niepotrzebne; tryb zapasowy pokazuje dopasowanie co
  do znaku tak samo, jak tor graficzny. To pierwszy kształt od kroku 18, którego
  renderer tekstowy **nie musi** degradować z ubytkiem.
- **OpenGL**: prostokąt i napis, bez pamięci podręcznej — bo wygrana
  z zapamiętywania bitmap była własnością toru sixelowego (D54).

`TextRun` **zostaje nietknięty** i to jest warunek, na którym całość stoi:
wiersz bez dopasowania oddaje co do podpisu te same prymitywy, co przed krokiem.
Reguła na przyszłość: **zanim dołożysz kształt, sprawdź, czy nie jest którymś
z istniejących pod inną nazwą.**

**2. Zakresy w wierszu, znaki zamiast bajtów.** `TableRow` niesie
`array<int, list<TextSpan>>` — zakresy wedle numeru kolumny, pusto domyślnie.
Wiersz niesie **zakresy, a nie podzieloną treść**, bo podział wymaga wiedzy,
której wiersz nie ma: od której kolumny zaczyna się napis po rozdziale
szerokości (krok 27) i ile z niego zostanie po przycięciu. Przycięcie liczy się
do treści **zachowanej**, a nie do napisu z wielokropkiem — wielokropek nie jest
dopasowaniem.

Przesunięcie liczy się **w znakach**. Odpowiedź była oczywista i plan tak ją
zapowiadał, ale musiała paść: `zażółć.txt` ma dziewięć znaków i trzynaście
bajtów, więc zakres liczony bajtami wylądowałby o cztery kolumny za daleko
i w połowie znaku. Wielkość liter składa `mb_stripos()`, więc `Ł` znajduje `ł`.

**3. Pole filtra jest oknem nakładanym, nie wierszem w panelu.** Stoi tam, gdzie
okno komend z kroku 19 — nad paskiem stanu. Wynikła z tego jedna rzecz, której
plan nie przewidywał, i jest ona ceną tego wyboru: **okno musi samo oddać
strzałki pionowe liście pod spodem**. Reguła kroku 19 mówi, że klawisz
przepuszczony przez okno próbuje jeszcze klawiszy globalnych, ale **do ekranu
nie schodzi nigdy** — bez tej ścieżki filtr byłby polem, w którym da się pisać,
ale nie da się wybrać tego, co się znalazło. Okno leży w
`Module/Browser/Presentation/Overlay`, bo zna stan panelu; to rozszerzenie
reguły 11 z komponentów na okna.

**4. Filtr dotyczy panelu z ogniskiem, a zawężenie mieszka w stanie panelu.**
Każdy panel ma własny filtr, bo ma już własny katalog, własny kursor i własne
okno przewijania. Samo zawężenie **nie weszło do agregatu `Directory`**, tylko
do `BrowserState`: filtr jest widokiem na katalog, a nie jego własnością — dwa
panele otwarte na tym samym katalogu mają prawo mieć różne filtry, a katalog na
dysku jest jeden. Panel trzyma przez to dwa katalogi: ten z dysku i ten
widoczny; przy pustym filtrze jest to **ten sam obiekt**, żeby klatka bez filtra
nie przechodziła przez ani jedno `array_filter`.

**5. `Enter` zatwierdza, `Esc` odmawia — rozstrzygnięcie spoza listy planu.**
Plan wymagał, żeby „zaznaczenie przeżyło wejście i wyjście z filtra”, ale zdanie
to znaczy dwie sprzeczne rzeczy naraz: wrócić do wpisu sprzed filtra czy zostać
na tym, który się znalazło. Rozdzielone zostało wzorem okna potwierdzenia (D56)
i reguły P3:

- **`Enter`** zostawia listę **zawężoną** i zaznaczenie tam, dokąd użytkownik
  doszedł. Filtr przeżywa zamknięcie pola, więc widać go znacznikiem w pasie
  ścieżki — wzorem znacznika wpisów ukrytych z kroku 21. Bez tego znacznika
  lista zawężona byłaby nieodróżnialna od katalogu, w którym tych plików po
  prostu nie ma.
- **`Esc` w oknie** zdejmuje filtr i wraca do wpisu **sprzed** otwarcia.
- **`Esc` na liście** (pole już zamknięte) zdejmuje sam filtr, zostawiając
  zaznaczenie tam, gdzie stoi. Klawisz pokazuje się w spisie **tylko wtedy, gdy
  jest co zdejmować** — na liście bez filtra nie robi nic i nie ma prawa
  twierdzić, że robi.

Zaznaczenie przenosi się przez każdą zmianę filtra **po nazwie, nie po numerze**
(`Directory::selectEntryNamed()`, ten sam mechanizm, co przy ukrywaniu wpisów),
bo filtr zmienia numery wszystkim wpisom naraz.

**Skutek uboczny, znaleziony przy okazji:** porównanie klawisza `.`
w `BrowserScreen` nie patrzyło na modyfikatory, więc `Ctrl`+`.` i `Alt`+`.`
przełączały wpisy ukryte. Naprawione przy dokładaniu `/` — dokładnie ten rodzaj
niedokładności, przed którym ostrzegała D58.

### D60 — Podgląd tekstu dostaje ognisko, a przewijanie liczy się w linijkach panelu

**Dotyczy:** modułu `FileInfo` (kroki 25 i 29), komponentu `TextView` i portu
podglądu tekstu. **Odwołuje rozstrzygnięcie z D58.**

**Data:** 2026-08-12.

**Żądanie użytkownika:** „Dodaj możliwość zmiany kursora na podgląd pliku
w module FileInfo i możliwość przewijania tekstu używając klawiszy strzałek,
pgup, pgdown, home i end.”

**1. Ognisko wraca — D58 mówiła, że go nie będzie.** Krok 29 rozstrzygnął tak:
*„Osobnego przełącznika ogniska nie ma z rozmysłu: panele odpowiadają na
rozłączne klawisze, więc nie ma czym się mylić ani czego przełączać — a `Tab`
zostaje wolny.”* Rozdział był taki: strzałki należą do sekcji, `PgUp`/`PgDn`
do podglądu. Powód odwołania jest jednozdaniowy: **podgląd tekstu, który nie
umie przewinąć się o wiersz, nie jest podglądem tekstu**, a strzałki są jedynym
klawiszem, którego użytkownik szuka odruchowo — i nie da się ich mieć w dwóch
miejscach naraz.

Ognisko przenosi `Tab`, czyli ten sam klawisz i ta sama klasa stanu
(`SplitState`), co podział przeglądarki od kroku 24 — wraz z regułą „brak
podziału sprowadza ognisko na pierwszy panel”. Panel czynny poznaje się po
akcencie w obwódce, też jak w przeglądarce. **Spis klawiszy zależy od ogniska**
i pokazuje wyłącznie to, co działa tu i teraz; klawisze niezwiązane z panelem
(`Alt`+`Z`, `s`, `d`) działają zawsze, bo dotyczą **opisywanego pliku**, a nie
tego, na co się patrzy.

**2. Jednostką przewijania jest linijka panelu, nie wiersz pliku.** To była
rozstrzygnięta wprost decyzja użytkownika, postawiona przeciw wariantowi
tańszemu o połowę. Różnica widać dopiero przy zawijaniu — i wtedy widać ją
boleśnie: wiersz pliku zajmuje kilka linijek, więc „przewiń o wiersz” znaczy raz
o jedną linijkę, a raz o dziesięć. Na pliku będącym **jedną długą linią**
(`.php-cs-fixer.cache`) strzałka w dół skakałaby od razu na koniec pliku.

Przy okazji naprawiło to wadę starszą, o której nikt nie wiedział: `PgDn`
przewijał o tyle **wierszy pliku**, ile panel ma linijek — a przy zawijaniu
wierszy widać mniej niż linijek, więc **gubił treść**.

**3. Kotwica zostaje na początku wiersza — pomijanie linijek jest osobno.**
Rozwiązanie, które przesuwałoby kotwicę w środek wiersza, wymagałoby mapowania
znaków na bajty: przy UTF-16 i przy rozwiniętych tabulatorach to nie jest ta sama
arytmetyka. Zamiast tego kotwica stoi tam, gdzie stała (na początku wiersza),
a ile jego linijek pominąć, mówi `$textRowSkip`. Pominięte linijki odcina się
z **treści**, nie polem komponentu: `TextView` zawija to, co dostanie, więc
wiersz podany bez początku zawija się dokładnie od właściwego miejsca. Rdzeń nie
wie o pomijaniu linijek nic.

Okno podglądu niesie za to **bajtowe początki wierszy** (`TextWindow::$starts`),
bo bez nich każde naciśnięcie strzałki musiałoby szukać początku wiersza osobnym
odczytem.

**4. Szerokość linijki ma jedno źródło i nie zależy od treści.** To jest lekcja
z dwóch pomyłek popełnionych przy tej zmianie, obu wyłapanych dopiero na
zrzucie z XTerma:

- **kolumna suwaka** znikała, gdy plik mieścił się w oknie, więc ten sam plik
  miał inną szerokość treści przed i po dopisaniu wiersza;
- **kolumna numerów** liczyła się z liczby **wczytanych wierszy**, więc plik
  o jednej długiej linii miał ją inną niż plik kodu — a obraz przewijał się
  wtedy o dwa znaki na krok.

Obie zależały od treści, a treść zmienia się przy każdym przewinięciu. Dziś obie
biorą się z **prostokąta**: suwak dostaje kolumnę z chwilą podania położenia
(a nie z chwilą, gdy naprawdę się rysuje), numery — z wysokości prostokąta.
Regułę trzyma jedno miejsce, `TextView::contentColumns()`, bo potrzebuje jej
także ten, kto czyta plik. **Reguła ogólna: geometria, od której zależy odczyt,
nie ma prawa zależeć od tego, co odczytano.**

**5. `End` gubi numery wierszy i to jest uczciwa cena.** Skok na koniec sadza
kotwicę po bajcie, a numeru wiersza z bajtu nie da się wyczytać bez przejścia
przez cały plik — czyli bez zrobienia tego, czego ten moduł nie robi (D58).
Numery znikają więc do najbliższego `Home`, zamiast pokazywać zmyśloną liczbę.
Sam skok cofa się **wiersz po wierszu**, aż uzbiera się panel linijek; wiersz
wypełniający cały panel mierzy się drugi raz, budżetem pełnym, bo inaczej plik
będący jedną linią kończyłby się tam, gdzie się zaczyna.

## Decyzje z planowania Fazy XI (2026-08-13)

### D61 — Diagnostyka, benchmark i testy funkcjonalne wchodzą do planu jako Faza XI z krokiem 38

**Dotyczy:** kroku 38 (pełna treść:
[38-rozbudowa-diagnostyki-i-testow.md](archiwum/38-rozbudowa-diagnostyki-i-testow.md))
i struktury planu ([00-index.md](00-index.md)).

**Data:** 2026-08-13.

**Decyzja:** na żądanie użytkownika plan dostaje krok przekrojowy — rozbudowę
narzędzi diagnostycznych, scenariuszy pomiarowych `bin/render-bench` i testów
funkcjonalnych, wraz ze scenariuszami obu rodzajów. Krok stoi w osobnej
Fazie XI i nosi numer 38.

**Uzasadnienie.** Aplikacja przez Fazy IV–IX urosła szybciej niż jej miary
i zaległości zebrały się w kilku miejscach naraz:

- tor tekstowy jest jedynym z trzech tłumaczy słownika prymitywów, którego
  narzędzie nie mierzy;
- koszt zimnej klatki (start, zmiana rozmiaru, pierwsze dekodowanie
  miniatury) jest odrzucany razem z rozgrzewką — dług zapisany już w kroku 16;
- regresję wizualną wykrywa wyłącznie człowiek oglądający PNG, choć
  najważniejsze odkrycie kroku 13 wyszło właśnie z obrazu, nie z liczb;
- przebiegi funkcjonalne istnieją jako skutki kroków, nie spis zachowań —
  nikt nie wie, których brakuje;
- metryczka wzorca nie niesie obciążenia maszyny, które dwukrotnie
  unieważniło pomiary (kroki 16 i 22).

**Jeden krok, nie trzy** — bo scenariusze pomiarowe i funkcjonalne mają
dzielić spis luk i katalog treści (deterministyczne klatki `ScenarioFactory`
mogą służyć obu), a trzy osobne kroki robiłyby trzy przeglądy tych samych
miejsc.

**Osobna faza, nie doklejka do Fazy VII** — krok nie dowozi komponentu ani
mechanizmu; rytm „jeden komponent — jeden krok” (D48) zostaje nietknięty.

**Granice zapisane od razu:** scenariusze `tree` i `menu` należą do kroków 31
i 32 (D48); bramka wydajności w CI pozostaje odrzucona (krok 16); otwarcie
pomiaru pełnej pętli (rozstrzygnięcie nr 5 kroku 16) wymaga osobnej zgody
użytkownika na starcie kroku.

**Odrzucone alternatywy:** trzy osobne kroki (diagnostyka / benchmark /
testy); dopisywanie scenariuszy wyłącznie przy okazji przyszłych kroków — to
już się dzieje (D48) i nie domyka luk wstecznych; test wydajnościowy
w bramce jakości — odrzucony w kroku 16 i nieprzywracany.

## Decyzje z planowania Fazy XII (2026-08-13)

### D62 — Makefile jako jedno wejście do projektu: osobna Faza XII z krokiem 39

**Dotyczy:** kroku 39 (pełna treść: [39-makefile.md](archiwum/39-makefile.md))
i struktury planu ([00-index.md](00-index.md)).

**Data:** 2026-08-13.

**Decyzja:** na żądanie użytkownika plan dostaje krok narzędziowy — `Makefile`
obejmujący weryfikację środowiska, instalację zależności, analizę jakości kodu,
testy jednostkowe, funkcjonalne i wydajnościowe oraz budowę aplikacji
Composerem. Krok stoi w osobnej Fazie XII i nosi numer 39.

**Uzasadnienie.** Wejścia do projektu istnieją, ale żadne z nich nie wie
o pozostałych:

- wymagania środowiska (PHP `^8.3`, `imagick`, `pcntl`, `stty`, koder `SIXEL`,
  Composer 2.x, opcjonalnie `glfw`, `intl`, `xterm`) opisuje **wyłącznie proza
  README** — sprawdzają się dopiero uruchomieniem aplikacji, i to częściowo:
  preflight w `bin/light-manager` zna trzy z nich;
- polecenia jakości mieszkają w `composer.json`, a bramka „PHPStan bez błędów,
  CS bez uwag, testy zielone” powtarza się w kryteriach każdego kroku planu
  jako **nawyk bez nazwy**;
- testy przebiegów leżą wymieszane z jednostkowymi w jednej testsuite, więc
  nie da się uruchomić samych szybkich ani samych funkcjonalnych;
- narzędzia mają trzy skrypty powłoki w `bin/`, każdy z własną kopią zasobów
  XTerma;
- **budowy nie ma w ogóle** — ani celu, ani katalogu wyniku, ani ustalenia, co
  „zbudowana aplikacja” znaczy w projekcie, który niczego nie kompiluje.

**Jeden krok, nie trzy** (środowisko / jakość / budowa) — bo to jeden plik
i jeden spójny zestaw celów; trzy kroki dzieliłyby ten sam `Makefile` na trzy
przeglądy i trzy okazje do rozjechania się nazw.

**Osobna faza, nie doklejka do Fazy XI** — krok 38 rozbudowuje **treść** miar
(scenariusze, przebiegi, wzorce), krok 39 daje **wejście** do ich uruchamiania.
Jedyny punkt styku to podział testsuite: pytanie nr 5 kroku 39 i pytanie nr 6
kroku 38 rozstrzygają tę samą granicę, więc zalecana kolejność to 38 przed 39,
a odwrotna wymaga przesądzenia podziału w kroku 39.

**Reguła zapisana od razu — Makefile nie jest drugim źródłem prawdy.** Cele mają
wołać `composer`, `phpunit` i `bin/render-bench`, a nie powtarzać ich
konfiguracji własnymi słowami; gdzie po tym kroku mieszka definicja poleceń
jakości (`composer.json` czy `Makefile`), rozstrzyga użytkownik na starcie
(pytanie nr 3), ale **jedno z dwóch miejsc musi ustąpić**.

**Granice zapisane od razu:** bramka wydajności pozostaje odrzucona (krok 16) —
cel pomiarowy powstaje, ale **nie może być zależnością** `make qa`, a reguła
`CLAUDE.md` o proszeniu użytkownika o zwolnienie mocy hosta obowiązuje go tak
samo jak wywołanie ręczne; CI poza zakresem (`make qa` ma być z niego
wywoływalny, gdyby powstało — i tyle); Makefile nie instaluje rozszerzeń PHP
ani zależności systemowych, tylko nazywa ich brak; Windows poza zakresem
(README zamyka projekt do Linuksa i macOS-a); wykrywanie Sixela odpowiedzią DA1
zostaje w `bin/terminal-probe`, bo wymaga interaktywnego terminala, którego
`make` nie ma.

**Znaczenie „budowy” rozstrzyga użytkownik** (pytanie nr 7 kroku): katalog
dystrybucyjny bez zależności deweloperskich, archiwum, PHAR czy sam
zoptymalizowany autoloader — od tego zależy, czy do projektu wchodzi nowa
zależność deweloperska (builder PHAR-a) i czy potrzebne jest źródło wersji,
którego `composer.json` dziś nie niesie.

**Odrzucone alternatywy:** kolejny skrypt w `bin/` zamiast Makefile'a — trzy
takie już są i to właśnie brak wspólnego wejścia jest problemem; rozbicie na
osobne kroki „środowisko”, „jakość” i „budowa”; wprowadzenie CI razem
z Makefile'em — to osobna decyzja, a jej najbardziej kuszący element (bramka
wydajności) jest odrzucony od kroku 16; dołożenie przy okazji nowych narzędzi
jakości (Psalm, Rector, Infection) — krok daje wejście do zestawu, który jest,
a nie powód do jego rozszerzania.

**Uzupełnienie tego samego dnia:** zakres kroku obejmuje także zapisanie reguły
procesu w dokumentach projektu — zobacz **D63**.

### D63 — Procesy projektu idą przez `make` i narzędzia repozytorium; reguła wchodzi do dokumentów

**Dotyczy:** kroku 39 ([39-makefile.md](archiwum/39-makefile.md), zakres pkt 8),
`CLAUDE.md`, [docs/architecture.md](../architecture.md) oraz
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md).

**Data:** 2026-08-13.

**Decyzja:** krok 39 nie kończy się na powstaniu `Makefile`. Ma **zapisać
w dokumentach**, że wszystkie procesy związane z aplikacją uruchamia się celami
`make`, a tam, gdzie projekt ma własne narzędzie (`bin/render-bench`,
`bin/terminal-probe`, scenariusze `ScenarioFactory`), używa się **jego** zamiast
dorabiać zastępnik doraźnie. Pełna treść — spis „proces → wejście” i reguła
pierwszeństwa — trafia do `docs/architecture.md`; skrót operacyjny do `SKILL.md`;
`CLAUDE.md` dostaje wskazanie i rozszerzenie istniejącej reguły pomiaru na cel
pomiarowy Makefile'a, **pozostając wskaźnikiem, a nie drugą instrukcją** (D13).

**Uzasadnienie.** Sam plik nie zmienia nawyku. `Makefile`, o którym dokumenty
milczą, przegrywa z pamięcią mięśniową w tydzień, a wtedy wejście do projektu
jest rozsypane po **czterech** miejscach zamiast trzech. Dziś stan dokumentów
wprost do tego zaprasza:

- `docs/architecture.md` opisuje procesy jednym wierszem „Skróty uruchomieniowe:
  `composer test`, `composer stan`, `composer cs`, `composer cs:check`” —
  **cztery z ośmiu** procesów projektu; o sprawdzeniu środowiska, pomiarze,
  budowie i uruchomieniu dokument źródłowy nie mówi nic;
- `SKILL.md` — plik ładowany przy pisaniu kodu w `src/`/`tests/` — **nie zawiera
  ani jednego polecenia**, więc nie odpowiada na pytanie „czym sprawdzić, co
  właśnie napisałem”;
- `CLAUDE.md` jest jedynym miejscem mówiącym o jakimkolwiek procesie i mówi
  o jednym: o pomiarze.

**Druga połowa reguły jest ważniejsza od pierwszej.** Wywołanie procesu
z pamięci zamiast celem `make` kosztuje niewiele; napisanie **własnej pętli
`microtime()` zamiast `bin/render-bench`** kosztuje wynik, którego nie da się
porównać z żadnym wzorcem, nie niesie metryczki środowiska i nie zostawia śladu
— czyli pracę do wyrzucenia. Ta sama zasada obejmuje dokładanie scenariuszy do
`ScenarioFactory` zamiast obok niej i sprawdzanie wejścia terminala
`bin/terminal-probe` zamiast doraźną powłoką.

**Granica zapisana od razu:** reguła dotyczy **procesów**, nie zabrania
narzędzi. Zawężenie przebiegu wolno wołać wprost (pojedynczy test filtrem
PHPUnita, jedna oś `bin/render-bench`, `composer` przy pracy nad zależnościami);
zakazane jest **dorabianie równoległej drogi** do procesu, który wejście już ma.
Jak ostro to sformułować — czy jako twardą regułę w rejestrze „nie odstępuj bez
jawnej zgody”, czy jako zalecenie — rozstrzyga użytkownik na starcie kroku
(pytanie nr 11), tak samo jak miejsce spisu w `docs/architecture.md`
(pytanie nr 12).

**Odrzucone alternatywy:** zostawienie reguły wyłącznie w `README.md` — README
czyta człowiek przy pierwszym kontakcie, a proces uruchamia się przy każdym
kroku planu; przeniesienie całej treści do `CLAUDE.md` — plik ma być krótkim,
bezwarunkowo ładowanym wskaźnikiem (D13), a nie miejscem, gdzie mieszka wiedza;
wymuszanie reguły hookami gita — mechanizm egzekwujący za plecami zamiast
zapisanej konwencji jest osobną decyzją i tego kroku nie dotyczy; opisanie
procesów w samym `Makefile` (komentarze w celach) — to jest pomoc przy
wywołaniu, nie miejsce, do którego ktoś zajrzy, zanim wywoła coś innego.

## Decyzje wykonawcze kroku 38 (2026-08-13)

### D64 — Rozstrzygnięcia startowe kroku 38: co narzędzie mierzy, czym wykrywa regresję i gdzie mieszkają przebiegi

**Dotyczy:** kroku 38 (pełna treść:
[38-rozbudowa-diagnostyki-i-testow.md](archiwum/38-rozbudowa-diagnostyki-i-testow.md)).

**Data:** 2026-08-13, przed pierwszą linią kodu — dziewięć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” plus dwa doprecyzowania, które wyszły dopiero
z odpowiedzi.

**Decyzje użytkownika:**

1. **Zakres pomiaru: potok i przesył zostają główną miarą, takt pętli wchodzi
   jako pomiar dodatkowy.** Rozstrzygnięcie nr 5 kroku 16 nie jest odwołane —
   jest **rozszerzone o osobny tryb**. Doprecyzowanie wymusiła budowa
   narzędzia: szesnaście scenariuszy powstaje w `ScenarioFactory` wprost
   z prymitywów, **z pominięciem ekranów**, więc taktu pętli nie da się dopiąć
   do nich jako kolejnej kolumny — potrzebuje własnego źródła treści. Stąd
   tryb `--loop` z **własną tabelą i własnym wzorcem**: liczby obu pomiarów są
   nieporównywalne, więc nie mają prawa stać w jednej tabeli.
2. **Zimna klatka: pierwsza próbka rozgrzewki raportowana osobno, kolumna
   wchodzi do wzorca.** Przestaje być odrzucana, staje obok mediany. Zimno jest
   **zimnem pamięci podręcznych klatki, nie zimnem procesu** — singletony
   i wybór fontu pozostają ciepłe — i tak ma być zapisane, żeby nikt nie czytał
   tej liczby jako kosztu startu aplikacji. Do wzorca wchodzi jako zapis, ale
   **nigdy nie podnosi alarmu regresji**: rozrzut pojedynczej próbki jest
   z natury większy od progu, którym mierzy się mediany.
3. **Tor tekstowy dostaje jawne kroki pomiarowe w `TextFrameRenderer`** —
   zmiana produkcyjna, świadoma i nazwana, na wzór rozbicia `SixelFrameEncoder`
   w kroku 16. D28 („zero wywołań pomiarowych w kodzie produkcyjnym”)
   **zostaje w mocy**: rozbicie na publiczne kroki nie jest instrumentacją,
   zegar stoi po stronie narzędzia. To drugi i ostatni renderer, któremu
   przyznano taki szew.
4. **Regresja wizualna: wzorcowe PNG w repozytorium, metryka AE.** Miejsce —
   `docs/pomiary/wzorce-png/`, tą samą zasadą, którą D33 zastosował do wzorców
   JSON: wzorzec poza repozytorium przepada razem z maszyną. Metryka **AE**
   (liczba różniących się pikseli) z progiem, a nie RMSE — bo cienki obrys
   zjedzony przez kwantyzator, czyli dokładnie to odkrycie kroku 13, tonie
   w średniej po całym obrazie. Przy przekroczeniu progu narzędzie zapisuje
   obraz różnicy obok i **odmawia, wskazując pliki** — bez GUI i bez raportów.
5. **Zrzuty robią oba tory, wzorce okienkowe też leżą w repozytorium.**
   Świadomie przyjęta cena: potok Imagicka jest deterministyczny i znosi ostry
   próg, a tor okienkowy rysuje przez sterownik GPU, więc jego wzorzec jest
   związany z maszyną — próg dla niego musi być luźniejszy i to ma być
   zapisane przy wzorcu, nie odkrywane przy pierwszej „regresji”.
6. **Komenda zrzutu z żywej aplikacji wchodzi do rejestru komend** i zapisuje
   **prymitywy oraz PNG we wszystkich trzech torach**. Doprecyzowanie, którego
   pytanie nie przewidziało: renderer tekstowy nie ma płótna, więc PNG musi
   skądś powstać — i powstaje **wiernie każdemu torowi**. Sixel oddaje płótno,
   okno oddaje bufor GPU, a tor tekstowy dostaje **rasteryzację bufora ANSI**
   fontem stałej szerokości wraz z kolorami. Odrzucono tańsze „PNG rysowane
   Imagickiem z prymitywów niezależnie od czynnego renderera”: zrzut, który
   pokazuje, co narysowałby **inny** renderer, nie jest dowodem na to, co
   narysował ten czynny — a właśnie tego dowodu zabrakło w kroku 29.
7. **Przebiegi funkcjonalne mieszkają w `tests/Functional/`**, jako nazwane
   pliki, razem z tymi, które dziś leżą rozsypane. Większość idzie przez
   `ScreenFixture`; **start aplikacji i zmiana rozmiaru — przez `GameLoop` ze
   `ScriptedTerminal`**, bo taktu bez pętli sprawdzić się nie da. Osobna
   testsuite w `phpunit.xml.dist` jest przy okazji **granicą, której potrzebuje
   krok 39** na rozbicie `test-unit` / `test-functional` (D62) — miękka
   zależność między krokami zostaje tym samym spłacona po stronie 38.
8. **Złote klatki: pliki serializacji prymitywów z jawną regeneracją.** Test
   przy różnicy wskazuje **pierwszy różniący się prymityw**, nie zrzuca całego
   pliku; regeneracja wyłącznie osobnym poleceniem, **nigdy automatem** — złoty
   plik regenerowany bez czytania przestaje być testem i to jest jedyne realne
   ryzyko tego pomysłu.
9. **Szczyt pamięci wchodzi jako kolumna** — jedna liczba na scenariusz
   (`memory_get_peak_usage`), bez profilowania funkcja po funkcji, które
   zostaje poza zakresem tak samo jak w kroku 16.
10. **`--save` na obciążonej maszynie ostrzega, nigdy nie odmawia.**
    Obciążenie (loadavg na rdzeń) wchodzi do metryczki wzorca i pokazuje się
    przy `--compare`. Odmowa zostaje wyłącznie przy strażniku rozrzutu
    (1,35×), który łapie zakłócone przebiegi po skutku, a nie po przesłance —
    decyzję o zapisie podejmuje człowiek, mając liczbę przed oczami.

**Uzasadnienie wspólne.** Wszystkie dziesięć rozstrzygnięć układa się w jedną
zasadę: **narzędzie ma mierzyć to, co dzieje się naprawdę, i mówić wprost,
czego nie mierzy**. Stąd zimna kolumna obok mediany zamiast zamiast niej, stąd
zrzut wierny każdemu torowi zamiast jednego wygodnego źródła obrazu, stąd
osobna tabela dla taktu pętli i stąd ostrzeżenie zamiast odmowy tam, gdzie
narzędzie zna przesłankę, a nie skutek.

**Granice zapisane od razu:** scenariusze `tree` i `menu` należą do kroków 31
i 32 (D48); bramka wydajności w CI pozostaje odrzucona (krok 16);
profilowanie (flame graph, pamięć funkcja po funkcji) zostaje poza zakresem;
zrzuty spod prawdziwego terminala zostają ręczne, na odciążonej maszynie, jak
każe `CLAUDE.md` i reguła 17 Skilla.

## Decyzje z planowania Fazy XIII (2026-08-13)

### D65 — Stopka pokazuje podpowiedzi dla elementu z ogniskiem: osobna Faza XIII z krokiem 40

**Dotyczy:** kroku 40 (pełna treść:
[40-stopka-kontekstowa.md](archiwum/40-stopka-kontekstowa.md))
i struktury planu ([00-index.md](00-index.md)).

**Data:** 2026-08-13.

**Decyzja:** na żądanie użytkownika plan dostaje krok, po którym pasek stanu
przestaje pokazywać cztery niezmienne klawisze rdzenia, a zaczyna pokazywać
czynności i skróty **elementu, ekranu albo modułu, na którym stoi ognisko**.
Krok stoi w osobnej Fazie XIII i nosi numer 40.

**To jest odwrócenie wcześniejszego rozstrzygnięcia** — jedyne takiej wagi od
czasu D60. Krok 14 postanowił, a krok 18 powtórzył i zapisał w komentarzu
`FrameComposer::hints()`: „stopka nie jest ściągawką, tylko wskazaniem, gdzie
ściągawka leży. Pełny spis stoi pod `F1`”. Odwraca się **zasięg**, nie źródło:
podpowiedzi nadal powstają z `KeyBinding`, czyli z tego samego miejsca, z którego
pochodzi obsługa klawisza. Powrotu do napisu `browser.hints` w katalogu — który
potrafił skłamać po zmianie wiązania i który krok 18 usunął — **nie ma i żadne
rozstrzygnięcie startowe tego kroku nie ma prawa go przywrócić**. Okno pomocy
zostaje przy tym **pełnym** spisem; stopka pokazuje to, co się mieści.

**Uzasadnienie.** Materiał na stopkę kontekstową jest w kodzie od dawna i już
dziś jest zależny od stanu — `BrowserScreen` pokazuje `Tab` dopiero przy
włączonym podziale, `FileInfoScreen` oddaje inne strzałki każdemu z dwóch paneli,
`BrowserScreen` dokłada `Esc`, dopiero gdy jest co zdejmować. Wszystkie te
odpowiedzi trafiają **wyłącznie do okna pomocy**, a pasek stanu, który użytkownik
ma przed oczami cały czas, powtarza te same cztery klawisze od pierwszego
uruchomienia. Skróty modułów (`Ctrl`+litera) nie pokazują się nawet tam — nie ma
ich w `globalBindings()`, bo powstają dopiero z rejestru modułów.

**Cztery rozstrzygnięcia startowe użytkownika (2026-08-13):**

1. **Zakres obejmuje formalizację ogniska**, nie samą stopkę. `FocusableInterface`
   istnieje od kroku 18, ale implementują go dwa komponenty (`TextInput`,
   `Button`), a prawdziwe ognisko żyje w `SplitState`, `BrowserPanes`
   i `SettingsCursor` — bez wspólnej nazwy i bez drogi na zewnątrz. Krok robi
   z tego kontrakt, a `BrowserScreen`, `FileInfoScreen` i `SettingsScreen` są
   jego prawdziwymi użytkownikami (reguła 13).
2. **Pasek stanu może urosnąć do dwóch wierszy.** Odrzucone zostały: sztywny jeden
   wiersz (cała trudność spadłaby na skracanie) oraz piąta strefa układu na
   podpowiedzi (największa zmiana w `HudLayout` i we wszystkich rendererach
   wzorców). Wybrany wariant jest najdroższy z punktu widzenia wzorców — zabiera
   wiersz liście i przelicza wszystkie zrzuty w trzech torach — i **to jest
   świadoma cena**, nie przeoczenie.
3. **Klawisze globalne zostają, kontekstowe stoją przed nimi.** Stopka czyta się
   „to, co tu i teraz” → „to, co wszędzie”. Ustępowanie idzie od końca, bo
   globalne jedne są niezmienne i stoją w oknie pomocy; `F1` ustępuje ostatni,
   inaczej znika jedyna droga do pełnego spisu.
4. **Fable / xhigh** — jak przy krokach 33–35, i z tego samego powodu: zmian jest
   dużo, są rozproszone (kontrakt, składanie klatki, komponent, progi układu, trzy
   ekrany, dwa moduły, dwa katalogi napisów, wzorce w trzech torach) i muszą
   zgodzić się ze sobą naraz.

**Trudność strukturalna zapisana od razu.** Aplikacja **nie ma zachowanego drzewa
komponentów** — komponent powstaje w `draw()` i ginie z klatką, a stan
przeżywający takt mieszka obok niego (`ScrollWindow`, `SectionState`,
`SplitState`). Elementu z ogniskiem nie da się więc odnaleźć chodzeniem po
drzewie: **ognisko trzeba zadeklarować, a nie odkryć**, i `FrameComposer` ma
o nie pytać, a nie go szukać. To przesądza o kształcie kontraktu z punktu 1
i jest właściwym powodem, dla którego krok nie jest drobiazgiem w jednym pliku.

**Pułapka pomiarowa zapisana od razu:** `ScenarioFactory::HINTS` jest **stałą
tekstową** oderwaną od aplikacji, więc dopóki się jej nie ruszy, pomiar „przed
i po” nie zobaczy zmiany stopki w ogóle. Zmiana tej stałej jest warunkiem
sensowności pomiaru, a nie kosmetyką.

**Granice zapisane od razu:** przemapowanie klawiszy przez użytkownika zostaje
poza zakresem; okno pomocy nie zawęża się do kontekstu — `F1` zostaje pełnym
spisem; klikalne podpowiedzi odpadają, bo rdzeń nie ma słownika zdarzeń myszy
(ta sama granica, co w krokach 34–35); ruch i przewijanie w pasku stanu odpadają,
bo ściągałyby oko z treści; okno komend zostaje przy własnej liście podpowiedzi.

**Styk z krokiem 32** (menu kontekstowe): oba kroki pytają „co da się zrobić
z tym, na czym stoi kursor”, ale z dwóch stron — menu o **komendy**, stopka
o **klawisze**. Zależności między nimi nie ma w żadną stronę; jeśli krok 32
wykona się pierwszy i wprowadzi zdolność „czego dotyczę”, stopka może z niej
skorzystać, a jeśli nie — nie ma z czego i nic to nie blokuje.

**Odrzucone alternatywy:** dopisanie stopki kontekstowej jako poprawki do
kroku 37 („dopracowanie okna”) — tamten krok dotyczy okna GLFW, a ten wszystkich
trzech torów naraz; napis podpowiedzi w katalogu, osobny na ekran (stan sprzed
kroku 18, usunięty właśnie dlatego, że kłamał); pokazywanie **wszystkich** wiązań
ekranu bez polityki szerokości — `FileInfoScreen` deklaruje ich osiem, więc
w osiemdziesięciu kolumnach nie ma o czym mówić.

## Decyzje z planowania Fazy XIV (2026-08-13)

### D66 — Operacje na plikach wchodzą jako Faza XIV: cztery kroki, usługa zapisu w rdzeniu, praca kawałkowa

**Dotyczy:** kroków 41–44 (pełna treść:
[41-operacje-fundament.md](archiwum/41-operacje-fundament.md),
[42-kopiowanie-i-przenoszenie.md](archiwum/42-kopiowanie-i-przenoszenie.md),
[43-zaznaczenie-wielokrotne.md](archiwum/43-zaznaczenie-wielokrotne.md),
[44-kosz-i-cofanie.md](archiwum/44-kosz-i-cofanie.md)) i struktury planu
([00-index.md](00-index.md)).

**Data:** 2026-08-13.

**Decyzja:** na żądanie użytkownika plan dostaje fazę, po której przeglądarka
przestaje wyłącznie czytać dysk. Zamyka to **pierwszą pozycję „Zakresu poza
MVP”** — stojącą tam od pierwszej wersji planu — oraz pozycję „zaznaczenie
wielokrotne”, wyłączoną swego czasu z kroku 32. Wstęp dowiozły kroki **24** (dwa
panele, czyli źródło i cel) i **28** (okno potwierdzenia, bez którego usuwanie nie
miało prawa powstać).

**Cztery rozstrzygnięcia startowe użytkownika (2026-08-13):**

1. **Cztery kroki, nie jeden.** Fundament (41: zmiana nazwy, nowy katalog,
   usunięcie), praca dłuższa od klatki (42: kopiowanie i przenoszenie),
   mnożnik (43: zaznaczenie wielokrotne) i droga powrotna (44: kosz i cofanie).
   Odrzucono jeden krok obejmujący całość — byłby najdłuższy w planie i nie dałby
   się rozliczyć pomiarem „przed i po”, bo mierzone byłyby naraz cztery różne
   rzeczy.
2. **Operacje zapisu mieszkają w rdzeniu jako usługa wspólna.** To jest
   **jawny wyjątek od reguły 15** („nowa funkcja to moduł, nie zmiana w rdzeniu”)
   i **częściowe odwrócenie D40/D42** („rdzeń przestaje wiedzieć o plikach”).
   Zakres tej wiedzy jest wyznaczony wąsko i szerszy być nie ma prawa: rdzeń zna
   **ścieżkę bezwzględną jako napis** i czynność do wykonania, a `Entry`,
   `Directory`, `DirectoryPath` i `EntryType` **nie mają prawa** trafić do
   sygnatury czegokolwiek w `src/Application` ani `src/Domain`. Powodem jest
   druga reguła tej samej pary: „moduł nigdy nie sięga do innego modułu” znaczy
   przy dwóch odbiorcach (przeglądarka, opis pliku) **dwie kopie kodu piszącego
   po dysku**. Rachunek `permissionsAsText()` wolno było powtórzyć, bo kosztował
   dziesięć linii bez skutków ubocznych; powtórzone `unlink()` kosztuje utratę
   danych w dwóch miejscach zamiast w jednym. **Cena zapisana od razu:** granica
   wyjątku musi trafić do `SKILL.md` wraz z powodem — nienazwana, otworzyłaby
   rdzeń na wszystko tym samym argumentem.
3. **Kopiowanie idzie pracą kawałkową we własnym procesie (D46), nie procesem
   potomnym.** Trzy powody, wszystkie konkretne: `BackgroundProcessPort` prowadzi
   **jedną** pracę naraz, więc kopiowanie wypierałoby `du` z modułu opisu pliku;
   postęp procesu jest nieznany, a bajty skopiowane własną ręką są policzone co
   do jednego; zachowanie `cp` zależy od systemu, a zachowanie własnej pętli nie.
   Krok 26 zostaje przez to **wzorcem do świadomego odrzucenia**, a nie do
   powtórzenia — pierwszy taki przypadek w planie.
4. **Zaznaczenie wielokrotne wchodzi, a usunięcie ma dwie drogi**: trwałą i do
   kosza, **rozróżniane użytym skrótem oraz ustawieniem modułu**. To rozstrzygnięcie
   przesądza o kształcie kroku 44 i o jego modelu — patrz trudność niżej.

**Trudności strukturalne zapisane od razu** (każda odkryta przy planowaniu, nie
przy pisaniu kodu):

- **Słownik wejścia nie zna `Shift`** — w żadnym z trzech torów.
  `KeySequenceParser` ma napisane wprost, że modyfikatory CSI nie zmieniają
  klawisza bazowego; `GlfwKeyMapper` czyta wyłącznie `GLFW_MOD_CONTROL`
  i `GLFW_MOD_ALT`, i tylko dla liter; `Ctrl`+litera jest **zajęty w całości**
  przez skróty modułów (krok 20), a `Alt` powstaje z `ESC` + znak drukowalny,
  więc z klawiszem funkcyjnym się nie składa. Rozstrzygnięcie 4 dotyka tego
  wprost, dlatego model kroku 44 wybiera się **po** odpowiedzi na jego pytanie
  startowe nr 1, a nie przed nią.
- **Praca kawałkowa posuwa się w `draw()` widocznego ekranu.** Dla sumy
  kontrolnej to zaleta („nikt nie liczy sumy pliku, na który nikt nie patrzy”),
  dla kopiowania wada: wyjście z przeglądarki wstrzymałoby pracę bez słowa.
  Krok 42 rekomenduje zostawienie tej zasady wraz z **zapisaną granicą**, bo
  wariant „praca ponad ekranami” potrzebuje paska postępu widocznego zewsząd,
  czyli tej samej strefy, którą przelicza krok 40 — a dwa kroki przebudowujące
  pasek stanu z dwóch powodów naraz to najprostsza droga do sprzeczności.
- **`ModuleContext` zna jeden wpis**, więc zaznaczenie wielokrotne albo zostaje
  własnością przeglądarki (rekomendacja kroku 43), albo kontrakt rdzenia rośnie
  o zbiór — czyli drugi wyjątek od reguły 15 w jednej fazie, i to dla wygody
  jednego przyszłego widoku, a nie dla niepowielania kodu.
- **Nazwa scenariusza `selection` jest zajęta** przez kursor na liście od kroku
  18; scenariusz zaznaczenia wielokrotnego potrzebuje własnej (`marked`).

**Granice zapisane od razu:** kosz obejmuje **wyłącznie** katalog domowy
(`.Trash-$uid` na wolumenach zewnętrznych zostaje poza zakresem); kolejki zadań
nie ma, bo jedna praca i jeden pasek to reguła kroków 23, 25 i 26; ponowienia
cofniętej operacji (`redo`) nie ma, bo bez stosu nie ma czego ponawiać; `chmod`
i `chown` zostają poza zakresem (wymagają własnego okna i uprawnień, których
aplikacja mieć nie powinna); śledzenia zmian katalogu z zewnątrz (`inotify`) nie
ma — lista odświeża się po **własnej** operacji; wznowienia przerwanego
kopiowania nie ma, bo praca przerwana jest pracą zakończoną.

**Styk z krokiem 32** (menu kontekstowe): jego zastrzeżenie ze szczytu pliku
mówiło, że menu należy odłożyć „do czasu, aż powstaną operacje na plikach, bo
dopiero one dadzą mu treść”. Faza XIV tę treść dowozi — zależności to jednak nie
tworzy w żadną stronę, a samo zastrzeżenie zdejmuje się dopiero przy starcie
kroku 32.

**Odrzucone alternatywy:** jeden krok obejmujący całość (rozstrzygnięcie 1);
operacje jako **nowy moduł `FileOps`** — najczystszy podział warstw, ale operacje
przestałyby być klawiszem na zaznaczonym wpisie, a stałyby się cudzym ekranem;
kopiowanie procesem potomnym przez `cp`/`mv` (rozstrzygnięcie 3); kopiowanie
synchroniczne z progiem rozmiaru i odmową powyżej niego — menadżer plików, który
odmawia skopiowania dużego pliku, jest niepełny; faza bez zaznaczenia
wielokrotnego (kopiowanie plik po pliku); usuwanie wyłącznie trwałe albo wyłącznie
do kosza — użytkownik chce obu dróg, rozróżnianych skrótem.

## Decyzje wykonawcze kroku 37 (2026-08-13)

### D67 — Rozstrzygnięcia startowe kroku 37: rozmiar w komórkach po ciszy, pełny ekran dwiema drogami, ikona okrężną drogą i skala tylko pokazana

**Dotyczy:** kroku 37 (pełna treść:
[37-dopracowanie-okna.md](archiwum/37-dopracowanie-okna.md)).

**Data:** 2026-08-13, przed pierwszą linią kodu — cztery pytania z sekcji „Do
rozstrzygnięcia na starcie kroku” plus piąte, które wymusiło **sprawdzenie
rozszerzenia** (patrz niżej).

**Ustalenie, które unieważniło część planu.** Sekcja „Stan zastany” kroku 37
wymieniała `glfwSetWindowIcon` wśród funkcji „wszystkich obecnych (sprawdzone
przy kroku 34)”. **W rozszerzeniu PHP-GLFW 2.2.0 tej funkcji nie ma w ogóle** —
jest wyłącznie `glfwIconifyWindow` (minimalizacja) i to ona zapewne była
źródłem pomyłki. Pozostałe funkcje wymienione w tamtej tabeli są obecne.
Rozstrzygnięcie nr 3 zostało więc zadane inaczej, niż przewidywał plan: nie
„skąd wziąć bitmapę”, tylko „czy w ogóle jest dokąd ją podać”.

**Decyzje użytkownika:**

1. **Rozmiar zapamiętuje się w komórkach, a zapisuje po uspokojeniu zmian.**
   Klucze zostają te same (`windowColumns`/`windowRows`), więc ekran ustawień
   nadal przełącza je strzałkami, a zapamiętany rozmiar nie jest osobnym bytem
   obok rozmiaru startowego — jest **tym samym bytem**. Konsekwencja, której nie
   dało się uniknąć: `WINDOW_*_CHOICES` przestaje być zakresem dopuszczalnych
   wartości (bo rozmiar po przeciągnięciu rogu prawie nigdy nie trafia
   w przystanek) i staje się **wyłącznie przystankami strzałek**; zakresu pilnują
   odtąd `WINDOW_*_MIN`/`MAX`. Druga konsekwencja: strzałka z wartości spoza
   listy musiała zmienić zachowanie — `next()` odsyłał na początek listy, więc
   „w prawo” z 137 kolumn dawałoby 80. Stąd `nextStop()`: sąsiad w stronę ruchu.
   Wybrany wariant zapisu (po ciszy, nie przy wyjściu) jest **droższy o klasę**
   (`WindowSizeSettle`) i o pytanie zadawane raz na takt, ale przeżywa `SIGKILL`
   i zamknięcie okna krzyżykiem. Zapis przy wyjściu **nie zniknął** — został jako
   druga droga dla zmiany, która nie zdążyła ucichnąć.
2. **Pełny ekran ma obie drogi naraz: komendę `core.fullscreen` i skrót `F11`,
   ale wyłącznie w torze okienkowym.** To jest **odstępstwo od planu, świadome
   i nazwane**: plan uprzedzał, że wariant „komenda i skrót” wymaga zmiany
   w `InputHandler`, „której plan nie przewiduje”. Zmiana weszła i sprowadza się
   do jednego: `globalBindings()` przyjmuje odtąd tryb (`bool $windowed`),
   a `InputHandler` — domknięcie przełączające (`null` w terminalu). `F11` jest
   przez to **pierwszym klawiszem rdzenia zależnym od trybu**; uzasadnia to
   precedens kroku 30 („spis pokazuje wyłącznie to, co działa tu i teraz”),
   rozciągnięty tu z okna pomocy na pasek stanu i na okno komend. Skrót
   **nie melduje się komunikatem**, komenda tak — bo skutek klawisza widać
   w tej samej klatce, a okno komend zamyka się razem z wykonaniem.
3. **Ikona idzie drogą `WM_CLASS` + wpis `.desktop`, a rysuje się z ról
   motywu.** Skoro `glfwSetWindowIcon` nie istnieje, zostaje droga standardowa
   obu serwerów wyświetlania: okno przedstawia się klasą
   (`glfwWindowHintString(GLFW_X11_CLASS_NAME, …)`), a pulpit dopasowuje do niej
   wpis i bierze ikonę stamtąd. Wpis zakłada **nowy skrypt**
   `bin/install-desktop-entry` wraz z ikoną rysowaną Imagickiem z ról włączonego
   motywu — cztery rozmiary w `~/.local/share/icons/hicolor/`. W repozytorium
   nie ląduje ani jeden plik binarny, a ikona zmienia się razem z motywem.
   Cena, którą trzeba znać: **krok dowozi możliwość, nie gotowy efekt** — bez
   uruchomienia skryptu ikony nie będzie, a w środowisku, które nie dopasowuje
   okien po `WM_CLASS`, nie będzie jej mimo skryptu. Warunkiem powodzenia jest
   zgodność `StartupWMClass` z `WM_CLASS`; pilnuje jej test, bo rozejście się
   tych dwóch napisów daje ikonę w spisie programów i ikonę zastępczą na pasku
   zadań, czyli usterkę, której nie widać w kodzie.
4. **Skala treści jest czytana i pokazywana, a nie stosowana.** Wartość
   `glfwGetWindowContentScale` trafia do zakładki „Aplikacja” okna pomocy —
   i tylko tam. Maszyna projektu ma skalę 1.0, więc przeliczenie komórki
   byłoby kodem, którego nie da się na niej sprawdzić; pokazana wartość
   pozwala komuś na innym sprzęcie powiedzieć, co widzi, i **dopiero to** ma
   zamienić się w przeliczenie. `VgContextService` zostaje nietknięty.
   Ograniczenie jest zapisane jawnie w README („Znane ograniczenia”), a nie
   tylko w komentarzu.

**Odrzucone alternatywy:** rozmiar w pikselach w nowych kluczach — wierny co do
piksela, ale odbierałby ekranowi ustawień możliwość przełączania strzałkami
i zostawiał `windowColumns`/`windowRows` bez roli; zapis wyłącznie przy wyjściu
— prostszy o klasę, ale gubiący rozmiar po `SIGKILL`; sam skrót `F11` bez
komendy — obowiązywałby także tryby terminalowe, w których nic nie znaczy; sama
komenda bez skrótu — pełny ekran to czynność, po którą sięga się odruchowo,
a nie z nazwy; pominięcie ikony wraz z zapisem powodu (wariant uczciwy, ale
zostawiający krok z trzema pozycjami z czterech); ikona przez zewnętrzne
narzędzie (`xseticon` — niezainstalowany, `xdotool` — tego nie umie); przeliczona
komórka pisana na ślepo z adnotacją „niesprawdzone na sprzęcie”.

**Co wyszło dopiero z uruchomienia** (i czego nie dało się przewidzieć przy
rozpisywaniu):

- **Powrót z pełnego ekranu nie trafia w te same piksele jednym wywołaniem.**
  `glfwSetWindowMonitor()` z zapamiętanymi granicami oddaje obszar treści niższy
  o pasek tytułu — menedżer okien liczy podaną geometrię jako geometrię **ramki**
  (zmierzone: 900×600 wracało jako 900×563, a okno zjeżdżało o te 37 pikseli
  w dół). Dostawienie rozmiaru osobnym `glfwSetWindowSize()` naprawia to
  w całości, ale **tylko wtedy, gdy pada po zakończeniu przejścia**; wołane
  w tym samym takcie nie zmienia nic. Stąd `restoreAfterFullscreen()`
  dopominające się z taktu na takt, z sufitem sekundy — wzorem reguły „element
  zmieniający się sam z siebie niczego nie wymusza” (11b): skoro pętla i tak
  pyta co takt, poprawka może poczekać na takt, w którym menedżer okien już
  odpowiedział. Bez tego pełny ekran **zapisywał do ustawień zmniejszony
  rozmiar**, czyli psuł pozycję nr 1 tego samego kroku.
- **Rozmiar wraca zaokrąglony w dół do pełnych komórek** (900×600 → 900×588 przy
  komórce 21 pikseli). To nie jest usterka, tylko cena rozstrzygnięcia nr 1
  wypowiedziana do końca: resztka, która nie tworzyła pełnego wiersza, nie ma
  czego pamiętać. Zapisane w README.
- **Naruszenie ochrony pamięci przy wyjściu z trybu okienkowego dotyczy także
  aplikacji**, nie tylko pomiaru — README przypisywał je dotąd samemu
  `render-bench`. Sprawdzone w osobnym drzewie roboczym na kodzie sprzed kroku
  37: **usterka jest starsza**, siedzi w sprzątaniu GLFW i pada **po** całym
  sprzątaniu aplikacji (historia komend i rozmiar okna są zapisane — sprawdzone
  wyjściem `F10` tuż po zmianie rozmiaru). Zapis README poszerzony.

## Decyzje ze startu kroku 31 (2026-08-14)

### D68 — Rozstrzygnięcia startowe kroku 31: prowadnice, kursor pod kluczem, widok panelu na klawisz i drzewo z plikami

**Dotyczy:** kroku 31 (pełna treść:
[31-drzewo-katalogow.md](archiwum/31-drzewo-katalogow.md)).

**Data:** 2026-08-14, przed pierwszą linią kodu — pięć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” plus trzy, które wynikły dopiero
z odpowiedzi nr 3 (klawisz widoku, znaczenie strzałek, treść drzewa).

**Decyzje użytkownika:**

1. **Wiersz drzewa dostaje pełne linie łączące** (`├─`, `└─`, `│`) — wariant
   najdroższy z trzech i wybrany świadomie. Cena jest wymierna i zapisana
   w komponencie: każdy poziom dokłada znak spoza podstawowej strony kodowej,
   czyli osobną bitmapę w pamięci podręcznej wierszy (D34), podczas gdy samo
   wcięcie ze znacznikiem kosztowałoby dwa znaki na całe drzewo — te same `▼`/`▶`,
   które krok 22 już rozliczył. Rozlicza to scenariusz `tree`.
2. **Kursor przechodzi na zwijanego rodzica**, a to wymusiło rzecz większą niż
   samo zachowanie: **kursor drzewa jest kluczem, a nie numerem**. Numer wiersza
   zmienia bowiem każde rozwinięcie i zwinięcie czegokolwiek powyżej, więc
   kursor-numer wędrowałby po drzewie sam z siebie. Reguła mieszka w
   `TreeState::collapse()`, a nie u wołającego, bo nie jest wyborem ekranu:
   węzły pod zwiniętą gałęzią przestają istnieć w spłaszczonej liście.
3. **Widok panelu przełącza klawisz, a nie ustawienie** — każdy panel z osobna
   bywa listą albo drzewem, także bez podziału. To jest **odstępstwo od planu**:
   plan zakładał „drzewo w jednym panelu podziału, lista w drugim”, a o tym, czy
   drzewo w ogóle powstaje, miało rozstrzygać ustawienie modułu. Skutki, których
   plan przez to nie przewidywał: drzewo należy do panelu (`BrowserPanes`), a nie
   do ustawień; każdy panel ma **własny** `BrowserTree` wraz z własnym oknem
   przewijania, bo lista i drzewo przewijają się po czym innym; ustawieniem
   modułu zostaje wyłącznie **głębokość**.
4. **Limit głębokości jest ustawieniem, z „bez limitu” jako jedną z wartości.**
   Stąd pozycja **wyboru**, a nie liczbowa — pierwsza taka w zakładce
   przeglądarki: „bez limitu” nie jest liczbą, a ekran ustawień pokazuje wartości
   wyboru **surowo**, bez katalogu napisów, więc zero albo wielkie przybliżenie
   trzeba by odgadywać. Znak `∞` czyta się tak samo w każdym języku. Domyślne
   osiem, bo limit ma być widoczny; odmowa rozwinięcia **melduje się zdaniem**,
   bo klawisz, który raz działa, a raz nie, czyta się jak usterka.
5. **`TreeState` i `SectionState` to dwie klasy.** Różnią się trzema rzeczami
   naraz i żadna nie jest kosmetyczna: drzewo trzyma **rozwinięcia** (domyślnie
   zwinięte), sekcja **zwinięcia** (domyślnie rozwinięte); kursor drzewa jest
   kluczem, sekcji — numerem; zwinięcie gałęzi przenosi kursor, zwinięcie sekcji
   nie ma czego przenosić. Wspólna klasa musiałaby przyjmować domyślną odpowiedź
   z zewnątrz, czyli nazywać się już nie stanem, tylko mapą wartości logicznych.
6. **Klawiszem widoku jest `Ctrl`+`T`** — i to jest rozstrzygnięcie z ceną
   nazwaną wprost przed wyborem. `Ctrl`+litera jest od kroku 19 przestrzenią
   **skrótów modułów**, sprawdzaną w `InputHandler` **przed** ekranem; litera bez
   zarejestrowanego modułu przechodzi niżej i tylko dlatego klawisz działa.
   Moduł ze skrótem `t` przejąłby go po cichu, więc kolizji pilnuje
   `BrowserShortcutsTest` — ma wyjść na testach, a nie na klawiaturze.
7. **W drzewie `→` rozwija, `←` zwija, a `Enter` wchodzi.** `Enter` zostaje przy
   swoim znaczeniu z całej aplikacji (P3: zatwierdza), więc katalog spod kursora
   staje się katalogiem panelu — z dowolnego poziomu drzewa. `←` ma trzy
   znaczenia czytane z góry na dół jako jedno zdanie („wróć o poziom”): zwiń,
   skocz do rodzica, wyjdź katalog wyżej. `Backspace` **nie zmienia znaczenia
   nigdy** — wyjście katalog wyżej ma jedną drogę niezależną od widoku.
8. **Drzewo pokazuje katalogi i pliki**, a nie samą strukturę katalogów. To jest
   konsekwencja rozstrzygnięcia nr 3 wypowiedziana do końca: skoro drzewo bywa
   **jedynym** widokiem panelu, drzewo bez plików byłoby regresją wobec listy,
   którą zastąpiło. Stąd `TreeNode` niesie także `value` — krótką wartość po
   prawej, jak `ListRow::$right` — i stąd zapis rozmiaru wyprowadził się
   z `EntryList` do wspólnego `EntrySize`.

**Co wyszło dopiero z rozpisania** (i czego nie było w żadnym z ośmiu pytań):

- **Odczyt gałęzi rozdzielił się na dwie drogi.** Plan przewidywał, że
  rozwinięcie podlega D46 (praca kawałkowa). Rozwinięcie **klawiszem** jednak
  jej nie potrzebuje: to jeden odczyt katalogu, czyli dokładnie tyle, co `Enter`
  w liście, a użytkownik właśnie o niego poprosił. Kawałkowania potrzebuje
  dopiero **odtwarzanie**: powrót do katalogu, w którym rozwiniętych było
  dziesięć gałęzi, chciałby dziesięciu odczytów w jednej klatce. Stąd reguła
  „najwyżej jedna gałąź na klatkę” — dziesięć klatek to jedna trzecia sekundy,
  w czasie której drzewo dosypuje się na oczach użytkownika.
- **Pamięć odczytanych gałęzi jest trwalsza od korzenia.** Wynika to z zakresu
  kroku („drzewo pokazuje to, co przeczytało”), ale skutek jest większy niż
  wykluczenie śledzenia zmian: wejście katalog niżej i powrót nie kosztuje ani
  jednego sięgnięcia na dysk. Czyszczona jest wyłącznie przy zmianie widoczności
  wpisów ukrytych, bo wtedy gałęzie mówią o czymś innym niż korzeń.
- **`BrowserTree::cursorDirectory()` okazał się szwem całego kroku.** Oddaje
  zwykły `Directory` z zaznaczeniem na węźle pod kursorem — i dzięki temu pas
  ścieżki, pas podglądu, kontekst sesji oraz `Enter` **nie wiedzą, że drzewo
  istnieje**. Bez tej jednej metody każde z tych czterech miejsc musiałoby
  rozgałęzić się na „lista albo drzewo”.
- **Korzeniem drzewa jest katalog panelu**, ten sam obiekt, który widzi lista.
  Skutki dwa: przełączenie widoku klawiszem nie kosztuje ani jednego odczytu,
  a zawężenie filtrem (krok 30) obowiązuje w drzewie na jego pierwszym poziomie.
  Drugi odczyt tego samego katalogu dałby panelowi dwie prawdy o jednym miejscu.

## Decyzje ze startu kroku 32 (2026-08-14)

### D69 — Rozstrzygnięcia startowe kroku 32: krok wchodzi z treścią, menu jest widokiem na rejestr, a klawiszem jest `F9`

**Dotyczy:** kroku 32 (pełna treść:
[32-menu-kontekstowe.md](archiwum/32-menu-kontekstowe.md)).

**Data:** 2026-08-14, przed pierwszą linią kodu — pięć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” plus trzy, które wynikły dopiero z odpowiedzi
nr 1 (klawisz otwierający, kształt zdolności co do liczby metod, treść wiersza).

**Punkt wyjścia — sprawdzenie zastrzeżenia ze szczytu planu.** Plan kroku 32
kazał **najpierw** sprawdzić w kodzie, czy menu ma z czego powstać, i odłożyć go,
jeśli okaże się, że potrzebuje własnej listy pozycji. Sprawdzenie dało wynik
jednoznaczny: rejestr trzymał osiem komend (`core.help`, `core.settings`,
`core.theme`, `core.language`, `core.dump`, `core.quit`, `core.fullscreen`
w torze okienkowym, `browser.jump`), z których **z zaznaczeniem związana była
jedna** — i to tylko dla katalogów. Moduł `FileInfo` wnosił **zero** komend, bo
opis wpisu otwierał się wyłącznie skrótem `Ctrl`+`D`. Menu zbudowane uczciwie na
takim rejestrze pokazałoby jedną pozycję na katalogu i **pustą listę na pliku**.

**Decyzje użytkownika:**

1. **Krok wchodzi teraz — ale z treścią, a nie z samym mechanizmem.** Odłożenie
   do kroku 41 (operacje na plikach) było rekomendacją odrzuconą świadomie; nie
   przeszedł też wariant „wąski”, czyli mechanizm na dzisiejszym rejestrze.
   Zamiast tego krok **dowozi komendy dla czynności, które aplikacja miała
   wyłącznie pod klawiszem**: `file-info.show` (`Ctrl`+`D`), `browser.open`
   (`Enter`), `browser.hidden` (`.`) i `browser.tree` (`Ctrl`+`T`). Rejestr
   rośnie z ośmiu pozycji do dwunastu, menu ma dwie pozycje na katalogu i jedną
   na pliku, a **żadna z tych nazw nie jest drugą implementacją** — obie drogi
   prowadzą do jednego miejsca w kodzie. Zysk uboczny, którego wariant wąski by
   nie dał: skrót `Ctrl`+litera działa dopóty, dopóki litery nie zajmie żaden
   moduł, a nazwa w rejestrze jest od tej kolizji niezależna.
2. **Osobne okno `MenuOverlay` w rdzeniu**, nie tryb `CommandOverlay`. Tamta
   klasa to 450 linii o pisaniu, uzupełnianiu i historii, a menu nie ma pola
   tekstowego, ma inne granice i inaczej traktuje literę — flaga trybu
   rozgałęziłaby `suggestions()`, `bounds()`, `handle()`, `complete()` i `run()`
   naraz. Wspólny zostaje **rejestr**, i to on, a nie klasa, jest tu miarą
   niepowtarzania: `Bootstrap` buduje oba okna w jednym miejscu, z jednego
   `CommandRegistry`.
3. **Zdolność „czego dotyczę” to osobny interfejs z dwiema metodami** —
   `AppliesToSelection::appliesTo(ModuleContext)` i `inputFor(ModuleContext)`.
   Wariant jednometodowy (`inputFor(): ?CommandInput`) był rekomendowany
   i odrzucony: rozdział pytań „czy dotyczę” od „czym się wywołuję” jest
   czytelniejszy i osobno testowalny, a cena — cztery jednolinijkowe
   `return new CommandInput()` — jest tymczasowa, bo pierwsza komenda
   z argumentem przyjdzie z krokiem 41. Metoda w `CommandInterface` odpadła
   z powodu zapisanego już w planie: wypełniałoby ją siedem klas rdzenia
   powtórzeniami odpowiedzi „nie dotyczę niczego”.
4. **Klawiszem jest `F9`, globalny** — pytanie, którego plan nie miał, bo
   wynikło dopiero z rozstrzygnięcia nr 2. W menedżerach plików menu wisi na
   `F9` od czasów Nortona i Midnight Commandera, a klawisz globalny znaczy tu
   coś konkretnego: menu czyta zaznaczenie z `LoopState::context()`, więc
   **moduł nie zmienia się ani o linię**. Wariant „klawisz ekranu przeglądarki”
   wymagałby podania modułowi rdzeniowego `CommandRegistry`, którego dziś nie
   widzi żaden.
5. **Prostokąt staje pośrodku**, jak `ConfirmOverlay`. Powód jest twardy, a nie
   estetyczny: rdzeń nie wie, gdzie moduł narysował zaznaczenie (lista czy
   drzewo, który z dwóch paneli), a pytanie ekranu o współrzędne kursora
   otworzyłoby `ScreenInterface` na współrzędne, których żaden kontrakt nie zna.
6. **Menu bez ani jednej pozycji nie otwiera się wcale** i mówi zdaniem
   w pasku stanu (`menu.empty`). Puste okno jest ślepą uliczką — trzeba je
   zamknąć, żeby dowiedzieć się, że nic w nim nie było; precedens z kroku 30:
   spis pokazuje wyłącznie to, co działa tu i teraz.
7. **Wiersz menu: nazwa komendy po lewej, opis po prawej** — układ identyczny
   z listą podpowiedzi okna komend, więc obie listy czyta się tak samo. Wariant
   „opis po lewej” był rekomendowany i odrzucony; wybrany uczy przy okazji nazw,
   czyli robi z menu widoczne dla użytkownika **drugie wejście do tego samego
   rejestru**, a nie osobny świat.

**Co wyszło dopiero z rozpisania** (i czego nie było w żadnym z ośmiu pytań):

- **Granica menu biegnie po zaznaczeniu, nie po module.** `browser.hidden`
  i `browser.tree` weszły do rejestru, ale zdolności `AppliesToSelection`
  **nie deklarują** i w menu ich nie ma: dotyczą panelu, a nie wpisu pod
  kursorem. Bez tej granicy menu stałoby się oknem komend bez pola tekstowego —
  czyli dokładnie tym, przed czym ostrzegał plan.
- **Czynność o dwóch wejściach musi mieć jedno miejsce w kodzie.** Przełączenie
  wpisów ukrytych to nie jest jedna linia: odczyt **obu** paneli idzie przed
  zapisem konfiguracji, bo nieudany odczyt rzuca wyjątek i ustawienie ma wtedy
  zostać takie, jakie było. Przepisanie tego do komendy byłoby przepisaniem
  pułapki, więc czynność wyprowadziła się z `BrowserScreen` do
  `Module/Browser/Presentation/HiddenEntries`, a ekran i komenda zamieniają jej
  wynik na własny typ skutku.
- **`useContext()` zamiast `Resettable`.** Stos okien woła `reset()` **wewnątrz**
  `open()`, więc menu resetowane tą drogą kasowałoby pozycje policzone chwilę
  wcześniej. Migawka kontekstu jest zarazem resetem — i jest to zarazem
  odpowiedź na pytanie, czego lista dotyczy: tego, na czym stał kursor
  w chwili naciśnięcia klawisza.
- **`BrowserPanes::focusedDirectory()` okazał się szwem tego kroku**, tak jak
  `cursorDirectory()` była szwem kroku 31. Komenda działająca na zaznaczeniu
  musi widzieć dokładnie to, co widzi kontekst sesji — czyli w widoku drzewa
  węzeł pod kursorem, a nie zaznaczenie listy. Dwa rachunki tej samej rzeczy
  rozjechałyby się przy pierwszym widoku, który dojdzie po drzewie; przy okazji
  zniknął trzeci rachunek, który stał w `BrowserScreen::pointed()`.
- **Litera przeskakująca do pozycji nie powstała** — jedyne odstępstwo od
  zakresu planu i świadome. Powód nie jest w koszcie, tylko w słowniku wiązań:
  `KeyBinding` umie wyrazić klawisz i konkretny znak, ale **nie „dowolną
  literę”**, więc funkcja nie miałaby jak trafić do stopki ani do okna pomocy —
  a przy dwóch pozycjach nie ma czego przeskakiwać. Wraca do rozważenia, gdy
  krok 41 wydłuży listę.

## Decyzje ze startu kroku 36 (2026-08-14)

### D70 — Rozstrzygnięcia startowe kroku 36: dźwięk wchodzi jako **moduł**, gra komendą i nie ma autostartu

**Dotyczy:** kroku 36 (pełna treść:
[36-odtwarzanie-muzyki.md](archiwum/36-odtwarzanie-muzyki.md)).

**Data:** 2026-08-14, przed pierwszą linią kodu — pięć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” plus trzy, które wynikły dopiero z tego, co
przyniósł sam start: pytanie o postać ścieżki utworu, **odwrócenie całego kroku
na moduł** i pytanie o cykl życia.

**Co rozstrzygnął pomiar, a nie wybór.** Trzy rzeczy z sekcji „Stan zastany”
miały status „do potwierdzenia” i potwierdziły się w kwadrans:

- **`GL\Audio\Engine` nie potrzebuje okna** — startuje bez `glfwInit()` i bez
  kontekstu OpenGL. Muzyka gra więc we **wszystkich trzech torach**, także
  terminalowych, a zależność od Fazy IX nie stwardniała (pytanie nr 1 planu
  odpadło samo).
- **MIDI nie wchodzi.** Plik `.mid`, który użytkownik położył w `assets/audio/`,
  miniaudio odrzuca: czyta WAV, MP3 i FLAC. Ten sam użytkownik dołożył wersję MP3
  i to ona jest utworem domyślnym; syntezator riffu z sekcji 2 planu **jest przez
  to niepotrzebny w całości** — a wraz z nim cała najbogaciej pokryta testami
  część planowanego kroku.
- **`Sound::stop()` jest pauzą, nie przewinięciem** — kursor zostaje. Stąd jedna
  komenda-przełącznik zamiast pary „graj” i „zatrzymaj”: para obiecywałaby
  rozróżnienie, którego pod spodem nie ma.

**Decyzje użytkownika:**

1. **Utwór wskazuje klucz ustawień z wartością domyślną w repozytorium** —
   `assets/audio/Deep Purple - Smoke On The Water.mp3`, ścieżka **względna wobec
   korzenia projektu**, bo bezwzględna zapisałaby się do konfiguracji przy
   pierwszej zmianie i przestała działać po przeniesieniu katalogu. Wariant
   „klucz pusty domyślnie” (repozytorium bez cudzego nagrania) został odrzucony
   świadomie, przy nazwanej wprost cenie: 5,3 MB nagrania w historii gita.
2. **Głośność 50%, zapętlenie włączone** — połowa, bo menadżer plików uruchamiany
   w cudzym terminalu nie powinien zacząć od pełnej mocy; zapętlenie, bo pięć
   i pół minuty kończy się ciszą w środku pracy. Autostart wybrany w tym samym
   pytaniu jako włączony **odpadł później**, wraz z rozstrzygnięciem nr 5.
3. **Bez rozszerzenia `glfw`: sama degradacja z komunikatem.** Pusty obiekt
   (`SilentAudioService`) zamiast odtwarzacza zewnętrznego czy FFI — ta sama
   zasada, którą tor okienkowy traktuje brak rozszerzenia od kroku 34: możliwość,
   nie wymóg. Odtwarzacz zewnętrzny odpadł także dlatego, że z listy preferencji
   planu maszyna projektu ma **wyłącznie `ffplay`**.
4. **Krok w całości jest modułem `Audio`, a nie rozbudową rdzenia** — i to jest
   rozstrzygnięcie największe, bo odwraca **plan całego kroku**. Zapadło
   w odpowiedzi na pytanie, którego plan nie mógł przewidzieć: ścieżka utworu
   byłaby **pierwszym kluczem rdzenia z wartością tekstową**, a ekran ustawień
   umie edytować tekst wyłącznie w pozycjach modułu (`ModuleSettingKind::Text`,
   krok 20). Zamiast dokładać rdzeniowi trzecią drogę edycji, dźwięk poszedł tam,
   gdzie wedle reguły 15 powinien był iść od początku: **nowa funkcja to moduł**.
   Skutki, których plan nie przewidywał: port i obie usługi leżą w `src/Module/Audio/`,
   a nie w rdzeniu; komendy nazywają się `audio.music` i `audio.volume`, bo
   przedrostka pilnuje rejestr; zakładka ustawień powstaje **za darmo**, razem
   z pozycją tekstową; rdzeń kosztuje **jedną linię** w `Bootstrapie`.
5. **Cyklu życia w kontrakcie modułu nie ma i nie przybywa — więc nie ma
   autostartu.** Rozważana zdolność `RunsWithApplication` (`start()`/`stop()`
   wołane przez `Bootstrap`) została odrzucona: kontrakt modułu nie zyskał ani
   jednej metody od kroku 20 i nie zyskuje jej dla wygody jednego modułu. Muzykę
   uruchamia komenda `audio.music`. Cena jest nazwana wprost: polecenie z D55
   („domyślnie i na początek”) **nie jest w tej części spełnione**, a pozycja
   ustawień „autostart” nie powstała, bo nie miałaby czego włączyć. Sprzątanie
   zostaje przy `register_shutdown_function` w usłudze modułu — czyli przy
   drugiej z dwóch dróg D47; pierwszej (jawnej, z `Bootstrap::shutdown()`) ten
   moduł mieć nie może, bo rdzeń go nie zna.
6. **Nazewnictwo:** port `AudioPort`, katalog usług po **roli**, nie po
   bibliotece, komendy jako przełącznik i wartość — wzorem `core.fullscreen`
   i `core.theme`. Po rozstrzygnięciu nr 4 „katalog po roli” znaczy po prostu
   `src/Module/Audio/`, a przedrostek komend zmienił się z `core.` na `audio.`.

**Co wyszło dopiero z rozpisania:**

- **Pierwsze w projekcie punktowe wyciszenie analizy statycznej.** `GL\Audio\Sound`
  w zainstalowanym rozszerzeniu (2.2.0) wystawia `isPlaying()`, `getCursor()`
  i `seekTo()`, ale **stuby `phpgl/ide-stubs` są starsze** i tych metod nie znają.
  Wyjściem alternatywnym było liczenie stanu „gra / nie gra” po swojemu — i to
  właśnie ono byłoby obejściem analizy kosztem zachowania: utwór kończy się sam,
  więc własna flaga kłamałaby przy wyłączonym zapętleniu. Uwaga zniknie, gdy
  stuby dogonią rozszerzenie; krok 34 obszedł ich dwie błędne stałe literałami
  i jest to ta sama klasa problemu.
- **Głośność musi być liczbą z listy przystanków, a nie dowolną z zakresu.**
  Wymusza to kontrakt ustawień modułu: `ModuleSetting::valueFrom()` sprowadza
  wartość spoza listy do domyślnej, więc zapisane 63 przepadłoby przy pierwszym
  odczycie pliku. Stąd `audio.volume` przyjmuje wielokrotności dziesięciu,
  a wartość spoza listy **nie zamyka okna komend** — jak nazwa motywu spoza listy.
- **Zapis głośności komendą musiał dostać własny przypadek użycia w module.**
  Rdzeniowy `ChangeModuleSettingUseCase::set()` zapisuje **napis** (bo służy
  pozycji tekstowej), a liczba zapisana napisem wróciłaby z pliku jako wartość
  nieodpowiedniego typu. Strzałki na zakładce idą dalej tamtą drogą (`shift()`),
  bo one przesuwają się po liście, a nie wpisują wartość.
- **Głośność ma dwa wejścia o różnym zasięgu w czasie** — i to jest cena braku
  cyklu życia wypowiedziana do końca: komenda działa **natychmiast**, także
  w trakcie grania, a pozycja na zakładce obowiązuje **od następnego uruchomienia
  utworu**, bo moduł bez ekranu nie dostaje od rdzenia ani jednego wywołania na
  klatkę i nie ma jak zauważyć zmiany. Zdanie o tym stoi w zakładce pomocy
  modułu, a nie tylko w dokumentacji.
- **Plik MIDI został w `assets/audio/` i aplikacja go nie odtworzy.** Wskazany
  w ustawieniach kończy się komunikatem, który wymienia formaty — to jedyne
  sensowne zachowanie, skoro silnik czyta próbki, a nie zapis nutowy.

## Decyzje z planowania Fazy XV (2026-08-14)

### D71 — Rozbudowa modułu dźwięku wchodzi jako Faza XV: dwa kroki, dwa mechanizmy rdzenia, kontrakt modułu odwraca D70

**Dotyczy:** kroków 45 ([45-ekran-audio-i-playlista.md](archiwum/45-ekran-audio-i-playlista.md))
i 46 ([46-efekty-specjalne.md](archiwum/46-efekty-specjalne.md)).

**Data:** 2026-08-14, tego samego dnia co ukończenie kroku 36 — na polecenie
użytkownika, który poprosił o okno modułu z dwoma panelami (efekty specjalne po
lewej, playlista po prawej), o wyniesienie wyboru utworu z ustawień do playlisty
i o ustawienia odtwarzania playlisty oraz efektów.

**Co rozstrzygnęło rozpoznanie w kodzie, zanim padło pierwsze pytanie.** Trzy
fakty, wszystkie sprawdzone przed rozpisaniem i wszystkie przesądzające
o kształcie fazy:

- **Mechanizmu zdarzeń nie ma w ogóle.** `src/Domain/Event/` jest **katalogiem
  pustym od kroku 01** — nazwę zarezerwowano przy zakładaniu struktury i nigdy
  nie wypełniono; w całym `src/` nie ma ani jednego `dispatch()`. Efekty
  specjalne nie mają więc skąd wiedzieć, że cokolwiek się stało.
- **Moduł nie dostaje wywołania spoza swojego ekranu.** Bez ekranu — nie dostaje
  go wcale (D70), z ekranem — wyłącznie wtedy, gdy ekran jest na wierzchu.
  Playlista nie ma jak zauważyć, że utwór się skończył. `NeedsTime` (krok 23) tu
  nie pomaga: rdzeń pyta o czas ekran i okno nakładane, czyli **to, co widać**.
- **Ustawienia modułu trzymają wyłącznie skalary** (`bool|int|string`), więc
  playlista i mapa hooków nie zmieszczą się w konfiguracji. Nośnikiem będzie
  własny plik modułu, wzorem `~/.light-manager/history` (krok 19).

Ponadto, dla kroku 46: **silnik miksuje kilka dźwięków naraz** — dwa obiekty
`Sound` z tego samego `Engine` grają równocześnie (sprawdzone 2026-08-14). Efekt
zagra **na** muzyce, a nie zamiast niej, więc krok nie potrzebuje ani kolejki,
ani ściszania.

**Decyzje użytkownika:**

1. **Faza z dwóch kroków, nie jeden krok.** Zakres dzieli się wzdłuż **dwóch
   niezależnych mechanizmów rdzenia**: takt dla playlisty (krok 45) i nazwane
   zdarzenia dla efektów (krok 46). Jeden mechanizm na krok — rytm z D48 i D66.
   Wariant „wszystko naraz” odpadł, bo dałby krok większy niż 21 i 35; wariant
   trzykrokowy — bo krok dowożący playlistę, która nie przechodzi sama dalej,
   byłby rzeczą świadomie niedokończoną.
2. **Ustawienia zostają w zakładce modułu**, a nie w osobnym ekranie
   konfiguracji. Aplikacja ma **jedną** drogę do ustawień (`F2`) i mechanizm
   z kroku 20 wystarcza na wszystko, czego ta faza potrzebuje: tryb odtwarzania
   jako pozycja wyboru, głośność, przełącznik efektów. Osobny ekran znaczyłby
   drugą drogę do ustawień oraz pierwszy moduł wnoszący **więcej niż jeden
   ekran**, czyli zmianę kontraktu (`ProvidesScreen` oddaje jeden).
3. **Kontrakt modułu zyskuje takt i zdarzenia — D70 zostaje odwrócone.**
   Różnica wobec tamtego rozstrzygnięcia jest jedna i musi być zapisana, bo bez
   niej wygląda to na zmianę zdania: w kroku 36 zdolność miała **jednego
   użytkownika i wyłącznie dla wygody** (muzykę dało się uruchomić komendą,
   autostart był udogodnieniem), a tutaj **bez niej funkcja nie istnieje** —
   playlista, która nie wie, że utwór się skończył, nie jest playlistą, a efekt
   bez zdarzenia nie ma czego zagrać. Rdzeń dostaje przy tym mechanizm
   **ogólny, nie wiedzę o dźwięku**: każdy przyszły moduł pracujący poza swoim
   ekranem korzysta z tego samego.

**Co z tego wynika dla kroków — i czego plany pilnują:**

- **Takt ma trzy reguły od pierwszego dnia**: jest tani (żadnego
  wejścia-wyjścia), niczego nie wymusza (nie prosi o przerysowanie) i nie rzuca
  (wyjątek modułu nie przerywa pętli). Jego koszt rozlicza oś `--loop` „przed
  i po” — to jedyne narzędzie mierzące coś wołanego trzydzieści razy na sekundę,
  a przy okazji **odpowiedź na pytanie odłożone w kroku 36**, czy dźwięk
  naprawdę nie wchodzi do ścieżki klatki.
- **Słownik zdarzeń jest zamknięty**, wzorem słownika prymitywów (reguła 11k):
  rozszerzenie wymaga zgody użytkownika. Kryterium doboru: wchodzi zdarzenie,
  które **rdzeń już zna z nazwy** (komunikat z tonem, otwarcie okna, start
  i koniec pracy, uruchomienie komendy), a nie takie, które trzeba by najpierw
  wymyślić. Każde `publish()` w rdzeniu musi dać się obronić **bez słowa
  „muzyka”**.
- **Okno audio rośnie w dwóch krokach**: w 45 ma jeden panel (playlista), w 46
  dostaje podział i lewy panel z efektami. Panel bez treści byłby obietnicą bez
  pokrycia, a `Split` doklejony później kosztuje tyle, co w kroku 24.
- **Autostart wraca jako możliwość.** Wykluczono go w kroku 36 dlatego, że nie
  było kogo obudzić; po kroku 45 będzie — więc decyzja „czy muzyka rusza sama”
  wraca na stół przy tamtym kroku i kosztuje jedną pozycję ustawień.
- **Klucz `track` znika, ale jego wartość nie ginie**: zasila playlistę przy
  pierwszym uruchomieniu po zmianie. Ustawienie, które użytkownik świadomie
  ustawił, nie ma prawa zniknąć przy przenosinach mechanizmu.

## Decyzje wykonawcze kroku 39 (2026-08-14)

### D72 — Rozstrzygnięcia startowe kroku 39: sprawdzenie powłoką, jedno źródło jakości w Composerze, budowa jako PHAR i twarda reguła procesu

**Dotyczy:** kroku 39 (pełna treść: [39-makefile.md](archiwum/39-makefile.md)),
`Makefile`, `bin/build-phar`, [docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md), `CLAUDE.md`
i `README.md`.

**Data:** 2026-08-14, przed pierwszą linią kodu — jedenaście pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” plus trzy, które wynikły dopiero z wyboru
PHAR-a i których plan przewidzieć nie mógł.

**Pytanie nr 5 odpadło przed zadaniem.** Krok 38 wykonał się pierwszy i granica
między przebiegami a testami jednostkowymi już istnieje: `phpunit.xml.dist` ma
testsuites `unit` i `functional`, a przebiegi leżą w `tests/Functional/`. Krok 39
**dowiązuje do nich cele**, zamiast rozstrzygać podział — dokładnie tak, jak
zakładała zalecana kolejność faz w [00-index.md](00-index.md).

**Decyzje użytkownika:**

1. **`check-env` blokuje na wymogach twardych, o SIXEL-u tylko ostrzega.**
   Kodem ≠ 0 kończy się brak PHP `^8.3`, `ext-imagick`, `ext-pcntl` albo `stty`
   (i brak Composera 2.x przy `install`/`build`). Brak kodera `SIXEL` jest
   **ostrzeżeniem przy kodzie 0**, bo aplikacja po nim działa — schodzi do trybu
   tekstowego (krok 07); `glfw`, `intl` i `xterm` są informacją. Odpowiedź DA1
   zostaje niesprawdzalna i cel mówi to wprost, odsyłając do `bin/terminal-probe`,
   zamiast udawać, że sprawdził.
2. **Sprawdzenie mieszka w samym Makefile, powłoką, po angielsku.** Bez
   `bin/check-env`: `command -v` i `php -r` w recepturze celu. Angielski jak
   jedyny napis poza katalogiem w [bin/light-manager:16](../../bin/light-manager#L16)
   — cel działa **przed** `composer install`, więc ani tłumacza, ani czym go
   wczytać, jeszcze nie ma.
3. **Jedno źródło prawdy dla jakości zostaje w `composer.json`.** Cele `cs`,
   `cs-check`, `stan`, `test` wołają skrypty Composera, a nie `vendor/bin/*`
   wprost. Makefile jest cienką warstwą i **nie powtarza konfiguracji własnymi
   słowami** — to jest reguła z sekcji „Cel” zastosowana do samego siebie.
4. **Bramek jest dwie i różnią się jedną rzeczą.** `make qa` (cs-check → stan →
   test) **przerywa na pierwszym błędzie**, bo literówka w stylu nie ma czekać na
   pełny przebieg testów; `make qa-full` przechodzi całość i kończy zbiorczym
   podsumowaniem z kodem wyjścia. Obie są recepturami sekwencyjnymi — kolejność
   bramki nie może zależeć od tego, czy ktoś dopisał `-j`.
5. **Cele pomiarowe odpalają pomiar od razu, wypisując ostrzeżenie.** Bariery
   `CONFIRM=1` nie ma; regułę o zwolnieniu mocy hosta niesie tekst celu i
   dokumenty. Tory dostają własne cele (`bench`, `bench-window`, `bench-text`,
   `bench-loop`, `bench-xterm`), bo spis `make help` ma pokazywać, że tory są
   cztery, a nie jeden z argumentem. Zakaz z kroku 16 zostaje: **żaden cel
   jakości nie zależy od pomiaru**.
6. **Budowa to PHAR, budowany własnym `bin/build-phar` na klasie `Phar`.**
   Builder z Composera (`humbug/box`) odpadł: to byłaby jedyna nowa zależność
   deweloperska w projekcie, a archiwum składa się z czterech katalogów i stuba.
   Wersję niesie **`composer.json`** — pole `version` dopisane przy tym
   rozstrzygnięciu, `0.1.0` — więc wynik nazywa się `light-manager-0.1.0.phar`
   i budowa nie wymyśla numeru, którego projekt nie prowadzi. `phar.readonly`
   jest w środowisku `On`, więc cel woła `php -d phar.readonly=0`.
7. **`assets/` leżą obok archiwum, a `src/` zostaje nietknięte.** Silnik
   `GL\Audio` to rozszerzenie C i pliku spod `phar://` nie przeczyta, a
   `GlAudioService::resolved()` dokleja ścieżkę względną do korzenia projektu —
   czyli w PHAR-ze **do wnętrza archiwum**. Rozważana poprawka w tym jednym
   miejscu została odrzucona: granica kroku „`src/` bez zmian” jest ważniejsza
   niż wygoda jednej pozycji ustawień. W wersji zbudowanej utwór wskazuje się
   **ścieżką bezwzględną** w ustawieniach modułu — ta droga działa dziś i bez
   żadnej zmiany (`resolved()` zostawia bezwzględne nietknięte). Budowa kładzie
   `assets/` obok PHAR-a i wypisuje tę ścieżkę na końcu; README to opisuje.
8. **Cele uruchomieniowe opakowują istniejące skrypty, nie powtarzają ich.**
   `run`, `run-window`, `run-xterm`, `probe`, `probe-xterm` wołają
   `bin/light-manager`, `bin/run.sh`, `bin/terminal-probe`
   i `bin/run-terminal-probe.sh`. Zasoby XTerma zostają w jednym miejscu — w
   skryptach — bo trzecia kopia listy `disallowedWindowOps` rozjechałaby się
   z dwiema poprzednimi.
9. **Obejście SIGSEGV Composera dostaje własny cel `install-safe`.** Ścieżka do
   `conf.d` bez `imagick` przychodzi zmienną (`COMPOSER_INI_SCAN_DIR`), bo jest
   specyficzna dla maszyny; `make install` zostaje czystym `composer install`.
   Obejście jest odtąd widoczne w `make help`, a nie tylko w prozie README.
10. **`make coverage` wchodzi, z czytelną odmową.** Cel sprawdza obecność Xdebuga
    albo PCOV-u i przy braku mówi, czego zainstalować, zamiast wywalić się
    komunikatem PHPUnita; raport HTML idzie do `build/coverage/`. To jedyne
    rozszerzenie zestawu narzędzi w tym kroku — Psalm, Rector i Infection
    zostają poza zakresem, bo dla nich nie ma nawet pytania.
11. **Reguła procesu wchodzi twardo obiema połowami, z zapisaną granicą.**
    W rejestrze `CLAUDE.md` („nie odstępuj bez jawnej zgody”) stoi i wejście
    przez `make`, i pierwszeństwo narzędzi repozytorium. Granica jest częścią
    reguły, nie przypisem: **zawężenie przebiegu wolno wołać wprost** (pojedynczy
    test filtrem PHPUnita, jedna oś `bin/render-bench`, `composer` przy pracy nad
    zależnościami); zakazane jest **dorabianie równoległej drogi** do procesu,
    który wejście już ma.
12. **Spis „proces → wejście” dostaje własny rozdział** w `docs/architecture.md`,
    przed „Co dalej”. Wiersz „Skróty uruchomieniowe: `composer test`…” w rozdz. 4
    **zostaje, ale zmienia rolę**: po rozstrzygnięciu nr 3 opisuje prawdę — to są
    polecenia, które woła cel `make`, a definicja mieszka w `composer.json`.

**Co wyszło dopiero z rozpisania:**

- **Wersja w `composer.json` powstała w trakcie odpowiedzi, nie przed nią.**
  Pytanie o źródło wersji zostało zadane przy założeniu, że go nie ma; użytkownik
  dopisał pole `version` i odpowiedział wskazaniem na nie. To jedyna zmiana
  w `composer.json` poza tą, której krok nie zrobił wcale (skrypty zostają, pkt 3).
- **PHAR nie może po prostu wciągnąć `bin/light-manager`.** Plik zaczyna się
  wierszem `#!/usr/bin/env php`, który przy `require` z wnętrza archiwum
  wypisałby się na STDOUT **przed pierwszą sekwencją sterującą** — czyli zepsuł
  klatkę. Budowa usuwa więc shebang przy wkładaniu pliku do archiwum i jest to
  jedyna transformacja treści w całej budowie; stub robi `Phar::mapPhar()`
  i `require`, a logiki startowej nie powtarza.
- **Katalogi napisów czytają się spod `phar://` bez żadnej zmiany.** `Catalog`
  robi `is_file()` i `require`, a `TranslatorService::directory()` liczy ścieżkę
  z `dirname(__DIR__, 3)` — jedno i drugie działa na strumieniu `phar://`.
  Sprawdzone uruchomieniem zbudowanego archiwum, nie założone.
- **Narzędzia repozytorium do archiwum nie wchodzą i wejść nie mogą.** PHAR niesie
  z `bin/` **wyłącznie `light-manager`**. `bin/render-bench` liczy ścieżki do
  `docs/pomiary/` i `tests/Golden/` (`BaselineStore`, `GoldenFrames`) — katalogów,
  których dystrybucja nie ma; `bin/install-desktop-entry` potrzebuje `realpath()`
  pliku wykonywalnego, a spod `phar://` go nie dostanie. Pomiar i wpis pulpitu są
  częścią repozytorium, nie aplikacji.

### D73 — Dwie usterki znalezione przez cele `make`: zasób OpenGL ginie przed kontekstem, a sprzątanie w testach przestaje być do zapamiętania

**Dotyczy:** `src/Infrastructure/Glfw/GlfwWindowService.php`,
`VgContextService.php`, `GlfwException.php` oraz `tests/Support/PinsLanguage.php`.
Rozliczenie kroku 39 ([39-makefile.md](archiwum/39-makefile.md), dziennik, pkt 6
i 7).

**Data:** 2026-08-14, po ukończeniu kroku 39, na polecenie użytkownika.

**Skąd się wzięły.** Obie usterki są **starsze od kroku 39** i obie ujawniły się
dopiero przez niego — nie dlatego, że coś zepsuł, tylko dlatego, że wejście przez
`make` zaczęło czytać kod wyjścia i pokazało katalog tymczasowy z boku.

**1. `bin/render-bench --window` kończył się SIGSEGV (kod 139)** już po
wypisaniu tabeli. Rozpoznanie przez rozbicie na najmniejsze przypadki: samo okno
zamyka się czysto (kod 0), okno **z kontekstem `VGContext`** kończy się
naruszeniem ochrony pamięci, a to samo z kontekstem zwolnionym **przed**
zamknięciem — znowu czysto. Przyczyna: obiekty rozszerzenia zwalniają zasoby GL
w destruktorach, a te wołają się przy sprzątaniu procesu, czyli **po**
`glfwTerminate()`. Kontekstu wtedy nie ma i zwolnienie trafia w pustkę.

**Rozwiązanie: kolejność zamiast nadziei.** `GlfwWindowService` dostał
`releaseBeforeClose(Closure $release)`, a `close()` wykonuje zamówienia
w odwrotnej kolejności, zanim zniszczy okno i zakończy GLFW. Zamawiającym jest
**twórca zasobu** — `VgContextService` robi to w tej samej linii konstruktora,
w której już wcześniej wymuszał kolejność powstawania (`GlfwWindowService::getInstance()`).
Pole `$vg` przestało być `readonly`, a `context()` po zwolnieniu **rzuca**
(`GlfwException::forReleasedContext()`), zamiast oddać `null`: poprawny przebieg
do tego stanu nie dochodzi, więc lepiej, żeby powiedział to głośno.

Rodzaju awarii nie przybyło — `forReleasedContext()` używa istniejącego
`GlfwProblem::WindowFailure`, bo z punktu widzenia użytkownika to jedno zdarzenie
(okna nie ma), a stan nieosiągalny nie zasługuje na własne zdanie w katalogu
napisów.

**Odrzucone alternatywy.** *Zamaskowanie kodu wyjścia w celu `make`* — cel
ukrywający naruszenie ochrony pamięci jest gorszy od celu, który się przewraca.
*Zwolnienie w obu miejscach wołających `close()`* (`Bootstrap::shutdown()`
i `BenchmarkCli::closeWindow()`) — to jest dokładnie wzorzec „dwa miejsca muszą
pamiętać”, przed którym broni D47; przy wyjściu awaryjnym, gdzie sprząta funkcja
zamknięcia procesu, nie zadziałałoby w ogóle. *`hasInstance()` w `AbstractSingleton`*
— rozszerzanie rdzeniowego wzorca Singletona po to, żeby jedna usługa mogła
zapytać o drugą, przy istniejącej i jawnej zależności między nimi.

**Zasięg jest szerszy niż narzędzie pomiarowe:** tę samą sekwencję wykonuje
aplikacja w trybie okienkowym, więc `./bin/light-manager --window` również kończył
się SIGSEGV po `F10` — niewidocznie, bo kodu wyjścia programu interaktywnego nikt
nie czyta. Po poprawce oba kończą się zerem, wraz ze scenariuszem `thumbnail`,
który jako jedyny tworzy tekstury.

**2. `tests/Support/PinsLanguage` zostawiał katalog w `/tmp`** po każdym
przebiegu testów. Cofnięcie (`unpinLanguage()`) istniało od początku i sześć klas
wołało je w `tearDown()`, ale siódma (`AudioServicesTest`, krok 36) o tym
zapomniała — i tak zostawało po jednym pustym katalogu na przebieg.

**Rozwiązanie: sprzątanie przestaje być do zapamiętania.** `unpinLanguage()`
niesie odtąd atrybut `#[After]`, więc PHPUnit woła je sam po każdym teście.
Kolejność sprawdzono uruchomieniem, a nie z pamięci: `tearDown()` biegnie
**przed** metodami `#[After]`, więc sprzątanie klasy zdąży skorzystać
z podmienionego katalogu domowego. Jawne wywołania z sześciu klas **zniknęły** —
jedna czynność ma mieć jedno wejście. Przy okazji `pinLanguage()` stało się
odporne na powtórzenie w jednym teście: kasuje poprzedni katalog i zapamiętuje
wyłącznie **zastane** zmienne środowiskowe (wcześniej drugie przypięcie
zapamiętałoby wartości ustawione przez pierwsze i cofnięcie przywróciłoby
katalog tymczasowy zamiast prawdziwego `HOME`).

**Czego nie zrobiono:** testu pilnującego reguły. Sprzątanie jest teraz w jednym
miejscu i woła je PHPUnit, więc nie ma czego zapominać; test sprawdzający, że
klasy „pamiętają”, pilnowałby mechanizmu, którego już nie ma.

### D74 — Rozstrzygnięcia startowe kroku 40: ognisko jako zadeklarowana dana, etykieta miejsca, drugi krótki klucz opisu i pasek rosnący z potrzeby

**Dotyczy:** kroku 40 (pełna treść:
[40-stopka-kontekstowa.md](archiwum/40-stopka-kontekstowa.md)),
`Presentation/Ui` (`DeclaresFocus`, `FocusHint`, `StatusHints`, `Hint`,
`KeyBinding`, `StatusBar`, `HudLayout`), `Presentation/Cli` (`FrameComposer`,
`InputHandler`, `Bootstrap`, `SettingsScreen`), obu modułów z ekranem, czterech
katalogów napisów, [docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-14, przed pierwszą linią kodu — osiem pytań z sekcji „Do
rozstrzygnięcia na starcie kroku”.

**Sprawdzenie stanu zastanego poprawiło jedną liczbę w planie i to ona zaważyła
na dwóch odpowiedziach.** Plan mówił o „czterech wiązaniach rdzenia”; w kodzie
jest ich **pięć** (`F9` dołożył krok 32), a w torze okienkowym sześć. Przy
dzisiejszych opisach to 78 kolumn samych podpowiedzi rdzenia — w oknie stu kolumn
z jakimkolwiek komunikatem stopka znikała **już przed tym krokiem**, w całości.

**Decyzje użytkownika:**

1. **Kontrakt ogniska: osobny interfejs zdolności wraz z daną.**
   `DeclaresFocus::focus(): ?FocusHint`, a `FocusHint` niesie klucz etykiety
   miejsca i listę wiązań. Nie `FocusableInterface`, bo prawdziwi właściciele
   ogniska (`BrowserPanes`, `SettingsCursor`, `SplitState`) komponentami **nie
   są** i musieliby dorobić `draw()` wyłącznie po to, żeby pasek stanu miał się
   kogo spytać. `ScreenInterface` zostaje nietknięty — to ta sama reguła, którą
   krok 24 zapisał dla `DrawsOwnFrame`.
2. **Stopka nazywa miejsce.** Przed wiązaniami stoi etykieta („Podgląd”, „Panel
   lewy”), bezwarunkowo; ustępuje razem z ostatnim wiązaniem miejsca, bo jest
   doklejona do **pierwszej** pozycji, a ustępowanie idzie od końca.
3. **Ogranicza sam budżet kolumn** — bez twardego limitu liczby pozycji.
4. **Powtórzenie to zgodność zestawu klawiszy _i_ klucza opisu.** Ostrożniejszy
   z dwóch wariantów; przy dzisiejszym kodzie trafia dokładnie tam, gdzie trzeba,
   bo ekran składa `bindings()` z wiązań miejsca **plus** własnych — powtórzeniem
   jest ten sam obiekt, a nie dwa podobne. Dzięki temu `↑↓ zmiana zaznaczenia`
   i `↑↓ przewijanie linijki` zostają w stopce **obiema** pozycjami.
5. **Drugi, krótki klucz opisu** (`<klucz>.short`, brak znaczy „użyj długiego”).
   Okno pomocy i wszystkie jego wzorce zostają **nietknięte**; cena to ~40 pozycji
   w czterech katalogach.
6. **Pasek rośnie z potrzeby, a wiersz bierze lista.** Warunki łącznie: pełny spis
   nie mieści się w wierszu dzielonym z komunikatem **i** okno jest wyższe od
   progu `ROWS_FOR_PREVIEW + 2`. Pas podglądu nie oddaje ani jednego wiersza.
7. **Wszystkie zarejestrowane skróty modułów** wchodzą do stopki, w grupie
   globalnej (więc ustępują pierwsze), a opisem jest **nazwa modułu**. Do okna
   pomocy nie idą — tam mają własną zakładkę i drugi wpis byłby powtórzeniem.
8. **`F1` ustępuje ostatni** — przypięty, znika dopiero wtedy, gdy nie mieści się
   sam jeden.

**Rozstrzygnięcie, którego pytania nie przewidziały, a które wynikło z odpowiedzi
nr 6:** `HudLayout` dostaje **pierwszą w projekcie odpowiedź zależną od treści**,
a nie od rozmiaru okna. Kolejność w `FrameComposer` jest przez to wymuszona:
wiązania → czy mieszczą się w jednym wierszu → podział okna. Pętli w rachunku
nie ma, bo szerokość treści strefy jest ta sama w obu wariantach oprawy — fakt
wygląda na drobiazg, więc dostał własną metodę z nazwą
(`HudLayout::contentColumns()`), zamiast zostać w dwóch odejmowaniach.

**Odrzucone alternatywy.** *Metoda w `ScreenInterface`* — kontrakt ekranu rósłby
po raz trzeci po krokach 21 i 24, a ekran bez ogniska nie ma czego deklarować.
*Skracanie mechaniczne opisów* — „powrót do listy plików” → „powrót” wychodzi
dobrze, ale „zwiń lub rozwiń sekcję” → „zwiń” kłamie w połowie. *Skrócenie samych
opisów w katalogu* — jedno źródło, ale zmienia **także okno pomocy**, gdzie pełne
zdanie czyta się lepiej, i wszystkie jego wzorce. *Twardy limit liczby pozycji* —
druga reguła robiąca to, co budżet kolumn, z liczbą wziętą nie z pomiaru.
*Ustępowanie przez ucięcie* — podpowiedź ucięta do `moduł.file-in…` nie jest
podpowiedzią.

**Skutek uboczny, wykryty przy okazji:** klucz `settings.hints`
(`↑↓ ruch · ←→ zmiana · Esc powrót`) leżał w obu katalogach rdzenia od czasów
sprzed kroku 18 i **nie miał ani jednego użytkownika w kodzie**. Był ostatnim
napisem-ściągawką w katalogu — czyli dokładnie tym, czego zakazuje kryterium
„żadne wiązanie nie pochodzi z napisu”. Usunięty.

## Decyzje ze startu kroku 41 (2026-08-14)

### D75 — Rozstrzygnięcia startowe kroku 41: port rzucający, usuwanie rekurencyjne jako praca kawałkowa, okno posuwające pracę i dwa okna rdzenia

**Dotyczy:** kroku 41 (pełna treść:
[41-operacje-fundament.md](archiwum/41-operacje-fundament.md)), `Application/Port`
(`FileOperationsPort`), `Application/Dto` (`RemovalState`, `RemovalStage`,
`WorkProgress`), `Domain/Exception` (`FileOperationException`),
`Infrastructure/FileSystem` (`FileOperationsService`), `Presentation/Ui`
(`RunsWork`, `OverlayOutcome`, `PromptOverlay`, `ProgressOverlay`),
`Presentation/Cli` (`GameLoop`, `InputHandler`), modułu przeglądarki, czterech
katalogów napisów, [docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-14, przed pierwszą linią kodu — dziewięć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku”, cztery pytania wynikłe z odpowiedzi i jedno
z granicy warstw, której plan nie zauważył.

**Sprawdzenie stanu zastanego potwierdziło tabelę planu w każdym wierszu i dodało
do niej jedną rzecz, która przesądziła o kształcie połowy kroku:**
`InputHandler` łapie `DomainException` **wyłącznie w drodze przez ekran**
(`toScreen()`); droga przez okno nakładane (`toOverlay()`) nie łapie nic. Skoro
zmiana nazwy i usunięcie wykonują się w domknięciu okna (D56), niepowodzenie
musi być złapane po stronie modułu, a nie zostawione rdzeniowi.

**Decyzje użytkownika (1–9 — pytania z planu):**

1. **Jeden port z trzema czynnościami, niepowodzenie rzucane.**
   `FileOperationsPort` w `Application/Port`, a niepowodzenie to
   `Domain\Exception\FileOperationException` — hierarchia `DomainException`
   z deklaracją `DescribesProblem`. To **nie jest** złamanie reguły 8 („wyjątek
   infrastruktury nie przekracza granicy portu”), bo wyjątek jest domenowy:
   dokładnie wzorzec `FilesystemDirectoryRepository`, który z warstwy
   `Infrastructure` rzuca `DirectoryNotReadableException` z warstwy `Domain`.
   `ProblemPresenter` zostaje nietknięty.
2. **Poprawną nazwę zna moduł**, nie rdzeń. Sprawdzenie pada przed wywołaniem
   portu; port bierze ścieżkę bezwzględną i nazwę jako napisy i ufa wołającemu.
   Rdzeń nie dostaje przez to ani jednego nowego pojęcia z dziedziny plików.
3. **Klawisze `F6` (nazwa), `F7` (nowy katalog), `F8` (usunięcie) oraz `Delete`**
   jako druga droga do usunięcia — wzorem klasycznych menadżerów co do klawisza.
   `F5` zostaje wolne dla kopiowania z kroku 42, `F3`/`F4` na przyszłość.
4. **Katalog niepusty usuwa się rekurencyjnie, a pytanie podaje liczbę wpisów.**
   Odrzucono odmowę (menadżer plików, który odmawia usunięcia katalogu, jest
   niepełny) i wariant bez liczby.
5. **Dwie komendy z argumentem, menu bez nowych pozycji** — i to jest jedyne
   rozstrzygnięcie, które **odwraca zapis planu**, bo plan zakładał rzecz
   niewykonalną w dzisiejszym rdzeniu. Powód jest granicą warstw z D39:
   `CommandOutcome` leży w `Application` i wskazuje ekran **identyfikatorem**, bo
   `ScreenInterface` leży w `Presentation`; okna nakładane rejestru
   identyfikatorów nie mają w ogóle. Żadna komenda nie umie więc otworzyć okna,
   a bez okna nie ma ani pytania przed usunięciem, ani pola na nazwę. Powstają
   dlatego `browser.rename <nazwa>` i `browser.mkdir <nazwa>` — **pierwsze komendy
   z argumentem w projekcie**, czyli dokładnie to, czego `AppliesToSelection`
   spodziewał się od kroku 32 — a `browser.delete` nie powstaje wcale, bo usuwać
   bez pytania nie wolno. Zobowiązanie z indeksu planu („czynność ma zadeklarować
   `AppliesToSelection`, a wtedy pojawi się w menu bez zmiany w rdzeniu”)
   **zostaje długiem** i jest zapisane w dzienniku kroku. Odrzucono trzeci
   wariant: okna nakładane pod identyfikatorem wraz z rejestrem wytwórni —
   trzeci nowy mechanizm rdzenia w jednym kroku, i to drugi sposób budowania
   tych samych okien.
6. **Odświeżenie przez ponowny odczyt katalogu**, wzorem `HiddenEntries::flip()`.
   Jedno źródło prawdy o zawartości; koszt jednego `scandir` **na operację**, nie
   na klatkę. Odrzucono zmianę wpisu w agregacie.
7. **Kursor idzie za nazwą**: po zmianie nazwy staje na nazwie nowej, na nowym
   katalogu staje od razu (po to się go tworzy), a po usunięciu zostaje **na tym
   samym numerze**, czyli na wpisie następnym; po usunięciu ostatniego cofa się
   o jeden, a w katalogu, który został pusty, znika.
8. **Okno nazwy stoi w rdzeniu** — `Presentation/Ui/Overlay/PromptOverlay`,
   `Dialog` plus `TextInput`, wynik domknięciem (D56). Kroki 42 i 44 poproszą
   o to samo okno.
9. **Nazwa zajęta to odmowa z własnym zdaniem.** Pytanie o nadpisanie należy do
   kroku 42, razem z nazwą zastępczą.

**Decyzje użytkownika (10–13 — wynikłe z odpowiedzi nr 4, bo plan ich nie
przewidział):** rozstrzygnięcie „rekurencyjnie, z liczbą” postawiło w kroku 41
pracę **dłuższą od klatki**, którą plan rezerwował dla kroku 42. Użytkownik
rozstrzygnął jej kształt wprost:

10. **Droga usuwania ma trzy okna: liczenie → pytanie z liczbą → usuwanie.**
    Okno liczenia pokazuje **samą nazwę** wpisu dokładanego do listy; okno
    usuwania — nazwę, licznik „N z M” i pasek postępu. Plik, dowiązanie i pusty
    katalog idą krótszą drogą: samo pytanie, bez okien pracy.
11. **Okno nakładane zyskuje takt i prawo ustąpienia miejsca.**
    `Presentation\Ui\RunsWork` deklarowana osobno (jak `NeedsTime`,
    `DrawsOwnFrame`, `DeclaresFocus`), pytana **raz na takt w `GameLoop`** — czyli
    w fazie „aktualizuj stan”, nie w rysowaniu, bo praca zmieniająca dysk nie ma
    prawa dziać się w środku składania klatki. `OverlayOutcome` zyskuje wskazanie
    **następnego okna** (`replace()`), wzorem `ScreenOutcome::opens()`: praca
    skończona musi otworzyć pytanie, a stos ma jedno piętro.
12. **Okno postępu stoi w rdzeniu**, obok `ConfirmOverlay`
    (`Presentation/Ui/Overlay/ProgressOverlay`). O plikach nie wie nic — dostaje
    klucz tytułu, domknięcia i **ogólną** daną `Application/Dto/WorkProgress`
    (wiersz treści, wykonane, całość). Kroki 42 i 44 biorą to samo okno.
13. **`Esc` przerywa usuwanie i mówi, ile usunięto.** Praca zatrzymuje się na
    najbliższym kawałku, pasek stanu podaje „usunięto N z M”, lista się odświeża.
    Cel kroku („operacja dzieje się w całości albo wcale”) dostaje przez to
    **zapisany wyjątek dla usuwania rekurencyjnego** — zgodny z D66, gdzie praca
    przerwana jest pracą zakończoną. Odrzucono okno bez wyjścia i drugie pytanie
    nad oknem postępu (stos okien ma jedno piętro).

**Rozstrzygnięcie wykonawcze, którego pytania nie przewidziały, a które wynika
z odpowiedzi nr 10:** okno pracy otwiera się **dopiero wtedy, gdy praca nie
zmieściła się w pierwszym kawałku**. Reguła bierze się wprost z tamtej
odpowiedzi — „plik i pusty katalog bez okien pracy, bo okno mignąwszy na klatkę
czyta się jak usterka” — i rozciąga ją na jedyny przypadek, którego z góry
rozpoznać nie da się: katalog o trzech wpisach. Dzięki niej ścieżka kodu zostaje
**jedna** (zawsze praca kawałkowa), a okien nie ma tam, gdzie nie mają czego
pokazać.

**Cena zapisana od razu:** reguła 15 przestaje być bezwyjątkowa (D66,
rozstrzygnięcie 2), a granica wyjątku wchodzi do `SKILL.md` wraz z powodem —
rdzeń zna **ścieżkę bezwzględną jako napis, czynność i stan pracy**, i nic
ponad to; `Entry`, `Directory`, `DirectoryPath` i `EntryType` nie mają prawa
pojawić się w sygnaturze niczego w `src/Application` ani `src/Domain`, czego
pilnuje `CoreKnowsNothingAboutFilesTest`.

## Decyzje po kroku 41 (2026-08-14)

### D76 — Pas podglądu znika z przeglądarki: miniaturę pokazuje moduł opisu pliku i tylko on

**Dotyczy:** `Module/Browser/Presentation/BrowserScreen` (strefa podglądu),
`BrowserModule` (port podglądu obrazów), `Presentation/Cli/Bootstrap`, usuniętych
`Module/Browser/Presentation/Component/PreviewBox` i
`Module/Browser/Application/UseCase/PreviewSelectedEntryUseCase`, `README.md`,
[docs/architecture.md](../architecture.md) i [docs/pomiary/README.md](../pomiary/README.md).

**Data:** 2026-08-14, na żądanie użytkownika, po kroku 41.

**Decyzja:** przeglądarka plików **nie ma pasa podglądu**. `preview()` oddaje
`null`, więc cztery wiersze pasa dostaje lista plików (reguła „strefa, której nie
ma, oddaje wiersze środkowi” — krok 21). Kod, który istniał wyłącznie dla tego
pasa, został **usunięty**, a nie wyłączony: `PreviewBox`, `PreviewSelectedEntryUseCase`
i jego test, a wraz z nimi zależność modułu przeglądarki od `ImagePreviewPort`.
Przeglądarka bierze odtąd z rdzenia trzy rzeczy zamiast czterech.

**Powód:** to samo pokazuje od kroku 25 moduł `FileInfo` (`Ctrl`+`D`) — i pokazuje
**lepiej**: miniaturę w całym prawym panelu, obok rozmiaru, praw dostępu, sumy
kontrolnej i podglądu treści tekstowej, a nie w czterech wierszach nad paskiem
stanu. Dwa miejsca robiące to samo znaczyły dwa razy ten sam odczyt obrazu
w klatce i dwie ścieżki do poprawiania przy każdej zmianie podglądu.

**Co to odwraca.** Pas podglądu wprowadził krok **12** i był wtedy jedną z dwóch
rzeczy, po których poznawało się tę aplikację; krok **13** wpisał go w drabinkę
stref, a krok **21** przeniósł jego zamawianie z rdzenia do ekranu przeglądarki
wraz ze zdaniem „pas jest zamawiany **zawsze**, także wtedy, gdy zaznaczony wpis
nie jest obrazem, bo znikanie pasa przesuwałoby listę pod ręką użytkownika”. To
zdanie zostaje **odwołane w całości** — nie dlatego, że było nieprawdziwe, ale
dlatego, że pas nie ma już czego pokazywać: podgląd wyprowadził się do modułu,
który powstał dwa lata planu później.

**Skutek uboczny, wart zapisania, bo jest długiem:** pasa podglądu **nie zamawia
odtąd ani jeden ekran** — przeglądarka oddała go modułowi, a `FileInfoScreen`
rysuje miniaturę w swoim własnym panelu i strefy skrajnej nigdy nie zamawiał.
Mechanizm rdzenia (trzecia strefa w `ScreenInterface`, próg `ROWS_FOR_PREVIEW`
w `HudLayout`, `previewIsPanel()`, scenariusz pomiarowy `thumbnail`) **zostaje bez
użytkownika w aplikacji**, co jest sprzeczne z regułą 13 („nic bez prawdziwego
odbiorcy”). Zostawiony świadomie i z dwóch powodów: `null` jest poprawną
odpowiedzią kontraktu od kroku 21, więc nic nie jest zepsute, a usunięcie trzeciej
strefy z rdzenia dotknęłoby `ScreenInterface`, `HudLayout`, `FrameComposer`,
`ScenarioFactory`, wszystkich wzorców pomiarowych i złotych klatek — czyli jest
osobną decyzją, nie skutkiem tej. **Do rozstrzygnięcia przy następnym module,
który zechce strefę skrajną**: albo znajdzie się dla niej odbiorca, albo trzecia
strefa wychodzi z kontraktu.

**Testy:** 1505 zielonych (przed zmianą 1525; ubyło 20 — cały test usuniętego
przypadku użycia i dwie asercje o strefie). Trzy testy zmieniły zdanie na
przeciwne i to jest właściwa forma zapisu tej decyzji w kodzie:
`BrowserModuleTest::testScreenOrdersTheHeaderButNoPreviewStrip`,
`ScreenZonesTest::testNoScreenOrdersThePreviewStripAnyMore`
i `GameLoopTest::testTheListTakesTheRowsOfTheAbsentPreviewStrip` — ten ostatni
sprawdza teraz, że panel listy jest **wyższy niż zostawiłby układ z pasem**, czyli
że lista dostała te wiersze naprawdę.

**Sprawdzone w prawdziwym terminalu** (`make run-xterm`, okno 100×30): panele
sięgają paska stanu, lista pokazuje **20 wierszy zamiast 13**, pustego prostokąta
po pasie nie ma. Miniatura działa tam, gdzie ma działać — `Ctrl`+`D` na pliku PNG
pokazuje ją w prawym panelu opisu wraz z wymiarami i formatem.

### D77 — Trzy długi bez właściciela wchodzą do planu jako Faza XVI z krokiem 47

**Dotyczy:** kroku 47 (pełna treść: [47-splata-dlugow.md](archiwum/47-splata-dlugow.md)),
a przez niego: `Application/Command/CommandOutcome`,
`Presentation/Cli/InputHandler`, `Presentation/Ui/OverlayInterface`,
`Presentation/Ui/ScreenInterface`, `Presentation/Ui/HudLayout`,
`Presentation/Cli/Screen/SettingsScreen`,
`Infrastructure/Diagnostics/ScenarioFactory` oraz wzorców pomiarowych i złotych
klatek w trzech torach.

**Data:** 2026-08-14, na żądanie użytkownika, zaraz po zamknięciu kroku 41.

**Decyzja:** długi, które **nie mają właściciela w żadnym zaplanowanym kroku**,
dostają własny krok planu, a nie kolejną adnotację „do zrobienia przy okazji”.
Krok zbiera trzy:

1. **Komenda nie umie otworzyć okna** — zobowiązanie kroku 41 wobec kroku 32
   (D75, rozstrzygnięcie 5). Skutek w aplikacji: `browser.delete` nie istnieje,
   a menu `F9` nie ma ani jednej operacji na plikach.
2. **Trzecia strefa została bez odbiorcy** — skutek uboczny D76. `preview()`
   oddaje `null` we **wszystkich** pięciu ekranach, a strefę zamawia już tylko
   scenariusz pomiarowy: narzędzie mierzy kawałek klatki, którego aplikacja nie
   rysuje. Reguła 13 jest złamana w rdzeniu.
3. **Zakładka ustawień dłuższa od okna gubi pozycje** — zauważone w kroku 29,
   od tamtej pory w „Zakresie poza MVP”. `VStack` nie rysuje szczeliny, która nie
   dostała wiersza, więc pozycja znika bez śladu; przy jedenastu pozycjach
   zakładki `file-info` dzieje się to już w oknie o 22 wierszach.

**Kolejność: krok wykonuje się przed krokiem 42, choć ma wyższy numer.** Jest to
pierwszy i jedyny krok planu, którego numer nie mówi nic o kolejności — numer
bierze się z chronologii planowania, a kolejność z rachunku: kroki 42 i 43
dokładają czynności, z których każda zechce pozycji w menu i okna wywołanego
komendą. Dług spłacony przed nimi obsłuży je za darmo; spłacony po nich znaczy
trzykrotną przeróbkę tego samego miejsca i trzykrotne przeliczenie wzorców.

**Uzasadnienie osobnego kroku, a nie poprawek przy okazji:** „przy okazji” już
było i właśnie dlatego długi są trzy. Każdy z nich jest **niezapłaconą ceną
decyzji podjętej świadomie**, a nie usterką do cichej naprawy — więc każdy wymaga
rozstrzygnięcia użytkownika, a rozstrzygnięcia startowe ma krok planu, nie
poprawka w przelocie. D76 wprost odesłał swój dług „do rozstrzygnięcia przy
następnym module, który zechce strefę skrajną”, a takiego modułu w planie nie ma.

**Co do kroku świadomie nie weszło:** długi **z właścicielem**. Szerokość okna
liczona z długości napisu (`ConfirmOverlay` przy bardzo długiej nazwie) należy do
kroku 42, bo to on dowiezie pierwszego prawdziwego odbiorcę; autostart muzyki do
kroku 45 (Faza XV znosi warunek, który go blokował); skala treści HiDPI czeka na
sprzęt, nie na krok. Sprawdzono przy tym dług „okno edycji wartości tekstowej”
z kroku 14 — **jest spłacony inaczej, niż zakładano**: pozycja tekstowa działa
w miejscu przez `TextInput` (krok 20, P13), więc okna nie potrzebuje i do kroku
nie wchodzi.

**Odrzucone alternatywy:**

- **Dopisać każdy dług do kroku, który go dotyka** (A do 42, B do 45, C do
  któregokolwiek) — odrzucone, bo dwa z trzech nie mają takiego kroku wcale,
  a dług A doklejony do kroku 42 zniknąłby w kroku o nieodwracalnym ryzyku
  utraty danych.
- **Zrobić je bez kroku planu, jako poprawki** — odrzucone: każdy wymaga
  rozstrzygnięcia (czy strefa wychodzi z kontraktu, jak komenda zamawia okno),
  a rozstrzygnięcia zapadają na starcie kroku i lądują w tym dzienniku.
- **Wstawić krok jako 42 z przenumerowaniem 42–46 na 43–47** — odrzucone przez
  użytkownika: numeracja oddawałaby kolejność, ale kosztem poprawek w indeksie,
  dzienniku i sześciu plikach kroków. Rozjazd numeru z kolejnością jest tańszy,
  o ile jest **zapisany** — i dlatego stoi w opisie Fazy XVI, w grafie zależności
  i tutaj.

**Model:** Opus / xhigh, z zastrzeżeniem: **Fable / xhigh**, jeśli
rozstrzygnięcie startowe nr 4 wyprowadzi trzecią strefę z `ScreenInterface` —
wtedy krok rusza kontrakt ekranu, układ, kompozytor klatki i wszystkie trzy
renderery naraz. Model wybiera się **po** tej odpowiedzi, wzorem kroku 44.

### D78 — Rozstrzygnięcia startowe kroku 47: zdolność zamiast rejestru, strefa wychodzi z kontraktu, granica menu przerysowana

**Dotyczy:** kroku 47 ([47-splata-dlugow.md](archiwum/47-splata-dlugow.md)),
`Presentation/Ui/Command/OpensOverlay` (nowe), `Presentation/Ui/ScreenInterface`,
`Presentation/Ui/HudLayout`, `Presentation/Cli/FrameComposer`,
`Presentation/Cli/Screen/SettingsScreen`, `Infrastructure/Diagnostics/ScenarioFactory`,
`Module/Browser/Presentation/Command/*` oraz wzorców pomiarowych i złotych klatek
w trzech torach.

**Data:** 2026-08-14, rozstrzygnięcia użytkownika na starcie kroku.

**Rozstrzygnięć jest dziewięć.** Trzy z nich zmieniają to, co plan kroku
zakładał, a jedno przerysowuje granicę postawioną w D69.

**1. Komenda zamawia okno zdolnością, nie identyfikatorem.** Powstaje
`Presentation\Ui\Command\OpensOverlay` — interfejs o jednej metodzie, deklarowany
**osobno**, jak `AppliesToSelection`, `RunsWork`, `NeedsTime` i `DrawsOwnFrame`.
`MenuOverlay` i `CommandOverlay` pytają `instanceof` i biorą okno **obiektem**.

Sprawdzenie przed rozstrzygnięciem pokazało rzecz, która odwraca opis długu
z planu kroku: **granica warstw nigdy nie była przeszkodą**. Wszystkie komendy
w projekcie — rdzenia i modułów — leżą w `Presentation`
(`Presentation/Cli/Command`, `Module/*/Presentation/Command`); w `Application`
leży sam **kontrakt**. Obie strony rozmowy widzą więc `OverlayInterface`
legalnie, a rozdzielał je wyłącznie `CommandOutcome` stojący pośrodku. Miejsce
zdolności bierze się z precedensu kontraktu modułu (D38, P2): dane i rejestr
w `Application`, **zdolności wymieniające typy z `Presentation/Ui` — w
`Presentation/Ui`**.

Odrzucono: **rejestr wytwórni pod identyfikatorem** (dwa nowe pojęcia —
identyfikator okna i wytwórnia — po to, żeby przejść granicę, której nie trzeba
przechodzić), **okno opisane danymi w `CommandOutcome`** (`Application` zyskałby
słownik okien rosnący przy każdym nowym oknie, a łańcuch okien usuwania i tak by
się w nim nie zmieścił) oraz **brakujący argument proszący o siebie** (nie
dokłada pojęć, ale pytania przed usunięciem nie obsłuży, bo pytanie nie jest
argumentem).

**2. Do menu `F9` wchodzą wszystkie trzy czynności kroku 41 — i to przerysowuje
granicę z D69.** Tamta decyzja mówiła: „granica biegnie po **zaznaczeniu**, nie
po module — `browser.hidden` i `browser.tree` są w rejestrze, ale w menu ich nie
ma”. `browser.mkdir` zaznaczenia nie dotyczy: działa na katalogu panelu, który
`ModuleContext` niesie osobnym polem (`path`). Wpuszczenie go **tym samym
argumentem wpuściłoby także tamte dwie**, więc granica dostaje nowe brzmienie,
a nie wyjątek:

> Menu pokazuje czynności zmieniające **zawartość miejsca**, w którym stoi
> zaznaczenie. Nie pokazuje czynności zmieniających **sposób oglądania** tego
> miejsca.

Przy tej granicy `browser.mkdir` jest w menu (tworzy wpis tam, gdzie stoi
kursor), a `browser.hidden` i `browser.tree` zostają poza nim (zmieniają widok,
nie dysk) — czyli wynik D69 zostaje nietknięty, a uzasadnienie przestaje zależeć
od tego, czy czynność czyta zaznaczenie. Nazwa `AppliesToSelection` opisuje odtąd
**migawkę kontekstu**, a nie sam zaznaczony wpis; zmiany nazwy interfejsu ten
krok nie robi, bo kosztowałaby cztery klasy i nic nie wyjaśniła.

**3. `browser.delete` przyjmuje opcjonalny argument z nazwą.** Bez argumentu
bierze wpis pod kursorem (jak `F8`), z argumentem — wskazany, po sprawdzeniu, że
istnieje w katalogu panelu. Spójne z `browser.rename <nazwa>`
i `browser.mkdir <nazwa>` z kroku 41.

**4. Trzecia strefa wychodzi z kontraktu.** `ScreenInterface::preview()` znika,
`HudLayout` traci strefę i próg, `FrameComposer` płaszczyznę, `ScenarioFactory`
przestaje ją zamawiać. Reguła 13 przestaje być złamana w rdzeniu, a rdzeń robi
się o pojęcie mniejszy. Odrzucono zostawienie mechanizmu jako zadeklarowanego
wyjątku (dług zostałby długiem, tylko udokumentowanym) oraz dopisanie odbiorcy
w kroku 45 (znaczyłoby dowiezienie funkcji w kroku, który miał nie dokładać
żadnej).

**Cena, znana z góry:** przeliczenie wszystkich wzorców pomiarowych i złotych
klatek w trzech torach — i **model kroku zmienia się na `Fable / xhigh`**,
dokładnie tak, jak przewidywał przypis przy tabeli Fazy XVI.

**5. Próg dwuwierszowego paska stanu przesuwa się o wysokość zniesionej strefy:
28 → 20.** Argument kroku 40 zostaje **dosłownie ten sam** — „wiersz dokładany
stopce zabiera się liście, a przy progu lista właśnie oddała osiem wierszy” —
tylko liczony bez składnika, którego już nie ma: strefa brała osiem wierszy, więc
bez niej lista ma przy 20 tyle samo, co miała przy 28. Odrzucono warunek liczony
z `ROWS_FOR_LIST_PANEL` (uzasadnienie stałoby samo, ale zmieniałby zachowanie
w oknach, które wzorce już opisują) i pozostawienie 28 (pilnowałoby zapasu,
którego nikt już nie zużywa).

**6. Scenariusz `thumbnail` przenosi się do panelu modułu `FileInfo`.** Miniaturę
rysuje `PreviewPane` tym samym komponentem `ImageBox`, więc pomiar mierzy odtąd
obraz tam, gdzie aplikacja go naprawdę rysuje. Odrzucono sztuczną strefę
w narzędziu (mierzyłaby układ, który przestał istnieć) i skasowanie scenariusza
(koszt dekodowania obrazu przestałby być mierzony w którymkolwiek z trzech
torów — a jest to jedyny scenariusz, który go mierzy).

**7. Pasek zakładek zostaje nieruchomy, przycisk „przywróć domyślne” przewija się
z pozycjami** jako ostatnia z nich. Położenie pamięta się **osobno dla każdej
zakładki** (`ScrollWindow::useContext()`, wzorem `SectionState` z kroku 22).
Odrzucono przycisk przyklejony na dole (zabierałby wiersz także zakładce
o czterech pozycjach) i przewijanie całości wraz z paskiem zakładek (użytkownik
traciłby jedyny wskaźnik, gdzie stoi).

**8. Przewijanie ustawień bierze sześć klawiszy:** strzałki, `PageUp`/`PageDown`
i `Home`/`End` — komplet, który `FileInfoScreen` ma już dziś. Sprawdzenie
poprawiło przy tym pytanie planu, które zakładało koszt: **słownik wejścia zna
wszystkie sześć** (`Application\Dto\Key`), więc trzy tory wejścia zostają
nietknięte.

**9. Kolejność potwierdzona: krok 47 wykonuje się przed krokiem 42** (D77),
mimo że użytkownik poprosił najpierw o rozpoczęcie tamtego. Sprawdzenie stanu
zastanego kroku 42 pokazało przy okazji, że **trzy założenia jego planu są
nieaktualne** — krok 41 dowiózł `RunsWork` (pracę posuwa okno, raz na takt
w `GameLoop`, nie ekran w `draw()`), gotowy i ogólny `ProgressOverlay` oraz pracę
kawałkową **wewnątrz** `FileOperationsPort`. Rozstrzygnięcie 1 tamtego kroku
(„kto posuwa pracę”) jest przez to rozstrzygnięte precedensem, punkt 7 („pasek
nad listą”) wchodzi w spór z regułą „jeden pasek, jedno miejsce”, a zdanie
„kopiowanie nie ma prawa dojść do portu operacji” trzeba rozstrzygnąć od nowa.
Poprawki w pliku kroku 42 należą do jego startu, nie do tego kroku.

## Decyzje ze startu kroku 42 (2026-08-15)

### D79 — Rozstrzygnięcia startowe kroku 42: osobny port pracy, okno postępu zamiast paska nad listą, etap liczenia i okno wyboru w rdzeniu

**Dotyczy:** kroku 42 (pełna treść:
[42-kopiowanie-i-przenoszenie.md](archiwum/42-kopiowanie-i-przenoszenie.md)),
`Application/Port` (`FileTransferPort`), `Application/Dto` (`TransferState`,
`TransferStage`, `TransferChoice`, `WorkProgress`), `Infrastructure/FileSystem`
(`FileTransferService`), `Presentation/Ui/Overlay` (`ChoiceOverlay`,
`ConfirmOverlay`, `ProgressOverlay`), modułu przeglądarki, czterech katalogów
napisów, [docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-15, przed pierwszą linią kodu — osiem pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” (dwa z nich rozstrzygnięte przez sprawdzenie,
nie przez wybór) oraz dwa wynikłe z odpowiedzi.

**Sprawdzenie stanu zastanego potwierdziło zapowiedź D78 (rozstrzygnięcie 9)
co do słowa: trzy założenia planu kroku są nieaktualne**, bo kroki 41 i 47
dowiozły to, czego plan spodziewał się dopiero tutaj.

1. **„Praca kawałkowa posuwa się w `draw()` ekranu”** — cała sekcja o trudności
   strukturalnej i rozstrzygnięcie nr 1. Krok 41 dowiózł `Presentation\Ui\RunsWork`:
   pracę posuwa **okno nakładane, raz na takt w `GameLoop`**, w fazie „aktualizuj
   stan”. Trzy warianty planu („pętla główna”, „ekran przeglądarki”, „ekran nie
   oddaje klawiszy”) opisują świat sprzed kroku 41; wygrał czwarty, którego plan
   nie znał, bo wtedy nie istniał.
2. **„Kopiowanie nie ma prawa dojść do portu operacji”** — `FileOperationsPort`
   prowadzi od kroku 41 własną pracę kawałkową (usunięcie drzewa), więc powód
   z planu („czynność natychmiastowa nie ma stanu, a kopiowanie ma go całe”)
   przestał odróżniać te dwa porty.
3. **„Pasek postępu nad listą panelu czynnego”** (punkt 7 zakresu) — istnieje
   gotowy i z założenia ogólny `ProgressOverlay`, którego docblock wprost mówi:
   „kopiowanie z kroku 42 i kosz z kroku 44 mają wziąć to samo okno, nie napisać
   drugiego”.

Reszta tabeli stanu zastanego zgadza się w każdym wierszu; poprawki wymagała
jedna liczba (scenariuszy jest osiemnaście, nie siedemnaście).

**Decyzje użytkownika (1–8 — pytania z planu):**

1. **Osobny `FileTransferPort` i osobna usługa**, mimo że pierwotny powód tego
   podziału się zdezaktualizował. Zostaje drugi, mocniejszy: stan kopiowania jest
   nieporównanie większy od stanu usuwania (lista źródeł, cel, otwarte uchwyty,
   pozycja w pliku, pamięć decyzji o kolizjach), a `FileOperationsService` ma już
   377 linii i własną pracę kawałkową. Odrzucono rozszerzenie
   `FileOperationsPort` — port urósłby z siedmiu do trzynastu metod i trzymał dwa
   różne stany pracy w jednej klasie.
   **Cena zapisana od razu:** granica wyjątku od reguły 15 (D66, D75) obejmuje
   odtąd **katalog `Infrastructure/FileSystem` jako całość**, a nie jedną klasę —
   zdanie „drugie i ostatnie miejsce rdzenia piszące po dysku” z docblocku usługi
   z kroku 41 traci ważność co do liczby, a zostaje co do zasady. Sama granica
   wiedzy jest nietknięta: port zna ścieżkę bezwzględną jako napis, czynność
   i stan pracy — i nic ponad to.
2. **Postęp pokazuje okno (`ProgressOverlay`), nie pasek nad listą.** Punkt 7
   zakresu zostaje **odwołany**. Zero nowych mechanizmów, reguła „jeden pasek,
   jedno miejsce” (krok 23) nietknięta, `Esc` przerywa tą samą drogą, co przy
   usuwaniu. Cena zapisana wprost, bo jest widoczna: **w trakcie kopiowania nie
   widać listy i nie da się nawigować**, bo okno nie oddaje klawiszy niżej
   (reguła 10). To rozstrzyga zarazem pytanie nr 8 planu („przerwanie przez zmianę
   katalogu”) — zmiana katalogu w trakcie pracy jest niemożliwa, więc pytanie
   znika, a nie doczekuje się odpowiedzi.
3. **Całość poznaje się etapem liczenia, przed pierwszym skopiowanym bajtem** —
   wzorem usuwania z kroku 41: liczenie (kawałkowe, z własnym oknem, jeśli nie
   zmieści się w pierwszym kawałku) → kopiowanie ze znanym mianownikiem
   w bajtach. Punkt 3 zakresu („leniwe chodzenie po drzewie”) zostaje przez to
   **odwołany co do kolejności, a nie co do kawałkowości**: chodzenie po drzewie
   nadal jest pracą kawałkową, tylko dzieje się w całości **przed** kopiowaniem,
   a nie na przemian z nim. Odrzucono rosnący mianownik (pasek potrafiłby się
   cofnąć, a kryterium ukończenia mówi „pasek mówi prawdę”) i tryb „postęp
   nieznany” (przy kopiowaniu płyty użytkownik nie wiedziałby, ile zostało —
   czyli dokładnie w przypadku, który jest miarą powodzenia kroku).
4. **Kolizja pyta nowym oknem wyboru w rdzeniu.** `Presentation\Ui\Overlay\ChoiceOverlay`
   — `Dialog` plus `ListView`, **bez nowego komponentu**, wzorem `MenuOverlay`
   z kroku 32. Sześć pozycji zamiast czterech z przełącznikiem: nadpisz / nadpisz
   wszystkie / pomiń / pomiń wszystkie / zmień nazwę / przerwij; „do wszystkich”
   wchodzi **teraz**, a nie z krokiem 43, bo lista źródeł istnieje od pierwszego
   dnia (punkt 2 zakresu). Odrzucono trzeci przycisk w `ConfirmOverlay` (okno
   mające oddawać „tak/nie” zaczęłoby oddawać trzy rzeczy) i pominięcie kolizji
   bez pytania (D75, rozstrzygnięcie 9, odesłał pytanie o nadpisanie właśnie
   tutaj).
5. **Cel zapisuje się wprost, a przerwanie usuwa niedokończony plik.** Odrzucono
   nazwę tymczasową z przemianowaniem na końcu. Cena, znana i przyjęta: zabicie
   procesu (`SIGKILL`, awaria zasilania) zostawia plik z właściwą nazwą i połową
   treści — `Esc` i `F10` sprzątają, `kill -9` nie ma jak.
6. **Dowiązanie symboliczne kopiuje się jako dowiązanie**, nigdy jako jego treść.
   Ta sama reguła, którą usługa z kroku 41 stosuje przy usuwaniu (`is_link()`
   sprawdzane **przed** `is_dir()`), i ten sam powód: dowiązanie do `/` w drzewie
   znaczyłoby przy podążaniu skopiowanie systemu. Pętli w drzewie nie trzeba przez
   to wykrywać wcale — chodzenie po drzewie w nie nie wchodzi.
7. **`F5` kopiowanie, `F6` przeniesienie, zmiana nazwy schodzi na `F4`.**
   Wierność klasykom w parze `F5`/`F6` okazała się warta przestawienia klawisza
   dowiezionego krok wcześniej. Odrzucono `F4` dla przeniesienia (para `F5`/`F4`
   nie znaczy nic w żadnym menadżerze) i jedno okno robiące obie rzeczy wzorem
   „RenMov” (jedno okno rozstrzygałoby o dwóch czynnościach, a krok 41 zbudował
   zmianę nazwy na `EntryName`, dla którego ukośnik jest błędem).
8. **Cel bierze się z okna ze ścieżką, wypełnionego katalogiem drugiego panelu.**
   `PromptOverlay` z kroku 41 — dokładnie ten trzeci użytkownik, którego D75
   (rozstrzygnięcie 8) obiecywał. `Enter` jest zarazem potwierdzeniem, więc
   osobnego okna pytania nie ma, a droga działa **także przy wyłączonym
   podziale**. Odrzucono cel brany z drugiego panelu bez pytania (przy wyłączonym
   podziale kopiowanie szłoby w miejsce niewidoczne na ekranie, a kosza nie ma do
   kroku 44).
   **Cena:** wpisana ścieżka nie jest nazwą, więc omija `EntryName` — sprawdzenie
   („istnieje, jest katalogiem, wolno w nim pisać”) należy do modułu, w domknięciu
   okna.

**Decyzje użytkownika (9–10 — wynikłe z odpowiedzi, bo plan ich nie
przewidział):**

9. **`WorkProgress` zyskuje gotowy napis licznika; pasek rośnie w bajtach.**
   Do dziś okno postępu składało licznik samo z pary liczb („3840 z 30001”) — to
   działa dla wpisów, a dla bajtów dałoby „12914688 z 734003200”. Dana rdzenia
   dostaje więc **czwarte, opcjonalne pole**: napis licznika **już złożony przez
   wołającego** („12,3 MB z 700 MB — plik 3 ze 120”). Pusty znaczy „złóż jak
   dotąd”, więc usuwanie z kroku 41 zostaje nietknięte, a okno przestaje wiedzieć,
   co liczy. Odrzucono formater rozmiaru w rdzeniu (rdzeń zyskałby pojęcie,
   którego nie ma, a moduły liczyłyby to samo dwiema drogami — dziś rozmiar
   zapisuje `Module\Browser\Presentation\Component\EntrySize`) i pasek liczony we
   wpisach (kopiowanie jednego pliku wielkości płyty pokazywałoby „0 z 1”
   i nieruchomy pasek przez cały czas pracy).
10. **Kawałek ma stały rozmiar, dobrany pomiarem** — jedna liczba wyprowadzona
    z budżetu klatki i sprawdzona trybem `--loop` (takt pętli w trakcie
    kopiowania kontra bez niego), wzorem `SCAN_PER_TICK` i `DELETE_PER_TICK`
    z kroku 41. Odrzucono kawałek dobierany do czasu poprzedniego kawałka:
    zachowanie zależne od zegara byłoby pierwszym takim mechanizmem w projekcie
    i test sprawdzałby regułę doboru zamiast skutku (D28 — zegar stoi po stronie
    narzędzia pomiarowego, nie produkcji).

**Rozstrzygnięcia wykonawcze, których pytania nie przewidziały, a które wynikają
z odpowiedzi:**

- **Pytanie o kolizję pada dla każdego wpisu, który miałby coś nadpisać —
  i wyłącznie dla takiego.** Katalog docelowy o tej samej nazwie **nie jest
  kolizją**, tylko scaleniem: utworzenie katalogu, który już jest, niczego nie
  niszczy. Kolizją jest plik pod istniejącą ścieżką i plik pod ścieżką zajętą
  przez katalog (i odwrotnie). „Nadpisz wszystkie” i „pomiń wszystkie” pamiętają
  odpowiedź do końca pracy — bez tego scalenie drzewa o dwudziestu kolizjach
  byłoby dwudziestoma pytaniami.
- **Przeniesienie w obrębie jednego systemu plików nie liczy niczego i nie
  kopiuje ani bajtu** — idzie `rename()`em, w całości, w klatce, w której się
  zaczęło. Rozpoznanie idzie przez **numer urządzenia** (`lstat()['dev']` źródła
  kontra `stat()['dev']` katalogu docelowego), a **nie** przez próbę `rename()`
  z odczytaniem błędu: PHP obsługuje `EXDEV` dla zwykłych plików sam, kopiując je
  wewnątrz wywołania — czyli dokładnie tak, jak ten krok kopiować nie może, bo
  zatrzymałoby to pętlę na czas całego pliku. Pułapka warta zapamiętania:
  **`rename()` w PHP nie zawsze jest operacją na metadanych.**
- **Źródło znika dopiero po potwierdzonym zapisaniu celu** i to jest reguła
  nieodwoływalna (punkt 4 zakresu): przy przenoszeniu między systemami plików
  usunięcie źródła jest ostatnią czynnością **każdego pliku z osobna**, a katalogi
  źródłowe znikają na końcu, w kolejności odwrotnej do odkrycia — tą samą regułą,
  którą krok 41 usuwa drzewo.
- **Prawa i czas zmiany ustawiają się na końcu pracy, nie przy tworzeniu.**
  Katalog o prawach `0555` musi być zapisywalny, dopóki wchodzi do niego
  zawartość, więc kopia dostaje prawa dopiero wtedy, gdy jest kompletna —
  osobnymi pozycjami na końcu tej samej listy pracy, w kolejności odwrotnej do
  odkrycia. Właściciela nie kopiujemy w ogóle (punkt 8 zakresu).
- **Kopiowanie w miejsce, z którego się kopiuje, jest niemożliwe i mówi
  dlaczego** — sprawdzeniem ścieżkowym w `begin()`, nie limitem: cel równy
  katalogowi źródła, cel wewnątrz kopiowanego katalogu i źródło równe celowi to
  trzy zdania w katalogu napisów, a nie trzy drogi do pętli nieskończonej.
- **Czynność mieszka w `Presentation` modułu, nie w jego `Application`.** Plan
  przewidywał `CopyEntriesUseCase` i `MoveEntriesUseCase`; powstaje zamiast nich
  `EntryTransfer` — wzorem `EntryOperations` z kroku 41 i z tego samego powodu:
  klasa składa łańcuch okien i zna `LoopState`, a to jest warstwa prezentacji
  (D41). Przypadek użycia, który niczego nie robi poza wywołaniem portu, byłby
  warstwą przepisującą argumenty.

**Dług spłacany przy okazji, bo krok dowozi mu pierwszego prawdziwego odbiorcę:**
`ConfirmOverlay` liczy szerokość z długości napisu i nie ma górnej granicy, którą
`PromptOverlay` dostał po obejrzeniu okna w prawdziwym terminalu (dziennik kroku
41). Pytania tego kroku niosą **nazwy plików**, więc granica wchodzi tu razem
z nimi — D77 wskazał krok 42 jako właściciela tego długu.

---

### D80 — Rozstrzygnięcia startowe kroku 43: kontekst rośnie o zbiór wbrew rekomendacji, znacznik plus rola, `Esc` zdejmuje warstwy po kolei

**Dotyczy:** kroku 43 (pełna treść:
[43-zaznaczenie-wielokrotne.md](archiwum/43-zaznaczenie-wielokrotne.md)),
`Application/Module` (`ModuleContext`), `Application/Port`
(`FileOperationsPort`), `Infrastructure/FileSystem` (`FileOperationsService`),
`Presentation/Ui/Overlay` (`ConfirmOverlay`), `Application/Ui` (`Role`),
`Infrastructure/Rendering` (`Theme` i trzy renderery), modułu przeglądarki
(`MarkedEntries`, `BrowserState`, `BrowserPanes`, `BrowserScreen`, `EntryList`,
`EntryOperations`, `EntryTransfer`), modułu opisu pliku (`FileInfoScreen`),
`Infrastructure/Diagnostics` (scenariusz `marked`), czterech katalogów napisów,
[docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-15, przed pierwszą linią kodu — osiem pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” (jedno z nich rozstrzygnięte przez sprawdzenie
kodu, nie przez wybór) oraz jedno wynikłe ze stanu zastanego, którego plan nie
przewidział.

**Sprawdzenie stanu zastanego: tabela z planu zgadza się w każdym wierszu, ale
jedno założenie kroku jest nieaktualne.** Pytanie nr 6 („liczba mnoga w napisach
— trzy formy w katalogu, czyli zmiana w rdzeniu, czy napis omijający odmianę”)
opierało się na zdaniu „katalog napisów tego dziś nie umie”. **Umie od kroku
15**: `TranslatorPort::plural()` i `PluralRule::Slavic` dają trzy formy dla
polskiego, a kroki 41 i 42 z nich korzystają (`module.browser.delete.done`).
Zostało wyłącznie **jedno miejsce bez drogi do form mnogich** — `ConfirmOverlay`,
który zawsze woła `translate()`. Zapowiadana „jedyna zmiana w rdzeniu” zeszła
przez to z przebudowy katalogu do **jednego opcjonalnego parametru okna**.

**Decyzje użytkownika (1–8 — pytania z planu; 9 — pytanie dodatkowe):**

1. **Kontekst sesji rośnie o zbiór — wbrew rekomendacji planu.** `ModuleContext`
   dostaje trzy liczby: `markedCount`, `markedBytes` i `markedDirectories`
   (o które suma milczy). Plan rekomendował wariant przeciwny, powołując się na
   regułę 15 („funkcja, której potrzebuje jeden moduł, jest modułem”); użytkownik
   wybrał wzrost, żeby pojęcie zbioru istniało w rdzeniu **raz**, a nie osobno
   w każdym module, który kiedyś zechce o nim mówić.
   **Warunek, pod którym ten wybór nie jest długiem, postawiono od razu i jest
   nim reguła 13:** mechanizm wchodzi **razem z odbiorcą**. Odbiorcą jest moduł
   opisu pliku — przy niepustym zbiorze jego górny pas mówi „Zaznaczono 12 wpisów
   · razem 4,1 GB” zamiast ścieżki. Punkt „Poza zakresem” planu („pokazywanie
   zaznaczenia w module opisu pliku”) zostaje przez to **odwołany**: był
   konsekwencją wariantu 1, który odpadł.
   Zbiór jedzie w kontekście **razem** z wpisem pod kursorem, a nie zamiast
   niego — odbiorca ma prawo pokazać jedno, drugie albo oba.
2. **Spacja przesuwa kursor w dół.** Klasyka menadżerów (mc, Far, Total
   Commander): zaznaczenie ciągu wpisów idzie jednym palcem. Na ostatnim wierszu
   kursor zostaje, bo `moveSelectionDown()` zatrzymuje się na krańcu listy.
3. **`Esc` zdejmuje warstwy po kolei: najpierw filtr, potem zaznaczenie.**
   Kolejność jest odwrotnością zakładania — filtr leży na wierzchu, bo zmienia
   to, co widać. Odrzucono kolejność odwrotną („zaznaczenie jest groźniejsze”)
   i czyszczenie obu naraz: jedno naciśnięcie odbierałoby użytkownikowi
   zawężenie, o które nie prosił. Przy obu pustych klawisz nie robi nic, jak
   przed krokiem. Opis w stopce mówi o warstwie, **która ustąpi teraz** — jeden
   opis dla obu byłby kłamstwem w połowie przypadków.
4. **Wpis zaznaczony i odfiltrowany nadal należy do zbioru.** Zbiór trzyma nazwy
   (reguła kroku 30), a filtr jest widokiem, nie własnością katalogu. Cena
   zapisana wprost: operacja dotknie czegoś, czego nie widać — dlatego
   podsumowanie w pasie ścieżki podaje liczbę **całego** zbioru i mianownik
   **pełnego** katalogu, a nie widocznej listy. Odrzucono czyszczenie zbioru przy
   zawężeniu (wpisanie fragmentu kasowałoby pracę zaznaczania) i wariant
   pośredni, w którym czynność bierze przecięcie zbioru z widokiem — dwie liczby
   znaczyłyby wtedy co innego i pytanie potwierdzenia musiałoby to tłumaczyć.
5. **Wiersz zaznaczony niesie dwa sygnały naraz: znacznik w kolumnie i rolę
   `Warning`.** Jeden sygnał mniej znaczyłby, że w torze tekstowym bez kolorów
   zaznaczenia nie widać wcale (sama rola) albo że zaznaczony katalog jest
   nieodróżnialny od niezaznaczonego (sam znacznik, bo `Accent` zostałby).
   Kolumna znacznika **powstaje dopiero przy niepustym zbiorze** — panel bez
   zaznaczenia wygląda co do znaku jak przed krokiem, czego dowodzą niezmienione
   wzorce pozostałych scenariuszy. Wiersz zaznaczony **i** pod kursorem zachowuje
   znacznik, a napis bierze rolę paska: bez znacznika byłby nieodróżnialny od
   zwykłego wiersza pod kursorem.
6. **Formy mnogie w katalogu — bez przebudowy rdzenia** (patrz sprawdzenie
   wyżej). `ConfirmOverlay` dostaje opcjonalny parametr `?int $count`; podany
   przełącza pytanie z `translate()` na `plural()`, a `null` (domyślny) zostawia
   zachowanie sprzed kroku. Tytuły okien **pracy** form mnogich nie dostają —
   ich zdania stawiają liczbę po dwukropku („Usuwanie zaznaczonych: 12”), więc
   nie odmieniają się przez nią; ta sama sztuczka trzyma podsumowanie w pasie
   ścieżki w kolumnach, na które je stać (`bez 2 kat.`).
7. **Katalog wolno zaznaczyć na równi z plikiem, a suma mówi, że go pomija.**
   Bez tego zaznaczenie przestawałoby być mnożnikiem najcięższych operacji —
   dwunastu katalogów nie skopiowałoby się jednym klawiszem. Rozmiar katalogu
   niesie w zbiorze `null`, a nie zero, i to jest różnica merytoryczna, nie
   techniczna: zajętość katalogu wraz z zawartością umie policzyć wyłącznie `du`
   z kroku 26, więc zero byłoby zmyśleniem.
8. **`*` odwraca zaznaczenie na liście widocznej.** Klawisz dotyczy tego, na co
   użytkownik patrzy — spójnie z regułą kroku 30 („spis pokazuje wyłącznie to, co
   działa tu i teraz”). Wpisy poza widokiem zostają w swoim stanie.
9. **Zaznaczenie jest własnością listy: drzewo ani nie zaznacza, ani zbioru nie
   widzi.** Pytanie dodatkowe, którego plan nie zadał, bo pisano go przed
   sprawdzeniem, że panel ma od kroku 31 **dwa widoki**. Precedens jest ten sam,
   który w kroku 31 zabrał drzewu podświetlenie filtra: zbiór trzyma nazwy
   z jednego katalogu, a węzły drzewa leżą na różnych poziomach — wspólny zbiór
   musiałby trzymać ścieżki, podsumowanie przestałoby dotyczyć jednego katalogu,
   a `TreeView` dostałby własny znacznik i własny wzorzec pomiarowy. Zbiór
   **przeżywa** przełączenie widoku (`Ctrl`+`T` niczego nie kasuje), ale dopóki
   widać drzewo, nie istnieje dla nikogo: ani dla znacznika, ani dla podsumowania,
   ani dla czynności. Inaczej `F8` w drzewie usuwałoby dwanaście wpisów, których
   nie widać.

**5a. Zaznaczenie dostaje własną rolę motywu — rozstrzygnięcie podjęte
w trakcie kroku, po obejrzeniu klatki.** Pierwsza wersja malowała wiersz
zaznaczony rolą `Warning` i przeszła wszystkie testy, bo testy patrzą na rolę,
a nie na kolor. Wzorzec PNG pokazał, czego nie widać w prymitywach: **w motywie
Grafit `warning` i `accent` to ten sam `#d9a441`** — jeden nasycony kolor jest
tam zasadą projektową (D25) — więc zaznaczony plik wyglądał w domyślnym motywie
jak katalog i z dwóch sygnałów rozstrzygnięcia 5 zostawał jeden.

Użytkownik wybrał **nową rolę** (`Role::Marked`, dwunastą, pierwszą dołożoną od
kroku 13) zamiast dwóch tańszych wyjść: zostawienia `Warning` wraz z zapisanym
ograniczeniem albo sięgnięcia po `Danger`, która znaczy dziś „nieodwracalne albo
błąd”. Kolorem jest **zieleń** we wszystkich czterech paletach — jedyny kolor,
którego projekt nie używał do niczego, więc niczemu nie odbiera znaczenia:
`#7fb069` (Grafit, przygaszona, żeby nie konkurowała z akcentem), `#a3be8c`
(Nordyk — kanoniczna zieleń Norda), `#4f7a2e` (Papier — ciemniejsza od tła,
jedyny motyw jasny), `#81c995` (Indygo). Cena zapisana wprost: **Grafit ma odtąd
dwa nasycone kolory**, a zdanie z jego docblocku o jednym traci ważność.

**Cena zmierzona, nie oszacowana, i warta zapisania osobno:** druga barwa
nasycona kosztuje w torze sixelowym **+6,4 ms kwantyzacji** na klatce, w której
zaznaczona jest blisko połowa wierszy (27,7 ms wobec 20,6 ms dla tej samej listy
bez zaznaczenia). Kwantyzator musi zmieścić w palecie drugą rampę półcieni —
czyli dokładnie to, przed czym ostrzegał D25. Koszt **rośnie z liczbą pikseli
nowej barwy**, a nie z samą jej obecnością: przy dwóch zaznaczonych wierszach
wynosi 1,0 ms. Sufit mieści się w budżecie klatki (27,7 z 33 ms, tyle co klatka
z miniaturą), a podniesienie palety go nie zdejmuje — przy 256 kolorach różnica
zostaje. Tory tekstowy i okienkowy nie płacą nic, bo nie kwantyzują.

**Wniosek ogólny, wart więcej niż sama decyzja:** rola dobrana „znaczeniowo”, bez
przejrzenia czterech palet, bywa **rolą bez koloru**. Testy prymitywów tego nie
złapią — złapało dopiero oko na wzorcu PNG, czyli narzędzie z kroku 38 użyte
zgodnie z przeznaczeniem. A rola dołożona do palety **nie jest darmowa w torze
sixelowym**, choć w dwóch pozostałych jest: następna niech przyjdzie
z pomiarem.

**Rozstrzygnięcia wynikłe z powyższych, zapisane przy pisaniu kodu:**

- **Port usuwania bierze listę ścieżek** (`beginRemoval(list<string>)`) —
  dokładnie tak, jak port kopiowania od kroku 42, którego docblock zapowiadał
  „lista, nie jeden wpis, także wtedy, gdy ma jeden element (krok 43 doda
  resztę)”. Zapowiedź spełniła się co do słowa: `EntryTransfer` nie zmienił ani
  jednej linii pracy, tylko wypełnił listę, którą tamten krok zostawił pustą.
  Granica wiedzy rdzenia zostaje nietknięta — **skąd ta lista się wzięła, port
  nie wie**.
- **Reguła pustego zbioru mieszka w jednym miejscu**
  (`BrowserState::operands()` wraz z `BrowserPanes::focusedOperands()`), a nie
  w każdej czynności z osobna. Czynność pyta „na czym mam działać” i dostaje
  listę nazw; czy przyszła ze zbioru, czy z kursora, nie jest jej sprawą.
- **Zmiana nazwy i nowy katalog zostają jednowpisowe** (nazwa jest jedna
  z definicji), a `browser.delete <nazwa>` **wyprzedza zaznaczenie**: komenda
  z argumentem mówi, co usunąć, więc zbiór nie ma tam nic do powiedzenia.
- **Kursor po usunięciu zbioru przeskakuje resztę zaznaczonych** — spada na
  pierwszy **nieusuwany** wpis poniżej ostatniego z nich, a gdy takiego nie ma,
  powyżej pierwszego. Przy jednym wpisie rachunek daje to samo, co przed krokiem.
- **Scenariusz `marked` rozlicza się w parze z `columns`**, tą samą konstrukcją,
  co `highlight` z kroku 30: ta sama treść, te same kolumny, to samo przewinięcie
  — różnicą jest wyłącznie to, co się mierzy. Zaznaczone **trzy pozycje
  z siedmiu**, a nie co trzecia, i ta liczba też wyszła z obejrzenia wzorca:
  katalogi wypadają co szósty wiersz, więc przy „co trzeciej” pozycji **każdy
  katalog był zaznaczony** i z klatki nie dało się odczytać, czy rola zaznaczenia
  odróżnia się od akcentu. Siódemka daje w jednym wzorcu wszystkie cztery
  kombinacje: plik i katalog, zaznaczony i nie. Rytmu **katalogów** ruszyć nie
  było wolno — dzielą go `columns` i `highlight`, których wzorce mają zostać
  bajt w bajt.

### D81 — Rozstrzygnięcia startowe kroku 44: `Shift` wchodzi do trzech torów wejścia, kosz jest katalogiem konfigurowalnym, stos cofnięć dostaje widok

**Dotyczy:** kroku 44 (pełna treść:
[44-kosz-i-cofanie.md](archiwum/44-kosz-i-cofanie.md)), `Application/Dto` (`KeyPress`),
`Infrastructure/Terminal` (`KeySequenceParser`), `Infrastructure/Glfw`
(`GlfwKeyMapper`), `Application/Port` (nowy port kosza), `Application`
(zapis operacji), `Infrastructure/FileSystem` (kosz wedle freedesktop.org),
`Presentation/Ui` (`KeyBinding`, widok stosu), modułu przeglądarki
(`BrowserSettings`, `BrowserScreen`, `EntryOperations`), katalogów napisów,
[docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-15, przed pierwszą linią kodu — osiem pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” oraz cztery wynikłe z odpowiedzi na nie,
bo trzy z nich przesunęły zakres kroku poza to, co plan przewidywał.

**Sprawdzenie stanu zastanego: tabela z planu zgadza się co do wejścia, ale
trzy jej wiersze i dwa zdania planu są nieaktualne** — plik kroku powstał
2026-08-13, przed krokami 42, 43 i 47.

- **Klawisze przeglądarki są dziś inne**: `F4` nazwa, `F5` kopiowanie, `F6`
  przeniesienie, `F7` nowy katalog, `F8` i `Delete` usunięcie. Plan mówił jeszcze
  o `F6`/`F7` z kroku 41 — krok 42 przesunął wszystko o dwa, żeby zgodzić się
  z układem znanym z menadżerów dwupanelowych. Wolny w module został **`F3`**.
- **`F9` odpada z kandydatów na klawisz cofania**: od kroku 32 otwiera menu
  kontekstowe i jest klawiszem **globalnym**, nie modułowym.
- **`FileOperationsPort::beginRemoval()` bierze już listę ścieżek** (krok 43),
  a `PaneRefresh` odświeża oba panele (krok 42) — zapis operacji ma na czym stanąć
  i nie musi tego dowozić.
- **`ChoiceOverlay` istnieje od kroku 42** (sześć odpowiedzi na kolizję nazw),
  więc pytanie o trzech odpowiedziach nie wymaga ani nowego okna, ani rozrostu
  `ConfirmOverlay`.
- **`ModuleSetting::text()` i `::number()` istnieją** (kroki 36 i 27), więc
  katalog kosza i głębokość stosu mają gotową postać pozycji w zakładce modułu.
- **`FileTransferPort::begin(…, move: true)` rozpoznaje inny system plików po
  numerze urządzenia** i sam kopiuje — droga „do kosza mimo granicy wolumenu”
  jest wywołaniem tego, co już stoi, a nie nową pracą kawałkową.

**Decyzje użytkownika (1–8 — pytania z planu; 9–12 — wynikłe z odpowiedzi):**

1. **`Shift` wchodzi do słownika wejścia — wariant 1, zgodnie z rekomendacją
   planu i mimo najwyższego kosztu.** `KeyPress` dostaje trzecie pole, parser
   terminalowy przestaje odrzucać modyfikatory CSI (`ESC [ 1 ; 2 P`), a
   `GlfwKeyMapper` czyta `GLFW_MOD_SHIFT` — i wszystkie trzy tory muszą się
   zgodzić. Odrzucono dwa tańsze wyjścia: rozejście się `F8` i `Delete` (dziś
   synonimów), które nie kosztowałoby w wejściu nic, oraz trzecią odpowiedź
   w oknie potwierdzenia. Powód wyboru jest ten sam, co w planie: obydwa tańsze
   rozwiązują **problem skrótu**, a nie problem brakującego modyfikatora, który
   wróci przy pierwszym zaznaczaniu zakresem.
2. **Ustawienie modułu przestawia znaczenie klawisza domyślnego**, a nie wyłącza
   drugą drogę. `F8` i `Delete` robią to, co mówi pozycja „usuwaj do kosza”
   (domyślnie: kosz), `Shift`+`F8` i `Shift`+`Delete` — zawsze to drugie. Obie
   drogi są przez to **zawsze osiągalne**, a ustawienie wybiera wyłącznie, która
   jest tańsza w palcach.
3. **Kosz jest katalogiem konfigurowalnym, ale jego układ — nie.** Wartość
   domyślna to `$XDG_DATA_HOME/Trash`, a bez zmiennej `~/.local/share/Trash`,
   czyli kosz środowiska graficznego. Ustawienie modułu wolno przestawić na
   dowolny katalog i **wszędzie obowiązuje ten sam układ freedesktop.org**:
   `files/`, `info/` i plik `.trashinfo` ze ścieżką powrotną, pisany **przed**
   przeniesieniem. Odrzucono wariant „kosz własny bez układu”, w którym
   przywracanie działałoby wyłącznie z aplikacji: ścieżka powrotna zapisana
   w katalogu jest jedyną rzeczą, która przeżywa zamknięcie programu.
4. **Do kosza przenosi się zmianą nazwy, nigdy kopiowaniem** — dopóki
   `rename()` wystarcza. Rozstrzygnięcie użytkownika postawione wprost: kosz ma
   być tani, a kopiowanie gigabajta w odpowiedzi na `Delete` tanie nie jest.
5. **Wpis z innego systemu plików dostaje ostrzeżenie i pytanie o trzech
   odpowiedziach**: skopiować do kosza, usunąć trwale, przerwać. Pytanie idzie
   przez `ChoiceOverlay` z kroku 42, a kopiowanie — przez
   `FileTransferPort::begin([wpis], kosz, move: true)`, czyli tę samą pracę
   kawałkową z oknem postępu, którą tamten krok dowiózł. Plan przewidywał w tym
   miejscu jedną z trzech dróg; użytkownik wybrał **wszystkie trzy naraz jako
   odpowiedzi**, bo koszt każdej z nich zna wyłącznie ten, kto patrzy na plik.
   Granica z planu zostaje przez to **zawężona, nie zniesiona**: `.Trash-$uid`
   na wolumenie zewnętrznym nadal **nie powstaje** — wpis stamtąd wędruje do
   kosza katalogu domowego albo nie wędruje wcale.
6. **Cofanie dostaje stos wraz z widokiem — wbrew rekomendacji planu**
   („jeden poziom, bo stos wymaga własnego widoku, a widok to osobna funkcja”).
   Użytkownik przyjął tę cenę świadomie: widok **jest** częścią tego kroku.
   Cofać wolno **dowolną pozycję z listy**, nie tylko wierzchołek — a że stan
   dysku mógł się od tamtej pory zmienić, każde cofnięcie sprawdza wykonalność
   tuż przed wykonaniem i przy odmowie **nie zdejmuje zapisu**.
7. **Głębokość stosu jest pozycją w zakładce modułu**, a nie stałą w kodzie.
   Zapis nadal **nie przeżywa zamknięcia aplikacji** — to zostaje z planu bez
   zmian, bo cofanie po restarcie byłoby dziennikiem transakcji.
8. **Widok pokazuje także operacje nieodwracalne — wyszarzone i niewybieralne.**
   Lista odpowiada przez to na dwa pytania naraz: „co mogę cofnąć” i „co się
   właściwie wydarzyło”. Warunek z planu („operacja nieodwracalna nie udaje, że
   da się ją cofnąć”) zostaje spełniony **rolą motywu i odmową**, a nie
   pominięciem w spisie.
9. **`F3` otwiera widok stosu, `Alt`+`u` cofa.** `F3` jest jedynym wolnym
   klawiszem funkcyjnym modułu, a `Alt`+litera jest w przeglądarce wolne
   w całości (`Alt`+`z` zajmuje zawijanie wierszy, ale w module opisu pliku —
   skróty modułowe nie kolidują między modułami). Odrzucono `Shift`+`F3` dla
   widoku (para `F8`/`Shift`+`F8` znaczy „to samo, ale mocniej”, a widok nie jest
   mocniejszym cofnięciem) oraz widok bez klawisza, wyłącznie komendą i pozycją
   w menu.
10. **Utworzenie katalogu jest odwracalne, dopóki katalog został pusty.**
    Cofnięcie w katalogu, do którego coś przybyło, mówi dlaczego i nie zdejmuje
    zapisu — ta sama reguła, co przy każdym nieudanym cofnięciu.
11. **Kolizja nazw w koszu rozwiązuje się sufiksem liczbowym**
    (`raport.pdf`, `raport.1.pdf`), czyli tak, jak rozwiązuje ją kosz środowiska
    graficznego. Odrzucono znacznik czasu (niezgodny z tym, co użytkownik widzi
    na pulpicie) i pytanie (dwanaście pytań przy usuwaniu dwunastu wpisów
    o nazwach, które w koszu już są).
12. **Zaznaczanie zakresem `Shift`+strzałki wchodzi w tym kroku.** Skoro
    modyfikator i tak przechodzi przez trzy tory, czynność kosztuje już tylko
    obsługę w ekranie przeglądarki — mechanizm zaznaczenia stoi od kroku 43.
    Pozycja „zaznaczanie zakresem” w spisie „Zakres poza MVP” zamyka się razem
    z tym krokiem, a zapowiedź z indeksu („wchodzi wraz z krokiem 44, jeśli
    rozstrzygnięcie startowe nr 1 wybierze wariant z modyfikatorem”) spełnia się
    co do słowa.

**Model kroku: `Fable` / `xhigh`** — warunek z przypisu w
[00-index.md](00-index.md) zaszedł, bo rozstrzygnięcie nr 1 wybrało wariant
z modyfikatorem, czyli zmianę w trzech torach wejścia naraz. Rozstrzygnięcia
startowe spisano na `Opus 5`; **kod pisze się po przełączeniu sesji**, i to jest
cały powód, dla którego ten wpis powstał przed pierwszą linią kodu, a nie razem
z nią.

**Rozstrzygnięcia wynikłe z powyższych, zapisane przy pisaniu kodu:**

- **`shift` istnieje wyłącznie przy klawiszach nazwanych** — i to nie jest
  oszczędność, tylko uczciwość wobec źródła: litera z `Shift`em przychodzi z obu
  torów jako **inna litera** (`Shift`+`a` to `A`), więc znacznik przy znaku
  drukowalnym nie miałby czego nieść. `Ctrl` i `Alt` przy nazwach CSI pozostają
  odrzucane (nie mają ani jednego użytkownika), a `Ctrl`+`Shift`+`Delete` niesie
  bit `Shift`a i tym samym **jest** `Shift`+`Delete`. `KeyBinding::matches()`
  porównuje `shift` przy nazwach tą samą regułą, którą litera porównuje `Ctrl`
  i `Alt`: goły `F8` nie łapie `Shift`+`F8`, a w ekranie `Shift` rozstrzyga się
  **przed** gałęziami klawiszy (`BrowserScreen::shifted()`).
- **Stos cofnięć leży w module, wbrew literze planu kroku** („zapis w rdzeniu,
  obok portów”). Plan pisano przed krokami 41–43; gdy operacje zmaterializowały
  się w całości po stronie przeglądarki, dziennik został z jednym piszącym
  i jednym czytającym — a wtedy reguła 15 wygrywa z zapisem w planie, tym samym
  rachunkiem, którym krok 36 zaprowadził dźwięk do modułu. W rdzeniu stanął
  wyłącznie **port kosza** (`TrashPort` + `XdgTrashService`), bo ten pisze po
  dysku (15b). Z tego samego powodu `UndoOverlay` leży w `Presentation/Overlay`
  **modułu** (wzorzec `FilterOverlay`), nie w rdzeniu, jak zapowiadała tabela
  planu.
- **`FileTransferPort::begin()` dostał czwarty, opcjonalny parametr** — mapę
  „ścieżka źródła → nazwa w celu” — i jest to jedyne rozszerzenie kontraktu
  rdzenia w tym kroku. Powód jest twardy: kolizja katalogów jest w tej pracy
  **scaleniem**, więc wpis kopiowany do kosza pod zajętą nazwą wtopiłby się
  w cudzy wpis; nazwa zarezerwowana plikiem informacyjnym **przed** pierwszym
  bajtem musi być nazwą, pod którą praca naprawdę pisze. Zwykłe kopiowanie mapy
  nie podaje i zachowuje się jak od kroku 42.
- **Ustawienie „pytaj przed usunięciem” rządzi odtąd koszem**, a usunięcie
  trwałe pyta **zawsze** — dokładnie wedle planu kroku (punkt 2): tamto
  ustawienie dotyczy czynności odwracalnej. Dotychczasowe przebiegi trwałe
  przeszły na `Shift`+`F8`, a „bez pytania — od razu” jest odtąd przebiegiem
  kosza.
- **`Shift`+strzałki są przełącznikiem na wpisie, z którego wychodzą** — jak
  spacja i jak w Far: zmiana kierunku najpierw dociąga wpis pod kursorem,
  a zdejmuje dopiero powrót po własnym śladzie. Spacja stała się szczególnym
  przypadkiem tego samego kroku zaznaczania (`markStep()`).
- **`releaseUnused()` oddaje nazwy, które w koszu zostały** — praca „skopiuj do
  kosza” przerwana w połowie pyta dokładnie o to: co naprawdę dojechało i ma
  prawo stanąć w zapisie cofnięcia. Wpis częściowo skopiowany zostaje w koszu
  **z poprawnym plikiem informacyjnym**, więc da się go przywrócić w części,
  którą ma.
- **Zapis w stosie pada wyłącznie po pracy ukończonej w całości** (kopiowanie
  i przeniesienie): praca przerwana zostawiła część wpisów tu, część tam,
  a zapis obiecujący cofnięcie połowy kłamałby w drugiej połowie. Usunięcie
  trwałe zapisuje się za to także po przerwaniu — jako historia z prawdziwą
  liczbą — bo niczego nie obiecuje.

**Pomiar (maszyna zwolniona przez użytkownika, obciążenie 0,14/rdzeń):** tor
`--loop` wobec wzorca po kroku 43 — **−1,4%**, czyli szum: koszt czytania
modyfikatora CSI (jedno `strpos()` na sekwencjach **z parametrami**) jest
poniżej rozdzielczości taktu. Pełne porównanie sixelowe — bez regresji powyżej
progu (wszystkie scenariusze w rozrzucie ±5%), wzorce PNG **zgodne co do
piksela** (0 ‰ we wszystkich czterech), bo krok nie zmienia wyglądu żadnej
klatki. Wzorce `po-kroku-44` zapisane dla czterech torów.

## Decyzje ze startu kroku 45 (2026-08-15)

### D82 — Rozstrzygnięcia startowe kroku 45: takt jedną metodą, trzy drogi do playlisty, jeden plik stanu modułu, `Alt`+strzałki odrzucone przez słownik wejścia

**Dotyczy:** kroku 45 (pełna treść:
[45-ekran-audio-i-playlista.md](archiwum/45-ekran-audio-i-playlista.md)),
`Application/Module` (nowa zdolność taktu), `Presentation/Cli` (`GameLoop`,
`Bootstrap`), modułu dźwięku w całości (`src/Module/Audio/`), katalogów napisów,
[docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-15, przed pierwszą linią kodu — sześć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku”, jedno odłożone przez D71 (autostart) i jedno,
które wynikło dopiero z odpowiedzi na pytanie o kolejność pozycji.

**Sprawdzenie stanu zastanego: tabela z planu zgadza się co do wiersza.**
`Settings::$modules` bierze wyłącznie skalary (`bool|int|string`), `NeedsTime`
obsługuje wyłącznie ekran i okno nakładane, `AudioPort::play()` przyjmuje już
ścieżkę (więc playlista nie rusza kontraktu portu), a litera `a` jest wolna —
zajęte są `b` (przeglądarka) i `d` (opis pliku), zabronione `c h i j m z`.
`ModuleSetting::choice()` istnieje od kroku 27, więc tryb odtwarzania ma gotową
postać pozycji w zakładce.

**Decyzje użytkownika (1–6 — pytania z planu; 7 — odłożone przez D71; 8 —
wynikłe z odpowiedzi na pytanie 5):**

1. **Takt to jedna metoda `tick(float $now)`**, deklarowana osobno jak
   `ProvidesCommands`, wołana w `GameLoop` w fazie „aktualizuj stan” — po
   obsłudze wejścia, przed składaniem klatki, w tym samym miejscu, w którym stoi
   `advanceWork()`. Czas przychodzi z zewnątrz, jak w `NeedsTime` (11b: zegar
   nigdy z `microtime()` w środku), więc takt daje się mierzyć osią `--loop`
   i podstawić w teście. Odrzucono parę `start()` + `tick()`: `start()` miałby
   dziś jednego użytkownika (autostart), czyli dokładnie ten argument, którym D70
   odrzuciło cykl życia — a autostart moduł zrobi sam przy pierwszym takcie.
   Odrzucono też `tick()` bez czasu, bo moduł musiałby sięgnąć po zegar sam.
2. **Utwory wchodzą na playlistę trzema drogami naraz**, a nie jedną: wpisem
   zaznaczonym w przeglądarce przez `ReadsContext`, komendą `audio.add <ścieżka>`
   z podpowiedziami z dysku i polem tekstowym w oknie modułu. Pierwsza jest
   najtańsza w palcach i najczystsza dla kontraktu (moduł nie poznaje cudzego
   modułu, tylko ścieżkę), druga działa spoza okna i wchodzi do menu `F9`,
   trzecia jest jedyną drogą do pliku, którego przeglądarka akurat nie pokazuje.
3. **Nośnikiem jest jeden plik stanu modułu `~/.light-manager/audio.json`**, a nie
   plik samej playlisty. Krok 46 dokłada do niego mapę hooków **kluczem, nie
   drugim plikiem** — i to jest cały powód wyboru. Format JSON, bo pozycja to
   para (ścieżka, nazwa do pokazania), a nie sam wiersz jak w historii komend;
   droga zapisu zostaje ta sama co tam: plik tymczasowy i `rename()`, żadna
   ścieżka nie rzuca.
4. **`Enter` gra od razu** — zgodnie ze zdaniem-miarą kroku. Kursor listy i utwór
   grany są przez to dwiema różnymi rzeczami, a znacznik przy granym pokazuje,
   która pozycja jest którą.
5. **Kolejność wolno zmieniać w tym kroku**, wbrew rekomendacji planu: pozycja
   wędruje w górę i w dół wraz z kursorem. Playlista, której nie da się ułożyć,
   jest listą w kolejności przypadkowej — a kolejność jest jedyną rzeczą, którą
   playlista naprawdę wnosi ponad zbiór ścieżek.
6. **Pozycja wskazująca plik, którego nie ma, zostaje — wyszarzona i pomijana.**
   Wzorem stosu cofnięć z kroku 44 (pozycje nieodwracalne wyszarzone, kursor je
   przeskakuje). Odpięty nośnik nie kasuje playlisty, a użytkownik widzi, czego
   brakuje. Automatyczne przejście dalej pomija taką pozycję zamiast zatrzymać
   się na niej.
7. **Autostart wchodzi jako pozycja ustawień modułu, domyślnie wyłączona** —
   dług z kroku 36 domyka się jedną pozycją przełącznika, bo takt daje wreszcie
   kogo obudzić (D71 zapowiedziało tę decyzję dokładnie w tym miejscu). Domyślnie
   wyłączona, bo aplikacja grająca bez pytania przy pierwszym uruchomieniu
   zaskakuje, a `bin/render-bench` czyta tę samą konfigurację.
8. **Przestawianie pozycji bierze `Shift`+strzałki, nie `Alt`+strzałki** — i to
   jest rozstrzygnięcie wymuszone przez **regułę 11j sprawdzoną w kodzie**, a nie
   przez wygodę. `Alt` jest w słowniku wejścia dopuszczony **wyłącznie przy
   literach**: `KeySequenceParser` odrzuca modyfikatory `Ctrl`/`Alt` przy
   klawiszach nazwanych, a `GlfwKeyMapper` wystawia znacznik `alt` tylko dla
   `GLFW_KEY_A`–`Z`. `Alt`+strzałki znaczyłyby więc otwarcie słownika w trzech
   torach naraz — czyli **drugą** zmianę rdzenia w kroku, który ma ruszyć
   wyłącznie takt, i to tej wielkości, która w kroku 44 przesunęła model na
   `Fable / xhigh` (D81, rozstrzygnięcie 1). `Shift`+strzałki działają w obu
   torach od tamtego kroku i kolizji nie ma: wiązania należą do ekranu, a stopka
   kontekstowa mówi, co działa tu i teraz.

**Pomiar (maszyna zwolniona przez użytkownika, obciążenie 0,06–0,10 na rdzeń):**
oś `--loop` wobec wzorca po kroku 44 — **+1,5% bez muzyki, +2,4% przy muzyce
granej w tle przebiegu**, obie liczby w rozrzucie szumu. Pełne porównanie
sixelowe: dziewiętnaście scenariuszy w granicach ±4,1%, bez regresji — krok nie
zmienia ani jednej klatki poza oknem, którego scenariusza świadomie nie ma
(`ListView` w strefie środkowej mierzy `text`). Wzorzec
`2026-08-15-po-kroku-45-loop.json` zapisany.

**Granica tego pomiaru jest zapisana w dzienniku kroku i warto ją znać przy
kroku 46:** `LoopBenchmarkRunner` nie jest `GameLoopem` — powtarza jego trzy fazy
ręcznie i modułów nie tyka, więc wołania taktu w mierzonej ścieżce **nie ma**.
Liczba mówi, że reszta taktu pętli się nie zmieniła; koszt samego taktu jest
rachunkiem konstrukcyjnym (jedno przejście po liście i jedno porównanie pola, bez
wejścia-wyjścia). Drugi przebieg — ten z muzyką — odpowiada natomiast na pytanie
odłożone w kroku 36: **wątek miksujący nie wchodzi do ścieżki klatki mierzalnie**.

**Trzy rzeczy wyszły dopiero z uruchomienia** (pełny zapis: dziennik kroku 45).
`play()` wraca, **zanim silnik zacznie grać**, więc takt tuż po starcie uznałby
świeży utwór za skończony — stąd karencja pół sekundy, liczona czasem klatki
i będąca **jedynym użytkownikiem** argumentu `$now` w kontrakcie taktu.
**Pauza wygląda dla silnika tak samo jak koniec utworu**, więc playlista musi
wiedzieć, czy to ona prowadzi grę; bez tego spacja przeskakiwałaby utwór.
I trzecia, złapana dopiero na klatce pod XTermem: **stopka pola na ścieżkę
obiecywała „Esc zamknij okno komend”**, bo klucz opisu pożyczono z okna komend —
test pilnuje, że klawisz działa tam, gdzie stoi w spisie, ale nie tego, czy opis
mówi prawdę o miejscu.

## Decyzje ze startu kroku 46 (2026-08-15)

### D83 — Rozstrzygnięcia startowe kroku 46: zdarzenia publikują także moduły, słownik ma 22 pozycje, przełącznik przy każdym przypisaniu

**Dotyczy:** kroku 46 (pełna treść:
[46-efekty-specjalne.md](archiwum/46-efekty-specjalne.md)), nowego katalogu
`src/Application/Event/`, dwóch zdolności w `Application/Module`,
`Presentation/Cli` (`LoopState`, `OverlayStack`, `Bootstrap`), obu okien rejestru
komend, modułu przeglądarki (`src/Module/Browser/`), modułu dźwięku w całości
(`src/Module/Audio/`), katalogów napisów, [docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md) i `README.md`.

**Data:** 2026-08-15, przed pierwszą linią kodu — sześć pytań z sekcji „Do
rozstrzygnięcia na starcie kroku", jedno dodatkowe (pierwsze uruchomienie)
i jedno powtórzone, bo odpowiedź na nie **zmieniła zakres kroku**.

**Sprawdzenie stanu zastanego: tabela z planu zgadza się co do wiersza.**
`src/Domain/Event/` jest pusty od kroku 01, w całym `src/` nie ma ani jednego
`dispatch()`, `LoopState::report()` jest jedynym miejscem, przez które przechodzą
wszystkie komunikaty wraz z tonem, a plik `~/.light-manager/audio.json` przeżywa
nieznane klucze — dokładnie tak, jak krok 45 to zaprojektował (D82 nr 3).
Doszedł jeden fakt, którego plan nie znał: użytkownik dowiózł **pięć próbek
dźwiękowych** w `assets/sfx/`.

**Rozstrzygnięcie, które zmieniło zakres kroku.** Plan mówił „rdzeń publikuje,
moduł odbiera" i miał „zdarzenia publikowane przez moduły" w sekcji *Poza
zakresem*. Odpowiedź użytkownika na pytanie o dwa efekty naraz okazała się
odpowiedzią na coś innego: zdarzenia mają być **listą zdarzeń rdzenia
i modułów**, z przykładami „zakończenie kopiowania z sukcesem", „przejście
kursora na liście", „niepowodzenie usunięcia pliku". Rozpoznanie w kodzie
potwierdziło, że inaczej się nie da: wszystkie zdania modułów schodzą się
w `LoopState::report()` z tonem, więc trzy zdarzenia rdzenia odróżniają
powodzenie od awarii, ale **nie odróżniają kopiowania od usunięcia**. Zdanie
z planu zostało odwołane jawnie, a wykluczenie zawężone do tego, co nim naprawdę
jest: kolejek, priorytetów i zdarzeń asynchronicznych.

**Decyzje użytkownika:**

1. **Słownik ma 22 pozycje: 5 rdzenia i 17 przeglądarki.** Rdzeń ogłasza trzy
   tony komunikatu, otwarcie okna nakładanego i wykonanie komendy — każde
   z miejsca, które **już istniało**. Przeglądarka: ruch kursora, wejście do
   katalogu, zaznaczenie wpisu oraz **siedem czynności × udana/nieudana**
   (zmiana nazwy, nowy katalog, kopiowanie, przeniesienie, kosz, usunięcie
   trwałe, cofnięcie). Rozbicie po czynnościach jest świadomie kupione za długość
   spisu w oknie odbiorcy: wariant „jedno wspólne niepowodzenie" odpadł, bo to
   właśnie „niepowodzenie usunięcia" było przykładem podanym przez użytkownika.
   **Koniec pracy do słownika nie wszedł** i powód jest mechaniczny:
   `Bootstrap::shutdown()` zatrzymuje silnik audio, więc dźwięk podpięty do
   zakończenia zostałby ucięty w pół.
2. **Słownik mieszka w `Application/Event`, nie w `Domain/Event`**, a zdolności
   nazywają się `ListensToEvents` (odbiór) i `DeclaresEvents` (ogłaszanie) — obie
   w `Application/Module`, bo nie wymieniają typów z `Presentation` (P2). Katalog
   `Domain/Event` **zostaje pusty**: „otwarto okno" i „wykonano komendę" to fakty
   aplikacji, a `Domain` rdzenia jest umyślnie chudy i jest słownikiem powłoki
   (reguła 1). Zdarzenie to **enum**, nie obiekt wartości z danymi — niesie
   wyłącznie tożsamość (D40 P5).
3. **Przypisanie idzie trzema drogami**: `F5` (wpis zaznaczony w przeglądarce
   przez `ReadsContext`), `F7` (pole na ścieżkę) i komenda
   `audio.hook <zdarzenie> <ścieżka>` — **pierwsza w projekcie z dwoma
   argumentami podpowiadanymi**. Mechanizm był na to gotowy od kroku 19
   (`SuggestsArguments` rozdziela podpowiedzi po nazwie argumentu), więc koszt
   wyniósł jedną klasę komendy.
4. **Przełącznik stoi przy każdym przypisaniu** (spacja w panelu), a nie tylko
   globalnie — i mieszka w mapie, nie w ustawieniach, bo mapa i tak trzyma po
   wierszu na zdarzenie, a pozycja w zakładce musiałaby powstać dla każdego
   z osobna. Globalny **zostaje** obok niego, bo żąda go kryterium ukończenia
   kroku („jeden przełącznik ucisza wszystkie efekty"). Wyciszenie zostawia plik,
   `F8` go zabiera — dwie różne czynności, dwa klawisze.
5. **Efekty mają własną głośność**, osobną pozycję w zakładce. Powód jest
   słyszalny, a nie porządkowy: klik zmiksowany na poziomie muzyki ginie pod nią
   albo krzyczy w ciszy. Domyślnie 70%, muzyka 50%.
6. **Uchwyt efektu jest jeden — nowy przerywa poprzedni.** Wariant „każdy plik ma
   własny uchwyt" odpadł; przy dźwiękach trwających pół sekundy różnicy nie
   słychać, a mapa uchwytów byłaby stanem do sprzątania.
7. **Zdarzenie bez przypisania zostaje w spisie**, wyszarzone i z kreską —
   wzorem playlisty i stosu cofnięć (D82 nr 6). Spis składa się przez to **ze
   słownika, a nie z mapy**: użytkownik ma widzieć, co jeszcze da się przypisać.
8. **Minimalny odstęp między dwoma odpaleniami tego samego zdarzenia stoi po
   stronie odbiorcy** (100 ms). Pytanie wynikło z rozstrzygnięcia nr 1: trzymana
   strzałka daje trzydzieści zdarzeń kursora na sekundę, a klik odpalany trzydzieści
   razy na sekundę jest warkotem. Wariant „przeglądarka publikuje rzadziej" odpadł,
   bo wnosiłby wiedzę o dźwięku do publikującego.
9. **Przy pierwszym uruchomieniu mapa jest pusta, a przełącznik włączony.** Ten
   sam rachunek, który w kroku 45 wyłączył autostart muzyki (D82 nr 7): aplikacja,
   która przy pierwszym uruchomieniu zaczyna klikać, zaskakuje. Próbki
   z `assets/sfx/` są materiałem do przypisania, a nie zestawem domyślnym.

**Co z tego wyszło w kodzie — trzy rzeczy warte zapamiętania.**

**Publikator zamieszkał w `LoopState`**, obok kontekstu sesji i z tego samego
powodu: stan pętli dostaje **każdy** moduł, więc `Bootstrap` urósł o jedną linię
(`useModules()`), a nie o argument przy każdym publikującym module.

**Zamkniętość słownika jest wykonana konstrukcyjnie.** Nazwy pochodzą z enumów,
a deklaracja katalogu powstaje z `cases()` — publikacja i spis u odbiorcy nie mają
jak się rozjechać. Rozjazd byłby przy tym **niewidoczny**: wiersz, do którego nic
nie dochodzi, wygląda dokładnie tak samo jak wiersz, do którego nic nie
przypisano.

**Zdarzenie „wejście do katalogu" ogłasza `BrowserState::enter()`, a nie ekran** —
i pada wyłącznie wtedy, gdy katalog naprawdę się zmienił. Tą samą metodą wchodzi
bowiem przełączenie wpisów ukrytych i odczyt katalogu na nowo po operacji, a jedno
porównanie ścieżek jest tańsze i uczciwsze niż cztery miejsca publikacji
(klawisz, drzewo, `browser.jump`, `browser.open`).

## Decyzje z planowania Fazy XVII (2026-08-15)

### D84 — Praca na zdalnym hoście wchodzi jako Faza XVII: moduł `Ssh`, `ext-ssh2` w procesie, trzy kroki

**Dotyczy:** kroków 48 ([48-ssh-sesja-i-hosty.md](archiwum/48-ssh-sesja-i-hosty.md)),
49 ([49-zdalny-katalog.md](49-zdalny-katalog.md)) i 50
([50-przesyl-plikow.md](50-przesyl-plikow.md)); nowego katalogu
`src/Module/Ssh/`, jednej linii w `Presentation/Cli/Bootstrap.php`, pola
`suggest` w `composer.json` oraz — warunkowo, wedle rozstrzygnięcia na starcie
kroku 50 — granicy wyjątku 15b.

**Data:** 2026-08-15, na polecenie użytkownika („przygotuj krok planu
z funkcjonalnością połączenia ssh"), przed pierwszą linią kodu i przed
rozpisaniem kroków.

**Co rozstrzygnęło rozpoznanie w środowisku, zanim padło pierwsze pytanie.**
Pięć faktów, wszystkie sprawdzone przy planowaniu i wszystkie przesądzające
o kształcie fazy:

- **`ext-ssh2` jest załadowane** — wersja 1.3.1 na libssh2 1.11.0, 34 funkcje,
  w tym `ssh2_auth_agent`, komplet `ssh2_sftp_*` i `ssh2_exec`; opakowania
  `ssh2.sftp://`, `ssh2.exec://`, `ssh2.shell://` i `ssh2.tunnel://`
  zarejestrowane. Droga „w procesie" nie wymaga więc ani jednej zależności
  Composera — a projekt ma dziś w `require` wyłącznie `ext-imagick`
  i `ext-pcntl`.
- **`ssh2_connect()` nie przyjmuje limitu czasu.** Sygnatura to
  `(host, port, methods, callbacks)`. Host nieosiągalny zatrzyma pętlę na
  `default_socket_timeout`, czyli domyślnie na minutę — i będzie to zawieszenie
  całej aplikacji, nie samego modułu.
- **`ssh2_fingerprint()` daje wyłącznie MD5 i SHA1**, a API `known_hosts`
  libssh2 **nie jest w PHP wystawione** (w spisie funkcji nie ma ani jednej
  `ssh2_known_hosts*`). Plik użytkownika ma przy tym **nazwy hostów
  zahaszowane** — 23 wpisy, wszystkie `|1|sól|skrót`. Weryfikacja klucza hosta
  jest więc wykonalna, ale robi ją moduł sam: HMAC-SHA1 nazwy, dopasowanie po
  typie klucza z `ssh2_methods_negotiated()`, SHA1 z odkodowanego base64.
- **Litera `s` jest wolna** (zajęte `b`, `d`, `a`; zakazane `c, h, i, j, m, z`),
  a `Ctrl`+`S` jest w terminalu bezpieczny, bo `TerminalService::RAW_MODE_SETTINGS`
  zawiera `-ixon` — XOFF nie zadziała.
- **Serwera SSH na maszynie nie ma** (port 22 zamknięty), jest za to `docker`.
  Sprawdzenie ręczne każdego z trzech kroków wymaga albo kontenera z `sshd`,
  albo hosta podanego przez użytkownika — i to jest warunek, o który trzeba
  poprosić przed pierwszym uruchomieniem, tak jak reguła 17 każe prosić
  o zwolnienie maszyny przed pomiarem.

**Decyzje użytkownika:**

1. **Zdalny panel przez SFTP**, a nie sesja powłoki ani samo połączenie. Funkcja
   wpina się w to, co aplikacja już umie: lista wpisów, chodzenie po katalogach,
   kolumny z kroku 27. Sesja powłoki odpadła, bo wymagałaby emulacji sekwencji
   sterujących i własnego bufora ekranu — dwóch rzeczy, których projekt nie ma
   w żadnej postaci, więc byłaby osobną aplikacją w aplikacji.
2. **Dostęp w procesie, przez `ext-ssh2`.** Wariant „proces potomny (`ssh`,
   `sftp`, `rsync`) przez `BackgroundProcessPort`" odpadł mimo dwóch realnych
   zalet (nie blokuje pętli, dziedziczy `~/.ssh/config` i agenta za darmo):
   ceną byłoby parsowanie wyjścia `sftp` i jedna praca naraz na cały moduł.
   Wariant „phpseclib przez Composera" odpadł jako pierwsza zależność
   produkcyjna projektu.
3. **Nowy moduł `src/Module/Ssh/`**, zgodnie z regułą 15. Rozbudowa modułu
   `Browser` odpadła, choć byłaby najkrótszą drogą do zdalnego panelu: wiązałaby
   SSH z przeglądarką na stałe. Port rdzenia odpadł, bo jego jedyna próba
   („dwóch odbiorców i powtórzenie o koszcie nieodwracalnym", 15b) jest
   niespełniona — odbiorca jest jeden.
4. **Faza z trzech kroków, nie jeden krok.** Zakres dzieli się wzdłuż trzech
   rzeczy, z których każda ma własne rozstrzygnięcia i własne rozliczenie: sesja
   (48), odczyt (49), zapis obustronny (50). Rytm ten sam, co w D48 i D66.

**Co z tych decyzji wynika dla kroków — i czego plany pilnują:**

- **Reguła nadrzędna fazy: żadne wywołanie sieciowe nie pada w rysowaniu
  klatki.** Jest to piąta reguła D46 rozciągnięta z zapisu na dysk na sieć,
  a wynika wprost z faktu, że `ext-ssh2` nie ma wywołań nieblokujących. Każde
  ma ponadto nałożony własny limit czasu — osiągalność portu sprawdza się
  osobno, `stream_socket_client()` z limitem sekundowym, **przed** uściskiem
  dłoni.
- **Kawałek pracy trwa tyle, ile trwa sieć — i to jest rzecz, której wzorzec
  z D46 nie przewidywał.** Praca lokalna liczy budżet we wpisach (512 na takt
  liczenia, krok 41) albo w bajtach (krok 42), bo każdy wpis i każdy bajt
  kosztuje tyle samo co poprzedni. Tutaj kosztem jest obieg do serwera, więc
  budżet **pyta zegara**, a nie licznika. Dotyczy to obu kroków pracujących:
  49 (atrybuty wpisów) i 50 (bloki pliku).
- **Odczyt katalogu może kosztować obieg na wpis.** `opendir()` na opakowaniu
  `ssh2.sftp://` oddaje same nazwy; czy rozszerzenie przekazuje atrybuty, które
  protokół SFTP niesie razem z nazwą, jest **niesprawdzone i sprawdzić się przy
  planowaniu nie dało** (brak serwera). Rekomendacja kroku 49, gdyby okazało się,
  że nie: `stat` **tylko dla widocznego okna**, kawałkowo — jedyny z trzech
  wariantów, który nie zakłada niczego o zdalnym systemie, bo serwer SFTP nie
  musi mieć powłoki.
- **Druga domena plikowa jest świadoma, nie przeoczona.** `DirectoryPath`,
  `Entry` i `EntryComparator` należą do modułu `Browser`, a moduł nigdy nie
  sięga do innego modułu — więc moduł `Ssh` dostaje własne `RemotePath`
  i `RemoteEntry`. Alternatywą byłoby wyniesienie ścieżki do rdzenia, czyli
  odwrócenie D42 („rdzeń nie wie, czym jest katalog ani wpis"), a to jest cena
  nieporównanie wyższa niż dwie klasy wartości. Granica tego powtarzania jest
  częścią zakresu kroku 49 i ma trafić do `SKILL.md` wraz z powodem —
  nienazwana, otworzyłaby drogę do powielania całych modułów.
- **`ModuleContext` ze ścieżką zdalną skłamałby, i to cicho.** Kontekst niesie
  ścieżkę **jako napis**, bez informacji, czyja jest, a moduł opisu pliku czyta
  ją `lstat`em: ekran zdalny publikujący `/var/log` sprawiłby, że `FileInfo`
  pokaże **lokalny** `/var/log`. Obie ścieżki istnieją, obie się czytają,
  a użytkownik ogląda opis nie tego pliku, na który patrzy. Rekomendacja kroku
  49: ekran zdalny kontekstu **nie publikuje**; pole „pochodzenie" w kontekście
  wchodzi dopiero razem z odbiorcą (reguła 13).
- **W drugą stronę ta sama droga jest legalna i darmowa.** Krok 50 czyta
  `ModuleContext`, żeby znać lokalny katalog docelowy pobrania — czyli poznaje
  drugą stronę przesyłu **nie sięgając do przeglądarki ani razu**. Dokładnie po
  to kontekst istnieje (D40 P5), i to jest najlepszy dowód, że tamto
  rozstrzygnięcie było trafne.
- **Zapis pobranego pliku dotyka wyjątku 15b i wymaga zgody wprost.**
  Rekomendacja kroku 50: pisze moduł, a wyjątek dostaje **drugi nazwany
  przypadek** o wąskiej granicy (wyłącznie w pracy przesyłu, wyłącznie do
  katalogu wskazanego przez użytkownika). Wariant „ścieżkę `ssh2.sftp://` podaje
  się rdzeniowemu `FileTransferPort` jako napis" kusi, bo port bierze „ścieżkę
  bezwzględną jako napis" — ale rozpoznanie systemu plików idzie tam przez numer
  urządzenia, a `is_link()` i prawa dostępu na URI nie znaczą nic; port zacząłby
  kłamać w miejscu, w którym dziś jest dokładny.
- **Moduł jest czwartym sprawdzianem kontraktu z kroku 20** — po module
  rysującym główną funkcję (21), module bez ekranu (36) i module pracującym, gdy
  go nie widać (45), przychodzi moduł **rozmawiający z czymś poza maszyną**.
  Rdzeń ma kosztować jedną linię w `Bootstrapie`; jeśli będzie kosztował więcej,
  jest to błąd do naprawienia, a nie powód, żeby dotknąć rdzenia.
- **Krok 48 jest zarazem pierwszym sprawdzianem mechanizmu zdarzeń z kroku 46
  przez moduł, którego przy jego powstawaniu nie było.** „Połączono",
  „rozłączono" i „nie udało się połączyć" dostają dźwięk przez `DeclaresEvents`,
  bez ani jednej linii w rdzeniu. Jeśli okaże się, że kosztuje to więcej,
  zamknięcie słownika zdarzeń z 11o'' ma usterkę, o której dziś nikt nie wie.
- **Testy nie otwierają połączenia sieciowego — w żadnym z trzech kroków.** Ta
  sama reguła, co przy silniku audio (11o): sprawdza się wszystko przed pierwszym
  wywołaniem, a resztę atrapą portu. Klasy czyste — `KnownHostsReader`,
  `RemotePath`, komparator, stan pracy — dają się sprawdzić bez ani jednego
  bajtu w sieci, i to jest kryterium ich podziału.

**Odrzucone alternatywy** (poza wymienionymi przy decyzjach 1–4): faza
dwukrokowa, w której odczyt i przesył idą razem — odpadła, bo krok 50 ma cenę
błędu nieodwracalną i zasługuje na własne rozliczenie; faza czterokrokowa
z osobnym krokiem na zapis po zdalnej stronie — zapis zdalny stoi w „Zakresie
poza MVP" i wejdzie osobno, jeśli okaże się potrzebny.

## Decyzje z planowania Fazy XVIII (2026-08-15)

### D85 — Kontenery wchodzą jako Faza XVIII: dwa moduły, droga mieszana, a współpracę niosą komendy i nowe kwerendy

**Dotyczy:** kroków 51 ([51-modul-docker.md](51-modul-docker.md)),
52 ([52-modul-kubernetes.md](52-modul-kubernetes.md)) i
53 ([53-kwerendy-miedzymodulowe.md](53-kwerendy-miedzymodulowe.md)); nowych
katalogów `src/Module/Docker/`, `src/Module/Kubernetes/` i `src/Application/Query/`,
**zmiany kontraktu `Application\Port\BackgroundProcessPort`**, trzech linii
w `Presentation/Cli/Bootstrap.php`, pola `suggest` w `composer.json` oraz reguł
11d i 15 w [SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md).

**Data:** 2026-08-15, na polecenie użytkownika („stwórz krok planu dodania
modułu obsługi `docker` i `docker compose`, dodatkowo krok obsługi kubernetesa;
moduły powinny mieć możliwość wzajemnego użycia"), przed pierwszą linią kodu.

**Co rozstrzygnęło rozpoznanie w środowisku, zanim padło pierwsze pytanie.**
Sześć faktów, wszystkie sprawdzone przy planowaniu:

- **Docker działa bez `sudo`** — 27.3.1, API demona **1.47**, 29 kontenerów
  i 123 obrazy. Gniazdo `/var/run/docker.sock` odpowiada **wprost z PHP**:
  `ext-curl` 8.5.0 obsługuje `CURLOPT_UNIX_SOCKET_PATH`, a `curl_multi_*` daje
  pracę nieblokującą. Klient nie wymaga więc ani procesu potomnego, ani
  zależności Composera.
- **Compose nie ma API.** Docker Compose v2.29.7 jest **wtyczką CLI**, a demon
  nie wystawia dla niej ani jednego zasobu — więc nawet moduł idący gniazdem
  potrzebuje procesu potomnego. `docker compose ls --format json` oddaje tablicę
  (jeden projekt, `dev`, uruchomiony), a `docker ps -a --format json` oddaje JSON
  **wierszami** i niesie etykietę `com.docker.compose.project`, czyli wiąże
  kontener z projektem za darmo.
- **Klastra k8s nie ma pod ręką.** `kubectl` v1.25.2 (2022), jedyny kontekst
  `ca-dev` **nie jest bieżący** (więc `kubectl` idzie na `localhost:8080`
  i dostaje odmowę), minikube zatrzymany, `helm` obecny. Sprawdzenie ręczne
  kroków 52 i 53 wymaga uruchomienia minikube — czyli zgody użytkownika, jak
  przy pomiarach (reguła 17).
- **`BackgroundProcessPort` prowadzi jedną pracę naraz** i jest to **decyzja
  z kroku 26**, zapisana wprost w kontrakcie, a nie ograniczenie techniczne.
  Dziś odbiorca jest jeden (`du` w module opisu pliku), więc nikomu to nie
  przeszkadzało; `compose up` i `kubectl logs -f` są pracami długimi
  i równoległymi, więc przy dzisiejszej regule jedna funkcja aplikacji ubijałaby
  drugą bez słowa wyjaśnienia.
- **Potomek nie dostaje wejścia** (ta sama reguła) — z czego wynika rzecz
  drobna, ale przesądzająca o kształcie kroku 52: `kubectl apply -f -` jest
  **niewykonalne**, plik podaje się ścieżką.
- **`src/Application/Query/` nie istnieje**, a `CommandOutcome` niesie przejście,
  `?Message` i `?screenId` — **danych nie niesie**. Rdzeń nie ma więc dziś ani
  jednego kanału, którym moduł oddałby drugiemu daną.

**Decyzje użytkownika:**

1. **Moduł `docker` bierze cały zakres**: podgląd i cykl życia kontenerów oraz
   obrazów, logi na żywo, budowanie obrazów **i** Docker Compose. Nic z tego nie
   zostało odłożone.
2. **Droga mieszana**: gniazdo dla Dockera, CLI dla Kubernetesa. Wariant „CLI dla
   obu" odpadł mimo wygody jednolitości, wariant „gniazdo dla obu" jest dla k8s
   niewykonalny bez własnego klienta HTTPS z certyfikatami z `kubeconfig`. Cena
   jest zapisana w planach wprost: **dwa różne rodzaje wejścia-wyjścia w jednej
   fazie i dwie różne drogi awarii do opisania** — a w module Dockera nawet trzy,
   bo compose i tak idzie procesem potomnym.
3. **Współpraca modułów: czynności przez istniejący rejestr komend, dane przez
   nowy rejestr kwerend.** To jest rozstrzygnięcie własne użytkownika, szersze
   niż którykolwiek z wariantów postawionych w pytaniu — te przewidywały rejestr
   zdolności **albo** same komendy. Powód, dla którego same komendy nie
   wystarczają, jest wymierny: `CommandOutcome` niesie **zdanie dla
   użytkownika**, a nie identyfikator zbudowanego obrazu, więc „zbuduj i podaj mi
   tag" nie ma czym wrócić. Dopisanie mu pola z danymi zamieniłoby skutek dla
   interfejsu w kanał danych — stąd druga, osobna rzecz.
4. **Trzy kroki**, wedle rytmu „jeden mechanizm — jeden krok" z D48 i D71:
   51 (prace tłowe równoległe wraz z modułem Dockera), 52 (moduł k8s), 53
   (kwerendy wraz z pierwszą czynnością przechodzącą przez oba moduły).

**Odstępstwo od brzmienia wariantu, świadome i uzasadnione regułą 13.** Etykieta
wybranego wariantu mówiła „52: mechanizm wraz z odbiorcą — modułem k8s". Po
rozstrzygnięciu nr 3 mechanizmem są **kwerendy**, a ich pierwszym odbiorcą nie
jest moduł k8s (ten stoi sam z siebie i Dockera nie potrzebuje), tylko
**czynność łącząca oba moduły**. Mechanizm przeniósł się więc do kroku 53, żeby
wejść razem z odbiorcą — dokładnie tak, jak każe reguła 13. W kroku 52 nie
zostało przez to **nic** z rdzenia i to jest jego zaleta, a nie brak: jest
sprawdzianem rozbudowy portu z kroku 51 przeprowadzonym przez kogoś, kto przy
niej nie stał.

**Co z tego wynika dla kroków — i czego plany pilnują:**

- **Reguła 11d dostaje poprawkę, i to na oczach dzisiejszego odbiorcy.** „Jedna
  praca naraz" staje się „kilka prac, każda ze swoim uchwytem, z górnym
  ograniczeniem liczby". Trzy rzeczy zostają nietknięte: zaglądanie nigdy nie
  blokuje, oba potoki czytane co klatkę **dla każdej pracy**, sprzątanie dwiema
  drogami (D47). Osobnym kryterium ukończenia kroku 51 jest zdanie: **`du`
  działa w trakcie pracy compose i odwrotnie** — bo zmiana reguły, po której
  starszy odbiorca przestaje działać, nie jest rozbudową, tylko regresją.
- **Reguła 15 zostaje w mocy i zostaje dopowiedziana.** Moduł nadal nie sięga do
  modułu — sięga do **rdzenia**, a rdzeń trzyma rejestr, do którego wpisał się
  ktoś inny. Tak działa to od kroku 19 przy komendach i od 46 przy zdarzeniach
  (`MenuOverlay` wywołuje komendę przeglądarki, nie znając przeglądarki); nikt
  tego wtedy nie nazwał współpracą modułów, ale nią było. Nowy jest **kanał,
  którym wraca dana**. Granica dopowiedzenia, do zapisania w `SKILL.md`: moduł
  zna **nazwę** cudzej komendy i kwerendy (napis), nigdy jej typ; kwerenda oddaje
  **dane pierwotne** (precedens `ModuleContext`, D40 P5); **moduł pytający musi
  umieć żyć bez odpowiedzi**, bo ten drugi bywa wyłączony, odrzucony albo
  nieobecny.
- **Cztery reguły kwerendy, wszystkie w kontrakcie albo w rejestrze**: kwerenda
  **czyta i nie zmienia** (co zmienia — jest komendą; bez tego pierwsza kwerenda
  `docker.prune` uczyniłaby mechanizm drugą drogą do czynności), **nie zna
  wołającego**, **nie woła kwerendy** (wzorem „zdarzenie nie rodzi zdarzenia",
  11o''), **odpowiada w klatce albo wcale** — praca dłuższa od klatki idzie
  komendą, a kwerenda oddaje jej **stan**, nie czeka na koniec.
- **Trzy mechanizmy rdzenia składają się po raz pierwszy w jedną czynność:
  komenda robi, zdarzenie ogłasza, kwerenda mówi co wyszło.** Budowa obrazu trwa
  minuty, więc wołający nie czeka w klatce — dowiaduje się zdarzeniem
  `docker.build.finished` i dopiero wtedy pyta o wynik. To zdanie jest zarazem
  najkrótszym opisem tego, po co krok 53 istnieje.
- **Krok 51 jest największym krokiem projektu** i plan mówi to wprost, razem
  z linią cięcia na wypadek, gdyby okazał się za duży: wychodzi z niego
  **compose**, bo jako jedyny nie dzieli z resztą ani drogi technicznej (CLI
  zamiast gniazda), ani danych (projekt zamiast kontenera). Wyjęcie czegokolwiek
  innego rozerwałoby rzecz trzymającą się razem.
- **Obraz zbudowany lokalnie nie istnieje w klastrze** — i to jest miejsce,
  w którym funkcja z kroku 53 może wyjść atrapą. Bez rozstrzygnięcia (`minikube
  image load` / rejestr i `push` / klaster dzielący demona) czynność skończy się
  podem w `ImagePullBackOff`, co wygląda jak usterka aplikacji, a nie jak
  brakujący krok. Rozstrzygnięcie stoi na starcie kroku 53, a `docker push` jest
  świadomie **poza zakresem kroku 51** i wchodzi tylko wtedy, gdy wybrana
  zostanie droga przez rejestr.
- **Dwie pułapki strumieniowe Dockera są w planie nazwane**, bo obie dają
  „działa, ale wygląda na zepsute": logi kontenera bez TTY są **multipleksowane
  ośmiobajtowymi ramkami** (czytane jak tekst dają śmieci co kilka wierszy),
  a budowa oddaje postęp jako **strumień obiektów JSON po jednym na wiersz**.
- **Testy nie rozmawiają ani z demonem, ani z klastrem** — w żadnym z trzech
  kroków, tą samą regułą, co przy silniku audio (11o) i sesji SSH (D84).
  Rozbieranie ramek i parsowanie JSON-a sprawdza się na próbkach bajtów,
  współpracę modułów — na **dwóch modułach-atrapach**.

**Odrzucone alternatywy** (poza wymienionymi przy decyzjach 1–4): jeden moduł
`containers` obejmujący Dockera i k8s — problem współpracy znikałby zamiast
zostać rozwiązany, a moduł urósłby do rozmiaru przeglądarki i robił dwie różne
rzeczy; jawny wyjątek od reguły 15 (moduł k8s zna port modułu docker) — najkrótsza
droga, ale otwiera regułę trzymającą cały podział, a następna para modułów
powołałaby się na precedens; cztery kroki z mechanizmami oddzielonymi od modułów —
dwa kroki dowoziłyby wtedy mechanizm bez odbiorcy, wbrew regule 13.

### D86 — Kwerendy dostają wszystkie moduły, a odbiorcą tych bez konsumenta w kodzie zostaje użytkownik

**Dotyczy:** kroku 53 ([53-kwerendy-miedzymodulowe.md](53-kwerendy-miedzymodulowe.md));
katalogów `src/Module/Browser/`, `src/Module/FileInfo/` i `src/Module/Audio/`
wraz z ich `lang/`; okna kwerend w `src/Presentation/Ui/Overlay/`; reguły 15
w [SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md).

**Data:** 2026-08-15, tego samego dnia co D85, przed pierwszą linią kodu — na
pytanie użytkownika, czy krok 53 zakłada kwerendy także w modułach istniejących.

**Nie zakładał — i było to wykluczenie świadome, nie przeoczenie.** Punkt *Poza
zakresem* w pierwotnym brzmieniu kroku mówił wprost: „współpraca modułów
niezwiązana z kontenerami — mechanizm to umożliwi, ale odbiorcy dziś nie ma".
Wykluczenie stało na regule 13 i było poprawne co do litery. Co do skutku —
zostawiało projekt z mechanizmem rdzenia **umiejącym odpowiedzieć wyłącznie na
pytania dwóch modułów napisanych razem z nim**, a to nie jest mechanizm rdzenia,
tylko wewnętrzne uzgodnienie tej pary.

**Co rozstrzygnęło rozpoznanie w kodzie, zanim padło pytanie do użytkownika.**
Trzy fakty, wszystkie sprawdzone przy uzupełnianiu:

- **`ModuleContext` niesie już ścieżkę i zaznaczenie** — `path`, `selection`,
  `kind`, `markedCount`, `markedBytes`, `markedDirectories`, wszystko jako dane
  pierwotne publikowane przez `BrowserState`. Kroki 51 i 52 **już się na tym
  opierają**: ścieżka pliku `compose` i ścieżka manifestu do `apply` biorą się
  stamtąd. Pierwsze kwerendy, które przychodzą do głowy (`browser.cwd`,
  `browser.selection`), byłyby więc **drugą drogą do danej rozdawanej co klatkę
  za darmo** — i drogą gorszą, bo wymagającą pytania i obsłużenia braku
  odpowiedzi.
- **Rdzeń nie umie wypisać katalogu.** `FileOperationsPort` ma `rename`,
  `createDirectory`, `delete` i usuwanie kawałkowe — listy nie ma; wypis należy
  do `DirectoryRepositoryInterface` w dziedzinie przeglądarki. Czyli
  `browser.entries` **jest jedyną uczciwą drogą** do zawartości katalogu dla
  cudzego modułu: alternatywą byłoby drugie czytanie systemu plików w module k8s
  albo sięgnięcie po typ przeglądarki, czyli złamanie reguły 15.
- **Kwerendy modułu opisu pliku mają gotowy precedens na regułę nr 4.**
  `ChecksumStage` i `DiskUsageStage` z kroków 25 i 26 opisują **stan pracy
  tłowej**, a kwerenda ma oddawać właśnie stan, a nie czekać na koniec. Nie
  trzeba wymyślać drugiego wzorca.

**Decyzje użytkownika:**

1. **Kwerendy dostają wszystkie trzy istniejące moduły** — `browser`,
   `file-info` i `audio`, po dwie każdy. Sześć kwerend dobranych jedną zasadą:
   kwerendą zostaje **to, o czym wie tylko ten moduł, a czego nie niesie
   `ModuleContext`**.
2. **Rozstrzygnięcie nr 7 kroku idzie na „kwerendy widoczne"** i krok dowozi
   **okno kwerend**. To ono, a nie wyjątek od reguły 13, jest powodem, dla
   którego cztery z sześciu kwerend wolno napisać bez konsumenta w kodzie:
   **konsument jest, tylko siedzi przed terminalem**.

**Co z tego wynika dla kroku — i czego plan pilnuje:**

- **Reguła 15 dostaje drugie zdanie graniczne**, obok „komenda robi, kwerenda
  mówi": **kontekst mówi, gdzie użytkownik stoi; kwerenda mówi, co u mnie jest.**
  Kontekst niesie jedno miejsce i jedno zaznaczenie — ścieżkę panelu czynnego,
  ale nie drugiego; liczbę zaznaczonych, ale nie ich nazwy; wpis pod kursorem,
  ale nie zawartość katalogu. To, czego kontekst nie niesie, jest zakresem
  kwerend; to, co niesie, **nie ma prawa się w nich powtórzyć**.
- **Zaznaczenie wielokrotne z kroku 43 po raz pierwszy dochodzi poza
  przeglądarkę.** `browser.marked` oddaje nazwy, których kontekst nie niesie,
  a odbiorcą jest `k8s.apply` na wielu manifestach naraz.
- **Okno kwerend ma być drugim trybem okna komend, nie drugim oknem**
  (rekomendacja, rozstrzygnięcie nr 9 kroku): te same komponenty, ten sam
  `CommandLineParser`, ten sam scenariusz pomiarowy `command` — i słownik
  wejścia nierosnący o klawisz. Wariant „osobne okno" ma cenę zapisaną z góry:
  własny scenariusz w `bin/render-bench`, a przy własnym klawiszu **trzy tory
  wejścia**, czyli ponowny rachunek modelu.
- **Model zostaje `Opus / xhigh`.** Sześć kwerend czyta kod, który stoi
  i działa, a okno nie dokłada ani jednego prymitywu, więc trzy renderery
  zostają nietknięte — warunek, dla którego kroki 44 i 47 poszły na `Fable`,
  nie zachodzi.
- **Rachunek kolumn przelicza się jak w kroku 46**: najdłuższa nazwa kwerendy
  z najdłuższym opisem z obu katalogów ma się zmieścić w oknie, a pilnuje tego
  test czytający `pl` i `en`. Tam ta sama rzecz omal nie ucięła najdłuższej
  nazwy zdarzenia i było to widać dopiero w klatce.

**Odrzucone alternatywy:** **jawny wyjątek od reguły 13** (kwerendy bez odbiorcy,
nazwane w planie wzorem `ProgressBar` z kroku 23) — wariant postawiony
użytkownikowi i odrzucony; tamten wyjątek kosztował trzy kroki długu i sam
`SKILL.md` mówi, że nie jest precedensem. **Kwerendy tylko dla przeglądarki**
(jedynego modułu z konsumentem w kodzie) — najtańszy wariant, zgodny z regułą 13
bez żadnego zabiegu, ale zostawiał dwa moduły nieme i odkładał pytanie
o widoczność kwerend na krok, którego nikt nie zaplanował. **Dociągnięcie modułu
`Ssh`** (`ssh.hosts`, zdalne ścieżki) — wiązałoby krok 53 z nierozpoczętą Fazą
XVII, dokładając do jego zależności kroki 48, 49 i 50; dopisanie kwerend
do gotowego modułu kosztuje jedną zdolność i **jest sprawdzianem, że mechanizm
wyszedł dobrze**, więc nie ma powodu robić tego tutaj.

## Decyzje z realizacji kroku 48 (2026-08-15)

### D87 — Rozstrzygnięcia startowe kroku 48: cała sesja w procesie potomnym przez `ControlMaster`, `known_hosts` prowadzi `ssh`, rdzeń rośnie o dwie rzeczy zamiast jednej linii

**Dotyczy:** kroku 48 ([48-ssh-sesja-i-hosty.md](archiwum/48-ssh-sesja-i-hosty.md)),
a przez rozstrzygnięcie nr 1 — **całej Fazy XVII**, czyli także kroków
[49](49-zdalny-katalog.md) i [50](50-przesyl-plikow.md); nowego katalogu
`src/Module/Ssh/`, `Application/Module` (nowa zdolność środowiskowa),
`Application/Module/ModuleRegistry` (piąty powód odrzucenia),
`Presentation/Ui/Component/TextInput` (tryb maskowany),
`Presentation/Cli/Bootstrap` (jedna linia), katalogów napisów,
[docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md)
i `README.md`.

**Data:** 2026-08-15, przed pierwszą linią kodu — osiem pytań z sekcji „Do
rozstrzygnięcia na starcie kroku” plus dwa, które wynikły dopiero z odpowiedzi
na pytanie pierwsze, plus jedno zadane ponownie po sprostowaniu błędu w opisie
wariantu.

**Sprawdzenie stanu zastanego: tabela z planu zgadza się co do wiersza.**
`ext-ssh2` 1.3.1 z 34 funkcjami, `ssh2_fingerprint()` wyłącznie MD5 i SHA1,
ani jednej `ssh2_known_hosts*`, `default_socket_timeout` = 60,
`SSH_AUTH_SOCK` ustawiony, `~/.ssh/known_hosts` z 23 wpisami o zahaszowanych
nazwach, `~/.ssh/config` z dwoma wpisami `Host`, litera `s` wolna, port 22
zamknięty, `src/Module/Ssh/` nie istnieje. Rozpoznanie dołożyło dwa fakty,
o które plan nie pytał, a które przesądziły o kształcie: **klient OpenSSH
9.6p1** wraz z `sftp`, `ssh-keyscan` i `ssh-keygen` jest w `PATH`,
a `HashKnownHosts` stoi na `yes` — czyli wpis dopisany przez `ssh` będzie
zahaszowany tak samo jak 23 istniejące.

**Decyzje użytkownika (1–8 — pytania z planu; 9 i 10 — wynikłe z odpowiedzi na
pytanie 1; 11 — pytanie 7 zadane ponownie po sprostowaniu):**

1. **Cała sesja żyje w procesie potomnym, a nie w procesie aplikacji.** To jest
   **jawne odwrócenie D84 nr 2** („dostęp w procesie, przez `ext-ssh2`”)
   i obejmuje całą Fazę XVII, nie sam krok 48. Powodem był problem, którego plan
   nie umiał rozwiązać inaczej: `ext-ssh2` nie ma ani jednego wywołania
   nieblokującego, więc uścisk dłoni zamrażał klatkę na setki milisekund,
   a `ssh2_connect()` bez limitu czasu zamrażał ją przy hoście nieosiągalnym na
   minutę. Wariant „przyjąć zamrożenie z ograniczeniem od góry” i wariant
   „strażnik na `SIGALRM`” zostały użytkownikowi postawione i odrzucone.
   **Zasób sesji nie przechodzi przez granicę procesu**, więc z tej odpowiedzi
   wynika wprost, że kroki 49 i 50 też idą potomkiem — i to zostało
   użytkownikowi powiedziane przed wyborem, a nie po nim.
2. **Postacią potomka jest klient OpenSSH, nie robotnik PHP.** Rozstrzygnięcie
   brzmiało dosłownie: „całość w procesie potomnym, żadnych robotników, wymiana
   danych z procesem głównym”. `ext-ssh2` wypada przez to z fazy **w całości**
   — razem z nim wypada `SshSessionPort` na rozszerzeniu, `UnavailableSshService`,
   `ssh2_methods_negotiated()` i cały rachunek odcisku rozpisany w zakresie nr 4
   planu. Wariant „robotnik PHP z `ext-ssh2` i własnym protokołem na potoku”
   został postawiony i odrzucony; wariant „tylko uścisk w potomku, sesja
   w rodzicu” też — bo nie rozwiązywał zastrzeżenia, tylko płacił za dwa uściski
   zamiast jednego.
3. **Sesja trwa przez `ControlMaster` + `ControlPersist`.** Mistrz zestawia się
   raz (`ssh -M -N -f -o ControlPath=…`) i **demonizuje się sam**, więc aplikacja
   nie trzyma jego potoków ani przez chwilę. Każda późniejsza operacja to krótki
   potomek wchodzący przez gniazdo **bez uścisku dłoni** — milisekundy zamiast
   setek. Stan sesji to `ssh -O check`, rozłączenie to `ssh -O exit`. Odrzucono
   długo żyjący `sftp` na potokach (rdzeniowy `BackgroundProcessPort` **nie umie
   podać potomkowi wejścia** — granica postawiona świadomie w kroku 26 — więc
   trzeba by własnej obsługi procesu i parsowania znaku zachęty `sftp>`) oraz
   świeży uścisk na każdą operację (zdanie z celu kroku — „nawiązać
   i **utrzymać** sesję” — przestałoby cokolwiek znaczyć).
4. **Hasło wchodzi jako droga uwierzytelnienia, a `TextInput` dostaje tryb
   maskowany.** To jest zmiana **komponentu rdzenia**, świadoma i przyjęta wraz
   z ceną: rekomendacja planu brzmiała odwrotnie („hasło poza zakresem, dopóki
   `TextInput` nie umie ukryć treści”). Odbiorca wchodzi razem z mechanizmem
   (reguła 13), a hasła w pliku nie ma i nie będzie — pytanie pada przy każdym
   połączeniu.
5. **Zapamiętane odciski mieszkają w `~/.ssh/known_hosts`, a prowadzi go `ssh`
   sam.** Aplikacja **nie dotyka tego pliku do zapisu ani razu**: pokazuje własne
   okno groźne z odciskiem, a po zgodzie łączy się z `StrictHostKeyChecking=accept-new`
   i to klient dopisuje wiersz w postaci kanonicznej, zahaszowanej. Rekomendacja
   planu (plik modułu) została odrzucona. **Sprostowanie zapisane, bo zmieniło
   wykonalność:** wariant ten byłby przy `ext-ssh2` **niewykonalny** — wiersz
   `known_hosts` wymaga pełnego klucza publicznego w base64, a rozszerzenie
   oddaje wyłącznie odcisk i nie ma funkcji zwracającej klucz. Dopiero
   rozstrzygnięcie nr 2 uczyniło tę odpowiedź wykonalną.
   **Druga poprawka, wynikła ze sprawdzenia zachowania klienta już po
   rozstrzygnięciu:** `ssh-keyscan` jest jednak w łańcuchu potrzebny — nie do
   zapisu, bo ten robi `ssh`, tylko do **pokazania odcisku w oknie pytania**.
   Powód jest mechaniczny: `ssh` z `StrictHostKeyChecking=yes` przy nieznanym
   hoście wypisuje „No … host key is known for …” na **strumieniu błędów**,
   a `BackgroundState` strumienia błędów świadomie nie niesie (krok 26: `du`
   zasypałby go wierszami „brak dostępu”). Odcisk bierze więc potok
   `ssh-keyscan -T <limit> -p <port> <host> | ssh-keygen -lf -`, sprawdzony na
   maszynie projektu — oddaje `SHA256:…`, czyli **dokładnie ten napis, który
   pokazałby `ssh`**. Rozstrzygnięcie nr 2 zarobiło tu drugi raz: `ssh2_fingerprint()`
   nie umiał SHA256 w ogóle i plan godził się na SHA1.
6. **`~/.ssh/known_hosts` czytamy.** Host, którego `ssh` już zna, nie zatrzymuje
   aplikacji pytaniem. Czyta go **własna klasa czysta** (`KnownHostsReader`:
   HMAC-SHA1 nazwy solą z wpisu), a nie proces potomny — bo to jedyny sposób,
   żeby ekran **przed** połączeniem wiedział, czy pytanie padnie, i jedyny, który
   daje się sprawdzić testem bez ani jednego bajtu w sieci.
7. **Jedna sesja naraz**, zgodnie z rekomendacją i wzorem „jedna praca naraz”
   (11d).
8. **Moduł bierze takt (`NeedsTick`).** Warunek z D82 jest przy potomku spełniony
   wprost, a nie naciągnięty: ktoś musi co klatkę zajrzeć, czy łączenie się
   skończyło, i przeczytać potoki (11d: nieczytany potok zatrzymuje potomka).
   Bez taktu „łączę…” nigdy nie zmieniłoby się w „połączono”.
9. **Potomków uruchamia rdzeniowy `BackgroundProcessPort`**, a nie własna usługa
   modułu. Reguła 15 w czystej postaci — moduł sięga po port rdzenia, jak
   `FileInfo` po `du`. **Cena przyjęta świadomie: jedna praca naraz**, więc
   zestawianie sesji przerwie liczenie `du` w module opisu pliku i odwrotnie.
   Przy `ControlMaster` boli to najmniej, bo potomki są krótkie — mistrz odchodzi
   w tło sam. Odrzucono rozszerzenie portu o wiele prac naraz: to jest zakres
   kroku 51 i zrobione tutaj ruszałoby kontrakt portu, usługę, uchwyt
   i wszystkich dzisiejszych odbiorców.
10. **Autostartu nie ma** — uruchomienie aplikacji nie sięga do sieci ani razu.
    Zgodnie z rekomendacją; zero pozycji ustawień, zero pytania o odcisk, zanim
    użytkownik cokolwiek zrobił.
11. **Brak klienta `ssh` odrzuca moduł, a rejestr dostaje na to zdolność.**
    Rekomendacja planu (moduł przyjęty, ekran mówi czego brak — wzorem 11o)
    została odrzucona. **Pytanie zadano dwa razy, bo pierwszy opis wariantu był
    błędny:** napisano „rejestr taki wariant zna”, a `ModuleRegistry::admit()`
    odrzuca z czterech powodów i wszystkie dotyczą samej deklaracji (zły
    identyfikator, identyfikator zajęty, litera spoza dozwolonych, litera zajęta)
    — mechanizmu „moduł mówi, że w tym środowisku nie ma czym działać” w rdzeniu
    **nie było**. Po sprostowaniu użytkownik wybrał dołożenie zdolności
    `RequiresEnvironment` (`unavailableReason(): ?string`, deklarowana osobno jak
    `NeedsTick`, pytana w `admit()` przed wpuszczeniem).

**Co z tych decyzji wynika dla kroku — i czego dziennik kroku pilnuje:**

- **Kryterium ukończenia „rdzeń urósł o jedną linię” jest odwołane, i to
  jawnie.** Rdzeń rośnie o **trzy** rzeczy: pozycję w `Bootstrapie` (przewidziane
  regułą 15), tryb maskowany `TextInput` (rozstrzygnięcie nr 4) i zdolność
  `RequiresEnvironment` wraz z piątym powodem odrzucenia (rozstrzygnięcie nr 11).
  Obie zmiany ponad linię są **rozstrzygnięciami użytkownika podjętymi wraz
  z ceną wypisaną przed wyborem**, a nie długiem przeoczonym w trakcie. Reguła 15
  zostaje przy tym nietknięta co do treści: obie nowe rzeczy są **mechanizmami
  rdzenia z odbiorcą** (reguła 13), a nie funkcją modułu wepchniętą do rdzenia.
- **`RequiresEnvironment` ma trzeciego i czwartego odbiorcę już zaplanowanych.**
  Kroki 51 (`docker`) i 52 (`kubectl`) potrzebują dokładnie tego samego pytania,
  więc mechanizm nie powstaje na zapas. Cena, którą trzeba znać: `admit()` dzieje
  się przy starcie aplikacji, więc sprawdzenie obecności programu wchodzi
  w ścieżkę uruchomienia — i musi być tanie.
- **Zakres nr 4 planu (odcisk klucza hosta) traci połowę treści i zyskuje
  nową.** Rachunek `ssh2_methods_negotiated()` → SHA1 z base64 →
  `ssh2_fingerprint()` znika razem z rozszerzeniem. Zostaje: `KnownHostsReader`
  czyta plik i mówi, czy host jest znany; nieznany → okno groźne z odciskiem →
  zgoda → `accept-new` i wiersz dopisuje `ssh`; klucz **niezgodny** to nie
  pytanie, tylko odmowa — i tutaj nie musimy jej pisać sami, bo `ssh` odmawia
  z własnym komunikatem, a moduł go pokazuje.
- **Kroki 49 i 50 zmieniają drogę techniczną, nie zakres.** Zdalny katalog czyta
  `sftp -o ControlPath=…`, a nie opakowanie `ssh2.sftp://`; przesył idzie tą samą
  drogą. Zastrzeżenie startowe kroku 50 (wyjątek 15b na zapis pobranego pliku)
  **zostaje otwarte** — potomek nie zmienia tego, kto pisze po dysku lokalnym.
  Pytanie z D84 o to, czy `opendir()` na opakowaniu oddaje atrybuty, **odpada
  bezprzedmiotowo**: `sftp ls -l` oddaje je razem z nazwą, więc rekomendacja
  „stat tylko dla widocznego okna” przestaje być potrzebna.
- **Faza przestaje zależeć od rozszerzenia PHP i zaczyna od programu w `PATH`.**
  `ext-ssh2` nie wchodzi do `suggest` w `composer.json`, bo nie jest już do
  niczego potrzebne; wymaganiem jest klient OpenSSH, a jego brak odrzuca moduł
  (nr 11). To jest **inna postać degradacji** niż przy `ext-glfw` w Fazie IX
  i w module dźwięku, i różnica jest zamierzona: tam brak rozszerzenia zostawiał
  moduł działający na pustym obiekcie, tu nie ma czego zostawić.
- **Reguła nadrzędna fazy zostaje w mocy i staje się łatwiejsza, nie
  trudniejsza:** żadne wywołanie sieciowe nie pada w rysowaniu klatki — bo żadne
  nie pada w procesie aplikacji w ogóle.

**Odrzucone alternatywy** (poza wymienionymi przy decyzjach): **`pcntl_alarm()`
jako strażnik zawieszonego uścisku** — postawiony i odrzucony razem z całym
wariantem „sesja w procesie”; niesprawdzalny bez serwera, bo libssh2 nie musi
poprawnie znieść EINTR w środku uścisku. **`ssh-keyscan` jako źródło klucza
**do zapisu** w `known_hosts`** — odrzucone: wpis pisze `ssh`, więc keyscan
kosztowałby wpis niezahaszowany albo własne HMAC-owanie nazwy. Do **pokazania**
odcisku keyscan zostaje, z powodu opisanego przy rozstrzygnięciu nr 5. **Własna usługa
procesu w module** (`SshProcessService`) — legalna wobec reguły 15, bo moduł ma
prawo do własnej warstwy `Infrastructure`, ale byłaby drugim w projekcie
miejscem robiącym `proc_open`, z własnym sprzątaniem i własnym limitem czasu,
czyli powtórzeniem kroku 26 w module.

### D88 — Rozstrzygnięcia startowe kroku 49: jeden ekran w dwóch postaciach, kontekst dostaje pochodzenie, a polecenie, którego wyjściem jest treść, nie scala strumieni

**Dotyczy:** kroku 49 ([49-zdalny-katalog.md](49-zdalny-katalog.md)),
`src/Module/Ssh/` (własna domena plikowa, odczyt, drugi ekran),
`Application/Module/ContextOrigin` i `ModuleContext` (pochodzenie),
`Application/Dto/BackgroundState` oraz
`Infrastructure/Process/BackgroundProcessService` (strumień błędów i limit
z konfiguracji), `Application/Dto/{Settings,SettingKey,SettingsTab}` (trzecia
zakładka rdzenia), modułu `FileInfo` (odbiorca pochodzenia), katalogów napisów,
[docs/architecture.md](../architecture.md),
[SKILL.md](../../.claude/skills/light-manager-conventions/SKILL.md)
i [docs/pomiary/README.md](../pomiary/README.md).

**Data:** 2026-08-15, przed pierwszą linią kodu — osiem pytań z sekcji „Do
rozstrzygnięcia na starcie kroku”, dwa wynikłe z odpowiedzi na nie oraz jedno
zadane **w trakcie**, po tym jak próba z żywym serwerem wykryła cichą utratę
danych.

**Sprawdzenie stanu zastanego: tabela z planu rozminęła się z rzeczywistością
w jednym miejscu, za to zasadniczym.** `ext-ssh2` nie jest już drogą tej fazy
(D87 nr 1 i 2), więc pytanie „czy `opendir()` na opakowaniu oddaje atrybuty”
straciło adresata. Zadano je na nowo `sftp`-owi, na żywym serwerze SFTP
(kontener `atmoz/sftp:alpine`, OpenSSH 9.6), **przed pierwszą linią kodu** — i to
ten pomiar, a nie wybór między wariantami, przesądził o kształcie kroku:

| Fakt | Liczba |
|---|---|
| `sftp ls -l` przez stojącego mistrza | **jeden obieg** oddaje nazwę, rodzaj, prawa, rozmiar i datę |
| koszt wywołania | ~0,93 s — koszt **otwarcia kanału** (to samo kosztuje `ssh … true`), nie listowania |
| pięć tysięcy wpisów ponad to | +0,1 s, 419 KB wypisu |
| rozczytanie tych wpisów w PHP | **3,2 ms** |
| postać wypisu | składa go **klient**, nie serwer, i nie zależy od ustawień językowych |

**Decyzje użytkownika (1–8 — pytania z planu; 9 i 10 — wynikłe; 11 — zadane
w trakcie):**

1. **Jeden ekran w dwóch postaciach.** Spis hostów ustępuje zdalnemu katalogowi
   po połączeniu i wraca po rozłączeniu; `F3` zagląda do spisu przy żywej sesji
   (`F2` należy do rdzenia — pomyłkę złapał przebieg funkcjonalny, który zamiast
   spisu otworzył ustawienia). Odrzucono podział ekranu (`Split` dałby tabeli
   o czterech kolumnach połowę szerokości) i kontrakt oddający wiele ekranów
   (zmiana rdzenia). **Postać zmienia takt, a nie klawisz**: połączenie kończy
   się w procesie potomnym, więc chwili „jest sesja” nie zna żaden klawisz.
2. **Granica powtarzania domeny plikowej jest podwójna.** *Jakościowa:* wolno
   powtórzyć **pojęcia** (ścieżka, wpis, rodzaj, filtr, porządek), nie wolno
   powtórzyć **mechanizmu rdzenia** (praca kawałkowa, komponenty, zdarzenia,
   proces tłowy, zakresy dopasowania). *Ilościowa:* **trzeci** moduł z własną
   domeną plikową uruchamia przegląd „czy to nadal powtórzenie”. Zapisane
   w `SKILL.md` jako reguła 15e.
3. **Atrybuty przychodzą jednym obiegiem** — wbrew rekomendacji planu, bo
   rekomendacja (`stat` kawałkowo dla widocznego okna) chroniła przed kosztem,
   którego przy tej drodze nie ma. **Praca kawałkowa została jednostopniowa,
   a budżet mierzony zegarem — zapowiadany jako główna trudność kroku — nie
   powstał w ogóle.** Z wzorca D46 zostaje to, co się liczy: praca jest daną
   oglądaną co klatkę.
4. **Wpisy ukryte: pozycja ustawień i klawisz `Ctrl`+`H`**, jak w przeglądarce —
   z ceną, której tamta nie ma: przełączenie znaczy **nowy obieg do serwera**, bo
   `sftp ls` bez `-a` wpisów zaczynających się kropką w ogóle nie przysyła.
5. **Filtr nazwy wchodzi wraz z podświetleniem dopasowania** — wbrew
   rekomendacji planu, który proponował odłożyć go w całości. Powtórzone jest
   **zachowanie**, nie mechanizm: zakresy liczy rdzeniowy `TextSpan` z kroku 30.
6. **Kontekst dostaje pochodzenie** (`ContextOrigin`) — wbrew rekomendacji
   planu, która brzmiała „ekran zdalny nie publikuje kontekstu”. Powód wyboru
   jest ten sam, co powód, dla którego plan się wahał: kontekst niósł ścieżkę
   jako napis, więc ekran zdalny publikujący `/var/log` kazałby modułowi opisu
   pliku pokazać **lokalny** `/var/log` — kłamstwo ciche, bo obie ścieżki
   istnieją i obie się czytają.
7. **Po rozłączeniu wraca spis hostów** — także po zerwaniu niechcianym. Powód
   zerwania mówi pasek stanu; lista zostawiona na ekranie nie miałaby czego
   dodać poza wrażeniem, że wciąż działa.
8. **Dowiązania pokazujemy tak, jak widzi je `lstat`, a `Enter` po prostu
   próbuje wejść.** Rozstrzygnięcie, dokąd prowadzą, kosztowałoby **obieg na
   każde dowiązanie w katalogu** — czyli dokładnie to, czego zastrzeżenie
   startowe kazało unikać.
9. **Limit wyjścia pracy tłowej wchodzi do konfiguracji** (`backgroundOutputKib`,
   domyślnie 1 MiB, trzecia zakładka rdzenia „Zasoby”). Pytania tego plan nie
   miał: dawna stała 64 KiB była dobrana pod polecenia oddające jeden wiersz
   (`du -s`, `file -b`) i urywała listę katalogu **po cichu** na siedmiuset
   wpisach. Odrzucono plik roboczy i przyjęcie obcięcia ze znakiem.
10. **Moduł opisu pliku pokazuje wpis zdalny z tego, co już wiadomo** — nazwa,
    host, rozmiar, data i prawa, wszystko z kontekstu, bez ani jednego dotknięcia
    dysku i sieci. To jest odbiorca, bez którego pochodzenie byłoby mechanizmem
    bez użytkownika (reguła 13). Cena: kontekst niesie trzy atrybuty zaznaczenia
    więcej, a suma kontrolna i zajętość odmawiają pracy zdaniem mówiącym dlaczego.
11. **Port pracy tłowej niesie strumień błędów osobnym polem.** Pytanie padło
    **w trakcie**, po tym jak próba z żywym serwerem wykryła, że katalog o pięciu
    tysiącach wpisów przychodzi jako 1551 — z kodem wyjścia zero. Użytkownik
    odrzucił obejście przez plik roboczy i polecił **znaleźć przyczynę**; ta
    okazała się leżeć w naszym własnym `2>&1` (patrz niżej). Naprawą jest zakaz
    scalania, a osobne pole jest tym, co pozwala go dotrzymać bez utraty powodu
    niepowodzenia.

**Przyczyna cichej utraty danych — bo to ona jest najtrwalszym wynikiem tego
kroku.** Polecenie kończyło się na `2>&1`, więc strumień błędów potomka i potok
z listą to **ten sam opis pliku**. `sftp` uruchamia `ssh`, a ten przy
`ControlPath` jest **klientem multipleksera**: przekazuje swoje deskryptory
mistrzowi połączenia, który obsługuje wiele sesji w jednej pętli i dlatego
ustawia im tryb nieblokujący. Tryb jest własnością **opisu pliku**, więc wracał
tym samym potokiem na wyjście `sftp`; odkąd potok się zapełnił (pętla klatek
opróżnia go raz na 33 ms), `write()` zwracał `EAGAIN`, a OpenSSH porzucał porcję
wypisu i kończył się kodem zero. Dowód A/B: flagi deskryptora wyjścia potomka to
`04001` z `2>&1` (130 KB z 419 KB) i `01` bez niego (419 KB z 419 KB). Rdzeń
i PHP są niewinne — port przeczytał 400 KB od równie wolno piszącego polecenia,
a stratę odtworzono **bez PHP**, samą powłoką z pauzami.

**Co z tych decyzji wynika dla kroku:**

- **Kryterium „rdzeń kosztuje jedną linię” jest odwołane, i to jawnie.** Rdzeń
  rośnie o **pięć** rzeczy: pozycję w `Bootstrapie` (stała tam od kroku 48),
  limit wyjścia w konfiguracji wraz z trzecią zakładką ustawień (nr 9),
  pochodzenie w `ModuleContext` (nr 6), odbiorcę pochodzenia w module opisu pliku
  (nr 10) i strumień błędów w `BackgroundState` (nr 11). Wszystkie są
  rozstrzygnięciami użytkownika podjętymi z ceną wypisaną **przed** wyborem,
  a nie długiem przeoczonym w trakcie.
- **Zasada z kroku 26 zostaje w mocy i zyskuje mocniejsze uzasadnienie.**
  „Strumieni się nie skleja” było dotąd argumentem o czytelności (`du` zasypuje
  strumień błędów); od tego kroku wiadomo, że sklejanie potrafi **zepsuć dane**.
  Pola są rozdzielone właśnie po to, żeby nikt niczego nie sklejał.
- **Kroki 50 i 51 dostają to za darmo.** Przesył plików pójdzie tą samą drogą
  (i tym samym zakazem), a moduł `docker` — pierwszy odbiorca limitu z konfiguracji
  poza tym krokiem, bo `docker logs` jest poleceniem, którego wyjściem jest treść.

**Odrzucone alternatywy** (poza wymienionymi przy decyzjach): **plik roboczy jako
droga wypisu** — sprawdzony i działający (komplet 419 KB), odrzucony przez
użytkownika, bo leczyłby objaw zamiast przyczyny; **`| cat` w potoku** — zmniejszał
stratę (211 KB zamiast 130 KB), więc był obejściem gorszym od tamtego;
**wykrywanie obcięcia po liczbie wpisów** — `sftp` nie mówi, ile miało być, więc
nie da się tego wykryć pewnie.
