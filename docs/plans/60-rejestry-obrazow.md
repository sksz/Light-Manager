# Krok 60 — Rejestry obrazów: książka, zawartość i sekret w klastrze

> **Skąd ten krok.** Powstał 2026-08-16 jako trzeci i ostatni krok **Fazy XX**
> ([00-decyzje.md](00-decyzje.md), D96). Domyka pozycję otwartą w kroku 54, gdzie
> rejestr wszedł do aplikacji **jeden**, trzema pozycjami ustawień — bo tyle
> wystarczyło, żeby obraz zbudowany Dockerem trafił do klastra. Ten krok robi
> z tego spis i dokłada dwie rzeczy, których tamten świadomie nie dowiózł:
> **widok zawartości rejestru** i **rejestr prywatny działający z klastrem**.

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D96 (nr 1 i 5). Dwie rzeczy do rozstrzygnięcia **na starcie** — patrz sekcja
„Dwa zastrzeżenia startowe".

## Cel

Rejestrów jest wiele, każdy z własnymi poświadczeniami. Obraz da się wypchnąć do
wybranego i pobrać z niego, zawartość rejestru widać **przed** wypchnięciem,
a klaster dostaje sekret, którym rejestr prywatny staje się dla niego czytelny.

Miarą powodzenia jest zdanie: **obraz zbudowany lokalnie ląduje w wybranym
rejestrze prywatnym, a `k8s.deploy-image` wdraża go w klastrze bez ani jednego
ręcznego `kubectl create secret`.**

Miarą drugą: **token nie przechodzi przez rejestr kwerend ani przez wiersz
polecenia** — w żadnym z trzech nowych zastosowań.

## Trudność strukturalna — trzy rzeczy, każda z własnym powodem

**Pierwsza: to jest druga rozmowa HTTP w module i pierwsza z siecią.**
Demon odpowiada po gnieździe unixowym (albo po tunelu, krok 58); rejestr stoi
**w internecie**, a reguła nadrzędna Fazy XVII brzmi: **żadne wywołanie sieciowe
nie pada w rysowaniu klatki**. Rozmowa z rejestrem idzie więc tą samą
maszynerią, co rozmowa z demonem — `curl_multi_*` w trybie nieblokującym,
pompowane raz na takt (`DockerConversation`) — a **nie** nowym, blokującym
klientem. Nowe jest wyłącznie to, dokąd i z jakim nagłówkiem.

**Druga: rejestr uwierzytelnia dwustopniowo.** `GET /v2/` oddaje `401`
z nagłówkiem `WWW-Authenticate: Bearer realm=…,service=…,scope=…`; dopiero
pytanie pod `realm` (z podstawowym uwierzytelnieniem) oddaje token, którym
podpisuje się właściwe wywołanie. To **trzy obiegi zamiast jednego** i wszystkie
trzy są nieblokujące — czyli maszyna stanu, a nie ciąg wywołań.

**Trzecia: katalog rejestru jest rozszerzeniem opcjonalnym.** `/v2/_catalog`
należy do API v2 Dockera, ale specyfikacja OCI go **nie wymaga** — GHCR
i Docker Hub go nie wystawiają. Widok zawartości musi więc mieć **dwa
zachowania**: pełen katalog tam, gdzie jest, i „podaj nazwę obrazu, pokażę
tagi" (`/v2/<nazwa>/tags/list`) wszędzie indziej. Sprawdzenie, **które rejestry
z osiągalnych mają katalog**, należy do kroku, a wynik — do dziennika.

## Dwa zastrzeżenia startowe

**Pierwsze: jak sekret rejestru trafia do klastra, skoro token nie ma prawa
opuścić modułu Dockera.** Reguła 11w zabrania wydawania obcym pola, którego
przekazanie byłoby oddaniem materiału uwierzytelnienia; reguła 15 zabrania
modułowi k8s sięgnąć po książkę rejestrów wprost. Wariant proponowany przez
plan: **przez ścieżkę pliku, nie przez treść** — moduł Dockera kładzie
`.dockerconfigjson` w pliku o prawach `0600` w `XDG_RUNTIME_DIR`, a kwerenda
oddaje **ścieżkę**; moduł k8s stosuje go `kubectl apply -f` i plik ginie zaraz
po. Poświadczenie nie przechodzi wtedy ani przez wiersz polecenia, ani przez
wiersze kwerendy. Warianty odrzucone przez plan (do potwierdzenia albo
odwrócenia na starcie): **pytanie użytkownika o token drugi raz**, w polu
maskowanym modułu k8s (działa, ale ta sama wartość byłaby wtedy w dwóch
miejscach) oraz **`kubectl create secret docker-registry --docker-password=…`**,
czyli hasło w wierszu polecenia — odrzucone twardo, bo krok 48 zakazał tej drogi
dla haseł SSH i powód (`ps` widzi wiersz polecenia) jest tu identyczny.

**Drugie: czym łatać wdrożenie.** `imagePullSecrets` dopina się do wdrożenia
`kubectl patch`em, a krok 54 nauczył się kosztownie, że **`--type=merge`
podmienia całą tablicę**. Tutaj jednak łata idzie **strategicznie** (domyślny
rodzaj dla zasobów wbudowanych), a `imagePullSecrets` ma w API klucz scalania po
nazwie — więc dopisanie nie kasuje istniejących. **To jest teza do sprawdzenia
na żywym klastrze przed napisaniem kodu**, nie założenie: różnica między „dopisze"
a „podmieni" to w tym miejscu utrata dostępu wdrożenia do jego własnego obrazu.

## Stan zastany (sprawdzony w kodzie i na maszynie 2026-08-16)

| Element | Stan |
|---|---|
| `DockerSettings` | Trzy pozycje rejestru: `registry` (domyślnie `ghcr.io`), `registryUser`, `registryToken` (**zasłonięta**, krok 54). Jeden rejestr — z założenia. |
| `RegistryAuth` | Składa `X-Registry-Auth`: base64 **wedle URL i bez dopełnienia**; zwykły `base64_encode()` daje napis, który demon odrzuca z `401`. |
| `PushWork`, `PushProgress`, `PushStage` | Wypchnięcie płynie strumieniem zdań o warstwach; posuwa je **takt modułu**, nie okno (krok 54, D94). |
| `DockerConversation` | `curl_multi_*` w trybie nieblokującym, pompowany raz na takt — maszyneria gotowa do drugiego rozmówcy. |
| `k8s.deploy-image` | Wdraża `kubectl set image` (nie `patch` — merge skasowałby drugi kontener); `imagePullSecret` **świadomie nie zakładany** (D94 nr 3), więc rejestr musi być publiczny. |
| Kwerendy | `docker.push` mówi o postępie; **`docker.registries` nie istnieje**. |
| `kubectl` 1.25 | Ma `create secret docker-registry` (sprawdzone `--help`). |
| Ustawienia modułu | `ModuleSetting` bierze **wyłącznie skalary** — książka rejestrów musi więc mieszkać w pliku stanu modułu, jak książka środowisk z kroku 58. |

## Zależności

- **Krok 54** — `docker.push`, `RegistryAuth`, `k8s.deploy-image` i pozycja
  zasłonięta w ustawieniach; ten krok jest jego dokończeniem.
- **Krok 58** — plik stanu modułu `docker.json` i jeden port zapisu; książka
  rejestrów wchodzi **do tego samego dokumentu** (dwa niezależne zapisy jednego
  pliku to wyścig).
- **Krok 59** — wybrany klaster jako miejsce, w którym powstaje sekret.
- **Krok 51** — rozmowa nieblokująca z demonem, na której wzoruje się rozmowa
  z rejestrem.
- **Krok 48** — pole maskowane i zakaz podawania poświadczeń wierszem polecenia.
- **Kroki 53 i 54** — kwerendy i choreografia czynności przechodzącej przez dwa
  moduły (`k8s.deploy-image` jako jedyny dotychczasowy precedens).
- **Kroki 19, 27, 28, 32** — okno komend, tabela, pytanie i menu.

## Model i wysiłek

**Opus / xhigh.** Krok dotyka **dwóch modułów naraz** i wnosi rozmowę
z rozmówcą, którego aplikacja dotąd nie miała — z uwierzytelnieniem
dwustopniowym i maszyną stanu na trzy obiegi. Prymitywów nie przybywa, słownik
wejścia nie rośnie, trzej tłumacze zostają nietknięci, więc warunek `Fable` nie
zachodzi.

## Zakres

### 1. Rejestr jako wpis książki

`Module/Docker/Domain/ValueObject/ImageRegistry` — nazwa własna (tożsamość),
adres (wzorzec z kroku 54 zostaje: host z opcjonalnym portem, pierwszy znak nie
myślnik), użytkownik, token, znacznik „bez TLS" dla rejestru w sieci lokalnej.
`RegistryBook` w tym samym dokumencie `docker.json`, co środowiska.

**Token trzyma się tam, gdzie dziś**: jawnie, w pliku należącym do użytkownika,
z prawami `0600`. Plik stanu modułu nie jest magazynem sekretów i **nie udaje
go** — zdanie z kroku 54 zostaje w mocy, zmienia się wyłącznie liczba wpisów.

### 2. Migracja trzech pozycji ustawień

Pozycje `registry`, `registryUser` i `registryToken` znikają z zakładki
i wchodzą do książki jako wpis o nazwie z adresu. Migracja pada raz, przy
pierwszym wczytaniu; wartości nie giną. Zakładka ustawień modułu zostaje
z jedną pozycją (`logLines`) plus tym, co dołożył krok 58.

### 3. Wypchnięcie i pobranie

`docker.push` pyta, **do którego** rejestru (`ChoiceOverlay`), a domyślną
odpowiedzią jest rejestr oznaczony jako domyślny. Dochodzi `docker.pull`:
`POST /images/create?fromImage=<nazwa>&tag=<etykieta>` z `X-Registry-Auth`,
odpowiedź strumieniem zdań o warstwach — czyli **ten sam kształt pracy**, co
wypchnięcie, i ta sama droga posuwania (takt modułu, nie okno).

`docker login` **nie wchodzi** i nie jest to przeoczenie: demon nie czyta
`~/.docker/config.json` (to plik klienta), więc nagłówek i tak składa moduł.

### 4. Zawartość rejestru

Czwarta postać ekranu modułu, klawisz **`r`**. Dwa zachowania z „trzeciej
trudności": katalog (`/v2/_catalog`, stronicowany nagłówkiem `Link`) tam, gdzie
rejestr go wystawia, a gdzie nie — pole na nazwę obrazu i lista jego tagów
(`/v2/<nazwa>/tags/list`). Wiersz tagu niesie to, co da się wziąć z manifestu
bez pobierania warstw: cyfrowy skrót, rozmiar sumaryczny i datę.

Rozmowa idzie maszyną stanu z „drugiej trudności": `GET /v2/` → token spod
`realm` → wywołanie właściwe. Odpowiedź `401` po tokenie znaczy **złe
poświadczenia** i ma własne zdanie; `404` na `_catalog` znaczy **rejestr bez
katalogu** i przełącza widok w tryb „podaj nazwę", zamiast mówić o błędzie.

### 5. Sekret rejestru w klastrze

Czynność `k8s.deploy-image` dostaje **drugi wariant**: obraz z rejestru
prywatnego. Choreografia (wariant proponowany, zastrzeżenie pierwsze):

1. Moduł k8s pyta kwerendą `docker.registries`, które rejestry są (nazwa, adres,
   użytkownik, **bez tokenu**).
2. Po wyborze woła komendę modułu Dockera, która kładzie `.dockerconfigjson`
   w pliku `0600` w `XDG_RUNTIME_DIR` i ogłasza zdarzenie; ścieżkę oddaje
   kwerenda.
3. Moduł k8s stosuje plik (`kubectl apply -f`) w wybranej przestrzeni nazw,
   dopina sekret do wdrożenia łatą **strategiczną** i kasuje plik — **także
   wtedy, gdy `apply` się nie powiódł**.

Nazwa sekretu jest wyprowadzona z nazwy wpisu rejestru i **stała**, żeby
powtórzone wdrożenie nie mnożyło sekretów w klastrze.

### 6. Kwerendy i zdarzenia

Nowa **`docker.registries`**: nazwa, adres, użytkownik, czy domyślny, czy
poświadczenia są ustawione (**wartość logiczna, nie treść**). Token do wierszy
**nie wchodzi** — to ta sama granica, którą `ssh.hosts` trzyma dla odcisku
klucza, a `docker.environments` dla ścieżek TLS (krok 58).

Kwerenda zawartości rejestru odpowiada **etapem w każdym wierszu** (reguła 11w:
„czytam", „nie ma nic" i „nikt jeszcze nie pytał" nie mają prawa wyglądać dla
obcego identycznie).

Zdarzenia: wypchnięcie i pobranie zakończone powodzeniem i niepowodzeniem —
pozycjami enuma modułu, słownik zostaje zamknięty konstrukcyjnie.

### 7. Napisy, pomiar, przebiegi

- **Napisy:** nazwy stanów rozmowy z rejestrem, cztery powody niepowodzenia
  (host nieosiągalny, złe poświadczenia, brak katalogu, odmowa zakresu), zdania
  o wypchnięciu i pobraniu, opisy dwóch kwerend i dwóch komend, spis klawiszy.
- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 59 — takt modułu
  rośnie o pompowanie drugiej rozmowy. Widok zawartości dostaje scenariusz
  w `ScenarioFactory`.
- **Przebiegi:** `tests/Functional/RegistryFlowTest.php` — wybór rejestru przy
  wypchnięciu, rejestr bez katalogu, `401` po tokenie, migracja z ustawień,
  sekret zakładany i plik kasowany **także po niepowodzeniu**, token nieobecny
  w wierszach kwerendy (osobna asercja, bo to jest granica bezpieczeństwa,
  a nie szczegół). **Żaden test nie rozmawia z prawdziwym rejestrem** — atrapa
  portu, jak `StubDockerApi`.

### 8. Dokumentacja

`docs/architecture.md` — rejestr jako drugi rozmówca HTTP modułu, dwustopniowe
uwierzytelnienie, katalog jako rozszerzenie opcjonalne. `SKILL.md` — zdanie
graniczne: **poświadczenie przechodzi między modułami przez plik o prawach
`0600`, nigdy przez wiersze kwerendy ani przez wiersz polecenia**, wraz
z powodem i dwoma odrzuconymi wariantami. `README.md` — jak dodać rejestr
prywatny i co trzeba mieć po stronie klastra.

## Poza zakresem

- **`docker login` i `~/.docker/config.json`** — demon tego pliku nie czyta.
- **Usuwanie tagów i obrazów z rejestru** (`DELETE /v2/…`) — rzecz nieodwracalna
  po stronie cudzego serwera; osobna decyzja, jeśli w ogóle.
- **Podpisy obrazów (cosign, Notary) i skanowanie podatności** — osobne
  narzędzia i osobny ekran.
- **Wyszukiwanie w Docker Hubie** (`/v1/search`) — inne API i inny rozmówca.
- **Rejestr jako źródło dla `k8s.deploy-image` bez modułu Dockera** — czynność
  zostaje choreografią dwóch modułów (krok 54).
- **Odświeżanie tokenu w tle** — token dostaje się na wywołanie i ginie z nim;
  własny magazyn tokenów byłby mechanizmem bez odbiorcy.
- **Rejestry z uwierzytelnieniem certyfikatem klienta** — inny wymiar niż
  użytkownik z tokenem; wchodzi wyłącznie z odbiorcą.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Docker/Domain/ValueObject/ImageRegistry.php` | Moduł/Domain | Nowe — wpis rejestru wraz z samowalidacją. |
| `Module/Docker/Application/RegistryBook.php`, `RegistryBookView.php` | Moduł/Application | Nowe — spis i migawka. |
| `Module/Docker/Infrastructure/DockerStateService.php` | Moduł/Infrastructure | Drugi klucz w `docker.json` (środowiska + rejestry). |
| `Module/Docker/Application/Port/RegistryPort.php` | Moduł/Application | Nowe — katalog, tagi, manifest; odpowiedzi **stanem**, nie wynikiem. |
| `Module/Docker/Infrastructure/RegistryApiService.php` | Moduł/Infrastructure | Nowe — trzy obiegi (401 → token → wywołanie) na `curl_multi_*`. |
| `Module/Docker/Infrastructure/RegistryAuth.php` | Moduł/Infrastructure | Nagłówek składany **z wpisu**, nie z ustawień. |
| `Module/Docker/Application/PullWork.php` | Moduł/Application | Nowe — pobranie obrazu, kształtem bliźniacze do `PushWork`. |
| `Module/Docker/Presentation/RegistryScreen.php`, `RegistryFlow.php` | Moduł/Presentation | Nowe — spis rejestrów i widok zawartości pod `r`. |
| `Module/Docker/Presentation/Query/RegistriesQuery.php`, `CatalogQuery.php` | Moduł/Presentation | Nowe — `docker.registries` i zawartość. |
| `Module/Docker/Presentation/Command/RegistrySecretCommand.php` | Moduł/Presentation | Nowe — plik `.dockerconfigjson` o prawach `0600`. |
| `Module/Docker/Application/DockerSettings.php` | Moduł/Application | Trzy pozycje wychodzą; migracja do książki. |
| `Module/Kubernetes/Application/ClusterActions.php`, `Presentation/DeployImageFlow.php` | Moduł/k8s | Drugi wariant wdrożenia: sekret rejestru i łata strategiczna. |
| `lang/pl.php`, `lang/en.php` obu modułów | Napisy | Punkt 7. |
| `tests/Module/Docker/…`, `tests/Functional/RegistryFlowTest.php` | Testy | Punkt 7. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Punkt 8. |

## Kryteria ukończenia

- **Obraz ląduje w wybranym rejestrze prywatnym**, a `k8s.deploy-image` wdraża
  go w klastrze **bez ręcznego `kubectl create secret`**.
- **Łata dopina sekret, zamiast podmieniać listę** — sprawdzone na wdrożeniu,
  które już jakiś sekret ma (teza z zastrzeżenia drugiego potwierdzona albo
  obalona, a wynik w dzienniku).
- **Token nie pojawia się w wierszach kwerendy ani w wierszu polecenia** —
  osobna asercja i osobny przebieg.
- **Plik z poświadczeniem ginie także po niepowodzeniu** `apply`.
- **Rejestr bez katalogu nie wygląda na zepsuty** — przechodzi w tryb „podaj
  nazwę obrazu"; spis rejestrów z katalogiem i bez trafia do dziennika.
- **Migracja trzech pozycji ustawień nie gubi poświadczeń.**
- **Rozmowa z rejestrem nie pada w rysowaniu klatki** — pompuje ją takt modułu.
- Napisy w obu językach, pomiar „przed i po" bez regresji, PHPStan `max`,
  PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
