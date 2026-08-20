# 6. Wzorce kodu — przykłady

> Rozdział 6 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

Rozdział **wskazuje pliki, a nie kopiuje ich treści** — konwencja przykładu
z kroku 62 ([docs/KONWENCJE.md](../KONWENCJE.md)). Do kroku 62 stały tu cztery
bloki kodu i **dwa z nich były już nieprawdziwe**: udokumentowany
`InvalidDirectoryPathException` nie miał `DescribesProblem`, `problemKey()` ani
`problemParameters()`, a `DirectoryRepositoryInterface::get()` nie miał
parametru `bool $includeHidden`. Kopia rozjechała się po cichu — bo blok
w markdownie wygląda tak samo poprawnie w dniu, w którym przestaje być prawdą.

## Wzorce w aplikacji

Rzeczy, które w kodzie **są** — wskazuje się je tam, gdzie żyją, bo tam pilnuje
ich bramka jakości razem z resztą aplikacji.

| Wzorzec | Plik | Co z niego przepisać |
|---|---|---|
| Obiekt wartości | [`src/Module/Browser/Domain/ValueObject/Selection.php`](../../src/Module/Browser/Domain/ValueObject/Selection.php) | `final`, promowana własność `readonly`, samowalidacja w konstruktorze, `equals()` porównujący treść. |
| Wyjątek domenowy — przodek | [`src/Domain/Exception/DomainException.php`](../../src/Domain/Exception/DomainException.php) | Abstrakcyjny, dziedziczy po `RuntimeException`; komunikat techniczny po angielsku, dane dla użytkownika jako typowane pola. |
| Wyjątek domenowy — potomek | [`src/Module/Browser/Domain/Exception/InvalidDirectoryPathException.php`](../../src/Module/Browser/Domain/Exception/InvalidDirectoryPathException.php) | Prywatny konstruktor, nazwany konstruktor statyczny, `DescribesProblem` z kluczem napisu i parametrami. |
| Interfejs repozytorium | [`src/Module/Browser/Domain/Repository/DirectoryRepositoryInterface.php`](../../src/Module/Browser/Domain/Repository/DirectoryRepositoryInterface.php) | Kontrakt w `Domain`, typy domenowe na wejściu i wyjściu, `@throws` w opisie; technologia dopiero w implementacji z `Infrastructure`. |

Dwie rozdzielne hierarchie wyjątków — domenową i infrastrukturalną — wraz
z granicą między nimi opisuje [rozdział 5](05-nazewnictwo.md); skąd którą
warstwą sięga się po napis do komunikatu — [rozdział 7](07-napisy.md).

## Wzorce bez odbiorcy w aplikacji

Rzeczy, których w kodzie **nie ma i być nie powinno**, leżą
w [`examples/`](../../examples/) — przykład dydaktyczny wstawiony do `src/`
byłby kodem bez użytkownika (reguła 13).

| Wzorzec | Plik |
|---|---|
| Obiekt wartości pokazany bez kontekstu modułu | [`examples/PortNumber.php`](../../examples/PortNumber.php), wiersze 15–40 |
| Wyjątek domenowy pokazany bez kontekstu modułu | [`examples/InvalidPortNumberException.php`](../../examples/InvalidPortNumberException.php), wiersze 20–32 |

Katalog przechodzi **PHPStan `max` i PHP-CS-Fixer razem z `src/`** — przykład,
który przestał się analizować, jest usterką, a nie tekstem.
