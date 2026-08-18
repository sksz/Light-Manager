# Krok 57 — Schowek systemowy: trzy tory, dwie drogi, jedno miejsce

> **Skąd ten krok.** Powstał 2026-08-16 z podziału zarysu Fazy XIX
> ([00-decyzje.md](../00-decyzje.md), D95 nr 9) i bierze z niego trzeci mechanizm.
> Zamyka fazę i **spłaca dług kroku 56**: zaznaczenie, które tamten krok
> dowiózł bez odbiorcy, dostaje tutaj to, po co powstało.

## Status

**Ukończony** 2026-08-17. Rozstrzygnięcia startowe:
[00-decyzje.md](../00-decyzje.md), **D101** (cztery pytania ze startu kroku) oraz
D95 (nr 5, 6, 8 i 9 — zakres).

**Trzy zdania tego planu obaliło rozpoznanie w kodzie** i poprawki stoją niżej,
przy punktach, których dotyczą:

1. Treść do skopiowania **nie pochodzi w całości od ekranu** — ścieżkę wpisu pod
   kursorem oddaje `ModuleContext::selectionPath()` z kroku 49, generycznie
   i dla ekranu zdalnego też (D101 nr 1, punkt 5 zakresu).
2. `Alt`+`v` **nie jest klawiszem rdzenia** — reguła 11p zabrania obiecywać
   w stopce klawisz, który w danym miejscu nic nie robi (D101 nr 3, punkt 4
   zakresu).
3. Rozbiór OSC **nie mieści się w jednym oknie dosłania** — sekwencja niepełna
   musi umieć czekać między taktami, z progiem długości i terminem (D101,
   rozpoznanie; punkt 3 zakresu).

Krok spłaca ponadto **połowę długu z D100**: treść okna nakładanego da się
skopiować, ale nie da się jej zaznaczyć myszą. Druga połowa ma odtąd właściciela
— [krok 77](../77-zaznaczanie-w-oknie.md), zarys (D101 nr 4).

## Cel

Treść zaznaczona w aplikacji trafia do **schowka systemowego**, a treść ze
schowka wraca do pola tekstowego — w każdym z trzech torów i także wtedy, gdy
aplikacja działa po drugiej stronie połączenia SSH.

Miarą powodzenia jest zdanie: **`Alt`+`c` kładzie zaznaczoną treść w schowku
środowiska graficznego, a `Alt`+`v` wstawia zawartość schowka do pola nazwy
pliku — w torze sixelowym, tekstowym i okienkowym.**

## Trzy drogi, z których żadna nie jest wspólna

| Tor | Zapis | Odczyt |
|---|---|---|
| Sixel i tekstowy (terminal) | OSC 52 — `\e]52;c;<base64>\e\\` | OSC 52 z pytajnikiem; odpowiedź **wraca na wejście**, nie z wywołania |
| Okienkowy (GLFW) | `glfwSetClipboardString()` | `glfwGetClipboardString()` |

Tor okienkowy jest przez to **najłatwiejszy z trzech**, odwrotnie niż zwykle:
oddaje treść synchronicznie i nie potrzebuje ani protokołu, ani zgody terminala.

Droga trzecia — `xclip`/`xsel` procesem potomnym — **została odrzucona**
(D95 nr 5): działa wyłącznie przy serwerze okien, czyli nie przez SSH i nie
w konsoli, a to jest dokładnie ta sytuacja, w której menedżer plików
w terminalu bywa najczęściej. Narzędzia są na maszynie (`xclip`, `xsel`;
`wl-copy` nie ma) i to nie wystarczyło.

## Zastrzeżenie startowe — rozstrzygnięte, i jest to rozstrzygnięcie z ceną

**Projekt sam sobie zablokował schowek.** `bin/run.sh` wyłącza `GetSelection`
i `SetSelection` na liście `disallowedWindowOps` — czyli dokładnie te operacje,
którymi OSC 52 czyta i zapisuje schowek. Lista powstała w kroku 34 jako
świadome zawężenie domyślnej: dopuszczono wyłącznie raport rozmiaru okna
w pikselach (`14`), a resztę zablokowano.

**Rozstrzygnięcie: wracają oba** (D95 nr 5). Odblokowanie `SetSelection`
pozwala aplikacji podmienić zawartość schowka użytkownika; odblokowanie
`GetSelection` — **przeczytać go**, i to drugie jest poważniejsze. Wiele
terminali wyłącza odczyt domyślnie właśnie dlatego, a odrzucone warianty
(sam zapis; hybryda z `xclip` do odczytu) tej zdolności aplikacji nie dawały.

Z ceny wynikają trzy zobowiązania, które są częścią zakresu, a nie komentarzem:

1. **Aplikacja czyta schowek wyłącznie na wyraźne polecenie** — `Alt`+`v` albo
   komenda. Nigdy przy starcie, nigdy w takcie, nigdy „na wszelki wypadek".
2. **Odczytana treść ma jedno miejsce docelowe: pole tekstowe z ogniskiem.**
   Nie trafia do dziennika, do pliku konfiguracyjnego, do procesu potomnego ani
   do komunikatu w pasku stanu.
3. **`bin/run.sh` mówi, dlaczego lista się zmieniła.** Komentarz nad nią
   tłumaczy dziś zawężenie z kroku 34; ma odtąd tłumaczyć także poszerzenie —
   inaczej następny czytający uzna zmianę za przeoczenie i cofnie ją.

## Stan zastany (sprawdzony w kodzie 2026-08-16)

| Element | Stan |
|---|---|
| `bin/run.sh` | `disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop`. |
| `TerminalService` | `write()` do surowego zapisu; `pushBackBytes()` do oddawania bajtów cudzej odpowiedzi — mechanizm powstał w kroku 07 dokładnie dla takich rozmów z terminalem. |
| `DeviceAttributesParser`, `WindowSizeParser` | Dwa precedensy pytania terminala i rozbioru odpowiedzi (kroki 07 i 33). Oba pytają **przy starcie**, synchronicznie. |
| `KeySequenceParser` | Rozbiera CSI; sekwencji OSC (`ESC ] … BEL/ST`) **nie zna**. |
| PHP-GLFW | `glfwSetClipboardString`, `glfwGetClipboardString` — sprawdzone `function_exists()`. |
| Tryb surowy | `isig` i `iexten` **włączone** (sprawdzone w pty: `intr = ^C`, `lnext = ^V`) — powód, dla którego klawiszem nie jest `Ctrl`+`Alt`+litera. |
| `Ctrl`+litera | Zajęte w całości przez skróty modułów (krok 20): `b`, `s`, `o`, `d`, `a`, `k`. |
| `Alt`+litera | Dwa użycia: `Alt`+`z` (zawijanie, krok 29), `Alt`+`u` (cofnięcie, krok 44). |
| `Presentation/Ui/Component/TextInput` | Pole tekstowe z karetką i trybem maskowanym (kroki 19 i 48) — pierwszy odbiorca wklejania. |
| `Module/Browser/Domain/…/MarkedEntries` | Zaznaczone wpisy (krok 43) — drugie źródło treści do skopiowania. |

## Zależności

- **Krok 56** — zaznaczenie jako główne źródło treści; ten krok spłaca jego dług
  wobec reguły 13.
- **Krok 55** — słownik wejścia: odpowiedź terminala ze schowkiem wchodzi jako
  **trzecia postać `InputEvent`**, więc nadtyp z tamtego kroku dostaje tu
  drugiego użytkownika i to jest jego sprawdzian.
- **Krok 06** — tryb surowy i wejście, na które wraca odpowiedź terminala.
- **Krok 07** — wzorzec rozmowy z terminalem: pytanie, rozbiór odpowiedzi
  i `pushBackBytes()` dla bajtów, które okazały się cudze.
- **Krok 19** — `TextInput` jako pierwszy odbiorca wklejania; `Alt`+litera jako
  droga, którą krok 29 już raz przeszedł.
- **Krok 33** — `WindowSizeParser` jako drugi precedens odpowiedzi terminala.
- **Krok 34** — lista `disallowedWindowOps` i powód, dla którego powstała.
- **Krok 43** — nazwy zaznaczonych wpisów jako drugie źródło treści.
- **Kroki 14 i 15** — napisy i (jeśli zajdzie potrzeba) pozycja ustawień.

## Model i wysiłek

**Opus / high.** Warunek `Fable` nie zachodzi ani w jednym punkcie: słownik
wejścia rośnie o **postać zdarzenia**, a nie o modyfikator ani o klawisz
(`Alt`+litera istnieje od kroku 29), słownik prymitywów zostaje nietknięty,
a trzej tłumacze nie dostają ani jednej linii. Trzy tory dotyka **port
z dwiema implementacjami**, a nie zmiana w każdym z nich.

Ryzyko kroku nie leży w rozmiarze, tylko w **asynchroniczności odczytu** (punkt
3 zakresu) i w tym, że terminal ma prawo nie odpowiedzieć.

## Zakres

### 1. `ClipboardPort` w rdzeniu

`Application/Port/ClipboardPort` — dwie metody: położenie tekstu i **poproszenie**
o tekst (nie: odczytanie go, patrz punkt 3). Dwie implementacje:
`Infrastructure/Terminal/TerminalClipboardService` (OSC 52, wspólna dla toru
sixelowego i tekstowego) oraz `Infrastructure/Glfw/GlfwClipboardService`.

Port jest **rdzeniowy** i nie jest to wyjątek od reguły 15: schowek to zdolność
toru wyjścia, jak `ViewportPort` i `FrameRendererPort` — moduł nie ma dostępu ani
do terminala, ani do okna inaczej niż portem, więc nie ma tu drugiej drogi do
rozważenia.

### 2. Zapis: OSC 52

`\e]52;c;<base64>\e\\` — zakończenie łańcucha `ST` (`ESC \`), nie `BEL`:
przyjmują je oba, ale `ST` jest postacią z normy.

Dwie rzeczy do sprawdzenia w kroku, bo obie potrafią uciąć treść po cichu:
**limit długości łańcucha OSC** (XTerm ma własny próg i przekroczenie kończy się
milczącym obcięciem, nie błędem) oraz **zachowanie przy treści wielowierszowej**.
Sprawdzenie należy do kroku, a wynik — do dziennika: kopiowanie, które oddaje
połowę zawartości bez ani jednego komunikatu, jest gorsze od kopiowania, które
odmawia.

### 3. Odczyt: pytanie, na które odpowiedź przychodzi później

**To jest jedyna prawdziwa trudność tego kroku.** OSC 52 z pytajnikiem
(`\e]52;c;?\e\\`) nie zwraca niczego z wywołania — terminal odpowiada
**sekwencją na wejściu aplikacji**, klatkę albo dwie później. Wklejenie nie może
więc być wynikiem funkcji; jest **zdarzeniem, które przychodzi**.

Rozwiązanie idzie po nadtypie z kroku 55: `ClipboardText implements InputEvent`
— trzecia postać zdarzenia, obok klawisza i wskaźnika. Wpada do tej samej
kolejki, wędruje tą samą drogą i trafia do tego, kto o nią prosił.

Trzy reguły, bez których to się rozjedzie:

- **Prosi ten, kto ma ognisko, i tylko on odbiera.** `LoopState` pamięta, kto
  poprosił; odpowiedź przychodząca do zamkniętego już pola jest **porzucana**,
  a nie wstawiana gdziekolwiek indziej.
- **Pytanie ma termin.** Terminal, który odczytu nie obsługuje, nie odpowiada
  **nic** — więc po 250 ms prośba wygasa i pasek stanu mówi, że schowek jest
  nieosiągalny. Bez terminu pole czekałoby w nieskończoność na coś, co nigdy nie
  przyjdzie.
- **Rozbiór OSC należy do parsera wejścia.** `KeySequenceParser` zna dziś CSI
  i SS3; dostaje trzecią gałąź — `ESC ] 52 ; …` zakończone `BEL` albo `ST`
  (obie postacie, bo terminale wysyłają obie). Sekwencja niepełna czeka
  na resztę tą samą drogą, którą czeka strzałka.

W torze okienkowym nic z tego nie zachodzi: `glfwGetClipboardString()` oddaje
treść od razu, a `ClipboardText` powstaje w tym samym takcie. Różnica jest
niewidoczna dla wołającego — i to jest cały sens portu.

### 4. Klawisze: `Alt`+`c` i `Alt`+`v`

Trzeci i czwarty użytkownik modyfikatora `Alt`, obok `Alt`+`z` i `Alt`+`u`.
Reguła 11j zostaje nietknięta: `Alt` przy literach, `Shift` przy nazwach,
kombinacji nie ma.

**`Ctrl`+`c` odpada dwukrotnie i oba powody są w kodzie**: `Ctrl`+litera należy
w całości do skrótów modułów (krok 20), a w trybie surowym `isig` zostaje
włączone, więc `^C` staje się SIGINT-em, **zanim aplikacja przeczyta cokolwiek**
— czyli zamyka program. `Ctrl`+`Alt`+`c` ma tę samą wadę (`ESC` + `0x03`),
a `Ctrl`+`Alt`+`v` dokłada drugą: `^V` to `lnext`, który przy włączonym `iexten`
połyka następny bajt. Oba sprawdzone w prawdziwym pty; wariant „tryb surowy
rośnie o `-isig -iexten`" był rozważany i **odrzucony** (D95 nr 8), bo zmieniałby
gwarancję z kroku 06 dla wygody jednego skrótu.

### 5. Co się kopiuje

`Alt`+`c` bierze — w tej kolejności — **zaznaczenie z kroku 56**, a gdy go nie
ma: **nazwy zaznaczonych wpisów** (krok 43), a gdy i tych nie ma: **ścieżkę
wpisu pod kursorem**. Kolejność rozstrzyga rdzeń, ale treść podaje ekran, bo to
on wie, co u niego znaczy „to, na czym stoję" — port dostaje gotowy napis
i o pochodzeniu nie wie.

Pasek stanu mówi, **co** skopiowano („skopiowano 3 nazwy", „skopiowano
zaznaczenie: 5 wierszy"), a nie „skopiowano" — bo trzy różne źródła bez nazwy
są nierozróżnialne dla użytkownika, który nacisnął ten sam klawisz.

### 6. Dwie komendy i pozycje w menu

`core.clipboard.copy` i `core.clipboard.paste` w rejestrze komend — czyli
i w oknie `F12`, i w menu `F9` (od kroku 32 menu jest widokiem na rejestr, więc
pozycje przychodzą za darmo). Zdolność `RequiresEnvironment` z kroku 48 mówi,
kiedy komendy nie ma czym wykonać.

### 7. `bin/run.sh`

`GetSelection` i `SetSelection` wypadają z `disallowedWindowOps`, a komentarz
nad listą tłumaczy **oba** rozstrzygnięcia: zawężenie z kroku 34 i poszerzenie
z tego kroku wraz z powodem (bez odblokowania obu operacji schowek w terminalu
nie istnieje).

### 8. Napisy, pomiar, przebiegi

- **Napisy:** trzy zdania o skopiowaniu (wedle źródła), zdanie o nieosiągalnym
  schowku, zdanie o treści za długiej, opisy dwóch klawiszy i dwóch komend.
- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 56 — parser wejścia
  dostaje trzecią gałąź, czyli kod na ścieżce każdego bajtu z terminala. Wyglądu
  klatki krok nie zmienia, więc porównania zrzutów nie potrzebuje.
- **Narzędzie, a nie zastępnik:** odpowiedź terminala na pytanie o schowek ogląda
  się `bin/terminal-probe` (`make probe-xterm`) — tą samą drogą, którą krok 07
  oglądał odpowiedź DA1 (reguła 18). To także jedyny sposób sprawdzenia
  **przed pisaniem kodu**, czy ten terminal w ogóle odpowiada po odblokowaniu
  `GetSelection`.
- **Przebiegi:** rozbiór odpowiedzi OSC 52 (pełnej, niepełnej, z `BEL` i z `ST`),
  wygaśnięcie prośby po terminie, odpowiedź przychodząca do zamkniętego pola,
  kopiowanie z każdego z trzech źródeł, treść wielowierszowa, treść pusta,
  wklejenie do pola maskowanego (schowek **nie** ma prawa trafić do dziennika),
  tor okienkowy przez atrapę portu.

### 9. Dokumentacja

`docs/architecture.md` — schowek jako port rdzenia, asynchroniczny odczyt
i trzecia postać `InputEvent`, wraz z trzema zobowiązaniami z zastrzeżenia.
`SKILL.md` — reguła o schowku: **czyta się wyłącznie na polecenie użytkownika,
a odczytana treść ma jedno miejsce docelowe**; obok niej zapis, że lista
`disallowedWindowOps` została poszerzona świadomie i dlaczego. `README.md` —
klawisze i zdanie o tym, co robić, gdy terminal odczytu nie obsługuje.

## Poza zakresem

- **Schowek jako historia** — schowek systemowy ma jedno miejsce.
- **Zaznaczenie pierwotne X11 (PRIMARY) i wklejanie środkowym przyciskiem** —
  osobne pojęcie, obecne tylko w jednym z trzech torów.
- **`xclip`/`xsel` jako droga zapasowa** — odrzucone rozstrzygnięciem nr 5;
  jeśli wróci, to jako osobna decyzja z własnym powodem.
- **Wayland** (`wl-copy`) — nie ma go na maszynie, a sesja jest X11.
- **Wklejanie do listy plików jako operacja na plikach** („skopiuj plik przez
  schowek") — to jest czynność na plikach, nie schowek tekstowy; należałaby do
  Fazy XIV.
- **Schowek jako źródło dla pól maskowanych bez ostrzeżenia** — wklejenie do
  pola z sekretem działa, ale treść nie trafia do żadnego zapisu.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/ClipboardPort.php` | Application | Nowe — położenie tekstu i prośba o tekst. |
| `Application/Dto/ClipboardText.php` | Application | Nowe — trzecia postać `InputEvent`. |
| `Infrastructure/Terminal/TerminalClipboardService.php` | Infrastructure | Nowe — OSC 52: zapis i pytanie. |
| `Infrastructure/Terminal/KeySequenceParser.php` | Infrastructure | Trzecia gałąź: `ESC ] 52 ; …` zakończone `BEL` albo `ST`. |
| `Infrastructure/Glfw/GlfwClipboardService.php` | Infrastructure | Nowe — dwie funkcje GLFW za tym samym portem. |
| `Presentation/Cli/LoopState.php` | Presentation | Kto poprosił o schowek i kiedy prośba wygasa. |
| `Presentation/Cli/InputHandler.php` | Presentation | `Alt`+`c`, `Alt`+`v`, doręczenie `ClipboardText` proszącemu. |
| `Presentation/Ui/Component/TextInput.php` | Presentation | Przyjęcie wklejonej treści w miejscu karetki. |
| `Presentation/Cli/Command/…` | Presentation | Dwie komendy rdzenia. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | Treść do skopiowania: zaznaczone wpisy albo wpis pod kursorem. |
| `bin/run.sh` | Narzędzia | `GetSelection` i `SetSelection` poza listą; komentarz o obu rozstrzygnięciach. |
| `lang/pl.php`, `lang/en.php` | Napisy | Punkt 8. |
| `tests/Unit/…`, `tests/Functional/ClipboardFlowTest.php` | Testy | Punkt 8; żaden test nie dotyka schowka osoby uruchamiającej. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Punkt 9. |

## Kryteria ukończenia

- **`Alt`+`c` kładzie treść w schowku środowiska graficznego** — sprawdzone
  ręcznie pod XTermem (reguła 17) i wklejeniem w innej aplikacji, w torze
  sixelowym, tekstowym i okienkowym.
- **`Alt`+`v` wstawia zawartość schowka do pola tekstowego** — w każdym z trzech
  torów; w terminalu odpowiedź przychodzi zdarzeniem, a nie z wywołania.
- **Terminal, który odczytu nie obsługuje, kończy się zdaniem, a nie
  zawieszeniem** — prośba wygasa po terminie.
- **Odpowiedź przychodząca do zamkniętego pola jest porzucana** — treść schowka
  nie trafia nigdzie indziej.
- **Treść za długa dla OSC 52 kończy się odmową ze zdaniem**, nigdy cichym
  obcięciem; próg zmierzony i zapisany w dzienniku.
- **Trzy źródła kopiowania mają trzy różne zdania** w pasku stanu.
- **`bin/run.sh` tłumaczy, dlaczego lista się zmieniła.**
- Testy nie dotykają schowka osoby uruchamiającej je.
- `bin/render-bench --loop` „przed i po" bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

### 2026-08-17 — rozpoznanie przed pierwszą linią kodu

Sprawdzone w kodzie, na maszynie i w `man xterm`. **Trzy zdania tego planu
okazały się nieprawdziwe**, a nie tylko nieprecyzyjne — wszystkie trzy stoją
wyżej, w „Statusie”, i wszystkie trzy rozstrzygnęło D101.

- **`ESC ]` było rozbierane jako `Alt`+`]`.** `]` to bajt drukowalny (`0x5D`),
  więc gałąź `parseAltCharacter()` z kroku 29 łapała początek łańcucha OSC
  pierwsza. Nowa gałąź stanęła **przed** nią.
- **Sekwencja niepełna miała dokładnie jedno okno na dosłanie reszty.**
  `readEvent()` czytał 1024 B, pytał parsera, a przy odmowie czekał 20 ms,
  dobierał **jeszcze raz** i wołał `parseAfterTimeout()`, po którym odpowiedź
  musiała już być. Dla strzałki to nadmiar; dla odpowiedzi o schowku niosącej
  kilkadziesiąt kilobajtów — za mało, a nadmiar rozsypałby się na fałszywe
  naciśnięcia.
- **Dwa z trzech źródeł kopiowania rdzeń już umiał przeczytać.**
  `ModuleContext::selectionPath()` (krok 49) oddaje ścieżkę wpisu **generycznie**,
  także dla ekranu zdalnego; `browser.marked` (krok 53) oddaje **nazwy**
  zaznaczonych wpisów i został napisany dokładnie dla takiego odbiorcy. Zdanie
  „treść podaje ekran” było prawdziwe tylko dla jednego źródła z trzech.
- **Reguła 11p zabraniała pokazać `Alt`+`v` w stopce globalnie** — „klawisz
  działający w danym miejscu musi tam stać w spisie, **i odwrotnie**”, a
  wklejanie bez pola tekstowego nie robi nic.
- **Wzorce zrzutów i złote klatki okazały się bezpieczne**, ale nie z powodu
  podanego w planie: `ScenarioFactory` składa **własne** `StatusHints` z pozycji
  bez wiązań, więc nowy klawisz rdzenia nie zmienia ani jednego wzorca. Zdanie
  „wyglądu klatki krok nie zmienia” zostaje prawdziwe — stopka jednak rośnie,
  tylko pomiar jej nie widzi.
- **XTerm 390 trzyma `GetSelection` i `SetSelection` na liście domyślnej**
  (`man xterm`: „i.e., no operations are allowed”), więc z listy musiały wypaść
  **oba**.

### 2026-08-17 — wykonanie

**Rdzeń urósł o pięć rzeczy, nie o jedną zapowiadaną przez D95.** Port schowka
był jedną z nich; cztery pozostałe są ceną asynchroniczności odczytu
i rozstrzygnięć D101 nr 1–3: trzecia postać zdarzenia (`ClipboardText`), dwie
zdolności (`CopiesContent`, `AcceptsPaste`), prośba z terminem w `LoopState`
i gałąź OSC w parserze wejścia.

**Trzy rzeczy wyszły inaczej, niż zapowiadał zakres, i każda ma powód w kodzie:**

1. **Klawisze schowka nie schodzą do okna nakładanego ani do ekranu — stoją
   przed nimi.** Nie jest to porządek, tylko konieczność: klawisz przepuszczony
   przez okno trafia do klawiszy globalnych, a te **zamykają okno**
   (`InputHandler::toOverlay()`, krok 19). `Alt`+`v` w polu filtra zamykałby przez
   to filtr, do którego miał wkleić wzorzec.
2. **`RequiresEnvironment` z kroku 48 nie miało tu zastosowania** (punkt 6
   zakresu obiecywał inaczej): jest to zdolność **modułu**, a nie komendy, i rdzeń
   nie ma dla komend niczego podobnego. Zamiast tego obie komendy istnieją zawsze,
   a odmowa jest zdaniem — schowek istnieje we wszystkich trzech torach, więc nie
   ma czego wyłączać.
3. **`bin/terminal-probe` urósł o dwa klawisze**, bo bez nich zapowiedziany
   w planie pomiar progu nie miałby narzędzia (reguła 18): `c` pyta terminal
   o schowek, `p` kładzie w nim próbkę o **podwajającej się** długości. Próbka
   rosnąca, a nie jedna, bo terminal swojego limitu nie podaje i nie da się go
   zapytać.

**Dług D100 spłacony do połowy**, zgodnie z D101 nr 4: `CopiesContent` deklarują
`ConfirmOverlay` (treść pytania — odcisk `SHA256:…` z kroku 48 nie widać nigdzie
indziej) i `CommandOverlay` (odpowiedź kwerendy, wierszami rozdzielonymi
tabulatorem). Zaznaczania myszą w oknie to nie dodaje — druga połowa ma
właściciela i termin: [krok 77](../77-zaznaczanie-w-oknie.md).

**Granica pomiaru, ta sama co w kroku 55 i warta powtórzenia**: oś `--loop`
**nie mierzy tego, co ten krok dokłada do wejścia**. `LoopBenchmarkRunner` woła
`$this->screen->handle($key)` na `KeyPress`ie zbudowanym wprost, więc ani
`KeySequenceParser`, ani `InputHandler` nie stoją na jej ścieżce — trzecia gałąź
parsera i gałąź klawiszy schowka są dla tej osi niewidoczne. Liczba z niej mówi
zatem to samo, co w kroku 55: **że reszta taktu się nie zmieniła**. Rachunek
statyczny na tę gałąź jest przy tym jednym porównaniem napisu na sekwencję
escape (czwarte ramię `match`a na `$buffer[1]`), a `awaitSequenceTail()` wykonuje
się **wyłącznie** wtedy, gdy parser odmówił — czyli w tych samych przypadkach,
w których do tego kroku płaciło się jedno oczekiwanie.

**Testy:** `KeySequenceParserTest` dostał dziesięć przebiegów na rozbiór
odpowiedzi (`ST` i `BEL`, pole wyboru w trzech postaciach, treść wielowierszowa,
schowek pusty, ładunek nierozczytany, sekwencja niepełna czekająca **po** upływie
okna, `Alt`+`]` nadal rozstrzygane po terminie, sekwencja w środku bufora);
`ClipboardFlowTest` — siedemnaście przebiegów użytkownika; `TerminalClipboardServiceTest`
— trzy odmowy, czyli wszystko, co da się sprawdzić **bez** dotykania cudzego
schowka. Zobowiązanie „testy nie dotykają schowka osoby uruchamiającej je” jest
spełnione konstrukcyjnie: drogą jest `tests/Support/StubClipboard`, wstawiany do
`ScreenFixture` jak każda inna atrapa.

`make qa` zielone: 2289 testów, 7455 asercji; PHPStan `max` bez błędów.

### 2026-08-17 — pomiar

**Oś `--loop`, „przed i po" wobec wzorca po kroku 56: −2,2% / −2,5%, bez
regresji.** Maszyna zwolniona (obciążenie 0,04 na rdzeń wobec 0,06 we wzorcu),
wzorzec zapisany jako `docs/pomiary/2026-08-17-po-kroku-57-loop.json`.

**Pierwszy przebieg trzeba było odrzucić i powód jest wart zapisania.** Przy
domyślnych 15 przebiegach oś pokazała `+20,2% ▲` w scenariuszu „klatka
z kompletem prac w tle" — przy wartości bezwzględnej **0,1 ms** i rozrzucie
0,1–0,2 ms, czyli 2×. Regresja była w całości zaokrągleniem: 120 przebiegów
w tej samej konfiguracji dało `−2,5%`. Wniosek na przyszłość: **oś `--loop` mierzy
dziś wartości na granicy własnej rozdzielczości wydruku**, więc jednocyfrowa
zmiana procentowa nic tam nie znaczy, dopóki rozrzut nie zejdzie poniżej progu.

### 2026-08-17 — sprawdzenie pod XTermem i defekt starszy od tego kroku

Narzędziem był `bin/terminal-probe` (`make probe-xterm`) i sama aplikacja
(`make run-xterm`, `make run-window`), wysterowane `xdotool`em; treść schowka
czytana z drugiej strony `xclip -o`, czyli **naprawdę „wklejeniem w innej
aplikacji"**. Schowek osoby uruchamiającej został zapisany przed pracą
i przywrócony po niej.

**Odczyt działa.** Po zdjęciu `GetSelection` z listy XTerm 390 odpowiada na
`OSC 52` z pytajnikiem, a trzecia gałąź parsera oddaje treść co do bajtu:
podglądowi wróciło `ClipboardText bajtów: 21 treść: LM-SCHOWEK-PROBA-2026`.

**Próg obcięcia zmierzony — i leży czterokrotnie wyżej niż nasza stała.** Próbka
podwajana od 1 kB dochodzi w całości do **256 kB**; przy **512 kB schowek zostaje
z poprzednią zawartością**, bez błędu, bez sygnału, bez śladu — dokładnie ta
cisza, przed którą broni własny próg. Stała **zostaje na 64 kB** i jest to wybór
zapisany w jej docbloku: zmierzony pułap należy do jednego terminala na jednej
maszynie, a multiplekser w środku drogi bywa znacznie skromniejszy. Odmowa przy
131 072 B sprawdzona pod XTermem: `odmowa: clipboard.problem.too-long`, schowek
nietknięty.

**Najdroższe znalezisko nie dotyczy schowka i jest starsze od tego kroku:
`Alt`+litera nie działała pod XTermem ani razu.** Domyślnie `metaSendsEscape`
jest `false`, a wtedy rozstrzyga `eightBitInput` i `Alt`+`c` przychodzi jako
**jeden znak drukowalny** — zmierzone bajty `c3 a3`, czyli `ã` (`0x63|0x80`);
`Alt`+`v` → `c3 b6` (`ö`), `Alt`+`z` → `c3 ba` (`ú`). Parser czyta to jako zwykłą
literę i **nie ma jak czytać inaczej**: użytkownik wpisujący `ã` w nazwę pliku
wysyła dokładnie te same bajty. Dotyczyło to `Alt`+`z` z kroku 29 i `Alt`+`u`
z kroku 44 **od chwili ich powstania**; nie wyszło wcześniej, bo klatki pod
XTermem nikt nie oglądał — zdanie stojące jako granica w dziennikach kroków 55,
56 i 46. Naprawa: zasób `metaSendsEscape: true` w `bin/run.sh`
i `bin/run-terminal-probe.sh`, wraz z objaśnieniem; po nim bajty to `76`/`7a`
ze znacznikiem `Alt`. Wariant „parser rozpoznaje postać ośmiobitową" został
**odrzucony** — odebrałby możliwość wpisywania znaków diakrytycznych.

Podgląd wejścia wypisuje przy tym odtąd **modyfikatory także przy klawiszu**. Bez
nich `c` i `Alt`+`c` dawały identyczny wiersz (`KeyPress::alt()` niesie w `raw`
samą literę), czyli narzędzie do odpowiadania na pytanie „co terminal naprawdę
przysłał" nie umiało odpowiedzieć na to jedno pytanie, które okazało się
najważniejsze.

**Kryteria ukończenia — co sprawdzone i czym:**

| Kryterium | Jak sprawdzone |
|---|---|
| `Alt`+`c` kładzie treść w schowku środowiska | `xclip -o` po naciśnięciu w aplikacji: tor **sixelowy** (`make run-xterm`) i **okienkowy** (`make run-window`) — oba oddały `/home/sksz/Projects/lm/.php-cs-fixer.dist.php` |
| Trzy źródła, trzy zdania | ścieżka wpisu (`Skopiowano ścieżkę: …`), dwa zaznaczone wpisy (`Skopiowano ścieżki 2 zaznaczonych wpisów.`), zaznaczenie myszą (prostokąt przez cztery wiersze oddał `claude/ git/ ssets/ in/` — **prostokątne, nie przepływowe**, widać po uciętych nazwach) |
| `Alt`+`v` wstawia treść do pola | filtr w torze sixelowym (`szukaj: compo`) i okienkowym (`szukaj: wklejka-z-okna`); w terminalu odpowiedź przyszła zdarzeniem, nie z wywołania |
| Brak pola → zdanie, nie odczyt | `! Nie ma gdzie wkleić — schowek trafia do pola tekstowego z ogniskiem.` i **zero pytań** do terminala (pilnuje tego również `ClipboardFlowTest`) |
| Treść za długa → odmowa | `odmowa: clipboard.problem.too-long` przy 131 072 B, schowek nietknięty |
| Stopka mówi prawdę w obie strony | okno 210 kolumn: `Alt+V wklej ze schowka` przy **polu**, `Alt+C skopiuj do schowka` wśród **globalnych** |
| `bin/run.sh` tłumaczy zmianę listy | komentarz opisuje oba zwężenia (kroki 34 i 57) oraz nowy zasób `metaSendsEscape` |
| Testy nie dotykają cudzego schowka | konstrukcyjnie — `tests/Support/StubClipboard`; jedyny test prawdziwej usługi sprawdza wyłącznie gałęzie **przed** zapisem |
| `--loop` bez regresji | −2,2% / −2,5% (120 przebiegów) |
| `make qa` zielone | 2289 testów, 7455 asercji, PHPStan `max` |

**Czego nie sprawdzono ręcznie i dlaczego:** toru **tekstowego** osobno. Schowek
wybiera się w `Bootstrap::clipboard()` wyłącznie po fladze `--window`, więc oba
tory terminalowe dostają **tę samą** `TerminalClipboardService` — nie ma tam ani
jednej gałęzi zależnej od renderera, a odpowiedź terminala rozbiera ten sam
parser. Sprawdzenie byłoby powtórzeniem toru sixelowego pod inną nazwą.
