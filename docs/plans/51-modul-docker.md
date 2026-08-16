# Krok 51 — Moduł `docker`: kontenery, obrazy, logi, budowanie i compose

> **Skąd ten krok.** Powstał 2026-08-15, na polecenie użytkownika, razem
> z krokami 52 i 53 jako pierwsza trzecia Fazy XVIII
> ([00-decyzje.md](00-decyzje.md), D85). Rusza przy tym **mechanizm rdzenia**:
> prac tłowych może być odtąd kilka naraz.

## Status

**Ukończony** (2026-08-16). Rozstrzygnięcia startowe zapadły 2026-08-15
([00-decyzje.md](00-decyzje.md), D90), przed pierwszą linią kodu; pomiar i klatka
pod XTermem — dzień później, na zwolnionej maszynie.

## Cel

Aplikacja ma pokazywać, co na tej maszynie działa w Dockerze, i pozwalać to
zatrzymać, uruchomić, obejrzeć w logach, zbudować i podnieść z pliku compose.

Miarą powodzenia jest zdanie: **`Ctrl`+`K` pokazuje kontenery i obrazy, `Enter`
otwiera logi płynące na żywo, `F7` buduje obraz z widocznym postępem, a projekt
compose podnosi się i kładzie z tego samego ekranu — przy czym pętla przez cały
ten czas rysuje trzydzieści klatek na sekundę.**

## Zastrzeżenie do rozstrzygnięcia na starcie — to jest największy krok fazy

Krok dowozi naraz: **mechanizm rdzenia** (kilka prac tłowych), **własnego klienta
HTTP po gnieździe unixowym**, **cztery obszary funkcji** (kontenery, obrazy,
logi, budowanie) i **piąty, który do gniazda nie należy** (compose). To jest
więcej, niż dowiózł którykolwiek krok tego projektu — łącznie z 21 i 35.

Rozstrzygnięcie użytkownika (D85 nr 4) mówi „trzy kroki na fazę” i ten plan tego
trzyma, ale **granica podziału jest wskazana z góry**: gdyby krok okazał się za
duży, wychodzi z niego **compose** — jest jedyną częścią, która nie dzieli
z resztą ani drogi technicznej (CLI zamiast gniazda), ani danych (projekt zamiast
kontenera). Wyjęcie czegokolwiek innego rozerwałoby rzecz, która trzyma się
razem.

## Zastrzeżenie drugie — jedna praca tłowa to dziś decyzja rdzenia, nie ograniczenie

`BackgroundProcessPort` **prowadzi jedną pracę naraz** i jest to decyzja z kroku
26, zapisana wprost w kontrakcie: „uchwyt istnieje po to, żeby wyparty
zamawiający dowiedział się, że jego praca nie trwa”. Dziś jedynym odbiorcą jest
`du` z modułu opisu pliku, więc nikomu to nie przeszkadzało.

Ten krok daje portowi **drugiego i trzeciego odbiorcę naraz**, a jego prace są
długie: `docker compose up` trwa minutami, `compose logs` nie kończy się nigdy.
Przy dzisiejszej regule podniesienie projektu **ubiłoby** liczenie zajętości
katalogu i odwrotnie — a użytkownik zobaczyłby, że jedna funkcja aplikacji
wyłącza drugą bez słowa wyjaśnienia.

Port musi więc urosnąć o **kilka prac naraz**, i to jest mechanizm rdzenia, który
ten krok rusza. Trzy rzeczy zostają przy tym nietknięte: sprzątanie dwiema
drogami (D47), zakaz podawania potomkowi wejścia i zasada „zaglądanie nigdy nie
blokuje”.

## Zależności

- **Krok 26** twardo i podwójnie: stamtąd pochodzi port pracy tłowej wraz
  z regułami, a ten krok **zmienia jego najważniejszą** — „jedna praca” staje się
  „kilka prac, każda ze swoim uchwytem”. Reguła 11d dostaje przez to poprawkę,
  którą trzeba zapisać w `SKILL.md`, bo dotyczy każdego przyszłego odbiorcy.
- **Krok 20 i 21** twardo: kontrakt modułu i jego sprawdzian. Rdzeń ma kosztować
  **jedną linię w `Bootstrapie`** ponad rozbudowę portu.
- **Krok 45** — `NeedsTick`: logi płyną także wtedy, gdy ekranu modułu nie widać,
  więc pompowanie strumieni musi dziać się w takcie. Warunek z D82 jest tu
  spełniony wprost: **bez taktu funkcja nie istnieje** (strumień nieczytany
  zatrzymuje nadawcę — ta sama zasada, co „oba potoki czytane co klatkę” w D47).
- **Krok 27** — `Table`, `Column`, `TableRow`: lista kontenerów ma pięć kolumn
  (nazwa, obraz, stan, porty, czas), lista obrazów cztery.
- **Krok 24** — `Split`: ekran dzieli się na listę i panel opisu; podział należy
  do modułu (reguła 11c).
- **Krok 29** — `TextView`: logi są treścią pliku, której moduł nie zna inaczej
  niż jako `list<string>` wierszy. Komponent **nie czyta** (reguła 11i), więc
  strumień rozbiera moduł.
- **Krok 28** — `ConfirmOverlay` w wariancie `dangerous`: usunięcie kontenera
  i obrazu jest nieodwracalne.
- **Krok 23 i 41** — `ProgressBar`, `ProgressOverlay`, `RunsWork`: budowanie
  obrazu mówi o sobie postępem.
- **Krok 46** — `DeclaresEvents`: „kontener wystartował”, „budowa skończona”,
  „budowa nieudana”. Krok 53 oprze na tych zdarzeniach współpracę modułów, więc
  **to jest miejsce, w którym one powstają**.
- Od Fazy XVII (48–50) **nie zależy** i ona nie zależy od niego.

## Model i wysiłek

**Opus / xhigh.**

Trzy trudności różnego rodzaju. **Rdzeniowa**: zmiana reguły portu, z której
dzisiejszy odbiorca korzysta i której zmiana musi go zostawić działającym.
**Techniczna**: własny nieblokujący klient HTTP po gnieździe unixowym wraz
z rozbieraniem dwóch formatów strumieniowych Dockera. **Rozmiarowa**: pięć
obszarów funkcji w jednym module — patrz zastrzeżenie pierwsze.

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| Docker | **27.3.1, działa bez `sudo`** (użytkownik w grupie `docker`); 29 kontenerów, 123 obrazy — materiału do sprawdzenia nie brakuje |
| Gniazdo | `/var/run/docker.sock`, `srw-rw---- root docker` |
| API demona | **1.47** (`MinAPIVersion` 1.24) — sprawdzone zapytaniem `GET /version` z PHP |
| `ext-curl` | **Załadowane, curl 8.5.0**; `CURLOPT_UNIX_SOCKET_PATH` działa, `curl_multi_*` daje pracę nieblokującą |
| `ext-phar` | Załadowane — `PharData` spakuje kontekst budowy do archiwum tar bez procesu potomnego |
| Docker Compose | **v2.29.7, wtyczka CLI** — demon **nie ma** dla niej ani jednego zasobu w API. `docker compose ls --format json` oddaje tablicę; jeden projekt (`dev`) jest w tej chwili uruchomiony |
| `docker ps -a --format json` | JSON **wierszami**, nie tablicą; niesie `Labels` z `com.docker.compose.project` — czyli wiąże kontener z projektem compose za darmo |
| `BackgroundProcessPort` | **Jedna praca naraz**, potomek bez wejścia, oba potoki czytane co klatkę, sprzątanie dwiema drogami |
| Litery skrótów | Zajęte `b`, `d`, `a` (plus `s` zamówione przez krok 48); zakazane `c, h, i, j, m, z`. Wolne m.in. `e, f, g, k, l, n, o, p, q, r, t, u, v, w` |
| `src/Module/Docker/` | Nie istnieje |

## Zakres

### 1. Rozbudowa portu pracy tłowej (rdzeń)

`BackgroundProcessPort` prowadzi odtąd **kilka prac naraz**, każdą pod własnym
uchwytem, z górnym ograniczeniem liczby (rozstrzygnięcie nr 1). Zmiany
w kontrakcie: `start()` nie przerywa cudzej pracy, `poll()` i `stop()` działają
po uchwycie jak dotąd, dochodzi `stopAll()` dla sprzątania.

Reguły, które **zostają**: zaglądanie nigdy nie blokuje, oba potoki czytane co
klatkę **dla każdej pracy**, potomek nie dostaje wejścia, sprzątanie dwiema
drogami (D47), kod wyjścia ≠ 0 nie jest sam z siebie niepowodzeniem.

Cena do zmierzenia: `poll()` na N pracach co klatkę. Oś `--loop` „przed i po”,
przy pustym zestawie prac i przy pełnym.

### 2. Klient gniazda w module

`Module\Docker\Infrastructure\DockerApiService` — HTTP/1.1 po gnieździe
unixowym na `ext-curl`, **nieblokujący** (`curl_multi_exec` + `curl_multi_select`
z zerowym czasem oczekiwania), pompowany raz na takt. Bez `ext-curl` moduł
degraduje się z komunikatem, jak moduł dźwięku bez `ext-glfw` (reguła 11o);
rozszerzenie wchodzi do `suggest`, **nigdy** do `require`.

Dwa formaty strumieniowe, oba do rozebrania przez moduł i oba będące pułapką:

- **logi kontenera bez TTY są multipleksowane** — każda porcja poprzedzona
  ośmiobajtową ramką (bajt strumienia, trzy wypełniające, cztery długości
  w kolejności sieciowej). Czytanie ich jak zwykłego tekstu daje śmieci co kilka
  wierszy;
- **budowa oddaje postęp jako strumień obiektów JSON**, po jednym na wiersz,
  wymieszanych z komunikatami błędów.

### 3. Kontenery i obrazy

Ekran podzielony (`Split`): lista po lewej, opis wybranego po prawej. Kontenery
z `GET /containers/json?all=1`, obrazy z `GET /images/json`. Czynności: start,
stop, restart, usunięcie (`ConfirmOverlay` w wariancie `dangerous`), usunięcie
obrazu. Odświeżanie **na żądanie i w takcie**, nigdy w rysowaniu.

### 4. Logi na żywo

`GET /containers/{id}/logs?follow=1&stdout=1&stderr=1&tail=…` jako strumień
pompowany w takcie, pokazywany `TextView`em. Bufor ma **górną granicę**
(rozstrzygnięcie nr 3) — kontener gadatliwy zapełniłby pamięć w minutę.

### 5. Budowanie obrazu

Kontekst pakuje `PharData` do archiwum tar — **pracą kawałkową** (D46), bo
katalog projektu bywa duży — a `POST /build?t=<tag>` wysyła go i oddaje postęp
strumieniem. Okno pracy (`ProgressOverlay`, `RunsWork`) pokazuje etap
z odczytanych obiektów JSON. Zdarzenie `docker.build.finished` albo
`docker.build.failed` na końcu — **to jest to, na czym stanie krok 53**.

### 6. Compose

Jedyna część idąca **procesem potomnym**, bo demon nie ma dla compose ani jednego
zasobu w API: `docker compose ls --format json` (lista projektów),
`up -d` / `down` / `ps` / `logs -f` dla wskazanego pliku. Kontener wie o swoim
projekcie z etykiety `com.docker.compose.project`, więc lista kontenerów daje się
zawęzić do projektu **bez drugiego pytania**.

Plik projektu wskazuje się ścieżką (`PromptOverlay`) albo bierze z `ModuleContext`
— przeglądarka publikuje katalog, w którym stoi użytkownik, a `docker-compose.yaml`
leży zwykle właśnie tam.

### 7. Pomiar

Oś `--loop` „przed i po” **dwukrotnie**: dla samej rozbudowy portu (nikt nie
pracuje) i dla taktu z pompowanym gniazdem oraz kilkoma pracami. Scenariusza
klatki krok **nie dokłada** — ekran to `Table` i `TextView` w strefie środkowej,
czyli treść mierzona przez `columns` i `text-view`; powód pominięcia idzie do
[docs/pomiary/README.md](../pomiary/README.md).

## Poza zakresem

- **`docker exec` i wejście do kontenera** — port nie daje potomkowi wejścia,
  a zasób `/exec` w API kończy się przejęciem połączenia i terminalem w terminalu.
  To jest ta sama rzecz, którą krok 48 wykluczył jako sesję powłoki.
- **Sieci, wolumeny, statystyki (`/stats`)** — nie mają odbiorcy (reguła 13).
- **Rejestry i logowanie (`docker login`, `push`, `pull`)** — `pull` byłby
  najtańszym kandydatem na rozszerzenie, ale wchodzi razem z odbiorcą, czyli
  najwcześniej w kroku 53.
- **Swarm, konteksty Dockera, demony zdalne po TCP/TLS** — pierwszy z brzegu
  wymaga certyfikatów, a odbiorcy nie ma.
- **Edycja pliku compose** — aplikacja nie ma edytora tekstu i ten krok go nie
  wnosi.
- **Zdarzenia demona (`GET /events`)** — kuszące jako źródło odświeżania listy,
  ale to trzeci strumień do rozebrania; osobna decyzja, jeśli odświeżanie
  na żądanie okaże się za rzadkie.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/BackgroundProcessPort.php` | Rdzeń/Application | **Zmiana kontraktu** — kilka prac naraz, `stopAll()` |
| `Infrastructure/Process/BackgroundProcessService.php` | Rdzeń/Infrastructure | Prace pod uchwytami, ograniczenie liczby, sprzątanie wszystkich |
| `Module/FileInfo/**` | Moduł | Sprawdzenie, że `du` działa jak dotąd — **odbiorca sprzed zmiany nie ma prawa ucierpieć** |
| `Module/Docker/Domain/ValueObject/{ContainerId,ImageRef,ContainerState}.php` | Moduł/Domain | **Nowe** |
| `Module/Docker/Application/Port/{DockerApiPort,ComposePort}.php` | Moduł/Application | **Nowe** — gniazdo i wtyczka CLI to dwie różne rzeczy |
| `Module/Docker/Application/{ContainerList,ImageList,LogStream,BuildWork}.php` | Moduł/Application | **Nowe** — stany oglądane co klatkę |
| `Module/Docker/Infrastructure/{DockerApiService,UnavailableDockerService,ComposeCliService,BuildContextPacker}.php` | Moduł/Infrastructure | **Nowe** |
| `Module/Docker/Presentation/{DockerModule,DockerScreen,ContainerPane,ImagePane,LogPane}.php` | Moduł/Presentation | **Nowe** |
| `Module/Docker/Presentation/Command/*.php` | Moduł/Presentation | `docker.ps`, `docker.build`, `docker.compose-up`, `docker.compose-down`, `docker.logs` |
| `Module/Docker/lang/{pl,en}.php` | Napisy | Nagłówki kolumn, stany, czynności, powody niepowodzeń |
| `Presentation/Cli/Bootstrap.php` | Rdzeń | **Jedna linia** — pozycja na liście modułów |
| `composer.json` | Projekt | `ext-curl` w `suggest` |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Poprawka reguły 11d (kilka prac naraz) wraz z powodem; moduł i jego dwie drogi do Dockera |
| testy | Testy | **Żaden test nie rozmawia z demonem**: rozbieranie ramek logów i strumienia budowy na próbkach bajtów, parser wyjścia compose na zapisanym JSON-ie, port za atrapą, kilka prac tłowych na `sleep`/`echo` |

## Rozstrzygnięcia startowe (2026-08-15)

Pełne uzasadnienia i odrzucone alternatywy: [00-decyzje.md](00-decyzje.md), D90.
Osiem pytań, osiem odpowiedzi, wszystkie przed pierwszą linią kodu.

1. **Ile prac tłowych naraz** → **wartość konfigurowalna**, a przekroczenie
   granicy znaczy **odmowę** (uchwyt wraca, powód odbiera pierwszy `poll()`).
   Wyparcie najstarszej odrzucone — przywracałoby chorobę, którą krok leczy.
2. **Litera skrótu** → moduł Dockera bierze **`Ctrl`+`O`**, a moduł k8s z kroku
   52 — **`Ctrl`+`K`**, rozstrzygnięte z góry dla obu. Propozycja planu (`k` dla
   kontenerów) przegrała z utrwalonym poza aplikacją skrótem Kubernetesa.
3. **Górna granica bufora logów** → **pozycja ustawień modułu** (lista
   przystanków), najstarsze wiersze wypadają, a pominięcie jest **widoczne**.
4. **Czy `docker.build` jest w tym kroku** → **jest**. Odbiorcą jest użytkownik,
   więc reguła 13 nie czeka na krok 53; ten dostaje zdarzenia jako rzecz zastaną.
5. **Skąd plik compose** → **kontekst jako propozycja + pole tekstowe**
   (`ReadsContext` wypełnia `PromptOverlay` wstępnie, wolno nadpisać).
6. **Co przy niedostępnym demonie** → **rozdzielnie**: brak `ext-curl` albo
   gniazda **odrzuca moduł** (`RequiresEnvironment`), leżący demon — **nie**,
   bo demona da się podnieść bez restartu aplikacji; ekran mówi powód.
7. **Czy odświeżanie idzie z zegara** → **co pięć sekund, ale tylko gdy ekran
   modułu jest widoczny**, plus `Ctrl`+`R` i samoczynnie po własnej czynności.
8. **Czy usunięcie trafia do menu `F9`** → **nie w tym kroku**; pytanie wraca
   w kroku 53, bo `MenuOverlay` zawęża się dziś do zaznaczenia przeglądarki.

**Skutek dla rdzenia:** rośnie o **trzy rzeczy**, nie o jedną linię — zmiana
kontraktu portu (zapowiedziana), **nowy klucz rdzenia** z granicą liczby prac
(skutek nr 1) i pozycja modułu w `Bootstrapie`.

## Stan zastany — poprawka do tabeli powyżej

Tabela „Stan zastany” jest prawdziwa w każdym wierszu poza jednym, a ten jeden
przesądza o wadze kroku: **odbiorców portu pracy tłowej jest trzech, nie
jeden**. Plan pisano, gdy Faza XVII była niewykonana; kroki 48–50 dołożyły
modułowi `Ssh` pięć miejsc wywołań (sonda sesji, `ssh -O check`, listing
`sftp`, przesył, sprzątanie połówki), z których dwa potrafią stać obok siebie.
Kryterium „starszy odbiorca nie ma prawa ucierpieć” dotyczy przez to **dwóch
modułów**, a rozbudowa portu zdejmuje z modułu `Ssh` cenę zapisaną w D89 —
przesył przestaje zajmować port na cały swój czas — **nie zmieniając w nim ani
jednej linii**.

Reszta liczb potwierdzona 2026-08-15: Docker 27.3.1, API demona 1.47, gniazdo
odpowiada z PHP (`GET /containers/json` → 200), `ext-curl` 8.5.0, `ext-phar`
obecne, Compose v2.29.7, 29 kontenerów, **125** obrazów.

## Kryteria ukończenia

- `Ctrl`+`K` pokazuje kontenery i obrazy z prawdziwego demona; start i stop
  działają, a lista pokazuje zmianę.
- Logi płyną na żywo i **nie mają w sobie śmieci** z ramek multipleksowania;
  kontener gadatliwy nie zjada pamięci.
- Budowa obrazu pokazuje postęp, kończy się zdarzeniem i nie blokuje pętli.
- Projekt compose podnosi się i kładzie, a lista kontenerów daje się zawęzić do
  jego projektu.
- **`du` z modułu opisu pliku działa w trakcie pracy compose** — i odwrotnie.
  To jest kryterium na rozbudowę portu, a nie na moduł.
- Bez `ext-curl` albo bez działającego demona aplikacja działa jak dotąd,
  a moduł mówi, czego brakuje.
- `bin/render-bench --loop` „przed i po” bez regresji — osobno dla pustego
  zestawu prac i dla pełnego.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie rozmawia
  z demonem ani nie uruchamia `docker`**.

## Dziennik realizacji

### 2026-08-15/16 — rdzeń, moduł, testy i dokumentacja; pomiar odłożony

**Stan: kod gotowy i zielony** (`make qa`: 2011 testów, PHPStan `max`,
PHP-CS-Fixer). Pomiar i klatka — w drugiej części dziennika, poniżej.

**Rdzeń urósł o cztery rzeczy, nie o trzy zapowiedziane w rozstrzygnięciach.**
Czwarta wyszła w trakcie i jest rozstrzygnięciem użytkownika podjętym przed
pierwszą linią kodu portu:

1. **Kontrakt `BackgroundProcessPort`** — kilka prac naraz, każda pod uchwytem,
   odmowa po przekroczeniu granicy.
2. **Klucz rdzenia `backgroundJobs`** (zakładka „Zasoby”, przystanki 1–16,
   domyślnie 8) — bo wartość konfigurowalna musi mieć gdzie mieszkać.
3. **Pozycja w `Bootstrapie`** — jedna linia, zgodnie z regułą 15.
4. **`Application\Port\BackgroundPumpPort` i faza w `GameLoop`** — pompowanie
   potoków wszystkich prac raz na klatkę. Postawiono trzy warianty z cenami;
   użytkownik wybrał osobne `pump()` zamiast „`poll()` posuwa wszystkie” i zamiast
   „każdy karmi swoją”. `poll()` jest odtąd **czystym odczytem stanu**.

**Dwie rzeczy z planu nie powstały i obie świadomie:**

- **`stopAll()` w porcie** — plan zapowiadał je „dla sprzątania”, ale metoda
  dostępna każdemu modułowi pozwala ubić pracę sąsiada jednym wywołaniem, czyli
  jest dawną regułą „jedna praca naraz” na żądanie. Sprzątanie całości ma drogę
  **poza portem** od kroku 26 (`shutdown()`, D47). Rozstrzygnięcie użytkownika.
- **`UnavailableDockerService`** (pusty obiekt portu) — po rozstrzygnięciu D90
  nr 6 nie ma odbiorcy: brak `ext-curl` albo gniazda **odrzuca moduł**, a leżący
  demon jest zdaniem na ekranie, nie pustym portem. Mechanizm bez odbiorcy łamie
  regułę 13, więc nie powstał.

**Compose ma trzy czynności zamiast pięciu z planu.** `ps` nie wchodzi, bo lista
kontenerów **już zna projekt** (etykieta `com.docker.compose.project` przychodzi
razem z listą, więc zawężenie nie kosztuje ani jednego pytania więcej), a
`logs -f` nie wchodzi, bo logi kontenera płyną gniazdem i drugi tor do tej samej
treści byłby drugą drogą do jednej rzeczy.

**Sprawdzone na żywym demonie — bez interfejsu, samym kodem modułu** (Docker
27.3.1, API 1.47, 29 kontenerów, 123 obrazy):

| Co | Wynik |
|---|---|
| lista kontenerów przez gniazdo | `Done` 200, 29 wpisów, 12 klatek; **najwolniejsze `pump()`+`poll()` 0,40 ms** |
| logi z ramkami multipleksera | trzy wiersze czystej treści, **bez śmieci z nagłówków** |
| odmowa demona | `404` + zdanie `No such container: …` podane w całości |
| dwie rozmowy naraz | 29 kontenerów i 123 obrazy, uchwyty się nie pomieszały |
| pakowanie kontekstu | 3 pliki zamiast 4 — `.dockerignore` wyciął `node_modules` (4 KB zamiast 204 KB); archiwum tymczasowe **zniknęło po sprzątaniu** |
| budowa obrazu | `Done`, 159 klatek, skrót obrazu odebrany; **najwolniejsza klatka 12,3 ms** (odczyt archiwum i wysyłka) |
| `compose ls -a` | 2 projekty, 46 klatek, najwolniejsza 0,79 ms |
| `compose up -d` | `Done`, 200 klatek (~0,6 s), zdanie `Container projekt-probka-1 Started` |
| `compose down` | `Done`, 3374 klatki (~10 s), zdanie `Network projekt_default Removed` |
| **praca sąsiada w trakcie obu prac compose** | `sleep 120` zamówiony osobno **przetrwał obie** — `Running` przed i po |

Ostatni wiersz jest **kryterium ukończenia rozbudowy portu** („`du` działa
w trakcie pracy compose i odwrotnie”) sprowadzonym do dwóch procesów. Obraz
i kontener próbki zostały po sprawdzeniu usunięte; stan maszyny wrócił do
zastanego.

**Test złapał usterkę, której oko by nie złapało.** Czytnik logów rozstrzygał
o ramkowaniu **pierwszą porcją, jaka przyszła** — a porcja krótsza od
ośmiobajtowego nagłówka wygląda jak zwykły tekst, bo nie ma w niej czego
sprawdzić. Odpowiedź raz udzielona obowiązuje do końca strumienia, więc cały log
ramkowany zamieniał się w wiersze zaczynające się ośmioma kropkami. Rozstrzygnięcie
przeniesiono na **ósmy bajt**; przypadek stoi odtąd w teście jako „ramka przecięta
w połowie”.

**Starsi odbiorcy portu nie zostali tknięci**: `src/Module/Ssh/` i
`src/Module/FileInfo/` nie mają w tym kroku ani jednej zmienionej linii kodu
produkcyjnego. Zmienił się jeden **test** modułu SSH — ten, który sprawdzał
wyparcie pracy przez cudze zamówienie: wyparcia nie ma, więc przebieg dochodzi do
`Idle` inną drogą (port już nie zna uchwytu), a moduł ma je rozumieć tak samo.
Cena zapisana w D89 („przesył zajmuje port na cały swój czas”) **zniknęła sama**.

### 2026-08-16 — pomiar i klatka pod XTermem

**Pomiar wykonany na zwolnionej maszynie** (obciążenie 0,05–0,13 na rdzeń,
rozrzut bez ostrzeżenia). Oś `--loop`, dwa przebiegi, tak jak żąda zakres nr 7:

| Co mierzone | Wynik |
|---|---|
| **przy pustym zestawie prac** — wobec wzorca po kroku 49 | **−1,1%**, bez regresji ([2026-08-16-po-kroku-51-loop.json](../pomiary/2026-08-16-po-kroku-51-loop.json)) |
| **przy pełnym zestawie** — `background` (1 praca) wobec `background-many` (8 prac) | takt **0,084 → 0,115 ms**, faza wejścia **0,006 → 0,034 ms** ([2026-08-16-po-kroku-51-loop-prace.json](../pomiary/2026-08-16-po-kroku-51-loop-prace.json)) |

**Cena rozbudowy portu wynosi 0,028 ms na klatkę** — tyle kosztuje `pump()`
przechodzący po ośmiu potomkach zamiast po jednym, czyli około 4 µs na pracę.
Wobec budżetu klatki (33,3 ms) jest to **0,09%**. Udział fazy wejścia w takcie
rośnie przy tym z 7% do 29% i to jest jedyne miejsce, w którym różnicę w ogóle
widać — całkowity czas taktu zaokrągla się w tabeli do tej samej dziesiątej
milisekundy.

**Narzędzie musiało dostać poprawkę, żeby ten pomiar był wykonalny.** Tor
`--loop` od kroku 38 wymuszał **jeden** scenariusz („osi scenariuszy w nim nie
ma, bo treść składa `LoopScenarioScreen`”), więc pary prac tłowych nie dawało się
na nim zmierzyć. Granicę postawiono dokładniej zamiast znosić zasadę: przechodzą
tędy **wyłącznie scenariusze różniące się liczbą prac tłowych**, bo mają w tym
torze tę samą treść klatki, a różnią się tylko tym, ilu potomków pompuje pętla.
Scenariusz bez prac tłowych nadal nie ma tu czego wnieść.

**Klatka obejrzana pod XTermem** (`make run-xterm ARGS='140x36'`, prawdziwy demon,
29 kontenerów, 123 obrazy) — wszystkie trzy postacie ekranu:

- **kontenery**: podział z listą i opisem, stan `działa` w akcencie i
  `restartuje` w ostrzeżeniu, kolumna portów (`8081->8081/tcp`), projekt compose
  w opisie;
- **obrazy**: rozmiary z przecinkiem dziesiętnym (`1,4 GiB`), wiek w formach
  mnogich, obrazy osierocone przygaszone i pokazane samym skrótem treści;
- **logi**: strumień MongoDB płynący na żywo, **bez jednego śmiecia z ramek
  multipleksowania**, z suwakiem i stopką mówiącą, co robi `End`.

**Klatka dała jedną poprawkę, której nie dałby żaden test.** Kolumna „Użyć”
(liczba kontenerów korzystających z obrazu) stała **pusta w każdym wierszu**:
demon zwraca w tym zasobie `Containers: -1` niezależnie od wariantu zapytania —
sprawdzone trzema (`bez opcji`, `?shared-size=true`, `?all=true`). Kolumna
zabierała osiem znaków szerokości nazwie obrazu, więc **wyszła z listy, z opisu
i z domeny**; zwolniona szerokość wróciła nazwie i ucinanie nazw zniknęło.
Reguła ogólna z tej poprawki, warta zapamiętania przy każdej następnej kolumnie
czytanej z cudzego API: **pole obecne w odpowiedzi nie znaczy, że ma wartość** —
i widać to dopiero na prawdziwych danych, bo w próbce testowej wpisuje się liczbę
własnoręcznie.

**Stan maszyny po sprawdzeniach jest taki, jak przed nimi**: obraz próbny
i kontener projektu próbnego usunięte, archiwum tymczasowe skasowane przez sam
port, aplikacja i XTerm zamknięte.

Scenariusza klatki krok nie dokłada, a powód pominięcia stoi
w [docs/pomiary/README.md](../pomiary/README.md).
