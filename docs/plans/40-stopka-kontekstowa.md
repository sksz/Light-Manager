# Krok 40 — Stopka mówi, co da się zrobić tu i teraz

> **Skąd ten krok.** Powstał 2026-08-13 na żądanie użytkownika. **Odwraca
> rozstrzygnięcie kroku 14, powtórzone w kroku 18** i zapisane do dziś wprost
> w komentarzu `FrameComposer::hints()`: „stopka nie jest ściągawką, tylko
> wskazaniem, gdzie ściągawka leży. Pełny spis stoi pod `F1`”. Powód tamtej
> decyzji zostaje w mocy w połowie — i ta połowa jest opisana niżej, bo
> przesądza o kształcie kroku.

## Status

**Nie rozpoczęty** (2026-08-13).

## Cel

Stopka ma mówić o **elemencie, na którym stoi kursor** — nie o czterech
klawiszach, które znaczą to samo od pierwszego uruchomienia.

Miarą powodzenia jest zdanie: **przeniesienie ogniska zmienia treść stopki w tej
samej klatce, a każdy klawisz, o którym stopka mówi, naprawdę działa w miejscu,
w którym stoi kursor.** Zdanie odwrotne jest równie wiążące — klawisz działający
tu i teraz, a w stopce niewymieniony, jest błędem, nie przeoczeniem.

## Co dokładnie się odwraca, a co zostaje

Odwraca się **zasięg**: stopka przestaje być wskaźnikiem na `F1` i zaczyna być
odpowiedzią na pytanie „co teraz mogę”.

Zostaje **źródło** i to jest ta połowa dawnej decyzji, której krok nie rusza:
podpowiedzi powstają z `KeyBinding`, czyli z tego samego miejsca, z którego
pochodzi obsługa klawisza. Do kroku 18 stopka brała napis `browser.hints`
z katalogu i potrafiła skłamać po zmianie wiązania; **do tego stanu nie ma
powrotu** i żadne rozstrzygnięcie tego kroku nie ma prawa go przywrócić.

Zostaje też okno pomocy jako **pełny** spis. Stopka pokazuje to, co się mieści;
`F1` pokazuje wszystko — i dlatego klawisz `F1` nie znika ze stopki nawet wtedy,
gdy ustępują jej pozostałe pozycje.

## Trudność strukturalna — najważniejsza treść tego pliku

**Aplikacja nie ma zachowanego drzewa komponentów.** Komponent powstaje
w `draw()` i ginie razem z klatką; wszystko, co ma przeżyć takt, mieszka **obok**
niego (`ScrollWindow` z kroku 18, `SectionState` z 22, `SplitState` z 24). Nie da
się więc „znaleźć elementu z ogniskiem”, chodząc po drzewie — drzewa nie ma
w żadnym momencie poza tą jedną chwilą, gdy klatka się składa.

Wniosek jest jeden i przesądza o kształcie całego kroku: **ognisko trzeba
zadeklarować, a nie odkryć.** `FrameComposer` nie ma prawa niczego szukać — pyta,
a odpowiada ten, kto ognisko trzyma, czyli ekran.

Drugi wniosek dotyczy dzisiejszego `FocusableInterface`. Interfejs istnieje od
kroku 18, ale implementują go **dwa komponenty** (`TextInput`, `Button`),
a prawdziwe ognisko aplikacji żyje gdzie indziej: w `SplitState`, w `BrowserPanes`
i w `SettingsCursor`. Ekrany wystawiają je na zewnątrz **wyłącznie pośrednio** —
przez `bindings()` zależne od stanu (`BrowserScreen` dokłada `Tab` dopiero przy
włączonym podziale, `FileInfoScreen` oddaje inne strzałki każdemu panelowi).
To działa, ale nie ma nazwy, więc każdy nowy ekran wymyśla to od nowa.

## Zależności

- **Krok 18** twardo i wielokrotnie: stamtąd pochodzi `KeyBinding` jako jedno
  źródło trzech rzeczy naraz, `FocusableInterface`, `StatusBar` i `HudLayout` —
  czyli komplet tego, co krok rusza.
- **Krok 19** — `OverlayInterface::bindings()` oraz reguła, że okno nakładane
  klawiszy niżej nie oddaje (`InputHandler::toOverlay`). Z niej wynika, że okno
  **wypiera** ekran ze stopki, zamiast się z nim dokładać.
- **Krok 24** — `SplitState` jest pierwszym miejscem w projekcie, gdzie ekran wie,
  który z jego kawałków ma ognisko. Krok robi z tego kontrakt.
- **Kroki 25 i 29** (wraz z **D60**) — `FileInfoScreen` z dwoma panelami
  i ogniskiem wędrującym między sekcjami a podglądem tekstu jest
  **najbogatszym odbiorcą** tego kroku i jego właściwym sprawdzianem.
- **Krok 30** — precedens „spis pokazuje wyłącznie to, co działa tu i teraz”
  (`Esc` w przeglądarce pojawia się dopiero, gdy jest co zdejmować). Krok 40
  rozciąga tę zasadę z okna pomocy na stopkę.
- **Krok 33** — pasek stanu rosnący do dwóch wierszy musi przeżyć przeliczenie
  układu przy zmianie rozmiaru okna; progi `HudLayout` są jedynym miejscem, gdzie
  ta reguła ma prawo mieszkać.
- **Krok 35** — zmiana wysokości strefy dotyczy **wszystkich trzech torów**
  (Sixel, tekst, okno), a nie samego renderera terminalowego.
- **Krok 38** — wzorce tekstowe, wzorce PNG i przebiegi funkcjonalne, które ten
  krok przelicza, oraz `ScenarioFactory::HINTS`, przez którą przechodzi pomiar.

Od kroków **31, 32, 36, 37 i 39** nie zależy i one nie zależą od niego. Z krokiem
**32** (menu kontekstowe) dzieli pytanie — „co da się zrobić z tym, na czym stoi
kursor” — zadane z dwóch stron: menu pyta o **komendy**, stopka o **klawisze**.
Jeśli krok 32 wykona się pierwszy i wprowadzi zdolność „czego dotyczę”, stopka
może z niej skorzystać; jeśli nie — nie ma z czego, i to nie jest przeszkoda.

## Model i wysiłek

**Fable / xhigh.**

Kod jest rozproszony i powiązany: kontrakt ogniska w `Presentation/Ui`, składanie
stopki w `FrameComposer`, rysowanie w `StatusBar`, progi w `HudLayout`, trzy
ekrany rdzenia i dwa moduły jako implementacje, dwa katalogi napisów plus
katalogi modułów, wreszcie przeliczenie wszystkich wzorców w trzech torach.
Każda z tych rzeczy z osobna jest niewielka; ryzyko leży w tym, że muszą zgodzić
się ze sobą naraz — czyli w tym samym miejscu, co przy krokach 33–35.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Presentation/Cli/FrameComposer::hints()` | Skleja podpowiedzi z **wyłącznie** `InputHandler::globalBindings()` — czterech wiązań rdzenia. Ekranu ani okna nie pyta. |
| `Presentation/Ui/Component/StatusBar` | Komunikat po lewej, podpowiedzi dosunięte do prawej, przegroda `Bar`. Rysuje **jeden wiersz** (`$bounds->row`); podpowiedzi znikają **w całości**, gdy zabraknie dwóch kolumn oddechu. |
| `Presentation/Ui/HudLayout::statusRows()` | 3 wiersze (panel) / 1 / 0 wedle wysokości okna. Treść panelu to **jeden** wiersz wewnątrz obwódki. |
| `Presentation/Ui/FocusableInterface` | Istnieje od kroku 18; implementują **dwa** komponenty: `TextInput`, `Button`. |
| `Presentation/Ui/SplitState`, `Module/Browser/…/BrowserPanes` | Prawdziwi właściciele ogniska — bez wspólnej nazwy i bez drogi na zewnątrz. |
| `ScreenInterface::bindings()`, `OverlayInterface::bindings()` | Już zależne od stanu (kroki 24, 29, 30) — **materiał na stopkę jest gotowy**, brakuje drogi. |
| `Presentation/Cli/InputHandler::toModule()` | Skróty modułów (`Ctrl`+litera) są obsługiwane, ale **nie ma ich w `globalBindings()`** — stopka nigdy o nich nie mówiła. |
| `Infrastructure/Diagnostics/ScenarioFactory::HINTS` | **Stała tekstowa** `'F1 pomoc · F2 ustawienia · F10 wyjście'` — scenariusze mierzą stopkę, której aplikacja nie ma. |
| `tests/Golden/*.txt`, `docs/pomiary/wzorce-png/` | Zawierają wiersz stopki — **każdy wzorzec do przeliczenia**. |

## Zakres

### 1. Ognisko dostaje nazwę i kontrakt

Ekran ma umieć powiedzieć, **co ma ognisko**, zamiast odpowiadać na to pytanie
pośrednio, przez treść `bindings()`. Kierunek do rozstrzygnięcia (pytanie 1),
z rekomendacją: **osobny interfejs zdolności** w rodzaju `NeedsTime`, `Resettable`
i `DrawsOwnFrame`, a nie nowa metoda w `ScreenInterface` — ekran bez ogniska
(pomoc, ekran startowy) nie ma czego deklarować i nie powinien być do tego
zmuszany. Kontrakt oddaje `FocusableInterface` albo samą listę wiązań wraz
z kluczem etykiety miejsca; kształt też jest pytaniem startowym.

Implementują: `BrowserScreen` (panel czynny), `FileInfoScreen` (sekcje albo
podgląd tekstu), `SettingsScreen` (pasek zakładek, pozycja, pole tekstowe
w edycji). To są **prawdziwi użytkownicy** i to oni, a nie test, rozstrzygają
o kształcie kontraktu (reguła 13).

### 2. Trzy poziomy i ich kolejność

Stopka czyta się od najbardziej szczegółowego do najbardziej ogólnego:

1. **element z ogniskiem** — panel, sekcja, pole,
2. **ekran albo okno nakładane** — okno **wypiera** ekran, bo klawisze do niego
   nie schodzą,
3. **globalne** — `F1`/`F2`/`F12`/`F10` wraz ze skrótami modułów.

Ustępowanie idzie **od końca**: pierwsze znikają globalne, bo one jedne są
niezmienne i stoją w oknie pomocy. `F1` jest ostatnim, który ustępuje — bez niego
znika jedyna droga do pełnego spisu. Powtórzenia (`↑↓` zadeklarowane i przez
element, i przez ekran) muszą zniknąć — pytanie 4 rozstrzyga, wedle czego.

### 3. Skróty modułów po raz pierwszy w stopce

`Ctrl`+litera otwiera moduł niezależnie od tego, co jest na wierzchu, a mimo to
nigdy nie było ich w stopce — `globalBindings()` ich nie zna, bo powstają
dopiero z rejestru modułów w `Bootstrap`. Krok domyka tę lukę; jeśli skrótów jest
więcej niż dwa, obowiązuje ich polityka szerokości z punktu 4.

### 4. Polityka szerokości

Reguła kroku 18 „podpowiedzi ustępują komunikatowi” **zostaje nietknięta** —
w wąskim oknie długi błąd jest ważniejszy. Ponad nią wchodzi ustępowanie
wewnątrz samych podpowiedzi: kolejność z punktu 2, znacznik ucięcia i próg,
poniżej którego pozycja znika w całości zamiast się urwać w połowie słowa.
Podpowiedź ucięta do `moduł.file-in…` nie jest podpowiedzią.

### 5. Pasek stanu może urosnąć do dwóch wierszy

Rozstrzygnięcie użytkownika (D65). Skutki, wszystkie do wykonania w tym kroku:

- `HudLayout::statusRows()` dostaje próg na wariant dwuwierszowy (panel: 4 wiersze
  zamiast 3, bo obwódka bierze dwa) wraz z regułą, **kiedy** rośnie — pytanie 6;
- `StatusBar` przestaje rysować wyłącznie `$bounds->row`;
- wiersz zabierany jest **liście**, więc próg musi liczyć się z `ROWS_FOR_PREVIEW`
  i `ROWS_FOR_LIST_PANEL`; w niskim oknie pasek nie rośnie w ogóle;
- **wszystkie wzorce** i zrzuty do przeliczenia (punkt 8).

To jest najdroższa część kroku i jedyna, która zmienia wygląd klatki poza samą
stopką.

### 6. Napisy

Dzisiejsze opisy są zdaniami: „zmiana zaznaczenia”, „powrót do listy plików”,
„zwiń lub rozwiń sekcję”. W oknie pomocy czytają się dobrze — w stopce, gdzie
pozycji ma być kilka naraz, nie mieszczą się nawet w szerokim oknie. Warianty
w pytaniu 5; ostrzeżenie ma tu wagę większą niż zwykle: **to jest największa
objętościowo część pracy**, dotyczy dwóch katalogów rdzenia i katalogów obu
modułów, a wybór „skrócić same opisy” zmienia **także okno pomocy** i wszystkie
jego wzorce.

### 7. Pomiar

`ScenarioFactory::HINTS` jest stałą tekstową, więc dopóki się jej nie ruszy,
pomiar „przed i po” **nie zobaczy niczego** — scenariusze rysują stopkę, której
aplikacja nie ma. Zmiana tej stałej na treść realistycznej długości jest
warunkiem sensowności całego pomiaru, a nie kosmetyką.

Scenariusze do przejrzenia: `chrome`, `chrome-text`, `settings`, `split`,
`command`. Trzy tory (Sixel, tekst, okno) — każdy nowy kształt stopki przechodzi
przez wszystkie naraz, jak od kroku 38. Nowy scenariusz powstaje tylko wtedy,
gdy dwuwierszowy pasek okaże się osobnym kosztem, a nie tym samym w innym
prostokącie.

Przed pomiarem obowiązuje reguła 17: **poproś użytkownika o zwolnienie mocy
hosta i poczekaj na potwierdzenie.**

### 8. Wzorce i przebiegi

- `tests/Golden/*.txt` — wiersz stopki jest w nich wprost (`settings.txt`:
  `T28,60,Muted,-,F1 pomoc · F2 ustawienia · F10 wyjście`);
- `docs/pomiary/wzorce-png/` — wzorce obrazowe z kroku 38;
- nowy przebieg funkcjonalny w `tests/Functional`: **przeniesienie ogniska
  zmienia stopkę**, a treść stopki zgadza się z klawiszami, które w tym miejscu
  naprawdę działają.

Ostatnie zdanie zasługuje na test szczególny, bo jest właściwym sensem kroku:
**dla każdego ekranu i każdego położenia ogniska każde wiązanie pokazane
w stopce ma być obsłużone przez `handle()`** — jeden test dla wszystkich, na
wzór `PrimitiveTranslationTableTest` z kroku 30.

### 9. Dokumentacja

`docs/architecture.md` (Słownik interfejsu — ognisko jako pojęcie rdzenia),
`SKILL.md` (reguła: nowy ekran deklaruje ognisko; stopka i okno pomocy mają jedno
źródło), `README.md`. Dawne zdanie „stopka nie jest ściągawką” usuwa się
**wraz z powodem** — komentarz w `FrameComposer` ma powiedzieć, co je zastąpiło
i dlaczego.

## Poza zakresem

- **Przemapowanie klawiszy przez użytkownika** — stopka pokazuje wiązania takimi,
  jakie są; czynienie ich konfigurowalnymi to osobna, większa rzecz.
- **Podpowiedzi kontekstowe w oknie pomocy** — `F1` zostaje **pełnym** spisem
  i to jest jego rola; krok jej nie zawęża.
- **Piąta strefa układu na podpowiedzi** — odrzucone przy planowaniu (D65) na
  rzecz paska stanu rosnącego do dwóch wierszy.
- **Klikalne podpowiedzi** — rdzeń nie ma słownika zdarzeń myszy w żadnej
  warstwie.
- **Przewijanie albo animowanie podpowiedzi w wąskim oknie** — ustępowanie jest
  regułą statyczną; ruch w pasku stanu ściągałby oko z treści.
- **Podpowiedzi dla komend okna komend** — okno komend ma własną listę
  podpowiedzi i to ona jest jego stopką.
- **Kolorowanie pozycji wedle rodzaju czynności** — pasek stanu ma dziś dwie role
  (`Muted`, `Border`) i nie ma powodu, by miał więcej.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/FocusableInterface.php` | Presentation | Kontrakt ogniska — wedle rozstrzygnięcia nr 1; być może nowy interfejs zdolności obok. |
| `Presentation/Ui/Component/StatusBar.php` | Presentation | Dwa wiersze, ustępowanie wewnątrz podpowiedzi, znacznik ucięcia. |
| `Presentation/Ui/HudLayout.php` | Presentation | Próg dla dwuwierszowego paska stanu wraz z regułą, kiedy nie rośnie. |
| `Presentation/Cli/FrameComposer.php` | Presentation | `hints()` pyta ognisko → ekran/okno → globalne; usunięcie odwróconego komentarza. |
| `Presentation/Cli/InputHandler.php` | Presentation | Skróty modułów jako wiązania do pokazania, nie tylko do obsługi. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Przekazanie skrótów modułów do składania stopki. |
| `Presentation/Cli/Screen/SettingsScreen.php`, `Screen/HelpScreen.php` | Presentation | Deklaracja ogniska (pasek zakładek / pozycja / pole); pomoc — jeśli ma czym. |
| `Module/Browser/Presentation/BrowserScreen.php`, `BrowserPanes.php` | Moduł | Panel czynny jako zadeklarowane ognisko. |
| `Module/FileInfo/Presentation/FileInfoScreen.php` | Moduł | Sekcje albo podgląd tekstu jako zadeklarowane ognisko. |
| `lang/pl.php`, `lang/en.php`, `Module/*/lang/*` | Napisy | Opisy w postaci nadającej się do stopki — wedle rozstrzygnięcia nr 5. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | `HINTS` przestaje być stałą oderwaną od aplikacji. |
| `tests/Golden/*.txt`, `docs/pomiary/wzorce-png/` | Wzorce | Przeliczenie po zmianie treści i wysokości stopki. |
| `tests/Functional/…FlowTest.php` | Testy | Nowy przebieg: ognisko zmienia stopkę. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Ognisko jako pojęcie rdzenia; nowa rola stopki wraz z powodem. |

## Do rozstrzygnięcia na starcie kroku

1. **Kształt kontraktu ogniska** — osobny interfejs zdolności (rekomendacja) czy
   metoda w `ScreenInterface`; czy oddaje `FocusableInterface`, czy samą listę
   wiązań wraz z kluczem etykiety miejsca.
2. **Czy stopka nazywa miejsce** — czy przed wiązaniami stoi etykieta („Podgląd”,
   „Panel prawy”), czy same klawisze. Etykieta pomaga, gdy ognisko wędruje, ale
   zabiera kolumny, których i tak brakuje.
3. **Ile pozycji maksymalnie** — twardy limit liczby wiązań w stopce czy sam
   budżet kolumn.
4. **Odsiew powtórzeń** — po czym poznajemy, że element i ekran mówią o tym samym
   klawiszu: po zestawie klawiszy, po kluczu opisu, czy po jednym i drugim.
5. **Napisy** — drugi, krótki klucz obok istniejącego; skracanie mechaniczne;
   czy skrócenie samych opisów (zmienia także okno pomocy).
6. **Kiedy pasek rośnie do dwóch wierszy** — zawsze gdy okno na to pozwala, czy
   dopiero gdy podpowiedzi nie mieszczą się w jednym; oraz co ma pierwszeństwo,
   gdy o ten sam wiersz upomni się pas podglądu.
7. **Skróty modułów w stopce** — wszystkie, czy tylko modułu, którego ekran jest
   na wierzchu.
8. **Czy `F1` naprawdę zostaje ostatni** — czy w skrajnie wąskim oknie stopka
   pokazuje sam `F1`, czy sam element z ogniskiem.

## Kryteria ukończenia

- Przeniesienie ogniska (`Tab` w przeglądarce, `Tab` w opisie pliku, kursor
  w ustawieniach) **zmienia treść stopki w tej samej klatce**.
- Otwarcie okna nakładanego wypiera ze stopki wiązania ekranu.
- Każde wiązanie pokazane w stopce jest obsłużone przez `handle()` miejsca,
  którego dotyczy — sprawdza to **jeden test dla wszystkich ekranów i położeń
  ogniska**, nie test na ekran.
- Żadne wiązanie nie pochodzi z napisu w katalogu — źródłem pozostaje
  `KeyBinding`.
- Stopka nigdy nie zasłania komunikatu i nigdy nie urywa pozycji w połowie słowa.
- Wzorce tekstowe i obrazowe przeliczone we **wszystkich trzech torach**; pomiar
  „przed i po” wykonany na zwolnionej maszynie i zapisany w `docs/pomiary/`.
- Okno pomocy nadal pokazuje **pełny** spis i nie zdublowało logiki stopki.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
