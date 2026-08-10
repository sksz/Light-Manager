# Krok 02 — Wzorzec Singleton i bootstrap usług poza pętlą aplikacji

## Status

Ukończony

## Zależności

Krok 01 (musi być znana struktura warstw, żeby wiedzieć, gdzie leżą usługi
i gdzie przebiega granica warstwy `Domain`).

## Model i wysiłek

Opus / high — architektura-krytyczna decyzja z realnym ryzykiem
niekontrolowanego globalnego stanu, jeśli źle udokumentowana i
niekonsekwentnie stosowana w kolejnych krokach.

## Cel

Ustalić dokładny, powtarzalny kształt wzorca Singleton dla klas usług spoza
warstwy `Domain` (zgodnie z decyzją: każda usługa to osobny, klasyczny
Singleton — zobacz [00-decyzje.md](00-decyzje.md), D7), zasady bootstrapu
aplikacji oraz twarde granice — które warstwy wolno korzystać z
Singletonów, a które nie.

## Zakres

- Standardowy szkielet klasy-Singletona dla usług: prywatny konstruktor,
  statyczna metoda `getInstance()`, zablokowane klonowanie i
  (de)serializacja — jeden wzorzec do powielania we wszystkich usługach
  (np. `TerminalService`, `SixelCapabilityService`, `RendererService` w
  `Infrastructure`/`Presentation`).
- Twarda reguła: klasy w `src/Domain` (encje, obiekty wartości, agregaty,
  serwisy domenowe) **nigdy** nie odwołują się do Singletonów usług i
  pozostają w pełni testowalne bez terminala/Imagick. Singletony wolno
  wywoływać wyłącznie z warstwy `Application` (orkiestracja use case'ów) i
  `Presentation` (bootstrap, pętla gry).
- Kolejność bootstrapu na starcie aplikacji — jawnie ustalona sekwencja
  inicjalizacji Singletonów zgodna z zależnościami z planu wdrożenia (np.
  `TerminalService` przed `SixelCapabilityService` (krok 07) przed
  `RendererService` (krok 08)).
- Znana wada wybranego podejścia (per-klasowy Singleton zamiast
  centralnego kontenera) — trudniejsza podmiana zależności w testach — i
  pragmatyczna mitygacja: każda klasa-Singleton udostępnia metodę
  `resetInstance()` przeznaczoną wyłącznie do użytku w testach, tak by nie
  blokować testowania warstwy `Application` mimo wybranego wzorca.
- Tabela: która usługa (z kroków 06–09 planu wdrożenia) staje się
  Singletonem i w jakiej kolejności jest inicjalizowana przy starcie.

## Poza zakresem tego kroku

Ogólny styl kodowania (krok 03), konkretna implementacja poszczególnych
usług — to dzieje się w krokach 06–09 planu wdrożenia, tu tylko wzorzec i
zasady jego użycia.

## Ryzyka

- Rozproszone Singletony (jeden per usługa, bez centralnego rejestru) mogą
  z czasem prowadzić do ukrytych zależności między usługami, trudnych do
  wyśledzenia bez jednego miejsca składającego całość — do zminimalizowania
  przez jawną tabelę kolejności bootstrapu i regułę „Domain nie zna
  Singletonów”.
- Metoda `resetInstance()` używana poza kontekstem testów mogłaby wprowadzić
  subtelne błędy (niespójny stan między wywołaniami) — do jasnego
  oznaczenia w kodzie jako API tylko-do-testów.

## Kryteria ukończenia

- Istnieje jeden, spisany szkielet klasy-Singletona, gotowy do powielenia
  przez wszystkie usługi z kroków 06–09.
- Jawnie spisana reguła „które warstwy wolno korzystać z Singletonów”, wraz
  z uzasadnieniem.
- Ustalona i spisana kolejność bootstrapu usług na starcie aplikacji.

## Specyfikacja

Rozstrzygnięcia poniżej wynikają z trzech decyzji podjętych przy starcie
tego kroku: wspólny mechanizm przez **dziedziczenie po klasie
abstrakcyjnej** (nie trait), reset w testach przez **Reflection** (bez
publicznego API produkcyjnego), kolejność bootstrapu przez **jawną klasę
orkiestrującą** (nie leniwe samo-okablowanie). Pełne uzasadnienia:
[00-decyzje.md](00-decyzje.md), D11.

### Klasa bazowa `AbstractSingleton`

Lokalizacja: `Infrastructure/Support/AbstractSingleton` (dopisane do
drzewa katalogów z kroku 01 — to jedyne rozszerzenie tamtej struktury
wynikające z tego kroku). Wszystkie usługi-Singletony z `Infrastructure`
dziedziczą po tej klasie; nic poza `Infrastructure` po niej nie dziedziczy.

```php
<?php

declare(strict_types=1);

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

**Świadome odstępstwo od dosłownego zapisu w „Zakres” tego kroku:**
konstruktor jest `protected`, nie `private`. Przy dziedziczeniu po klasie
abstrakcyjnej z jedną, współdzieloną metodą `getInstance()` (wzorzec
„self-registering singleton przez late static binding”, `new static()`)
konstruktor musi być widoczny dla klasy bazowej i klas pochodnych —
`private` uniemożliwiłby to mechanicznie. `protected` daje dokładnie to
samo zabezpieczenie na zewnątrz (`new TerminalService()` spoza hierarchii
klas nadal kończy się błędem dostępu) — różnica dotyczy wyłącznie
widoczności wewnątrz hierarchii, nie z zewnątrz. Konkretne usługi (np.
`TerminalService`) **nie deklarują własnego konstruktora** ponownie, chyba
że potrzebują logiki inicjalizacyjnej — wtedy nadpisują `__construct()`
jako `protected` i mogą (nie muszą) wywołać `parent::__construct()`.

`getInstance()`, `__clone()`, `__wakeup()`, `__sleep()` są `final` — klasy
pochodne nie mogą nadpisać mechaniki Singletona, tylko dostarczyć własny
konstruktor i logikę usługi.

### Reguła: które warstwy wolno korzystać z Singletonów

| Warstwa | Wolno wywoływać `*Service::getInstance()`? | Uzasadnienie |
|---|---|---|
| `Domain` | **Nie, nigdy.** | Encje/VO/agregaty/serwisy domenowe muszą pozostać testowalne bez terminala/Imagick i niezależne od cyklu życia procesu CLI. |
| `Application` | Tak, wyłącznie poprzez typ interfejsu portu (`Application/Port`) wstrzyknięty do use case'u — use case nie wywołuje `getInstance()` sam, dostaje już gotowy obiekt. | Use case zna kontrakt (port), nie wie, że pod spodem jest Singleton — podmiana na inną implementację (np. w teście) wymaga tylko innego obiektu spełniającego interfejs. |
| `Infrastructure` | Tak — to tu żyją same klasy-Singletony i mogą się nawzajem pobierać przez `getInstance()`, jeśli jedna usługa potrzebuje drugiej (np. wewnątrz konstruktora). | To naturalne miejsce na `getInstance()` — usługi implementują porty i mogą współpracować bezpośrednio. |
| `Presentation` | Tak, ale w praktyce ograniczone do jednego miejsca: klasy `Bootstrap` (patrz niżej) oraz punktu wejścia w `bin/`. | Bootstrap to jedyne miejsce, gdzie aplikacja świadomie „każe” Singletonom powstać we właściwej kolejności; reszta `Presentation` powinna dostawać zależności jako wstrzyknięte porty, tak jak `Application`. |

Reguła operacyjna do przestrzegania od kroku 05 wzwyż: `use
LightManager\Infrastructure\...` w plikach `Domain/**` jest zawsze błędem
przeglądu kodu. Rozważenie narzędzia wymuszającego to automatycznie (np.
reguła architektury w PHPStan albo Deptrac) to wejście dla kroku 03/05, nie
decyzja tego kroku.

### Orkiestracja bootstrapu: klasa `Bootstrap`

`Bootstrap` **nie jest usługą i nie dziedziczy po `AbstractSingleton`** —
to jednorazowa procedura uruchamiana raz, z punktu wejścia w `bin/`, przed
wejściem w pętlę gry (krok 09). Żyje w `Presentation/Cli/Bootstrap`.

```php
<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Infrastructure\Rendering\RendererService;
use LightManager\Infrastructure\Terminal\SixelCapabilityService;
use LightManager\Infrastructure\Terminal\TerminalService;

final class Bootstrap
{
    public static function boot(): void
    {
        TerminalService::getInstance();
        SixelCapabilityService::getInstance();
        RendererService::getInstance();
    }
}
```

Zasada rozstrzygająca, co trafia do `Bootstrap::boot()`: usługa jest tu
wywoływana jawnie **tylko wtedy, gdy jej konstruktor ma efekt uboczny
wymagany przed rozpoczęciem pętli gry** (np. wejście w tryb raw terminala,
zarejestrowanie handlerów sygnałów, wykrycie trybu renderowania). Usługi
bez takiego wymogu inicjalizują się leniwie, przy pierwszym realnym użyciu
— nie trzeba ich wymieniać w `Bootstrap`.

### Tabela: usługi-Singletony i kolejność bootstrapu

| Usługa | Implementuje port | Warstwa / katalog | Krok wdrożenia | W `Bootstrap::boot()`? | Kolejność |
|---|---|---|---|---|---|
| `TerminalService` | `TerminalPort` | `Infrastructure/Terminal` | 06 | Tak | 1 — wchodzi w tryb raw, rejestruje sygnały; musi zadziałać, zanim cokolwiek czyta STDIN. |
| `SixelCapabilityService` | `RendererModeDetectorPort` | `Infrastructure/Terminal` | 07 | Tak | 2 — zależy od `TerminalService` (potrzebuje surowego, nieblokującego odczytu odpowiedzi DA1). |
| `RendererService` | `FrameRendererPort` | `Infrastructure/Rendering` | 08 | Tak | 3 — zależy od `SixelCapabilityService` (musi znać `RendererMode`, zanim zbuduje właściwy renderer). |
| `ThumbnailGeneratorService` | `ThumbnailGeneratorPort` | `Infrastructure/Imagick` | 12 | Nie | Brak wymogu — inicjalizuje się leniwie przy pierwszym podglądzie obrazu (krok 12); niezależna od pozostałych trzech. |

To rozstrzyga otwartą kwestię z tabeli portów w kroku 01
(„Singleton (krok 02)? Do potwierdzenia”): `ThumbnailGeneratorService`
**jest** Singletonem jak pozostałe, tylko nie wymaga miejsca w jawnej
sekwencji `Bootstrap::boot()`.

### Reset w testach: `ResetsSingletons` (bez publicznego API produkcyjnego)

Ponieważ `$instances` to **jedna, współdzielona** prywatna statyczna
właściwość zadeklarowana na `AbstractSingleton` (nie kopiowana per klasa,
jak byłoby przy trait), reset dowolnej usługi wymaga tylko jednego
Reflection-owego uchwytu na tę współdzieloną właściwość — to konkretna,
praktyczna korzyść z wyboru dziedziczenia zamiast trait przy okazji tej,
odrębnie podjętej, decyzji o resecie.

```php
<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Infrastructure\Support\AbstractSingleton;
use ReflectionProperty;

trait ResetsSingletons
{
    protected function resetSingleton(string $singletonClass): void
    {
        $property = new ReflectionProperty(AbstractSingleton::class, 'instances');
        $property->setAccessible(true);

        /** @var array<class-string, AbstractSingleton> $instances */
        $instances = $property->getValue();
        unset($instances[$singletonClass]);
        $property->setValue(null, $instances);
    }
}
```

Umiejscowienie: `tests/Support/ResetsSingletons.php` (poza strukturą
`src/`, zgodnie z konwencją, że `tests/Support` mieści pomoce testowe,
które nie mają odpowiednika produkcyjnego namespace'u — do potwierdzenia
razem z resztą konwencji testowych w kroku 03). Testy warstwy
`Application`/`Infrastructure` używają traita przez `use ResetsSingletons;`
w klasie testowej i wywołują `$this->resetSingleton(TerminalService::class)`
w `tearDown()`. Dokładne API Reflection do zweryfikowania względem
ostatecznej wersji PHP ustalonej w kroku 03 (sygnatury
`ReflectionProperty::setValue()` są stabilne w całej serii PHP 8.x, więc
ryzyko rozjazdu jest niskie).

## Dziennik realizacji

- **2026-08-07** — Ukończono. Ustalono szkielet `AbstractSingleton`
  (dziedziczenie + late static binding, konstruktor `protected` z
  udokumentowanym uzasadnieniem odstępstwa od „prywatny” z zakresu kroku),
  regułę „które warstwy wolno korzystać z Singletonów” (Domain: nigdy),
  klasę orkiestrującą `Bootstrap` z jawną kolejnością trzech usług
  (`TerminalService` → `SixelCapabilityService` → `RendererService`),
  zasadę odróżniającą usługi wymagające miejsca w `Bootstrap::boot()` od
  tych inicjalizowanych leniwie (`ThumbnailGeneratorService`), oraz
  mechanizm resetu w testach przez Reflection (`ResetsSingletons`, bez
  żadnego publicznego API produkcyjnego). Rozstrzygnięto przy okazji
  otwartą kwestię z tabeli portów kroku 01 — `ThumbnailGeneratorService`
  jest Singletonem. Jedyne rozszerzenie struktury z kroku 01: dopisano
  `Infrastructure/Support/` na współdzieloną infrastrukturę. Bez fizycznego
  tworzenia plików PHP — to nadal zakres kroku 05. Decyzje odnotowane w
  [00-decyzje.md](00-decyzje.md), D11.
