# Krok 55 — Mysz: wskaźnik wchodzi do słownika wejścia

> **Skąd ten krok.** Powstał 2026-08-16 jako **zarys całej Fazy XIX** pod tytułem
> „Mysz, zaznaczanie treści i schowek" — wzmianka z rozpoznaniem, mająca
> unieruchomić fakty i wypisać pytania. Rozpisany tego samego dnia, po
> rozstrzygnięciu wszystkich siedmiu ([00-decyzje.md](00-decyzje.md), D95).
> Jedno z nich rozsadziło zarys: zakres myszy wyszedł na **komplet**, więc trzy
> mechanizmy z tytułu stały się **trzema krokami** (55, 56, 57), a ten bierze
> pierwszy z nich.

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D95 — dziesięć pytań rozstrzygniętych przed pierwszą linią kodu, w tym trzy,
których zarys nie przewidywał.

## Cel

Aplikacja przyjmuje **wskaźnik**, a nie tylko klawiaturę. Kliknięcie stawia
kursor tam, gdzie użytkownik patrzy; kółko przewija; prawy przycisk otwiera
menu; granica podziału daje się przeciągnąć, a podpowiedź w stopce i zakładka
w oknie pomocy — kliknąć.

Miarą powodzenia jest zdanie: **kliknięcie w wiersz listy stawia na nim kursor
we wszystkich trzech torach, a raportowanie myszy gaśnie przy każdym wyjściu
z aplikacji — także awaryjnym.**

Miarą drugą, wymierną: **rdzeń nie zyskuje mapy tego, co gdzie narysowano.**
Jeśli po tym kroku `FrameComposer` albo `ComponentInterface` wie, gdzie leży
wiersz listy, rozstrzygnięcie nr 2 wyszło źle i to jest miejsce, w którym się
to okaże.

## Trudność strukturalna — dwie rzeczy, obie starsze od tego kroku

**Pierwsza: wskaźnika nie ma w słowniku wejścia i nie da się go tam wcisnąć.**
`Key` ma 27 pozycji i ani jednej o myszy; `KeyPress` niesie znak, klawisz
nazwany i trzy znaczniki modyfikatorów. Zdarzenie wskaźnika ma współrzędne,
przycisk, rodzaj (naciśnięcie, zwolnienie, ruch, kółko) i własne modyfikatory —
czyli nie mieści się w `KeyPress` inaczej niż przez pola, które przy 99%
naciśnięć nic nie znaczą. Reguła 11j mówi o klawiszach; o wskaźniku nie mówi
nic, bo go nie było.

**Druga jest ta sama, o którą potknął się krok 40 przy ognisku: aplikacja nie
ma zachowanego drzewa komponentów.** Komponent powstaje w `draw()` i ginie
razem z klatką (reguła 11a), a wszystko, co przeżywa takt, mieszka **obok**
niego — `ScrollWindow`, `SectionState`, `SplitState`, `TreeState`. Pytanie
„który element leży pod kursorem" jest więc dokładnie tak samo niewykonalne,
jak było pytanie „który element ma ognisko". Krok 40 odpowiedział na to
deklaracją (reguła 11p) i **ten krok odpowiada tak samo** — to nie jest
naśladownictwo, tylko ten sam brak i to samo jedyne wyjście.

Trzecia rzecz nie jest trudnością, ale przesądziła o klawiszach w kroku 57
i musi stać tutaj, bo dotyczy trybu surowego z kroku 06: **`isig` i `iexten`
zostają w nim włączone.** Sprawdzone w prawdziwym pty przy rozpisywaniu planu
— po `stty -icanon -echo -ixon min 1 time 0` nadal obowiązuje `intr = ^C`
i `lnext = ^V`. Klawiatura generuje przez to SIGINT, a `^V` połyka następny
bajt; oba fakty są dziś nieszkodliwe i oba stają się przeszkodą, gdy ktoś
sięgnie po `Ctrl`+`Alt`+literę (D95 nr 8).

## Stan zastany (sprawdzony w kodzie 2026-08-16)

| Element | Stan |
|---|---|
| `Application/Dto/Key` | **27 pozycji**, żadna o wskaźniku. Potwierdzone. |
| `Application/Dto/KeyPress` | Pięć pól: `key`, `raw`, `ctrl`, `alt`, `shift`. Cztery nazwane konstruktory. Potwierdzone. |
| `Application/Port/InputPort` | Dwie metody: `readKey(): ?KeyPress`, `shutdownRequested(): bool`. Dwie implementacje: `TerminalService`, `GlfwInputService`. |
| `Infrastructure/Terminal/TerminalService` | Tryb surowy `-icanon -echo -ixon min 1 time 0`; **raportowania myszy nie włącza nic**. Przywrócenie zabezpieczone trzytorowo: sygnały, `register_shutdown_function()`, jawne `restore()`. Ekran zapasowy i ukrycie kursora tą samą drogą. |
| `Infrastructure/Terminal/KeySequenceParser` | Rozbiera CSI po bajcie końcowym i po tyldzie; `hasShift()` czyta drugi parametr. **Sekwencji SGR myszy (`ESC [ < … M/m`) nie zna.** |
| `Infrastructure/Glfw/GlfwInputService` | Dwa wywołania zwrotne (klawisz, znak) do jednej kolejki; `glfwPollEvents()` przy pierwszym pytaniu w takcie. |
| PHP-GLFW | Komplet sprawdzony `function_exists()`: `glfwSetMouseButtonCallback`, `glfwSetCursorPosCallback`, `glfwSetScrollCallback`, `glfwGetMouseButton`, `glfwSetCursorEnterCallback`, `glfwCreateStandardCursor`. Stałe `GLFW_MOUSE_BUTTON_*`, `GLFW_PRESS`, `GLFW_RELEASE`, `GLFW_IBEAM_CURSOR` — są. |
| `Presentation/Cli/GameLoop` | `consumeInput()` zbiera **wszystkie** klawisze zaległe od poprzedniego taktu w pętli `while (readKey())`. |
| `Presentation/Cli/InputHandler` | Trzy piętra: okno nakładane → klawisze globalne → ekran. Okno modalne; klawisz przepuszczony przez nie **nie schodzi** do ekranu. |
| `Presentation/Cli/FrameComposer` | Zna `HudLayout` i prostokąty trzech stref; oddaje ekranowi `Rect` i dostaje prymitywy. **Nie zapamiętuje niczego.** |
| `Presentation/Ui/DeclaresFocus` + `FocusHint` | Wzorzec deklaracji z kroku 40 wraz z testem zobowiązania obustronnego (`StatusHintsTruthTest`). |
| `Presentation/Ui/SplitState` | Trzyma **samą stronę podziału** (`bool`), nie proporcję. Granica podziału jest dziś liczona, nie przechowywana. |
| `Presentation/Ui/Component/Tabs` | Etykiety w jednym wierszu, odstęp `GAP_COLUMNS = 3`; pozycje kolumn liczy w `draw()` i nie oddaje ich nikomu. |
| `Presentation/Ui/StatusHints` | Składa pozycje stopki z `KeyBinding`; `lines()` pakuje je w jeden albo dwa wiersze. Pozycja **zna swoje wiązanie**, ale nie swoją kolumnę. |
| `Infrastructure/Glfw/GlfwFrameMetrics` | `rowHeight` i `columnWidth` liczone z pikseli okna; `GlfwViewportService::cells()` robi już przeliczenie piksele → komórki. |
| `Application/Dto/SettingKey` | Dziesięć kluczy rdzenia (`language` … `backgroundJobs`); przełącznik myszy będzie jedenastym. |
| Ekrany do obsłużenia | Rdzeń: `HelpScreen`, `SettingsScreen`, `StartupScreen`. Moduły: `BrowserScreen`, `FileInfoScreen`, `AudioScreen`, `DockerScreen`, `ClusterScreen`, `HostsScreen`, `RemoteScreen`, `SshScreen`. |

## Zależności

- **Krok 06** twardo i podwójnie: tryb surowy jest miejscem, w którym włącza się
  raportowanie myszy, a **gwarancja przywrócenia terminala** jest jedynym
  powodem, dla którego wolno je w ogóle włączyć. Raportowanie niezdjęte przy
  wyjściu zostawia użytkownikowi terminal sypiący sekwencjami przy każdym ruchu
  myszy — awaria widoczna długo po zamknięciu aplikacji.
- **Krok 09** — kolejka zdarzeń: `consumeInput()` zbiera dziś klawisze zaległe
  od poprzedniego taktu i będzie zbierać zdarzenia wskaźnika tą samą pętlą.
- **Krok 18** — komponenty i prostokąty: `Rect` w siatce znakowej jest jedynym
  układem współrzędnych, jaki znają komponenty, więc **to do niego** trzeba
  sprowadzić wszystko, co przychodzi z myszy.
- **Krok 19** — okno nakładane: klik trafia najpierw w nie, tą samą regułą
  pierwszeństwa, którą chodzi klawisz.
- **Krok 21** — przeglądarka jako pierwszy i główny odbiorca; kliknięcie w wiersz
  listy plików jest zdaniem-miarą tego kroku.
- **Krok 24** — podział ekranu: kliknięcie przenosi ognisko **między panelami**,
  a granica daje się przeciągnąć. To jedyna czynność kroku, która wymaga stanu,
  którego dziś nie ma: `SplitState` trzyma stronę, nie proporcję.
- **Krok 32** — menu kontekstowe pod prawym przyciskiem; menu istnieje, więc
  krok dokłada wyłącznie drugą drogę do jego otwarcia.
- **Krok 33** — rozmiar okna nie jest stałą: przeliczenie współrzędnych na siatkę
  znakową musi brać rozmiar **z bieżącej klatki**, a nie z uruchomienia.
- **Kroki 34 i 35** — tor okienkowy: wywołania zwrotne GLFW i metryka komórki,
  bez której piksele nie zamienią się w wiersz i kolumnę.
- **Krok 40** — ognisko deklarowane, nie odkrywane: kliknięcie **przenosi
  ognisko**, więc `DeclaresFocus` jest stroną, która musi się zgodzić, a stopka
  jest jednym z celów kliknięcia.
- **Kroki 14 i 15** — przełącznik ustawień rdzenia i napisy.

Do kroków **56** i **57** ma zależność **w drugą stronę**: oba stoją na słowniku
wejścia i na `AcceptsPointer`, które powstają tutaj. Kolejność `55 → 56 → 57`
jest przez to obowiązkowa.

## Model i wysiłek

**Fable / xhigh** — zachodzi ten sam warunek, który indeks stawia w przypisach
¹ i ² od kroku 33: krok **zmienia słownik wejścia** (`InputPort` przestaje
oddawać `KeyPress`) i dotyka **wszystkich trzech torów naraz** — parser
terminalowy, wywołania zwrotne GLFW i tor tekstowy, który raportowanie myszy
dostaje na równi z sixelowym (D95 nr 7).

Wariant tańszy z zarysu (`Opus / high` przy zakresie zawężonym do schowka bez
myszy) **odpadł**: rozstrzygnięcie nr 3 poszło na komplet czynności.

## Zakres

### 1. Słownik wejścia: `InputEvent` jako wspólny nadtyp

`Application/Dto/InputEvent` — interfejs znacznikowy. `KeyPress` go implementuje
(bez zmiany w polach i konstruktorach), a obok staje `PointerEvent`:

| Pole | Znaczenie |
|---|---|
| `row`, `column` | **komórka siatki znakowej**, liczona od zera — nigdy piksel |
| `button` | `PointerButton`: `Left`, `Middle`, `Right` |
| `action` | `PointerAction`: `Press`, `Release`, `Drag`, `ScrollUp`, `ScrollDown` |
| `ctrl`, `alt`, `shift` | te same trzy modyfikatory, co w `KeyPress`, tą samą regułą 11j |

**Współrzędne są w komórkach, nie w pikselach, i to jest rozstrzygnięcie, nie
wygoda.** `Rect` jest jedynym układem współrzędnych komponentów (krok 18),
a piksele zaczynają się dopiero w rendererze — zdarzenie niosące piksele
zmuszałoby każdy ekran do poznania metryki czcionki. Przeliczenie należy więc
do infrastruktury: w terminalu robi je protokół (SGR podaje kolumnę i wiersz
liczone od jedynki — odejmujemy ją w parserze), w oknie —
`GlfwViewportService::cells()`, czyli ten sam rachunek, którym okno liczy dziś
swój rozmiar.

`InputPort::readKey()` zmienia się w `readEvent(): ?InputEvent`. To jest cena
rozstrzygnięcia nr 1 wypisana wprost: sygnaturę woła pętla, `InputHandler`,
`LoopBenchmarkRunner` i przebiegi testowe. Zyskiem jest **jedna kolejka**, czyli
zachowana kolejność kliknięcia wobec klawisza — bez niej kliknięcie stawiające
ognisko i litera wpisana zaraz po nim mogłyby się w jednym takcie wyminąć.

### 2. Tor terminalowy: raportowanie SGR i jego zdejmowanie

Włączenie wraz z wejściem w tryb surowy, zdjęcie **tą samą trzytorową
gwarancją**, którą krok 06 dał ustawieniom terminala (sygnały, funkcja
zamknięcia procesu, jawne `restore()`) — obok ekranu zapasowego i ukrycia
kursora, które chodzą tą drogą od początku:

- `\e[?1000h` — raportowanie naciśnięć i zwolnień,
- `\e[?1002h` — **ruch wyłącznie przy wciśniętym przycisku**,
- `\e[?1006h` — tryb SGR.

Zdejmowanie w odwrotnej kolejności. Dwa wybory do zapamiętania. **`1002`, a nie
`1003`**: raportowanie każdego ruchu wysyłałoby zdarzenie na każdą przekroczoną
komórkę, czyli zalewałoby pętlę wejściem, którego nikt nie zamawiał — a jedyne,
co by z tego wynikło, to podpowiedzi pod kursorem, których krok nie ma
w zakresie. **`1006` jest obowiązkowy**, nie dodatkowy: tryb domyślny koduje
współrzędną jako bajt z przesunięciem 32, więc powyżej 223. kolumny przestaje
działać, a okno pomiarowe projektu ma 100 kolumn tylko dlatego, że tak je
ustawiono w `bin/run.sh`.

`KeySequenceParser` rozpoznaje `ESC [ < b ; x ; y M` (naciśnięcie) i `… m`
(zwolnienie), gdzie `b` niesie przycisk, znacznik ruchu (bit 32), kółko
(bity 64–65) i modyfikatory (bity 4–16). Sekwencja niepełna czeka na resztę tą
samą drogą, którą czeka dziś strzałka.

### 3. Tor okienkowy: trzy wywołania zwrotne do tej samej kolejki

`glfwSetMouseButtonCallback`, `glfwSetCursorPosCallback` i
`glfwSetScrollCallback` dopisują `PointerEvent` do kolejki, w której stoją już
klawisze — więc kolejność zachowuje się sama, bez ani jednej zmiany
w `GameLoop`. Położenie kursora przychodzi w pikselach i zamienia się na komórki
metryką z `VgContextService`; ruch **bez wciśniętego przycisku jest odrzucany
w wywołaniu zwrotnym**, żeby tor okienkowy zachowywał się dokładnie jak `1002`
w terminalu.

### 4. Trafienie: `AcceptsPointer`

Zdolność deklarowana osobno — jak `DeclaresFocus`, `NeedsTime` i `DrawsOwnFrame`
— a nie metoda w `ScreenInterface`:

```
interface AcceptsPointer
{
    public function pointer(PointerEvent $event): ScreenOutcome;
}
```

Ekran, który ją deklaruje, **pamięta prostokąt z ostatniego rysowania** i sam
tłumaczy współrzędne na własne pojęcia: numer wiersza listy, stronę podziału,
zakładkę. Stan przeżywający klatkę mieszka więc tam, gdzie mieszka cały taki
stan w tej aplikacji — obok komponentu, u ekranu (reguła 11a).

Zobowiązanie obustronne, wzorem kroku 40 i pilnowane testem: **ekran
deklarujący `AcceptsPointer` musi obsłużyć kliknięcie w każde miejsce, które
deklaruje w `focus()`** — inaczej mysz działa w połowie ekranu i nie widać
tego, dopóki ktoś nie kliknie.

Okno nakładane ma **pierwszeństwo**, tą samą regułą co klawisz: `OverlayInterface`
może zadeklarować `AcceptsPointer`, a kliknięcie **poza** oknem jest połykane
(okno jest modalne — kliknięcie w listę pod spodem zmieniałoby zaznaczenie,
którego użytkownik w tej chwili nie widzi).

### 5. Co mysz robi — komplet z rozstrzygnięcia nr 3

| Czynność | Skutek | Właściciel odpowiedzi |
|---|---|---|
| Lewy przycisk w wierszu listy | kursor staje na wskazanej pozycji | ekran modułu |
| Lewy przycisk w drugim panelu | ognisko przechodzi na ten panel **i** kursor staje | `BrowserScreen` + `SplitState` |
| Podwójne kliknięcie | to, co `Enter` w tym miejscu | ekran |
| Prawy przycisk | menu z kroku 32, po uprzednim postawieniu kursora | `InputHandler` |
| Kółko | przewinięcie o **trzy wiersze**, bez ruszania kursora | ekran (`ScrollWindow`) |
| Przeciągnięcie granicy podziału | zmiana proporcji paneli | `SplitState` |
| Kliknięcie w podpowiedź stopki | wykonanie jej klawisza | `StatusHints` + `InputHandler` |
| Kliknięcie w zakładkę | przejście na tę zakładkę | `HelpScreen`, `SettingsScreen` |

Trzy z tych wierszy dokładają coś, czego dziś nie ma, i każdy jest nazwany:

- **Podwójne kliknięcie wymaga zegara.** Próg 400 ms; czas podaje pętla
  (`LoopState::now()`), tą samą drogą, którą dostaje go karetka. To jedyna rzecz
  w kroku pytająca o czas, więc rozpoznanie pary należy do jednego miejsca —
  `PointerGestures` obok `InputHandler`, nie do każdego ekranu z osobna.
- **Granica podziału przestaje być liczona, a zaczyna być pamiętana.**
  `SplitState` dostaje proporcję (ułamek szerokości albo wysokości) wraz
  z granicami, poniżej których panel przestaje mieć sens. To jedyna zmiana
  w stanie, jaką krok wnosi poza wejściem.
- **Pozycja stopki musi poznać swoją kolumnę.** `StatusHints::lines()` pakuje
  dziś pozycje w wiersze i gubi po drodze to, gdzie która stanęła. Pakowanie
  zaczyna oddawać zakres kolumn wraz z wiązaniem — a kliknięcie zamienia się na
  `KeyPress` i wraca do `InputHandler::handle()`, czyli **wykonuje się tą samą
  drogą co klawisz**, a nie drugą, równoległą.

### 6. Ustawienie rdzenia: `mouse`

Jedenasty klucz `SettingKey`, przełącznik, **domyślnie włączony**. Powody są
dwa i oba prawdziwe: terminal bez raportowania myszy (a takich jest wiele)
i użytkownik, który woli natywne zaznaczanie terminala. Wyłączenie zdejmuje
raportowanie **w locie**, a nie dopiero przy następnym uruchomieniu — sekwencja
wyłączająca jest tą samą, którą wysyła `restore()`.

W torze okienkowym przełącznik nic nie zdejmuje (nie ma czego), więc odpina
wywołania zwrotne od kolejki — zachowanie ma być takie samo, a nie „podobne".

### 7. Napisy, pomiar, przebiegi

- **Napisy:** pozycja ustawień wraz z opisem, dwa zdania o wyłączonej myszy.
  Klawiszy krok nie dokłada, więc stopka nie rośnie ani o pozycję.
- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 54, bo krok dokłada
  gałąź do parsera wejścia — czyli do kodu na ścieżce **każdego** naciśnięcia
  (ten sam rachunek, co w kroku 44). Wygląd klatki się nie zmienia, więc
  porównania sixelowego krok nie potrzebuje; potrzebuje za to **drugiego
  przebiegu `--loop` z myszą wyłączoną w ustawieniach**, bo to on odpowiada na
  pytanie, czy przełącznik ma sens.
- **Narzędzie, a nie zastępnik:** sekwencje myszy ogląda się `bin/terminal-probe`
  (`make probe`, `make probe-xterm`) — to on od kroku 06 pokazuje, co terminal
  naprawdę przysyła, i **on ma poznać raportowanie myszy**, zamiast dorabiania
  doraźnego skryptu (reguła 18). Podgląd wejścia jest przy tym jedynym sposobem
  sprawdzenia, że sekwencja niepełna czeka na resztę, a nie rozsypuje się na
  znaki.
- **Przebiegi:** rozbiór sekwencji SGR (naciśnięcie, zwolnienie, kółko,
  przeciągnięcie, modyfikatory), zamiana pikseli na komórki w torze okienkowym,
  kliknięcie w wiersz listy w obu panelach, przewinięcie kółkiem, podwójne
  kliknięcie wraz z progiem czasu, kliknięcie poza oknem nakładanym, kliknięcie
  w podpowiedź stopki, kliknięcie w zakładkę, przeciągnięcie granicy podziału
  wraz z granicami. Tor okienkowy jak w kroku 44 — przez mapowanie, bez okna.

### 8. Dokumentacja

`docs/architecture.md` — wskaźnik w słowniku wejścia, `InputEvent` jako nadtyp,
reguła „współrzędne w komórkach, nigdy w pikselach", zdejmowanie raportowania
jako czwarta rzecz w gwarancji wyjścia z kroku 06. `SKILL.md` — nowa reguła 11z
(deklaracja trafienia wzorem 11p, wraz z zobowiązaniem obustronnym) oraz dopisek
do 11j o tym, że modyfikatory wskaźnika są **tymi samymi trzema**. `README.md` —
mysz wśród sposobów obsługi i pozycja ustawień.

## Poza zakresem

- **Zaznaczanie treści myszą** — krok 56. Tutaj przeciągnięcie zna wyłącznie
  granica podziału.
- **Schowek** — krok 57.
- **Podpowiedzi pod kursorem (hover)** — wymagałyby trybu `1003`, czyli
  zdarzenia na każdą przekroczoną komórkę; punkt 2 mówi, dlaczego nie.
- **Zmiana kształtu kursora** (`glfwCreateStandardCursor`, `GLFW_IBEAM_CURSOR`)
  — funkcja jest, ale pierwszym powodem, żeby kursor zmienił kształt, jest
  zaznaczanie tekstu; wchodzi razem z nim w kroku 56.
- **Przeciąganie i upuszczanie plików** — to jest czynność na plikach, nie
  wejście.
- **Mysz w oknie startowym** (`StartupScreen`) — ekran istnieje przez ułamek
  sekundy i nie ma w nim czego kliknąć.
- **Zmiana rozmiaru okna myszą** — należy do menedżera okien i do kroku 37.
- **Kółko poziome i przyciski boczne** — nie mają odbiorcy (reguła 13).

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Dto/InputEvent.php` | Application | Nowe — interfejs znacznikowy wspólny dla klawisza i wskaźnika. |
| `Application/Dto/KeyPress.php` | Application | `implements InputEvent`; pola i konstruktory nietknięte. |
| `Application/Dto/PointerEvent.php` | Application | Nowe — wiersz, kolumna, przycisk, rodzaj, trzy modyfikatory. |
| `Application/Dto/PointerButton.php`, `PointerAction.php` | Application | Nowe — dwa enumy. |
| `Application/Port/InputPort.php` | Application | `readKey()` → `readEvent(): ?InputEvent`. |
| `Application/Dto/SettingKey.php` | Application | Jedenasty klucz: `mouse`. |
| `Infrastructure/Terminal/TerminalService.php` | Infrastructure | Włączenie i zdjęcie `1000`/`1002`/`1006` wraz z trybem surowym; przełączanie w locie. |
| `Infrastructure/Terminal/KeySequenceParser.php` | Infrastructure | Rozbiór `ESC [ < b ; x ; y M/m`. |
| `Infrastructure/Terminal/ParsedKey.php` | Infrastructure | Niesie `InputEvent`, nie `KeyPress`. |
| `Infrastructure/Glfw/GlfwInputService.php` | Infrastructure | Trzy wywołania zwrotne do tej samej kolejki; odrzucenie ruchu bez przycisku. |
| `Infrastructure/Glfw/GlfwPointerMapper.php` | Infrastructure | Nowe — piksele na komórki, przyciski i modyfikatory GLFW na słownik. |
| `Infrastructure/Config/SettingsService.php` | Infrastructure | Odczyt i zapis przełącznika myszy. |
| `Presentation/Ui/AcceptsPointer.php` | Presentation | Nowe — zdolność deklarowana, wzorem `DeclaresFocus`. |
| `Presentation/Ui/SplitState.php` | Presentation | Proporcja podziału wraz z granicami — obok strony ogniska. |
| `Presentation/Ui/StatusHints.php` | Presentation | Pakowanie oddaje zakres kolumn każdej pozycji. |
| `Presentation/Cli/GameLoop.php` | Presentation | `consumeInput()` rozdziela zdarzenie na dwie drogi. |
| `Presentation/Cli/InputHandler.php` | Presentation | Trzy piętra dla wskaźnika; prawy przycisk do menu; klik w stopkę zamieniony na klawisz. |
| `Presentation/Cli/PointerGestures.php` | Presentation | Nowe — rozpoznanie podwójnego kliknięcia i przeciągnięcia. |
| `Presentation/Cli/Screen/HelpScreen.php`, `SettingsScreen.php` | Presentation | `AcceptsPointer`: zakładki i pozycje. |
| `Module/*/Presentation/*Screen.php` | Moduły | `AcceptsPointer` w ośmiu ekranach modułów; przeglądarka pełni, reszta co najmniej listą i kółkiem. |
| `Infrastructure/Diagnostics/LoopBenchmarkRunner.php` | Infrastructure | Nowa sygnatura portu wejścia. |
| `lang/pl.php`, `lang/en.php` | Napisy | Pozycja ustawień, opis, dwa zdania. |
| `tests/Unit/…`, `tests/Functional/PointerFlowTest.php` | Testy | Parser SGR, mapowanie GLFW, przebiegi z punktu 7, zobowiązanie obustronne `AcceptsPointer`. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Punkt 8. |

## Rozstrzygnięcia startowe (2026-08-16, D95)

Pełna treść wraz z odrzuconymi alternatywami:
[00-decyzje.md](00-decyzje.md), D95. Dotyczą tego kroku:

1. **Wskaźnik wchodzi jako wspólny nadtyp `InputEvent`**, w jednej kolejce
   z klawiszami — nie jako drugi kanał portu i nie jako pola w `KeyPress`.
2. **Trafienie deklaruje ekran** (`AcceptsPointer`), wzorem reguły 11p. Rdzeń nie
   zyskuje mapy tego, co gdzie narysowano.
3. **Mysz umie komplet**: kursor, ognisko, kółko, prawy przycisk, podwójne
   kliknięcie, granica podziału, stopka, zakładki. To rozstrzygnięcie rozbiło
   fazę na trzy kroki.
7. **Raportowanie włącza się także w torze tekstowym** — jednolicie we wszystkich
   trzech, kosztem natywnego zaznaczania terminala (zostaje pod `Shift`em).
9. **Faza dzieli się na trzy kroki**: 55 mysz, 56 zaznaczanie treści, 57 schowek.
10. **Mysz jest przełącznikiem ustawień rdzenia**, domyślnie włączonym.

## Kryteria ukończenia

- **Kliknięcie w wiersz listy stawia kursor we wszystkich trzech torach** —
  sprawdzone przebiegiem dla toru terminalowego i okienkowego oraz klatką pod
  XTermem (reguła 17: po uzgodnieniu z użytkownikiem).
- **Raportowanie myszy gaśnie przy każdym wyjściu** — normalnym (`F10`), przez
  sygnał (`Ctrl`+`C`, `kill -TERM`) i przez błąd; po zamknięciu aplikacji ruch
  myszy nad terminalem nie wypisuje ani jednego znaku.
- **Wyłączenie myszy w ustawieniach przywraca natywne zaznaczanie terminala**
  i działa w locie.
- **Rdzeń nie ma mapy trafień**: `FrameComposer` i `ComponentInterface` zostają
  nietknięte co do tego, co gdzie leży — pilnuje tego przegląd i test.
- **Ekran deklarujący `AcceptsPointer` obsługuje kliknięcie w każde miejsce,
  które deklaruje w `focus()`** — pilnuje tego jeden test dla wszystkich ekranów.
- Kliknięcie poza oknem nakładanym **nie robi nic** i nie zamyka okna.
- Przeciągnięcie granicy podziału zmienia proporcję i zatrzymuje się na
  granicach; kliknięcie w podpowiedź stopki robi to samo, co jej klawisz.
- `bin/render-bench --loop` „przed i po" bez regresji, wraz z drugim przebiegiem
  przy myszy wyłączonej.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
