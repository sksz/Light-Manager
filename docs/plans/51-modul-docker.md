# Krok 51 — Moduł `docker`: kontenery, obrazy, logi, budowanie i compose

> **Skąd ten krok.** Powstał 2026-08-15, na polecenie użytkownika, razem
> z krokami 52 i 53 jako pierwsza trzecia Fazy XVIII
> ([00-decyzje.md](00-decyzje.md), D85). Rusza przy tym **mechanizm rdzenia**:
> prac tłowych może być odtąd kilka naraz.

## Status

**Nie rozpoczęty.**

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

## Do rozstrzygnięcia na starcie kroku

1. **Ile prac tłowych naraz** i co się dzieje po przekroczeniu granicy —
   odmowa z komunikatem czy wyparcie najstarszej.
2. **Litera skrótu** — propozycja `k` (kontenery), bo `d` zajmuje moduł opisu
   pliku; krok 52 zamówi drugą i warto rozstrzygnąć obie naraz.
3. **Górna granica bufora logów** — ile wierszy trzymamy i co widać po jej
   przekroczeniu.
4. **Czy `docker.build` jest w tym kroku, czy dopiero w 53** — budowa jest tu
   funkcją samą w sobie, ale jej **jedynym odbiorcą poza użytkownikiem** jest
   krok 53; wcześniejsze dowiezienie jest bezpieczne, późniejsze — czystsze
   wobec reguły 13.
5. **Skąd bierze się plik compose** — wyłącznie z pola tekstowego, czy także
   z `ModuleContext` (katalog przeglądarki).
6. **Co widać przy niedostępnym demonie** — pusty ekran z powodem, czy moduł
   odrzucony przez rejestr; przy braku `ext-curl` odpowiedź może być inna niż
   przy zatrzymanym demonie.
7. **Czy odświeżanie list idzie z zegara** (co N sekund w takcie), czy wyłącznie
   na `F5`. Zegar znaczy zapytanie do demona trzydzieści razy na minutę.
8. **Czy usunięcie obrazu i kontenera trafia do menu `F9`** — czynności
   nieodwracalne mają tam pozycję od kroku 47, ale te nie dotyczą zaznaczenia
   w przeglądarce.

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

_(pusty — krok nie rozpoczęty)_
