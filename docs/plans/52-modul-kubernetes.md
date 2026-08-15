# Krok 52 — Moduł `k8s`: konteksty, zasoby klastra i logi

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 51 i 53 jako środkowa
> trzecia Fazy XVIII ([00-decyzje.md](00-decyzje.md), D85). Mechanizmu rdzenia
> **nie rusza żadnego** — i to jest jego główna właściwość: bierze port
> rozbudowany w kroku 51 i sprawdza, czy tamta zmiana wystarczy komuś, kto przy
> niej nie stał.

## Status

**Nie rozpoczęty.** Zablokowany przez krok 51 (kilka prac tłowych naraz).

## Cel

Aplikacja ma pokazywać, co dzieje się w klastrze Kubernetes wskazanym przez
`kubeconfig`, i pozwalać obejrzeć zasób, przeczytać jego logi i zastosować plik.

Miarą powodzenia jest zdanie: **`Ctrl`+`U` pokazuje pody wybranego kontekstu
i przestrzeni nazw, `Enter` otwiera opis, `l` — logi płynące na żywo,
a niedostępny klaster kończy się zdaniem w pasku stanu po sekundzie, a nie
zawieszeniem aplikacji.**

## Zastrzeżenie do rozstrzygnięcia na starcie — klastra nie ma pod ręką

Maszyna projektu ma `kubectl`, `helm` i `minikube`, ale **żadnego działającego
klastra**: jedyny kontekst (`ca-dev`) nie jest bieżący, więc `kubectl` idzie na
`localhost:8080` i dostaje odmowę połączenia, a minikube jest zatrzymany.

Wynikają z tego dwie rzeczy, obie do rozstrzygnięcia **przed pierwszą linią
kodu**. Pierwsza: sprawdzenie kroku wymaga **uruchomienia minikube**, czyli
zgody użytkownika i zwolnienia maszyny — tak samo, jak reguła 17 każe prosić
przed pomiarem. Druga, ważniejsza dla kodu: **stan „nie ma klastra” jest tu
stanem zwykłym, a nie awarią** — i to on, a nie widok pełen podów, jest
pierwszym, który krok musi narysować poprawnie.

Klient jest ponadto **stary**: `kubectl` v1.25.2 z 2022 roku. Różnica większa niż
jeden wydany numer wobec serwera jest niewspierana, więc moduł ma **pokazać obie
wersje i nie udawać, że wie lepiej** — a nie próbować to obchodzić.

## Zależności

- **Krok 51** twardo i w jednym miejscu: `kubectl logs -f` to praca długa,
  a lista podów odświeżana obok niej — druga. Bez rozbudowy portu jedna z nich
  ubijałaby drugą. Poza tym punktem krok jest od 51 **niezależny**: nie zna
  Dockera, nie czyta jego danych i nie sięga do jego modułu (reguła 15). Ich
  spotkanie jest treścią kroku 53.
- **Krok 26** — wzorzec pracy tłowej: uruchamiam i nie czekam, zaglądam
  i nie blokuję, sprzątam dwiema drogami. Wraz z regułą, którą trzeba tu wypełnić
  osobno: **potomek nie dostaje wejścia**, więc `kubectl apply -f -` **nie jest
  wykonalne** — plik podaje się ścieżką.
- **Krok 20 i 21** — kontrakt modułu i jego sprawdzian; rdzeń kosztuje jedną
  linię w `Bootstrapie`.
- **Krok 27, 24, 29, 28** — `Table` (pody mają pięć kolumn: nazwa, gotowość,
  stan, restarty, wiek), `Split` (lista i opis), `TextView` (logi
  i opis zasobu w YAML-u), `ConfirmOverlay` (usunięcie poda jest nieodwracalne).
- **Krok 45** — `NeedsTick`: logi płyną, gdy ekranu nie widać; ten sam warunek
  co w kroku 51.
- **Krok 22** — `SectionState`: opis zasobu składa się ze zwijanych sekcji,
  jak opis pliku w module `FileInfo`.
- **Krok 46** — `DeclaresEvents`: „zastosowano plik”, „usunięto zasób”,
  „utracono połączenie z klastrem”.
- Od Fazy XVII (48–50) nie zależy i ona nie zależy od niego. Punkt styku
  z krokiem 48 jest treściowy, nie kodowy: **oba moduły rozmawiają z czymś poza
  maszyną i oba muszą umieć powiedzieć „tamta strona nie odpowiada”.**

## Model i wysiłek

**Opus / high.**

Kontraktu rdzenia krok nie rusza, mechanizmu nie wnosi, komponentu nie dokłada —
wszystko, czego potrzebuje, powstało w krokach 26, 27, 29 i 51. Trudność jest
jedna i wąska: **czas oczekiwania**. Każde wywołanie `kubectl` może wisieć aż do
własnego limitu, więc limit jest częścią każdego polecenia, a nie ozdobą; do tego
dochodzi rozbieranie wyjścia narzędzia, które bywa nowsze albo starsze od
klastra.

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| `kubectl` | **v1.25.2**, zbudowany 2022-09-21 — stary wobec dzisiejszych klastrów |
| Konteksty | Jeden (`ca-dev`), **żaden nie jest bieżący** — kolumna `CURRENT` pusta |
| Klaster | **Nieosiągalny**: `kubectl get ns` kończy się „connection to localhost:8080 refused” |
| `minikube` | Zainstalowany, **zatrzymany** (`host: Stopped`) |
| `helm` | Zainstalowany — poza zakresem tego kroku |
| Praca tłowa | Kilka naraz **od kroku 51**; potomek nadal bez wejścia |
| `src/Module/Kubernetes/` | Nie istnieje |

## Zakres

### 1. Port `kubectl` i jego reguły

`Module\Kubernetes\Application\Port\KubectlPort` — polecenia składane
z argumentów (cytowanie przez `escapeshellarg()`, jak każe kontrakt portu
tłowego), wynik jako `-o json`, **limit czasu obowiązkowy w każdym wywołaniu**
(`--request-timeout` plus limit samego procesu).

Bez `kubectl` w `PATH` moduł degraduje się z komunikatem, jak moduł dźwięku bez
rozszerzenia (reguła 11o).

### 2. Kontekst i przestrzeń nazw

Wybór kontekstu z `kubectl config get-contexts` i przestrzeni nazw z listy;
oba zapamiętywane w ustawieniach modułu. **Brak bieżącego kontekstu jest stanem
do narysowania**, nie błędem: ekran mówi, co wybrać, a nie „connection refused”.

### 3. Lista zasobów

Pody jako pierwszy i obowiązkowy rodzaj (`kubectl get pods -o json`), za nimi —
wedle rozstrzygnięcia nr 2 — wdrożenia i usługi. Wiersz to `TableRow`; odświeżanie
na żądanie (`F5`) i ewentualnie z zegara w takcie.

### 4. Opis zasobu

Prawy panel: dane z `get -o json` ułożone w **zwijane sekcje** (krok 22) —
tożsamość, stan, kontenery, zdarzenia. Surowy YAML jako osobny widok
(`TextView`), jeśli rozstrzygnięcie nr 3 go wpuści.

### 5. Logi

`kubectl logs -f` jako praca długa, pompowana w takcie, pokazywana `TextView`em
z tą samą górną granicą bufora, co w module Dockera. Wybór kontenera, gdy pod ma
ich kilka.

### 6. Zastosowanie i usunięcie

`kubectl apply -f <ścieżka>` — **ścieżką, nie wejściem standardowym**, bo port
potomkowi wejścia nie daje. Ścieżka z pola tekstowego albo z `ModuleContext`
(katalog przeglądarki). Usunięcie zasobu przez `ConfirmOverlay` w wariancie
`dangerous`.

To jest zarazem **droga, którą wejdzie krok 53**: wdrożenie obrazu zbudowanego
Dockerem kończy się `apply` albo `set image`, więc czynność ma tu powstać
w jednym miejscu (reguła 11n: czynność o dwóch wejściach mieszka raz).

### 7. Pomiar

Oś `--loop` „przed i po”. Scenariusza klatki krok nie dokłada — `Table`,
`TextView` i sekcje są mierzone przez `columns`, `text-view` i `sections`; powód
pominięcia idzie do [docs/pomiary/README.md](../pomiary/README.md).

## Poza zakresem

- **Helm** — osobne narzędzie, osobny model danych, osobny krok, jeśli w ogóle.
- **`kubectl exec` i `port-forward`** — pierwszy potrzebuje wejścia dla potomka
  (port go nie daje), drugi jest procesem żyjącym bez widocznego skutku
  w aplikacji.
- **Edycja zasobu** (`kubectl edit`) — aplikacja nie ma edytora tekstu.
- **Obserwowanie zmian przez API klastra** (`watch`) — wymagałoby klienta HTTPS
  z certyfikatami z `kubeconfig`, czyli drugiej drogi technicznej w module,
  który właśnie dostał pierwszą.
- **Uruchamianie i zatrzymywanie minikube z aplikacji** — to jest zarządzanie
  maszyną, nie klastrem.
- **Zasoby własne (CRD), RBAC, węzły** — wchodzą, gdy będą miały odbiorcę.
- **Wielu klastrów naraz** — jeden kontekst na raz, wzorem „jedna sesja” z kroku
  48.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Kubernetes/Domain/ValueObject/{ResourceRef,ContextName,Namespace}.php` | Moduł/Domain | **Nowe** |
| `Module/Kubernetes/Application/Port/KubectlPort.php` | Moduł/Application | **Nowy** |
| `Module/Kubernetes/Application/{ResourceList,LogStream,ClusterState}.php` | Moduł/Application | **Nowe** — stany oglądane co klatkę |
| `Module/Kubernetes/Application/UseCase/{SelectContext,ApplyManifest,DeleteResource}UseCase.php` | Moduł/Application | **Nowe** |
| `Module/Kubernetes/Infrastructure/{KubectlService,UnavailableKubectlService,ResourceJsonParser}.php` | Moduł/Infrastructure | **Nowe** |
| `Module/Kubernetes/Presentation/{KubernetesModule,ClusterScreen,ResourcePane,DescribePane}.php` | Moduł/Presentation | **Nowe** |
| `Module/Kubernetes/Presentation/Command/*.php` | Moduł/Presentation | `k8s.context`, `k8s.namespace`, `k8s.get`, `k8s.logs`, `k8s.apply`, `k8s.delete` |
| `Module/Kubernetes/lang/{pl,en}.php` | Napisy | Nagłówki kolumn, stany zasobów, powody niepowodzeń, zdanie o braku kontekstu |
| `Presentation/Cli/Bootstrap.php` | Rdzeń | **Jedna linia** |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Moduł i jego reguła limitu czasu |
| testy | Testy | **Żaden test nie wywołuje `kubectl`**: parser na zapisanym JSON-ie (pody w kilku stanach, lista pusta, wyjście błędu), stan „brak kontekstu”, port za atrapą |

## Do rozstrzygnięcia na starcie kroku

1. **Litera skrótu** — propozycja `u` (kUbernetes), bo `k` zamawia krok 51.
   Rozstrzygnąć razem z tamtą.
2. **Które rodzaje zasobów wchodzą** — same pody, czy pody + wdrożenia + usługi.
   Każdy rodzaj to inny zestaw kolumn i inny opis.
3. **Czy jest widok surowego YAML-a** zasobu, czy wyłącznie sekcje.
4. **Skąd bierze się limit czasu** — stała, pozycja ustawień modułu, czy jedno
   i drugie.
5. **Czy lista odświeża się z zegara**, czy wyłącznie na `F5` — zegar znaczy
   proces potomny co kilka sekund, bez końca.
6. **Co robi moduł przy niezgodnej wersji klienta i serwera** — ostrzega raz,
   ostrzega zawsze, czy odmawia czynności zmieniających.
7. **Czy `apply` wchodzi w tym kroku** — jest czynnością zmieniającą klaster,
   a jej **jedynym odbiorcą poza użytkownikiem** jest krok 53 (to samo pytanie,
   co przy `docker.build` w kroku 51).
8. **Czy sprawdzenie ręczne idzie na minikube** (wymaga zgody i zwolnienia
   maszyny), czy na klastrze `ca-dev` podanym przez użytkownika.

## Kryteria ukończenia

- `Ctrl`+`U` pokazuje pody wybranego kontekstu; zmiana przestrzeni nazw zmienia
  listę.
- **Brak bieżącego kontekstu i niedostępny klaster mają własne zdania** — żadne
  z nich nie wygląda jak awaria aplikacji.
- Każde wywołanie `kubectl` kończy się **najpóźniej po swoim limicie**, a przez
  ten czas pętla rysuje klatki.
- Logi płyną na żywo i dają się przewijać; drugi pod otwarty w logach zastępuje
  pierwszy, a nie mnoży prac bez końca.
- `apply` z pliku działa i mówi, co zrobił; usunięcie pyta.
- Wersje klienta i serwera są widoczne w opisie modułu.
- `bin/render-bench --loop` „przed i po” bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie wywołuje
  `kubectl`**.

## Dziennik realizacji

_(pusty — krok nie rozpoczęty)_
