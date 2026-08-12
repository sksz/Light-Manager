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
[01-warstwy-ddd-i-struktura-katalogow.md](01-warstwy-ddd-i-struktura-katalogow.md)).
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
[02-wzorzec-singleton-i-bootstrap.md](02-wzorzec-singleton-i-bootstrap.md)).
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
[03-standardy-stylu-kodowania.md](03-standardy-stylu-kodowania.md)).
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
[06-terminal-io.md](06-terminal-io.md)).
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
[07-wykrywanie-sixel.md](07-wykrywanie-sixel.md)).
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
[08-render-imagick-sixel.md](08-render-imagick-sixel.md)).
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
[09-petla-glowna.md](09-petla-glowna.md)).
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
[10-nawigacja-fs.md](10-nawigacja-fs.md)).
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
[11-render-listy-plikow.md](11-render-listy-plikow.md)).
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
w [12-podglad-miniatur.md](12-podglad-miniatur.md)).

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

**Dotyczy:** kroku 13 (pełna treść: [13-motyw-graficzny.md](13-motyw-graficzny.md)).

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
[14-konfiguracja-i-ekran-ustawien.md](14-konfiguracja-i-ekran-ustawien.md)).

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
w [13-motyw-graficzny.md](13-motyw-graficzny.md)).

**Decyzja:**
- **Degradacja układu ustępuje po kolei, zaczynając od pasa podglądu.**
  Panele zostają w każdym oknie, w którym się mieszczą; progi: 26 wierszy
  (pas podglądu), 18 (obwódka ścieżki), 12 (obwódka paska stanu), 8
  (obwódka listy). Lista dostaje zawsze co najmniej jeden wiersz.
- **Pasek stanu jako osobny panel z obwódką** (3 wiersze), wiernie wobec
  makiety, zamiast jednowierszowej belki.
- **Etykiety stref po angielsku** (`PATH`, `FILES`, `PREVIEW`), a
  wielojęzyczność całego interfejsu jako osobny krok planu —
  [krok 15](15-wielojezycznosc.md).
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
[16-narzedzia-diagnostyczne-wydajnosci.md](16-narzedzia-diagnostyczne-wydajnosci.md)).

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
[17-optymalizacja-wydajnosci.md](17-optymalizacja-wydajnosci.md)).

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
[20-moduly-plugins.md](20-moduly-plugins.md)).

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
[14-konfiguracja-i-ekran-ustawien.md](14-konfiguracja-i-ekran-ustawien.md)).

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
[15-wielojezycznosc.md](15-wielojezycznosc.md)).

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
[16-narzedzia-diagnostyczne-wydajnosci.md](16-narzedzia-diagnostyczne-wydajnosci.md)).

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
[17-optymalizacja-wydajnosci.md](17-optymalizacja-wydajnosci.md)).

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
[18-komponenty-i-plaszczyzny.md](18-komponenty-i-plaszczyzny.md),
[19-okno-komend.md](19-okno-komend.md) i
[20-moduly-plugins.md](20-moduly-plugins.md).

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
[18-komponenty-i-plaszczyzny.md](18-komponenty-i-plaszczyzny.md)).

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

**Dotyczy:** kroku 20 (pełna treść: [20-moduly-plugins.md](20-moduly-plugins.md)).
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

**Dotyczy:** kroku 19 (pełna treść: [19-okno-komend.md](19-okno-komend.md)).
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
kroku” i „Odstępstwa od planu” w [19-okno-komend.md](19-okno-komend.md)).

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
[21-przegladarka-jako-modul.md](21-przegladarka-jako-modul.md)) oraz dwóch
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
kroku” i „Odstępstwa od planu” w [20-moduly-plugins.md](20-moduly-plugins.md)).

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
kroku” i „Odstępstwa od planu” w [21-przegladarka-jako-modul.md](21-przegladarka-jako-modul.md)).

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

**Dotyczy:** kroków 22, 23, 24 i 25 (pełna treść: [22-zwijana-sekcja.md](22-zwijana-sekcja.md),
[23-pasek-postepu.md](23-pasek-postepu.md), [24-podzial-ekranu.md](24-podzial-ekranu.md),
[25-pelny-obraz-pliku.md](25-pelny-obraz-pliku.md)).

**Data:** 2026-08-10.

**Decyzja** — dziewięć rozstrzygnięć użytkownika na starcie kroku „pełny obraz
stanu pliku” (P1–P9 w [25-pelny-obraz-pliku.md](25-pelny-obraz-pliku.md)), z których
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

**Dotyczy:** kroku 23 (pełna treść: [23-pasek-postepu.md](23-pasek-postepu.md)).

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

**Dotyczy:** kroku 24 (pełna treść: [24-podzial-ekranu.md](24-podzial-ekranu.md)).

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
[25-pelny-obraz-pliku.md](25-pelny-obraz-pliku.md), [26-proces-tlowy.md](26-proces-tlowy.md)).

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

**Dotyczy:** kroku 26 (pełna treść: [26-proces-tlowy.md](26-proces-tlowy.md)).

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

**Dotyczy:** kroku 27 (pełna treść: [27-tabela-kolumn.md](27-tabela-kolumn.md)).

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

**Dotyczy:** kroku 33 (pełna treść: [33-reakcja-na-zmiane-rozmiaru.md](33-reakcja-na-zmiane-rozmiaru.md))
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

**Dotyczy:** kroku 33 (pełna treść: [33-reakcja-na-zmiane-rozmiaru.md](33-reakcja-na-zmiane-rozmiaru.md)).

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

**Dotyczy:** kroków 34 i 35 (pełna treść: [34-okno-glfw.md](34-okno-glfw.md),
[35-renderer-opengl.md](35-renderer-opengl.md)) i struktury planu
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

**Dotyczy:** kroku 34 (pełna treść: [34-okno-glfw.md](34-okno-glfw.md)).

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

**Dotyczy:** kroku 35 (pełna treść: [35-renderer-opengl.md](35-renderer-opengl.md)).

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

**Dotyczy:** kroku 36 (pełna treść: [36-odtwarzanie-muzyki.md](36-odtwarzanie-muzyki.md))
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

**Dotyczy:** kroku 28 (pełna treść: [28-okno-potwierdzenia.md](28-okno-potwierdzenia.md)).

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

**Dotyczy:** kroku 37 (pełna treść: [37-dopracowanie-okna.md](37-dopracowanie-okna.md))
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
