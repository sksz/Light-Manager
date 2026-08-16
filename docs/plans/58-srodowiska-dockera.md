# Krok 58 — Środowiska Dockera: jeden demon przestaje być założeniem

> **Skąd ten krok.** Powstał 2026-08-16 na polecenie użytkownika, jako pierwszy
> krok **Fazy XX** ([00-decyzje.md](00-decyzje.md), D96). Otwiera pozycję
> wykluczoną wprost z kroku 51 — „Swarm, konteksty Dockera i demony zdalne po
> TCP/TLS" — i robi to z powodu, którego tamten krok nie miał: moduł ma dziś
> wpisaną w kod odpowiedź na pytanie „**z którym** demonem rozmawiasz", a odpowiedź
> ta brzmi „z tym jedynym".

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D96 (nr 1, 2, 3 i 5).

## Cel

Moduł Dockera rozmawia z **wybranym środowiskiem**: lokalnym gniazdem, demonem
za tunelem SSH albo demonem po TCP z TLS-em klienta. Kontenery, obrazy, logi,
budowa, wypchnięcie i compose działają w każdym z nich **tą samą drogą**.

Miarą powodzenia jest zdanie: **kontenery zdalnego demona widać w tym samym
panelu, w którym widać lokalne, a przełączenie środowiska nie zmienia ani jednej
linii kodu rozmowy z demonem.**

Miarą drugą, wymierną: **brak lokalnego gniazda przestaje odrzucać moduł.** Dziś
odrzuca ([`DockerModule::unavailableReason()`](../../src/Module/Docker/Presentation/DockerModule.php)),
a przy środowisku zdalnym byłaby to odmowa bez powodu — maszyna bez demona
lokalnego jest dokładnie tą, na której zdalne środowisko ma sens.

## Trudność strukturalna — cztery rzeczy, wszystkie starsze od tego kroku

**Pierwsza: gniazdo jest stałą klasy, a nie daną.** `DockerApiService::SOCKET_PATH`
to `'/var/run/docker.sock'`, a `isSupported()` jest **statyczna** — czyli
odpowiedź „czy da się w ogóle" jest dziś globalna i pada raz na uruchomienie.
Przy książce środowisk odpowiedź brzmi „**zależy które**", a to zmienia nie
wartość, tylko kształt pytania.

**Druga: na tym samym sprawdzeniu stoi odrzucenie modułu** (reguła 11s). Reguła
żąda, żeby odpowiedź była tania i padała raz na uruchomienie — i to zostaje
spełnione, ale sens się przesuwa: moduł wchodzi, gdy jest `ext-curl`, a brak
demona staje się **stanem środowiska**, nie brakiem modułu. Precedens jest
w tym samym kroku 51 i mówi dokładnie to: „leżący demon nie odrzuca modułu"
(D90) — rozszerzamy go na demona nieobecnego.

**Trzecia: tunel to praca tłowa, która ma trwać.** `ssh -M -N -f` demonizuje się
sam (krok 48), więc uchwyt pracy gaśnie, a gniazdo zostaje — i musi zostać
posprzątane **dwiema drogami** (D47: jawnie w `Bootstrap::shutdown()` i przez
`register_shutdown_function`). Gniazdo po nieposprzątanym tunelu jest gorsze od
jego braku: `connect()` w nie trafia i wisi.

**Czwarta: potomek nie dostaje wejścia ani własnego środowiska.**
`BackgroundJob` woła `proc_open($command, [1 => …, 2 => …], $pipes)` **napisem**,
czyli przez powłokę i bez deskryptora 0. Zmienna środowiskowa dla compose idzie
więc **przedrostkiem wiersza polecenia** (`DOCKER_HOST=unix://… docker compose …`),
a nie tablicą `env` — port jej nie przyjmuje i nie ma powodu, żeby zaczął.

## Stan zastany (sprawdzony w kodzie i na maszynie 2026-08-16)

| Element | Stan |
|---|---|
| `DockerApiService` | `SOCKET_PATH = '/var/run/docker.sock'` — **stała klasy**; `isSupported()` **statyczna**: `ext-curl` + `CURLOPT_UNIX_SOCKET_PATH` + `file_exists()`. Wywołanie: `CURLOPT_UNIX_SOCKET_PATH => self::SOCKET_PATH`. |
| `DockerModule::unavailableReason()` | Brak gniazda → moduł **odrzucony** (`unavailable.socket`). |
| `ComposeCliService` | `docker compose -f <plik> up -d\|down`, `docker compose ls -a --format json`; środowiska nie zna. |
| `BackgroundProcessPort::start()` | Bierze **gotowy wiersz polecenia**; `BackgroundJob` uruchamia go `proc_open`em ze stringiem (czyli powłoką), bez deskryptora wejścia. |
| Ustawienia modułu | Cztery pozycje: `logLines` + trzy o rejestrze (krok 54). O demonie — ani jednej. |
| Kwerendy modułu | `docker.containers`, `docker.images`, `docker.compose`, `docker.build`, `docker.push` — żadna nie mówi, **skąd** dane. |
| `~/.light-manager/ssh.json` | Wzorzec książki: plik stanu modułu, tryb `0600`, nieznane klucze przeżywają zapis (kroki 48–50). |
| Maszyna | Docker 27.3.1, compose v2.29.7; gniazdo `srw-rw---- root:docker`, użytkownik **jest** w grupie `docker`. |
| `docker context ls --format json` | Działa; oddaje **po jednym obiekcie JSON w wierszu** (NDJSON), pola `Name`, `Description`, `DockerEndpoint`, `Current`, `Error`, `ContextType`. Jeden kontekst: `default` → `unix:///var/run/docker.sock`. Katalog `~/.docker/contexts` **nie istnieje** — powstaje dopiero z pierwszym kontekstem własnym. |
| OpenSSH | 9.6p1; `man ssh` na tej maszynie dokumentuje `-L local_socket:remote_socket` (przekierowanie gniazd unixowych, od 6.7). |
| `ext-curl` | OpenSSL/3.0.13 — czyli `CURLOPT_SSLCERT`/`SSLKEY`/`CAINFO` mają czym działać. |

## Zależności

- **Krok 51** — cały moduł: rozmowa z demonem, ramkowanie logów, budowa, compose.
- **Krok 48** — dwa wzorce naraz: **książka wpisów w pliku stanu modułu**
  (`ssh.json`, tryb `0600`) oraz **`ssh -M -N -f` jako proces, który się
  demonizuje**. Modułu Ssh ten krok **nie dotyka** (reguła 15).
- **Krok 26 i 51** — port pracy tłowej wraz z kilkoma pracami naraz; tunel jest
  kolejnym jego użytkownikiem.
- **Krok 53 i 54** — kwerendy: dane hosta do tunelu bierze się z `ssh.hosts`
  (reguła 15g — trzy napisy, ani jednego typu), a listy modułu dostają nazwę
  środowiska.
- **Krok 45** — takt modułu: tunel trzeba posunąć i zauważyć, że wstał.
- **Kroki 24, 27, 28, 30, 32, 40** — panele, tabela, pytanie, filtr, menu
  i podpowiedzi stopki dla ekranu spisu środowisk.
- **Kroki 14 i 15** — ustawienia i napisy.

## Model i wysiłek

**Opus / xhigh.** Warunek `Fable` nie zachodzi: prymitywów nie przybywa, słownik
wejścia zostaje nietknięty, trzej tłumacze nie dostają ani jednej linii. Wysiłek
trzyma to, że krok dokłada **dwie drogi transportu naraz** (rozstrzygnięcie D96
nr 2), przestawia sposób odrzucania modułu i wprowadza pracę tłową, która ma
przeżyć swój uchwyt.

## Zakres

### 1. Środowisko jako obiekt wartości i książka

`Module/Docker/Domain/ValueObject/DockerEnvironment` — nazwa własna (tożsamość,
wzorem `HostProfile`), rodzaj, adres, pola zależne od rodzaju. Rodzaj to enum
`EnvironmentKind`: **`LocalSocket`**, **`SshTunnel`**, **`Tcp`**.
Samowalidacja rzuca wyjątkiem modułu deklarującym `DescribesProblem` (reguła 8),
a wzorce nazw są wąskie jak w kroku 48 — wartość wchodzi do wiersza polecenia.

`Module/Docker/Application/EnvironmentBook` — kolekcja mutowalna w miejscu,
tożsamość po nazwie, kolejność dopisywania (wzorem `HostBook`).
`EnvironmentBookPort` → `DockerStateService` z plikiem
`~/.light-manager/docker.json`, tryb `0600`, **nieznane klucze przeżywają
zapis** — bo krok 60 dopisze do tego samego dokumentu książkę rejestrów, a dwa
niezależne zapisy jednego pliku to wyścig przy pierwszym zapisie z dwóch miejsc.

### 2. Dwa źródła jednej listy

Konteksty klienta czyta się `docker context ls --format json` (praca tłowa,
NDJSON), a wpisy własne dochodzą z książki (D96 nr 3). Trzy reguły, bez których
to się rozjedzie:

- **Pochodzenie jest widoczne** — wpis czytany od klienta ma znacznik i nie da
  się go z aplikacji skasować (należy do cudzego narzędzia, a moduł do cudzych
  plików nie pisze).
- **Przy zbieżnej nazwie wygrywa wpis własny**, bo to on niesie pola, których
  kontekst klienta nie ma (certyfikaty, host tunelu); kolizja jest widoczna
  w spisie, nie rozstrzygana po cichu.
- **Brak klienta `docker` nie jest awarią** — lista schodzi wtedy do wpisów
  własnych plus gniazda lokalnego. Moduł nie wymaga dziś klienta do niczego poza
  compose i **nie zacznie**.

### 3. `DockerApiService` przestaje być stałą

Ścieżka gniazda albo adres bazowy z TLS-em przychodzą **z wybranego wpisu**.
`isSupported()` przestaje być statyczne i staje się pytaniem o wpis; statyczne
zostaje wyłącznie „czy `ext-curl` w ogóle jest".

Dla `Tcp`: `CURLOPT_SSLCERT`, `CURLOPT_SSLKEY`, `CURLOPT_CAINFO` ze ścieżek
wpisu, adres `https://host:2376`. Reszta rozmowy — ramkowanie logów, strumień
budowy, `X-Registry-Auth` — **bez zmian**, i to jest cała stawka tego punktu.

### 4. Tunel SSH

Wpis rodzaju `SshTunnel` niesie cel (`user@host`, port, ścieżkę gniazda po
stronie zdalnej — domyślnie `/var/run/docker.sock`). Moduł uruchamia własnego
potomka rdzeniowym portem pracy tłowej:

```
ssh -M -N -f -L <lokalne>:<zdalne> [-p port] <cel>
```

Cztery rzeczy, każda z powodem:

- **Ścieżka gniazda lokalnego leży w `XDG_RUNTIME_DIR`** (`/run/user/<uid>`),
  a nie w `/tmp`: katalog jest prywatny dla użytkownika, a gniazdo daje pełną
  władzę nad demonem po drugiej stronie.
- **Nazwa gniazda zawiera nazwę wpisu**, bo dwa środowiska mają prawo stać
  jednocześnie.
- **Sprzątanie dwiema drogami** (D47) — plus skasowanie pliku gniazda, bo
  `ssh` zostawia go po sobie, a gniazdo po nieżyjącym tunelu wisi przy
  `connect()`.
- **Dane hosta bierze się kwerendą `ssh.hosts`**, jeśli użytkownik wskaże wpis
  książki hostów — trzy napisy, ani jednego typu z tamtego modułu
  (reguła 15g, `NoModuleKnowsAnotherModuleTest`). Adresu wpisanego wprost też
  wolno użyć; wtedy kwerenda nie pada w ogóle.

Stan tunelu ma **cztery postacie** (nie ma / wstaje / stoi / nie wstał
z powodem) i jest widoczny w górnym pasie — inaczej „demon nie odpowiada"
i „tunel nie wstał" wyglądają identycznie, a wymagają dwóch różnych czynności.

### 5. Ekran spisu środowisk

Trzecia postać ekranu modułu (obok kontenerów i obrazów), klawisz **`e`** —
litery moduł nie zajmuje ani jednej, a `Ctrl`+litera należy do skrótów modułów
(krok 20). Wzorem `HostsScreen` z kroku 48: spis, dodanie, zmiana, usunięcie,
wybór bieżącego. Wpis czytany od klienta pokazuje się w spisie, ale zmiany
i usunięcia nie przyjmuje (punkt 2).

### 6. Compose w wybranym środowisku

`DOCKER_HOST` idzie **przedrostkiem wiersza polecenia** (punkt „czwarta
trudność"): `DOCKER_HOST=unix:///run/user/1000/lm-docker-<nazwa>.sock docker
compose -f <plik> up -d`. Dla `Tcp` dochodzą `DOCKER_TLS_VERIFY=1`
i `DOCKER_CERT_PATH=<katalog>`.

**Pułapka do nazwania w napisach, nie w komentarzu**: plik compose czyta
**klient**, więc leży po stronie lokalnej — ale `volumes:` z montowaniem
katalogu wskazują ścieżki **po stronie demona**, a kontekst budowy jedzie przez
sieć. Compose lokalnego pliku przeciwko zdalnemu demonowi działa i **nie
znaczy tego samego**, co lokalnie; użytkownik ma się o tym dowiedzieć zdaniem,
zanim podniesie projekt, a nie z zachowania kontenerów.

### 7. Odrzucenie modułu i kwerendy

`unavailableReason()` odpowiada odtąd wyłącznie za `ext-curl`
(i `CURLOPT_UNIX_SOCKET_PATH`). Brak gniazda lokalnego jest stanem wpisu
`LocalSocket` — z tym samym zdaniem, co dziś, ale w treści ekranu.

Kwerendy: nowa **`docker.environments`** (nazwa, rodzaj, adres bez
poświadczeń, pochodzenie, czy bieżące, stan tunelu), a `docker.containers`,
`docker.images` i `docker.compose` dostają **nazwę środowiska w każdym
wierszu** — inaczej odpowiedź dwóch różnych demonów wygląda dla obcego
identycznie (reguła 11w). **Ścieżki kluczy TLS ani celu SSH do wierszy nie
wchodzą** — to ta sama granica, którą `ssh.hosts` trzyma dla odcisku klucza.

### 8. Zdarzenia

Jedna nowa pozycja w `DockerEvent`: **zmiana środowiska**. Słownik jest
zamknięty konstrukcyjnie (reguła 11o''), więc pozycja wchodzi enumem modułu
i deklaracją katalogu z `cases()` — bez dotykania rdzenia.

### 9. Napisy, pomiar, przebiegi

- **Napisy:** rodzaje środowisk, cztery stany tunelu, powody niepowodzenia
  (gniazdo niedostępne, tunel nie wstał, certyfikat odrzucony, host nieznany),
  zdanie o pułapce z punktu 6, spis klawiszy ekranu, opis kwerendy w obu
  językach.
- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 54 — takt modułu
  rośnie o dosunięcie tunelu. Wygląd klatki zmienia się o jeden ekran, więc
  scenariusz spisu środowisk dokłada się **do** `ScenarioFactory`, a nie obok
  niej (reguła 18).
- **Przebiegi:** `tests/Functional/DockerEnvironmentFlowTest.php` — wybór
  środowiska unieważnia obie listy; tunel, który nie wstał, kończy się zdaniem,
  a nie pustą listą; wpis czytany od klienta nie daje się skasować; compose
  dostaje przedrostek; sprzątanie po wyjściu kasuje gniazdo. **Żaden test nie
  uruchamia ani `ssh`, ani `docker`** — atrapy portów, jak w krokach 48–52.

### 10. Dokumentacja

`docs/architecture.md` — środowisko jako współrzędna każdej rozmowy z demonem,
trzy drogi transportu, tunel jako praca przeżywająca uchwyt. `SKILL.md` —
rozszerzenie reguły 11t o zdanie: **z którym demonem rozmawiamy, jest daną
wpisu, a nie stałą usługi**; obok tego zapis, że zmienna środowiskowa dla
potomka idzie przedrostkiem wiersza polecenia, bo port bierze napis.
`README.md` — jak dodać środowisko zdalne.

## Poza zakresem

- **Pisanie do cudzych plików klienta** (`docker context create`, `use`) —
  moduł czyta konteksty i **nie dopisuje** do nich, tak samo jak moduł k8s nie
  zmienia `kubeconfig` (krok 52).
- **Swarm i tryb roju** — osobne pojęcie, bez odbiorcy (reguła 13).
- **`docker context` jako jedyne źródło** — odrzucone w D96 nr 3.
- **Zdalny plik compose** (pobranie przez SFTP i uruchomienie) — plik czyta
  klient, więc to jest przesył pliku, czyli krok 50 wołany ręcznie.
- **Zdarzenia demona (`GET /events`)** jako źródło odświeżania — wykluczone
  z kroku 51 i zostaje wykluczone.
- **Sieci, wolumeny, `docker stats`** — bez odbiorcy.
- **Automatyczne podnoszenie tunelu przy starcie aplikacji** — tunel wstaje na
  wybór środowiska, bo start nie ma prawa kosztować procesu potomnego (krok 52).

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Docker/Domain/ValueObject/DockerEnvironment.php`, `EnvironmentKind.php` | Moduł/Domain | Nowe — wpis środowiska i jego rodzaj wraz z samowalidacją. |
| `Module/Docker/Application/EnvironmentBook.php`, `EnvironmentBookView.php` | Moduł/Application | Nowe — książka i jej migawka dla ekranu. |
| `Module/Docker/Application/Port/EnvironmentBookPort.php` | Moduł/Application | Nowe — zapis i odczyt `docker.json`. |
| `Module/Docker/Application/TunnelState.php` | Moduł/Application | Nowe — cztery postacie tunelu, posuwane taktem. |
| `Module/Docker/Infrastructure/DockerStateService.php` | Moduł/Infrastructure | Nowe — plik stanu modułu (0600, nieznane klucze przeżywają). |
| `Module/Docker/Infrastructure/DockerContextReader.php` | Moduł/Infrastructure | Nowe — rozbiór NDJSON z `docker context ls`. |
| `Module/Docker/Infrastructure/SocketTunnelService.php` | Moduł/Infrastructure | Nowe — `ssh -M -N -f -L`, sprzątanie dwiema drogami. |
| `Module/Docker/Infrastructure/DockerApiService.php` | Moduł/Infrastructure | Ścieżka gniazda / adres TLS z wpisu; `isSupported()` przestaje być statyczne. |
| `Module/Docker/Infrastructure/ComposeCliService.php` | Moduł/Infrastructure | Przedrostek `DOCKER_HOST`/`DOCKER_TLS_VERIFY`/`DOCKER_CERT_PATH`. |
| `Module/Docker/Presentation/EnvironmentScreen.php`, `EnvironmentFlow.php` | Moduł/Presentation | Nowe — spis środowisk i okna dodania/zmiany. |
| `Module/Docker/Presentation/DockerModule.php` | Moduł/Presentation | Odrzucenie wyłącznie za `ext-curl`; ekran i kwerenda środowisk. |
| `Module/Docker/Presentation/Query/EnvironmentsQuery.php` | Moduł/Presentation | Nowe — `docker.environments`. |
| `Module/Docker/Application/DockerEvent.php` | Moduł/Application | Jedna pozycja: zmiana środowiska. |
| `Module/Docker/lang/pl.php`, `en.php` | Napisy | Punkt 9. |
| `tests/Module/Docker/…`, `tests/Functional/DockerEnvironmentFlowTest.php` | Testy | Punkt 9. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Punkt 10. |

## Kryteria ukończenia

- **Kontenery i obrazy zdalnego demona widać w tych samych panelach**, bez
  rozgałęzienia w kodzie rozmowy z demonem — sprawdzone dla tunelu SSH i dla
  TCP+TLS.
- **Przełączenie środowiska unieważnia obie listy, logi i budowę** — żaden
  wiersz z poprzedniego demona nie przeżywa zmiany.
- **Brak lokalnego gniazda nie odrzuca modułu**; ekran mówi zdaniem, co wybrać.
- **Tunel, który nie wstał, ma własne zdanie** — odróżnialne od „demon nie
  odpowiada".
- **Po wyjściu z aplikacji nie zostaje ani jeden proces `ssh` i ani jedno
  gniazdo** — sprawdzone także przy wyjściu awaryjnym.
- **Compose w środowisku zdalnym podnosi projekt**, a zdanie o granicy pliku
  i montowań pada **przed** podniesieniem.
- **Poświadczenia nie wychodzą kwerendą** — `docker.environments` nie niesie
  ścieżek kluczy ani celu SSH.
- Napisy w obu językach, `bin/render-bench --loop` „przed i po" bez regresji,
  PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
