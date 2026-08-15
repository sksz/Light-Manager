# Krok 47 — Spłata długów: komenda otwiera okno, strefa bez odbiorcy, zakładka bez przewijania

> **Skąd ten krok.** Powstał 2026-08-14 na żądanie użytkownika, zaraz po
> zamknięciu kroku 41. Zbiera trzy długi, które **nie mają właściciela** w żadnym
> zaplanowanym kroku: zobowiązanie kroku 41 wobec kroku 32 (D75, rozstrzygnięcie
> 5), skutek uboczny D76 (trzecia strefa została bez odbiorcy) i defekt zauważony
> w kroku 29, który do dziś leży w „Zakresie poza MVP”. Długi z właścicielem do
> tego kroku **nie wchodzą** — szerokość okna liczona z długości napisu należy do
> kroku 42, autostart muzyki do kroku 45, skala treści czeka na sprzęt, nie na
> krok.

## Status

**Ukończony** (2026-08-14). Trzy długi spłacone w całości, bez zastrzeżeń.
Rozstrzygnięcia startowe — dziewięć, z czego jedno przerysowuje granicę z D69,
a jedno odwraca opis długu w tym pliku — leżą w [00-decyzje.md](../00-decyzje.md),
D78.

## Ustalenia (decyzje użytkownika, 2026-08-14)

| Pytanie | Wybór |
|---|---|
| **1** — droga komendy do okna | **Zdolność `OpensOverlay`** w `Presentation/Ui/Command`, deklarowana osobno wzorem `AppliesToSelection` i `RunsWork`. Bez rejestru, bez identyfikatorów okien, `Application` nietknięty |
| **2** — pozycje w menu `F9` | **Wszystkie trzy czynności kroku 41** — a granica z D69 dostaje nowe brzmienie: menu pokazuje czynności zmieniające **zawartość miejsca**, nie **sposób oglądania** |
| **3** — argument `browser.delete` | **Opcjonalny** — bez niego wpis pod kursorem, z nim wskazany po sprawdzeniu, że istnieje |
| **4** — trzecia strefa | **Wychodzi z kontraktu** — `preview()` znika z `ScreenInterface` |
| **5** — próg dwuwierszowej stopki | **28 → 20**, czyli przesunięty o wysokość zniesionej strefy; argument kroku 40 zostaje dosłownie ten sam |
| **6** — scenariusz `thumbnail` | **Przenosi się do panelu modułu `FileInfo`** (`PreviewPane`, ten sam `ImageBox`) |
| **7** — przewijanie ustawień | **Pasek zakładek nieruchomy**, przycisk „przywróć domyślne” przewijany jako ostatnia pozycja, położenie pamiętane osobno na zakładkę |
| **8** — klawisze przewijania | **Sześć**: strzałki, `PageUp`/`PageDown`, `Home`/`End` — słownik wejścia zna wszystkie, trzy tory zostają nietknięte |
| **9** — kolejność wobec kroku 42 | **Potwierdzona: 47 przed 42** |

**Dwie rzeczy, które sprawdzenie kodu zmieniło w treści tego pliku:**

1. **Opis długu A był nieścisły.** Granica warstw nigdy nie była przeszkodą —
   wszystkie komendy leżą w `Presentation`, w `Application` leży sam kontrakt,
   a rozdzielał je wyłącznie `CommandOutcome` stojący pośrodku. Rozwiązanie jest
   przez to o dwa pojęcia tańsze, niż plan zakładał.
2. **Pytanie 8 zakładało koszt, którego nie ma.** `Application\Dto\Key` zna
   `Home`, `End`, `PageUp` i `PageDown` od dawna; zdanie „których słownik wejścia
   dziś nie zna” było nieprawdziwe.

## Cel

Trzy rzeczy, które aplikacja obiecała i nie dowiozła, przestają być obietnicami.
Nowej funkcji krok **nie dokłada ani jednej** — i to jest jego właściwość, a nie
skromność zakresu.

Miarą powodzenia są trzy zdania, z których dziś żadne nie jest prawdziwe:
**usunięcie da się wywołać z menu `F9`, a nie tylko klawiszem**; **żaden
mechanizm rdzenia nie stoi bez odbiorcy**; **zakładka ustawień dłuższa od okna
przewija się, zamiast gubić pozycje**.

## Dlaczego te trzy razem, a nie każdy przy okazji

Bo „przy okazji” już było i właśnie dlatego są trzy. Dług A czekał na krok 41,
który go **zaciągnął, a nie spłacił**; dług C czeka od kroku 29 na „jakąś
rozbudowę ustawień”, a rozbudowa ustawień nigdy nie jest osobnym krokiem; dług B
powstał tydzień temu wraz z decyzją, która wprost mówi: „do rozstrzygnięcia przy
następnym module, który zechce strefę skrajną” — a takiego modułu w planie nie
ma.

Wspólne mają jedno i to jest powód, dla którego stoją w jednym kroku: **wszystkie
trzy są niezapłaconą ceną decyzji, które zapadły świadomie**. Żaden nie jest
usterką do naprawienia po cichu — każdy wymaga rozstrzygnięcia użytkownika,
i dlatego zbiera je krok planu, a nie doraźna poprawka.

Kolejność wykonania wewnątrz kroku jest przy tym **wiążąca: A, potem B, potem
C** — A dotyka kontraktu komendy i okien, B kontraktu ekranu wraz ze wszystkimi
wzorcami pomiarowymi, C jednego ekranu. Odwrotna kolejność znaczy przeliczanie
wzorców dwa razy.

## Dlaczego przed krokiem 42, choć numer ma wyższy

Numer bierze się z chronologii planu, kolejność wykonania — nie. Krok idzie
**zaraz po 41**, przed 42, i powód jest mechaniczny: kroki 42 i 43 dokładają
kolejne czynności na plikach, a każda z nich zechce pozycji w menu i okna
wywołanego z komendy. Spłacony dług A obsłuży je **za darmo**, niespłacony każe
przerabiać to samo miejsce trzy razy — raz po kroku 41, raz po 42 i raz po 43.

Ten sam rachunek dotyczy długu B: wzorce pomiarowe w trzech torach przelicza się
raz, a nie po każdym kroku Fazy XIV.

## Dług A — komenda nie umie otworzyć okna

**Skąd.** Krok 32 zostawił zobowiązanie: czynność kroku 41 miała zadeklarować
`AppliesToSelection` i pojawić się w menu **bez zmiany w rdzeniu**. Krok 41
sprawdził to i odbił się od granicy warstw z D39: `CommandOutcome` leży
w `Application` i wskazuje ekran **identyfikatorem**, bo `ScreenInterface` leży
w `Presentation`. Dla okien nakładanych nie ma nawet tego — `InputHandler`
tłumaczy identyfikator wyłącznie na ekran.

**Co z tego wynika w aplikacji.** `browser.delete` nie istnieje wcale (usuwać bez
pytania nie wolno, a pytania komenda nie postawi), `browser.rename`
i `browser.mkdir` istnieją **tylko z argumentem wpisanym w oknie komend**,
a menu `F9` nie ma ani jednej operacji na plikach — pokazuje cztery pozycje
z kroku 32 i tyle.

**Gdzie leży trudność, której nie widać z opisu.** Ekran otwiera okno
**obiektem** (`ScreenOutcome::opens(OverlayInterface)`), bo leży z nim w jednej
warstwie. Okno powstaje przy tym z **argumentów i domknięcia**: `ConfirmOverlay`
dostaje pytanie i to, co zrobić po „tak”, `PromptOverlay` treść początkową
i odbiorcę nazwy. Rejestr gotowych okien — odpowiednik listy ekranów
w `InputHandler::openById()` — **nie wystarczy**, bo okno bez kontekstu
zaznaczenia nie ma o co zapytać. Identyfikator okna w kontrakcie już zresztą
jest (`OverlayInterface::id()`), tylko służy do czego innego: nazywa płaszczyzną
w `FrameComposer`.

**Reguła, od której nie ma odstępstwa:** komenda i klawisz mają prowadzić
w **to samo miejsce**. Wzorzec istnieje i nazywa się `EntryOperations`
(krok 41, wzorem `HiddenEntries` z kroku 32) — druga droga do usuwania byłaby
gorsza niż brak pierwszej.

## Dług B — trzecia strefa została bez odbiorcy

**Skąd.** D76 wyprowadził pas podglądu z przeglądarki i zapisał skutek uboczny
wprost: mechanizm zostaje, użytkownika nie ma. Sprawdzenie w kodzie potwierdza to
co do znaku — **`preview()` oddaje `null` w każdym ekranie aplikacji**:
`BrowserScreen`, `FileInfoScreen`, `SettingsScreen`, `HelpScreen`
i `LoopScenarioScreen`. Jedynym miejscem, które jeszcze strefę zamawia, jest
**scenariusz pomiarowy** — czyli narzędzie mierzy kawałek klatki, którego
aplikacja nie rysuje.

To jest złamanie reguły 13 („nic bez prawdziwego odbiorcy”) w rdzeniu, a nie
w module — i dlatego nie wolno go zostawić bez decyzji.

**Czego usunięcie dotknie — i tu jest niespodzianka.** Nie jednej stałej.
`ROWS_FOR_STATUS_LINES` z kroku 40 jest **wyprowadzony** z progu podglądu
(`ROWS_FOR_PREVIEW + 2`), a jego uzasadnienie brzmi: „przy `ROWS_FOR_PREVIEW`
lista właśnie oddała podglądowi osiem wierszy, zabranie jej dziewiątego
odwracałoby powód, dla którego tamten próg istnieje”. Znika podgląd — znika
całe to zdanie, a próg paska stanu potrzebuje **nowego** uzasadnienia, nie nowej
liczby.

**Czego nie wolno stracić przy okazji.** Scenariusz `thumbnail` jest jedynym,
który mierzy koszt dekodowania obrazu i zamiany go na prymityw obrazu w trzech
torach naraz. Miniatura **nadal jest w aplikacji** — rysuje ją `PreviewPane`
modułu `FileInfo` tym samym komponentem `ImageBox`. Scenariusz ma więc zmienić
miejsce, a nie zniknąć; zniknięcie zaślepiłoby pomiar ścieżki obrazu.

## Dług C — zakładka ustawień nie przewija się

**Skąd.** Zauważone przy kroku 29, gdy zakładka `file-info` urosła do dziesięciu
pozycji; dziś ma ich **jedenaście**. Zapisane w „Zakresie poza MVP” i od tamtej
pory nietknięte.

**Mechanizm.** `SettingsScreen::draw()` składa zakładkę z `VStack`u, w którym
każda pozycja to `Slot::fixed(..., 1)`. `Distribution` przydziela wiersze od
góry, a szczelina, która wiersza nie dostała, **nie rysuje się wcale** — nie ma
ani przycięcia, ani śladu, ani wielokropka. Pozycja po prostu nie istnieje.

**Rachunek, który mówi, że to nie jest problem teoretyczny.** Zakładka
`file-info` to jedenaście pozycji plus pasek zakładek, odstęp, odstęp i przycisk
„przywróć domyślne” — piętnaście wierszy. W oknie o 22 wierszach strefa środkowa
dostaje szesnaście, panel zjada obwódką dwa, zostaje czternaście: **przycisk
znika bez śladu**. Poniżej znikają pozycje. Od kroku 33 rozmiar okna nie jest
stałą uruchomienia, więc wystarczy, że użytkownik zmniejszy okno.

**Wzorzec jest w projekcie i to dwa razy.** `HelpScreen` przewija treść
`ScrollWindow`em (strzałki, `help.key.scroll`), a `ScrollWindow::useContext()`
umie pamiętać położenie **osobno dla każdego kontekstu** — dokładnie tak, jak
`SectionState` z kroku 22 pamięta zwinięcie pod kluczem. Zakładka jest tym samym
problemem: pięć zakładek, pięć niezależnych położeń.

## Zależności

- **Krok 41** — twardo dla długu A: to on go zaciągnął i to jego `EntryOperations`
  jest jedynym miejscem, w które komenda ma trafić.
- **Krok 32** — `MenuOverlay`, `AppliesToSelection` i reguła „menu jest widokiem
  na rejestr komend, a nie drugą listą działań” (D69). Odbiorcą długu A jest
  właśnie menu.
- **Krok 19** — kontrakt komendy, `CommandOutcome`, `CommandArgument`
  i `InputHandler::openById()`, czyli **jedyne dziś tłumaczenie identyfikatora na
  obiekt**. Krok dokłada do niego drugie albo dowodzi, że nie trzeba.
- **Krok 18** — `OverlayInterface`, `ScreenInterface`, `ScreenOutcome::opens()`
  i reguła 10; wszystkie trzy długi leżą w tym, co tam powstało.
- **Krok 40** — dla długu B twardo i nieoczywiście: próg dwuwierszowego paska
  stanu jest wyprowadzony z progu pasa podglądu, więc usunięcie strefy **wraca
  do rachunku tamtego kroku**.
- **Krok 21** — zasada „strefa niezamówiona oddaje wiersze środkowi”, na której
  stoi dzisiejsza poprawność `null`a.
- **Kroki 12, 13 i 25** — pochodzenie pasa podglądu i miejsce, w którym miniatura
  została (`PreviewPane`, `ImageBox`).
- **Krok 14** — ekran ustawień; **krok 29** — miejsce, w którym dług C
  zauważono; **krok 22** — wzorzec pamięci przewijania pod kluczem;
  **krok 33** — od niego dług C jest osiągalny bez zmiany terminala.
- **Krok 38** — wzorce i przebiegi w trzech torach; dług B przelicza je wszystkie.

Od kroków **31, 34–37 i 39** nie zależy i one nie zależą od niego. Kroki **42, 43
i 44** zależą od niego **miękko, ale realnie**: wykonany wcześniej daje ich
czynnościom drogę do menu za darmo (patrz sekcja o kolejności).

## Model i wysiłek

**Fable / xhigh** — wybrane **po** rozstrzygnięciu nr 4, zgodnie z przypisem przy
tabeli Fazy XVI.

Kodu nie jest dużo, ale dwa z trzech długów dotykają **kontraktów rdzenia** —
komendy i ekranu — a kontrakt zmieniony źle wraca w każdym następnym kroku.
Rozstrzygnięcie 4 (strefa wychodzi z `ScreenInterface`) dokłada do tego
`HudLayout`, `FrameComposer`, `ScenarioFactory` oraz przeliczenie osiemnastu
scenariuszy w trzech torach wraz ze złotymi klatkami i wzorcami PNG — czyli
dokładnie ten warunek, dla którego przypis przewidywał wyższy model.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Command/CommandOutcome` | `done` / `stay` / `opens(screenId)` / `quit`; ekran **identyfikatorem**, okna nie zna wcale. |
| `Presentation/Cli/InputHandler::openById()` | Jedyne tłumaczenie napisu na obiekt — przeszukuje pomoc, ustawienia i moduły. |
| `Presentation/Ui/OverlayInterface::id()` | Identyfikator **istnieje**, ale służy jako nazwa płaszczyzny w `FrameComposer`. |
| `Presentation/Ui/ScreenOutcome::opens()` | Ekran otwiera okno **obiektem** — obie klasy leżą w `Presentation`. |
| `Presentation/Ui/OverlayOutcome` | `closes`, `quits`, `message`, `screenId` oraz `next` (`replace()` z kroku 41) — okno otwiera okno, mając już jego obiekt. |
| `AppliesToSelection` | Deklarują cztery komendy: `browser.hidden`, `browser.open`, `browser.tree`, `file-info.show`. `RenameCommand` **świadomie nie deklaruje** (komentarz w klasie). |
| `Module/Browser/…/EntryOperations` | Trzy czynności; jedno miejsce dla klawisza i komendy — cel, w który ma trafić menu. |
| `ScreenInterface::preview()` | **Wszystkie pięć ekranów oddaje `null`.** |
| `HudLayout` | `ROWS_FOR_PREVIEW = 26`, `PREVIEW_INNER_ROWS = 6`, `ROWS_FOR_STATUS_LINES = ROWS_FOR_PREVIEW + 2`, `previewIsPanel()`. |
| `Infrastructure/Diagnostics/ScenarioFactory` | `withPreview` włączane wyłącznie dla `Scenario::Thumbnail`; `ImageBox` rysowany w strefie podglądu. |
| `Module/FileInfo/…/PreviewPane` | Ten sam `ImageBox` **w panelu modułu** — miniatura żyje tutaj. |
| `tests/Golden/` | Osiemnaście złotych klatek; `docs/pomiary/wzorce-png/` — po jednym PNG na scenariusz i tor. |
| `Presentation/Cli/Screen/SettingsScreen::draw()` | `VStack` ze `Slot::fixed(..., 1)` na pozycję; **żadnego `ScrollWindow`**. |
| `Presentation/Ui/ScrollWindow` | `useContext()`, `keepVisible()`, `position()` (pasek przewijania) — gotowe; używa go `HelpScreen`. |
| Liczba pozycji zakładek | Rdzeń 9, `file-info` 11, `browser` 7, `audio` 4. |

## Zakres

### 1. Komenda dostaje drogę do okna (dług A)

Rdzeń zyskuje **jedną** drogę, którą komenda zamawia okno, i ta droga ma
przechodzić przez granicę warstw bez naginania jej: `Application` nie zobaczy
`OverlayInterface`, tak jak dziś nie widzi `ScreenInterface`.

Kształt jest pytaniem 1 i ma trzy warianty z ceną:

1. **Rejestr wytwórni okien w `Presentation`** — komenda oddaje identyfikator,
   `InputHandler` tłumaczy go na wytwórnię, wytwórnia dostaje kontekst
   zaznaczenia i buduje okno. Symetryczne do `openById()`; kosztuje nowe pojęcie
   (wytwórnia) i miejsce, w którym rejestruje się okna.
2. **Okno opisane danymi** — `CommandOutcome` niesie żądanie: rodzaj (pytanie
   albo pole tekstowe), klucze napisów, wariant `dangerous`. `Presentation`
   składa z tego `ConfirmOverlay` albo `PromptOverlay`. Bez rejestru, za to
   `Application` zyskuje słownik okien, którego dziś nie ma.
3. **Brakujący argument prosi o siebie sam** — rejestr wie, że `browser.rename`
   ma argument `CommandArgumentKind::Text`, więc wywołanie bez argumentu otwiera
   `PromptOverlay` na ten argument. Nie dokłada **żadnego** nowego pojęcia
   i obsługuje dwie istniejące komendy od ręki; nie obsłuży natomiast pytania
   przed usunięciem, bo pytanie nie jest argumentem.

Rekomendacja: **wariant 1**, bo jako jedyny obsługuje wszystkie trzy przypadki
(pytanie, pole, łańcuch okien usuwania) tą samą drogą, a łańcuch okien to jest
dokładnie to, czego krok 41 nie umiał zrobić.

### 2. Trzy operacje wchodzą do menu `F9` (dług A, jego odbiorca)

`browser.delete` powstaje — **z pytaniem i paskiem postępu**, tą samą drogą co
klawisz `F8`, przez `EntryOperations`. `browser.rename` i `browser.mkdir`
deklarują `AppliesToSelection` i przestają wymagać argumentu wpisanego z ręki:
wywołane bez niego pytają oknem.

Menu zostaje **widokiem na rejestr** (D69) — ani jednej pozycji własnej.

### 3. Trzecia strefa dostaje rozstrzygnięcie (dług B)

Dwa warianty, oba dopuszczalne, jeden do wybrania (pytanie 4):

- **Wychodzi z kontraktu.** `ScreenInterface::preview()` znika, `HudLayout`
  traci strefę i próg, `FrameComposer` jedną płaszczyznę, a `ScenarioFactory`
  przestaje ją zamawiać. Rdzeń robi się o pojęcie mniejszy i reguła 13
  przestaje być złamana.
- **Zostaje jako zadeklarowany wyjątek** — z powodem, terminem i nazwanym
  kandydatem na odbiorcę, wpisanym do `SKILL.md` obok wyjątku od reguły 15
  z kroku 41. Bez tego wyjątek jest przemilczeniem, a nie decyzją.

W obu wariantach **próg `ROWS_FOR_STATUS_LINES` dostaje własne uzasadnienie**,
niezależne od pasa podglądu (pytanie 5), a scenariusz `thumbnail` **zostaje** —
przenosi się do miniatury w panelu modułu (pytanie 6).

### 4. Zakładka ustawień przewija się (dług C)

`SettingsScreen` dostaje `ScrollWindow` z osobnym kontekstem na zakładkę, wzorem
`HelpScreen`u i `SectionState`. Kursor zostaje widoczny (`keepVisible()`), a przy
zakładce dłuższej od okna pojawia się pasek przewijania — `ScrollWindow` umie go
policzyć (`position()`), więc powstaje wygląd, a nie mechanizm.

Pasek zakładek **nie przewija się razem z treścią**: on ma zostać widoczny
zawsze, inaczej użytkownik traci wiedzę, gdzie jest. Przycisk „przywróć
domyślne” przewija się razem z pozycjami — jest ostatnią pozycją, a nie stopką
zakładki (pytanie 7).

### 5. Napisy

Klucze do opisu nowych klawiszy przewijania w ustawieniach, nazwa i opis komendy
`browser.delete` oraz — jeśli powstanie wariant 1 z punktu 1 — tytuły
i przyciski okien zamawianych przez komendy. Wszystko przez katalogi, jak dotąd:
rdzeń do `lang/`, moduł do `src/Module/Browser/lang/`.

### 6. Pomiar

Krok **nie dokłada ani jednego prymitywu**, więc oczekiwanie jest jasne: koszt
klatki ma nie drgnąć nigdzie poza scenariuszem `thumbnail`, który zmienia
miejsce. Jeśli trzecia strefa wyjdzie z kontraktu, przeliczenia wymagają
**wszystkie** wzorce w trzech torach — bo `HudLayout` liczy strefy dla każdego
scenariusza, nie tylko dla podglądowego.

Obowiązuje reguła 17: **przed pomiarem poproś użytkownika o zwolnienie maszyny
i poczekaj na potwierdzenie.**

### 7. Wzorce, przebiegi i sprawdzenie w terminalu

- Przebieg funkcjonalny: **usunięcie wywołane z menu `F9`** przechodzi przez
  pytanie i kończy się usunięciem — na prawdziwym katalogu tymczasowym, wzorem
  `FileOperationsFlowTest` z kroku 41;
- przebieg: `browser.rename` bez argumentu otwiera okno nazwy, `Esc` nie dotyka
  dysku;
- przebieg: zakładka dłuższa od okna **pokazuje ostatnią pozycję po przewinięciu**
  — dziś nie pokazuje jej wcale;
- test pilnujący reguły 13 dla strefy podglądu: albo ma odbiorcę, albo nie ma jej
  w kontrakcie (wedle rozstrzygnięcia 4);
- złote klatki i wzorce PNG przeliczone w trzech torach;
- sprawdzenie w prawdziwym terminalu (`make run-xterm`) obowiązkowe dla długu C:
  **wysokość okna jest tu treścią sprawdzenia**, a nie tłem — po tej samej
  regule, którą krok 41 znalazł usterkę szerokości okna nazwy.

### 8. Dokumentacja

`docs/architecture.md` — droga komendy do okna (albo powód, dla którego jej nie
ma) oraz stan trzeciej strefy. `SKILL.md` — jeśli strefa zostaje, wyjątek od
reguły 13 nazwany i ograniczony, obok wyjątku od reguły 15 z kroku 41.
`README.md` — nowe pozycje menu i klawisze przewijania w ustawieniach.
`docs/pomiary/README.md` — nowe miejsce scenariusza `thumbnail`.

## Poza zakresem

- **Nowe operacje na plikach** — kopiowanie i przenoszenie to krok 42,
  zaznaczenie wielokrotne krok 43, kosz krok 44. Ten krok nie dokłada czynności,
  tylko drogę do tych, które są.
- **Okno wyboru z listą odpowiedzi** (nadpisz / pomiń / zmień nazwę) — należy do
  kolizji nazw, czyli do kroku 42.
- **Szerokość okna liczona z długości napisu** (`ConfirmOverlay`) — dług
  **z właścicielem**: krok 42 dostanie pierwszego prawdziwego odbiorcę.
- **Przewijanie innych ekranów** — pomoc przewija się od kroku 20, opis pliku od
  kroku 25; dług dotyczy ustawień i tylko ich.
- **Drugi rejestr działań** — menu zostaje widokiem na rejestr komend (D69);
  gdyby okazało się, że pozycja wymaga własnej listy, znaczy to, że
  rozstrzygnięcie 1 wybrano źle.
- **Okna nakładane wywoływane z ekranu ustawień** — pozycja tekstowa działa
  w miejscu przez `TextInput` i to jest rozstrzygnięte (krok 20, P13); ten krok
  tego nie odwraca.
- **Cykl życia modułu i zdarzenia** — mechanizmy Fazy XV; długiem nie są, bo
  mają swoje kroki.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Command/CommandOutcome.php` | Application | Droga do okna — kształt wedle rozstrzygnięcia 1. |
| `Presentation/Cli/InputHandler.php` | Presentation | Tłumaczenie żądania okna na obiekt — obok istniejącego `openById()`. |
| `Presentation/Ui/OverlayRegistry.php` | Presentation | Nowe, jeśli wygra wariant 1 — wytwórnie okien pod identyfikatorem. |
| `Presentation/Ui/Overlay/MenuOverlay.php` | Presentation | Pozycje, które otwierają okno, a nie tylko wykonują komendę. |
| `Module/Browser/Presentation/Command/DeleteCommand.php` | Moduł | Nowe — `browser.delete` prowadzące przez `EntryOperations`. |
| `Module/Browser/Presentation/Command/RenameCommand.php`, `MakeDirectoryCommand.php` | Moduł | `AppliesToSelection`; brak argumentu otwiera okno. |
| `Presentation/Ui/ScreenInterface.php` | Presentation | `preview()` znika albo zostaje z zadeklarowanym wyjątkiem (rozstrzygnięcie 4). |
| `Presentation/Ui/HudLayout.php` | Presentation | Strefa i próg podglądu; **nowe uzasadnienie progu paska stanu**. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Płaszczyzna podglądu — wedle rozstrzygnięcia 4. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | Scenariusz `thumbnail` mierzy miniaturę w panelu modułu. |
| `Presentation/Cli/Screen/SettingsScreen.php` | Presentation | `ScrollWindow` z kontekstem na zakładkę, pasek przewijania, klawisze. |
| `lang/pl.php`, `lang/en.php`, `Module/Browser/lang/*.php` | Napisy | Komenda usunięcia, klawisze przewijania, tytuły okien z komend. |
| `tests/Functional/*FlowTest.php` | Testy | Usunięcie z menu, komenda bez argumentu, zakładka po przewinięciu. |
| `tests/Golden/*.txt`, `docs/pomiary/wzorce-png/` | Testy i pomiary | Przeliczone, jeśli strefa wyjdzie z kontraktu. |
| `docs/architecture.md`, `SKILL.md`, `README.md`, `docs/pomiary/README.md` | Dokumentacja | Droga do okna, stan trzeciej strefy, nowe klawisze. |

## Do rozstrzygnięcia na starcie kroku

1. **Droga komendy do okna** — rejestr wytwórni (rekomendacja), okno opisane
   danymi, czy brakujący argument proszący o siebie.
2. **Zakres pozycji w menu** — czy wchodzą trzy operacje kroku 41, czy tylko
   usunięcie (jedyna, która dziś komendy nie ma wcale).
3. **`browser.delete` z argumentem** — czy komenda przyjmuje nazwę wpisaną
   ręcznie, czy działa wyłącznie na wpisie pod kursorem.
4. **Trzecia strefa** — wychodzi z kontraktu czy zostaje jako zadeklarowany
   wyjątek od reguły 13.
5. **Próg dwuwierszowego paska stanu** — jakie ma uzasadnienie po odejściu pasa
   podglądu; zostaje liczbowo taki sam czy zmienia wartość.
6. **Scenariusz `thumbnail`** — przenosi się do panelu modułu (rekomendacja),
   zostaje ze sztuczną strefą, czy znika wraz z nią.
7. **Przewijanie ustawień** — czy pasek zakładek zostaje nieruchomy
   (rekomendacja) i czy przycisk „przywróć domyślne” przewija się z pozycjami.
8. **Klawisze przewijania w ustawieniach** — same strzałki (kursor pociąga
   widok), czy dodatkowo `PageUp`/`PageDown`, których słownik wejścia dziś nie
   zna.
9. **Kolejność wobec kroku 42** — potwierdzenie, że krok idzie przed nim; gdyby
   miało być odwrotnie, dług A przerabia się dwa razy.

## Kryteria ukończenia

- `F9` na wpisie pokazuje usunięcie; wybranie go otwiera pytanie, a „tak” usuwa —
  tą samą drogą co `F8`, dowodzi tego przebieg funkcjonalny.
- `browser.rename` i `browser.mkdir` wywołane bez argumentu otwierają okno nazwy.
- Żadna czynność nie ma dwóch dróg do dysku — komenda i klawisz schodzą się
  w `EntryOperations`.
- Trzecia strefa albo ma odbiorcę, albo nie ma jej w kontrakcie; wybór zapisany
  wraz z powodem, a nie samą regułą.
- Próg dwuwierszowego paska stanu ma uzasadnienie, które nie powołuje się na pas
  podglądu.
- Zakładka ustawień dłuższa od okna pokazuje **każdą** pozycję po przewinięciu,
  a kursor nigdy nie wychodzi poza widok; sprawdzone w oknie o 22 wierszach
  i niższym.
- Pomiar w trzech torach bez regresji, wykonany na zwolnionej maszynie;
  scenariusz `thumbnail` mierzy miniaturę tam, gdzie aplikacja ją rysuje.
- Wygląd obejrzany w prawdziwym terminalu — przewijanie ustawień i menu
  z operacjami.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-14 — rozstrzygnięcia startowe i cały kod kroku

**Opis długu A w tym pliku był nieścisły i sprawdzenie kodu to odwróciło.**
Granica warstw nigdy nie była przeszkodą: **wszystkie** komendy w projekcie —
rdzenia i modułów — leżą w `Presentation`, a w `Application` leży sam kontrakt.
Rozdzielał je wyłącznie `CommandOutcome` stojący pośrodku rozmowy, której obie
strony są w tej samej warstwie. Rozwiązanie wyszło przez to o dwa pojęcia tańsze,
niż plan zakładał: zdolność `Presentation\Ui\Command\OpensOverlay` o jednej
metodzie, deklarowana osobno jak `AppliesToSelection` i `RunsWork`, zamiast
rejestru wytwórni pod identyfikatorem.

**Co powstało**

| Plik | Rola |
|---|---|
| `Presentation/Ui/Command/OpensOverlay` | zdolność: „potrzebuję okna, zanim cokolwiek zrobię”; `null` znaczy „wykonaj mnie zwyczajnie” |
| `Module/Browser/…/Command/DeleteCommand` | `browser.delete [nazwa]` — komenda, której krok 41 nie zdołał napisać |
| `tests/Functional/SettingsScrollFlowTest` | sześć przypadków przewijania zakładki, w tym kolumna suwaka |

`EntryOperations` dostało trzy metody dla komend (`renameRequest`,
`directoryRequest`, `deleteRequest`) oddające `OverlayOutcome`, a trzy dla
klawiszy (`…Prompt`) przekładające je na `ScreenOutcome` **jednym** prywatnym
mapowaniem — klasa istnieje po to, żeby klawisz i komenda prowadziły w to samo,
więc i przekład typów należy tam, a nie do trzech komend osobno.

**Menu w pustym katalogu zmieniło zachowanie i to jest skutek, nie usterka.**
Do tego kroku pusty katalog dostawał zdanie „nie ma czego pokazać”, bo każda
pozycja wymagała zaznaczenia. `browser.mkdir` zaznaczenia nie wymaga i jest
jedyną czynnością, która ma tam sens — więc menu **otwiera się z jedną pozycją**.
Mechanizm „menu bez pozycji się nie otwiera” zostaje i pilnuje go `MenuOverlayTest`.

**Dług B: cztery testy zmieniły zdanie na przeciwne i to jest właściwa forma
zapisu decyzji w kodzie.** `ScreenZonesTest` sprawdza teraz przez refleksję, że
`preview()` **nie istnieje** w kontrakcie (po D76 sprawdzał, że wszystkie ekrany
oddają `null`); `GameLoopTest` liczy 23 wiersze panelu listy — piętnaście, które
zostawiał układ z pasem, plus osiem, które pas brał; `HudLayoutTest` stracił
kolumnę w tabeli-wyroczni (drabinka ścieżki i stopki jest **nietknięta co do
liczby**, lista wchłonęła wiersze pasa) i zyskał test przesuniętego progu.

**Rzecz wypatrzona przy przeliczaniu progu, warta zapamiętania:** próg
dwuwierszowej stopki dało się przenieść **bez zmiany argumentu**. Przy starym
progu (28 wierszy okna: pas 8, stopka 4, ścieżka 3) liście zostawało trzynaście
wierszy; przy nowym (20 wierszy, bez pasa) zostaje ich dokładnie tyle samo. Liczba
się zmieniła, zdanie uzasadniające — nie.

**Pomiar: cztery przebiegi, żeby odróżnić kod od maszyny.** Pierwsze dwa
(bezpośrednio po `make qa`) pokazały po trzy regresje — ale **zestawy się
różniły**, co samo w sobie jest podpisem szumu. Rozstrzygnęło zmierzenie
**starego kodu w tym samym środowisku** (`git stash push -- src/`): stary kod nie
miał regresji, a po przywróceniu nowego i odczekaniu też ich nie było. Wniosek na
przyszłość, ostrzejszy niż reguła 17: **maszyna zwolniona przez użytkownika bywa
nadal gorąca po własnym `make qa`** — a wtedy najtańszym rozstrzygnięciem jest
przebieg na kodzie sprzed zmiany, nie trzeci przebieg na kodzie po niej.

Wynik końcowy (`2026-08-14-po-kroku-47*.json`, cztery tory): bez regresji.
Jedyna prawdziwa zmiana to `klatka z miniaturą` (+9%) i **nie jest regresją
ścieżki rysowania**: scenariusz rysuje odtąd dwa panele i obraz w prawym z nich
zamiast pasa u dołu — inna klatka, nie wolniejszy renderer. Zrzuty PNG: **17 z 18
klatek piksel w piksel identycznych** w obu torach graficznych, co jest
najmocniejszym dowodem, że zniesienie strefy nie ruszyło niczego poza nią.

### 2026-08-14 — sprawdzenie w prawdziwym terminalu i jedna usterka

**`bin/run.sh` przyjmuje odtąd rozmiar okna** (`make run-xterm ARGS='100x16'`).
Nie jest to obejście reguły 18, tylko jej zastosowanie: wysokość terminala jest
w tym kroku **treścią sprawdzenia**, a nie tłem, więc narzędzie projektu dostaje
oś zamiast doraźnego zastępnika obok siebie.

Obejrzane w XTermie: menu `F9` z pięcioma pozycjami (trzy operacje kroku 41 plus
wejście i opis wpisu), pełny cykl usunięcia z menu — pytanie w wariancie groźnym
z ogniskiem na „Nie”, po zgodzie „Usunięto 1 wpis.”, lista odświeżona i kursor na
następcy — oraz zakładka ustawień w oknie o **szesnastu** wierszach.

**Usterka, którą pokazał wyłącznie prawdziwy terminal:** suwak wchodził **na
wartości** wyrównane do prawej krawędzi („1024”, „30” dotykały szyny), bo
szczeliny `VStack` dostawały pełną szerokość strefy. `Table` rozwiązał ten sam
problem w kroku 27 jedną linią (`$scrolls ? $bounds->columnsFrom(0, columns - 1)
: $bounds`) i ta linia weszła tutaj. Regresję pilnuje
`SettingsScrollFlowTest::testTheScrollbarDoesNotSitOnTheValues`, który porównuje
**prawą krawędź napisów** z kolumną suwaka — sam pomiar przewijania by tego nie
złapał, bo pozycje były na swoich miejscach, tylko o kolumnę za szerokie.

Wniosek ten sam, co po krokach 28 i 41, i już trzeci raz: **rozmiar liczony
z treści trzeba zobaczyć, a nie wyliczyć.**

**Bramka:** `make qa` zielone — **1518 testów**, 4074 asercje (przed krokiem 1505),
PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag.
