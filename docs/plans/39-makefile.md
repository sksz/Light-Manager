# Krok 39 — Makefile: środowisko, instalacja, jakość, testy i budowa

> **Skąd ten krok.** Żądanie użytkownika z 2026-08-13
> ([00-decyzje.md](00-decyzje.md), D62): jeden `Makefile` ma zebrać wejścia do
> projektu — weryfikację środowiska, instalację, analizę jakości kodu, testy
> (jednostkowe, funkcjonalne, wydajnościowe) oraz budowę aplikacji Composerem.
> Uzupełnienie z tego samego dnia (D63): krok ma **domknąć rzecz dokumentami** —
> `CLAUDE.md` i `docs/architecture.md` mają odtąd wskazywać `make` i narzędzia
> repozytorium (`bin/render-bench`, `bin/terminal-probe`) jako drogę do
> wszystkich procesów aplikacji, żeby plik nie skończył jako wygodny skrót,
> o którym nikt nie pamięta.

## Status

**Nie rozpoczęty** (2026-08-13).

## Cel

Wejścia do projektu są dziś rozsypane po trzech miejscach i żadne z nich nie
wie o pozostałych: wymagania środowiska opisuje README prozą, polecenia jakości
mieszkają w `composer.json` (`cs`, `cs:check`, `stan`, `test`), a narzędzia mają
własne skrypty powłoki w `bin/`. Skutek jest zawsze ten sam: wymagania
sprawdzają się **dopiero uruchomieniem**, a najczęstsza porażka nie jest błędem
w kodzie, tylko środowiskiem — brak kodera SIXEL, brak `pcntl`, Composer
wywracający się na `imagick`+`openswoole` (README opisuje to jako znane
ograniczenie środowiska).

Krok nie wnosi nowej wiedzy o projekcie — wnosi **jedno wejście** do wiedzy,
która już jest, i dokłada jedno pojęcie, którego dotąd nie było: **budowę**.

Druga połowa celu jest dokumentacyjna i bez niej pierwsza się nie utrzyma.
`Makefile`, o którym dokumenty milczą, przegrywa z nawykiem w tydzień: ktoś
(człowiek albo agent) napisze `vendor/bin/phpunit --filter …`, ktoś inny
zmierzy wydajność własną pętlą `microtime()` zamiast `bin/render-bench`, i po
kilku krokach planu wejście znów będzie wiedzą plemienną — tyle że w czterech
miejscach zamiast trzech. Dlatego `CLAUDE.md`, `docs/architecture.md` i Skill
konwencji mają po tym kroku mówić **jednym głosem**: procesy aplikacji
uruchamia się celami `make`, a tam, gdzie projekt ma własne narzędzie
(`bin/render-bench`, `bin/terminal-probe`), używa się **jego**, zamiast dorabiać
zastępnik doraźnie.

Miarą powodzenia jest zdanie: **`make` bez argumentów wypisuje spis wejść;
`make check-env` na świeżym klonie mówi, czego brakuje, zanim cokolwiek zostanie
zainstalowane; `make qa` jest dokładnie tą bramką, którą dziś wywołuje się
ręcznie z pamięci; pomiar wydajności zostaje poza nią — tak jak rozstrzygnął
krok 16 — a dokumenty projektu nie znają żadnej innej drogi do tych procesów
niż ta.**

Obowiązuje przy tym reguła, od której zależy, czy plik zestarzeje się dobrze:
**cel `make` nie może być jedynym miejscem, w którym wiedza istnieje.** Makefile
ma wołać `composer`, `bin/render-bench` i `phpunit` — nie powtarzać ich
konfiguracji własnymi słowami, bo druga definicja tej samej rzeczy rozjeżdża się
z pierwszą w tygodniu, w którym nikt nie patrzy.

## Zależności

- **Krok 04** twardo w części dokumentacyjnej — to tam powstała trójka
  `docs/architecture.md` + `SKILL.md` + `CLAUDE.md` wraz z podziałem ról (D8,
  D13): pełna treść w dokumencie źródłowym, skrót operacyjny w Skillu,
  a w `CLAUDE.md` **krótki, bezwarunkowo ładowany wskaźnik**. Reguła procesu
  wchodzi w ten sam podział, a nie obok niego — inaczej `CLAUDE.md` spuchnie
  do drugiej instrukcji.
- **Krok 05** twardo — `composer.json`, skrypty jakości i lista wymagań w README
  powstały tam. Makefile ich nie zastępuje, tylko daje im wspólne wejście.
- **Kroki 07 i 08** — wymagania toru graficznego (koder `SIXEL` w Imagicku,
  degradacja do trybu tekstowego przy jego braku), które `check-env` ma umieć
  nazwać, nie zgadując przy tym więcej, niż potrafi bez terminala.
- **Krok 16** — cel pomiarowy opakowuje `bin/render-bench`; stamtąd też pochodzi
  rozstrzygnięcie, którego ten krok **nie wolno cicho odwrócić**: bramki
  wydajności nie ma.
- **Krok 34** — `glfw` jest wymogiem **opcjonalnym**, wyłącznie dla `--window`;
  jego brak nie może psuć żadnego celu.
- **Krok 38** — miękko, ale realnie: podział `test-unit` / `test-functional`
  potrzebuje katalogu przebiegów funkcjonalnych, którego miejsce rozstrzyga
  pytanie nr 6 tamtego kroku. Zalecana kolejność to **38 przed 39**; odwrotna
  jest dopuszczalna, o ile rozstrzygnięcie nr 5 tego kroku przesądzi podział
  testsuite tutaj, a krok 38 tylko dołoży do niego treść.

Od kroków **31, 32, 36 i 37** nie zależy i one nie zależą od niego. Kodu
aplikacji nie dotyka w ogóle — `src/` zostaje nietknięte.

## Model i wysiłek

**Opus / medium** — objętość jest mała (jeden plik i kilka wierszy
dokumentacji), ale trzy rozstrzygnięcia są łatwe do zepsucia i drogie do
cofnięcia: skład bramki jakości (co w niej jest, a co udaje, że jest),
sprawdzenie środowiska działające **przed** instalacją zależności oraz
znaczenie słowa „budowa” w projekcie, który niczego nie kompiluje.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Wejście | Stan dziś |
|---|---|
| Wymagania środowiska | proza w README: PHP `^8.3`, `imagick`, `pcntl`, `stty`, koder `SIXEL`, Composer 2.x; `glfw`, terminal sixelowy i `intl` opcjonalnie — **nic tego nie sprawdza przed uruchomieniem** |
| Preflight w kodzie | [bin/light-manager:15-39](../../bin/light-manager#L15-L39) (brak `vendor/`, brak `imagick`, brak `glfw` pod `--window`), `ImagickCapabilityService` (koder `SIXEL`), `SixelCapabilityService` (DA1 — **wymaga interaktywnego terminala**) |
| Instalacja | `composer install`; znane ograniczenie: SIGSEGV Composera przy załadowanych `imagick`+`openswoole`, obejście przez `PHP_INI_SCAN_DIR` opisane w README |
| Jakość | `composer cs`, `cs:check` (PHP-CS-Fixer, `src` + `tests`), `composer stan` (PHPStan `max`, `src` + `tests` + `lang`) |
| Testy | **jedna** testsuite `LightManager` nad całym `tests/` (83 pliki); przebiegi funkcjonalne (`GameLoopTest`, `CommandWindowFlowTest`, `BrowserSplitTest`, `TextScrollTest`) siedzą wśród jednostkowych i niczym się nie wyróżniają |
| Wydajność | `bin/render-bench` oraz `bin/run-render-bench.sh` (XTerm — jedyna droga do `--transfer`); poza bramką **z rozmysłu** (krok 16) |
| Budowa | **nie istnieje** — nie ma celu, katalogu wyniku ani ustalenia, co „zbudowana aplikacja” w tym projekcie znaczy |
| Skrypty pomocnicze | `bin/run.sh`, `bin/run-terminal-probe.sh`, `bin/run-render-bench.sh` — każdy powtarza ten sam zestaw zasobów XTerma |
| `.gitignore` | `vendor`, `*.cache` — katalogu wyniku budowy nie zna |
| `docs/architecture.md` | rozdz. 4 „Standardy PHP i narzędzia” kończy się wierszem „Skróty uruchomieniowe: `composer test`, `composer stan`, `composer cs`, `composer cs:check`” — **cztery z ośmiu procesów projektu**; o sprawdzeniu środowiska, pomiarze, budowie i uruchomieniu dokument źródłowy nie mówi nic |
| `SKILL.md` | zna reguły kodu i `@phpstan-ignore`, ale **nie zna ani jednego polecenia** — agent piszący kod nie dowie się z niego, czym sprawdzić swoją pracę |
| `CLAUDE.md` | wskazuje dokumenty i niesie regułę pomiaru („poproś o zwolnienie mocy hosta i **poczekaj na potwierdzenie**”) — jedyne miejsce, które w ogóle mówi o procesie, i to o jednym |

## Zakres

### 1. Kształt pliku i `make help`

Jeden `Makefile` w korzeniu, GNU Make, POSIX-owa powłoka (README zamyka rzecz
do Linuksa i macOS-a — Windows i tak nie jest wspierany). Cel domyślny to
**`help`**: spis celów z jednozdaniowymi opisami, generowany z komentarzy przy
samych celach, żeby spis nie był trzecim miejscem do aktualizowania. Wszystkie
cele w `.PHONY` poza tymi, które naprawdę są plikami (patrz pkt 3).

Cel, którego nie ma w `help`, nie istnieje — to jedyny sposób, żeby plik
pozostał czytelną mapą, a nie zbiorem skrótów znanych jednej osobie.

### 2. `make check-env` — weryfikacja środowiska

Cel odpowiada na pytanie „czy ta maszyna udźwignie ten projekt”, i ma
odpowiadać **na świeżym klonie, przed `composer install`**. To ograniczenie
przesądza o kształcie: sprawdzenie nie może korzystać z autoloadera, więc nie
sięgnie po `ImagickCapabilityService` ani po katalog napisów — jego komunikaty
z konieczności staną obok reguły z kroku 15, dokładnie tak, jak stoi już
jedyny taki napis w aplikacji ([bin/light-manager:13-16](../../bin/light-manager#L13-L16)).
Gdzie ta logika mieszka — pytanie nr 2.

Wymogi rozpadają się na trzy rodzaje i **to rozróżnienie jest treścią celu**,
nie ozdobą — bo od niego zależy kod wyjścia:

| Wymóg | Rodzaj | Czego dotyczy |
|---|---|---|
| PHP `^8.3` | twardy | uruchomienie czegokolwiek |
| `ext-imagick`, `ext-pcntl` | twardy | tor sixelowy i sygnały |
| `stty` | twardy | tryb surowy terminala |
| Composer 2.x | twardy dla instalacji i budowy | `make install`, `make build` |
| koder `SIXEL` w ImageMagicku | **do rozstrzygnięcia** (pytanie nr 1) | bez niego aplikacja startuje, ale schodzi do trybu tekstowego |
| `ext-glfw` | opcjonalny | wyłącznie `--window` (krok 34) |
| `ext-intl` | opcjonalny | sortowanie i tłumacz mają degradację |
| `xterm` | opcjonalny | `bin/run*.sh` i pomiar `--transfer` |
| terminal z Sixelem (DA1) | **niesprawdzalny tutaj** | wymaga interaktywnego terminala i trybu surowego — zostaje `bin/terminal-probe`, a `check-env` ma to powiedzieć wprost, zamiast udawać, że sprawdził |

Brak wymogu twardego kończy się kodem różnym od zera — dzięki temu inne cele
mogą się o `check-env` oprzeć zamiast powtarzać sprawdzenia.

### 3. `make install` — instalacja

`composer install` z `check-env` jako zależnością — instalacja na maszynie bez
`imagick` ma się nie zaczynać, zamiast kończyć w połowie.

Cel plikowy, nie fikcyjny: **`vendor/autoload.php` zależy od `composer.json`
i `composer.lock`**, więc powtórzony `make install` nie robi nic, a cele jakości
i testów mogą wskazać `vendor/autoload.php` jako zależność i tym samym działać
na świeżym klonie bez tajemniczego błędu o brakującej klasie.

Otwarte zostaje, czy Makefile niesie obejście SIGSEGV Composera z README
(`PHP_INI_SCAN_DIR` + `--ignore-platform-req=ext-imagick`) jako wariant celu,
czy zostawia je w dokumentacji — pytanie nr 9.

### 4. Analiza jakości: cele składowe i `make qa`

Cele `cs`, `cs-check`, `stan` opakowują to, co już istnieje — pytanie tylko,
**przez co** wołają: przez skrypty Composera (definicja zostaje w jednym
miejscu, kosztem warstwy pośredniej) czy przez `vendor/bin/*` wprost (krócej,
ale wtedy `composer.json` i `Makefile` opisują to samo dwa razy). To jest
dokładnie ten wybór, przed którym ostrzega reguła z „Celu” — pytanie nr 3.

`make qa` to nazwana bramka: dziś istnieje wyłącznie jako nawyk („PHPStan bez
błędów, CS bez uwag, testy zielone” w kryteriach każdego kroku). Kolejność ma
znaczenie praktyczne — najtańsze pierwsze, żeby literówka w stylu nie czekała
na pełny przebieg testów. Przy `make -j` kolejność zależności nie jest
gwarantowana, więc bramka musi być albo recepturą sekwencyjną, albo jawnie
oznaczona `.NOTPARALLEL` — inaczej „bramka” raz na jakiś czas kłamie
o kolejności, w której się przewróciła. Czy zatrzymuje się na pierwszym błędzie,
czy przechodzi do końca ze zbiorczym podsumowaniem — pytanie nr 4.

### 5. Testy: jednostkowe, funkcjonalne, wydajnościowe

`make test` zostaje pełnym przebiegiem PHPUnita. Rozbicie na `test-unit`
i `test-functional` wymaga jednak czegoś, czego dziś nie ma: **granicy między
nimi**. Testy przebiegów istnieją, ale leżą wymieszane z jednostkowymi, a ich
docelowe miejsce rozstrzyga pytanie nr 6 kroku 38. Możliwe drogi (pytanie nr 5):
testsuites w `phpunit.xml.dist` nad osobnymi katalogami, podział po atrybutach
grup, albo odłożenie rozbicia do czasu ukończenia kroku 38.

Cele wydajnościowe stoją **osobno i celowo poza bramką**. Krok 16 odrzucił
bramkę wydajności, bo rozrzut czyni ją generatorem fałszywych alarmów, a rozrzut
nie zniknął; `CLAUDE.md` idzie dalej i wymaga, żeby **przed pomiarem poprosić
użytkownika o zwolnienie mocy hosta i poczekać na potwierdzenie**. Wygodny cel
`make bench` działa wprost przeciwko tej regule, jeśli daje się uruchomić
odruchowo — dlatego pytanie nr 6 dotyczy nie tego, czy cel powstanie, tylko czy
wymaga jawnego potwierdzenia i które wejścia dostają własne cele (`bin/render-bench`,
tor okienkowy `--window`, `bin/run-render-bench.sh` pod XTermem dla `--transfer`).
Wybór wzorca i próg odmowy przy obciążeniu **należą do kroku 38**, nie tutaj —
Makefile ma delegować, nie dorabiać drugiej polityki pomiaru.

### 6. `make build` — budowa aplikacji (kształt do rozstrzygnięcia)

Aplikacja w PHP niczego nie kompiluje, więc „budowa” nie ma tu jednego
oczywistego znaczenia i **to jest główne pytanie kroku** (nr 7). Cztery drogi,
w kolejności rosnącego kosztu:

| Droga | Co daje | Czym płaci |
|---|---|---|
| zoptymalizowany autoloader w miejscu (`dump-autoload --optimize --classmap-authoritative`) | szybszy start | to przyspieszenie, nie budowa — nie powstaje nic, co da się przenieść |
| katalog dystrybucyjny `build/` (`install --no-dev` + `bin/`, `src/`, `lang/`) | uruchamialny bez Composera na maszynie docelowej; **zero nowych zależności** | trzeba świadomie wypisać, co wchodzi (i że `tests/`, `docs/` nie) |
| archiwum z tego katalogu | jeden plik do przeniesienia | wymaga wersji w nazwie, a `composer.json` wersji nie niesie |
| PHAR | jeden plik wykonywalny | builder jako nowa zależność deweloperska, `phar.readonly=0` przy budowaniu, katalogi napisów i fonty czytane spod `phar://` do sprawdzenia |

Jedna przeszkoda odpada z góry: konfiguracja i historia komend idą do katalogu
domowego (`SettingsService`, `CommandHistoryService` czytają `HOME`), więc
niezapisywalny wynik budowy niczego nie blokuje.

Wynik trafia do `build/`, a `build/` do `.gitignore`.

### 7. Sprzątanie

`make clean` usuwa to, co narzędzia wytworzyły: `.php-cs-fixer.cache`,
`.phpunit.result.cache`, `build/`. `make dist-clean` dokłada `vendor/`.
Granica jest ostra z rozmysłu — cel sprzątający, który kasuje o jeden katalog
za dużo, kasuje go komuś raz i zostaje zapamiętany na zawsze. Żaden z nich nie
tyka `docs/pomiary/` (wzorce są w repozytorium celowo, D33) ani konfiguracji
użytkownika w `HOME`.

### 8. Dokumenty: `make` jako wejście do procesów projektu

Ta część jest wymaganiem osobnym (D63), nie ozdobą poprzednich: **reguła, że
procesy aplikacji idą przez `make` i narzędzia repozytorium, ma być zapisana
tam, gdzie się ją czyta**, a nie tylko wykonana w pliku, który trzeba samemu
znaleźć. Podział ról z kroku 04 (D8, D13) zostaje nietknięty — każdy dokument
dostaje swoją porcję, w swoim rejestrze:

| Dokument | Co dostaje |
|---|---|
| `docs/architecture.md` | **pełną treść**: spis „proces → wejście” dla wszystkich procesów projektu (sprawdzenie środowiska, instalacja, jakość, testy, pomiar, budowa, uruchomienie, podgląd wejścia terminala), regułę pierwszeństwa narzędzi repozytorium i granicę wywołań doraźnych. Miejsce: rozdz. 4 albo nowy rozdział — pytanie nr 12. Wiersz „Skróty uruchomieniowe: `composer …`” przestaje być prawdą i musi zniknąć albo zmienić rolę |
| `.claude/skills/light-manager-conventions/SKILL.md` | **skrót operacyjny**: nazwę bramki i regułę „nie dorabiaj zastępnika narzędzia, które projekt ma”. Skill ładuje się przy pisaniu kodu w `src/`/`tests/` — czyli dokładnie wtedy, gdy ta wiedza jest potrzebna, a dziś nie ma tam ani jednego polecenia |
| `CLAUDE.md` | **wskaźnik, nie instrukcja**: zdanie o `make` jako wejściu do procesów i rozszerzenie istniejącej reguły pomiaru na cel pomiarowy. Plik ma zostać krótki — pełna treść mieszka w dokumencie źródłowym |
| `README.md` | wejście dla człowieka: `make` w „Instalacji” i „Narzędziach deweloperskich”, opis budowy, obejście SIGSEGV Composera przy właściwym celu |

Treść reguły ma dwie połowy i **druga jest ważniejsza**, bo to ona zapobiega
najczęstszej stracie:

1. **Wejście do procesu to cel `make`** — bramka jakości, instalacja, budowa,
   testy i pomiar mają nazwane wejścia i nie wywołuje się ich składając
   polecenia z pamięci.
2. **Narzędzie projektu ma pierwszeństwo przed doraźnym zastępnikiem.** Pomiar
   wydajności robi `bin/render-bench` — z jego fazami, wzorcami i metryczką
   środowiska — a nie własna pętla `microtime()` napisana na jeden raz; wejście
   terminala sprawdza `bin/terminal-probe`, a nie `read` w powłoce; scenariusz
   pomiarowy dokłada się do `ScenarioFactory`, a nie obok niego. Doraźny
   zastępnik nie jest szybszy — jest po prostu **niepodpięty do niczego**:
   nie porówna się z wzorcem, nie zostawi śladu i nie odpowie następnym razem.

Granica musi być w tekście, inaczej reguła zamieni się w dogmat: **zawężenie
przebiegu wolno wołać wprost** (pojedynczy test filtrem PHPUnita, jedna oś
`bin/render-bench`, `composer` przy pracy nad zależnościami) — cel `make` jest
wejściem do **procesu**, a nie kagańcem na narzędzie. Zakazane jest dorabianie
**równoległej drogi** do procesu, który wejście już ma. Jak ostro to zapisać —
pytanie nr 11.

Dokumenty mają po tym kroku mówić jednym głosem: żadne miejsce nie może opisywać
procesu poleceniem, którego krok nie ustalił — a to znaczy przejrzenie README,
`architecture.md`, `SKILL.md` i `CLAUDE.md` **naraz**, nie po kolei przy okazji.

## Poza zakresem

- **CI** (GitHub Actions i pokrewne) — w repozytorium go nie ma i ten krok go
  nie wprowadza. `make qa` ma być wywoływalny z CI, gdyby kiedyś powstało; to
  cała jego relacja z tym tematem.
- **Bramka wydajności** — odrzucona w kroku 16 i nieprzywracana tylnymi drzwiami
  przez zależność `qa` od celu pomiarowego.
- **Instalowanie rozszerzeń PHP i zależności systemowych.** Makefile sprawdza
  i nazywa brak; kompilowanie `glfw` ze źródeł, doinstalowywanie ImageMagicka
  czy XTerma zostaje po stronie użytkownika.
- **Docker i konteneryzacja** — inne zadanie, inne decyzje.
- **Windows** — README zamyka projekt do Linuksa i macOS-a; Makefile nie
  udaje przenośności, której aplikacja nie ma.
- **Nowe narzędzia jakości** (Psalm, Rector, Infection, mutacje) — krok daje
  wejście do zestawu, który jest, a nie powód do jego rozszerzania.
- **Wersjonowanie i wydania** (tagi, changelog) — poza budową, która co najwyżej
  **czyta** wersję, jeśli rozstrzygnięcie nr 7 jej zażąda.
- **Przegląd `docs/architecture.md` poza rozdziałem o procesach** — krok dopisuje
  wejścia i prostuje wiersz, który przestanie być prawdą; warstw, Singletonów
  ani słownika nie rusza.
- **Wymuszanie reguły procesu narzędziowo** (hooki gita, `pre-commit`) — reguła
  wchodzi do dokumentów, a nie do mechanizmu, który by ją egzekwował za plecami.
- **Kod aplikacji** — `src/` zostaje nietknięte.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Makefile` | — | nowy plik: wszystkie cele |
| `bin/check-env` (warunkowo) | — | sprawdzenie środowiska bez autoloadera, jeśli tak rozstrzygnie pytanie nr 2 |
| `composer.json` | — | ewentualne przestawienie skryptów wobec `make` (pytanie nr 3) |
| `phpunit.xml.dist` | Testy | podział na testsuites (pytanie nr 5) |
| `tests/**` (warunkowo) | Testy | przeniesienie istniejących przebiegów do katalogu funkcjonalnego — **wyłącznie w uzgodnieniu z krokiem 38** |
| `.gitignore` | — | `build/` |
| `README.md` | Dokumentacja | `make` w „Instalacji” i „Narzędziach deweloperskich”; opis budowy |
| `docs/architecture.md` | Dokumentacja | spis „proces → wejście” i reguła pierwszeństwa narzędzi repozytorium; **prostowanie rozdz. 4** — wiersz „Skróty uruchomieniowe: `composer …`” przestaje opisywać całość (pytanie nr 12) |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | nazwa bramki jakości i reguła „nie dorabiaj zastępnika narzędzia, które projekt ma” — pierwsze polecenia w tym pliku |
| `CLAUDE.md` | Dokumentacja | zdanie o `make` jako wejściu do procesów; istniejąca reguła pomiaru rozszerzona na cel pomiarowy — **bez rozrostu pliku**, wskaźnik zostaje wskaźnikiem (D13) |
| `src/**` | — | **bez zmian** |

## Do rozstrzygnięcia na starcie kroku

1. **Twardość wymogów w `check-env`**: co blokuje (kod wyjścia ≠ 0), co jest
   degradacją, a co informacją. W szczególności brak kodera `SIXEL` — aplikacja
   po nim działa w trybie tekstowym, więc czy to porażka, czy ostrzeżenie?
2. **Gdzie mieszka sprawdzenie**: w samym Makefile (`command -v`, `php -r`) czy
   w `bin/check-env` (PHP bez autoloadera, bo działa przed instalacją)? I w jakim
   języku mówi — polskim jak dokumentacja czy angielskim jak jedyny napis poza
   katalogiem w `bin/light-manager`?
3. **Jedno źródło prawdy dla jakości**: cele wołają `composer cs:check` / `stan`
   / `test`, czy `vendor/bin/*` wprost — a `composer.json` zostaje, chudnie do
   aliasów albo traci skrypty?
4. **Skład i zachowanie `make qa`**: kolejność (`cs-check` → `stan` → `test`)
   oraz zatrzymanie na pierwszym błędzie kontra przebieg do końca ze zbiorczym
   podsumowaniem.
5. **Podział testów**: testsuites `unit`/`functional` wchodzą tutaj (i wtedy
   krok 38 dokłada do gotowej struktury), czy krok czeka na rozstrzygnięcie
   nr 6 kroku 38? Jeśli tutaj — po katalogach czy po grupach?
6. **Cele pomiarowe**: czy `make bench` wymaga jawnego potwierdzenia (zmienna,
   osobna nazwa celu) zgodnie z regułą `CLAUDE.md`; które wejścia dostają własne
   cele (`bin/render-bench`, `--window`, XTerm dla `--transfer`) i jak przekazuje
   się do nich argumenty.
7. **Co znaczy „budowa”**: katalog dystrybucyjny bez zależności deweloperskich,
   archiwum, PHAR (nowa zależność + `phar.readonly`) czy sam zoptymalizowany
   autoloader? Skąd bierze się wersja, jeśli wynik ma ją w nazwie? Czy budowa
   kończy się sprawdzeniem, że wynik w ogóle się ładuje?
8. **Cele uruchomieniowe**: czy `make` opakowuje `bin/run.sh`,
   `bin/run-terminal-probe.sh` i tryb okienkowy, czy skrypty zostają jedynym
   wejściem do uruchamiania?
9. **Obejście SIGSEGV Composera** (`imagick`+`openswoole`): wariant celu
   w Makefile czy notatka w README bez wsparcia narzędziowego?
10. **`make coverage`**: wchodzi (wymaga Xdebuga albo PCOV — narzędzi spoza
    listy wymagań, więc trzeba czytelnie odmówić przy ich braku) czy zostaje
    poza zakresem?
11. **Ostrość reguły procesu**: „procesy uruchamiaj przez `make`” to **twarda
    reguła** w rejestrze `CLAUDE.md` („nie odstępuj bez jawnej zgody”), czy
    zalecenie z wyjątkami? I gdzie dokładnie biegnie granica wywołań doraźnych
    — pojedynczy test filtrem, jedna oś `bin/render-bench`, `composer require`
    przy pracy nad zależnościami: wolno bez pytania czy nie?
12. **Miejsce spisu „proces → wejście”** w `docs/architecture.md`: rozbudowa
    rozdziału 4 („Standardy PHP i narzędzia”) czy nowy rozdział o procesach
    projektu przed „Co dalej”? I co dzieje się z wierszem o skrótach Composera
    — znika, czy zostaje jako „co woła cel `make`”?

## Kryteria ukończenia

- `make` bez argumentów wypisuje spis celów z opisami; żaden cel nie jest
  „ukryty” przed tym spisem.
- `make check-env` działa **na świeżym klonie bez `vendor/`** i bez
  interaktywnego terminala, rozróżnia wymogi zgodnie z rozstrzygnięciem nr 1
  i mówi wprost, czego sprawdzić nie potrafi (DA1 — zostaje `bin/terminal-probe`).
- `make install` doprowadza świeży klon do stanu uruchamialnego, a powtórzony
  nie robi nic — bo `vendor/autoload.php` jest celem plikowym, nie `.PHONY`.
- `make qa` przechodzi na czystym drzewie i jest **dokładnie** dzisiejszym
  zestawem (`cs:check`, `stan`, `test`) — bez nowych narzędzi i bez pomiaru
  wydajności wśród zależności.
- Testy dają się uruchomić w rozbiciu z rozstrzygnięcia nr 5, a `make test`
  zostaje pełnym przebiegiem; liczba testów po podziale zgadza się z liczbą
  sprzed niego.
- Cel pomiarowy istnieje, niesie ostrzeżenie zgodne z `CLAUDE.md` i **nie jest**
  zależnością żadnego celu jakości.
- `make build` daje wynik w `build/` zgodny z rozstrzygnięciem nr 7, bez
  zależności deweloperskich; `build/` jest w `.gitignore`.
- `make clean` usuwa wyłącznie wytwory narzędzi i `build/`; `vendor/` znika
  dopiero na `dist-clean`, a `docs/pomiary/` nie znika nigdy.
- Cały plik działa pod GNU Make z POSIX-ową powłoką; **zero nowych zależności
  Composera**, chyba że rozstrzygnięcie nr 7 wprowadzi builder PHAR-a.
- `docs/architecture.md` niesie spis „proces → wejście” dla **wszystkich**
  procesów projektu wraz z regułą pierwszeństwa narzędzi repozytorium
  i zapisaną granicą wywołań doraźnych; wiersz o skrótach Composera nie opisuje
  już czegoś, czego nie ma.
- `CLAUDE.md` wskazuje `make` jako wejście do procesów i rozszerza regułę
  pomiaru na cel pomiarowy — **pozostając wskaźnikiem** (D13), a nie drugą
  instrukcją.
- `SKILL.md` zna nazwę bramki jakości i regułę „narzędzie projektu przed
  doraźnym zastępnikiem” — czyli agent piszący kod dowiaduje się, czym sprawdzić
  swoją pracę, z pliku, który i tak wtedy czyta.
- **Dokumenty mówią jednym głosem**: README, `docs/architecture.md`, `SKILL.md`
  i `CLAUDE.md` nie opisują żadnego procesu poleceniem sprzecznym z tym, które
  ustalił krok, i żadne polecenie opisane w dokumentacji nie przestaje działać.
- Każde polecenie wpisane do dokumentacji zostało **uruchomione**, a nie
  wymyślone — z pomiarem włącznie, na zasadach `CLAUDE.md` (odciążony host,
  za potwierdzeniem użytkownika).

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
