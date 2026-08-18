# Krok 59 — Klastry Kubernetesa: połączenie przestaje być jednym kontekstem

> **Skąd ten krok.** Powstał 2026-08-16 jako drugi krok **Fazy XX**
> ([00-decyzje.md](../00-decyzje.md), D96). Robi dla modułu `k8s` to, co krok 58
> robi dla Dockera, i z tego samego powodu: miejsce, z którym moduł rozmawia,
> jest dziś **jedną wartością w ustawieniach**, a ma być spisem, który
> użytkownik prowadzi.

## Status

**Ukończony** (2026-08-18). Rozstrzygnięcia startowe:
[00-decyzje.md](../00-decyzje.md), D96 (nr 1, 3 i 4) oraz **D103** — zastrzeżenie
startowe (przegląd 15e) rozstrzygnięte na starcie: **wspólna książka w rdzeniu,
dzielona na sekcje**, jeden plik `~/.light-manager/state.json`, wpis
nieprzezroczysty dla rdzenia, wspólny zapis także dla `settings` i `history`.
Rekomendacja planu (wydzielenie samego zapisu) odrzucona przez użytkownika.

## Cel

Moduł prowadzi **spis klastrów**: każdy wpis ma nazwę własną, plik `kubeconfig`,
kontekst i przestrzeń nazw. Przełączenie klastra jest wyborem z listy, a nie
zmianą pozycji ustawień, i obejmuje **także klastry z plików, których dziś moduł
w ogóle nie widzi**.

Miarą powodzenia jest zdanie: **dwa klastry z dwóch różnych plików `kubeconfig`
stoją na jednej liście, a przełączenie między nimi nie miesza ani zasobów, ani
spisu rodzajów, ani otwartego opisu.**

Miarą drugą, wymierną: **dwa wpisy o kontekstach tej samej nazwy w dwóch plikach
są dwoma różnymi miejscami** — i widać to w drzewie, w liście i w pamięci
podręcznej. To jest ten warunek, który dzisiejszy kod łamie po cichu.

## Trudność strukturalna — trzy rzeczy, wszystkie do rozbrojenia naraz

**Pierwsza: miejsce ma dziś jedną współrzędną, a musi mieć dwie.**
`KubectlCall` niesie `?ContextName`, `KubectlService` skleja z tego `--context`
i `--request-timeout`. Flaga `--kubeconfig` **nie pada nigdzie** (sprawdzone),
choć klient 1.25 ją ma. Druga współrzędna musi dojść do **każdego** wywołania —
także do `config view`, dziś jedynego bez kontekstu, bo to właśnie ono ma
wypisać zawartość wskazanego pliku.

**Druga: wszystko, co moduł pamięta, jest zawiązane na nazwie kontekstu.**
`ClusterScreen::forgetEverything()` przestawia `TreeState` na
`self::ID . ':' . <kontekst>`, `ClusterSession` liczy pokolenia, `ResourceCache`
i `ApiCatalog` unieważniają się razem z nimi. Mechanizm jest i działa —
zmienia się **klucz**: tożsamością miejsca staje się **nazwa wpisu książki**,
bo `default` w dwóch plikach to dwa różne klastry, a `minikube` odtworzony po
skasowaniu to trzeci.

**Trzecia: stan „nie ma klastra" zyskuje dwa nowe warianty.** Dziś są cztery
(`Unknown`, `Reading`, `NoContext`, `Unreachable`); dochodzą **plik nie
istnieje** i **w pliku nie ma takiego kontekstu**. Oba są odwracalne czynnością
użytkownika i oba muszą powiedzieć **którą** — a nie „klaster nie odpowiada",
bo pod tym zdaniem nie widać, że to literówka w ścieżce.

## Zastrzeżenie startowe — trzecie powtórzenie wzorca książki (reguła 15e)

Po kroku 58 ten sam wzorzec stoi w projekcie **trzy razy**: `HostBook`
(krok 48), `EnvironmentBook` (krok 58) i `ClusterBook` (tutaj). Reguła 15e mówi,
co wtedy robić, i mówi to precyzyjnie: **trzeci raz uruchamia przegląd „czy to
nadal powtórzenie, czy już wspólne miejsce" — obowiązek postawienia pytania, a
nie automatyczną przeprowadzkę do rdzenia.**

Przegląd należy do zakresu tego kroku, a jego wynik — do dziennika. Materiał do
niego jest już policzony:

- **Za powtórzeniem:** pola wpisów są rozłączne (host SSH ma klucz i odcisk,
  środowisko Dockera — certyfikaty TLS, klaster — plik i kontekst), a wspólne
  zostaje wyłącznie „nazwa własna jest tożsamością" i „kolejność jest kolejnością
  dopisywania". Wyniesienie tego do rdzenia znaczyłoby ogólny magazyn wpisów,
  czyli mechanizm bez własnej treści.
- **Za wspólnym miejscem:** trzy pliki stanu modułów mają dziś **trzy niezależne
  implementacje zapisu** (`0600`, plik tymczasowy, zachowanie nieznanych kluczy)
  — i to jest mechanizm, a nie pojęcie dziedziny, czyli dokładnie ta połowa
  granicy 15e, której powtarzać **nie wolno**.

Rekomendacja planu: **pojęcia zostają w modułach, a przegląd kończy się
wydzieleniem samego zapisu** (jeden rdzeniowy sposób pisania pliku stanu modułu,
trzech użytkowników od pierwszego dnia — czyli warunek reguły 13 spełniony).
Rozstrzyga użytkownik na starcie kroku.

## Stan zastany (sprawdzony w kodzie i na maszynie 2026-08-16)

| Element | Stan |
|---|---|
| `KubectlCall` | Niesie `?ContextName` i `OutputShape`; `contexts()` — jedyne wywołanie bez kontekstu (`config view -o json`). |
| `KubectlService` | Skleja `--context` i `--request-timeout=<n>s`, cytuje `escapeshellarg`; **`--kubeconfig` nie pada nigdzie**. |
| `ClusterState` | Czyta konteksty z domyślnego `kubeconfig`, wybiera zapamiętany albo bieżący; przy braku obu — `NoContext`. Pliku **nie zmienia** (świadomie, krok 52). |
| `ClusterSession` | Kontekst, przestrzeń, limit czasu i licznik pokoleń — jedno miejsce naraz („jeden klaster naraz", wzorem jednej sesji z kroku 48). |
| `KubernetesSettings` | Sześć pozycji; `context` i `namespace` to **napisy zapamiętujące miejsce** — pozycje, których użytkownik nie przestawia strzałkami. |
| `ClusterScreen` | `c` otwiera `ChoiceOverlay` z kontekstami; `forgetEverything()` przestawia `TreeState` na klucz z **nazwy kontekstu**. |
| Kwerendy modułu | Sześć; `k8s.cluster` świadomie **nie niesie adresu serwera** (reguła 11w). |
| Maszyna | kubectl 1.25.2; `~/.kube/config` z **jednym** kontekstem `ca-dev` i **bez** `current-context`; minikube zainstalowany, ale zatrzymany. |
| `kubectl options` | `--kubeconfig` i `--context` są flagami globalnymi klienta 1.25 (sprawdzone). |

## Zależności

- **Krok 52** — cały moduł: sesja, drzewo rodzajów, pamięć podręczna, logi.
- **Krok 58** — wzorzec książki dowieziony po raz drugi i **przegląd 15e**,
  który tu się rozstrzyga; kolejność ma znaczenie, numer sam z siebie nie.
- **Krok 48** — pierwsza książka wpisów i plik stanu modułu.
- **Kroki 53 i 54** — kwerendy modułu, które dostają nazwę wpisu; `k8s.cluster`
  i `k8s.contexts` zmieniają przez to treść.
- **Kroki 22, 24, 27, 28, 31** — sekcje, panele, tabela, pytanie i drzewo dla
  ekranu spisu.
- **Kroki 14 i 15** — migracja dwóch pozycji ustawień i napisy.

## Model i wysiłek

**Opus / high.** Krok jest **lżejszy od 58** i ma być: nowej drogi transportu nie
wnosi (wszystko idzie tym samym `kubectl` w procesie potomnym), komponentu nie
dokłada, rdzenia nie rusza. Jego ciężar leży w **przeliczeniu tożsamości
miejsca** przez cztery klasy stanu naraz — czyli w pracy, która jest ryzykowna,
ale nie rozległa.

## Zakres

### 1. Klaster jako wpis książki

`Module/Kubernetes/Domain/ValueObject/ClusterProfile` — nazwa własna
(tożsamość), ścieżka `kubeconfig`, nazwa kontekstu, przestrzeń nazw,
opcjonalny limit czasu własny. Samowalidacja wyjątkiem modułu; ścieżka
sprawdzana **istnieniem dopiero przy użyciu**, bo plik na dysku sieciowym bywa
chwilowo nieobecny, a wpis nie ma przez to przestać istnieć.

`ClusterBook` + `ClusterBookPort` → `KubernetesStateService`
(`~/.light-manager/k8s.json`, tryb `0600`, nieznane klucze przeżywają zapis).

### 2. Dwa źródła jednej listy

**Czytane:** konteksty z domyślnego `~/.kube/config` **oraz** ze ścieżek
w zmiennej `KUBECONFIG` (lista rozdzielona dwukropkami — czytamy ją, bo to
standard narzędzia i użytkownik już ją ma). **Własne:** wpisy książki,
wskazujące dowolny plik.

Reguły te same, co w kroku 58: pochodzenie widoczne, wpis własny wygrywa przy
zbieżnej nazwie, a wpisu czytanego nie da się z aplikacji skasować — bo moduł
**do `kubeconfig` nie pisze** i to zdanie z kroku 52 zostaje w mocy.

### 3. Druga współrzędna w każdym wywołaniu

`KubectlCall` niesie odtąd **miejsce**, a nie sam kontekst: ścieżkę pliku plus
nazwę kontekstu. `KubectlService` dokłada `--kubeconfig <ścieżka>` obok
`--context`. Flagi idą **argumentem**, a nie zmienną środowiskową, z tego samego
powodu, dla którego argumentem idzie kontekst: cytowanie jest wtedy jedno
i stoi w jednym miejscu.

`contexts()` przestaje być wywołaniem „bez miejsca" i dostaje ścieżkę — po to,
żeby spis kontekstów dało się pobrać **z każdego** pliku, nie tylko
z domyślnego.

### 4. Tożsamość miejsca przeliczona przez cztery klasy stanu

Klucz kontekstu w `TreeState`, `SectionState`, `ScrollWindow` i `ResourceCache`
bierze się odtąd z **nazwy wpisu**, nie z nazwy kontekstu. `ClusterSession`
rośnie o ścieżkę pliku, a `generation()` zmienia się przy każdej zmianie
którejkolwiek współrzędnej.

To jest **punkt, w którym krok może cicho zepsuć działającą rzecz**, więc ma
własny przebieg funkcjonalny: dwa wpisy o kontekstach tej samej nazwy, ten sam
rodzaj zasobu, przełączenie tam i z powrotem — listy nie mają prawa się zmieszać.

### 5. Ekran spisu klastrów

Klawisz **`c`** przestaje otwierać okno wyboru kontekstu i otwiera **spis
klastrów** (druga postać ekranu modułu, wzorem `HostsScreen`): dodanie, zmiana,
usunięcie, wybór bieżącego, a przy wpisie czytanym — sam wybór. Okno wyboru
kontekstu zostaje jako droga **wewnątrz** wpisu (plik ma zwykle więcej niż jeden
kontekst).

### 6. Dwa nowe stany bez klastra

`ClusterStage` rośnie o `MissingFile` i `UnknownContext`. Oba mają zdanie
mówiące **co poprawić**, oba przyjmują te same klawisze co `NoContext`
(wybór i ponowienie) i oba rysują się tak, jak reszta stanów — czyli
w treści ekranu, a nie w górnym pasie (poprawka z 2026-08-16).

### 7. Migracja ustawień

Pozycje `context` i `namespace` **znikają z zakładki** i wchodzą do książki jako
wpis o nazwie z zapamiętanego kontekstu, wskazujący domyślny `kubeconfig`.
Migracja pada raz, przy pierwszym wczytaniu książki; wartości nie giną, a stara
pozycja zostaje w `settings.json` nietknięta (nikt jej już nie czyta, a jej
skasowanie nie ma odbiorcy).

### 8. Kwerendy

Nowa **`k8s.clusters`** (nazwa wpisu, plik, kontekst, przestrzeń, pochodzenie,
czy bieżący, etap). `k8s.cluster` i `k8s.contexts` dostają **nazwę wpisu**;
adres serwera nadal **nie wychodzi** (reguła 11w), a **ścieżka pliku wychodzi** —
to nie jest materiał uwierzytelnienia, tylko lokalizacja pliku, którą użytkownik
sam wpisał.

### 9. Napisy, pomiar, przebiegi, dokumentacja

- **Napisy:** dwa nowe stany, pochodzenie wpisu, spis klawiszy ekranu, powody
  odrzucenia wpisu, opisy kwerendy w obu językach.
- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 58; scenariusz
  spisu klastrów dokłada się do `ScenarioFactory`.
- **Przebiegi:** `tests/Functional/ClusterBookFlowTest.php` — dwa pliki, dwa
  konteksty o tej samej nazwie, przełączenie w obie strony; wpis wskazujący
  nieistniejący plik; wpis z kontekstem, którego w pliku nie ma; migracja
  z ustawień. **Żaden test nie uruchamia `kubectl`** (kryterium z kroku 52).
- **Dokumentacja:** `docs/architecture.md` — miejsce jako para (plik, kontekst);
  `SKILL.md` — rozszerzenie reguły 11v o zdanie, że tożsamością miejsca jest
  **nazwa wpisu**, a nie nazwa kontekstu, wraz z powodem; wynik przeglądu 15e.

## Poza zakresem

- **Pisanie do `kubeconfig`** (`use-context`, dodawanie klastrów i użytkowników)
  — zdanie z kroku 52 zostaje: wybór zrobiony w menadżerze plików nie zmienia
  tego, co użytkownik zastanie w swoim terminalu.
- **Tunel SSH do serwera API klastra** — `kubeconfig` wskazuje już adres
  osiągalny, a `port-forward` był wykluczony z kroku 52 i zostaje.
- **Uwierzytelnienie przez wtyczki `exec`** (`aws eks get-token`, `gke-gcloud-auth`)
  — klient robi to sam i moduł nie ma w tym udziału; jeśli wtyczki brak, powód
  przychodzi ze strumienia błędów jak każdy inny.
- **Więcej niż jeden klaster naraz** — „jeden klaster naraz" zostaje
  (krok 52, wzorem jednej sesji z kroku 48); książka zmienia to, **z ilu** można
  wybierać, a nie ile stoi otwartych.
- **Uruchamianie i zatrzymywanie minikube** — to jest zarządzanie maszyną, nie
  klastrem (wykluczone z kroku 52).
- **Helm, RBAC, obserwowanie zmian przez API** — bez zmian wobec kroku 52.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Kubernetes/Domain/ValueObject/ClusterProfile.php` | Moduł/Domain | Nowe — wpis książki wraz z samowalidacją. |
| `Module/Kubernetes/Application/ClusterBook.php`, `ClusterBookView.php` | Moduł/Application | Nowe — spis i jego migawka. |
| `Module/Kubernetes/Application/Port/ClusterBookPort.php` | Moduł/Application | Nowe — zapis i odczyt `k8s.json`. |
| `Module/Kubernetes/Infrastructure/KubernetesStateService.php` | Moduł/Infrastructure | Nowe — plik stanu modułu. |
| `Module/Kubernetes/Application/KubectlCall.php` | Moduł/Application | Miejsce zamiast samego kontekstu; `contexts()` z plikiem. |
| `Module/Kubernetes/Infrastructure/KubectlService.php` | Moduł/Infrastructure | `--kubeconfig` obok `--context`. |
| `Module/Kubernetes/Application/ClusterSession.php`, `ClusterState.php`, `ClusterStage.php` | Moduł/Application | Druga współrzędna, dwa nowe stany, unieważnianie po nazwie wpisu. |
| `Module/Kubernetes/Presentation/ClusterBookScreen.php` | Moduł/Presentation | Nowe — spis klastrów pod `c`. |
| `Module/Kubernetes/Presentation/ClusterScreen.php` | Moduł/Presentation | Klucze stanu z nazwy wpisu; dwa nowe stany w treści. |
| `Module/Kubernetes/Presentation/Query/ClustersQuery.php` | Moduł/Presentation | Nowe — `k8s.clusters`. |
| `Module/Kubernetes/Application/KubernetesSettings.php` | Moduł/Application | Dwie pozycje wychodzą z zakładki; migracja do książki. |
| `Module/Kubernetes/lang/pl.php`, `en.php` | Napisy | Punkt 9. |
| `tests/Module/Kubernetes/…`, `tests/Functional/ClusterBookFlowTest.php` | Testy | Punkt 9. |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Punkt 9 wraz z wynikiem przeglądu 15e. |

## Kryteria ukończenia

- **Dwa klastry z dwóch plików `kubeconfig` stoją na jednej liście** i dają się
  przełączać.
- **Konteksty o tej samej nazwie w dwóch plikach nie mieszają danych** — osobny
  przebieg funkcjonalny.
- **`--kubeconfig` jedzie w każdym wywołaniu**, także w spisie kontekstów.
- **Wpis wskazujący nieistniejący plik i wpis z nieznanym kontekstem mają własne
  zdania** — odróżnialne od „klaster nie odpowiada".
- **Migracja z ustawień nie gubi zapamiętanego miejsca.**
- **Moduł nadal nie pisze do `kubeconfig`** — pilnuje tego przebieg.
- **Przegląd reguły 15e wykonany, a jego wynik zapisany** w dzienniku kroku
  i w `SKILL.md`.
- Napisy w obu językach, pomiar „przed i po" bez regresji, PHPStan `max`,
  PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

**2026-08-18 — wykonanie: przegląd 15e rozsadził zakres kroku, a reszta poszła
wedle planu.**

1. **Zastrzeżenie startowe rozstrzygnięte przeciwnie do rekomendacji planu**
   (D103 nr 1). Plan rekomendował „pojęcia zostają w modułach, wychodzi sam
   zapis"; użytkownik rozstrzygnął: **wspólna książka w rdzeniu, dzielona na
   sekcje**. Materiał, który to przesądził, jest liczbą, nie uznaniem: wzorzec
   książki stał po raz trzeci, ale **mechanizm zapisu — po raz piąty** (trzy
   usługi stanu modułów plus `SettingsService` i `CommandHistoryService`),
   skopiowany niemal co do znaku. Rdzeń urósł przez to o dwie rzeczy zamiast
   zapowiadanego zera (D96: „rdzeń nie rośnie w tej fazie o nic") —
   `Application\State\Book` i `StateDocumentPort` + `StateDocumentService`
   + `StateFile` — obie z odbiorcami od pierwszego dnia (reguła 13): trzy
   książki i cztery sekcje. **Kopii mechanizmu zapisu nie ma już ani jednej.**
2. **Trzy pliki stanu modułów zamieniły się w trzy sekcje jednego dokumentu**
   (`~/.light-manager/state.json`), a migracja mieszka **za portem, nie
   u właścicieli**: sekcja nieobecna czyta się ze starego `<sekcja>.json`, stary
   plik zostaje na dysku nietknięty, a sekcją staje się przy pierwszym zapisie
   któregokolwiek właściciela. Dzięki temu `AudioStateService`,
   `SshStateService` i `DockerStateService` straciły po ~80 linii mechanizmu,
   a `KubernetesStateService` jest **pierwszą usługą stanu w projekcie bez ani
   jednej linii zapisu** — to jest wymierny skutek przeglądu.
3. **Miejsce ma dwie współrzędne i przeszło przez cztery klasy stanu bez
   niespodzianki.** `ClusterPlace` (plik + kontekst) jedzie w każdym wywołaniu
   dwiema flagami, a klucz stanu bierze się z `ClusterSession::key()`, czyli
   z **nazwy wpisu**. Zapowiadany „punkt, w którym krok może cicho zepsuć
   działającą rzecz" okazał się tani, bo `generation()` istniało od kroku 52
   i wystarczyło rozszerzyć jego warunek — pamięć podręczna, katalog rodzajów,
   opis i drzewo unieważniły się same.
4. **Dwie pułapki taktu, obie znalezione pomiarem zachowania, nie przeglądem.**
   Pierwsza: **plik czytany w tej chwili musi wypaść z ponownego zamówienia** —
   bez tego takt dokładał go do kolejki co klatkę, a drugi odczyt nadpisywał
   wynik pierwszego pustą listą (widoczne jako „kontekst zniknął z pliku,
   którego przed chwilą był"). Druga: **pytanie o wersje musi wiązać się
   z pokoleniem sesji** — inaczej klaster, który wersji serwera nie podaje,
   czyli dokładnie ten nieosiągalny, jest pytany trzydzieści razy na sekundę.
   Obie kosztowały po jednym warunku i obie mają test.
5. **Łańcuch okien wyszedł trzyogniwowy, a nie czteroogniwowy — z powodu
   kontraktu rdzenia.** `PromptOverlay` na pustym polu świadomie nie robi nic
   (krok 41), a przestrzeń nazw **ma prawo zostać pusta** („ta z pliku"), więc
   czwarte okno zawieszałoby łańcuch na wpisie bez przestrzeni. Przestrzeń
   zmienia się odtąd klawiszem `n` i **zapisuje przy wpisie**; przy okazji
   wróciło zdanie z kroku 52, które przeprowadzka omal nie zgubiła:
   przestrzeń zapisana przy kontekście w `kubeconfig` jest **propozycją**, więc
   źródła są trzy w kolejności — wpis, plik, `default`.
6. **Klawisz `c` zmienił znaczenie, a stary dostał literę `k`.** `c` otwiera
   spis klastrów (piąta postać ekranu), `k` — wybór kontekstu **wewnątrz** pliku
   wpisu, bo plik ma zwykle więcej niż jeden. Litera `k` była wolna
   w przestrzeni ekranu; skrót modułu (`Ctrl`+`K`) to inna przestrzeń
   (reguła 11j), więc kolizji nie ma.
7. **Plan zderzył się z pominięciem pomiarowym — i tym razem wygrało
   pominięcie.** Punkt 9 zapowiadał scenariusz spisu klastrów; przy rozpisaniu
   okazało się, że krok 58 dowiózł **dokładnie ten kształt** (`environments`:
   `Table` z nagłówkiem, pięć kolumn, trzy role wierszy naraz), a różni je
   wyłącznie treść komórek, która do pomiaru nie wchodzi. Powód pominięcia
   zapisany w `docs/pomiary/README.md` — odwrotnie niż w kroku 58, gdzie
   rozstrzygnięcie użytkownika kazało scenariusz dołożyć.
8. **Pomiar bez regresji.** Oś `--loop` wobec wzorca po kroku 58: **+0,1% /
   +0,8%** (obciążenie 0,10 na rdzeń wobec 0,04 wzorca). Tor sixelowy wobec
   wzorca po kroku 58: dwadzieścia dwa scenariusze w przedziale −5,9%…+4,4%,
   bez regresji powyżej progu. Wzorzec `--loop` zapisany
   (`2026-08-18-po-kroku-59-loop.json`); **wzorca sixelowego narzędzie odmówiło
   zapisać dwukrotnie** — strażnik rozrzutu z reguły 17 uznał część pomiarów za
   niestabilne, tak samo jak w kroku 22. Porównanie i tak przeszło, więc krok
   rozlicza się z niego, a nie z zapisu.
9. **`make qa` zielone**: 2384 testy, 7884 asercje. Nowe: `BookTest`,
   `StateDocumentServiceTest`, `ClusterProfileTest`, `ClustersTest`
   i przebieg `ClusterBookFlowTest`. Trzy testy usług stanu przepisano na
   sekcje wraz z próbą migracji ze starego pliku; `ClusterStateTest`
   i `ClusterFlowTest` — na dwuwspółrzędne miejsce. **Żaden test nie uruchamia
   `kubectl`** (kryterium z kroku 52), ale pliki `kubeconfig` są w testach
   **prawdziwe** (puste, w katalogu tymczasowym z podstawionym `HOME`), bo brak
   pliku rozstrzyga się `is_file()`, a nie odpowiedzią klienta.

**Czego krok nie dowiózł:**

- **Klatki pod XTermem nikt nie oglądał** — dług ciągnący się od kroku 46;
  spis klastrów rysuje się tym samym `Table` co spis środowisk, więc ryzyko
  jest niskie, ale zdanie „sprawdzone" byłoby nieprawdziwe.
- **Wzorzec sixelowy po kroku 59 nie istnieje** (punkt 8) — następny krok
  porównuje się z wzorcem po kroku 58, tak jak ten.
- **Własny limit czasu wpisu nie ma drogi z interfejsu**: pole jest w wpisie
  i przeżywa zapis, ale ustawić je da się wyłącznie ręczną edycją
  `state.json`. Łańcuch okien świadomie o nie nie pyta (punkt 5).
