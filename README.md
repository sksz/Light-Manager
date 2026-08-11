# Light Manager

Menadżer plików w terminalu napisany w PHP. Cała klatka ekranu jest budowana
jako jeden obraz przez Imagick i wypychana do terminala protokołem Sixel, w
architekturze pętli głównej znanej z gier.

## Wymagania

- PHP `^8.3` (weryfikowane: 8.3.11)
- Rozszerzenia PHP: `imagick`, `pcntl`
- Zewnętrzne polecenie `stty` — stąd założenie **Linux/macOS**; Windows nie
  jest wspierany
- Interaktywny terminal na standardowym wejściu — uruchomienie z potoku lub
  przekierowania z pliku kończy się czytelnym błędem
- ImageMagick z wkompilowanym koderem `SIXEL` — bez niego aplikacja startuje,
  ale zejdzie do trybu tekstowego (fallback, krok 07 planu)
- Terminal obsługujący Sixel (np. XTerm z `-ti vt340`, WezTerm, foot, mlterm) —
  wykrywanie odbywa się w runtime. **gnome-terminal odpada**: VTE nie ma
  Sixela od wersji 0.75.90 (zobacz „Znane ograniczenia”)
- Composer 2.x

## Instalacja

```bash
composer install
```

## Uruchomienie

```bash
./bin/light-manager
```

lub równoważnie:

```bash
php bin/light-manager
```

lub z XTerm:
```bash
xterm -ti vt340 -fa 'DejaVu Sans Mono' -fs 11 -geometry 100x30 \
  -xrm 'XTerm*maxGraphicSize: 4000x4000' \
  -xrm 'XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop' \
  -e bash -c 'cd /home/sksz/Projects/light_manager && ./bin/light-manager'
```

lub poprzez alias:
```bash
./bin/run.sh
```

Aplikacja przechodzi na osobny ekran, rysuje klatkę w stałym takcie i czeka na
wejście. Wyjście: klawisz `F10` albo Ctrl+C — w obu przypadkach terminal wraca
do stanu sprzed uruchomienia.

### Sterowanie

| Klawisz | Działanie |
|---|---|
| `↑` / `↓` | zmiana zaznaczenia |
| `Enter` / `→` | wejście do katalogu (na pliku `Enter` nie robi nic) |
| `Backspace` / `←` | katalog wyżej |
| `.` | pokaż lub ukryj wpisy ukryte (ustawienie trwałe, dotyczy obu paneli) |
| `Tab` | przejście do drugiego panelu — tylko przy włączonym podziale |
| `F1` | ekran pomocy — pełna lista klawiszy |
| `F2` | ekran ustawień |
| `F12` | okno komend |
| `Ctrl`+litera | okno modułu — `Ctrl+B` przeglądarka plików, `Ctrl+D` opis zaznaczonego pliku |
| `Esc` | powrót do modułu domyślnego z każdego ekranu; zamknięcie okna komend |
| `F10` | wyjście (działa na każdym ekranie) |

`Enter` jest w całej aplikacji klawiszem **zatwierdzania**: na katalogu wchodzi
do środka, w polu tekstowym zatwierdza wartość, w oknie komend uruchamia
komendę. Na pliku nie ma czego zatwierdzić — opis pliku przeprowadził się do
modułu `file-info` i ma własny skrót `Ctrl+D`.

Wyjście wisi na klawiszu funkcyjnym, a nie na literze, i przestało to być
decyzją na przyszłość: aplikacja **ma** pole tekstowe (okno komend), a klawisz
kończący pracę nie może być znakiem, który użytkownik właśnie wpisuje. Skutek uboczny jest wart tyle, co
sama zmiana — **żadna litera nie jest zarezerwowana**, więc cały alfabet zostaje
wolny dla komend i skrótów modułów.

Pasek stanu u dołu wypisuje tylko klawisze rdzenia (`F1 pomoc · F2 ustawienia ·
F12 okno komend · F10 wyjście`) — pełna ściągawka mieszka na ekranie pomocy. Nie jest tam
przepisana ręcznie: powstaje z tych samych wiązań, które klawisze obsługują.

Przeglądarkę można podzielić na **dwa panele** — dwa katalogi, dwa kursory,
niezależne od siebie. Włącza się to w ustawieniach modułu („Podział na dwa
panele”), a `Tab` przenosi ognisko z jednego panelu do drugiego. Panel czynny
poznaje się po akcencie w obwódce i po ścieżce, którą pokazuje górny pas klatki;
katalog panelu nieczynnego widać w etykiecie jego ramki. Panele stają domyślnie
obok siebie — pozycja „Panele obok siebie” przestawia je jeden nad drugi.

Podział **nie powstaje w oknie węższym niż 72 kolumny** (a przy układzie
poziomym — niższym niż 14 wierszy w strefie listy). Poniżej progu widać jeden
panel i klatka wygląda dokładnie tak, jak przed włączeniem. To ta sama zasada,
którą kieruje się pas podglądu: dwa panele w wąskim oknie **mieszczą się**
arytmetycznie, ale nazw plików nie da się w nich przeczytać.

Podział jest sprawą modułu, a nie okna: `F1`, `F2` i skrót modułu zastępują cały
ekran razem z podziałem, bo widoczny ekran jest zawsze jeden.

Zaznaczenie pliku graficznego pokazuje jego miniaturę w pasie u dołu klatki,
wraz z wymiarami i formatem. Pas pojawia się w oknie wysokim na co najmniej
16 wierszy. Pliki uszkodzone, większe niż 32 MB albo o rozdzielczości powyżej
50 Mpx dostają zamiast miniatury ramkę z powodem. W trybie tekstowym zostaje
sam wiersz podpisu — fallback z definicji nie pokazuje bitmap.

### Okno komend

`F12` otwiera nad bieżącym ekranem pas z polem wpisywania, a nad nim listę
podpowiedzi. Wzorzec pochodzi z wiersza poleceń `vima`: czynność wywołuje się
**po nazwie**, zamiast szukać dla niej wolnego klawisza.

| Klawisz | Działanie |
|---|---|
| znaki | wpisywanie nazwy; lista filtruje się w locie |
| `Tab` | uzupełnia najdłuższy wspólny przedrostek pasujących nazw |
| `↑` / `↓` | wybór pozycji z listy |
| `Enter` | uruchamia wpisany wiersz albo wskazaną pozycję |
| `←` / `→`, `Home`, `End`, `Delete`, `Backspace` | poprawianie wiersza |
| `Esc` albo `F12` | zamknięcie okna |

Przy pustym polu lista pokazuje **najpierw historię**, a pod nią komplet komend
— dzięki temu powtórzenie ostatniego wywołania nie wymaga osobnego klawisza,
a ten, kto nazw nie zna, widzi je wszystkie od razu.

Komendy nazywają się z przestrzenią właściciela: rdzeń wnosi `core.*`, a każdy
moduł — wyłącznie `<id modułu>.*`. Przedrostka pilnuje rejestr, więc kolizja
między modułami jest niemożliwa z konstrukcji. Dziś dostępne są:

| Komenda | Argument | Działanie |
|---|---|---|
| `core.help` | — | otwiera ekran pomocy |
| `core.settings` | — | otwiera ekran ustawień |
| `core.theme` | nazwa motywu | ustawia motyw graficzny |
| `core.language` | kod języka | ustawia język interfejsu |
| `core.quit` | — | kończy pracę |
| `browser.jump` | ścieżka | przechodzi do wskazanego katalogu |

`browser.jump` podpowiada katalogi **z dysku**, w miarę wpisywania: to
pierwsza w projekcie komenda z podpowiedziami liczonymi na żądanie, a nie
policzonymi przy starcie. Ścieżka względna liczy się od bieżącego miejsca.

Argumenty rozdziela spacja, a wartość ze spacją bierze się w cudzysłów
(`core.theme "moj motyw"`). Brak wymaganego argumentu, nadmiarowa wartość
i nieznana nazwa **zostawiają okno otwarte** wraz z wpisanym wierszem — powód
staje w pasku stanu, więc literówki nie trzeba przepisywać od nowa.

Historia trzyma dwadzieścia ostatnich wierszy wraz z argumentami i przeżywa
ponowne uruchomienie: leży w `~/.light-manager/history`, osobno od konfiguracji,
bo jest śladem pracy, a nie ustawieniem.

### Ustawienia

`F2` otwiera ekran ustawień w miejscu listy plików. Pasek stanu u dołu zostaje,
a w górnym pasie ekran ustawień stawia **położenie pliku konfiguracyjnego** —
jedynej rzeczy, której nie da się z niego wyczytać.

Zakładek jest tyle, ile ich wnosi ta wersja: dwie rdzeniowe (**Wygląd**,
**Grafika**), spis **Moduły** i po jednej na każdy moduł, który wnosi własne
ustawienia. Pierwszym wierszem spisu **Moduły** jest **moduł otwierany przy
starcie** — jego wartości to identyfikatory modułów, które naprawdę wnoszą okno,
a lista powstaje przy starcie, nie w kodzie. Kursor zaczyna na pasku zakładek: `←` / `→` przełączają wtedy
zakładkę, a `↓` wchodzi w pozycje. Na pozycji `←` / `→` zmieniają wartość,
`↑` / `↓` chodzą po liście, `Esc` wraca do plików.

Pozycja tekstowa (np. argumenty polecenia w module `file-info`) zachowuje się
inaczej: `Enter` wchodzi w nią i zatwierdza wpisaną wartość, `Esc` porzuca
zmianę. Wartość niezgodna z wymaganiami pozycji **nie nadpisuje poprzedniej** —
powód staje w pasku stanu.

Pod pozycjami zakładek rdzenia stoi przycisk **Przywróć ustawienia domyślne** —
`Enter` na nim cofa wszystkie ustawienia naraz, więc po zabłądzeniu w motywach
i palecie nie trzeba kasować pliku konfiguracyjnego.

| Zakładka | Pozycja | Wartości | Domyślnie |
|---|---|---|---|
| Wygląd | Język | Automatyczny, Polski, English | Automatyczny |
| Wygląd | Motyw | Grafit, Nordyk, Papier, Indygo | Grafit |
| Grafika | Wygładzanie tekstu | tak / nie | nie |
| Grafika | Wygładzanie obrysów | tak / nie | tak |
| Grafika | Kolory palety Sixela | 16, 32, 64, 128 | 64 |
| Moduły | Moduł otwierany przy starcie | identyfikatory modułów z oknem | `browser` |
| Moduły | *(moduł)* | włączony / wyłączony | włączony |
| Przeglądarka plików | Pokazuj wpisy ukryte | tak / nie | nie |
| Przeglądarka plików | Podział na dwa panele | tak / nie | nie |
| Przeglądarka plików | Panele obok siebie | tak / nie | tak |
| Przeglądarka plików | Kolumny szczegółów (data, prawa) | tak / nie | **tak** |
| Przeglądarka plików | Nazwy kolumn nad listą | tak / nie | nie |
| Opis pliku | Limit czasu polecenia (s) | 1, 2, 5, 10 | 2 |
| Opis pliku | Dodatkowe argumenty | tekst | *(puste)* |
| Opis pliku | Zapis czasu | absolute, relative | absolute |
| Opis pliku | Pokazuj i-węzeł i dowiązania | tak / nie | nie |
| Opis pliku | Suma kontrolna sha256 | tak / nie | **nie** |
| Opis pliku | Limit rozmiaru sumy (MiB) | 16, 64, 256, 1024 | 256 |
| Opis pliku | Zajętość katalogu na dysku (du) | tak / nie | **nie** |
| Opis pliku | Limit czasu pracy w tle (s) | 5, 15, 30, 60 | 15 |

Każda zmiana działa natychmiast — motyw i jakość rysowania widać w następnej
klatce, bez restartu — i od razu ląduje w pliku, więc przeżywa nawet zabicie
procesu sygnałem. Dwa wyjątki, o których ekran mówi wprost: przełącznik modułu
i moduł otwierany przy starcie działają **po ponownym uruchomieniu**, bo mapa
skrótów, lista ekranów i lista zakładek powstają raz.

**Paleta poniżej 64 kolorów**: kwantyzator poświęca wtedy odcień obwódki na
rzecz liczniejszych pikseli tekstu i panele znikają z ekranu, zostawiając same
nawiasy narożne. Ustawienie jest dostępne, ale aplikacja o tym ostrzega.

**Wartości domyślne wygładzania i palety są tymczasowe.** Pochodzą z doraźnych
pomiarów kroku 13, robionych przez podmienianie stałych w kodzie. Krok 16 planu
daje powtarzalne narzędzie pomiarowe i dopiero po nim zostaną potwierdzone albo
poprawione.

### Moduły

Funkcję dopisuje się do aplikacji **modułem**, nie zmianą w rdzeniu. Moduł ma
pięć punktów zaczepienia i deklaruje tylko te, których naprawdę potrzebuje:

1. własne okno wraz ze skrótem `Ctrl`+litera. Okno to **trzy strefy klatki**:
   górny pas, środkowy panel i pas podglądu — moduł zamawia te, które ma czym
   wypełnić, a rdzeń rysuje ich oprawę i pasek stanu,
2. własną zakładkę w oknie konfiguracji, opisaną danymi — rdzeń ją rysuje,
   prowadzi po niej kursor i zapisuje wartości,
3. własną zakładkę w oknie pomocy: część automatyczną rdzeń składa z deklaracji
   (skrót, klawisze okna, pozycje ustawień), a moduł dokłada własne wiersze,
4. własne napisy w `src/Module/<Nazwa>/lang/`, scalane z katalogiem rdzenia,
5. własne komendy w oknie komend.

Dopisanie modułu ze wszystkimi pięcioma punktami kosztuje **jedną zmianę
w rdzeniu**: dopisanie klasy do listy w `Presentation\Cli\Bootstrap`.

Wbudowane są dziś dwa:

- **Przeglądarka plików** (`browser`, `Ctrl+B`) — sam menadżer plików. Nie jest
  rdzeniem z doklejonymi modułami, tylko modułem jak każdy inny: cała domena
  katalogu, nawigacja, ekran i komenda `browser.jump` leżą w `src/Module/Browser/`,
  a w rdzeniu nie została ani jedna klasa wiedząca, czym jest plik.

  Lista ma **cztery kolumny**: nazwa, rozmiar, data zmiany i prawa dostępu.
  W wąskim oknie — na przykład w panelu podziału — kolumny **ustępują po kolei**:
  najpierw prawa, potem data, potem rozmiar, a nazwa nie ustępuje nigdy. Kolumna,
  która nie mieści się w całości, **znika w całości**: przycięta data (`2026-08-…`)
  nie mówi nic, a zabiera znaki nazwie, która by je wykorzystała.
- **Opis pliku** (`file-info`, `Ctrl+D`) — **pełny obraz stanu zaznaczonego
  wpisu**, także katalogu: cztery zwijane sekcje po lewej i miniatura po prawej.

  | Sekcja | Co pokazuje |
  |---|---|
  | Tożsamość | nazwa, rodzaj z `lstat`, opis od polecenia `file`, cel dowiązania wraz z informacją, czy istnieje, liczba wpisów katalogu |
  | Rozmiar | rozmiar w jednostkach i co do bajta, bloki i-węzła, zajętość katalogu na dysku (`du`), suma kontrolna `sha256` |
  | Uprawnienia | prawa `rwx` i ósemkowo, właściciel, grupa, opcjonalnie i-węzeł i liczba dowiązań |
  | Czasy | zmiana treści, zmiana i-węzła, odczyt — datą albo jako „ile temu” |

  Sekcje zwija się `Enter`em, a `↑`/`↓` chodzą po ich nagłówkach. **Suma kontrolna
  liczy się dopiero po naciśnięciu `s`** i domyślnie jest wyłączona: czyta cały
  plik, więc nie ma prawa startować sama przy przewijaniu listy. Liczy się po
  kawałku na klatkę, pokazuje prawdziwy postęp paskiem i przerywa się natychmiast,
  gdy zaznaczenie się zmieni. Powyżej ustawionego limitu rozmiaru nie startuje
  i mówi dlaczego.

  **Zajętość katalogu na dysku liczy się po naciśnięciu `d`** i też domyślnie jest
  wyłączona. Stoi za nią polecenie `du` uruchomione w tle i doglądane między
  klatkami — pętla w tym czasie nie czeka ani chwili. Wiersz powstaje **tylko dla
  katalogu**: dla zwykłego pliku tę samą liczbę podają stojące obok bloki i-węzła,
  odczytane z `lstat` bez uruchamiania czegokolwiek. Postępu `du` nie zna, więc
  pasek nie udaje, że go zna — jego wypełnienie wędruje tam i z powrotem. Praca
  ma własny limit czasu, osobny i hojniejszy od limitu polecenia `file`, bo
  sekundy spędzone w tle nie kosztują ani jednej klatki. Po zamknięciu aplikacji
  — także `Ctrl+C` — nie zostaje po niej ani jeden proces.

#### Moduł domyślny

Aplikacja startuje z oknem modułu wskazanego kluczem `startupModule`; domyślnie
jest to przeglądarka. Wskazanie innego uruchamia aplikację z jego oknem jako dnem
— tym, do którego wraca `Esc`.

**Przeglądarka jest modułem ostatniej szansy.** Nie da się jej wyłączyć ani
odrzucić (przy kolizji skrótu odpada ten drugi moduł), a aplikacja wraca do niej
w czterech przypadkach: moduł domyślny jest wyłączony, został odrzucony przy
starcie, nie ma go na liście albo nie wnosi okna. Za każdym razem powód widać
w pasku stanu — bo każdy z nich prowadzi do innej poprawki.

Zasady, które obowiązują moduły:

- **Identyfikator** pasuje do `[a-z][a-z0-9-]*` i jest jeden dla wszystkiego:
  klucz w pliku konfiguracyjnym (`modules.<id>`), przedrostek napisów
  (`module.<id>.`) i przestrzeń nazw komend (`<id>.`).
- **Skrót to `Ctrl` plus litera.** Sześć liter jest zajętych przez terminal:
  `c` i `z` są sygnałami, a `h`, `i`, `j` i `m` przychodzą tym samym bajtem, co
  Backspace, Tab i Enter. Zostaje dwadzieścia.
- **Moduł z zabronioną literą, ze skrótem zajętym przez inny moduł albo
  z powtórzonym identyfikatorem nie zostaje załadowany** — w całości, nie tylko
  jego skrót. Aplikacja startuje, a powód widać w pasku stanu i na zakładce
  „Moduły”. Kolizję łapie test, zanim zobaczy ją użytkownik.
- **Moduły się nie znają.** Moduł dostaje od rdzenia kontekst sesji (ścieżka,
  nazwa zaznaczenia, jego rodzaj) i nic ponadto; do infrastruktury rdzenia sięga
  wyłącznie przez port.
- **Moduł da się wyłączyć** na zakładce „Moduły”. Zmiana zapisuje się od razu,
  ale działa po ponownym uruchomieniu — mapa skrótów i lista zakładek powstają
  raz, przy starcie.

Moduły ładowane z zewnątrz (spoza repozytorium) są **poza zakresem**: kontrakt
ma dojrzeć na modułach wbudowanych, zanim stanie się API dla obcego kodu.

### Język interfejsu

Aplikacja mówi po polsku albo po angielsku. Domyślne ustawienie **Automatyczny**
bierze język ze środowiska — sprawdza `LC_ALL`, `LC_MESSAGES` i `LANG`, w tej
kolejności, i przyjmuje pierwszą wartość z rozpoznawalnym kodem (`pl_PL.UTF-8`
i `pl` znaczą to samo). Gdy żadna nic nie mówi, zostaje angielski.

Wybór zapisany w ustawieniach jest mocniejszy od środowiska i działa
natychmiast, bez restartu. Napisy leżą w `lang/pl.php` i `lang/en.php` —
dopisanie kolejnego języka to nowy plik obok nich i nowa pozycja w
`Application\Dto\Language`.

Komunikaty samych wyjątków są techniczne i zawsze po angielsku: pisze się je dla
osoby czytającej ślad stosu. To, co widzi użytkownik — także przy nieudanym
starcie — przechodzi przez katalog napisów.

### Plik konfiguracyjny

`~/.light-manager/settings.json`. Katalog i plik powstają dopiero przy pierwszej
zmianie ustawienia — sam start aplikacji niczego nie tworzy na dysku.

```json
{
    "language": "auto",
    "theme": "grafit",
    "startupModule": "browser",
    "textAntialias": false,
    "strokeAntialias": true,
    "paletteColors": 64,
    "modules": {
        "browser": { "enabled": true, "showHidden": false },
        "file-info": { "enabled": true, "timeout": 2, "arguments": "",
                       "timeFormat": "absolute", "inode": false,
                       "checksum": false, "checksumLimit": 256 }
    }
}
```

Podobiekt `modules` dopisuje się dopiero wtedy, gdy któreś ustawienie modułu
zostanie ruszone. **Ustawienia modułu nieznanego zostają nietknięte** — moduł
wyłączony albo usunięty z listy odzyska swoją konfigurację, gdy wróci.

Ręczna edycja jest możliwa, ale plik jest czytany raz, przy starcie. Zasady
odczytu:

- **Brak pliku** — wartości domyślne, bez słowa. To normalny stan pierwszego
  uruchomienia.
- **Plik nieczytelny albo niepoprawny JSON** — wartości domyślne i ostrzeżenie w
  pasku stanu. Aplikacja startuje i **nie nadpisuje pliku, którego nie
  zrozumiała**; nadpisze go dopiero jawna zmiana ustawienia.
- **Nieznany klucz** — pomijany po cichu (plik z nowszej wersji nie ma prawa
  straszyć).
- **Znany klucz z wartością spoza zakresu** — wartość domyślna dla tego klucza,
  reszta pliku zostaje, plus ostrzeżenie z nazwą pozycji.
- **`startupModule` bez pokrycia w rejestrze** — aplikacja startuje
  z przeglądarką i mówi w pasku stanu, dlaczego. Zakresu tego klucza nie da się
  sprawdzić przy odczycie: znają go dopiero moduły przyjęte w tym uruchomieniu.
- **`showHiddenEntries` z pliku sprzed wersji 0.21** — przepisywany raz do
  `modules.browser.showHidden`, żeby ustawienie przeżyło aktualizację. Ze starego
  miejsca znika przy najbliższym zapisie.

Zapis idzie przez plik tymczasowy i `rename()` w tym samym katalogu, więc
przerwany zapis zostawia poprzednią, poprawną wersję zamiast obciętego JSON-a.

### Zasoby XTerma wymagane w trybie graficznym

Trzy zasoby, każdy z innego powodu — bez nich klatka jest ucięta, pusta albo
przewinięta:

| Zasób | Domyślnie | Dlaczego trzeba zmienić |
|---|---|---|
| `decTerminalID: 340` (albo `-ti vt340`) | `420` | bez tego XTerm nie zgłasza Sixela w odpowiedzi DA1 i aplikacja schodzi do trybu tekstowego |
| `maxGraphicSize: 4000x4000` | `1000x1000` | klatka większa niż limit **nie rysuje się w ogóle**; okno 200×50 już go przekracza |
| `disallowedWindowOps` bez `14` | lista z `14` | XTerm blokuje raport rozmiaru okna (`ESC [ 14 t`), więc aplikacja musi zgadywać rozmiar komórki znakowej |

Ostatni wpis jest celowo węższy niż `allowWindowOps: true`: dopuszcza wyłącznie
raport rozmiaru, a zmiana rozmiaru i pozycji okna oraz raportowanie tytułu
pozostają zablokowane.

Na stałe w `~/.Xresources` (potem `xrdb -merge ~/.Xresources`):

```
XTerm*decTerminalID: 340
XTerm*maxGraphicSize: 4000x4000
XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop
```

Bez ostatniego zasobu aplikacja nadal działa — przyjmuje wtedy komórkę 6×13 px
(domyślny font XTerma) i rysuje klatkę mniejszą niż okno, zostawiając margines
przy prawej i dolnej krawędzi.

## Struktura

```
bin/         skrypty wejściowe CLI (aplikacja i narzędzia diagnostyczne)
src/         kod aplikacji (PSR-4, namespace LightManager\)
src/Module/  moduły — każdy z własnymi warstwami i własnymi napisami
             (Browser — menadżer plików, FileInfo — opis zaznaczonego pliku)
tests/       testy PHPUnit (namespace LightManager\Tests\)
lang/        katalogi napisów interfejsu (rdzeń)
docs/        architektura, plany wdrożenia i wzorce pomiarów
```

Podział `src/` na warstwy (`Domain`, `Application`, `Infrastructure`,
`Presentation`) wraz z regułą zależności opisuje
[docs/architecture.md](docs/architecture.md).

## Narzędzia deweloperskie

Podgląd wejścia terminala — sprawdza tryb surowy i rozpoznawanie klawiszy bez
czekania na pętlę główną:

```bash
./bin/terminal-probe
```

Na starcie pokazuje wykryty tryb renderowania (Sixel albo fallback tekstowy),
a potem wypisuje nazwę klawisza i jego bajty, po jednym wierszu na zdarzenie
(sekwencja escape liczy się jako jedno zdarzenie). Wyjście: `F10` albo Ctrl+C —
w obu przypadkach terminal wraca do stanu sprzed uruchomienia.

```bash
composer test        # PHPUnit
composer stan        # PHPStan (poziom max)
composer cs:check    # PHP-CS-Fixer — podgląd zmian, bez zapisu
composer cs          # PHP-CS-Fixer — zapis poprawek
```

### Pomiar wydajności renderowania

`bin/render-bench` mierzy potok renderowania klatki bez uruchamiania aplikacji
i bez edytowania kodu:

```bash
./bin/render-bench                       # wszystkie scenariusze, konfiguracja domyślna
./bin/render-bench --help                # pełna lista opcji i scenariuszy
./bin/render-bench --palette=16 --text-aa # inna konfiguracja, bez ruszania kodu
```

Klatka rozbita jest na trzy fazy — **rysowanie**, **kwantyzację** i **kodowanie
do Sixela** — mierzone osobno, a każdy scenariusz izoluje inny element klatki
(sam tekst, same ramki, zaznaczenie, suwak, miniatura, okienko). Dzięki temu
koszt elementu da się *odjąć*, zamiast zgadywać z sumy.

Dwa scenariusze wyłamują się z tej reguły i robią to celowo. **`background`
rysuje klatkę co do prymitywu równą `chrome-text`, ale przy uruchomionym procesie
potomnym**, doglądanym raz na klatkę tak samo, jak dogląda go aplikacja. Odjęcie
jednego od drugiego daje więc nie koszt elementu interfejsu, lecz cenę pracy
toczącej się obok pętli — a twierdzenie, że praca tłowa nie kosztuje klatki, jest
dzięki temu sprawdzalne, a nie deklarowane. **`columns`** rysuje z kolei tę samą
listę, co `chrome-text`, ale w czterech kolumnach zamiast dwóch — różnica jest
ceną rozdziału szerokości i dwóch dodatkowych napisów w każdym wierszu.

#### Jak czytać wynik

```
Scenariusz            Rysowanie  Kwantyzacja  Kodowanie     Razem        Rozrzut     Blob
ramki z tekstem     314,7 (77%)   87,5 (21%)   7,7 (2%)  410,8 ms    409,0–475,5  23,1 kB
```

- **mediana**, nie średnia — pojedynczy przebieg zakłócony przez inny proces
  przesuwa średnią i zostaje niewidoczny;
- **procent w nawiasie** to udział fazy w klatce;
- **rozrzut** (min–max) mówi, czy medianie wolno wierzyć; wiersz z „!”
  przekroczył 1,35× i jest oznaczony jako niewiarygodny;
- **Blob** to bajty, które trzeba jeszcze wypchnąć na terminal — konfiguracja
  szybsza w liczeniu, ale dwukrotnie grubsza w zapisie, nie jest szybsza wcale.

Pierwsze przebiegi (domyślnie trzy) są rozgrzewką i nie wchodzą do wyniku:
pierwsza klatka płaci za wybór fontu i pomiar szerokości napisów.

#### Wzorce i porównanie

```bash
./bin/render-bench --save                # zapisz wzorzec do docs/pomiary/
./bin/render-bench --compare             # porównaj z najnowszym wzorcem
```

Przebieg z niestabilnym pomiarem **nie zostanie zapisany** jako wzorzec.
Ograniczenia porównania (przede wszystkim: to samo obciążenie maszyny) opisuje
[docs/pomiary/README.md](docs/pomiary/README.md).

#### Przesył do terminala

Jedyna faza, której nie da się zmierzyć bez prawdziwego terminala — narzędzie
nigdy nie podstawia w jej miejsce zapisu do pliku, bo zmierzyłoby wtedy prędkość
jądra, a nie terminala. Bez terminala mówi wprost, że tej fazy nie zmierzyło.

```bash
./bin/run-render-bench.sh --transfer     # XTerm z zasobami wymaganymi dla Sixela
```

Raportuje rozmiar klatki, czas zapisu, liczbę wywołań `fwrite()` (jeden zapis
rozpada się na kilka), przepustowość oraz **przybliżony** czas do odpowiedzi
terminala na zapytanie DA1 wysłane zaraz po klatce. Ta ostatnia liczba jest
oszacowaniem dolnym: terminal może odpowiedzieć, zanim domaluje obraz.

#### Zrzut klatki do PNG

Liczby nie pokazują wszystkiego — przy 16 i 32 kolorach kwantyzator zjada
obwódki paneli, co jest niewidoczne w czasie ani w rozmiarze bloba:

```bash
./bin/render-bench --png=/tmp/klatka.png --scenario=chrome-text
```

Zrzut powstaje **przed** kwantyzacją, więc pokazuje, co narysował enkoder.
Skutki samej palety ogląda się na terminalu, gdzie naprawdę występują.

## Dokumentacja

- [docs/architecture.md](docs/architecture.md) — warstwy DDD, wzorzec
  Singleton, standardy PHP (dokument źródłowy)
- [docs/plans/00-index.md](docs/plans/00-index.md) — plan wdrożenia i status
  poszczególnych kroków
- [docs/plans/00-decyzje.md](docs/plans/00-decyzje.md) — dziennik decyzji
  architektonicznych

## Znane ograniczenia

Tryb renderowania jest wykrywany raz, przy starcie: aplikacja pyta terminal
o możliwości (Primary Device Attributes) i czeka na odpowiedź do 300 ms.
Multipleksery (tmux, screen) potrafią tę odpowiedź odfiltrować — aplikacja
zejdzie wtedy do trybu tekstowego mimo terminala obsługującego Sixel.

**gnome-terminal nie nadaje się do trybu graficznego** i nie da się tego
naprawić konfiguracją. VTE usunęło obsługę Sixela z gałęzi stabilnej w wersji
0.75.90 (commit `e264c6e`, 2024-02-10, „SIXEL support is not in a releasable
state”); w 0.76 zostały same zaślepki ABI — `vte_terminal_get_enable_sixel()`
zwraca zaszyte `false`, a setter nic nie zapisuje. Klucz `enable-sixel`
w profilu gnome-terminala jest wobec tego bezczynny.

Ostatni wiersz okna zostaje w trybie graficznym pusty. To rezerwa: obraz
sięgający ostatniego wiersza wypycha ekran o wiersz w górę, bo terminal stawia
kursor pod obrazem.

Terminal jest przywracany do stanu sprzed uruchomienia na trzech ścieżkach:
przez obsługę sygnałów (SIGINT, SIGTERM, SIGHUP, SIGQUIT), przez funkcję
zamknięcia procesu (również przy niezłapanym wyjątku) i przez jawne
`restore()`. Jedynym wyjątkiem jest **SIGKILL** (`kill -9`), którego nie da
się przechwycić — po nim terminal zostaje w trybie surowym i trzeba go
naprawić poleceniem `stty sane`.

## Znane ograniczenie środowiska

Composer potrafi zakończyć się naruszeniem ochrony pamięci (SIGSEGV) przy
równoległym pobieraniu wielu paczek, gdy załadowane są rozszerzenia `imagick`
i `openswoole`. Obejście — uruchomienie Composera z ich pominięciem:

```bash
PHP_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick \
  composer update --ignore-platform-req=ext-imagick
```

Dotyczy wyłącznie samego Composera; uruchomienie aplikacji wymaga `imagick`
włączonego normalnie.
