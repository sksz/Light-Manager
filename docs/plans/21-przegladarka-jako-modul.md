# Krok 21 — Przeglądarka plików jako moduł domyślny

> **Skąd ten krok.** Powstał przy doprecyzowaniu kroku 20 (2026-08-09) na
> życzenie użytkownika: menadżer plików ma być **jednym z modułów** i modułem
> **domyślnym przy uruchomieniu**, chyba że konfiguracja wskaże inny. Krok 20
> dowozi kontrakt modułu i sprawdza go na małym `FileInfo`; ten krok wkłada
> w ten kontrakt główną funkcję aplikacji.

## Status

**Ukończony** (2026-08-10). Kod, testy i dokumentacja gotowe: PHPStan `max` bez
błędów, PHP-CS-Fixer bez uwag, **819 testów** (1880 asercji) zielonych, klatka
zmierzona i rozliczona „przed i po”, wygląd sprawdzony pod prawdziwym XTermem.

Pytania planistyczne były zamknięte przed startem (P1–P12 poniżej,
[00-decyzje.md](00-decyzje.md), D40); rozstrzygnięcia wykonawcze zapadły na
starcie kroku i są zapisane w D42 oraz w „Dzienniku realizacji”.

## Ustalenia (decyzje użytkownika, 2026-08-09)

Zapis w [00-decyzje.md](00-decyzje.md), D40.

| Pytanie | Wybór |
|---|---|
| **P1** — ile rdzenia schodzi do modułu | **Wszystko, łącznie z domeną katalogu** — rdzeń przestaje wiedzieć o plikach |
| **P2** — który krok to robi | **Nowy krok 21**; krok 20 zostaje przy `FileInfo` |
| **P3** — gdzie wybiera się moduł domyślny | **Klucz rdzenia** (`startupModule`) z wartościami z rejestru; pozycja na zakładce „Moduły” |
| **P4** — moduł domyślny niedostępny | **Powrót do przeglądarki** — moduł ostatniej szansy, niewyłączalny i nieodrzucalny |
| **P5** — skąd inne moduły biorą zaznaczenie | **Kontekst sesji jako dane pierwotne** — `ModuleContext` (ścieżka, nazwa zaznaczenia, rodzaj) |
| **P6** — kto rysuje ścieżkę i pas podglądu | **Ekran — trzy strefy zamiast jednej**; zasada kroku 20 „środkowy panel i nic poza nim” zostaje **uchylona** |
| **P7** — skrót przeglądarki | **Deklarowany jak w każdym module**; na dnie znaczy to samo, co `Esc` |
| **P8** — co jeszcze schodzi | **`showHiddenEntries`, komenda skoku, katalog startowy, wyjątki domenowe katalogu** |
| **P9** — kiedy powstaje `ModuleContext` | **W kroku 20** — `FileInfo` od pierwszego dnia czyta kontekst, nie `Directory` |
| **P10** — tożsamość modułu | **`browser`, skrót `Ctrl+B`** |
| **P11** — `Domain/` rdzenia | **Zostaje** — `Message`, `MessageTone`, `Preview`, `RendererMode`, `ScrollPosition` i korzeń hierarchii wyjątków |
| **P12** — model i wysiłek | **Opus / xhigh** |

### Co ten krok zmienia w ustaleniach kroku 20

Dwie rzeczy, obie świadomie:

- **Zasada „moduł dostaje środkowy panel i nic poza nim” przestaje
  obowiązywać** (P6). W kroku 20 była prawdziwa, bo pasek ścieżki i pas podglądu
  rysowały się z `Directory` w `LoopState` — czyli z czegoś, co należało do
  rdzenia. Po P1 rdzeń nie ma już tego katalogu i nie ma z czego ich narysować.
  Strefy przechodzą do ekranu razem z danymi, z których powstają.
- **`ReadsCurrentDirectory` nie powstaje w ogóle** (P5, P9). Zastępuje ją
  `ReadsContext` wraz z `ModuleContext` — i to **już w kroku 20**, żeby pierwszy
  moduł projektu nie powstawał na kontrakcie, który ginie krok później.

Reszta ustaleń kroku 20 (D38) zostaje nietknięta.

## Zależności

- **Krok 20** (moduły) — twardo i w całości. Ten krok nie dokłada do kontraktu
  modułu ani jednej metody: bierze `ModuleInterface`, `ModuleShortcut`,
  `ModuleRegistry`, `ProvidesScreen`, `ProvidesSettingsTab`, `ProvidesHelpTab`,
  `ProvidesCommands`, `ReadsContext` i `ModuleContext` **takie, jakie są**.
  To jest sprawdzian kontraktu, nie jego ciąg dalszy — a jeśli czegoś zabraknie,
  dziennik ma to powiedzieć wprost wraz z powodem.
- **Krok 18** (komponenty i płaszczyzny) — `ScreenInterface` powstał tam i tutaj
  zostaje **rozszerzony o dwie strefy** (P6). To pierwsza zmiana w tym kontrakcie
  od jego powstania.
- **Krok 14** (konfiguracja) — nowy klucz rdzenia `startupModule` wchodzi do
  istniejącego pliku i na zakładkę otwartą w kroku 20. Klucz `showHiddenEntries`
  z niego **wychodzi** (P8).
- **Krok 13** (motyw i układ panelowy) — `HudLayout` pyta dziś ekran o jedno
  (`usesPreview()`), a po zmianie o dwie strefy; progi ustępowania zostają
  nietknięte.
- **Krok 19** (okno komend) — `browser.jump` przejmuje po `file-info.jump`
  implementację podpowiedzi `OnDemand`, a `CommandOutcome` wiąże się po
  `ScreenInterface::id()`, więc identyfikator `browser` musi zostać ten sam.
- **Krok 17** (optymalizacja) — klatka po przebudowie stref ma zostać zmierzona
  `bin/render-bench` i rozliczona „przed i po”, tak jak każda zmiana potoku
  rysowania od kroku 16.

## Model i wysiłek

**Opus / xhigh** (P12) — wyżej niż krok 20, na równi z 19.

Powód nie jest w liczbie plików, tylko w tym, że krok **wyjmuje rdzeniowi jego
główną funkcję i wkłada ją z powrotem przez kontrakt, który ma dopiero jeden
tydzień**. Naraz zmieniają się: kontrakt ekranu (pierwszy raz od kroku 18),
warstwa domeny (przenosiny agregatu wraz z repozytorium), stan pętli, stos
ekranów, bootstrap i konfiguracja. Każda z tych rzeczy z osobna jest odwracalna;
wszystkie naraz — już nie, bo błąd w jednej udaje błąd w drugiej.

## Cel

Sprawić, żeby menadżer plików nie był **rdzeniem z doklejonymi modułami**, tylko
**modułem jak każdy inny**, który uruchamia się domyślnie, bo tak mówi
konfiguracja — a nie dlatego, że jest wpisany w pętlę.

Miarą powodzenia są trzy zdania:

1. **Rdzeń nie zna słowa „katalog”.** W `src/Domain`, `src/Application`
   i `src/Presentation` nie zostaje ani jedna klasa wiedząca, czym jest wpis
   w systemie plików. Sprawdza to test.
2. **Wskazanie innego modułu domyślnego w konfiguracji uruchamia aplikację
   z jego ekranem** — bez zmiany w kodzie rdzenia.
3. **Przeglądarka działa dokładnie tak, jak przed zmianą.** Ani jeden klawisz,
   ani jeden napis, ani jeden pas klatki nie zachowuje się inaczej. To
   przenosiny, nie przeprojektowanie.

## Stan zastany (sprawdzony w kodzie 2026-08-09, po kroku 19)

| Element | Stan | Los w tym kroku |
|---|---|---|
| `Domain/Aggregate/Directory` | Agregat katalogu wraz z zaznaczeniem | → `Module/Browser/Domain/Aggregate/` |
| `Domain/ValueObject/DirectoryPath`, `Entry`, `EntryType`, `Selection` | Rdzeń | → `Module/Browser/Domain/ValueObject/` |
| `Domain/Repository/DirectoryRepositoryInterface` | Jedyne repozytorium w projekcie | → `Module/Browser/Domain/Repository/` |
| `Domain/Exception/DirectoryNotReadable…`, `InvalidDirectoryPath…`, `InvalidEntry…`, `InvalidSelection…` | Rdzeń | → `Module/Browser/Domain/Exception/`, dalej po rdzeniowym `DomainException` |
| `Domain/ValueObject/Message`, `MessageTone`, `Preview`, `RendererMode`, `ScrollPosition` | Rdzeń | **Zostają** (P11) |
| `Application/UseCase/MoveSelection…`, `NavigateInto…`, `NavigateUp…`, `ToggleHidden…`, `OpenStartingDirectory…`, `PreviewSelectedEntry…` | Sześć przypadków użycia | → `Module/Browser/Application/UseCase/` |
| `Infrastructure/Filesystem/FilesystemDirectoryRepository`, `EntryComparator` | Rdzeń | → `Module/Browser/Infrastructure/` |
| `Presentation/Cli/Screen/BrowserScreen` | Ekran rdzenia | → `Module/Browser/Presentation/` |
| `LoopState` | Trzyma `Directory`, `enterDirectory()`, `showsHiddenEntries()` | Traci katalog; zyskuje kontekst sesji (krok 20) |
| `ScreenStack` | `$browser` jako dno, `close()` zawsze do niego | Dnem staje się ekran **modułu domyślnego** |
| `ScreenInterface` | `usesPreview()`, `headerSuffix()`; docblock mówi „ekran zajmuje **wyłącznie** środkowy panel” | Trzy strefy (P6); docblock do przepisania |
| `FrameComposer` | Rysuje ścieżkę z `$state->directory()` i pas podglądu z `PreviewSelectedEntryUseCase` | Obie strefy oddaje ekranowi; zostaje oprawa i pasek stanu |
| `HudLayout` | `__construct(rows, columns, bool $withPreview)` | Pyta ekran o istnienie strefy, nie o `bool` z jednej metody |
| `SettingKey::ShowHiddenEntries` | Klucz rdzenia | → `modules.browser.showHidden` (P8) |
| `Settings` | Sześć pól skalarnych plus `modules` (krok 20) | Traci `showHiddenEntries`, zyskuje `startupModule` |
| `Bootstrap::startingPath()`, `OpenStartingDirectoryUseCase` | Rdzeń zna katalog roboczy procesu | → moduł (P8) |
| `file-info.jump` (krok 20) | Komenda modułu `FileInfo` wraz z podpowiedziami `OnDemand` | → `browser.jump` (P8) |
| `Module/FileInfo/*` (krok 20) | Czyta `ModuleContext` przez `ReadsContext` | **Nietknięty** — zmienia się wyłącznie wydawca kontekstu |

Trzy pozycje przesądzają o rozmiarze kroku: **przenosiny domeny** (pierwszy
moduł z własną warstwą `Domain/`), **trzy strefy ekranu** (pierwsza zmiana
kontraktu z kroku 18) i **dno stosu ekranów**, które przestaje być wpisane
w kod.

## Zakres

### 1. Kontrakt ekranu: trzy strefy zamiast jednej

Dziś rdzeń rysuje pasek ścieżki i pas podglądu **za** ekran, bo ma czym: katalog
leży w `LoopState`. Po przenosinach nie ma — więc strefy idą tam, gdzie dane.

Postać proponowana (rozstrzygnięcie wykonawcze nr 1 dopuszcza drugą):

```php
namespace LightManager\Presentation\Ui;

final class ScreenZone
{
    public function __construct(
        /** Klucz katalogu z etykietą obwódki strefy. */
        public readonly string $labelKey,
        public readonly ComponentInterface $content,
    ) {}
}
```

```php
interface ScreenInterface extends ComponentInterface
{
    public function id(): string;

    /** Klucz etykiety strefy środkowej — jak dziś. */
    public function labelKey(): string;

    /** Górny pas; `null` — strefa nie powstaje i wiersze idą do środka. */
    public function header(): ?ScreenZone;

    /** Pas podglądu; `null` zastępuje dzisiejsze `usesPreview() === false`. */
    public function preview(): ?ScreenZone;

    /** @return list<KeyBinding> */
    public function bindings(): array;

    public function handle(KeyPress $key): ScreenOutcome;
}
```

Trzy rzeczy, które się przy tym **nie** zmieniają:

- **Oprawa zostaje rdzeniowi.** Obwódki, nawiasy narożne i etykiety stref rysuje
  dalej `FrameComposer::chrome()`, a etykietę bierze z `ScreenZone::labelKey` —
  tak samo, jak dziś bierze `$screen->labelKey()` dla środka. Moduł nie rysuje
  ramek i nie zna motywu od tej strony.
- **Pasek stanu zostaje rdzeniowi w całości.** Komunikat i podpowiedzi klawiszy
  globalnych to sprawa powłoki, nie modułu.
- **Progi ustępowania zostają w `HudLayout`.** Zmienia się wyłącznie źródło
  odpowiedzi „czy strefa podglądu ma powstać”: dziś `usesPreview()`, po zmianie
  `preview() !== null`.

`headerSuffix()` **znika**. Był szczeliną wybitą w kroku 18 dokładnie po to, żeby
ekran mógł dopisać coś do ścieżki rysowanej przez rdzeń; gdy ekran rysuje cały
pasek, szczelina nie ma czego łatać. Numer zaznaczenia i znacznik wpisów ukrytych
wracają tam, skąd pochodzą — do przeglądarki.

**Ekrany rdzenia po zmianie.** `HelpScreen` i `SettingsScreen` nie mają ścieżki
ani podglądu, więc oddają `null` w obu strefach — i to jest **zmiana widoczna**:
dziś na ustawieniach i w pomocy pasek ścieżki stoi, bo rdzeń rysuje go
bezwarunkowo. Czy ma zniknąć, czy ma go wypełniać rdzeń z kontekstu sesji —
rozstrzygnięcie wykonawcze nr 2.

### 2. Kontekst sesji: zmiana wydawcy, nie kontraktu

`ModuleContext` i `ReadsContext` powstają **w kroku 20** (P9), więc ten krok
zmienia jedną rzecz: kto kontekst publikuje.

```php
namespace LightManager\Application\Module;

final class ModuleContext
{
    public function __construct(
        /** Ścieżka bieżącego miejsca — napis, nie obiekt wartości. */
        public readonly string $path,
        /** Nazwa zaznaczenia albo `null`, gdy nic nie jest zaznaczone. */
        public readonly ?string $selection = null,
        public readonly ContextEntryKind $kind = ContextEntryKind::None,
    ) {}
}
```

| Krok | Wydawca | Odbiorca |
|---|---|---|
| 20 | rdzeniowy `BrowserScreen` po każdej zmianie katalogu i zaznaczenia | ekran `FileInfo` przez `ReadsContext` |
| 21 | **ekran modułu `browser`** — ten sam kod, inne miejsce | bez zmian |

**Kontekst jest daną pierwotną i to jest w nim najważniejsze.** Napis, napis
i enum o trzech przypadkach — nic, czego rdzeń nie mógłby przenieść między
dwoma modułami, które o sobie nie wiedzą. Gdyby niósł `Directory`, rdzeń
musiałby zobaczyć typ modułu, a `FileInfo` — typ przeglądarki; reguła „moduły się
nie znają” padłaby przy pierwszym module.

Kontekst trzyma `LoopState`, bo tam już mieszka wszystko, co przeżywa klatkę.
Moduł bez wydawcy kontekstu (żaden ekran nic nie publikuje) daje kontekst pusty,
a nie brak kontekstu — odbiorca nie ma sprawdzać `null`.

### 3. Przenosiny domeny katalogu

Pierwszy moduł z **własną warstwą `Domain/`**. Do `src/Module/Browser/Domain/`
schodzą: `Directory`, `DirectoryPath`, `Entry`, `EntryType`, `Selection`,
`DirectoryRepositoryInterface` oraz cztery wyjątki katalogu (P8).

Zasady, które przy tym obowiązują:

- **Wyjątki modułu dziedziczą dalej po rdzeniowym `DomainException`.** Korzeń
  hierarchii zostaje w rdzeniu, bo to on ją łapie: `InputHandler` stawia
  komunikat z wyjątku domenowego w pasku stanu i ma to robić niezależnie od
  tego, czyj wyjątek złapał.
- **Domena modułu nie widzi domeny rdzenia poza tym korzeniem.** `Message`
  i `Preview` należą do powłoki; katalog nie ma powodu ich znać.
- **`ScrollPosition` zostaje w rdzeniu**, mimo że jedynym dzisiejszym
  użytkownikiem jest lista plików: to pojęcie listy, nie katalogu, i używa go
  komponent `ListView`, z którego złoży się każdy moduł z listą.

Po przenosinach `Domain/` rdzenia to `Message`, `MessageTone`, `Preview`,
`RendererMode`, `ScrollPosition`, `DomainException` i trzy wyjątki klatki (P11).
Domena chuda, ale prawdziwa — słownik powłoki terminalowej. `architecture.md`
dopisuje o tym jedno zdanie, żeby następny czytelnik nie wziął jej chudości za
niedopatrzenie.

### 4. `LoopState` bez katalogu i stan modułu

`LoopState` traci `directory()`, `enterDirectory()` i `showsHiddenEntries()`.
Zostaje: ustawienia, komunikat, okna nakładane, czas klatki i kontekst sesji —
czyli **stan powłoki**, nie stan menadżera plików.

Katalog przenosi się do modułu. Proponowany kształt: `BrowserState`
w `Module/Browser/Presentation/` — jeden obiekt trzymający `Directory`
i publikujący kontekst po każdej zmianie, wstrzykiwany do ekranu **i** do komendy
`browser.jump`, bo obie te rzeczy zmieniają katalog. Alternatywa (katalog w polu
ekranu, komenda dostaje ekran) jest rozstrzygnięciem wykonawczym nr 3.

**Publikowanie kontekstu ma jedno miejsce.** Gdyby ekran publikował go w `draw()`,
a komenda w `execute()`, to po pierwszym module z dwoma wejściami kontekst
zacząłby się rozjeżdżać o klatkę. Zmiana katalogu i zmiana zaznaczenia przechodzą
przez `BrowserState`, więc publikacja jest tam, gdzie zmiana.

### 5. Dno stosu ekranów przestaje być wpisane w kod

```php
// dziś
public function __construct(private readonly ScreenInterface $browser) { … }

// po zmianie
public function __construct(private readonly ScreenInterface $floor) { … }
```

Zmiana jest jednowierszowa, a znaczy tyle, że `close()` wraca **do ekranu modułu
domyślnego**, nie do przeglądarki. Reszta zachowania zostaje:

- `Esc` z dowolnego ekranu wraca na dno,
- skrót modułu otwiera jego ekran, a naciśnięty ponownie — zamyka
  (`ScreenStack::toggle()`),
- **skrót modułu domyślnego na dnie nie robi nic** i wynika to z istniejącego
  kodu: `toggle()` widzi `current === screen`, woła `close()`, a `close()`
  stawia ten sam ekran. Przypadku szczególnego nie ma i nie trzeba go pisać.

`InputHandler` dostaje mapę `Ctrl`+litera → ekran modułu (to już zakres kroku 20)
i **nic ponadto** — dno jest sprawą stosu, nie obsługi wejścia.

### 6. Moduł domyślny w konfiguracji

Nowy klucz rdzenia (P3):

```json
{
  "language": "auto",
  "theme": "grafit",
  "startupModule": "browser",
  "modules": {
    "browser":   { "enabled": true, "showHidden": false },
    "file-info": { "enabled": true, "timeout": 2, "arguments": "" }
  }
}
```

| Rzecz | Rozstrzygnięcie |
|---|---|
| Wartość domyślna | `browser` |
| Dopuszczalne wartości | **identyfikatory modułów przyjętych przez rejestr**, mających ekran — lista liczona przy starcie, nie wpisana w kod |
| Pozycja w ustawieniach | zakładka „Moduły”, **nad** spisem modułów, komponent `Choice` |
| Skutek zmiany | **po ponownym uruchomieniu**, tak samo jak przełącznik włączenia modułu; ekran mówi o tym wprost |
| Wartość nieznana w pliku | moduł ostatniej szansy plus komunikat w pasku stanu — jak każda wartość spoza zakresu od kroku 14 |

`SettingKey` zyskuje przypadek `StartupModule`, a `SettingsScreen::position()` —
gałąź rysującą go `Choice`m z listą z rejestru. To pierwszy klucz rdzenia,
którego **dopuszczalne wartości nie są znane w czasie pisania kodu**, i to jest
jego jedyna nowość wobec `Theme` i `Language`.

Komenda `core.startup-module` **nie powstaje** — patrz „Poza zakresem”.

### 7. Moduł ostatniej szansy

Aplikacja nigdy nie zostaje bez ekranu (P4). Przeglądarka jest modułem
uprzywilejowanym i ma to być widoczne, a nie ukryte:

1. **Rejestr dostaje identyfikator modułu ostatniej szansy z zewnątrz** —
   `Bootstrap` podaje `'browser'`, rejestr nie zna tej nazwy z siebie. Dzięki
   temu `Application/Module` pozostaje wolne od wiedzy o konkretnym module,
   a test może podstawić inny.
2. **Nie da się go wyłączyć.** Przełącznik na zakładce „Moduły” stoi, ale jest
   zablokowany wraz z powodem — tak samo, jak dla modułu odrzuconego (krok 20).
3. **Nie da się go odrzucić.** Rejestr sprawdza go **pierwszym**, więc przy
   kolizji skrótu odrzucony zostaje ten drugi moduł.
4. **Brak modułu ostatniej szansy na liście w `Bootstrap` jest błędem
   programistycznym**, nie sytuacją użytkownika — łapie go test, a nie obsługa
   w runtime.

Rdzeń wraca do niego w czterech przypadkach: moduł domyślny wyłączony, odrzucony
przez rejestr, nieobecny na liście albo bez ekranu. Za każdym razem z komunikatem
w pasku stanu — użytkownik ma wiedzieć, że dostał **nie to**, o co prosił.

### 8. Ustawienia przeglądarki

`showHiddenEntries` przestaje być kluczem rdzenia i staje się
`modules.browser.showHidden` (P8) — pozycją typu `Toggle` na zakładce ustawień
modułu.

To jest **drugi sprawdzian mechanizmu z kroku 20 i pierwszy poważny**: ustawienie
modułu zmienia się nie tylko na ekranie ustawień, ale i **klawiszem** (`.`
w przeglądarce), w środku klatki, wraz z ponownym odczytem katalogu. Jeśli
`ChangeModuleSettingUseCase` z kroku 20 tego nie udźwignie, dziennik ma to
powiedzieć wraz z powodem.

Stary klucz w istniejącym pliku konfiguracyjnym: rozstrzygnięcie wykonawcze nr 4
(przepisać raz czy pominąć milcząco).

### 9. Komenda `browser.jump`

Krok 20 dowozi `file-info.jump <ścieżka>` wraz z **pierwszą w projekcie
implementacją podpowiedzi `OnDemand`**. Po wyprowadzeniu nawigacji skok należy do
przeglądarki, bo tylko ona umie zmienić katalog — komenda przenosi się do
`Module/Browser/Application/Command/` i zmienia nazwę na `browser.jump`.

Sam mechanizm podpowiadania ścieżek przenosi się **bez zmian**: to ta sama klasa
w innym katalogu, z inną przestrzenią nazw. Testy idą razem z nią.

`FileInfo` zostaje przy `ProvidesCommands` bez komend albo traci tę zdolność —
rozstrzygnięcie wykonawcze nr 5.

### 10. Warstwy i struktura katalogów

```
src/
├── Domain/                      # chudnie: Message, MessageTone, Preview,
│                                # RendererMode, ScrollPosition, DomainException
│                                # + trzy wyjątki klatki
├── Application/
│   └── Module/                  # ModuleContext, ContextEntryKind (krok 20)
├── Presentation/
│   └── Ui/
│       ├── ScreenZone.php       # nowy — etykieta i treść strefy
│       └── Module/              # ProvidesScreen, ProvidesHelpTab, ReadsContext
└── Module/
    ├── Browser/
    │   ├── Domain/
    │   │   ├── Aggregate/       # Directory
    │   │   ├── ValueObject/     # DirectoryPath, Entry, EntryType, Selection
    │   │   ├── Repository/      # DirectoryRepositoryInterface
    │   │   └── Exception/       # cztery wyjątki katalogu
    │   ├── Application/
    │   │   ├── UseCase/         # sześć przypadków użycia
    │   │   └── Command/         # JumpCommand
    │   ├── Infrastructure/      # FilesystemDirectoryRepository, EntryComparator
    │   ├── Presentation/        # BrowserModule, BrowserScreen, BrowserState
    │   └── lang/                # pl.php, en.php
    └── FileInfo/                # bez zmian (krok 20)
```

Reguła zależności z kroku 20 zostaje w brzmieniu, w którym powstała; ten krok
dokłada do niej **jedno zdanie**: moduł może mieć własną warstwę `Domain/`,
a jego wyjątki dziedziczą po rdzeniowym `DomainException` i nie sięgają po nic
innego z domeny rdzenia.

`docs/architecture.md` i `.claude/skills/light-manager-conventions/SKILL.md`
przyjmują to **w tym samym kroku**.

## Poza zakresem tego kroku

- **Drugi moduł z ekranem** — dowodem elastyczności jest wskazanie `file-info`
  jako domyślnego w konfiguracji i sprawdzenie, że aplikacja startuje z jego
  ekranem. To test, nie nowy moduł.
- **Komenda `core.startup-module`** — zmiana modułu domyślnego działa dopiero po
  restarcie, więc komenda dawałaby złudzenie natychmiastowości. Ekran ustawień
  mówi to wprost, wiersz komend nie miałby jak.
- **Przełączanie modułu domyślnego bez restartu.**
- **Więcej niż dwa piętra stosu ekranów** — moduł otwarty z modułu.
- **Dwa moduły widoczne naraz** (podział panelu, drugi panel).
- **Moduł bez ekranu jako domyślny** — wykluczony z listy dopuszczalnych wartości
  przy starcie.
- **Wybór modułu domyślnego z linii poleceń** (`--module=`) — aplikacja nie ma
  dziś obsługi argumentów wywołania i ten krok jej nie wprowadza.
- **Zmiana zachowania przeglądarki** — jakakolwiek. Klawisze, napisy i wygląd mają
  zostać co do znaku; to przenosiny.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/ScreenZone.php` | Presentation | Nowy — etykieta strefy i jej treść. |
| `Presentation/Ui/ScreenInterface.php` | Presentation | `header()` i `preview()` zamiast `headerSuffix()` i `usesPreview()`; przepisany docblock (zasada „wyłącznie środkowy panel” przestaje obowiązywać). |
| `Presentation/Ui/HudLayout.php` | Presentation | Istnienie strefy podglądu z `preview() !== null`; progi bez zmian. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Oddaje ekranowi pasek ścieżki i pas podglądu; traci `PreviewSelectedEntryUseCase`; etykiety stref z `ScreenZone`. |
| `Presentation/Cli/ScreenStack.php` | Presentation | Dnem jest ekran modułu domyślnego, nie przeglądarka. |
| `Presentation/Cli/LoopState.php` | Presentation | Traci katalog, `enterDirectory()` i `showsHiddenEntries()`; zostaje kontekst sesji z kroku 20. |
| `Presentation/Cli/Screen/HelpScreen.php` | Presentation | `header()` i `preview()` (patrz rozstrzygnięcie nr 2). |
| `Presentation/Cli/Screen/SettingsScreen.php` | Presentation | Jak wyżej; pozycja `startupModule` na zakładce „Moduły”; przełącznik modułu ostatniej szansy zablokowany. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Buduje moduł `browser`, podaje rejestrowi identyfikator modułu ostatniej szansy, wybiera ekran dna z konfiguracji; traci `startingPath()`; `VERSION` → `0.21.0`. |
| `Application/Module/ModuleRegistry.php` | Application | Identyfikator modułu ostatniej szansy z zewnątrz; sprawdzany pierwszym, nieodrzucalny i niewyłączalny. |
| `Application/Dto/Settings.php` | Application | Pole `startupModule`; **traci** `showHiddenEntries`. |
| `Application/Dto/SettingKey.php` | Application | Przypadek `StartupModule`; **traci** `ShowHiddenEntries`. |
| `Infrastructure/Config/SettingsService.php` | Infrastructure | Odczyt i zapis `startupModule`; los starego klucza (rozstrzygnięcie nr 4). |
| `Domain/ValueObject/{DirectoryPath,Entry,EntryType,Selection}.php` | Domain | **Przeniesione** do `Module/Browser/Domain/ValueObject/`. |
| `Domain/Aggregate/Directory.php` | Domain | **Przeniesiony** do `Module/Browser/Domain/Aggregate/`. |
| `Domain/Repository/DirectoryRepositoryInterface.php` | Domain | **Przeniesiony** do `Module/Browser/Domain/Repository/`. |
| `Domain/Exception/{DirectoryNotReadable,InvalidDirectoryPath,InvalidEntry,InvalidSelection}Exception.php` | Domain | **Przeniesione** do `Module/Browser/Domain/Exception/`; nadal po `DomainException`. |
| `Application/UseCase/{MoveSelection,NavigateIntoDirectory,NavigateUp,ToggleHiddenEntries,OpenStartingDirectory,PreviewSelectedEntry}UseCase.php` | Application | **Przeniesione** do `Module/Browser/Application/UseCase/`. |
| `Infrastructure/Filesystem/{FilesystemDirectoryRepository,EntryComparator}.php` | Infrastructure | **Przeniesione** do `Module/Browser/Infrastructure/`. |
| `Presentation/Cli/Screen/BrowserScreen.php` | Presentation | **Przeniesiony** do `Module/Browser/Presentation/`; trzy strefy; zaznaczenie i znacznik wpisów ukrytych w pasku ścieżki; `.` przez `ChangeModuleSettingUseCase`. |
| `Module/Browser/Presentation/BrowserModule.php` | Module | Nowy — tożsamość `browser`, skrót `Ctrl+B`, zakładka ustawień, pomoc, komenda. |
| `Module/Browser/Presentation/BrowserState.php` | Module | Nowy — katalog wraz z publikacją kontekstu (rozstrzygnięcie nr 3). |
| `Module/Browser/Application/Command/JumpCommand.php` | Module | **Przeniesiona** z `FileInfo`; nazwa `browser.jump`. |
| `Module/Browser/lang/pl.php`, `en.php` | Module | Nowe — napisy przeglądarki z przedrostkiem `module.browser.`. |
| `lang/pl.php`, `lang/en.php` | Napisy | Klucze przeglądarki **wychodzą** do modułu; wchodzą: `startupModule`, powód blokady modułu ostatniej szansy, komunikat o powrocie do niego. |
| `README.md` | Dokumentacja | Moduł domyślny i jego wybór; `Ctrl+B` w tabeli sterowania. |
| `docs/architecture.md` | Dokumentacja | Chuda domena rdzenia; moduł z własną warstwą `Domain/`; uchylenie zasady „środkowy panel i nic poza nim”; moduł ostatniej szansy. |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | To samo w skrócie operacyjnym — **w tym samym kroku**. |
| testy | Testy | Rdzeń bez wiedzy o plikach (test po przestrzeniach nazw), dno stosu z konfiguracji, moduł ostatniej szansy (wyłączenie, odrzucenie, brak, brak ekranu), `startupModule` (wartość nieznana, moduł bez ekranu), trzy strefy ekranu, kontekst po zmianie wydawcy, `showHidden` jako ustawienie modułu zmieniane klawiszem, `browser.jump` po przenosinach, przenosiny domeny wraz z testami. |

## Do rozstrzygnięcia na starcie kroku

Pytania planistyczne **P1–P12 są zamknięte** (sekcja „Ustalenia”, D40).
Poniższe to rozstrzygnięcia wykonawcze:

1. **Kształt kontraktu stref** — `header()`/`preview()` oddające `?ScreenZone`
   (propozycja wyżej), czy sześć metod bliższych dzisiejszemu kształtowi
   (`headerLabelKey()`, `drawHeader()`, `usesPreview()`, `previewLabelKey()`,
   `drawPreview()`)? Pierwsze jest krótsze, drugie nie każe składać komponentu
   dla strefy, która i tak się nie narysuje.
2. **Co widać w pasku ścieżki na ekranie pomocy i ustawień.** Dziś stoi tam
   ścieżka, bo rysuje ją rdzeń bezwarunkowo. Warianty: strefa znika (klatka
   zmienia wygląd), rdzeń wypełnia ją z kontekstu sesji (wygląd zostaje, ale
   rdzeń rysuje coś, czego ekran nie zamawiał), albo ekrany rdzenia same biorą
   ścieżkę z kontekstu.
3. **Gdzie mieszka katalog modułu** — osobny `BrowserState` wstrzykiwany do
   ekranu i komendy, czy pole w ekranie, a komenda dostaje ekran?
4. **Los klucza `showHiddenEntries` w istniejącym pliku** — przepisać raz do
   `modules.browser.showHidden`, czy pominąć milcząco (zgodnie z regułą kroku 14
   o nieznanych kluczach) i pozwolić ustawieniu wrócić do domyślnego?
5. **Czy `FileInfo` zostaje przy `ProvidesCommands`** po oddaniu `jump`
   przeglądarce — z pustą listą komend, czy tracąc zdolność?
6. **Kolejność wyboru dna przy starcie**: rejestr → konfiguracja → moduł
   ostatniej szansy. Co ma pierwszeństwo, gdy moduł domyślny jest wyłączony,
   a zarazem odrzucony — komunikat mówi o jednej przyczynie czy o obu?
7. **Czy pas podglądu rysowany przez moduł zmienia koszt klatki.** Dziś liczy go
   `FrameComposer` raz na klatkę; po zmianie robi to ekran w tym samym takcie,
   ale przez inną ścieżkę. Do rozliczenia pomiarem, nie rozumowaniem.

## Kryteria ukończenia

- **W `src/Domain`, `src/Application`, `src/Infrastructure` i `src/Presentation`
  nie zostaje ani jedna klasa wiedząca, czym jest katalog albo wpis w systemie
  plików.** Sprawdza to test przechodzący po przestrzeniach nazw.
- Przeglądarka jest modułem: implementuje `ModuleInterface`, `ProvidesScreen`,
  `ProvidesSettingsTab`, `ProvidesHelpTab` i `ProvidesCommands`, a jej ekran —
  `ScreenInterface` i publikuje `ModuleContext`.
- **Kontrakt modułu z kroku 20 nie zyskał ani jednej metody**, a jeśli zyskał —
  dziennik mówi którą i dlaczego kontrakt był za wąski.
- `startupModule` wskazujący `file-info` uruchamia aplikację z jego ekranem jako
  dnem stosu — **bez zmiany w kodzie rdzenia**. Sprawdza to test.
- Moduł domyślny wyłączony, odrzucony, nieobecny albo bez ekranu → aplikacja
  startuje z przeglądarką i **mówi o tym w pasku stanu**. Cztery przypadki,
  cztery testy.
- Przeglądarki **nie da się wyłączyć ani odrzucić**: przełącznik na zakładce
  „Moduły” jest zablokowany wraz z powodem, a moduł kolidujący z jej skrótem to
  ten drugi zostaje odrzucony.
- `Ctrl+B` otwiera przeglądarkę z każdego ekranu; naciśnięty na niej samej, gdy
  jest dnem, **nie robi nic**.
- Ekran rysuje trzy strefy; oprawa, etykiety i pasek stanu zostają w rdzeniu.
  `headerSuffix()` znika z kontraktu.
- `showHiddenEntries` leży w `modules.browser.showHidden`, zmienia się klawiszem
  `.` **i** na zakładce ustawień modułu, a obie drogi kończą się w tym samym
  miejscu.
- `browser.jump` przenosi do wskazanego katalogu i podpowiada ścieżki — ta sama
  funkcja, co `file-info.jump` z kroku 20, w innym module.
- **Przeglądarka zachowuje się co do znaku tak, jak przed zmianą**: klawisze,
  napisy, układ klatki i zachowanie pasa podglądu. Odstępstwo, jeśli będzie, jest
  w dzienniku wraz z powodem.
- Klatka zmierzona `bin/render-bench` i rozliczona „przed i po” — również wtedy,
  gdy wynik jest niekorzystny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` i `SKILL.md` opisują chudą domenę rdzenia, moduł
  z własną warstwą `Domain/`, trzy strefy ekranu i moduł ostatniej szansy —
  zgodnie ze sobą.
- `README.md` opisuje moduł domyślny, jego wybór w konfiguracji i `Ctrl+B`.

## Rozstrzygnięcia wykonawcze ze startu kroku (2026-08-10)

Siedem pytań z sekcji wyżej, rozstrzygniętych przez użytkownika przed otwarciem
edytora, plus jedno ósme, którego plan nie przewidział.

| # | Pytanie | Wybór |
|---|---|---|
| 1 | Kształt kontraktu stref | **`header()`/`preview()` oddające `?ScreenZone`** — propozycja planu |
| 2 | Pasek ścieżki na ekranach rdzenia | **Górny pas to pole ekranu, nie stały pasek ścieżki**; pomoc stawia w nim nazwę i wersję aplikacji, ustawienia — położenie pliku konfiguracyjnego |
| 3 | Gdzie mieszka katalog modułu | **Osobny `BrowserState`** wstrzykiwany do ekranu i do komendy |
| 4 | Stary klucz `showHiddenEntries` | **Przepisać raz** do `modules.browser.showHidden` |
| 5 | `FileInfo` a `ProvidesCommands` | **Zostaje z pustą listą** — moduł ma się rozrastać (krok 25, wtedy numerowany jako 22) |
| 6 | Komunikat przy powrocie do przeglądarki | **Konkretna przyczyna — cztery klucze napisów** |
| 7 | Koszt pasa podglądu | Rozliczony pomiarem (niżej) |
| 8 | **Wyjątki katalogu w `ProblemPresenter`** (poza planem) | **Wyjątek sam się przedstawia** — nowy interfejs `Domain\Exception\DescribesProblem` |

Rozstrzygnięcie nr 8 powstało w trakcie: `ProblemPresenter` dobierał zdanie dla
użytkownika **po klasie wyjątku** i rozpoznawał `DirectoryNotReadableException`
oraz `InvalidDirectoryPathException`. Po przenosinach oba należą do modułu, więc
rdzeń wciąż wiedziałby, czym jest katalog — czyli główne kryterium kroku nie
byłoby spełnione. Odrzucono: nową zdolność modułu (kontrakt miał nie urosnąć)
i łapanie własnych wyjątków przez moduł (krok 20 ustalił odwrotnie).

## Odstępstwa od planu

Dziesięć, każde z powodem. Żadne nie dotyka kontraktu modułu.

1. **`Domain\Exception\DescribesProblem`** — nowy, jednometodowy interfejs
   w domenie rdzenia (rozstrzygnięcie nr 8). Reguła ze SKILL-a „napis dobiera
   presenter po klasie wyjątku” obowiązuje odtąd **wyłącznie dla wyjątków
   rdzenia**; wyjątek modułu podaje klucz katalogu i parametry sam.
2. **`Presentation\Cli\StartupScreen`** — plan zostawiał wybór dna w `Bootstrap`.
   Cztery drogi awaryjne to cztery testy, a `Bootstrap` nie daje się wywołać bez
   terminala i Imagicka. Wybór wyprowadzony do osobnej klasy; `Bootstrap` stawia
   już tylko komunikat, bo to on trzyma stan pętli.
3. **Dwa komponenty własne modułu** (`Presentation/Component/`), których plan nie
   przewidywał. `PathLine` — bo ścieżkę skraca się **od lewej**
   (`DirectoryPath::shortenedTo()`), a `Label::fit()` ucina koniec: postawienie
   go w komponentach rdzenia przywróciłoby rdzeniowi `DirectoryPath`.
   `PreviewBox` — bo ekran pytany o strefy **przed** podziałem okna nie wie, czy
   pas podglądu powstanie; liczenie podglądu dopiero w `draw()` odtwarza dawne
   zachowanie `FrameComposer` co do taktu (patrz „Pomiar”).
4. **`BrowserModule` składa się sam i leniwie.** Plan sugerował wiązanie
   w `Bootstrap` (tak powstaje `FileInfo`), ale wtedy rdzeń poznałby
   `FilesystemDirectoryRepository` i `DirectoryPath`. Leniwość ma z kolei jedną
   twardą przyczynę: otwarcie katalogu startowego potrafi skończyć się
   komunikatem, a napisy modułu wchodzą do katalogu **po** zbudowaniu rejestru —
   moduł składany zachłannie wypisałby użytkownikowi surowy klucz.
5. **`HudLayout` zyskał `withHeader`** obok `withPreview`. Skoro `header()` może
   oddać `null`, podział okna musi to zobaczyć. Progi ustępowania nietknięte.
6. **`SettingsScreen` dostał `SettingsPort`** — żeby postawić w swoim nagłówku
   położenie pliku konfiguracyjnego (rozstrzygnięcie nr 2).
7. **`ChangeSettingUseCase` dostał listę `startupModules`.** `startupModule` jest
   pierwszym kluczem rdzenia, którego zakresu nie zna się w czasie pisania kodu;
   motywy mają na to port, moduły portu nie mają i mieć nie muszą — to napisy.
8. **`SettingsTab::modules()` niesie klucz `StartupModule`**, więc spis modułów
   ma o jeden wiersz więcej niż modułów. Pozycja stoi **nad** spisem, bo jej
   wartości to identyfikatory z tego właśnie spisu.
9. **`FileInfo` zmienił się bardziej niż „tylko wydawca kontekstu”**: stracił
   `JumpCommand` z konstruktora, dostał `header()`/`preview()` z nowego kontraktu
   i zapamiętuje kontekst, żeby postawić ścieżkę w górnym pasie. Bez tego
   ostatniego jego klatka straciłaby pasek ścieżki — a to byłaby zmiana wyglądu.
10. **Klawisze przeglądarki przeniosły się w oknie pomocy** z ogólnego spisu
    „Klawisze” na **jej własną zakładkę**. To jedyne miejsce, w którym przenosiny
    widać w interfejsie, i wynika wprost z P8 kroku 20: moduł dostaje zakładkę,
    a jego klawisze idą tam z deklaracji. Klatki samej przeglądarki nie dotyczy.

**Czego nie zrobiono:** `Bootstrap` nadal wiąże wnętrze modułu `FileInfo`
(ekran, przypadek użycia, usługa) zamiast dostać gotowy moduł. Nie łamie to
kryterium kroku — żadna z tych klas nie wie, czym jest katalog — ale obietnica
„jedna pozycja na liście” jest dla `FileInfo` spełniona słabiej niż dla
przeglądarki. Pilnuje tego test `CoreKnowsNothingAboutFilesTest`, który dla
przeglądarki jest twardy, a dla reszty modułów wymaga tylko, żeby nikt poza
`Bootstrapem` ich nie widział. Do wyrównania w kroku **25**, przy okazji
rozbudowy (krok nosił wtedy numer 22 — przenumerowanie: D43).

## Pomiar

Wzorce: [2026-08-10-przed-krokiem-21.json](../pomiary/2026-08-10-przed-krokiem-21.json)
i [2026-08-10-po-kroku-21.json](../pomiary/2026-08-10-po-kroku-21.json).

| Scenariusz | Przed | Po | Zmiana |
|---|---|---|---|
| puste płótno | 7,4 ms | 7,2 ms | −2,1% |
| sam tekst | 12,6 ms | 12,6 ms | −0,3% |
| same ramki | 12,2 ms | 11,2 ms | −8,2% |
| ramki z tekstem | 19,1 ms | 19,9 ms | +4,3% |
| zaznaczenie | 20,7 ms | 20,5 ms | −0,7% |
| suwak | 16,6 ms | 16,0 ms | −3,9% |
| klatka z miniaturą | 29,1 ms | 28,3 ms | −2,9% |
| klatka z okienkiem | 25,1 ms | 25,2 ms | +0,4% |
| okno komend | 30,0 ms | 30,0 ms | −0,2% |

**Bez regresji powyżej progu**, a rozrzut wyników mieści się w szumie pomiaru.
Wynik trzeba jednak czytać z zastrzeżeniem, bo inaczej obiecywałby więcej, niż
mierzy: scenariusze `bin/render-bench` powstają w `ScenarioFactory` i idą prosto
do renderera, **z pominięciem `FrameComposer`**. Zgodność liczb mówi więc tyle,
że przebudowa stref nie zmieniła ani jednego prymitywu docierającego do
renderera — i to jest prawdziwa treść tego pomiaru.

Na pytanie z rozstrzygnięcia nr 7 („czy pas podglądu rysowany przez moduł zmienia
koszt klatki”) odpowiada za to **konstrukcja**, nie tabela: `PreviewBox` liczy
podgląd raz na klatkę, tak jak dawne `FrameComposer::previewOf()`, i **później niż
ono** — dopiero gdy strefa dostała wiersze. Okno niższe od progu pasa podglądu
robi po zmianie ściśle mniej roboty niż przed nią.

## Dziennik realizacji

**2026-08-10 — krok wykonany w całości.**

Co powstało i co zniknęło:

- **Domena katalogu zeszła do modułu**: `Directory`, `DirectoryPath`, `Entry`,
  `EntryType`, `Selection`, `DirectoryRepositoryInterface` i cztery wyjątki
  katalogu leżą w `src/Module/Browser/Domain/`. Razem z nimi sześć przypadków
  użycia, `FilesystemDirectoryRepository`, `EntryComparator`, ekran i komenda
  skoku. `Domain/` rdzenia to dziś pięć obiektów wartości plus hierarchia
  wyjątków.
- **`ScreenInterface` zmienił kształt po raz pierwszy od kroku 18**:
  `header()` i `preview()` oddające `?ScreenZone` zastąpiły `headerSuffix()`
  i `usesPreview()`. Zasada kroku 20 „moduł dostaje środkowy panel i nic poza
  nim” **została uchylona**, zgodnie z D40/P6.
- **`LoopState` stracił katalog**, `ScreenStack` — wpisane dno, `Bootstrap` —
  `startingPath()` i wszystkie sześć przypadków użycia nawigacji.
- **`startupModule` wszedł do konfiguracji**, `showHiddenEntries` z niej wyszedł
  do `modules.browser.showHidden` wraz z jednorazowym przepisaniem starego klucza.
- **Przeglądarka jest modułem ostatniej szansy**: rejestr sprawdza ją pierwszą,
  `isEnabled()` zawsze oddaje dla niej prawdę, a przełącznik na zakładce „Moduły”
  mówi tylko, dlaczego nie działa.

**Kontrakt modułu z kroku 20 nie zyskał ani jednej metody** — i to jest główny
wynik tego kroku, bo taki był jego sprawdzian. Główna funkcja aplikacji weszła
w `ModuleInterface`, `ProvidesScreen`, `ProvidesSettingsTab`, `ProvidesHelpTab`
i `ProvidesCommands` takie, jakie zastała. Zmienił się za to **kontrakt ekranu**,
i to było w planie.

Trzy zdania, którymi krok mierzył swoje powodzenie, sprawdzają testy:

1. `CoreKnowsNothingAboutFilesTest` — w `src/Domain`, `src/Application`,
   `src/Infrastructure` i `src/Presentation` nie ma ani jednego odwołania do
   `LightManager\Module\Browser\…`; jedyne, co widzi `Bootstrap`, to klasa
   `BrowserModule` i napis `'browser'` jako identyfikator modułu ostatniej szansy.
2. `BrowserModuleTest::testAnotherModuleNamedInTheConfigurationBecomesTheFloor` —
   `startupModule` wskazujący `file-info` uruchamia aplikację z jego ekranem jako
   dnem, bez zmiany w kodzie rdzenia.
3. `BrowserModuleTest` i `InputHandlerTest` — klawisze, napisy, pasek ścieżki
   wraz z numerem zaznaczenia i znacznikiem wpisów ukrytych oraz zachowanie pasa
   podglądu zostały co do znaku. Sprawdzone dodatkowo pod prawdziwym XTermem:
   klatka po zmianie wygląda tak, jak przed nią.

Cztery drogi awaryjne wyboru dna mają cztery osobne komunikaty i cztery testy
(`StartupScreenTest`). Przypadki się nie nakładają i wynika to z rejestru: moduł
nieobecny na liście nie ma jak być wyłączony, a wyłączony **nie jest sprawdzany**,
więc nie ma jak być zarazem odrzucony.

**Co sprawdziło się samo z siebie i warto to zapisać.** `ChangeModuleSettingUseCase`
z kroku 20 udźwignął ustawienie zmieniane **klawiszem**, w środku klatki, wraz
z ponownym odczytem katalogu — a to był jego pierwszy poważny sprawdzian i plan
kazał zapisać, gdyby go nie udźwignął. Nie trzeba było w nim zmienić nic:
`shift()` napisany dla strzałki na ekranie ustawień obsłużył `.` w przeglądarce
bez różnicy.
