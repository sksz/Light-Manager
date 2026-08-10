# Krok 05 — Szkielet projektu i wymagania

## Status

Ukończony

## Zależności

Kroki 01–04 (całość ustaleń architektury i stylu — szkielet ma je
bezpośrednio zastosować: strukturę katalogów DDD, wzorzec Singleton,
standardy stylu/narzędzi).

## Model i wysiłek

Sonnet / medium — praca głównie strukturalna i niewielkie ryzyko
techniczne, ale wysiłek podniesiony do przyjętej podłogi (zobacz
[00-decyzje.md](00-decyzje.md), D5) — żaden krok, nawet pozornie prosty,
nie schodzi poniżej `medium`.

## Cel

Przygotować minimalny, ale poprawny szkielet projektu PHP, na którym będą
nadbudowywane kolejne kroki, oraz zweryfikować że środowisko ma wszystko,
czego potrzeba (rozszerzenie Imagick z obsługą kodera `sixel`).

## Zakres

- Struktura katalogów: `src/` (kod aplikacji, PSR-4), `bin/` (skrypt
  wejściowy uruchamiany z linii poleceń), `docs/plans/` (już istnieje).
- `composer.json`: nazwa pakietu, autoload PSR-4, wymagana wersja PHP,
  wymagane rozszerzenie `ext-imagick` (deklaracja w `require`).
- Skrypt startowy w `bin/` z shebangiem, ustawiony jako wykonywalny, na
  razie wypisujący komunikat startowy (placeholder pod pętlę główną z
  kroku 09).
- Weryfikacja przy starcie: czy `ext-imagick` jest załadowane oraz czy lista
  obsługiwanych formatów Imagick zawiera `SIXEL`
  (`Imagick::queryFormats('SIXEL')`) — jeśli nie, aplikacja powinna to
  zakomunikować (przyda się też krokowi 07).
- Minimalny `README.md` z instrukcją uruchomienia (`php bin/...`) i
  wymaganiami.

## Poza zakresem tego kroku

Jakakolwiek logika renderowania, pętli, wejścia — to kolejne kroki.

## Ryzyka

- Środowisko może mieć Imagick bez kodera Sixel skompilowanego w bibliotece
  ImageMagick (koder Sixel bywa opcjonalny przy kompilacji) — do
  zweryfikowania właśnie w tym kroku, wynik wpływa na krok 07/08.

## Kryteria ukończenia

- `composer install` przechodzi bez błędów.
- Uruchomienie skryptu z `bin/` wypisuje komunikat startowy i wynik
  sprawdzenia dostępności Imagick+Sixel.
- Struktura katalogów i autoload gotowe pod kolejne kroki.

## Dziennik realizacji

- **2026-08-07** — Ukończono. Utworzono fizycznie strukturę katalogów `src/`
  (Domain/Application/Infrastructure/Presentation ze wszystkimi podkatalogami
  z kroku 01, wraz z `Infrastructure/Support` dopisanym w kroku 02), `bin/`
  oraz `tests/`. Powstały: `composer.json` (pakiet `morfeusz/light-manager`,
  `php: ^8.3`, `ext-imagick: *`, PSR-4 `LightManager\` → `src/` i
  `LightManager\Tests\` → `tests/`, skróty `composer test|stan|cs|cs:check`),
  wykonywalny `bin/light-manager`, `README.md` oraz trzy konfiguracje
  narzędzi przeniesione bez zmian z kroku 03 (`.php-cs-fixer.dist.php`,
  `phpstan.neon.dist`, `phpunit.xml.dist`).

  **Wynik weryfikacji ryzyka tego kroku (kluczowy dla kroków 07–08):**
  `ext-imagick` **jest** załadowane, a koder Sixel **jest** dostępny —
  `Imagick::queryFormats('SIXEL')` zwraca `["SIXEL"]` przy ImageMagick
  6.9.12-98 Q16. Unieważnia to ostrzeżenie z kroku 03 („imagick nie jest
  obecnie załadowane”) — stan środowiska zmienił się między krokami.

  **Stan narzędzi:** `composer validate` — OK; `./bin/light-manager` —
  wypisuje komunikat startowy i obie informacje diagnostyczne, kod wyjścia 0;
  PHP-CS-Fixer — OK (0 plików); PHPUnit — OK („No tests executed!”, kod 0);
  PHPStan — kod wyjścia 1 z komunikatem „No files found to analyse.”, bo
  `src/` i `tests/` są celowo puste (zobacz odstępstwo niżej). Konfiguracja
  PHPStan została zweryfikowana jako poprawna: po tymczasowym dodaniu
  jednego pliku w `src/Domain/ValueObject` analiza przeszła z wynikiem
  „No errors” na poziomie `max` (plik następnie usunięto). Błąd zniknie
  samoczynnie z pierwszym plikiem PHP w kroku 06 — nie wymaga zmiany
  konfiguracji.

  **Odstępstwa od planu (decyzje użytkownika, zobacz
  [00-decyzje.md](00-decyzje.md), D14):** `src/` zawiera wyłącznie puste
  katalogi — pliki bazowe, których fizyczne utworzenie kroki 02 i 03 odesłały
  „do kroku 05” (`Infrastructure/Support/AbstractSingleton`,
  `Domain/Exception/DomainException`, `tests/Support/ResetsSingletons`),
  powstaną dopiero razem z pierwszą realną usługą w kroku 06. W konsekwencji
  weryfikacja Imagick/Sixel żyje w samym `bin/light-manager`, a nie w klasie
  warstwy `Presentation`. `tests/` utworzono jako pojedynczy katalog, bez
  odwzorowania drzewa `src/` — lustrzana struktura powstanie wraz z
  pierwszymi testami.

  **Zachowanie skryptu startowego przy brakach** (drobne rozstrzygnięcie
  wynikające z zakresu, nie osobna decyzja architektoniczna): brak
  `ext-imagick` kończy działanie kodem 1 (rozszerzenie jest twardym wymogiem
  w `require` `composer.json`), natomiast brak kodera SIXEL to wyłącznie
  komunikat — aplikacja działa dalej, bo to wejście dla trybu fallback z
  kroku 07.

  **Poza zasięgiem narzędzi:** `bin/light-manager` nie jest objęty ani
  finderem PHP-CS-Fixer, ani ścieżkami PHPStan (obie konfiguracje wskazują
  `src` i `tests`, zgodnie z kodem z kroku 03) — plik napisano ręcznie w
  zgodzie z PSR-12 i `strict_types`. Ewentualne dopisanie `bin` do obu
  konfiguracji to otwarta kwestia do rozstrzygnięcia przez użytkownika.

  **Uwaga środowiskowa** (obowiązująca procedura dla kolejnych kroków, D15):
  Composer kończy się naruszeniem ochrony pamięci (SIGSEGV, kod 139) przy
  równoległym pobieraniu wielu paczek, gdy załadowane są rozszerzenia
  `imagick` i `openswoole`. Zależności deweloperskie
  (`friendsofphp/php-cs-fixer` 3.95, `phpstan/phpstan` 2.2,
  `phpunit/phpunit` 12.5) zainstalowano, uruchamiając Composera z
  `PHP_INI_SCAN_DIR` wskazującym kopię `conf.d` bez tych dwóch rozszerzeń,
  z `--ignore-platform-req=ext-imagick`. Obejście opisane w `README.md`.
