# Krok 41 — Fundament operacji: nazwa, nowy katalog, usunięcie

> **Skąd ten krok.** Powstał 2026-08-13 na żądanie użytkownika. Zamyka pierwszą
> pozycję „Zakresu poza MVP”, która stoi w planie od jego pierwszej wersji:
> *operacje na plikach (kopiuj / przenieś / usuń / zmień nazwę / nowy katalog)*.
> Wstęp do niej dowiozły dwa kroki — **24** (dwa panele, czyli źródło i cel)
> i **28** (okno potwierdzenia, bez którego usuwanie nie miało prawa powstać).

## Status

**Ukończony z zastrzeżeniem** (2026-08-14). Rozstrzygnięcia startowe —
trzynaście, z czego cztery wynikły z odpowiedzi na pytanie nr 4, a jedno odwraca
zapis planu — leżą w [00-decyzje.md](00-decyzje.md), D75.

**Zastrzeżenie:** rozstrzygnięcie nr 5 planu („dwie czynności w menu kontekstowym
przez `AppliesToSelection`”) okazało się **niewykonalne w dzisiejszym rdzeniu** —
`CommandOutcome` leży w `Application` i wskazuje ekran identyfikatorem, a okna
nakładane rejestru identyfikatorów nie mają, więc żadna komenda nie umie otworzyć
okna. Powstały dwie komendy z argumentem (`browser.rename`, `browser.mkdir`),
`browser.delete` nie powstał wcale, a **menu `F9` nie zyskało ani jednej pozycji**.
Zobowiązanie wobec kroku 32 zostaje długiem.

## Cel

Przeglądarka po raz pierwszy **zmienia** to, co pokazuje. Do tego kroku aplikacja
wyłącznie czytała dysk — jedynym plikiem, który potrafiła zapisać, był jej własny
plik konfiguracyjny.

Miarą powodzenia jest zdanie: **jeden klawisz na zaznaczonym wpisie zmienia dysk,
a lista pokazuje skutek w tej samej klatce — w obu panelach, jeśli oba patrzą na
ten sam katalog.** Zdanie odwrotne jest równie wiążące: operacja, która się nie
udała, ma zostawić dysk nietknięty i powiedzieć dlaczego, a nie zostawić po sobie
plik w połowie.

## Rozstrzygnięcie wykonywane wbrew dotychczasowej regule — najważniejsza treść tego pliku

Użytkownik rozstrzygnął (2026-08-13, [00-decyzje.md](00-decyzje.md), D66,
rozstrzygnięcie 2), że operacje zapisu mieszkają **w rdzeniu, jako usługa
wspólna**. To jest odwrócenie części D40 i wchodzi w spór z regułą 15
Skilla, więc musi być zapisane wprost, zanim powstanie pierwsza linia kodu.

**Co mówiło dotąd.** Krok 20 ustalił regułę 15: „nowa funkcja to moduł
w `src/Module/`, nie zmiana w rdzeniu”. Krok 21 (D40, D42) wyprowadził z rdzenia
pojęcie katalogu i pliku co do znaku — do tego stopnia, że `ProblemPresenter`
przestał rozpoznawać wyjątki po klasie, a `Entry::permissionsAsText()` **świadomie
powtarza** rachunek z modułu `FileInfo`, bo „moduł nigdy nie sięga do innego
modułu, a rdzeń nie wie, czym jest plik”.

**Co się zmienia.** Rdzeń dostaje port operacji zapisu i jego usługę. Zakres tej
wiedzy jest **ściśle wyznaczony i szerszy być nie ma prawa**:

- rdzeń zna **ścieżkę bezwzględną jako napis** i czynność do wykonania na niej;
- rdzeń **nadal nie wie**, czym jest wpis katalogu, sortowanie, ukrywanie,
  zaznaczenie ani podgląd — `Entry`, `Directory`, `DirectoryPath` i `EntryType`
  zostają w module i **nie mają prawa** pojawić się w sygnaturze niczego
  w `src/Application` ani `src/Domain`;
- rdzeń **nie rysuje** niczego z powodu operacji — okna, klawisze i komunikaty
  zamawia moduł, tak jak dotąd.

**Powód, dla którego to jest do przyjęcia.** Operacji potrzebuje dziś jeden moduł,
ale kroki 42 i 44 dokładają do nich pracę kawałkową i kosz, a moduł `FileInfo`
jest naturalnym drugim odbiorcą (usunięcie opisywanego pliku). Reguła „moduł nigdy
nie sięga do innego modułu” znaczy przy dwóch odbiorcach **dwie kopie tego samego
kodu piszącego po dysku** — a to jest gorsze niż jeden port w rdzeniu. Rachunek
`permissionsAsText()` wolno było powtórzyć, bo powtórzenie kosztowało dziesięć
linii bez skutków ubocznych; powtórzone `unlink()` kosztuje utratę danych w dwóch
miejscach zamiast w jednym.

**Cena zapisana od razu:** reguła 15 przestaje być bezwyjątkowa, a `SKILL.md` musi
powiedzieć **gdzie przebiega nowa granica**, inaczej następna funkcja wjedzie do
rdzenia tym samym argumentem. Zapis granicy jest częścią zakresu tego kroku
(punkt 10), nie dopiskiem po fakcie.

## Zależności

- **Krok 28** twardo — `ConfirmOverlay` wraz z wariantem `dangerous` i wzorcem
  z D56 („decyzja wraca domknięciem”). Usuwanie jest **pierwszym prawdziwym
  użytkownikiem wariantu groźnego**: dziś okna potwierdzenia używa jeden ekran
  (`SettingsScreen::restoreConfirmation()`) i to w wariancie zwykłym.
- **Krok 18** — `TextInput`, `Dialog`, `Button`, `KeyBinding` i reguła 10 („nowe
  okno nad ekranem to `OverlayInterface`”). Okno wprowadzania nazwy nie powołuje
  ani jednego nowego komponentu.
- **Krok 19** — `OverlayInterface`, `ScreenOutcome::opens()` i kontrakt komendy,
  jeśli operacje mają mieć drugie wejście przez rejestr (pytanie 5).
- **Krok 21** (D42) — `DescribesProblem`, czyli jedyna droga, którą niepowodzenie
  operacji dojdzie do paska stanu **bez** przywracania rdzeniowi wiedzy o plikach.
- **Krok 24** — `BrowserPanes`, czyli dwa panele. Odświeżenie **obu** po zmianie
  na dysku jest wymogiem tego kroku, nie ozdobą.
- **Kroki 14 i 15** — ustawienia modułu i katalog napisów.
- **Krok 38** — wzorce w trzech torach i katalog przebiegów funkcjonalnych.

Od kroków **31, 32, 36, 37, 39 i 40** nie zależy i one nie zależą od niego.
Z krokiem **32** (menu kontekstowe) styka się treścią: menu miało „odłożyć się do
czasu, aż powstaną operacje na plikach”, bo dziś nie ma czego pokazać. Po tym
kroku ma — i to jest zdjęcie zastrzeżenia ze szczytu pliku kroku 32, ale
zależności w żadną stronę to nie tworzy.

## Model i wysiłek

**Opus / high.**

Kodu niedużo, ale trafia w trzy miejsca naraz: nowy port w rdzeniu, nowe okno
nakładane i ekran modułu, który po raz pierwszy coś psuje na dysku. Ryzyko nie
leży w objętości, tylko w tym, że **błąd jest nieodwracalny** — testy operacji
zapisu muszą dotykać prawdziwego katalogu tymczasowego, a nie atrapy.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Port/*` | Dziesięć portów rdzenia; **żaden nie zapisuje niczego** poza konfiguracją (`SettingsPort`) i historią komend. |
| `Infrastructure/Config/SettingsService` | Jedyne miejsce rdzenia piszące na dysk — i wyłącznie własny plik. |
| `Presentation/Ui/Overlay/ConfirmOverlay` | Gotowe; wariant `dangerous` **nie ma dziś użytkownika**. Decyzja wraca domknięciem (D56). |
| `Presentation/Ui/Component/TextInput` | Pole z karetką i `NeedsTime`; używają go `CommandOverlay`, `FilterOverlay` i ekran ustawień. |
| `Module/Browser/…/DirectoryRepositoryInterface` | **Tylko odczyt**: `get(path, includeHidden)`. |
| `Module/Browser/…/Entry` | Waliduje nazwę wpisu (pusta, `.`, `..`, ukośnik) — **jedyne miejsce w projekcie**, które wie, co jest poprawną nazwą. |
| `Module/Browser/…/BrowserState` | Katalog, filtr i publikacja kontekstu; `enter()` jest jedyną drogą zmiany katalogu. |
| `Module/Browser/…/BrowserPanes::all()` | Oba panele osiągalne — gotowa droga do odświeżenia drugiego. |
| `BrowserScreen::bindings()` | Strzałki, `Enter`, `Backspace`, `.`, `/`, `Tab`, `Esc`. **F3–F9 i F11 są wolne** (rdzeń bierze `F1`, `F2`, `F10`, `F12`). |
| `Application/Dto/Key` | `F1`–`F12` oraz `Delete` są w słowniku; **`Insert` go nie ma**, a spacja przychodzi jako `Character`. |
| `Domain/Exception/DescribesProblem` | Wyjątek przedstawia się kluczem katalogu wraz z parametrami (D42). |
| `Infrastructure/Support/InfrastructureException` | Druga hierarchia wyjątków — miejsce dla niepowodzeń zapisu (reguła 8). |

## Zakres

### 1. Kontrakt operacji w rdzeniu

Port w `Application/Port` wraz z usługą-Singletonem w `Infrastructure`. Trzy
czynności, wszystkie **natychmiastowe** — kończą się w tej samej klatce, w której
się zaczęły, i to jest granica tego kroku: praca dłuższa od klatki należy do
kroku 42.

Kształt do rozstrzygnięcia (pytanie 1); kierunek: jeden port z trzema metodami,
bez stanu i bez zasady „jedna praca naraz” — ta zasada jest własnością pracy
kawałkowej (`ChecksumPort`, `BackgroundProcessPort`), a nie zmiany nazwy.

Port **nie zna pojęcia wpisu katalogu**. Bierze ścieżki bezwzględne jako napisy
i oddaje albo nic, albo wyjątek — reguła z sekcji o rozstrzygnięciu, powtórzona
tutaj, bo to jest miejsce, w którym najłatwiej ją złamać.

### 2. Okno wprowadzania nazwy

Zmiana nazwy i nowy katalog potrzebują jednego: **napisu wpisanego przez
użytkownika, zatwierdzonego `Enter`em i odrzuconego `Esc`**. Okno składa się
z `Dialog`u i `TextInput`u, więc komponent nie powstaje ani jeden — powstaje
sposób ich użycia, dokładnie jak w kroku 28.

Wzorzec oddawania wyniku bierze się z D56: domknięcie podane przy tworzeniu okna,
wykonane po `Enter`, oddające komunikat do paska stanu. Kontrakt okna nakładanego
nie rośnie ani o pole.

Miejsce klasy (rdzeń czy moduł) jest pytaniem 8. Rekomendacja: **rdzeń**, bo
kroki 42 (nazwa zastępcza przy kolizji) i 44 (nazwa przy przywracaniu z kosza)
poproszą o to samo okno, a trzeci użytkownik przesądza sprawę wcześniej, niż
zdąży ona stać się długiem.

**Nazwa wpisana do okna jest treścią, nie ścieżką**: ukośnik w niej jest błędem,
a nie zaproszeniem do utworzenia katalogu piętro niżej. Sprawdzenie należy do
tego, kto wie, co jest poprawną nazwą (pytanie 2).

### 3. Trzy czynności i ich klawisze

- **Zmiana nazwy** wpisu pod kursorem — okno z nazwą bieżącą jako treścią
  początkową.
- **Nowy katalog** w katalogu panelu czynnego — okno z pustą treścią.
- **Usunięcie** wpisu pod kursorem — `ConfirmOverlay` w wariancie `dangerous`,
  z nazwą wpisu w pytaniu.

Klawisze do rozstrzygnięcia (pytanie 3); rekomendacja: **`F5`–`F8` wzorem
klasycznych menadżerów**, bo z niczym nie kolidują, a litery są zasobem, którego
przeglądarka będzie jeszcze potrzebowała.

### 4. Odświeżenie listy — obu paneli

Po każdej udanej operacji lista ma pokazywać skutek **natychmiast**. To brzmi
oczywisto i nie jest: paneli są dwa i mogą patrzeć na ten sam katalog, a od kroku
24 **każdy ma własny agregat tego samego katalogu**, właśnie dlatego, że
`Directory` jest mutowalny w miejscu.

Reguła: odświeża się każdy panel, którego katalog jest tym, w którym coś się
zmieniło. Zaznaczenie przenosi się **po nazwie**, nie po numerze — tak samo, jak
przy filtrze z kroku 30; po zmianie nazwy kursor idzie za nazwą nową (pytanie 7).

Droga odświeżenia jest pytaniem 6: ponowny odczyt przez repozytorium (prosto,
kosztuje jedno `scandir`) albo zmiana wpisu w agregacie (tanio, ale tworzy drugie
źródło prawdy o zawartości katalogu).

### 5. Niepowodzenie mówi, co się stało

Brak praw, plik zajęty, nazwa zajęta, katalog niepusty, dysk pełny — każdy z tych
przypadków ma **własne zdanie**, a nie wspólne „nie udało się”. Wyjątek
przedstawia się sam (`DescribesProblem`), więc rdzeń nie uczy się przy okazji, co
to jest katalog.

**Operacja albo się dzieje w całości, albo wcale.** Sprawdzenie warunków przed
czynnością (istnienie, prawa, kolizja nazw) należy do usługi, nie do ekranu —
ekran ma pokazać skutek, a nie zgadywać, czy da się go osiągnąć.

### 6. Ustawienia modułu

Jedna pozycja: **czy pytać o potwierdzenie przed nieodwracalnym usunięciem**
(domyślnie tak). Więcej nie — kroki 42 i 44 dołożą własne, a ustawienie dodane na
zapas jest przełącznikiem bez odbiorcy (reguła 13 zastosowana do konfiguracji).

### 7. Napisy

Nazwy czynności, pytania okien, zdania niepowodzeń, opisy klawiszy do okna pomocy
— katalog modułu (`module.browser.*`), a jeśli okno nazwy stanie w rdzeniu, jego
tytuł i przyciski idą do `lang/pl.php` i `lang/en.php`.

### 8. Pomiar

Scenariusz `popup` z kroku 18 obejmuje okno nakładane nad listą i **prawdopodobnie
wystarcza** — okno nazwy składa się z tych samych prymitywów, co okno komend.
Nowy scenariusz powstaje wyłącznie wtedy, gdy okaże się osobnym kosztem, a nie tym
samym w innym prostokącie.

Przed pomiarem obowiązuje reguła 17: **poproś użytkownika o zwolnienie mocy hosta
i poczekaj na potwierdzenie.**

### 9. Wzorce i przebiegi

- Złota klatka dla okna nazwy, jeśli scenariusz powstanie;
- nowy przebieg w `tests/Functional`: **utworzenie katalogu, zmiana nazwy
  i usunięcie w prawdziwym katalogu tymczasowym**, wraz ze sprawdzeniem, że
  drugi panel patrzący na ten sam katalog widzi zmianę;
- przebieg odmowy: `Esc` w oknie nazwy i „nie” w oknie potwierdzenia **nie
  dotykają dysku**.

Testy operacji zapisu dotykają prawdziwego systemu plików w katalogu tymczasowym
i sprzątają po sobie — atrapa repozytorium sprawdziłaby tu wyłącznie samą siebie.

### 10. Dokumentacja

`docs/architecture.md` — nowa granica rdzenia zapisana wprost: co rdzeń o plikach
wie (ścieżka i czynność) i czego nadal nie wie (wpis, sortowanie, podgląd).
`SKILL.md` — reguła 15 przestaje być bezwyjątkowa; wyjątek ma być **nazwany
i ograniczony**, żeby następna funkcja nie wjechała do rdzenia tym samym
argumentem. `README.md` — nowe klawisze.

## Poza zakresem

- **Kopiowanie i przenoszenie** — praca dłuższa od klatki, krok 42.
- **Zaznaczenie wielokrotne** — krok 43; ten krok działa na wpisie pod kursorem.
- **Kosz i cofanie** — krok 44; tutaj usunięcie jest nieodwracalne i pyta o zgodę.
- **Prawa dostępu i właściciel** (`chmod`, `chown`) — osobna funkcja, wymaga
  własnego okna i wiedzy o uprawnieniach, której rdzeń nie ma.
- **Dowiązania symboliczne jako czynność** (tworzenie) — czytanie ich jest, ale
  tworzenie to trzecia operacja bez odbiorcy.
- **Śledzenie zmian katalogu z zewnątrz** (`inotify`) — lista odświeża się po
  **własnej** operacji; zmiana zrobiona spoza aplikacji nadal wymaga wejścia do
  katalogu na nowo.
- **Operacje na plikach zdalnych** — aplikacja zna jeden system plików.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/FileOperationsPort.php` | Application | Nowe — trzy czynności natychmiastowe na ścieżkach bezwzględnych. |
| `Infrastructure/FileSystem/FileOperationsService.php` | Infrastructure | Nowe — Singleton wykonujący czynności; sprawdzenia przed, nie po. |
| `Infrastructure/FileSystem/FileOperationException.php` | Infrastructure | Nowe — hierarchia `InfrastructureException`, przedstawia się przez `DescribesProblem`. |
| `Presentation/Ui/Overlay/PromptOverlay.php` | Presentation | Nowe — okno nazwy; wynik domknięciem (D56). Miejsce wedle rozstrzygnięcia 8. |
| `Module/Browser/Application/UseCase/RenameEntryUseCase.php` | Moduł | Nowe — zmiana nazwy wraz z odświeżeniem paneli. |
| `Module/Browser/Application/UseCase/CreateDirectoryUseCase.php` | Moduł | Nowe. |
| `Module/Browser/Application/UseCase/DeleteEntryUseCase.php` | Moduł | Nowe. |
| `Module/Browser/Application/UseCase/RefreshPanesUseCase.php` | Moduł | Nowe — odświeżenie każdego panelu patrzącego na zmieniony katalog. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | Klawisze operacji, otwieranie okien, obsługa skutku. |
| `Module/Browser/Presentation/BrowserModule.php` | Moduł | Wstrzyknięcie portu operacji do nowych przypadków użycia. |
| `Module/Browser/Application/BrowserSettings.php` | Moduł | Pozycja „pytaj przed usunięciem”. |
| `Module/Browser/lang/pl.php`, `lang/en.php` | Napisy | Czynności, pytania, niepowodzenia, opisy klawiszy. |
| `lang/pl.php`, `lang/en.php` | Napisy | Tytuł i przyciski okna nazwy — jeśli stanie w rdzeniu. |
| `tests/Functional/FileOperationsFlowTest.php` | Testy | Nowy przebieg na prawdziwym katalogu tymczasowym. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Nowa granica rdzenia; wyjątek od reguły 15 nazwany i ograniczony. |

## Do rozstrzygnięcia na starcie kroku

1. **Kształt portu** — jeden port z trzema metodami (rekomendacja) czy osobne
   porty; czy oddaje wynik, czy rzuca.
2. **Kto wie, co jest poprawną nazwą** — rdzeń dostaje własne pojęcie nazwy wpisu,
   czy sprawdza moduł przed wywołaniem portu (dziś wie o tym `Entry`).
3. **Klawisze** — `F5`–`F8` wzorem klasycznych menadżerów (rekomendacja) czy
   litery.
4. **Usunięcie katalogu niepustego** — odmowa czy usunięcie rekurencyjne;
   w drugim wariancie pytanie musi powiedzieć **ile** wpisów zniknie, a policzenie
   ich jest chodzeniem po drzewie w środku klatki (czyli tematem kroku 42).
5. **Drugie wejście przez komendy** (`browser.rename`, `browser.mkdir`) — czy
   operacje trafiają też do rejestru komend, czy zostają przy klawiszach.
6. **Droga odświeżenia** — ponowny odczyt katalogu czy zmiana wpisu w agregacie.
7. **Kursor po operacji** — idzie za nową nazwą, zostaje na numerze, czy wraca na
   początek listy; co po usunięciu ostatniego wpisu.
8. **Miejsce okna nazwy** — rdzeń (rekomendacja, bo kroki 42 i 44 też go chcą) czy
   moduł, wzorem `FilterOverlay`.
9. **Zachowanie przy nazwie zajętej** — odmowa z komunikatem czy pytanie
   o nadpisanie (drugie wchodzi w treść kroku 42 i lepiej mu ją zostawić).

## Kryteria ukończenia

- Zmiana nazwy, utworzenie katalogu i usunięcie działają z klawiszy przeglądarki,
  a lista pokazuje skutek w tej samej klatce.
- Drugi panel patrzący na ten sam katalog widzi zmianę — bez wchodzenia do
  katalogu na nowo.
- Odmowa (`Esc`, „nie”) nie dotyka dysku; dowodzi tego przebieg funkcjonalny.
- Każde niepowodzenie ma własne zdanie w obu językach i przechodzi przez
  `DescribesProblem` — `ProblemPresenter` nie rozpoznaje niczego po klasie.
- W `src/Application` i `src/Domain` nie ma ani jednej sygnatury z `Entry`,
  `Directory`, `DirectoryPath` ani `EntryType`.
- Nowa granica rdzenia zapisana w `docs/architecture.md` i w `SKILL.md` —
  z powodem, nie samą regułą.
- Testy operacji dotykają prawdziwego katalogu tymczasowego i sprzątają po sobie.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-14 — rozstrzygnięcia startowe i cały kod kroku

**Rozstrzygnięć wyszło trzynaście, a nie dziewięć** ([00-decyzje.md](00-decyzje.md),
D75). Cztery dołożyła odpowiedź na pytanie nr 4: „rekurencyjnie, z liczbą wpisów”
postawiło w tym kroku **pracę dłuższą od klatki**, którą plan rezerwował dla kroku
42 — a wraz z nią okno liczenia, okno postępu, takt pracy w pętli i pytanie, co
znaczy `Esc` w połowie usuwania.

**Jedno rozstrzygnięcie odwróciło zapis planu, bo plan zakładał rzecz
niewykonalną.** Punkt 5 („drugie wejście przez komendy”) miał dać dwie pozycje
w menu kontekstowym przez `AppliesToSelection`. Sprawdzenie pokazało granicę
warstw z D39: `CommandOutcome` leży w `Application` i wskazuje ekran
**identyfikatorem**, bo `ScreenInterface` leży w `Presentation` — a okna nakładane
rejestru identyfikatorów nie mają w ogóle. **Żadna komenda nie umie otworzyć
okna**, więc `browser.delete` (pytanie plus pasek postępu) i `browser.rename`
(pole na nazwę) nie mogły powstać w kształcie, jaki plan opisał. Powstały dwie
komendy z argumentem — **pierwsze w projekcie** — a `browser.delete` nie powstał
wcale, bo usuwać bez pytania nie wolno.

**Dług zapisany wprost:** zobowiązanie z indeksu planu („czynność kroku 41 ma
zadeklarować `AppliesToSelection`, a wtedy pojawi się w menu bez zmiany
w rdzeniu”) **nie jest spłacone**. Spłaci je krok, który da rdzeniowi okna pod
identyfikatorem — jeśli kiedyś taki będzie potrzebny. Menu `F9` nie zyskało ani
jednej pozycji.

**Co powstało w rdzeniu** (wyjątek od reguły 15, D66):

| Plik | Rola |
|---|---|
| `Application/Port/FileOperationsPort` | cztery czynności: nazwa, nowy katalog, usunięcie wpisu, usunięcie drzewa jako praca kawałkowa |
| `Application/Dto/RemovalState`, `RemovalStage` | stan pracy usuwania; **sześć etapów, w tym `Ready`** — przystanek na pytanie, którego nie ma żadna inna praca w projekcie |
| `Application/Dto/WorkProgress` | stan pracy **w języku okna**: wiersz treści, wykonane, całość |
| `Domain/Exception/FileOperationException` | niepowodzenie, które samo podaje zdanie (`DescribesProblem`) |
| `Infrastructure/FileSystem/FileOperationsService` | drugie i ostatnie miejsce rdzenia piszące po dysku |
| `Presentation/Ui/RunsWork` | zdolność okna: „prowadzę pracę”, pytana raz na takt |
| `Presentation/Ui/Overlay/PromptOverlay` | okno o jedno słowo |
| `Presentation/Ui/Overlay/ProgressOverlay` | okno pracy — pierwsze, które **działa samo** |

**Trzy zmiany w rdzeniu, których plan nie przewidywał, i każda z powodu:**

1. **`OverlayOutcome` zyskał `replace()`** — usuwanie prowadzi przez trzy okna po
   kolei, a stos ma jedno piętro, więc „zamknij i otwórz” musi stać się naraz.
2. **`ConfirmOverlay` oddaje `OverlayOutcome`, nie `?Message`** — pytanie stoi
   odtąd **w środku** łańcucha okien i musi umieć powiedzieć, co pokazać dalej.
   Dostał ponadto drugie, opcjonalne domknięcie: sprzątanie **po odmowie**, bo
   „nie” po policzonym drzewie musi porzucić listę wpisów.
3. **`InputHandler` łapie `DomainException` także w drodze przez okno nakładane** —
   i to jest **naprawa usterki starszej od tego kroku**: do dziś wyjątek rzucony
   z domknięcia okna nie miał nad sobą żadnego łapacza i kończyłby aplikację.
   Okno **zostaje przy tym otwarte**, co jest tu zaletą, nie kompromisem: po zdaniu
   „nazwa jest już zajęta” użytkownik ma dokładnie jedną rzecz do zrobienia.

**Rzecz wypatrzona przy pisaniu testów, warta zapamiętania:** liczenie **nie dzieli
się na kawałki wewnątrz jednego katalogu** — `scandir()` oddaje całą zawartość
naraz, więc tysiąc plików w jednym katalogu policzy się w jednym kawałku, choćby
budżet wynosił dziesięć. Kawałków przybywa dopiero wtedy, gdy przybywa
**katalogów do przejścia**. Nie jest to usterka (przerwanie w środku tablicy nie
oszczędziłoby ani jednej operacji systemowej), ale test okna liczenia musiał
z tego powodu budować drzewo **rozgałęzione**, a nie zatłoczone.

**Reguła wykonawcza, która wynikła z rozstrzygnięcia nr 10:** okno pracy otwiera
się **dopiero wtedy, gdy praca nie zmieściła się w pierwszym kawałku**. Dzięki
temu ścieżka kodu została jedna (zawsze praca kawałkowa), a okna nie migają tam,
gdzie nie mają czego pokazać — przy pliku, pustym katalogu i katalogu o trzech
wpisach.

**Kolejność usuwania stoi na jednym zdaniu:** pliki naprzód, katalogi w kolejności
**odwrotnej do odkrycia**. Rodzic stoi w liście przed dzieckiem, więc odwrócenie
stawia dziecko przed rodzicem — a katalog daje się usunąć wyłącznie pusty.
Sortowania po głębokości nie ma i nie było potrzebne.

**Co się zmieniło w module** (`Module/Browser/`): `EntryName` (nazwa wpisana przez
użytkownika — sprawdzenie zeszło tu z rdzenia, D75 nr 2) wraz
z `InvalidEntryNameException`; `EntryOperations` (trzy czynności, jedno miejsce dla
klawisza i komendy — wzorzec `HiddenEntries` z kroku 32); `RenameCommand`
i `MakeDirectoryCommand`; `BrowserState::refresh()` (odświeżenie **z zachowanym
filtrem** — w odróżnieniu od `enter()`); `BrowserTree::forgetBranches()`
i `BrowserPanes::forgetBranches()`; pozycja ustawień „Pytaj przed usunięciem”.

**`ToggleHiddenEntriesUseCase` zniknął, a na jego miejsce wszedł
`ReloadDirectoryUseCase`.** Nazwa mówiła prawdę, dopóki jedynym powodem ponownego
odczytu było przełączenie wpisów ukrytych; drugi powód (własna zmiana na dysku)
zrobiłby z tego dwie klasy robiące to samo.

**Kursor po operacji** (rozstrzygnięcie 7) liczy się **przed** czynnością, a nie po
niej: wpisu, który zniknął, nie ma już jak znaleźć. `EntryOperations` zapamiętuje
więc „następcę” — wpis stojący niżej, a przy usuwaniu ostatniego wyżej — i po
odświeżeniu zaznacza go **po nazwie**, tą samą regułą, którą filtr z kroku 30
przenosi zaznaczenie.

**Odświeżenie dotyczy panelu na zmienionym katalogu i panelu leżącego w jego
środku.** Drugi przypadek jest tym, o którym łatwo zapomnieć: usunięcie katalogu
wyciąga panelowi ziemię pod nogami, a wtedy ponowny odczyt się nie udaje i panel
wchodzi do najbliższego czytelnego wyżej — drogą, którą aplikacja otwiera katalog
startowy.

**Stan zastany zgadzał się z tabelą planu w każdym wierszu**, a sprawdzenie
dołożyło do niej jedną rzecz, która przesądziła o kształcie połowy kroku:
`InputHandler` łapał wyjątki wyłącznie w drodze przez ekran (zobacz zmianę nr 3
wyżej).

**Testy:** 1524 zielone (przed krokiem 1460, więc +64), PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag — `make qa` przechodzi. Nowe pliki testowe:
`tests/Infrastructure/FileSystem/FileOperationsServiceTest` (14 przypadków **na
prawdziwym katalogu tymczasowym** — atrapa systemu plików sprawdzałaby tu samą
siebie), `tests/Functional/FileOperationsFlowTest` (17 przypadków, cała droga przez
`InputHandler`, prawdziwe repozytorium i prawdziwy dysk),
`tests/Module/Browser/Domain/ValueObject/EntryNameTest`,
`tests/Application/Dto/RemovalStateTest`,
`tests/Presentation/Ui/Overlay/PromptOverlayTest` i `ProgressOverlayTest`.
`ScreenFixture` dostał **atrapę operacji** (`StubFileOperations`) jako domyślną
i to nie jest ostrożność teoretyczna: ścieżki katalogów trzymanych w pamięci
(`/home`, `/home/projekty`) bywają na maszynie testowej **prawdziwe**, a prawdziwa
usługa zapisu podstawiona w teście stopki mogłaby zajrzeć do cudzego katalogu
domowego.

**Pomiar:** krok **nie dokłada ani jednego prymitywu** i nie dotyka ścieżki
rysowania — operacje dzieją się w fazie „aktualizuj stan” pętli. Powody pominięcia
scenariuszy dla obu nowych okien oraz dla samych operacji zapisane
w [docs/pomiary/README.md](../pomiary/README.md).

### 2026-08-14 — pomiar i sprawdzenie w prawdziwym terminalu

**Pomiar (`make bench --compare`, host zwolniony na prośbę):** bez regresji.
Wszystkie osiemnaście scenariuszy wyszło 2–12% **szybciej** niż wzorzec kroku 40
przy identycznym obciążeniu maszyny (0,14 na rdzeń w obu przebiegach) — to rozrzut
środowiska, nie zasługa kroku, bo krok ścieżki rysowania nie dotyka. Wzorzec
zamykający: `docs/pomiary/2026-08-14-po-kroku-41.json`.

**Sprawdzenie klatki (`make run-xterm`, katalog pokazowy w scratchpadzie, wejście
komendą `browser.jump` — repozytorium nietknięte).** Obejrzane wszystkie trzy nowe
widoki i cały cykl:

- okno nazwy z polem i karetką, stopka z klawiszami okna;
- pytanie w wariancie groźnym z liczbą: „Usunąć „zdjęcia” wraz z zawartością? Do
  usunięcia: 6.” (pięć plików plus sam katalog — dokładnie ta konwencja, którą
  krok przyjął), ognisko na „Nie”;
- okno postępu na katalogu o **30 000 plikach**: nazwa usuwanego wpisu zmieniająca
  się co klatkę, licznik „3840 z 30001 13%” w środku paska, wypełnienie rosnące
  (13% → 39% → koniec), napis zmieniający rolę tam, gdzie przechodzi przez
  wypełnienie. Praca poszła ~8000 wpisów na sekundę, czyli tyle, ile zakłada
  budżet kawałka (256 × 30 kl./s), a aplikacja **ani razu nie stanęła**;
- po skończeniu: „Usunięto 30001 wpisów.” w pasku stanu, lista odświeżona, kursor
  na następcy usuniętego wpisu;
- utworzenie katalogu o nazwie **ze spacją**: „Katalog „nowy katalog” utworzony.”,
  kursor stanął na nowym wpisie.

**Jedna usterka, którą pokazał wyłącznie prawdziwy terminal, i jej naprawa:**
tytuł okna nazwy dyktował jego szerokość, więc „Nowy katalog w /tmp/claude-1000/…”
z pełną ścieżką **rozdmuchiwał okno na całą szerokość klatki** — okno pytające
o jedno słowo wyglądało jak drugi panel. Naprawione dwiema zmianami: `PromptOverlay`
dostał górną granicę szerokości (`MAX_COLUMNS = 64`; tytuł dłuższy ucina `Dialog`,
jak każdy inny), a tytuł „nowego katalogu” **stracił ścieżkę**, bo katalog panelu
czynnego stoi w górnym pasie klatki i powtarzać go nie ma po co. Regresję pilnuje
`PromptOverlayTest::testALongTitleDoesNotStretchTheWindow`.

Wniosek na przyszłość, ten sam co po kroku 28: **rozmiar okna liczony z długości
napisu trzeba zobaczyć, a nie wyliczyć** — `ConfirmOverlay` ma tę samą regułę i
przy bardzo długiej nazwie pliku zachowa się tak samo. Zostaje to zapisane tutaj,
a nie naprawione po drodze: krok 42 dołoży do pytań nazwy plików (kolizje przy
kopiowaniu) i to on będzie miał prawdziwego użytkownika dla tej poprawki.

**Bramka po naprawie:** `make qa` zielone — 1525 testów, 4133 asercje.
