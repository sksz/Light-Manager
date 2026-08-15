# Krok 44 — Kosz i cofnięcie ostatniej operacji

> **Skąd ten krok.** Powstał 2026-08-13 razem z całą Fazą XIV, na rozstrzygnięcie
> użytkownika: **usunięcie trwałe albo do kosza — zależnie od użytego skrótu
> i od ustawień modułu.** Stoi osobno od kroku 41, bo dokłada do usuwania dwie
> rzeczy, których tamten świadomie nie ma: **drugą drogę** i **drogę powrotną**.
> Pełne uzasadnienie fazy: [00-decyzje.md](../00-decyzje.md), D66.

## Status

**Ukończony** (2026-08-15).

Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md), D81 — wszystkie osiem
pytań z tego pliku i cztery wynikłe z odpowiedzi, spisane na `Opus 5`; kod
powstał po przełączeniu sesji na `Fable`, zgodnie z przypisem w indeksie.
Wszystkie kryteria ukończenia spełnione; szczegóły i odstępstwa — dziennik
realizacji na końcu pliku.

## Cel

Usunięcie przestaje być końcem. Wpis wędruje do kosza systemowego, skąd da się go
odzyskać — a ostatnia operacja daje się cofnąć bez opuszczania aplikacji.

Miarą powodzenia jest zdanie: **wpis usunięty domyślnym klawiszem znajduje się
w koszu środowiska graficznego, wraz z zapisaną ścieżką, z której zniknął —
a jedno naciśnięcie klawisza cofania przywraca go tam z powrotem.**

## Trudność strukturalna — najważniejsza treść tego pliku

**Rozstrzygnięcie „zależnie od skrótu” trafia w słownik wejścia, którego dziś nie
ma.** Aplikacja zna dwa modyfikatory i oba **wyłącznie przy literach**:

- `Ctrl`+litera od kroku 19 — i jest **zajęty w całości**: `Ctrl`+litera otwiera
  moduł, niezależnie od tego, co stoi na wierzchu;
- `Alt`+litera od kroku 29 (D58) — w torze terminalowym powstaje z `ESC` + znak
  drukowalny, więc z klawiszem funkcyjnym **nie da się go złożyć**;
- `Shift` **nie istnieje w żadnym torze**: `KeySequenceParser` ma napisane wprost
  „modyfikatory (`ESC [ 3 ; 5 ~` = `Ctrl`+`Delete`) nie zmieniają klawisza
  bazowego”, a `GlfwKeyMapper` czyta `GLFW_MOD_CONTROL` i `GLFW_MOD_ALT`, i tylko
  dla liter.

Z tego wynikają trzy drogi (pytanie 1), wszystkie z ceną:

1. **`Shift` wchodzi do słownika wejścia.** `KeyPress` dostaje trzecie pole, parser
   terminalowy przestaje odrzucać modyfikatory CSI, `GlfwKeyMapper` czyta
   `GLFW_MOD_SHIFT`, a wszystko to musi zgodzić się w trzech torach naraz. Koszt
   jak `Ctrl` w kroku 19 albo `Alt` w 29 — czyli **osobna praca wewnątrz tego
   kroku**, nie dopisek. Zysk sięga dalej niż ten krok: `Shift`+strzałki to
   zaznaczanie zakresem, którego krok 43 musiał odmówić.
2. **Dwa różne klawisze bazowe** — na przykład `F8` (wedle ustawienia) i `Delete`
   (zawsze trwale), albo odwrotnie. Zero zmian w wejściu; ceną jest skrót, którego
   nikt nie odgadnie bez zajrzenia do pomocy.
3. **Jeden klawisz i pytanie w oknie** — okno potwierdzenia z trzema odpowiedziami
   („do kosza / trwale / nie”). Zero zmian w wejściu i zero zgadywania, ale każde
   usunięcie kosztuje wtedy jedno pytanie więcej.

Rekomendacja: **wariant 1**, mimo najwyższego kosztu — bo jest jedynym, który
zostawia po sobie coś ponad ten krok, i bo dwa pozostałe rozwiązują problem
skrótu, a nie problem brakującego modyfikatora, który wróci przy zaznaczaniu
zakresem.

> **Rozstrzygnięte 2026-08-15: wariant 1** (D81, nr 1). Zaznaczanie zakresem
> (`Shift`+strzałki) wchodzi przy tym **do zakresu tego kroku** (D81, nr 12), bo
> po opłaceniu modyfikatora kosztuje już tylko obsługę w ekranie przeglądarki.

**Druga trudność: cofanie nie jest odwrotnością każdej operacji.** Zmianę nazwy da
się cofnąć zmianą nazwy, przeniesienie — przeniesieniem z powrotem, kosz —
przywróceniem z kosza. Usunięcia trwałego **nie da się cofnąć w ogóle**,
a cofnięciem kopiowania jest usunięcie kopii, czyli operacja, która sama w sobie
jest nieodwracalna. Spis tego, co odwracalne, musi być zapisany w kodzie jako
**jedyne źródło prawdy** — a nie w napisie, który przy pierwszej nowej operacji
skłamie.

## Zależności

- **Krok 41** twardo — usunięcie, port operacji i okno potwierdzenia, do których
  ten krok dokłada drugą drogę.
- **Krok 42** — przeniesienie do kosza leżącego na **innym systemie plików** jest
  kopiowaniem i usunięciem, czyli pracą kawałkową. Bez kroku 42 kosz działa
  wyłącznie tam, gdzie `rename()` wystarcza, i musi umieć odmówić.
- **Kroki 6, 19 i 29** — słownik wejścia i jego dwa dotychczasowe rozszerzenia;
  wariant 1 rozstrzygnięcia startowego dokłada trzecie.
- **Kroki 14 i 15** — ustawienie „co robi klawisz domyślny” i napisy.
- **Krok 38** — wzorce i przebiegi.

Z krokiem **43** łączy go zależność miękka: cofnięcie operacji na zbiorze
przywraca zbiór, więc jeśli 43 wykona się pierwszy, zapis operacji musi pamiętać
listę, a nie jeden wpis. Zalecana kolejność: **41 → 42 → 43 → 44**, czyli
numeryczna — ten krok jest jedynym w fazie, który zyskuje na tym, że wszystko
inne już stoi.

Od kroków **31, 32, 36, 37, 39 i 40** nie zależy i one nie zależą od niego.

## Model i wysiłek

**Fable / xhigh** — **rozstrzygnięte 2026-08-15** (D81): pytanie 1 wybrało
wariant z modyfikatorem, więc krok obejmuje zmianę w trzech torach wejścia
naraz, czyli dokładnie tę klasę ryzyka, którą niosły kroki 33–35. Zapowiadany
wariant tańszy (`Opus / high`) odpada.

## Stan zastany (sprawdzony w kodzie 2026-08-15)

| Element | Stan |
|---|---|
| `Application/Dto/KeyPress` | Dwa modyfikatory: `ctrl`, `alt`. **`shift` nie istnieje.** Potwierdzone. |
| `Infrastructure/Terminal/KeySequenceParser` | Modyfikatory CSI **świadomie odrzucane** (`firstParameter()`); `Alt` powstaje wyłącznie z `ESC` + znak drukowalny. Potwierdzone. |
| `Infrastructure/Glfw/GlfwKeyMapper` | Czyta `GLFW_MOD_CONTROL` i `GLFW_MOD_ALT` — i tylko dla liter. Potwierdzone. |
| `Presentation/Cli/InputHandler` | `Ctrl`+litera zajęte przez skróty modułów — **cały zakres**. Klawisze globalne: `F1`, `F2`, `F9` (menu), `F10`, `F11`, `F12`. |
| **Klawisze przeglądarki** | `F4` nazwa, `F5` kopiowanie, `F6` przeniesienie, `F7` nowy katalog, `F8`+`Delete` usunięcie — **plan mówił jeszcze o `F6`/`F7` z kroku 41**; krok 42 przesunął układ o dwa. Wolny został **`F3`**. |
| `Application/Port/FileOperationsPort` (po kroku 41) | Usunięcie trwałe; kosza nie zna. `beginRemoval()` bierze **listę ścieżek** (krok 43). |
| `Application/Port/FileTransferPort` (po kroku 42) | Praca kawałkowa; `begin(…, move: true)` **rozpoznaje inny system plików po numerze urządzenia** i sam kopiuje. |
| `Presentation/Ui/Overlay/ChoiceOverlay` (krok 42) | Okno listy odpowiedzi — **gotowe** dla pytania o trzech drogach; `ConfirmOverlay` zostaje przy dwóch. |
| `Application/Module/ModuleSetting` | `toggle()`, `choice()`, `number()`, `text()` — katalog kosza i głębokość stosu mają gotową postać pozycji. |
| `Module/Browser/Application/BrowserSettings` | Siedem pozycji; odczyty wskazują deklaracje **numerem**, więc nowe wchodzą **na koniec**. |
| `Module/Browser/Presentation/PaneRefresh` (krok 42) | Odświeżenie obu paneli po zmianie na dysku — droga gotowa dla cofnięcia. |
| `$XDG_DATA_HOME` / `~/.local/share/Trash` | Kosz środowiska graficznego; aplikacja nie zna dziś żadnej ze zmiennych XDG (`getenv('HOME')` czytają dwie usługi konfiguracji). |

## Zakres

### 1. Kosz wedle specyfikacji freedesktop.org

Nie własny katalog `.lm-trash`, tylko **ten sam kosz, który widzi środowisko
graficzne** — inaczej użytkownik ma dwa kosze i o jednym z nich nie wie.

- katalog **domyślny**: `$XDG_DATA_HOME/Trash`, a gdy zmiennej nie ma —
  `~/.local/share/Trash`;
- katalog **wolno przestawić** pozycją tekstową w zakładce modułu (D81, nr 3),
  ale **układ zostaje ten sam wszędzie**: kosz wskazany ręcznie dostaje `files/`,
  `info/` i pliki informacyjne dokładnie jak kosz środowiska;
- plik ląduje w `files/`, a obok, w `info/`, staje `nazwa.trashinfo`
  z sekcją `[Trash Info]`, wierszem `Path=` (ścieżka bezwzględna, zakodowana jak
  adres URL) i `DeletionDate=` w postaci `YYYY-MM-DDThh:mm:ss`;
- kolizja nazw rozwiązuje się **sufiksem liczbowym** (`raport.pdf`,
  `raport.1.pdf` — jak w koszu środowiska), **a plik informacyjny powstaje
  przed przeniesieniem** — plik w koszu bez wpisu informacyjnego jest plikiem,
  którego nie da się przywrócić;
- wpis wędruje do kosza **zmianą nazwy, nigdy kopiowaniem** (D81, nr 4): kosz ma
  być tani, a kopiowanie gigabajta w odpowiedzi na `Delete` tanie nie jest.

**Kosz na wolumenie zewnętrznym** (`.Trash-$uid`) zostaje **poza zakresem** —
aplikacja go nie zakłada. Wpis leżący poza systemem plików kosza dostaje za to
ostrzeżenie i pytanie o **trzech** odpowiedziach (D81, nr 5): skopiować do kosza
(praca kawałkowa `FileTransferPort::begin(…, move: true)` wraz z oknem postępu),
usunąć trwale, przerwać. Pytanie idzie przez `ChoiceOverlay` z kroku 42, więc
nowego okna krok nie dokłada.

### 2. Dwie drogi usunięcia i ustawienie modułu

`F8` i `Delete` robią to, co mówi ustawienie modułu (**domyślnie: do kosza** —
D81, nr 9), a `Shift`+`F8` i `Shift`+`Delete` **zawsze** to drugie, czyli
usunięcie trwałe (D81, nr 1 i 2). Obie drogi są przez to zawsze osiągalne,
a ustawienie wybiera wyłącznie, która jest tańsza w palcach.

**Usunięcie trwałe pyta zawsze**, niezależnie od ustawienia „pytaj przed
usunięciem” z kroku 41: tamto ustawienie dotyczy czynności odwracalnej, a ta nie
jest. Okno staje w wariancie `dangerous` i mówi wprost, że kosz tu nie pomoże.

### 3. Cofnięcie ostatniej operacji

Zapis wykonanych operacji w rdzeniu, obok portów z kroków 41 i 42. Odwracalne są:
zmiana nazwy, przeniesienie, usunięcie do kosza i utworzenie katalogu (o ile
pozostał pusty). Nieodwracalne: usunięcie trwałe i — z rozmysłem — **kopiowanie**,
bo jego cofnięciem byłoby usunięcie, czyli druga operacja nieodwracalna udająca
powrót.

**Stos wraz z widokiem — wbrew rekomendacji planu** (D81, nr 6): rekomendowano
jeden poziom właśnie dlatego, że stos wymaga własnego widoku („co właściwie
cofam?”), a użytkownik przyjął tę cenę i **widok jest częścią tego kroku**.
Cofać wolno **dowolną pozycję z listy**, nie tylko wierzchołek — a że stan dysku
mógł się od tamtej pory zmienić, każde cofnięcie sprawdza wykonalność tuż przed
wykonaniem. Głębokość stosu jest **pozycją w zakładce modułu** (D81, nr 7).

Widok pokazuje **także operacje nieodwracalne**, wyszarzone i niewybieralne
(D81, nr 8) — lista odpowiada przez to na dwa pytania naraz: „co mogę cofnąć”
i „co się właściwie wydarzyło”. Warunek „nieodwracalne nie udaje odwracalnego”
spełnia się rolą motywu i odmową, nie pominięciem w spisie.

Zapis **nie przeżywa zamknięcia aplikacji** — cofanie po restarcie byłoby
dziennikiem transakcji, a nie wygodą.

Klawisze (D81, nr 9): `Ctrl`+`z` **odpada**, bo `Ctrl`+litera należy w całości do
skrótów modułów (krok 20), a `F9` odpada, bo od kroku 32 otwiera menu. Zostaje
**`Alt`+`u` na cofnięcie** i **`F3` na widok stosu** — jedyny wolny klawisz
funkcyjny modułu.

### 4. Co widać po cofnięciu

Pasek stanu mówi, **co** zostało cofnięte, nie „cofnięto”. Panele odświeżają się
tą samą drogą, co po operacji (krok 41, punkt 4), a kursor staje na wpisie
przywróconym — bo to on jest odpowiedzią na pytanie „czy się udało”.

Cofnięcie, które się nie udało (plik zdążył zniknąć, prawa się zmieniły,
w katalogu docelowym stoi już coś o tej nazwie), mówi dlaczego i **nie zdejmuje
zapisu** — inaczej użytkownik traci jedyną informację o tym, co się stało.

### 5. Zaznaczanie zakresem (`Shift`+strzałki)

Wchodzi do zakresu **wraz z modyfikatorem** (D81, nr 12) i jest jedyną częścią
kroku, która z koszem ani cofaniem nie ma nic wspólnego. Powód jest rachunkowy:
`Shift` przechodzi tu przez trzy tory wejścia, a mechanizm zaznaczenia stoi
gotowy od kroku 43 — czynność kosztuje więc już tylko obsługę w ekranie
przeglądarki. Krok 43 musiał jej odmówić właśnie z braku modyfikatora.

`Shift`+strzałka przesuwa kursor i **zaznacza wpis, z którego wychodzi** — tak
samo, jak spacja z kroku 43, tyle że bez podnoszenia palca.

### 6. Napisy, pomiar, wzorce

- napisy: obie drogi usunięcia, ostrzeżenie przy trwałym, pytanie o wpis spoza
  systemu plików kosza, nazwy stanów cofnięcia, opisy pozycji w widoku stosu,
  dwa ustawienia modułu, opisy klawiszy;
- pomiar: **wejście** — parser przestaje odrzucać modyfikatory CSI, a to jest kod
  na ścieżce każdego klawisza (wariant 1 rozstrzygnięcia 1 zaszedł, więc
  zapowiedź obowiązuje). Kosz i cofanie wyglądu klatki nie zmieniają, ale
  **widok stosu jest oknem nakładanym z listą**, więc rozlicza się wzorcem
  `popup` — czy potrzebuje własnego scenariusza, rozstrzyga reguła z kroku 38:
  scenariusz przynosi ten, kto dowozi komponent, a widok stosu żadnego nie
  dokłada (jest `Dialog`iem z `ListView`, jak menu z kroku 32);
- przebiegi: usunięcie do kosza wraz ze sprawdzeniem pliku informacyjnego,
  przywrócenie, cofnięcie zmiany nazwy, cofnięcie przeniesienia, cofnięcie
  utworzenia katalogu wraz z odmową dla katalogu niepustego, odmowa cofnięcia
  usunięcia trwałego, cofnięcie pozycji ze środka stosu, wpis spoza systemu
  plików kosza (wszystkie trzy odpowiedzi), zaznaczanie zakresem.

Przebiegi kosza działają na **podstawionym katalogu kosza** (własna wartość
`XDG_DATA_HOME` albo ustawienia modułu w katalogu tymczasowym), a nie na koszu
użytkownika — test, który zaśmieca kosz osoby uruchamiającej testy, jest błędem,
nie niedogodnością.

### 7. Dokumentacja

`docs/architecture.md` — kosz jako droga usunięcia, jego konfigurowalne miejsce
i granica „`.Trash-$uid` nie powstaje”; spis operacji odwracalnych wraz
z powodem, dla których pozostałe nimi nie są. `SKILL.md` — słownik modyfikatorów
rośnie o `Shift` i musi to być zapisane obok `Ctrl` i `Alt`. `README.md` —
klawisze.

## Poza zakresem

- **Przeglądanie i opróżnianie kosza w aplikacji** — kosz jest katalogiem, więc
  `browser.jump` dowozi go za darmo; własny widok kosza to osobny moduł.
- **Kosz na wolumenach zewnętrznych** (`.Trash-$uid`) — punkt 1; wpis stamtąd ma
  drogę do kosza katalogu domowego, ale kosza na wolumenie aplikacja nie zakłada.
- **Cofanie przeżywające zamknięcie aplikacji** — to byłby dziennik transakcji.
- **Ponowienie cofniętej operacji** (`redo`) — stos jest do cofania, nie do
  powtarzania.
- **Automatyczne opróżnianie kosza po czasie** — należy do środowiska, nie do
  menadżera plików.
- **Cofnięcie kopiowania** — punkt 3, wraz z powodem.

Pozycja „stos cofnięć i jego widok” **wypadła z tej listy** 2026-08-15: D81
nr 6 wciągnął ją do zakresu kroku wbrew rekomendacji planu.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/TrashPort.php` | Application | Nowe — przeniesienie do kosza i przywrócenie z niego. |
| `Infrastructure/FileSystem/XdgTrashService.php` | Infrastructure | Nowe — kosz wedle freedesktop.org; plik informacyjny przed przeniesieniem; sufiks liczbowy przy kolizji; miejsce z ustawień. |
| `Application/UndoJournal.php` (albo `Application/Undo/`) | Application | Nowe — stos operacji wraz z drogą powrotną i spisem odwracalnych. |
| `Application/Dto/KeyPress.php` | Application | `shift` — trzecie pole modyfikatora. |
| `Infrastructure/Terminal/KeySequenceParser.php` | Infrastructure | Modyfikatory CSI przestają być odrzucane (`ESC [ 1 ; 2 P`). |
| `Infrastructure/Glfw/GlfwKeyMapper.php` | Infrastructure | `GLFW_MOD_SHIFT` — także dla klawiszy specjalnych i strzałek. |
| `Presentation/Ui/KeyBinding.php` | Presentation | `Shift` w opisie skrótu — trzecia postać obok `ctrl` i `alt`. |
| `Presentation/Ui/Overlay/UndoOverlay.php` | Presentation | Nowe — widok stosu: `Dialog` z `ListView`, pozycje nieodwracalne wyszarzone. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | Druga droga usunięcia, `Alt`+`u`, `F3`, `Shift`+strzałki, komunikaty. |
| `Module/Browser/Presentation/EntryOperations.php` | Moduł | Kosz jako droga usunięcia, pytanie przy wpisie spoza systemu plików kosza, zapis operacji. |
| `Module/Browser/Application/BrowserSettings.php` | Moduł | Trzy pozycje **na końcu**: „usuwaj do kosza”, katalog kosza (tekst), głębokość stosu (liczba). |
| `Module/Browser/lang/pl.php`, `lang/en.php`, `lang/*.php` | Napisy | Obie drogi, ostrzeżenie, pytanie o trzech drogach, stany cofnięcia, widok stosu. |
| `tests/Functional/TrashFlowTest.php`, `UndoFlowTest.php` | Testy | Kosz na podstawionym katalogu; cofanie wszystkich operacji odwracalnych, także ze środka stosu. |
| `tests/Unit/…` (parser, mapper, `KeyBinding`) | Testy | `Shift` w trzech torach — tor okienkowy przez `GlfwKeyMapper` bez okna. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Kosz, granice, spis operacji odwracalnych, trzeci modyfikator. |

## Rozstrzygnięcia startowe (2026-08-15)

Pełna treść wraz z odrzuconymi alternatywami:
[00-decyzje.md](../00-decyzje.md), D81. Skrót:

1. **`Shift` wchodzi do słownika wejścia** — wariant 1, zgodnie z rekomendacją.
   Krok idzie przez to modelem **Fable / xhigh**.
2. **Ustawienie przestawia znaczenie klawisza domyślnego**: `F8`/`Delete` wedle
   ustawienia, `Shift`+`F8`/`Shift`+`Delete` zawsze usunięcie trwałe.
3. **Kosz jest katalogiem konfigurowalnym**, domyślnie XDG; układ
   freedesktop.org obowiązuje **wszędzie**.
4. **Do kosza przenosi się zmianą nazwy, nigdy kopiowaniem** — dopóki `rename()`
   wystarcza.
5. **Wpis spoza systemu plików kosza**: ostrzeżenie i pytanie o trzech
   odpowiedziach (skopiuj do kosza / usuń trwale / przerwij), przez
   `ChoiceOverlay` i `FileTransferPort`.
6. **Stos cofnięć wraz z widokiem**, cofanie **dowolnej pozycji** — wbrew
   rekomendacji planu, która dawała jeden poziom.
7. **Głębokość stosu jest pozycją w zakładce modułu**; zapis nie przeżywa
   zamknięcia aplikacji.
8. **Widok pokazuje też operacje nieodwracalne** — wyszarzone, niewybieralne.
9. **`Alt`+`u` cofa, `F3` otwiera widok stosu** (`F9` odpada — od kroku 32 to
   menu).
10. **Utworzenie katalogu jest odwracalne, dopóki katalog został pusty**;
    inaczej odmowa bez zdejmowania zapisu.
11. **Kolizja nazw w koszu: sufiks liczbowy**, jak w koszu środowiska.
12. **Zaznaczanie zakresem `Shift`+strzałki wchodzi w tym kroku.**

Do tego jedno rozstrzygnięcie z pytań planu, które wypadło na „tak” bez
alternatywy: **kosz jest domyślną drogą usunięcia od pierwszego uruchomienia**
(D81, nr 9 w spisie pytań planu, nr 2 w treści decyzji).

## Kryteria ukończenia

- Wpis usunięty klawiszem domyślnym leży w koszu wraz z poprawnym plikiem
  `.trashinfo` — sprawdza to przebieg na podstawionym koszu.
- Kosz przestawiony w ustawieniach na inny katalog działa **tak samo**, wraz
  z `files/`, `info/` i przywracaniem.
- Usunięcie trwałe pyta **zawsze** i mówi wprost, że kosz tu nie pomoże.
- Wpis spoza systemu plików kosza dostaje pytanie o trzech odpowiedziach —
  **nigdy po cichu usunięty trwale i nigdy po cichu skopiowany**.
- Cofnięcie przywraca stan sprzed wybranej operacji odwracalnej, a kursor staje
  na przywróconym wpisie.
- Widok stosu pokazuje operacje nieodwracalne, ale ich **nie da się wybrać** —
  spis odwracalnych jest w kodzie, nie w napisie.
- Cofnięcie nieudane mówi dlaczego i nie zdejmuje zapisu.
- `Shift` działa we **wszystkich trzech torach** wejścia, a koszt na ścieżce
  klawisza zmierzony (`bin/render-bench`, oś wejścia).
- `Shift`+strzałki zaznacza zakresem w liście plików.
- Testy nie dotykają kosza osoby uruchamiającej je.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone (`make qa`).

## Dziennik realizacji

### 2026-08-15 — rozstrzygnięcia startowe i sprawdzenie stanu zastanego

Praca przed pierwszą linią kodu, wykonana na `Opus 5`:

1. **Stan zastany sprawdzony w kodzie**, nie odtworzony z planu — i trzy jego
   wiersze okazały się nieaktualne, bo plik kroku powstał 2026-08-13, przed
   krokami 42, 43 i 47. Klawisze przeglądarki przesunęły się o dwa (`F4`–`F8`),
   `F9` przeszło do klawiszy globalnych jako menu, a `ChoiceOverlay`,
   `ModuleSetting::text()`/`number()`, `PaneRefresh` i rozpoznanie innego systemu
   plików w `FileTransferPort` — czyli cztery rzeczy, których krok miał szukać —
   **już stoją**. Tabela „Stan zastany” przepisana.
2. **Dwanaście rozstrzygnięć** (osiem pytań planu, cztery wynikłe z odpowiedzi)
   zapisane w [00-decyzje.md](../00-decyzje.md) jako **D81** wraz z odrzuconymi
   alternatywami. Trzy z nich przesunęły zakres kroku: kosz stał się katalogiem
   konfigurowalnym, wpis spoza jego systemu plików dostał pytanie zamiast
   odmowy, a stos cofnięć — widok, którego plan świadomie nie obejmował.
3. **Model kroku rozstrzygnięty na `Fable` / `xhigh`** — warunek z przypisu
   w indeksie zaszedł, bo pytanie 1 wybrało wariant z modyfikatorem. Sesja
   przełącza się przed pierwszą linią kodu.

### 2026-08-15 — wykonanie (Fable / xhigh)

**Wejście — `Shift` w trzech torach naraz.** `KeyPress` dostał trzecie pole
i nazwany konstruktor `shifted()`; `KeySequenceParser` przestał odrzucać
modyfikatory CSI w całości — bit `Shift`a czyta z drugiego parametru
(`ESC [ 3 ; 2 ~` = `Shift`+`Delete`), a `Ctrl`/`Alt` przy nazwach pozostają
odrzucane, bo nie mają ani jednego użytkownika; `GlfwKeyMapper` czyta
`GLFW_MOD_SHIFT` dla klawiszy nazwanych. Reguła słownika: **`shift` istnieje
wyłącznie przy nazwach** — litera z `Shift`em przychodzi z obu torów jako inna
litera, więc znacznik przy znaku nie miałby czego nieść. `KeyBinding` dostał
postać `shifted()` i porównuje znacznik przy nazwach tą samą regułą, którą
litera porównuje `Ctrl` i `Alt`: goły `F8` nie łapie `Shift`+`F8`. W ekranie
`Shift` rozstrzyga się **przed** gałęziami klawiszy (`BrowserScreen::shifted()`).

**Kosz.** `TrashPort` (rdzeń, 15b — pisze po dysku) i `XdgTrashService`:
układ freedesktop.org z plikiem informacyjnym **przed** przeniesieniem (tryb
`x` jako rezerwacja nazwy bez zamków), sufiks liczbowy przed rozszerzeniem,
ścieżka powrotna kodowana jak adres URL, katalog kosza podawany w każdym
wywołaniu (jego wybór to pozycja modułu). `EntryTrash` jest punktem wejścia obu
dróg: `F8`/`Delete` wedle ustawienia (domyślnie kosz), `Shift`+klawisz zawsze
to drugie; trwałe pyta zawsze, a `askBeforeDelete` rządzi odtąd koszem. Wpis
z innego systemu plików dostaje pytanie o trzech odpowiedziach przez
`ChoiceOverlay`; droga „skopiuj do kosza” idzie pracą kawałkową kroku 42 pod
nazwami zarezerwowanymi z góry — co wymagało **jedynego rozszerzenia kontraktu
rdzenia w tym kroku**: `FileTransferPort::begin()` przyjmuje opcjonalną mapę
nazw docelowych, bo kolizja katalogów jest w tej pracy scaleniem i wpis
o zajętej nazwie wtopiłby się w cudzy.

**Cofanie.** Stos (`UndoJournal`, `UndoEntry`, `UndoKind`) stanął **w module,
wbrew literze planu** — operacje zmaterializowały się w całości po stronie
przeglądarki, więc reguła 15 wygrała z zapisem „w rdzeniu” (rachunek D70).
Spis odwracalnych mieszka w `UndoEntry::reversible()`. `Alt`+`u` cofa najnowsze
odwracalne; `F3` otwiera `UndoOverlay` (w module, wzorzec `FilterOverlay`) —
`Dialog` z `ListView`, pozycje nieodwracalne wyszarzone rolą `Muted`
i przeskakiwane kursorem. Wykonawca (`EntryUndo`): zmiana nazwy i pusty katalog
wracają natychmiast, kosz przywróceniem (częściowe niepowodzenie **wymienia
zapis na pomniejszony**), przeniesienie — `EntryTransfer::beginRestore()`, czyli
tą samą pracą kawałkową z oknami w drugą stronę. Zapis kopiowania
i przeniesienia pada wyłącznie po pracy ukończonej w całości; trwałe zapisuje
się też po przerwaniu, bo jako historia niczego nie obiecuje.

**Zaznaczanie zakresem** weszło zgodnie z D81 nr 12: `Shift`+strzałki to krok
zaznaczania (`markStep()`), którego spacja jest szczególnym przypadkiem —
przełącznik na wpisie, z którego wychodzi, jak w Far.

**Ustawienia:** trzy pozycje na końcu zakładki (`deleteToTrash`,
`trashDirectory` — pusta znaczy „kosz środowiska”, `undoDepth` z listy
przystanków). **Napisy:** komplet pl/en. **Przebiegi:** `TrashFlowTest`
(9, w tym trzy odpowiedzi pytania EXDEV na atrapach), `UndoFlowTest` (9, w tym
cofnięcie ze środka stosu i odmowa bez zdjęcia zapisu), `XdgTrashServiceTest`
(14, na podstawionym katalogu — kosz użytkownika nietknięty), `UndoJournalTest`
(6), zakres w `MarkedEntriesFlowTest`. Przebiegi trwałe przeszły na
`Shift`+`F8`; „bez pytania — od razu” jest odtąd przebiegiem kosza.

**Pomiar** (maszyna zwolniona, obciążenie 0,14/rdzeń): `--loop` wobec wzorca po
kroku 43 — **−1,4%**, szum; jedyna zmiana kroku na ścieżce klatki (parser) jest
poniżej rozdzielczości taktu. Pełne porównanie sixelowe bez regresji, wzorce
PNG zgodne co do piksela (krok nie zmienia wyglądu klatki). Wzorce
`po-kroku-44` zapisane dla czterech torów; spis pominięć w
`docs/pomiary/README.md` urósł o trzy pozycje z powodami.

**Dokumentacja:** `docs/architecture.md` (podrozdział „Kosz i cofnięcie”,
tabela granicy wyjątku 15b, trzeci modyfikator w §5), `SKILL.md` (11j — trzy
modyfikatory; 15b — dziewięć czynności; nowa 15d), `README.md` (klawisze
i rozdział o usuwaniu). Bramka `make qa` zielona: 1656 testów.

**Odstępstwa od planu — trzy, wszystkie zapisane w D81 z powodami:** stos
w module zamiast w rdzeniu (reguła 15), `UndoOverlay` w module zamiast
w `Presentation/Ui/Overlay` (wzorzec `FilterOverlay`), rozszerzenie
`FileTransferPort::begin()` o mapę nazw (jedyna droga do kosza przez granicę
wolumenu bez scalenia z cudzym wpisem).
