# Krok 41 — Fundament operacji: nazwa, nowy katalog, usunięcie

> **Skąd ten krok.** Powstał 2026-08-13 na żądanie użytkownika. Zamyka pierwszą
> pozycję „Zakresu poza MVP”, która stoi w planie od jego pierwszej wersji:
> *operacje na plikach (kopiuj / przenieś / usuń / zmień nazwę / nowy katalog)*.
> Wstęp do niej dowiozły dwa kroki — **24** (dwa panele, czyli źródło i cel)
> i **28** (okno potwierdzenia, bez którego usuwanie nie miało prawa powstać).

## Status

**Nie rozpoczęty** (2026-08-13).

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

*(pusty — krok nierozpoczęty)*
