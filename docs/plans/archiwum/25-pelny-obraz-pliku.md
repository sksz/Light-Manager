# Krok 25 — Pełny obraz stanu pliku w module `FileInfo`

> **Skąd ten krok.** Powstał przy realizacji kroku 21 (2026-08-10) na życzenie
> użytkownika: *„File info powinien pozwolić na pobranie pełnych informacji
> o pliku, jak np. przez polecenia `file` czy `du`, tworząc kompletny obraz stanu
> pliku”.* Krok 21 był przenosinami co do znaku, więc rozbudowa dostała własny
> numer, żeby nie mieszać nowej funkcji z refaktorem głównej.
>
> **Numer 22 → 25** (2026-08-10). Rozstrzygnięcia startowe wprowadziły trzy nowe
> komponenty rdzenia, których moduł jest odbiorcą — a odbiorca nie może wyprzedzić
> tego, co odbiera. Kroki 22, 23 i 24 to te komponenty; rozbudowa `FileInfo` idzie
> za nimi. Przenumerowanie jest zgodne z zasadą `00-index.md`, że kolejność
> realizacji pokrywa się z numeracją (precedens: D9, D35).

## Status

**Ukończony z zastrzeżeniem** (2026-08-10). PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag, **916 testów** (2202 asercje) zielone, klatka zmierzona
i rozliczona „przed i po”, wzorzec zapisany.

**Zastrzeżenie dotyczy jednego z czterech źródeł: `du` nie wszedł.** Zajętość na
dysku wymaga procesu potomnego doglądanego między klatkami, a użytkownik
rozstrzygnął na starcie kroku, że **mechanizm procesu tłowego dostaje własny
krok planu (26)**. Wiersza „zajęte na dysku” nie ma więc wcale — świadomie,
zamiast pokazywać go z wartością, której nie ma jak policzyć. Wraz z `du`
przesunęły się dwie pozycje ustawień (`du`, `backgroundTimeout`). Wszystko
pozostałe z P1–P9 zostało dowiezione.

## Cel

Zamienić `FileInfo` z „jednego zdania od polecenia `file`” w **kompletny obraz
stanu pliku**: czym jest, ile zajmuje naprawdę, do kogo należy, co wolno z nim
zrobić i kiedy był ruszany ostatni raz.

Miarą powodzenia jest zdanie, którego dziś nie da się powiedzieć: **żeby
dowiedzieć się o pliku wszystkiego, co mówi o nim system, nie trzeba wychodzić
z menadżera do powłoki.**

## Ustalenia (decyzje użytkownika, 2026-08-10)

| Pytanie | Wybór |
|---|---|
| **P1** — źródła informacji | **Wszystkie cztery**: `stat`, dowiązanie symboliczne, `du`, `sha256` — obok istniejącego `file` |
| **P2** — `du` a pętla główna | **Praca w tle, prywatna sprawa modułu** — kontrakt modułu z kroku 20 zostaje nietknięty |
| **P3** — katalogi | **Tak** — moduł opisuje odtąd także katalogi |
| **P4** — kształt ekranu | **Zwijane sekcje**, komponentem rdzenia (krok 22) |
| **P5** — wolne źródło przed policzeniem | **Wiersz od razu, wartość „liczę…”** — układ nie skacze |
| **P6** — `sha256` | **W tle jak `du`, za przełącznikiem, z konfigurowalnym limitem rozmiaru** |
| **P7** — ustawienia modułu | **Wszystkie cztery**: włączenie `du`, format czasu, i-węzeł i dowiązania, osobny limit czasu źródeł tłowych |
| **P8** — postęp pracy tłowej | **Pasek postępu z tekstem**, komponentem rdzenia (krok 23) |
| **P9** — `Bootstrap` a wnętrze modułu | **Moduł składa się sam i leniwie**, jak `BrowserModule` po kroku 21 |

## Zależności

- **Krok 22** (zwijana sekcja) — twardo: obraz stanu pliku dzieli się na sekcje,
  a sekcja jest komponentem rdzenia (P4).
- **Krok 23** (pasek postępu) — twardo: `du` i `sha256` mówią o sobie paskiem
  (P8), a nie własnym napisem.
- **Krok 24** (podział ekranu) — z niego pochodzi komponent `Split`. Czy moduł
  z niego skorzysta, przesądza rozstrzygnięcie nr 6.
- **Krok 21** (przeglądarka jako moduł) — formalnie: `file-info.jump` przeniosła
  się stamtąd do przeglądarki, więc dopiero po nim wiadomo, co modułowi zostaje
  (dziś: `ProvidesCommands` z pustą listą, D42). Istotnie: kontrakt modułu
  przeszedł tam sprawdzian na głównej funkcji aplikacji. Stamtąd też pochodzi
  **niespłacony dług**: `Bootstrap` wciąż wiąże wnętrze `FileInfo` — ten krok go
  spłaca (P9).
- **Krok 20** (moduły) — stamtąd pochodzi zakładka ustawień modułu, w której
  lądują cztery nowe pozycje (P7).

## Model i wysiłek

**Opus / high.**

Krok nie zmienia ani jednego kontraktu — cała robota mieści się w jednym module —
ale dokłada **procesy potomne działające w tle** i pracę na wolnym zasobie (`du`
na dużym drzewie potrafi trwać sekundy, `sha256` na dużym pliku dłużej), a to
jest ta klasa zmian, w której łatwo zawiesić pętlę główną na trzydzieści klatek.
Praca tłowa jest przy tym **pierwszą w projekcie**, więc nie ma się na czym
wzorować.

## Stan zastany (sprawdzony w kodzie 2026-08-10, po kroku 21)

| Element | Stan | Los w tym kroku |
|---|---|---|
| `Application/Port/FileInspectorPort` | Jedna metoda `describe(path, timeout, arguments): string` — jedno zdanie od `file` | Rozrasta się albo dostaje rodzeństwo (rozstrzygnięcie nr 1) |
| `Infrastructure/FileInspectorService` | `proc_open` + sondowanie co 10 ms + `proc_terminate` po limicie, **synchronicznie**; Singleton | Zostaje dla `file`; praca tłowa to osobne narzędzie |
| `Application/UseCase/InspectSelectedEntryUseCase` | Odrzuca wszystko, co nie jest plikiem (`kind !== File`) | Przestaje odrzucać katalogi (P3) |
| `Application/Dto/EntryDescription` | `name` plus `list<string>` wierszy | Zastąpione strukturą sekcji |
| `Application/FileInfoSettings` | Dwie pozycje: `timeout`, `arguments` | Sześć pozycji (P7) |
| `Presentation/FileInfoScreen` | Liczy opis przy zmianie zaznaczenia i przy `reset()`; rysuje `ListView`em ze zwijaniem wierszy | Sekcje, pasek postępu, doglądanie pracy tłowej |
| `Presentation/FileInfoModule` | `ProvidesCommands` z pustą listą; budowany w `Bootstrap` z wstrzykniętym ekranem | Składa się sam i leniwie (P9) |
| `Presentation/Cli/Bootstrap.php` (linie 204–206) | Wiąże ekran, przypadek użycia i usługę modułu | **Traci to wiązanie** — jedna pozycja na liście, jak `BrowserModule` |

## Zakres

### 1. Źródła informacji i ich koszt

| Źródło | Co daje | Koszt | Jak liczone |
|---|---|---|---|
| `stat`/`lstat` (PHP, bez procesu) | rozmiar, uprawnienia, właściciel, grupa, `mtime`/`ctime`/`atime`, liczba dowiązań, i-węzeł | znikomy | synchronicznie, w klatce |
| dowiązanie symboliczne | dokąd prowadzi, czy cel istnieje, jaki jest jego rodzaj | znikomy | synchronicznie, w klatce |
| `file` (proces potomny) | czym plik jest według zawartości — **istnieje dziś** | jeden `proc_open`, limit z ustawień | synchronicznie, jak dziś |
| `du` (proces potomny) | rozmiar zajęty na dysku; dla katalogu — wraz z zawartością | od milisekund do sekund | **w tle** (P2) |
| `sha256` | suma kontrolna zawartości | rośnie z rozmiarem pliku | **w tle**, za przełącznikiem, do limitu rozmiaru (P6) |

Kolejność rozstrzyga koszt: to, co znikome, jest na ekranie od pierwszej klatki;
to, co drogie, dochodzi z paskiem postępu.

### 2. Praca w tle jako prywatna sprawa modułu

**Kontrakt modułu z kroku 20 nie rośnie** (P2) — i to jest główny sprawdzian tego
kroku, tak jak dla kroku 21 był nim kontrakt nietknięty przez główną funkcję
aplikacji.

Praca tłowa mieści się wewnątrz `FileInfo`, bo moduł ma dwa własne wejścia
wołane co klatkę: `useContext()` i `draw()`. Proces startuje przy zmianie
zaznaczenia i jest **doglądany nieblokująco** — dokładnie tak, jak dziś
`FileInspectorService` sonduje `proc_get_status()`, tylko bez czekania w pętli.

Trzy rzeczy, które muszą być w kodzie jawne, bo bez nich praca tłowa jest
wyciekiem, a nie funkcją:

- **Zmiana zaznaczenia przerywa pracę poprzednią** (`proc_terminate`), zanim
  zacznie nową. Inaczej przewinięcie listy o dwadzieścia pozycji zostawia
  dwadzieścia procesów.
- **Zamknięcie ekranu i wyjście z aplikacji przerywa pracę.** `reset()` już
  istnieje; wyjście wymaga sprzątania na tej samej ścieżce, którą terminal wraca
  do trybu normalnego.
- **Limit czasu obowiązuje także w tle** (P7, osobna pozycja ustawień). Praca
  tłowa nie blokuje klatki, ale proces, który nigdy nie kończy, to nadal proces,
  który nigdy nie kończy.

### 3. Katalogi

`InspectSelectedEntryUseCase` przestaje odrzucać `kind !== File` (P3). Katalog
dostaje uprawnienia, właściciela, czasy, liczbę wpisów oraz `du` — dla niego
najbardziej sensowne. Zmieniają się przy tym napisy, które dziś mówią co innego:
`module.file-info.nothing`, `module.file-info.description`
i `module.file-info.help.enter`.

### 4. Ekran: sekcje, w tym wolne

Cztery sekcje (P4), składane komponentem z kroku 22:

| Sekcja | Zawartość |
|---|---|
| Tożsamość | rodzaj wpisu, opis od `file`, cel dowiązania |
| Rozmiar | rozmiar, zajęte na dysku (`du`), suma kontrolna (`sha256`) |
| Uprawnienia | tryb `rwx` i ósemkowo, właściciel, grupa, i-węzeł i dowiązania (za przełącznikiem) |
| Czasy | zmiana treści, zmiana i-węzła, odczyt — w formacie z ustawień |

Wiersze wolnych źródeł stoją od pierwszej klatki z wartością „liczę…” (P5), więc
układ nie skacze, a pasek postępu (krok 23) pokazuje, ile zostało — tam, gdzie da
się to policzyć.

### 5. Ustawienia modułu

Dwie istniejące plus cztery nowe (P7), a `sha256` dokłada dwie własne (P6):

| Klucz | Rodzaj | Domyślnie |
|---|---|---|
| `timeout` | liczba z listy | 2 s — bez zmian |
| `arguments` | tekst | puste — bez zmian |
| `du` | przełącznik | włączone |
| `timeFormat` | wybór: bezwzględny / „ile temu” | bezwzględny |
| `inode` | przełącznik | wyłączone |
| `backgroundTimeout` | liczba z listy | wyżej niż `timeout` — sekundy w tle bolą mniej |
| `checksum` | przełącznik | **wyłączone** |
| `checksumLimit` | liczba z listy (MiB) | powyżej limitu `sha256` nie startuje i mówi dlaczego |

### 6. `Bootstrap` przestaje wiązać wnętrze modułu

Dług zapisany w dzienniku kroku 21 („do wyrównania przy okazji rozbudowy”).
`FileInfoModule` buduje ekran, przypadek użycia i usługi **sam
i leniwie** (P9) — leniwość ma tę samą twardą przyczynę, co w `BrowserModule`:
napisy modułu wchodzą do katalogu **po** zbudowaniu rejestru, więc moduł składany
zachłannie wypisałby użytkownikowi surowy klucz.

Po zmianie `Bootstrap` widzi z modułu **jedną klasę**, a `CoreKnowsNothingAboutFilesTest`
robi się dla `FileInfo` tak samo twardy, jak dziś dla przeglądarki.

## Poza zakresem

- **Zmiana uprawnień, właściciela i czasów** — moduł opisuje, nie zmienia.
  Operacje na plikach mają własne miejsce w planie („Zakres poza MVP”).
- **Podgląd zawartości pliku tekstowego** — osobna funkcja i osobny moduł.
- **Atrybuty rozszerzone i ACL** (`getfattr`, `getfacl`) — kolejne procesy
  potomne o niepewnej dostępności; jeśli w ogóle, to osobnym krokiem.
- **Kontekst SELinux.**
- **Sumy kontrolne inne niż `sha256`.**
- **Praca tłowa jako zdolność kontraktu modułu** — świadomie zostaje prywatna
  (P2). Gdy zażąda jej drugi moduł, wtedy będzie na czym oprzeć kontrakt.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/FileInfo/Application/Dto/EntryDescription.php` | Moduł/Application | Sekcje zamiast płaskiej listy wierszy; wiersz wolnego źródła zna swój stan. |
| `Module/FileInfo/Application/Port/FileInspectorPort.php` | Moduł/Application | Rozstrzygnięcie nr 1 — rozrost albo rodzeństwo portów. |
| `Module/FileInfo/Application/Port/BackgroundWorkPort.php` | Moduł/Application | Nowy — start, doglądanie i przerwanie pracy tłowej. |
| `Module/FileInfo/Application/UseCase/InspectSelectedEntryUseCase.php` | Moduł/Application | Katalogi (P3); składanie sekcji; zamawianie pracy tłowej. |
| `Module/FileInfo/Application/FileInfoSettings.php` | Moduł/Application | Sześć nowych pozycji (P6, P7). |
| `Module/FileInfo/Infrastructure/FileStatService.php` | Moduł/Infrastructure | Nowy — `stat`/`lstat` i dowiązania, bez procesu potomnego. |
| `Module/FileInfo/Infrastructure/BackgroundProcessService.php` | Moduł/Infrastructure | Nowy — `du` i `sha256` w tle, wraz z przerywaniem i limitem czasu. |
| `Module/FileInfo/Presentation/FileInfoScreen.php` | Moduł/Presentation | Sekcje z kroku 22, pasek postępu z kroku 23, doglądanie pracy tłowej, sprzątanie w `reset()`. |
| `Module/FileInfo/Presentation/FileInfoModule.php` | Moduł/Presentation | Składa się sam i leniwie (P9). |
| `Module/FileInfo/lang/pl.php`, `en.php` | Napisy | Etykiety sekcji i wierszy, „liczę…”, powody odmowy, nowe pozycje ustawień; poprawione napisy mówiące „tylko pliki”. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Traci wiązanie wnętrza `FileInfo` (linie 204–206); `VERSION`. |
| `README.md` | Dokumentacja | Co pokazuje moduł i czym steruje na swojej zakładce. |
| testy | Testy | Sekcje i ich kolejność; katalog opisany; praca tłowa (start, wynik, przerwanie przy zmianie zaznaczenia, limit czasu, sprzątanie przy wyjściu); `sha256` powyżej limitu rozmiaru; formaty czasu; `Bootstrap` widzi jedną klasę modułu. |

## Do rozstrzygnięcia na starcie kroku

Pytania planistyczne **P1–P9 są zamknięte** (sekcja „Ustalenia”, D43). Poniższe
to rozstrzygnięcia wykonawcze:

1. **Kształt portu.** `FileInspectorPort` rozrasta się o metody dla nowych źródeł,
   czy każde źródło dostaje własny port? Pierwsze jest krótsze, drugie nie każe
   `stat`-owi znać limitu czasu, którego nie ma po co znać.
2. **Kto dogląda pracy tłowej** — ekran w `draw()`, ekran w `useContext()` czy
   osobny obiekt stanu modułu na wzór `BrowserState` z kroku 21.
3. **Jak `sha256` liczy postęp.** Własny odczyt pliku w kawałkach zna liczbę
   przeczytanych bajtów; `sha256sum` jako proces potomny nie mówi nic. Postęp
   z paska (P8) ma sens tylko w pierwszym wariancie.
4. **Czy `du` ma postęp, czy tylko pasek bez liczby.** `du` nie zna postępu,
   więc pasek pracowałby w trybie „nieznany” z kroku 23.
5. **Skąd biorą się nazwy właściciela i grupy.** `posix_getpwuid()` bywa
   niedostępne; wtedy zostaje sam numer i trzeba to powiedzieć, a nie pokazać
   pustkę.
6. **Czy moduł używa `Split` z kroku 24** — sekcje po lewej, coś po prawej
   (miniatura? podgląd początku pliku?). Jeśli nie, zależność od kroku 24 jest
   wyłącznie kolejnościowa i dziennik ma to powiedzieć wprost.
7. **Co się dzieje przy szybkim przewijaniu listy.** Zaznaczenie zmieniane
   trzydzieści razy na sekundę oznacza trzydzieści startów i przerwań procesu;
   opóźnienie przed startem pracy tłowej jest prawdopodobnie konieczne — a jeśli
   tak, to ile i czym mierzone.

## Kryteria ukończenia

- Opis pliku pokazuje **cztery sekcje** i nie zawiesza pętli głównej ani na jedną
  klatkę — sprawdza to pomiar, nie wrażenie.
- Wolne źródło ma swój wiersz od pierwszej klatki, z wartością „liczę…”, i układ
  ekranu nie skacze, gdy wynik dochodzi.
- Zmiana zaznaczenia **przerywa** pracę tłową poprzedniego wpisu; po przewinięciu
  listy o dwadzieścia pozycji nie zostaje ani jeden proces potomny. Sprawdza to
  test.
- Wyjście z aplikacji i zamknięcie ekranu sprzątają pracę tłową.
- Katalog jest opisywany, a `du` dla niego ma sens; napisy nie mówią już „tylko
  pliki”.
- `sha256` jest domyślnie wyłączona, a powyżej limitu rozmiaru **nie startuje
  i mówi dlaczego**.
- **Kontrakt modułu z kroku 20 nie zyskał ani jednej metody**, a jeśli zyskał —
  dziennik mówi którą i dlaczego kontrakt był za wąski.
- `Bootstrap` widzi z modułu **jedną klasę**, a `CoreKnowsNothingAboutFilesTest`
  jest dla `FileInfo` tak samo twardy jak dla przeglądarki. Dług z kroku 21
  spłacony.
- Klatka zmierzona `bin/render-bench` i rozliczona „przed i po” — również wtedy,
  gdy wynik jest niekorzystny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `README.md` opisuje, co moduł pokazuje i czym steruje.

## Rozstrzygnięcia wykonawcze ze startu kroku (2026-08-10)

Siedem pytań z sekcji niżej, rozstrzygniętych przez użytkownika przed otwarciem
edytora. Trzecie z nich zmieniło zakres kroku.

| # | Pytanie | Wybór |
|---|---|---|
| 1 | Kształt portu | **Trzy porty wedle natury źródła** — `FileInspectorPort` nietknięty, doszły `FileStatPort` i `ChecksumPort` |
| 2 | Kto dogląda pracy | **Osobny obiekt stanu** (`FileInfoState`), na wzór `BrowserState` z kroku 21 |
| 3 | Jak `sha256` liczy postęp | **Własny odczyt po kawałku na klatkę** — a *komponent procesu tłowego* dostaje **własny krok planu** |
| 4 | Czym liczyć `du` | **Czeka na krok 26** — w tym kroku nie ma go wcale |
| 5 | Właściciel i grupa | **Nazwa, a przy jej braku numer z adnotacją** |
| 6 | Czy moduł używa `Split` | **Tak — sekcje po lewej, miniatura po prawej** |
| 7 | Kiedy startuje praca | **Na żądanie, klawiszem `s`** |

Rozstrzygnięcie nr 3 brzmiało: *„`hash_init()` + `hash_update_stream()` w procesie
w tle, który przekazuje dane do procesu głównego. Komponent procesu robimy jako
osobny krok planu. Teraz jedynie własny odczyt po kawałku.”* — i to ono
podzieliło pracę tłową na dwie: **odczyt własny wchodzi teraz, proces potomny
osobnym krokiem**.

Rozstrzygnięcie nr 7 unieważniło przy okazji pytanie planu o opóźnienie startu
(„co się dzieje przy szybkim przewijaniu”): praca uruchamiana klawiszem nie
startuje przy przewijaniu w ogóle, więc żaden licznik czasu nie jest potrzebny.
Skutek uboczny: `NeedsTime` na ekranie, dowieziony w kroku 23, **nadal nie ma
użytkownika produkcyjnego**.

## Odstępstwa od planu

Pięć, każde z powodem.

1. **`du` nie wszedł** (rozstrzygnięcie nr 4). Sekcja „Rozmiar” pokazuje za to
   **bloki i-węzła** z `lstat` — czyli miejsce zajęte przez sam plik, bez
   zawartości katalogu. To nie jest zamiennik `du` i nie udaje nim być.
2. **Dwie pozycje ustawień przesunięte do kroku 26** (`du`, `backgroundTimeout`).
   Pozycja, która nie ma czym sterować, jest obietnicą bez pokrycia. Zostało
   sześć: dwie istniejące plus cztery nowe.
3. **`BackgroundWorkPort` nie powstał**, a w jego miejscu stanął `ChecksumPort`
   o innym kształcie: `begin()`, `advance($bytes)`, `stop()`. Port mówi o pracy
   liczonej **w klatce**, a nie o pracy zleconej na zewnątrz.
4. **Powstał `PreviewEntryUseCase`** — spoza listy planowanych plików, wymuszony
   rozstrzygnięciem nr 6. Jest bliźniaczy wobec przypadku użycia w przeglądarce
   i to nie jest powtórzenie przez nieuwagę: reguła 15 zabrania modułowi sięgać
   do innego modułu, a wspólne zostaje to, co należy do rdzenia (`ImagePreviewPort`,
   `Preview`, `ImageBox`).
5. **Scenariusza `bin/render-bench` nie dołożono.** Ekran modułu składa się
   z komponentów, które **mają już swoje scenariusze** — sekcje (krok 22), pasek
   postępu (krok 23) i podział (krok 24). Nowy scenariusz mierzyłby ich sumę,
   czyli nic, czego nie widać osobno.

**Czego nie zrobiono:** opisu nie widziano jeszcze w prawdziwym terminalu —
sprawdzono go zrzutem klatki z **prawdziwymi** usługami (`lstat`, `file`, `posix`,
`ImagePreviewService`), a nie tylko testami na dublerach.

## Pomiar

Wzorce: [2026-08-10-po-kroku-24.json](../../pomiary/2026-08-10-po-kroku-24.json)
i [2026-08-10-po-kroku-25.json](../../pomiary/2026-08-10-po-kroku-25.json).

| Scenariusz | Przed | Po | Zmiana |
|---|---|---|---|
| puste płótno | 6,6 ms | 6,8 ms | +2,6% |
| sam tekst | 11,1 ms | 11,5 ms | +3,6% |
| same ramki | 10,3 ms | 10,5 ms | +2,2% |
| ramki z tekstem | 17,2 ms | 17,7 ms | +2,8% |
| zaznaczenie | 19,1 ms | 19,0 ms | −0,6% |
| suwak | 13,6 ms | 13,9 ms | +2,1% |
| klatka z miniaturą | 26,5 ms | 26,5 ms | +0,1% |
| klatka z okienkiem | 22,6 ms | 22,1 ms | −2,2% |
| okno komend | 27,1 ms | 27,2 ms | +0,4% |
| zwijane sekcje | 16,4 ms | 16,2 ms | −1,1% |
| paski postępu | 23,9 ms | 23,9 ms | +0,0% |
| klatka podzielona | 25,0 ms | 24,8 ms | −0,8% |

**Bez regresji powyżej progu, i tak miało być.** Krok nie tknął ani jednego
prymitywu, ani jednej klasy potoku rysowania i ani jednego komponentu rdzenia —
cała praca zmieściła się w module. Tabela jest tu dowodem **negatywnym**: gdyby
którykolwiek scenariusz drgnął, znaczyłoby to, że krok wyszedł poza moduł, choć
nie miał prawa.

Jest za to koszt, którego ta tabela **nie mierzy**, i trzeba go nazwać: liczenie
sumy kontrolnej dokłada do klatki odczyt 4 MiB (~3 ms na typowym dysku) przez
czas trwania pracy. Nie widać go w pomiarze, bo scenariusze pomiarowe nie czytają
plików; widać go za to w budżecie taktu — 3 ms z 33 ms, wyłącznie wtedy, gdy
użytkownik nacisnął `s`.

## Dziennik realizacji

**2026-08-10 — krok wykonany.**

Co powstało w module — trzynaście plików, ani jednego w rdzeniu:

- **`Application/Dto`** — `FileStat` (wynik `lstat` jako dane pierwotne),
  `EntryKind` (osiem rodzajów wpisu), `DescriptionSection` i `DescriptionRow`
  (opis jako sekcje, klucze katalogu zamiast napisów), `ChecksumState`
  i `ChecksumStage` (stan pracy oglądany co klatkę).
- **`Application/Port`** — `FileStatPort` (bez limitu czasu, bo nie ma czego
  przerywać) i `ChecksumPort` (`begin`/`advance`/`stop`).
- **`Application/UseCase`** — przebudowany `InspectSelectedEntryUseCase` (cztery
  sekcje, katalogi, dowiązania, dwa formaty czasu) i nowy `PreviewEntryUseCase`.
- **`Infrastructure`** — `FileStatService` (`lstat`, `readlink`, `posix`, licznik
  wpisów przez `readdir`) i `ChecksumService` (`hash_init` + `hash_update_stream`).
- **`Presentation`** — `FileInfoState` (opis, miniatura, praca i jej przerywanie),
  przebudowany `FileInfoScreen`, `Component/PreviewPane`, samoskładający się
  `FileInfoModule`.

**Trzy klocki rdzenia spotkały się w jednym ekranie i to był ich sprawdzian.**
Kroki 22, 23 i 24 dowiozły sekcje, pasek postępu i podział — każdy osobno, każdy
z własnym pomiarem, a pasek postępu **bez użytkownika w aplikacji** (świadome
złamanie reguły 13, zapisane wtedy jako wyjątek). Tutaj wszystkie trzy złożyły
się w jeden ekran i pasowały bez ani jednej poprawki w rdzeniu. To jest dowód,
którego kroki 22–24 nie mogły dostarczyć same.

**Dług z kroku 21 spłacony i pilnuje go maszyna.** `Bootstrap` widział z modułu
`FileInfo` cztery klasy — ekran, przypadek użycia, usługę i tłumacza — a dziś
widzi jedną, tak samo jak z przeglądarki. `CoreKnowsNothingAboutFilesTest` dostał
test, który sprawdza to dla **każdego** modułu: w wiązaniu nie wolno wymienić
niczego poza klasą modułu.

**Co sprawdziło się samo z siebie.** Obawa planu o „trzydzieści startów
i przerwań procesu na sekundę” przy szybkim przewijaniu rozwiązała się przez
rozstrzygnięcie nr 7 — praca zaczyna się klawiszem, więc przewijanie nie
uruchamia niczego. Zostało po niej jedno: `FileInfoState` przerywa liczenie przy
zmianie zaznaczenia mimo wszystko, bo użytkownik może zacząć pracę i **potem**
przewinąć listę.

**Czego kod nie pozwolił zrobić inaczej.** Wiersz sumy kontrolnej dokłada do
sekcji **ekran**, a nie przypadek użycia — bo opis liczy się raz na zaznaczenie,
a stan sumy zmienia się co klatkę. Obiekt liczony raz musiałby być przeliczany
trzydzieści razy na sekundę albo kłamać; trzeciej możliwości nie ma.

**Testy:** 13 nowych w `FileDescriptionTest` (sekcje i ich kolejność, katalog bez
polecenia `file`, dowiązanie wraz z celem, właściciel bez `posix`, i-węzeł za
przełącznikiem, dwa formaty czasu, suma kontrolna: start na żądanie, postęp po
kawałku, przerwanie przy zmianie zaznaczenia, sprzątanie w `reset()`, trzy
odmowy) plus przepisany `FileInfoModuleTest` i nowy test granicy rdzenia. Razem
**916** zielonych. Do zestawu ekranów doszły dwa dublery: `StubFileStat`
i `StubChecksums` — pierwszy dlatego, że prawdziwy `lstat` dawałby wynik zależny
od maszyny, drugi dlatego, że liczba kroków pracy musi być w teście powiedziana
wprost.
