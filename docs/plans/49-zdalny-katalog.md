# Krok 49 — Zdalny katalog: panel modułu czyta przez SFTP

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 48 i 50 jako środkowa
> trzecia Fazy XVII ([00-decyzje.md](00-decyzje.md), D84). To on jest tym,
> po co faza istnieje: sesja bez listy plików jest połączeniem donikąd.

## Status

**Nie rozpoczęty.** Zablokowany przez krok 48 (bez sesji nie ma czego czytać).

## Cel

Ekran modułu `ssh` ma pokazywać zawartość katalogu na połączonym hoście
i pozwalać po nim chodzić — tak samo, jak przeglądarka chodzi po dysku lokalnym.

Miarą powodzenia jest zdanie: **po połączeniu widać zdalny katalog domowy
z nazwami, rozmiarami i datami, `Enter` wchodzi w podkatalog, `Backspace` wraca
wyżej, a katalog o dziesięciu tysiącach wpisów nie zatrzymuje pętli ani na jedną
klatkę dłużej niż lokalny.**

## Zastrzeżenie do rozstrzygnięcia na starcie — jeden obieg sieci na wpis

`opendir()` na opakowaniu `ssh2.sftp://` oddaje **same nazwy**. Rozmiar, data,
prawa i rodzaj wpisu wymagają `stat()`, a każde takie pytanie to **osobny obieg
do serwera**: przy łączu o czasie odpowiedzi 20 ms katalog o pięciuset wpisach
kosztuje **dziesięć sekund**, w których aplikacja nie robi nic innego.

To jest główna trudność kroku i **pierwsza rzecz do sprawdzenia empirycznie**
(protokół SFTP niesie atrybuty razem z nazwą w odpowiedzi `READDIR`; pytanie
brzmi, czy rozszerzenie PHP je przekazuje, czy odrzuca — dokumentacja milczy,
a maszyna projektu nie ma serwera, na którym dałoby się to sprawdzić przy
planowaniu).

Trzy warianty, jeśli okaże się, że atrybuty trzeba dobierać osobno:

| Wariant | Cena |
|---|---|
| **(a)** same nazwy, kolumny puste | tanie i szczere, ale lista traci to, co krok 27 dał liście lokalnej |
| **(b) `stat` tylko dla widocznego okna, kawałkowo** (D46) | trzydzieści obiegów na ekran zamiast tysiąca; kolumny wypełniają się przez kilka klatek. **Rekomendacja** — wzorzec, który projekt ma i stosuje od kroku 25 |
| **(c)** jeden `ssh2_exec('ls -l …')` i parsowanie | jeden obieg na katalog, ale zakłada powłokę POSIX po drugiej stronie i łamie się na nazwach z odstępami i znakami nowej linii |

Wariant **(b)** jest rekomendowany także dlatego, że jest jedynym, który nie
zakłada niczego o zdalnym systemie — a serwer SFTP nie musi mieć powłoki.

## Zależności

- **Krok 48** całkowicie: sesja, port i stan połączenia powstają tam. Ten krok
  dokłada do modułu drugi ekran albo drugi panel (rozstrzygnięcie nr 1) i drugi
  port.
- **Krok 25** wzorcowo i twardo — **praca kawałkowa** (D46) jest tu użyta po raz
  piąty, a po raz pierwszy do pracy, której czas kawałka **zależy od łącza**,
  a nie od procesora. Trzy części wzorca obowiązują bez zmian: port mówi
  o pracy, stan pracy jest daną oglądaną co klatkę, praca ma właściciela, który
  ją przerywa.
- **Krok 27** twardo: wiersz zdalnego katalogu to `TableRow` z kolumnami, jak
  wiersz lokalny — z tą różnicą, że kolumny **przez kilka klatek świecą pustką**.
  Reguła 11e (kolumna stała ustępuje pierwsza) obowiązuje bez zmian.
- **Krok 18** — `ListView`, `ScrollWindow`, `Scrollbar`, `Table`; komponentu krok
  **nie dokłada**.
- **Krok 21** jako **wzorzec do powtórzenia, nie źródło kodu**: moduł nigdy nie
  sięga do innego modułu (reguła 15), więc `DirectoryPath`, `Entry`
  i `EntryComparator` z przeglądarki są **nie do użycia** — moduł dostaje własne,
  i to jest świadome powtórzenie, nie przeoczenie (rozstrzygnięcie nr 2).
- **Krok 30** miękko: filtr nazwy w zdalnym panelu ma sens i kosztuje tyle, co
  `NameFilter` w przeglądarce — ale jest do rozstrzygnięcia, bo powiela kolejny
  obiekt tamtego modułu.
- **Krok 43** miękko: zaznaczenie wielokrotne jest **mnożnikiem operacji**, więc
  ma sens dopiero razem z krokiem 50. Rekomendacja: wchodzi tam, nie tutaj.
- **Krok 33** — rozmiar okna nie jest stałą uruchomienia, więc „widoczne okno”,
  z którego liczy się zamówienie atrybutów, zmienia się między klatkami.

## Model i wysiłek

**Opus / xhigh.**

Trzy trudności naraz i każda innego rodzaju. **Wydajnościowa**: praca kawałkowa,
której kawałek trwa tyle, ile trwa sieć — czego projekt nie miał ani razu (`du`
z kroku 26 jest procesem potomnym czytanym nieblokująco, a nie wywołaniem, które
zamiera). **Architektoniczna**: druga domena plikowa w drugim module i granica,
za którą byłoby to powielanie już nie do obrony. **Cicha**: `ModuleContext`
niesie ścieżkę jako napis, a ścieżka zdalna wpuszczona do niego **skłamie**
odbiorcy, który czyta ją `lstat`em (patrz zakres nr 4).

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| `ssh2_sftp_*` | Komplet: `stat`, `lstat`, `realpath`, `readlink`, `rename`, `unlink`, `mkdir`, `rmdir`, `chmod`, `symlink` |
| Opakowanie `ssh2.sftp://` | Zarejestrowane — `opendir`, `readdir`, `stat`, `fopen` działają na URL-u |
| Czy `readdir` niesie atrybuty | **Niesprawdzone** — pierwsza rzecz do rozstrzygnięcia empirycznie (zastrzeżenie wyżej) |
| `FilesystemDirectoryRepository` | Lokalny odpowiednik: `scandir()` + jedno `stat()` na wpis, sortowanie w `EntryComparator`. **Wzorzec do powtórzenia, kod nie do użycia** (reguła 15) |
| `ModuleContext` | Sześć pól danych pierwotnych; `path` to **napis ścieżki lokalnej**, a moduł `FileInfo` czyta go `lstat`em |
| `Table`, `Column`, `TableRow` | Gotowe od kroku 27 — kolumny liczą się raz na klatkę dla wszystkich wierszy naraz |
| `docs/pomiary/README.md` | Spis „element → scenariusz” domknięty w kroku 38; nowy element wymaga scenariusza **albo zapisanego powodu pominięcia** |

## Zakres

### 1. Własna domena zdalnego katalogu

`Module\Ssh\Domain`: `RemotePath` (bezwzględna, porządkowana tekstowo — jak
`DirectoryPath`), `RemoteEntry` (nazwa, rodzaj, rozmiar, data, prawa **oraz
znacznik „atrybutów jeszcze nie znam”**), `RemoteDirectory` (agregat) i własny
komparator.

Powtórzenie wobec przeglądarki jest **świadome i nazwane**: alternatywą byłoby
wyniesienie ścieżki do rdzenia, czyli odwrócenie D42 („rdzeń nie wie, czym jest
katalog ani wpis”) — a to jest cena nieporównanie wyższa niż dwie klasy wartości.
Granica, za którą powtarzanie przestaje być do obrony, jest częścią
rozstrzygnięcia nr 2 i ma trafić do `SKILL.md` razem z powodem.

**Jedna rzecz różni `RemoteEntry` od `Entry` i nie jest kosmetyczna**: wpis
lokalny zna swoje atrybuty od chwili powstania, zdalny **rodzi się bez nich**.
To jest właściwość danej, nie jej odbiorcy — i to ona przesądza o kształcie
wiersza w tabeli.

### 2. Odczyt kawałkowy

Port `RemoteDirectoryPort` z metodami wzorowanymi na D46: `begin(RemotePath)`,
`advance(int $budget)`, `state()`, `stop()`. Etapy: **nazwy** (jeden obieg)
→ **atrybuty widocznego okna** (po jednym obiegu na wpis, budżet dobrany do
taktu) → **gotowe**. Wejście w katalog przerywa poprzednią pracę — jedna praca
naraz (11d).

**Budżet kawałka jest tu czasem, nie liczbą wpisów** — i to jest pierwsza taka
praca w projekcie. Wpis lokalny kosztuje mikrosekundy i da się ich policzyć 512
na takt (krok 41); zdalny kosztuje tyle, ile trwa obieg, więc kawałek musi
pytać zegara, a nie licznika. Zegar bierze się **z zewnątrz**, jak w regule 11b.

### 3. Ekran i chodzenie po katalogach

`Enter` wchodzi, `Backspace` wraca wyżej, `F5` czyta na nowo, `Home`/`End`
i strzałki jak wszędzie. Katalog startowy z profilu hosta, a `realpath`
rozstrzyga, czym jest `~`. Ostatni katalog zapamiętuje się w pliku stanu modułu
(dopisanie klucza do dokumentu z kroku 48).

Wpisy ukryte, filtr nazwy i sortowanie — rozstrzygnięcia nr 4 i 5.

### 4. Kontekst sesji: pułapka do rozstrzygnięcia przed pierwszą linią kodu

`ModuleContext` niesie ścieżkę **jako napis**, bez informacji, czyja to ścieżka.
Ekran zdalny, który opublikuje `/var/log`, sprawi, że moduł opisu pliku pokaże
**lokalny** `/var/log` — bo tamten czyta ścieżkę `lstat`em i nie ma jak
zauważyć, że mówi o cudzej maszynie. Kłamstwo jest przy tym ciche: obie ścieżki
istnieją, obie się czytają, a użytkownik ogląda opis nie tego pliku, na który
patrzy.

Trzy wyjścia:

| Wyjście | Cena |
|---|---|
| **(a)** ekran zdalny **nie publikuje** kontekstu | najtańsze i nic nie łamie; `FileInfo` pokazuje ostatnie miejsce lokalne. **Rekomendacja na ten krok** |
| **(b)** ścieżka publikowana jako URI (`sftp://użytkownik@host/var/log`) | zmiana **umowy** kontekstu, więc dotyka wszystkich odbiorców naraz |
| **(c)** kontekst dostaje pole „pochodzenie” | zmiana rdzenia; wolno ją zrobić **razem z odbiorcą** (reguła 13), czyli gdy `FileInfo` nauczy się mówić „to jest wpis zdalny, nie umiem go opisać” |

Krok 50 czyta kontekst w drugą stronę (przeglądarka → moduł `ssh`, żeby znać
domyślny katalog docelowy pobrania) i **tamta droga jest legalna bez żadnej
zmiany** — kontekst po to istnieje.

### 5. Zerwana sesja w środku pracy

Każdy obieg może się nie udać. Stan pracy niesie wtedy powód, ekran pokazuje
zdanie w pasku stanu, a moduł wraca do stanu „rozłączony” — **bez wyjątku
przechodzącego przez granicę portu** (reguła 8) i bez przerwania pętli.

### 6. Pomiar

Rysowanie jest to samo, co w liście lokalnej (`Table` w strefie środkowej,
mierzy `columns`), więc **scenariusza klatki krok nie dokłada** — powód
pominięcia idzie do [docs/pomiary/README.md](../pomiary/README.md), tą samą
drogą, którą zapisano go dla `MenuOverlay` i `EntryTree`.

Osobno, do dziennika kroku, dwie liczby, których `bin/render-bench` nie zna
i znać nie ma: **ile obiegów kosztuje katalog** i **po ilu klatkach lista jest
kompletna**. Narzędzie mierzy klatkę, nie sieć — i to jest granica pomiaru do
zapisania wprost, jak granica osi `--loop` w krokach 45 i 46.

Oś `--loop` rozlicza za to rzecz, którą zna: **takt z pracą kawałkową w tle nie
ma prawa urosnąć**.

## Poza zakresem

- **Zapis po zdalnej stronie** — zmiana nazwy, nowy katalog, usunięcie. Wszystkie
  cztery wywołania są w rozszerzeniu (`ssh2_sftp_rename`, `_mkdir`, `_rmdir`,
  `_unlink`), więc jest to krok o rozmiarze kroku 41 — i osobny krok, jeśli
  faza się przedłuży (patrz [00-index.md](00-index.md), „Zakres poza MVP”).
- **Przesył plików** — krok 50.
- **Podgląd zdalnych plików** (miniatura, tekst) — `ImagePreviewService`
  i `TextPreviewService` czytają **ścieżkę lokalną**; podgląd zdalny wymagałby
  albo pobrania do pliku tymczasowego, albo nauczenia obu strumienia. Osobna
  decyzja, po kroku 50.
- **Drzewo katalogów zdalnych** (`TreeView`) — rozwinięcie gałęzi to kolejny
  obieg, a krok 31 zakładał odczyt tani.
- **Zdalny `du` i suma kontrolna przez `ssh2_exec`** — kuszące i tanie, ale
  zakłada powłokę POSIX po drugiej stronie; osobna decyzja.
- **Zaznaczenie wielokrotne** — jest mnożnikiem operacji, więc należy do kroku,
  który operacje wnosi.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Ssh/Domain/ValueObject/{RemotePath,RemoteEntry,RemoteEntryType}.php` | Moduł/Domain | **Nowe** |
| `Module/Ssh/Domain/Aggregate/RemoteDirectory.php` | Moduł/Domain | **Nowy** |
| `Module/Ssh/Domain/Repository/RemoteDirectoryRepositoryInterface.php` | Moduł/Domain | **Nowy** |
| `Module/Ssh/Application/Port/RemoteDirectoryPort.php` | Moduł/Application | **Nowy** — praca kawałkowa (D46) |
| `Module/Ssh/Application/{RemoteListingState,RemoteBrowseState}.php` | Moduł/Application | **Nowe** — stan pracy i stan oglądania |
| `Module/Ssh/Application/UseCase/{EnterRemoteDirectory,RemoteGoUp}UseCase.php` | Moduł/Application | **Nowe** |
| `Module/Ssh/Infrastructure/{Sftp,RemoteEntryComparator}Service.php` | Moduł/Infrastructure | **Nowe** — odczyt przez opakowanie strumienia i sortowanie |
| `Module/Ssh/Presentation/{RemoteScreen,RemoteList}.php` | Moduł/Presentation | **Nowe** — ekran i komponent modułu (`Table` w środku) |
| `Module/Ssh/Presentation/SshModule.php` | Moduł/Presentation | Drugi ekran albo drugi panel (rozstrzygnięcie nr 1) |
| `Module/Ssh/Infrastructure/SshStateService.php` | Moduł/Infrastructure | Klucz „ostatni katalog” w dokumencie z kroku 48 |
| `Module/Ssh/lang/{pl,en}.php` | Napisy | Nagłówki kolumn, stany odczytu, powody niepowodzeń |
| `docs/pomiary/README.md` | Dokumentacja | Powód pominięcia scenariusza |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Druga domena plikowa i jej granica; kawałek mierzony czasem |
| testy | Testy | Atrapa portu odczytu (bez sieci), komparator, `RemotePath`, praca kawałkowa na dublerze, przebieg funkcjonalny przez `ScreenFixture` |

## Do rozstrzygnięcia na starcie kroku

1. **Drugi ekran modułu czy podział jednego** — moduł wnosi dziś **jeden** ekran
   (`ProvidesScreen` oddaje jeden), więc lista hostów i lista plików albo dzielą
   ekran (`Split`, krok 24), albo jedna zastępuje drugą po połączeniu. Trzecia
   droga — kontrakt oddający wiele ekranów — jest zmianą rdzenia i wymaga zgody.
2. **Granica powtarzania domeny plikowej** — ile wolno powtórzyć z przeglądarki,
   zanim właściwym rozwiązaniem stanie się wspólne miejsce. Odpowiedź ma trafić
   do `SKILL.md` razem z powodem, jak granica wyjątku 15b.
3. **Skąd biorą się atrybuty** — wariant (a), (b) czy (c) z zastrzeżenia,
   po sprawdzeniu, co naprawdę oddaje `readdir` rozszerzenia.
4. **Wpisy ukryte** — pozycja ustawień modułu, klawisz, czy jedno i drugie
   (przeglądarka ma jedno i drugie: `Ctrl`+`H` i ustawienie).
5. **Filtr nazwy** — wchodzi w tym kroku (powielając `NameFilter`) czy nie
   wchodzi wcale.
6. **Kontekst sesji** — wariant (a), (b) czy (c) z zakresu nr 4.
7. **Co widać przed połączeniem** — pusty panel z zaproszeniem, ostatnia lista
   zapamiętana z poprzedniej sesji, czy ekran hostów.
8. **Dowiązania symboliczne** — `stat` (jak przeglądarka: dowiązanie do katalogu
   zachowuje się jak katalog) czy `lstat` (widać, że to dowiązanie).

## Kryteria ukończenia

- Po połączeniu widać zdalny katalog domowy z nazwami; kolumny wypełniają się
  najpóźniej po kilku klatkach i **żadna klatka nie trwa dłużej niż budżet**.
- `Enter` wchodzi w podkatalog, `Backspace` wraca wyżej, `F5` czyta na nowo.
- Katalog o tysiącach wpisów nie zawiesza aplikacji — przewijanie działa, zanim
  atrybuty się dobiorą.
- Zerwanie sesji w środku odczytu kończy się zdaniem w pasku stanu i stanem
  „rozłączony”, nie wyjątkiem.
- Ostatni katalog przeżywa ponowne uruchomienie aplikacji.
- `bin/render-bench --loop` „przed i po” bez regresji, także przy pracy trwającej
  w tle.
- Spis „element → scenariusz” w `docs/pomiary/README.md` ma zapisany powód
  pominięcia.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie otwiera
  połączenia sieciowego**.

## Dziennik realizacji

_(pusty — krok nie rozpoczęty)_
