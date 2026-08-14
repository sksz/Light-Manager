# Krok 11 — Renderowanie listy plików w klatce

## Status

Ukończony

## Zależności

Krok 08 (potok renderowania), Krok 10 (stan nawigacji).

## Model i wysiłek

Sonnet / medium — rozszerzenie istniejącego potoku renderowania o nową
treść, bez nowych problemów niskopoziomowych.

## Cel

Połączyć stan nawigacji (krok 10) z potokiem renderowania (krok 08): lista
plików/katalogów bieżącego katalogu staje się faktyczną zawartością
rysowanej klatki.

## Zakres

- Rysowanie listy wpisów jako tekstu w obrębie klatki Imagick (oraz w
  rendererze fallback).
- Wizualne odróżnienie zaznaczonego wpisu (np. inwersja kolorów/tło) i
  typu wpisu (katalog vs plik).
- Przewijanie listy, gdy liczba wpisów przekracza dostępną wysokość
  ekranu (okno przewijania podążające za zaznaczeniem).
- Nagłówek/pasek ze ścieżką bieżącego katalogu.

## Poza zakresem tego kroku

Miniatury obrazów (krok 12).

## Kryteria ukończenia

- Lista plików bieżącego katalogu jest widoczna na ekranie i aktualizuje
  się przy nawigacji.
- Zaznaczenie jest jednoznacznie widoczne, a lista poprawnie przewija się
  dla katalogów z dużą liczbą wpisów.

## Specyfikacja zrealizowana

### Powstałe i zmienione pliki

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Domain/ValueObject/Frame.php` | Domain | Przepisany: tytuł, `list<FrameLine>`, komunikat, okienko. |
| `Domain/ValueObject/FrameLine.php` | Domain | Nowy — tekst wiersza wraz ze stylem. |
| `Domain/ValueObject/LineStyle.php` | Domain | Nowy — enum: zwykły, katalog, zaznaczony, zaznaczony katalog. |
| `Domain/ValueObject/Popup.php` | Domain | **Przeniesiony** z `Application/Dto`. |
| `Application/Port/ViewportPort.php` | Application | Nowy — liczba wierszy i kolumn obszaru rysowania. |
| `Application/UseCase/RenderCurrentFrameUseCase.php` | Application | Przepisany: nagłówek, formatowanie wpisów, rozmiary, okno przewijania. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Przepisany: nagłówek, pasek zaznaczenia, stopka, okienko jako prostokąt. |
| `Infrastructure/Imagick/SixelFrameMetrics.php` | Infrastructure | Nowy — przelicza siatkę znakową terminala na piksele. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | Przepisany: style ANSI, stopka, okienko ze znaków rysunkowych. |
| `Infrastructure/Terminal/TerminalSizeService.php` | Infrastructure | Implementuje `ViewportPort`. |
| `Presentation/Cli/LoopState.php` | Presentation | Usunięte pola zastępcze z kroku 09 (licznik klatek, ostatni klawisz). |
| 6 plików testów | Testy | 27 nowych testów (łącznie 218). |

### Ustalone zachowania

| Zachowanie | Rozstrzygnięcie |
|---|---|
| Kształt klatki | Wiersze z jawnym stylem — renderer tłumaczy styl na kolory, nie zna pojęć domenowych |
| Przewijanie | Liczone w `Application` przez nowy `ViewportPort`, więc w pełni testowalne |
| Okno przewijania | Podąża za zaznaczeniem z zapasem 2 wierszy od krawędzi |
| Zaznaczenie | Pasek na pełną szerokość w odwróconych kolorach |
| Katalogi | Inny kolor **oraz** końcowy ukośnik — czytelne także bez kolorów |
| Rozmiar pliku | Skrócony z jednostką, z polskim przecinkiem: `340 B`, `1,2 kB`, `5,0 MB` |
| Układ | Nagłówek u góry, lista pośrodku, komunikat w ostatnim wierszu |
| Nagłówek | Ścieżka + pozycja `26/31` + znacznik `• ukryte`, gdy ukryte są widoczne |
| Okienko | W trybie Sixel prawdziwy prostokąt z obwódką; w tekstowym ramka ze znaków |

### Kluczowe rozstrzygnięcia techniczne

- **`Popup` przeniesiony z `Application/Dto` do `Domain/ValueObject`.** Wymusiła
  to reguła zależności: `Frame` jest obiektem wartości w `Domain` i nie może
  odwoływać się do `Application`. Skoro `Frame` opisuje zawartość ekranu, jego
  nakładka należy do tej samej warstwy. Alternatywą byłoby zduplikowanie klasy
  po obu stronach granicy.
- **`SixelFrameMetrics` przelicza siatkę znakową na piksele.** Wysokość wiersza
  bierze się z podziału płótna przez liczbę wierszy terminala, a nie ze stałej —
  inaczej lista policzona w warstwie aplikacji (na podstawie `ViewportPort`) nie
  mieściłaby się w klatce rysowanej przez Imagick. Rozmiar fontu wynika z
  wysokości wiersza.
- **Wiersz wypełnia pełną szerokość.** Nazwa jest dopychana spacjami do miejsca,
  w którym zaczyna się rozmiar, żeby pasek zaznaczenia sięgał krawędzi ekranu.
  Za długie nazwy są skracane wielokropkiem, ścieżka w nagłówku — od lewej, bo
  jej ogon niesie więcej informacji niż korzeń drzewa.
- **Okno przewijania jest stanem widoku**, trzymanym w przypadku użycia i
  zerowanym przy zmianie katalogu. Prawdziwy margines wymaga pamiętania
  poprzedniego położenia okna — bez tego lista przesuwałaby się przy każdym
  ruchu zaznaczenia.
- **Pola zastępcze z kroku 09 usunięte** (`renderedFrames`, `lastKey` w
  `LoopState`, wraz z ich metodami) — klatka pokazuje teraz realną treść, więc
  licznik klatek i ostatni klawisz nie mają już czego obsługiwać.

### Weryfikacja

Testy jednostkowe pokrywają składanie klatki bez terminala: nagłówek, style,
formatowanie rozmiarów, skracanie nazw, pusty katalog oraz **pięć scenariuszy
przewijania** (okno stoi, podąża w dół, wraca w górę, zatrzymuje się na końcu
listy, zeruje się po zmianie katalogu). Enkoder Sixel sprawdzany strukturalnie —
każdy element treści (zaznaczenie, katalog, komunikat, okienko) musi zmieniać
wynikowe bajty.

Pod realnym PTY, na katalogu z 32 wpisami:

| Sprawdzenie | Wynik |
|---|---|
| Klatka startowa | Nagłówek ze skróconą ścieżką i `1/31`, katalogi z ukośnikiem na górze listy, 21 wierszy treści |
| Sortowanie i rozmiary | `Archiwum/ ćwiczenia/ projekty/ film.mkv 5,0 MB … raport.csv` |
| Przewijanie po 25 ruchach w dół | Nagłówek `26/31`, lista zaczyna się od `plik-03`, zaznaczenie 2 wiersze od dolnej krawędzi |
| Zaznaczenie | Wiersz otoczony `ESC [ 7 m` (inwersja), katalogi `ESC [ 36 m` |
| Okienko | Wyśrodkowana ramka z tytułem `notatka.txt` i opisem z `file` |
| Komunikat | Ostatni wiersz ekranu, na czerwono (`ESC [ 31 m`) |
| Tryb Sixel | 15 kompletnych klatek, 0 uciętych, ostatnia 61,5 kB przy płótnie 1000×600 |

Układ pikselowy w trybie Sixel nie był oglądany — sprawdzono go strukturalnie i
testami; wygląd zweryfikowano na rendererze tekstowym, który dostaje dokładnie
te same wiersze i style.

## Dziennik realizacji

- **2026-08-08** — Ukończono. Lista plików, zaznaczenie, przewijanie, nagłówek,
  komunikat i okienko trafiły na ekran w obu trybach renderowania. Powstały
  3 nowe klasy domenowe, port widoku i klasa metryki; przepisane zostały klatka,
  przypadek użycia składający klatkę oraz oba renderery. 27 nowych testów
  (łącznie 218), PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag.

  **Decyzje użytkownika podjęte na starcie kroku** (zobacz
  [00-decyzje.md](../00-decyzje.md), D21): kształt `Frame` jako wiersze ze stylem;
  przewijanie liczone w `Application` przez nowy port; pasek w inwersji i
  ukośnik przy katalogu; nagłówek u góry, komunikat w stopce; rozmiary skrócone
  z jednostką; margines przewijania; okienko jako prawdziwy prostokąt w trybie
  Sixel; licznik pozycji i znacznik ukrytych w nagłówku.

  **Znane ograniczenia:** klatka nie reaguje na zmianę rozmiaru okna (rozmiar
  mierzony raz, przy starcie — ograniczenie z kroku 08); okienko nie zawija
  długich opisów, tylko je przycina; nazwy z podwójną szerokością znaku (CJK,
  emoji) liczone są jak pojedyncze, więc mogą rozjechać wyrównanie rozmiarów.
