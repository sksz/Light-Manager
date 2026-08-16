# Krok 54 — Kwerendy modułów Fazy XVII i XVIII: obraz zbudowany Dockerem ląduje w klastrze

> **Skąd ten krok.** Powstał 2026-08-16, na starcie kroku 53
> ([00-decyzje.md](00-decyzje.md), D92 nr 2), z jego podziału. Krok 53 urósł
> trzecim rozszerzeniem — kwerendy dostały **rdzeń i sześć modułów**, a rejestr
> stał się **jedyną drogą odczytu** — i w tym kształcie mieścił mechanizm, okno,
> trzynaście kwerend rdzenia, czternaście modułowych, przebudowę odczytów oraz
> czynność przechodzącą przez dwa moduły. Podział biegnie tam, gdzie biegnie
> granica faz: **53 bierze rdzeń i moduły sprzed Fazy XVII**, ten krok — trzy
> moduły, które tamte fazy dowiozły, wraz z pierwszym odbiorcą mechanizmu
> w kodzie.

## Status

**Nie rozpoczęty.** Zablokowany przez krok 53 (mechanizm, okno, fasady).

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

1. kwerenda `docker.images` → lista obrazów do wyboru (`ChoiceOverlay`);
   pozycja „zbuduj nowy” prowadzi do etapu 2, wybór istniejącego — do 4;
2. komenda `docker.build` z katalogiem i znacznikiem jako argumentami;
3. czekanie na zdarzenie `docker.build.finished` albo `.failed`; okno pracy
   pokazuje etap, `Esc` przerywa czekanie (nie budowę — ta należy do tamtego
   modułu);
4. udostępnienie obrazu klastrowi — wedle rozstrzygnięcia nr 1;
5. kwerenda `k8s.deployments` → wybór wdrożenia i podmiana obrazu
   (`kubectl set image` albo `apply`).

### 6. Pomiar

Oś `--loop` „przed i po” wobec wzorca po kroku 53. Okna czynności
(`ChoiceOverlay`, `ProgressOverlay`) mają zapisane powody pominięcia od kroków 41
i 42, więc scenariusza klatki krok nie dokłada.

## Poza zakresem

- **Zmiana czegokolwiek w mechanizmie kwerend** — jeśli okaże się potrzebna, jest
  to wynik kroku 53 do zapisania w dzienniku, a nie cichy dopisek tutaj.
- **Rejestr obrazów i `docker push`** — wchodzi tylko wtedy, gdy rozstrzygnięcie
  nr 1 wybierze tę drogę.
- **Kwerendy zmieniające cokolwiek** — to są komendy.

## Do rozstrzygnięcia na starcie kroku

Cztery pytania **odłożone z kroku 53** (D92) wraz z czynnością, której dotyczą:

1. **Jak obraz trafia do klastra** — `minikube image load`, rejestr i `push`, czy
   założenie o wspólnym demonie. Bez tego czynność jest atrapą.
2. **Czyja jest czynność `deploy-image`** — modułu Kubernetesa (rekomendacja),
   modułu Dockera, czy trzeciego modułu spinającego.
3. **Czy czekanie na cudze zdarzenie ma limit czasu** i co się dzieje po jego
   upływie — budowa trwa dalej u siebie, więc porzucenie czekania nie może jej
   ubić.
4. **Czy `file-info.usage` dostaje drugiego odbiorcę w kodzie** — wagę katalogu
   kontekstu budowy pokazywaną przed wysłaniem go do demona.

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

_(pusty — krok nie rozpoczęty)_
