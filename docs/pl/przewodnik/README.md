# Przewodnik dewelopera

Odpowiada na pytanie **jak dołożyć swoją rzecz**. Nie odpowiada na pytanie
**dlaczego tak wyszło** — to jest [dziennik decyzji](../../plans/00-decyzje.md),
ani **jak jest** — to jest [dokument źródłowy](../../architecture.md).

Jesteś tu pierwszy dzień → [onboarding](../onboarding/README.md).
Chcesz aplikacji **używać**, a nie ją rozwijać → [podręcznik](../podrecznik/README.md).

Lustro angielskie: [../../en/guide/](../../en/guide/README.md).

| # | Rozdział | Odpowiada na pytanie |
|---|---|---|
| 1 | [Mapa kodu](01-mapa-kodu.md) | gdzie co leży i dlaczego tam |
| 2 | [Cykl życia klatki](02-cykl-klatki.md) | jak to się kręci i co wolno w której fazie |
| 3 | [Jak dodać swoją rzecz](03-jak-dodac.md) | **osiem przewodników**: moduł, komenda, kwerenda, ustawienie, komponent, okno, praca tłowa, napisy |
| 4 | [Zanim dołożysz](04-zanim-dolozysz.md) | dwie rzeczy, na które odpowiedź prawie zawsze brzmi „nie” |
| 5 | [Pułapki](05-pulapki.md) | **dziesięć** rzeczy, za które projekt już raz zapłacił |
| 6 | [Workflow pracy](06-workflow.md) | kolejność procesów, bramka, testy, pomiar, budowa |
| 7 | [Jak czytać dziennik decyzji](07-dziennik-jak-czytac.md) | 110 wpisów i trzy drogi dojścia do właściwego |
| 8 | [Spisy pod pilnowaniem](08-spisy.md) | **komendy, kwerendy** i to, co zrobić, gdy test zgodności się czerwieni |

## Trzy najkrótsze drogi

- **Chcę coś dołożyć i nie wiem, gdzie** → [1](01-mapa-kodu.md) →
  [3](03-jak-dodac.md).
- **Coś nie działa i nie rozumiem dlaczego** → [5](05-pulapki.md), pisane
  objawem.
- **Chcę wiedzieć, czy wolno mi tknąć rdzeń** → [4](04-zanim-dolozysz.md).

## Przykłady

Przewodniki wskazują **prawdziwe pliki**, nie bloki w markdownie
([konwencja](../../KONWENCJE.md)). Wzorzec, który w aplikacji **jest**,
wskazywany jest w `src/`; wzorzec dydaktyczny —
w [`examples/`](../../../examples/).

Kompletny mikromoduł — komenda, kwerenda, pozycja ustawień i napisy w dwóch
językach — stoi w
[`examples/modul-przykladowy/`](../../../examples/modul-przykladowy/)
i przechodzi PHPStan `max` razem z `src/`.
