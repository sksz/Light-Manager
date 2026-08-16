# Krok 57 — Schowek systemowy: trzy tory, dwie drogi, jedno miejsce

> **Skąd ten krok.** Powstał 2026-08-16 z podziału zarysu Fazy XIX
> ([00-decyzje.md](00-decyzje.md), D95 nr 9) i bierze z niego trzeci mechanizm.
> Zamyka fazę i **spłaca dług kroku 56**: zaznaczenie, które tamten krok
> dowiózł bez odbiorcy, dostaje tutaj to, po co powstało.

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D95 (nr 5, 6, 8 i 9).

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

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
