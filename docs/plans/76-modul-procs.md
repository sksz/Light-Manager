# Krok 76 — Moduł `procs`: procesy i odpowiedź na pytanie „co zajęło ten port"

> **Skąd ten krok.** Powstał 2026-08-16 jako trzeci i ostatni krok **Fazy XXIV**
> ([00-decyzje.md](00-decyzje.md), D98) — a zarazem ostatni krok całej
> propozycji modułów. Jest **zarysem, a nie planem**.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**.

## Cel

Moduł pokazuje **procesy maszyny i porty, które nasłuchują**, pozwala znaleźć
właściciela portu i zakończyć proces za potwierdzeniem.

Miarą powodzenia jest zdanie: **„adres jest już używany" przestaje być powodem
do wyjścia z aplikacji.**

## Dlaczego mały krok stoi na końcu

Bo jest **najmniejszy i najbardziej samodzielny** z dziesięciu: nie zależy od
żadnego z pozostałych, nie ma sekretu, nie rozmawia z siecią i nie prowadzi
książki. Stoi ostatni nie dlatego, że jest trudny, tylko dlatego, że pozostałe
dziewięć odpowiada na pytania zadawane częściej.

## Zarys zakresu

- **Lista procesów** — identyfikator, właściciel, zużycie procesora i pamięci,
  wiersz polecenia; sortowanie po zużyciu.
- **Porty nasłuchujące** — protokół, adres, port, proces właściciela
  (`ss -ltnp`).
- **Znalezienie po porcie** — wpisanie numeru portu prowadzi do procesu.
- **Zakończenie procesu** — `SIGTERM`, a `SIGKILL` dopiero po drugim pytaniu.
- **Filtr** — z kroku 30, po nazwie i po porcie.
- **Kwerendy** — `procs.list`, `procs.ports`.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`.

## Pytania do rozstrzygnięcia

1. **Skąd dane** — `/proc` czytane wprost przez PHP (bez procesu potomnego,
   ale z rachunkiem na ścieżce klatki), czy `ps`/`ss` pracą tłową? Pierwsze
   jest szybsze, drugie zgodne z resztą aplikacji.
2. **Odświeżanie** — takt modułu daje listę żywą, ale lista procesów odczytana
   co klatkę jest dokładnie tym rodzajem pracy, przed którym broni reguła
   „żadnej pracy dłuższej od klatki w klatce".
3. **Cudze procesy.** Zakończenie procesu innego użytkownika wymaga uprawnień,
   których aplikacja nie ma. Widać je z powodem, czy nie widać ich wcale?
4. **Czy `SIGKILL` jest w ogóle dostępny z aplikacji** — jest nieodwracalny
   i nie daje procesowi posprzątać.
5. **Czy moduł widzi procesy potomne samej aplikacji** — pracę tłową prowadzi
   rdzeń i ma na nią kwerendę `core.jobs`; pokazanie ich drugą drogą byłoby
   drugą drogą do tej samej danej.

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Narzędzia | `ss` i `lsof` w `PATH`; `/proc` dostępne (Linux). |
| Kwerenda rdzenia | `core.jobs` oddaje prace tłowe aplikacji — patrz pytanie nr 5. |
| Reguła nadrzędna | Odczyt listy procesów nie pada w rysowaniu klatki. |

## Zależności

- **Kroki 20, 26** — kontrakt modułu i praca tłowa.
- **Kroki 27, 28, 30** — tabela, pytanie przed czynnością nieodwracalną, filtr.
- **Krok 53** — kwerendy.

## Model i wysiłek (wstępnie)

**Opus / medium.** Jeden widok w dwóch postaciach i jedna czynność. Ciężar
mieści się w rozstrzygnięciu nr 1 i w tym, żeby lista odświeżana często nie
weszła na ścieżkę klatki.

## Poza zarysem

- Drzewo procesów i zależności rodzic–potomek.
- Zmiana priorytetu (`nice`, `renice`) i ograniczanie zasobów.
- Procesy na maszynie zdalnej — należą do rozmowy modułu `ssh`.
- Wykresy zużycia w czasie — aplikacja nie ma prymitywu wykresu.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
