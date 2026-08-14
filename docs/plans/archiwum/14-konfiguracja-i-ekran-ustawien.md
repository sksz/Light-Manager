# Krok 14 — Konfiguracja aplikacji i ekran ustawień

## Status

Ukończony

## Zależności

Krok 13 (motyw graficzny) — bez obiektu motywu z nazwanymi rolami nie ma
czego przełączać.

## Model i wysiłek

Opus / high — krok wprowadza pierwszy trwały stan poza pamięcią procesu
(plik na dysku) oraz drugi tryb interakcji w pętli głównej. Oba tematy
dotykają miejsc, które dotąd miały jedną ścieżkę.

## Cel

Dać aplikacji konfigurację: plik `~/.light-manager/settings.json` oraz
ekran ustawień wywoływany z poziomu aplikacji, w którym można wybrać motyw
i przestawić przełączniki dotąd zaszyte w kodzie.

Krok domyka dług zaciągnięty w D18: antyaliasing tekstu został wtedy
świadomie ustawiony na sztywno („przełącznik wystawimy, gdy w projekcie
pojawi się konfiguracja”). Tu pojawia się konfiguracja.

## Ustalenia (decyzje użytkownika, 2026-08-08)

Zapis w [00-decyzje.md](../00-decyzje.md), D26.

| Rozstrzygnięcie | Wybór |
|---|---|
| Motyw | **Przełączalny** — Grafit domyślny, pozostałe palety dostępne |
| Nośnik konfiguracji | **Plik JSON** w ukrytym katalogu w katalogu domowym: `~/.light-manager/settings.json` |
| Sposób zmiany | **Ekran konfiguracyjny w aplikacji** (nie edycja pliku ręcznie jako jedyna droga) |

## Zakres

### 1. Plik konfiguracyjny

Ścieżka: `~/.light-manager/settings.json`. Katalog i plik powstają dopiero
przy pierwszym zapisie — sam start aplikacji niczego nie tworzy na dysku.

Zawartość początkowa (klucze rosną wraz z projektem):

```json
{
    "theme": "grafit",
    "showHiddenEntries": false,
    "textAntialias": false,
    "strokeAntialias": true,
    "paletteColors": 64
}
```

*(Poprawione względem pierwotnej wersji planu: brakowało klucza
`strokeAntialias`, a `textAntialias` miał wartość `true` niezgodną z §4
i z D27 — domyślnie tekst jest **bez** wygładzania.)*

Zasady odczytu:

- **Brak pliku** → wartości domyślne, cisza (to normalny stan pierwszego
  uruchomienia).
- **Plik nieczytelny albo niepoprawny JSON** → wartości domyślne plus
  komunikat w pasku stanu w tonie `Warning`; aplikacja nie przerywa
  startu i **nie nadpisuje** pliku, którego nie zrozumiała.
- **Nieznany klucz** → pomijany po cichu. **Znany klucz z wartością spoza
  zakresu** (np. `"theme": "nieistniejacy"`) → wartość domyślna dla tego
  klucza plus komunikat.

Zapis: przez plik tymczasowy i `rename()` w tym samym katalogu, żeby
przerwany zapis nie zostawił obciętego pliku. Zapis wyłącznie na żądanie —
po zmianie ustawienia na ekranie konfiguracyjnym.

### 2. Warstwy

| Element | Warstwa | Rola |
|---|---|---|
| `Application/Dto/Settings` | Application | Wartości konfiguracji jako DTO portu. |
| `Application/Port/SettingsPort` | Application | `load(): Settings`, `save(Settings $settings): void`. |
| `Infrastructure/Config/SettingsService` | Infrastructure | Singleton: ścieżka, odczyt, walidacja, zapis atomowy. |
| `Infrastructure/Config/ConfigException` | Infrastructure | Wyjątek dziedziczący po `InfrastructureException`. |
| `Application/Port/ThemePort` | Application | Lista dostępnych motywów i wybór aktywnego — port zakładamy **dopiero teraz**, bo dopiero teraz `Application` naprawdę woła motyw (zasada z D17). |

`Infrastructure/Rendering/ThemeService` z kroku 13 zyskuje katalog czterech
palet (Grafit, Nordyk, Papier, Indygo — wartości z porównania z
2026-08-08) i implementuje `ThemePort`.

### 3. Ekran ustawień

Drugi tryb pętli głównej. Dziś `LoopState` zna jeden stan — przeglądanie
katalogu; tu dochodzi tryb ustawień, a `InputHandler` kieruje klawisze do
właściwej obsługi.

Zachowanie:

- Otwarcie klawiszem — propozycja: `,` (nieużywany, wolny od kolizji z
  nawigacją). Zamknięcie: `Esc`.
- Strzałki góra/dół — wybór pozycji, lewo/prawo albo `Enter` — zmiana
  wartości, `Esc` — powrót.
- **Podgląd na żywo**: zmiana motywu przerysowuje ekran natychmiast, bez
  restartu. Pętla i tak składa klatkę 20 razy na sekundę, więc kosztuje to
  tylko podmianę obiektu motywu.
- Zapis do pliku po każdej zmianie wartości (nie przy wyjściu z ekranu) —
  ustawienie przeżywa zabicie procesu sygnałem.

Pozycje na start: motyw (lista), wygładzanie tekstu (tak/nie), wygładzanie
obrysów (tak/nie), liczba kolorów palety Sixela (16/32/64/128), pokazywanie
wpisów ukrytych (tak/nie — dziś przełączane klawiszem `.`, tu zyskuje
trwałość).

Przy palecie warto pokazać ostrzeżenie: poniżej 64 kolorów obwódki paneli
znikają z klatki (D27). Ustawienie zostaje dostępne, ale użytkownik ma
wiedzieć, co kupuje.

**Wartości domyślne są tymczasowe.** Paleta 64 i wygładzanie „tekst nie,
obrys tak” pochodzą z doraźnych pomiarów kroku 13 — robionych przez
podmienianie stałych w kodzie, bez powtarzalnej metody. Po
[kroku 16](16-narzedzia-diagnostyczne-wydajnosci.md), który daje narzędzie
pomiarowe, należy je zweryfikować i poprawić, jeśli liczby powiedzą co
innego. Jawnie odłożony dług.

### 4. Przełączniki dotąd zaszyte w kodzie

| Stała | Miejsce | Po zmianie |
|---|---|---|
| `TEXT_ANTIALIAS` | `SixelFrameEncoder` | z konfiguracji (dług z D18); domyślnie wyłączony |
| `STROKE_ANTIALIAS` | `SixelFrameEncoder` | z konfiguracji; domyślnie **włączony** — bez niego nie ma zaokrągleń (D27) |
| `PALETTE_COLORS` | `SixelFrameEncoder` | z konfiguracji, wartość domyślna z pomiarów kroku 13 |
| `PALETTE_COLORS_WITH_IMAGE` | `SixelFrameEncoder` | zostaje w kodzie — wynika z natury bitmapy, nie z gustu |
| stan „pokaż ukryte” | `LoopState` | wczytywany ze konfiguracji przy starcie |

## Poza zakresem tego kroku

- Konfiguracja skrótów klawiszowych.
- Standard XDG (`$XDG_CONFIG_HOME/light-manager/`) — rozstrzygnięty na
  starcie kroku: zostaje jedna, przewidywalna ścieżka (D31).
- Motywy definiowane przez użytkownika w pliku (własne wartości
  `#rrggbb`) — na razie wybór z czterech wbudowanych.
- Przeładowanie pliku po zmianie z zewnątrz (obserwowanie pliku).

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Dto/Settings.php` | Application | Nowy. |
| `Application/Port/SettingsPort.php` | Application | Nowy. |
| `Application/Port/ThemePort.php` | Application | Nowy. |
| `Application/UseCase/LoadSettingsUseCase.php` | Application | Nowy — start aplikacji. |
| `Application/UseCase/ChangeSettingUseCase.php` | Application | Nowy — zmiana + zapis. |
| `Application/UseCase/RenderSettingsFrameUseCase.php` | Application | Nowy — składanie klatki ekranu ustawień. |
| `Infrastructure/Config/SettingsService.php` | Infrastructure | Nowy — Singleton, odczyt/zapis JSON. |
| `Infrastructure/Config/ConfigException.php` | Infrastructure | Nowy. |
| `Infrastructure/Rendering/ThemeService.php` | Infrastructure | Katalog czterech palet, implementacja `ThemePort`. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Antyaliasing i liczba kolorów z konfiguracji. |
| `Presentation/Cli/LoopState.php` | Presentation | Tryb (przeglądanie / ustawienia). |
| `Presentation/Cli/InputHandler.php` | Presentation | Rozdział klawiszy wg trybu. |
| `Presentation/Cli/GameLoop.php` | Presentation | Wybór przypadku użycia składającego klatkę wg trybu. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Wczytanie konfiguracji przed pętlą, dowiązanie nowych portów. |
| `README.md` | Dokumentacja | Opis pliku konfiguracyjnego i klawisza otwierającego ustawienia. |
| `docs/architecture.md` | Dokumentacja | Nowe porty w tabeli §3, nowy katalog `Infrastructure/Config` w §1. |
| testy | Testy | Odczyt/zapis/walidacja konfiguracji, przełączanie trybu pętli, składanie klatki ustawień. |

## Rozstrzygnięcia ze startu kroku (2026-08-09)

Zapis w [00-decyzje.md](../00-decyzje.md), D31.

| Pytanie | Rozstrzygnięcie |
|---|---|
| `~/.light-manager/` a XDG | **Jedna, przewidywalna ścieżka** `~/.light-manager/settings.json`; XDG odrzucone |
| Kształt klatki ekranu | **Istniejący `Frame` + `FrameLine`**, zaznaczenie stylem `Selected` — renderery bez zmian |
| Klawisz otwierający | **`F2`**, a dodatkowo **`F1`** dla pełnego ekranu pomocy |
| Zmiana liczby kolorów palety | **Natychmiast**, jak podgląd motywu |

Rozszerzenia zakresu zgłoszone przy okazji (poza pierwotnym planem):

- **Ekran pomocy pod `F1`** — trzeci tryb pętli, pełna ściągawka klawiszy.
- **Zakładki na ekranie ustawień** — „Wygląd” i „Grafika”; pasek zakładek
  jest jednym z miejsc, które odwiedza kursor.
- **Skrócona stopka** — `↑↓ ruch · F1 pomoc · F2 ustawienia · q wyjście`
  zamiast pięciopozycyjnej ściągawki.
- **Ekrany zajmują tylko środkowy panel** — ścieżka i pasek stanu zostają.

## Specyfikacja zrealizowana

### Nawigacja po ekranie ustawień

```
╭── SETTINGS ────────────────────────────────╮
│▌ [ WYGLĄD ]   GRAFIKA                      │  ← wiersz 0: pasek zakładek
│                                            │  ← wiersz 1: odstęp
│  Motyw                        Grafit       │  ← wiersz 2: pierwsza pozycja
│  Pokazuj wpisy ukryte         nie          │
╰────────────────────────────────────────────╯
```

| Klawisz | Na pasku zakładek | Na pozycji |
|---|---|---|
| `↑` | — | wyżej; z pierwszej pozycji na pasek |
| `↓` | wejście na pierwszą pozycję | niżej (bez zawijania) |
| `←` / `→` | poprzednia / następna zakładka (cyklicznie) | poprzednia / następna wartość |
| `Enter` | następna zakładka | następna wartość |
| `Esc` / `F2` | powrót do listy plików | powrót do listy plików |

Podział pozycji: **Wygląd** — motyw, pokazywanie wpisów ukrytych;
**Grafika** — wygładzanie tekstu, wygładzanie obrysów, liczba kolorów
palety Sixela.

### Cztery palety

Wartości pozostałych trzech palet nie były nigdzie zapisane (porównanie z
2026-08-08 istniało wyłącznie jako makiety w rozmowie), więc powstały na
nowo, według tych samych ról co Grafit: **Nordyk** (chłodna,
niskokontrastowa, akcent `#88c0d0`), **Papier** (jedyna jasna — obwódka
musi być *ciemniejsza* od tła, odwrotnie niż w pozostałych), **Indygo**
(granat z błękitem `#8ab4f8` sprzed kroku 13, ale z rozdzielonymi rolami).
Wymagają obejrzenia pod XTermem — patrz „Dług”.

### Zmiany wykraczające poza tabelę „Planowane zmiany w plikach”

| Plik | Dlaczego |
|---|---|
| `Application/Port/FrameRendererPort.php` | `render()` dostaje `Frame` **wraz z** `FrameLayout`. Renderer liczył układ po raz drugi po swojej stronie; od tego kroku rozminąłby się z warstwą aplikacji, bo o kształcie stref decyduje ekran, którego renderer nie zna. |
| `Application/Port/FrameLayoutPort.php` | `layoutFor()` przyjmuje `Screen` — pas podglądu należy do listy plików, na pozostałych ekranach jego wiersze wracają do środkowego panelu, a ten dostaje etykietę `SETTINGS` albo `HELP`. |
| `Application/Dto/Key.php`, `Infrastructure/Terminal/KeySequenceParser.php` | Klawiszy funkcyjnych w projekcie nie było. Dodano **F1–F12**, nie tylko dwa używane: tablica kodów to i tak jedno miejsce, a połowiczna obsługa wróciłaby przy pierwszym `F3`. |
| `Application/UseCase/RenderHelpFrameUseCase.php` | Ekran pomocy — nie było go w planie. |
| `Domain/ValueObject/DirectoryPath.php` | `shortenedTo()` — skracanie ścieżki od lewej potrzebne teraz w trzech miejscach zamiast jednego. |
| `Infrastructure/Rendering/RenderingOptions.php` | Motyw i trzy przełączniki zebrane w jedną wartość obowiązującą przez jedną klatkę. |

## Dług świadomie zaciągnięty

1. **Okno edycji wartości tekstowej.** Ustalony model interakcji
   przewiduje, że `Enter` na pozycji z polem tekstowym otwiera okienko z
   bieżącą wartością (`Enter` zatwierdza, `Esc` anuluje). **Nie
   zaimplementowane**: żadne z pięciu dzisiejszych ustawień nie jest polem
   tekstowym, więc powstałaby ścieżka bez wywołania i bez sposobu na
   przetestowanie. Do zrobienia razem z pierwszym takim ustawieniem.
2. **Wartości domyślne wygładzania i palety** — patrz „Wartości domyślne
   są tymczasowe” wyżej; weryfikacja po
   [kroku 16](16-narzedzia-diagnostyczne-wydajnosci.md).
3. **Trzy nowe palety nieobejrzane na ekranie.** Grafit przeszedł w kroku
   13 przez oglądanie renderów; Nordyk, Papier i Indygo — nie. Papier jest
   tu najbardziej ryzykowny: jasne tło odwraca cały rachunek kontrastu, na
   którym opierało się dobranie obwódki (D27).

## Kryteria ukończenia

- Aplikacja startuje bez pliku konfiguracyjnego i niczego nie tworzy na
  dysku, dopóki użytkownik nie zmieni ustawienia.
- Uszkodzony plik nie przerywa startu i nie zostaje nadpisany.
- Ekran ustawień otwiera się i zamyka klawiszem, pozwala zmienić cztery
  pozycje, a zmiana motywu jest widoczna natychmiast.
- Zmienione ustawienie przeżywa ponowne uruchomienie aplikacji.
- Stałe `TEXT_ANTIALIAS` i `PALETTE_COLORS` znikają z `SixelFrameEncoder`.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `README.md` i `docs/architecture.md` opisują konfigurację.

## Dziennik realizacji

### 2026-08-09 — realizacja kroku

**Zrobione:**

- Konfiguracja: `Application/Dto/{Settings, SettingKey, SettingsTab,
  SettingsCursor, LoadedSettings, Screen}`, porty `SettingsPort` i
  `ThemePort`, `Infrastructure/Config/{SettingsService, ConfigException}`.
  Odczyt wyrozumiały (brak pliku — cisza, nieznany klucz — cisza, wartość
  spoza zakresu — domyślna plus ostrzeżenie), zapis przez plik tymczasowy
  i `rename()`, prawa `0600`.
- Cztery palety w `Theme`, katalog i `ThemePort` w `ThemeService`,
  `RenderingOptions` jako wartość obowiązująca przez jedną klatkę.
- Stałe `TEXT_ANTIALIAS`, `STROKE_ANTIALIAS` i `PALETTE_COLORS` zniknęły z
  `SixelFrameEncoder`; `PALETTE_COLORS_WITH_IMAGE` została, zgodnie z
  planem.
- Trzy ekrany w pętli: przeglądanie, ustawienia (`F2`), pomoc (`F1`).
  `LoopState` zna ekran, kursor ustawień i przewijanie pomocy;
  `InputHandler` rozdziela klawisze wg ekranu; `GameLoop` wybiera
  przypadek użycia składający klatkę.
- Klawisze funkcyjne F1–F12 w `Key` i `KeySequenceParser` (warianty SS3,
  tyldowy i z modyfikatorem).
- 27 nowych testów; łącznie 430 testów, 952 asercje. PHPStan `max` bez
  błędów, PHP-CS-Fixer bez uwag.

**Odstępstwa od planu:**

- **Klawisz otwierający: `F2`, nie `,`** — decyzja użytkownika, wraz z
  `F1` dla pomocy. Wymusiło to obsługę klawiszy funkcyjnych, której
  projekt dotąd nie miał.
- **Trzeci ekran (pomoc) i zakładki na ekranie ustawień** — rozszerzenie
  zakresu zgłoszone przy rozstrzyganiu pytań startowych.
- **`FrameRendererPort::render()` przyjmuje `FrameLayout`** — plan tego nie
  przewidywał, ale bez tego renderer liczyłby układ dla niewłaściwego
  ekranu. Przy okazji zniknął podwójny rachunek układu na klatkę.
- **`SettingsPort` nie rzuca wyjątków** — plan zakładał
  `save(Settings): void` i `ConfigException` przechodzący przez granicę.
  Wyjątek infrastruktury po stronie `Application` byłby złamaniem reguły
  zależności, więc port oddaje problem opisem. `ConfigException` istnieje
  i działa, ale wyłącznie wewnątrz `Infrastructure/Config`.
- **`SettingsPort::load()` przyjmuje listę nazw motywów** — zakres klucza
  `theme` zna katalog palet, a nie nośnik konfiguracji. Bez tego
  `Infrastructure/Config` musiałoby sięgać do `Infrastructure/Rendering`,
  a walidacja rozjechałaby się między dwie warstwy.
- **Przełącznik `.` zyskał trwałość** i wspólną drogę z ekranem ustawień:
  oba przechodzą przez `ChangeSettingUseCase`, oba przeładowują katalog
  **przed** zapisem, żeby nieudany odczyt nie zostawił pliku niezgodnego z
  listą na ekranie.
- **Wartości trzech dodatkowych palet powstały na nowo** — nie były
  zapisane w repozytorium.

**Nie zrobione (świadomie):** okno edycji wartości tekstowej — patrz
sekcja „Dług świadomie zaciągnięty”.

**Weryfikacja manualna:** pozostaje po stronie użytkownika — trzy nowe
palety, wygląd zakładek i ekranu pomocy w trybie Sixel wymagają obejrzenia
pod XTermem. Testy sprawdzają strukturę klatki, nie jej wygląd.
