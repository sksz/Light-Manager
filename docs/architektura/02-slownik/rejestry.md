# 2. Słownik domenowy — Zdarzenia, kwerendy i cudze czynności

> Część rozdziału 2. Pojęcia i wstęp: [slownik.md](slownik.md).
> Spis rozdziałów: [docs/architecture.md](../../architecture.md).

Trzy drogi, którymi moduł rozmawia z aplikacją i z innym modułem — i to, że czwartej nie ma.

## Zdarzenia aplikacji (od kroku 46)

Aplikacja ogłasza **nazwane momenty**, a moduł może je odebrać i coś z nimi
zrobić. Mechanizm jest w rdzeniu ogólny — rdzeń nie wie o dźwięku ani o żadnym
innym odbiorcy — a nazwa klasy wyznacza jego granicę: **`EventRegistry`, nie
szyna**. Kolejek nie ma, priorytetów nie ma, zdarzeń odłożonych w czasie nie ma;
publikacja jest synchroniczna i kończy się, zanim wróci wołający. Rzecz jest
bliższa `CommandRegistry` niż czemukolwiek z podręcznika o zdarzeniach — łącznie
z regułą przestrzeni nazw, którą stamtąd powtarza co do joty.

**Słownik jest zamknięty, a jego rozszerzenie wymaga zgody użytkownika** — ta
sama reguła, co przy słowniku prymitywów (reguła 11k). Powód jest inny niż tam:
zdarzenie publikuje rdzeń albo moduł, a odbiera **ktoś zupełnie inny**, więc
każde nowe jest umową, której nie da się cofnąć bez zmiany w obu miejscach naraz.
Kryterium doboru: wchodzi zdarzenie, które publikujący **już zna z nazwy**, bo je
gdzieś raportuje albo przełącza.

**Zamkniętość jest wykonana konstrukcyjnie, nie regulaminowo**: nazwy pochodzą
z enumów (`Application\Event\AppEvent` dla rdzenia,
`Module\Browser\Application\BrowserEvent` dla przeglądarki), a deklaracja
katalogu powstaje z `cases()`. Publikacja i spis pokazywany użytkownikowi nie mają
przez to jak się rozjechać — a rozjazd byłby **niewidoczny**: wiersz, do którego
nic nie dochodzi, wygląda tak samo jak wiersz, do którego nic nie przypisano.

| Kto | Ile | Co ogłasza |
|---|---|---|
| rdzeń (`core.*`) | 5 | trzy tony komunikatu (`LoopState::report()` — jedyne miejsce, przez które przechodzą **wszystkie** zdania aplikacji), otwarcie okna nakładanego, wykonanie komendy |
| przeglądarka (`browser.*`) | 17 | ruch kursora, wejście do katalogu, zaznaczenie wpisu oraz **siedem czynności × udana/nieudana** |

Zdarzenie niesie **wyłącznie tożsamość** — nazwę i nic ponad nią (ta sama zasada,
którą kieruje się `ModuleContext`, D40 P5). Obiektu domeny modułu przez zdarzenie
nie przekazujemy nigdy, bo odbiorca musiałby wtedy poznać moduł, który je
publikuje.

Trzy reguły publikacji, wszystkie **wykonane w `EventRegistry::publish()`**,
a nie zostawione dobrej woli wołającego:

- **publikacja jest tania i nie rzuca** — wyjątek odbiorcy ginie w rejestrze, bo
  publikacja stoi w środku `report()` i w środku czynności na plikach, a te nie
  mają dokąd zgłosić cudzego kłopotu;
- **publikujący nie wie, kto słucha** — przy zerze odbiorców `publish()` kończy
  się na jednym sprawdzeniu w tablicy;
- **zdarzenie nie rodzi zdarzenia** — odbiorca próbujący publikować w trakcie
  odbioru zostaje zignorowany; bez tego pojedynczy błąd zapętliłby pętlę główną.

Odbiorca dostaje **napis, a nie typ**, i nie ma prawa niczego zwrócić: to nie jest
droga, którą moduł zmienia bieg aplikacji — od tego są komendy. Czasu odbiór też
nie dostaje; odbiorca, któremu jest potrzebny, bierze go z taktu (`NeedsTick`),
o który i tak prosi.

**Kosztem w rdzeniu jest jedna linia w `Bootstrapie`**
(`$state->events()->useModules($modules->accepted())`), a rejestr mieszka
w `LoopState` — obok kontekstu sesji i z tego samego powodu: stan pętli dostaje
**każdy** moduł, więc publikacja nie kosztuje ani jednego argumentu więcej.

**Krok 46 odwrócił przy tym jedno zdanie własnego planu** i warto wiedzieć,
dlaczego: plan mówił „rdzeń publikuje, moduł odbiera”, a zdarzenia modułów miał
w wykluczeniach. Rozpoznanie w kodzie pokazało, że wszystkie zdania modułów
schodzą się w `LoopState::report()` z tonem — trzema zdarzeniami rdzenia da się
więc odróżnić powodzenie od awarii, ale **nie da się odróżnić kopiowania od
usunięcia**. Efekt przypisany do „zakończonego kopiowania” wymaga, żeby to
kopiowanie samo o sobie powiedziało (D83, rozstrzygnięcia 1–2).

## Kwerendy: jedyna droga odczytu (od kroku 53)

**Komenda robi, kwerenda mówi.** Rejestr komend niesie od kroku 19 czynności,
rejestr zdarzeń od kroku 46 — nazwane momenty, a `CommandOutcome` niesie **zdanie
dla użytkownika**. Danej nie niósł do kroku 53 żaden z tych kanałów: „zbuduj obraz
i podaj mi jego znacznik" nie miało czym wrócić. Kwerendy są tym kanałem —
i od razu **jedynym**: odczyt idzie przez rejestr także wewnątrz rdzenia i wewnątrz
modułu (D92 nr 3).

`Application\Query\QueryRegistry` powtarza konstrukcję rejestru komend co do
joty: przestrzeń nazw wymuszona (`core.*`, `<id modułu>.*`), odrzucenie z powodem
jako dana, zbiór globalny. Moduł wnosi swoje zdolnością
`Application\Module\ProvidesQueries`, a rdzeń wylicza swoje w jednym miejscu
(`Presentation\Cli\Query\CoreQueries`). Rejestr mieszka w `LoopState`, obok
rejestru zdarzeń i z tego samego powodu — więc kosztuje **jedną linię**
w `Bootstrapie`.

Cztery reguły kwerendy, wykonane w kontrakcie albo w rejestrze:

1. **Czyta i nie zmienia.** Co zmienia — jest komendą. Bez tego pierwsza kwerenda
   `docker.prune` uczyniłaby mechanizm drugą drogą do czynności.
2. **Nie zna wołającego** i wygląda tak samo przy zerze pytających.
3. **Nie woła kwerendy** — pytanie zadane w trakcie odpowiadania zostaje
   odmówione, wzorem „zdarzenie nie rodzi zdarzenia".
4. **Odpowiada w klatce albo nie odpowiada wcale.** Praca dłuższa od klatki idzie
   komendą i pracą kawałkową, a kwerenda oddaje **stan tej pracy** — precedens
   z kroków 25 i 26 (`ChecksumStage`, `DiskUsageStage`).

**Wynik ma dwa oblicza, a nie dwa kanały.** `QueryResult` niesie wiersze danych
pierwotnych — `list<array<string, string|int|bool>>` — dla **każdego**, oraz
ładunek typowany wydawany **wyłącznie właścicielowi** (`payloadFor($owner)`).
Dzięki temu jedna droga nie znaczy rysowania z tablic napisów: `BrowserScreen`
dostaje `Directory`, a moduł Kubernetesa — napisy i liczby. Reguła 15 zostaje
nietknięta, bo cudzy ładunek wraca jako `null`.

**Routing.** Kwerenda oddaje tani `generation(): int`, a rejestr pamięta ostatnią
odpowiedź pod kluczem `nazwa + argumenty`; dopóki pokolenie się nie zmieniło,
odczyt kosztuje jedno wyszukanie w tablicy. Wiersze budują się **leniwie**, więc
właściciel czytający ładunek nie płaci za tablice, których nikt nie obejrzy.
Źródło bez naturalnego licznika deklaruje `QueryInterface::VOLATILE` i wtedy
**nie jest pamiętane w ogóle** — poprawka wymuszona testem: odpowiedź pamiętana
„na jedną klatkę" oddawała stan sprzed zmiany, która padła w tej samej klatce.

**Widoczność.** Spis kwerend i ich wykonanie stoją w **drugim trybie okna komend**
(`F12`, przełącza `Tab` przy pustym polu). Odpowiedź jednowierszowa pokazuje się
jako pary `pole: wartość` (`ListRow`), wielowierszowa — jako `Table` z nagłówkiem.
Historii kwerendy nie mają: historia zapisuje czynność, a nie pytanie.

**Kontekst a kwerenda.** Zdanie rozważane przy planowaniu — „kontekst mówi, gdzie
użytkownik stoi; kwerenda mówi, co u mnie jest" — **nie obowiązuje** (D92 nr 8).
Broniło ono przed dwiema drogami do jednej danej, a po rozstrzygnięciu „rejestr
jedyną drogą odczytu" drugiej drogi nie ma. `ModuleContext` zostaje jako to, co
rdzeń rozdaje **bez pytania**, a `core.context`, `browser.cwd` i
`browser.selection` są tym samym oglądanym przez kanał — z jedną różnicą, która
uzasadnia ich istnienie: kontekst mówi o panelu **czynnym**, kwerenda o dowolnym.

**Zasięg domknięty w kroku 54.** Kwerendy mają odtąd **wszystkie moduły** —
w kroku 54 było ich sześć (`ssh` cztery, `docker` pięć, `k8s` sześć) — a strażnik
`QueryIsTheOnlyReadPathTest` nie zna ani jednego zwolnienia. Dopisanie ich do
gotowych modułów kosztowało **jedną zdolność na moduł** i ani jednej linii
w `Application/Query/`; to była druga miara tamtego kroku i sprawdza się ją
`git diff`em na katalogu mechanizmu, a nie na słowo. Trzy rzeczy wyszły przy tym
na jaw i obowiązują przy każdej następnej kwerendzie:

- **Odpowiedź asynchroniczna niesie etap w każdym wierszu**, a pusta lista dostaje
  wiersz z samym etapem (`ssh.entries`, `k8s.resources`, `docker.images`).
  Bez tego „czytam z sieci", „nie ma nic" i „nikt jeszcze nie pytał" wyglądają dla
  obcego modułu identycznie — a różnią się tym, czy warto poczekać, czy coś
  zrobić. Ostatni z tych trzech stanów wykrył dopiero test czynności
  `k8s.deploy-image`: moduł Dockera pyta demona o obrazy wtedy, gdy ktoś na nie
  patrzy, więc czynność uruchomiona wcześniej zastawała pustkę.
- **Fasada oddaje migawkę, a nie obiekt roboczy.** Obiekt roboczy musiałby wracać
  jako `null`owalny (przy module wyłączonym nie ma czego oddać), a wtedy każde
  miejsce odczytu powtarzałoby obsługę braku — czyli dokładnie to, przed czym
  broni `QueryRegistry::ask()`.
- **Materiał uwierzytelnienia nie wchodzi do wierszy.** Odcisk klucza i ścieżka
  klucza prywatnego zostają poza `ssh.hosts`, a adres serwera poza `k8s.cluster`:
  „dana pierwotna" nie znaczy „wszystka", a wiersze widzi każdy.

## Moduł zamawia cudzą czynność (od kroku 54)

Kwerenda mówi, ale **nie robi**. Czynność przechodząca przez dwa moduły —
`k8s.deploy-image`, dla której cała Faza XVIII ma trzy kroki zamiast dwóch —
potrzebowała przez to trzeciego kanału i pokazała **lukę zapisaną w regułach od
kroku 53**: reguła 15g mówiła „moduł zna nazwę cudzej komendy", ale nic nie
dawało modułowi rejestru komend, więc nazwa była bezużyteczna. Nikt tego nie
zauważył, bo pierwszy odbiorca powstał dopiero teraz.

`LoopState::commands()` jest odtąd **trzecim rejestrem** obok zdarzeń i kwerend,
z tego samego rachunku: stan pętli dostaje każdy moduł. Rejestr **wchodzi
wypełniony** (`useCommands()` w `Bootstrapie`, przed składaniem modułów), a o okno
pyta się **zdolności komendy** (`OpensOverlay`), nie rejestru — tą samą linią, co
okno komend i menu.

Całość składa się w zdanie: **komenda robi, zdarzenie ogłasza, kwerenda mówi co
wyszło.** Czynność `k8s.deploy-image` zna moduł Dockera z **trzech napisów**
(`docker.images`, `docker.build`, `docker.push`) i ani jednego typu; pilnuje tego
`NoModuleKnowsAnotherModuleTest`, chodzący po przestrzeniach nazw, a nie po
treści wywołań.

Praca cudza trwa przy tym minutami, więc czekanie na nią jest **oknem pracy
pytającym kwerendą raz na takt** — `RunsWork` z kroku 41 pasuje tu bez zmiany,
choć powstało dla pracy własnej. `Esc` porzuca **czekanie, nie pracę**: ta należy
do tamtego modułu i trwa u niego dalej. Warunkiem tego zdania była zmiana
wewnątrz modułu Dockera — budowa i wypychanie posuwają się odtąd **taktem
modułu**, a nie własnym oknem, bo stos okien ma jedno piętro i praca posuwana
przez własne okno stawała, gdy cokolwiek innego zajęło ekran.
