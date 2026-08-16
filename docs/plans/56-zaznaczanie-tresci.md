# Krok 56 — Zaznaczanie treści: klatka uczy się mówić, co na niej pisze

> **Skąd ten krok.** Powstał 2026-08-16 z podziału zarysu Fazy XIX
> ([00-decyzje.md](00-decyzje.md), D95 nr 9). Zarys nosił tytuł całej fazy
> i mieścił trzy mechanizmy; rozstrzygnięcie nr 3 (mysz w komplecie) rozbiło go
> na trzy kroki. Ten bierze drugi mechanizm — ten, który w tej aplikacji jest
> trudniejszy niż gdzie indziej, i to z powodu, który jest jej podstawą.

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D95 (nr 4 i nr 9 wraz z jawnym wyjątkiem od reguły 13).

## Cel

Przeciągnięcie myszą zaznacza **treść klatki**, a nie piksele obrazu: widać
prostokąt zaznaczenia, a aplikacja wie, jaki tekst się pod nim znajduje.

Miarą powodzenia jest zdanie: **przeciągnięcie przez pięć wierszy listy plików
zaznacza dokładnie te pięć wierszy we wszystkich trzech torach, a odczytana
z zaznaczenia treść jest tym samym napisem, który widać na ekranie.**

## Dlaczego to jest trudne akurat tutaj

**Klatka jest bitmapą.** Sixel (kroki 08 i 13) wypycha do terminala obraz, więc
zaznaczenie myszą po stronie terminala łapie **piksele**, a użytkownik dostaje
bezużyteczny prostokąt. Tor okienkowy (krok 35) ma to samo: rysuje prymitywy
w OpenGL, a nie tekst, który system umiałby zaznaczyć. Zaznaczanie musi więc
zrobić **aplikacja sama**, znając własne prymitywy — czyli jest funkcją rdzenia,
a nie ustawieniem terminala.

Wyjątkiem jest tor tekstowy, w którym terminal umiałby to za darmo — ale krok 55
włączył tam raportowanie myszy jednolicie z pozostałymi (D95 nr 7), więc i tam
zaznacza aplikacja. Natywne zaznaczanie zostaje osiągalne pod `Shift`em
(XTerm i większość terminali omija wtedy raportowanie aplikacji) oraz po
wyłączeniu myszy w ustawieniach.

**Dobra wiadomość jest w kodzie od kroku 18 i nikt jej dotąd nie potrzebował:**
`TextFrameRenderer::composeBuffer()` zamienia prymitywy klatki w **siatkę
znaków** (`CellBuffer`). Warstwa tekstowa klatki nie jest więc nowym pomysłem,
tylko rachunkiem, który stoi w rendererze tekstowym i którego dwa pozostałe tory
nie mają jak zawołać.

## Zastrzeżenie startowe — funkcja bez odbiorcy, przyjęta świadomie

**Ten krok dowozi zaznaczenie, którego nie ma gdzie skopiować.** Schowek
przychodzi dopiero w kroku 57, więc między jednym a drugim aplikacja ma
zaznaczanie, które widać i które nie robi nic — czyli **jawny wyjątek od reguły
13** („żaden komponent nie powstaje bez prawdziwego użytkownika w aplikacji").

Wyjątek jest przyjęty **za zgodą użytkownika**, z pełną ceną wypisaną przed
wyborem (D95 nr 9): odrzucona została zamiana kolejności kroków 56 i 57, która
usuwała problem bez żadnego kosztu — schowek wykonany wcześniej miałby odbiorcę
w tym, co już istnieje (ścieżka wpisu pod kursorem, nazwy zaznaczonych wpisów
z kroku 43). Precedens jest jeden: `ProgressBar` z kroku 23, którego prawdziwy
odbiorca przyszedł dwa kroki później. Zapis SKILL.md przy tamtym wyjątku mówi
wprost, że **nie jest precedensem** i że następny wymaga takiej samej jawnej
zgody — ta zgoda padła.

Dług jest przez to nazwany i ma właściciela: **spłaca go krok 57**, a do tego
czasu zaznaczenie jest niekompletne i tak ma być opisane w statusie kroku.

## Stan zastany (sprawdzony w kodzie 2026-08-16)

| Element | Stan |
|---|---|
| `Infrastructure/Rendering/TextFrameRenderer` | `composeBuffer(Frame, Theme, rows, columns): CellBuffer` — prymitywy na siatkę znaków wraz z kolorami. Jedyny taki rachunek w projekcie. |
| `Infrastructure/Rendering/CellBuffer` | `put()`, `write()`, `paint()`, `toAnsi()`. **Odczytu nie ma** — bufor umie tylko przyjąć i oddać ANSI. |
| `Application/Ui/Primitive/TextRun` | Niesie wiersz, kolumnę, napis i rolę — czyli wszystko, czego trzeba, żeby złożyć siatkę znaków bez renderera. |
| `Application/Ui/Primitive/TextMark` | Napis **na własnym tle** (krok 30, reguła 11k) — gotowy kształt do rysowania zaznaczenia. |
| `Application/Ui/Frame` / `Plane` | Klatka to lista płaszczyzn; płaszczyzna niesie identyfikator, prostokąt, prymitywy i znacznik nieprzezroczystości. Czwarta płaszczyzna nie wymaga zmiany kontraktu. |
| `Presentation/Ui/ScrollWindow`, `SectionState`, `SplitState`, `TreeState` | Cztery klasy pamiętające coś między klatkami — wzorzec dla piątej. |
| Prymitywy | **Siedem** klas implementuje `Primitive`: `TextRun`, `TextMark`, `RoundRect`, `Bar`, `Bitmap`, `Scrollbar`, `CornerBrackets`. Słownik zamknięty (11k), trzech tłumaczy (11h). |
| Reguła 11k w `SKILL.md` | Mówi o **ośmiu** kształtach — a implementacji jest siedem (sprawdzone `grep -l "implements Primitive"`). Liczba rozjechała się z kodem i krok ma ją przy okazji sprostować; sam zapis reguły („zanim dołożysz kształt, sprawdź, czy nie jest którymś z istniejących pod inną nazwą") zostaje w mocy. |

## Zależności

- **Krok 55** całkowicie: `PointerEvent`, rozpoznanie przeciągnięcia
  (`PointerGestures`) i `AcceptsPointer`. Bez nich nie ma czym zaznaczać.
- **Kroki 08, 13 i 35** — trzy tory rysowania; to one są powodem, dla którego
  zaznaczanie musi zrobić aplikacja.
- **Krok 18** — prymitywy jako to, co zostaje z komponentu, i płaszczyzny jako
  to, z czego składa się klatka.
- **Krok 30** — `TextMark`: napis na własnym tle, czyli kształt, którym da się
  narysować zaznaczenie **bez otwierania słownika prymitywów**.
- **Krok 38** — wzorce i przebiegi; porównanie zrzutów jest tu jedynym uczciwym
  sprawdzianem, bo zaznaczenie jest rzeczą **widoczną**.

Do kroku **57** ma zależność w drugą stronę: schowek bierze zaznaczenie jako
źródło treści.

## Model i wysiłek

**Opus / xhigh.** Warunek `Fable` z przypisów ¹ i ² **nie zachodzi**: słownik
wejścia nie rośnie (wskaźnik wszedł w kroku 55), a słownik prymitywów zostaje
zamknięty — zaznaczenie rysuje się `TextMark`ami, czyli ósmym kształtem z kroku
30, więc trzej tłumacze zostają nietknięci.

Wysiłek trzyma co innego: **rachunek na ścieżce klatki**. Warstwa tekstowa jest
drugim przejściem po prymitywach i musi być liczona wyłącznie wtedy, gdy jest
zaznaczenie — a to znaczy, że pomiar „przed i po" jest tu warunkiem, a nie
formalnością.

## Zakres

### 1. Warstwa tekstowa klatki

Rachunek „prymitywy → siatka znaków" wychodzi z `TextFrameRenderer` do
**wspólnego miejsca** i przestaje być własnością jednego toru. Miejscem jest
`Application/Ui/FrameText`, bo prymitywy mieszkają w `Application/Ui/Primitive`,
a rachunek nie zna ani terminala, ani okna, ani motywu.

Renderer tekstowy **nakłada na niego kolory** i zostaje tym, czym był — to jest
przy tym jedyny fragment tego kroku, który dotyka gorącej ścieżki toru
tekstowego, więc rozlicza się osobnym pomiarem osi `--text`. Wariant „drugi,
uproszczony rachunek obok istniejącego" jest odrzucony z góry: dwie kopie
odwzorowania prymitywu na znak rozjechałyby się przy pierwszym nowym kształcie,
a rozjazd byłby niewidoczny — zaznaczenie oddawałoby inny tekst niż ten na
ekranie.

`FrameText` odpowiada na dwa pytania: **jaki znak stoi w komórce** i **jaki
napis stoi w prostokącie** (wiersz po wierszu, z obcięciem spacji na końcach —
bo klatka jest wyrównana i bez obcięcia każdy skopiowany wiersz kończyłby się
smugą spacji).

### 2. Zaznaczenie jako stan żyjący między klatkami

`Presentation/Ui/SelectionState` — piąta klasa w rodzinie `ScrollWindow`,
`SectionState`, `SplitState`, `TreeState`, z tą samą regułą własności: komponent
jest bezstanowy, więc zaznaczenie mieszka obok niego. Właścicielem jest **rdzeń**
(`LoopState`), a nie ekran — i to jest różnica wobec ogniska: zaznaczenie
przecina panele, ekrany i okna nakładane, bo dotyczy **klatki**, a nie treści
któregokolwiek z nich.

Trzyma komórkę początkową, komórkę bieżącą i znacznik trwania przeciągnięcia.
Kasuje się **przy każdej zmianie ekranu, otwarciu okna i zmianie rozmiaru
okna** — zaznaczenie wskazujące miejsce, którego już nie ma, kłamie.

### 3. Zaznaczenie jest prostokątne

Nie „przepływowe" (od punktu do punktu, z całymi wierszami pośrodku), tylko
**blokowe** — i to nie jest uproszczenie, tylko dopasowanie do tego, czym jest
ta klatka. Ekran składa się z paneli stojących obok siebie: zaznaczenie
przepływowe przeciągnięte przez listę plików zabrałoby ze sobą obwódkę panelu
sąsiedniego, jego treść i pasek stanu. Prostokąt bierze dokładnie to, co
użytkownik obrysował.

Ceną jest wiersz dłuższy od panelu, który zaznaczy się do szerokości panelu,
a nie do końca treści. Cena jest widoczna i przyjęta; przewijanie w trakcie
zaznaczania **nie wchodzi do zakresu** (punkt „Poza zakresem").

### 4. Kiedy przeciągnięcie zaznacza, a kiedy nie

Rozróżnia je **ruch, a nie modyfikator**: samo kliknięcie stawia kursor (krok
55), a naciśnięcie z przesunięciem o co najmniej jedną komórkę zaczyna
zaznaczanie. Modyfikator odpada z dwóch powodów naraz — `Shift`+przeciągnięcie
jest w terminalach ucieczką do zaznaczania natywnego (i ma nią zostać), a `Alt`
w torze terminalowym jest nieodróżnialny od `Esc` naciśniętego tuż wcześniej
(reguła 11j).

Wyjątkiem jest **granica podziału**: przeciągnięcie zaczęte na niej należy do
kroku 55 i zaznaczenia nie zaczyna. Pierwszeństwo rozstrzyga się w jednym
miejscu (`InputHandler`), a nie w każdym ekranie z osobna.

### 5. Jak zaznaczenie widać

Czwarta płaszczyzna klatki — `selection` — składana **wyłącznie wtedy, gdy
zaznaczenie istnieje**, między treścią a oknem nakładanym. Rysuje się
`TextMark`ami: znak wzięty z warstwy tekstowej, tło z roli motywu. To ten sam
chwyt, którym karetka `TextInput` z kroku 19 udała podświetlenie parą
istniejących prymitywów, i którym `TextMark` z kroku 30 związał pismo z tłem —
słownik prymitywów **zostaje zamknięty**.

Rola motywu jest nowa (`Role::Selection`) i dotyczy wszystkich trzech
tłumaczy — ale rola nie jest kształtem, więc reguła 11k nie zachodzi.

### 6. Kursor tekstowy w oknie

`glfwCreateStandardCursor(GLFW_IBEAM_CURSOR)` — kształt kursora zmienia się nad
treścią, którą da się zaznaczyć. Wchodzi tutaj, a nie w kroku 55, bo dopiero
tutaj ma co oznaczać. Tory terminalowe nie mają czym tego zrobić i **nie
udają**, że mają.

### 7. Napisy, pomiar, przebiegi

- **Napisy:** zdanie o liczbie zaznaczonych wierszy w pasku stanu (jedyne, co
  krok mówi użytkownikowi, bo skopiować jeszcze nie ma jak).
- **Pomiar:** trzy osie, nie jedna. `--text` — bo rachunek warstwy tekstowej
  wychodzi z renderera tekstowego i wraca do niego przez nowe miejsce.
  `--loop` — bo klatka z zaznaczeniem składa czwartą płaszczyznę. Porównanie
  zrzutów (`--compare`) — bo zaznaczenie jest **widoczne**, a to jedyny
  sprawdzian, który to potwierdzi w trzech torach naraz. Wzorzec odniesienia:
  po kroku 55.
- **Przebiegi:** złożenie warstwy tekstowej z klatki z **każdym kształtem
  słownika**, odczyt prostokąta wraz z obcięciem spacji, zaznaczenie przez
  granicę panelu, kasowanie zaznaczenia przy zmianie ekranu i rozmiaru okna,
  rozróżnienie kliknięcia od przeciągnięcia, pierwszeństwo granicy podziału.

### 8. Dokumentacja

`docs/architecture.md` — warstwa tekstowa klatki jako drugie oblicze tego
samego rachunku, zaznaczenie jako stan rdzenia i powód, dla którego jest
prostokątne. `SKILL.md` — reguła o warstwie tekstowej („klatka umie oddać swoją
treść jako znaki; rachunek jest jeden i mieszka w `Application/Ui`") wraz
z zapisem jawnego wyjątku od reguły 13 i jego terminem spłaty.

## Poza zakresem

- **Kopiowanie zaznaczenia do schowka** — krok 57. To jest właśnie ten dług.
- **Zaznaczanie przepływowe** (od punktu do punktu z całymi wierszami) — punkt 3
  wraz z powodem.
- **Przewijanie w trakcie zaznaczania** (zaznaczenie sięgające poza widoczną
  część listy) — zaznaczenie dotyczy **klatki**, a nie treści pod nią; sięganie
  poza widok wymagałoby zaznaczenia w pojęciach ekranu, czyli innego mechanizmu.
- **Zaznaczanie klawiaturą** — `Shift`+strzałki znaczą od kroku 44 zaznaczanie
  wpisów i drugiego znaczenia nie dostaną.
- **Zaznaczanie prostokątne w podglądzie tekstu jako funkcja modułu** — osobna
  rzecz; ten krok zaznacza klatkę, nie treść pliku.
- **Podświetlanie znalezionego tekstu** — to `TextMark` w rękach filtra
  (krok 30), nie zaznaczenie.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Ui/FrameText.php` | Application | Nowe — prymitywy na siatkę znaków; znak w komórce i napis w prostokącie. |
| `Application/Ui/Role.php` | Application | `Selection` — rola tła zaznaczenia. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | `composeBuffer()` stoi na `FrameText` i dokłada kolory. |
| `Infrastructure/Rendering/SixelFrameRenderer.php`, `OpenGlFrameRenderer.php` | Infrastructure | Nowa rola w tabeli motywu (kształtów nie przybywa). |
| `Presentation/Ui/SelectionState.php` | Presentation | Nowe — początek, koniec, znacznik przeciągania, kasowanie. |
| `Presentation/Cli/LoopState.php` | Presentation | Właściciel zaznaczenia; kasowanie przy zmianie ekranu, okna i rozmiaru. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Czwarta płaszczyzna — wyłącznie gdy zaznaczenie istnieje. |
| `Presentation/Cli/InputHandler.php` | Presentation | Przeciągnięcie: pierwszeństwo granicy podziału, potem zaznaczanie. |
| `Infrastructure/Glfw/GlfwWindowService.php` | Infrastructure | Kursor `I-beam` nad treścią. |
| `lang/pl.php`, `lang/en.php` | Napisy | Zdanie o liczbie zaznaczonych wierszy. |
| `tests/Unit/FrameTextTest.php`, `tests/Functional/SelectionFlowTest.php` | Testy | Punkt 7. |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Punkt 8. |

## Kryteria ukończenia

- **Przeciągnięcie zaznacza dokładnie te wiersze, przez które przeszło** — we
  wszystkich trzech torach, sprawdzone porównaniem zrzutów.
- **Odczytana treść zaznaczenia jest tym samym napisem, który widać** — pilnuje
  tego przebieg składający klatkę z każdego kształtu słownika prymitywów.
- **Zaznaczenie kasuje się** przy zmianie ekranu, otwarciu okna nakładanego
  i zmianie rozmiaru okna.
- **Kliknięcie nadal stawia kursor** — rozróżnienie idzie ruchem, nie
  modyfikatorem, a granica podziału ma pierwszeństwo.
- **Słownik prymitywów zostaje zamknięty** — zaznaczenie rysuje się `TextMark`ami;
  jeśli okaże się to niewykonalne, jest to wynik do zapisania w dzienniku
  i zgoda użytkownika na dziewiąty kształt, a nie cichy dopisek.
- **Warstwa tekstowa nie kosztuje w klatce bez zaznaczenia** — oś `--loop`
  bez regresji; oś `--text` bez regresji mimo wyniesienia rachunku.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
