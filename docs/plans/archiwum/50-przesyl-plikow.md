# Krok 50 — Przesył plików: pobranie i wysłanie pracą kawałkową

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 48 i 49, jako ostatnia
> trzecia Fazy XVII ([00-decyzje.md](../00-decyzje.md), D84). To on domyka zdanie
> „menadżer plików umie SSH”: lista zdalnych plików, po której da się tylko
> chodzić, jest oglądaniem, a nie zarządzaniem.

## Status

**W trakcie.** Kroki 48 i 49 ukończone; rozstrzygnięcia startowe zapadły
2026-08-15 ([00-decyzje.md](../00-decyzje.md), D89).

## Cel

Plik ma się dać skopiować z hosta na dysk lokalny i z dysku lokalnego na host —
po kawałku, z paskiem postępu, z pytaniem o kolizję nazw i z przerwaniem, po
którym na dysku nie zostaje plik wyglądający na gotowy.

Miarą powodzenia jest zdanie: **`F5` na wpisie zdalnym pobiera go do katalogu,
w którym stoi przeglądarka, `F6` wysyła w drugą stronę, okno postępu mówi ile
i dokąd, a `Esc` przerywa pracę, nie zostawiając połówki pliku.**

## Zastrzeżenie do rozstrzygnięcia na starcie — kto pisze po dysku lokalnym

Reguła 15b brzmi bez wyjątków: **wszystko, co pisze po dysku, idzie przez port
rdzenia**. Pobranie pliku pisze po dysku lokalnym, więc albo łamie tę regułę,
albo musi przez taki port przejść — a żaden z trzech istniejących nie umie wziąć
źródła, którego nie ma na dysku.

| Wyjście | Cena |
|---|---|
| **(a)** rdzeń dostaje port „zapisz strumień do pliku” | rdzeń zaczyna wiedzieć, że istnieją źródła nielokalne — czyli dokładnie ta wiedza, której D42 mu odmawia |
| **(b)** moduł pisze sam, a wyjątek 15b dostaje **drugi nazwany przypadek** | wymaga jawnej zgody; granica musi być wąska: moduł pisze wyłącznie w pracy przesyłu i wyłącznie do katalogu wskazanego przez użytkownika. **Rekomendacja** |
| **(c)** ścieżkę strumienia (`ssh2.sftp://…`) podaje się rdzeniowemu `FileTransferPort` jako napis | kusi, bo port bierze „ścieżkę bezwzględną jako napis" — ale rozpoznanie systemu plików idzie tam przez **numer urządzenia** (`lstat()['dev']`), a `is_link()` i prawa dostępu na URI nie znaczą nic. Port zacząłby kłamać w miejscu, w którym dziś jest dokładny |

Wariant **(b)** jest rekomendowany, bo próba z reguły 15b jest tu niespełniona
wprost: funkcja wchodząca do rdzenia musi mieć **dwóch odbiorców i powtórzenie
o koszcie nieodwracalnym**. Odbiorca jest jeden — moduł `ssh` — a powtórzenia
nie ma żadnego.

## Zależności

- **Kroki 48 i 49** całkowicie: sesja i zdalny katalog powstają tam.
- **Krok 42** wzorcowo i najmocniej ze wszystkich — to jest ta sama praca
  z drugim rodzajem źródła. Stamtąd pochodzi komplet reguł, których krok nie ma
  prawa wymyślać od nowa: **liczenie przed pracą** (mianownik znany od pierwszego
  bajtu), **przystanek w środku pracy** na pytanie o kolizję (`Colliding`),
  **sprzątanie pliku zapisanego w połowie** przy przerwaniu i **źródło znika
  dopiero po potwierdzonym zapisaniu celu** (jeśli przesył ma wariant
  przenoszący — rozstrzygnięcie nr 4).
- **Krok 41** — `ProgressOverlay`, `PromptOverlay`, `ChoiceOverlay`, `RunsWork`.
  Praca zmieniająca dysk posuwa się w `GameLoop`, w fazie „aktualizuj stan”,
  **nigdy w rysowaniu** (piąta reguła D46).
- **Krok 23** — `ProgressBar`; rozmiar pliku znamy ze `stat`, więc pasek ma
  mianownik od początku i tryb „postęp nieznany” nie dostaje tu użytkownika.
- **Krok 21** przez `ReadsContext`, i to jest **legalna droga do drugiej strony
  przesyłu**: przeglądarka publikuje `ModuleContext`, więc moduł `ssh` zna
  katalog, w którym użytkownik stoi lokalnie, **nie sięgając do tamtego modułu**.
  Dokładnie po to kontekst istnieje (D40 P5).
- **Krok 47** — `OpensOverlay`: obie czynności dostają komendę i pozycję w menu
  `F9` za darmo.
- **Krok 46** — zdarzenia modułu (`ssh.transfer.done`, `ssh.transfer.failed`)
  dają dźwięk bez zmiany w rdzeniu.
- **Krok 43** miękko: zaznaczenie wielokrotne jest mnożnikiem tej czynności.
  Rekomendacja: praca bierze **listę źródeł od pierwszego dnia** (jak
  `FileTransferPort::begin()` w kroku 42), nawet jeśli zaznaczenia w panelu
  zdalnym jeszcze nie ma — wtedy dołożenie go nie kosztuje ani jednej zmiany
  w pracy.

## Model i wysiłek

**Opus / xhigh.**

Cena błędu jest tu **nieodwracalna** — to jedyna praca w projekcie, która pisze
po dwóch systemach plików naraz — a do trudności kroku 42 dochodzi jedna, której
tamten nie miał: **kawałek pracy trwa tyle, ile trwa sieć**. Budżet dobrany
w bajtach przestaje mówić cokolwiek o czasie klatki, a przerwane połączenie
w środku zapisu jest stanem, którego kopiowanie lokalne nie zna.

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| Drogi przesyłu w rozszerzeniu | Trzy: strumień `ssh2.sftp://` (czytany i pisany po kawałku), `ssh2_scp_recv`/`ssh2_scp_send` (**cały plik w jednym wywołaniu — do pętli się nie nadają**) oraz `ssh2_exec` |
| `FileTransferService` | Rdzeniowy wzorzec pracy: dwa etapy, dwie miary budżetu, pamięć odpowiedzi o kolizjach, sprzątanie połówki przy przerwaniu |
| Reguła 15b | Granicą jest **katalog `Infrastructure/FileSystem`**, a próba na przyszłość brzmi: dwóch odbiorców i powtórzenie o koszcie nieodwracalnym |
| `ModuleContext` | Niesie lokalną ścieżkę i zaznaczenie; publikuje przeglądarka, czyta każdy moduł z `ReadsContext` |
| `WorkProgress` | Licznik wolno podać **gotowym napisem**, gdy praca liczy w czymś innym niż sztuki (`12,3 MB`) — krok 42 |

## Zakres

### 1. Port przesyłu w module

`Module\Ssh\Application\Port\RemoteTransferPort`: `begin(list<string> $sources,
string $target, TransferDirection $direction)`, `advance(int $budget)`,
`resolve(TransferChoice $choice, ?string $newName)`, `state()`, `stop()` —
kształt wzięty z `FileTransferPort` co do metody, bo praca jest tą samą pracą.

**Lista źródeł, nie jeden wpis**, od pierwszego dnia (nauka z kroków 42 i 43).

### 2. Dwie strony i sposób wskazania celu

Pobranie: cel podpowiada `ModuleContext` (katalog, w którym stoi przeglądarka),
a użytkownik może go zmienić w `PromptOverlay`. Wysłanie: źródłem jest wpis
zaznaczony w przeglądarce — **też przez kontekst** — a celem katalog otwarty
w panelu zdalnym.

To jest cała odpowiedź na pytanie „skąd moduł zna drugą stronę”, i ona **nie
łamie reguły 15**: moduł czyta kontekst, a nie cudzy moduł.

### 3. Budżet kawałka mierzony czasem

Praca lokalna liczy bajty, bo bajt kosztuje tyle samo co poprzedni. Tutaj
kawałek to obieg sieci: rozmiar bloku dobiera się do przepustowości (rzędu
64–256 kB), ale **wyjściem z pętli kawałka jest zegar, nie licznik**. Rozstrzygnięcie
nr 2 mówi, ile milisekund taktu wolno oddać przesyłowi.

Konsekwencja, o której trzeba wiedzieć z góry: przy zerwanym łączu pojedyncze
`fread()` może zamrzeć na dłużej niż cały budżet — limit czasu strumienia
(`stream_set_timeout()`) jest częścią zakresu, nie ozdobą.

### 4. Kolizje, przerwanie, sprzątanie

Kolizja nazw pyta `ChoiceOverlay` (nadpisz / pomiń / zmień nazwę / przerwij,
z pamięcią odpowiedzi „dla wszystkich”), przerwanie usuwa plik zapisany
w połowie — **po obu stronach**, bo wysyłanie zostawia połówkę na hoście.
`Esc` w oknie postępu przerywa; wyjście z aplikacji w trakcie pracy sprząta
dwiema drogami (D47).

### 5. Postęp

`ProgressOverlay` z `WorkProgress`: nazwa pliku, licznik w bajtach podany
**gotowym napisem** (jednostki idą przez katalog napisów) i pasek z mianownikiem
znanym ze `stat`. Przy wielu plikach licznik mówi też, który to z ilu.

### 6. Pomiar

Praca dzieje się w fazie „aktualizuj stan”, nie w rysowaniu, więc scenariusza
klatki krok **nie dokłada** — powód pominięcia idzie do
[docs/pomiary/README.md](../../pomiary/README.md) tą samą drogą, którą zapisano go
dla operacji na plikach w kroku 41. Rozlicza się oś `--loop` „przed i po”: takt
z trwającym przesyłem nie ma prawa wyjść poza budżet.

Do dziennika kroku, poza narzędziem: **przepustowość osiągnięta wobec `scp`**
na tym samym pliku i tym samym łączu. Jeśli różnica będzie wielokrotnością,
znak to, że rozmiar bloku jest dobrany źle.

## Poza zakresem

- **Zapis po zdalnej stronie poza przesyłem** — zmiana nazwy, nowy katalog,
  usunięcie zdalne. Osobny krok, jeśli faza się przedłuży.
- **Wznawianie przerwanego przesyłu** — wymaga pamięci pozycji przeżywającej
  zamknięcie aplikacji, czyli osobnej rzeczy do zaprojektowania.
- **Przesył katalogu wraz z zawartością** — rozstrzygnięcie nr 3; rekomendacja:
  pliki w tym kroku, drzewa osobno, bo chodzenie po zdalnym drzewie to kolejne
  obiegi i kolejny etap liczenia.
- **`rsync`, `scp` jako proces potomny** — dostęp ma być w procesie (D84).
- **Przesył zdalny → zdalny** (jedna sesja, dwa katalogi) — nie ma odbiorcy.
- **Zachowanie praw i czasu zmiany** przy przesyle — rozstrzygnięcie nr 5.
- **Kosz i cofanie** dla operacji zdalnych — `TrashPort` jest portem dysku
  lokalnego i tak ma zostać.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Ssh/Application/Port/RemoteTransferPort.php` | Moduł/Application | **Nowy** |
| `Module/Ssh/Application/{RemoteTransferState,TransferDirection}.php` | Moduł/Application | **Nowe** — stan pracy i kierunek |
| `Module/Ssh/Infrastructure/RemoteTransferService.php` | Moduł/Infrastructure | **Nowa** — praca kawałkowa na strumieniach |
| `Module/Ssh/Presentation/RemoteTransfer.php` | Moduł/Presentation | **Nowa** — czynność w jednym miejscu dla klawisza i komendy (reguła 11n) |
| `Module/Ssh/Presentation/Command/{Download,Upload}Command.php` | Moduł/Presentation | **Nowe**, z `OpensOverlay` |
| `Module/Ssh/Presentation/RemoteScreen.php` | Moduł/Presentation | Klawisze przesyłu, okno postępu, `RunsWork` |
| `Module/Ssh/Presentation/SshModule.php` | Moduł/Presentation | `ReadsContext`, nowe zdarzenia w `DeclaresEvents` |
| `Module/Ssh/lang/{pl,en}.php` | Napisy | Nazwy czynności, jednostki, pytania o kolizję, powody niepowodzeń |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Drugi nazwany przypadek wyjątku 15b (jeśli wariant (b)) wraz z granicą; kawałek mierzony czasem |
| `docs/pomiary/README.md` | Dokumentacja | Powód pominięcia scenariusza |
| testy | Testy | Praca na atrapie portu (bez sieci): kolizja, przerwanie w połowie, zerwana sesja w środku zapisu, lista wielu źródeł, sprzątanie połówki po obu stronach |

## Do rozstrzygnięcia na starcie kroku

1. **Kto pisze po dysku lokalnym** — wariant (a), (b) czy (c) z zastrzeżenia.
   Wariant (b) znaczy **rozszerzenie wyjątku 15b** i wymaga zgody wprost.
2. **Ile milisekund taktu wolno oddać przesyłowi** i jaki jest rozmiar bloku.
3. **Czy katalogi wchodzą do zakresu**, czy krok przesyła wyłącznie pliki.
4. **Czy jest wariant przenoszący** (skopiuj i usuń źródło), czy wyłącznie
   kopiowanie. Przenoszenie znaczy usuwanie po drugiej stronie, czyli czynność
   nieodwracalną — a te w tym projekcie zawsze pytają.
5. **Prawa i czas zmiany** — przenosić czy zostawić domyślne.
6. **Zachowanie przy zerwanej sesji w środku pracy** — próbować połączyć się
   ponownie, czy przerwać i posprzątać (rekomendacja: przerwać; wznawianie jest
   poza zakresem).
7. **Klawisze** — `F5`/`F6` powtarzają układ znany z menadżerów dwupanelowych,
   ale w tej aplikacji `F5` znaczy dziś „odśwież” w panelu zdalnym (krok 49).

## Kryteria ukończenia

- Pobranie pliku z hosta do katalogu, w którym stoi przeglądarka, kończy się
  plikiem **identycznym co do bajtu** (sprawdzone sumą kontrolną) i wpisem
  widocznym po odświeżeniu panelu lokalnego.
- Wysłanie działa w drugą stronę, z tym samym sprawdzeniem.
- Okno postępu mówi, ile i dokąd; pasek ma mianownik od pierwszego bajtu.
- `Esc` przerywa pracę i **nie zostawia połówki pliku po żadnej ze stron**.
- Kolizja nazw pyta, a odpowiedź „dla wszystkich” zapamiętuje się na czas pracy.
- Zerwane łącze w środku pracy kończy się zdaniem w pasku stanu, nie wyjątkiem
  i nie zawieszoną pętlą.
- Klatka w trakcie przesyłu mieści się w budżecie — `bin/render-bench --loop`
  „przed i po” bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie otwiera
  połączenia sieciowego**.

## Dziennik realizacji

### 2026-08-15 — rozstrzygnięcia startowe i rozpoznanie

Jedenaście rozstrzygnięć ([00-decyzje.md](../00-decyzje.md), D89): siedem pytań
z sekcji powyżej, cztery wynikłe z rozpoznania i jedno **zadane dwa razy**, bo
wariant wybrany za pierwszym razem stracił adresata wraz z drogą techniczną fazy.

**Tabela „Stan zastany” z planu opisywała drogę, której już nie ma.** Wszystkie
trzy wiersze o `ext-ssh2` są bezprzedmiotowe po D87, więc pytania kroku zadano na
nowo `sftp`-owi, na żywym serwerze (kontener `atmoz/sftp:alpine`, ten sam co
w kroku 49) i **przed pierwszą linią kodu**. Rozpoznanie zdjęło z kroku obie
trudności, które plan zapowiadał jako główne:

- **budżet kawałka mierzony zegarem nie ma się do czego odnieść** — takt to jedno
  `poll()`, dokładnie jak przy listowaniu;
- **rozmiar bloku nie jest naszą sprawą**, bo bajtów nie przepisuje PHP (`-B`
  i `-R` bez wpływu na pętli zwrotnej: 32 MB w 1,10–1,20 s tak czy owak).

Dołożyło za to trzy fakty, które przesądziły o kształcie kodu:

| Fakt | Skutek dla kroku |
|---|---|
| `sftp` rysuje pasek postępu **wyłącznie na terminalu sterującym** (`progressmeter.c`: `getpgrp()` vs `tcgetpgrp()`); na potoku milczy nawet po poleceniu `progress` | postęp czyta **rosnący plik roboczy** (`stat`), więc jest dokładny przy pobieraniu i nieznany w środku wysyłanego pliku |
| zwykłe `rename` w `sftp` idzie rozszerzeniem `posix-rename@openssh.com` i **nadpisuje cicho** (kod zero na zajętej nazwie) | zatwierdzenie zdalne idzie `rename -l`, a cel zwalnia się **jawnie** i tylko po zgodzie |
| zerwana sesja zabija `sftp` **sygnałem**: kod 141, `stderr` **pusty** | powód podaje moduł z kodu wyjścia, nie z wypisu klienta |

**Zastrzeżenie startowe o wyjątku 15b okazało się bezprzedmiotowe** i to jest
najtrwalszy wynik rozpoznania: plik pisze `sftp` — potomek uruchomiony rdzeniowym
`BackgroundProcessPort` — a jedyne zapisy z PHP (zatwierdzenie zmianą nazwy,
skasowanie połówki) umie już `FileOperationsPort` z kroku 41. **Rdzeń kosztuje
w tym kroku zero zmian**, pierwszy raz w całej Fazie XVII.

### 2026-08-15 — wykonanie

**Kod.** Moduł urósł o port pracy (`RemoteTransferPort`), jej stan
(`RemoteTransferState`, `RemoteTransferStage`, `RemoteTransferItem`,
`TransferDirection`), usługę (`RemoteTransferService`), czynność o dwóch
wejściach (`RemoteTransfer`, reguła 11n), dwie komendy z `OpensOverlay`, zatrzask
kontekstu (`LocalPlace`) i rachunek rozmiaru wyjęty z ekranu (`RemoteSize` —
drugi użytkownik tej samej liczby). `SftpCommand` dostał trzy polecenia
(`download`, `upload`, `remove`), `SftpFailureReader` — **drugą tablicę wzorców**,
bo te same słowa znaczą przy przesyle co innego niż przy odczycie katalogu.

**Klawisze.** `F5` pobiera, `F6` wysyła, a odświeżanie listy przeprowadziło się na
`Ctrl`+`R`. Cena tej przeprowadzki jest zapisana w teście
(`RemoteShortcutsTest`): `Ctrl`+litera to przestrzeń skrótów modułów, więc moduł
ze skrótem `r` przejąłby klawisz po cichu — dokładnie tak, jak pilnuje tego
`BrowserShortcutsTest` dla `Ctrl`+`T` od kroku 31.

**Czego nie ma, choć plan to przewidywał.** Etapu liczenia (rozmiary przychodzą
z listą), budżetu kawałka w bajtach ani `stream_set_timeout()` — strumienia w PHP
nie ma. Zamiast limitu czasu dobranego z sufitu pracuje **wykrywanie zastoju**:
plik, który nie urósł przez 30 s, kończy pracę zdaniem „łącze milczy”, a limit
twardy (godzina) jest wyłącznie sufitem awarii.

#### Próba na żywym serwerze — prawdziwym kodem modułu

Osiem scenariuszy na kontenerze `atmoz/sftp:alpine` (port 2223), wołanych przez
te same klasy, które wołałaby aplikacja (`OpenSshSessionService`,
`RemoteTransferService`, `FileOperationsService`), z pętlą po 33 ms zamiast
`GameLoop`:

| Scenariusz | Wynik |
|---|---|
| pobranie 32 MB | **1028 ms**, 32 klatki, mianownik paska znany od pierwszej klatki |
| suma kontrolna | `sha256` lokalnie i zdalnie **identyczne** |
| plik roboczy po pracy | sprzątnięty; nazwa docelowa pojawia się dopiero na końcu |
| kolizja nazw | praca staje na `Colliding` **przed** uruchomieniem potomka; „pomiń” zostawia plik w celu nietknięty |
| przerwanie w połowie | ani połówki, ani pliku docelowego |
| wysłanie 8 MB | **1160 ms**, po drugiej stronie sam plik docelowy — bez `.lm-part` |
| wysłanie na nazwę zajętą bez zgody | odmowa (`transfer.nameTaken`), zdalna połówka **sprzątnięta** |
| zerwanie sesji w środku pobierania | `transfer.dropped` po **jednej klatce**, plik roboczy sprzątnięty |
| sprzątanie | zero procesów `sftp` po rozłączeniu |

**Przepustowość wobec `scp`** (pytanie z zakresu nr 6 planu): 32 MB przez
stojącego mistrza to 1,03 s kodem modułu, 1,10–1,20 s gołym `sftp` i 1,20 s
`scp` — czyli **parytet**, a nie wielokrotność. Rozmiar bloku jest więc dobrany
dobrze i nie ma czego stroić.

**Jedna usterka wyszła dopiero z próby** i nie jest usterką kodu, tylko granicą
środowiska: gniazdo `ControlMaster` to ścieżka uniksowa, więc `HOME` głębszy niż
~90 znaków wywraca **całą sesję** komunikatem `unix_listener: path too long`.
Próba dostała krótki katalog domowy; w aplikacji nie zachodzi, bo `HOME`
użytkownika jest krótki.

#### Pomiar

`bin/render-bench --loop` „przed i po” wobec wzorca po kroku 49
(`2026-08-15-po-kroku-49-loop.json`), na maszynie zwolnionej przez użytkownika:

| Przebieg | Zmiana | Obciążenie (wzorzec / teraz) |
|---|---|---|
| praca stojąca | **−2,3%** | 0,15 / 0,07 |
| **z przesyłem trwającym w tle** | **−1,1%** | 0,15 / 0,12 |
| drugi przebieg z przesyłem | **+0,1%** | 0,15 / 0,14 |

Narzędzie nie zgłosiło regresji w żadnym z nich; wszystkie trzy liczby leżą po
obu stronach zera, czyli w szumie. **Przesył w tle był prawdziwy**: przez ~110 s
prawdziwy kod modułu pobrał przez żywą sesję **107 razy plik 32 MB** (ok. 3,4 GB),
a nie udawał pracy zajętą pętlą.

Granica tej liczby jest ta sama, co w krokach 45, 46, 48 i 49: `--loop` nie woła
taktu modułów, więc mówi, że **reszta** taktu się nie zmieniła. O koszcie samego
taktu tego modułu świadczy to, na co się on składa: jedno `poll()`, które
z definicji nie blokuje, plus jedno `stat()` na pliku roboczym.

Scenariusza klatki krok **nie dokłada** — powód pominięcia stoi
w [docs/pomiary/README.md](../../pomiary/README.md), wraz z liczbą, której narzędzie
nie zna i znać nie ma: 32 MB przez stojącego mistrza to 1,03 s, czyli parytet
ze `scp`.

#### Czego krok nie dowiózł

- **Klatki pod XTermem nikt nie oglądał** — jak w krokach 46, 48 i 49. Okna
  przesyłu są złożone z komponentów rdzenia (`ProgressOverlay`, `ChoiceOverlay`,
  `PromptOverlay`) sprawdzonych prymitywami i przebiegami, ale nie okiem
  w prawdziwym terminalu.
- **Prawdziwej sieci nie było** — próba szła po pętli zwrotnej, więc czasy są
  dolną granicą. Zachowanie przy łączu wolnym pokazuje za to więcej niż w kroku
  49: pasek postępu dostał tam pięć próbek na 32 MB, bo dane szły szybciej niż
  klatki. Przy prawdziwym łączu próbek będzie tyle, ile klatek.
- **Zastój sprawdzono wyłącznie testem**, nie na żywym łączu: odtworzenie
  „łącze milczy, a potomek żyje” wymagałoby zawieszenia serwera w środku pracy.
  Zerwanie sesji — czyli przypadek częstszy — sprawdzono na żywo.
- **Wysyłanie nie ma postępu w bajtach** i to jest cena drogi, nie przeoczenie
  (D89 nr 2). Widać ją najmocniej przy jednym dużym pliku: okno mówi „wysyłam”,
  ale nie mówi, jak daleko.
- **Zaznaczenie wielokrotne nie ma po której stronie działać**: praca bierze
  listę od pierwszego dnia, ale panel zdalny zaznaczania nie ma, a `ModuleContext`
  nie niesie nazw zaznaczonych wpisów. Dołożenie jednego albo drugiego nie
  kosztuje w tej pracy ani jednej linii.

#### Rachunek końcowy

Testów **1952** (przybyło 34; nowe: `RemoteTransferServiceTest` — 14 przypadków
na atrapie procesu potomnego i **prawdziwym** porcie zapisu, `RemoteTransferTest`
— 10 na atrapie portu, `RemoteShortcutsTest`, sześć w `SftpCommandTest` i trzy
przebiegi funkcjonalne). PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag,
`make qa` zielone. **Żaden test nie otwiera połączenia sieciowego** —
`ScreenFixture` dostał czwartą atrapę portu modułu, z tego samego powodu, dla
którego w kroku 49 dostał trzecią, i z wyższą stawką: przesył nie tylko wychodzi
do sieci, ale **pisze po dysku**.
