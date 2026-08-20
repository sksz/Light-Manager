# Przykłady kodu

Pliki wskazywane przez dokumentację zamiast kopiowane do niej — konwencja
opisana w [docs/KONWENCJE.md](../docs/KONWENCJE.md), „Konwencja przykładu”.

Katalog trzyma **wyłącznie przykłady dydaktyczne**: rzeczy, których w aplikacji
nie ma i być nie powinno. Wzorzec, który w aplikacji **jest**, dokumentacja
wskazuje w `src/` — bo tam broni go bramka jakości razem z resztą kodu.

Przykłady przechodzą **PHPStan `max` i PHP-CS-Fixer razem z `src/`**
(`make qa`). Przykład, który przestał się analizować, jest usterką, a nie
tekstem — i to jest cały powód, dla którego są plikami, a nie blokami
w markdownie.

| Plik | Co pokazuje |
|---|---|
| [`PortNumber.php`](PortNumber.php) | Obiekt wartości: `final`, `readonly`, samowalidacja w konstruktorze, `equals()`. |
| [`InvalidPortNumberException.php`](InvalidPortNumberException.php) | Wyjątek domenowy: prywatny konstruktor, nazwany konstruktor statyczny, komunikat techniczny po angielsku, dane jako pole. |
| [`modul-przykladowy/`](modul-przykladowy/) | **Kompletny mikromoduł**: kontrakt modułu i trzy zdolności, komenda z argumentem, kwerenda z pokoleniem, pozycja ustawień i napisy w dwóch językach. |
| [`zadanie-kwerenda/`](zadanie-kwerenda/) | **Zadanie ćwiczebne onboardingu**: moduł z jedną kwerendą — plik startowy z luką w `start/` i rozwiązanie w `rozwiazanie/`. |

## Zadanie ćwiczebne

[`zadanie-kwerenda/`](zadanie-kwerenda/) różni się od pozostałych przykładów
przeznaczeniem: nie jest wzorcem do przeczytania, tylko **ćwiczeniem do
przejścia** — czwartym przystankiem
[onboardingu](../docs/pl/onboarding/04-pierwsza-zmiana.md). Stąd jego kształt:
dwa katalogi zamiast jednego, jedna luka w `start/` i ta sama rzecz gotowa
w `rozwiazanie/`.

Obie kopie przechodzą PHPStan `max` razem z resztą — także ta z luką, bo luka
jest pustą odpowiedzią kwerendy, a nie kodem, który się nie analizuje.

## Mikromoduł

[`modul-przykladowy/`](modul-przykladowy/) jest wzorcem dla
[przewodnika dewelopera](../docs/pl/przewodnik/03-jak-dodac.md) i **nie jest
wpisany do `Bootstrapu`** — moduł przykładowy w spisie byłby modułem bez
odbiorcy (reguła 13).

| Plik | Przewodnik, który go wskazuje |
|---|---|
| [`PrzykladModule.php`](modul-przykladowy/PrzykladModule.php) | Nowy moduł |
| [`Command/PowitanieCommand.php`](modul-przykladowy/Command/PowitanieCommand.php) | Nowa komenda |
| [`Query/StanQuery.php`](modul-przykladowy/Query/StanQuery.php) | Nowa kwerenda |
| [`PrzykladSettings.php`](modul-przykladowy/PrzykladSettings.php) | Nowa pozycja ustawień |
| [`lang/pl.php`](modul-przykladowy/lang/pl.php), [`lang/en.php`](modul-przykladowy/lang/en.php) | Nowe napisy i drugi język |

Ekran, zakładkę pomocy i takt pokazuje moduł **prawdziwy** —
`src/Module/AddressBook/`. Wzorca, który w aplikacji jest, dokumentacja tu nie
kopiuje.
