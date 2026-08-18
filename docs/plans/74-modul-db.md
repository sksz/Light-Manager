# Krok 74 — Moduł `db`: bazy danych, do których zagląda się w trakcie pracy

> **Skąd ten krok.** Powstał 2026-08-16 jako pierwszy krok **Fazy XXIV**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**; jedno —
miejsce na sekret — jest wspólne z krokiem 70 i rozstrzyga się tam.

## Cel

Moduł prowadzi **książkę połączeń do baz danych**, pokazuje ich strukturę
w drzewie i pozwala wykonać zapytanie, którego wynik ląduje w tabeli.

Miarą powodzenia jest zdanie: **zajrzenie do tabeli aplikacji nie wymaga
wychodzenia z menadżera plików ani otwierania drugiego narzędzia.**

## Trudność — pokusa, którą projekt już raz odrzucił

W środowisku są `pdo_pgsql`, `pdo_mysql`, `pgsql`, `mysqli` i `mongodb`. Każde
z nich kusi tym samym, czym `ext-ssh2` przed rozstrzygnięciem **D87**:
wywołaniem **w procesie aplikacji, blokującym i bez limitu czasu**. Tamta pokusa
kosztowała odwrócenie drogi technicznej całej Fazy XVII na jej starcie, i to po
tym, jak plan był już napisany.

Wniosek dla tego kroku jest zapisany zawczasu: **drogą domyślną jest proces
potomny** (`psql`, `mysql --batch`), a wywołanie w procesie wymaga uzasadnienia,
nie odwrotnie.

## Zarys zakresu

- **Książka połączeń** — nazwa własna, silnik, host, port, baza, użytkownik,
  sekret; plik stanu modułu.
- **Struktura** — `TreeView` z kroku 31: schematy → tabele → kolumny.
- **Podgląd danych** — pierwsze `N` wierszy tabeli w `Table`, z filtrem.
- **Zapytanie** — treść wpisana przez użytkownika, wykonanie pracą tłową, wynik
  w tabeli; historia zapytań.
- **Kwerendy** — `db.connections`, `db.schema`, `db.result`; **bez sekretów
  w wierszach** (reguła z kroku 54).

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`. Uwaga jednak: to jest **czwarte
postawienie wzorca książki wpisów** po hostach SSH (48), środowiskach Dockera
(58) i klastrach (59) — czyli krok wchodzi już **po** przeglądzie reguły 15e
wykonanym w kroku 59 i ma jego wynik zastosować, a nie powtórzyć.

**Od 2026-08-18 dochodzi do tego druga rzecz do zastosowania, nie do
wymyślenia**: krok 60 wynosi książkę adresową do osobnego modułu, a jego
granicą jest zdanie „adres w książce, poświadczenie u czytelnika". Książka
połączeń tego modułu rozpada się przez to na dwie części — **adres serwera bazy
jest wpisem książki adresowej**, a silnik, nazwa bazy i sekret zostają tutaj —
i pytanie 3 poniżej ma odtąd o jedną odpowiedź mniej do wymyślenia.

## Pytania do rozstrzygnięcia

1. **Które silniki w pierwszym kroku** — PostgreSQL i MariaDB/MySQL są na
   maszynie; MongoDB ma rozszerzenie, ale zupełnie inną dziedzinę (nie ma
   schematu ani SQL-a).
2. **Czy moduł pozwala pisać do bazy** (`INSERT`, `UPDATE`, `DELETE`, DDL), czy
   wyłącznie czytać? Jeśli pisze — czy potwierdzenie jest przy każdym
   zapytaniu zmieniającym, czy raz na połączenie?
3. **Miejsce na sekret** — jak w kroku 70; hasło do bazy jest tym samym
   problemem, co token.
4. **Wynik większy od okna** — zapytanie oddające milion wierszy. Limit
   nakładany zawsze, limit w ustawieniach, czy praca kawałkowa z paskiem
   postępu?
5. **Wpisy czytane z cudzych plików** — `~/.pgpass`, `~/.my.cnf`,
   `DATABASE_URL` ze środowiska. Wzór z kroku 58 mówi, że cudze pliki się
   czyta, a nie zapisuje; czy dotyczy to także poświadczeń?

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Klienci | `psql` 16.14 i `mysql` (MariaDB 10.11.14) w `PATH`. |
| Rozszerzenia PHP | `pdo_pgsql`, `pdo_mysql`, `pgsql`, `mysqli`, `mongodb` — wszystkie załadowane i wszystkie blokujące. |
| Precedens | D87 (krok 48): rozszerzenie blokujące bez limitu czasu wypadło z fazy w całości na rzecz procesu potomnego. |
| Wzorzec książki | Czwarte postawienie; przegląd 15e należy do kroku 59. |

## Zależności

- **Kroki 20, 26** — kontrakt modułu i praca tłowa.
- **Kroki 27, 29, 30, 31** — tabela, widok tekstu, filtr, drzewo.
- **Kroki 48, 58, 59** — książka wpisów, plik stanu modułu, wynik przeglądu
  reguły 15e.
- **Kroki 53 i 54** — kwerendy i sekret w ustawieniach.

## Model i wysiłek (wstępnie)

**Opus / xhigh.** Ciężar trzymają **dwa silniki o różnej składni introspekcji**,
wynik o nieznanym z góry rozmiarze i to, że moduł ma dostęp do danych
produkcyjnych — czyli każda czynność zmieniająca jest nieodwracalna w cudzym
systemie.

## Poza zarysem

- Migracje i wersjonowanie schematu.
- Edytor zapytań z podpowiadaniem i kolorowaniem składni — kolorowanie jest
  wykluczone z kroku 29 i zostaje wykluczone.
- Eksport wyniku do pliku — wchodzi, gdy będzie odbiorca.
- Tunelowanie połączenia przez SSH — powtórka rozstrzygnięcia z kroku 58, do
  rozważenia dopiero po nim.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
