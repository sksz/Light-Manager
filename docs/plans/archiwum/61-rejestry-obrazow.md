# Krok 61 — Rejestry obrazów: książka, zawartość i sekret w klastrze

> **Skąd ten krok.** Powstał 2026-08-16 jako ostatni krok **Fazy XX**
> ([00-decyzje.md](../00-decyzje.md), D96); **numer 60 → 61 od 2026-08-18**, gdy
> przed nim stanęła książka adresowa (D104). Domyka pozycję otwartą w kroku 54, gdzie
> rejestr wszedł do aplikacji **jeden**, trzema pozycjami ustawień — bo tyle
> wystarczyło, żeby obraz zbudowany Dockerem trafił do klastra. Ten krok robi
> z tego spis i dokłada dwie rzeczy, których tamten świadomie nie dowiózł:
> **widok zawartości rejestru** i **rejestr prywatny działający z klastrem**.

## Status

**Ukończony z zastrzeżeniem — 2026-08-20.** Trzy etapy z trzech, dokumentacja
domknięta, pomiar rozliczony, `make qa` zielone (2494 testy).

**Zastrzeżenie jest jedno i dotyczy pomiaru, nie zakresu: taktu modułu nie
mierzy żadna z czterech osi**, więc koszt tego kroku — trzy obiegi HTTP
pompowane raz na takt — rozliczono rozumowaniem i konstrukcją, nie liczbą.
Oś `--loop` pokazała szum, ale pokazałaby go także wtedy, gdyby krok kosztował
dziesięć razy więcej: modułów w tym torze nie ma. Jest to **dług pomiarowy
fazy**, wymagający osobnej decyzji (piąta oś albo moduły w torze `--loop`), a nie
dług do spłacenia w tym pliku.

Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md),
**D107** (cztery odpowiedzi, jedna poza podanymi wariantami) oraz wcześniejsze
D96 (nr 1 i 5) i **D104** (rejestr rozdziałem książki). Oba zastrzeżenia startowe
są rozstrzygnięte — sekcja „Dwa zastrzeżenia startowe" opisuje odtąd **stan
sprzed rozstrzygnięcia** i zostaje jako zapis rozumowania, a nie jako pytanie.

Etapy: **(1)** rozdział rejestrów w książce, migracja trzech pozycji ustawień
i wybór rejestru przy `docker.push`; **(2)** rozmowa z rejestrem i widok
zawartości pod `r`; **(3)** `docker.pull` i sekret w klastrze. Każdy kończy się
zieloną bramką.

## Cel

Rejestrów jest wiele, każdy z własnymi poświadczeniami. Obraz da się wypchnąć do
wybranego i pobrać z niego, zawartość rejestru widać **przed** wypchnięciem,
a klaster dostaje sekret, którym rejestr prywatny staje się dla niego czytelny.

Miarą powodzenia jest zdanie: **obraz zbudowany lokalnie ląduje w wybranym
rejestrze prywatnym, a `k8s.deploy-image` wdraża go w klastrze bez ani jednego
ręcznego `kubectl create secret`.**

Miara druga brzmiała pierwotnie „token nie przechodzi przez rejestr kwerend ani
przez wiersz polecenia" i **jest odwołana w pierwszej połowie** (D107 nr 1).
Powód: przesłanka, na której stała — „token nie ma prawa opuścić modułu
Dockera" — przestała być prawdą wraz z krokiem 60, bo `address-book.value`
oddaje pole rodzaju `secret` każdemu, kto zapyta, i mówi to we własnej
dokumentacji. Miara brzmi odtąd: **token nie pojawia się w wierszu polecenia ani
w domyślnych wierszach spisu rejestrów** — zakaz wiersza polecenia zostaje
twardy i pochodzi z kroku 48. Równoważą to dwa zobowiązania: kwerenda
poświadczenia jest `VOLATILE` i **osobna** od `docker.registries`.

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
nazwie — więc dopisanie nie kasuje istniejących. **To była teza do sprawdzenia
na żywym klastrze przed napisaniem kodu**, nie założenie: różnica między „dopisze"
a „podmieni" to w tym miejscu utrata dostępu wdrożenia do jego własnego obrazu.
**Teza potwierdzona pomiarem 2026-08-19** — wynik w dzienniku.

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
| Ustawienia modułu | `ModuleSetting` bierze **wyłącznie skalary**, więc spis rejestrów nie zmieści się w zakładce — od kroku 60 jego miejscem jest **rozdział książki adresowej**, nie plik stanu modułu. |
| `DockerChapter` | Deklaruje **jeden** rozdział (`kind`, `socket`, `target`, `port`, `cert`, `key`, `ca`) dwiema komendami w takcie; drugi rozdział to ta sama droga jeszcze raz. |
| `address-book.value` | Oddaje pole rodzaju `secret` **każdemu, kto zapyta** — i mówi to we własnej dokumentacji. Przegrody między modułami **nie ma** (D104 nr 1). |

## Zależności

- **Krok 54** — `docker.push`, `RegistryAuth`, `k8s.deploy-image` i pozycja
  zasłonięta w ustawieniach; ten krok jest jego dokończeniem.
- **Krok 58** — deklaracja rozdziału książki przez moduł (`DockerChapter`)
  i jeden port zapisu; rejestry idą **tą samą drogą**, drugim rozdziałem.
- **Krok 59** — wybrany klaster jako miejsce, w którym powstaje sekret.
- **Krok 51** — rozmowa nieblokująca z demonem, na której wzoruje się rozmowa
  z rejestrem.
- **Krok 48** — pole maskowane i zakaz podawania poświadczeń wierszem polecenia.
- **Kroki 53 i 54** — kwerendy i choreografia czynności przechodzącej przez dwa
  moduły (`k8s.deploy-image` jako jedyny dotychczasowy precedens).
- **Kroki 19, 27, 28, 32** — okno komend, tabela, pytanie i menu.
- **Krok 60** — książka adresowa. Zależność **rozstrzygnięta przez D104**,
  jeszcze zanim ten krok się zaczął: rejestr wchodzi **rozdziałem** książki,
  a wzorzec książki nie staje po raz czwarty. Moduł Dockera deklaruje przez to
  **dwa rozdziały** — środowiska (58) i rejestry — bo ich pola są rozłączne,
  a wpis książki zostaje jeden.

## Model i wysiłek

**Opus / xhigh.** Krok dotyka **dwóch modułów naraz** i wnosi rozmowę
z rozmówcą, którego aplikacja dotąd nie miała — z uwierzytelnieniem
dwustopniowym i maszyną stanu na trzy obiegi. Prymitywów nie przybywa, słownik
wejścia nie rośnie, trzej tłumacze zostają nietknięci, więc warunek `Fable` nie
zachodzi.

## Zakres

### 1. Rejestr jako rozdział książki adresowej

**Przepisane wobec pierwotnego planu** (D104, D107): `RegistryBook`,
`RegistryBookView` ani drugi klucz w `docker.json` **nie powstają**. Moduł
Dockera deklaruje w książce adresowej **drugi rozdział**, obok tego, który ma od
kroku 58 na środowiska — dwiema komendami w takcie (`address-book.chapter`
i `address-book.field`), jak każdy deklarujący.

Powodem dwóch rozdziałów zamiast jednego jest **rozłączność pól**, a nie
porządek: demon opisuje się gniazdem, celem SSH i ścieżkami TLS, rejestr —
użytkownikiem i tokenem. Wpis książki zostaje **jeden**, więc jedna maszyna ma
prawo być naraz demonem i rejestrem albo tylko jednym z nich — dokładnie tak,
jak D105 opisało wpis będący naraz hostem SSH, demonem i klastrem.

Pola rozdziału: adres (wzorzec z kroku 54 zostaje — host z opcjonalnym portem,
pierwszy znak nie myślnik), użytkownik, token rodzaju **`secret`**, znacznik
„bez TLS" dla rejestru w sieci lokalnej i znacznik „domyślny". Tożsamością wpisu
jest jego identyfikator, nie nazwa (15h).

**Token trzyma się tam, gdzie dziś**: jawnie, w pliku należącym do użytkownika,
z prawami `0600`. Książka nie jest magazynem sekretów i **nie udaje go** —
zdanie z kroku 54 zostaje w mocy, a D105 powtórzyło je dla samej książki
(„maskowanie nie jest zamkiem").

### 2. Migracja trzech pozycji ustawień

Pozycje `registry`, `registryUser` i `registryToken` znikają z zakładki i wchodzą
do książki jako wpis o nazwie z adresu. Migracja idzie **komendami książki**,
nieniszcząco i przez właściciela sekcji — tak, jak D103 opisało migrację trzech
książek modułów, a nie własnym kodem piszącym po cudzej sekcji. Pada raz, przy
pierwszym wczytaniu; wartości nie giną. Zakładka ustawień modułu zostaje z jedną
pozycją (`logLines`) plus tym, co dołożył krok 58.

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

**Przepisane wobec pierwotnego planu** (D107 nr 1). Choreografia jest krótsza od
proponowanej, bo nie broni przegrody, której nie ma:

1. Moduł k8s pyta kwerendą `docker.registries`, które rejestry są (nazwa, adres,
   użytkownik, czy domyślny, czy poświadczenia ustawione — **bez tokenu**).
2. Po wyborze pyta **drugą, osobną kwerendą** o gotową treść
   `.dockerconfigjson` dla wskazanego rejestru. Składa ją moduł Dockera, bo
   format jest pojęciem Dockera; kwerenda jest **`VOLATILE`**, żeby materiał
   uwierzytelnienia nie leżał w pamięci rejestru kwerend.
3. Moduł k8s zapisuje treść do pliku o prawach `0600`, stosuje go
   `kubectl apply -f <plik>` w wybranej przestrzeni nazw, dopina sekret do
   wdrożenia łatą **strategiczną** i kasuje plik — **także wtedy, gdy `apply`
   się nie powiódł**.

Plik nie znika z kroku, tylko **zmienia właściciela**: `kubectl` nie przyjmuje
wejścia (reguła 11v unieważnia `apply -f -`), więc treść i tak musi trafić na
dysk, zanim się ją zastosuje. Nowa komenda modułu Dockera **nie powstaje** —
zastępuje ją kwerenda.

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
| ~~`Module/Docker/Application/RegistryBook.php`, `RegistryBookView.php`~~ | — | **Skreślone** (D104, D107): spis mieszka w książce adresowej, nie we własnej książce modułu. |
| ~~`Module/Docker/Infrastructure/DockerStateService.php`~~ | — | **Skreślone**: drugi klucz w `docker.json` nie powstaje. |
| `Module/Docker/Presentation/DockerChapter.php` | Moduł/Presentation | Drugi rozdział książki — pola rejestru, deklarowane w takcie. |
| `Module/Docker/Application/Port/RegistryPort.php` | Moduł/Application | Nowe — katalog, tagi, manifest; odpowiedzi **stanem**, nie wynikiem. |
| `Module/Docker/Infrastructure/RegistryApiService.php` | Moduł/Infrastructure | Nowe — trzy obiegi (401 → token → wywołanie) na `curl_multi_*`. |
| `Module/Docker/Infrastructure/RegistryAuth.php` | Moduł/Infrastructure | Nagłówek składany **z wpisu**, nie z ustawień. |
| `Module/Docker/Application/PullWork.php` | Moduł/Application | Nowe — pobranie obrazu, kształtem bliźniacze do `PushWork`. |
| `Module/Docker/Presentation/RegistryScreen.php`, `RegistryFlow.php` | Moduł/Presentation | Nowe — spis rejestrów i widok zawartości pod `r`. |
| `Module/Docker/Presentation/Query/RegistriesQuery.php`, `CatalogQuery.php` | Moduł/Presentation | Nowe — `docker.registries` i zawartość. |
| ~~`Module/Docker/Presentation/Command/RegistrySecretCommand.php`~~ | — | **Skreślone** (D107 nr 1): komendy nie ma, treść `.dockerconfigjson` oddaje kwerenda `VOLATILE`; plik `0600` zapisuje moduł k8s, bo to on go stosuje. |
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
- **Token nie pojawia się w wierszu polecenia ani w domyślnych wierszach spisu
  rejestrów** — osobna asercja i osobny przebieg. Miara zmieniona wobec
  pierwotnej (D107 nr 1); przez kwerendę poświadczenia token przechodzi
  świadomie, a kwerenda jest `VOLATILE` i osobna od `docker.registries`.
- **Plik z poświadczeniem ginie także po niepowodzeniu** `apply`.
- **Rejestr bez katalogu nie wygląda na zepsuty** — przechodzi w tryb „podaj
  nazwę obrazu"; spis rejestrów z katalogiem i bez trafia do dziennika.
- **Migracja trzech pozycji ustawień nie gubi poświadczeń.**
- **Rozmowa z rejestrem nie pada w rysowaniu klatki** — pompuje ją takt modułu.
- Napisy w obu językach, pomiar „przed i po" bez regresji, PHPStan `max`,
  PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

### 2026-08-20 — pomiar: żadna oś nie mierzy tego, co ten krok dokłada

**Oś `--loop`: −0,1% i +1,7%** wobec wzorca po kroku 77 (`2026-08-20-po-kroku-61-loop.json`,
obciążenie 0,05 na rdzeń) — szum. **I nie znaczy to „bez regresji", tylko „nie
zmierzone"**: `LoopBenchmarkRunner` składa klatkę z `LoopScenarioScreen` przez
`FrameComposer` i **nie wykonuje taktu modułu** — modułów w tym torze nie ma
w ogóle. Cały koszt tego kroku siedzi w takcie modułu (pompowanie drugiej
rozmowy, posuwanie pobierania, karmienie koordynatora), więc oś go nie widzi.

**Oś sixelowa: bez regresji przypisywalnej kodowi** (`2026-08-20-po-kroku-61.json`,
0,04 na rdzeń). Pierwszy przebieg pokazał **17 regresji** i został odrzucony jako
środowiskowy: obciążenie wynosiło 0,08 na rdzeń wobec 0,03 we wzorcu, a tabela
przesunęła się **w całości**, łącznie z `pustym płótnem` (+9,7%), którego ten
krok nie ma jak dotknąć. Przebieg powtórzony po ostygnięciu dał jedną flagę —
`spis środowisk Dockera` +12,0% — i ta **nie obroniła się przy sprawdzeniu**:
przebieg zawężony, minuty później i przy tym samym kodzie, dał `liście
w kolumnach` **+11,2%** tam, gdzie pełny przebieg dawał **+0,4%**. Powtarzalność
pomiaru na tej maszynie jest dziś rzędu **±10 punktów**, więc flagi +12% nie da
się przypisać kodowi.

Rozstrzyga to drugi, mocniejszy argument — **konstrukcyjny**: poza modułami krok
zmienił w `src/` **dwa pliki i oba przez przeniesienie** (`PickOverlay`,
`PickItem` z modułu Kubernetesa do rdzenia, kod bez zmian), a `ScenarioFactory`
nie tworzy ani jednego, ani drugiego. Klatki scenariuszy nie mają jak wyglądać
inaczej.

**Zapisana luka, której ten krok nie zamyka: taktu modułu nie mierzy żadna
z czterech osi.** Sixelowa, okienkowa i tekstowa mierzą rysowanie; `--loop`
mierzy pętlę **bez modułów**. Dopóki tak jest, koszt rozmowy z demonem, z
rejestrem i z klastrem rozlicza się wyłącznie rozumowaniem, nie liczbą — a to
jest dług pomiarowy fazy, nie tego kroku, i wymaga osobnej decyzji (piąta oś albo
moduły w torze `--loop`).

**Scenariusz widoku zawartości nie powstał — i to jest odstępstwo od §7 planu,
z zapisanym powodem** (`docs/pomiary/README.md`). Panel rysuje `ListView`
z `ListRow`ami niosącymi samą etykietę, czyli **ściśle uboższą treść** niż
`text` i `scrollbar`; scenariusz dałby liczbę nieodróżnialną od tamtych, czyli
tabelę udającą pomiar. Reguła 16b przewiduje na to drugą drogę i tę wybrano.

**Pomiar wykrył przy tym prawdziwy defekt — i to jest jedyna rzecz, którą ten
przebieg naprawdę znalazł.** Sprawdzając, czy oś `--loop` w ogóle dotyka taktu
modułu, wyszło, że takt wołał `registryToken()` — kwerendę oddającą **materiał
uwierzytelnienia** — **przy każdym takcie**, trzydzieści razy na sekundę,
i odrzucał odpowiedź przy wszystkich poza tym jednym, w którym rejestr się
zmienił. Ta sama pułapka, którą krok 59 zapłacił, pytając klaster o wersję
serwera co klatkę. Token idzie odtąd **domknięciem, nie wartością**: koordynator
woła je wyłącznie wtedy, gdy naprawdę zmienia punkt końcowy. Pilnuje tego
osobny przebieg liczący **wywołania** — bo moduł pytający raz wygląda dokładnie
tak samo, jak moduł pytający bez końca.

### 2026-08-19 — etap 3: pobranie obrazu i sekret rejestru w klastrze

Dowiezione: `docker.pull` (praca bliźniacza do wypchnięcia, posuwana taktem),
kwerenda `docker.registry-secret` oddająca gotowy `.dockerconfigjson`, port
`SecretFilePort` z plikiem `0600` i drugi wariant `k8s.deploy-image` — sekret
zakładany i dopinany **bez ani jednego ręcznego `kubectl create secret`**.
`make qa` zielone, 2494 testy.

**Sprawdzone na żywym klastrze kodem produkcyjnym, nie `curl`em ani ręcznym
YAML-em.** Plik legł w `/run/user/1000/lm-secret-lm-registry-proba.json`
z prawami **`600`**; `kubectl apply` założył sekret rodzaju
`kubernetes.io/dockerconfigjson` o poprawnej treści; wywołanie zbudowane przez
`KubectlCall::addPullSecret()` wykonane **dosłownie** dało
`[lm-registry-proba, sekret-cudzy]` przy nietkniętym kontenerze — czyli
dopisało, a nie podmieniło. Plik skasowany, przestrzeń nazw usunięta.

**Dwa błędy projektowe wyłapane przed dowiezieniem, oba w tym samym miejscu.**
Pierwsza wersja dała `PullSecretWork` **ten sam** `ClusterActions`, którym
posługuje się ekran — i było to niewykonalne z dwóch powodów naraz, z których
każdy wystarczał. `ClusterActions` prowadzi **jedną czynność naraz**, a wdrożenie
z rejestru prywatnego zamawia sekret i podmianę obrazu w tej samej chwili, więc
drugie `begin()` porzuciłoby pierwsze; skutek zabiera się **raz**
(`takeOutcome()`), a `ClusterScreen` już go zabiera, więc jeden z dwóch odbiorców
nigdy niczego by nie zobaczył — i to **cicho**, bo `null` znaczy tam „jeszcze nic
się nie stało". Sekret ma odtąd **własny tor czynności**.

**Wniosek na przyszłość:** kanał „zabierz raz" ma **jednego** odbiorcę i nowy
odbiorca znaczy nowy kanał, nie podział istniejącego. Objaw drugiego odbiorcy
jest nie do odróżnienia od pracy, która jeszcze trwa.

**Trzy rzeczy warte zapamiętania z samego etapu.** Sekret powstaje **przed**
podmianą obrazu i kolejność jest warunkiem, nie porządkiem: wdrożenie
przestawione na obraz, do którego nie ma poświadczenia, kończy się
`ImagePullBackOff`. Rejestr rozpoznaje się **po adresie w nazwie obrazu**,
najdłuższym pasującym przedrostkiem (`example.com` i `example.com:5000` to dwa
różne rejestry), a rejestr nieznany książce albo bez poświadczeń znaczy
**„nie trzeba"**, a nie „nie udało się" — i nie zatrzymuje wdrożenia. Plik ginie
**zaraz po zastosowaniu**, a nie na końcu łańcucha, i ginie **także po
niepowodzeniu** — osobny przebieg pilnuje jednego i drugiego.

**Granica, która została:** poświadczenie **przechodzi przez rejestr kwerend**
i jest to świadome (D107 nr 1). Broni tego kształt kodu, nie komentarz: kwerenda
jest `VOLATILE`, jest **osobna** od `docker.registries`, odpowiada wyłącznie na
pytanie o **jeden** wpis, a do wiersza polecenia nie trafia nic — osobna asercja
sprawdza to na **wszystkich** wywołaniach łańcucha, nie na pierwszym.

### 2026-08-19 — teza o łacie strategicznej **potwierdzona na żywym klastrze**

Sprawdzone na `minikube` (serwer v1.25.0, przestrzeń nazw `lm-proba` założona
i skasowana) na wdrożeniu, które **już miało** `imagePullSecrets` i **dwa**
kontenery — bo tylko taki przypadek rozróżnia „dopisze" od „podmieni".

| Co zrobiono | `imagePullSecrets` po | Kontenery |
|---|---|---|
| stan początkowy | `[sekret-istniejacy]` | `api`, `pomocnik` |
| łata **strategiczna** (domyślna) | `[sekret-nowy, sekret-istniejacy]` | nietknięte |
| ta sama łata **dwa razy więcej** | `[sekret-nowy, sekret-istniejacy]` | nietknięte |
| łata `--type=merge` | **`[sekret-scalajacy]`** | nietknięte |

**Trzy wnioski, wszystkie zmierzone.**

1. **Łata strategiczna dopisuje** — klucz scalania po nazwie działa, istniejący
   sekret zostaje. Teza planu potwierdzona, kod etapu trzeciego ma na czym stanąć.
2. **Jest idempotentna**: powtórzenie tej samej łaty **nie mnoży** wpisów. To
   spełnia zapowiedź planu („nazwa sekretu stała, żeby powtórzone wdrożenie nie
   mnożyło sekretów") **bez ani jednej linii kodu po naszej stronie** — nie
   trzeba sprawdzać, czy sekret już jest.
3. **`--type=merge` kasuje istniejący sekret** i to jest dokładnie ta sama
   pułapka, którą krok 54 zapłacił przy kontenerach — tam merge podmieniał całą
   tablicę kontenerów, tu podmienia całą tablicę sekretów. Lekcja jest więc
   **ogólniejsza, niż ją zapisano**: `--type=merge` podmienia **każdą** tablicę,
   a nie tylko kontenery; rodzaj łaty dobiera się do pola, nie do zasobu.

**Sprawdzona przy okazji droga zakładania sekretu**, żeby etap trzeci nie
zderzył się z nią później: manifest `kubernetes.io/dockerconfigjson` zapisany do
pliku o prawach **`600`** i zastosowany `kubectl apply -f <plik>` przechodzi,
a **powtórzone zastosowanie oddaje `unchanged`** — czyli i ta połowa jest
idempotentna. Plik skasowany po zastosowaniu; potwierdza to zapis planu, że
`kubectl` wejścia nie przyjmuje (11v unieważnia `apply -f -`), więc treść musi
trafić na dysk, a właścicielem tej drogi jest moduł k8s.

### 2026-08-19 — etap 2: rozmowa z rejestrem i widok zawartości pod `r`

Dowiezione: port `RegistryPort` z maszyną stanu na **trzy obiegi**
(`RegistryApiService`, `curl_multi_*` nieblokująco, pompowane taktem modułu),
piąta postać ekranu pod klawiszem `r`, kwerenda `docker.catalog` niosąca etap
w każdym wierszu i cele `make registry-start|stop|status`. `make qa` zielone,
2488 testów.

**Które rejestry wystawiają `/v2/_catalog` — odpowiedź zmierzona, nie
przepisana.** Sprawdzone na `registry:2` postawionym celem `make registry-start`:
katalog **jest**, odpowiada `200` już w **pierwszym obiegu**, bo ten rejestr nie
uwierzytelnia. Zmierzone własnym portem, nie `curl`em: `asking→done`, `200`,
treść `{"repositories":["proba/alpine"]}`; `tags/list` tak samo; repozytorium
nieznane oddaje `404` czytane jako **„nie ma"**, a nie jako awarię. Wyjścia
w internet nie było (D107 nr 3), więc o GHCR i Docker Hubie ten dziennik
**niczego nie twierdzi** — plan zakłada, że katalogu nie mają, i tak też
napisana jest druga gałąź widoku, ale zmierzone to nie jest i jest to zapisana
granica.

**Ścieżka trzyobiegowa nie ma jak wywołać się lokalnie** i to jest druga
zapisana granica: `registry:2` nie uwierzytelnia, a postawienie serwera tokenów
byłoby uruchamianiem cudzego oprogramowania po to, żeby mieć co testować. Dowód
ma więc dwie części, obie bez sieci: `RegistryChallengeTest` (rozbiór nagłówka —
w tym **przecinek w `scope`**, który podział po przecinkach rozerwałby w pół,
oddając token na węższe uprawnienie, niż poproszono) i `RegistryConversationTest`
(maszyna stanu posuwana kodami odpowiedzi: `401` z wyzwaniem zamawia obieg
drugi, `401` **po** tokenie jest odmową i nie ponawia się w kółko).

**Trzy rzeczy, na których etap się potknął.**

*Pierwsza:* `DockerJsonReader` miał już prywatne `tags()`, więc metoda portu
musiała nazwać się `registryTags()` — kolizja wyszła dopiero przy `php -l`.

*Druga:* `Ctrl`+`R` **nie dochodził do panelu**. `DockerScreen::handle()`
przechwytuje ten klawisz **przed** rozdziałem na postaci, żeby odświeżyć
kontenery i obrazy; postać rejestru trzeba było dopisać tam, wzorem środowisk.
Objaw był mylący: widok się przełączał, punkt końcowy stał poprawnie
z tokenem, a port nie dostawał ani jednego pytania.

*Trzecia:* pierwszy przebieg testu „otwierał katalog", nie otwierając go — sam
takt bez klawiszy. Poprawione na **prawdziwe klawisze** (`r`, potem `Ctrl`+`R`),
bo kryterium kroku mówi „rozmowa nie pada przy wejściu w widok", a sprawdzić to
da się wyłącznie wtedy, gdy w widok naprawdę się wchodzi.

**Dwie decyzje projektowe warte zapamiętania.** Rozmowę posuwa **takt modułu**,
a nie widok — więc odpowiedź dojdzie także wtedy, gdy użytkownik przełączy się
na kontenery, i po powrocie zastanie ją gotową; to ta sama lekcja, którą krok 54
zapłacił za budowę posuwaną własnym oknem. Katalog ściąga się **na żądanie**
(`Ctrl`+`R`), bo pytanie zadawane samo przy każdym wejściu w widok byłoby ruchem
sieciowym do cudzego serwera, którego nikt nie zamówił — reguła z kroku 48 (`F5`,
nigdy co kilka sekund).

### 2026-08-19 — etap 1: rozdział, migracja i wybór rejestru przy wypchnięciu

Dowiezione: rozdział `registry` w książce adresowej (moduł Dockera deklaruje
odtąd **dwa**), migracja trzech pozycji ustawień, kwerenda `docker.registries`
i pytanie „do którego rejestru" przed nazwą docelową. `make qa` zielone,
2470 testów; przebiegi w `tests/Functional/RegistryFlowTest.php`.

**Dwie rzeczy, na których krok się potknął — obie wyłapane przez strażników,
nie przez oko.**

*Pierwsza:* `DockerChapter` czytał ustawienia przez `LoopState::settings()`, żeby
wziąć trzy stare wartości do migracji. Złapał to `QueryIsTheOnlyReadPathTest`
zdaniem „odczyt z pominięciem rejestru kwerend" — reguła 11w nie ma wyjątku dla
migracji. Droga jest `CoreReader::settings()` i moduł miał ją od kroku 60.

*Druga i poważniejsza:* `RegistriesQuery` czytała książkę **własnym pytaniem do
`address-book.entries`** — czyli kwerenda wołała kwerendę (11w). Strażnik
`QueryRegistry::$asking` oddaje wtedy **pustkę po cichu**: kwerenda była
zarejestrowana, wpisy w książce stały, `DockerQueries::registries()` wołane
z zewnątrz oddawało je poprawnie, a `docker.registries` uparcie zwracało `[]`.
Rozwiązaniem jest wzorzec, który ten moduł ma od kroku 58: **koordynator
karmiony raz na takt** (`Registries`, rodzeństwo `Environments`), a kwerenda
czyta już tylko to, co u niego leży. Pokolenie jest przy tym **prawdziwym
licznikiem**, a nie `VOLATILE`, bo koordynator umie powiedzieć, że spis się
zmienił.

**Wniosek na przyszłość:** dana mieszkająca w **cudzym** module nie da się
przeczytać z wnętrza własnej kwerendy — między nimi musi stanąć koordynator,
i jest to reguła, a nie wzorzec do naśladowania z wyboru. Objaw jest przy tym
mylący: nie ma ani wyjątku, ani wiersza z powodem, tylko pusta odpowiedź.

**Jedno rozstrzygnięcie o rdzeniu po drodze** (na pytanie użytkownika):
`PickOverlay` i `PickItem` **przeprowadziły się z modułu Kubernetesa do rdzenia**
(`Presentation/Ui/Overlay/`). Plan kroku każe pytać o rejestr `ChoiceOverlay`em,
ale tamto okno bierze pozycje jako **klucze katalogu** i ucina nadmiar
`array_slice`iem milczkiem, a nazwy rejestrów są **daną**. `PickOverlay` powstał
w kroku 54 dokładnie z tego powodu, nie zna ani jednego typu modułu (wszystkie
importy miał już rdzeniowe) i właśnie dostał **drugiego odbiorcę** — czyli próbę
z 15b spełnia. Kopia w module Dockera byłaby drugim egzemplarzem mechanizmu,
czyli tym, przed czym ostrzega D103.

**Trzy rzeczy warte zapamiętania z samego etapu.** Pytanie o rejestr pada
**tylko wtedy, gdy jest z czego wybierać** — przy jednym rejestrze byłoby
pytaniem o jedną odpowiedź. Migracja **nie zakłada wpisu, którego nikt nie
zamawiał**: sam adres domyślny bez użytkownika i bez tokenu jest wartością
deklaracji, a nie wyborem, więc książka po pierwszym uruchomieniu zostaje pusta,
jeśli była pusta. Znacznik migracji jest **drugi i osobny** od tego
z kroku 60 — jeden wspólny znaczyłby, że uruchomienie sprzed kroku 61 uznaje
rejestr za przeniesiony, bo środowiska przeniosły się krok wcześniej.

### 2026-08-19 — rozstrzygnięcia startowe: plan zastany był w dwóch miejscach nieaktualny

Krok zaczął się od sprawdzenia, co z jego przesłanek jeszcze obowiązuje — i dwie
nie obowiązywały, obie unieważnione przez krok 60, który wszedł do planu **po**
napisaniu tego dokumentu. Pierwsza była porządkowa (własna książka rejestrów
kontra rozdział — rozstrzygnięte przez D104 na długo przed startem). Druga była
**przesłanką bezpieczeństwa**: cała choreografia z plikiem `0600` broniła
przegrody między modułami, a `address-book.value` żadnej przegrody nie ma
i mówi to wprost we własnej dokumentacji.

Rozstrzygnięcia stoją w D107. Jedno wychodzi poza podane warianty i odwołuje
**miarę powodzenia** zapisaną w planie — pierwszy raz w tym projekcie, gdy
rozstrzygnięcie startowe rusza miarę, a nie zakres.

**Wniosek na przyszłość:** plan napisany przed krokiem, który wszedł przed nim,
trzeba czytać jako **stan wiedzy z dnia napisania**, a nie jako obowiązujące
ustalenia — i pierwszą czynnością kroku jest sprawdzenie, które z jego przesłanek
przeżyły.
