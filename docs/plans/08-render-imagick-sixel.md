# Krok 08 — Potok renderowania: Imagick canvas → Sixel → STDOUT

## Status

Ukończony

## Zależności

Krok 05 (szkielet), Krok 07 (wynik wykrywania trybu renderowania).

## Model i wysiłek

Opus / high — rdzeń wizualny całej aplikacji i największe ryzyko techniczne
projektu (renderowanie tekstu przez Imagick, poprawność enkodowania Sixel,
wydajność, repozycjonowanie kursora).

## Cel

Dostarczyć wspólny, wielokrotnego użytku mechanizm: zbuduj klatkę jako obraz
Imagick → zakoduj (Sixel albo fallback tekstowy) → wypisz na STDOUT tak, by
kolejne klatki nadpisywały poprzednią w tym samym miejscu ekranu, zamiast
przewijać terminal w nieskończoność.

## Zakres

- Abstrakcja „klatki” (frame): obiekt/kontener opisujący co ma się znaleźć
  na ekranie (na razie: tło + tekst statyczny jako placeholder — treść z
  listy plików dochodzi w kroku 11), niezależny od tego, czy renderer to
  Sixel czy tekst.
- Renderer Sixel: budowa `Imagick` (rozmiar płótna, tło), rysowanie tekstu
  przez `ImagickDraw`/`annotateImage` (wymaga sprawdzenia dostępności
  delegata fontów), `setImageFormat('sixel')`, pobranie bajtów i zapis na
  STDOUT.
- Repozycjonowanie kursora: przed każdym przerysowaniem przesunięcie
  kursora do znanego punktu startowego klatki (sekwencje ANSI
  cursor-up/home) tak, by kolejna klatka nadpisała poprzednią zamiast
  dopisywać się pod spodem.
- Renderer fallback (tekstowy/ANSI): ten sam kontrakt „narysuj klatkę”, ale
  wyjście jako zwykły tekst z kodami ANSI (kolor/pozycjonowanie), używany
  gdy krok 07 zwróci `TextFallback`.
- Prosty pomiar/log czasu renderowania jednej klatki (przyda się do oceny,
  czy pełne przerysowanie na każdą klatkę jest wystarczająco szybkie dla
  MVP).

## Poza zakresem tego kroku

Treść klatki związana z listą plików i miniaturami (kroki 11, 12) — tu
powstaje mechanizm, nie docelowa zawartość.

## Ryzyka

- Renderowanie tekstu przez Imagick wymaga dostępnego delegata fontów (np.
  FreeType) w instalacji ImageMagick — do zweryfikowania, z planem B
  (rysowanie prostych kształtów zamiast tekstu, jeśli render tekstu jest
  niedostępny/zbyt wolny).
- Wydajność: pełne przerysowanie całej klatki przez Imagick + enkodowanie
  Sixel przy każdym cyklu pętli może być zauważalnie wolne — do zmierzenia
  w tym kroku; jeśli okaże się problemem, temat ograniczenia częstotliwości
  przerysowań trafia do kroku 09 i/lub dziennika decyzji.

## Kryteria ukończenia

- Uruchomienie renderera dwukrotnie z rzędu pokazuje drugą klatkę w miejscu
  pierwszej (bez przewijania ekranu), zarówno w trybie Sixel, jak i
  fallback.
- Zmierzony czas jednego cyklu render+wypisanie jest udokumentowany (nawet
  jeśli tylko orientacyjnie) w dzienniku realizacji tego kroku.

## Specyfikacja zrealizowana

### Powstałe pliki

| Plik | Warstwa | Rola |
|---|---|---|
| `Domain/Exception/DomainException.php` | Domain | Abstrakcyjna baza wyjątków domenowych (odroczona z kroku 03 — pierwszy realny użytkownik pojawił się tutaj). |
| `Domain/Exception/InvalidFrameException.php` | Domain | Wyjątek klatki, nazwany konstruktor `::forEmptyTitle()`. |
| `Domain/ValueObject/Frame.php` | Domain | Niemutowalny opis ekranu: tytuł + wiersze, `equals()`. |
| `Application/Port/FrameRendererPort.php` | Application | Kontrakt: `render(Frame $frame): void`. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Klatka → bajty Sixel; nie wie nic o terminalu. |
| `Infrastructure/Rendering/SixelFrameRenderer.php` | Infrastructure | Wypchnięcie bloba na terminal z repozycjonowaniem kursora. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | Wariant tekstowy/ANSI dla trybu fallback. |
| `Infrastructure/Rendering/RendererService.php` | Infrastructure | Singleton implementujący port; wybiera strategię i przejmuje ekran. |
| `Infrastructure/Terminal/TerminalSize.php` | Infrastructure | Rozmiar okna w pikselach i w komórkach. |
| `Infrastructure/Terminal/WindowSizeParser.php` | Infrastructure | Czysta analiza odpowiedzi na `ESC [ 14 t`. |
| `Infrastructure/Terminal/TerminalSizeService.php` | Infrastructure | Singleton mierzący okno raz, przy starcie. |
| 4 pliki testów | Testy | 26 nowych testów (łącznie 91). |

Rozszerzone: `TerminalService` (alternatywny bufor ekranu, `sizeInCells()`,
`pushBackBytes()`), `ImagickCapabilityService` (wybór fontu o stałej szerokości),
oba parsery odpowiedzi terminala (`strip()`).

### Kluczowe rozstrzygnięcia techniczne

- **Kolejność operacji przed enkodowaniem zmieniła wydajność 3,6×.** Sama
  zamiana `setImageFormat('sixel')` na obrazie Q16 kosztuje 425 ms przy płótnie
  800×600. Kwantyzacja do 16 kolorów **i** przełączenie obrazu na typ paletowy
  (`IMGTYPE_PALETTE`) skracają to do 118 ms, przy czym samo enkodowanie Sixela
  spada z ~290 ms do ~5 ms — reszta czasu to rysowanie tekstu i kwantyzacja.
  Wariant bez kwantyzacji, za to z typem paletowym, jest **wolniejszy** (271 ms)
  niż oba kroki naraz.
- **Płótno wypełnia całe okno**, więc kolejna klatka zamalowuje poprzednią bez
  czyszczenia ekranu — wystarczy `ESC [ H` przed każdym rysowaniem. Renderer
  tekstowy przeciwnie: tekst nie pokrywa całego okna, więc czyści ekran jawnie
  (`ESC [ 2 J`), inaczej zostawałyby resztki dłuższej poprzedniej klatki.
- **Alternatywny bufor ekranu włącza renderer, nie `TerminalService`.** Gdyby
  robił to konstruktor usługi terminala, narzędzia korzystające z samego
  wejścia (`bin/terminal-probe`) traciłyby całe swoje wypisane wyjście przy
  wyjściu z bufora. Wyjście z bufora obsługuje natomiast `restore()`, więc
  wszystkie trzy ścieżki przywracania z kroku 06 działają bez zmian.
- **Pomiar rozmiaru okna przeniesiony do bootstrapu.** Pierwotnie
  `TerminalSizeService` powstawał leniwie, przy pierwszym renderowaniu — przez
  co czas pierwszej klatki wynosił 305 ms zamiast ~1 ms (doliczał się timeout
  zapytania o rozmiar). Teraz tworzy go konstruktor `RendererService`, więc
  `lastRenderMilliseconds()` mierzy faktyczne rysowanie.
- **Font o stałej szerokości wybierany z listy preferencji** (DejaVu Sans Mono →
  Liberation Mono → Nimbus Mono PS → FreeMono → Courier), z awaryjnym szukaniem
  czegokolwiek kończącego się na „Mono” i ostatecznie fontem domyślnym
  ImageMagick. To realizacja „planu B” z sekcji Ryzyka: delegat fontów jest w
  tym środowisku dostępny (632 fonty znane Imagickowi), ale kod nie zakłada
  konkretnej instalacji.

### Defekt wykryty w trakcie: połykanie cudzej odpowiedzi

Krok 08 dołożył **drugie** zapytanie do terminala (`ESC [ 14 t` obok `ESC [ c`
z kroku 07). Okazało się, że czytnik jednego zapytania konsumuje i wyrzuca
odpowiedź drugiego, jeśli ta przyjdzie w jego oknie czasowym — w teście z
odwróconą kolejnością odpowiedzi wykrywanie Sixela dawało fałszywe
`TextFallback` mimo poprawnej odpowiedzi terminala.

Naprawa: `TerminalService::pushBackBytes()` plus metoda `strip()` w obu
parserach. Po dopasowaniu własnej odpowiedzi czytnik oddaje resztę bufora, więc
kolejne zapytanie ją znajdzie, a znaki wpisane przez użytkownika nie giną.
Bajty **nie** są oddawane po przekroczeniu limitu czasu — nieodebrana
odpowiedź trafiłaby wtedy do strumienia klawiszy jako fałszywe zdarzenia.

Zweryfikowano trzy kolejności doręczenia odpowiedzi (zgodna z zapytaniami,
odwrotna, obie naraz jednym ciągiem) — w każdej wynik to `Sixel` i poprawne
960×540.

### Pomiary wydajności

Płótno 960×540, klatka z tytułem i 22 wierszami treści, mediana z 10 klatek,
odczyt przez `lastRenderMilliseconds()`:

| Tryb | Bootstrap | Klatka min | Mediana | Maks | Rozmiar klatki |
|---|---|---|---|---|---|
| Sixel | 50 ms | 106,2 ms | **111,7 ms** | 114,8 ms | ~103 kB |
| Tekstowy | 651 ms | 0 ms | **0 ms** | 0,1 ms | ~130 B |

Dla klatki z 3 wierszami tryb Sixel schodzi do ~58 ms. Wnioski dla kroku 09:
**przerysowywanie zdarzeniowe, po odebranym wejściu, a nie w stałym takcie** —
111 ms na klatkę to ~9 klatek na sekundę, więc rysowanie „na zapas” w pętli
paliłoby procesor bez powodu.

Bootstrap 651 ms w trybie tekstowym to koszt dwóch zapytań, na które terminal
nie odpowiedział (2 × 300 ms). Możliwe usprawnienie na przyszłość: pomijać
zapytanie o piksele, gdy tryb i tak jest tekstowy.

### Weryfikacja pod realnym PTY

| Sprawdzenie | Wynik |
|---|---|
| Wejście/wyjście z alternatywnego bufora | dokładnie 1 × `ESC [ ? 1049 h` i 1 × `ESC [ ? 1049 l`, kursor ukryty i przywrócony |
| Siedem klatek pod rząd | 7 ładunków Sixel, 7 terminatorów, 7 × `ESC [ H` |
| **Nadpisywanie klatek** | między końcem jednej klatki a początkiem następnej **wyłącznie** `ESC [ H` — zero przewijania |
| Tryb tekstowy | 7 × `ESC [ 2 J` + `ESC [ H`, zero ładunków Sixel, treść czytelna |
| Objętość strumienia | 31,6 kB (Sixel) vs 928 B (tekst) na 7 klatek |

## Dziennik realizacji

- **2026-08-07** — Ukończono. Zrealizowano pełny zakres kroku: abstrakcja
  klatki, renderer Sixel przez Imagick, renderer tekstowy, repozycjonowanie
  kursora i pomiar czasu klatki. Powstało 11 plików produkcyjnych i 4 pliki
  testów (26 nowych testów, łącznie 91); PHPStan `max` bez błędów,
  PHP-CS-Fixer bez uwag.

  **Decyzje użytkownika podjęte na starcie kroku** (zobacz
  [00-decyzje.md](00-decyzje.md), D18): rozmiar płótna z zapytania o piksele z
  fallbackiem na `stty size`; alternatywny bufor ekranu; antyaliasing tekstu
  **domyślnie włączony**, z przełącznikiem odłożonym na później — zrealizowane
  jako jedna, opisana stała `TEXT_ANTIALIAS` w `SixelFrameEncoder`, bez
  budowania systemu konfiguracji.

  **Uzupełnienia poza literalnym zakresem:** `Domain/Exception/DomainException`
  (odroczony z kroku 03) wraz z `InvalidFrameException`; ukrywanie kursora
  razem z wejściem w alternatywny bufor; `TerminalService::pushBackBytes()` i
  `strip()` w parserach jako naprawa defektu opisanego wyżej.

  **Znane ograniczenia:** klatka nie reaguje na zmianę rozmiaru okna — rozmiar
  jest mierzony raz, przy starcie (obsługa SIGWINCH nie jest w planie MVP);
  start w trybie tekstowym kosztuje ~0,65 s czekania na odpowiedzi, których
  terminal nie udziela.
