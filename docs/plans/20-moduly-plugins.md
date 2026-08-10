# Krok 20 — Moduły (plugins)

> **Historia numeracji:** krok nosił wcześniej numer 18, przez chwilę 19,
> a ostatecznie stoi na 20. Przed nim stanęły dwa kroki, od których zależy
> kontrakt modułu: [18 — komponenty interfejsu i płaszczyzny](18-komponenty-i-plaszczyzny.md)
> (ukończony) oraz [19 — okno komend](19-okno-komend.md) (plan niedokończony).
> Plan poniżej został **przepisany po ukończeniu kroku 18** — dawna sekcja
> „Wpływ kroków 18 i 19” zniknęła, bo jej treść jest już wbudowana w tekst.

## Status

**Ukończony** (2026-08-09). Kod, testy i dokumentacja gotowe: PHPStan `max` bez
błędów, PHP-CS-Fixer bez uwag, **781 testów** (1757 asercji) zielonych. Wygląd
sprawdzony pod prawdziwym terminalem (patrz „Dziennik realizacji”).

Pytania planistyczne były zamknięte przed startem (P1–P17 poniżej,
[00-decyzje.md](00-decyzje.md), D38); rozstrzygnięcia wykonawcze zapadły na
starcie kroku i są zapisane w D41.

**Uzupełnienie po dokończeniu planu kroku 19** (2026-08-09, D39): kontrakt
komendy jest już rozstrzygnięty, więc sekcja 8 przestała być miejscem
zarezerwowanym. Zmieniło się przy tym rozgraniczenie kroków — `Ctrl` w warstwie
wejścia **schodzi do kroku 19** (D39, P17), bo to tam mieszka jego pierwszy
użytkownik, komponent `TextInput`.

**Uzupełnienie po zaplanowaniu kroku 21** (2026-08-09, D40): przeglądarka plików
ma się stać modułem — i to modułem domyślnym — w [kroku 21](21-przegladarka-jako-modul.md).
Do tego kroku wraca jedna zmiana, i to zawczasu:

- **`ReadsCurrentDirectory` nie powstaje.** Zastępuje ją `ReadsContext` wraz
  z `ModuleContext` — kontekstem sesji złożonym z danych pierwotnych (ścieżka,
  nazwa zaznaczenia, rodzaj wpisu). Powód: w kroku 21 `Directory` schodzi do
  modułu przeglądarki, a zdolność wymieniająca ten typ kazałaby modułowi
  `FileInfo` zobaczyć typ innego modułu. Pierwszy moduł projektu nie ma
  powstawać na kontrakcie, który ginie krok później (D40, P5 i P9).

Krok 21 **niczego więcej z tego planu nie zmienia przed czasem**; uchyla za to
przy okazji jedną jego zasadę — patrz „Poza zakresem tego kroku”.

## Ustalenia (decyzje użytkownika, 2026-08-09)

Zapis w [00-decyzje.md](00-decyzje.md), D38. Uzupełniają i w trzech punktach
zmieniają D30, która powstała przed krokiem 18.

| Pytanie | Wybór |
|---|---|
| **P1** — komendy modułu wobec nieukończonego planu kroku 19 | **Miejsce zarezerwowane** — plan 20 mówi, czego potrzebuje od kontraktu komendy, ale go nie przesądza. **Nieaktualne od D39**: kontrakt zapadł, sekcja 8 jest już konkretna |
| **P2** — miejsce kontraktu po kroku 18 | **Podział na dwie warstwy** — dane w `Application/Module/`, zdolności dotykające interfejsu w `Presentation/Ui/Module/` |
| **P3** — `Enter` na pliku po migracji okienka | **Reguła ogólna „zatwierdź”, a na pliku nic** — opis pliku otwiera skrót modułu |
| **P4** — zakładka ustawień modułu | **Opisana danymi** — rdzeń rysuje, prowadzi kursor i zapisuje |
| **P5** — `ReadsCurrentDirectory` | **Na ekranie modułu**, nie na klasie modułu. **Zmienione przez D40**: zdolność nazywa się `ReadsContext` i przyjmuje `ModuleContext`, nie `Directory` |
| **P6** — `version()` w kontrakcie | **Usunięta** — wraca, gdy moduły staną się zewnętrzne |
| **P7** — klawisze modułów | **`Ctrl` + znak wybrany przez moduł** |
| **P8** — pomoc | **Zakładka na moduł**: część automatyczna z deklaracji plus wiersze modułu |
| **P9** — model `Ctrl` | **Flaga `ctrl` w `KeyPress`**; parser rozpoznaje bajty sterujące, `KeyBinding::ctrl()` dopasowuje i wyświetla. **Wykonanie przeniesione do kroku 19** (D39, P17) |
| **P10** — sygnały terminala | **Bez `-isig`** — `Ctrl+C`, `Ctrl+Z` i `Ctrl+\` zostają sygnałami i trafiają na listę zarezerwowanych |
| **P11** — skrót modułu `FileInfo` | **`Ctrl+D`** („detail information”) |
| **P12** — rodzaje pozycji ustawień modułu | **Cztery**: przełącznik, wybór z listy, liczba z listy, **pole tekstowe** |
| **P13** — walidacja pozycji tekstowej | **Rdzeń, według deklaracji** (wzorzec, długość maksymalna) |
| **P14** — dowód dla zakładki ustawień | **`FileInfo` dostaje dwie pozycje**: limit czasu polecenia `file` i jego dodatkowe argumenty |
| **P15** — miejsce `ModuleRegistry` | **Całość w `Application/Module/`**; skrót deklarowany jako dana (`ModuleShortcut`), nie jako `KeyBinding` |
| **P16** — napisy modułu | **Własne pliki napisów w katalogu modułu**, scalane przez katalog, z **wymuszonym przedrostkiem `module.<id>.`** |
| **P17** — minimum kontraktu | **Wszystkie zdolności opcjonalne** — moduł bez ekranu jest legalny |

### Co P2, P9 i P15 zmieniają wobec D30

D30 mówiła „kontrakt w `src/Application/Module/`”, bo powstała wtedy, gdy ekran
był przypadkiem enuma, a nie obiektem w `Presentation`. Po decyzji P3 kroku 18
`ScreenInterface`, `KeyBinding` i wszystkie komponenty leżą w `Presentation/Ui`,
więc interfejs `ProvidesScreen` opisany w `Application` sięgałby po klasę
z warstwy leżącej **na zewnątrz** niego — strzałka w złą stronę. Stąd podział
z P2 i konsekwencja z P15: rejestr, który ma zostać w `Application`, nie może
oprzeć wykrywania kolizji na `KeyBinding` i dostaje własną, czysto danową
postać skrótu.

P9 dokładała do zakresu coś, czego D30 nie przewidywała: **poprawkę w warstwie
wejścia z kroku 06**, bo modułowy `Ctrl`+znak nie ma dziś jak zaistnieć — bajt
sterujący wpada do aplikacji jako zwykły znak. Po D39 ta poprawka **należy do
kroku 19**: pierwszym, który musi odróżnić literę od bajtu sterującego, jest
pole tekstowe okna komend, a nie skrót modułu. Krokowi 20 zostaje z niej
wyłącznie **lista liter zarezerwowanych** i wykrywanie kolizji.

## Zależności

- **Krok 18** (komponenty i płaszczyzny) — **ukończony**. Dał to, na czym
  stoi cały ten krok: `ScreenInterface` wraz z `ScreenOutcome` (ekrany są już
  obiektami), `KeyBinding` jako jedno źródło obsługi, podpowiedzi i spisu
  w pomocy, katalog komponentów, z których złoży się ekran modułu, oraz pasek
  zakładek w oknie pomocy. **Punkt „ekrany jako obiekty” zniknął z zakresu
  tego kroku w całości** (P8 kroku 18).
- **Krok 19** (okno komend) — twardo, i to potrójnie (plan gotowy, D39):
  1. **kontrakt komendy** (`CommandInterface` i spółka w `Application/Command/`),
     którego potrzebuje zdolność `ProvidesCommands` (sekcja 8);
  2. **komponent `TextInput`**, bez którego nie ma pozycji tekstowej
     w zakładce ustawień modułu (P12) ani trybu edycji, w którym `Enter`
     zatwierdza, a `Esc` wycofuje;
  3. **`Ctrl` w warstwie wejścia** — flaga w `KeyPress`, rozpoznanie bajtów
     sterujących w parserze i `KeyBinding::ctrl()`, bez których skrót modułu nie
     ma jak zaistnieć (D39, P17).
  Krok 20 nie da się zacząć przed ukończeniem kroku 19.
- **Krok 14** (konfiguracja i ekran ustawień) — moduł dokłada zakładkę do
  istniejącego paska i zapisuje ustawienia w istniejącym pliku. Krok otwiera
  przy okazji `SettingsTab` i `SettingsCursor`, dziś zamknięte na enumie.
- **Krok 15** (wielojęzyczność) — etykiety zakładek, nazwy ustawień i treść
  pomocy idą przez katalog napisów. Katalog uczy się scalać źródła (P16).
- **Krok 06** (terminal I/O) — nie jest zależnością w sensie kolejności, ale
  krok 20 **wraca do jego kodu**: `KeySequenceParser` i `KeyPress` muszą
  poznać `Ctrl` (P9).
- **Krok 17** (optymalizacja) — nieaktualna w dawnym brzmieniu: ekran modułu
  nie oddaje już `FrameLine`, tylko rysuje się komponentami. Zależność zostaje
  jako fakt historyczny, nie jako wymaganie.

## Model i wysiłek

**Opus / high.** Krok zakłada **pierwsze publiczne API projektu w części
niewykonanej przez krok 18**: kontrakt modułu wraz z rejestrem, otwiera
zamknięte dziś struktury ustawień, dokłada `Ctrl` do warstwy wejścia i wyprowadza
z rdzenia działającą funkcję. Kontrakt modułu jest tym rodzajem decyzji, którą
trudno cofnąć po tym, jak powstaną pierwsze moduły — kosztuje wtedy nie jeden
refaktor, lecz tyle refaktorów, ile modułów.

## Cel

Dać projektowi miejsce, w którym dopisuje się funkcję, nie dotykając rdzenia.

Moduł ma **pięć** punktów zaczepienia:

1. własne okno (ekran zajmujący środkowy panel) wraz ze skrótem `Ctrl`+znak,
2. własną zakładkę w oknie konfiguracji,
3. własną zakładkę w oknie pomocy,
4. własne napisy w katalogu tłumaczeń,
5. własne **komendy** w oknie komend z kroku 19 — piąty punkt, dodany po
   decyzji P13 kroku 18.

Miarą powodzenia jest jedno zdanie: **dopisanie modułu ze wszystkimi pięcioma
punktami wymaga jednej zmiany w rdzeniu — dopisania klasy do listy
w `Bootstrap`.**

## Stan zastany (sprawdzony w kodzie 2026-08-09, po kroku 18)

| Element | Stan |
|---|---|
| `ScreenInterface` (`Presentation/Ui`) | **Istnieje** i rozszerza `ComponentInterface`: `id()`, `labelKey()`, `usesPreview()`, `headerSuffix()`, `bindings()`, `handle()`, `draw()`. Ekran modułu implementuje go **bez żadnych zmian w kontrakcie**. |
| `ScreenOutcome` | Istnieje: `stay()`, `close()`, `quit()`, `opens(Dialog)`. Wystarcza modułom bez zmian. |
| `ScreenStack`, `InputHandler` | Istnieją; stos ma dwa piętra (przeglądarka + jeden ekran nad nią), `InputHandler` zna trzy klawisze globalne (`F1`, `F2`, `F10`) i **dwa ekrany wpisane w konstruktor** — to miejsce, w które wejdzie mapa skrótów modułów. |
| Enum `Screen` | **Nie istnieje** — usunięty w kroku 18. |
| `HelpScreen` | Istnieje wraz z paskiem zakładek, ale zakładki są **dwie i przełączane binarnie** (`$tab === TAB_KEYS ? TAB_ABOUT : TAB_KEYS`). Spis klawiszy składa z `bindings()` ekranów zameldowanych przez `knowAbout()`. |
| `SettingsScreen`, `SettingsCursor` | Istnieją; kursor chodzi po `SettingsTab::cases()`, a `position()` rozpisuje `match` po `SettingKey`. |
| `SettingsTab`, `SettingKey` | **Enumy z zamkniętą listą przypadków** — moduł nie dołoży do nich nic. To wciąż główna przeszkoda, dokładnie jak zapowiadała D30. |
| `Settings`, `SettingsService` | Istnieją; `Settings` ma sześć pól skalarnych i nie ma miejsca na podprzestrzeń modułów. Zapis atomowy przez plik tymczasowy — do wykorzystania bez zmian. |
| `KeyPress`, `Key`, `KeySequenceParser` | Istnieją; **`Ctrl` nie istnieje jako pojęcie**. Bajty `0x01`–`0x1A` wpadają jako `Key::Character` z bajtem sterującym w `raw`. |
| `KeyBinding` | Istnieje: `of(list<Key>)`, `character(string)`, `matches()`, `display()`. Brakuje wariantu z `Ctrl`. |
| `Catalog`, `TranslatorService` | Istnieją; katalog czyta **jeden plik na język** z jednego katalogu (`lang/`). Scalania źródeł nie ma. |
| Okienko `file` | `InspectSelectedEntryUseCase` + `FileInspectorPort` + `FileInspectorService` + `Dto/EntryDescription`; otwierane `Enter`em w `BrowserScreen`, pokazywane komponentem `Dialog`. Krok 18 świadomie zostawił tę decyzję temu krokowi. |
| `Key::Tab` | Rozpoznawany przez parser, **niezwiązany z niczym** — żaden ekran nie ma na nim wiązania. |
| Tryb surowy | `-icanon -echo -ixon min 1 time 0`; `ISIG` **zostaje włączone**, obsługiwane sygnały: `SIGINT`, `SIGTERM`, `SIGHUP`, `SIGQUIT`. |

Dwie pozycje przesądzają o rozmiarze kroku: **enumy ustawień** (trzeba je
otworzyć) i **brak `Ctrl` w warstwie wejścia** (trzeba go dołożyć). Reszta
rdzenia jest po kroku 18 gotowa.

## Zakres

### 1. Kontrakt modułu — dane w `Application`, interfejs w `Presentation`

Baza plus zdolności; moduł implementuje wyłącznie te interfejsy, które
odpowiadają temu, co naprawdę wnosi (P17). Rejestr i strona `Presentation`
rozpoznają zdolności przez `instanceof`.

```php
namespace LightManager\Application\Module;

interface ModuleInterface
{
    /** Identyfikator: `[a-z][a-z0-9-]*`; zarazem klucz w pliku konfiguracyjnym
     *  i przedrostek napisów (`module.<id>.`). */
    public function id(): string;

    /** Klucz katalogu napisów z nazwą modułu — nie sam napis (krok 15). */
    public function nameKey(): string;

    /** Klucz katalogu z jednym zdaniem: po co ten moduł istnieje. */
    public function descriptionKey(): string;

    /** Skrót otwierający ekran modułu; `null`, gdy moduł ekranu nie wnosi. */
    public function shortcut(): ?ModuleShortcut;
}
```

`version()` **znika z kontraktu** (P6): moduły są wbudowane w repozytorium, więc
ich wersja zawsze równałaby się `Bootstrap::VERSION`. Pole wraca wtedy, gdy
moduły staną się zewnętrzne i naprawdę będą mogły się z aplikacją rozjechać.

Zdolności rozkładają się na dwie warstwy (P2) — granicą jest to, czy interfejs
wymienia typ z `Presentation`:

| Zdolność | Warstwa | Metody | Po co |
|---|---|---|---|
| `ProvidesSettingsTab` | `Application/Module` | `settingsTab(): ModuleSettingsTab` | Zakładka w oknie konfiguracji, opisana danymi |
| `ProvidesScreen` | `Presentation/Ui/Module` | `screen(): ScreenInterface` | Własne okno modułu |
| `ProvidesHelpTab` | `Presentation/Ui/Module` | `helpKeys(): list<string>` | Wiersze dopisywane pod automatyczną częścią zakładki pomocy |
| `ProvidesCommands` | `Application/Module` | `commands(): list<CommandInterface>` | Komendy w oknie komend (sekcja 8) |
| `ReadsContext` | `Presentation/Ui/Module` | `useContext(ModuleContext $context): void` | **Implementuje ją ekran, nie moduł** (P5) |

**Dlaczego `ReadsContext` leży na ekranie.** To ekran korzysta z kontekstu, nie
moduł; moduł byłby wyłącznie posłańcem do własnego ekranu. Rdzeń sprawdza
`instanceof` na obiekcie zwróconym przez `screen()` i podaje mu kontekst przed
złożeniem klatki — dokładnie w miejscu, w którym dziś `FrameComposer` pyta ekran
o treść.

**Dlaczego kontekst jest daną pierwotną, a nie `Directory`** (D40, P5). Zdolność
w pierwotnym brzmieniu (`useDirectory(Directory)`) działa dopóty, dopóki katalog
należy do rdzenia. W kroku 21 schodzi on do modułu przeglądarki, a wtedy
`FileInfo` czytałby typ innego modułu — koniec zasady „moduły się nie znają”.
Kontekst niesie więc napis, napis i enum:

```php
namespace LightManager\Application\Module;

final class ModuleContext
{
    public function __construct(
        /** Ścieżka bieżącego miejsca. */
        public readonly string $path,
        /** Nazwa zaznaczenia albo `null`, gdy nic nie jest zaznaczone. */
        public readonly ?string $selection = null,
        public readonly ContextEntryKind $kind = ContextEntryKind::None,
    ) {}
}
```

Kontekst trzyma `LoopState`, a **publikuje go ekran, który zna bieżące miejsce**
— w tym kroku jeszcze rdzeniowy `BrowserScreen`, od kroku 21 ekran modułu
`browser`. Brak wydawcy daje kontekst pusty, nie `null`: odbiorca ma czytać, a nie
sprawdzać istnienie.

**Dlaczego „własne okno” i „przejęcie listy plików” to jedna zdolność, nie
dwie.** Ekran z definicji zajmuje środkowy panel — ten sam, w którym normalnie
stoi lista plików — więc każdy ekran modułu *już* zastępuje jej treść. Jedyna
różnica między oknem niezależnym (spis zakładek) a alternatywnym widokiem
katalogu (drzewo, siatka miniatur) polega na tym, czy ekran chce dostawać
kontekst. Jeden mechanizm, jedna ścieżka w `FrameComposer`, o jeden kontrakt
mniej.

Ekran modułu odpowiada za wszystko wewnątrz panelu: treść, zaznaczenie,
przewijanie i klawisze. Rdzeń nie zakłada, że zaznaczenie w ogóle istnieje.
Poza panelem nie zmienia się nic — ścieżka u góry, pasek stanu u dołu i pas
podglądu zostają w gestii rdzenia (kontrakt `ScreenInterface` z kroku 18). Ta
ostatnia zasada obowiązuje **do kroku 21**, w którym rdzeń traci katalog i wraz
z nim podstawę do rysowania ścieżki oraz podglądu (D40, P6).

### 2. Skrót modułu i `Ctrl` w warstwie wejścia

Moduł bierze **`Ctrl` + literę** (P7). Rdzeń nie rezerwuje ani jednej litery
(P6 kroku 18) ani żadnej kombinacji z `Ctrl` — zajęte są wyłącznie klawisze
funkcyjne (`F1` pomoc, `F2` ustawienia, `F10` wyjście, `F12` okno komend
z kroku 19), `Esc` i klawisze nawigacji, które i tak należą do ekranów.

**Skrót jest daną, nie `KeyBinding`iem** (P15) — inaczej rejestr w `Application`
nie mógłby go zobaczyć:

```php
namespace LightManager\Application\Module;

final class ModuleShortcut
{
    /** @param string $character pojedyncza litera `a`–`z` */
    public function __construct(
        public readonly string $character,
        public readonly bool $ctrl = true,
    ) {}
}
```

Flaga `ctrl` jest dziś zawsze `true`, a walidacja odrzuca `false` — pole
istnieje po to, żeby lista kombinacji zarezerwowanych i porównywanie skrótów
miały pełną tożsamość klawisza, a nie sam znak. `KeyBinding` do podpowiedzi
i do spisu w pomocy składa z niej strona `Presentation`.

#### Co ten krok zastaje gotowe, a co dokłada

`Ctrl`+litera przychodzi ze STDIN jako **jeden bajt sterujący** `0x01`–`0x1A`.
Rozpoznanie tego faktu — flaga `ctrl` w `KeyPress`, przekład bajtów w
`KeySequenceParser`, `KeyBinding::ctrl()` wraz z napisem „Ctrl+D” — **powstaje
w kroku 19** (D39, P17), bo pierwszym, który musi odróżnić literę od bajtu
sterującego, jest tamtejsze pole tekstowe.

Krok 20 zastaje to gotowe i dokłada **tylko listę liter zarezerwowanych**
(niżej) wraz z wykrywaniem kolizji w rejestrze. To jedyne, czego skróty modułów
potrzebują ponad mechanizm z kroku 19.

#### Litery zabronione

Po decyzji P10 (**bez `-isig`** — awaryjne przerwanie z klawiatury i zawieszanie
procesu zostają) zabronionych jest **sześć liter**, każda z konkretnego powodu:

| Litera | Bajt | Powód |
|---|---|---|
| `c` | `0x03` | `SIGINT` — nie dociera do aplikacji |
| `h` | `0x08` | to samo, co Backspace |
| `i` | `0x09` | to samo, co Tab |
| `j` | `0x0A` | to samo, co Enter |
| `m` | `0x0D` | to samo, co Enter |
| `z` | `0x1A` | `SIGTSTP` — nie dociera do aplikacji |

Zostaje **dwadzieścia liter**. `Ctrl+S` i `Ctrl+Q` są wśród nich, bo tryb surowy
już dziś wyłącza sterowanie przepływem (`-ixon`). `Ctrl+\` (`SIGQUIT`) i `Ctrl+[`
(Esc) nie są literami, więc nie mają jak się pojawić w deklaracji.

Lista zabronionych stoi **w jednym miejscu w kodzie** — jako stała rejestru —
i jest tym samym źródłem dla wykrywania kolizji i dla testu.

### 3. Rejestr modułów

`ModuleRegistry` — zwykły obiekt (nie Singleton), tworzony w `Bootstrap`
z gotowej listy instancji modułów i z wczytanych ustawień. Leży **w całości
w `Application/Module/`** (P15) i operuje wyłącznie na danych: identyfikatorach,
skrótach i podprzestrzeni `modules` konfiguracji. O ekranach nie wie nic.

Zadania:

1. **Odsiać moduły wyłączone** w konfiguracji (`modules.<id>.enabled`).
2. **Sprawdzić tożsamości** — powtórzony `id()` albo `id()` niezgodny
   z `[a-z][a-z0-9-]*` to błąd.
3. **Sprawdzić skróty** — litera spoza listy dozwolonych (sekcja 2) oraz
   kolizja między modułami.
4. **Odrzucić moduł kolizyjny w całości** — nie tylko jego skrót. Moduł, do
   którego nie da się wejść, a który dokłada zakładki, myliłby bardziej, niż
   pomagał.
5. **Wystawić wynik**: listę modułów przyjętych i listę odrzuceń
   (`ModuleRejection`: identyfikator modułu plus klucz katalogu z powodem).

**Powód odrzucenia jest daną, nie wyjątkiem.** D30 przewidywała
`ModuleException`; rezygnujemy z niej, bo rejestr niczego nie przerywa —
odrzucenie jest **wynikiem** jego pracy, a wyjątek musiałby zostać złapany
w miejscu wywołania i natychmiast zamieniony z powrotem na daną.

`Bootstrap` składa z odrzuceń jeden `Message` w tonie `Warning` i stawia go
w pasku stanu przy starcie — tak samo, jak dziś robi to z nieczytelnym
katalogiem startowym i z uwagą do pliku konfiguracji.

**Lista klas modułów żyje w `Bootstrap`** — w tym samym miejscu, które już dziś
jest jedynym znającym konkretne klasy `Infrastructure`.

**Kolizja jest wykrywalna testem.** Moduły są wbudowane w repozytorium, a lista
jest w kodzie, więc test składający pełny komplet modułów łapie kolizję, zanim
zobaczy ją użytkownik. Przełącznik „włączony/wyłączony” tego nie psuje:
wyłączenie modułu może kolizję tylko usunąć, nigdy stworzyć.

### 4. Zakładka w oknie konfiguracji

`SettingsTab` i `SettingKey` są dziś enumami, więc krok musi je otworzyć:

| Dziś | Po zmianie |
|---|---|
| `SettingsTab` — enum `Appearance`/`Graphics` | Obiekt wartości: klucz etykiety plus pozycje. Lista zakładek składana przy starcie: dwie rdzeniowe, po jednej na moduł z `ProvidesSettingsTab`, na końcu „Moduły” |
| `SettingKey` — enum z sześcioma przypadkami | **Zostaje** — opisuje ustawienia rdzenia. Pozycje modułu opisuje `ModuleSetting` |
| `SettingsCursor` — chodzi po `SettingsTab::cases()` | Chodzi po liście zakładek podanej z zewnątrz |
| `SettingsScreen::position()` — `match` po `SettingKey` | Dwie drogi: rdzeniowa (jak dziś) i modułowa (z `ModuleSetting`), obie kończące się tym samym komponentem |

Zakładka modułu deklaruje pozycje **danymi, nie kodem** (P4) — dzięki temu ekran
ustawień rysuje ją tym samym kodem, co zakładki rdzenia, moduł nie musi nic
wiedzieć o rysowaniu, a zapis wartości ma jedno miejsce w całej aplikacji:

```php
namespace LightManager\Application\Module;

final class ModuleSetting
{
    public function __construct(
        public readonly string $key,          // klucz w `modules.<id>`
        public readonly string $labelKey,     // `module.<id>.setting.<klucz>`
        public readonly ModuleSettingKind $kind,
        /** @var list<string|int> dopuszczalne wartości — dla Choice i Number */
        public readonly array $choices,
        public readonly string|int|bool $default,
        /** Wzorzec i długość — wyłącznie dla Text; sprawdza je rdzeń (P13). */
        public readonly ?string $pattern = null,
        public readonly ?int $maxLength = null,
    ) {}
}
```

Cztery rodzaje pozycji (P12) i komponenty, którymi się rysują:

| Rodzaj | Komponent | Zmiana wartości |
|---|---|---|
| `Toggle` | `Toggle` (krok 18) | strzałki poziome |
| `Choice` | `Choice` (krok 18) | strzałki poziome, cyklicznie po `choices` |
| `Number` | `Choice` | jak wyżej; wartość liczbowa zamieniana na napis przez rdzeń |
| `Text` | **`TextInput` (krok 19)** | `Enter` wchodzi w edycję i zatwierdza, `Esc` wycofuje |

**Pozycja tekstowa jest jedynym miejscem, które dokłada ekranowi ustawień nowy
tryb.** Trzy pozostałe rodzaje zmieniają się strzałkami, bez stanu pośredniego;
tekst wymaga edycji znak po znaku, zatwierdzenia i wycofania. To jest ta część
kroku, której nie da się zacząć przed krokiem 19 — i drugi, obok komend, powód
twardej zależności.

**Walidację robi rdzeń według deklaracji** (P13): przy zatwierdzeniu sprawdza
`pattern` i `maxLength`, a wartość niezgodną odrzuca wraz z komunikatem w pasku
stanu, zostawiając poprzednią. Moduł nie dostaje wywołania zwrotnego — dzięki
temu deklaracja pozycji pozostaje czystymi danymi, dającymi się porównać
i zapisać.

**Zapis:** `Settings` zyskuje pole `modules` — mapa `id modułu` → mapa `klucz` →
wartość. W pliku daje to podobiekt obok kluczy rdzenia:

```json
{
  "language": "auto",
  "theme": "grafit",
  "paletteColors": 64,
  "modules": {
    "file-info": { "enabled": true, "timeout": 2, "arguments": "" }
  }
}
```

Ustawienia modułu dziedziczą po kroku 14 całą obsługę: zapis atomowy przez plik
tymczasowy, milczące pomijanie nieznanych kluczy, wartość domyślna plus
komunikat przy wartości spoza zakresu. **Ustawienia modułu nieznanego zostają
w pliku nietknięte** — moduł chwilowo wyłączony (albo usunięty z listy
w `Bootstrap`) nie ma tracić swojej konfiguracji.

### 5. Zakładka „Moduły” w ustawieniach

Osobna zakładka rdzenia, dokładana zawsze — także wtedy, gdy nie ma ani jednego
modułu (pusty spis mówi wprost, że mechanizm istnieje).

Dla każdego modułu: nazwa, skrót otwierający, przełącznik włączony/wyłączony.
Moduł odrzucony przy starcie stoi na liście wraz z powodem odrzucenia
i **nie da się go włączyć** — kolizji skrótu nie usunie przełącznik, tylko
poprawka w kodzie.

Zmiana przełącznika zapisuje się natychmiast, jak każde inne ustawienie. Skutek
jest widoczny **po ponownym uruchomieniu**: mapa skrótów, lista ekranów i lista
zakładek powstają raz, przy starcie. Ekran mówi o tym wprost, zamiast zostawiać
użytkownika z wrażeniem, że przełącznik nie działa.

### 6. Zakładka w oknie pomocy

Okno pomocy **istnieje od kroku 14**, a pasek zakładek dostało w kroku 18 — do
zrobienia zostaje sama zakładka modułu oraz jedna poprawka w rdzeniu:
`HelpScreen` przełącza dziś zakładki **binarnie** (dwie, na przemian), a musi
chodzić po liście dowolnej długości, cyklicznie, jak `Tabs` w ustawieniach.

Zakładka modułu składa się z dwóch części (P8):

1. **Część automatyczna**, generowana przez rdzeń z deklaracji modułu: nazwa
   i opis, skrót otwierający, klawisze obsługiwane przez jego ekran
   (`ScreenInterface::bindings()`), pozycje jego zakładki ustawień. Ta część nie
   ma prawa skłamać po zmianie wiązania, bo pochodzi z tego samego miejsca, co
   samo wiązanie — dokładnie tak, jak spis klawiszy rdzenia od kroku 18.
2. **Część własna** — wiersze z `helpKeys()`, dopisywane poniżej. Metoda zwraca
   **klucze katalogu**, nie napisy (P16), więc treść pomocy tłumaczy się tak samo
   jak reszta interfejsu.

### 7. Napisy modułu

Moduł niesie **własne pliki napisów** w swoim katalogu (P16):

```
src/Module/FileInfo/lang/pl.php
src/Module/FileInfo/lang/en.php
```

`Catalog` uczy się scalać źródła, a `TranslatorService` — przyjmować je przed
pierwszym tłumaczeniem (katalogi wczytuje leniwie, więc wystarczy zarejestrować
źródła w `Bootstrap`, zanim ruszy pętla).

**Przedrostek jest wymuszony:** z plików modułu katalog przyjmuje wyłącznie
klucze zaczynające się od `module.<id>.`; pozostałe pomija i mówi o tym
komunikatem. Kolizja z kluczem rdzenia staje się przez to **niemożliwa
z konstrukcji**, a źródło każdego napisu widać po samej nazwie klucza. Test
kompletności języków (krok 15) obejmuje pliki modułów tak samo jak rdzeniowe.

### 8. Komendy modułu

Kontrakt komendy zapadł w kroku 19 (D39), więc ta sekcja przestała być miejscem
zarezerwowanym. Wszystko, czego krok 20 od niego potrzebował, jest spełnione:

| Potrzeba kroku 20 | Jak spełniona w kroku 19 |
|---|---|
| Warstwa kontraktu zgodna z podziałem z P2 | `CommandInterface` leży w `Application/Command/` — czyli tam, gdzie dane; zdolność `ProvidesCommands` mieści się więc obok `ProvidesSettingsTab`, po stronie `Application` |
| Przestrzeń nazw modułu | `<id modułu>.` — **wymuszona przez rejestr komend**, dokładnie jak `module.<id>.` dla napisów |
| Kolizja wykrywana przy starcie | Rejestr komend odrzuca nazwę spoza przestrzeni właściciela i nazwę powtórzoną; test łapie to przed użytkownikiem |
| Opis komendy jako klucz katalogu | `CommandInterface::descriptionKey()` |

```php
namespace LightManager\Application\Module;

interface ProvidesCommands
{
    /** @return list<CommandInterface> nazwy muszą zaczynać się od `<id>.` */
    public function commands(): array;
}
```

**Pierwsza komenda modułu: `file-info.jump <ścieżka>`** — skok do wskazanego
katalogu (decyzja P13 kroku 18). Jest zarazem **pierwszą w całym projekcie
implementacją podpowiedzi liczonych na żądanie** (`SuggestionSource::OnDemand`):
krok 19 zadeklarował ten rodzaj, ale nie miał dla niego użytkownika, bo żadna
komenda rdzenia nie przyjmuje ścieżki. Uzupełnianie ścieżek z systemu plików
powstaje więc tutaj — przy swoim użytkowniku, zgodnie z zasadą P5 kroku 18.

Ekran modułu nie jest do tego potrzebny: komenda działa niezależnie od tego,
czy `FileInfo` jest otwarty, bo zbiór komend jest globalny (D39, P18).

### 9. Pierwszy moduł — `FileInfo`

Dowód, że kontrakt wystarcza. Opis zaznaczonego pliku wyprowadza się z rdzenia
do `src/Module/FileInfo/` wraz z testami: `InspectSelectedEntryUseCase`,
`FileInspectorPort`, `FileInspectorService` oraz `Dto/EntryDescription`.

Moduł implementuje `ModuleInterface`, `ProvidesSettingsTab`, `ProvidesHelpTab`
i `ProvidesCommands`, a jego ekran — `ScreenInterface` i `ReadsContext`.
**Wszystkie pięć punktów zaczepienia w jednym module**, więc migracja przeciera
każdą ścieżkę kontraktu naraz.

- **Identyfikator:** `file-info`; katalog `src/Module/FileInfo/`.
- **Skrót:** `Ctrl+D` (P11) — „detail information”. Litera `d` jest wolna:
  `0x04` nie znaczy w trybie surowym EOF-u (`-icanon`).
- **Ustawienia** (P14): `timeout` — limit czasu polecenia `file`, liczba z listy
  (`1`, `2`, `5`, `10` sekund); `arguments` — dodatkowe argumenty polecenia,
  pole tekstowe z wzorcem odrzucającym znaki powłoki. Dwie pozycje, dwa nowe
  rodzaje: jeden moduł przeciera obie ścieżki.
- **Ekran:** panel z opisem zaznaczonego pliku, dziś pokazywany w oknie
  modalnym. Ekran dostaje kontekst sesji przez `ReadsContext` i składa ścieżkę
  opisywanego pliku z jego dwóch pól — katalogu i nazwy zaznaczenia.
- **Komenda:** `file-info.jump <ścieżka>` — skok do wskazanego katalogu wraz
  z podpowiadaniem ścieżek (sekcja 8). **Przenosi się do modułu przeglądarki
  w kroku 21** jako `browser.jump` (D40, P8): po wyprowadzeniu nawigacji tylko
  ona umie zmienić katalog. W tym kroku zostaje tam, gdzie jest — bo tu jest
  jedynym modułem, który może ją wnieść.

**`Enter` na pliku przestaje otwierać opis** (P3). `Enter` staje się w całej
aplikacji klawiszem **zatwierdzania**: na katalogu wchodzi do środka, w polu
tekstowym zatwierdza wartość, w oknie komend (krok 19) uruchamia komendę.
Na pliku nie robi **nic** — nie ma czego zatwierdzić — tak samo, jak dziś
zachowuje się na pustym katalogu. Do zmiany: usunięcie `InspectSelectedEntryUseCase`
z `BrowserScreen`, klucz `help.key.open` w `lang/pl.php` i `lang/en.php` (dziś
„wejście do katalogu, dla pliku — opis”) oraz tabela sterowania w `README.md`.

### 10. Warstwy i struktura katalogów

```
src/
├── Application/
│   └── Module/                  # dane i rejestr: ModuleInterface,
│                                # ModuleShortcut, ModuleSettingsTab,
│                                # ModuleSetting, ModuleSettingKind,
│                                # ProvidesSettingsTab, ProvidesCommands,
│                                # ModuleRegistry, ModuleRejection,
│                                # ModuleContext, ContextEntryKind
├── Presentation/
│   └── Ui/
│       └── Module/              # zdolności dotykające interfejsu:
│                                # ProvidesScreen, ProvidesHelpTab,
│                                # ReadsContext
└── Module/
    └── FileInfo/
        ├── Application/         # UseCase/, Port/, Command/
        ├── Infrastructure/      # FileInspectorService
        ├── Presentation/        # FileInfoModule, FileInfoScreen
        └── lang/                # pl.php, en.php
```

Reguła zależności zyskuje jedną strzałkę, bez wyjątków od dotychczasowych:

```
Module → Presentation → Application → Domain
Module → Application → Domain
Module → Domain
```

- Moduł **nigdy** nie sięga do `Infrastructure` rdzenia inaczej niż przez port.
- Moduł **nigdy** nie sięga do innego modułu — moduły nie znają się nawzajem.
- Klasa modułu (ta implementująca `ModuleInterface`) to **zwykły obiekt**,
  tworzony `new`-em w `Bootstrap` z wstrzykniętymi portami; nie jest Singletonem
  i nie woła `getInstance()`. Usługi w jego własnej warstwie `Infrastructure`
  pozostają Singletonami na dotychczasowych zasadach.
- Moduł powtarza wewnątrz podział na warstwy, ale **katalog warstwy pustej po
  prostu nie powstaje** — `FileInfo` nie ma własnego słownika domenowego, więc
  nie ma katalogu `Domain/`.
- Klasa modułu leży w jego warstwie `Presentation`, bo implementuje zdolności
  wymieniające typy z `Presentation/Ui`.

`docs/architecture.md` i `.claude/skills/light-manager-conventions/SKILL.md`
przyjmują te zasady **w tym samym kroku** — dokument i Skill nie mają prawa się
rozjechać (zasada z §Wstępu `architecture.md`).

### 11. Awaria modułu

Bez granicy `try/catch` zakładanej specjalnie dla modułów. Moduł wbudowany
w repozytorium jest tym samym kodem co rdzeń i podlega tym samym zasadom:
wyjątek domenowy trafia do paska stanu przez istniejącą obsługę
w `InputHandler`, każdy inny przerywa aplikację i zostawia ślad.

Wyjątek dotyczy wyłącznie **startu**: nieprawidłowy moduł (kolizja skrótu,
zabroniona litera, powtórzony identyfikator) jest odrzucany przez rejestr wraz
z opisem powodu, bo błąd konfiguracji zestawu modułów nie ma prawa odebrać
użytkownikowi menadżera plików.

## Poza zakresem tego kroku

- **Moduły ładowane w runtime** spoza repozytorium (`~/.light-manager/modules/`,
  paczki Composera) — świadomie odłożone; kontrakt ma dojrzeć na modułach
  wbudowanych, zanim stanie się API dla obcego kodu.
- **Konfigurowalne skróty klawiszowe** — kolizję rozwiązuje poprawka w kodzie,
  nie zmiana wiązania przez użytkownika.
- **Rozszerzony protokół klawiatury** (`modifyOtherKeys`, protokół `kitty`) —
  odzyskałby `Ctrl+I`, `Ctrl+M` i `Ctrl+H`, ale kosztem przebudowy warstwy
  wejścia i ścieżki awaryjnej dla terminali, które go nie umieją. Sześć
  zabronionych liter jest tańsze niż ten dług.
- **Wyłączenie sygnałów** (`-isig`) — `Ctrl+C`, `Ctrl+Z` i `Ctrl+\` zostają
  sygnałami (P10).
- **Dokładanie stref do klatki przez moduł** (własny pas obok podglądu, drugi
  panel) — moduł dostaje środkowy panel i nic poza nim. **Zasada obowiązuje
  do kroku 21**: gdy katalog zejdzie do modułu, rdzeń nie będzie miał z czego
  narysować ścieżki ani podglądu, więc obie strefy przejdą do ekranu (D40, P6).
  Dwie zostają rdzeniowi na zawsze: oprawa stref i pasek stanu.
- **Komunikacja między modułami**, zdarzenia, wspólna szyna.
- **Moduł zmieniający renderowanie** (własny renderer, własna paleta, własny
  prymityw).
- **Moduł działający w tle** między klatkami — ekran modułu żyje wtedy, gdy jest
  otwarty.
- **Przeładowanie zestawu modułów bez restartu.**
- **Kontrakt komendy** — należy do kroku 19 (sekcja 8).

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Module/ModuleInterface.php` | Application | Nowy — tożsamość, klucze napisów, skrót. |
| `Application/Module/ModuleShortcut.php` | Application | Nowy — litera plus `ctrl`; postać danowa skrótu (P15). |
| `Application/Module/ProvidesSettingsTab.php` | Application | Nowy. |
| `Application/Module/ModuleSettingsTab.php` | Application | Nowy — zakładka opisana danymi. |
| `Application/Module/ModuleSetting.php` | Application | Nowy — pozycja: klucz, etykieta, rodzaj, wartości, domyślna, wzorzec, długość. |
| `Application/Module/ModuleSettingKind.php` | Application | Nowy — enum: `Toggle`, `Choice`, `Number`, `Text`. |
| `Application/Module/ModuleRegistry.php` | Application | Nowy — odsiew, walidacja tożsamości i skrótów, lista zarezerwowanych liter. |
| `Application/Module/ModuleRejection.php` | Application | Nowy — powód odrzucenia jako dana, nie wyjątek. |
| `Presentation/Ui/Module/ProvidesScreen.php` | Presentation | Nowy — `screen(): ScreenInterface`. |
| `Presentation/Ui/Module/ProvidesHelpTab.php` | Presentation | Nowy — `helpKeys(): list<string>`. |
| `Presentation/Ui/Module/ReadsContext.php` | Presentation | Nowy — `useContext(ModuleContext)`; implementuje ją **ekran** modułu (P5, D40). |
| `Application/Module/ModuleContext.php` | Application | Nowy — kontekst sesji jako dane pierwotne: ścieżka, nazwa zaznaczenia, rodzaj (D40). |
| `Application/Module/ContextEntryKind.php` | Application | Nowy — enum: `File`, `Directory`, `None`. |
| `Application/Module/ProvidesCommands.php` | Application | Nowy — komendy modułu w przestrzeni `<id>.` (sekcja 8). |
| `Module/FileInfo/Application/Command/JumpCommand.php` | Module | Nowy — `file-info.jump <ścieżka>`; pierwsza w projekcie implementacja podpowiedzi `OnDemand`. |
| `Application/Dto/SettingsTab.php` | Application | Enum ustępuje obiektowi wartości; lista zakładek składana przy starcie. |
| `Application/Dto/SettingsCursor.php` | Application | Chodzi po liście zakładek podanej z zewnątrz, nie po `cases()`. |
| `Application/Dto/Settings.php` | Application | Pole `modules`, odczyt i podmiana wartości pojedynczego modułu. |
| `Application/UseCase/ChangeModuleSettingUseCase.php` | Application | Nowy — zmiana pozycji modułu wraz z walidacją wzorca i długości (P13). |
| `Infrastructure/Config/SettingsService.php` | Infrastructure | Odczyt i zapis podprzestrzeni `modules`; nieznany moduł zachowuje swoje klucze. |
| `Infrastructure/I18n/Catalog.php` | Infrastructure | Scalanie źródeł z wymuszonym przedrostkiem `module.<id>.` (P16). |
| `Infrastructure/I18n/TranslatorService.php` | Infrastructure | Rejestracja źródeł napisów modułów przed pierwszym tłumaczeniem. |
| `Presentation/Cli/Screen/SettingsScreen.php` | Presentation | Zakładki modułów, pozycje z `ModuleSetting`, tryb edycji pozycji tekstowej, zakładka „Moduły”. |
| `Presentation/Cli/Screen/HelpScreen.php` | Presentation | Zakładki jako lista dowolnej długości; zakładka na moduł z częścią automatyczną i wierszami `helpKeys()`. |
| `Presentation/Cli/Screen/BrowserScreen.php` | Presentation | Traci `InspectSelectedEntryUseCase`; `Enter` na pliku nie robi nic (P3); **publikuje kontekst sesji** po każdej zmianie katalogu i zaznaczenia (D40). |
| `Presentation/Cli/LoopState.php` | Presentation | Trzyma kontekst sesji — pusty, dopóki nikt go nie opublikuje (D40). |
| `Presentation/Cli/InputHandler.php` | Presentation | Mapa `Ctrl`+litera → ekran modułu obok trzech klawiszy globalnych. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Podaje kontekst sesji ekranowi, który implementuje `ReadsContext`. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Lista klas modułów, budowa rejestru, wstrzyknięcie portów, mapa skrótów, źródła napisów, komunikat o odrzuceniach; `VERSION` → `0.20.0`. |
| `Application/UseCase/InspectSelectedEntryUseCase.php` | Application | **Przeniesiony** do `Module/FileInfo/Application/UseCase/`. |
| `Application/Port/FileInspectorPort.php` | Application | **Przeniesiony** do `Module/FileInfo/Application/Port/`. |
| `Application/Dto/EntryDescription.php` | Application | **Przeniesiony** do `Module/FileInfo/Application/Dto/`. |
| `Infrastructure/Filesystem/FileInspectorService.php` | Infrastructure | **Przeniesiony** do `Module/FileInfo/Infrastructure/`. |
| `Module/FileInfo/Presentation/FileInfoModule.php` | Module | Nowy — klasa modułu: tożsamość, skrót `Ctrl+D`, zakładka ustawień, pomoc. |
| `Module/FileInfo/Presentation/FileInfoScreen.php` | Module | Nowy — ekran z opisem zaznaczonego pliku. |
| `Module/FileInfo/lang/pl.php`, `en.php` | Module | Nowe — napisy modułu z przedrostkiem `module.file-info.`. |
| `lang/pl.php`, `lang/en.php` | Napisy | `help.key.open` bez „dla pliku — opis”; klucze zakładki „Moduły” i powodów odrzucenia. |
| `README.md` | Dokumentacja | Sekcja „Moduły”, zmieniona tabela sterowania (`Ctrl+D`, `Enter` bez opisu pliku). |
| `docs/architecture.md` | Dokumentacja | §1 o `src/Module/`, `Application/Module/` i `Presentation/Ui/Module/`; reguła zależności; §3 o module jako zwykłym obiekcie. |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | To samo w skrócie operacyjnym — **w tym samym kroku**. |
| testy | Testy | Rejestr (kolizje, zabronione litery, tożsamości, odsiew), `ModuleShortcut`, parser bajtów sterujących, `KeyBinding` z `Ctrl`, podprzestrzeń `modules` w pliku (w tym moduł nieznany), walidacja pozycji tekstowej, zakładki pomocy, moduł `FileInfo` po przenosinach, przedrostek w katalogu napisów. |

## Do rozstrzygnięcia na starcie kroku

Pytania planistyczne **P1–P17 są zamknięte** (sekcja „Ustalenia”, D38).
Poniższe to rozstrzygnięcia wykonawcze — takie, których nie da się podjąć
sensownie przed otwarciem edytora:

1. **Skąd `Catalog` bierze katalog z napisami modułu.** Deklaracja w kontrakcie
   (`ModuleInterface::translations(): ?string`) wnosi do warstwy `Application`
   ścieżkę na dysku; konwencja (`src/Module/<Nazwa>/lang/`) tego unika, ale każe
   `Bootstrap`owi wyprowadzać ścieżkę z nazwy klasy. Rozstrzygnąć przy pierwszym
   scalaniu źródeł.
2. **Czy zakładka „Moduły” jest zwykłą zakładką, czy przypadkiem osobnym.**
   Jej pozycje to przełączniki, więc pasuje do `ModuleSetting`; ale wiersz
   modułu odrzuconego nie jest ustawieniem i nie da się go przełączyć.
3. **Gdzie mieszka tryb edycji pozycji tekstowej** — w `SettingsScreen`, czy
   w samym `TextInput` z kroku 19. Odpowiedź zależy od tego, jak krok 19
   rozwiąże to w oknie komend; nie powielać dwóch trybów edycji.
4. **Czy `SettingsTab` po otwarciu zostaje w `Application/Dto`**, czy przenosi
   się bliżej ekranu ustawień. Po zmianie na obiekt wartości niesie klucze
   etykiet i listę pozycji, więc jest bliżej opisu ekranu niż danych aplikacji.
5. **Kolejność zakładek ustawień** — rdzeniowe, modułów, „Moduły” na końcu, czy
   „Moduły” tuż po rdzeniowych. Przy jednym module różnica jest żadna; przy
   pięciu zaczyna być widoczna.
6. **Co widzi ekran modułu, gdy katalog jest pusty albo nieczytelny.**
   `ReadsContext` dostaje kontekst zawsze, ale `selection` może być `null` —
   kontrakt musi to powiedzieć wprost, zanim powstanie drugi moduł.
7. **Wzorzec dla pozycji `arguments` modułu `FileInfo`.** Argumenty polecenia
   `file` idą do procesu potomnego; wzorzec ma odrzucić wszystko, co mogłoby
   z jednego polecenia zrobić dwa.
8. **Kiedy `FrameComposer` podaje kontekst ekranowi** — przed `draw()` każdej
   klatki, czy tylko po zmianie? Kontekst jest niezmienny, więc podanie go co
   klatkę nie kosztuje nic poza wywołaniem; przekazanie po zmianie wymaga wiedzy
   o tym, że zmiana zaszła.

## Kryteria ukończenia

- Dopisanie modułu ze wszystkimi punktami zaczepienia wymaga **jednej zmiany
  w rdzeniu**: dopisania klasy do listy w `Bootstrap`.
- Moduł otwiera własne okno skrótem `Ctrl`+litera, obsługuje w nim klawisze
  i wraca `Esc`em; okno modułu zastępuje treść panelu listy plików i prowadzi
  własne zaznaczenie oraz przewijanie.
- Moduł wnosi własne **komendy** w przestrzeni `<id>.`; `file-info.jump`
  przenosi do wskazanego katalogu i **podpowiada ścieżki** z systemu plików —
  pierwsza w projekcie implementacja podpowiedzi liczonych na żądanie.
- Moduł deklarujący literę zabronioną (`c`, `h`, `i`, `j`, `m`, `z`) albo zajętą
  przez inny moduł **nie zostaje załadowany**, aplikacja startuje, a powód widać
  w pasku stanu i na zakładce „Moduły”. Sprawdza to test rejestru.
- Moduł dokłada zakładkę do okna konfiguracji; jego ustawienia przeżywają
  ponowne uruchomienie i leżą w podprzestrzeni `modules.<id>`. Ustawienia
  modułu **wyłączonego albo nieznanego zostają w pliku nietknięte**.
- Wszystkie cztery rodzaje pozycji działają, a wartość tekstowa niezgodna ze
  wzorcem jest odrzucana z komunikatem, bez nadpisania poprzedniej.
- Moduł dokłada zakładkę do okna pomocy; jej część automatyczna wymienia skrót,
  klawisze ekranu i pozycje ustawień pochodzące **z deklaracji modułu**, a nie
  z przepisanego ręcznie tekstu. Okno pomocy przełącza zakładki cyklicznie,
  niezależnie od ich liczby.
- Napisy modułu leżą w jego katalogu i wchodzą do katalogu napisów **wyłącznie**
  pod przedrostkiem `module.<id>.`; klucz spoza przedrostka jest pomijany.
  Test kompletności języków obejmuje pliki modułów.
- Okienko `file` działa jak wcześniej, ale jego kod w całości leży
  w `src/Module/FileInfo/`; w rdzeniu nie zostaje po nim ani jedna klasa.
  Otwiera je `Ctrl+D`; `Enter` na pliku nie robi nic, a spis klawiszy, napisy
  i `README.md` mówią to samo.
- Moduł bez ekranu (sama zakładka ustawień albo sama pomoc) jest legalny
  i działa — sprawdza to test rejestru, nie moduł wbudowany.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` i `SKILL.md` opisują warstwę `Module`, podział
  kontraktu na dwie warstwy, regułę zależności i status modułu wobec wzorca
  Singleton — zgodnie ze sobą.
- `README.md` opisuje moduły, skrót `Ctrl+D` i zmienioną tabelę sterowania.

## Dziennik realizacji

### 2026-08-09 — krok wykonany

**Stan:** kod, testy i dokumentacja gotowe. PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag, **781 testów** (1757 asercji) zielonych — przybyło 74
wobec stanu po kroku 19.

#### Co powstało

| Warstwa | Klasy |
|---|---|
| `Application/Module` | `ModuleInterface`, `ModuleShortcut`, `ModuleContext`, `ContextEntryKind`, `ModuleSettingsTab`, `ModuleSetting`, `ModuleSettingKind`, `ProvidesSettingsTab`, `ProvidesCommands`, `ModuleRegistry`, `ModuleRejection` |
| `Presentation/Ui/Module` | `ProvidesScreen`, `ProvidesHelpTab`, `ReadsContext` |
| `Application/Dto` | `SettingsTabKind` (nowa), `SettingsTab` (enum → obiekt wartości), `SettingsCursor` (chodzi po liście z zewnątrz) |
| `Application/UseCase` | `ChangeModuleSettingUseCase` |
| `Module/FileInfo` | `FileInfoModule`, `FileInfoScreen`, `Command/JumpCommand`, `Application/FileInfoSettings`, przeniesione: `InspectSelectedEntryUseCase`, `FileInspectorPort`, `Dto/EntryDescription`, `Infrastructure/FileInspectorService`; `lang/pl.php`, `lang/en.php` |
| Testy | `ModuleRegistryTest`, `ModuleSettingTest`, `ChangeModuleSettingUseCaseTest`, `FileInfoModuleTest`, `Support/FakeModule` |

Zmienione: `Settings` (pole `modules`, `copy()` zamiast sześciu wypisanych
`with*`), `SettingsService` (podprzestrzeń `modules`), `SettingsPort`
(`current()`), `Catalog` (scalanie źródeł z wymuszonym przedrostkiem),
`TranslatorService` (`addSource()`, `ignoredKeys()`), `SettingsScreen` (trzy
rodzaje zakładek, tryb edycji pozycji tekstowej), `HelpScreen` (zakładki
cykliczne, zakładka na moduł), `BrowserScreen` (publikuje kontekst, traci opis
pliku), `LoopState` (kontekst sesji), `InputHandler` (mapa skrótów `Ctrl`+litera),
`FrameComposer` (podaje kontekst), `Bootstrap` (lista modułów, rejestr, zakładki,
źródła napisów, komunikat o odrzuceniach, `VERSION` 0.20.0), `lang/*.php`.

**W rdzeniu nie została po opisie pliku ani jedna klasa** — cztery przeniesione
pliki zniknęły z `Application/UseCase`, `Application/Port`, `Application/Dto`
i `Infrastructure/Filesystem`.

#### Rozstrzygnięcia wykonawcze ze startu kroku

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Skąd `Catalog` bierze katalog napisów modułu | Metoda w kontrakcie: `ModuleInterface::translations(): ?string` |
| 2 | Czy zakładka „Moduły” jest zwykłą zakładką | Przypadek osobny — `SettingsTabKind::Modules` |
| 3 | Gdzie mieszka tryb edycji pozycji tekstowej | W `SettingsScreen`; `TextInput` bez zmian |
| 4 | Czy `SettingsTab` zostaje w `Application/Dto` | Zostaje — niesie same dane, a chodzi po niej `SettingsCursor` |
| 5 | Kolejność zakładek ustawień | Rdzeniowe → „Moduły” → zakładki modułów |
| 6 | Co widzi ekran modułu przy pustym kontekście | Własny napis modułu (`module.file-info.nothing`) |
| 7 | Wzorzec pozycji `arguments` | Bez białej listy: odrzucane wyłącznie znaki sterujące; znaki specjalne wolno zacytować |
| 8 | Kiedy `FrameComposer` podaje kontekst | Co klatkę, przed `draw()` |

#### Odstępstwa od planu

1. **`JumpCommand` leży w `Module/FileInfo/Presentation/Command/`, a nie
   w `Application/Command/`** (tabela plików mówiła inaczej). Komenda dostaje
   `LoopState` — obiekt warstwy dostarczania — więc w `Application` modułu
   łamałaby regułę zależności. Ta sama zasada postawiła w kroku 19 komendy
   rdzenia w `Presentation/Cli/Command`.
2. **`SettingsTabKind` — klasa spoza planu.** Bez rodzaju zakładki wiersz modułu
   odrzuconego musiałby udawać ustawienie (rozstrzygnięcie 2).
3. **`SettingsPort` zyskuje `current()`.** Moduł czyta własne ustawienia, a nie
   ma dostępu do klasy `Infrastructure`; metoda istniała po stronie usługi od
   kroku 14 i do portu weszła wraz z pierwszym użytkownikiem spoza rdzenia.
4. **`FileInspectorService` idzie przez `proc_open`, nie `exec`.** Limit czasu
   bez możliwości przerwania procesu jest limitem tylko z nazwy — a to on jest
   pierwszą z dwóch pozycji ustawień modułu.
5. **`Settings` dostaje prywatne `copy()`** zamiast sześciu metod `with*`
   wypisujących komplet pól. Przy siódmym polu — podprzestrzeni modułów —
   pominięcie jednego argumentu byłoby cichą utratą całej konfiguracji modułów.
6. **`FileInfoScreen` zapamiętuje opis** i liczy go przy zmianie zaznaczenia
   oraz przy każdym otwarciu ekranu (`reset()`), a nie co klatkę. Trzydzieści
   procesów potomnych na sekundę kosztowałoby więcej niż cała reszta klatki;
   przeliczenie przy otwarciu sprawia, że zmiana ustawień modułu jest widoczna
   od razu.
7. **Komunikat o odrzuceniach ustępuje komunikatom startu.** Pasek stanu niesie
   jeden napis, więc nieotwarty katalog i uwaga do pliku konfiguracji mają
   pierwszeństwo przed modułem, który odpadł.

#### Sprawdzenie pod prawdziwym terminalem

Wszystkie ścieżki kroku obejrzane w działającej aplikacji (tryb tekstowy, PTY):

- `Ctrl+D` otwiera okno modułu; dla `composer.json` pokazuje `JSON text data`,
  a dla zaznaczonego katalogu — `(nie zaznaczono pliku)`.
- Ekran ustawień pokazuje zakładki `WYGLĄD`, `GRAFIKA`, `MODUŁY`, `Opis pliku`;
  zakładka modułu niesie obie pozycje.
- Tryb edycji pozycji tekstowej rysuje etykietę jako zachętę, wpisaną wartość
  i **migającą karetkę** (`Dodatkowe argumenty: -k▌`).
- `F12` → `file-info.jump docs` przenosi do katalogu i podpowiada ścieżki
  z dysku.
- Pasek stanu przy starcie milczy o modułach — komplet wbudowanych przechodzi
  bez odrzucenia.

#### Czego nie zrobiono

- **`MessageOverlay` został bez użytkownika w kodzie produkcyjnym.** Opis pliku
  przestał być oknem modalnym, a nic innego okna z treścią do przeczytania dziś
  nie otwiera. Klasa zostaje, bo jest jedyną drogą, którą `ScreenOutcome::opens()`
  oddaje ekranom — także modułowym — i pilnuje jej test. Gdyby do kroku 21 nie
  znalazła użytkownika, powinna zniknąć wraz z nim.
- **Pomiaru wydajności nie powtórzono.** Krok nie dotyka potoku renderowania:
  ekran modułu rysuje się tymi samymi komponentami, co ekrany rdzenia, a jedyna
  nowa praca na klatkę to jedno wywołanie `useContext()` z trzema polami.

#### Wpływ na krok 21

Kontrakt modułu stoi w komplecie i **przeglądarka nie będzie potrzebowała
w nim niczego nowego** — to jest jej sprawdzian. Do przeniesienia zostają trzy
rzeczy zapowiedziane w D40: `HudLayout` pytający ekran o dwie strefy zamiast
jednej flagi, klucz `showHiddenEntries` schodzący z rdzenia do ustawień modułu
i `file-info.jump` zmieniający się w `browser.jump`.
