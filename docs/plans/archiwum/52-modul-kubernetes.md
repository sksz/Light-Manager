# Krok 52 — Moduł `k8s`: konteksty, zasoby klastra i logi

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 51 i 53 jako środkowa
> trzecia Fazy XVIII ([00-decyzje.md](../00-decyzje.md), D85). Mechanizmu rdzenia
> **nie rusza żadnego** — i to jest jego główna właściwość: bierze port
> rozbudowany w kroku 51 i sprawdza, czy tamta zmiana wystarczy komuś, kto przy
> niej nie stał.

## Status

**Ukończony z zastrzeżeniem** (2026-08-16). Rozstrzygnięcia startowe zapadły
przed pierwszą linią kodu ([00-decyzje.md](../00-decyzje.md), **D91**), sprawdzenie
poszło na żywym minikube, a pomiar `--loop` — na maszynie z zatrzymanym klastrem.

**Zastrzeżenie jest jedno i dotyczy rdzenia**: plan zapowiadał, że krok „nie
rusza żadnego mechanizmu rdzenia”, a rusza **jeden** — port pracy tłowej oddaje
odtąd wypis pracy trwającej (D91 nr 12), bo bez tego `kubectl logs -f` nie miał
jak powiedzieć ani słowa. Zmiana ma własne testy i nie dotyka żadnego
z dotychczasowych odbiorców. Drugie, mniejsze: **klatki nikt nie oglądał pod
XTermem** — dziennik niżej.

**Trzy z nich zmieniają zakres zapisany niżej** i zostały w nim naniesione:
rodzajów zasobów jest **tyle, ile ma klaster** (nie trzy wybrane, nr 2), ekran
prowadzi **drzewo grup i rodzajów** obok listy (nr 3 — dokłada zależność od kroku
31), a **wartość w sekrecie daje się zmienić** (nr 10 — znosi punkt „Poza
zakresem” o edycji zasobu).

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
- **Krok 31** — `TreeView`, `TreeNode` i `TreeState`. **Zależność dopisana
  2026-08-16** (D91 nr 3): plan nie przewidywał drzewa, bo nie przewidywał
  wszystkich rodzajów zasobów. Lewy panel prowadzi **grupy API → rodzaje →
  zasoby**, więc obowiązuje tu wszystko, co krok 31 ustalił: spłaszcza **moduł**,
  nie komponent; kursor drzewa jest **kluczem, nie numerem**; gałąź czyta się
  **na żądanie i najwyżej jedną na klatkę** — a tutaj czytanie gałęzi znaczy
  proces potomny, więc reguła jest ostrzejsza niż przy katalogach na dysku.
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

### 3. Katalog rodzajów i drzewo (D91 nr 2 i 3)

**Rodzaje bierze się z klastra, nie z listy w kodzie.** Katalog czyta
`kubectl api-resources -o wide` — i jest to **jedyne miejsce modułu rozczytujące
tekst**, bo klient 1.25 nie umie tu JSON-a (`-o` przyjmuje `wide` i `name`).
Katalog niesie nazwę, skróty, grupę, namespace'owość i czasowniki; pamięta się go
per kontekst, bo CRD dochodzą i znikają.

Lewy panel to `TreeView`: **grupy API → rodzaje → zasoby**. Rozwinięcie rodzaju
czyta jego listę (`kubectl get <rodzaj> -o json`) — jedna gałąź na klatkę,
a odczyt jest procesem potomnym, nie wywołaniem funkcji.

### 4. Lista i opis zasobu

Prawy panel pokazuje **listę wybranego rodzaju** albo **opis wybranego zasobu**
— zależnie od tego, na czym stoi drzewo; w głąb wchodzi się w obu panelach.

Kolumny listy (D91 nr 4): ogólne z `metadata` (nazwa, przestrzeń nazw, wiek) dla
**każdego** rodzaju, a rodzaje znane dostają **pakiet kolumn** liczony z JSON-a —
pody `READY`/`STATUS`/`RESTARTS`, wdrożenia `READY`/`UP-TO-DATE`/`AVAILABLE`,
usługi `TYPE`/`CLUSTER-IP`/`PORTS`, sekrety `TYPE`/`DATA`. Wiersz to `TableRow`.

Opis: dane z `get -o json` w **zwijanych sekcjach** (krok 22) — tożsamość, stan,
kontenery, zdarzenia — a `y` przełącza na **surowy YAML** w `TextView`
(rozstrzygnięcie nr 3 wpuściło go: D91 nr 5).

### 4b. Sekrety: zamaskowane, odsłaniane i zmienialne (D91 nr 10)

Wartości pod kluczami są **ukryte** w liście, w opisie i w widoku YAML; `x`
odsłania wybraną. Edycja obejmuje **zmianę wartości, dodanie klucza i skasowanie
klucza**, a wpisać wolno base64 albo tekst surowy do zakodowania. Zapis idzie
`kubectl patch --type=merge -p '<json>'` — **argumentem, nigdy wejściem
standardowym**, bo port potomkowi wejścia nie daje.

Powód maskowania jest wymierny, nie ostrożnościowy: `core.dump` z kroku 38
zapisuje klatkę na dysk, a zrzut ekranu z hasłem w środku jest nieodwracalny.

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
pominięcia idzie do [docs/pomiary/README.md](../../pomiary/README.md).

## Poza zakresem

- **Helm** — osobne narzędzie, osobny model danych, osobny krok, jeśli w ogóle.
- **`kubectl exec` i `port-forward`** — pierwszy potrzebuje wejścia dla potomka
  (port go nie daje), drugi jest procesem żyjącym bez widocznego skutku
  w aplikacji.
- ~~**Edycja zasobu** (`kubectl edit`) — aplikacja nie ma edytora tekstu.~~
  **Zniesione 2026-08-16** (D91 nr 10), i to wąsko: zmienia się **wartość pod
  kluczem w `Secret`cie** (oraz dodaje i kasuje klucze), a nie dowolne pole
  dowolnego zasobu. Droga to `kubectl patch --type=merge -p`, czyli pole tekstowe
  i jeden argument — nie edytor. Dowolne pole dowolnego zasobu **zostaje poza
  zakresem** i wymagałoby edytora, którego aplikacja nadal nie ma.
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

## Rozstrzygnięcia startowe — wszystkie podjęte 2026-08-16 (D91)

| # | Pytanie | Odpowiedź |
|---|---|---|
| 1 | Litera skrótu | `Ctrl`+`K` — przydzielone wcześniej, D90 nr 2; pytania nie zadawano |
| 2 | Które rodzaje zasobów | **Wszystkie**, z `api-resources` — nie trzy wybrane |
| 3 | Widok surowego YAML-a | Sekcje domyślnie, YAML pod klawiszem `y` |
| 4 | Skąd limit czasu | **Pozycja ustawień modułu** (liczba z listy przystanków) |
| 5 | Odświeżanie z zegara | **Tak, ale tylko przy widocznym ekranie** + `Ctrl`+`R` + po własnej czynności; odstęp drugą pozycją ustawień |
| 6 | Niezgodne wersje | Pokazuje obie, ostrzega, **nie odmawia** niczego |
| 7 | Czy `apply` wchodzi tutaj | **Tak, wraz z usunięciem** — reguła 11n, odbiorcą jest użytkownik |
| 8 | Gdzie sprawdzenie ręczne | **minikube**, klaster uruchamia użytkownik |

Trzy pytania **ponad plan**, wynikłe z odpowiedzi na nr 2 (D91 nr 3, 4 i 10):
układ ekranu to **drzewo grup i rodzajów obok listy**; kolumny biorą się
**z JSON-a** (ogólne z `metadata` plus pakiety dla znanych rodzajów), a nie
z tabeli drukowanej przez serwer; sekret jest **maskowany, odsłaniany klawiszem
i zmienialny** wraz z dodawaniem i kasowaniem kluczy.

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

### 2026-08-16 — rozstrzygnięcia, moduł, rozbudowa rdzenia

**Rozstrzygnięcia startowe zapadły przed pierwszą linią kodu** ([00-decyzje.md](../00-decyzje.md),
D91): siedem pytań z planu (ósme — litera — było już rozstrzygnięte w D90 nr 2)
plus **trzy pytania, których plan nie przewidział**, a które wynikły z odpowiedzi
na pytanie o rodzaje zasobów.

**Krok wyszedł większy, niż go zaplanowano, i przesądziła o tym jedna
odpowiedź.** Plan stawiał wybór „same pody czy pody + wdrożenia + usługi”;
użytkownik odpowiedział „wszystkie elementy k8s”. Rodzaj zasobu przestał przez to
być gałęzią `match`a, a stał się **daną przychodzącą z klastra** — i to pociągnęło
resztę: katalog z `api-resources`, drzewo grup zamiast płaskiej listy, pakiety
kolumn dla rodzajów znanych i kolumny ogólne dla wszystkich pozostałych.

**Rdzeń kosztował więcej niż jedną linię — i plan mówił, że nie kosztuje nic.**
Zdanie „mechanizmu rdzenia krok nie rusza żadnego” upadło na `kubectl logs -f`:
`BackgroundState` przy pracy trwającej niósł **pusty wypis**, więc polecenie
niekończące się nigdy nie miało jak powiedzieć ani słowa. Postawiono trzy drogi
z ceną wypisaną przed wyborem (rosnący plik roboczy wzorem kroku 50, powtarzane
`logs --tail`, rozbudowa portu); użytkownik wybrał rozbudowę. Okazała się
**dwuczęściowa**: samo oddawanie wypisu nie wystarczyło, bo bufor odrzucał nadmiar
po przekroczeniu granicy i log zamilkłby po kilkunastu sekundach. Stąd
`OutputShape` — wynik odrzuca nadmiar (zachowanie sprzed kroku co do bajtu),
strumień zapomina najstarsze, a `BackgroundState::$droppedBytes` mówi, ile
przepadło. Trzy testy w `BackgroundProcessServiceTest` pilnują obu zachowań
i tego, że **starszy odbiorca nie ucierpiał**.

**Co powstało:** `src/Module/Kubernetes/` — cztery obiekty wartości i wyjątek
w `Domain`, port `KubectlPort` z jednym wywołaniem opisanym argumentami,
osiem klas stanu w `Application`, pięć klas rozczytujących w `Infrastructure`
(w tym **jedyny w module parser tekstu**), ekran w czterech postaciach, cztery
komendy i dwa katalogi napisów. Rdzeń: jedna linia w `Bootstrapie` plus
rozbudowa portu.

**Trzy rzeczy warte zapamiętania, każda kosztowała osobne rozpoznanie:**

1. **`kubectl api-resources` nie umie JSON-a** (klient 1.25: `-o` przyjmuje
   `wide` i `name`), więc katalog rodzajów rozbiera się tekstem — i **nie
   podziałem po spacjach**, bo pusta kolumna `SHORTNAMES` przesuwa wszystkie
   pozostałe. Test tej pułapki jest pierwszy w `ApiResourcesParserTest`.
2. **Nazwa klasy z planu była w PHP niewykonalna**: `Namespace` to słowo
   zastrzeżone, więc obiektem wartości jest `NamespaceName`.
3. **Stan poda liczy się z powodu czekania kontenera, nie z fazy** — pod
   w `CrashLoopBackOff` ma fazę `Running`, więc wypisanie fazy dałoby listę
   samych „działających” podów, z których żaden nie działa.

**Bramka jakości:** `make qa` zielone — PHPStan `max`, PHP-CS-Fixer,
**2077 testów** (przed krokiem 2011; doszło 66, w tym sześć przebiegu
funkcjonalnego `ClusterFlowTest`). Żaden test nie wywołuje `kubectl` ani nie
rozmawia z klastrem — odpowiedzi udaje `tests/Support/StubKubectl.php`.

**Trzy testy sprzed kroku zmieniły zdanie i to było zamierzone**:
`ModuleRegistryTest` (szósta litera skrótu), `InputHandlerTest` (szósta zakładka
pomocy) oraz `DockerShortcutsTest`, który do tego kroku pilnował, żeby litery `k`
nikt nie zajął — zamówienie z D90 nr 2 zostało zrealizowane, więc test sprawdza
teraz, że litera **należy do modułu klastra**.

### 2026-08-16 — sprawdzenie na żywym klastrze i pomiar

**Sprawdzenie poszło na minikube** (rozstrzygnięcie nr 8), a klaster okazał się
świeżo utworzony: `kubectl` v1.25.2 wobec serwera v1.25.0, czyli **bez rozjazdu
wersji** — ostrzeżenie z rozstrzygnięcia nr 8 nie miało się na czym pokazać
i pozostaje sprawdzone wyłącznie kodem.

Przeszło wszystko, o co pyta miara powodzenia:

| Sprawdzenie | Wynik |
|---|---|
| Konteksty i wersje | `ca-dev` i `minikube`, bieżący `minikube`, obie wersje odczytane |
| Katalog rodzajów | **49 rodzajów w 18 grupach**, `pods` ze skrótem `po`, CRD-y obecne — parser tekstu zdał na prawdziwym wypisie `api-resources` |
| Lista podów | 7 wierszy `kube-system`; `READY`, `STATUS`, restarty i węzeł zgodne z `kubectl get` |
| Opis zasobu | odczytany; kontener `etcd`, faza `Running` |
| **Logi na żywo** | **pierwszy wiersz po 0,07 s**, przy pracy, która nadal trwa; 20 wierszy, 0 utraconych bajtów; zamknięcie zwalnia pracę |
| Sekret | sześć kluczy z rozmiarami, **żadna wartość nieodsłonięta domyślnie** |
| `apply` z pliku | zastosowany, zasób widoczny w klastrze |
| Zmiana wartości sekretu | nowa wartość w klastrze |
| Dodanie i skasowanie klucza | klucz dodany, skasowany, a **sąsiedni nietknięty** — scalająca zmiana zachowuje się zgodnie z założeniem |
| Usunięcie zasobu | zasób zniknął |

Sprawdzenie czynności zmieniających szło we **własnej przestrzeni nazw**
(`lm-krok52`), skasowanej po wszystkim; poza nią nic w klastrze nie zostało
dotknięte.

**Jedna rzecz wyszła przy okazji i warto ją znać**: klucz sekretu musi pasować do
`[-._a-zA-Z0-9]+`, więc `hasło` nie jest poprawnym kluczem. Moduł nie sprawdza
tego u siebie i **nie ma tego robić** — serwer odmawia zdaniem, które trafia do
paska stanu przez `problem.rejected`, tak samo jak przy błędnej ścieżce w `apply`.
Zasada jest ta sama, co przy ścieżce manifestu: `kubectl` powie o tym lepiej niż my.

**Pomiar osi `--loop` „przed i po”** wykonany na maszynie ze **zatrzymanym**
minikube (obciążenie 0,10 na rdzeń wobec 0,09 we wzorcu):

| Scenariusz | Wzorzec (po kroku 51) | Teraz | Zmiana |
|---|---|---|---|
| klatka z pracą w tle | 0,1 ms | 0,1 ms | **−1,0%** |
| klatka z kompletem prac w tle | 0,1 ms | 0,1 ms | **+4,3%** |

„Bez regresji powyżej progu”; wzorzec zapisany jako
[2026-08-16-po-kroku-52-loop.json](../../pomiary/2026-08-16-po-kroku-52-loop.json).
Rozbudowa portu nie kosztuje przy tym mierzalnie **także dlatego, że nic w niej
nie liczy się przy pustym zestawie prac**: kształt wypisu rozstrzyga się przy
dokładaniu porcji, a `droppedBytes` rośnie wyłącznie przy przesuwaniu bufora.

**Makefile dostał trzy cele** (`minikube-start`, `minikube-stop`,
`minikube-status`) — na prośbę użytkownika, żeby klaster do sprawdzeń podnosiło
i kładło jedno wejście, a nie polecenie pamiętane z głowy (reguła 18). Cel
`minikube-status` ma `|| true`, bo narzędzie kończy się kodem 7 przy zatrzymanym
węźle, a dla pytania o stan „zatrzymany” jest **odpowiedzią, nie awarią**.

**Czego krok nie dowiózł:** klatki obejrzanej pod XTermem. Moduł rysuje się
komponentami, które mają swoje scenariusze pomiarowe i złote klatki, a przebieg
funkcjonalny sprawdza treść — ale **wyglądu podziału z drzewem nikt jeszcze nie
oglądał okiem**. To samo zastrzeżenie zostało po kroku 46 i wtedy okazało się
warte odnotowania.
