# Krok 49 — Zdalny katalog: panel modułu czyta przez SFTP

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 48 i 50 jako środkowa
> trzecia Fazy XVII ([00-decyzje.md](00-decyzje.md), D84). To on jest tym,
> po co faza istnieje: sesja bez listy plików jest połączeniem donikąd.

## Status

**Ukończony z zastrzeżeniem** (2026-08-15).

> **Zastrzeżenie:** klatki pod XTermem nikt jeszcze nie oglądał, a próba
> z żywym serwerem szła po **pętli zwrotnej** (kontener), nie przez prawdziwą
> sieć — jak w kroku 48. Szczegóły w dzienniku realizacji na końcu pliku.

> **Droga techniczna kroku jest inna, niż zakładał ten plan** — odwróciło ją
> rozstrzygnięcie D87 nr 1 podjęte na starcie kroku 48: `ext-ssh2` wypadło
> z fazy w całości, a katalog czyta `sftp -b -` wchodzący przez gniazdo mistrza
> połączenia. Sekcje „Zastrzeżenie", „Zakres" nr 2 i „Do rozstrzygnięcia" są
> poniżej **przepisane**; cel, kryteria ukończenia i granice zakresu zostały te
> same.

## Cel

Ekran modułu `ssh` ma pokazywać zawartość katalogu na połączonym hoście
i pozwalać po nim chodzić — tak samo, jak przeglądarka chodzi po dysku lokalnym.

Miarą powodzenia jest zdanie: **po połączeniu widać zdalny katalog domowy
z nazwami, rozmiarami i datami, `Enter` wchodzi w podkatalog, `Backspace` wraca
wyżej, a katalog o dziesięciu tysiącach wpisów nie zatrzymuje pętli ani na jedną
klatkę dłużej niż lokalny.**

## Zastrzeżenie — rozstrzygnięte pomiarem, nie wyborem

**Postawienie problemu było słuszne, a odpowiedź wyszła inna niż wszystkie trzy
przewidziane warianty.** Plan pytał, czy atrybuty wpisu wymagają osobnego obiegu
na wpis (`opendir()` na opakowaniu `ssh2.sftp://` oddaje same nazwy) i polecał
wariant (b) — `stat` kawałkowo dla widocznego okna. Rozszerzenie wypadło z fazy
razem z D87, więc pytanie zmieniło adresata: **co oddaje `sftp ls -l`**.

Sprawdzone na żywym serwerze przed pierwszą linią kodu:

| Fakt | Liczba |
|---|---|
| `sftp ls -l` przez stojącego mistrza | **jeden obieg** daje nazwę, rodzaj, prawa, rozmiar i datę |
| koszt wywołania | ~0,93 s — i jest to koszt **otwarcia kanału** w kontenerze, nie listowania (`ssh … true` kosztuje tyle samo) |
| pięć tysięcy wpisów ponad to | +0,1 s, 419 KB wypisu |
| rozczytanie tych wpisów w PHP | **3,2 ms** |

Wniosek przesądził o kształcie kroku: **koszt siedzi w wywołaniu, a nie we
wpisie**, więc praca kawałkowa została **jednostopniowa**, a budżet mierzony
zegarem — zapowiadany jako główna trudność kroku — okazał się chronić przed
kosztem, którego nie ma. Zarzut planu wobec wariantu (c) („łamie się na nazwach
z odstępami") **nie dotyczy tej drogi**: wypis `sftp` składa **klient**, a nie
powłoka po drugiej stronie, i nazwa stoi w wierszu ostatnia. Sprawdzone nazwami
z odstępem, cudzysłowem, apostrofem i znakami spoza ASCII; granicą pozostaje
nazwa ze **znakiem nowej linii** — zapisana w dzienniku.

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

## Rozstrzygnięte na starcie kroku (2026-08-15)

Pełne uzasadnienia i odrzucone alternatywy: [00-decyzje.md](00-decyzje.md), D88.
**Cztery odpowiedzi poszły wbrew rekomendacji planu** — nr 3, 5, 6 i 9.

| # | Pytanie | Rozstrzygnięcie | Wobec rekomendacji |
|---|---|---|---|
| 1 | Drugi ekran czy podział jednego | **Jeden ekran w dwóch postaciach** — spis hostów ustępuje katalogowi po połączeniu, `F3` zagląda z powrotem | zgodnie (bez zmiany rdzenia) |
| 2 | Granica powtarzania domeny plikowej | **Jakościowa i ilościowa naraz** — pojęcia wolno, mechanizmy nie; trzeci moduł uruchamia przegląd (`SKILL.md`, 15e) | — |
| 3 | Skąd biorą się atrybuty | **Jeden obieg na katalog** (`sftp ls -l`), praca kawałkowa jednostopniowa | wbrew — wariant (b) stał się bezprzedmiotowy |
| 4 | Wpisy ukryte | **Ustawienie modułu i klawisz `Ctrl`+`H`**, jak w przeglądarce; przełączenie kosztuje nowy obieg | zgodnie |
| 5 | Filtr nazwy | **Wchodzi, wraz z podświetleniem dopasowania** | wbrew |
| 6 | Kontekst sesji | **Kontekst dostaje pochodzenie** (`ContextOrigin`) wraz z odbiorcą w module opisu pliku | wbrew — plan polecał „ekran zdalny nie publikuje" |
| 7 | Co widać przed połączeniem i po rozłączeniu | **Spis hostów** w obu przypadkach, także przy zerwaniu | zgodnie |
| 8 | Dowiązania symboliczne | **Pokazujemy jak `lstat`, `Enter` próbuje wejść** — zero dodatkowych obiegów | zgodnie |
| 9 | Duży katalog wobec limitu wyjścia | **Limit wyjścia pracy tłowej wchodzi do konfiguracji** (`backgroundOutputKib`, domyślnie 1 MiB) | wbrew — plan nie znał tego pytania |
| 10 | Co robi `FileInfo` z wpisem zdalnym | **Pokazuje to, co już wiadomo** — z kontekstu, bez sieci i bez dysku | pytanie wynikłe z nr 6 |
| 11 | Skąd powód niepowodzenia po zakazie scalania | **Port niesie strumień błędów osobnym polem** | pytanie wynikłe z próby na żywym serwerze |

**Kryterium „rdzeń kosztuje jedną linię" jest tym samym odwołane.** Rdzeń rośnie
o **pięć** rzeczy i wszystkie są rozstrzygnięciami użytkownika podjętymi z ceną
wypisaną przed wyborem: limit wyjścia w konfiguracji wraz z trzecią zakładką
ustawień (nr 9), pochodzenie w `ModuleContext` (nr 6), odbiorca tego pochodzenia
w module opisu pliku (nr 10), strumień błędów w `BackgroundState` (nr 11) —
a pozycja w `Bootstrapie` stała tam od kroku 48.

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

### 2026-08-15 — rozpoznanie i rozstrzygnięcia startowe

Stan zastany sprawdzony przed pytaniami i **rozminął się z tabelą planu w jednym
miejscu, za to zasadniczym**: `ext-ssh2` nie jest już drogą tej fazy (D87), więc
pytanie „czy `readdir` rozszerzenia niesie atrybuty" straciło adresata. Zadano je
na nowo `sftp`-owi — na żywym serwerze, przed pierwszą linią kodu — i odpowiedź
przesądziła o kształcie kroku (tabela w sekcji „Zastrzeżenie").

Jedenaście rozstrzygnięć (osiem z planu, trzy wynikłe) w [00-decyzje.md](00-decyzje.md),
D88. Cztery poszły wbrew rekomendacji planu.

### 2026-08-15 — wykonanie

**Rdzeń urósł o pięć rzeczy zamiast zapowiadanej jednej linii** i każda ma swój
numer w D88. Dwie z nich to mechanizmy z odbiorcą wchodzącym w tym samym kroku
(pochodzenie kontekstu → moduł opisu pliku; strumień błędów → odczyt katalogu),
jedna to pozycja konfiguracji wraz z trzecią zakładką ustawień („Zasoby"), a piąta
stała w `Bootstrapie` od kroku 48.

**Warstwy `UseCase` moduł znowu nie dostał** — powód ten sam, co w kroku 48:
`EnterRemoteDirectoryUseCase` byłby przepuszczeniem wywołania do portu. Jego
miejsce zajął koordynator `Application\RemoteBrowser` (wzorem `SshSession`
i `PlaylistPlayer`). **Repozytorium w `Domain` też nie powstało, choć plan je
przewidywał**, i tu powód jest mocniejszy niż oszczędność: repozytorium oddaje
agregat *w chwili wywołania*, czyli obiecuje odpowiedź natychmiast — a to jest
dokładnie ta obietnica, której cała Faza XVII złożyć nie może.

**Znacznika „atrybutów jeszcze nie znam" w `RemoteEntry` nie ma**, choć plan
zapowiadał go jako rzecz odróżniającą wpis zdalny od lokalnego. Był własnością
odrzuconej drogi dwuetapowej; przy jednym obiegu wpis bez atrybutów nie ma jak
powstać, a pole o zawsze tej samej wartości byłoby pytaniem bez treści.

**Ekran dostał klawisz, którego plan nie przewidywał** (`F3`, zajrzenie do spisu
hostów przy żywej sesji) — i **nie `F2`, bo ten należy do rdzenia**. Pomyłkę
złapał przebieg funkcjonalny, który zamiast spisu hostów otworzył ekran ustawień.

#### Próba z żywym serwerem — i jedna usterka, której nie złapałby żaden test

Sprawdzenie ręczne poszło na kontenerze `atmoz/sftp:alpine` (port 2223),
**prawdziwym kodem modułu**, nie skrótem przez powłokę: dziewięć scenariuszy od
uścisku dłoni po sprzątanie. Wykryło **cichą utratę danych**, i to nie w module,
tylko w sposobie, w jaki cała faza rozmawia z procesem potomnym.

**Objaw:** katalog o pięciu tysiącach wpisów przychodził jako **1551 wpisów**,
z kodem wyjścia **0**. Nie było błędu, ostrzeżenia ani śladu w kodzie wyjścia —
lista po prostu się kończyła.

**Droga do przyczyny** (skrypty w katalogu roboczym, nie w repozytorium):

1. Port pracy tłowej **przeczytał 400 KB** od polecenia piszącego równie wolno —
   czyli rdzeń jest niewinny.
2. To samo `sftp` zapisane **do pliku** oddawało komplet 418 922 B; przez potok
   czytany co 33 ms — jedną trzecią.
3. Stratę odtworzono **bez PHP**, samą powłoką z pauzami — czyli język też jest
   niewinny.
4. Flagi deskryptora wyjścia potomka w przebiegu, który gubił: **`04001`**,
   czyli `O_WRONLY|O_NONBLOCK`.
5. Przebieg bez `2>&1`: flagi **`01`**, wypis **kompletny**.

**Przyczyna:** polecenie kończyło się na `2>&1`, więc strumień błędów potomka
i potok z listą to **ten sam opis pliku**. `sftp` uruchamia `ssh`, a ten przy
`ControlPath` jest **klientem multipleksera** — przekazuje swoje deskryptory
mistrzowi połączenia, który obsługuje wiele sesji w jednej pętli i dlatego
ustawia im tryb nieblokujący. Tryb jest własnością **opisu pliku**, więc wracał
tym samym potokiem na wyjście `sftp`; odkąd potok się zapełnił, `write()`
zwracał `EAGAIN`, a OpenSSH porzucał porcję wypisu i kończył się kodem zero.

**Naprawa** (rozstrzygnięcie użytkownika nr 11, po odrzuceniu drogi przez plik
roboczy): przestać scalać. Lista idzie wyjściem, powód niepowodzenia — strumieniem
błędów, który port rdzenia niesie odtąd **osobnym polem**. Zasada z kroku 26
(„strumieni się nie skleja") zostaje w mocy; pola są rozdzielone właśnie po to.
Regresji pilnują dwa testy: `SftpCommandTest::testStreamsAreNeverMerged`
i `BackgroundProcessServiceTest::testLargeOutputSurvivesFrameRateDraining`.

Przy okazji wyszły **trzy drobniejsze rzeczy**, wszystkie z prawdziwych danych:

1. **`preg_split('/\R/')` rozcinało nazwy w środku znaku** — poza trybem UTF-8
   bajt `0x85` jest dla `\R` znakiem nowej linii, a to **drugi bajt litery `ą`**.
   Nazwa „zażółć gęślą jaźń.txt" rozpadała się na pół. Podział idzie odtąd
   wypisanym wprost wzorcem, a trybu UTF-8 **nie wolno tu włączyć**: nazwa pliku
   na cudzej maszynie nie musi być poprawnym UTF-8, a `preg_*` oddaje wtedy
   `false` — czyli jeden zepsuty bajt kasowałby cały katalog.
2. **Katalog bez prawa wejścia mówił „sesja zerwana"** — `sftp` narzeka tam
   „remote readdir(…): Permission denied", a nie „Can't ls", więc wpadał pod
   ogólny wzorzec odmowy uwierzytelnienia.
3. **Usterka z kroku 48**: sprzątanie gniazda mistrza pytało `is_file()`, a gniazdo
   uniksowe **nie jest zwykłym plikiem** — warunek nie był spełniony nigdy.

#### Liczby z próby (kontener na pętli zwrotnej, maszyna projektu)

| Czynność | Czas | Klatek po 33 ms |
|---|---|---|
| uścisk dłoni (host znany) | **166 ms** | 6 |
| katalog startowy: `pwd` + `ls` jednym wywołaniem | **929 ms** | 29 |
| katalog o 14 wpisach | **927 ms** | 29 |
| katalog o **5000 wpisów** (419 KB wypisu) | **1236 ms** | 37 |
| katalog nieistniejący → `listing.missing` | 928 ms | — |
| katalog o prawach 000 → `listing.denied` | 961 ms | — |
| odczyt po zerwanej sesji → `listing.dropped` | **100 ms** | 3 |

**To jest liczba, od której zależy odpowiedź na zastrzeżenie startowe:** pięć
tysięcy wpisów kosztuje **jeden obieg** i 1,2 s, z czego 0,93 s to otwarcie
kanału w tym kontenerze (`ssh … true` kosztuje tyle samo), a rozczytanie wypisu
w PHP — **3,2 ms**. Wariant „stat na wpis" kosztowałby przy tym łączu **pięć
tysięcy obiegów**.

Sprawdzone przy okazji i zgodne z oczekiwaniem: nazwy z odstępem, cudzysłowem,
apostrofem i znakami spoza ASCII przechodzą nietknięte; `Ctrl`+`H` zamawia nowy
obieg i pokazuje o jeden wpis więcej; dowiązania widać jako dowiązania (`lstat`),
a nie jako cele; plik starszy niż pół roku dostaje rok zamiast godziny; sprzątanie
zostawia zero procesów i zero gniazd, a `~/.ssh/known_hosts` użytkownika jest po
próbie **bajt w bajt** taki sam jak przed nią (23 wiersze, ta sama suma MD5).

#### Pomiar

`bin/render-bench --loop` „przed i po" wobec wzorca po kroku 48: dwa przebiegi,
**+1,3%** i **+2,4%**, przy obciążeniu maszyny 0,12 i 0,17 na rdzeń wobec 0,11 we
wzorcu — czyli szum, i to szum, w którym drugi przebieg miał **wyższe obciążenie
niż wzorzec**. Narzędzie nie zgłosiło regresji w żadnym z nich. Wzorzec zapisany
jako `2026-08-15-po-kroku-49-loop.json`.

**Granica tej liczby jest ta sama, co w krokach 45, 46 i 48**: `--loop` nie woła
taktu modułów, więc mówi ona, że *reszta* taktu się nie zmieniła. O koszcie
samego taktu tego modułu świadczy to, na co się on składa: jedno `poll()`, które
z definicji nie blokuje, plus — raz na odczyt — rozczytanie wypisu, zmierzone na
**3,2 ms dla pięciu tysięcy wpisów**.

Scenariusza klatki krok **nie dokłada**; dwa powody pominięcia (panel i odczyt)
stoją w [docs/pomiary/README.md](../pomiary/README.md).

#### Czego krok nie dowiózł

- **Klatki pod XTermem nikt nie oglądał** — jak w krokach 46 i 48. Kolumny,
  podświetlenie filtra i pole zawężania sprawdzono prymitywami w testach
  i przebiegiem funkcjonalnym, ale nie okiem w prawdziwym terminalu.
- **Prawdziwej sieci nie było** — próba szła po pętli zwrotnej, więc czasy są
  dolną granicą, a zachowanie przy łączu wolnym albo zrywającym w środku odczytu
  pozostaje niesprawdzone.
- **Nazwa ze znakiem nowej linii** pokazuje się jako pierwsza linia swojej nazwy;
  wejść w nią się nie da. Granica znana, zapisana w parserze i w teście.
- **`SftpDirectoryService` nie ma testu jednostkowego** — sięga po singleton portu
  tłowego, którego nie da się podstawić. Sprawdzają go klasy czyste, na które
  został rozłożony (`SftpCommand`, `SftpListingParser`, `SftpFailureReader`),
  atrapa portu w `RemoteBrowserTest` i próba na żywym serwerze.

#### Rachunek końcowy

Testów **1918** (przybyło 69), PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag,
`make qa` zielone. **Żaden test nie otwiera połączenia sieciowego** — i jest to
zdanie, które ten krok musiał wywalczyć: pierwsza wersja oprzyrządowania **nie
podstawiała portu odczytu**, więc przebieg funkcjonalny z podstawioną sesją wziął
prawdziwą usługę i wypuścił procesy `sftp` do hosta z przykładowego wpisu książki.
Procesy ubito, przyczynę usunięto (`StubRemoteDirectory` w `ScreenFixture`),
a zdanie w tym miejscu znaczy odtąd to, co mówi.

Wejścia testów pochodzą z **prawdziwego przebiegu**, nie z ręcznego rachunku:
wypis w `SftpListingParserTest` skopiowano bajt w bajt z żywego serwera razem
z nazwami, które miały parser wywrócić — i dwie z nich go wywróciły.
