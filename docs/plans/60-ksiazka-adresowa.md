# Krok 60 — Książka adresowa: adres przestaje być własnością jednego modułu

> **Skąd ten krok.** Powstał 2026-08-18 na polecenie użytkownika, jako czwarty
> krok **Fazy XX** ([00-decyzje.md](00-decyzje.md), D96 — faza; **D104** — ten
> krok). Wchodzi **przed** rejestrami
> obrazów — dawnym krokiem 60, odtąd [61](61-rejestry-obrazow.md) — i przesuwa
> całą resztę planu o jeden. Powód kolejności jest ten sam, dla którego faza
> w ogóle powstała: rejestr obrazów to **czwarty spis adresów** w tej aplikacji,
> a książka ma stać, zanim ktoś zacznie pisać czwarty.

## Status

**Ukończony z zastrzeżeniem** 2026-08-18. Rozstrzygnięcia startowe:
[00-decyzje.md](00-decyzje.md), **D105** — cztery z pięciu pytań planu;
piąte (rejestry obrazów) należy do kroku 61 i implementacji nie blokowało.
**Dwa z nich odwróciły rekomendację planu** i zmieniły konstrukcję: wpis jest
pojemnikiem z własnym identyfikatorem, a pola dokładają moduły rozdziałami.

Zastrzeżenie — **dwie rzeczy nieobejrzane**, obie z tego samego powodu (reguła
17: pomiar i klatka wymagają zwolnionej maszyny, a o to prosi się użytkownika):
**oś `--loop` „przed i po"** nie została zmierzona i **klatki książki pod XTermem
nikt nie oglądał**. Pominięcie pomiarowe jest zapisane w
`docs/pomiary/README.md`, ale samego przebiegu „przed i po" nie zastępuje.

Trzy rozstrzygnięcia zapadły już przy zamawianiu kroku
([00-decyzje.md](00-decyzje.md), **D104**): książka wychodzi do **osobnego
modułu z kompletem elementów**, krok wchodzi jako **60** (a plan od dawnego 60
wzwyż przenumerowuje się o jeden), a **„dostępna dla rdzenia" znaczy widoczna
w rejestrach globalnych** — w oknie komend, w oknie kwerend i w menu `F9`, czyli
tak samo jak każdy inny moduł.
Rdzeń **nie** dostaje drogi, którą czyta dane modułu; D42 („rdzeń nie wie, czym
jest wpis") zostaje nietknięte, a `AddressBookPort` w rdzeniu **nie powstaje**.

## Cel

Adres, pod którym coś stoi, mieszka w aplikacji **raz** — we własnym module —
a moduły, które się pod niego łączą, **czytają go kwerendą i nie znają się
nawzajem**. Książka ma własny ekran, własne komendy, własne kwerendy, własne
zdarzenia i własną sekcję stanu; sesja zdalna przestaje być jej właścicielem
i staje się jej czytelnikiem, jak każdy inny.

Miarą powodzenia jest zdanie: **jeden wpis `biuro` otwiera sesję SSH i podnosi
tunel do demona Dockera, a jego adres poprawia się w jednym miejscu.**

Miarą drugą, wymierną: **odrzucenie modułu `ssh` nie zabiera użytkownikowi ani
jednego adresu.** Dziś zabiera wszystkie — `SshModule` deklaruje
`RequiresEnvironment` i maszyna bez klienta `ssh` nie ma jak nawet obejrzeć
książki, choć adresy w niej są potrzebne modułowi Dockera, który z klientem
`ssh` nie ma nic wspólnego.

## Trudność strukturalna — cztery rzeczy, z których trzy są starsze od tego kroku

**Pierwsza: moduł, który czytał własne dane obiektem, staje się obcym.**
`HostProfile` jest dziś wymieniony w **23 plikach** modułu Ssh — w sesji, obu
portach, obu usługach klienta, ekranie, łańcuchu okien, komendzie i widoku
zdalnego katalogu. Po wyniesieniu książki moduł Ssh dostaje z niej **wiersze
napisów** (reguła 15g), bo ładunek typowany należy wyłącznie do właściciela
kwerendy (`QueryResult::payloadFor()`). To nie znaczy, że `HostProfile` znika:
znaczy, że **wpis książki i cel połączenia to dwa pojęcia**, a nie jedno. Wpis
mówi „pod jakim adresem"; cel — „z kim, jako kto i czym się przedstawiając".
Reguła 15e mówi o takim powtórzeniu wprost i dopuszcza je dla **pojęć
dziedziny**; zakaz dotyczy mechanizmu, a mechanizm (książka, zapis sekcji) idzie
z rdzenia i **nie jest** powtarzany.

**Druga: reguła 11w przecina wpis na pół — i to jest treść kroku, nie skutek
uboczny.** Kwerenda `ssh.hosts` świadomie nie oddaje obcym odcisku klucza ani
ścieżki klucza prywatnego. Po wyniesieniu książki ta granica przestaje być
wewnętrzną sprawą jednego modułu i staje się **linią podziału danych**: adres
idzie do książki, materiał uwierzytelnienia zostaje u tego, kto się nim
przedstawia. Rachunek za tę decyzję jest już policzony i wychodzi zero:
**jedyny obcy czytelnik książki czyta dziś cztery pola** — `name`, `host`,
`user`, `port` (`EnvironmentScreen::resolveTunnelTarget()`), a piątego (`auth`)
nie czyta nikt.

**Trzecia: tożsamością wpisu jest nazwa, a nazwy trzymają w ręku obcy.** Wpis
tunelowy modułu Dockera niesie **nazwę wpisu książki jako napis**, a moduł Ssh
kluczuje nią ostatni oglądany katalog. Książka nie ma jak pójść za zmianą nazwy,
bo nie wie, kto ją zapisał u siebie — więc albo zmiany nazwy nie ma, albo ktoś
musi umieć naprawić cudze odniesienia. Ten krok wybiera pierwsze i mówi
dlaczego (patrz „Poza zakresem").

**Czwarta: przeprowadzka dotyczy żywych danych użytkownika.** Książka mieszka
w sekcji `ssh` dokumentu `~/.light-manager/state.json` (krok 59) razem
z katalogami zapamiętanymi per wpis. Krok rozdziela tę sekcję na dwie i robi to
**przy danych, które ktoś już ma** — czyli migracją leniwą i nieniszczącą,
wzorem D103: sekcja nieobecna czyta się ze starego miejsca, stare klucze
zostają nietknięte, a nowa sekcja powstaje przy pierwszym zapisie.

## Stan zastany (sprawdzony w kodzie 2026-08-18)

| Element | Stan |
|---|---|
| `Module/Ssh/Application/HostBook` | Kolekcja mutowalna w miejscu, oparta od kroku 59 na rdzeniowej `Application\State\Book`; tożsamość po nazwie własnej, kolejność dopisywania. Modułowi została wiedza, że ładunkiem jest `HostProfile`. |
| `Module/Ssh/Domain/ValueObject/HostProfile` | Siedem pól: nazwa, host, port, użytkownik, sposób uwierzytelnienia, ścieżka klucza, katalog zdalny. Samowalidacja wąskimi wzorcami (wartości wchodzą do wiersza polecenia). **Wymieniony w 23 plikach modułu.** |
| Kwerenda `ssh.hosts` | Ładunek `HostBookView` — wyłącznie właścicielowi; wiersze obcym: `name`, `host`, `port`, `user`, `auth`, `connected`. Pokolenie to **prawdziwy licznik** (`SshSession::revision()`), nie `VOLATILE`. Odcisku i ścieżki klucza w wierszach nie ma (reguła 11w). |
| Jedyny obcy czytelnik | `Module/Docker/Presentation/EnvironmentScreen::resolveTunnelTarget()` — przegląda **wszystkie** wiersze `ssh.hosts` w poszukiwaniu jednej nazwy i bierze `host`, `user`, `port`. Pola `auth` nie czyta nikt. |
| Sekcja `ssh` w `state.json` | Dwa klucze: `hosts` (książka) i `directories` (ostatni katalog per wpis, krok 49). Mechanizm zapisu — rdzeniowy `StateDocumentPort` (krok 59); usługa modułu zna wyłącznie **treść** sekcji. |
| `SshModule` | `RequiresEnvironment`: brak klienta `ssh` w `PATH` **odrzuca moduł**, a wraz z nim książkę. Skrót `Ctrl`+`S` otwiera dziś spis hostów. |
| `HostsScreen` | 502 wiersze. `Table` ze spisem; `Enter` łączy albo rozłącza, `F4` przestawia sposób uwierzytelnienia, `F5` odświeża, `F7` dodaje wpis z **jednego napisu** `[użytkownik@]host[:port]`, `F8` usuwa. |
| `ConnectFlow` + `ConnectCommand` | Łańcuch okien jest jeden dla ekranu i komendy (11n). Komenda **umie otworzyć okno** — zdolność `Presentation\Ui\Command\OpensOverlay` z kroku 47 (`overlayFor()`); samo `CommandOutcome` ma cztery postaci (`done`, `stay`, `opens(<ekran>)`, `quit`) i żadnej „otwórz okno". |
| Podpowiedzi argumentów | `SuggestsArguments` + `SuggestionSource::OnDemand` — `ssh.connect` podpowiada nazwy z książki. |
| Litery skrótów | Zajęte: `a` (audio), `b` (przeglądarka), `d` (opis pliku), `k` (k8s), `o` (Docker), `s` (Ssh). Zakazane: `c`, `h`, `i`, `j`, `m`, `z` (`ModuleRegistry::FORBIDDEN_CHARACTERS`). Wolnych zostaje czternaście. |
| `NoModuleKnowsAnotherModuleTest` | Chodzi po przestrzeniach nazw w `src/Module`; kontrola własna wymaga **co najmniej sześciu** modułów. |
| Pomiar | Ekran spisu hostów ma **zapisane pominięcie** (`docs/pomiary/README.md`): to `Table` w strefie środkowej, czyli treść scenariusza `columns`, a okna to `popup`, `progress` i `command`. Spis środowisk dostał w kroku 58 własny scenariusz `environments` (nagłówek tabeli i trzy role wierszy). |

## Zależności

- **Krok 48** — źródło całej materii: książka hostów, jej ekran, łańcuch okien
  i plik stanu. Ten krok **nie dowozi nowej funkcji użytkownikowi**, tylko
  przenosi istniejącą tam, gdzie ma odbiorców.
- **Krok 59** — rdzeniowa `Book` (porządek i tożsamość) oraz `StateDocumentPort`
  z sekcjami jednego dokumentu; bez nich krok musiałby powtórzyć mechanizm,
  czego reguła 15e zabrania wprost.
- **Kroki 53 i 54** — kwerendy jako jedyna droga odczytu i czynność przechodząca
  przez dwa moduły; to stąd bierze się cała droga „adres z książki do modułu".
- **Krok 58** — pierwszy odbiorca spoza modułu Ssh: cel tunelu SSH brany
  z książki trzema napisami (reguła 15g).
- **Krok 20** — kontrakt modułu; ten krok jest jego **siódmym** sprawdzianem
  i pierwszym, w którym moduł powstaje **wyłącznie po to, żeby dzielić dane**.
- **Krok 47** — `OpensOverlay`: komenda książki otwiera łańcuch okien wpisu.
- **Krok 46** — zdarzenia; zmiana wpisu ma być zauważalna bez odpytywania.
- **Kroki 27, 28, 30, 32, 40** — tabela, pytanie, filtr, menu i podpowiedzi
  stopki dla ekranu książki.
- **Kroki 55 i 57** — wskaźnik i schowek: ekran książki deklaruje te same
  zdolności, co spis hostów, bo adres jest treścią, którą się kopiuje.
- **Kroki 14 i 15** — ustawienia i napisy.

## Model i wysiłek

**Opus / xhigh.** Warunek `Fable` z przypisów ¹ i ² nie zachodzi: prymitywów nie
przybywa, słownik wejścia zostaje nietknięty, trzej tłumacze nie dostają ani
jednej linii.

Wysiłek trzymają dwie rzeczy, z których żadna nie jest rozmiarem kodu.
**Pierwsza:** krok rusza **trzy moduły naraz** — nowy, Ssh (23 pliki wymieniają
`HostProfile`) i Docker — a żaden z nich nie ma prawa zobaczyć typu drugiego.
**Druga:** przeprowadzka dotyczy **danych, które użytkownik już ma**; błąd
w migracji sekcji nie jest usterką wyglądu, tylko utratą książki adresowej.

## Zakres

### 1. Moduł i jego tożsamość

`src/Module/AddressBook/` z pełnym podziałem na warstwy, identyfikator
`address-book` (wzorem `file-info` — dwa słowa, myślnik), skrót `Ctrl`+`W`
(litera wolna; mnemonik: **w**pisy). Klasa modułu deklaruje:
`ProvidesScreen`, `ProvidesCommands`, `ProvidesQueries`, `ProvidesHelpTab`,
`ProvidesSettingsTab` i `DeclaresEvents`.

**Nie deklaruje `RequiresEnvironment` i to jest decyzja, nie przeoczenie**:
książka nie potrzebuje do działania niczego spoza aplikacji, a moduł odrzucony
zabrałby adresy wszystkim pozostałym. **Nie deklaruje `NeedsTick`** — nie ma
pracy w locie, a takt bez odbiorcy byłby polem, które ktoś weźmie za źródło
prawdy o klatce (warunek z D82).

### 2. Wpis jako obiekt wartości — sam adres

`Domain/ValueObject/AddressEntry`: **nazwa własna** (tożsamość), **host**,
**port**, **użytkownik**, **opis** (jedno zdanie użytkownika, do pokazania
w spisie). Samowalidacja wyjątkiem modułu deklarującym `DescribesProblem`
(reguła 8), wzorce **wąskie jak w kroku 48** — wartość wchodzi do cudzego
wiersza polecenia, a pierwszy znak nie może być myślnikiem, bo `ssh`
przeczytałby go jako opcję.

**Materiału uwierzytelnienia we wpisie nie ma i nie będzie**: ani hasła, ani
ścieżki klucza, ani odcisku, ani certyfikatu. Sposób, w jaki się przedstawiamy,
jest własnością tego, kto się łączy — moduł Ssh trzyma go u siebie, moduł
Dockera u siebie. Książka odpowiada wyłącznie na pytanie **gdzie**.

### 3. Książka i sekcja stanu

`Application/AddressBook` — na rdzeniowej `Book` (krok 59), tożsamość po nazwie,
kolejność dopisywania. `Application/Port/AddressBookPort` →
`Infrastructure/AddressBookStateService`, sekcja **`address-book`** dokumentu
`~/.light-manager/state.json`, klucz `entries`. Żadna ścieżka portu nie rzuca,
a wpis nie do przyjęcia **wypada, zostawiając resztę książki** (wzorzec
z `SshStateService`: jeden zepsuty wiersz nie odbiera całego spisu).

**Migracja — leniwa i nieniszcząca** (D103): brak sekcji `address-book`
w dokumencie każe przeczytać `ssh.hosts` ze **starej** sekcji i zamienić każdy
wpis na `AddressEntry`; pola `auth` i `keyPath` **zostają w sekcji `ssh`**
(punkt 7). Stare klucze zostają na dysku nietknięte, sekcja nowa powstaje przy
pierwszym zapisie.

### 4. Ekran książki

`Presentation/AddressBookScreen` — `Table` ze spisem (nazwa, adres, opis),
wzorem `HostsScreen`, i **bez jednej rzeczy, którą tamten ma**: bez łączenia.
Klawisze: `F7` dodaje wpis z jednego napisu `[użytkownik@]host[:port]` (ten sam
rozbiór, co dziś), `F4` zmienia podświetlony łańcuchem okien, `F8` usuwa za
`ConfirmOverlay`, filtr z kroku 30 zawęża po nazwie i adresie. Ekran deklaruje
`CopiesContent` (krok 57) i przyjmuje wskaźnik (krok 55) — adres jest treścią,
którą się kopiuje, i to jest jego najczęstsze użycie.

Górny pas mówi, **ile modułów widzi tę książkę** — nie z ciekawości, tylko
dlatego, że pusta książka na maszynie bez modułu Ssh musi wyglądać inaczej niż
książka, której nikt nie czyta.

### 5. Komendy

Cztery, wszystkie w przestrzeni `address-book.`:

- **`address-book.show`** — otwiera ekran (`CommandOutcome::opens()`); druga
  droga do tego, co `Ctrl`+`W`, z tego samego powodu, co `file-info.show`.
- **`address-book.add <nazwa> <adres>`** — dopisuje albo **zastępuje** wpis
  o tej nazwie; adres w postaci `[użytkownik@]host[:port]`. Zdolność
  `OpensOverlay` (krok 47) daje jej łańcuch okien wtedy, gdy argumentu brakuje —
  czyli komenda i `F7` prowadzą **jedną** czynność, a nie dwie (11n).
- **`address-book.remove <nazwa>`** — usuwa; pyta oknem, bo usunięcie wpisu
  bywa cudzą awarią (tunel Dockera wskazuje nazwę).
- **`address-book.rename`** — **nie powstaje** (patrz „Poza zakresem").

Podpowiedzi argumentów (`SuggestsArguments`, `SuggestionSource::OnDemand`) biorą
się z nazw wpisów — jedynego spisu nazw, które te komendy przyjmują.

### 6. Kwerendy

- **`address-book.entries`** — cała książka. Wiersze: `name`, `host`, `port`,
  `user`, `note`. Ładunek typowany (`AddressBookView`) — wyłącznie właścicielowi.
  Pokolenie **prawdziwym licznikiem**, jak w `ssh.hosts`: książka zmienia się
  w dwóch miejscach i oba biją licznik, więc warunek z D93 nr 1 zachodzi
  i wynik wolno pamiętać.
- **`address-book.entry`** z argumentem `name` — **jeden** wpis. Powstaje
  z policzonej potrzeby: dzisiejszy `resolveTunnelTarget()` przegląda w tym celu
  **całą** listę wierszy, bo innej drogi nie ma.

**Ani jedna kwerenda nie oddaje materiału uwierzytelnienia** — nie dlatego, że
go nie pokazuje, tylko dlatego, że go nie ma (punkt 2). To jest ta granica
reguły 11w, która po tym kroku przestaje wymagać czujności.

### 7. Moduł Ssh przestaje być właścicielem książki

Zmiany, każda z powodem:

- **`HostProfile` zostaje w module Ssh** jako **cel połączenia**, ale przestaje
  być wpisem książki: składa się z wiersza kwerendy `address-book.entry`
  (adres) i z własnego zapisu modułu (sposób uwierzytelnienia, ścieżka klucza).
- **`HostBook`, `HostBookView`, `HostBookPort` i `LoadedHostBook` znikają.**
  `SshSession::book()`, `add()`, `remove()` i `revision()` — razem z nimi.
- **Sekcja `ssh` traci klucz `hosts`, zyskuje `credentials`** — mapa
  „nazwa wpisu → sposób uwierzytelnienia i ścieżka klucza". Klucz `directories`
  (krok 49) zostaje bez zmian: to pamięć modułu, nie adres.
- **`SshScreen` pokazuje wpisy czytane kwerendą** wraz z tym, czego książka nie
  wie: z kim stoi sesja. `Enter` łączy, `F4` przestawia uwierzytelnienie
  (własna dana modułu), a `F7` i `F8` **schodzą z ekranu** — dopisanie
  i usunięcie adresu należy odtąd do książki.
- **`ssh.connect <nazwa>`** rozwiązuje nazwę przez kwerendę książki; nazwa
  nieznana kończy się tym samym zdaniem, co dziś.
- **Kwerenda `ssh.hosts` znika**, bo powtarzałaby cudzą odpowiedź. Zostaje
  `ssh.session` z nazwą wpisu, z którym stoi sesja.

### 8. Moduł Dockera pyta o jeden wpis

`EnvironmentScreen::resolveTunnelTarget()` przestaje przeglądać listę i pyta
`address-book.entry` **nazwą** — jedno wyszukanie zamiast przejścia po całej
książce. Nazwa kwerendy zmienia się w jednym miejscu w kodzie i w trzech
komentarzach; **ani jeden typ nie przechodzi granicy modułu** (reguła 15g),
a `DockerEnvironment` nadal niesie nazwę wpisu jako napis.

### 9. Zdarzenia, ustawienia, napisy, pomoc

- **Zdarzenia** (`AddressBookEvent`): wpis dopisany, wpis zmieniony, wpis
  usunięty. Słownik zamknięty konstrukcyjnie (11o''), więc wchodzi enumem modułu
  i deklaracją katalogu — rdzeń nie rośnie o nic. Odbiorcą jest ekran modułu
  Ssh (spis do przerysowania) i wpis tunelowy Dockera, który wskazuje nazwę
  właśnie usuniętą.
- **Ustawienia** — jedna pozycja: **kolejność spisu** (dopisywania /
  alfabetycznie). Pozycji „pytaj przed usunięciem" **nie ma**: usunięcie pyta
  zawsze, bo cudze odniesienie do nazwy jest niewidoczne z tego ekranu.
- **Napisy** (`lang/pl.php`, `lang/en.php`, przedrostek `module.address-book.`):
  nazwa i opis modułu, spis klawiszy, powody odrzucenia wpisu, opisy czterech
  komend i dwóch kwerend, pomoc.
- **Pomoc** (`ProvidesHelpTab`): czym jest książka, kto ją czyta, dlaczego nie
  ma w niej haseł ani kluczy, co się dzieje przy usunięciu wpisu, na który ktoś
  się powołuje.

### 10. Pomiar, przebiegi, dokumentacja

- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 59. Ekran książki
  to `Table` o trzech kolumnach z nagłówkiem — czyli **ten sam skład**, co
  scenariusze `columns` i `environments`; plan **rekomenduje pominięcie**
  z wpisem w `docs/pomiary/README.md`, a rozstrzygnięcie należy do użytkownika
  (w kroku 58 poszło odwrotnie i miał rację plan, nie rekomendacja).
- **Przebiegi:** `tests/Functional/AddressBookFlowTest.php` — dopisanie wpisu
  widać w spisie modułu Ssh **bez restartu**; usunięcie wpisu, na który wskazuje
  wpis tunelowy Dockera, kończy się zdaniem, a nie ciszą; migracja starej sekcji
  daje komplet wpisów, a `auth` i `keyPath` zostają w sekcji `ssh`; moduł Ssh
  odrzucony (brak klienta) **nie odbiera książki**. Żaden test nie uruchamia
  `ssh` ani `docker` — atrapy portów, jak w krokach 48–52.
- **Strażnicy:** `NoModuleKnowsAnotherModuleTest` przechodzi bez wyjątku,
  a jego kontrola własna podnosi próg z sześciu modułów na **siedem**.
  Dochodzi test przeszukujący spłaszczone wiersze obu kwerend za materiałem
  uwierzytelnienia (wzorem kroku 58).
- **Dokumentacja:** `docs/architecture.md` — książka adresowa jako pierwszy
  moduł istniejący po to, żeby dzielić dane, i granica „adres w książce,
  poświadczenie u czytelnika". `SKILL.md` — dopowiedzenie reguły 15g o zdanie:
  **dana dzielona przez kilka modułów dostaje moduł-właściciela, nie miejsce
  w rdzeniu**. `README.md` — skąd biorą się adresy.

## Poza zakresem

- **Sekrety w książce** — hasła, klucze, certyfikaty i odciski. Zostają u tego,
  kto się nimi przedstawia; książka nie staje się magazynem poświadczeń, bo plik
  stanu nim nie jest i **nie udaje**, że jest (zdanie z planu kroku 61).
- **Zmiana nazwy wpisu** — nazwa jest tożsamością, a odniesienia do niej trzymają
  obcy (wpis tunelowy Dockera, katalogi zapamiętane przez moduł Ssh) jako napisy,
  za którymi książka nie umie pójść. Zmiana nazwy bez naprawy tych odniesień
  psułaby po cichu; wejdzie, gdy pojawi się odbiorca umiejący je naprawić.
- **Import z `~/.ssh/config`** — czytanie cudzego formatu jest osobną pracą
  wielkości kroku (parser, `Include`, wzorce `Host *`), a książka ma najpierw
  mieć czytelników. Naturalny kandydat na krok następny.
- **Grupy, znaczniki i drzewo wpisów** — bez odbiorcy (reguła 13).
- **Przeniesienie do książki spisów środowisk Dockera i klastrów Kubernetesa** —
  te wpisy niosą pola **nie-adresowe** (certyfikaty TLS, ścieżka `kubeconfig`,
  nazwa kontekstu, przestrzeń nazw), a ich tożsamością bywa co innego niż adres.
  Książka adresowa nie jest ich następcą; wolno im natomiast **wskazywać** wpis
  książki nazwą, i tak właśnie robi dziś wpis tunelowy.
- **Droga, którą rdzeń czyta książkę** — rozstrzygnięte przy zamawianiu kroku:
  rdzeń widzi ją oknem komend, oknem kwerend i menu `F9`, a własnego portu nie
  dostaje.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/AddressBook/Domain/ValueObject/AddressEntry.php` | Moduł/Domain | Nowe — wpis wraz z samowalidacją. |
| `Module/AddressBook/Domain/Exception/InvalidAddressEntryException.php` | Moduł/Domain | Nowe — powody odrzucenia wpisu (reguła 8). |
| `Module/AddressBook/Application/AddressBook.php`, `AddressBookView.php` | Moduł/Application | Nowe — książka na rdzeniowej `Book` i migawka dla ekranu. |
| `Module/AddressBook/Application/Port/AddressBookPort.php`, `LoadedAddressBook.php` | Moduł/Application | Nowe — odczyt i zapis sekcji. |
| `Module/AddressBook/Application/AddressBookEvent.php`, `AddressBookSettings.php` | Moduł/Application | Nowe — trzy zdarzenia i jedna pozycja ustawień. |
| `Module/AddressBook/Infrastructure/AddressBookStateService.php` | Moduł/Infrastructure | Nowe — sekcja `address-book` i **migracja** z sekcji `ssh`. |
| `Module/AddressBook/Presentation/AddressBookModule.php`, `AddressBookScreen.php`, `EntryFlow.php`, `AddressBookQueries.php` | Moduł/Presentation | Nowe — moduł, ekran, łańcuch okien wpisu, fasada odczytu. |
| `Module/AddressBook/Presentation/Command/{Show,Add,Remove}Command.php` | Moduł/Presentation | Nowe — trzy komendy wraz z podpowiedziami nazw. |
| `Module/AddressBook/Presentation/Query/{EntriesQuery,EntryQuery}.php` | Moduł/Presentation | Nowe — `address-book.entries` i `address-book.entry`. |
| `Module/AddressBook/lang/pl.php`, `en.php` | Napisy | Punkt 9. |
| `Module/Ssh/Application/{HostBook,HostBookView}.php`, `Port/{HostBookPort,LoadedHostBook}.php` | Moduł/Application | **Usunięte** — książka przenosi się w całości. |
| `Module/Ssh/Application/SshSession.php` | Moduł/Application | Traci książkę i licznik jej pokoleń; cel składa z wiersza kwerendy i własnych poświadczeń. |
| `Module/Ssh/Infrastructure/SshStateService.php` | Moduł/Infrastructure | Klucz `hosts` → `credentials`; `directories` bez zmian. |
| `Module/Ssh/Presentation/{SshScreen,HostsScreen,ConnectFlow,SshQueries}.php` | Moduł/Presentation | Spis czytany kwerendą; `F7`/`F8` schodzą z ekranu. |
| `Module/Ssh/Presentation/Query/HostsQuery.php` | Moduł/Presentation | **Usunięte** — cudza odpowiedź nie jest powtarzana. |
| `Module/Ssh/Presentation/Command/ConnectCommand.php` | Moduł/Presentation | Nazwa rozwiązywana kwerendą książki. |
| `Module/Docker/Presentation/EnvironmentScreen.php` | Moduł/Presentation | `address-book.entry` zamiast przejścia po wierszach `ssh.hosts`. |
| `Presentation/Cli/Bootstrap.php` | Rdzeń | **Jedna pozycja na liście modułów** — i ani linii więcej. |
| `tests/Module/AddressBook/…`, `tests/Functional/AddressBookFlowTest.php` | Testy | Punkt 10. |
| `tests/Presentation/NoModuleKnowsAnotherModuleTest.php` | Testy | Próg kontroli własnej: sześć modułów → siedem. |
| `docs/architecture.md`, `SKILL.md`, `README.md`, `docs/pomiary/README.md` | Dokumentacja | Punkt 10. |

## Kryteria ukończenia

- **Jeden wpis, dwaj czytelnicy**: adres poprawiony w książce widać w spisie
  modułu Ssh i w wyborze celu tunelu Dockera **bez restartu aplikacji**.
- **Ani jeden typ nie przechodzi między modułami** — `NoModuleKnowsAnotherModuleTest`
  zielony, a moduły Ssh i Docker znają książkę wyłącznie z nazw kwerend i komend.
- **Migracja nie gubi ani jednego wpisu**: dokument stanu sprzed kroku daje po
  pierwszym uruchomieniu komplet adresów w książce, sposoby uwierzytelnienia
  w sekcji `ssh`, a stare klucze zostają nietknięte.
- **Brak klienta `ssh` nie zabiera adresów** — moduł Ssh odrzucony, książka stoi.
- **Poświadczenia nie wychodzą kwerendą** — sprawdzone testem przeszukującym
  spłaszczone wiersze obu kwerend.
- **Rdzeń urósł o jedną pozycję w `Bootstrap`** i ani o linię więcej (reguła 15).
- Napisy w obu językach, `bin/render-bench --loop` „przed i po" bez regresji,
  PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Pytania do rozstrzygnięcia

1. **Identyfikator i litera skrótu.** Plan pisze `address-book` i `Ctrl`+`W`
   (wzorem `file-info`; mnemonik „wpisy"). Warianty: `hosts` — krótsze
   w kwerendach (`hosts.entries`), ale kłamie, gdy w książce stanie adres bazy
   danych albo rejestru; `book` — najkrótsze, lecz myli się z rdzeniową `Book`
   z kroku 59. Litery wolne: `e`, `f`, `g`, `l`, `n`, `p`, `q`, `r`, `t`, `u`,
   `v`, `w`, `x`, `y`.
2. **Co niesie wpis.** Plan rekomenduje **sam adres** (nazwa, host, port,
   użytkownik, opis), a sposób uwierzytelnienia zostawia modułowi Ssh — bo
   jedyny dzisiejszy czytelnik obcy bierze dokładnie cztery pola adresowe,
   a poświadczenie każdego rozmówcy wygląda inaczej. Wariant przeciwny (wpis
   niesie też sposób uwierzytelnienia) jest o jedno pole wygodniejszy dla ekranu
   Ssh i o jedną granicę słabszy dla reguły 11w.
3. **Co zostaje z ekranu modułu Ssh.** Plan zostawia mu spis czytany kwerendą
   wraz ze stanem sesji, a dopisywanie i usuwanie przenosi do książki. Wariant
   tańszy: moduł Ssh traci ekran spisu w ogóle, a `ssh.connect` otwiera
   `ChoiceOverlay` z nazwami — mniej kodu, ale użytkownik traci widok „z kim
   stoję i co jest w książce" naraz.
4. **Czy książka ma zakładkę ustawień.** Plan daje jedną pozycję (kolejność
   spisu). Wariant przeciwny — brak zakładki — jest uczciwszy wobec zasady, że
   pozycja ustawień dla funkcji, której nie ma, jest obietnicą bez pokrycia.
5. **Czy rejestry obrazów z kroku 61 biorą adres z tej książki.** Odpowiedź
   „tak" zmienia zakres tamtego kroku (rejestr = wpis książki + poświadczenie
   u Dockera) i jest powodem, dla którego ten krok stoi przed nim. Odpowiedź
   „nie" zostawia rejestrom własny spis i **stawia wzorzec książki po raz
   czwarty**, co po D103 wymaga uzasadnienia.

## Dziennik realizacji

**2026-08-18 — kod, testy i dokumentacja.** Zakres wykonany, z **sześcioma
rzeczami wartymi zapisania** — wszystkie wyszły z rozstrzygnięć D105, których
plan nie przewidywał, albo z reguł, na które ten kod trafił pierwszy raz.

1. **Wpis jest pojemnikiem, a nie zestawem pól — i to zmieniło pół planu.**
   Plan rekomendował „sam adres" (nazwa, host, port, użytkownik, opis);
   rozstrzygnięcie D105 nr 2 i 3 zamieniło to na **identyfikator + nazwa + adres
   + rozdziały**, których pola deklarują moduły. Doszły przez to trzy klasy,
   których plan nie wymieniał: `AddressChapter`, `ChapterField` i `FieldKind`.
   Zysk jest większy, niż plan zakładał: moduł Ssh nie musiał **niczego** oddać
   ze swojego opisu hosta — port i login stoją w rozdziale `ssh`, a nie
   w polach książki, więc książka nie wie, co to port.
2. **Zmiana nazwy wpisu weszła do zakresu, choć plan ją wykluczał** — i to nie
   jest rozszerzenie zakresu, tylko zniknięcie powodu. Plan zabraniał jej,
   bo „odniesienia trzymają obcy, a książka nie umie za nimi pójść"; skoro
   tożsamością jest losowy identyfikator, odniesienia nie zauważają zmiany
   nazwy w ogóle. Zmiana idzie zwykłym `F4`.
3. **Rozdział zakłada się w takcie modułu, a nie przy pierwszym odczycie** —
   poprawka wymuszona przebiegiem, nie projektem. Pierwsza wersja deklarowała
   rozdział leniwie, przy pierwszym pytaniu o hosty; użytkownik zaczynający od
   `Ctrl`+`W` otwierał przez to wpis **bez pól rozdziału**, bo moduł Ssh nie
   zdążył się jeszcze przedstawić. Takt biegnie niezależnie od tego, na co
   użytkownik patrzy (11o'), więc jest jedynym miejscem bez tej kolejności.
4. **Fasada nie może zapamiętać rejestru komend w konstruktorze** — druga
   poprawka wymuszona przebiegiem i warta zapisania jako pułapka: rejestr komend
   wchodzi do `LoopState` **po** złożeniu modułów (`useCommands()`), a fasada
   powstaje razem z ekranem, czyli wcześniej. Zapamiętany w konstruktorze jest
   pusty na zawsze. `SshQueries` bierze odtąd oba rejestry ze stanu pętli
   **w chwili użycia**.
5. **Wpis powstaje po adresie, a pola rozdziałów dopisują się po nim** —
   trzecia poprawka z przebiegu, tym razem na regule z kroku 41: `PromptOverlay`
   na pustym polu **świadomie nic nie robi**. Łańcuch kończący się jednym
   zapisem stawał więc na pierwszym polu, które użytkownik chciał zostawić
   puste, a wpis nie powstawał wcale. Zapis po każdym ogniwie ma przy tym skutek
   uboczny, który okazał się zaletą: **`Esc` w środku zostawia wpis z tym, co
   już dostał**.
6. **Granica 11w wyszła z kroku mocniejsza, niż weszła.** Plan obiecywał, że
   materiał uwierzytelnienia nie wejdzie do wierszy kwerendy; po rozdzieleniu
   nie wchodzi **do książki w ogóle** — sposób uwierzytelnienia i ścieżka klucza
   zostały w sekcji `ssh`, kluczowane identyfikatorem wpisu, z drogą awaryjną po
   nazwie dla wpisów sprzed migracji (identyfikator powstaje losowo dopiero przy
   przenosinach, więc inaczej poświadczenia migrowanych hostów by się zgubiły).

**Co dokładnie powstało i co zniknęło.** Nowy moduł: 6 klas `Application`,
3 `Domain`, 1 `Infrastructure`, 9 `Presentation` (ekran, łańcuch okien, fasada,
cztery komendy, dwie kwerendy), dwa katalogi napisów po 65 kluczy. Z modułu Ssh
**zniknęły** `HostBook`, `HostBookView`, `HostBookPort`, `LoadedHostBook`
i kwerenda `ssh.hosts`; `HostProfile` **został** i jest odtąd celem składanym
z wiersza książki i własnych poświadczeń, a `HostTarget` rozbiera adres tak samo
jak wcześniej. Moduł Dockera zmienił **jedną metodę**: cel tunelu pyta
`address-book.entry` o jeden wpis zamiast przeglądać całą listę. Rdzeń urósł
o **jedną pozycję w `Bootstrapie`** i ani o linię więcej — kryterium spełnione.

**Testy:** 31 nowych (`AddressEntryTest`, `AddressBookTest`,
`AddressBookStateServiceTest`, `AddressBookFlowTest`), przełożone przebiegi
sesji zdalnej i katalogu zdalnego, przepisany `SshStateServiceTest`
(poświadczenia i katalogi zamiast książki), próg strażnika `NoModuleKnows…`
podniesiony z sześciu modułów na siedem. `make qa` zielone: **2412 testów,
8030 asercji**, PHPStan `max`, PHP-CS-Fixer.

**Czego nie zrobiono i dlaczego** — patrz „Status": pomiar `--loop` „przed
i po" oraz obejrzenie klatki pod XTermem czekają na zwolnienie maszyny przez
użytkownika (reguła 17). Rozstrzygnięcie 5 z „Pytań" (rejestry obrazów) zostaje
otwarte dla kroku 61.
