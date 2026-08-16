# Krok 68 — Moduł `deps`: zależności projektu i to, co w nich przeterminowane

> **Skąd ten krok.** Powstał 2026-08-16 jako trzeci i ostatni krok **Fazy XXII**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**; pytania
czekają w sekcji „Pytania do rozstrzygnięcia".

## Cel

Moduł pokazuje **zależności projektu, w którym stoi użytkownik**: wersję
zainstalowaną obok najnowszej dostępnej oraz zgłoszone podatności. Jeden panel,
jedna tabela, dwie rozmowy z narzędziem.

Miarą powodzenia jest zdanie: **paczka przeterminowana i paczka z podatnością
różnią się w tabeli na pierwszy rzut oka, a odczyt nie blokuje ani jednej
klatki.**

## Dlaczego to jest najlepszy stosunek wartości do rozmiaru w całej propozycji

Bo cała treść przychodzi **gotowa i w JSON-ie**: `composer outdated --format=json`,
`composer audit --format=json` i `npm outdated --json` odpowiadają danymi, a nie
tekstem dla ludzi. Moduł nie ma własnej dziedziny do wymyślenia — ma spis,
tabelę i dwa wywołania pracą tłową.

## Zarys zakresu

- **Tabela zależności** — nazwa, wersja zainstalowana, najnowsza zgodna,
  najnowsza w ogóle, rodzaj (produkcyjna / deweloperska), źródło (`composer`,
  `npm`).
- **Podatności** — osobny ton wiersza i osobna sekcja z opisem; treść z `audit`.
- **Filtr** — z kroku 30, po nazwie i po tym, czy zależność jest
  przeterminowana.
- **Odświeżenie** — pracą tłową, na żądanie; nigdy z klatki.
- **Kwerendy** — `deps.packages`, `deps.advisories`.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`.

## Pytania do rozstrzygnięcia

1. **Które ekosystemy w pierwszym kroku** — sam `composer` (projekt jest w PHP
   i sprawdzi się na sobie), czy od razu `composer` i `npm`?
2. **Sieć.** Oba polecenia biją do rejestrów, więc trwają sekundy i **mogą nie
   odpowiedzieć wcale**. Limit czasu jest pozycją ustawień czy stałą? Co widzi
   użytkownik bez sieci — pustą tabelę czy ostatnią znaną, z datą?
3. **Czy wynik jest pamiętany między uruchomieniami aplikacji** — plik stanu
   modułu (wzór `SshStateService`), czy odczyt za każdym razem?
4. **Czy moduł umie aktualizować** — czy zostaje przy czytaniu? Aktualizacja to
   zapis w cudzym projekcie i osobna klasa ryzyka.
5. **Skąd katalog** — `browser.cwd`, jak w krokach 66 i 67.

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Narzędzia | `composer` 2.7.1 i `npm` (Node 21.6.0) w `PATH`. |
| Formaty | `--format=json` w obu poleceniach Composera, `--json` w `npm outdated`. |
| Ten projekt | Ma `composer.json`; jest więc pierwszym odbiorcą modułu. |

## Zależności

- **Kroki 20 i 21** — kontrakt modułu i katalog bieżący.
- **Krok 26** — praca tłowa.
- **Kroki 27 i 30** — tabela i filtr.
- **Krok 53** — kwerendy.

## Model i wysiłek (wstępnie)

**Opus / medium.** Najlżejszy krok całej propozycji i taki ma być: jeden widok,
dwa wywołania, żadnego stanu poza pamięcią podręczną. Progu `medium` nie schodzi
poniżej, bo minimalnym wysiłkiem kroku w tym projekcie jest `medium`
(00-index.md, „Zasady przydziału modelu i wysiłku").

## Poza zarysem

- Aktualizowanie zależności i rozwiązywanie konfliktów wersji.
- Ekosystemy poza `composer` i `npm`.
- Graf zależności przechodnich — `TreeView` by to uniósł, ale odbiorcy dziś nie
  ma (reguła 13).
- Licencje paczek.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
