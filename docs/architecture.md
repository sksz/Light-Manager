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
├── Domain/
│   ├── Aggregate/       # korzenie agregatów (encje z tożsamością)
│   ├── ValueObject/     # niemutowalne wartości, w tym natywne enum'y
│   ├── Repository/      # interfejsy repozytoriów (bez implementacji)
│   ├── Service/         # serwisy domenowe (obecnie: brak — zarezerwowane)
│   ├── Event/           # zdarzenia domenowe (obecnie: brak — zarezerwowane)
│   └── Exception/       # wyjątki domenowe
├── Application/
│   ├── UseCase/         # przypadki użycia orkiestrujące Domain przez Porty
│   ├── Ui/              # klatka, płaszczyzna, prymitywy, geometria (krok 18)
│   ├── Command/         # kontrakt komendy, parser wiersza, rejestr (krok 19)
│   ├── Module/          # kontrakt modułu w części danowej, rejestr (krok 20)
│   ├── Dto/             # obiekty transferu danych wejście/wyjście
│   └── Port/             # interfejsy portów wyjściowych
├── Infrastructure/
│   ├── Filesystem/      # implementacje repozytoriów Domain
│   ├── Terminal/        # implementacje portów terminala
│   ├── Rendering/       # implementacje portu renderowania
│   ├── Imagick/         # adaptery na bibliotekę Imagick
│   ├── Config/          # trwała konfiguracja (plik JSON w katalogu domowym)
│   ├── I18n/            # katalogi napisów, wybór języka, liczba mnoga
│   ├── Diagnostics/     # pomiar wydajności potoku (narzędzie bin/render-bench)
│   └── Support/         # wspólna infrastruktura (AbstractSingleton)
└── Presentation/
    ├── Ui/              # komponenty, kontenery, kontrakt ekranu, kursor
    │   ├── Component/   # Panel, Label, ListView, Tabs, Choice, Toggle,
    │   │                # Button, Dialog, StatusBar, ImageBox, Spacer,
    │   │                # TextInput (krok 19)
    │   ├── Container/   # VStack, Slot
    │   ├── Module/      # zdolności modułu dotykające interfejsu (krok 20)
    │   └── Overlay/     # okna nakładane: CommandOverlay, MessageOverlay
    └── Cli/             # bootstrap, pętla gry, składanie klatki, ekrany,
                         # komendy rdzenia (Command/)

src/Module/              # moduły (krok 20) — po katalogu na moduł
└── FileInfo/
    ├── Application/     # UseCase/, Port/, Dto/ modułu
    ├── Infrastructure/  # usługi modułu (Singletony na zasadach rdzenia)
    ├── Presentation/    # klasa modułu, jego ekran, jego komendy
    └── lang/            # napisy modułu (pl.php, en.php)
```

**Warstwa `Module` stoi na zewnątrz wszystkiego** i jest jedynym miejscem, do
którego rdzeń nie ma prawa sięgnąć inaczej niż przez kontrakt modułu. Moduł
powtarza wewnątrz podział rdzenia, ale **katalog warstwy pustej po prostu nie
powstaje** — `FileInfo` nie ma własnego słownika domenowego, więc nie ma
katalogu `Domain/`.

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
| `Application/Module` | `ModuleInterface`, `ModuleShortcut`, `ModuleContext`, `ContextEntryKind`, `ModuleSettingsTab`, `ModuleSetting`, `ModuleSettingKind`, `ProvidesSettingsTab`, `ProvidesCommands`, `ModuleRegistry`, `ModuleRejection` |
| `Presentation/Ui/Module` | `ProvidesScreen`, `ProvidesHelpTab`, `ReadsContext` |

Powód podziału jest ten sam, co przy komendach: interfejs opisany
w `Application`, który wymieniałby `ScreenInterface`, sięgałby po klasę z warstwy
leżącej **na zewnątrz** niego. Stąd też skrót modułu jest daną (`ModuleShortcut`),
a nie `KeyBinding`iem — rejestr, który ma zostać w `Application`, musi umieć
porównać dwa skróty, nie widząc `Presentation`.

## 2. Słownik domenowy (ubiquitous language)

| Termin (PL) | Identyfikator | Blok DDD | Katalog | Opis |
|---|---|---|---|---|
| Ścieżka katalogu | `DirectoryPath` | Value Object | `Domain/ValueObject` | Zwalidowana, bezwzględna ścieżka; rzuca `InvalidDirectoryPathException`. |
| Wpis | `Entry` | Value Object | `Domain/ValueObject` | Niemutowalny opis elementu katalogu (nazwa, `EntryType`, rozmiar). |
| Rodzaj wpisu | `EntryType` | Value Object (`enum`) | `Domain/ValueObject` | `Directory` \| `File`. |
| Zaznaczenie | `Selection` | Value Object | `Domain/ValueObject` | Nieujemny indeks zaznaczonego `Entry`. |
| Katalog | `Directory` | **Agregat, Encja** | `Domain/Aggregate` | Tożsamość = `DirectoryPath`. Agreguje `Entry` i `Selection`; mutowalny w miejscu (encje ≠ Value Objects). |
| Tryb renderowania | `RendererMode` | Value Object (`enum`) | `Domain/ValueObject` | `Sixel` \| `TextFallback`. |
| Komunikat | `Message` | Value Object | `Domain/ValueObject` | Treść paska stanu wraz z tonem; `marked()` dokleja znak wiodący. |
| Ton komunikatu | `MessageTone` | Value Object (`enum`) | `Domain/ValueObject` | `Info` \| `Warning` \| `Error`; każdy ma własny znak (`·`, `!`, `×`). |
| Położenie okna | `ScrollPosition` | Value Object | `Domain/ValueObject` | Pierwszy widoczny wpis, liczba widocznych i liczba wszystkich — wejście do suwaka. |
| Podgląd miniatury | `ThumbnailPreview` | Value Object | `Domain/ValueObject` | Wygenerowana miniatura (dane + wymiary); `null` = brak podglądu. |

Od kroku 18 (D36) **`Domain` nie zna już słownictwa rysowania**. Klatka,
wiersz, styl wiersza i okienko wyprowadziły się stamtąd: klatka do
`Application/Ui`, reszta zniknęła na rzecz komponentów i prymitywów. Domena
menadżera plików opisuje pliki, katalogi i zaznaczenie — nie to, jak wyglądają.

### Słownik interfejsu (od kroku 18)

| Termin (PL) | Identyfikator | Warstwa | Katalog | Opis |
|---|---|---|---|---|
| Klatka | `Frame` | Application | `Application/Ui` | Stos płaszczyzn w porządku nakładania — jedyne, co przechodzi przez `FrameRendererPort`. |
| Płaszczyzna | `Plane` | Application | `Application/Ui` | Niezależnie umieszczony plan obrazu: prostokąt i lista prymitywów. Spodnia to ekran, nad nią stoją okna nakładane. Flaga `opaque` każe rendererowi **wymazać prostokąt** przed narysowaniem — bez niej okno złożone z samej obwódki przepuszcza to, co pod nim. |
| Prymityw | `Primitive` | Application | `Application/Ui/Primitive` | Kształt gotowy do narysowania: `TextRun`, `RoundRect`, `CornerBrackets`, `Bar`, `Bitmap`, `Scrollbar`. Zamknięty słownik. |
| Rola koloru | `Role` | Application | `Application/Ui` | Rola motywu (tło, obwódka, akcent, …). Prymityw niesie rolę, nie kolor. |
| Prostokąt | `Rect` | Application | `Application/Ui` | Obszar w **siatce znakowej**; piksele zaczynają się dopiero w rendererze. |
| Komponent | `ComponentInterface` | Presentation | `Presentation/Ui` | Element interfejsu, który rysuje się w zadanym prostokącie: `Panel`, `Label`, `ListView`, `Tabs`, `Choice`, `Toggle`, `Button`, `Dialog`, `StatusBar`, `ImageBox`, `Spacer`. |
| Kontener | `VStack`, `Slot` | Presentation | `Presentation/Ui/Container` | Rozdziela miejsce między dzieci; `Slot` niesie rozmiar minimalny, preferowany i kolejność ustępowania. |
| Kursor | `FocusableInterface` | Presentation | `Presentation/Ui` | Komponent przyjmujący klawisze; `handle()` oddaje `bool`, więc nieobsłużony klawisz wędruje wyżej. |
| Wiązanie klawisza | `KeyBinding` | Presentation | `Presentation/Ui` | Klawisz wraz z kluczem opisu — jedno źródło dla obsługi, podpowiedzi w stopce i spisu w pomocy. |
| Ekran | `ScreenInterface` | Presentation | `Presentation/Ui` | Treść środkowego panelu wraz z obsługą klawiszy. Ścieżka, pasek stanu i pas podglądu należą do rdzenia. |
| Okno nakładane | `OverlayInterface` | Presentation | `Presentation/Ui` | Płaszczyzna **nad** ekranem, która sama mówi, gdzie stanąć, i **zużywa albo przepuszcza** klawisz. Przepuszczony trafia wyłącznie do klawiszy globalnych — nigdy do ekranu pod spodem. |
| Karetka | `TextInput` | Presentation | `Presentation/Ui/Component` | Miejsce wpisywania **wewnątrz** komponentu — w odróżnieniu od kursora, który wędruje **między** komponentami. |
| Komenda | `CommandInterface` | Application | `Application/Command` | Czynność wywoływana po nazwie wraz z deklaracją argumentów. Nazwa nosi przestrzeń właściciela (`core.*`), a wynik wskazuje ekran **identyfikatorem**, bo `Application` nie widzi `ScreenInterface`. |

Granica między tymi dwiema połówkami jest jednozdaniowa i wynika z reguły
zależności: **komponent wie, jak wyglądać; prymityw jest tym, co z tej wiedzy
zostaje po przekroczeniu portu**. Renderery leżą w `Infrastructure` i
implementują port z `Application`, więc nie wolno im zobaczyć klasy
z `Presentation` — a prymityw musi być dla nich widoczny.

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
| `TerminalPort` | `TerminalService` | Tak | Tak — kolejność 1 |
| `RendererModeDetectorPort` | `SixelCapabilityService` | Tak | Tak — kolejność 2 |
| `FrameRendererPort` | `RendererService` | Tak | Tak — kolejność 3 |
| `ThumbnailGeneratorPort` | `ThumbnailGeneratorService` | Tak | Nie — leniwa inicjalizacja |
| `FileInspectorPort` | `FileInspectorService` | Tak | Nie — leniwa inicjalizacja |
| `ViewportPort` | `TerminalSizeService` | Tak | Tak — pośrednio, przez `RendererService` |
| `FrameLayoutPort` | `HudFrameLayoutService` | Tak | Nie — leniwa inicjalizacja |
| `SettingsPort` | `Config\SettingsService` | Tak | Tak — kolejność 3, **przed** rendererem |
| `ThemePort` | `Rendering\ThemeService` | Tak | Nie — leniwa inicjalizacja |

`Domain/Repository`: `DirectoryRepositoryInterface` → `FilesystemDirectoryRepository`
(nie jest portem aplikacyjnym, lecz domenową abstrakcją dostępu do danych).

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

**Klatka i ekrany** (krok 14): `FrameRendererPort::render()` dostaje `Frame`
**wraz z** `FrameLayout`. Wcześniej renderer liczył układ po raz drugi, po swojej
stronie; od kroku 14 rozminąłby się z warstwą aplikacji na pewno, bo o kształcie
stref decyduje pokazywany ekran (`Application/Dto/Screen`), którego renderer sam
z siebie nie zna. Ekran ustawień i pomocy podmieniają wyłącznie środkowy panel —
mają własną etykietę i przejmują wiersze pasa podglądu, bo miniatura nie ma tam
czego pokazywać.

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
- Konfiguracje (`.php-cs-fixer.dist.php`, `phpstan.neon.dist`,
  `phpunit.xml.dist`) — zaprojektowane w
  [docs/plans/03-standardy-stylu-kodowania.md](plans/03-standardy-stylu-kodowania.md),
  utworzone w korzeniu repozytorium w kroku 05 (bez zmian względem planu).
  Skróty uruchomieniowe: `composer test`, `composer stan`, `composer cs`,
  `composer cs:check`.

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
  gdy formalnie jest niemutowalną wartością.
- **Moduły** (krok 20): klasa modułu ma sufiks `Module` (`FileInfoModule`) i leży
  w warstwie `Presentation` swojego katalogu, bo implementuje zdolności
  wymieniające typy z `Presentation/Ui`. Zdolności nazywają się od tego, co
  wnoszą (`Provides…`) albo czego potrzebują (`Reads…`). Komenda modułu, która
  dostaje stan pętli, leży w jego `Presentation/Command` — tą samą zasadą, którą
  komendy rdzenia leżą w `Presentation/Cli/Command`, a nie w `Application`.
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

## 8. Co dalej

Tabela mapująca kroki wdrożenia (05–12) na warstwy/katalogi:
[docs/plans/01-warstwy-ddd-i-struktura-katalogow.md](plans/01-warstwy-ddd-i-struktura-katalogow.md#tabela-kroki-0512-planu-wdrożenia--warstwy--katalogi).
Bieżący status realizacji: [docs/plans/00-index.md](plans/00-index.md).
