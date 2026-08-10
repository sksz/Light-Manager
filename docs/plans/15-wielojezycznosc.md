# Krok 15 — Wielojęzyczność interfejsu

## Status

Ukończony

## Zależności

Krok 13 (motyw graficzny) — to on wprowadził pierwsze napisy po angielsku
obok polskich. Krok 14 (konfiguracja), jeśli wybór języka ma być trwały i
przestawiany z ekranu ustawień.

## Model i wysiłek

Sonnet / medium — zadanie jest rozległe (dotyka wszystkich warstw), ale
mechanicznie proste: wyprowadzenie napisów do jednego miejsca i podmiana
w miejscach użycia. Trudność leży w granicach warstw, nie w algorytmach.

## Cel

Wyprowadzić wszystkie napisy widoczne dla użytkownika do jednego miejsca i
pozwolić wybrać język interfejsu.

Powód powstania kroku: w kroku 13 etykiety stref weszły po angielsku
(`PATH`, `FILES`, `PREVIEW`), a komunikaty i podpowiedzi klawiszy zostały
po polsku. Ekran mówi dziś dwoma językami naraz, a napisy leżą rozsypane po
trzech warstwach.

## Stan zastany — gdzie dziś leżą napisy

| Miejsce | Przykłady | Warstwa |
|---|---|---|
| `HudFrameLayoutService` | `PATH`, `FILES`, `PREVIEW` | Infrastructure |
| `RenderCurrentFrameUseCase` | `(katalog jest pusty)`, podpowiedzi klawiszy, `• ukryte`, jednostki rozmiaru | Application |
| `PreviewSelectedEntryUseCase` | `Nie udało się odczytać obrazu.`, komunikaty o limitach | Application |
| Wyjątki domenowe | `"%s" nie jest prawidłową ścieżką katalogu.` | Domain |
| Wyjątki infrastruktury | komunikaty o terminalu i Imagicku | Infrastructure |
| `bin/light-manager` | komunikat preflightu o braku `ext-imagick` | punkt wejścia |

Osobny przypadek: **formatowanie liczb**. Rozmiary plików mają dziś polski
przecinek dziesiętny wpisany na sztywno (`str_replace('.', ',')` w
`RenderCurrentFrameUseCase`) — to również element języka, nie tylko napisy.

## Zakres

1. **Katalog napisów** — jedno miejsce z tekstami dla każdego języka
   (start: polski i angielski).
2. **Sposób sięgania po napis** z każdej warstwy, bez łamania reguły
   zależności: `Domain` nie może wołać Singletona ani znać katalogu.
3. **Wybór języka**: z konfiguracji (krok 14) i/lub ze zmiennej środowiskowej
   `LANG`/`LC_MESSAGES`, z językiem domyślnym jako ostatnią deską ratunku.
4. **Przeniesienie wszystkich napisów** z tabeli powyżej do katalogu.
5. **Ujednolicenie języka etykiet stref** z resztą interfejsu.
6. **Formatowanie liczb** zależne od języka (separator dziesiętny).

## Rozstrzygnięcia ze startu kroku

Wszystkie siedem to decyzje użytkownika, podjęte po przedstawieniu wariantów
(pełne uzasadnienia i odrzucone alternatywy:
[00-decyzje.md](00-decyzje.md), D32).

1. **Wyjątki niosą techniczny komunikat po angielsku**, a użytkownik widzi
   osobny, tłumaczony tekst dobierany w `Presentation` **po klasie wyjątku**.
   Odrzucone: wyjątek z kluczem i parametrami; tekst wstrzykiwany z góry.
2. **Konkrety do przetłumaczonego zdania biorą się z typowanych pól
   wyjątku** (`DirectoryNotReadableException::$path`), nie z rozbierania
   treści komunikatu. Odrzucone: zdania ogólne bez parametrów; zdanie ogólne
   z dopisanym technicznym opisem.
3. **Katalog napisów to tablice PHP** — `lang/pl.php`, `lang/en.php`.
   Odrzucone: JSON, klasa z metodami.
4. **Klucze płaskie, rozdzielone kropką** (`settings.key.theme`).
   Odrzucone: tablice zagnieżdżone.
5. **Mechanizm liczby mnogiej wchodzi od razu.** Odrzucone: odłożenie go do
   pierwszego napisu, który go naprawdę potrzebuje.
6. **Wybór języka: ustawienie `auto` (domyślne) plus `LANG`.** `auto` czyta
   `LC_ALL`/`LC_MESSAGES`/`LANG`; wybór zapisany w konfiguracji jest od nich
   mocniejszy. Odrzucone: wyłącznie konfiguracja; wyłącznie środowisko.
7. **Językiem domyślnym jest angielski.** Odrzucone: polski.
8. **`intl` pozostaje opcjonalne** — `NumberFormatter`, gdy jest dostępny,
   w przeciwnym razie separator z katalogu. Zasada z D20 bez zmian.
   Odrzucone: separator wyłącznie z katalogu; `intl` jako twardy wymóg.

## Specyfikacja zrealizowana

### Katalog napisów

`lang/pl.php` i `lang/en.php` zwracają płaską tablicę `klucz => napis`.
Parametry są **nazwane** (`{path}`, `{count}`), nie pozycyjne — tłumaczenie
bywa przestawione względem oryginału, a nazwa to przeżywa. Wpis zapisany jako
lista niesie formy mnogie.

### Mechanizm

| Klasa | Rola |
|---|---|
| `Application/Dto/Language` | `auto` \| `pl` \| `en`; `FALLBACK` = angielski |
| `Application/Port/TranslatorPort` | `translate()`, `plural()`, `number()`, `active()` |
| `Infrastructure/I18n/Catalog` | wczytanie i sprawdzenie kształtu jednego pliku napisów |
| `Infrastructure/I18n/PluralRule` | `Germanic` (2 formy) i `Slavic` (3 formy) |
| `Infrastructure/I18n/TranslatorService` | Singleton implementujący port; wybór języka, podstawienia, liczby |
| `Presentation/Cli/ProblemPresenter` | wyjątek → zdanie w języku interfejsu |

Żadna ścieżka nie kończy się wyjątkiem: brak klucza w wybranym języku sięga do
angielskiego, brak klucza w ogóle daje na ekranie sam klucz, brak pliku języka
cofa się do angielskiego.

### Wybór języka na żywo

`TranslatorService::active()` pyta konfigurację przy **każdym** wywołaniu — tak
samo jak `ThemeService` o motyw (D31) — więc zmiana języka na ekranie ustawień
jest widoczna w następnej klatce. Odczyt zmiennych środowiskowych jest
zapamiętywany, bo środowisko procesu w trakcie działania się nie zmienia.

Kolejność wczytywania w `SettingsService::load()` musiała się przy tym zmienić:
ustawienia trafiają do pola **przed** złożeniem komunikatu o wadliwym pliku, bo
napis idzie przez tłumacza, a ten pyta konfigurację o język. Odwrotna kolejność
wpuszczałaby go w środek trwającego odczytu.

### Nowe ustawienie

`SettingKey::Language` (`language`), pierwsza pozycja zakładki „Wygląd”,
wartości `auto` / `Polski` / `English`. `Settings::display()` zniknęło —
składanie wartości do pokazania przeszło do `RenderSettingsFrameUseCase`, bo
„tak”, „nie” i nazwa języka to napisy, a obiekt konfiguracji ma nieść wartości,
nie ich brzmienie.

### Formy mnogie w praktyce

Jedyny dzisiejszy napis, który naprawdę odmienia się przez liczbę, to komunikat
o kluczach odrzuconych przy wczytywaniu konfiguracji („wartość spoza zakresu,
użyto domyślnej” wobec „wartości spoza zakresu, użyto domyślnych”). Mechanizm
wchodzi więc z realnym użyciem i testem, a nie jako kod bez wywołania.

### Testy

- `tests/Support/StubTranslator` — oddaje klucz z parametrami zamiast napisu.
  Testy `Application` sprawdzają dzięki temu, **o który** napis przypadek
  użycia prosi i z jakimi danymi, a nie jak on brzmi; zmiana treści napisu nie
  psuje wtedy dwudziestu asercji.
- `tests/Support/PinsLanguage` — przypina język i katalog domowy tam, gdzie
  test dotyka realnych napisów (`HudFrameLayoutServiceTest`,
  `SettingsServiceTest`, `TranslatorServiceTest`). Bez tego wynik zależałby od
  `LANG` osoby uruchamiającej testy.
- `TranslatorServiceTest` porównuje zestawy kluczy obu katalogów i liczbę form
  mnogich — języki nie mają prawa się rozjechać.
- `PluralRuleTest` — trzynaście liczebników po polsku, cztery po angielsku.

## Poza zakresem

- Języki pisane od prawej do lewej (zmieniłyby układ klatki, nie tylko
  napisy).
- Tłumaczenie dokumentacji projektu i planów.
- Lokalizacja skrótów klawiszowych (klawisz `q` dla „quit” w innym języku).

## Kryteria ukończenia

- Żaden napis widoczny dla użytkownika nie jest wpisany na sztywno w
  `Application`, `Infrastructure` ani `Presentation`.
- Cały ekran mówi jednym językiem — łącznie z etykietami stref.
- Przełączenie języka zmienia interfejs bez zmiany kodu.
- Brak katalogu dla wybranego języka nie wywraca aplikacji: zostaje język
  domyślny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` opisuje, skąd którą warstwą sięga się po napis.

## Dziennik realizacji

**2026-08-09** — krok zrealizowany w całości.

**Co powstało:** `lang/pl.php`, `lang/en.php`, `Application/Dto/Language`,
`Application/Port/TranslatorPort`, `Infrastructure/I18n/{Catalog, PluralRule,
TranslatorService}`, `Presentation/Cli/ProblemPresenter`,
`Infrastructure/Terminal/TerminalProblem`, `Infrastructure/Config/ConfigFailure`,
`tests/Support/{StubTranslator, PinsLanguage}`, `tests/Infrastructure/I18n/*`.

**Co przeniesiono do katalogu:** etykiety stref (`HudFrameLayoutService`),
wszystkie napisy trzech przypadków użycia rysujących klatki, podpisy pasa
podglądu, ostrzeżenie o palecie, opisy z `FileInspectorService`, komunikaty
konfiguracji, komunikat startowy o nieczytelnym katalogu i preflight o braku
`ext-imagick`. Separator dziesiętny rozmiaru pliku przestał być `str_replace`.

**Odstępstwa i rozszerzenia zakresu:**

- Plan wymieniał sześć rzeczy do rozstrzygnięcia; doszła siódma — skąd
  przetłumaczone zdanie bierze parametry, skoro komunikaty wyjątków zostają
  techniczne. Bez niej pasek stanu straciłby nazwę katalogu, którą dziś pokazuje.
- `ConfigException` i `TerminalException` dostały typowane pole opisujące
  rodzaj awarii (`ConfigFailure`, `TerminalProblem`). Każda z nich miała po
  kilka nazwanych konstruktorów, a rozpoznawanie „po klasie wyjątku” nie miało
  ich jak rozróżnić. Alternatywą było rozbicie każdej na kilka klas.
- `Settings::display()` usunięte, `SettingKey::label()` i `SettingsTab::label()`
  zamienione na `labelKey()`. To zmiana w kodzie kroku 14, policzona tutaj.
- `ProblemPresenter` (warstwa `Presentation`) zna obie hierarchie wyjątków,
  także infrastrukturalną — ta sama swoboda, z której korzysta `Bootstrap`.
- **`bin/terminal-probe` zostało po polsku.** To narzędzie diagnostyczne dla
  osoby rozwijającej projekt, nie interfejs aplikacji; plan wymieniał w tabeli
  napisów wyłącznie `bin/light-manager`.
- Komunikat o brakujących zależnościach Composera w `bin/light-manager` jest
  jedynym napisem, który nie przechodzi przez katalog — pada, zanim istnieje
  autoloader, którym dałoby się tłumacza wczytać. Przetłumaczony na angielski.

**Zweryfikowano:** PHPUnit 468 testów zielonych (było 464), PHPStan `max` bez
błędów (`lang/` dopisane do analizowanych ścieżek), PHP-CS-Fixer bez uwag.
Uruchomienie `./bin/light-manager` bez terminala wypisuje komunikat o braku
sesji interaktywnej po polsku przy `LC_ALL=pl` i po angielsku przy `LC_ALL=en`.
