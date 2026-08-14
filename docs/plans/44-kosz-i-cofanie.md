# Krok 44 — Kosz i cofnięcie ostatniej operacji

> **Skąd ten krok.** Powstał 2026-08-13 razem z całą Fazą XIV, na rozstrzygnięcie
> użytkownika: **usunięcie trwałe albo do kosza — zależnie od użytego skrótu
> i od ustawień modułu.** Stoi osobno od kroku 41, bo dokłada do usuwania dwie
> rzeczy, których tamten świadomie nie ma: **drugą drogę** i **drogę powrotną**.
> Pełne uzasadnienie fazy: [00-decyzje.md](00-decyzje.md), D66.

## Status

**Nie rozpoczęty** (2026-08-13).

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

**Opus / high** — a przy wyborze wariantu 1 rozstrzygnięcia startowego
**Fable / xhigh**, bo wtedy krok obejmuje zmianę w trzech torach wejścia naraz,
czyli dokładnie tę klasę ryzyka, którą niosły kroki 33–35.

Decyzję o modelu podejmuje się **po** rozstrzygnięciu pytania 1, nie przed nim.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Dto/KeyPress` | Dwa modyfikatory: `ctrl`, `alt`. **`shift` nie istnieje.** |
| `Infrastructure/Terminal/KeySequenceParser` | Modyfikatory CSI **świadomie odrzucane**; `Alt` powstaje wyłącznie z `ESC` + znak drukowalny. |
| `Infrastructure/Glfw/GlfwKeyMapper` | Czyta `GLFW_MOD_CONTROL` i `GLFW_MOD_ALT` — i tylko dla liter. |
| `Presentation/Cli/InputHandler::toModule()` | `Ctrl`+litera zajęte przez skróty modułów — **cały zakres**. |
| `Application/Port/FileOperationsPort` (po kroku 41) | Usunięcie trwałe; kosza nie zna. |
| `Application/Port/FileTransferPort` (po kroku 42) | Praca kawałkowa — droga do kosza na innym systemie plików. |
| `Presentation/Ui/Overlay/ConfirmOverlay` | Dwie odpowiedzi; trzecia wymagałaby zmiany (albo okna z kroku 42). |
| `Module/Browser/Application/BrowserSettings` | Pozycje modułu — miejsce na „co robi klawisz domyślny”. |
| `$XDG_DATA_HOME` / `~/.local/share/Trash` | Kosz środowiska graficznego; aplikacja nie zna dziś żadnej ze zmiennych XDG. |

## Zakres

### 1. Kosz wedle specyfikacji freedesktop.org

Nie własny katalog `.lm-trash`, tylko **ten sam kosz, który widzi środowisko
graficzne** — inaczej użytkownik ma dwa kosze i o jednym z nich nie wie.

- katalog: `$XDG_DATA_HOME/Trash`, a gdy zmiennej nie ma — `~/.local/share/Trash`;
- plik ląduje w `Trash/files/`, a obok, w `Trash/info/`, staje `nazwa.trashinfo`
  z sekcją `[Trash Info]`, wierszem `Path=` (ścieżka bezwzględna, zakodowana jak
  adres URL) i `DeletionDate=` w postaci `YYYY-MM-DDThh:mm:ss`;
- kolizja nazw w koszu rozwiązuje się sufiksem, **a plik informacyjny powstaje
  przed przeniesieniem** — plik w koszu bez wpisu informacyjnego jest plikiem,
  którego nie da się przywrócić.

**Kosz na innym systemie plików** (`.Trash-$uid` na wolumenie) zostaje **poza
zakresem**. Wpis leżący poza systemem plików katalogu domowego kończy się wtedy
albo odmową z komunikatem, albo pytaniem o usunięcie trwałe — pytanie 3.

### 2. Dwie drogi usunięcia i ustawienie modułu

Klawisz domyślny robi to, co mówi ustawienie modułu (**domyślnie: do kosza**),
a drugi skrót robi to drugie — wedle rozstrzygnięcia z sekcji o trudności
strukturalnej.

**Usunięcie trwałe pyta zawsze**, niezależnie od ustawienia „pytaj przed
usunięciem” z kroku 41: tamto ustawienie dotyczy czynności odwracalnej, a ta nie
jest. Okno staje w wariancie `dangerous` i mówi wprost, że kosz tu nie pomoże.

### 3. Cofnięcie ostatniej operacji

Zapis wykonanych operacji w rdzeniu, obok portów z kroków 41 i 42. Odwracalne są:
zmiana nazwy, przeniesienie, usunięcie do kosza i utworzenie katalogu (o ile
pozostał pusty). Nieodwracalne: usunięcie trwałe i — z rozmysłem — **kopiowanie**,
bo jego cofnięciem byłoby usunięcie, czyli druga operacja nieodwracalna udająca
powrót.

Głębokość zapisu jest pytaniem 4; rekomendacja: **jeden poziom**, bo stos
cofnięć wymaga własnego widoku („co właściwie cofam?”), a widok to osobna funkcja.
Zapis **nie przeżywa zamknięcia aplikacji** — cofanie po restarcie byłoby dziennikiem
transakcji, a nie wygodą.

Klawisz cofania: `Ctrl`+`z` **odpada** — `Ctrl`+litera należy w całości do skrótów
modułów (krok 20). Kandydaci: `F9`, albo `Alt`+`z`, jeśli `Alt`+litera nie zderza
się ze skrótem modułu (pytanie 5).

### 4. Co widać po cofnięciu

Pasek stanu mówi, **co** zostało cofnięte, nie „cofnięto”. Panele odświeżają się
tą samą drogą, co po operacji (krok 41, punkt 4), a kursor staje na wpisie
przywróconym — bo to on jest odpowiedzią na pytanie „czy się udało”.

Cofnięcie, które się nie udało (plik zdążył zniknąć, prawa się zmieniły,
w katalogu docelowym stoi już coś o tej nazwie), mówi dlaczego i **nie zdejmuje
zapisu** — inaczej użytkownik traci jedyną informację o tym, co się stało.

### 5. Napisy, pomiar, wzorce

- napisy: obie drogi usunięcia, ostrzeżenie przy trwałym, nazwy stanów cofnięcia,
  ustawienie modułu, opisy klawiszy;
- pomiar: **żadnego nowego scenariusza** — kosz i cofanie nie zmieniają wyglądu
  klatki poza pytaniem, które rysuje się wzorcem `popup`. Jeśli wybrany zostanie
  wariant 1 rozstrzygnięcia 1, pomiaru wymaga natomiast **wejście**: parser
  przestaje odrzucać modyfikatory CSI, a to jest kod na ścieżce każdego klawisza;
- przebiegi: usunięcie do kosza wraz ze sprawdzeniem pliku informacyjnego,
  przywrócenie, cofnięcie zmiany nazwy, cofnięcie przeniesienia, odmowa cofnięcia
  usunięcia trwałego, kosz na innym systemie plików.

Przebiegi kosza działają na **podstawionym katalogu kosza** (własna wartość
`XDG_DATA_HOME` w katalogu tymczasowym), a nie na koszu użytkownika — test, który
zaśmieca kosz osoby uruchamiającej testy, jest błędem, nie niedogodnością.

### 6. Dokumentacja

`docs/architecture.md` — kosz jako droga usunięcia i granica „tylko kosz katalogu
domowego”; spis operacji odwracalnych wraz z powodem, dla których pozostałe nimi
nie są. `SKILL.md` — jeśli `Shift` wejdzie do wejścia, słownik modyfikatorów
rośnie i musi to być zapisane obok `Ctrl` i `Alt`. `README.md` — klawisze.

## Poza zakresem

- **Przeglądanie i opróżnianie kosza w aplikacji** — kosz jest katalogiem, więc
  `browser.jump` dowozi go za darmo; własny widok kosza to osobny moduł.
- **Kosz na wolumenach zewnętrznych** (`.Trash-$uid`) — punkt 1.
- **Stos cofnięć głębszy niż rozstrzygnięcie z pytania 4** i jego widok.
- **Cofanie przeżywające zamknięcie aplikacji** — to byłby dziennik transakcji.
- **Ponowienie cofniętej operacji** (`redo`) — bez stosu nie ma czego ponawiać.
- **Automatyczne opróżnianie kosza po czasie** — należy do środowiska, nie do
  menadżera plików.
- **Cofnięcie kopiowania** — punkt 3, wraz z powodem.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/TrashPort.php` | Application | Nowe — przeniesienie do kosza i przywrócenie z niego. |
| `Infrastructure/FileSystem/XdgTrashService.php` | Infrastructure | Nowe — kosz wedle freedesktop.org; plik informacyjny przed przeniesieniem. |
| `Application/Command/…` albo `Application/UndoJournal.php` | Application | Nowe — zapis ostatniej operacji wraz z drogą powrotną. |
| `Application/Dto/KeyPress.php` | Application | `shift` — **wyłącznie** przy wariancie 1 rozstrzygnięcia 1. |
| `Infrastructure/Terminal/KeySequenceParser.php` | Infrastructure | Modyfikatory CSI przestają być odrzucane — jw. |
| `Infrastructure/Glfw/GlfwKeyMapper.php` | Infrastructure | `GLFW_MOD_SHIFT` — jw. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | Druga droga usunięcia, klawisz cofania, komunikaty. |
| `Module/Browser/Application/BrowserSettings.php` | Moduł | „Usuwaj do kosza” jako pozycja zakładki modułu. |
| `Module/Browser/lang/pl.php`, `lang/en.php`, `lang/*.php` | Napisy | Obie drogi, ostrzeżenie, stany cofnięcia. |
| `tests/Functional/TrashFlowTest.php`, `UndoFlowTest.php` | Testy | Kosz na podstawionym `XDG_DATA_HOME`; cofanie wszystkich operacji odwracalnych. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Kosz, granice, spis operacji odwracalnych, ewentualny trzeci modyfikator. |

## Do rozstrzygnięcia na starcie kroku

1. **Jak skrót rozróżnia obie drogi** — `Shift` wchodzi do słownika wejścia
   (rekomendacja, najdroższe, zostawia po sobie zaznaczanie zakresem), dwa różne
   klawisze bazowe, czy trzecia odpowiedź w oknie potwierdzenia. **Od tej
   odpowiedzi zależy model i wysiłek kroku.**
2. **Który klawisz jest domyślny** i co robi wedle ustawienia — czy ustawienie
   przestawia znaczenie klawisza domyślnego, czy wyłącza drugą drogę zupełnie.
3. **Wpis poza systemem plików katalogu domowego** — odmowa z komunikatem, pytanie
   o usunięcie trwałe, czy kopiowanie do kosza pracą kawałkową z kroku 42.
4. **Głębokość cofania** — jeden poziom (rekomendacja) czy stos; jeśli stos, to
   czy z widokiem.
5. **Klawisz cofania** — `F9`, `Alt`+litera, czy inny; `Ctrl`+litera jest zajęty
   w całości.
6. **Cofnięcie utworzenia katalogu** — czy w ogóle (katalog mógł już nie być pusty),
   i co wtedy.
7. **Nazwa w koszu przy kolizji** — sufiks liczbowy, znacznik czasu, czy pytanie.
8. **Czy kosz jest domyślny od pierwszego uruchomienia** — rekomendacja: tak, bo
   krok 41 zostawił usuwanie nieodwracalne, a to jest właśnie ta zmiana.

## Kryteria ukończenia

- Wpis usunięty domyślnym klawiszem leży w koszu środowiska graficznego wraz
  z poprawnym plikiem `.trashinfo` — sprawdza to przebieg na podstawionym koszu.
- Usunięcie trwałe pyta **zawsze** i mówi wprost, że kosz tu nie pomoże.
- Cofnięcie przywraca stan sprzed ostatniej operacji odwracalnej, a kursor staje
  na przywróconym wpisie.
- Operacja nieodwracalna **nie udaje**, że da się ją cofnąć — spis odwracalnych
  jest w kodzie, nie w napisie.
- Cofnięcie nieudane mówi dlaczego i nie zdejmuje zapisu.
- Wpis spoza systemu plików katalogu domowego obsłużony wedle rozstrzygnięcia 3 —
  nigdy po cichu usunięty trwale.
- Przy wariancie 1 rozstrzygnięcia 1: `Shift` działa we **wszystkich trzech
  torach** wejścia, a koszt na ścieżce klawisza zmierzony.
- Testy nie dotykają kosza osoby uruchamiającej je.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
