# Krok 19 — Okno komend

## Status

**Ukończony z zastrzeżeniem** (2026-08-09). Kod, testy, pomiar i dokumentacja
gotowe: PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, **707 testów** (1565
asercji) zielonych. Niespełnione zostaje jedno: **wygląd okna nie został
obejrzany pod prawdziwym terminalem** — pomiar i testy nie zastąpią spojrzenia
na karetkę, obwódkę pasa i listę podpowiedzi.

## Zależności

- **Krok 18** (komponenty interfejsu i płaszczyzny) — **ukończony**, twardo.
  Okno komend jest złożone z tego, co tam powstało: `ListView` na listę
  podpowiedzi, `Panel` na obwódkę, `KeyBinding` jako źródło opisu klawiszy,
  `ComponentInterface` i `FocusableInterface` jako kontrakt każdego elementu.
  **Wyjątkiem jest `TextInput`** — powstaje tutaj (P14 planu kroku 18), bo
  tutaj mieszka jego pierwszy użytkownik.
- **Krok 14** (konfiguracja) — komendy `core.theme` i `core.language` zmieniają
  ustawienie tą samą drogą co ekran ustawień (`ChangeSettingUseCase`), a nie
  własną.
- **Krok 15** (wielojęzyczność) — opisy komend i komunikaty o błędach składni
  są napisami widocznymi dla użytkownika i idą przez katalog jako klucze.
- **Krok 06** (terminal I/O) — krok **wraca do jego kodu**: `KeySequenceParser`
  i `KeyPress` poznają `Ctrl` (P17). Praca przeniesiona tutaj z kroku 20, bo
  `TextInput` jest pierwszym w aplikacji, który musi odróżnić znak od bajtu
  sterującego.

Krok **20** (moduły) zależy od tego kroku podwójnie: potrzebuje kontraktu
komendy dla zdolności `ProvidesCommands` **oraz** komponentu `TextInput` dla
pozycji tekstowej w zakładce ustawień modułu (D38).

## Model i wysiłek

**Opus / xhigh** (P25). Wyżej niż kroki 18 i 20, i to jest świadome: krok dotyka
naraz czterech rzeczy, z których każda ma własnych użytkowników w przyszłości —
pętli głównej (płaszczyzna modalna, która przyjmuje klawisze), warstwy wejścia
(`Ctrl`), katalogu komponentów (`TextInput`) i **publicznego API** (kontrakt
komendy, który moduły zastaną gotowy w kroku 20). Trzy z nich są nieodwracalne
tanim kosztem po tym, jak pojawią się pierwsi użytkownicy.

## Cel

Dać aplikacji jedno miejsce, w którym wywołuje się czynność **po nazwie**,
zamiast szukać dla niej wolnego klawisza.

Wzorzec: wiersz poleceń z `vima`. Klawisz **`F12`** otwiera okno komend nad
bieżącym ekranem; użytkownik wpisuje nazwę, a okno **pokazuje dostępne komendy**
i **uzupełnia** wpisywane nazwy.

Trzy rzeczy, których dziś nie ma, a które krok ma dać:

1. **Czynna płaszczyzna modalna** — okno, które przyjmuje klawisze i nie oddaje
   ich niżej. Krok 18 zapowiedział ją w celu 2, ale nie miał na czym sprawdzić:
   dzisiejsze okno modalne jest bierne i zamyka się pierwszym klawiszem.
2. **Pole tekstowe** — pierwszy komponent aplikacji przyjmujący dowolny znak.
3. **Komenda** — nazwana czynność z deklarowanymi argumentami, którą wnosi
   rdzeń albo moduł.

## Ustalenia (decyzje użytkownika, 2026-08-09)

Zapis w [00-decyzje.md](../00-decyzje.md), D39. P1–P8 zapadły przy planowaniu
kroku 18 (jako rozstrzygnięcie jego pytania P13); P9–P25 — przy dokańczaniu
tego planu.

| Pytanie | Wybór |
|---|---|
| **P1** — skąd pomysł | **Wiersz poleceń z `vima`** — okno komend zamiast skrótu na każdą czynność |
| **P2** — klawisz otwierający | **`F12`** |
| **P3** — podpowiadanie | Okno **pokazuje dostępne komendy** |
| **P4** — uzupełnianie | Okno ma **mechanizm autouzupełniania** wpisywanej nazwy |
| **P5** — kto wnosi komendy | **Każdy moduł definiuje własne komendy** (krok 20) |
| **P6** — skok do ścieżki | **Komenda modułu `file-info`**, nie rdzenia, i nie w tym kroku |
| **P7** — kolejność | Okno komend **przed** modułami: krok 19, moduły 20 |
| **P8** — `TextInput` | **Powstaje w tym kroku**, przy swoim użytkowniku |
| **P9** — mechanizm okna | **Płaszczyzna modalna z kursorem** — `LoopState` dostaje stos okien nakładanych zamiast pojedynczego `Dialog` |
| **P10** — miejsce w klatce | **Pas przy dolnej krawędzi**, jak w `vimie`; lista podpowiedzi wyrasta w górę |
| **P11** — kontrakt komendy | **Interfejs z deklaracją argumentów** |
| **P12** — argumenty | **Lista rozdzielana spacjami**; parser **w rdzeniu**, wspólny dla wszystkich; komenda dostaje argumenty z nazwą i wartością |
| **P13** — nazwy argumentów | **Pozycyjnie w wierszu, nazwy z deklaracji** |
| **P14** — spacje w wartości | **Cudzysłowy obsługiwane przez parser rdzenia** |
| **P15** — walidacja | **Rdzeń: obecność i rodzaj**; istnienie zasobu sprawdza sama komenda |
| **P16** — nazewnictwo | **Przestrzeń nazw**: `core.` dla rdzenia, `<id modułu>.` dla modułów |
| **P17** — `Ctrl` w wejściu | **Przeniesiony z kroku 20 do tego kroku** |
| **P18** — zasięg komend | **Jeden zbiór globalny**, niezależny od aktywnego ekranu |
| **P19** — komendy rdzenia | **`core.settings`, `core.help`, `core.quit`, `core.theme <nazwa>`, `core.language <kod>`** |
| **P20** — uzupełnianie nazw | **Lista filtruje się w locie, `Tab` uzupełnia** wspólny przedrostek |
| **P21** — podpowiedzi argumentów | **Komenda podpowiada sama**, w dwóch odmianach: **stałe** (liczone raz w `Bootstrap`) i **liczone na żądanie** |
| **P22** — pamięć podpowiedzi | **Gotowe wiersze `ListRow`**, nie napisy i nie prymitywy |
| **P23** — historia | **20 wpisów w pamięci i tyle samo w pliku** `~/.light-manager/`; zapis po zapełnieniu bufora i przy zamknięciu; do historii idzie **cały wiersz z argumentami**; przy pustym polu historia stoi **na górze listy** podpowiedzi |
| **P24** — warstwa kontraktu | **`Application/Command/`**; wynik komendy niesie **identyfikator ekranu**, nie jego obiekt |
| **P25** — model i wysiłek | **Opus / xhigh** |

### Trzy rozstrzygnięcia, które zmieniają zakres innych kroków

- **P17 przenosi `Ctrl` z kroku 20 do 19.** Flaga w `KeyPress`, rozpoznanie
  bajtów `0x01`–`0x1A` w parserze i `KeyBinding::ctrl()` powstają tutaj. Krok 20
  zastaje mechanizm gotowy i dokłada do niego wyłącznie **listę liter
  zarezerwowanych** dla skrótów modułów.
- **P21 i P6 razem odsuwają podpowiadanie ścieżek do kroku 20.** Rodzaj
  „liczone na żądanie” zostaje **zadeklarowany** w kontrakcie, ale pierwszą jego
  implementację wnosi `file-info.jump`. Powód jest zasadą kroku 18 (P5): każdy
  mechanizm powstaje przy prawdziwym użytkowniku, a żadna komenda rdzenia
  ścieżki nie przyjmuje.
- **P24 dokłada `CommandOutcome` wariant z identyfikatorem ekranu.** `core.settings`
  ma otworzyć ekran ustawień, a kontrakt w `Application` nie może zobaczyć
  `ScreenInterface`. Wiązanie idzie więc po `ScreenInterface::id()` — po napisie,
  który już dziś istnieje.

## Stan zastany (sprawdzony w kodzie 2026-08-09, po kroku 18)

| Element | Stan |
|---|---|
| `LoopState::$overlay` | **Pojedynczy `?Dialog`**, bierny. `InputHandler` zamyka go **pierwszym dowolnym klawiszem** i nic poza tym nie robi. |
| `FrameComposer::modal()` | Stawia okno **pośrodku**, w rozmiarze z `Dialog::size()`, przycięte do okna terminala. Pas przy dolnej krawędzi wymaga drugiej reguły umieszczania. |
| `Plane` | `Application/Ui`: identyfikator, prostokąt, prymitywy, `modal`. Płaszczyzna niesie **wynik narysowania**, nie komponent — drzewo komponentów nie przekracza portu. |
| `ScreenOutcome::opens(Dialog)` | Jedyna droga położenia czegoś na wierzchu; typ pola to konkretny `Dialog`, nie interfejs. |
| `ListView`, `ListRow` | Istnieją wraz z przewijaniem, zaznaczeniem i suwakiem — gotowa lista podpowiedzi. |
| `Panel`, `Label`, `StatusBar`, `Dialog`, `Button` | Istnieją. |
| `TextInput` | **Nie istnieje.** Kontrakt `FocusableInterface` (`handle(KeyPress): bool`) został w kroku 18 sprawdzony pod jego kątem i uznany za wystarczający. |
| `KeyPress`, `Key`, `KeySequenceParser` | Istnieją; **`Ctrl` nie istnieje jako pojęcie** — bajty `0x01`–`0x1A` wpadają jako `Key::Character` z bajtem sterującym w `raw`. |
| `Key::F12` | Ma przypadek w enumie, a parser rozpoznaje sekwencję `ESC [ 24 ~`. **Warstwa terminala jest gotowa.** |
| `InputHandler` | Trzy klawisze globalne (`F1`, `F2`, `F10`) wpisane wprost; `globalBindings()` jako jedno źródło obsługi, podpowiedzi i spisu w pomocy. |
| `ChangeSettingUseCase` | Istnieje; zmienia ustawienie **o krok w lewo lub w prawo**, nie na wskazaną wartość — `core.theme grafit` potrzebuje drugiej drogi. |
| `SettingsService` | Zapis atomowy przez plik tymczasowy i `rename()` w `~/.light-manager/` — wzór dla pliku historii. |
| Katalog `~/.light-manager/` | Powstaje **dopiero przy pierwszym zapisie** konfiguracji; historia musi liczyć się z jego brakiem. |

Dwie pozycje przesądzają o rozmiarze kroku: **bierne okno modalne** (trzeba je
zamienić na czynne) i **brak `Ctrl`** w warstwie wejścia.

## Zakres

### 1. Czynna płaszczyzna modalna

Dzisiejsze okno modalne jest napisem na wierzchu: `LoopState` trzyma `?Dialog`,
`FrameComposer` rysuje je pośrodku, a `InputHandler` zamyka pierwszym klawiszem.
Okno komend musi klawisze **przyjmować**, więc pojęcie „okna nakładanego” staje
się kontraktem:

```php
namespace LightManager\Presentation\Ui;

interface OverlayInterface extends ComponentInterface
{
    /** Prostokąt, jakiego okno chce w terminalu o podanym rozmiarze. */
    public function bounds(int $rows, int $columns): Rect;

    /** @return list<KeyBinding> źródło podpowiedzi i spisu w pomocy */
    public function bindings(): array;

    public function handle(KeyPress $key): OverlayOutcome;
}
```

`OverlayOutcome` jest bliźniakiem `ScreenOutcome`: okno zostaje, okno się
zamyka, aplikacja kończy pracę — plus opcjonalny `Message` do paska stanu.

Dwa okna nakładane, oba na tym samym kontrakcie:

| Okno | Zachowanie |
|---|---|
| `MessageOverlay` (dzisiejszy `Dialog`) | Bierne: **dowolny klawisz zamyka**. Zachowanie bez zmian, tylko opakowane w kontrakt. |
| `CommandOverlay` (okno komend) | Czynne: klawisze idą do niego i **nie schodzą niżej**, dopóki jest otwarte. |

`LoopState` dostaje **stos okien** (`OverlayStack`) zamiast pojedynczego pola.
Stos ma dziś jedno piętro i klasa ma to powiedzieć wprost — tak samo jak
`ScreenStack` mówi o swoich dwóch. Chodzi o to, by reguła „klawisz idzie do
wierzchu” miała jedno miejsce, zanim okien zrobi się więcej.

Wędrówka klawisza w `InputHandler` wygląda po zmianie tak:

```
okno nakładane (jeśli otwarte)  →  klawisze globalne (F1, F2, F10, F12)  →  aktywny ekran
```

Zmiana wobec dzisiaj jest jedna, ale zasadnicza: okno nakładane **może klawisz
zużyć albo przepuścić**, zamiast zawsze się zamykać.

### 2. Umieszczenie okna: pas przy dolnej krawędzi

Okno komend staje **tuż nad paskiem stanu** i zajmuje pełną szerokość:

```
┌ ŚCIEŻKA ────────────────────────────────┐
│ /home/sksz/Projects                     │
├ PLIKI ──────────────────────────────────┤
│ …                                       │
│ …                                       │
│ core.help          okno pomocy          │  ← lista podpowiedzi
│ core.language      język interfejsu     │     (wyrasta w górę)
│ core.quit          zakończ pracę        │
│ core.settings      ustawienia           │
├─────────────────────────────────────────┤
│ > core.se_                              │  ← pole wpisywania
├─────────────────────────────────────────┤
│ F1 pomoc · F2 ustawienia · F10 wyjście  │
└─────────────────────────────────────────┘
```

Wysokość okna to wiersz pola plus tyle wierszy listy, ile jest podpowiedzi — nie
więcej niż połowa okna terminala, żeby lista plików nie znikła pod spodem.
Umieszczenie liczy `FrameComposer` z `OverlayInterface::bounds()`, więc reguła
„pośrodku” dzisiejszego `Dialog`a zostaje nietknięta i obie żyją obok siebie.

### 3. Komponent `TextInput`

Pierwszy komponent przyjmujący dowolny znak — i pierwsza prawdziwa próba
katalogu z kroku 18. **Jeśli dołożenie go wymaga poprawki w kontrakcie
komponentu, znaczy to, że kontrakt był za wąski** — i lepiej dowiedzieć się tego
tutaj niż od pierwszego modułu.

Zakres edycji (P22 planu, patrz „Ustalenia”): dopisywanie znaku, `Backspace`,
`Delete`, kursor w lewo i w prawo, `Home` i `End`. Tyle, ile ma wiersz poleceń
`vima` — poprawienie literówki w środku ścieżki nie ma wymagać kasowania jej
końca. Zaznaczanie, kasowanie słowa i wklejanie **nie wchodzą** (patrz „Poza
zakresem”).

Dwie rzeczy, o których komponent musi wiedzieć:

- **Znak z `Ctrl` nie jest treścią.** `Ctrl+D` ma zostać przepuszczony wyżej,
  a nie wpisany do pola jako bajt sterujący. To jedyny powód, dla którego `Ctrl`
  przenosi się do tego kroku (P17).
- **Karetka to nie kursor.** Krok 18 nazwał „kursorem” (`Cursor`) ognisko
  wędrujące między komponentami; migające miejsce wpisywania w polu tekstowym
  jest czym innym i musi mieć własną nazwę — **karetka** — żeby dokumentacja się
  nie rozjechała.

### 4. `Ctrl` w warstwie wejścia (przeniesione z kroku 20)

`Ctrl`+litera przychodzi ze STDIN jako **jeden bajt sterujący** `0x01`–`0x1A`.

| Klasa | Zmiana |
|---|---|
| `KeyPress` | Pole `bool $ctrl`, konstruktor `KeyPress::ctrl(string $letter)`, uwzględnienie flagi w `equals()`. |
| `KeySequenceParser` | Bajty `0x01`–`0x1A` → litera z `ctrl: true`. **Cztery wyjątki zostają nazwanymi klawiszami**, bo są tym samym bajtem: `0x08` (Backspace), `0x09` (Tab), `0x0A` i `0x0D` (Enter). |
| `KeyBinding` | `KeyBinding::ctrl(string $character, string $descriptionKey)`; `matches()` **musi porównywać flagę** — bez tego wiązanie na literę `d` złapałoby `Ctrl+D`; `display()` składa „Ctrl+D”. |
| `TextInput` | Znak z `ctrl: true` przepuszcza wyżej. |

Krok 20 dokłada do tego już tylko listę liter zarezerwowanych (`c`, `h`, `i`,
`j`, `m`, `z`) i wykrywanie kolizji między modułami.

### 5. Kontrakt komendy

Leży w `Application/Command/` (P24) i nie zna ani jednego typu z `Presentation`.

```php
namespace LightManager\Application\Command;

interface CommandInterface
{
    /** Pełna nazwa z przestrzenią: `core.settings`, `file-info.jump`. */
    public function name(): string;

    /** Klucz katalogu napisów z opisem czynności. */
    public function descriptionKey(): string;

    /** @return list<CommandArgument> w kolejności, w jakiej padają w wierszu */
    public function arguments(): array;

    public function execute(CommandInput $input): CommandOutcome;
}
```

```php
final class CommandArgument
{
    public function __construct(
        public readonly string $name,              // nazwa z deklaracji (P13)
        public readonly CommandArgumentKind $kind, // Text | Number | Path
        public readonly bool $required,
        public readonly SuggestionSource $suggestions = SuggestionSource::None,
    ) {}
}
```

`CommandInput` niesie argumenty **z nazwą i wartością** (P12): mapa
`nazwa argumentu` → wartość, złożona przez parser rdzenia, plus surowy wiersz
dla komendy, która naprawdę go potrzebuje.

`CommandOutcome` niesie skutek: okno zostaje, okno się zamyka, aplikacja kończy
pracę — plus opcjonalny `Message` i **opcjonalny identyfikator ekranu do
otwarcia** (P24). Ten ostatni jest ceną za trzymanie kontraktu w `Application`;
wiązanie idzie po `ScreenInterface::id()`, więc rdzeń tłumaczy napis na obiekt
w jednym miejscu, a nieznany identyfikator jest błędem wykrywalnym testem.

**Podpowiedzi argumentów** (P21) deklaruje `SuggestionSource`:

| Wartość | Kto liczy i kiedy |
|---|---|
| `None` | Argument nie ma podpowiedzi (np. dowolny tekst). |
| `Fixed` | Rdzeń pyta komendę **raz, w `Bootstrap`**, i trzyma wynik w pamięci jako gotowe `ListRow` (P22). Tak działają `core.theme` i `core.language`. |
| `OnDemand` | Rdzeń pyta komendę przy każdej zmianie wpisanego przedrostka. **Zadeklarowane w tym kroku, implementowane w kroku 20** przez `file-info.jump`. |

Komenda, która cokolwiek podpowiada, implementuje osobną, opcjonalną zdolność:

```php
interface SuggestsArguments
{
    /** @return list<string> */
    public function suggestions(string $argument, string $prefix): array;
}
```

### 6. Parser wiersza — jeden dla wszystkich komend

Parser mieszka w rdzeniu (P12) i jest **jedynym** miejscem, które wie, jak wiersz
zamienia się w argumenty. Żadna komenda nie pisze tego drugi raz.

Kolejno:

1. **Podział na słowa** po spacjach, z obsługą **cudzysłowów** prostych
   i podwójnych (P14). `jump "/home/sksz/Moje pliki"` daje jeden argument;
   cudzysłowy są zdejmowane z wartości.
2. **Rozpoznanie nazwy** — pierwsze słowo; reszta to wartości.
3. **Mapowanie pozycyjne na nazwy z deklaracji** (P13): pierwsza wartość trafia
   pod nazwę pierwszego argumentu i tak dalej.
4. **Walidacja** (P15): brak argumentu wymaganego, nadmiarowa wartość, wartość
   nieliczbowa tam, gdzie zadeklarowano `Number`. Każdy z tych przypadków kończy
   się komunikatem złożonym z deklaracji — **jednakowym dla wszystkich komend**,
   bo składa go rdzeń.

Czego parser **nie** sprawdza: czy ścieżka istnieje, czy nazwa motywu jest
znana, czy plik da się otworzyć. To wie komenda i to ona o tym mówi.

### 7. Rejestr komend

`CommandRegistry` w `Application/Command/`, obok rejestru modułów z kroku 20
i na tych samych zasadach: zwykły obiekt, budowany w `Bootstrap`, operujący
wyłącznie na danych.

Zadania:

1. **Zebrać komendy** rdzenia i (od kroku 20) modułów w **jeden zbiór globalny**
   (P18).
2. **Wymusić przestrzeń nazw** (P16): od rdzenia przyjmuje wyłącznie nazwy
   zaczynające się od `core.`, od modułu `file-info` — wyłącznie od
   `file-info.`. Kolizja między modułami staje się przez to niemożliwa
   z konstrukcji, bo identyfikatory modułów są już pilnowane przez rejestr
   modułów; zostaje wyłącznie kolizja modułu z samym sobą, którą łapie test.
3. **Wyszukiwać po przedrostku** — do listy podpowiedzi.
4. **Liczyć najdłuższy wspólny przedrostek** pasujących nazw — do `Tab`.
5. **Złożyć w `Bootstrap` gotowe wiersze `ListRow`** dla wszystkich komend
   i dla podpowiedzi `Fixed` (P22). Rasteryzacja napisów ma własną pamięć
   podręczną kluczowaną treścią (krok 17, D34), więc powtarzalne wiersze nie
   kosztują drugi raz.

### 8. Zachowanie okna

| Klawisz | Czynność |
|---|---|
| `F12` | Otwiera okno; naciśnięty ponownie — zamyka (tak samo jak `F1` i `F2` na swoich ekranach) |
| znak | Dopisuje do pola; lista podpowiedzi filtruje się **w locie** (P20) |
| `Tab` | Uzupełnia najdłuższy wspólny przedrostek pasujących nazw |
| `↑` / `↓` | Chodzą po liście podpowiedzi |
| `Enter` | Uruchamia komendę — wpisaną albo wskazaną na liście |
| `Esc` | Zamyka okno bez uruchamiania |

**Przy pustym polu lista pokazuje najpierw historię, potem wszystkie komendy**
(P23, P19 „pełna lista od razu”). Jeden zbiór i jedna nawigacja: użytkownik nie
musi znać dodatkowego klawisza, żeby powtórzyć ostatnie wywołanie, a ten, kto
nie zna nazw, widzi je wszystkie od pierwszej chwili. Po pierwszym znaku lista
przechodzi w zwykłe podpowiedzi.

**Nieznana nazwa** zostawia okno otwarte, a powód stawia w pasku stanu (P18
pytania o nieznaną nazwę): wpisany tekst zostaje w polu do poprawienia, bo
literówka nie ma kosztować przepisania całego wiersza.

`Enter` znaczy w oknie komend to samo, co w całej aplikacji po decyzji P3
kroku 20: **zatwierdź**.

### 9. Komendy rdzenia

Pięć, i to na nich krok się sprawdza — do kroku 20 są jedyną zawartością okna
(P19):

| Komenda | Argument | Co robi |
|---|---|---|
| `core.settings` | — | Otwiera ekran ustawień (przez identyfikator ekranu, P24) |
| `core.help` | — | Otwiera okno pomocy |
| `core.quit` | — | Kończy pracę aplikacji |
| `core.theme` | `nazwa`, wymagany, podpowiedzi `Fixed` | Ustawia motyw na wskazany |
| `core.language` | `kod`, wymagany, podpowiedzi `Fixed` | Ustawia język interfejsu |

`core.theme` i `core.language` są tu ważniejsze, niż wygląda: to **pierwsze
prawdziwe użycie argumentu** i jedyni użytkownicy podpowiedzi `Fixed` w tym
kroku. Wymagają przy okazji drugiej drogi zmiany ustawienia —
`ChangeSettingUseCase` przesuwa dziś wartość **o krok**, a komenda ustawia
**wskazaną**.

### 10. Historia komend

- **20 wpisów w pamięci**, tyle samo w pliku `~/.light-manager/history` (P23).
- Do historii idzie **cały wiersz wraz z argumentami** — to argumentów nie chce
  się wpisywać drugi raz.
- **Zapis po zapełnieniu bufora i przy zamknięciu aplikacji**; plik jest
  nadpisywany w całości, tą samą drogą co konfiguracja (plik tymczasowy plus
  `rename()`), więc przerwany zapis zostawia poprzednią, poprawną wersję.
- **Odczyt przy starcie** wypełnia bufor, więc strzałka w górę działa od
  pierwszej chwili nowej sesji.
- Katalog `~/.light-manager/` może nie istnieć (powstaje przy pierwszym zapisie
  konfiguracji) — historia musi go założyć albo milcząco odpuścić zapis, tak jak
  robi to konfiguracja.

Historia jest **osobnym plikiem, nie kluczem w `settings.json`**: to nie jest
ustawienie, tylko ślad pracy, i ma inny cykl życia niż konfiguracja.

### 11. Warstwy i struktura katalogów

```
src/
├── Application/
│   ├── Command/                 # CommandInterface, CommandArgument,
│   │                            # CommandArgumentKind, SuggestionSource,
│   │                            # SuggestsArguments, CommandInput,
│   │                            # CommandOutcome, CommandLineParser,
│   │                            # CommandRegistry, CommandHistory
│   └── Port/
│       └── CommandHistoryPort   # odczyt i zapis pliku historii
├── Infrastructure/
│   └── Config/
│       └── CommandHistoryService
└── Presentation/
    ├── Ui/
    │   ├── Component/TextInput.php
    │   ├── OverlayInterface.php, OverlayOutcome.php
    │   └── Overlay/CommandOverlay.php, MessageOverlay.php
    └── Cli/
        ├── Command/             # SettingsCommand, HelpCommand, QuitCommand,
        │                        # ThemeCommand, LanguageCommand
        └── OverlayStack.php
```

Granica jest ta sama, którą krok 18 zapisał jednym zdaniem: **kontrakt komendy
jest daną, więc mieszka w `Application`; okno, które ją wywołuje, jest
interfejsem, więc mieszka w `Presentation`.** Komendy rdzenia leżą
w `Presentation/Cli/Command/`, bo dostają wstrzyknięte `ScreenStack`
i `LoopState` — strzałka `Presentation → Application` jest w dobrą stronę.

`docs/architecture.md` i `.claude/skills/light-manager-conventions/SKILL.md`
przyjmują nowe pojęcia (komenda, okno nakładane, karetka) **w tym samym
kroku** — dokument i Skill nie mają prawa się rozjechać.

## Poza zakresem tego kroku

- **Podpowiadanie ścieżek** — rodzaj `OnDemand` jest zadeklarowany, ale pierwszą
  implementację wnosi `file-info.jump` w kroku 20 (zasada „komponent przy
  użytkowniku”, P5 kroku 18).
- **Skok do ścieżki** — komenda modułu, krok 20 (P6).
- **Zaznaczanie, kasowanie słowa i wklejanie w `TextInput`** — `Ctrl+W` i jemu
  podobne wchodzą w przestrzeń skrótów, którą krok 20 rozdaje modułom.
- **Komendy zależne od ekranu** — zbiór jest globalny (P18).
- **Argumenty nazwane w wierszu** (`klucz=wartość`) i flagi.
- **Łączenie komend** (potok, średnik), skrypty, makra.
- **Komenda pytająca o potwierdzenie** — wynik komendy jest jednorazowy.
- **Znak ucieczki `\ `** — spacje w wartości załatwiają cudzysłowy (P14).
- **Historia współdzielona między uruchomieniami równoległymi** — plik jest
  nadpisywany w całości przez ostatni proces, który się zamknie.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Command/CommandInterface.php` | Application | Nowy — nazwa, opis, deklaracja argumentów, wykonanie. |
| `Application/Command/CommandArgument.php` | Application | Nowy — nazwa, rodzaj, wymagalność, źródło podpowiedzi. |
| `Application/Command/CommandArgumentKind.php` | Application | Nowy — enum `Text`, `Number`, `Path`. |
| `Application/Command/SuggestionSource.php` | Application | Nowy — enum `None`, `Fixed`, `OnDemand`. |
| `Application/Command/SuggestsArguments.php` | Application | Nowy — opcjonalna zdolność komendy. |
| `Application/Command/CommandInput.php` | Application | Nowy — argumenty z nazwą i wartością plus surowy wiersz. |
| `Application/Command/CommandOutcome.php` | Application | Nowy — zostaw / zamknij / zakończ, `Message`, identyfikator ekranu. |
| `Application/Command/CommandLineParser.php` | Application | Nowy — podział z cudzysłowami, mapowanie pozycyjne, walidacja obecności i rodzaju. |
| `Application/Command/CommandRegistry.php` | Application | Nowy — zbiór globalny, wymuszona przestrzeń nazw, wyszukiwanie po przedrostku, wspólny przedrostek. |
| `Application/Command/CommandHistory.php` | Application | Nowy — bufor 20 wpisów, wypełniany przy starcie, opróżniany do portu. |
| `Application/Port/CommandHistoryPort.php` | Application | Nowy — odczyt i zapis historii. |
| `Infrastructure/Config/CommandHistoryService.php` | Infrastructure | Nowy — plik `~/.light-manager/history`, zapis atomowy wzorem `SettingsService`. |
| `Application/Dto/KeyPress.php` | Application | Pole `ctrl`, konstruktor `ctrl()`, uwzględnienie w `equals()` (P17). |
| `Infrastructure/Terminal/KeySequenceParser.php` | Infrastructure | Bajty `0x01`–`0x1A` jako litera z `Ctrl`, poza czterema aliasami klawiszy nazwanych. |
| `Presentation/Ui/KeyBinding.php` | Presentation | `ctrl()`, porównanie flagi w `matches()`, „Ctrl+D” w `display()`. |
| `Presentation/Ui/Component/TextInput.php` | Presentation | Nowy — pole wpisywania z karetką i edycją w wierszu. |
| `Presentation/Ui/OverlayInterface.php`, `OverlayOutcome.php` | Presentation | Nowe — kontrakt okna nakładanego i jego skutek. |
| `Presentation/Ui/Overlay/CommandOverlay.php` | Presentation | Nowy — okno komend: pole, lista podpowiedzi, uzupełnianie, historia. |
| `Presentation/Ui/Overlay/MessageOverlay.php` | Presentation | Nowy — dzisiejszy `Dialog` w kontrakcie okna nakładanego (dowolny klawisz zamyka). |
| `Presentation/Cli/OverlayStack.php` | Presentation | Nowy — które okno jest na wierzchu i dokąd wraca `Esc`. |
| `Presentation/Cli/LoopState.php` | Presentation | `?Dialog` ustępuje stosowi okien nakładanych. |
| `Presentation/Ui/ScreenOutcome.php` | Presentation | Pole `overlay` przyjmuje `OverlayInterface`, nie konkretny `Dialog`. |
| `Presentation/Cli/InputHandler.php` | Presentation | `F12` w klawiszach globalnych; okno nakładane może klawisz **zużyć albo przepuścić**, zamiast zawsze się zamykać. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Umieszczanie okna z `OverlayInterface::bounds()` obok dzisiejszej reguły „pośrodku”. |
| `Presentation/Cli/Command/*` | Presentation | Nowe — pięć komend rdzenia (`settings`, `help`, `quit`, `theme`, `language`). |
| `Application/UseCase/ChangeSettingUseCase.php` | Application | Druga droga: ustawienie **wskazanej** wartości obok dzisiejszego przesunięcia o krok. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Rejestr komend, wstępne złożenie wierszy `ListRow`, wczytanie historii, zapis historii w `shutdown()`; `VERSION` → `0.19.0`. |
| `lang/pl.php`, `lang/en.php` | Napisy | Opisy pięciu komend rdzenia, komunikaty parsera (brak argumentu, zła wartość, nieznana komenda), etykieta okna komend. |
| `README.md` | Dokumentacja | Sekcja „Okno komend”, `F12` w tabeli sterowania. |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Pojęcia: komenda, okno nakładane, karetka — **w tym samym kroku**. |
| `20-moduly-plugins.md` | Plan | `Ctrl` schodzi z zakresu kroku 20 do 19; sekcja 8 kroku 20 dostaje gotowy kontrakt komendy. |
| testy | Testy | Parser (cudzysłowy, mapowanie pozycyjne, braki i nadmiary, rodzaje), rejestr (przestrzeń nazw, powtórzenia, wyszukiwanie, wspólny przedrostek), `TextInput` (edycja, karetka, znak z `Ctrl`), parser bajtów sterujących, `KeyBinding` z `Ctrl`, okno komend (uzupełnianie, historia na górze pustej listy, nieznana nazwa), historia (bufor, zapis po zapełnieniu i przy zamknięciu, odczyt przy starcie), pięć komend rdzenia. |

## Kryteria ukończenia

- `F12` otwiera okno komend nad dowolnym ekranem; ekran pod spodem **zostaje
  widoczny**, a klawisze nie schodzą do niego, dopóki okno jest otwarte.
- Okno nakładane potrafi klawisz **zużyć albo przepuścić** — dzisiejsze
  „dowolny klawisz zamyka” zostaje wyłącznie zachowaniem `MessageOverlay`.
- Pole tekstowe pozwala poprawić literówkę **w środku** wpisanego wiersza:
  strzałki, `Home`, `End`, `Delete`, `Backspace`.
- Znak wpisany z `Ctrl` **nie trafia do pola**; Backspace, Tab i Enter nadal
  przychodzą jako Backspace, Tab i Enter.
- Kontrakt komponentu z kroku 18 udźwignął `TextInput` **bez zmian** — a jeśli
  nie, poprawka jest opisana w dzienniku wraz z powodem (to sprawdzian, który
  krok 18 sobie zadał).
- Lista podpowiedzi filtruje się w locie, `Tab` uzupełnia wspólny przedrostek,
  `↑`/`↓` chodzą po liście, `Enter` uruchamia wskazaną komendę.
- Przy pustym polu lista pokazuje **historię na górze, a pod nią wszystkie
  komendy**.
- Pięć komend rdzenia działa: `core.settings` i `core.help` otwierają ekrany,
  `core.quit` kończy pracę, `core.theme` i `core.language` ustawiają **wskazaną**
  wartość i podpowiadają dopuszczalne.
- Parser rozumie cudzysłowy, mapuje wartości pozycyjnie na nazwy z deklaracji
  i odrzuca wywołanie niekompletne albo ze złym rodzajem wartości — komunikatem
  **jednakowym dla wszystkich komend**.
- Nieznana nazwa zostawia okno otwarte wraz z wpisanym tekstem i stawia powód
  w pasku stanu.
- Historia przeżywa ponowne uruchomienie: 20 wpisów w pamięci i w pliku, zapis
  po zapełnieniu bufora i przy zamknięciu, cały wiersz wraz z argumentami.
- Rejestr komend przyjmuje wyłącznie nazwy w przestrzeni właściciela (`core.`
  dla rdzenia) i wykrywa powtórzenie nazwy — sprawdza to test.
- Podpowiedzi `Fixed` powstają **raz, w `Bootstrap`**, jako gotowe `ListRow`;
  klatka z otwartym oknem komend nie liczy ich ponownie.
- Klatka z otwartym oknem komend zmierzona `bin/render-bench` i opisana
  w dzienniku — również wtedy, gdy wynik jest niekorzystny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` i `SKILL.md` opisują komendę, okno nakładane i karetkę
  — zgodnie ze sobą.
- `README.md` opisuje okno komend i `F12` w tabeli sterowania.

## Do rozstrzygnięcia na starcie kroku

Pytania planistyczne **P1–P25 są zamknięte** (sekcja „Ustalenia”, D39).
Poniższe to rozstrzygnięcia wykonawcze:

1. **Czym rysuje się karetka.** `Bar` o cienkiej grubości (prymityw z kroku 18),
   `Highlight` pod znakiem, czy odwrócenie ról koloru na jednej komórce?
   W trybie tekstowym odpowiedź może być inna niż w Sixelu.
2. **Czy karetka miga.** Miganie wymaga czasu w komponencie, a `LoopState`
   jest jedynym miejscem, które dziś zna zegar — i to dla komunikatów.
   Karetka nieruchoma jest tańsza i nie budzi pytania o takt.
3. **Ile wierszy dostaje lista podpowiedzi** przy krótkim oknie terminala.
   Połowa wysokości to propozycja, nie pomiar; drabinka ustępowania z kroku 13
   ma tu swoje zdanie.
4. **Czy `OverlayStack` jest osobną klasą, czy polem w `LoopState`.** Dziś ma
   jedno piętro; `ScreenStack` powstał jako osobna klasa przy dwóch.
5. **Jak `CommandRegistry` trafia do okna komend** — wstrzyknięty w `Bootstrap`
   przy budowie okna, czy podawany przy otwarciu. Od tego zależy, czy okno da
   się zbudować raz, czy powstaje przy każdym `F12`.
6. **Czy pamięć wierszy `ListRow` unieważnia się po zmianie języka.** Opisy
   komend są tłumaczone przy składaniu wiersza w `Bootstrap`, a `core.language`
   potrafi ten język zmienić **w trakcie** — bez unieważnienia okno pokaże
   poprzedni język do restartu.
7. **Gdzie kończy się `CommandInput::raw()`.** Surowy wiersz jest wygodny, ale
   komenda, która go użyje zamiast argumentów, obchodzi walidację rdzenia.

## Dziennik realizacji

### 2026-08-09 — krok wykonany

**Stan:** kod, testy, pomiar i dokumentacja gotowe. PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag, **707 testów** (1565 asercji) zielonych — przybyło 90
wobec stanu po kroku 18.

#### Co powstało

| Warstwa | Klasy |
|---|---|
| `Application/Command` | `CommandInterface`, `CommandArgument`, `CommandArgumentKind`, `SuggestionSource`, `SuggestsArguments`, `CommandInput`, `CommandOutcome`, `CommandTransition`, `CommandLine`, `CommandCompletion`, `CommandLineParser`, `CommandRegistry`, `CommandRejection`, `CommandHistory`, `Prefix` |
| `Application/Port` | `CommandHistoryPort` |
| `Infrastructure/Config` | `CommandHistoryService` |
| `Presentation/Ui` | `OverlayInterface`, `OverlayOutcome`, `NeedsTime` |
| `Presentation/Ui/Component` | `TextInput` |
| `Presentation/Ui/Overlay` | `CommandOverlay`, `MessageOverlay`, `Suggestion` |
| `Presentation/Cli` | `OverlayStack`, `Command/ScreenCommand`, `Command/SettingCommand`, `Command/QuitCommand` |

Zmienione: `KeyPress` (flaga `ctrl`), `KeySequenceParser` (bajty `0x01`–`0x1A`),
`KeyBinding` (`ctrl()`, porównanie flagi, „Ctrl+D”), `LoopState` (stos okien
i czas klatki zamiast pojedynczego `Dialog`), `ScreenOutcome` (pole `overlay`
przyjmuje `OverlayInterface`), `InputHandler` (`F12`, wędrówka przez okno),
`FrameComposer` (płaszczyzna okna z `bounds()`), `GameLoop` (`tick()`),
`HelpScreen` (sekcje spisu spoza ekranów), `ChangeSettingUseCase` (`set()`),
`Bootstrap` (rejestr, okno, historia, `VERSION` 0.19.0), `ScenarioFactory`
(scenariusz `command`).

#### Wynik pomiaru

`bin/render-bench --scenarios=chrome-text,popup,command --iterations=9 --warmup=4`
(1000×600 px, siatka 166×46, grafit, paleta 64):

| Scenariusz | Rysowanie | Kwantyzacja | Kodowanie | Razem | Blob |
|---|---|---|---|---|---|
| ramki z tekstem | 6,9 ms | 8,3 ms | 4,4 ms | **20,7 ms** | 23,6 kB |
| klatka z okienkiem | 11,0 ms | 9,1 ms | 4,5 ms | **25,5 ms** | 29,3 kB |
| okno komend | 16,1 ms | 8,0 ms | 4,5 ms | **28,8 ms** | 26,0 kB |

Okno komend kosztuje **+8,1 ms** wobec zwykłej klatki listy i mieści się
w budżecie taktu (33 ms przy 30 kl./s). Cały przyrost siedzi w rysowaniu i ma
oczywiste źródło: pas jest szeroki na całe okno, niesie obwódkę panelu, pięć
wierszy listy z paskiem zaznaczenia i wiersz pola. Kwantyzacja i kodowanie
nie drgnęły — okno nie wprowadza do klatki ani jednego koloru spoza motywu, więc
szybka ścieżka palety z kroku 17 (D34) działa dalej.

**Cena migającej karetki:** płaszczyzna okna zmienia podpis dwa razy na sekundę,
więc jej pamięć podręczna trafia w około 28 z 30 klatek zamiast w 30. Przy
16,1 ms rysowania daje to około 1 ms na sekundę — mierzalne, ale nieodczuwalne.

#### Odstępstwa od planu

1. **Pamięć podpowiedzi trzyma klucze, nie gotowe `ListRow`** — odwrócenie P22,
   wybrane przez użytkownika przy starcie kroku (rozstrzygnięcie wykonawcze nr 6).
   Powodem była zmiana języka komendą `core.language`: gotowe wiersze zostałyby
   w poprzednim języku do restartu. Rejestr trzyma więc komendy, a wiersz składa
   się przy rysowaniu — rasteryzacja napisu i tak ma własną pamięć (D34).
   `prepare()` zostaje i liczy raz to, co naprawdę stałe: **wartości** podpowiedzi
   `Fixed`.
2. **Karetka miga** (rozstrzygnięcie nr 2). Komponent nie zna `microtime()` —
   zegar przychodzi z `LoopState`, przez nowy, jednometodowy interfejs
   `NeedsTime`. To jedyna klasa spoza planu; jej brak oznaczałby albo `useTime()`
   w `OverlayInterface` (i pustą metodę w oknie z opisem pliku), albo czas
   wołany wewnątrz komponentu, czyli koniec testowalności.
3. **Klawisz globalny przy otwartym oknie zamyka okno.** Plan mówił „okno →
   klawisze globalne → ekran”, ale nie mówił, co się dzieje z oknem, gdy zadziała
   klawisz globalny. `F1` z otwartym oknem komend znaczy „pokaż pomoc”, a nie
   „pokaż pomoc pod spodem”.
4. **`Enter` uruchamia wskazaną pozycję tylko po ruchu strzałkami.** Plan mówił
   „wpisaną albo wskazaną”, bez reguły rozstrzygającej. Bez znacznika „wskazano”
   każde wywołanie brałoby pierwszą pozycję listy i wpisany wiersz nie miałby
   jak zadziałać.
5. **Wpis powtórzony przesuwa się na koniec historii**, zamiast dokładać kopię.
   Dwadzieścia miejsc dzielonych z dziesięciokrotnie powtórzonym skokiem to
   historia bez historii.
6. **Jedna klasa na dwie komendy otwierające ekran** (`ScreenCommand`), nie dwie.
   Różnią się wyłącznie dwoma napisami.
7. **`CommandInput` nie ma `raw()`** (rozstrzygnięcie wykonawcze nr 7 —
   rozstrzygnięte przez pominięcie). Komenda dostaje wyłącznie sprawdzone
   argumenty; surowy wiersz pozwalałby obejść walidację rdzenia, a nie miał
   dziś ani jednego użytkownika.
8. **`Prefix`** — mała klasa pomocnicza w `Application/Command`, bo wspólny
   przedrostek liczą dwa miejsca (nazwy komend w rejestrze, wartości argumentów
   w oknie) i nie ma powodu, by istniał dwa razy.

#### Rozstrzygnięcia wykonawcze ze startu kroku

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Czym rysuje się karetka | `Highlight` na jednej komórce — ten sam pasek, co zaznaczenie w liście; znak pod nią w roli `SelectionText` |
| 2 | Czy karetka miga | Tak, pół sekundy świeci, pół gaśnie |
| 3 | Wysokość listy podpowiedzi | Tyle, ile trzeba, nie więcej niż połowa okna |
| 4 | `OverlayStack` osobno czy w `LoopState` | Osobna klasa, trzymana przez `LoopState` |
| 5 | Jak rejestr trafia do okna | Wstrzyknięty w `Bootstrap`; okno powstaje raz na uruchomienie |
| 6 | Pamięć a zmiana języka | Pamięć trzyma klucze (patrz odstępstwo 1) |
| 7 | `CommandInput::raw()` | Nie powstaje (patrz odstępstwo 7) |

#### Czego nie zrobiono

- **Oglądu wyglądu pod prawdziwym terminalem** — jedyne niespełnione kryterium.
  Do sprawdzenia: czy karetka jest widoczna na tle paska zaznaczenia, czy pas
  okna nie zlewa się z paskiem stanu i czy migotanie nie rozprasza.
- **Powodów odrzucenia komendy nie widać w interfejsie.** `CommandRejection`
  powstaje, jest testowany i wraca z rejestru, ale nikt go użytkownikowi nie
  pokazuje: komenda rdzenia nie ma jak zostać odrzucona (nazwy są w kodzie
  i pilnuje ich test), a pierwszym prawdziwym źródłem odrzuceń będą komendy
  modułów w kroku 20 — wraz z paskiem, w którym mają się pojawić.

#### Poprawka po pierwszym uruchomieniu (2026-08-09)

Użytkownik zobaczył, że **miniatura z pasa podglądu przebija się przez okno
komend**. Przyczyna: `Panel` rysuje `RoundRect` z wypełnieniem `null`, czyli samą
obwódkę — okno zbudowane z panelu niczego nie zakrywa. `Dialog` (opis pliku)
problemu nie miał, bo wypełnia się `Role::Surface` sam z siebie.

Rozstrzygnięcie użytkownika: **nieprzezroczystość należy do kontraktu klatki**,
a nie do dyscypliny autora okna. `Plane` zyskało flagę `opaque` (domyślnie
`fałsz`), a oba renderery wymazują prostokąt takiej płaszczyzny, zanim ją
narysują — Sixel kolorem tła motywu, tryb tekstowy spacją na tle. Flaga musi być
opcjonalna: `chrome` i `content` obejmują całe okno, więc gdyby zakrywały
bezwarunkowo, treść wymazywałaby oprawę.

Wymazanie idzie przez **zapamiętaną bitmapę i `compositeImage`**, a nie przez
`drawImage()`: ten ostatni kosztuje tyle, ile całe płótno, niezależnie od
wielkości kształtu — ta sama pułapka złapała krawędź zaznaczenia w kroku 18
(+17 ms na klatkę). Pomiar A/B tej samej klatki, 25 przebiegów naprzemiennych:
rysowanie **20,4 ms z wymazywaniem wobec 18,6 ms bez** — **+1,76 ms**. Kwantyzacja
nie drgnęła, bo prostokąt jest w kolorze tła motywu i nie wpuszcza do klatki
żadnej barwy spoza palety (D34).

Pomiary całego potoku z tego dnia nie nadają się do porównania z tabelą powyżej:
maszyna była obciążona i sama klatka listy — nietknięta tą poprawką — spowolniła
z 20,7 do 31,1 ms. Liczbę kosztu wymazywania daje wyłącznie pomiar A/B.

Sprawdzają to dwa testy: `SixelFrameEncoderTest` (piksel pod nieprzezroczystą
płaszczyzną ma kolor tła motywu) i podpis płaszczyzny (pamięć podręczna nie ma
prawa pomylić zakrywającej z przezroczystą).

#### Wpływ na krok 20

Kontrakt komendy stoi, więc zdolność `ProvidesCommands` ma na czym się oprzeć.
`Ctrl` w warstwie wejścia jest gotowy — krokowi 20 zostaje z niego wyłącznie
lista liter zarezerwowanych. `TextInput` przeszedł przez kontrakt komponentu
z kroku 18 **bez zmiany kontraktu**, więc pozycja tekstowa w zakładce ustawień
modułu wchodzi tą samą drogą. Podpowiedzi `OnDemand` czekają na swojego
pierwszego użytkownika: `file-info.jump`.
