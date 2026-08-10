# Krok 03 — Standardy stylu kodowania PHP

## Status

Ukończony

## Zależności

Krok 01 (konwencje muszą odnosić się do właściwych warstw/katalogów
ustalonych w tamtym kroku).

## Model i wysiłek

Sonnet / medium — dobrze przetarty teren (PSR-12, PHPStan, PHP-CS-Fixer to
standardowe, nieprojektowe wybory dla nowoczesnego PHP), effort na
przyjętej podłodze (zobacz [00-decyzje.md](00-decyzje.md), D5).

## Cel

Ustalić konkretne, wymuszalne narzędziami standardy stylu kodu
obowiązujące we wszystkich warstwach `src/`, tak by kroki 05–12 planu
wdrożenia miały jednoznaczny, spójny punkt odniesienia zamiast improwizować
styl krok po kroku.

## Zakres

- Minimalna wersja PHP i uzasadnienie (do potwierdzenia w trakcie
  realizacji, z uwzględnieniem wymagań Imagick oraz przydatnych funkcji
  języka: `readonly` properties, `enum`, typowane właściwości).
- `declare(strict_types=1)` obowiązkowe w każdym pliku PHP.
- PSR-12 jako baza formatowania + PHP-CS-Fixer jako narzędzie wymuszające,
  z konfiguracją trzymaną w repozytorium.
- PHPStan jako statyczna analiza (docelowy poziom do ustalenia, dążenie do
  max/9), uruchamiana jako część pracy nad każdym kolejnym krokiem.
- Konwencje dla bloków taktycznych DDD z kroku 01:
  - obiekty wartości: niemutowalność (`readonly` właściwości), samowalidacja
    w konstruktorze rzucająca wyjątki domenowe zamiast zwracania `null`/`false`;
  - encje: jawny identyfikator, porównywanie po identyfikatorze, nie po
    wartości;
  - interfejsy repozytoriów: nazwane od czasownika domenowego, zwracają
    obiekty Domain, nie surowe struktury (tablice, `stdClass`);
  - wyjątki domenowe: osobna hierarchia w `Domain/Exception`, warstwy wyższe
    nie rzucają generycznych `\Exception`/`\RuntimeException` w kodzie
    domenowym.
- Konwencje testów: PHPUnit, katalog `tests/` odzwierciedlający strukturę
  `src/`; testy jednostkowe obowiązkowe dla `Domain`/`Application` (czyste,
  bez zależności od terminala/Imagick); dla `Infrastructure`/`Presentation`
  testy w miarę możliwości, z akceptacją, że część zachowań (realne
  renderowanie w terminalu) pozostaje do weryfikacji manualnej — zgodnie z
  ograniczeniami opisanymi już w krokach 06–08 planu wdrożenia.
- Konwencje komentarzy/PHPDoc: PHPDoc tylko tam, gdzie typy PHP nie
  wystarczają (np. adnotacje generyczne dla kolekcji obiektów); zakaz
  komentarzy opisujących „co” zamiast „dlaczego”.

## Poza zakresem tego kroku

Struktura katalogów (krok 01), wzorzec Singleton (krok 02) — tu wyłącznie
konwencje pisania i formatowania kodu.

## Ryzyka

- Zbyt wysoki poziom PHPStan ustawiony od razu na starcie może spowolnić
  wczesne kroki planu wdrożenia (dużo adnotacji typów dla API Imagick,
  które bywa słabo otypowane) — do zweryfikowania praktycznie przy pierwszym
  użyciu Imagick w kroku 08, z możliwością udokumentowanych, punktowych
  wyjątków (`@phpstan-ignore` z uzasadnieniem) zamiast obniżania poziomu
  globalnie.

## Kryteria ukończenia

- Konfiguracja PHP-CS-Fixer i PHPStan gotowa do użycia (lub jawnie
  zaplanowana do dodania w kroku 05 „Szkielet projektu”).
- Spisane konwencje dla obiektów wartości, encji, repozytoriów, wyjątków i
  testów, gotowe do zastosowania od kroku 05 wzwyż.

## Specyfikacja

### Wersja PHP

**`^8.3`** (composer.json w kroku 05: `"php": "^8.3"`). Zgodnie z decyzją
podjętą przy starcie tego kroku — pokrywa się z wersją zainstalowaną
lokalnie (`php -v` → 8.3.11), więc krok 05 uruchomi się bez dodatkowej
instalacji PHP. Daje wszystko, czego wymaga specyfikacja z kroków 01–02:
`readonly` properties, natywne `enum`, typowane właściwości, `never`.

Uwaga poboczna zaobserwowana przy sprawdzaniu wersji: rozszerzenie
`imagick` nie jest obecnie załadowane w tym środowisku (`php -m` go nie
pokazuje) — to nie jest przedmiotem tego kroku, ale krok 05 i tak ma to
zweryfikować (patrz jego „Ryzyka”), więc odnotowuję to tu jako wczesne
ostrzeżenie, żeby nie było zaskoczeniem.

### `declare(strict_types=1)`

Obowiązkowe w każdym pliku `.php` w `src/` i `tests/`, wymuszane
automatycznie regułą PHP-CS-Fixer `declare_strict_types` (patrz niżej) —
nie tylko konwencja do pamiętania ręcznie.

### PHP-CS-Fixer — konfiguracja

Baza `@PSR12` plus wąski zestaw reguł niekontrowersyjnych (bez
eksperymentalnego `@PhpCsFixer:risky` w całości — tylko pojedyncze,
świadomie wybrane reguły `risky`, stąd `setRiskyAllowed(true)`).

```php
<?php
// .php-cs-fixer.dist.php (do fizycznego utworzenia w kroku 05)

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'void_return' => true,
        'binary_operator_spaces' => ['default' => 'single_space'],
    ])
    ->setFinder($finder);
```

### PHPStan — konfiguracja

Poziom **`max`** od samego początku (nie numer `9` na sztywno — `max`
pozostaje poprawny niezależnie od przyszłych wersji PHPStan). Zgodnie z
już odnotowanym ryzykiem tego kroku: gdy Imagick (krok 08) okaże się
niewystarczająco otypowane, rozwiązaniem są punktowe, uzasadnione
`@phpstan-ignore` w konkretnej linii — **nie** obniżanie poziomu globalnie.

```neon
# phpstan.neon.dist (do fizycznego utworzenia w kroku 05)
parameters:
    level: max
    paths:
        - src
        - tests
```

### Testy — PHPUnit

- Paczka `phpunit/phpunit` (gałąź zgodna z PHP 8.3 — dokładną wersję
  ustala `composer require` w kroku 05, tu tylko wymóg narzędzia).
- `tests/` odzwierciedla `src/` 1:1 (namespace `LightManager\Tests\...`
  równoległy do `LightManager\...`), klasa testowa `{Nazwa}Test`
  (`tests/Domain/Aggregate/DirectoryTest.php` dla
  `src/Domain/Aggregate/Directory.php`).
- `Domain`/`Application`: testy jednostkowe **obowiązkowe**, bez żadnego
  I/O (żaden test nie dotyka terminala/Imagick/systemu plików —
  potwierdza to, że granica warstw z kroku 01 rzeczywiście trzyma).
- `Infrastructure`/`Presentation`: testy automatyczne w miarę możliwości;
  zachowania wymagające realnego terminala pozostają do weryfikacji
  manualnej — zgodnie z tym, co już zapisano wprost w kryteriach
  ukończenia kroków 06–08.
- Preferuj `self::assertSame()` nad `self::assertEquals()` (asercje
  ścisłe, spójne z regułą `strict_comparison` z PHP-CS-Fixer).

```xml
<!-- phpunit.xml.dist (do fizycznego utworzenia w kroku 05) -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="LightManager">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

### Konwencje dla bloków taktycznych DDD (konkretny kod)

**Value Object** — `final`, `readonly`, walidacja w konstruktorze,
`equals()` do porównania po wartości:

```php
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
```

**Encja / agregat** — jawna tożsamość, `equals()` porównuje wyłącznie
identyfikator, nie całą zawartość:

```php
final class Directory
{
    public function __construct(
        private readonly DirectoryPath $path,
        /** @var list<Entry> */
        private array $entries,
        private ?Selection $selection,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->path->equals($other->path);
    }
}
```

**Interfejs repozytorium** — czasownik domenowy, zwraca obiekty `Domain`;
`get()` rzuca wyjątek domenowy przy braku, `find()` (jeśli potrzebny)
zwraca `null`:

```php
interface DirectoryRepositoryInterface
{
    public function get(DirectoryPath $path): Directory;
}
```

### Hierarchia wyjątków domenowych

Zgodnie z decyzją: **abstrakcyjna klasa bazowa**, nie interfejs
znacznikowy — spójne z podejściem „dziedziczenie” już wybranym w kroku 02
dla `AbstractSingleton`.

```php
namespace LightManager\Domain\Exception;

abstract class DomainException extends \RuntimeException
{
}
```

`\RuntimeException` jako rodzic SPL (nie `\LogicException`) — błędy
domenowe w tej aplikacji wynikają z danych zewnętrznych poznawanych
dopiero w trakcie działania (zła ścieżka, katalog bez uprawnień), nie z
błędu logiki programu wykrywalnego statycznie. Klasa jest `abstract`:
nie da się rzucić gołego `DomainException` — zawsze konkretny, nazwany
wyjątek, co wymusza czytelne `catch` i komunikaty.

Konwencja dodatkowa: wyjątki domenowe preferują **nazwane konstruktory
statyczne** budujące czytelny komunikat, zamiast składania stringa w
miejscu rzucenia:

```php
final class InvalidDirectoryPathException extends DomainException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('"%s" nie jest prawidłową ścieżką katalogu.', $path));
    }
}
```

### PHPDoc i komentarze

- PHPDoc tylko tam, gdzie typy PHP nie wystarczają — głównie kształt
  kolekcji: `@param list<Entry> $entries`, `@return list<Entry>`.
- Zakaz PHPDoc/komentarzy opisujących „co” robi kod, skoro nazwy już to
  mówią; komentarz tylko dla nieoczywistego „dlaczego” (ukryte założenie,
  obejście konkretnego problemu) — spójne z ogólną zasadą już przyjętą we
  współpracy nad tym projektem, tu tylko potwierdzone jako obowiązująca
  dla `src/`.

### Pakiety Composer wymagane przez ten krok (do dodania w kroku 05)

`require-dev`: `friendsofphp/php-cs-fixer`, `phpstan/phpstan`,
`phpunit/phpunit` — najnowsze stabilne wersje zgodne z PHP `^8.3` w
momencie realizacji kroku 05 (nie pinujemy tu konkretnych numerów, żeby
nie było rozjazdu między planem a realnym stanem Packagist w chwili
`composer require`).

## Dziennik realizacji

- **2026-08-07** — Ukończono. Ustalono: wymaganą wersję PHP `^8.3`
  (potwierdzoną jako zgodną z lokalnym środowiskiem — 8.3.11 zainstalowane,
  ale `imagick` obecnie nieaktywny, odnotowane jako uwaga dla kroku 05);
  konfigurację PHP-CS-Fixer (`@PSR12` + wąski zestaw reguł) i PHPStan
  (poziom `max` od startu, z polityką punktowych `@phpstan-ignore` zamiast
  obniżania poziomu); konwencje testów PHPUnit (`tests/` lustrzane wobec
  `src/`, obowiązkowe testy jednostkowe `Domain`/`Application`); konkretne
  wzorce kodu dla Value Objects, encji/agregatów i interfejsów
  repozytoriów; hierarchię wyjątków domenowych jako abstrakcyjną klasę
  bazową `DomainException extends \RuntimeException` z konwencją nazwanych
  konstruktorów statycznych. Wszystkie pliki konfiguracyjne pokazane jako
  gotowy do skopiowania kod — fizyczne utworzenie nadal należy do kroku 05,
  zgodnie z dopuszczoną przez ten krok alternatywą w „Kryteriach
  ukończenia”. Decyzje odnotowane w [00-decyzje.md](00-decyzje.md), D12.
