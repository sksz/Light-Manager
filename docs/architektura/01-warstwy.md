# 1. Warstwy (Domain-Driven Design)

> Rozdział 1 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

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

### Jedyny wyjątek: zapis na dysk (krok 41, D66/D75)

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
