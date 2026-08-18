# Krok 77 — Zaznaczanie treści w oknie nakładanym

> **Skąd ten krok.** Powstał 2026-08-17 **na starcie kroku 57**
> ([00-decyzje.md](00-decyzje.md), D101 nr 4) i jest **zarysem, a nie planem**.
> Spłaca drugą połowę długu nazwanego w D100 — tę, której krok 57 świadomie nie
> wziął.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**; pytania
czekają w sekcji „Pytania do rozstrzygnięcia".

## Cel

Zaznaczenie treści z kroku 56 przestaje kończyć się na krawędzi okna
nakładanego: prostokąt daje się przeciągnąć **po oknie**, a `Alt`+`c` z kroku 57
kopiuje to, co pod nim pisze.

Miarą powodzenia jest zdanie: **odcisk `SHA256:…` z pytania o nieznany klucz
hosta i wiersz logu kontenera dają się obrysować myszą i skopiować — w torze
sixelowym, tekstowym i okienkowym.**

## Dług, który ten krok spłaca

D100 zapisał go wprost, jako skutek uboczny zakresu, a nie przeoczenie:

> **Treści okna nakładanego nie da się zaznaczyć** […] otwarcie okna kasuje
> zaznaczenie (punkt 2 zakresu), a kliknięcie w okno zużywa okno (krok 55). Dług
> jest nazwany tutaj i nie ma właściciela — odcisk klucza hosta i log kontenera
> to treści, które ktoś zechce skopiować.

**Krok 57 spłacił z tego połowę i nie więcej** (D101 nr 4): okno deklaruje
`Presentation\Ui\CopiesContent`, więc `Alt`+`c` w pytaniu o klucz hosta kopiuje
odcisk. Wskazać myszą, **co** z okna skopiować, nadal nie sposób — i to jest
dokładnie ta połowa, która została.

## Dlaczego to jest krok, a nie punkt w kroku 57

Bo rusza **trzy reguły mechanizmu z kroku 56 naraz**, a każda z nich powstała
z powodu, który trzeba unieważnić osobno:

1. **Otwarcie okna kasuje zaznaczenie** (`SelectionState::useFrame()`, reguła
   11ź). Powód był dobry: prostokąt jest współrzędnymi w siatce znakowej, a nie
   wskazaniem na treść, więc po zakryciu ekranu oknem wskazywałby miejsce,
   którego już nie ma. Zaznaczenie **w** oknie tego powodu nie ma — ale
   zaznaczenie zrobione **przed** otwarciem okna nadal go ma, więc reguła nie
   znika, tylko dzieli się na dwie.
2. **Kliknięcie w okno zużywa okno** (`InputHandler::toOverlayPointer()`, krok
   55). Okno jest modalne i ma nim zostać; przeciągnięcie musi się z tej
   modalności wyłamać, nie znieść jej. Zdolność `DragsOwnContent` z kroku 56
   odpowiada dziś **za ekran** i nie ma bliźniaka dla okna.
3. **Prostokąt jest jeden na klatkę** i mieszka w `LoopState`. Zaznaczenie
   w oknie i zaznaczenie pod oknem to dwa różne prostokąty w dwóch różnych
   układach współrzędnych — albo jeden, którego znaczenie zmienia się przy
   otwarciu okna.

Do tego dochodzi rzecz, której krok 56 nie musiał rozstrzygać: **warstwa
tekstowa klatki niesie okno razem z ekranem** (`FrameText::of()` przechodzi po
wszystkich płaszczyznach, a płaszczyzna okna jest `opaque`), więc odczyt treści
jest **gotowy** — nowego rachunku nie trzeba. To jest jedyna tania część tego
kroku i warto o niej pamiętać przy szacowaniu: koszt siedzi w regułach, nie
w czytaniu.

## Zarys zakresu

- **Zaznaczenie przeżywa otwarcie okna albo powstaje w oknie** — jedno z dwóch,
  do rozstrzygnięcia (pytanie 1).
- **Przeciągnięcie w oknie nie zużywa okna** — bliźniak `DragsOwnContent` dla
  `OverlayInterface` albo odwrotna reguła: okno **oddaje** przeciągnięcie, jeśli
  nie ma dla niego użytku.
- **Kasowanie przeliczone** — trzy dzisiejsze powody (zmiana ekranu, otwarcie
  okna, zmiana rozmiaru) rozdzielają się na te, które dotyczą zaznaczenia w oknie,
  i te, które go nie dotyczą; dochodzi czwarty: **zamknięcie** okna.
- **Kopiowanie bez zmian** — `Alt`+`c` z kroku 57 bierze zaznaczenie jako
  pierwsze źródło i nie musi wiedzieć, czy prostokąt leży na oknie.
- **Pomiar** — oś `--loop` „przed i po"; wyglądu klatki krok nie zmienia poza
  tym, że prostokąt daje się narysować w miejscu, w którym dziś się nie da, więc
  scenariusz `marquee` z kroku 56 dostaje wariant z oknem.
- **Przebiegi** — przeciągnięcie w oknie pytania, przeciągnięcie zaczęte na
  ekranie i skończone na oknie (i odwrotnie), zamknięcie okna z żywym
  zaznaczeniem, `Alt`+`c` w oknie.

## Czym płaci rdzeń

**Dwie rzeczy, obie w `Presentation`**: bliźniak zdolności przeciągnięcia dla
okna nakładanego i przeliczone reguły kasowania w `SelectionState`. Modułów krok
nie dotyka — okna, które zyskują zaznaczalną treść, nie zyskują ani jednej linii.

## Pytania do rozstrzygnięcia

1. **Jeden prostokąt czy dwa** — czy zaznaczenie zrobione przed otwarciem okna
   przeżywa je (jeden prostokąt, którego treść zmienia się pod oknem, bo warstwa
   tekstowa niesie to, co widać), czy otwarcie okna nadal kasuje, a zaznaczenie
   w oknie zaczyna się od zera (dwa prostokąty, nigdy naraz). Wariant drugi jest
   uczciwszy wobec zdania „zaznaczenie dotyczy klatki”, pierwszy — tańszy
   w kodzie i mniej zaskakujący dla użytkownika, który obrysował coś i otworzył
   pomoc.
2. **Kto ustępuje przy przeciągnięciu** — okno deklaruje zdolność „prowadzę
   własne przeciągnięcie” (wzorem `DragsOwnContent`, czyli stan) albo oddaje
   niezużyte przeciągnięcie skutkiem (`OverlayOutcome::handled === false`, czyli
   zdarzenie). Kontrakt okna ma na to **gotowe** pole `handled`, którego kontrakt
   ekranu nie miał — i to jest różnica, dla której D100 nr 2 wybrało wtedy
   zdolność.
3. **Czy zaznaczenie w oknie da się przewinąć** — okno komend i okno kwerend mają
   listy; prostokąt przeżywający przewijanie znaczy w nich to samo, co w ekranie
   (nowa treść pod tym samym prostokątem), ale okno pytania listy nie ma i tam
   pytanie nie istnieje.
4. **Czy `Shift`+przeciągnięcie zostaje ucieczką** do zaznaczania natywnego
   terminala także nad oknem — dziś zostaje, bo reguła jest globalna; nad oknem
   modalnym może to być mylące.

## Stan zastany (sprawdzony 2026-08-17, przy starcie kroku 57)

| Element | Stan |
|---|---|
| `Presentation\Ui\SelectionState` | Jeden prostokąt; `useFrame()` kasuje przy zmianie ekranu, otwarciu okna i zmianie rozmiaru. |
| `Presentation\Ui\DragsOwnContent` | Zdolność **ekranu**; `InputHandler::dragsOwn()` pyta o nią wyłącznie ekran na wierzchu. |
| `InputHandler::toOverlayPointer()` | Kliknięcie poza oknem jest połykane **niezależnie** od zdolności okna; wewnątrz decyduje `OverlayOutcome::$handled`. |
| `Application\Ui\FrameText::of()` | Przechodzi po **wszystkich** płaszczyznach klatki, łącznie z płaszczyzną okna — treść okna jest już czytelna. |
| `Presentation\Ui\CopiesContent` | Deklarują ją ekran **i** okno (krok 57, D101 nr 4) — czyli kopiowanie z okna działa, brakuje wskazania myszą. |

## Zależności

- **Krok 56** — mechanizm zaznaczenia, którego trzy reguły ten krok przelicza.
- **Krok 57** — `Alt`+`c` i zdolność `CopiesContent`, czyli odbiorca zaznaczenia.
- **Krok 55** — pierwszeństwo wskaźnika i modalność okna.
- **Krok 19** — okno nakładane i `OverlayOutcome::$handled`.
- **Krok 28** — okno pytania, czyli pierwsza treść, po którą ktoś sięgnie.
- **Kroki 51, 52** — logi kontenera i zasobu, czyli druga taka treść.

## Model i wysiłek (wstępnie)

**Opus / high.** Warunek `Fable` z przypisów ¹ i ² nie zachodzi: prymitywów nie
przybywa (prostokąt to `TextMark` na wiersz — 11k), słownik wejścia nie rośnie,
trzej tłumacze zostają nietknięci. Wysiłek trzyma **przeliczenie reguł
kasowania** i pierwszeństwo wskaźnika przy oknie modalnym — czyli to samo
miejsce, w którym krok 56 pomylił się co do `InputHandler`a.

## Poza zarysem

- **Zaznaczanie przepływowe** (od punktu do punktu, całe wiersze pośrodku) —
  prostokąt zostaje prostokątem, jak rozstrzygnęło D100.
- **Zaznaczanie klawiaturą** — mysz i tylko mysz, jak w kroku 56.
- **Zaznaczenie sięgające poza widok** — treść przewinięta pod prostokątem jest
  nową treścią zaznaczenia i to zdanie zostaje w mocy.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
