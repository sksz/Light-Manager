# 7. Napisy i języki interfejsu

> Rozdział 7 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

Ustalone w kroku 15 ([docs/plans/00-decyzje.md](../plans/00-decyzje.md), D32).
**Żaden napis widoczny dla użytkownika nie jest wpisany na sztywno w kodzie.**

## Katalog napisów

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

## Skąd którą warstwą sięga się po napis

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

## Wybór języka

Ustawienie `language` (`auto` | `pl` | `en`), domyślnie `auto`. `auto` czyta
`LC_ALL`, `LC_MESSAGES` i `LANG` — pierwszą zmienną z rozpoznawalnym kodem;
nierozpoznany kod schodzi do angielskiego. Wybór zapisany w konfiguracji jest
mocniejszy od środowiska.

`TranslatorService` pyta o ustawienie przy **każdym** wywołaniu — tak jak
`ThemeService` o motyw (D31) — więc zmiana języka na ekranie ustawień jest
widoczna w następnej klatce, bez restartu.

## Liczby

Separator dziesiętny należy do języka, więc formatowanie liczb idzie przez ten
sam port (`TranslatorPort::number()`). Gdy dostępne jest rozszerzenie `intl`,
liczbę składa `NumberFormatter`; w przeciwnym razie wchodzi ścieżka awaryjna
z separatorem z katalogu (`format.decimal`) — ta sama zasada, którą D20 przyjął
dla `Collator`. Grupowanie tysięcy jest wyłączone, żeby obie ścieżki dawały
identyczny napis.
