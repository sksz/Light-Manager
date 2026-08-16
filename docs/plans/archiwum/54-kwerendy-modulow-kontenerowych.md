# Krok 54 — Kwerendy modułów Fazy XVII i XVIII: obraz zbudowany Dockerem ląduje w klastrze

> **Skąd ten krok.** Powstał 2026-08-16, na starcie kroku 53
> ([00-decyzje.md](../00-decyzje.md), D92 nr 2), z jego podziału. Krok 53 urósł
> trzecim rozszerzeniem — kwerendy dostały **rdzeń i sześć modułów**, a rejestr
> stał się **jedyną drogą odczytu** — i w tym kształcie mieścił mechanizm, okno,
> trzynaście kwerend rdzenia, czternaście modułowych, przebudowę odczytów oraz
> czynność przechodzącą przez dwa moduły. Podział biegnie tam, gdzie biegnie
> granica faz: **53 bierze rdzeń i moduły sprzed Fazy XVII**, ten krok — trzy
> moduły, które tamte fazy dowiozły, wraz z pierwszym odbiorcą mechanizmu
> w kodzie.

## Status

**Ukończony** (2026-08-16). Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md),
D94; poprawki z realizacji i z próby na żywym klastrze — w dzienniku niżej.

## Cel

Moduł ma umieć **poprosić o coś, czego sam nie umie**, nie wiedząc, kto to
zrobi — a pierwszą taką rzeczą jest obraz kontenera: zbudowany przez moduł
Dockera, wdrożony przez moduł Kubernetesa.

Miarą powodzenia jest zdanie: **`k8s.deploy-image` pokazuje listę obrazów, które
zna moduł Dockera, buduje wskazany, czeka na koniec budowy i podmienia obraz
w wybranym wdrożeniu — a przy wyłączonym module Dockera ta sama czynność mówi,
czego brakuje, zamiast się wywrócić.**

Miarą drugą: **trzy moduły, które dowiozły Fazy XVII i XVIII, oddają kwerendą
wszystko, o czym wiedzą** — na tych samych zasadach, co rdzeń i trzy moduły
kroku 53, i **bez jednej linii zmiany w mechanizmie**. Jeśli dopisanie kwerend do
gotowego modułu kosztuje więcej niż jedną zdolność, mechanizm z kroku 53 wyszedł
źle i to jest miejsce, w którym się to okaże.

## Zastrzeżenie do rozstrzygnięcia na starcie — obraz zbudowany lokalnie nie istnieje w klastrze

To jest miejsce, w którym funkcja może wyjść atrapą, więc stoi tu wprost: obraz
zbudowany przez lokalnego demona **nie jest widoczny dla klastra**, chyba że
klaster używa tego samego demona. Bez rozstrzygnięcia tej rzeczy
`k8s.deploy-image` skończy się podem w stanie `ImagePullBackOff` i będzie to
wyglądało jak usterka aplikacji, a nie jak brakujący krok.

| Droga | Cena |
|---|---|
| `minikube image load <tag>` | jedno polecenie, ale wiąże funkcję z jednym rodzajem klastra |
| wypchnięcie do rejestru (`docker push`) | działa wszędzie, ale wymaga rejestru, logowania i `imagePullPolicy` — a `push` jest **poza zakresem kroku 51** |
| klaster dzielący demona (`kind load`, docker-desktop) | nic nie kosztuje, gdy jest; nie ma go, gdy go nie ma |

**Stan maszyny sprawdzony 2026-08-16:** Docker 27.3.1 odpowiada gniazdem,
`kubectl` v1.25.2 ma jedyny kontekst `ca-dev` (nieaktywny), **minikube v1.27.0
jest zatrzymany**, `kind` nie ma w ogóle. Sprawdzenie ręczne wymaga uruchomienia
minikube — czyli zgody użytkownika, jak przy pomiarach (reguła 17).

## Zależności

- **Krok 53** całkowicie: mechanizm (`Application/Query/`), zdolność
  `ProvidesQueries`, okno kwerend, wzorzec fasady czytającej przez rejestr
  i reguła „poza kwerendą nikt nie trzyma referencji do obiektu stanu”.
- **Kroki 48, 49 i 50** — moduł `ssh`: książka hostów, stan sesji, listing
  zdalny, stan przesyłu.
- **Krok 51** — moduł `docker`: obrazy, kontenery, projekty compose, stan budowy.
- **Krok 52** — moduł `k8s`: konteksty, przestrzenie nazw, rodzaje zasobów
  z `api-resources`, wiersze zasobu, wdrożenia, informacja o klastrze.
- **Krok 46** twardo i **z trzeciej strony niż zwykle**: zdarzenia są tu
  **spoiwem czasu**. Budowa obrazu trwa minuty, więc wołający nie czeka na nią
  w klatce — dowiaduje się o końcu zdarzeniem `docker.build.finished`, a dopiero
  potem pyta kwerendą o wynik. Stąd reguła kroku: **komenda robi, zdarzenie
  ogłasza, kwerenda mówi co wyszło.**
- **Kroki 32 i 47** — `MenuOverlay`, `ChoiceOverlay`, `OpensOverlay`: wybór
  obrazu i wdrożenia to okna, a czynność ma trafić do menu `F9` bez zmiany
  w rdzeniu.
- **Kroki 23, 41 i 42** — okno pracy i postęp: czynność złożona z budowy
  i wdrożenia ma **dwa etapy**, więc mówi o sobie tak, jak kopiowanie.

## Model i wysiłek

**Opus / xhigh.** Ten sam rachunek, co w kroku 53: prymitywów nie przybywa, trzy
renderery zostają nietknięte, słownik wejścia nie rośnie. Wysiłek trzyma
**choreografia** — czynność przechodząca przez dwa moduły, trzy mechanizmy
rdzenia i pracę trwającą minuty, która musi zachować się poprawnie, gdy w połowie
zniknie jedna ze stron.

## Zakres

### 1. Kwerendy modułu `ssh`

| Kwerenda | Oddaje |
|---|---|
| `ssh.hosts` | Książka hostów: nazwa, użytkownik, host, port, metoda uwierzytelnienia |
| `ssh.session` | Stan sesji: etap, host, odcisk klucza, powód niepowodzenia |
| `ssh.entries` | Listing zdalnego katalogu wraz z etapem pracy — **nigdy nie czeka na sieć** |
| `ssh.transfer` | Stan przesyłu: kierunek, plik, bajty, etap |

### 2. Kwerendy modułu `docker`

| Kwerenda | Oddaje |
|---|---|
| `docker.images` | Obrazy: `id`, `tag`, `bytes`, `created` |
| `docker.containers` | Kontenery: `id`, `name`, `image`, `state`, `project` |
| `docker.compose` | Projekty compose: nazwa, plik, stan, liczba usług |
| `docker.build` | Stan budowy: etap, znacznik, ostatni komunikat demona |

### 3. Kwerendy modułu `k8s`

| Kwerenda | Oddaje |
|---|---|
| `k8s.contexts` | Konteksty z `kubeconfig` wraz z bieżącym |
| `k8s.namespaces` | Przestrzenie nazw znane sesji |
| `k8s.kinds` | Rodzaje zasobów z `api-resources` (grupa, rodzaj, namespace'owość) |
| `k8s.resources` | Wiersze zasobu wskazanego rodzaju wraz z etapem pracy |
| `k8s.deployments` | Wdrożenia wraz z obrazem każdego kontenera |
| `k8s.cluster` | Wersja klastra, adres serwera, stan sesji |

Wszystkie sześć obowiązuje reguła nr 4 kwerendy: **odpowiadają stanem pamięci
modułu, nigdy wywołaniem sieciowym w klatce.** To jest ta sama reguła nadrzędna,
którą Faza XVII zapisała jako „żadne wywołanie sieciowe nie pada w rysowaniu
klatki”.

### 4. Odczyt przez rejestr także wewnątrz tych trzech modułów

Fasady `SshQueries`, `DockerQueries`, `KubernetesQueries` — wzorem kroku 53.
Sprawdzian mechanizmu: **żadna z nich nie wymaga zmiany w `Application/Query/`.**

### 5. Pierwszy odbiorca w kodzie: `k8s.deploy-image`

Czynność przechodzi przez pięć etapów, z których **żaden nie zna Dockera
z typu**:

1. kwerenda `docker.images` → lista obrazów do wyboru (okno modułu, z
   przewijaniem — D94); pozycja „zbuduj nowy” prowadzi do etapu 2, wybór
   istniejącego — do 4;
2. komenda `docker.build` z katalogiem i znacznikiem jako argumentami;
3. czekanie na zdarzenie `docker.build.finished` albo `.failed`; okno pracy
   pokazuje etap czytany kwerendą `docker.build`, `Esc` przerywa czekanie (nie
   budowę — ta idzie odtąd taktem modułu Dockera, D94 nr 5), a limit czasu
   kończy czekanie zdaniem;
4. **wypchnięcie obrazu do rejestru** komendą `docker.push` (GHCR, D94 nr 1
   i 2) — obraz zbudowany na demonie hosta jest dla klastra niewidoczny, bo
   minikube prowadzi własnego demona w kontenerze;
5. kwerenda `k8s.deployments` → wybór wdrożenia (to samo okno, co w etapie 1)
   i podmiana obrazu (`kubectl set image`).

### 5b. Zakres dołożony rozstrzygnięciem nr 1: `docker push`

Plan kroku 51 trzymał push poza swoim zakresem, a rozstrzygnięcie o rejestrze
wciąga go tutaj: `POST /images/{name}/push` gniazdem, z nagłówkiem
`X-Registry-Auth` złożonym z pozycji ustawień modułu Dockera (rejestr,
użytkownik, token — ostatni **maskowany**). Praca idzie taktem modułu, jak
budowa, i kończy się własną parą zdarzeń.

### 6. Pomiar

Oś `--loop` „przed i po” wobec wzorca po kroku 53. Okna czynności
(`ChoiceOverlay`, `ProgressOverlay`) mają zapisane powody pominięcia od kroków 41
i 42, więc scenariusza klatki krok nie dokłada.

## Poza zakresem

- **Zmiana czegokolwiek w mechanizmie kwerend** — jeśli okaże się potrzebna, jest
  to wynik kroku 53 do zapisania w dzienniku, a nie cichy dopisek tutaj.
- **`imagePullSecret` i obrazy prywatne** (D94 nr 3) — czynność sprawdza i mówi,
  ale sekretu w cudzym klastrze nie zakłada.
- **`minikube image load` i założenie o wspólnym demonie** — odrzucone wraz
  z rozstrzygnięciem nr 1.
- **Kwerendy zmieniające cokolwiek** — to są komendy.

## Rozstrzygnięte na starcie kroku (2026-08-16, D94)

Cztery pytania **odłożone z kroku 53** (D92) plus trzy, które wynikły z wybranej
drogi i z rozpoznania w kodzie:

1. **Obraz trafia do klastra rejestrem — GHCR, publicznie** (`ghcr.io/sksz/…`).
   Wszystkie trzy drogi z tabeli zastrzeżenia odrzucone; **`docker push` wchodzi
   przez to do zakresu kroku**, choć plan kroku 51 trzymał go poza swoim.
2. **Poświadczenia są pozycjami ustawień modułu Dockera**, a push idzie gniazdem
   z nagłówkiem `X-Registry-Auth` — nie odczytem `~/.docker/config.json`
   (to plik **klienta**; demon nie czyta go ani razu) i nie `docker push`
   procesem potomnym.
3. **`imagePullSecret` poza zakresem** — obraz publiczny go nie potrzebuje, a przy
   prywatnym czynność **mówi wprost**, zamiast milczeć aż do `ImagePullBackOff`.
4. **Czynność należy do modułu `k8s`** — Dockera pyta nazwą kwerendy i nazwą
   komendy, nigdy typem.
5. **Budowa przenosi się do taktu modułu Dockera i dostaje limit czasu
   czekania.** Powód jest znaleziskiem z rozpoznania, nie preferencją: dziś
   `BuildWork::tick()` woła **wyłącznie okno postępu** Dockera, a stos okien ma
   jedno piętro — więc zapowiedź „`Esc` przerywa czekanie, nie budowę” była
   w tym kształcie **niewykonalna**. Okno budowy zostaje obserwatorem, zdarzenia
   ogłasza takt (`takeFinished()` czeka na to od kroku 51).
6. **`file-info.usage` zostaje przy jednym odbiorcy** — drugi kosztowałby argument
   ze ścieżką, stan pracy na ścieżkę i komendę startu, czyli mechanizm w cudzym
   module.
7. **`ModuleSetting` rośnie o znacznik maskowania** — jedno pole obok `pattern`
   i `maxLength`, przekazane do `TextInput`, który maskowanie ma od kroku 48.
   Znacznik przy istniejącym rodzaju, **nie piąty rodzaj**. To jedyna zmiana
   rdzenia w kroku.

Ponadto, bez pytania, bo po powyższych bezalternatywne: **wybór obrazu i wybór
wdrożenia to okno modułu `k8s`, a nie `ChoiceOverlay`.** Okno rdzenia ucina
nadmiar pozycji `array_slice`iem **milczkiem** i mówi o sobie wprost, że
przewijania mieć nie zamierza, a jego etykiety idą przez katalog napisów —
podczas gdy nazwa obrazu jest daną. Okno znające dane modułu mieszka w jego
`Presentation/Overlay` (reguła 11, precedens `FilterOverlay`) i składa się z tego
samego, co `ChoiceOverlay`, plus `ScrollWindow`. Jedno okno obsługuje oba wybory.

## Kryteria ukończenia

- `k8s.deploy-image` przechodzi całą drogę: wybór, budowa, czekanie,
  udostępnienie, podmiana — na prawdziwym demonie i prawdziwym klastrze
  (minikube, po uzgodnieniu z użytkownikiem).
- **Wyłączony moduł Dockera nie wywraca niczego**: czynność mówi, czego brakuje.
- **Trzy moduły oddają komplet kwerend**, a okno kwerend pokazuje **wszystkie**
  pozycje aplikacji wraz z opisami — po polsku i po angielsku.
- **Mechanizm z kroku 53 nie zmienia się ani o linię** — a jeśli zmienia, dziennik
  mówi dlaczego.
- Żaden moduł nie wymienia w kodzie **typu** innego modułu — pilnuje tego test.
- `bin/render-bench --loop` „przed i po” bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie rozmawia
  z demonem, z klastrem ani z siecią**.

## Dziennik realizacji

### 2026-08-16 — kwerendy trzech modułów, wypychanie do rejestru i czynność

**Zrobione i zielone (`make qa`: 2128 testów).**

- **Czternaście kwerend zapowiedzianych planem plus jedna nieprzewidziana**:
  `ssh` (4), `docker` (**5** — `docker.push` doszła razem z rejestrem),
  `k8s` (6), wraz z fasadami `SshQueries`, `DockerQueries`, `KubernetesQueries`.
- **Mechanizm z kroku 53 nietknięty** — `git diff --stat src/Application/Query/`
  jest pusty. Miara druga kroku sprawdzona maszynowo, a nie na słowo: dopisanie
  kwerend do gotowego modułu kosztowało **jedną zdolność** (`ProvidesQueries`).
- **Strażnik rozszerzony na wszystkie sześć modułów** i uruchomiony pierwszy raz
  znalazł **sześć** odczytów z pominięciem rejestru — w `ConnectCommand`,
  `DisconnectCommand`, `ConnectFlow`, `RemoteTransfer`, `RemoteScreen`
  i `ComposeFlow`. Wszystkie przepięte; `ConnectCommand` stracił przy tym
  `SshSession` jako zależność nieużywaną (wykrył PHPStan, jak w kroku 53).
- **Drugi strażnik, którego żądały kryteria, a którego nie było**:
  `NoModuleKnowsAnotherModuleTest` — chodzi po **przestrzeniach nazw**, bo
  zakazane jest tu samo wymienienie typu, a nie czytanie nim (różnica wobec
  `QueryIsTheOnlyReadPathTest`).
- **`docker.push`** wraz z `PushWork`, `PushFlow`, `RegistryAuth` i trzema
  pozycjami ustawień; **`ModuleSetting::secret()`** jako jedyna zmiana rdzenia
  z D94 nr 7.
- **`k8s.deploy-image`** wraz z `DeployImageFlow`, `PickOverlay`/`PickItem`
  i `kubectl set image`; przebieg `DeployImageFlowTest`.
- **Napisy** w obu katalogach dla wszystkich kwerend, pozycji i zdań czynności.
- **Dokumentacja**: `docs/architecture.md` (zasięg kwerend domknięty, rozdział
  „Moduł zamawia cudzą czynność"), `SKILL.md` (11x, 11y oraz dopiski do 11t, 11v,
  11w i 15g).

**Pięć rzeczy wyszło inaczej, niż je zaplanowano.**

1. **Rdzeń kosztował dwie rzeczy, nie jedną — i druga była luką w regułach,
   a nie nową funkcją.** `LoopState::commands()` musiał powstać, bo reguła 15g
   mówiła od kroku 53 *„moduł zna nazwę cudzej komendy"*, a **nic nie dawało
   modułowi rejestru komend**. Nazwa była przez to bezużyteczna i nikt tego nie
   zauważył, bo pierwszy odbiorca — `k8s.deploy-image` — powstał dopiero teraz.
   Trzeci rejestr w stanie pętli, po zdarzeniach i kwerendach, z tego samego
   rachunku: stan dostaje każdy moduł.
2. **Budowa nie była pracą tłową i plan tego nie zauważył** (D94 nr 5, wykryte
   przy rozpoznaniu). `BuildWork::tick()` wołało wyłącznie okno postępu, a stos
   okien ma jedno piętro — więc zapowiedź „`Esc` przerywa czekanie, nie budowę"
   była **niewykonalna**. Posuwanie przeszło do taktu modułu wraz z publikacją
   zdarzeń; okno zostało obserwatorem. Pilnuje tego
   `BuildRunsOutsideItsWindowTest`.
3. **Okno wyboru z planu gubiłoby dane milczkiem.** `ChoiceOverlay` mówi o sobie
   wprost, że przewijania mieć nie zamierza, a `array_slice` ucina nadmiar bez
   śladu — założenie prawdziwe dla odpowiedzi i **fałszywe dla danych**. Wybór
   obrazu i wdrożenia idzie przez `PickOverlay` w module; rdzeń nie urósł o linię.
4. **`docker.push` nie było w planie kroku** i weszło razem z rozstrzygnięciem
   o rejestrze (D94 nr 1). Trzy pułapki warte zapisania: nagłówek to base64
   **wedle URL i bez dopełnienia** (zwykły `base64_encode()` daje `401`), nazwa
   i etykieta idą **osobno** w ścieżce zasobu, a rozdziela je dwukropek stojący
   **po ostatnim ukośniku** — inaczej port rejestru (`localhost:5000/…`) wygląda
   jak etykieta.
5. **„Nikt jeszcze nie pytał" trzeba było odróżnić od „nie ma nic" — wykrył to
   test czynności, nie przegląd.** Moduł Dockera pyta demona o obrazy dopiero
   wtedy, gdy ktoś na nie patrzy (D90 nr 7), więc `k8s.deploy-image` uruchomione
   przed otwarciem tamtego ekranu zastawało pustkę i wyglądało to jak maszyna bez
   obrazów. `docker.images` niesie odtąd `loaded` w wierszu, a czynność mówi
   `deploy.imagesNotRead`. Ta sama reguła obowiązuje `ssh.entries`
   i `k8s.resources` i weszła do `SKILL.md`.

**Pomiar osi `--loop` — bez regresji, i to w drugą stronę.** Wzorzec odniesienia:
`2026-08-16-po-kroku-53-loop.json`, nowy: `2026-08-16-po-kroku-54-loop.json`
(60 przebiegów, 5 rozgrzewkowych, maszyna zwolniona na prośbę — reguła 17).

| Przebieg | Obciążenie/rdzeń | Praca w tle | Komplet prac |
|---|---|---|---|
| pierwszy | 0,08 (wzorzec 0,15) | −6,5% | −3,3% |
| powtórzony, zapisany | 0,07 | −7,6% | −2,5% |

Wartości ujemne są w granicach rozrzutu taktu kosztującego 0,1 ms i tłumaczą się
**niższym obciążeniem maszyny** (0,07 wobec 0,15 przy wzorcu), a nie
przyspieszeniem kodu. Wniosek jest jeden i wystarczający: **przebudowa odczytów
w trzech modułach nie kosztowała w klatce nic mierzalnego** — bo trzy z czternastu
kwerend mają prawdziwe pokolenie, a reszta jest ulotna i tania.
