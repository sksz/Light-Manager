# Krok 67 — Moduł `runner`: zadania projektu uruchamiane stamtąd, gdzie się stoi

> **Skąd ten krok.** Powstał 2026-08-16 jako drugi krok **Fazy XXII**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem** —
> unieruchamia fakty i wypisuje pytania.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**; pytania
czekają w sekcji „Pytania do rozstrzygnięcia".

## Cel

Moduł wykrywa **zadania projektu**, w którym stoi użytkownik — cele `Makefile`,
skrypty `composer.json` i `package.json` — uruchamia wybrane pracą tłową
i pokazuje wyjście na żywo. Kod wyjścia ogłasza zdarzeniem.

Miarą powodzenia jest zdanie: **`make qa` tego repozytorium daje się uruchomić
z poziomu aplikacji, a jego wyjście widać w trakcie, nie po zakończeniu.**

## Dlaczego to jest tanie, a nie oczywiste

Cała maszyneria stoi: praca tłowa niesie strumienie od kroku 26, kilka prac
naraz od 51, `TextView` przewija treść od 29, a zdarzenia z kroku 46 mają już
efekt dźwiękowy — czyli **zakończone zadanie może zabrzmieć bez ani jednej nowej
linii w rdzeniu**. Nieoczywista jest granica: nie każde zadanie da się uruchomić
bez terminala sterującego, a `make run` tej właśnie aplikacji jest tego
najbliższym przykładem.

## Zarys zakresu

- **Wykrycie zadań** — trzy źródła w katalogu: cele `Makefile` (z komentarzy
  opisujących, wzorem `make` bez argumentów), `scripts` z `composer.json`,
  `scripts` z `package.json`.
- **Spis i wybór** — lewy panel z zadaniami, filtr z kroku 30; `MenuOverlay`
  jako druga droga.
- **Przebieg** — praca tłowa, wyjście w `TextView` na żywo, `Esc` przerywa
  pracę, a nie samo patrzenie.
- **Wynik** — kod wyjścia jako zdarzenie modułu (wzór z kroku 46) i zdanie
  w stopce.
- **Historia przebiegów** — ostatnie uruchomienia z czasem trwania i kodem
  wyjścia.
- **Kwerendy** — `runner.tasks` (co da się uruchomić), `runner.runs` (co
  właśnie biegnie i co się skończyło).

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`.

## Pytania do rozstrzygnięcia

1. **Granica zadań interaktywnych.** Moduł uruchamia to, co pisze na potok —
   ale co robi z celem, który czeka na wejście albo chce terminala (`make run`,
   `composer require` bez `--no-interaction`)? Odmowa z powodem, próba
   z limitem czasu, czy pozycja w ustawieniach?
2. **Środowisko przebiegu** — zmienne z procesu aplikacji, czy własne
   z ustawień? Pytanie ma znaczenie dla `nvm`, `PATH` i wszystkiego, co
   deweloper ma w powłoce, a czego proces potomny aplikacji nie dziedziczy.
3. **Ile zadań naraz** — jedno, czy tyle, ile pozwala granica prac tłowych
   z kroku 51 (pozycja ustawień)?
4. **Skąd katalog** — `browser.cwd`, jak w kroku 66, czy zapamiętany korzeń
   projektu?
5. **Czy wynik zadania wchodzi do zdarzeń aplikacji** — słownik zdarzeń jest
   zamknięty konstrukcyjnie (krok 46), więc dołożenie nazw jest decyzją, nie
   dopiskiem.

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Narzędzia | `make`, `composer` 2.7.1, `npm` (Node 21.6.0) w `PATH`. |
| Ten projekt | 29 celów `make` (D97) — pierwszy odbiorca modułu jest sam projekt. |
| Praca tłowa | Kilka prac naraz od kroku 51; strumień błędów osobno (reguła 15f). |

## Zależności

- **Kroki 20 i 21** — kontrakt modułu i katalog bieżący.
- **Kroki 26 i 51** — praca tłowa równoległa.
- **Kroki 24, 29, 30, 32** — panele, widok tekstu, filtr, menu.
- **Krok 46** — zdarzenia i efekty dźwiękowe jako gotowy odbiorca wyniku.
- **Krok 53** — kwerendy.

## Model i wysiłek (wstępnie)

**Opus / high.** Nowej drogi technicznej nie wnosi — proces potomny jest w
projekcie od kroku 26. Ciężar leży w **granicy zadań interaktywnych** i w tym,
żeby przerwanie pracy nie zostawiało procesu potomnego bez właściciela.

## Poza zarysem

- Edycja `Makefile` i plików z zadaniami.
- Zadania zdalne (na hoście SSH) — to jest rozmowa z cudzą maszyną, czyli
  własność modułu `ssh`, nie tego.
- Harmonogram i uruchamianie cykliczne.
- Rozpoznawanie zadań z narzędzi, których w środowisku nie ma (`just`, `task`,
  `gradle`) — wchodzą, gdy będzie odbiorca.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
