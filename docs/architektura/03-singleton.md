# 3. Wzorzec Singleton, porty i bootstrap

> Rozdział 3 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

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
[docs/plans/00-decyzje.md](../plans/00-decyzje.md), D17). Port zakłada się wtedy i
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
