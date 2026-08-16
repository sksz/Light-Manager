# Architektura i konwencje kodowania — Light Manager

Ostateczna, obowiązująca wersja ustaleń wypracowanych w fazie planowania
architektury i stylu (kroki 01–04 w
[docs/plans/00-index.md](plans/00-index.md)). Pełna historia decyzji,
uzasadnień i odrzuconych alternatyw: [docs/plans/00-decyzje.md](plans/00-decyzje.md).
Skrócone, operacyjne podsumowanie do automatycznego stosowania przez
Claude Code: `.claude/skills/light-manager-conventions/SKILL.md`.

Każda zmiana konwencji opisanych tutaj musi być odzwierciedlona jednocześnie
w tym dokumencie i w treści Skilla — nie mają rozjeżdżać się w czasie.

## 1. Warstwy (Domain-Driven Design)

Projekt stosuje pełne DDD z jednym bounded contextem: przeglądanie i
podgląd systemu plików w terminalu.

```
src/
├── Domain/              # od kroku 21 **słownik powłoki terminalowej**, nie plików
│   ├── ValueObject/     # Message, MessageTone, Preview, RendererMode, ScrollPosition
│   └── Exception/       # korzeń hierarchii, trzy wyjątki klatki, DescribesProblem
├── Application/
│   ├── UseCase/         # przypadki użycia orkiestrujące Domain przez Porty
│   ├── Ui/              # klatka, płaszczyzna, prymitywy, geometria (krok 18)
│   ├── Command/         # kontrakt komendy, parser wiersza, rejestr (krok 19)
│   ├── Module/          # kontrakt modułu w części danowej, rejestr (krok 20)
│   ├── Event/           # zamknięty słownik zdarzeń i ich rejestr (krok 46)
│   ├── Dto/             # obiekty transferu danych wejście/wyjście
│   └── Port/             # interfejsy portów wyjściowych
├── Infrastructure/
│   ├── Terminal/        # implementacje portów terminala
│   ├── Rendering/       # implementacje portu renderowania (Sixel, tekst, OpenGL)
│   ├── Imagick/         # adaptery na bibliotekę Imagick
│   ├── Glfw/            # tryb okienkowy (krok 34): okno GLFW, wejście, viewport
│   ├── Config/          # trwała konfiguracja (plik JSON w katalogu domowym)
│   ├── I18n/            # katalogi napisów, wybór języka, liczba mnoga
│   ├── Diagnostics/     # pomiar wydajności potoku (narzędzie bin/render-bench)
│   └── Support/         # wspólna infrastruktura (AbstractSingleton)
└── Presentation/
    ├── Ui/              # komponenty, kontenery, kontrakt ekranu, kursor
    │   ├── Component/   # Panel, Label, ListView, Table, Tabs, Choice, Toggle,
    │   │                # Button, Dialog, StatusBar, ImageBox, Spacer,
    │   │                # TextInput (krok 19)
    │   ├── Container/   # VStack, Slot, Span, Distribution
    │   ├── Module/      # zdolności modułu dotykające interfejsu (krok 20)
    │   └── Overlay/     # okna nakładane: CommandOverlay, MessageOverlay
    └── Cli/             # bootstrap, pętla gry, składanie klatki, ekrany,
                         # komendy rdzenia (Command/)

src/Module/              # moduły (krok 20) — po katalogu na moduł
├── Browser/             # menadżer plików: moduł domyślny i ostatniej szansy
│   ├── Domain/          # Directory, DirectoryPath, Entry, EntryType, Selection,
│   │                    # repozytorium katalogów, cztery wyjątki katalogu
│   ├── Application/     # sześć przypadków użycia nawigacji i podglądu
│   ├── Infrastructure/  # FilesystemDirectoryRepository, EntryComparator
│   ├── Presentation/    # BrowserModule, BrowserScreen, BrowserState,
│   │                    # Component/ (PathLine, PreviewBox), Command/ (jump)
│   └── lang/            # napisy modułu (pl.php, en.php)
└── FileInfo/            # pełny obraz stanu wpisu (krok 25)
    ├── Application/     # Dto/ (FileStat, sekcje, stan sumy), Port/ (inspektor,
    │                    # stat, suma kontrolna), UseCase/ (opis, miniatura)
    ├── Infrastructure/  # FileInspectorService, FileStatService, ChecksumService
    ├── Presentation/    # FileInfoModule, FileInfoScreen, FileInfoState,
    │                    # Component/ (PreviewPane)
    └── lang/            # napisy modułu (pl.php, en.php)
```

**Warstwa `Module` stoi na zewnątrz wszystkiego** i jest jedynym miejscem, do
którego rdzeń nie ma prawa sięgnąć inaczej niż przez kontrakt modułu. Moduł
powtarza wewnątrz podział rdzenia, ale **katalog warstwy pustej po prostu nie
powstaje** — `FileInfo` nie ma własnego słownika domenowego, więc nie ma
katalogu `Domain/`. Jego `Application/Dto` opisuje za to **wynik wywołania
systemowego** (`FileStat`), a nie regułę biznesową, i to jest różnica między
danymi a domeną.

**Moduł może mieć własną warstwę `Domain/`** (krok 21) i przeglądarka plików
jest pierwszym, który ją ma: `Directory` wraz z całym słownikiem katalogu zszedł
tam z rdzenia. Wyjątki takiego modułu **dziedziczą dalej po rdzeniowym
`DomainException`** — korzeń hierarchii zostaje w rdzeniu, bo to on ją łapie —
ale poza tym korzeniem domena modułu nie widzi domeny rdzenia. Może za to
zadeklarować `Domain\Exception\DescribesProblem` i podać klucz zdania dla
użytkownika; bez tego `ProblemPresenter` musiałby znać jej klasy z nazwy.

**Rdzeń nie wie, czym jest katalog ani wpis w systemie plików** i pilnuje tego
test chodzący po przestrzeniach nazw (`CoreKnowsNothingAboutFilesTest`).
`Domain/` rdzenia jest przez to chudy — pięć obiektów wartości i hierarchia
wyjątków — i **to nie jest niedopatrzenie**: to słownik powłoki terminalowej,
czyli tego, czym aplikacja jest po odjęciu wszystkich modułów.

#### Jedyny wyjątek: zapis na dysk (krok 41, D66/D75)

Od kroku 41 rdzeń **umie zmienić dysk** i jest to jawny, nazwany wyjątek od
reguły „nowa funkcja to moduł” oraz częściowe odwrócenie zasady wyżej. Powodem
jest druga reguła tej samej pary: „moduł nigdy nie sięga do innego modułu”
znaczyłoby przy dwóch odbiorcach (przeglądarka, opis pliku) **dwie kopie kodu
piszącego po dysku** — a powtórzone `unlink()` kosztuje utratę danych w dwóch
miejscach zamiast w jednym. Powtórzony rachunek praw dostępu
(`Entry::permissionsAsText()`) wolno było zostawić w dwóch miejscach, bo
kosztował dziesięć linii bez skutków ubocznych.

Granica tej wiedzy jest **wąska i szersza być nie ma prawa**:

| Rdzeń **wie** | Rdzeń **nadal nie wie** |
|---|---|
| ścieżka bezwzględna jako **napis** | czym jest wpis katalogu (`Entry`) |
| nazwa jako **napis** — bez oceny, czy jest poprawna | czym jest katalog (`Directory`, `DirectoryPath`) i jego ścieżka jako pojęcie |
| cztery czynności: zmiana nazwy, nowy katalog, usunięcie wpisu, usunięcie **listy** wpisów wraz z drzewami (krok 43) | sortowanie, ukrywanie, filtr, podgląd, **skąd wzięła się ta lista** |
| dwie czynności dłuższe od klatki: kopiowanie i przeniesienie (krok 42) | po co ta czynność zachodzi i co ma się odświeżyć potem |
| kosz: przeniesienie do niego, rezerwacja nazwy i przywrócenie (krok 44) | co robi klawisz domyślny i gdzie kosz leży — to pozycje ustawień **modułu** |
| stan pracy (`RemovalState`, `TransferState`) i stan pracy do pokazania (`WorkProgress`) | który panel na to patrzy i gdzie ma stanąć kursor |

Praktyczne skutki, których pilnuje test: `Entry`, `Directory`, `DirectoryPath`
i `EntryType` **nie mają prawa** pojawić się w sygnaturze niczego w
`src/Application` ani `src/Domain`; poprawność nazwy sprawdza **moduł**
(`Module/Browser/Domain/ValueObject/EntryName`), bo tylko on wie, czym jest
nazwa wpisu; rdzeń **nie rysuje** niczego z powodu operacji — okna, klawisze
i komunikaty zamawia moduł.

Kod: `Application/Port/FileOperationsPort`, `Application/Port/FileTransferPort`
i `Application/Port/TrashPort` (kontrakty),
`Infrastructure/FileSystem/FileOperationsService`, `FileTransferService`
i `XdgTrashService` (Singletony), `Domain/Exception/FileOperationException`
(niepowodzenie, które samo podaje zdanie dla użytkownika).

**Granicą wyjątku jest katalog `Infrastructure/FileSystem`, a nie jedna klasa**
(krok 42, D79 nr 1). Do kroku 42 usług było tam dwie razem z `SettingsService`
i docblock mówił „ostatnie miejsce, które ma prawo powstać”; kopiowanie dostało
własną usługę, bo jego stan — lista źródeł, cel, otwarte uchwyty, kolejka
i pamięć odpowiedzi o kolizjach — nie ma nic wspólnego ze stanem usuwania. Zasada
zostaje nietknięta co do treści: **wszystko, co pisze po dysku, idzie przez port
rdzenia**, a poza tym katalogiem nie pisze nic poza `SettingsService`
(własny plik konfiguracyjny) i `DesktopEntryInstaller` (wpis `.desktop`,
uruchamiany wyłącznie z `bin/`).

**Zbiór zaznaczonych jest własnością panelu, a nie katalogu** (krok 43, D80).
Mieszka w `BrowserState`, obok filtra i z tego samego powodu, którym krok 30
uzasadnił tamto miejsce: dwa panele otwarte na tym samym katalogu mają prawo
zaznaczać co innego, a katalog na dysku jest jeden. Trzy reguły, które z tego
wynikają i obowiązują każdą czynność:

1. **Pusty zbiór znaczy „wpis pod kursorem”**, a nie „nic” — inaczej każda
   operacja wymagałaby dwóch kroków tam, gdzie dziś wymaga jednego. Rachunek
   stoi w **jednym** miejscu (`BrowserState::operands()`), a nie w każdej
   czynności z osobna.
2. **Zbiór trzyma nazwy, nie numery**, więc przeżywa zawężenie filtrem; przycina
   się wyłącznie po **własnej** zmianie na dysku (`refresh()`), a ginie razem
   z katalogiem (`enter()`), jak filtr.
3. **Po operacji zaznaczone zostaje to, czego nie dotknęła** — wpisy pominięte
   i nieudane. To jedyna droga, którą użytkownik dowie się, co się nie udało,
   bez listy błędów, której aplikacja nie ma.

Rdzeń o zbiorze wie dokładnie tyle, ile mówi `ModuleContext` (trzy liczby),
i tyle, ile widzi port: **listę ścieżek**. Skąd ta lista się wzięła — z zaznaczeń
czy z kursora — port nie wie i wiedzieć nie ma prawa.

**Reguła zależności** — strzałki tylko „do środka”:

```
Presentation → Application → Domain
Infrastructure → Domain (implementuje Domain/Repository)
Infrastructure → Application (implementuje Application/Port)
Module → Presentation → Application → Domain
Module → Application → Domain
Module → Domain
```

`Domain` nie zależy od niczego innego w projekcie — żadnych bibliotek
zewnętrznych (Imagick, `pcntl`), żadnych klas-Singletonów. `Application`
zna wyłącznie interfejsy (`Domain/Repository`, `Application/Port`), nigdy
konkretnych klas `Infrastructure`. Dowiązanie interfejs → implementacja
następuje w bootstrapie `Presentation` (klasa `Bootstrap`, patrz §3).

**Moduł (krok 20)** podlega tej samej regule, z dwoma dodatkowymi zakazami:
nigdy nie sięga do `Infrastructure` rdzenia inaczej niż przez port i **nigdy nie
sięga do innego modułu** — moduły się nie znają. Kontrakt modułu jest z tego
powodu **podzielony na dwie warstwy**, a granicą jest to, czy interfejs wymienia
typ z `Presentation`:

| Warstwa | Co tam leży |
|---|---|
| `Application/Module` | `ModuleInterface`, `ModuleShortcut`, `ModuleContext`, `ContextEntryKind`, `ContextOrigin`, `ModuleSettingsTab`, `ModuleSetting`, `ModuleSettingKind`, `ProvidesSettingsTab`, `ProvidesCommands`, `NeedsTick`, `RequiresEnvironment`, `ListensToEvents`, `DeclaresEvents`, `ModuleRegistry`, `ModuleRejection` |
| `Presentation/Ui/Module` | `ProvidesScreen`, `ProvidesHelpTab`, `ReadsContext` |

**Kontrakt modułu nie zyskał w kroku 21 ani jednej metody.** Przeglądarka plików
— główna funkcja aplikacji — weszła w niego takim, jaki wyszedł z kroku 20, i to
był jego sprawdzian.

**W kroku 45 zyskał jedną zdolność: takt** (`NeedsTick`, D82 nr 1). Moduł, który
ją deklaruje, dostaje wywołanie **raz na klatkę, niezależnie od tego, co widać** —
i to jest pierwsza rzecz, którą rdzeń robi dla modułu z własnej inicjatywy.
Warunek, pod którym ta zmiana nie łamie D70, jest zapisany przy opisie modułu
dźwięku niżej: zdolności nie dokłada się dla wygody, tylko wtedy, gdy bez niej
funkcja nie istnieje.

**W kroku 46 zyskał dwie kolejne: odbiór i ogłaszanie zdarzeń**
(`ListensToEvents`, `DeclaresEvents`, D83). Ten sam warunek co wyżej i ta sama
odpowiedź: efekt dźwiękowy bez zdarzenia nie ma czego zagrać. Zdarzenia opisuje
rozdział „Zdarzenia aplikacji” niżej.

Powód podziału jest ten sam, co przy komendach: interfejs opisany
w `Application`, który wymieniałby `ScreenInterface`, sięgałby po klasę z warstwy
leżącej **na zewnątrz** niego. Stąd też skrót modułu jest daną (`ModuleShortcut`),
a nie `KeyBinding`iem — rejestr, który ma zostać w `Application`, musi umieć
porównać dwa skróty, nie widząc `Presentation`.

## 2. Słownik domenowy (ubiquitous language)

Domena **rdzenia** — słownik powłoki, w której moduły się rysują:

| Termin (PL) | Identyfikator | Blok DDD | Katalog | Opis |
|---|---|---|---|---|
| Tryb renderowania | `RendererMode` | Value Object (`enum`) | `Domain/ValueObject` | `Sixel` \| `TextFallback` \| `OpenGl` (od kroku 34). |
| Komunikat | `Message` | Value Object | `Domain/ValueObject` | Treść paska stanu wraz z tonem; `marked()` dokleja znak wiodący. |
| Ton komunikatu | `MessageTone` | Value Object (`enum`) | `Domain/ValueObject` | `Info` \| `Warning` \| `Error`; każdy ma własny znak (`·`, `!`, `×`). |
| Położenie okna | `ScrollPosition` | Value Object | `Domain/ValueObject` | Pierwszy widoczny wpis, liczba widocznych i liczba wszystkich — wejście do suwaka. |
| Podgląd miniatury | `ThumbnailPreview` | Value Object | `Domain/ValueObject` | Wygenerowana miniatura (dane + wymiary); `null` = brak podglądu. |

Domena **modułu przeglądarki** — słownik katalogu, od kroku 21 poza rdzeniem:

| Termin (PL) | Identyfikator | Blok DDD | Katalog | Opis |
|---|---|---|---|---|
| Ścieżka katalogu | `DirectoryPath` | Value Object | `Module/Browser/Domain/ValueObject` | Zwalidowana, bezwzględna ścieżka; rzuca `InvalidDirectoryPathException`. |
| Wpis | `Entry` | Value Object | `Module/Browser/Domain/ValueObject` | Niemutowalny opis elementu katalogu (nazwa, `EntryType`, rozmiar). |
| Rodzaj wpisu | `EntryType` | Value Object (`enum`) | `Module/Browser/Domain/ValueObject` | `Directory` \| `File`. |
| Zaznaczenie | `Selection` | Value Object | `Module/Browser/Domain/ValueObject` | Nieujemny indeks zaznaczonego `Entry` — **kursor**, jeden na panel. |
| Zbiór zaznaczonych | `MarkedEntries` | Value Object | `Module/Browser/Domain/ValueObject` | Zaznaczenie **wielokrotne** (krok 43): nazwy wraz z rozmiarami, gdzie `null` znaczy „katalog, rozmiaru nie ma”. Trzyma **nazwy, nie numery**, więc przeżywa zawężenie filtrem; suma pomija katalogi i osobna liczba mówi, ilu. |
| Fragment nazwy | `NameFilter` | Value Object | `Module/Browser/Domain/ValueObject` | Zawężenie listy podciągiem, bez rozróżniania wielkości liter (krok 30). |
| Katalog | `Directory` | **Agregat, Encja** | `Module/Browser/Domain/Aggregate` | Tożsamość = `DirectoryPath`. Agreguje `Entry` i `Selection`; mutowalny w miejscu (encje ≠ Value Objects). |

Od kroku 18 (D36) **`Domain` nie zna słownictwa rysowania**, a od kroku 21 — także
słownictwa plików. Pierwsze wyprowadziło się do `Application/Ui` i do komponentów,
drugie do modułu przeglądarki. Zostało to, co przeżywa każdą zmianę modułów:
komunikat, podgląd, tryb renderowania i położenie okna listy.

### Słownik interfejsu (od kroku 18)

| Termin (PL) | Identyfikator | Warstwa | Katalog | Opis |
|---|---|---|---|---|
| Klatka | `Frame` | Application | `Application/Ui` | Stos płaszczyzn w porządku nakładania — jedyne, co przechodzi przez `FrameRendererPort`. |
| Płaszczyzna | `Plane` | Application | `Application/Ui` | Niezależnie umieszczony plan obrazu: prostokąt i lista prymitywów. Spodnia to ekran, nad nią stoją okna nakładane. Flaga `opaque` każe rendererowi **wymazać prostokąt** przed narysowaniem — bez niej okno złożone z samej obwódki przepuszcza to, co pod nim. |
| Prymityw | `Primitive` | Application | `Application/Ui/Primitive` | Kształt gotowy do narysowania: `TextRun`, `TextMark`, `RoundRect`, `CornerBrackets`, `Bar`, `Bitmap`, `Scrollbar`. Słownik **zamknięty**; otwierany dotąd raz, w kroku 30. |
| Podświetlenie | `TextMark` | Application | `Application/Ui/Primitive` | Napis postawiony **na własnym tle** — ósmy kształt, dołożony w kroku 30 dla dopasowania filtra (D59). Niesie sam fragment, rolę pisma i rolę tła; tło obejmuje tyle kolumn, ile fragment ma znaków. |
| Zakres dopasowania | `TextSpan` | Presentation | `Presentation/Ui/Component` | Kawałek napisu — przesunięcie i długość **w znakach**. Wiersz niesie zakresy, a nie podzieloną treść: tylko komponent wie, od której kolumny zaczyna się napis i ile z niego zostanie po przycięciu. |
| Rola koloru | `Role` | Application | `Application/Ui` | Rola motywu (tło, obwódka, akcent, …). Prymityw niesie rolę, nie kolor. Ról jest **dwanaście**: jedenaście z kroku 13 plus `Marked` z kroku 43 — pierwsza dołożona od tamtego czasu i dołożona z konieczności, nie z wygody (D80 nr 5a): zaznaczenie potrzebowało koloru odróżnialnego naraz od tekstu, akcentu (katalogi) i czerwieni, a `Warning` jest w motywie Grafit **tym samym kolorem, co akcent** (jeden nasycony kolor, D25). |
| Prostokąt | `Rect` | Application | `Application/Ui` | Obszar w **siatce znakowej**; piksele zaczynają się dopiero w rendererze. |
| Komponent | `ComponentInterface` | Presentation | `Presentation/Ui` | Element interfejsu, który rysuje się w zadanym prostokącie: `Panel`, `Label`, `ListView`, `SectionList`, `ProgressBar`, `Tabs`, `Choice`, `Toggle`, `Button`, `Dialog`, `StatusBar`, `ImageBox`, `TextView`, `Spacer`. |
| Zwijana sekcja | `Section`, `SectionList` | Presentation | `Presentation/Ui/Component` | Grupa wierszy z etykietą i znacznikiem `▼`/`▶`. `Section` jest **daną** (jak `ListRow`), `SectionList` — komponentem: spłaszcza sekcje do wierszy, wycina okno i oddaje rysowanie `ListView`owi. Sekcje przewijają się **jak jedna lista**, więc wycinanie okna musi widzieć je wszystkie naraz. |
| Podział | `Split`, `SplitAxis` | Presentation | `Presentation/Ui` | Dzieli prostokąt na dwa i oddaje je dwojgu dzieciom, wzdłuż osi pionowej albo poziomej. **Nie tworzy drugiego ekranu** — widoczny ekran jest nadal jeden, a `F1`, `F2` i skrót modułu zastępują go razem z podziałem. Progi (`MINIMUM_COLUMNS`, `MINIMUM_ROWS`) mają tę samą naturę, co progi wysokości w `HudLayout`: mówią, co się jeszcze da czytać, a nie co się mieści. |
| Pasek postępu | `ProgressBar` | Presentation | `Presentation/Ui/Component` | Wypełniany tor z napisem w środku, w **dwóch trybach**: postęp znany (wypełnienie od lewej plus liczba procent) i nieznany (odcinek wędrujący tam i z powrotem, **bez liczby**). Tor rysuje się rolą `Surface`, wypełnienie `Accent`, a napis zmienia rolę tam, gdzie przechodzi przez wypełnienie. |
| Kontener | `VStack`, `Slot` | Presentation | `Presentation/Ui/Container` | Rozdziela miejsce między dzieci; `Slot` to **miara wraz z dzieckiem**. |
| Rozdział miejsca | `Span`, `Distribution` | Presentation | `Presentation/Ui/Container` | Jedna reguła na **obie osie** (krok 27): `Span` niesie minimum, rozmiar preferowany i kolejność ustępowania, `Distribution` je dzieli. Wołają ją `VStack` (wiersze) i `Table` (kolumny). |
| Tabela | `Column`, `TableRow`, `Table` | Presentation | `Presentation/Ui/Component` | Lista o wielu kolumnach wyrównanych w pionie. `Column` mówi, **czego chce**; szerokości liczy tabela raz na klatkę, dla wszystkich wierszy naraz. Stoi **obok** `ListView`, nie zamiast niego. |
| Widok tekstu | `TextView` | Presentation | `Presentation/Ui/Component` | Treść pliku w prostokącie (krok 29). Dostaje **gotowe wiersze**, a nie ścieżkę: nie czyta, nie dekoduje, nie zna pliku ani kodowania. Zawijanie łamie **po znaku, nie po słowie** (podgląd kodu ma pokazywać wcięcia) i **nie ma górnego progu**: wiersz dłuższy od całego prostokąta wypełnia panel, bo rysowanie kończy się na dolnej krawędzi tak czy owak (poprawka z 2026-08-12; wcześniej próg sprawiał, że najdłuższe wiersze jako jedyne się nie zawijały). Suwakowi oddaje kolumnę, zamiast kłaść go na treści jak `ListView` — i oddaje ją **z chwilą podania położenia**, a kolumnę numerów liczy z wysokości prostokąta, żeby szerokość treści nie zmieniała się wraz z treścią. Regułę tej szerokości wystawia `contentColumns()`, bo potrzebuje jej także ten, kto czyta plik. |
| Drzewo | `TreeNode`, `TreeView` | Presentation | `Presentation/Ui/Component` | Drzewo **już spłaszczone** (krok 31), czyli wpisane w listę wierszy. `TreeNode` jest **daną** (jak `ListRow` i `Section`) i nie ma ani wskaźnika na rodzica, ani listy dzieci; `TreeView` liczy wcięcie, prowadnice (`│`, `├─`, `└─`) i znacznik gałęzi, wycina okno, a rysowanie oddaje `ListView`owi. Wcięcie ustępuje **od lewej**, gdy nazwie zostałoby mniej niż `MINIMUM_LABEL` kolumn. |
| Kursor | `FocusableInterface` | Presentation | `Presentation/Ui` | Komponent przyjmujący klawisze; `handle()` oddaje `bool`, więc nieobsłużony klawisz wędruje wyżej. |
| Ognisko | `DeclaresFocus`, `FocusHint` | Presentation | `Presentation/Ui` | **Miejsce, na którym stoi kursor** — nazwane i zadeklarowane przez ekran (krok 40). `FocusHint` niesie klucz etykiety miejsca i jego wiązania; zdolność `DeclaresFocus` deklaruje się osobno, jak `NeedsTime`, bo ekran o jednym miejscu nie ma czego deklarować. Ogniska **nie da się odkryć**: drzewa komponentów aplikacja nie przechowuje. |
| Wiązanie klawisza | `KeyBinding` | Presentation | `Presentation/Ui` | Klawisz wraz z **dwoma** kluczami opisu — długim dla okna pomocy i krótkim dla paska stanu (krok 40; brak krótkiego znaczy „użyj długiego”). Jedno źródło dla obsługi, podpowiedzi w stopce i spisu w pomocy. |
| Podpowiedzi stopki | `StatusHints`, `Hint` | Presentation | `Presentation/Ui` | Trzy poziomy złożone w jeden ciąg: **miejsce z ogniskiem → ekran albo okno nakładane → klawisze globalne wraz ze skrótami modułów**. Ustępowanie idzie od końca, powtórzenia odsiewa zgodność klawiszy **i** klucza opisu, `F1` jest przypięty. Pozycja nie mieści się w całości — znika w całości. |
| Ekran | `ScreenInterface` | Presentation | `Presentation/Ui` | Treść **dwóch stref** klatki wraz z obsługą klawiszy: górnego pasa (`header()`) i środkowego panelu (`draw()`). Rdzeniowi zostają oprawa stref i pasek stanu. **Pas podglądu wyszedł z kontraktu w kroku 47** (D78): po D76 nie zamawiał go ani jeden ekran, a mechanizm bez odbiorcy łamie regułę 13 — więc `preview()`, strefa w `HudLayout` i jej próg zniknęły razem. |
| Strefa ekranu | `ScreenZone` | Presentation | `Presentation/Ui` | Zamówienie strefy skrajnej: klucz etykiety obwódki plus komponent z treścią. `null` znaczy „strefa nie powstaje, jej wiersze idą do środka”. |
| Kontekst sesji | `ModuleContext` | Application | `Application/Module` | Gdzie użytkownik stoi i co ma zaznaczone — **dane pierwotne** (napis, napis, enum, trzy liczby). Publikuje go ten, kto zna bieżące miejsce; czyta każdy ekran z `ReadsContext`. Od kroku 43 niesie także **zaznaczenie wielokrotne**: liczbę wpisów, sumę rozmiarów plików i liczbę katalogów, o które ta suma milczy (D80 nr 1). Odbiorcą jest moduł opisu pliku — mechanizm i użytkownik weszły jednym krokiem, jak każe reguła 13. **Od kroku 49 mówi ponadto, czyja jest ścieżka** (`ContextOrigin`: `Local` albo `Remote`), niesie podpis miejsca i trzy atrybuty zaznaczenia (rozmiar, czas zmiany, prawa) — bo odbiorca wpisu zdalnego nie ma jak dobrać ich sam: `lstat` opisałby lokalny plik o tej samej nazwie, a sieć nie pada w rysowaniu klatki (D88 nr 3). Atrybuty są `null`-owalne i to jest ich treść: wydawca, który ich nie zna, mówi „nie wiem”, a nie „zero”. |
| Okno nakładane | `OverlayInterface` | Presentation | `Presentation/Ui` | Płaszczyzna **nad** ekranem, która sama mówi, gdzie stanąć, i **zużywa albo przepuszcza** klawisz. Przepuszczony trafia wyłącznie do klawiszy globalnych — nigdy do ekranu pod spodem. |
| Okno pytające | `ConfirmOverlay` | Presentation | `Presentation/Ui/Overlay` | Okno nakładane, które **czegoś chce od wołającego** (krok 28). Decyzja wraca domknięciem podanym przy tworzeniu: po „tak” wykonuje się ono i oddaje **skutek okna** (`OverlayOutcome`, od kroku 41 — bo pytanie stoi w środku łańcucha okien). Ognisko startuje na „nie”, `Esc` znaczy to samo co „nie”, a drugie, opcjonalne domknięcie sprząta **po odmowie**. Od kroku 43 przyjmuje ponadto **liczbę do form mnogich**: pytanie o zbiór odmienia się przez nią w każdym języku słowiańskim, a `null` znaczy „pytanie bez liczby” i jest wartością domyślną. |
| Okno o nazwę | `PromptOverlay` | Presentation | `Presentation/Ui/Overlay` | Jedno pole tekstowe w `Dialog`u (krok 41): `Enter` zatwierdza, `Esc` odmawia, `Enter` na pustym polu nie robi nic. Wpisanego napisu **nie ocenia** — o tym, co jest poprawną nazwą, wie ten, kto wie, czym jest nazwa (moduł). Domknięcie oddaje `OverlayOutcome` (od kroku 42, tą samą drogą co `ConfirmOverlay`): okno stoi w środku łańcucha, bo wpisana ścieżka zaczyna pracę pokazywaną oknem postępu. |
| Okno wyboru | `ChoiceOverlay` | Presentation | `Presentation/Ui/Overlay` | Pytanie o **więcej niż dwie odpowiedzi** (krok 42): `Dialog` plus `ListView`, pozycje przychodzą kluczami katalogu, domknięcie dostaje identyfikator wybranej. `Esc` znaczy odpowiedź **ostatnią** — bo praca, która czeka na odpowiedź, nie może zostać z oknem zamkniętym milczkiem. Pierwszym pytaniem jest kolizja nazw przy kopiowaniu. |
| Okno pracy | `ProgressOverlay` | Presentation | `Presentation/Ui/Overlay` | Okno pracy dłuższej od klatki (krok 41) i pierwsze, które **działa samo**: deklaruje `RunsWork`, więc pętla pyta je raz na takt. Karmione ogólną daną `WorkProgress` — o plikach nie wie nic. Pasek pokazuje się dopiero wtedy, gdy praca zna swoją całość, a licznik składa **wołający**, jeśli praca liczy w czymś innym niż sztuki (krok 42: bajty zapisane jako `12,3 MB z 700 MB`). |
| Zdolność okna „prowadzę pracę” | `RunsWork` | Presentation | `Presentation/Ui` | Deklarowana osobno, jak `NeedsTime` i `DeclaresFocus` (krok 41). `advance()` posuwa pracę o kawałek i oddaje `OverlayOutcome` — więc okno może **zamknąć się samo** albo `replace()`em ustąpić miejsca kolejnemu. Pytanie pada w `GameLoop`, w fazie „aktualizuj stan”: praca zmieniająca dysk nie ma prawa dziać się w środku rysowania. |
| Karetka | `TextInput` | Presentation | `Presentation/Ui/Component` | Miejsce wpisywania **wewnątrz** komponentu — w odróżnieniu od kursora, który wędruje **między** komponentami. |
| Komenda | `CommandInterface` | Application | `Application/Command` | Czynność wywoływana po nazwie wraz z deklaracją argumentów. Nazwa nosi przestrzeń właściciela (`core.*`), a wynik wskazuje ekran **identyfikatorem**, bo `Application` nie widzi `ScreenInterface`. |
| Zdolność komendy „czego dotyczę” | `AppliesToSelection` | Application | `Application/Command` | Doklejana **obok** kontraktu, wzorem `SuggestsArguments` (krok 32): `appliesTo()` mówi, dla jakiego zaznaczenia komenda ma sens, `inputFor()` składa z kontekstu jej argumenty. Komenda bez tej zdolności nie wchodzi do menu kontekstowego — i to jest właściwa domyślna odpowiedź. |
| Menu kontekstowe | `MenuOverlay` | Presentation | `Presentation/Ui/Overlay` | **Widok na rejestr komend, nie drugi rejestr** (krok 32). Pokazuje wyłącznie te komendy, które deklarują `AppliesToSelection` i pasują do zaznaczenia; wybór wywołuje `execute()`, czyli tę samą linię, co okno komend. Składa się z `Dialog`u i `ListView` — komponentu nie dokłada. |

Granica między tymi dwiema połówkami jest jednozdaniowa i wynika z reguły
zależności: **komponent wie, jak wyglądać; prymityw jest tym, co z tej wiedzy
zostaje po przekroczeniu portu**. Renderery leżą w `Infrastructure` i
implementują port z `Application`, więc nie wolno im zobaczyć klasy
z `Presentation` — a prymityw musi być dla nich widoczny.

#### Stan żyjący między klatkami

**Komponent jest bezstanowy i powstaje na nowo w każdej klatce**, więc nie
zapamięta niczego, co użytkownik zrobił przed chwilą. Co ma przeżyć klatkę,
mieszka **obok** komponentu, a właścicielem jest ekran:

| Klasa | Katalog | Co pamięta | Od kroku |
|---|---|---|---|
| `ScrollWindow` | `Presentation/Ui` | Który wycinek listy jest widoczny i jak podąża za kursorem. | 18 |
| `SectionState` | `Presentation/Ui` | Które sekcje są zwinięte i na której stoi kursor. | 22 |
| `SplitState` | `Presentation/Ui` | Który panel podziału jest czynny — wraz z regułą, że wyłączony podział sprowadza ognisko na pierwszy. | 24 |
| `TreeState` | `Presentation/Ui` | Które gałęzie drzewa są rozwinięte i na którym węźle stoi kursor. | 31 |

Trzy z nich mają tę samą metodę `useContext(string)` i to nie jest przypadek:
zmiana kontekstu — inny katalog, inna zakładka, inny opisywany plik — zaczyna
oglądanie od początku. `SectionState` trzyma przy tym zwinięcie **pod kluczem
sekcji, a nie pod jej numerem**, żeby sekcja, która zniknęła z listy i wróciła,
wróciła w tym samym stanie.

`TreeState` powtarza tę regułę o wymiar głębiej i różni się od `SectionState`
trzema rzeczami naraz — wszystkie trzy wynikają z tego, że drzewo zmienia się
częściej niż lista sekcji:

- trzyma **rozwinięcia**, a nie zwinięcia: gałąź bez wpisu jest **zwinięta**,
  sekcja bez wpisu — **rozwinięta**;
- jego kursor jest **kluczem, a nie numerem**, bo numer wiersza zmienia każde
  rozwinięcie i zwinięcie czegokolwiek powyżej;
- **zwinięcie gałęzi przenosi na nią kursor**, zamiast zostawiać go na numerze,
  który przejął przypadkowy sąsiad.

Rozwinięcia przeżywają przy tym zmianę kontekstu — kursor nie. Klucz gałęzi jest
bezwzględny, więc po powrocie do poprzedniego korzenia znaczy dokładnie to samo,
i na tym stoi obietnica „drzewo wraca takie, jakie się je zostawiło”.

#### Ognisko deklaruje się, a nie odkrywa (od kroku 40)

Powyższa tabela ma **konsekwencję, o którą potyka się każdy, kto pyta „co ma
teraz kursor”**: skoro komponent powstaje w `draw()` i ginie razem z klatką,
to **drzewa komponentów nie ma w żadnym momencie poza tą jedną chwilą, gdy
klatka się składa**. Nie da się więc znaleźć elementu z ogniskiem, chodząc po
drzewie — bo nie ma po czym chodzić. Prawdziwi właściciele ogniska nie są przy
tym komponentami: `BrowserPanes` trzyma numer panelu, `SettingsCursor` numer
pozycji, `SplitState` samą stronę podziału.

Stąd kontrakt: **pyta rdzeń, odpowiada ten, kto ognisko trzyma** — czyli ekran
albo okno nakładane, przez zdolność `DeclaresFocus`. `FocusHint` niesie klucz
etykiety miejsca („Podgląd”, „Panel lewy”) i jego wiązania klawiszy; odpowiedź
liczy się **co klatkę**, bo ognisko przenosi się klawiszem, a pasek stanu ma
pokazać nowe miejsce w tej samej klatce, w której ono się zmieniło.

Zobowiązania są dwa i obowiązują w obie strony:

- każde wiązanie oddane w `focus()` musi wystąpić także w `bindings()`, bo okno
  pomocy zostaje **pełnym** spisem — ekran składa więc `bindings()` z wiązań
  miejsca **plus** własnych, a powtórzenia odsiewa `StatusHints`;
- każde wiązanie pokazane w stopce musi być w tym miejscu naprawdę obsłużone
  przez `handle()`, i odwrotnie — klawisz działający tu i teraz, a przemilczany,
  jest błędem. Pilnuje tego jeden test dla wszystkich ekranów i wszystkich
  położeń ogniska (`tests/Functional/StatusHintsFlowTest.php`).

Pasek stanu wolno przy tym urosnąć do **dwóch wierszy** — i jest to jedyne
miejsce, w którym `HudLayout` dostaje odpowiedź zależną od **treści**, a nie od
rozmiaru okna (`$wideStatus`). Wiersz zabiera się liście, nigdy pasowi podglądu,
i tylko powyżej progu liczonego z tym pasem; w niskim oknie podpowiedzi ustępują
pozycjami. Rachunek nie kręci się w kółko, bo szerokość treści strefy jest ta
sama w obu wariantach oprawy (`HudLayout::contentColumns()`).

#### Element żyjący własnym rytmem a takt pętli (od kroku 23)

Niektóre elementy zmieniają wygląd **bez udziału użytkownika**: karetka mruga,
a wypełnienie paska postępu wędruje. Reguła jest jedna i ma dwie części.

**Nikt nie wymusza przerysowania, bo nie ma czego wymuszać.** Pętla główna
rysuje klatkę w każdym takcie — 30 razy na sekundę — niezależnie od tego, czy
cokolwiek się zmieniło, i tak jest od kroku 09. Element ruchomy nie potrzebuje
więc żadnej ścieżki „obudź pętlę”: wystarczy, że w kolejnej klatce narysuje się
inaczej. Gdyby pętla kiedykolwiek zaczęła oszczędzać klatki przy bezruchu, to
**ta** zmiana musiałaby przynieść taką ścieżkę ze sobą — i rozliczyć ją wobec
elementów ruchomych, a nie odwrotnie.

**Zegar przychodzi z zewnątrz, nigdy z `microtime()` w środku.** Czas klatki zna
pętla i tylko ona (`LoopState::tick()`); do elementu wędruje przez
`Presentation\Ui\NeedsTime` — interfejs deklarowany osobno, jak `Resettable`,
więc ekran i okno bez ruchomej treści nie deklarują niczego. Składanie klatki
pyta o niego **ekran** i **okno nakładane**, zawsze przed narysowaniem. Powód
jest testowy: `microtime()` w komponencie zamieniłby ruch w coś, czego nie da się
sprawdzić bez czekania, a tak test podaje własną chwilę i ogląda element
w dowolnym miejscu cyklu.

Cena jest jedna i trzeba ją znać: **element ruchomy z założenia nie trafia do
pamięci podręcznej wierszy** (D34) — jego wiersz jest w każdej klatce inny, więc
rasteryzuje się od nowa. Dlatego pasek postępu ma własny scenariusz pomiaru.

#### Komponent nie czyta (od kroku 29)

`TextView` pokazuje treść pliku, a **pliku nie zna**: dostaje listę wierszy już
zdekodowanych, z rozwiniętymi tabulatorami i oznaczonymi znakami sterującymi.
Granica jest ta sama, co między komponentem a rendererem, tyle że po drugiej
stronie: *komponent wie, jak wyglądać* — a nie skąd wziąć treść.

Wszystko, co ma z wejściem-wyjściem wspólnego, zostaje po stronie modułu
(`TextPreviewPort` i jego usługa w `Module/FileInfo/Infrastructure`), bo to tam
mieszka wiedza o tym, co wolno przeczytać i jak długo. Rdzeń nie wie, czym jest
plik — reguła 15 obowiązuje tu tak samo, jak przy podglądzie obrazu.

**Odczyt idzie przesuwnym oknem, jak w edytorze**, i to jest rozstrzygnięcie
o wadze wzorca: w pamięci siedzą wyłącznie te wiersze, które właśnie widać,
a przewinięcie porzuca poprzednie i doczytuje następne. Konsekwencje, które
z tego wynikają i o których trzeba pamiętać przy każdym podobnym podglądzie:

- **Miejsce w pliku to bajt, nie numer wiersza** (`TextAnchor`) — tylko bajt
  pozwala usiąść w środku pliku bez przeczytania wszystkiego przed nim. Numer
  wiersza jedzie obok i liczy się przyrostowo.
- **Ile czytać, wiadomo dopiero przy rysowaniu**, bo budżet odczytu liczy się
  z geometrii panelu. Stąd podgląd powstaje w `draw()`, a przewinięcie
  zamówione klawiszem czeka na rozliczenie — dokładnie jak `ScrollWindow`
  rozdziela `scrollBy()` od `clamp()`.
- **Suwak liczy się w bajtach**, bo liczby wierszy pliku nie znamy i poznać jej
  nie chcemy: kosztowałaby przejście przez cały plik przy pierwszym pokazaniu.
- **Praca kawałkowa (D46) tu nie obowiązuje** i nie musi: jedno okno to
  kilkadziesiąt kilobajtów, więc mieści się w klatce z zapasem. Wzorzec z kroku
  25 dotyczy prac, których w klatce wykonać **nie da się**.
- **Bajt to nie znak** — podgląd czyta także UTF-16 i UTF-32, więc podział na
  wiersze szuka znaku nowej linii w kodowaniu źródła i **wyłącznie na granicy
  jednostki kodowej**. Bajty `0A 00` wypadają w UTF-16LE także w środku pary
  innych znaków; wzięte za koniec wiersza przesunęłyby kotwicę o pół znaku
  i wszystko po niej byłoby śmieciem. Wszystkie kotwice są z tego samego powodu
  wyrównane do jednostki, a bufor urwany budżetem docina się do niej.

#### Komponent dostaje drzewo spłaszczone (od kroku 31)

`TreeView` rysuje drzewo, a **drzewa nie zna**: dostaje listę `TreeNode`ów, w której
gałęzie zostały już rozwinięte do wierszy. Węzeł nie ma wskaźnika na rodzica ani
listy dzieci i to nie jest oszczędność — komponent, który sam schodziłby po
gałęziach, musiałby wiedzieć, **skąd biorą się dzieci**, a biorą się z odczytu
katalogu. Wracamy tym samym do granicy z kroku 29: *komponent wie, jak wyglądać* —
a nie skąd wziąć treść. Spłaszcza więc moduł (`BrowserTree`), dokładnie tak, jak
w kroku 22 spłaszczał ekran.

Konsekwencje, które z tego wynikają:

- **Węzeł niesie prowadnice, a nie samą głębokość** (`guides`, po jednej wartości
  logicznej na przodka). Z liczby poziomów nie da się narysować pionowej kreski:
  poziom przodka, który był ostatnim dzieckiem, musi zostać pusty. Głębokość jest
  przez to długością tej listy — dwa pola mówiące to samo rozjechałyby się przy
  pierwszym spłaszczeniu liczonym inaczej.
- **Spłaszczenie jest zapamiętane, nie liczone co klatkę.** Klatka, w której nic
  się nie zmieniło, kosztuje trzy porównania zamiast tysiąca konstruktorów — i to
  jest cała odpowiedź na pytanie, czy rozwinięta gałąź o tysiącu wpisów gubi
  klatkę.
- **Gałąź czyta się na żądanie i najwyżej jedną na klatkę.** Rozwinięcie klawiszem
  czyta od razu, bo kosztuje tyle, co `Enter` w liście i użytkownik właśnie o to
  poprosił. Gałęzie **odtwarzane** — po powrocie do katalogu, po przełączeniu
  wpisów ukrytych — dochodzą po jednej na takt, wzorcem pracy kawałkowej (D46):
  dziesięć odczytów naraz nie mieści się w klatce, a dziesięć klatek to jedna
  trzecia sekundy.
- **Drzewo pokazuje to, co przeczytało.** Pamięć odczytanych gałęzi jest trwalsza
  od korzenia, więc wejście katalog niżej i powrót nie kosztuje ani jednego
  sięgnięcia na dysk. Ceną jest brak śledzenia zmian w systemie plików — świadomy
  i wykluczony z zakresu kroku.

#### Rozmiar okna terminala nie jest stałą uruchomienia (od kroku 33)

**O rozmiar okna pyta się co klatkę i niczego się z niego nie zapamiętuje.**
`SIGWINCH` ustawia w `TerminalService` znacznik — tym samym wzorcem, co sygnały
zamknięcia — a `TerminalSizeService` zdejmuje go przy najbliższym odczycie
i mierzy ponownie: komórki ze `stty size`, piksele zapytaniem `ESC [ 14 t`
(ponawianym wyłącznie u terminala, który odpowiedział przy starcie; milczącemu
rozmiar komórki liczy się z poprzedniego pomiaru). Kontrakty `ViewportPort`
i `InputPort` (do kroku 34 pod nazwą `TerminalPort`) są nietknięte: składanie
klatki i renderer pytały co klatkę już wcześniej, więc świeżą odpowiedź
dostają, nie wiedząc, że coś zaszło.

Konsekwencje dla piszącego kod:

- **Nie zapamiętuj wierszy, kolumn ani pikseli między klatkami.** Prostokąty
  liczą się w każdej klatce od nowa z `ViewportPort`; stan żyjący między
  klatkami (`ScrollWindow`, `SectionState`, `SplitState`, `TreeState`) ścina się
  do pojemności przy rysowaniu i to wystarcza.
- **Pamięć podręczna zależna od rozmiaru ma rozmiar w kluczu** (D34) — wtedy
  zmiana okna unieważnia ją sama, bez ścieżki unieważnienia. Krok 33 niczego
  w pamięciach nie zmienił i to była teza tego wzorca, sprawdzona w praktyce.
- **Renderer sixelowy czyści ekran raz po zmianie** — jawny wyjątek od reguły
  „czyszczenie daje migotanie” (krok 08): reguła „kolejna klatka zamalowuje
  poprzednią” stoi na płótnie o stałym rozmiarze.
- **Okno zwężone poniżej sensu rysuje, co się zmieści** — strefy i kolumny
  ustępują wedle swoich reguł (`HudLayout`, `Distribution`), planszy zastępczej
  nie ma.

#### Prezentacja poza terminalem: tryb okienkowy (od kroku 34)

**Trzeci tryb renderowania otwiera natywne okno przez PHP-GLFW zamiast rysować
w terminalu** — wybierany flagą CLI `--window`, zanim cokolwiek dotknie
terminala, więc detekcja DA1 w ogóle nie startuje. Tryby terminalowe zostają
pierwszorzędne: `ext-glfw` nie wchodzi do `require` (jest w `suggest`),
a bez flagi nie ma żadnego wymogu.

Tor okienkowy to te same trzy porty z innymi implementacjami — i **nic ponad
to**: pętla, ekrany, moduły i komponenty nie wiedzą, że cokolwiek się zmieniło.

- **`InputPort`** (do kroku 34 `TerminalPort` — nazwa przeszła na neutralną,
  gdy kontrakt dostał drugie źródło, D53) → `GlfwInputService`: zdarzenia
  klawiszy i znaków GLFW wpadają do kolejki jako te same `KeyPress`,
  z pominięciem `KeySequenceParser`. Mapowanie kodów na `Key` żyje w czystym
  `GlfwKeyMapper` — bez jednego wywołania GLFW, testowalne bez okna.
  `Ctrl` i `Alt` przychodzą polem `mods`, nie bajtem sterującym ani sekwencją
  escape.
- **`ViewportPort`** → `GlfwViewportService`: framebuffer podzielony przez
  komórkę zastępczą (stałą do kroku 35, w którym zastąpią ją metryki fontu).
  Rozmiar czyta się co pytanie, **bez znacznika i bez ponownego pomiaru** —
  to uproszczenie wzorca z kroku 33, bo GLFW oddaje rozmiar tanio i w procesie.
- **`FrameRendererPort`** → `OpenGlFrameRenderer`: do kroku 35 **zastępczy**
  (tło w kolorze roli motywu + zamiana buforów, treść `Frame` świadomie
  ignorowana); pełne tłumaczenie prymitywów dowozi krok 35.

Reguły, których pilnują testy (`WindowedModeTouchesNoTerminalTest`):

- **Tor okienkowy nie dotyka terminala** — kod w `Infrastructure/Glfw`
  i renderer okienkowy nie mają prawa wymienić usług terminalowych, `STDIN`,
  `STDOUT` ani sekwencji sterujących. Terminal, z którego padło polecenie,
  zostaje nietknięty (sprawdzalne przekierowaniem STDOUT — zero bajtów).
- **Zamknięcie okna i sygnał zbiegają się w jednym miejscu taktu** — obie
  drogi prowadzą do tego samego `break`, a sprzątanie (`glfwTerminate`)
  idzie jak wszędzie dwiema drogami: jawnie w `Bootstrap::shutdown()`
  i funkcją zamknięcia procesu.
- **Zasób OpenGL ginie przed kontekstem, nie razem z procesem** (poprawka
  z kroku 39). Obiekty rozszerzenia zwalniają zasoby GL w destruktorach, a te
  wołają się przy sprzątaniu procesu — czyli **po** `glfwTerminate()`, kiedy
  kontekstu już nie ma; skutkiem jest naruszenie ochrony pamięci po ostatniej
  linii kodu. Dlatego twórca zasobu zamawia jego zwolnienie przez
  `GlfwWindowService::releaseBeforeClose()`, a `close()` wykonuje zamówienia
  w odwrotnej kolejności, zanim zniszczy okno. Dziś zamawia jeden —
  `VgContextService` ze swoim `VGContext`; `VGImage` z pamięci tekstur
  przeżywa kontekst bez szkody (sprawdzone). Reguła na przyszłość: **nowy
  długo żyjący obiekt rozszerzenia to nowe zamówienie**, a nie założenie, że
  zdąży zginąć sam.
- **Rozmiar startowy okna pochodzi z ustawień** (`windowColumns` ×
  `windowRows`, domyślnie 100×30 komórek, D53) — dlatego w torze okienkowym
  konfiguracja czyta się **przed** otwarciem okna; pułapki znanej z toru
  terminalowego tu nie ma, bo odczyt pliku terminala nie dotyka.
- Kontekst OpenGL to **3.3 core** (D53) — pod obie techniki rysowania
  rozważane w kroku 35; rytm klatek zostaje stałym taktem pętli
  z `glfwSwapInterval(0)`, żeby oba tory zachowywały się identycznie.

#### Okno pamięta, jak je ustawiono (od kroku 37)

Te same dwa klucze rdzenia (`windowColumns`/`windowRows`) są od kroku 37
**zarazem pamięcią rozmiaru**: okno zapisuje pod nie siatkę nadaną
przeciągnięciem rogu albo maksymalizacją, więc następny start zastaje je
takim, jakim je zostawiono (D67).

- **Rozmiar mierzy się w komórkach, nie w pikselach.** Klucze zostają jedne,
  a ekran ustawień nadal przełącza je strzałkami. Ceną jest okno zmieniające
  rozmiar w pikselach po zmianie fontu — świadoma, bo siatka jest tym, co
  użytkownik ustawiał.
- **Lista `WINDOW_*_CHOICES` przestała być zakresem dopuszczalnych wartości**
  i jest wyłącznie **przystankami strzałek**; wartości pilnują odtąd granice
  `WINDOW_*_MIN`/`MAX`. Strzałka z wartości spoza listy idzie do sąsiada
  w swoją stronę (`Settings::nextStop()`), a nie na początek listy — po
  przeciągnięciu rogu wartość spoza listy jest stanem zwykłym, nie awaryjnym.
- **Zapis następuje po uspokojeniu zmian, nie przy każdym zdarzeniu.**
  Przeciąganie rogu sypie zdarzeniami dziesiątkami na sekundę;
  `WindowSizeSettle` (czysty, bez GLFW) odnotowuje chwilę zmiany, a pytanie
  „czy już cisza” pada raz na takt, zaraz po `glfwPollEvents()`. Zmianę, która
  nie zdążyła się uspokoić, dopisuje `Bootstrap::shutdown()`.
- **Pamiętanie włącza się jawnie** (`GlfwWindowService::rememberSize()`, za
  pokazaniem okna). `bin/render-bench --window` przemierza w jednym przebiegu
  kilkanaście rozmiarów okna ukrytego i żaden z nich nie jest wyborem
  użytkownika — narzędzie pomiarowe nie ma prawa zapisać niczego do ustawień.
- **Pełny ekran** (`core.fullscreen` oraz `F11`) zapamiętuje położenie
  i rozmiar okna, bo `glfwSetWindowMonitor()` ich nie przechowuje. Powrót
  wymaga **dwóch rzeczy, nie jednej**: samo `glfwSetWindowMonitor()` oddaje
  obszar treści niższy o pasek tytułu (menedżer okien liczy podaną geometrię
  jako geometrię ramki), a poprawiające to `glfwSetWindowSize()` działa
  **dopiero po zakończeniu przejścia** — więc dopominanie się o właściwy
  rozmiar idzie z taktu na takt (`restoreAfterFullscreen()`, sufit sekundy).
  Rozmiar narzucony pełnym ekranem **nie jest** wyborem użytkownika i do
  ustawień nie trafia — ani w trakcie, ani w czasie powrotu. `F11` jest
  pierwszym klawiszem rdzenia, którego obecność zależy od trybu — w terminalu
  nie znaczyłby nic, a spis klawiszy pokazuje to, co działa tu i teraz.
- **Ikona okna idzie okrężną drogą, bo prostej nie ma**: rozszerzenie
  PHP-GLFW 2.2 nie wystawia `glfwSetWindowIcon`. Okno przedstawia się klasą
  (`WM_CLASS` z podpowiedzi `GLFW_X11_CLASS_NAME`), a wpis `.desktop` wraz
  z ikoną zakłada `bin/install-desktop-entry` — ikonę **rysuje z ról
  włączonego motywu**, więc w repozytorium nie leży ani jeden plik binarny.
  Warunkiem powodzenia jest zgodność `StartupWMClass` z `WM_CLASS`; pilnuje
  jej test.
- **Skala treści jest czytana i pokazywana, a nie stosowana** (rozstrzygnięcie
  nr 4 kroku 37): `glfwGetWindowContentScale` trafia do zakładki „Aplikacja”
  okna pomocy, bo maszyna projektu ma skalę 1.0 i przeliczanie komórki byłoby
  kodem, którego nie da się tu sprawdzić.

#### Trzeci tłumacz słownika: renderer OpenGL (od kroku 35)

**Ten sam słownik prymitywów tłumaczy się teraz na trzy sposoby**: Imagick →
Sixel, `CellBuffer` → ANSI, oraz — od kroku 35 — wprost na wywołania API
wektorowego rozszerzenia PHP-GLFW (NanoVG na GL3, D54). W trybie okienkowym
**Imagicka nie ma w ścieżce klatki wcale**, także w dekodowaniu podglądów:
piksele wchodzą natywnie przez `Texture2D::fromDisk()`.

Renderer niczego do słownika nie dokłada i to jest jego sprawdzian — jak
krok 21 był sprawdzianem kontraktu modułu. Zasady, które z tego wynikają:

- **Nowy prymityw obowiązuje odtąd trzy renderery naraz.** Kompletności
  tabeli tłumaczeń pilnuje `PrimitiveTranslationTableTest` — dla renderera
  okienkowego i sixelowego wymaga wpisu na **każdy** prymityw słownika.
  Tekstowy jest z tego wymogu zwolniony świadomie: nawias narożny i suwak
  nie mają odpowiednika w siatce znakowej, więc degraduje je do niczego.
- **Geometria jest lustrem toru sixelowego, nie nowym pomysłem.**
  `GlfwFrameMetrics` powtarza reguły `SixelFrameMetrics` — rozmiar pisma
  jako udział wysokości wiersza, obwódka biegnąca środkiem skrajnych
  wierszy, prawa krawędź liczona od prawej strony framebuffera. Rozjazd
  któregokolwiek z nich widać w klatce natychmiast.
- **Komórkę dyktuje font.** `VgContextService` mierzy szerokość znaku fontu
  o stałej szerokości (lista preferencji ścieżek TTF + `fc-match`, wzorem
  kroku 08) i z niej liczy komórkę; `GlfwViewportService` dzieli przez nią
  framebuffer. Dlatego okno rodzi się **ukryte**: rozmiar startowy z ustawień
  da się przeliczyć na piksele dopiero po zmierzeniu fontu, więc `Bootstrap`
  wymiaruje okno i pokazuje je raz, już poprawne.
- **Pamięć podręczna przenosi się na tekstury.** `VgTextureCache` trzyma
  zdekodowane podglądy z limitem i porządkiem LRU, kluczowane ścieżką wraz
  z czasem i rozmiarem pliku (wzorem `ThumbnailService`); wpisem jest także
  **nieudane dekodowanie**, inaczej pętla próbowałaby go 30 razy na sekundę.
  Atlas glifów utrzymuje NanoVG wewnętrznie — to okienny odpowiednik pamięci
  bitmap napisów z kroku 17.
- **Z przełączników jakości kroku 14 obowiązuje jeden**: `strokeAntialias`
  (→ `shapeAntiAlias`). `textAntialias` i `paletteColors` **nie dotyczą**
  toru okienkowego — NanoVG wygładza tekst zawsze, a palety indeksowanej nie
  ma wcale; to pierwszy renderer rysujący w pełnej głębi kolorów.

Pomiar wchodzi do `bin/render-bench` osią `--window` (okno ukryte hintem
`GLFW_VISIBLE`): te same scenariusze, inne fazy — „rysowanie” i „bufory”
zamiast trzech faz Sixela, bez kolumny kwantyzacji i bez bajtów. Podpis
konfiguracji niesie słowo `window`, więc wzorzec okienkowy nie ma jak zostać
porównany z sixelowym. **Pomiar toru okienkowego stawia barierę `glFinish()`
po zamianie buforów** — bez niej zegar mierzy czas *zlecenia* klatki
sterownikowi, a nie jej wykonania (różnica dwukrotna, zmierzona).

#### Ósmy prymityw: dlaczego słownik został otwarty (krok 30)

Słownik prymitywów był **zamknięty od kroku 18** i przez dwanaście kroków nikt
go nie ruszył — łącznie z krokiem 19, w którym karetka pola tekstowego udała
podświetlenie parą „wypełnienie plus napis”, żeby nowego kształtu nie dokładać.
Krok 30 otwiera go raz, na jawną zgodę użytkownika (D48), i dokłada **jeden**
kształt: `TextMark`, czyli napis na własnym tle.

Ważniejsze od samego dołożenia jest to, **czego nie dołożono**. Wyjściowa
propozycja planu — „samo tło pod fragmentem” — okazała się przy rozpisaniu
synonimem: prostokąt wypełniony rolą motywu **już jest** w słowniku dwa razy,
jako `Bar` z `Weight::Fill` i jako `RoundRect` bez obrysu. Ósmy kształt musiał
więc być czymś, czego żaden z siedmiu nie umie, i jest: **związaniem pisma
z tłem w jednej rzeczy**. Trzy konsekwencje, wszystkie zmierzone albo widoczne
w kodzie:

- **Tor sixelowy składa jedną bitmapę zamiast dwóch.** `compositeImage` kosztuje
  tyle, ile kształt, ale samo wywołanie kosztuje zawsze — a przy filtrze
  trafiającym w każdy wiersz listy wywołań jest tyle, ile wierszy.
- **Tor tekstowy degraduje kształt do atrybutu, nie do treści.** Tło i kolor
  pisma to dokładnie te dwa atrybuty, które komórka siatki znakowej ma, więc
  dopasowanie widać tam co do znaku tak samo, jak w torze graficznym. Odwracanie
  atrybutów, które plan kroku dopuszczał jako ostateczność, okazało się zbędne.
- **`TextRun` zostaje nietknięty**, a wraz z nim koszt wiersza bez dopasowania.
  To jest najważniejsze kryterium kroku 30 i pilnuje go zarówno test
  strukturalny (`TableTest`: wiersz bez zakresów oddaje te same podpisy, co
  przed krokiem), jak i para scenariuszy pomiaru `columns` i `highlight`.

Zakresy dopasowania niesie **wiersz**, nie gotowy podział na kawałki
(`TableRow::$marks`, klucz = numer kolumny; pusta tablica domyślnie).
Przesunięcie liczy się w **znakach**, bo rysuje się je w kolumnach — nazwa
`zażółć.txt` ma dziewięć znaków i trzynaście bajtów, a zakres liczony bajtami
wylądowałby w połowie znaku.

#### Dźwięk gra obok klatki (od kroku 36)

Muzyka jest **modułem** (`src/Module/Audio/`), a nie rozbudową rdzenia, i to jest
rozstrzygnięcie użytkownika ze startu kroku (D70) zgodne z regułą 15: nowa
funkcja dopisuje się modułem. Rdzeń kosztuje przez to dokładnie tyle, ile reguła
przewiduje — **jedną pozycję na liście w `Bootstrapie`** — i nie wie o dźwięku
nic ponad to: ani że gra, ani czym.

Moduł jest przy tym **sprawdzianem kontraktu z drugiej strony niż krok 21**:
tamten pytał, czy kontrakt udźwignie główną funkcję aplikacji, ten — czy
udźwignie moduł, **który nic nie rysuje**. Udźwignął bez zmiany: `shortcut()`
wolno oddać `null`, zdolności deklaruje się osobno, a moduł bez ani jednej z nich
jest legalny. Zostają dwie komendy (`audio.music`, `audio.volume`), zakładka
ustawień, zakładka pomocy i własne napisy — bez ekranu, bez skrótu i **bez ani
jednego komponentu**.

Granica, której ten krok nie przekracza, brzmi: **dźwięk nie wchodzi do ścieżki
klatki**. Silnik miksuje we własnym wątku, więc pętla główna, renderery
i komponenty nie dowiadują się, że cokolwiek gra. Gdyby moduł czegokolwiek od
nich potrzebował, byłby to znak, że stoi źle.

Port (`Module/Audio/Application/Port/AudioPort`) ma **dwie implementacje i to jest
cały mechanizm degradacji**: `GlAudioService` na `GL\Audio\Engine` oraz
`SilentAudioService` — pusty obiekt dla środowisk bez rozszerzenia `glfw`. Wybór
zapada **raz**, przy składaniu modułu, więc brak rozszerzenia nie jest
rozgałęzieniem w kodzie komend. Silnik audio **nie potrzebuje okna** (sprawdzone
na starcie kroku: startuje bez `glfwInit()` i bez kontekstu OpenGL), więc muzyka
gra także w obu torach terminalowych — zależność od Fazy IX przez to nie
stwardniała.

Trzy rzeczy, które warto znać, zanim się ten kod ruszy. **Referencja do `Sound`
musi przeżyć całą grę** — obiekt zebrany przez odśmiecacz zabiera ze sobą dźwięk,
a testu na to napisać się nie da. **`Sound::stop()` jest pauzą, nie
przewinięciem** — stąd jedna komenda-przełącznik zamiast pary „graj”
i „zatrzymaj”. **Silnik miksuje kilka dźwięków naraz** (sprawdzone
2026-08-14) — fakt potrzebny dopiero krokowi 46, ale rozpoznany wcześniej.

Sprzątanie idzie **dwiema drogami naraz** (D47): jawnie i przez
`register_shutdown_function` rejestrowaną przy starcie silnika. Testy silnika nie
uruchamiają **w ogóle** — test, który go uruchomi, gra muzykę na maszynie ciągłej
integracji i zostawia po sobie wątek; sprawdzać wolno wszystko przed pierwszą
prośbą o granie.

#### Takt modułu i playlista (od kroku 45)

Krok 45 **odwraca jedno zdanie kroku 36**: „autostartu nie ma, bo kontrakt modułu
nie zna cyklu życia”. Kontrakt zna go od tego kroku — a różnica, która na to
pozwoliła, jest jedna i musi być zapisana, bo bez niej wygląda to na zmianę
zdania (D71, D82 nr 1). W kroku 36 zdolność miała **jednego użytkownika
i wyłącznie dla wygody**: muzykę dało się uruchomić komendą, autostart był
udogodnieniem. Tutaj **bez wywołania spoza ekranu funkcja nie istnieje**:
playlista, która nie wie, że utwór się skończył, nie jest playlistą, tylko listą
ścieżek. `Presentation\Ui\NeedsTime` nie wystarcza i zostało to sprawdzone przed
pierwszą linią kodu: o czas klatki pyta `FrameComposer`, a pyta o niego **ekran
i okno nakładane**, czyli to, co akurat widać.

**Mechanizm rdzenia to jedna zdolność i jedna klasa.**
`Application\Module\NeedsTick` (`tick(float $now)`) deklaruje się osobno, jak
`ProvidesCommands`; `Presentation\Cli\ModuleTicker` odsiewa chętnych **raz, przy
składaniu aplikacji** i woła ich raz na klatkę z `GameLoop`, w fazie „aktualizuj
stan” — obok kawałka pracy okna (`RunsWork`), a nie w rysowaniu. Trzy reguły
obowiązują każdy przyszły moduł:

- **takt jest tani** — porównanie stanu, nigdy wejście-wyjście; praca dłuższa od
  klatki dzieli się na kawałki (D46);
- **takt niczego nie wymusza** — nie prosi o przerysowanie i nie zwraca skutku do
  pętli (reguła 11b w drugą stronę);
- **takt nie rzuca** — wyjątek modułu łapie `ModuleTicker` i stawia zdanie
  w pasku stanu, tą samą drogą, którą łapane są wyjątki ekranu; wyjątek jednego
  modułu nie zabiera taktu pozostałym.

Czas przychodzi **z zewnątrz**, i to nie jest ozdobnik: playlista mierzy nim
**karencję po starcie utworu** (pół sekundy), bo `play()` wraca, zanim wątek
miksujący odnotuje granie — takt tuż po starcie zobaczyłby „nie gram” i przeleciał
całą listę w ułamku sekundy.

**Playlista zastępuje klucz `track`.** Wybór utworu przestał być pozycją
ustawień, bo jednej ścieżki już nie wystarcza; dawna wartość **zasila playlistę
przy pierwszym uruchomieniu po zmianie** i dopiero wtedy zapisuje się plik.
Ustawienia modułu trzymają wyłącznie skalary, więc nośnikiem listy jest **własny
plik stanu modułu** `~/.light-manager/audio.json` (wzorem historii komend: zapis
przez plik tymczasowy i `rename()`, żadna ścieżka nie rzuca). Klucze, których ta
wersja nie zna, przeżywają zapis — krok 46 dołoży mapę hooków **kluczem, a nie
drugim plikiem**. Plik ruszony ręcznie daje pustą playlistę **wraz z powodem**,
pokazywanym w oknie modułu zamiast listy.

Przełącznik `loop` zamienił się w **tryb odtwarzania** (`PlaybackMode`: pętla
listy, zatrzymaj po utworze, powtarzaj utwór), a dawna wartość przekłada się na
nowy klucz bez pytania użytkownika o zdanie — do pierwszej zmiany w zakładce
rządzi nadal stary klucz, więc konfiguracja nie zmienia się bez jego udziału.
Powtarzanie utworu zapętla **silnik**, nie playlista. Pozycja wskazująca plik,
którego nie ma, **zostaje na liście** wyszarzona i podpisana, a wypada wyłącznie
z wyboru „co grać dalej” (D82 nr 6).

Okno modułu (`Ctrl`+`A`) **nie dokłada ani jednego komponentu rdzenia** i to jest
sprawdzian tego kroku, ten sam, który przeszło menu z kroku 32: całość to
`ListView`, `Label`, `TextInput` i `ScrollWindow`. Utwory wchodzą **trzema
drogami**: `F5` bierze wpis zaznaczony w przeglądarce przez `ReadsContext` (moduł
nie poznaje cudzego modułu, tylko ścieżkę), `F7` otwiera pole na ścieżkę, a
komenda `audio.add` działa także wtedy, gdy okna nie widać. Kolejność zmienia się
`Shift`+strzałkami — **nie `Alt`+strzałkami**, bo `Alt` jest w słowniku wejścia
dopuszczony wyłącznie przy literach (reguła 11j), a otwieranie słownika byłoby
drugą zmianą rdzenia w kroku, który ma ruszyć wyłącznie takt.

#### Efekty specjalne (od kroku 46)

Moduł dźwięku jest **pierwszym odbiorcą zdarzeń** i nie zna ani jednej ich nazwy:
dostaje napis, zagląda do mapy „zdarzenie → plik” i gra albo milczy. Zdarzenie
dołożone gdziekolwiek indziej pojawia się przez to w jego oknie bez ani jednej
zmiany w module.

Mapa mieszka **w tym samym pliku, co playlista** (`~/.light-manager/audio.json`,
klucz `hooks`) — na tym właśnie polegało rozstrzygnięcie D82 nr 3, podjęte krok
wcześniej. Porty są przy tym dwa (`PlaylistPort`, `EffectMapPort`) mimo jednej
usługi, bo odbiorcy są różni i żaden nie ma powodu widzieć cudzych metod.

Efekt gra **na muzyce, nie zamiast niej**: `AudioPort::playEffect()` sięga po
**drugi uchwyt `Sound`** z tego samego silnika, który miksuje oba (sprawdzone
przy planowaniu fazy). Uchwyt jest jeden, więc nowy efekt przerywa poprzedni —
przy dźwiękach trwających pół sekundy to jest wybór, a nie ograniczenie. Kursor
efektu cofa się przed każdym zagraniem, bo `play()` na przerwanym wznowiłby go od
miejsca przerwania.

Trzy rzeczy, które przy dokładaniu odbiorcy trzeba znać:

- **odbiór nie dotyka dysku** — mapę wczytuje **takt**, a dostępność plików
  przelicza się przy otwarciu okna; zdarzenie, które padło przed pierwszym
  taktem, milczy;
- **minimalny odstęp należy do odbiorcy** — trzymana strzałka daje trzydzieści
  zdarzeń kursora na sekundę, więc odtwarzacz efektów milczy przez 100 ms po
  każdym zagraniu **tego samego** zdarzenia; publikujący nie ma prawa wiedzieć,
  że ktoś zamienia jego zdarzenie na dźwięk;
- **kłopotu z odtworzeniem nie zgłaszamy nikomu** — jesteśmy w środku cudzej
  czynności, a zdanie w pasku stanu nadpisałoby to, które ta czynność właśnie
  o sobie powiedziała.

Okno modułu rośnie do **dwóch paneli** (`Split`, `SplitState`, `Tab`): po lewej
spis zdarzeń z przypisaniami (`Table`, trzy kolumny), po prawej playlista.
Spis składa się **ze słownika, a nie z mapy** — widać wszystkie zdarzenia, także
nieprzypisane, wyszarzone i z kreską. Podział zachowuje się przy tym **inaczej niż
w przeglądarce**: poniżej progu szerokości widać panel **z ogniskiem**, a nie
zawsze pierwszy, bo panele są tu dwiema różnymi rzeczami, a nie dwoma widokami
tego samego.

Wyciszenie i zabranie pliku to **dwie różne czynności** (spacja i `F8`):
przełącznik siedzi przy przypisaniu, a nie w ustawieniach, bo mapa i tak trzyma po
wierszu na zdarzenie, a pozycja w zakładce musiałaby powstać dla każdego z osobna.
W zakładce stoją za to dwie pozycje wspólne: **przełącznik uciszający wszystko
naraz** i **własna głośność efektów** — bo klik zmiksowany na poziomie muzyki
ginie pod nią albo krzyczy w ciszy.

#### Zdarzenia aplikacji (od kroku 46)

Aplikacja ogłasza **nazwane momenty**, a moduł może je odebrać i coś z nimi
zrobić. Mechanizm jest w rdzeniu ogólny — rdzeń nie wie o dźwięku ani o żadnym
innym odbiorcy — a nazwa klasy wyznacza jego granicę: **`EventRegistry`, nie
szyna**. Kolejek nie ma, priorytetów nie ma, zdarzeń odłożonych w czasie nie ma;
publikacja jest synchroniczna i kończy się, zanim wróci wołający. Rzecz jest
bliższa `CommandRegistry` niż czemukolwiek z podręcznika o zdarzeniach — łącznie
z regułą przestrzeni nazw, którą stamtąd powtarza co do joty.

**Słownik jest zamknięty, a jego rozszerzenie wymaga zgody użytkownika** — ta
sama reguła, co przy słowniku prymitywów (reguła 11k). Powód jest inny niż tam:
zdarzenie publikuje rdzeń albo moduł, a odbiera **ktoś zupełnie inny**, więc
każde nowe jest umową, której nie da się cofnąć bez zmiany w obu miejscach naraz.
Kryterium doboru: wchodzi zdarzenie, które publikujący **już zna z nazwy**, bo je
gdzieś raportuje albo przełącza.

**Zamkniętość jest wykonana konstrukcyjnie, nie regulaminowo**: nazwy pochodzą
z enumów (`Application\Event\AppEvent` dla rdzenia,
`Module\Browser\Application\BrowserEvent` dla przeglądarki), a deklaracja
katalogu powstaje z `cases()`. Publikacja i spis pokazywany użytkownikowi nie mają
przez to jak się rozjechać — a rozjazd byłby **niewidoczny**: wiersz, do którego
nic nie dochodzi, wygląda tak samo jak wiersz, do którego nic nie przypisano.

| Kto | Ile | Co ogłasza |
|---|---|---|
| rdzeń (`core.*`) | 5 | trzy tony komunikatu (`LoopState::report()` — jedyne miejsce, przez które przechodzą **wszystkie** zdania aplikacji), otwarcie okna nakładanego, wykonanie komendy |
| przeglądarka (`browser.*`) | 17 | ruch kursora, wejście do katalogu, zaznaczenie wpisu oraz **siedem czynności × udana/nieudana** |

Zdarzenie niesie **wyłącznie tożsamość** — nazwę i nic ponad nią (ta sama zasada,
którą kieruje się `ModuleContext`, D40 P5). Obiektu domeny modułu przez zdarzenie
nie przekazujemy nigdy, bo odbiorca musiałby wtedy poznać moduł, który je
publikuje.

Trzy reguły publikacji, wszystkie **wykonane w `EventRegistry::publish()`**,
a nie zostawione dobrej woli wołającego:

- **publikacja jest tania i nie rzuca** — wyjątek odbiorcy ginie w rejestrze, bo
  publikacja stoi w środku `report()` i w środku czynności na plikach, a te nie
  mają dokąd zgłosić cudzego kłopotu;
- **publikujący nie wie, kto słucha** — przy zerze odbiorców `publish()` kończy
  się na jednym sprawdzeniu w tablicy;
- **zdarzenie nie rodzi zdarzenia** — odbiorca próbujący publikować w trakcie
  odbioru zostaje zignorowany; bez tego pojedynczy błąd zapętliłby pętlę główną.

Odbiorca dostaje **napis, a nie typ**, i nie ma prawa niczego zwrócić: to nie jest
droga, którą moduł zmienia bieg aplikacji — od tego są komendy. Czasu odbiór też
nie dostaje; odbiorca, któremu jest potrzebny, bierze go z taktu (`NeedsTick`),
o który i tak prosi.

**Kosztem w rdzeniu jest jedna linia w `Bootstrapie`**
(`$state->events()->useModules($modules->accepted())`), a rejestr mieszka
w `LoopState` — obok kontekstu sesji i z tego samego powodu: stan pętli dostaje
**każdy** moduł, więc publikacja nie kosztuje ani jednego argumentu więcej.

**Krok 46 odwrócił przy tym jedno zdanie własnego planu** i warto wiedzieć,
dlaczego: plan mówił „rdzeń publikuje, moduł odbiera”, a zdarzenia modułów miał
w wykluczeniach. Rozpoznanie w kodzie pokazało, że wszystkie zdania modułów
schodzą się w `LoopState::report()` z tonem — trzema zdarzeniami rdzenia da się
więc odróżnić powodzenie od awarii, ale **nie da się odróżnić kopiowania od
usunięcia**. Efekt przypisany do „zakończonego kopiowania” wymaga, żeby to
kopiowanie samo o sobie powiedziało (D83, rozstrzygnięcia 1–2).

#### Jeden ekran, dwa panele (od kroku 24)

Podział ekranu **nie znosi zasady „jeden ekran naraz”** i to jest jego
najważniejsze rozstrzygnięcie. `ScreenStack` ma nadal dno i jedno piętro nad nim,
`ScreenInterface` ma nadal sześć metod, a `InputHandler` nadal oddaje klawisz
jednemu ekranowi. Podział dzieje się **wewnątrz** ekranu: `F1`, `F2` i skrót
modułu zastępują go w całości, razem z oboma panelami.

Wynika z tego reguła własności: **podział należy do modułu, nie do rdzenia.**
Rdzeń daje klocek (`Split`) i pamięć ogniska (`SplitState`); to, czy dwa panele
w ogóle powstaną, co w nich stoi i którym klawiszem chodzi ognisko, rozstrzyga
ekran — a ustawienia podziału leżą w podprzestrzeni modułu, nie w kluczach
rdzenia.

Jeden wyjątek od podziału obowiązków musiał przy tym powstać i ma własny
interfejs: **`Presentation\Ui\DrawsOwnFrame`**. Rdzeń rysuje obwódki stref (reguła
kroku 21), ale ekran podzielony potrzebuje **dwóch** obwódek zamiast jednej,
a rdzeń nie wie, który panel jest czynny, więc nie ma czym pokazać ogniska.
Ekran deklarujący ten interfejs dostaje **cały** prostokąt strefy i oddaje własną
oprawę; odpowiedź zależy od ustawień i od szerokości okna, więc jest metodą,
a nie samą deklaracją klasy. `ScreenInterface` zostaje przez to nietknięty po raz
trzeci — tą samą drogą, którą idą `Resettable`, `ReadsContext` i `NeedsTime`.

**Metoda oddaje prymitywy, a nie rysuje na miejscu, i to jest w niej
najważniejsze.** Rdzeń kładzie je na płaszczyźnie **spodniej** — tej samej, którą
renderer pamięta między klatkami (krok 17, dźwignia 4). Powód jest zmierzony,
a nie estetyczny: obwódka z wygładzanym obrysem kosztuje **około 13 ms**, więc
dwie ramki rysowane co klatkę zabierały 27 ms z 33 ms budżetu. Po przeniesieniu
na płaszczyznę spodnią kosztują tyle, co pierwsza klatka po zmianie — a pamięć
odświeża się sama, bo podpis płaszczyzny obejmuje każdy prymityw: przeniesienie
ogniska albo zmiana katalogu zmienia podpis i oprawa powstaje na nowo raz.

Zasada ogólniejsza, którą to wyraża: **wszystko, co między klatkami się nie
zmienia, należy do płaszczyzny spodniej — niezależnie od tego, kto to narysował.**

#### Dwa wejścia do jednego rejestru komend (od kroku 32)

Menu kontekstowe (`F9`) jest **widokiem na `CommandRegistry`, a nie drugim
zbiorem czynności** — i to nie jest opis implementacji, tylko warunek, pod
którym w ogóle powstało. Zbiór działań, który trzeba uzgadniać przy każdej nowej
komendzie, jest dokładnie tym długiem, przed którym ostrzega reguła 15: dopisanie
modułu ma kosztować **jedną** zmianę w rdzeniu.

Ponad okno komend menu wnosi dwie rzeczy i ani jednej więcej: **wybór bez
pisania** oraz **zawężenie do zaznaczenia**. To drugie wymaga, żeby komenda umiała
powiedzieć, czego dotyczy — stąd `AppliesToSelection`, doklejana obok kontraktu
jak `SuggestsArguments`, a nie dopisana do `CommandInterface`. Różnica jest
praktyczna: siedem komend rdzenia zostaje nietkniętych, bo `core.theme` nie jest
czynnością na pliku i nie ma powodu, żeby o tym mówiła.

Granica biegnie po **zaznaczeniu**, nie po module: `browser.hidden` i
`browser.tree` są w rejestrze, ale do menu nie wchodzą, bo dotyczą panelu, a nie
wpisu pod kursorem. Nazwa dla czynności, którą aplikacja umiała wyłącznie pod
klawiszem, jest przy tym osobną wartością — komenda i klawisz mają jednak
prowadzić do **jednego** miejsca w kodzie (`HiddenEntries`), bo dwie
implementacje tej samej czynności rozjeżdżają się przy pierwszej poprawce.

Zaznaczenie przychodzi do okna **przy otwarciu**, migawką z `LoopState::context()`:
okno zużywa klawisze, więc dopóki stoi, zaznaczenie nie ma jak się zmienić.
Prostokąt staje pośrodku, jak okno potwierdzenia — rdzeń nie wie, gdzie moduł
narysował kursor (lista czy drzewo, który z dwóch paneli), a pytanie ekranu
o współrzędne otworzyłoby `ScreenInterface` na współrzędne, których żaden kontrakt
nie zna. Menu bez ani jednej pozycji **nie otwiera się wcale**: mówi zdaniem
w pasku stanu, zamiast prosić o zamknięcie pustego prostokąta.

#### Rozdział miejsca: jedna reguła na dwie osie (od kroku 27)

Miejsce dzieli się w tej aplikacji **wszędzie tak samo**, niezależnie od osi:
wiersze między strefami klatki, kolumny między polami wiersza listy. Regułę
niesie `Container\Distribution`, a to, czego chce uczestnik podziału — `Span`:
minimum, rozmiar preferowany i kolejność ustępowania.

Reguła ma trzy zdania i wszystkie trzy obowiązują obie osie:

1. uczestnicy o podanej mierze biorą swoje, elastyczni dzielą resztę;
2. gdy brakuje, oddają miejsce w kolejności `yieldOrder` — każdy do swojego
   minimum, dopiero potem ustępuje następny;
3. uczestnik, któremu zostałoby mniej niż minimum, **znika w całości**.

Punkt trzeci jest sednem i był rozstrzygany trzy razy niezależnie, zanim dostał
jedno miejsce w kodzie: pas podglądu w kroku 12, drabinka stref w 13, kolumny
w 27. Za każdym razem odpowiedź brzmiała tak samo — **element przycięty w połowie
jest gorszy niż nieobecny**.

Miara ma dwie postacie stałe i różnią się dokładnie minimum:

- `Span::fixed()` — **kurczy się stopniowo** do zera. Pas podglądu niższy o wiersz
  jest nadal pasem podglądu.
- `Span::rigid()` — **tyle albo nic**. Kolumna z datą zwężona o trzy znaki nie
  jest „węższą datą”, tylko napisem `2026-08-…`, a przy okazji zabiera te znaki
  nazwie, która by je wykorzystała.

Minimum uczestnika elastycznego jest przy tym **progiem ustępowania sąsiadów**,
a nie obietnicą: dopóki suma minimów mieści się w prostokącie, nikt nie ustępuje.
Kolumna nazwy z minimum równym czterem znaczyłaby więc „nazwa może zejść do
czterech znaków, byle data została” — czyli odwrotność tego, czego chce lista.

#### Praca dłuższa od klatki (od kroku 25, proces potomny od 26)

Pętla główna nie ma prawa czekać. Wszystko, co trwa dłużej niż jedna klatka —
liczenie sumy kontrolnej, przejście po drzewie katalogów — dzieli się więc na
kawałki, a jeden kawałek przypada na klatkę. Wzorzec ma trzy części i wszystkie
trzy są obowiązkowe:

1. **Port mówi o pracy, a nie o wyniku.** Nie ma metody `checksum(path): string`
   — są `begin()`, `advance($bytes)` i `stop()`. Kształt kontraktu wymusza to,
   że wynik nie jest dostępny od razu.
2. **Stan pracy jest daną, którą ekran ogląda co klatkę** (`ChecksumState`:
   etap, ułamek, wynik albo powód niepowodzenia). To z niej bierze się wypełnienie
   paska postępu — i dlatego postęp jest **prawdziwy**, a nie udawany.
3. **Praca ma właściciela, który ją przerywa.** Stan modułu (`FileInfoState`)
   zatrzymuje ją przy zmianie zaznaczenia i przy `reset()`. Bez tego przewinięcie
   listy zostawiałoby za sobą tyle otwartych plików, ile pozycji minięto.

**Praca zaczyna się na żądanie, nie sama z siebie.** Zaznaczenie zmienia się przy
przewijaniu trzydzieści razy na sekundę, a praca uruchamiana odruchowo byłaby
wtedy trzydziestoma pracami przerwanymi w tej samej sekundzie. Wiersz stoi więc
od pierwszej klatki z podpowiedzią, którym klawiszem go policzyć.

Praca w **procesie potomnym** podlega tym samym trzem regułom i dokłada
**czwartą: sprzątanie przy wyjściu z aplikacji**. Od kroku 26 mechanizm jest
rdzeniowy — `Application\Port\BackgroundProcessPort` (start, doglądanie,
przerwanie) i `Infrastructure\Process\BackgroundProcessService` za nim. Moduł
sięga po niego tak samo, jak po `ImagePreviewPort`, a `Bootstrap` podaje go
w jednej linii.

**Stan pracy niesie oba strumienie — osobno** (od kroku 49). Do tamtego kroku
strumień błędów był czytany i wyrzucany, bo `du` zasypuje go wierszami „brak
dostępu”, a sklejenie zamieniłoby liczbę do odczytania w stertę do przeszukania.
Ta zasada **zostaje w mocy** i pola są rozdzielone właśnie po to; zmieniło się
co innego: polecenie, którego wyjściem jest **treść**, nie ma prawa scalać
strumieni w wierszu polecenia (`2>&1`), bo scalanie potrafi **zepsuć dane**
(reguła 15f w `SKILL.md`) — a mimo to musi mieć jak powiedzieć, co poszło nie tak.
**Ile wyjścia pamiętamy, mówi odtąd konfiguracja** (`backgroundOutputKib`,
domyślnie 1 MiB, zakładka „Zasoby”): dawna stała 64 KiB była dobrana pod
polecenia oddające jeden wiersz i urywała listę katalogu **po cichu**. Limit
obowiązuje każdy strumień z osobna i bierze się **raz, przy uruchomieniu
pracy** — praca mierzona w trakcie dwiema różnymi miarami nie miałaby miary
w ogóle.

Czwarta reguła brzmi: **potomek nie ma prawa przeżyć procesu, który go
uruchomił**, a ponieważ dróg wyjścia z aplikacji jest więcej niż jedna, drogi
sprzątania są dwie i obie obowiązują:

- **jawna** — `Bootstrap::shutdown()` woła `shutdown()` usługi przed zapisem
  historii i przywróceniem terminala, czyli tą samą ścieżką, którą terminal
  wraca do trybu normalnego;
- **gwarancja ostatniej szansy** — `register_shutdown_function` zarejestrowana
  leniwie przy pierwszym uruchomieniu pracy, dla wyjść, których pierwsza droga
  nie dosięga: błędu krytycznego i `exit()` z boku.

To jest ten sam układ, którym `TerminalService` broni trybu surowego, i z tego
samego powodu: jedna droga jest czytelna, druga nieomylna.

Trzy rzeczy ponadto, każda niosąca własną klasę błędów:

- **usługa prowadzi kilka prac naraz** (od kroku 51), każdą pod własnym uchwytem
  (`BackgroundHandle`), z **granicą braną z ustawień** (`backgroundJobs`,
  domyślnie osiem, zakładka „Zasoby”). Do tamtego kroku prowadziła **jedną** i była
  to decyzja z kroku 26, nie ograniczenie techniczne: przy jednym odbiorcy (`du`)
  nikomu nie przeszkadzała, ale odbiorców zrobiło się trzech — doszła sesja zdalna
  (kroki 48–50) i moduł Dockera, którego `compose up` trwa minutami. Uchwyt zmienił
  przez to **znaczenie, nie kształt**: przestał mówić „wyparto cię”, zaczął
  „prace da się rozróżnić”. **Przekroczenie granicy znaczy odmowę, nie wyparcie
  najstarszej** — wyparcie przywracałoby chorobę, którą rozbudowa leczy, a odmowa
  idzie tą samą drogą, co każda inna awaria startu: uchwyt wraca, powód odbiera
  pierwszy `poll()`;
- **potoki są nieblokujące i opróżniane co klatkę — dla każdej pracy naraz**.
  Do kroku 51 karmił je właściciel przy zaglądaniu; odkąd prac jest kilka, robi to
  **pętla** przez osobny port `Application\Port\BackgroundPumpPort` (`pump()`),
  wołany raz na klatkę w fazie „aktualizuj stan”. Port jest osobny **konstrukcyjnie,
  a nie z porządku**: pompowanie należy do pętli, nie do modułu — ta sama zasada,
  która w kroku 26 zostawiła `shutdown()` poza portem. Właściciel niezaglądający
  (ekran modułu zniknął, moduł ma usterkę) zatrzymałby inaczej swojego potomka na
  pełnym potoku, a jego limitu czasu też nie miałby kto sprawdzić. `poll()` jest
  odtąd **czystym odczytem stanu**;
- **kod wyjścia różny od zera nie jest sam z siebie niepowodzeniem** — `du`
  kończy się jedynką za każdy nieprzeczytany katalog, a mimo to podaje sumę tego,
  co przeczytać zdołało. Co z kodu wynika, rozstrzyga zamawiający; rdzeń go tylko
  podaje;
- **praca trwająca oddaje swój wypis, a zamawiający mówi, czym ten wypis jest**
  (od kroku 52, D91 nr 12). Do tamtego kroku `Running` znaczyło „nic ci jeszcze
  nie powiem”, co zamykało drogę każdemu poleceniu **niekończącemu się nigdy**:
  `kubectl logs -f` pisał wiersze do potoku, port je zbierał, a pierwszy raz
  oddałby je po śmierci potomka, czyli nigdy. Rozbudowa ma dwie części i druga
  jest ważniejsza od pierwszej. Samo oddawanie wypisu nie wystarcza, bo bufor
  **odrzucał nadmiar** po przekroczeniu granicy — log dobiłby do niej
  w kilkanaście sekund i zamilkł na zawsze. Wynik i strumień mają wobec granicy
  wymagania przeciwne, więc doszło `Application\Dto\OutputShape`: `Result`
  (domyślny, zachowanie sprzed kroku 52 co do bajtu) zbiera do granicy i odrzuca
  nadmiar, `Stream` **zapomina najstarsze**, a ile bajtów wypadło, mówi
  `BackgroundState::$droppedBytes`. Kształt podaje się przy `start()`, bo jest
  własnością **zamówienia, nie polecenia**: to samo `kubectl logs` bywa jednym
  i drugim, zależnie od `-f`. Dwie rzeczy zostają nietknięte i są warunkiem
  przyjęcia zmiany: **`poll()` pozostaje czystym odczytem** (stan powstaje
  w `pump()`) i **treść nadal odbiera się przy `Done`** — wypis w trakcie jest
  urwany w połowie wiersza, a pół JSON-a nie jest JSON-em.

Pierwszym odbiorcą jest wiersz „zajęte na dysku” w module `FileInfo` — liczony
poleceniem `du` **tylko dla katalogów** (dla zwykłego pliku tę samą liczbę podają
bloki i-węzła z `lstat`, bez uruchamiania czegokolwiek) i **na żądanie klawiszem
`d`**, jak suma kontrolna. Postępu `du` nie zna, więc pasek chodzi w trybie
„nieznany” — pierwsze prawdziwe użycie tego trybu od kroku 23.

#### Sesja zdalna: praca poza maszyną (od kroku 48)

Moduł `src/Module/Ssh/` nawiązuje i utrzymuje połączenie SSH z hostem ze spisu,
który użytkownik prowadzi z ekranu (`Ctrl`+`S`). Jest **czwartym sprawdzianem
kontraktu modułu** — po module rysującym główną funkcję (21), module bez ekranu
(36) i module pracującym, gdy go nie widać (45), przyszedł moduł **rozmawiający
z czymś poza maszyną**.

**Reguła nadrzędna, obowiązująca całą Fazę XVII: żadne wywołanie sieciowe nie
pada w rysowaniu klatki.** Jest to piąta reguła D46 rozciągnięta z zapisu na dysk
na sieć — i tutaj jest spełniona mocniej, niż brzmi: **żadne nie pada w procesie
aplikacji w ogóle**.

**Sesja żyje w procesie potomnym, a nie w procesie aplikacji** (D87 nr 1 i 2),
i to jest jawne odwrócenie D84 nr 2. Powód jest wymierny: `ext-ssh2` nie ma ani
jednego wywołania nieblokującego, a `ssh2_connect()` nie przyjmuje limitu czasu,
więc host nieosiągalny zamroziłby **całą aplikację** na `default_socket_timeout`,
czyli na minutę. Trzy warianty pośrednie — przyjęcie zamrożenia z ograniczeniem
od góry, strażnik na `pcntl_alarm()`, uścisk w potomku przy sesji w rodzicu —
zostały postawione i odrzucone. Zasób sesji nie przechodzi przez granicę procesu,
więc rozstrzygnięcie obejmuje także kroki 49 i 50.

**Trwanie daje `ControlMaster` klienta OpenSSH.** `ssh -M -N -f -o ControlPath=…
-o ControlPersist=yes` zestawia połączenie **raz** i demonizuje się samo, więc
aplikacja nie trzyma jego potoków ani przez chwilę; każda późniejsza operacja to
krótki potomek wchodzący przez gniazdo **bez uścisku dłoni** — milisekundy
zamiast setek. Stan sesji to `ssh -O check`, rozłączenie to `ssh -O exit`,
a gniazdo mieszka w `~/.light-manager/` pod nazwą będącą **skrótem z celu**, bo
gniazdo uniksowe ma twardy limit długości ścieżki.

Potomków uruchamia **rdzeniowy `BackgroundProcessPort`** (D87 nr 9) — moduł
sięga po port rdzenia, jak `FileInfo` po `du`. Cena przyjęta świadomie: port
prowadzi **jedną pracę naraz**, więc zestawianie sesji przerywa liczenie `du`
i odwrotnie. Z tego samego powodu **stan sesji odświeża się wyłącznie na żądanie
(`F5`)**, a nie w takcie: pytanie co kilka sekund znaczyłoby proces potomny co
kilka sekund, czyli zabijanie cudzej pracy w kółko. Sesja zerwana przez sieć bywa
przez to przez chwilę pokazana jako żywa i **jest to znana cena, nie usterka**.

**Weryfikacja klucza hosta: czyta moduł, pisze `ssh`** (D87 nr 5 i 6).
`KnownHostsReader` — klasa czysta poza jednym odczytem pliku — mówi **przed**
połączeniem, czy `~/.ssh/known_hosts` zna ten host; nazwy są tam zahaszowane, więc
dopasowanie to `hash_hmac('sha1', $nazwa, base64_decode($sól), true)`, w którym
**kluczem HMAC jest sól, a nazwa jest treścią** (odwrotnie, niż podpowiada
kolejność argumentów). Host nieznany idzie po odcisk potokiem
`ssh-keyscan | ssh-keygen -lf -`, po czym **zatrzymuje połączenie oknem groźnym**;
po zgodzie łączy się z `StrictHostKeyChecking=accept-new`, a wiersz dopisuje
**klient**, w postaci kanonicznej i zahaszowanej. Aplikacja nie dotyka tego pliku
do zapisu ani razu. Klucz niezgodny z zapamiętanym to nie pytanie, tylko odmowa —
i tej odmowy też nie piszemy sami.

Dwie rzeczy, o które łatwo się potknąć przy ruszaniu tego kodu. **Diagnostyka
klienta idzie na strumieniu błędów**, więc polecenia **sesji** kończą się na
`2>&1` — i wolno im, bo mistrz z `-N` na standardowym wyjściu nie pisze nic,
a cały ich wypis to kilkadziesiąt bajtów. **Poleceniu odczytu katalogu (krok 49)
wolno tego nie robić i nie wolno tego robić** — powód stoi niżej, przy zdalnym
katalogu, i jest zmierzony, nie teoretyczny. **Hasło nie może iść wejściem**: `ssh` czyta je
z terminala sterującego, a port tłowy potomkowi wejścia nie podaje — więc idzie
przez `SSH_ASKPASS` (`bin/ssh-askpass`) i zmienną środowiskową, nigdy przez
wiersz polecenia, który widzi w systemie każdy.

**Profil hosta pilnuje się sam i jest to warstwa bezpieczeństwa, nie porządek.**
Nazwa hosta, login i ścieżka klucza trafiają do wiersza polecenia uruchamianego
przez powłokę, więc mają wąskie wzorce — a osobno pilnowane jest to, czego
cytowanie upilnować nie może: **żadna wartość nie zaczyna się od `-`**, bo `ssh`
przeczytałby ją jako opcję.

Rdzeń urósł przez ten moduł o **trzy rzeczy, nie o jedną**, i wszystkie trzy są
rozstrzygnięciami użytkownika podjętymi z ceną wypisaną przed wyborem (D87):
pozycję w `Bootstrapie` (reguła 15), **tryb maskowany `TextInput`** (bo hasło
weszło do zakresu) i **zdolność `Application\Module\RequiresEnvironment`** — piąty
powód odrzucenia w rejestrze i pierwszy zależny od maszyny, na której aplikacja
stoi. Czwarta zmiana doszła w trakcie i wynikła z obejrzenia okna:
**`ConfirmOverlay` zawija długie pytanie zamiast je ucinać**, bo odcisk SHA256
ucięty w połowie nie jest odciskiem.

**Bez klienta OpenSSH moduł znika ze spisu wraz z powodem** — inaczej niż moduł
dźwięku, który bez rozszerzenia zostaje na pustym obiekcie. Różnica jest
zamierzona: cisza jest sensowną postacią muzyki, a spis hostów, z którymi nie da
się połączyć, nie jest sensowną postacią sesji zdalnej — obiecywałby.

#### Zdalny katalog: lista przychodząca później (od kroku 49)

Ten sam moduł pokazuje **zawartość katalogu na połączonym hoście**, a ekran ma
odtąd **dwie postacie**: spis hostów przed połączeniem i zdalny katalog po nim,
z `F3` zaglądającym z powrotem do spisu (`F2` należy do rdzenia). Postać zmienia
**takt, a nie klawisz** — połączenie kończy się w procesie potomnym, więc chwili,
w której „jest sesja”, nie zna żaden klawisz; zna ją dopiero `poll()`.

**Jedno wywołanie na katalog, nie jedno na wpis.** Odczyt idzie poleceniem
`printf 'ls -lf "…"' | sftp -b - -o ControlPath=…`, które wchodzi przez gniazdo
stojącego mistrza. Wypis `sftp ls -l` niesie **nazwę razem z atrybutami**
(rodzaj, prawa, rozmiar, czas) i składa go **klient**, a nie serwer — pole liczby
dowiązań pokazuje `?`, bo protokół jej nie niesie, właściciel jest zawsze liczbą,
a nazwa miesiąca nie zależy od ustawień językowych. Postać wiersza nie zależy więc
od tego, co stoi po drugiej stronie; `ssh ls -l` zależałoby, bo zakłada powłokę
POSIX, której serwer SFTP mieć nie musi. Czas formatuje **potomek**, dlatego
polecenie narzuca mu `TZ=UTC` — inaczej daty zdalne rozjeżdżałyby się z lokalnymi
o różnicę stref.

**Praca kawałkowa została jednostopniowa** i to jest zmiana wobec planu kroku,
podyktowana pomiarem: koszt siedzi w **wywołaniu** (~0,93 s otwarcia kanału na
pętli zwrotnej), a nie we wpisie (pięć tysięcy wpisów to +0,1 s, a ich rozczytanie
w PHP — 3,2 ms). Plan przewidywał drugi stopień („atrybuty widocznego okna po
jednym obiegu na wpis”) i budżet kawałka mierzony zegarem; jedno i drugie
chroniłoby przed kosztem, którego nie ma. Z wzorca D46 zostaje to, co się liczy:
**praca jest daną oglądaną co klatkę, nie procesem**.

**Strumieni tego polecenia nie wolno scalać** (reguła 15f w `SKILL.md`) i jest to
najdroższa lekcja tego kroku. `2>&1` przenosiło na wyjście `sftp` tryb
nieblokujący, który mistrz połączenia nakłada deskryptorom przekazanym mu przez
klienta multipleksera — a wtedy zapis do zapełnionego potoku zwracał `EAGAIN`,
OpenSSH porzucał porcję wypisu i kończył się **kodem zero**. Z 419 KB listy
dochodziło 130 KB, bez śladu w kodzie wyjścia. Stąd czwarta zmiana rdzenia kroku:
`BackgroundState` niesie odtąd **strumień błędów osobnym polem**, a zasada z kroku
26 („strumieni się nie skleja”) zostaje w mocy — pola są rozdzielone właśnie po to.

**Moduł ma własną domenę plikową i jest to świadome powtórzenie** (`RemotePath`,
`RemoteEntry`, `RemoteEntryType`, `RemoteNameFilter`, `RemoteEntryComparator`):
reguła 15 zabrania sięgania do przeglądarki, a wyniesienie ścieżki do rdzenia
byłoby odwróceniem D42. Granica tego powtarzania — pojęcia wolno, mechanizmy nie,
a trzeci taki moduł uruchamia przegląd — stoi w `SKILL.md` jako reguła 15e.
Ścieżka zdalna porządkuje się **tekstowo**, bo systemu plików po drugiej stronie
nie ma o co zapytać bez obiegu; dowiązanie w środku ścieżki zostaje przez to
nierozwinięte, a rozwinięcie należy do serwera (`pwd` przy katalogu startowym).

**Kontekst sesji mówi odtąd, czyja jest ścieżka** (`ContextOrigin`, piąta zmiana
rdzenia). Bez tego ekran zdalny publikujący `/var/log` kazałby modułowi opisu
pliku pokazać **lokalny** `/var/log` — kłamstwo ciche, bo obie ścieżki istnieją
i obie się czytają. Odbiorca wszedł razem z mechanizmem (reguła 13): moduł opisu
pliku rozpoznaje wpis zdalny i opisuje go **wyłącznie z kontekstu**, nie dotykając
ani dysku, ani sieci. Kontekst niesie po to trzy atrybuty zaznaczenia (rozmiar,
czas, prawa), a suma kontrolna i zajętość odmawiają pracy zdaniem, które mówi
dlaczego.

#### Przesył plików: pobranie i wysłanie (od kroku 50)

Ten sam moduł **przenosi pliki w obie strony**: `F5` pobiera wpis zdalny do
katalogu, w którym stoi przeglądarka, `F6` wysyła wpis lokalny do katalogu
otwartego w panelu. Odświeżanie listy przeprowadziło się przy tym z `F5` na
`Ctrl`+`R` — układ jest odtąd ten sam, co w przeglądarce (`F5` kopiuj, `F6`
przenieś) i w menadżerach dwupanelowych. Obie czynności mają komendy (`ssh.get`,
`ssh.put`) i pozycje w menu `F9`, a mieszkają w **jednym** miejscu
(`RemoteTransfer`, reguła 11n).

**Treść ląduje pod nazwą roboczą, a zatwierdza ją zmiana nazwy** — i to jest
całe zabezpieczenie przed połówką pliku. Lokalnie zatwierdza `FileOperationsPort`
rdzenia, zdalnie — `rename -l` w tym samym wsadzie `sftp`. Myślnik w `rename -l`
nie jest ozdobą: zwykłe `rename` idzie rozszerzeniem `posix-rename@openssh.com`
i **nadpisuje cicho** (sprawdzone: kod zero na zajętej nazwie), a nadpisanie ma
być skutkiem odpowiedzi użytkownika, nie właściwością protokołu. Cel zwalnia się
przez to **jawnie** (`-rm`) i tylko po zgodzie.

**Wyjątek 15b zostaje przy jednym nazwanym przypadku**, choć krok pisze po dysku:
plik pisze `sftp`, czyli potomek uruchomiony rdzeniowym `BackgroundProcessPort`,
a jedyne zapisy z PHP — zmiana nazwy i skasowanie połówki — idą przez port
rdzenia z kroku 41. Zastrzeżenie startowe planu („rdzeń dostaje port zapisu
strumienia”) okazało się bezprzedmiotowe wraz z drogą techniczną fazy (D89 nr 1).

**Postęp czyta `stat`, a nie klienta.** `sftp` pokazuje pasek postępu wyłącznie
wtedy, gdy jego wyjście jest terminalem sterującym (`progressmeter.c` porównuje
`getpgrp()` z `tcgetpgrp()`), więc na potoku milczy — nawet po poleceniu
`progress` w wsadzie. Pobieranie liczy się przez to **rosnącym plikiem roboczym**
(odczyt lokalny, darmowy, co klatkę), a wysyłanie zna wyłącznie granice plików:
w środku jednego pasek wchodzi w tryb „postęp nieznany”. Asymetria jest
własnością drogi i jest widoczna dla użytkownika. Ten sam odczyt pełni straż nad
zerwanym łączem: plik, który nie rośnie przez 30 s, kończy pracę zdaniem „łącze
milczy”, a limit twardy (godzina) jest wyłącznie sufitem awarii.

**Jeden potomek na plik** (D89 nr 3). Wsad `sftp` przerywa się na pierwszym
błędzie, więc jedno wywołanie na całą pracę znaczyłoby, że niepowodzenie jednego
pliku ubija resztę, a o kolizje trzeba by pytać w komplecie przed startem. Cena
jest zmierzona i nazwana: otwarcie kanału kosztuje tyle, co cały odczyt katalogu.

**Kolizję rozstrzyga strona, która wie za darmo**: przy pobieraniu dysk
(`file_exists`), przy wysyłaniu **lista, którą panel ma na ekranie**. Katalog
zdalny inny niż otwarty oddaje „nie wiem”, a nie „nic tam nie ma” — i wtedy przed
cichym nadpisaniem broni `rename -l`. Odpowiedzi są rdzeniowe (`TransferChoice`),
bo słownik „nadpisz / pomiń / zmień nazwę / przerwij (i wszystkie)” jest
mechanizmem, a mechanizmów rdzenia moduł nie powtarza (15e).

**Druga strona przesyłu bierze się z kontekstu sesji.** Ekran zdalny publikuje
własny kontekst (`Remote`), więc lokalnej ścieżki w chwili przesyłu nie ma czego
zapytać — `LocalPlace` zatrzaskuje ostatni kontekst z pochodzeniem `Local`,
podany ekranowi przez `ReadsContext` przed rysowaniem. Moduł nie sięga przez to
do przeglądarki ani razu (reguła 15).

**Przesyłane są pliki, nie katalogi**, a przesył wyłącznie kopiuje: wariantu
przenoszącego nie ma, praw i czasu zmiany nie przenosi (`sftp -p` poza zakresem),
a wznawianie przerwanej pracy zostaje osobną rzeczą do zaprojektowania.

#### Docker: kilka rozmów naraz (od kroku 51)

Moduł `src/Module/Docker/` (`Ctrl`+`O`) pokazuje kontenery i obrazy tej maszyny,
puszcza logi na żywo, buduje obrazy i podnosi projekty compose. Jest **piątym
sprawdzianem kontraktu modułu** — po module rysującym główną funkcję (21),
module bez ekranu (36), module pracującym, gdy go nie widać (45), i module
rozmawiającym z cudzą maszyną (48) przyszedł moduł **prowadzący kilka rozmów
naraz**: dwie listy, strumień logów, budowa i praca compose potrafią trwać w tej
samej chwili.

**Drogi są dwie i to nie z wygody.** Docker idzie **gniazdem unixowym**
(`/var/run/docker.sock`, `ext-curl` z `CURLOPT_UNIX_SOCKET_PATH`, rodzina
`curl_multi_*` w trybie nieblokującym), a compose — **procesem potomnym**, bo
demon nie wystawia dla wtyczki ani jednego zasobu w API. Rozmowy pompuje takt
modułu (`NeedsTick`), nigdy rysowanie klatki; `curl_multi_select()` nie pada ani
razu, bo pytanie o gotowość deskryptorów kosztuje tyle samo, co samo posunięcie
transferu.

**Dwa formaty strumieniowe są pułapkami dającymi „działa, ale wygląda na
zepsute”** i oba rozbiera moduł, nie komponent (reguła 11i):

- **logi kontenera bez TTY są multipleksowane** — osiem bajtów przed każdą porcją
  (numer strumienia, trzy wypełniające, cztery długości w kolejności sieciowej).
  Czytane jak tekst dają śmieci co kilka wierszy. Czy strumień jest ramkowany,
  rozstrzyga **treść, a nie pytanie do demona**, i rozstrzyga się to dopiero
  z ósmym bajtem: porcja krótsza od nagłówka wygląda jak zwykły tekst, a odpowiedź
  raz udzielona obowiązuje do końca strumienia;
- **budowa oddaje postęp strumieniem obiektów JSON**, po jednym na wiersz, i to
  w nim — a nie w kodzie odpowiedzi — przychodzi **niepowodzenie**: nieudana
  budowa kończy się kodem HTTP 200, bo z punktu widzenia protokołu wszystko
  poszło dobrze.

**Kontekst budowy pakuje `PharData` pracą kawałkową** (D46), z pominięciem tego,
co wyklucza `.dockerignore` — czytany w podzbiorze składni, którego różnica wobec
pełnej semantyki Dockera objawia się **rozmiarem kontekstu, a nie wynikiem
budowy**. Bez tego pierwszy lepszy projekt Node.js wysłałby demonowi
`node_modules`.

**Moduł odmawia startu bez `ext-curl` albo bez gniazda** (`RequiresEnvironment`),
ale **nie odmawia z powodu leżącego demona**: rozszerzenia nie da się doładować
w trakcie działania aplikacji, a demona da się podnieść — moduł odrzucony przy
starcie nie wróciłby aż do restartu, więc zatrzymany demon jest zdaniem na
ekranie, a nie powodem nieobecności.

**Listy odświeżają się z zegara co pięć sekund, ale wyłącznie przy widocznym
ekranie**; zawężenie do projektu compose nie kosztuje ani jednego pytania więcej,
bo kontener zna swój projekt z etykiety `com.docker.compose.project`
przychodzącej razem z listą.

#### Kubernetes: moduł, który nie wie z góry, co pokaże (od kroku 52)

Moduł `src/Module/Kubernetes/` (`Ctrl`+`K`) pokazuje zasoby klastra wskazanego
przez `kubeconfig`, opisuje wybrany zasób, puszcza logi poda na żywo, stosuje
manifesty i pozwala zmienić wartość w sekrecie. Jest **szóstym sprawdzianem
kontraktu modułu** i różni się od pięciu poprzednich jedną rzeczą: **nie zna
z góry swojej treści**. Rodzaje zasobów przychodzą z klastra, więc drzewo jednego
wygląda inaczej niż drugiego, a operator zainstalowany w międzyczasie zmienia je
bez ani jednej linii dopisanej do aplikacji (D91 nr 2).

**Wszystko idzie procesem potomnym** — `kubectl` uruchamiany rdzeniowym portem
pracy tłowej — więc reguła nadrzędna z kroku 48 jest spełniona w najmocniejszej
postaci: żadne wywołanie sieciowe nie pada w rysowaniu klatki, bo żadne nie pada
w procesie aplikacji. **Limit czasu jest częścią każdego wywołania i jest
podwójny**: `--request-timeout` (klient przestaje czekać na serwer) plus limit
procesu (rdzeń ubija potomka, który zawiesił się przed wysłaniem żądania).
Wyjątek jest jeden i nazwany: **strumień logów nie dostaje limitu żądania**, bo
ten zamknąłby go w chwili, gdy zaczyna działać.

**Ekran to `Split`: drzewo grup API i rodzajów po lewej, treść po prawej**
(D91 nr 3) — dla rodzaju jego lista, dla zasobu opis w zwijanych sekcjach, a `y`
przełącza na surowy YAML. Trzy poziomy drzewa zamiast dwóch, bo rodzajów bywa
kilkadziesiąt; grupa mieści się w panelu, płaska lista sześćdziesięciu pozycji
nie. **Rozwinięcie gałęzi jest jedynym momentem, w którym pytamy o listę** —
każde takie pytanie to proces potomny, więc gałąź rozwinięta i nieoglądana
zostaje taka, jaka była.

**Kolumny liczy moduł, nie serwer** (D91 nr 4) i jest to cena wybrana świadomie:
`-o json` plus ręcznie napisane pakiety kolumn dla kilkunastu popularnych
rodzajów, a rodzaj spoza spisu — w tym każdy CRD — pokazuje nazwę, wiek i nic
więcej. Odrzucona droga (rozczytywanie tabeli drukowanej przez serwer) dawała
prawdziwe kolumny każdemu rodzajowi za darmo, ale kupowała je parserem tekstu
wyrównanego spacjami.

**Jedno wywołanie mimo to oddaje tekst**: `kubectl api-resources` nie umie JSON-a
w kliencie 1.25 (`-o` przyjmuje `wide` i `name`). Wiersz rozbiera się wyrażeniem
opartym na niezmiennikach — czasowniki w nawiasach kwadratowych, przed nimi stała
kolejność pól — **nigdy podziałem po spacjach**: kolumna `SHORTNAMES` bywa pusta,
a wtedy podział przesuwa wszystkie pozostałe i `namespaced` czyta się
z `APIVERSION`, czyli połowa katalogu dostaje odwróconą odpowiedź na pytanie „czy
ten zasób mieszka w przestrzeni nazw”.

**Sekrety są zamaskowane** w liście, w opisie i w widoku YAML; `x` odsłania
**jeden** klucz, a `e` otwiera zmianę — wartość tekstem albo zapisem base64,
dodanie klucza, skasowanie klucza (D91 nr 10). Wszystkie trzy idą jednym
poleceniem `kubectl patch --type=merge -p '<json>'`, bo scalająca zmiana kasuje
klucz o wartości `null` i zostawia nietknięte te, których nie wymieniono. Fragment
idzie **argumentem**, nigdy wejściem standardowym — ta sama reguła unieważnia
`kubectl apply -f -`, więc manifest podaje się ścieżką. Powód maskowania jest
wymierny: `core.dump` z kroku 38 zapisuje klatkę na dysk.

**Stan „nie ma klastra” jest stanem zwykłym, nie awarią**, i rozpada się na trzy:
brak bieżącego kontekstu (spis czyta się z pliku, więc pada także bez sieci —
ekran mówi, co wybrać), klaster nieosiągalny (powód pochodzi ze strumienia błędów
klienta, nie z domysłu) i klaster gotowy. **Wersje klienta i serwera są widoczne,
a różnica większa niż jedno wydanie jest ostrzeżeniem, nie odmową** — Kubernetes
nazywa ją niewspieraną, a nie niemożliwą.

Rdzeń kosztuje **jedną linię w `Bootstrapie` plus rozbudowę portu pracy tłowej**
o wypis pracy trwającej (niżej, rozdz. o pracy tłowej) — plan kroku zakładał, że
mechanizmu rdzenia nie ruszy żadnego, i to założenie zostało odwołane
rozstrzygnięciem użytkownika (D91 nr 12).

#### Kosz i cofnięcie ostatniej operacji (od kroku 44)

Usunięcie przestało być końcem: klawisz domyślny (`F8`, `Delete`) robi to, co
mówi pozycja modułu „usuwaj do kosza” (domyślnie: kosz), a `Shift`+`F8`
i `Shift`+`Delete` — **zawsze to drugie** (D81, nr 1–2). Ustawienie przestawia
znaczenie klawisza, a nie wyłącza drugą drogę, więc obie są zawsze osiągalne.
Usunięcie trwałe **pyta zawsze**, oknem w wariancie groźnym — ustawienie „pytaj
przed usunięciem” z kroku 41 rządzi odtąd czynnością odwracalną, czyli koszem.

Kosz jest **katalogiem konfigurowalnym o stałym układzie** (D81, nr 3):
domyślnie `$XDG_DATA_HOME/Trash` (bez zmiennej — `~/.local/share/Trash`), czyli
kosz środowiska graficznego, a pozycja tekstowa modułu wolno wskazać dowolny
inny. Układ freedesktop.org obowiązuje wszędzie: wpis ląduje w `files/`,
a w `info/` staje `nazwa.trashinfo` ze ścieżką powrotną (kodowaną jak adres URL)
i datą usunięcia — **pisany przed przeniesieniem**, bo wpis w koszu bez niego
jest wpisem, którego nie da się przywrócić. Plik informacyjny tworzony trybem
`x` jest zarazem rezerwacją nazwy; kolizję rozwiązuje sufiks liczbowy przed
rozszerzeniem (`raport.pdf`, `raport.1.pdf`), jak w koszu środowiska.

Do kosza przenosi się **zmianą nazwy, nigdy kopiowaniem** (D81, nr 4) — dlatego
katalog z zawartością jedzie w całości, bez liczenia i bez okien pracy, i to
jest główny zysk kosza nad usuwaniem. Wpis z **innego systemu plików** dostaje
ostrzeżenie i pytanie o trzech odpowiedziach (D81, nr 5): skopiować do kosza
(pracą kawałkową z kroku 42 — nazwy rezerwuje się w koszu przed pierwszym
bajtem, a praca dostaje je mapą `targetNames` w `begin()`), usunąć trwale albo
przerwać. Kosza na wolumenie (`.Trash-$uid`) aplikacja **nie zakłada**.

**Stos cofnięć jest pamięcią modułu, nie rdzenia** — wbrew literze planu kroku
i zgodnie z regułą 15: operacje zmaterializowały się w całości po stronie
przeglądarki, więc dziennik (`Module/Browser/Application/Undo/UndoJournal`) ma
jednego piszącego i jednego czytającego. `Alt`+`u` cofa najnowszą operację
odwracalną; `F3` otwiera widok stosu, w którym cofnąć wolno **dowolną pozycję**
(D81, nr 6) — pozycje nieodwracalne stoją wyszarzone i niewybieralne (nr 8),
bo lista odpowiada też na pytanie „co się właściwie wydarzyło”. Głębokość stosu
jest pozycją ustawień (nr 7); zapis **nie przeżywa zamknięcia aplikacji** —
cofanie po restarcie byłoby dziennikiem transakcji, a nie wygodą.

**Spis operacji odwracalnych mieszka w kodzie** (`UndoEntry::reversible()`),
nie w napisie — wraz z powodami, dla których pozostałe nimi nie są:

| Operacja | Cofnięcie |
|---|---|
| zmiana nazwy | zmiana nazwy z powrotem |
| nowy katalog | usunięcie — **dopóki pozostał pusty** (D81, nr 10) |
| do kosza | przywrócenie z kosza (ścieżka z pliku informacyjnego) |
| przeniesienie | przeniesienie z powrotem, tą samą pracą kawałkową |
| kopiowanie | **nie** — cofnięciem byłoby usunięcie kopii, czyli operacja nieodwracalna udająca powrót |
| usunięcie trwałe | **nie** — nie ma skąd wrócić |

Cofnięcie nieudane (wpis zniknął, miejsce zajęte, katalog przestał być pusty)
**mówi dlaczego i nie zdejmuje zapisu** — inaczej użytkownik traci jedyną
informację o tym, co się stało; przywrócenie zbioru przerwane w połowie wymienia
zapis na pomniejszony o to, co już wróciło. Kursor po cofnięciu staje na wpisie
przywróconym, bo to on jest odpowiedzią na pytanie „czy się udało”.

## 3. Wzorzec Singleton, porty i bootstrap

Każda usługa spoza `Domain` to osobny, klasyczny Singleton (nie centralny
kontener/rejestr). Wspólny mechanizm — dziedziczenie po klasie
abstrakcyjnej:

```php
namespace LightManager\Infrastructure\Support;

abstract class AbstractSingleton
{
    /** @var array<class-string, static> */
    private static array $instances = [];

    protected function __construct()
    {
    }

    final public static function getInstance(): static
    {
        return self::$instances[static::class] ??= new static();
    }

    final public function __clone(): void
    {
        throw new \LogicException(static::class . ' jest Singletonem i nie może być klonowany.');
    }

    final public function __wakeup(): void
    {
        throw new \LogicException(static::class . ' jest Singletonem i nie może być deserializowany.');
    }

    final public function __sleep(): array
    {
        throw new \LogicException(static::class . ' jest Singletonem i nie może być serializowany.');
    }
}
```

Konstruktor jest `protected` (nie `private`) — techniczna konieczność przy
współdzielonej `getInstance()` w klasie bazowej; efekt na zewnątrz
identyczny (blokada `new` spoza hierarchii klas).

**Które warstwy wolno korzystać z Singletonów:**

| Warstwa | Wolno? | Uzasadnienie |
|---|---|---|
| `Domain` | **Nigdy.** | Musi pozostać testowalna bez terminala/Imagick. |
| `Application` | Tak, wyłącznie przez interfejs portu wstrzyknięty do use case'u. | Use case nie wywołuje `getInstance()` sam. |
| `Infrastructure` | Tak — usługi mogą się nawzajem pobierać przez `getInstance()`. | Naturalne miejsce na współpracę usług. |
| `Presentation` | Tak, ale w praktyce tylko w klasie `Bootstrap` i punkcie wejścia `bin/`. | Reszta dostaje zależności jako wstrzyknięte porty. |
| `Module` | Klasa modułu — **nigdy**. Jego usługi `Infrastructure` — tak, na zasadach rdzenia. | Moduł powstaje `new`-em w `Bootstrap` z wstrzykniętymi zależnościami; usługa modułu jest usługą jak każda inna. |

**Klasa modułu nie jest Singletonem** i nie woła `getInstance()`. Jest zwykłym
obiektem tworzonym w `Bootstrap` — w tym samym miejscu i na tych samych prawach,
co ekrany. Powód jest ten sam, dla którego ekrany przestały być przypadkami
enuma: obiekt z wstrzykniętymi zależnościami da się złożyć w teście, a Singleton
trzeba w nim resetować przez refleksję. Singletonami pozostają usługi w warstwie
`Infrastructure` **modułu** (np. `FileInspectorService`), bo to zwykłe usługi
infrastruktury i nie różnią się niczym od usług rdzenia.

**Porty aplikacyjne i usługi:**

| Port (`Application/Port`) | Implementacja (`Infrastructure`) | Singleton | W `Bootstrap::boot()`? |
|---|---|---|---|
| `InputPort` (do kroku 34 `TerminalPort`) | `TerminalService`; w trybie okienkowym `Glfw\GlfwInputService` | Tak | Tak — kolejność 1 |
| `RendererModeDetectorPort` | `SixelCapabilityService` | Tak | Tak — kolejność 2; **nie w torze okienkowym** (DA1 nie wychodzi) |
| `FrameRendererPort` | `RendererService`; w trybie okienkowym `OpenGlFrameRenderer` (zwykły obiekt, jak strategie w `RendererService`) | Tak / nie | Tak — kolejność 3 |
| `ThumbnailGeneratorPort` | `ThumbnailGeneratorService` | Tak | Nie — leniwa inicjalizacja |
| `FileInspectorPort` | `FileInspectorService` | Tak | Nie — leniwa inicjalizacja |
| `ViewportPort` | `TerminalSizeService`; w trybie okienkowym `Glfw\GlfwViewportService` | Tak | Tak — pośrednio, przez `RendererService` |
| `FrameLayoutPort` | `HudFrameLayoutService` | Tak | Nie — leniwa inicjalizacja |
| `SettingsPort` | `Config\SettingsService` | Tak | Tak — kolejność 3, **przed** rendererem; w torze okienkowym **pierwsza** (rozmiar okna z ustawień) |
| `ThemePort` | `Rendering\ThemeService` | Tak | Nie — leniwa inicjalizacja |
| `FileOperationsPort` (krok 41) | `FileSystem\FileOperationsService` | Tak | Nie — `Bootstrap` podaje go modułowi przeglądarki, jak `ImagePreviewPort` |
| `FileTransferPort` (krok 42) | `FileSystem\FileTransferService` | Tak | Nie — tą samą drogą, co port wyżej |

Tor okienkowy dokłada do sekwencji bootstrapu usługę bez portu:
`Glfw\GlfwWindowService` (`glfwInit()`, okno z kontekstem 3.3 core,
sprzątanie) — okienny odpowiednik efektu ubocznego `TerminalService`,
dlatego stoi zaraz po konfiguracji, przed wejściem i rendererem.

`Module/Browser/Domain/Repository`: `DirectoryRepositoryInterface` →
`Module/Browser/Infrastructure/FilesystemDirectoryRepository` (nie jest portem
aplikacyjnym, lecz domenową abstrakcją dostępu do danych — i od kroku 21 należy
do modułu, nie do rdzenia).

**Motyw i układ** (krok 13): kolory interfejsu opisane rolami żyją w
`Infrastructure/Rendering/Theme`, wydawane przez `ThemeService`; podział okna na
strefy liczy `HudFrameLayoutService` za portem `FrameLayoutPort`. Układ ma port,
bo pojemność listy liczy `Application`, a grubość ramek zna wyłącznie renderer.
Żaden literał `#rrggbb` ani kod ANSI nie ma prawa pojawić się poza `Theme` i
`AnsiPalette`.

**Konfiguracja** (krok 14): `SettingsPort` i `ThemePort` opisują dwie połowy
jednej sprawy — nośnik wartości i zakres jednego z kluczy. `ThemeService` zyskał
port dopiero teraz, bo dopiero teraz `Application` naprawdę woła motyw: ekran
ustawień musi znać nazwy palet, żeby po nich chodzić. Wartości kolorów zostają
po stronie renderowania; warstwa aplikacji zna nazwy, nigdy zawartości.

Trzy reguły tego kroku, wynikające z tego, że ustawienia zmieniają się **w
trakcie** działania pętli:

1. **Nic, co pochodzi z konfiguracji, nie jest wstrzykiwane raz przy budowie
   usługi.** Renderery pytają o motyw przy każdej klatce, a enkoder Sixela
   dostaje `RenderingOptions` parametrem `encode()`. Inaczej zmiana motywu
   wymagałaby restartu.
2. **Konfiguracja wchodzi do bootstrapu przed rendererem**, bo to ona wybiera
   motyw. Odczytana później zostałaby zapamiętana bez sprawdzenia nazwy palety.
3. **Wyjątki infrastruktury nie przekraczają granicy portu.** `SettingsPort`
   nie rzuca: nieczytelny plik i nieudany zapis wracają opisem (`LoadedSettings`
   i wynik `save()`), który warstwa wyżej stawia w pasku stanu. `ConfigException`
   istnieje, ale żyje wyłącznie wewnątrz `Infrastructure/Config`.

**Klatka i strefy** (kroki 14, 18, 21 i 47): przez port przechodzi sam `Frame` —
stos płaszczyzn — a podział okna na strefy liczy `HudLayout` po stronie
`Presentation`. O tym, czy powstanie strefa górna, decyduje **pokazywany
ekran**: `header()` oddające `?ScreenZone`. Strefa niezamówiona nie dostaje ani
jednego wiersza, a jej miejsce zabiera lista.

**Stref było trzy do kroku 47.** Pas podglądu wyszedł z kontraktu wraz
z `preview()` (D78), bo po wyprowadzeniu miniatury do modułu `FileInfo` (D76) nie
zamawiał go żaden ekran — a mechanizm rdzenia bez odbiorcy jest złamaniem reguły
13, nie zapasem na przyszłość. Razem z nim zniknęły `previewIsPanel()`, próg
`ROWS_FOR_PREVIEW` i płaszczyzna podglądu w `FrameComposer`, a **próg
dwuwierszowego paska stanu przesunął się z 28 na 20 wierszy**: był liczony jako
`ROWS_FOR_PREVIEW + 2`, a jego uzasadnienie („przy progu lista właśnie oddała
podglądowi osiem wierszy”) zostaje w mocy dosłownie — bez pasa lista ma przy
dwudziestu wierszach dokładnie tyle, ile miała przy dwudziestu ośmiu z pasem.

Do kroku 20 pasek ścieżki i pas podglądu rysował rdzeń, bo miał czym: katalog
leżał w stanie pętli. Krok 21 zabrał mu ten katalog, więc obie strefy przeszły do
ekranu razem z danymi, z których powstają. Rdzeniowi zostały **oprawa i stopka** —
obwódki, nawiasy narożne, etykiety stref oraz pasek stanu z komunikatem
i podpowiedziami klawiszy globalnych. Ekran nie rysuje ramek i nie zna motywu od
tej strony.

Nie każdy Singleton musi implementować port. Usługa używana wyłącznie wewnątrz
`Infrastructure` — jak `Imagick/ImagickCapabilityService`, odpowiadająca na
pytania o możliwości lokalnego ImageMagick — pozostaje bez interfejsu w
`Application/Port`, bo żadna warstwa wyżej jej nie woła (krok 07,
[docs/plans/00-decyzje.md](plans/00-decyzje.md), D17). Port zakłada się wtedy i
tylko wtedy, gdy `Application` albo `Presentation` naprawdę potrzebuje danego
zachowania.

**Bootstrap** (`Presentation/Cli/Bootstrap`, nie jest Singletonem):

```php
final class Bootstrap
{
    public static function boot(): void
    {
        TerminalService::getInstance();
        SixelCapabilityService::getInstance();
        RendererService::getInstance();
    }

    public static function createGameLoop(): GameLoop;  // dowiązanie portów do implementacji
    public static function shutdown(): void;            // przywrócenie terminala po pętli
}
```

Usługa trafia do `Bootstrap::boot()` tylko, gdy jej konstruktor ma efekt
uboczny wymagany przed pętlą gry (np. wejście w tryb raw terminala).
Pozostałe usługi inicjalizują się leniwie przy pierwszym `getInstance()`.

**Dno stosu ekranów** (krok 21) przestało być wpisane w kod. Wskazuje je klucz
rdzenia `startupModule`, którego dopuszczalne wartości pochodzą **z rejestru
modułów** — to pierwszy klucz konfiguracji, którego zakresu nie zna się w czasie
pisania kodu. Wybór robi `Presentation\Cli\StartupScreen`; `Bootstrap` podaje mu
wyłącznie identyfikator **modułu ostatniej szansy** (`LAST_RESORT_MODULE`), a ten
moduł:

- jest sprawdzany przez rejestr **pierwszy**, więc przy kolizji skrótu odrzucony
  zostaje ten drugi moduł,
- **nie da się go wyłączyć** — przełącznik na zakładce „Moduły” stoi, ale mówi
  tylko, dlaczego nie działa,
- przejmuje dno w czterech przypadkach: moduł domyślny wyłączony, odrzucony,
  nieobecny na liście albo bez ekranu. Każdy z nich ma **własny komunikat**, bo
  każdy prowadzi do innej poprawki.

Nazwa modułu ostatniej szansy stoi w `Bootstrap`, a nie w `ModuleRegistry`:
warstwa `Application/Module` nie zna nazwy żadnego konkretnego modułu. Jego brak
na liście modułów jest **błędem programistycznym**, nie sytuacją użytkownika —
kończy się wyjątkiem i łapie go test.

**Reset w testach** — wyłącznie przez Reflection, zero publicznego API w
kodzie produkcyjnym: trait `tests/Support/ResetsSingletons` zeruje
współdzieloną właściwość `AbstractSingleton::$instances` dla wskazanej
klasy.

## 4. Standardy PHP i narzędzia

- **PHP `^8.3`** (zgodne z lokalnym środowiskiem deweloperskim).
- `declare(strict_types=1)` obowiązkowe w każdym pliku, wymuszane przez
  PHP-CS-Fixer.
- **PHP-CS-Fixer**: baza `@PSR12` + `declare_strict_types`,
  `strict_comparison`, `strict_param`, `single_quote`,
  `trailing_comma_in_multiline`, `ordered_imports`, `no_unused_imports`,
  `void_return`, `binary_operator_spaces`.
- **PHPStan**: poziom `max` od startu. Punktowe, uzasadnione
  `@phpstan-ignore` zamiast obniżania poziomu globalnie.
- **PHPUnit**: `tests/` odzwierciedla `src/` 1:1, klasa testowa
  `{Nazwa}Test`. Testy jednostkowe obowiązkowe dla `Domain`/`Application`
  (zero I/O). `Infrastructure`/`Presentation` — testy automatyczne w miarę
  możliwości, reszta do weryfikacji manualnej. Preferuj
  `self::assertSame()` nad `assertEquals()`.
- **Dwie grupy testów od kroku 38** (`phpunit.xml.dist`): `unit` — klasy;
  `functional` — **przebiegi użytkownika** w `tests/Functional/`, nazwane
  `{Przebieg}FlowTest`. Przebieg to sekwencja klawiszy przez `ScreenFixture`
  (komplet ekranów i prawdziwych modułów bez FS, terminala i Imagicka)
  z asercjami w punktach kontrolnych; start aplikacji i zmiana rozmiaru okna
  idą dodatkowo przez `GameLoop` ze `ScriptedTerminal`, bo taktu bez pętli
  sprawdzić się nie da. Katalog jest **spisem zachowań**, a nie zbiorem
  skutków ubocznych kolejnych kroków — brak przebiegu w spisie jest luką do
  uzupełnienia, a nie stanem naturalnym.
- **Złote klatki**: `tests/Golden/<scenariusz>.txt` — serializacja prymitywów
  klatek `ScenarioFactory`, porównywana niezależnie od renderera. Odnawia je
  **wyłącznie** `./bin/render-bench --golden-save`, po przeczytaniu różnicy;
  plik regenerowany automatem przestaje być testem.
- Konfiguracje (`.php-cs-fixer.dist.php`, `phpstan.neon.dist`,
  `phpunit.xml.dist`) — zaprojektowane w
  [docs/plans/archiwum/03-standardy-stylu-kodowania.md](plans/archiwum/03-standardy-stylu-kodowania.md),
  utworzone w korzeniu repozytorium w kroku 05 (bez zmian względem planu).
- **Definicje poleceń jakości mieszkają w `composer.json`** (`cs`, `cs:check`,
  `stan`, `test`) i to je wołają cele `make cs`, `cs-check`, `stan`, `test` —
  Makefile jest cienką warstwą, nie drugą definicją (krok 39, D72). Wejściem do
  procesu jest cel `make`: pełny spis w rozdz. 8 „Procesy projektu”.

### Diagnostyka i pomiar (kroki 16, 35, 38)

Jedyną drogą do pomiaru jest `bin/render-bench` — doraźna pętla `microtime()`
daje wynik, którego nie da się z niczym porównać, nie niesie metryczki
środowiska i nie zostawia śladu. Narzędzie mierzy **cztery tory**, każdy
odpowiadający na inne pytanie:

| Tor | Wywołanie | Fazy |
|---|---|---|
| sixelowy | *(domyślny)* | rysowanie → kwantyzacja → kodowanie |
| okienkowy | `--window` | rysowanie → zamiana buforów |
| tekstowy | `--text` | prymitywy → bufor komórek → bajty ANSI |
| takt pętli | `--loop` | wejście → stan → złożenie klatki (bez renderera) |

Zasady, których nie wolno cicho odwrócić:

- **Zero wywołań pomiarowych w kodzie produkcyjnym** (D28). Zegar stoi po
  stronie narzędzia; renderery są rozbite na **publiczne kroki**
  (`SixelFrameEncoder` w kroku 16, `TextFrameRenderer` w kroku 38) i to jedyne
  przyznane szwy.
- **Każdy element interfejsu ma scenariusz albo zapisany powód pominięcia** —
  spis w [docs/pomiary/README.md](pomiary/README.md); nowy scenariusz musi dać
  się rozliczyć **w parze** z istniejącym.
- **Zimna klatka jedzie obok mediany, nie zamiast niej** i nigdy nie podnosi
  alarmu regresji.
- **Obciążenie maszyny wchodzi do metryczki wzorca**; przy zapisie ostrzega,
  ale nie odmawia — odmowa zostaje strażnikowi rozrzutu (1,35×).
- **Regresję wizualną wykrywa porównanie zrzutów** (`--png-compare`, metryka
  AE), a nie oko; wzorcowe PNG leżą w `docs/pomiary/wzorce-png/`.
- **Zrzut z żywej aplikacji** robi komenda `core.dump` — prymitywy i obraz
  **wierny torowi** (płótno, bufor karty albo rasteryzacja bufora ANSI).

## 5. Konwencje nazewnictwa

- Segmenty namespace = katalogi 1:1 (PSR-4), root: `LightManager\`.
- **Value Objects**: rzeczownik bez sufiksu, `final`, `readonly`,
  samowalidacja w konstruktorze, metoda `equals()`. Natywne `enum` liczy
  się jako Value Object.
- **Encje / agregaty**: rzeczownik bez sufiksu, jawna tożsamość, `equals()`
  porównuje wyłącznie identyfikator, mutowalne w miejscu.
- **Interfejsy repozytoriów**: sufiks `RepositoryInterface`.
- **Implementacje repozytoriów**: sufiks `Repository` poprzedzony
  technologią (`FilesystemDirectoryRepository`).
- **Porty aplikacyjne**: sufiks `Port`.
- **Implementacje portów (Singletony)**: sufiks `Service`.
- **Use case'y**: czasownik + rzeczownik + sufiks `UseCase`.
- **Wyjątki domenowe**: sufiks `Exception`, dziedziczą po
  `Domain\Exception\DomainException` (abstrakcyjna, `extends \RuntimeException`).
  Preferowane nazwane konstruktory statyczne (`::forPath()`). **Treść
  komunikatu jest techniczna i po angielsku** — pisana dla śladu stosu, nie dla
  użytkownika; dane potrzebne warstwie `Presentation` do złożenia napisu (np.
  ścieżka) wyjątek wystawia jako publiczne, typowane pola (§7).
- **Wyjątki infrastruktury**: sufiks `Exception`, dziedziczą po
  `Infrastructure\Support\InfrastructureException` (abstrakcyjna,
  `extends \RuntimeException`) — osobna hierarchia, równoległa do domenowej,
  ta sama konwencja nazwanych konstruktorów (`TerminalException::forMissingPcntl()`)
  i ta sama zasada technicznego komunikatu po angielsku.
  Wprowadzona w kroku 06 ([docs/plans/00-decyzje.md](plans/00-decyzje.md), D16).
- **DTO portów**: obiekty wejścia/wyjścia portów aplikacyjnych żyją w
  `Application/Dto` (np. `KeyPress` i enum `Key` z kroku 06). Pojęcie
  techniczne warstwy dostarczania nie trafia do `Domain/ValueObject`, nawet
  gdy formalnie jest niemutowalną wartością. `KeyPress` niesie **trzy
  modyfikatory, rozłącznie**: `ctrl` od kroku 19 (skróty modułów) i `alt` od
  kroku 29 (zawijanie wierszy w podglądzie) — oba wyłącznie przy literach —
  oraz `shift` od kroku 44 (druga droga usunięcia, zaznaczanie zakresem),
  wyłącznie przy **klawiszach nazwanych**: litera z `Shift`em przychodzi z obu
  torów jako inna litera, więc znacznik przy znaku nie miałby czego nieść.
  Kombinacji modyfikatorów słownik **nie zna** i nie ma ich po co znać, dopóki
  nie pojawi się użytkownik — w torze okienkowym `Ctrl` wygrywa, w terminalowym
  taka para w ogóle nie powstaje, a `Ctrl`+`Shift`+`Delete` niesie w CSI bit
  `Shift`a i tym samym jest `Shift`+`Delete`. Cena `Alt` w terminalu jest znana
  i zapisana przy parserze: `ESC`+litera to te same dwa bajty co `Esc`
  naciśnięty tuż przed literą, więc rozstrzyga o nich czas — jak we wszystkich
  emulatorach terminala od czasów VT100. Wiązanie klawisza porównuje
  **wszystkie** znaczniki, więc wiązanie na gołą literę nie łapie skrótu
  z modyfikatorem, a goły `F8` nie łapie `Shift`+`F8` — od kroku 44 znaczą
  dwie różne rzeczy, z których jedna jest nieodwracalna.
- **Moduły** (krok 20): klasa modułu ma sufiks `Module` (`FileInfoModule`) i leży
  w warstwie `Presentation` swojego katalogu, bo implementuje zdolności
  wymieniające typy z `Presentation/Ui`. Zdolności nazywają się od tego, co
  wnoszą (`Provides…`) albo czego potrzebują (`Reads…`). Komenda modułu, która
  dostaje stan pętli, leży w jego `Presentation/Command` — tą samą zasadą, którą
  komendy rdzenia leżą w `Presentation/Cli/Command`, a nie w `Application`.
  Moduł może mieć **własne komponenty** w swoim `Presentation/Component` (krok 21:
  `PathLine`, `PreviewBox`), gdy element interfejsu zna typ jego domeny —
  postawiony w katalogu komponentów rdzenia przywróciłby rdzeniowi wiedzę, którą
  właśnie mu odebrano. Słownik prymitywów zostaje przy tym **zamknięty**: komponent
  modułu składa się z komponentów rdzenia, a nie z nowych kształtów. Moduł może
  mieć także **własne okno nakładane** w `Presentation/Overlay` (krok 30:
  `FilterOverlay`), gdy okno zna jego stan; obowiązuje tam ta sama zasada, co
  przy komponentach.
- **Wyjątki modułu** (krok 21) dziedziczą po rdzeniowym `DomainException`
  i mogą zadeklarować `Domain\Exception\DescribesProblem` — parę „klucz katalogu
  plus parametry”, z której `ProblemPresenter` składa zdanie dla użytkownika.
  Rozpoznawanie po klasie zostaje wyłącznie dla wyjątków rdzenia; wyjątek modułu
  przedstawia się sam, bo rdzeń nie ma prawa znać jego nazwy.
- PHPDoc tylko tam, gdzie typy PHP nie wystarczają (kształt kolekcji:
  `list<Entry>`). Komentarze tylko dla nieoczywistego „dlaczego”.

## 6. Wzorce kodu — przykłady

```php
// Value Object
final class Selection
{
    public function __construct(
        public readonly int $index,
    ) {
        if ($index < 0) {
            throw InvalidSelectionException::forNegativeIndex($index);
        }
    }

    public function equals(self $other): bool
    {
        return $this->index === $other->index;
    }
}

// Wyjątek domenowy
abstract class DomainException extends \RuntimeException
{
}

final class InvalidDirectoryPathException extends DomainException
{
    // Komunikat techniczny po angielsku; ścieżka jako pole, bo to z niej
    // `Presentation` składa przetłumaczone zdanie dla użytkownika.
    private function __construct(
        public readonly string $path,
    ) {
        parent::__construct(sprintf('"%s" is not an absolute directory path.', $path));
    }

    public static function forPath(string $path): self
    {
        return new self($path);
    }
}

// Interfejs repozytorium
interface DirectoryRepositoryInterface
{
    public function get(DirectoryPath $path): Directory;
}
```

## 7. Napisy i języki interfejsu

Ustalone w kroku 15 ([docs/plans/00-decyzje.md](plans/00-decyzje.md), D32).
**Żaden napis widoczny dla użytkownika nie jest wpisany na sztywno w kodzie.**

### Katalog napisów

- Pliki `lang/pl.php` i `lang/en.php` w korzeniu repozytorium zwracają płaską
  tablicę `klucz => napis`. Klucze są rozdzielone kropką (`browser.hints`,
  `settings.key.theme`), parametry zapisane w nawiasach klamrowych
  (`{path}`) — nazwane, nie pozycyjne, bo tłumaczenie bywa przestawione
  względem oryginału.
- Wpis zapisany jako lista niesie **formy mnogie**; regułę wyboru formy zna
  `Infrastructure\I18n\PluralRule` (polski — trzy formy, angielski — dwie).
- **Angielski jest językiem zapasowym**: brak klucza w wybranym języku sięga
  do `en`, brak klucza w ogóle daje na ekranie sam klucz. Żadna z tych ścieżek
  nie rzuca wyjątku.
- Kompletności katalogów pilnuje test `TranslatorServiceTest` — porównuje
  zestawy kluczy i liczbę form mnogich. Od kroku 20 obejmuje **także pliki
  modułów**.
- **Moduł niesie własne pliki napisów** w `src/Module/<Nazwa>/lang/`, a katalog
  je scala. Z pliku modułu przyjmowane są wyłącznie klucze zaczynające się od
  `module.<id>.`; pozostałe są pomijane i wracają komunikatem przy starcie.
  Kolizja z kluczem rdzenia jest przez to **niemożliwa z konstrukcji**, a źródło
  każdego napisu widać po samej nazwie klucza.

### Skąd którą warstwą sięga się po napis

| Warstwa | Droga do napisu |
|---|---|
| `Domain` | **Nie sięga wcale.** Wyjątki niosą techniczny komunikat po angielsku i typowane pola z danymi. |
| `Application` | Wyłącznie przez wstrzyknięty `Application\Port\TranslatorPort`. |
| `Infrastructure` | `TranslatorService::getInstance()` — jak każda inna usługa-Singleton. |
| `Presentation` | Wstrzyknięty port (`InputHandler` przez `ProblemPresenter`) albo Singleton w bootstrapie i w `bin/`. |

`Application\Dto` przechowuje **klucze**, nie napisy: `SettingKey::labelKey()`,
`SettingsTab::$labelKey`, `Language::labelKey()`. Tak samo `Application\Module`:
`ModuleInterface::nameKey()`, `ModuleSetting::$labelKey`,
`ModuleRejection::$reasonKey`.

`Presentation\Cli\ProblemPresenter` zamienia wyjątek na zdanie w języku
interfejsu — dobiera napis po klasie wyjątku, a konkrety bierze z jego pól.
Wyjątek nieprzewidziany dostaje zdanie ogólne; przy nieudanym starcie dopisuje
się do niego oryginalna treść, bo nikt jej już inaczej nie zobaczy.

### Wybór języka

Ustawienie `language` (`auto` | `pl` | `en`), domyślnie `auto`. `auto` czyta
`LC_ALL`, `LC_MESSAGES` i `LANG` — pierwszą zmienną z rozpoznawalnym kodem;
nierozpoznany kod schodzi do angielskiego. Wybór zapisany w konfiguracji jest
mocniejszy od środowiska.

`TranslatorService` pyta o ustawienie przy **każdym** wywołaniu — tak jak
`ThemeService` o motyw (D31) — więc zmiana języka na ekranie ustawień jest
widoczna w następnej klatce, bez restartu.

### Liczby

Separator dziesiętny należy do języka, więc formatowanie liczb idzie przez ten
sam port (`TranslatorPort::number()`). Gdy dostępne jest rozszerzenie `intl`,
liczbę składa `NumberFormatter`; w przeciwnym razie wchodzi ścieżka awaryjna
z separatorem z katalogu (`format.decimal`) — ta sama zasada, którą D20 przyjął
dla `Collator`. Grupowanie tysięcy jest wyłączone, żeby obie ścieżki dawały
identyczny napis.

## 8. Procesy projektu (od kroku 39)

Wejściem do każdego procesu jest **cel `make`**. `make` bez argumentów wypisuje
spis wszystkich celów z opisami; cel, którego w tym spisie nie ma, nie istnieje.

| Proces | Wejście | Co się pod tym kryje |
|---|---|---|
| Sprawdzenie środowiska | `make check-env` | Powłoka, **przed** instalacją zależności: PHP `^8.3`, `ext-imagick`, `ext-pcntl`, `stty`, Composer 2.x jako wymogi twarde; koder `SIXEL` jako ostrzeżenie (bez niego tryb tekstowy); `glfw`, `intl`, `xterm` jako informacja |
| Instalacja | `make install` | `composer install`; cel plikowy — powtórzony nie robi nic. Obejście SIGSEGV Composera: `make install-safe COMPOSER_INI_SCAN_DIR=…` |
| Jakość | `make qa` | `cs-check` → `stan` → `test`, **stop na pierwszym błędzie**. `make qa-full` przechodzi całość ze zbiorczym podsumowaniem; osobno `make cs`, `cs-check`, `stan` |
| Testy | `make test` | Obie grupy naraz; `make test-unit` i `make test-functional` — grupy z `phpunit.xml.dist` osobno. Zawężenie: `make test ARGS='--filter …'` |
| Pokrycie | `make coverage` | Raport HTML do `build/coverage/`; bez Xdebuga albo PCOV-u cel **czytelnie odmawia** — żadne z nich nie jest wymaganiem projektu |
| Pomiar wydajności | `make bench`, `bench-window`, `bench-text`, `bench-loop`, `bench-xterm` | `bin/render-bench` i jego cztery tory; `bench-xterm` idzie przez `bin/run-render-bench.sh`, bo faza przesyłu wymaga prawdziwego XTerma. **Poza bramką jakości** — krok 16 odrzucił bramkę wydajności i to nie zmieniło się |
| Budowa | `make build` | `bin/build-phar`: `build/light-manager-<wersja>.phar` (wersja z `composer.json`) plus `assets/` **obok** archiwum. Kończy się sprawdzeniem, że wynik się ładuje |
| Uruchomienie | `make run`, `run-window`, `run-xterm` | `bin/light-manager`, ten sam z `--window`, `bin/run.sh` (XTerm z zasobami trybu graficznego) |
| Podgląd wejścia terminala | `make probe`, `probe-xterm` | `bin/terminal-probe` — **jedyna** droga do sprawdzenia odpowiedzi DA1, bo wymaga interaktywnego terminala w trybie surowym |
| Klaster do sprawdzeń modułu `k8s` | `make minikube-start`, `minikube-stop`, `minikube-status` | `minikube` (od kroku 52). Klaster jest **obciążeniem maszyny, nie częścią aplikacji**: moduł działa bez niego, a przed każdym `make bench*` węzeł ma być **zatrzymany** (reguła 17). `minikube-status` wolno wołać zawsze — nie podnosi klastra ani go nie kładzie, a kod wyjścia narzędzia (7 przy zatrzymanym, 85 przy nieistniejącym) jest tu odpowiedzią, nie awarią |
| Sprzątanie | `make clean`, `dist-clean` | `clean` usuwa wytwory narzędzi i `build/`; `dist-clean` dokłada `vendor/`. Żaden nie tyka `docs/pomiary/` (wzorce są w repozytorium celowo, D33) ani konfiguracji w `HOME` |

### Reguła pierwszeństwa (D63)

Reguła ma dwie połowy i **druga jest ważniejsza**, bo to ona zapobiega
faktycznej stracie pracy:

1. **Wejściem do procesu jest cel `make`.** Bramki, instalacji, budowy, testów
   i pomiaru nie składa się z pamięci.
2. **Narzędzie repozytorium ma pierwszeństwo przed doraźnym zastępnikiem.**
   Pomiar wydajności robi `bin/render-bench` — z jego fazami, wzorcami
   i metryczką środowiska — a nie własna pętla `microtime()`; wejście terminala
   sprawdza `bin/terminal-probe`, a nie `read` w powłoce; scenariusz pomiarowy
   dokłada się **do** `ScenarioFactory`, a nie obok niej. Doraźny zastępnik nie
   jest szybszy — jest **niepodpięty do niczego**: nie porówna się z wzorcem,
   nie zostawi śladu i nie odpowie następnym razem.

**Granica, bez której reguła staje się dogmatem:** zawężenie przebiegu wolno
wołać wprost — pojedynczy test filtrem PHPUnita, jedna oś `bin/render-bench`,
`composer` przy pracy nad zależnościami. Cel `make` jest wejściem do **procesu**,
a nie kagańcem na narzędzie. Zakazane jest dorabianie **równoległej drogi** do
procesu, który wejście już ma.

**Makefile nie jest drugim źródłem prawdy.** Cele wołają `composer`,
`bin/render-bench`, `bin/run*.sh` i `bin/build-phar`; konfiguracji tych narzędzi
nie powtarzają własnymi słowami. Definicje poleceń jakości mieszkają
w `composer.json`, zasoby XTerma — w skryptach `bin/run*.sh`, podział testów —
w `phpunit.xml.dist`.

**Pomiar ma ponadto regułę własną, starszą od tego rozdziału** (reguła 17
Skilla): przed uruchomieniem celu pomiarowego i przed oglądaniem klatki
w prawdziwym terminalu poproś użytkownika o zwolnienie mocy hosta i **poczekaj
na potwierdzenie**. Cele `bench*` nie mają bariery technicznej — mają regułę.

### Co budowa wkłada do archiwum, a czego nie

`bin/build-phar` bierze `src/`, `lang/`, `bin/light-manager`, `composer.json`,
`composer.lock` oraz `vendor/` zainstalowane **bez zależności deweloperskich**,
z autoloaderem z mapy klas. Nie bierze `tests/`, `docs/` ani narzędzi
repozytorium: `bin/render-bench` liczy ścieżki do `docs/pomiary/`
i `tests/Golden/`, a `bin/install-desktop-entry` potrzebuje `realpath()` pliku
wykonywalnego — spod `phar://` żadnego z tych miejsc nie ma.

Dwie rzeczy, które z postaci archiwum wynikają i o których trzeba wiedzieć:

- **Katalogi napisów czytają się spod `phar://` bez zmian** — `Catalog` robi
  `is_file()` i `require`, a ścieżki liczą się z `dirname(__DIR__, …)`. Dotyczy
  to również katalogów modułów (`ModuleInterface::translations()`).
- **`assets/` leżą obok archiwum, nie w nim** — silnik `GL\Audio` jest
  rozszerzeniem C i pliku spod `phar://` nie przeczyta. W zbudowanej aplikacji
  utwór wskazuje się **ścieżką bezwzględną** w ustawieniach modułu audio;
  ścieżka względna liczy się od korzenia projektu, którego dystrybucja nie ma.

## 9. Co dalej

Tabela mapująca kroki wdrożenia (05–12) na warstwy/katalogi:
[docs/plans/archiwum/01-warstwy-ddd-i-struktura-katalogow.md](plans/archiwum/01-warstwy-ddd-i-struktura-katalogow.md#tabela-kroki-0512-planu-wdrożenia--warstwy--katalogi).
Bieżący status realizacji: [docs/plans/00-index.md](plans/00-index.md).
