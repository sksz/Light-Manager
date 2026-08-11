# Krok 33 — Reakcja na zmianę rozmiaru okna terminala

> **Skąd ten krok.** Powstał 2026-08-11, na polecenie użytkownika. Do tej pory
> rozmiar okna był mierzony **raz, przy starcie** — założenie jawne od kroku 06
> („Ustala rozmiar okna terminala raz, przy starcie aplikacji” — komentarz
> klasowy `TerminalSizeService`) i do dziś nienaruszone przez żaden krok.

## Status

**Nie rozpoczęty** (2026-08-11).

## Cel

Sprawić, żeby aplikacja **zauważała zmianę rozmiaru okna i od następnej klatki
rysowała się w nowym rozmiarze** — bez restartu, bez artefaktów po poprzednim
rozmiarze i bez podrożenia klatki w oknie, które się nie zmienia.

Miarą powodzenia jest zdanie: **użytkownik przeciąga róg okna XTerma,
a lista plików płynie za nim — powiększona klatka pokazuje więcej wierszy,
pomniejszona mniej, i w żadnym momencie na ekranie nie zostaje strzęp
poprzedniej klatki.**

## Zależności

- **Krok 06** (terminal I/O i sygnały) — twardo i podwójnie. `SIGWINCH` wchodzi
  obok czterech już obsługiwanych sygnałów i tym samym wzorcem: uchwyt ustawia
  znacznik, pętla czyta go między klatkami (`pcntl_async_signals` już działa,
  `EINTR` w `stream_select` już wyciszony). Stamtąd pochodzi też maszyneria
  ewentualnego ponownego pytania o piksele: `WindowSizeParser`
  i `pushBackBytes()` — odpowiedź terminala, która przyszła wymieszana
  z klawiszami, umie oddać klawisze z powrotem.
- **Krok 09** (pętla główna) — znacznik zmiany musi być odczytany w **jednym**
  miejscu taktu, tak samo jak `shutdownRequested()`. Serie `SIGWINCH` sypiące
  się przy przeciąganiu rogu okna składają się wtedy same do jednego pomiaru
  na klatkę — pętla jest naturalnym tłumikiem drgań.
- **Krok 17** (pamięci podręczne) — to on czyni ten krok tanim i to jest do
  sprawdzenia, nie do zrobienia: klucz płaszczyzny spodniej i bitmap napisów
  **zawiera rozmiar** (`metricsKey()`), więc pamięć „odświeża się sama, bez
  ścieżki unieważnienia, o której można zapomnieć” — dokładnie na tę sytuację
  była projektowana (D34). Krok 33 jest pierwszym prawdziwym sprawdzianem tego
  zdania.
- **Krok 18** (komponenty i płaszczyzny) — układ (`HudLayout`) i komponenty
  liczą się **co klatkę** z podanych prostokątów, a stan żyjący między klatkami
  (`ScrollWindow`, `SectionState`, `SplitState`) ścina się do pojemności przy
  rysowaniu (`clamp()`). Dzięki temu warstwa ekranów nie powinna wymagać ani
  jednej zmiany — i to też jest teza do sprawdzenia, nie życzenie.

Od kroków **27–32** (Faza VII) krok **nie zależy** i one nie zależą od niego —
wolno go zrobić przed nimi, po nich albo pomiędzy.

## Model i wysiłek

**Fable / xhigh.**

Kodu będzie mało, bo cała droga rozmiaru do klatki już istnieje:
`FrameComposer` pyta `ViewportPort` o wiersze i kolumny **co klatkę**,
`SixelFrameRenderer` pyta o piksele **co klatkę** — zamrożona jest wyłącznie
odpowiedź. Ciężar leży w rozstrzygnięciach: skąd wziąć piksele w środku pętli
(zapytanie `ESC [ 14 t` konkuruje o STDIN z klawiszami), czy `TerminalSizeService`
ma prawo przestać być niezmienny, i co pokazać w oknie zwężonym poniżej sensu.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Infrastructure/Terminal/TerminalSizeService` | Mierzy w konstruktorze, wynik w polu `readonly` — rozmiar żyje tak długo, jak proces. Komórki ze `stty size`, piksele z `ESC [ 14 t` (300 ms na odpowiedź), fallback: komórka 6×13 |
| `Infrastructure/Terminal/TerminalService` | `SIGINT`/`SIGTERM`/`SIGHUP`/`SIGQUIT` → znacznik `shutdownRequested`; `SIGWINCH` **nieobsługiwany**; `pcntl` wymagane twardo już dziś |
| `Presentation/Cli/GameLoop` | Rysuje co takt niezależnie od zmian; znacznik zamknięcia czyta między klatkami — gotowe miejsce na drugi znacznik |
| `Presentation/Cli/FrameComposer` | `rows()`/`columns()` z portu **co klatkę**; `MINIMUM_COLUMNS = 20` — dolny próg tylko dla kolumn i tylko rachunkowy |
| `Infrastructure/Imagick/SixelFrameEncoder` | Płaszczyzna spodnia i bitmapy napisów kluczowane `metricsKey()` z rozmiarem w środku — po zmianie rozmiaru odświeżą się same; stare wpisy wypiera limit `RowBitmapCache` (512) |
| `Infrastructure/Rendering/TextFrameRenderer` | `CellBuffer` budowany z rozmiaru co klatkę — po odmrożeniu rozmiaru nie wymaga niczego |
| `Presentation/Ui/ScrollWindow`, `SectionState`, `SplitState` | Stan między klatkami; pojemność dostają przy rysowaniu i ścinają się do niej |

## Zakres

### 1. Wykrycie zmiany

`SIGWINCH` w `TerminalService`, obok czterech obsługiwanych sygnałów i tym samym
wzorcem: uchwyt ustawia znacznik i nic więcej — pomiar w uchwycie sygnału
dotykałby STDIN w nieprzewidywalnym momencie klatki. Pętla czyta znacznik raz
na takt, więc seria sygnałów z przeciągania kosztuje jeden pomiar na klatkę.

### 2. Ponowny pomiar

Komórki: `stty size`, jak przy starcie. Piksele — najcięższe rozstrzygnięcie
kroku (patrz lista startowa, punkt 1): ponowne `ESC [ 14 t` w środku pętli
albo przeliczenie z **zapamiętanego rozmiaru komórki** (piksele z startu
podzielone przez komórki z startu — font przecież się nie zmienił).

### 3. Droga nowego rozmiaru do klatki

`FrameComposer` i `SixelFrameRenderer` pytają o rozmiar co klatkę już dziś,
więc jedyne pytanie brzmi: **jak odpowiedź przestaje być zamrożona**. To zmiana
w kontrakcie `ViewportPort` albo w cyklu życia `TerminalSizeService` —
rozstrzygnięcie nr 2, bo niezmienność usługi była dotąd jej gwarancją.

### 4. Sprzątnięcie po poprzednim rozmiarze

Rysowanie stoi na regule „kolejna klatka zamalowuje poprzednią w całości,
wystarczy odesłać kursor do rogu” (krok 08). Zmiana rozmiaru tę regułę łamie
w obie strony: po zmniejszeniu okna terminal łamie i przewija stare wiersze,
po powiększeniu nowa klatka nie sięga tam, gdzie leżą resztki starej.
Jednorazowe czyszczenie ekranu przy obsłudze zmiany jest kandydatem
oczywistym — i wyjątkiem od reguły „czyszczenie daje migotanie”, więc ma być
jawny i opisany.

### 5. Okno za małe

Dziś `max(20, columns)` w `FrameComposer` chroni wyłącznie rachunek formatowania
— klatka i tak rysuje się w prawdziwej szerokości okna. Po tym kroku okno można
zwęzić **w trakcie działania** do rozmiaru, w którym strefy przestają się
mieścić, więc trzeba rozstrzygnąć, co wtedy widać (punkt 4 listy startowej).

### 6. Pomiar

Klatka w oknie o stałym rozmiarze **nie ma prawa zdrożeć** — to najważniejsze
kryterium, ważniejsze od samej reakcji na zmianę: ścieżka klatki dostaje odczyt
znacznika i nic ponadto. Pierwsza klatka po zmianie płaci za przebudowę
płaszczyzny spodniej — jednorazowo, jak pierwsza klatka po starcie i po zmianie
motywu; `bin/render-bench` mierzy zimną klatkę już dziś, więc nowy scenariusz
nie jest przesądzony (lista startowa, punkt 5).

## Poza zakresem

- **Reakcja na zmianę fontu w trakcie działania** — zmienia rozmiar komórki
  w pikselach bez `SIGWINCH` w niektórych terminalach; rzadkość, którą
  rozwiązałoby dopiero cykliczne odpytywanie, a ono kosztuje w każdej klatce.
- **Zachowanie pozycji kursora względem treści** — kursor listy zostaje na tym
  samym **wpisie**, bo tak działa `keepVisible()`; żadnych dodatkowych starań
  o „ten sam wiersz ekranu”.
- **Przerysowanie w trakcie przeciągania częściej niż takt pętli** — 30 klatek
  na sekundę to więcej, niż terminal zdąży doręczyć sygnałów.
- **Okna nakładane pamiętające rozmiar** — `bounds()` liczy się co klatkę
  z aktualnych wierszy i kolumn; jeśli któreś okno tego nie robi, to jest błąd
  tamtego okna, nie zakres tego kroku.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Infrastructure/Terminal/TerminalService.php` | Infrastructure | `SIGWINCH` → znacznik zmiany rozmiaru, wzorem `shutdownRequested`. |
| `Infrastructure/Terminal/TerminalSizeService.php` | Infrastructure | Ponowny pomiar; koniec z `readonly` **albo** inna droga — rozstrzygnięcie nr 2. |
| `Application/Port/ViewportPort.php` | Application | Ewentualnie — zależnie od rozstrzygnięcia nr 2. |
| `Presentation/Cli/GameLoop.php` | Presentation | Odczyt znacznika między klatkami, obsługa pomiaru i czyszczenia. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Ewentualnie — okno za małe (rozstrzygnięcie nr 4). |
| `lang/pl.php`, `lang/en.php` | Napisy | Ewentualnie — komunikat okna za małego. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Rozmiar przestaje być stałą uruchomienia. |
| testy | Testy | Znacznik po sygnale, nowy rozmiar w następnej klatce, ścięcie przewinięcia po zmniejszeniu, klatka bez zmiany bez nowych kosztów. |

## Do rozstrzygnięcia na starcie kroku

1. **Skąd piksele po zmianie**: ponowne `ESC [ 14 t` w środku pętli (prawdziwa
   odpowiedź, ale zapytanie konkuruje o STDIN z klawiszami i ma 300 ms limitu,
   czyli do sześciu straconych klatek) czy przeliczenie z zapamiętanego rozmiaru
   komórki (zero I/O, ale kłamie po zmianie fontu w locie).
2. **Czy `TerminalSizeService` przestaje być niezmienny** — pole przestaje być
   `readonly`, czy rozmiar wraca do bycia mierzonym na żądanie z pamięcią
   podręczną unieważnianą znacznikiem; i czy `ViewportPort` ma o tym wiedzieć.
3. **Gdzie stoi znacznik zmiany** — w `TerminalService` obok `shutdownRequested`
   (sygnały w jednym miejscu) czy przy usłudze rozmiaru (znacznik przy danych,
   których dotyczy).
4. **Okno za małe**: rysować, co się zmieści (dzisiejsze zachowanie, tylko
   rozciągnięte na czas działania), czy plansza „okno za małe” z wymaganym
   minimum.
5. **Czy pomiar dostaje scenariusz w `bin/render-bench`** — zimna klatka mierzy
   przebudowę płaszczyzny spodniej już dziś; osobny scenariusz miałby sens
   tylko, jeśli pomiar „przed i po” pokaże koszty poza nią.

## Kryteria ukończenia

- Zmiana rozmiaru okna pod XTermem: następna klatka w nowym rozmiarze, bez
  strzępów poprzedniej — sprawdzone w prawdziwym terminalu (za zgodą
  użytkownika, wedle procedury z `CLAUDE.md`).
- Zmniejszenie okna poniżej długości listy: kursor pozostaje widoczny, okno
  przewijania ścięte, suwak mówi prawdę.
- Klatka w oknie o stałym rozmiarze kosztuje tyle, co przed krokiem —
  potwierdzone pomiarem `bin/render-bench`, nie wrażeniem.
- Tryb tekstowy (fallback) reaguje tak samo jak sixelowy.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
