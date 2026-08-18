# Krok 73 — Moduł `env`: pliki środowiskowe i różnica, której nikt nie widzi

> **Skąd ten krok.** Powstał 2026-08-16 jako czwarty i ostatni krok **Fazy
> XXIII** ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**.

## Cel

Moduł czyta **pliki środowiskowe projektu** (`.env`, `.env.example`,
`.env.local` i im podobne) i pokazuje je obok siebie: które klucze są wszędzie,
którego brakuje, który jest nadmiarowy, a który ma inną wartość.

Miarą powodzenia jest zdanie: **klucz obecny w `.env.example`, a nieobecny
w `.env`, widać bez czytania obu plików.**

## Dlaczego akurat to

Bo jest to **najtańszy moduł całej propozycji** — czyta pliki, rysuje tabelę,
nie rozmawia z niczym — a odpowiada na błąd, który kosztuje najwięcej czasu
w cudzym projekcie: aplikacja nie wstaje, bo w środowisku brakuje jednego
klucza dopisanego przez kogoś innego w zeszłym tygodniu.

## Zarys zakresu

- **Porównanie plików** — kolumna na plik, wiersz na klucz; brak, nadmiar
  i różnica wartości mają własny ton.
- **Wartości zasłonięte** — domyślnie widać, **że** wartość jest, a nie jaka;
  odsłonięcie jest czynnością.
- **Filtr** — z kroku 30, po nazwie klucza.
- **Kopiowanie brakującego klucza** z jednego pliku do drugiego — jeśli
  rozstrzygnięcie nr 3 na to pozwoli.
- **Kwerendy** — `env.files`, `env.diff`; **bez wartości** w wierszach, wzorem
  reguły z kroku 54.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`. Gdyby moduł miał zapisywać,
robi to przez `FileOperationsPort` z kroku 41 — czyli wyjątek 15b zostaje
nietknięty i nie rośnie.

## Pytania do rozstrzygnięcia

1. **Które pliki moduł uznaje za środowiskowe** — wzorzec nazw (`.env*`), spis
   w ustawieniach, czy zaznaczenie użytkownika w przeglądarce?
2. **Czy moduł pisze.** Dopisanie brakującego klucza jest kuszące i tanie, ale
   to zapis do cudzego pliku konfiguracyjnego — i albo idzie przez port
   rdzenia z kroku 41, albo nie ma go wcale.
3. **Czy wartości widać domyślnie.** Plik `.env` bywa pełen haseł; zasłonięcie
   domyślne jest bezpieczniejsze, ale utrudnia to, po co moduł powstał.
4. **Czy porównanie obejmuje środowisko procesu** (`getenv`) — bo różnica
   „plik mówi X, proces widzi Y" jest tą, która najczęściej boli.
5. **Formaty pokrewne** — `docker-compose` `environment:`, `values.yaml`,
   `ConfigMap`. Kuszące, ale to są cudze dziedziny i cudze moduły.

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Zapis na dysk | Wyłącznie przez porty rdzenia z kroków 41, 42 i 44 (wyjątek 15b) — moduł nie woła `file_put_contents`. |
| Reguła o materiale uwierzytelnienia | Krok 54: wiersze kwerendy widzi każdy, więc sekrety do nich nie wchodzą. |
| Ten projekt | `.env` nie ma — odbiorcą jest projekt cudzy, oglądany przeglądarką. |

## Zależności

- **Kroki 20, 21, 24** — kontrakt modułu, katalog bieżący, dwa panele.
- **Kroki 27, 29, 30** — tabela, widok tekstu, filtr.
- **Krok 41** — port zapisu, gdyby rozstrzygnięcie nr 2 wypadło na „pisze".
- **Krok 53** — kwerendy.

## Model i wysiłek (wstępnie)

**Opus / medium.** Bez sieci, bez procesu potomnego, bez stanu poza odczytem.
Ciężar całego kroku mieści się w rozstrzygnięciu nr 3 i w tym, żeby moduł nie
stał się drugą drogą do zapisu plików.

## Poza zarysem

- Szyfrowanie plików środowiskowych (`sops`, `age`, `ansible-vault`).
- Wysyłanie wartości do cudzych magazynów sekretów.
- Sprawdzanie, czy klucz jest w kodzie w ogóle używany — to jest analiza
  źródła, czyli inna praca.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
