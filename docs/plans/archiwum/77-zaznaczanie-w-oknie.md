# Krok 77 — Zaznaczanie treści w oknie nakładanym

> **Skąd ten krok.** Powstał 2026-08-17 **na starcie kroku 57**
> ([00-decyzje.md](../00-decyzje.md), D101 nr 4) jako **zarys**; planem stał się
> 2026-08-19, wraz z rozstrzygnięciami startowymi (D106). Spłaca drugą połowę
> długu nazwanego w D100 — tę, której krok 57 świadomie nie wziął.

## Status

**Ukończony 2026-08-19.** Wydany jako **19.4.0** w fazie Kastaniety.
Zastrzeżenie odziedziczone po kroku 55 — „klatki pod XTermem nikt nie oglądał” —
**jest zdjęte**: użytkownik przeciągnął myszą po oknie komend w prawdziwym
terminalu i dostarczył dwa zrzuty. Widać na nich prostokąt na treści okna, zdanie
„Zaznaczono 14 wierszy klatki” i „Skopiowano zaznaczenie: 14 wierszy”. Kontrola
wizualna narzędziem projektu jest osobno zrobiona i czysta (`--png-compare`:
21 z 21 scenariuszy zgodnych co do piksela, wzorzec `sixel-marquee-popup.png`
obejrzany).

Rozstrzygnięcia startowe zapadły **2026-08-19**
([00-decyzje.md](../00-decyzje.md), D106) — cztery pytania zarysu mają cztery
odpowiedzi i stoją niżej, w sekcji „Rozstrzygnięcia startowe". Zarys stał się
przez to planem; sekcje „Cel", „Dług", „Dlaczego to jest krok" i „Poza zarysem"
zostają bez zmian, bo rozstrzygnięcia ich nie ruszyły.

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

## Zakres

Sześć punktów; pierwsze trzy to cały mechanizm, trzy pozostałe to jego cena
i dowód.

1. **Warstwa tekstowa liczy się z klatki pełnej.** `FrameComposer::compose()`
   składa odtąd w kolejności **oprawa → treść → okno nakładane → zaznaczenie**,
   więc `FrameText::of()` dostaje płaszczyznę okna i przepisuje jej treść
   (płaszczyzna jest `opaque`, więc wymazanie prostokąta już umie), a prostokąt
   zaznaczenia rysuje się **nad** oknem, a nie pod nim. Bez tej zamiany
   pozostałe punkty nie mają czego czytać ani gdzie się narysować. Rachunku nie
   przybywa: warstwa tekstowa powstaje nadal wyłącznie przy żywym zaznaczeniu.
2. **Przeciągnięcie nad oknem buduje prostokąt.** `InputHandler::pointer()`
   przestaje kończyć drogę na `toOverlayPointer()`: okno dostaje wskaźnik jak
   dziś, a zaraz po nim ten sam ruch idzie do `select()` — dokładnie tak, jak
   nad ekranem, gdzie `toScreenPointer()` i `select()` widzą to samo zdarzenie.
   **Modalność zostaje nietknięta**: pod okno nadal nie schodzi ani stopka, ani
   ekran, ani menu, ani podwójne kliknięcie.
3. **Okno umie zabrać przeciągnięcie sobie.** Nowa zdolność
   `Presentation\Ui\DragsOwnContentInOverlay` — bliźniak `DragsOwnContent`
   z kroku 56, o jednej metodzie `isDraggingOwn()`. `InputHandler::dragsOwn()`
   pyta o nią **okno na wierzchu**, a ekran pod spodem przestaje być pytany
   w ogóle, dopóki okno stoi (mógł zostać w stanie „trzymam granicę podziału").
   Deklarującego zdolność nie ma i nie dorabiamy go — to trzeci jawny wyjątek od
   reguły 13, zapisany w D106 nr 2.
4. **Kasowanie: identyfikator zamiast flagi.**
   `SelectionState::useFrame(string $screen, ?string $overlayId, int $rows, int $columns)`
   — klucz klatki niesie **który** to okno, a nie „czy jakieś". Otwarcie kasuje,
   zamknięcie kasuje, **podmiana okna kasuje** (`OverlayOutcome::replace()`,
   krok 41 — łańcuch trzech okien przy usuwaniu katalogu). Przewijanie nie
   kasuje, w oknie tak samo jak w ekranie.
5. **Kopiowanie bez zmian.** `Alt`+`c` bierze zaznaczenie jako pierwsze z trzech
   źródeł (reguła 11ż) i nie wie, czy prostokąt leży na oknie. Ani jednej linii
   w `InputHandler::copyable()`.
6. **Moduły bez zmian.** Okna, które zyskują zaznaczalną treść — pytanie
   o klucz hosta (28, 48), logi kontenera (51) i zasobu (52) — nie zyskują ani
   jednej linii.

## Pomiar i przebiegi

- **Oś `--loop` „przed i po"** — bo krok dokłada rachunek na ścieżce klatki
  (płaszczyzna okna wchodzi do warstwy tekstowej) i przestawia kolejność
  składania.
- **Scenariusz `marquee-popup`** — `marquee` z kroku 56 dostaje wariant
  z otwartym oknem, bo dopiero on mierzy to, co ten krok dokłada: przepisanie
  prostokąta okna do warstwy tekstowej i `TextMark`i rysowane na wierzchu.
  Rozlicza się w parze z `marquee` (reguła 16b), a różnica między nimi jest ceną
  kroku wyrażoną w milisekundach.
- **Przebiegi** (`tests/Functional/SelectionInOverlayFlowTest.php`):
  przeciągnięcie w oknie pytania i odczyt odcisku; przeciągnięcie zaczęte na
  ekranie i skończone na oknie oraz odwrotnie; otwarcie okna z żywym
  zaznaczeniem (kasuje); zamknięcie okna z żywym zaznaczeniem (kasuje); podmiana
  okna (kasuje); przewinięcie listy okna (nie kasuje); `Alt`+`c` w oknie.
- **Klatka pod XTermem** — bo krok zmienia kolejność płaszczyzn, a to jedyna
  rzecz w nim, której test nie widzi oczami użytkownika.

## Czym płaci rdzeń

**Dwie rzeczy, obie w `Presentation`**: bliźniak zdolności przeciągnięcia dla
okna nakładanego i przeliczone reguły kasowania w `SelectionState`. Modułów krok
nie dotyka — okna, które zyskują zaznaczalną treść, nie zyskują ani jednej linii.

## Rozstrzygnięcia startowe (2026-08-19, D106)

Cztery pytania zarysu, cztery odpowiedzi. Pełne uzasadnienia stoją
w [00-decyzje.md](../00-decyzje.md), D106; tutaj są same odpowiedzi.

1. **Dwa prostokąty, nigdy naraz.** Otwarcie okna kasuje, zamknięcie kasuje,
   zaznaczenie w oknie zaczyna się od zera. Zarys szacował ten wariant jako
   droższy — **jest odwrotnie**: klucz klatki niesie `overlayOpen` od kroku 56,
   więc kosztuje w `SelectionState` zero linii, a wariant „jeden prostokąt"
   wymagałby zniesienia kasowania także przy zamknięciu.
2. **Okno ustępuje zdolnością, nie skutkiem** — `DragsOwnContentInOverlay`.
   Odwraca to rekomendację przez `OverlayOutcome::handled === false` i płaci
   nazwaną cenę: zdolność wchodzi **bez deklarującego**, jako trzeci jawny
   wyjątek od reguły 13. Broni jej to, że nie jest nowym mechanizmem, tylko
   drugą połową mechanizmu, który odbiorcę ma.
3. **Przewijanie listy okna nie kasuje** — reguła 11ź bez wyjątku.
4. **`Shift`+przeciągnięcie zostaje ucieczką** także nad oknem: w torze
   terminalowym przechwytuje je emulator, więc odwrotna reguła dałaby się
   dowieźć wyłącznie w torze okienkowym.

## Stan zastany (sprawdzony 2026-08-19, przy starcie kroku)

Tabela z 2026-08-17 miała trzy wiersze niedokładne i **wszystkie trzy przestawiły
rachunek kosztów** — stąd druga kolumna: co zarys zapisał, a co jest.

| Element | Stan sprawdzony 2026-08-19 |
|---|---|
| `Presentation\Ui\SelectionState` | Jeden prostokąt; `useFrame()` kasuje przy zmianie ekranu, otwarciu okna, **zamknięciu okna** i zmianie rozmiaru. Zarys zapowiadał zamknięcie jako powód nowy — stoi w kodzie od kroku 56, bo `overlayOpen` jest w kluczu klatki. |
| `Presentation\Ui\DragsOwnContent` | Zdolność **ekranu**; `InputHandler::dragsOwn()` pyta o nią wyłącznie ekran na wierzchu. Bez zmian wobec zarysu. |
| `InputHandler::toOverlayPointer()` | Kliknięcie **kończy drogę** na oknie: `pointer()` zwraca z niego wprost, więc przy otwartym oknie `select()` nie pada ani razu. Wewnątrz decyduje `OverlayOutcome::$handled`, ale zdolność `AcceptsPointerInOverlay` deklaruje **jedno z dwunastu okien** (`MenuOverlay`) — pozostałe połykają wskaźnik, zanim cokolwiek rozstrzygną. |
| `Application\Ui\FrameText::of()` | Przechodzi po wszystkich płaszczyznach i **umie** okno (płaszczyzna jest `opaque`) — ale `FrameComposer` podaje mu płaszczyzny **złożone do tej pory**, a okno dokłada **po** zaznaczeniu. Zdanie zarysu „odczyt treści jest gotowy" jest prawdziwe o klasie i nieprawdziwe o składaniu klatki. |
| `Presentation\Ui\CopiesContent` | Deklarują ją ekran **i** okno (krok 57, D101 nr 4) — kopiowanie z okna działa, brakuje wskazania myszą. Bez zmian wobec zarysu. |

## Zależności

- **Krok 56** — mechanizm zaznaczenia, którego trzy reguły ten krok przelicza.
- **Krok 57** — `Alt`+`c` i zdolność `CopiesContent`, czyli odbiorca zaznaczenia.
- **Krok 55** — pierwszeństwo wskaźnika i modalność okna.
- **Krok 19** — okno nakładane i `OverlayOutcome::$handled`.
- **Krok 28** — okno pytania, czyli pierwsza treść, po którą ktoś sięgnie.
- **Kroki 51, 52** — logi kontenera i zasobu, czyli druga taka treść.

## Model i wysiłek

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

### 2026-08-19 — klatka pod XTermem: sprawna, a zgłoszony przeze mnie defekt był błędem odczytu

Przeciągnięcie myszą po oknie komend w prawdziwym terminalu **działa**: prostokąt
kładzie się na treści okna, pasek stanu mówi „Zaznaczono 14 wierszy klatki”,
a `Alt`+`c` — „Skopiowano zaznaczenie: 14 wierszy”.

**Zgłosiłem wtedy defekt, którego nie ma, i to jest wpis o tym, jak go nie
zgłaszać.** Ze zrzutu odczytałem, że prostokąt powtarza dwa znaki stojące na lewo
od swojej krawędzi (`…clau` + `aude-1000`). Policzyłem znaki po lewej stronie
krawędzi na 22; jest ich 20. Przy dwudziestu wszystkie wiersze zgadzają się co do
znaku — `core.dump /tmp/claud` + `e-1000`, `browser.jump /tmp/cl` + `aude-1000`,
`browser.jump /home/s` + `ksz/Projects`.

Rozstrzygnęły to dwie rzeczy, obie tańsze od wpatrywania się w zrzut i obie warte
zapamiętania jako **kolejność, w której należało zacząć**:

1. **Odtworzenie w kodzie** — okno komend z długim wierszem historii, zaznaczenie
   zaczęte w środku wiersza: `mark.column` równa się kolumnie cięcia, a treść
   znacznika, treść z `FrameText` i treść skopiowana są **tym samym napisem**.
   Model był poprawny, więc do schowka i tak szło to, co trzeba.
2. **Wzorzec `sixel-marquee.png`** — wiersze zaznaczone stoją w jednej linii
   z niezaznaczonymi pod nimi, czyli `TextMark` nie jest w torze sixelowym
   przesunięty względem `TextRun`.

**Wniosek: zrzut ekranu nie jest dowodem na przesunięcie o dwie kolumny.** Przy
pisemku 8 px różnica dwóch znaków mieści się w błędzie odczytu, a wzorzec PNG
i odtworzenie w kodzie dają odpowiedź wprost. Luka, którą to odsłoniło i która
zostaje **niezałatana**: żaden scenariusz pomiarowy nie ma prostokąta, którego
krawędź **tnie tekst w poprzek** — `marquee` obrysowuje pełną szerokość panelu,
a `marquee-popup` całe okno. Gdyby przesunięcie było prawdziwe, `--png-compare`
by go nie złapał.

### 2026-08-19 — pomiar: oś `--loop` tego kroku nie mierzy, mierzy go para sixelowa

**Zapowiedź osi `--loop` w tym planie była błędna i jest odwołana.** Tor taktu
składa klatkę `LoopScenarioScreen`em, a ten nie ma **ani okna nakładanego, ani
zaznaczenia** — a koszt kroku pojawia się wyłącznie wtedy, gdy istnieją oba
naraz. Bez nich `FrameComposer::selection()` wychodzi tak samo wcześnie jak
przedtem, a płaszczyzna okna ląduje po prostu w innym miejscu tablicy. Zmierzone
mimo to, jako dowód braku różnicy (wzorzec `2026-08-19-po-kroku-60-loop.json`):
**+2,2% i −0,9%** na dwóch wierszach osi, czyli szum. Jest to ten sam kształt
pomyłki, który krok 55 zapisał u siebie („oś `--loop` nie mierzy tego, co ten
krok dokłada do wejścia”) — **wniosek na przyszłość: zanim wpiszesz oś do planu,
sprawdź, czy scenariusz tej osi ma w ogóle stan, który krok zmienia.**

**Cenę kroku mierzy para sixelowa** (`2026-08-19-po-kroku-77.json`, obciążenie
0,03 na rdzeń, rozrzut poniżej progu we wszystkich wierszach):

| Para | Rysowanie | Kwantyzacja | Razem |
|---|---|---|---|
| `popup` | 6,9 ms | 7,5 ms | **18,6 ms** |
| `marquee` | 5,3 ms | 9,5 ms | **19,0 ms** |
| `marquee-popup` | 8,0 ms | 14,2 ms | **26,3 ms** |

Czyli **+7,7 ms wobec `popup`** i **+7,3 ms wobec `marquee`**, z czego lwia część
siedzi w **kwantyzacji**, a nie w rysowaniu (+6,7 ms wobec `popup` przy +1,1 ms
rysowania). Klatka mieści się w budżecie 33 ms i płaci się za nią **wyłącznie
w chwili, gdy prostokąt leży na oknie** — czyli w trakcie przeciągania.

**Regresji nie ma, ale liczby „przed i po” są nieporównywalne wprost.** Cała
tabela dwudziestu trzech scenariuszy wyszła o 8–15% niżej od wzorca kroku 60,
łącznie z `pustym płótnem`, którego ten krok nie ma jak dotknąć — więc jest to
**różnica środowiska, nie kodu**, i tak też ją czytam. Dowodem braku regresji
jest tu co innego: `--png-compare` daje **21 z 21 scenariuszy zgodnych co do
piksela** (0 różniących się pikseli, próg 0 ‰), czyli odwrócenie kolejności
płaszczyzn nie zmieniło ani jednej istniejącej klatki.

**Pierwszy przebieg odrzucony i niezapisany** — narzędzie odmówiło wzorca, bo
wiersz `lista z podświetleniem` miał rozrzut 18,3–27,6 ms. Powtórzony na maszynie
bezczynnej przeszedł bez ostrzeżeń. Odrzucona jest też moja pomyłka po drodze,
warta zapisania, bo kosztowała przebieg: pętla czekająca na spadek obciążenia
nigdy się nie zamknęła, bo **`awk` w polskiej lokalizacji czyta `0.85` jako
`0`** — maszyna stała wtedy bezczynnie, a ja myślałem, że mierzy.

**Do zapamiętania poza tym krokiem:** scenariusze `environments` (58)
i `address-book` (60) **nie mają wzorców PNG** — `--png-compare` mówi o nich
„brak wzorca”. To luka tamtych kroków, nie tego; nie uzupełniam jej tutaj, bo
wzorzec zapisany bez obejrzenia różnicy jest gorszy od braku wzorca.

### 2026-08-19 — rozstrzygnięcia startowe i stan zastany

Zarys stał się planem. Przed pytaniami sprawdzony został kod, nie zarys — i to
przestawiło dwie z czterech odpowiedzi: kasowanie przy zamknięciu okna **już
było**, a warstwa tekstowa okna **jeszcze nie widziała**. Rozstrzygnięcia stoją
w D106; jedno (nr 2) odwraca rekomendację i płaci trzecim jawnym wyjątkiem od
reguły 13.

