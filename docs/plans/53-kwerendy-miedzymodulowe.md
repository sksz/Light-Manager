# Krok 53 — Kwerendy międzymodułowe: obraz zbudowany Dockerem ląduje w klastrze

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 51 i 52, jako ostatnia
> trzecia Fazy XVIII ([00-decyzje.md](00-decyzje.md), D85). To on jest powodem,
> dla którego faza ma trzy kroki, a nie dwa: **moduły mają się wzajemnie
> używać**, a reguła 15 mówi, że moduł nigdy nie sięga do innego modułu.

> **Uzupełniony 2026-08-15, tego samego dnia i przed pierwszą linią kodu**
> ([00-decyzje.md](00-decyzje.md), D86). W pierwotnym brzmieniu krok wnosił
> mechanizm rdzenia o zasięgu na wszystkie moduły, ale kwerendy dawał **wyłącznie
> dwóm modułom, które sam dopiero powołuje** — trzy moduły, które aplikacja ma
> dzisiaj (`browser`, `file-info`, `audio`), zostawały poza nim, a punkt *Poza
> zakresem* mówił wprost „odbiorcy dziś nie ma". Rozstrzygnięciem użytkownika
> kwerendy dostają **wszystkie trzy**, a odbiorcą tych bez konsumenta w kodzie
> zostaje **użytkownik**: rozstrzygnięcie nr 7 idzie na „kwerendy widoczne",
> czyli krok dowozi **okno kwerend**. Reguła 13 zostaje przez to spełniona bez
> wyjątku — a wyjątek był drugim postawionym wariantem i został odrzucony.

## Status

**Nie rozpoczęty.** Zablokowany przez kroki 51 i 52.

## Cel

Moduł ma umieć **poprosić o coś, czego sam nie umie**, nie wiedząc, kto to
zrobi — a pierwszą taką rzeczą jest obraz kontenera: zbudowany przez moduł
Dockera, wdrożony przez moduł Kubernetesa.

Miarą powodzenia jest zdanie: **`k8s.deploy-image` pokazuje listę obrazów, które
zna moduł Dockera, buduje wskazany, czeka na koniec budowy i podmienia obraz
w wybranym wdrożeniu — a przy wyłączonym module Dockera ta sama czynność mówi,
czego brakuje, zamiast się wywrócić.**

Miarą drugą, dołożoną uzupełnieniem: **każdy moduł aplikacji — także te trzy,
które istniały przed tym krokiem — oddaje kwerendą to, o czym wie tylko on,
a użytkownik widzi ten spis i umie go zapytać sam.** Mechanizm rdzenia, który
umie odpowiedzieć wyłącznie na pytania dwóch modułów napisanych razem z nim, nie
jest mechanizmem rdzenia, tylko wewnętrznym uzgodnieniem tej pary.

## Zastrzeżenie do rozstrzygnięcia na starcie — reguła 15 nie zostaje złamana, tylko dopowiedziana

Reguła 15 brzmi: **moduł nigdy nie sięga do innego modułu.** Ten krok jej **nie
odwołuje** i odwołać nie ma prawa — to ona trzyma cały podział, na którym stoi
dziewięć modułów i cztery fazy planu.

Dopowiedzenie brzmi: moduł sięga do **rdzenia**, a rdzeń trzyma rejestr, do
którego wpisał się ktoś inny. Dokładnie tak działa to od kroku 19 przy komendach
i od 46 przy zdarzeniach — nikt nie nazwał tego wtedy współpracą modułów, ale
nią było: `MenuOverlay` wywołuje komendę przeglądarki, nie znając przeglądarki.
Nowe jest **jedno**: kanał, którym wraca **dana**, a nie skutek dla interfejsu.

Granica, poza którą to przestaje być dopowiedzeniem, a staje się wyłomem, jest
częścią zakresu i ma trafić do `SKILL.md` wraz z powodem:

- moduł zna **nazwę** cudzej komendy i kwerendy (napis), nigdy jej typ;
- kwerenda oddaje **dane pierwotne** (napisy, liczby, wartości logiczne) — ta
  sama zasada, którą kieruje się `ModuleContext` (D40 P5);
- moduł pytający **musi umieć żyć bez odpowiedzi**, bo ten drugi bywa wyłączony,
  odrzucony albo nieobecny.

## Zastrzeżenie drugie — obraz zbudowany lokalnie nie istnieje w klastrze

To jest miejsce, w którym funkcja może wyjść atrapą, więc stoi tu wprost: obraz
zbudowany przez lokalnego demona **nie jest widoczny dla klastra**, chyba że
klaster używa tego samego demona. Bez rozstrzygnięcia tej rzeczy `k8s.deploy-image`
skończy się podem w stanie `ImagePullBackOff` i będzie to wyglądało jak usterka
aplikacji, a nie jak brakujący krok.

Trzy drogi, wszystkie do rozstrzygnięcia na starcie (nr 4):

| Droga | Cena |
|---|---|
| `minikube image load <tag>` | jedno polecenie, ale wiąże funkcję z jednym rodzajem klastra |
| wypchnięcie do rejestru (`docker push`) | działa wszędzie, ale wymaga rejestru, logowania i `imagePullPolicy` — a `push` jest **poza zakresem kroku 51** |
| klaster dzielący demona (`kind load`, docker-desktop) | nic nie kosztuje, gdy jest; nie ma go, gdy go nie ma |

## Zastrzeżenie trzecie — kwerenda nie jest drugą drogą do `ModuleContext`

To zastrzeżenie **weszło razem z uzupełnieniem** i jest jego najważniejszą
częścią, bo bez niego pierwsze kwerendy przeglądarki powtórzyłyby kanał, który
w rdzeniu stoi od kroku 21.

Sprawdzone w kodzie przy uzupełnianiu: `Application\Module\ModuleContext` niesie
już **ścieżkę bieżącego miejsca, nazwę zaznaczenia, jego rodzaj oraz liczbę,
wagę i liczbę katalogów w zaznaczeniu wielokrotnym** — wszystko jako dane
pierwotne, publikowane przez `BrowserState` i podawane każdemu modułowi za darmo
przez `LoopState`. Kroki 51 i 52 **już się na tym opierają**: ścieżka pliku
`compose` i ścieżka manifestu do `apply` biorą się właśnie stamtąd.

Kwerenda `browser.cwd` byłaby więc drugą drogą do danej, którą rdzeń rozdaje
bez pytania — i drogą gorszą, bo kontekst jest w klatce zawsze, a kwerenda
wymaga zapytania i obsłużenia braku odpowiedzi.

Granica, którą krok ma zapisać w `SKILL.md` obok zdania „komenda robi, kwerenda
mówi":

> **Kontekst mówi, gdzie użytkownik stoi. Kwerenda mówi, co u mnie jest.**

Kontekst niesie **jedno miejsce i jedno zaznaczenie**: ścieżkę panelu czynnego,
ale nie drugiego; liczbę zaznaczonych wpisów, ale nie ich nazwy; wpis pod
kursorem, ale nie zawartość katalogu. To, czego kontekst nie niesie, jest
zakresem kwerend przeglądarki. To, co niesie, **nie ma prawa się w nich
powtórzyć** — i pilnuje tego przegląd przy odbiorze kroku, nie test, bo powtórkę
widać w nazwie.

## Zależności

- **Kroki 51 i 52** całkowicie: obie strony współpracy powstają tam. Ten krok
  **nie dokłada ani jednej funkcji kontenerowej** — dokłada kanał między nimi.
- **Krok 19** twardo i **potrójnie** (trzecia strona doszła z uzupełnieniem):
  `CommandRegistry` jest **istniejącym** kanałem czynności (rozstrzygnięcie
  użytkownika, D85 nr 3), `CommandInterface` — wzorem, wedle którego powstaje
  kontrakt kwerendy, a `CommandOverlay` (`F12`) — oknem, którego drugim trybem
  ma zostać okno kwerend (rozstrzygnięcie nr 9). `find()` już umie oddać komendę
  po nazwie; nic w rejestrze komend zmieniać nie trzeba.
- **Krok 46** twardo i **z trzeciej strony niż zwykle**: zdarzenia są tu
  **spoiwem czasu**. Budowa obrazu trwa minuty, więc wołający nie może na nią
  czekać w klatce — dowiaduje się o końcu zdarzeniem `docker.build.finished`,
  a dopiero potem pyta kwerendą o wynik. Stąd reguła kroku: **komenda robi,
  zdarzenie ogłasza, kwerenda mówi co wyszło.**
- **Krok 20** — `ProvidesQueries` staje obok `ProvidesCommands`
  i `ProvidesSettingsTab` w `Application/Module`, bo nie wymienia ani jednego
  typu z `Presentation` (kryterium podziału z D38 P2).
- **Krok 21** wzorcowo i **granicznie**: `ModuleContext` jest **precedensem na
  dane pierwotne przechodzące między modułami** — i to on rozstrzyga zarówno, co
  kwerendzie wolno oddać, jak i czego oddawać jej nie wolno, bo już to rozdaje
  (zastrzeżenie trzecie).
- **Kroki 25 i 26** — kwerendy modułu opisu pliku oddają **stan pracy tłowej**
  (`ChecksumStage`, `DiskUsageStage`), a nie jej wynik po czekaniu. To jest
  jedyny w projekcie istniejący precedens na regułę nr 4 kwerendy i krok ma z
  niego skorzystać, a nie wymyślać drugi.
- **Krok 43** — `browser.marked` oddaje **nazwy** zaznaczonych wpisów, czyli
  dokładnie to, czego `ModuleContext` z tamtego kroku nie niesie (niesie liczbę,
  wagę i liczbę katalogów). Bez tej kwerendy zaznaczenie wielokrotne nie ma jak
  dojść do żadnego modułu poza przeglądarką i opisem pliku.
- **Krok 24** — kwerenda przeglądarki musi umieć powiedzieć o **obu panelach**,
  bo kontekst mówi wyłącznie o czynnym; panel podaje się argumentem.
- **Krok 45** — `audio.playlist` i `audio.now-playing` czytają `PlaylistPlayer`
  i `Playlist` stamtąd; kwerenda niczego w module dźwięku nie zmienia i nie
  dokłada.
- **Krok 32 i 47** — `MenuOverlay`, `ChoiceOverlay`, `OpensOverlay`: wybór obrazu
  i wdrożenia to okna, a czynność ma trafić do menu `F9` bez zmiany w rdzeniu.
- **Krok 23, 41 i 42** — okno pracy i postęp: czynność złożona z budowy
  i wdrożenia ma **dwa etapy**, więc mówi o sobie tak, jak kopiowanie.

## Model i wysiłek

**Opus / xhigh.**

Krok wnosi **mechanizm rdzenia o zasięgu na wszystkie przyszłe moduły** — a takie
w tym projekcie zawsze kosztowały najwięcej, bo pomyłka w kontrakcie zostaje na
lata (tak było ze słownikiem prymitywów, zdarzeń i kontraktem modułu). Do tego
dochodzi **choreografia**: czynność, która przechodzi przez dwa moduły, trzy
mechanizmy rdzenia i pracę trwającą minuty, a musi zachowywać się poprawnie, gdy
w połowie zniknie jedna ze stron.

**Uzupełnienie krok powiększyło, ale progu nie przesunęło** — i to jest wniosek
z rachunku, nie z ostrożności. Sześć kwerend na trzech istniejących modułach to
sześć klas czytających kod, który stoi i działa; okno kwerend to `OverlayInterface`
złożony z komponentów, które istnieją od kroku 19, i **nie dokładający ani jednego
prymitywu** — czyli trzy renderery zostają nietknięte. Warunek, dla którego kroki
44 i 47 poszły na `Fable / xhigh` (zmiana słownika wejścia albo kontraktu ekranu
i wszystkich trzech tłumaczy naraz), **nie zachodzi**. Zostaje **Opus / xhigh**,
a wraz z nim uwaga: jeśli rozstrzygnięcie nr 9 pójdzie na osobne okno z własnym
klawiszem, rachunek trzeba przeliczyć jeszcze raz — nowy klawisz rdzenia znaczy
trzy tory wejścia.

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| `src/Application/Query/` | **Nie istnieje** — kwerendy są mechanizmem naprawdę nowym, nie przemianowaniem czegoś |
| `CommandRegistry` | Ma `find(string $name): ?CommandInterface`, `all()`, `matching()`; przedrostka właściciela pilnuje przy dodawaniu. **Nic tu zmieniać nie trzeba** |
| `CommandOutcome` | Niesie przejście, `?Message` i `?screenId` — **danych nie niesie i nieść nie ma** |
| `EventRegistry` | Zdarzenia rdzenia i modułów, słownik zamknięty konstrukcyjnie (nazwy z enumów), publikacja nie rzuca i nie rodzi zdarzenia |
| `ModuleContext` | Precedens: dane pierwotne przechodzą między modułami, typy modułu — nie. **Niesie już** `path`, `selection`, `kind`, `markedCount`, `markedBytes`, `markedDirectories` — i to jest granica z zastrzeżenia trzeciego |
| `ModuleRegistry` | Wie, które moduły są przyjęte, wyłączone i odrzucone — czyli **wie, czy jest kogo pytać** |
| `CommandOverlay` | Okno komend spod `F12`: `TextInput`, `ListView`, `ScrollWindow`, `Panel`, uzupełnianie przez `Prefix::shared`, historia przy pustym polu. **Kandydat na okno kwerend jako drugi tryb**, nie drugie okno |
| Moduły aplikacji | Trzy: `browser` (9 komend), `file-info` (1), `audio` (4). **Żaden nie ma dziś kwerendy** i żaden nie oddaje niczego poza kontekstem sesji |
| `FileOperationsPort` | Umie `rename`, `createDirectory`, `delete` i usuwanie kawałkowe — **listy katalogu nie umie**. Wypis katalogu należy do przeglądarki (`DirectoryRepositoryInterface` w jej `Domain`), więc `browser.entries` jest jedyną uczciwą drogą do niego dla cudzego modułu |
| Katalogi napisów | Kwerenda widoczna dla użytkownika ma opis w `lang/{pl,en}.php` **swojego** modułu, pod przedrostkiem `module.<id>.` — jak komendy i zdarzenia |

## Zakres

### 1. Kwerendy jako mechanizm rdzenia

Nowy katalog `src/Application/Query/`:

- `QueryInterface` — nazwa w przestrzeni właściciela (`docker.images`,
  `k8s.deployments`), opis kluczem katalogu napisów, deklarowane argumenty
  (wzorem `CommandArgument`) i wykonanie oddające **dane pierwotne**;
- `QueryResult` — wiersze jako `list<array<string, string|int|bool>>` plus
  ewentualny powód niepowodzenia; kwerenda **nie rzuca** (zasada portu, reguła 8);
- `QueryRegistry` — `add(owner, queries)`, `find(name)`, `all()`, `matching()`,
  odsiew nazw spoza przestrzeni właściciela — konstrukcyjnie to samo, co
  `CommandRegistry`.

Zdolność `Application\Module\ProvidesQueries`.

Cztery reguły, wszystkie wykonane w rejestrze albo w kontrakcie:

1. **Kwerenda czyta i nie zmienia.** Co zmienia — jest komendą. Bez tego podziału
   pierwszy moduł z kwerendą `docker.prune` uczyniłby mechanizm drugą drogą do
   czynności.
2. **Kwerenda nie zna wołającego** i wygląda tak samo przy zerze pytających.
3. **Kwerenda nie woła kwerendy** — ta sama reguła, którą zdarzenia mają jako
   „zdarzenie nie rodzi zdarzenia”, i z tego samego powodu: łańcuch zapętliłby
   pętlę.
4. **Kwerenda odpowiada w klatce albo nie odpowiada wcale.** Praca dłuższa od
   klatki idzie komendą i pracą kawałkową; kwerenda oddaje **stan tej pracy**,
   a nie czeka na jej koniec.

### 2. Brak odpowiedzi jest zwykłym stanem

`find()` oddaje `null`, gdy moduł jest wyłączony, odrzucony albo nieobecny.
Wołający **musi to obsłużyć zdaniem dla użytkownika**, a nie wyjątkiem — i to
jest reguła, bez której rejestr stałby się cichą zależnością między modułami.

Widoczny skutek: czynność `k8s.deploy-image` przy wyłączonym module Dockera
**pokazuje się w menu i mówi, czego brakuje**, zamiast znikać bez śladu
(rozstrzygnięcie nr 3).

### 3. Czynności przez rejestr komend

Wołanie cudzej czynności idzie **istniejącym** `CommandRegistry::find()` +
`execute()` — bez nowego mechanizmu (D85 nr 3). Wynikiem jest `CommandOutcome`,
czyli zdanie dla użytkownika; **danych stamtąd nie wyciągamy i nie dopisujemy
mu pola** — od danych są kwerendy. To rozgraniczenie jest całą treścią
rozstrzygnięcia użytkownika i ma zostać zapisane w `SKILL.md` jednym zdaniem:
**komenda robi, kwerenda mówi.**

### 4. Kwerendy trzech istniejących modułów

Ta sekcja **weszła uzupełnieniem** (D86). Zasada doboru jest jedna i wynika
z zastrzeżenia trzeciego: kwerendą zostaje **to, o czym wie tylko ten moduł,
a czego nie niesie `ModuleContext`**.

**Przeglądarka (`browser`)** — dwie kwerendy, obie z odbiorcą w kodzie tego kroku:

| Kwerenda | Oddaje | Odbiorca |
|---|---|---|
| `browser.entries` | Wpisy katalogu: `name`, `kind` (`file`/`dir`/`link`), `bytes`, `hidden`. Argumenty: ścieżka (domyślnie z kontekstu) i panel | Wybór manifestu i katalogu kontekstu budowy w `k8s.deploy-image` — **bez czytania systemu plików przez moduł k8s**, którego rdzeń i tak by mu nie dał (`FileOperationsPort` listy nie ma) |
| `browser.marked` | Nazwy i ścieżki zaznaczonych wpisów | `k8s.apply` na wielu manifestach naraz: zaznaczenie z kroku 43 po raz pierwszy dochodzi do modułu spoza przeglądarki |

**Opis pliku (`file-info`)** — dwie kwerendy, obie oddające **stan pracy tłowej**,
czyli wykonanie reguły nr 4 na istniejącym precedensie:

| Kwerenda | Oddaje | Odbiorca |
|---|---|---|
| `file-info.usage` | Zajętość ścieżki policzoną przez `du` wraz z etapem (`DiskUsageStage`: `Idle`, `Running`, `Done`, `Failed`) — **nigdy nie czeka na koniec** | Okno kwerend; drugiego odbiorcę, tym razem w kodzie, stawia rozstrzygnięcie nr 10 — waga katalogu kontekstu budowy przed wysłaniem go do demona |
| `file-info.digest` | `sha256` ścieżki wraz z etapem (`ChecksumStage`, te same cztery stany), jeśli policzony; inaczej sam etap | Okno kwerend |

**Dźwięk (`audio`)** — dwie kwerendy, obie czytające `PlaylistPlayer`:

| Kwerenda | Oddaje | Odbiorca |
|---|---|---|
| `audio.now-playing` | Tytuł, numer pozycji, tryb odtwarzania, czy gra, czy silnik jest dostępny | Okno kwerend |
| `audio.playlist` | Pozycje playlisty: `index`, `title`, `path`, `playable` | Okno kwerend |

Trzy rzeczy obowiązują wszystkie sześć naraz:

1. **Żadna nie powtarza `ModuleContext`.** Ścieżka bieżąca, nazwa zaznaczenia
   i liczba zaznaczonych do kwerend nie wchodzą — są w kontekście.
2. **Żadna nie zmienia niczego.** `audio.playlist` czyta playlistę i jej nie
   przestawia; przestawianie ma komendę od kroku 45.
3. **Żadna nie wymusza zmiany w rdzeniu.** Moduł dokłada `ProvidesQueries` tak,
   jak dokłada `ProvidesCommands` — `Bootstrap` nie rośnie o pozycję na moduł.

### 5. Okno kwerend: użytkownik jest odbiorcą

Rozstrzygnięcie nr 7 zostało **podjęte z góry** (D86): kwerendy są widoczne dla
użytkownika. To ono, a nie wyjątek od reguły 13, jest powodem, dla którego cztery
z sześciu kwerend powyżej wolno napisać bez konsumenta w kodzie — **konsument
jest, tylko siedzi przed terminalem**.

Zakres okna:

- **spis wszystkich kwerend** z opisem z katalogu napisów i właścicielem, tak jak
  okno komend pokazuje wszystkie komendy przy pustym polu;
- **wykonanie kwerendy wpisanej z argumentami** — wiersz rozbiera ten sam
  `CommandLineParser`, bo składnia `nazwa arg arg` jest ta sama i drugiego
  parsera projekt mieć nie będzie;
- **pokazanie wyniku jako wierszy** — `QueryResult` niesie listę rekordów, więc
  wynik to tabela, a nie zdanie; miejsce jej pokazania rozstrzyga nr 9;
- **pokazanie braku wykonawcy zdaniem** — ta sama ścieżka, którą idzie czynność
  `k8s.deploy-image` przy wyłączonym module Dockera (sekcja 2).

Czego okno **nie** robi: nie zapamiętuje historii kwerend (historia jest
własnością komend, bo tam zapisuje się czynność, a nie pytanie), nie odświeża
wyniku samo z siebie i nie przewija się poza to, co daje `ScrollWindow`.

### 6. Pierwszy odbiorca w kodzie: `k8s.deploy-image`

Czynność mieszka w module Kubernetesa — bo to on kończy pracę — i przechodzi
przez pięć etapów, z których **żaden nie zna Dockera z typu**:

1. kwerenda `docker.images` → lista obrazów do wyboru (`ChoiceOverlay`);
   pozycja „zbuduj nowy” prowadzi do etapu 2, wybór istniejącego — do 4;
2. komenda `docker.build` z katalogiem i znacznikiem jako argumentami;
3. czekanie na zdarzenie `docker.build.finished` albo `.failed`; okno pracy
   pokazuje etap, `Esc` przerywa czekanie (nie budowę — ta należy do tamtego
   modułu);
4. udostępnienie obrazu klastrowi — wedle rozstrzygnięcia nr 4;
5. kwerenda `k8s.deployments` → wybór wdrożenia i podmiana obrazu
   (`kubectl set image` albo `apply`).

Kwerendy dowożone przy okazji: `docker.images`, `docker.containers`,
`k8s.deployments`, `k8s.contexts`. Każda ma **odbiorcę w tej czynności** —
reguła 13 obowiązuje kwerendy tak samo, jak komponenty.

### 7. Pomiar

Kwerendy są wołane **na żądanie**, nie co klatkę, więc oś `--loop` mierzy tu
wyłącznie to, że rejestr niczego nie kosztuje przy zerze pytań — „przed i po",
bez regresji.

Scenariusza klatki krok **nie dokłada, o ile okno kwerend zostanie drugim trybem
okna komend** (rozstrzygnięcie nr 9): scenariusz `command` w `bin/render-bench`
rysuje wtedy tę samą klatkę, a różnica jest w treści listy, nie w kształcie.
Wariant „osobne okno" **dokłada scenariusz** i to jest jego cena zapisana z góry,
a nie odkryta przy odbiorze. Okna wyboru czynności `k8s.deploy-image` to
`ChoiceOverlay` i `ProgressOverlay` — oba mają zapisane powody pominięcia od
kroków 41 i 42.

Rachunek kolumn okna kwerend przelicza się **jak w kroku 46**: najdłuższa nazwa
kwerendy razem z najdłuższym opisem z obu katalogów napisów ma się zmieścić,
a pilnuje tego test czytający `pl` i `en` — bo tam omal nie ucięło najdłuższej
nazwy zdarzenia i było to widać dopiero w klatce.

## Poza zakresem

- **Kwerendy zmieniające cokolwiek** — to są komendy (reguła nr 1).
- **Kwerendy asynchroniczne, kolejki, obietnice** — kwerenda odpowiada w klatce
  albo oddaje stan pracy. Rzecz, której nikt jeszcze nie potrzebuje.
- **Rejestr zdolności ogólnego przeznaczenia** („moduł ogłasza, że umie X”) —
  nazwa kwerendy wystarcza; osobna warstwa deklaracji byłaby rozwiązaniem
  problemu, którego nie ma.
- **Automatyczne wykrywanie, kto co umie** — wołający zna nazwę, którą wpisał
  programista, a nie szuka wykonawcy po opisie.
- **Rejestr obrazów i `docker push`** — wchodzi tylko wtedy, gdy rozstrzygnięcie
  nr 4 wybierze tę drogę udostępnienia obrazu klastrowi.
- **Kwerendy modułu `Ssh`** (`ssh.hosts`, zdalne ścieżki) — Faza XVII jest
  **nierozpoczęta**, a wciągnięcie jej tutaj dołożyłoby kroki 48, 49 i 50 do
  zależności i związało dwie fazy, które dziś stoją osobno. Mechanizm
  to umożliwi, a dopisanie kwerend do gotowego modułu kosztuje jedną zdolność —
  i to jest właśnie sprawdzian, że mechanizm wyszedł dobrze. **Wariant „wszystkie
  trzy plus `ssh`" był postawiony użytkownikowi i został odrzucony** (D86).
- **Kwerendy powtarzające `ModuleContext`** (`browser.cwd`, `browser.selection`)
  — zastrzeżenie trzecie. Danych rozdawanych co klatkę nie podaje się drugi raz
  na życzenie.
- **Historia kwerend i zapamiętane wyniki** — okno kwerend pyta i pokazuje;
  pamięta okno komend, bo tam pada czynność.
- **Kwerendy w oknie kwerend zmieniające cokolwiek** — patrz reguła nr 1;
  widoczność mechanizmu nie jest zgodą na drugą drogę do czynności.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Query/{QueryInterface,QueryArgument,QueryResult,QueryRegistry,QueryRejection}.php` | Rdzeń/Application | **Nowe** — mechanizm |
| `Application/Module/ProvidesQueries.php` | Rdzeń/Application | **Nowa** zdolność |
| `Presentation/Cli/Bootstrap.php` | Rdzeń | Rejestr kwerend składany jak rejestr komend — **bez pozycji na moduł** |
| `Presentation/Cli/LoopState.php` | Rdzeń | Dostęp do rejestru kwerend, obok rejestru zdarzeń (krok 46) |
| `Presentation/Ui/Overlay/CommandOverlay.php` **albo** `QueryOverlay.php` | Rdzeń/Presentation | Okno kwerend: drugi tryb istniejącego okna (rekomendacja) albo osobne — rozstrzygnięcie nr 9 |
| `Module/Docker/**` | Moduł | `ProvidesQueries`: `docker.images`, `docker.containers` |
| `Module/Kubernetes/**` | Moduł | `ProvidesQueries`: `k8s.deployments`, `k8s.contexts`; czynność `k8s.deploy-image` wraz z oknami i czekaniem na zdarzenie |
| `Module/Kubernetes/lang/{pl,en}.php` | Napisy | Etapy czynności, zdanie „nie ma kto zbudować obrazu” |
| `Module/Browser/**` | Moduł | **Uzupełnienie**: `ProvidesQueries` — `browser.entries`, `browser.marked`; czytają istniejące repozytorium katalogu, niczego nie dokładają do jego dziedziny |
| `Module/FileInfo/**` | Moduł | **Uzupełnienie**: `ProvidesQueries` — `file-info.usage`, `file-info.digest` jako **stan** pracy tłowej (`DiskUsageStage`, `ChecksumStage`) |
| `Module/Audio/**` | Moduł | **Uzupełnienie**: `ProvidesQueries` — `audio.now-playing`, `audio.playlist`; czytają `PlaylistPlayer` |
| `Module/{Browser,FileInfo,Audio}/lang/{pl,en}.php` | Napisy | **Uzupełnienie**: opisy sześciu kwerend w obu językach — widoczne w oknie kwerend, więc przez katalog, nie napisem w kodzie |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Kwerendy jako mechanizm, granica dopowiedzenia reguły 15, zdanie „komenda robi, kwerenda mówi” **oraz** „kontekst mówi gdzie stoję, kwerenda mówi co u mnie jest” |
| testy | Testy | Rejestr kwerend (przedrostki, brak wykonawcy, kwerenda wołająca kwerendę), **dwa moduły-atrapy współpracujące bez Dockera i bez klastra**, przebieg czynności na atrapach obu portów, **kompletność opisów kwerend w obu katalogach napisów** (wzorem kroku 46) |

## Do rozstrzygnięcia na starcie kroku

1. **Kształt wyniku kwerendy** — wiersze danych pierwotnych (rekomendacja), czy
   jedna wartość plus opcjonalna lista. Wynik musi udźwignąć listę obrazów
   i pojedynczy identyfikator naraz.
2. **Gdzie mieszka rejestr kwerend** — w `LoopState`, obok rejestru zdarzeń
   i kontekstu sesji (rekomendacja: tak, bo wtedy `Bootstrap` rośnie o jedną
   linię, a nie o argument przy każdym module), czy osobnym Singletonem.
3. **Co widzi użytkownik, gdy wykonawcy nie ma** — czynność znika z menu, czy
   stoi w nim i mówi, czego brakuje (rekomendacja: stoi i mówi).
4. **Jak obraz trafia do klastra** — `minikube image load`, rejestr i `push`,
   czy założenie o wspólnym demonie. Bez tego czynność jest atrapą (zastrzeżenie
   drugie).
5. **Czyja jest czynność `deploy-image`** — modułu Kubernetesa (rekomendacja),
   modułu Dockera, czy trzeciego modułu spinającego. Trzeci znaczy moduł
   istniejący wyłącznie po to, żeby znać dwa inne.
6. **Czy czekanie na cudze zdarzenie ma limit czasu** i co się dzieje po jego
   upływie — budowa trwa dalej u siebie, więc porzucenie czekania nie może jej
   ubić.
7. ~~**Czy kwerendy są widoczne dla użytkownika**~~ — **rozstrzygnięte
   2026-08-15, przed startem kroku** (D86): **są widoczne**. Powód nie jest
   wygodą, tylko regułą 13: cztery z sześciu kwerend istniejących modułów nie
   mają konsumenta w kodzie, a widoczność daje im prawdziwego odbiorcę zamiast
   wyjątku. Jak to widać — rozstrzygnięcie nr 9.
8. **Czy kwerendy istniejących modułów wchodzą przed czynnością, czy po niej.**
   Rekomendacja: **przed** — sześć kwerend na trzech modułach, które stoją
   i działają, jest najtańszym sprawdzianem kontraktu; wykryta w nich wada
   kontraktu kosztuje przeróbkę sześciu klas, a wykryta po czynności — także
   samej czynności.
9. **Czym jest okno kwerend** — drugim trybem okna komend spod `F12`
   (rekomendacja: tak — te same komponenty, ten sam parser wiersza, ten sam
   scenariusz pomiarowy `command`, a słownik wejścia nie rośnie o klawisz), czy
   osobnym oknem. Rozstrzygnięcie obejmuje dwie rzeczy naraz: **czym przełącza
   się tryb** i **gdzie ląduje wynik** — wiersze `QueryResult` w liście okna czy
   w osobnym oknie tekstu.
10. **Czy `file-info.usage` dostaje drugiego odbiorcę w kodzie** — wagę katalogu
    kontekstu budowy pokazywaną przed wysłaniem go do demona. Rzecz jest tania
    (kwerenda już istnieje) i chroni przed klasyczną pułapką Dockera, ale
    dokłada etap do czynności, która ma ich pięć.
11. **Co robi okno kwerend z kwerendą wymagającą argumentu**, którego użytkownik
    nie podał — odmawia zdaniem czy pyta `PromptOverlay`em. Rekomendacja:
    odmawia, tak jak okno komend przy brakującym argumencie komendy.

## Kryteria ukończenia

- `k8s.deploy-image` przechodzi całą drogę: wybór, budowa, czekanie,
  udostępnienie, podmiana — na prawdziwym demonie i prawdziwym klastrze
  (minikube, po uzgodnieniu z użytkownikiem).
- **Wyłączony moduł Dockera nie wywraca niczego**: czynność mówi, czego brakuje,
  a aplikacja działa dalej.
- Kwerenda spoza przestrzeni właściciela zostaje odrzucona wraz z powodem —
  jak komenda i jak zdarzenie.
- Kwerenda wołająca kwerendę zostaje zignorowana, a nie zapętla pętli.
- **Wszystkie trzy moduły sprzed tego kroku oddają swoje kwerendy**, a okno
  kwerend pokazuje **dziesięć pozycji** (sześć istniejących modułów, cztery
  kontenerowe) wraz z opisami — po polsku i po angielsku.
- **Kwerenda modułu wyłączonego nie stoi w oknie**, a kwerenda modułu
  odrzuconego nie stoi tam tym bardziej — spis jest widokiem na rejestr, a nie
  drugą listą (wzorem menu z kroku 32).
- **`file-info.usage` i `file-info.digest` odpowiadają w klatce także wtedy, gdy
  praca tłowa trwa** — oddają etap, nie czekają na koniec. Sprawdzane na katalogu
  na tyle dużym, żeby `du` nie zdążyło; **żaden test nie mierzy czasu zegarem**.
- **Żadna kwerenda nie oddaje danej, którą niesie `ModuleContext`** —
  sprawdzane przeglądem sześciu nazw i ich pól przy odbiorze kroku.
- Żaden moduł nie wymienia w kodzie **typu** innego modułu — pilnuje tego test,
  wzorem `CoreKnowsNothingAboutFilesTest`.
- Rejestr kwerend przy zerze pytań **nie kosztuje mierzalnie** —
  `bin/render-bench --loop` „przed i po” bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie rozmawia
  z demonem ani z klastrem**.

## Dziennik realizacji

_(pusty — krok nie rozpoczęty)_
