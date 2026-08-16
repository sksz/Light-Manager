# Krok 70 — Moduł `http`: endpointy aplikacji i pytanie „czy to jeszcze żyje"

> **Skąd ten krok.** Powstał 2026-08-16 jako drugi krok **Fazy XXIII**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**; jedno
z nich — miejsce na sekret — jest wspólne z krokiem 69 i rozstrzyga się tam.

## Cel

Moduł prowadzi **spis zapytań HTTP** do usług, którymi użytkownik się opiekuje:
wysyła je na żądanie, pokazuje kod odpowiedzi, czas i treść, a wybrane sprawdza
cyklicznie jako health-check.

Miarą powodzenia jest zdanie: **usługa, która przestała odpowiadać, mówi o tym
sama — bez otwierania jej ekranu.**

## Dlaczego to nie jest kolejny klient REST

Bo odbiorcą jest **opiekun uruchomionej aplikacji**, a nie autor zapytania:
wartością jest spis endpointów, które trzeba mieć na oku, i to, że aplikacja
zauważa ich milczenie **taktem modułu** (`NeedsTick` z kroku 45), a wynik ogłasza
zdarzeniem (krok 46) — czyli padnięta usługa może zabrzmieć tak samo, jak
zakończone kopiowanie.

## Zarys zakresu

- **Spis zapytań** — nazwa własna, metoda, adres, nagłówki, treść; plik stanu
  modułu, wzorem `SshStateService`.
- **Wysyłka** — pracą tłową albo `curl_multi` poza klatką; kod, czas, rozmiar,
  treść w `TextView`.
- **Health-check** — wybrane wpisy odpytywane taktem modułu z własnym odstępem;
  zmiana stanu (żyje → nie żyje) jest zdarzeniem.
- **Historia** — ostatnie odpowiedzi z czasem, żeby dało się zobaczyć, kiedy
  usługa zwolniła.
- **Kwerendy** — `http.endpoints`, `http.health`; **bez nagłówków
  uwierzytelniających w wierszach** (reguła z kroku 54).

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`.

## Pytania do rozstrzygnięcia

1. **Droga techniczna** — `ext-curl` w procesie (jak Docker), czy `curl`
   procesem potomnym (jak `kubectl`)? Rozmowa z gniazdem lokalnym była szybka
   i to usprawiedliwiało wywołanie w procesie; sieć rozległa tego nie
   usprawiedliwia, a `curl_multi` bez blokowania jest trzecią drogą pośrednią.
2. **Sekrety w zapytaniach** — token w nagłówku to ten sam problem, co
   w kroku 69, i rozstrzyga się razem z nim.
3. **Czy health-check chodzi, gdy ekran modułu jest zamknięty.** Takt modułu na
   to pozwala od kroku 45 — ale to znaczy ruch w sieci bez patrzenia
   użytkownika i musi dać się wyłączyć jedną pozycją.
4. **Co znaczy „nie żyje"** — kod ≥ 500, brak odpowiedzi, przekroczony czas,
   niezgodna treść? Definicja jest daną wpisu czy stałą modułu?
5. **Import kolekcji** z cudzych narzędzi (OpenAPI, `.http`) — w pierwszym
   kroku czy nigdy?

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| `ext-curl` | Załadowane; moduł Dockera rozmawia nim z gniazdem `/var/run/docker.sock`. |
| `curl` | Jest w `PATH`. |
| Takt modułu | `NeedsTick` od kroku 45 — moduł dostaje takt także wtedy, gdy jego ekranu nie widać. |
| Zdarzenia | Słownik zamknięty konstrukcyjnie (krok 46); nowe nazwy są decyzją. |

## Zależności

- **Kroki 20 i 26** — kontrakt modułu i praca tłowa.
- **Kroki 27 i 29** — tabela i widok treści odpowiedzi.
- **Kroki 45 i 46** — takt modułu i zdarzenia.
- **Krok 51** — rozmowa nieblokująca przez `ext-curl` jako wzór.
- **Krok 53** — kwerendy.

## Model i wysiłek (wstępnie)

**Opus / high.** Nowego rodzaju rozmówcy nie wnosi, jeśli krok 69 rozstrzygnie
drogę wcześniej; ciężar leży w **pracy chodzącej bez patrzenia** — takt, który
sięga do sieci, musi umieć nie przeszkodzić klatce ani razu.

## Poza zarysem

- Gniazda sieciowe trwałe (WebSocket, SSE) — inny rodzaj rozmowy.
- gRPC.
- Testy kontraktowe i asercje na treści odpowiedzi.
- Wykresy czasu odpowiedzi — aplikacja nie ma prymitywu wykresu, a jego
  dołożenie ruszałoby trzech tłumaczy słownika.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
