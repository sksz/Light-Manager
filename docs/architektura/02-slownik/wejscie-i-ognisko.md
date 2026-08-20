# 2. Słownik domenowy — Wejście, ognisko i schowek

> Część rozdziału 2. Pojęcia i wstęp: [slownik.md](slownik.md).
> Spis rozdziałów: [docs/architecture.md](../../architecture.md).

Kto dostaje klawisz, kto o tym decyduje i którędy do aplikacji wchodzi treść, której nikt nie wpisał.

## Schowek: odczyt, który przychodzi później (od kroku 57)

Schowek systemowy jest **portem rdzenia**, a nie funkcją modułu, i to nie jest
wyjątek od reguły 15: dostęp do schowka ma ten, kto ma dostęp do terminala albo
do okna, a to jest tor wyjścia — dokładnie tak samo jak `ViewportPort`
i `FrameRendererPort`. Drugiej drogi do schowka nie ma czego rozważać, bo moduł
nie sięga do `Infrastructure` inaczej niż portem.

Trudność jest w **jednej z dwóch metod portu**. Zapis kończy się w tym samym
wywołaniu; odczyt nie ma jak, bo w torze terminalowym idzie protokołem `OSC 52`
z pytajnikiem (`\e]52;c;?\e\\`), a ten **nie zwraca niczego**: terminal odpowiada
sekwencją na wejściu aplikacji, klatkę albo dwie później, albo — gdy odczytu nie
obsługuje — **nie odpowiada nic i nie mówi o tym ani słowem**. Port nazywa więc
tę metodę `requestText()`, a nie `text()`: nazwa obiecująca zwrot wartości byłaby
kłamstwem w jednej z dwóch implementacji, a takiego kłamstwa nie widać, dopóki
ktoś nie uruchomi programu pod terminalem bez obsługi.

Wklejenie jest przez to **zdarzeniem, które przychodzi** (`ClipboardText`, trzecia
postać `InputEvent`), a nie wynikiem funkcji. Cztery reguły trzymają to razem:

- **Prosi ten, kto ma ognisko.** `Alt`+`v` sprawdza, czy na wierzchu stoi ktoś
  deklarujący `AcceptsPaste`; bez pola tekstowego pytanie **nie pada w ogóle**.
- **Rdzeń pamięta prośbę, a nie proszącego** (D101 nr 2). `LoopState` trzyma
  znacznik z terminem; odbiorcę pyta się na nowo przy doręczeniu. Wariant
  z referencją do okna albo ekranu odrzucono: byłby pierwszą taką referencją
  w stanie pętli i trzema miejscami, w których trzeba by ją kasować.
- **Pytanie ma termin** (ćwierć sekundy, osiem klatek). Bez niego pole czekałoby
  w nieskończoność na coś, co nigdy nie przyjdzie, a użytkownik zobaczyłby
  klawisz, po którym nic się nie stało. Pytanie o wygaśnięcie pada **raz na takt**
  w `GameLoop`, w fazie „aktualizuj stan”.
- **Rozbiór `OSC` należy do parsera wejścia** i jest jedynym miejscem, w którym
  `KeySequenceParser::parseAfterTimeout()` **nie rozstrzyga**: długość odpowiedzi
  zależy od zawartości schowka, więc sekwencja niepełna czeka między taktami.
  Wolno tak zrobić tylko wtedy, gdy w buforze stoi pełny znacznik `ESC ] 5 2 ;` —
  bez tego warunku samo naciśnięcie `Alt`+`]` zamurowałoby całe wejście.

W torze okienkowym nic z tego nie zachodzi (`glfwGetClipboardString()` oddaje
treść od razu), ale treść **i tam wchodzi przez kolejkę zdarzeń**, a nie przez
zwrot z wywołania — inaczej wołający musiałby wiedzieć, w którym torze działa,
żeby wiedzieć, gdzie szukać odpowiedzi.

**Trzy zobowiązania, na które zamieniła się cena tej drogi** (D95 nr 5). Żeby
schowek w terminalu istniał, `bin/run.sh` musiał zdjąć z listy
`disallowedWindowOps` **oba** wpisy — `SetSelection` i `GetSelection` — a drugi
z nich pozwala aplikacji działającej w terminalu **przeczytać cudzy schowek**:

1. **Czyta się wyłącznie na wyraźne polecenie użytkownika** — `Alt`+`v` albo
   komenda `core.clipboard.paste`. Nigdy przy starcie, nigdy w takcie, nigdy
   w rysowaniu klatki.
2. **Odczytana treść ma jedno miejsce docelowe: pole tekstowe z ogniskiem.** To
   zobowiązanie jest **kształtem kodu, nie obietnicą** — treść wychodzi z parsera
   i ma dokładnie jedną drogę dalej, przez `AcceptsPaste`.
3. **`bin/run.sh` tłumaczy, dlaczego lista się zmieniła** — oba zwężenia, z kroku
   34 i z kroku 57, wraz z powodami. Bez tego następny czytający uzna zmianę za
   przeoczenie i cofnie ją.

**Drugi zasób XTerma, który krok 57 musiał dołożyć, jest defektem starszym od
niego**: `metaSendsEscape: true`. Bez niego `Alt`+litera **nie dochodzi do
aplikacji w ogóle** — domyślnie rozstrzyga `eightBitInput` i `Alt`+`c` przychodzi
jako jeden znak drukowalny (`0x63|0x80`, czyli `ã`). Dotyczyło to od chwili
powstania także `Alt`+`z` (krok 29) i `Alt`+`u` (krok 44); nie wyszło wcześniej,
bo klatki pod XTermem nikt nie oglądał. Naprawa należy do **terminala, nie do
parsera**, i nie jest to wygoda: użytkownik wpisujący `ã` w nazwę pliku wysyła
dokładnie te same bajty, więc parser rozpoznający tę postać jako `Alt` odbierałby
możliwość wpisywania znaków diakrytycznych.

Kopiowanie ma za to **trzy źródła w ustalonej kolejności**, a rozstrzyga ją rdzeń,
bo tylko on widzi wszystkie trzy naraz: zaznaczenie klatki (krok 56) → treść, którą
ekran albo okno uzna za swoją (`CopiesContent`) → ścieżka wpisu pod kursorem
z `ModuleContext`. Skrajne dwa są rdzeniowe i darmowe; środkowe jest zdolnością,
bo nazw zaznaczonych wpisów kontekst nie niesie — ma o nich trzy liczby i ani
jednej nazwy. Pasek stanu mówi **co** skopiowano, a nie „skopiowano": trzy różne
treści po tym samym klawiszu są dla użytkownika nierozróżnialne, dopóki zdanie
jest jedno.

## Ognisko deklaruje się, a nie odkrywa (od kroku 40)

Powyższa tabela ma **konsekwencję, o którą potyka się każdy, kto pyta „co ma
teraz kursor”**: skoro komponent powstaje w `draw()` i ginie razem z klatką,
to **drzewa komponentów nie ma w żadnym momencie poza tą jedną chwilą, gdy
klatka się składa**. Nie da się więc znaleźć elementu z ogniskiem, chodząc po
drzewie — bo nie ma po czym chodzić. Prawdziwi właściciele ogniska nie są przy
tym komponentami: `BrowserPanes` trzyma numer panelu, `SettingsCursor` numer
pozycji, `SplitState` samą stronę podziału.

Stąd kontrakt: **pyta rdzeń, odpowiada ten, kto ognisko trzyma** — czyli ekran
albo okno nakładane, przez zdolność `DeclaresFocus`. `FocusHint` niesie klucz
etykiety miejsca („Podgląd”, „Panel lewy”) i jego wiązania klawiszy; odpowiedź
liczy się **co klatkę**, bo ognisko przenosi się klawiszem, a pasek stanu ma
pokazać nowe miejsce w tej samej klatce, w której ono się zmieniło.

Zobowiązania są dwa i obowiązują w obie strony:

- każde wiązanie oddane w `focus()` musi wystąpić także w `bindings()`, bo okno
  pomocy zostaje **pełnym** spisem — ekran składa więc `bindings()` z wiązań
  miejsca **plus** własnych, a powtórzenia odsiewa `StatusHints`;
- każde wiązanie pokazane w stopce musi być w tym miejscu naprawdę obsłużone
  przez `handle()`, i odwrotnie — klawisz działający tu i teraz, a przemilczany,
  jest błędem. Pilnuje tego jeden test dla wszystkich ekranów i wszystkich
  położeń ogniska (`tests/Functional/StatusHintsFlowTest.php`).

Pasek stanu wolno przy tym urosnąć do **dwóch wierszy** — i jest to jedyne
miejsce, w którym `HudLayout` dostaje odpowiedź zależną od **treści**, a nie od
rozmiaru okna (`$wideStatus`). Wiersz zabiera się liście, nigdy pasowi podglądu,
i tylko powyżej progu liczonego z tym pasem; w niskim oknie podpowiedzi ustępują
pozycjami. Rachunek nie kręci się w kółko, bo szerokość treści strefy jest ta
sama w obu wariantach oprawy (`HudLayout::contentColumns()`).

## Dwa wejścia do jednego rejestru komend (od kroku 32)

Menu kontekstowe (`F9`) jest **widokiem na `CommandRegistry`, a nie drugim
zbiorem czynności** — i to nie jest opis implementacji, tylko warunek, pod
którym w ogóle powstało. Zbiór działań, który trzeba uzgadniać przy każdej nowej
komendzie, jest dokładnie tym długiem, przed którym ostrzega reguła 15: dopisanie
modułu ma kosztować **jedną** zmianę w rdzeniu.

Ponad okno komend menu wnosi dwie rzeczy i ani jednej więcej: **wybór bez
pisania** oraz **zawężenie do zaznaczenia**. To drugie wymaga, żeby komenda umiała
powiedzieć, czego dotyczy — stąd `AppliesToSelection`, doklejana obok kontraktu
jak `SuggestsArguments`, a nie dopisana do `CommandInterface`. Różnica jest
praktyczna: siedem komend rdzenia zostaje nietkniętych, bo `core.theme` nie jest
czynnością na pliku i nie ma powodu, żeby o tym mówiła.

Granica biegnie po **zaznaczeniu**, nie po module: `browser.hidden` i
`browser.tree` są w rejestrze, ale do menu nie wchodzą, bo dotyczą panelu, a nie
wpisu pod kursorem. Nazwa dla czynności, którą aplikacja umiała wyłącznie pod
klawiszem, jest przy tym osobną wartością — komenda i klawisz mają jednak
prowadzić do **jednego** miejsca w kodzie (`HiddenEntries`), bo dwie
implementacje tej samej czynności rozjeżdżają się przy pierwszej poprawce.

Zaznaczenie przychodzi do okna **przy otwarciu**, migawką z `LoopState::context()`:
okno zużywa klawisze, więc dopóki stoi, zaznaczenie nie ma jak się zmienić.
Prostokąt staje pośrodku, jak okno potwierdzenia — rdzeń nie wie, gdzie moduł
narysował kursor (lista czy drzewo, który z dwóch paneli), a pytanie ekranu
o współrzędne otworzyłoby `ScreenInterface` na współrzędne, których żaden kontrakt
nie zna. Menu bez ani jednej pozycji **nie otwiera się wcale**: mówi zdaniem
w pasku stanu, zamiast prosić o zamknięcie pustego prostokąta.
