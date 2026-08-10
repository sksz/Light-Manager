# Krok 18 — Komponenty interfejsu i płaszczyzny

## Status

**Ukończony z zastrzeżeniem** (2026-08-09). Kod, testy, pomiary i dokumentacja
gotowe; PHPStan `max` i PHP-CS-Fixer bez uwag, 602 testy zielone. Niespełnione
zostaje jedno kryterium — zgodność klatki „piksel w piksel”, której nie da się
już sprawdzić, bo zrzuty sprzed przebudowy nie powstały (szczegóły w dzienniku).
Wygląd wymaga obejrzenia pod prawdziwym terminalem.

**Nazewnictwo:** decyzja P10 wybrała `Plane` zamiast `Layer` właśnie po to, by
„warstwa” pozostała zarezerwowana dla warstw DDD. W tym pliku **płaszczyzna**
znaczy zawsze nakładany plan obrazu, a **warstwa** — wyłącznie `Domain`,
`Application`, `Infrastructure`, `Presentation`.

## Ustalenia (decyzje użytkownika, 2026-08-09)

Zapisane w [00-decyzje.md](00-decyzje.md) jako D36.

| Pytanie | Wybór |
|---|---|
| **P1** — `Frame` i `FrameLine` | **Wymiana całości** — `Frame` niesie płaszczyzny; `FrameLine` i `FrameSegment` znikają na rzecz komponentów i prymitywów |
| **P2** — droga komponentu do renderera | **Prymitywy** — komponent oddaje kształty z rolami motywu, renderer nie zna treści; nowy komponent nie dotyka renderera |
| **P3** — warstwa DDD dla komponentów | **`Presentation/Ui/`** — wraz z przeniesieniem składania ekranów do warstwy dostarczania |
| **P4** — układ | **Same kontenery** — `FrameLayout`, `FrameZone` i `HudFrameLayoutService` znikają; podział okna wyrażony kontenerami |
| **P5** — zakres katalogu komponentów | **Całość poza `TextInput`** (patrz P14); każdy komponent z prawdziwym użytkownikiem w aplikacji |
| **P6** — wyjście z aplikacji | **Tylko `F10`** — `q` przestaje cokolwiek znaczyć |
| **P7** — wydajność | **Bez progu liczbowego, z obowiązkiem pomiaru** każdego scenariusza przed i po oraz opisania wyniku w dzienniku |
| **P8** — ekrany jako obiekty | **Pełny kontrakt** — `ScreenInterface`, `ScreenOutcome`, enum `Screen` znika; krok modułów traci ten punkt w całości |
| **P9** — zasięg komponentyzacji | **Cała klatka** (rozstrzygnięte przez konsekwencję P4) |
| **P10** — nazewnictwo | `Component` / **`Plane`** / `Primitive` / **`Cursor`** |
| **P11** — miejsce prymitywów | **`Application/Ui/`, a `Frame` przenosi się tam za nimi** — domena przestaje wiedzieć o rysowaniu |
| **P12** — użytkownicy nowych komponentów | `Button` → **„przywróć ustawienia domyślne”** w konfiguracji; `TextInput` → patrz P13 i P14 |
| **P13** — skok do ścieżki | **Nie w tym kroku.** Powstaje **okno komend** (nowy krok 19) otwierane `F12`, z podpowiedziami i autouzupełnianiem; skok do ścieżki staje się komendą modułu `file` (krok 20) |
| **P14** — `TextInput` | **Przenosi się do kroku 19** i powstaje razem z oknem komend, czyli przy swoim użytkowniku |

### Konsekwencje, które trzeba obejść, a nie przemilczeć

**P3 — granica dla prymitywu.** Renderery leżą w `Infrastructure` i
implementują port zadeklarowany w `Application`, więc **nie wolno im zobaczyć
ani jednej klasy z `Presentation`**. Komponent może zatem mieszkać
w `Presentation`, ale **prymityw i płaszczyzna — już nie**: to one przechodzą
przez port. Stąd podział z sekcji 7.

**P11 — domena traci słownictwo rysowania.** Z `Domain/ValueObject` znikają
`Frame`, `FrameLine`, `FrameSegment`, `Alignment`, `LineStyle` i `Popup`.
Zostaje to, co opisuje treść, a nie obraz: `Entry`, `DirectoryPath`,
`Selection`, `Preview`, `Message`, `ScrollPosition`. `architecture.md` §2
(słownik domenowy) traci sześć wierszy i zyskuje nowe pojęcia po stronie
`Application` — to najbardziej widoczna zmiana dokumentacyjna kroku.

**P4 — największe pojedyncze ryzyko kroku.** Znikają `FrameLayout`,
`FrameZone`, `FrameLayoutPort` i `HudFrameLayoutService`, a wraz z nimi
**drabinka ustępowania stref z kroku 13**: w niskim oknie znika najpierw pas
podglądu, potem obwódka ścieżki, potem obwódka paska stanu, a na końcu obwódka
listy — przy czym lista zawsze dostaje co najmniej jeden wiersz. To nie jest
zwykły podział proporcjonalny i sam „elastyczny rozmiar” tego nie odda.
Zabezpieczenie opisuje sekcja 1.

**P6 — `q` się zwalnia.** Wyjście przenosi się na `F10`, więc rdzeń nie
rezerwuje już **ani jednej litery** — cały alfabet zostaje wolny dla komend
(krok 19) i skrótów modułów (krok 20). Do zmiany: `InputHandler::QUIT`, klucz
`browser.hints` w `lang/pl.php` i `lang/en.php` (dziś kończy się na
„`q` wyjście”), wiersz `['q', 'help.key.quit']` w spisie klawiszy pomocy oraz
tabela sterowania w `README.md`. Warstwa terminala jest gotowa: `Key::F10` ma
już przypadek w enumie, a `KeySequenceParser` rozpoznaje sekwencję `ESC [ 21 ~`.

**P8 — pętla główna wchodzi w zakres.** Pełny kontrakt ekranu oznacza, że enum
`Screen` znika, a `GameLoop`, `LoopState` i `InputHandler` przestają wybierać
ekran przez `match`. Krok 20 traci przez to swój punkt „ekrany jako obiekty”
w całości i zaczyna wprost od kontraktu modułu.

**P13 i P14 — `TextInput` opuszcza ten krok.** Skok do ścieżki miał być
pierwszym prawdziwym zastosowaniem pola tekstowego; po decyzji P13 przenosi
się do okna komend (krok 19) jako komenda modułu `file` (krok 20). Skoro
użytkownik pola wyemigrował, **pole idzie za nim**: `TextInput` powstaje
w kroku 19, przy oknie komend. Zasada z P5 zostaje w ten sposób nienaruszona,
a katalog komponentów dostaje pierwszą prawdziwą próbę — rozszerzenie przez
kod pisany już po kroku 18, bez ruszania rdzenia.

Krok 18 zostaje więc z **dwunastoma komponentami**, z których każdy ma
w aplikacji miejsce pracy: jedenaście przejmuje robotę wykonywaną dziś
ręcznie, a `Button` dostaje nową — przywracanie ustawień domyślnych.

## Zależności

- **Krok 13** (motyw graficzny) — komponent rysuje się rolami motywu, nie
  kolorami; katalog ról powstał tam.
- **Krok 14** (konfiguracja i ekran ustawień) — ekran ustawień jest jedynym
  miejscem w aplikacji, w którym istnieją dziś „pozycja z wartością” i „pasek
  zakładek”. To on dostarcza pierwszych prawdziwych użytkowników komponentów
  `Choice` i `Tabs`, zamiast wymyślać ich na sucho.
- **Krok 15** (wielojęzyczność) — etykiety komponentów są napisami widocznymi
  dla użytkownika i mają iść przez katalog napisów od pierwszej linii.
- **Krok 16** (narzędzia diagnostyczne) — twardo. Przebudowa potoku rysowania
  bez pomiaru „przed i po” byłaby zakładem, a nie inżynierią; `bin/render-bench`
  wraz z wzorcami jest jedynym sposobem, by pokazać, ile kosztuje warstwa
  pośrednia.
- **Krok 17** (optymalizacja) — twardo. Klatka listy kosztuje dziś 34,2 ms
  dzięki dwóm pamięciom podręcznym (bitmapy wierszy, chrom) i segmentowemu
  `FrameLine`. Komponenty muszą wejść **na** ten kształt, a nie obok niego;
  robione przed krokiem 17 zostałyby przez niego przeorane.

## Model i wysiłek

**Opus / high.** Krok wymienia sposób, w jaki cała aplikacja opisuje swój
ekran: dziś `Frame` z płaską listą wierszy, po zmianie — drzewo komponentów
w płaszczyznach. Dotyka obu rendererów, wszystkich trzech ekranów, pętli
głównej, obsługi klawiszy i pamięci podręcznych, od których zależy wydajność.
Zakłada przy tym **pierwsze publiczne API projektu** — katalog komponentów
i kontrakt ekranu, na których oprą się okno komend (krok 19) i moduły
(krok 20).

## Cel

Zamknąć elementy interfejsu w komponenty, którymi da się złożyć ekran, zamiast
rysować każdy ekran od zera w dwóch rendererach naraz.

Trzy rzeczy, których dziś nie ma, a które krok ma dać:

1. **Komponent** — samoopisujący się element interfejsu: okno, etykieta,
   przycisk, pole wprowadzania danych, pole wyboru opcji, lista, zakładki.
   Wie, ile miejsca potrzebuje, jak się narysować i — jeśli przyjmuje kursor
   — co zrobić z klawiszem.
2. **Płaszczyzna** — niezależnie umieszczony plan obrazu z porządkiem
   nakładania. Ekran to płaszczyzna spodnia; okno modalne, lista rozwijana czy
   podpowiedź to płaszczyzny nad nią. Płaszczyzna może być modalna: przejmuje
   klawisze i nie oddaje ich niżej.
3. **Wędrówka klawisza** — klawisz idzie do komponentu z kursorem, a dopiero
   nieobsłużony wraca wyżej: do płaszczyzny, do ekranu, na koniec do rdzenia.

## Stan zastany (sprawdzony w kodzie 2026-08-09)

| Element | Stan |
|---|---|
| `Frame` | Płaska struktura: tytuł, `list<FrameLine>`, komunikat, okienko, podgląd, położenie okna przewijania, podpowiedzi. Renderer zna znaczenie **każdego** z tych pól. |
| `FrameLine` / `FrameSegment` | Wiersz jako segmenty z wyrównaniem (krok 17, D34). Najbliższy istniejący krewny komponentu — i jedyny. |
| `FrameLayout` / `FrameZone` | Podział okna na cztery **stałe** strefy: ścieżka, lista, podgląd, pasek stanu, wraz z drabinką ustępowania w niskim oknie. |
| `Popup` | Jedyna dzisiejsza „płaszczyzna”: modalna, jedna, wyśrodkowana, rysowana osobno w `SixelFrameEncoder::drawPopup()` **i** w `TextFrameRenderer::overlayPopup()`. |
| Ekrany | Trzy przypadki użycia (`RenderCurrentFrameUseCase`, `RenderSettingsFrameUseCase`, `RenderHelpFrameUseCase`), każdy z własnym rachunkiem okna przewijania i własnym przycinaniem wiersza. |
| Wybór ekranu | Enum `Screen` plus `match` w `GameLoop` i drugi w `InputHandler`. |
| Pasek zakładek | Napis składany w `RenderSettingsFrameUseCase::tabBar()` — aktywna zakładka to `[ Etykieta ]`, bo wiersz nie niesie koloru. |
| Pozycja ustawienia | `position()` — etykieta po lewej, wartość po prawej; zmiana wartości mieszka gdzie indziej (`ChangeSettingUseCase`). |
| Przycisk | **Nie istnieje.** |
| Pole wprowadzania danych | **Nie istnieje.** `InputHandler` opiera się wprost na tym, że nie istnieje: *„`q` kończy aplikację wszędzie — nigdzie nie ma pola tekstowego, które mogłoby tę literę przechwycić”*. |
| Kursor (ognisko) | **Nie istnieje** jako mechanizm. Jest `SettingsCursor` — kursor jednego ekranu. |
| Pamięci podręczne (krok 17) | Bitmapa wiersza kluczowana treścią segmentów; chrom jako sklonowane płótno. Obie stoją na tym, że treść wiersza i chrom są **niezmienne między klatkami**. |

## Na czym polega problem

Trzy dowody z kodu, nie z przeczucia:

1. **Ten sam element rysowany dwa razy, w dwóch miejscach.** Okienko ma dwie
   niezależne implementacje: 49 linii w enkoderze Sixela i 28 w rendererze
   tekstowym. Każdy nowy element interfejsu kosztuje dziś dwie implementacje
   i dwa zestawy testów — a niezgodność między nimi wychodzi dopiero na oczy.
2. **Ten sam rachunek pisany trzy razy.** Okno przewijania liczą trzy
   przypadki użycia: `windowOffset()` z marginesem w przeglądarce,
   `offsetFor()` w ustawieniach, `clamp()` w pomocy. Przycięcie zbyt długiego
   wiersza — również trzy razy (`entryLine()`, `position()`, `fit()`).
3. **Elementy udawane tekstem.** Aktywna zakładka to nawiasy kwadratowe wokół
   napisu, bo wiersz nie ma jak powiedzieć „ten fragment jest wyróżniony”.
   Zaznaczona pozycja ustawień to `LineStyle::Selected` — ten sam styl, którym
   lista plików zaznacza plik. Interfejs nie ma słownika na własne części.

Okno komend (krok 19) i moduły (krok 20) dołożą do tego kod piszący własne
ekrany i własne komendy. Gdyby weszły przed komponentami, odziedziczyłyby te
trzy problemy i utrwaliły je w API, którego potem nie da się zmienić bez
ruszania wszystkich modułów naraz. **To jest powód, dla którego komponenty
stoją na początku tej trójki.**

## Zakres

### 1. Geometria, płaszczyzny i układ

Jeden układ współrzędnych: **siatka znakowa terminala** (wiersz, kolumna).
Piksele pozostają wyłącznie w `SixelFrameMetrics` — inaczej tryb tekstowy nie
miałby jak narysować niczego, co powstało w krokach graficznych.

```php
namespace LightManager\Application\Ui;   // P11

final class Rect          // prostokąt w siatce znakowej
{
    public function __construct(
        public readonly int $row,
        public readonly int $column,
        public readonly int $rows,
        public readonly int $columns,
    ) {}
}

final class Plane
{
    /** @param list<Primitive> $primitives */
    public function __construct(
        public readonly string $id,
        public readonly Rect $bounds,
        public readonly array $primitives,
        /** Czy płaszczyzna połyka klawisze zamiast oddawać je niżej. */
        public readonly bool $modal = false,
        /** Czy płaszczyzny pod nią mają zostać przygaszone. */
        public readonly bool $dims = false,
    ) {}
}
```

`Frame` przestaje nieść wiersze, a zaczyna nieść **płaszczyzny w porządku
nakładania** (P1). Płaszczyzna spodnia to ekran; okno modalne, lista rozwijana
czy menu to płaszczyzny nad nią. `FrameLine` i `FrameSegment` znikają — ich
rolę przejmują komponenty (co pokazać) i prymityw `TextRun` (co narysować).
Dzisiejszy `Popup` znika jako osobne pojęcie: staje się komponentem `Dialog`
w płaszczyźnie modalnej.

**Płaszczyzna niesie prymitywy, nie komponent.** Komponenty leżą po P3
w `Presentation`, a płaszczyzna przekracza port — nie może więc trzymać
korzenia drzewa, tylko **wynik jego narysowania**. Drzewo komponentów zostaje
po stronie ekranu i nigdy nie przekracza portu.

**Układ (P4): same kontenery.** Prostokąty rozdziela kontener, nie ekran.
Podział okna na ścieżkę, listę, pas podglądu i pasek stanu przestaje być
osobnym mechanizmem — staje się pionowym kontenerem płaszczyzny spodniej, a
`FrameLayout`, `FrameZone`, `FrameLayoutPort` i `HudFrameLayoutService` znikają.

Żeby to było możliwe, kontener musi umieć odtworzyć drabinkę ustępowania
z kroku 13. Dziecko deklaruje więc trzy rzeczy, nie jedną:

```php
final class Slot
{
    public function __construct(
        public readonly ComponentInterface $child,
        /** Poniżej tylu wierszy dziecko nie ma sensu — 0 znaczy „może zniknąć”. */
        public readonly int $minimumRows,
        /** Ile chce dostać, gdy jest z czego dawać; null — „resztę”. */
        public readonly ?int $preferredRows,
        /** Kolejność ustępowania: im wyżej, tym wcześniej oddaje wiersze. */
        public readonly int $yieldOrder,
    ) {}
}
```

Dzisiejsza drabinka wyraża się wtedy wprost: pas podglądu ustępuje pierwszy
(`yieldOrder` najniższy, `minimumRows` 0), potem obwódki, a lista ma
`minimumRows` równe 1 i `preferredRows` `null`, czyli bierze resztę. Jedna
rzecz nie jest jednak „mniej wierszy”, tylko „mniej ozdoby”: w niskim oknie
panel ścieżki zamienia się w goły wiersz. Wobec tego **`Panel` musi umieć
oddać własną obwódkę, zanim odbierze wiersz treści** — to wymaganie na
komponent, nie na kontener.

**Zabezpieczenie, bez którego nie zaczynamy.** `HudFrameLayoutService` zostaje
w testach jako **wyrocznia**: dla każdej wysokości okna od 1 do 60 wierszy
nowy układ musi dać ten sam podział co on. Usuwa się go z kodu dopiero wtedy,
gdy ten test przechodzi w komplecie. Bez tego drabinka dopracowana pomiarami
w kroku 13 rozjedzie się po cichu, a zobaczy to dopiero użytkownik z niskim
oknem terminala.

### 2. Prymitywy rysowania

Komponenty nie rysują pikseli — składają **listę prymitywów**, a renderer
zamienia ją na Sixela albo na ANSI (P2). Renderer przestaje wiedzieć, czym
jest lista plików, pasek stanu czy okienko; zna wyłącznie kształty — i dzięki
temu moduł z kroku 20 może wnieść własny komponent, nie dotykając rdzenia.

Słownik prymitywów jest wyznaczony przez to, co enkoder rysuje **dziś** — nic
ponad to, bo każdy nadmiarowy kształt to obowiązek dla trybu tekstowego:

| Prymityw | Dziś rysuje to | Tryb tekstowy |
|---|---|---|
| `TextRun` (wiersz, kolumna, napis, rola koloru) | `annotateImage()` w sześciu miejscach | wprost |
| `RoundRect` (prostokąt, promień, wypełnienie, obrys) | panele, pasek zaznaczenia, okienko, ramka pustego podglądu | znaki rysunkowe albo pominięcie |
| `CornerBrackets` (prostokąt, promień) | nawiasy narożne paneli i okienek | pominięcie |
| `Bar` (prostokąt, rola) | kreska rozdzielająca w pasku stanu, krawędź zaznaczenia | znak `│` albo pominięcie |
| `Bitmap` (prostokąt, źródło) | miniatura | podpis zamiast obrazu |

Prymityw niesie **rolę motywu**, nie kolor — inaczej komponent musiałby znać
paletę, a zmiana motywu w locie (krok 14) przestałaby działać.

### 3. Katalog komponentów

```php
namespace LightManager\Presentation\Ui;   // P3

interface ComponentInterface
{
    /** Ile miejsca komponent chce zająć, gdy ma go do dyspozycji tyle. */
    public function measure(int $rows, int $columns): Size;

    /** @return list<Primitive> treść w podanym prostokącie */
    public function draw(Rect $bounds): array;
}

interface FocusableInterface          // komponent przyjmujący kursor
{
    /** @return list<KeyBinding> źródło podpowiedzi w pasku stanu i spisu w pomocy */
    public function bindings(): array;

    /** Czy klawisz został zużyty; nieobsłużony wędruje wyżej. */
    public function handle(KeyPress $key): bool;
}
```

Katalog wchodzący w krok — z zaznaczeniem, skąd każdy komponent bierze się
w dzisiejszym kodzie, bo to mierzy prawdziwy koszt:

| Komponent | Rola | Materiał w kodzie |
|---|---|---|
| `Panel` (okno) | obwódka, etykieta wpięta w krawędź, nawiasy narożne, treść w środku | `drawPanel()` + `openZone()` |
| `Label` | wiersz tekstu z wyrównaniem i stylem | `FrameLine` / `FrameSegment` |
| `ListView` (lista) | wpisy, zaznaczenie, okno przewijania, suwak | `body()`, `windowOffset()`, `scroll()`, `drawScrollbar()` |
| `Tabs` (zakładki) | pasek zakładek, aktywna, przełączanie ←/→ | `tabBar()` |
| `Choice` (pole wyboru opcji) | etykieta i wartość z zamkniętej listy, ←/→ zmienia | `position()` + `ChangeSettingUseCase` |
| `Toggle` | dwustanowy `Choice` | `yesNo()` |
| `Button` (przycisk) | etykieta w obwódce, `Enter` wyzwala działanie | **od zera**; użytkownik: „przywróć ustawienia domyślne” (P12) |
| `Dialog` (okno modalne) | tytuł, treść, rząd przycisków; mieszka we własnej płaszczyźnie | `Popup` + `drawPopup()` + `overlayPopup()` |
| `StatusBar` | komunikat w tonie, podpowiedzi, kreska między nimi | `drawStatus()` |
| `ImageBox` | miniatura albo ramka z powodem jej braku | `drawPreview()` |
| `Scrollbar` | szyna i suwak | `drawScrollbar()` |
| `Spacer` / `Separator` | odstęp, kreska | puste wiersze `FrameLine::of('')` |

**`TextInput` (pole wprowadzania danych) nie wchodzi w ten krok** — powstaje
w kroku 19 wraz z oknem komend, czyli przy swoim pierwszym użytkowniku (P14).
Kontrakt komponentu musi być na to gotowy: `FocusableInterface` ma wystarczyć
komponentowi, który przyjmuje **każdy znak**, a nie tylko klawisze sterujące.
Sprawdzianem jest pytanie, na które trzeba umieć odpowiedzieć przed końcem
kroku 18: czy `handle(KeyPress $key): bool` wystarczy do zbudowania pola
tekstowego bez zmiany kontraktu. Jeśli nie — poprawka należy do kroku 18, nie
do 19.

### 4. Kursor i wędrówka klawisza

Komponenty przyjmujące klawisze układają się w **pierścień kursora** wewnątrz
płaszczyzny. Klawisz idzie: komponent z kursorem → płaszczyzna (przełączanie
kursora, `Esc`) → ekran → rdzeń (`F10`, klawisze okien). Nieobsłużony wędruje
wyżej; obsłużony zatrzymuje się.

Zysk jest podwójny: znika `match` po ekranach w `InputHandler`, a podpowiedzi
w pasku stanu i spis klawiszy w pomocy zaczynają pochodzić **z deklaracji
komponentów** (`bindings()`), a nie z ręcznie przepisanej tablicy `KEYS`
w `RenderHelpFrameUseCase`. Ta sama deklaracja jest potrzebna oknu komend
(krok 19) do podpowiadania i automatycznej części zakładki pomocy modułu
(krok 20).

### 5. Ekrany jako obiekty (P8)

Enum `Screen` znika. Pętla trzyma referencję do aktywnego ekranu, a
przeglądarka plików, ustawienia i pomoc stają się równorzędne — tak samo, jak
za chwilę okno komend i ekrany modułów.

```php
namespace LightManager\Presentation\Ui;

interface ScreenInterface
{
    public function id(): string;

    /** Etykieta środkowego panelu — dla przeglądarki dzisiejsze `FILES`. */
    public function label(): string;

    /** Korzeń drzewa komponentów tego ekranu. */
    public function content(): ComponentInterface;

    public function handle(KeyPress $key): ScreenOutcome;
}
```

`ScreenOutcome` niesie, co po klawiszu ma się stać: ekran zostaje, ekran się
zamyka (powrót do przeglądarki), aplikacja kończy pracę — plus opcjonalny
`Message` do paska stanu i opcjonalna płaszczyzna do położenia na wierzchu.

Ekran zajmuje **wyłącznie środkowy panel**; ścieżka u góry, pasek stanu u dołu
i pas podglądu zostają w gestii rdzenia. Ten podział jest jednocześnie
kontraktem, na którym w kroku 20 stanie ekran modułu.

### 6. Przebudowa trzech ekranów rdzenia

Dowód, że katalog wystarcza — te same trzy ekrany, złożone z komponentów:

- **Przeglądarka:** `VStack(Panel(Label), Panel(ListView), Panel(ImageBox),
  StatusBar)`.
- **Ustawienia:** `VStack(Panel(Label), Panel(Tabs + [Choice|Toggle]* +
  Button), StatusBar)` — przycisk to „przywróć ustawienia domyślne” (P12).
- **Pomoc:** `VStack(Panel(Label), Panel(Tabs + Label*), StatusBar)` — przy
  okazji ekran dostaje pasek zakładek, który krok 14 był mu winien (D33).

Trzy ekrany mają wspólny szkielet i różnią się **jednym** komponentem
w środku. To jest właśnie ten kształt, na którym oprą się okno komend i ekran
modułu.

Wymaganie na tę przebudowę: **klatka przeglądarki ma wyglądać identycznie**.
Krok 17 pokazał, że taki wymóg da się sprawdzić co do piksela — zrzuty PNG
z `bin/render-bench` przed i po różniły się zerem pikseli. Ten sam probierz
obowiązuje tutaj; ekrany ustawień i pomocy zmienią się o tyle, o ile zyskają
prawdziwe zakładki zamiast nawiasów kwadratowych.

### 7. Wydajność (P7)

Krok 17 zszedł z 212,4 do 34,2 ms na klatce listy. Warstwa pośrednia jest
dokładnie tym rodzajem zmiany, który taki zysk oddaje z powrotem: drzewo
obiektów budowane trzydzieści razy na sekundę, prymitywy jako kolejne obiekty,
pamięci podręczne kluczowane treścią, której już nie ma w tej samej postaci.

**Bez progu liczbowego, ale z obowiązkiem pomiaru** — ten sam wybór, którym
krok 17 odrzucił twardy budżet 50 ms (D29): czas klatki zależy też od maszyny
i rozmiaru okna. Obowiązki są za to twarde:

1. **Pomiar wzorca przed pierwszą linią kodu** — `bin/render-bench --baseline`
   na wszystkich scenariuszach.
2. **Pomiar po przebudowie, scenariusz po scenariuszu**, z wynikiem opisanym
   w dzienniku realizacji — również wtedy, gdy jest gorszy.
3. **Pamięć podręczna przenosi się z wiersza na prymityw** — bitmapa `TextRun`
   zamiast bitmapy `FrameLine`; klucz nadal niesie wszystko, co wpływa na
   piksele (D34).
4. **Płaszczyzna statyczna zastępuje pamięć podręczną chromu** — dzisiejszy
   chrom (cztery panele) jest szczególnym przypadkiem płaszczyzny, która nie
   zmienia się między klatkami. Zamiast pamięci „na chrom”, pamięć „na
   płaszczyznę”, kluczowana jej prymitywami.

### 8. Warstwy DDD i struktura katalogów

Rozstrzygnięte (P3): **komponenty mieszkają w `Presentation/Ui/`**, a
składanie ekranów przenosi się do warstwy dostarczania.

Reguła zależności wymusza przy tym podział na dwa poziomy. Renderery leżą
w `Infrastructure` i implementują port zadeklarowany w `Application`; gdyby
prymityw albo płaszczyzna mieszkały w `Presentation`, renderer musiałby
sięgnąć po klasę z warstwy leżącej na zewnątrz niego — strzałka w złą stronę.
Granica przebiega więc dokładnie tam, gdzie przechodzi klatka:

| Co | Gdzie | Dlaczego |
|---|---|---|
| Komponenty: `Panel`, `Label`, `ListView`, `Tabs`, `Choice`, `Toggle`, `Button`, `Dialog`, `StatusBar`, `ImageBox`, `Scrollbar`, `Spacer` | `Presentation/Ui/Component/` | decyzja P3 |
| Kontenery i szczeliny (`VStack`, `HStack`, `Slot`) | `Presentation/Ui/Container/` | liczą układ komponentów, więc stoją po ich stronie |
| Kursor: pierścień, wędrówka klawisza, wiązania | `Presentation/Ui/` | żyje wyłącznie po stronie dostarczania |
| Ekrany i ich kontrakt | `Presentation/Ui/` + `Presentation/Cli/Screen/` | konsekwencja P3 — przestają być przypadkami użycia |
| **Prymitywy, płaszczyzna, geometria i `Frame`** | `Application/Ui/` (P11) | przechodzą przez `FrameRendererPort`, więc musi je widzieć `Infrastructure` |

```
src/
├── Domain/ValueObject/       # bez Frame, FrameLine, FrameSegment,
│                             # Alignment, LineStyle, Popup — zostaje treść:
│                             # Entry, DirectoryPath, Selection, Preview,
│                             # Message, ScrollPosition
├── Application/
│   └── Ui/
│       ├── Primitive/        # TextRun, RoundRect, CornerBrackets, Bar, Bitmap
│       ├── Frame.php         # przeniesiony z Domain (P11); niesie płaszczyzny
│       ├── Plane.php
│       ├── Rect.php
│       └── Size.php
└── Presentation/
    ├── Ui/
    │   ├── Component/        # Panel, Label, ListView, Tabs, Choice, Toggle,
    │   │                     # Button, Dialog, StatusBar, ImageBox, Scrollbar
    │   │                     # (TextInput dochodzi w kroku 19)
    │   ├── Container/        # VStack, HStack, Slot
    │   ├── ComponentInterface.php
    │   ├── FocusableInterface.php
    │   ├── ScreenInterface.php
    │   ├── ScreenOutcome.php
    │   └── CursorRing.php
    └── Cli/
        └── Screen/           # BrowserScreen, SettingsScreen, HelpScreen
```

Podział ma czytelną granicę i warto ją zapisać jednym zdaniem, bo to ono
trafi do `architecture.md`: **komponent wie, jak wyglądać; prymityw jest tym,
co z tej wiedzy zostaje po przekroczeniu portu.**

Co zostaje w `Application` po przeprowadzce ekranów: porty, ustawienia,
`PreviewSelectedEntryUseCase` i cała reszta przypadków użycia operujących na
domenie. Znikają stamtąd wyłącznie trzy klasy `Render*FrameUseCase` — te,
których jedynym zadaniem było złożenie obrazu.

`docs/architecture.md` i `.claude/skills/light-manager-conventions/SKILL.md`
przyjmują nowe pojęcia (komponent, płaszczyzna, prymityw, kursor) **w tym
samym kroku** — dokument i Skill nie mają prawa się rozjechać. Dochodzi do
tego zapis §1: `Presentation` zyskuje własny podkatalog `Ui/`, a reguła
zależności — zdanie o tym, dlaczego prymityw nie może w nim leżeć.

## Wpływ na kroki 19 i 20

### Krok 19 — okno komend

Powstaje po tym kroku i stoi na jego dorobku — z jednym wyjątkiem:

- **`TextInput` powstaje tam, nie tutaj** (P14). Krok 18 ma za to obowiązek
  udowodnić, że jego kontrakt komponentu udźwignie pole tekstowe **bez
  zmian**. Jeśli okaże się, że nie — poprawka należy do kroku 18.
- **Płaszczyzna modalna** to sposób, w jaki okno komend wchodzi na wierzch
  ekranu, nie zabierając go użytkownikowi z oczu.
- **`bindings()` komponentów** są gotowym źródłem dla podpowiadania komend.
- **Lista podpowiedzi** to `ListView` w płaszczyźnie nad polem — czyli
  komponent, który już będzie istniał.
- `F12` dołącza do klawiszy rdzenia obok `F1`, `F2` i `F10`.

### Krok 20 — moduły

Krok 20 był pisany wobec dzisiejszego `FrameLine`. Po tym kroku zmienia się
w nim siedem rzeczy — wszystkie **na korzyść**, bo moduł dostaje gotowy
słownik zamiast surowych wierszy:

| Miejsce w [20-moduly-plugins.md](20-moduly-plugins.md) | Zmiana |
|---|---|
| `ScreenInterface::content(int $rows, int $columns): list<FrameLine>` | Ekran oddaje **komponent**, nie listę wierszy. |
| `ScreenInterface::scroll(): ?ScrollPosition` | Znika — przewijaniem zarządza `ListView`. |
| `ScreenInterface::hints(): string` | Zastąpione przez `bindings()` komponentów; podpowiedzi i spis w pomocy z jednego źródła. |
| Zakres „ekrany jako obiekty” | **Wykonany w kroku 18** (P8) — krok 20 traci ten punkt w całości. |
| `ScreenOutcome` z opcjonalnym `Popup` | „Otwórz okienko” staje się „połóż płaszczyznę” z komponentem `Dialog`. |
| `ModuleSettingsTab` / `ModuleSetting` opisane danymi | Może stać się zbędne: skoro moduł umie zwrócić `Choice` i `Toggle`, opis zakładki danymi jest drugim sposobem na to samo. |
| Pytania otwarte nr 3, 6, 7 kroku 20 | **Odpowiedziane** — `Popup` znika na rzecz komponentu; ekrany leżą w `Presentation`; `TextInput` istnieje. |
| Pytanie otwarte nr 2 kroku 20 („klawisze rdzenia”) | Lista topnieje: po P6 rdzeń nie rezerwuje **ani jednej litery**, a `.` przechodzi do wiązań komponentu listy. |
| Zakres modułu | Dochodzi punkt piąty: moduł wnosi **własne komendy** do okna komend z kroku 19. |

## Poza zakresem tego kroku

- **Pole tekstowe (`TextInput`) i okno komend** — osobny krok 19 (P14).
- **Mysz i zdarzenia wskaźnika** — komponent zna klawiaturę i nic poza nią.
- **Animacje i przejścia między płaszczyznami.**
- **Automatyczne zawijanie tekstu** i wielowierszowa edycja.
- **Przewijanie poziome.**
- **Motywy komponentów** ponad role z kroku 13 — komponent nie dostaje własnej
  palety.
- **Rysowanie poza siatką znakową** (elementy o wysokości ułamka wiersza).
- **Zmiana modelu odświeżania** — D19 zostaje nietknięte, klatka nadal powstaje
  w każdym takcie.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Ui/Rect.php`, `Size.php`, `Plane.php` | Application | Nowe — geometria i płaszczyzna przechodząca przez port. |
| `Application/Ui/Frame.php` | Application | **Przeniesiony** z `Domain/ValueObject` (P11); niesie płaszczyzny zamiast wierszy. |
| `Application/Ui/Primitive/*` | Application | Nowe — słownik prymitywów z sekcji 2. |
| `Presentation/Ui/Component/*` | Presentation | Nowe — katalog komponentów z sekcji 3. |
| `Presentation/Ui/Container/VStack.php`, `HStack.php`, `Slot.php` | Presentation | Nowe — układ z rozmiarem minimalnym, preferowanym i kolejnością ustępowania (P4). |
| `Presentation/Ui/ComponentInterface.php`, `FocusableInterface.php`, `CursorRing.php` | Presentation | Nowe — kontrakt komponentu i pierścień kursora. |
| `Presentation/Ui/ScreenInterface.php`, `ScreenOutcome.php` | Presentation | Nowe — kontrakt ekranu (P8). |
| `Presentation/Cli/Screen/BrowserScreen.php`, `SettingsScreen.php`, `HelpScreen.php` | Presentation | Nowe — ekrany jako obiekty; przejmują treść trzech przypadków użycia. |
| `Application/Dto/Screen.php` | Application | **Usunięty** — zastąpiony obiektami ekranów (P8). |
| `Domain/ValueObject/Frame.php` | Domain | **Wyprowadzony** do `Application/Ui/` (P11). |
| `Domain/ValueObject/Popup.php` | Domain | **Usunięty** — zastąpiony komponentem `Dialog`. |
| `Domain/ValueObject/FrameLine.php`, `FrameSegment.php`, `Alignment.php`, `LineStyle.php` | Domain | **Usunięte** — zastąpione komponentami i prymitywem `TextRun` (P1). |
| `Application/UseCase/RenderCurrentFrameUseCase.php` | Application | **Usunięty** — treść przechodzi do `BrowserScreen`; przewijanie i przycinanie schodzą do `ListView`. |
| `Application/UseCase/RenderSettingsFrameUseCase.php` | Application | **Usunięty** — treść przechodzi do `SettingsScreen`. |
| `Application/UseCase/RenderHelpFrameUseCase.php` | Application | **Usunięty** — treść przechodzi do `HelpScreen`; spis klawiszy z `bindings()`. |
| `Application/UseCase/RestoreDefaultSettingsUseCase.php` | Application | Nowy — działanie przycisku „przywróć ustawienia domyślne” (P12). |
| `Application/Port/FrameRendererPort.php` | Application | Przyjmuje płaszczyzny z prymitywami. |
| `Application/Dto/FrameLayout.php`, `FrameZone.php`, `Port/FrameLayoutPort.php` | Application | **Usunięte** — podział okna wyraża kontener (P4). |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Przestaje znać strefy i treść; rysuje prymitywy. Pamięć podręczna wiersza → prymitywu, chromu → płaszczyzny. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | To samo w ANSI; degradacja prymitywów bez odpowiednika w tekście. |
| `Infrastructure/Rendering/HudFrameLayoutService.php` | Infrastructure | **Usunięty** po przejściu testu-wyroczni (P4). |
| `Infrastructure/Rendering/SegmentLayout.php`, `PlacedSegment.php` | Infrastructure | Rozmieszczanie segmentów wchodzi do komponentu `Label`; w trybie tekstowym zostaje samo dopychanie. |
| `Presentation/Cli/GameLoop.php` | Presentation | Pyta aktywny ekran o treść; `match` po enumie znika (P8). |
| `Presentation/Cli/InputHandler.php` | Presentation | Kieruje klawisz do kursora; sam obsługuje wyłącznie klawisze globalne. `QUIT` z `q` na `F10` (P6). |
| `Presentation/Cli/LoopState.php` | Presentation | Trzyma aktywny ekran i stos płaszczyzn zamiast pojedynczego `Popup`. |
| `lang/pl.php`, `lang/en.php` | Napisy | `browser.hints` bez `q` (P6), etykieta przycisku przywracania ustawień. |
| `README.md` | Dokumentacja | Tabela sterowania: `F10` zamiast `q`. |
| `bin/render-bench` + `Infrastructure/Diagnostics/*` | Infrastructure | Scenariusze na nowym kontrakcie klatki; wzorzec sprzed przebudowy zachowany do porównania. |
| testy | Testy | Każdy komponent osobno (`measure`, `draw`, `handle`), wędrówka klawisza, płaszczyzny modalne, test-wyrocznia układu, zgodność zrzutu PNG przed i po. |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Pojęcia: komponent, płaszczyzna, prymityw, kursor — **w tym samym kroku**. |
| `20-moduly-plugins.md` | Plan | Kontrakt ekranu modułu przepisany na komponenty (tabela „Wpływ na kroki 19 i 20”). |

## Kryteria ukończenia

- Trzy ekrany rdzenia są złożone **wyłącznie** z komponentów; ani jeden nie
  składa wierszy ręcznie.
- Klatka przeglądarki w trybie Sixel jest **piksel w piksel** taka sama jak
  przed przebudową (zrzuty `bin/render-bench`).
- Układ z kontenerów daje **ten sam podział okna** co `HudFrameLayoutService`
  dla każdej wysokości od 1 do 60 wierszy — sprawdza to test-wyrocznia, a
  usunięcie starej usługi następuje dopiero po jego przejściu.
- Każdy scenariusz `bin/render-bench` zmierzony przed i po, a wynik — również
  niekorzystny — opisany w dzienniku realizacji (P7).
- `q` nie kończy już aplikacji, `F10` kończy; napisy, spis klawiszy w pomocy
  i `README.md` mówią to samo. Żadna litera nie jest zarezerwowana przez rdzeń.
- Enum `Screen` znika; pętla trzyma aktywny ekran przez `ScreenInterface`.
- Okno przewijania, przycinanie wiersza i pasek zakładek istnieją w kodzie
  **po jednym razie**, a nie po trzy.
- Dodanie nowego elementu interfejsu wymaga **jednej** klasy komponentu — a nie
  zmiany w obu rendererach.
- Klawisz trafia do komponentu z kursorem; podpowiedzi w pasku stanu i spis
  w pomocy pochodzą z `bindings()`.
- Kontrakt komponentu udźwignie pole tekstowe **bez zmian** — sprawdzone
  szkicem `TextInput` w teście, zanim krok 19 zbuduje go naprawdę (P14).
- Tryb tekstowy pokazuje wszystkie komponenty w postaci uproszczonej i nie
  wymaga ani jednej gałęzi `if` po typie ekranu.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` i `SKILL.md` opisują komponenty i płaszczyzny —
  zgodnie ze sobą.

## Do rozstrzygnięcia na starcie kroku

Pytania planistyczne **P1–P14 są zamknięte** (sekcja „Ustalenia”, D36).
Poniższe to rozstrzygnięcia wykonawcze — takie, których nie da się podjąć
sensownie przed otwarciem edytora, a które warto mieć spisane, żeby nie
przeoczyć ich w trakcie:

1. **Czy `measure()` jest w ogóle potrzebne.** Kontener zna rozmiar
   minimalny, preferowany i kolejność ustępowania ze `Slot`, więc pytanie
   komponentu o rozmiar może się okazać drugim źródłem tej samej informacji.
   Rozstrzygnąć przy pierwszym kontenerze, nie przy projektowaniu interfejsu.
2. **Jak `ListView` dostaje treść** — listą gotowych `Label`, listą napisów,
   czy wywołaniem zwrotnym po wiersz? Od tego zależy, czy pamięć podręczna
   prymitywów widzi te same obiekty między klatkami.
3. **Czy `Primitive` to interfejs, czy klasa zapieczętowana.** PHP nie ma
   typów sumarycznych; renderer i tak będzie robił `match` po klasie.
4. **Gdzie kończy się przygaszanie płaszczyzny** (`dims`) — czy rysuje je
   renderer jako prostokąt z przezroczystością, czy sama płaszczyzna wnosi
   prymityw.
5. **Kolejność rysowania wewnątrz płaszczyzny** — czy prymitywy z drzewa
   komponentów zachowują porządek obchodzenia (rodzic przed dzieckiem), czy
   potrzebna jest jawna kolejność. Dzisiejszy enkoder opiera się na porządku
   wywołań, więc zmiana wymaga uwagi przy dowodzie „zero różnych pikseli”.

## Dziennik realizacji

### 2026-08-09 — krok wykonany

**Stan:** kod, testy, pomiary i dokumentacja gotowe. PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag, **602 testy** (1345 asercji) zielone.

#### Co powstało

| Warstwa | Klasy |
|---|---|
| `Application/Ui` | `Frame`, `Plane`, `Rect`, `Size`, `Role`, `Corner` |
| `Application/Ui/Primitive` | `Primitive`, `TextRun`, `RoundRect`, `CornerBrackets`, `Bar`, `Weight`, `Bitmap`, `Scrollbar` |
| `Presentation/Ui` | `ComponentInterface`, `FocusableInterface`, `KeyBinding`, `ScreenInterface`, `ScreenOutcome`, `Transition`, `Resettable`, `ScrollWindow`, `HudLayout` |
| `Presentation/Ui/Component` | `Panel`, `Label`, `ListView`, `ListRow`, `Tabs`, `Choice`, `Toggle`, `Button`, `Dialog`, `StatusBar`, `ImageBox`, `Spacer`, `Highlight` |
| `Presentation/Ui/Container` | `VStack`, `Slot` |
| `Presentation/Cli` | `FrameComposer`, `ScreenStack`, `Screen/BrowserScreen`, `Screen/SettingsScreen`, `Screen/HelpScreen` |
| `Application` | `RestoreDefaultSettingsUseCase`, `Dto/EntryDescription` |
| `Infrastructure/Rendering` | `CellBuffer` |

Usunięte: `Frame`, `FrameLine`, `FrameSegment`, `Alignment`, `LineStyle`,
`Popup` (Domain), `FrameLayout`, `FrameZone`, `FrameLayoutPort`, `Screen`,
trzy `Render*FrameUseCase` (Application), `HudFrameLayoutService`,
`SegmentLayout`, `PlacedSegment` (Infrastructure).

#### Wyniki pomiarów (P7)

Wzorzec **przed** przebudową nie zapisał się: narzędzie odmawia zapisu, gdy
rozrzut przekracza próg 1,35×, a maszyna okazała się na to za niespokojna
w trzech kolejnych przebiegach. Liczby poniżej pochodzą z najstabilniejszego
z nich (9 przebiegów, 5 na rozgrzewkę) i zostały spisane ręcznie. Wzorzec **po**
przebudowie zapisał się bez uwag — `docs/pomiary/2026-08-09-po-kroku-18.json`.

| Scenariusz | Przed | Po | Zmiana |
|---|---|---|---|
| puste płótno | 9,2 ms | 7,6 ms | −17% |
| sam tekst | 23,4 ms | 12,6 ms | **−46%** |
| same ramki | 13,6 ms | 11,9 ms | −13% |
| ramki z tekstem | 28,7 ms | 21,1 ms | **−26%** |
| zaznaczenie | 26,8 ms | 22,3 ms | −17% |
| klatka z miniaturą | 70,9 ms | 66,6 ms | −6% |
| klatka z okienkiem | 45,1 ms | 26,1 ms | **−42%** |
| suwak | 9,9 ms | 16,1 ms | scenariusz zmieniony — dziś rysuje pełną listę pod suwakiem, wcześniej sam suwak |

Warstwa pośrednia nie kosztowała nic — klatka **przyspieszyła**. Źródło zysku
jest jedno: pamięć podręczna przeszła z wiersza na pojedynczy napis, a wartości
po prawej stronie wierszy („12 kB”, „1,4 MB”) powtarzają się w liście
kilkakrotnie, więc rasteryzują się raz zamiast raz na wiersz.

**Dwie regresje złapane pomiarem i naprawione w trakcie:**

1. **Krawędź zaznaczenia rysowana wprost — +17 ms na klatkę.** Rozbicie paska
   zaznaczenia na dwa prymitywy (wypełnienie i krawędź) sprawiło, że krawędź
   przestała być częścią zapamiętanej bitmapy. Koszt `drawImage()` nie zależy
   od wielkości kształtu, tylko od wielkości płótna, więc kreska szeroka na dwa
   piksele kosztowała w czterdziestu sześciu wierszach więcej niż wszystkie
   napisy razem. Naprawa: cienkie kreski też idą przez pamięć podręczną.
   Scenariusz `zaznaczenie` spadł z 45,1 do 22,3 ms.
2. **Przygaszanie tła pod oknem modalnym — +75 ms w kwantyzacji.** Zmierzone
   trzy warianty: zasłona z `ImagickDraw` 34,3 ms, `modulateImage()` 23,2 ms,
   `evaluateImage()` 8,1 ms. Wygrał najtańszy — i tak nie wystarczyło, bo
   **każdy** z nich wypuszcza do klatki kolory spoza motywu, a na tym, że klatka
   bez bitmapy zawiera wyłącznie kolory motywu, stoi szybka ścieżka palety
   z kroku 17 (D34). Kwantyzacja skoczyła z 9 do 84 ms. Funkcję wycofano wraz
   z polem `Plane::dims`; powód zapisany w dokumentacji klasy, żeby nikt nie
   próbował drugi raz.

#### Odstępstwa od planu

1. **Kontrakt komponentu ma jedną metodę, nie dwie.** Plan przewidywał
   `measure()` obok `draw()`; pytanie było wpisane do rozstrzygnięć na starcie
   kroku i padło na „nie”. Rozmiar minimalny, preferowany i kolejność
   ustępowania niesie `Slot`, a komponent znający swoją naturalną wysokość
   (`Dialog::size()`) wystawia ją własną metodą. Dwa źródła tej samej liczby
   musiałyby się pilnować nawzajem.
2. **Prymitywów jest sześć, nie pięć.** Doszedł `Scrollbar`, a `Bar` zyskał
   grubość (`Weight`). Powód jest ten sam w obu przypadkach: suwak jest węższy
   od komórki, a przegroda w pasku stanu ma jeden piksel — złożenie ich
   z prostokątów wymagałoby od komponentu rachunku w pikselach, których nie zna.
   `Bitmap` niesie przy okazji podpis zastępczy, bo to renderer rozstrzyga, czy
   obraz się wczyta.
3. **`TextRun` ma pole `clearBehind`.** Etykieta wpięta w krawędź panelu leży
   **na linii obwódki** i musi wyciąć sobie miejsce, inaczej kreska przechodzi
   przez litery. Jedyny użytkownik, ale bez niego panel nie wygląda jak przed
   krokiem.
4. **Zgodność „piksel w piksel” — niesprawdzona, i to jest brak.** Zrzuty PNG
   sprzed przebudowy nie zostały wykonane, a starego kodu nie da się już
   uruchomić (repozytorium nie jest pod kontrolą wersji — D4). Zgodność
   **geometrii** jest za to zapewniona regułą lustrzanego mapowania prostokąta
   na piksele: lewa krawędź liczy się od lewej strony płótna, prawa od prawej,
   więc kształt pełnej szerokości ma jednakowe marginesy tak samo jak wcześniej.
   Bez tej reguły cała oprawa przesunęłaby się o resztę z dzielenia szerokości
   płótna przez liczbę kolumn — cztery piksele przy domyślnej konfiguracji.
   **Wygląd wymaga obejrzenia oczami pod XTermem przed uznaniem kroku za
   zamknięty.**
5. **Okno modalne w trybie tekstowym wygląda inaczej niż przed krokiem.**
   Renderer tekstowy dostał bufor komórek (`CellBuffer`) i rysuje obwódki
   znakami rysunkowymi zamiast składać wiersze z napisów. Bez bufora nakładane
   płaszczyzny nie dają się zrobić w ogóle.
6. **Opis pliku pod `Enterem` **został**.** Plan sugerował, że przenosi się do
   modułu, ale to jest pytanie otwarte **kroku 20**, nie decyzja kroku 18.
   Zachowanie bez zmian; zmieniło się tylko to, czym okno jest zbudowane
   (`Dialog` zamiast `Popup`) i że przypadek użycia oddaje dane
   (`EntryDescription`), a nie gotowy kształt okna.
7. **`SettingsCursor` zyskał wiersz czynności.** Przycisk przywracania ustawień
   domyślnych musiał dostać miejsce, które odwiedza kursor — to poprawka
   w kodzie kroku 14, policzona do zakresu tego kroku.

#### Czego nie zrobiono

- **Zrzutu PNG „przed”** — patrz odstępstwo 4. Kryterium „piksel w piksel”
  pozostaje niesprawdzone i jest jedynym niespełnionym kryterium ukończenia.
- **Oglądu wyglądu pod prawdziwym terminalem** — pomiar i testy nie zastąpią
  spojrzenia na łuki narożników i etykiety stref.

#### Wpływ na kroki 19 i 20

Kontrakt komponentu udźwignął przycisk bez zmian — `handle(KeyPress): bool`
wystarczyło. Pole tekstowe z kroku 19 wchodzi tą samą drogą, którą przetarł
`Button` na ekranie ustawień: kursor kieruje do niego klawisz, a nieobsłużony
wraca do ekranu. `ScreenInterface` wraz z `ScreenOutcome` jest gotowy, więc
krok 20 zaczyna wprost od kontraktu modułu.
