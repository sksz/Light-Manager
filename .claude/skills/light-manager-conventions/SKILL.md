---
name: light-manager-conventions
description: Use whenever writing, reviewing, or reasoning about PHP code in this repository's src/ or tests/ — enforces DDD layer boundaries (Domain/Application/Infrastructure/Presentation), the per-service Singleton pattern, and PHP coding standards (strict_types, PSR-12, PHPStan max, DomainException hierarchy). Not needed for pure planning/docs-only discussion.
---

# Light Manager — konwencje kodu

Pełny dokument źródłowy: `docs/architecture.md` (i historia decyzji w
`docs/plans/00-decyzje.md`). Ten Skill to operacyjny skrót — jeśli
brakuje tu szczegółu, sprawdź `docs/architecture.md` zamiast zgadywać.

## Twarde reguły (nie łam bez jawnej zgody użytkownika)

1. **Warstwy**: `src/Domain`, `src/Application`, `src/Infrastructure`,
   `src/Presentation`. Zależności tylko „do środka”:
   `Presentation → Application → Domain`,
   `Infrastructure → Domain/Application` (przez implementację interfejsów).
   **Interfejs graficzny stoi po dwóch stronach tej granicy** (krok 18, D36):
   komponenty i kontenery w `Presentation/Ui`, a klatka, płaszczyzny i
   prymitywy w `Application/Ui`. Zasada: *komponent wie, jak wyglądać;
   prymityw jest tym, co z tej wiedzy zostaje po przekroczeniu portu*.
   Renderer nigdy nie widzi komponentu.
   **Kontrakt komendy leży w `Application/Command`** (krok 19, D39) i nie zna
   ani jednego typu z `Presentation`: ekran do otwarcia wskazuje
   **identyfikatorem** (`ScreenInterface::id()`), a nie obiektem.
   **Kontrakt modułu stoi po dwóch stronach tej samej granicy** (krok 20, D38,
   P2): dane i rejestr w `Application/Module`, zdolności wymieniające typy
   z `Presentation/Ui` — w `Presentation/Ui/Module`. Stąd `ModuleShortcut` jest
   daną, a nie `KeyBinding`iem: rejestr żyje w `Application`.
2. **`Domain` nigdy nie odwołuje się do Singletonów ani do żadnej biblioteki
   zewnętrznej** (Imagick, `pcntl`, terminal). Musi dać się testować bez
   I/O.
3. **Każda usługa poza `Domain` to osobny Singleton** — dziedziczy po
   `Infrastructure/Support/AbstractSingleton` (`getInstance()`, konstruktor
   `protected`, nie `private`). Żaden centralny kontener/rejestr usług.
4. **`Application` zna tylko interfejsy** (`Domain/Repository`,
   `Application/Port`), nigdy konkretnych klas `Infrastructure`.
5. `declare(strict_types=1)` w każdym pliku PHP. PHP `^8.3`.
6. **Value Objects**: `final`, `readonly`, samowalidacja w konstruktorze
   rzucająca wyjątek domenowy, metoda `equals()`. Encje/agregaty:
   `equals()` porównuje tylko tożsamość, mutowalne w miejscu jest OK.
7. **Napisy**: żaden tekst widoczny dla użytkownika nie jest wpisany w kod.
   Katalogi `lang/pl.php` i `lang/en.php` (płaskie klucze z kropką, parametry
   `{nazwa}`, listy = formy mnogie; angielski jest zapasowy).
   `Domain` **nie sięga po napisy w ogóle**; `Application` — wyłącznie przez
   wstrzyknięty `Application\Port\TranslatorPort`; `Infrastructure` i
   `Presentation` — przez `TranslatorService::getInstance()` albo wstrzyknięty
   port. `Application/Dto` trzyma **klucze** (`SettingKey::labelKey()`), nie
   napisy. Liczby formatuje `TranslatorPort::number()`.
   **Jeden jawny wyjątek** (D33): treść klatek budowanych przez
   `Infrastructure\Diagnostics\ScenarioFactory` i podpis konfiguracji
   `BenchmarkOptions::signature()` są nietłumaczone — to nie interfejs, tylko
   obciążenie pomiaru i identyfikator wzorca, a długość napisu w znakach wchodzi
   do wyniku. Nie „poprawiaj” ich przez katalog; napisy samego narzędzia
   (klucze `bench.*`) idą przez katalog normalnie.
8. **Wyjątki**: dwie rozdzielne hierarchie, obie abstrakcyjne i obie
   `extends \RuntimeException` —
   `Domain\Exception\DomainException` dla warstwy domenowej i
   `Infrastructure\Support\InfrastructureException` dla warstwy
   infrastruktury (np. `Terminal\TerminalException`). Nie dziedziczą po
   sobie nawzajem. Preferuj nazwane konstruktory statyczne
   (`InvalidDirectoryPathException::forPath($path)`,
   `TerminalException::forMissingPcntl()`), nie `new X("...")` ze
   sklejanym stringiem w miejscu użycia.
   **Komunikat wyjątku jest techniczny i po angielsku** — napis dla
   użytkownika dobiera `Presentation\Cli\ProblemPresenter` po klasie
   wyjątku, a dane bierze z jego publicznych, typowanych pól.
   **Wyjątek infrastruktury nie przekracza granicy portu** — jeśli
   `Application` ma się o awarii dowiedzieć, port oddaje ją opisem
   (`?string`, DTO wyniku), a nie rzuca (krok 14, `SettingsPort`).
9. Testy w `tests/`, lustrzane wobec `src/`. `Domain`/`Application`:
   testy jednostkowe obowiązkowe, zero I/O. Reset Singletonów w testach
   wyłącznie przez `tests/Support/ResetsSingletons` (Reflection) — nigdy
   przez publiczną metodę `resetInstance()` w klasie produkcyjnej.
   Testy `Application` dostają `tests/Support/StubTranslator` (oddaje klucz,
   nie napis); testy zależne od realnych napisów przypinają język przez
   `tests/Support/PinsLanguage`.
10. **Nowe okno „nad” ekranem to `OverlayInterface`** (`Presentation/Ui`), nie
    nowe pole w stanie pętli. Okno samo wyznacza swój prostokąt (`bounds()`)
    i **zużywa albo przepuszcza** klawisz; przepuszczony trafia wyłącznie do
    klawiszy globalnych, nigdy do ekranu pod spodem (krok 19). Płaszczyznę
    takiego okna składa się z `opaque: true` — **warstwa ma zakrywać to, co pod
    nią**, a `Panel` rysuje samą obwódkę, bez tła.
11. **Nowy element interfejsu to nowy komponent w `Presentation/Ui/Component`**,
    a nie nowa metoda w rendererze. Komponent oddaje prymitywy z ról motywu i
    prostokątów w siatce znakowej — pikseli nie zna. Słownik prymitywów jest
    **zamknięty**; jego rozszerzenie to obowiązek dla obu rendererów naraz i
    wymaga zgody użytkownika.
12. **Żaden komponent nie powstaje bez prawdziwego użytkownika w aplikacji**
    (krok 18, P5) — komponent pokryty samym testem to API zaprojektowane na
    domysł. Ta sama zasada odsunęła podpowiadanie ścieżek z kroku 19 do 20:
    rodzaj `SuggestionSource::OnDemand` jest zadeklarowany, ale pierwszą
    implementację wnosi komenda modułu.
13. PHPStan `level: max`. Zamiast obniżać poziom — punktowy
    `@phpstan-ignore-line` z komentarzem uzasadniającym.
14. **Nowa funkcja to moduł w `src/Module/`, nie zmiana w rdzeniu** (krok 20).
    Moduł powtarza wewnątrz podział na warstwy (katalog warstwy pustej nie
    powstaje), a reguła zależności zyskuje jedną strzałkę:
    `Module → Presentation → Application → Domain`. Dwa zakazy ponad to: moduł
    **nigdy** nie sięga do `Infrastructure` rdzenia inaczej niż przez port
    i **nigdy** nie sięga do innego modułu. Klasa modułu to **zwykły obiekt**
    tworzony `new`-em w `Bootstrap` — nie Singleton; Singletonami zostają usługi
    w jego własnej warstwie `Infrastructure`. Napisy modułu leżą w jego katalogu
    `lang/` i wchodzą do katalogu **wyłącznie** pod przedrostkiem `module.<id>.`.
    Dopisanie modułu ma kosztować **jedną zmianę w rdzeniu**: pozycję na liście
    w `Bootstrap`. Jeśli kosztuje więcej — to jest błąd do naprawienia, a nie
    powód, żeby dotknąć rdzenia.

## Nazewnictwo (skrót)

`...RepositoryInterface` (Domain) → `...Repository` z technologią
(Infrastructure, np. `FilesystemDirectoryRepository`). `...Port`
(Application) → `...Service` (Infrastructure, Singleton). `...UseCase`
(Application). `...Exception` (Domain oraz Infrastructure). DTO opisujące
zdarzenia wejściowe — `Application/Dto` (np. `KeyPress`, enum `Key`).
Wartości opisujące **obraz** (`Frame`, `Plane`, `Rect`, `Size`, `Role`,
`Corner` i prymitywy `TextRun`, `RoundRect`, `CornerBrackets`, `Bar`, `Bitmap`,
`Scrollbar`) leżą w `Application/Ui` — przechodzą przez `FrameRendererPort`,
więc muszą być widoczne dla `Infrastructure`. Wartości opisujące
**konfigurację** (`Settings`, `SettingKey`, `SettingsTab`, `SettingsTabKind`,
`SettingsCursor`, `Language`) leżą w `Application/Dto`. Kontrakt modułu —
`ModuleInterface`, `ModuleShortcut`, `ModuleContext`, `ModuleSetting`,
`ModuleRegistry` i spółka — w `Application/Module`; zdolności `ProvidesScreen`,
`ProvidesHelpTab` i `ReadsContext` w `Presentation/Ui/Module`. Klasa modułu ma
sufiks `Module` i leży w warstwie `Presentation` swojego katalogu; jego komenda,
skoro dostaje stan pętli, leży w `Presentation/Command` modułu. Komendy — kontrakt, argumenty, parser
wiersza, rejestr i historia — w `Application/Command`; komendy rdzenia
(`ScreenCommand`, `SettingCommand`, `QuitCommand`) w `Presentation/Cli/Command`,
bo dostają stan pętli i stos ekranów. `Domain/ValueObject` **nie zawiera już
niczego o rysowaniu** (krok 18, D36). Katalogi napisów i wybór języka — `Infrastructure/I18n`
(`TranslatorService`, `Catalog`, `PluralRule`), pliki napisów w `lang/`.

## Gdy coś tu nie pasuje do zadania

Jeśli zadanie wymaga odstępstwa od powyższego (np. nowa warstwa, inny
wzorzec DI) — zapytaj użytkownika zamiast cicho odstępować; to są
świadome decyzje architektoniczne z `docs/plans/00-decyzje.md`, nie
przypadkowe konwencje.
