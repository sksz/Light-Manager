# Krok 12 — Podgląd miniatur obrazów

## Status

Ukończony

## Zależności

Krok 11.

## Model i wysiłek

Sonnet / medium — użycie już oswojonego w projekcie Imagick do prostej
operacji (resize), głównie obsługa błędów i integracja z layoutem.

## Cel

Zamknąć zakres MVP: gdy zaznaczony wpis to obsługiwany plik graficzny,
wygenerować i wyświetlić jego miniaturę jako część tej samej klatki Imagick.

## Zakres

- Wykrywanie, czy zaznaczony plik jest obrazem (po rozszerzeniu i/lub
  próbie odczytu przez Imagick).
- Generowanie miniatury (`thumbnailImage`/`resizeImage`) do rozmiaru
  dopasowanego do dostępnego miejsca w layoucie klatki.
- Komponowanie miniatury do głównego obrazu klatki (ten sam canvas co
  lista plików — dalej jeden spójny obraz → jeden sixel na klatkę).
- Obsługa błędów: uszkodzony plik, nieobsługiwany format, zbyt duży plik
  (limit rozmiaru/rozdzielczości wejściowej) — czytelny placeholder zamiast
  awarii.
- Zachowanie dla trybu fallback (tekstowego): np. sam napis z
  nazwą/rozmiarem obrazu zamiast bitmapy, skoro fallback z definicji nie
  pokazuje grafiki.

## Poza zakresem tego kroku

Podgląd innych typów plików (tekst itp.) — poza MVP.

## Kryteria ukończenia

- Zaznaczenie pliku graficznego pokazuje jego miniaturę w klatce w
  rozsądnym czasie (bez zauważalnego zawieszenia UI).
- Uszkodzone/nieobsługiwane pliki graficzne nie powodują awarii aplikacji,
  tylko czytelny komunikat/placeholder.
- To domyka zakres MVP („Minimalny”) ustalony w [00-decyzje.md](../00-decyzje.md)
  (D1).

## Specyfikacja zrealizowana

Miniatura pojawia się w **pasie u dołu klatki**, wysokim na 8 wierszy, i
podąża za zaznaczeniem — bez naciskania czegokolwiek. Pas leży między listą
a stopką: kreska oddzielająca, pod nią miniatura wyrównana do lewej, obok niej
podpis z wymiarami i formatem. W oknie niższym niż 16 wierszy pas znika w
całości i lista odzyskuje jego miejsce.

Rozpoznawanie idzie dwustopniowo: rozszerzenie (czysta decyzja, bez dotykania
dysku) odsiewa pliki, które obrazem nie są, a dopiero to, co przeszło, trafia
do `Imagick::pingImage` po wymiary i format. Limity — 32 MB pliku i 50 Mpx
rozdzielczości — są sprawdzane przed dekodowaniem; po przekroczeniu albo przy
błędzie odczytu zostaje pusta ramka z powodem w środku. W trybie tekstowym pas
to kreska i wiersz podpisu, bo fallback z definicji nie pokazuje bitmap.

## Kryteria ukończenia — stan

- **Miniatura w rozsądnym czasie.** Zmierzone na klatce 800×480 px, siatka
  100×30, zdjęcie 4000×3000 JPEG (1,5 MB): klatka bez pasa 50 ms (mediana),
  pas z samą ramką 56 ms, pas z miniaturą 72 ms. Pierwsze wejście na zdjęcie —
  czyli jedyny moment, w którym plik jest dekodowany — to 102 ms, tyle co
  zwykła klatka. Bez podpowiedzi `jpeg:size` dla dekodera było 387 ms, co dało
  się zauważyć jako zacięcie.
- **Uszkodzone i nieobsługiwane pliki nie wywracają aplikacji.** Sprawdzone na
  realnym terminalu: plik `uszkodzony.png` z treścią tekstową daje ramkę
  z napisem „Nie udało się odczytać obrazu.”, aplikacja rysuje dalej.
- **Zakres MVP domknięty** — nawigacja, lista, podgląd miniatur i wyjście.

## Dziennik realizacji

**2026-08-08.** Zrealizowany w całości. Rozstrzygnięcia przed kodowaniem
(pytania do użytkownika, pełny zapis w [00-decyzje.md](../00-decyzje.md), D24):
pas u dołu zamiast panelu bocznego i okienka modalnego; port w `Application`
plus obiekt wartości w `Domain` zamiast wykrywania w rendererze; rozszerzenie
jako filtr wstępny przed pingiem; ramka z powodem zamiast cichego pominięcia.

Powstało: `Domain/ValueObject/Preview`, `Domain/Exception/InvalidPreviewException`,
`Application/Dto/ImageMetadata`, `Application/Port/ImagePreviewPort`,
`Application/UseCase/PreviewSelectedEntryUseCase`,
`Infrastructure/Imagick/ImagePreviewService`,
`Infrastructure/Imagick/ThumbnailService`. Zmienione: `Frame` (niesie
`?Preview`), `RenderCurrentFrameUseCase` (rezerwuje wiersze pasa),
`SixelFrameEncoder` (rysowanie pasa, paleta), `TextFrameRenderer` (kreska
i podpis), `Bootstrap`.

Odstępstwa od planu i rzeczy, których plan nie przewidywał:

- **Paleta zależna od treści klatki.** 16 kolorów ustalone w kroku 08 robi ze
  zdjęcia plakat. Klatka z bitmapą dostaje 256 kolorów, pozostałe zostają przy
  16 — koszt płacony tylko wtedy, gdy w pasie naprawdę leży obraz. Blob rośnie
  z 16 kB do 44 kB.
  **Skorygowane 2026-08-09 (D37)** — zobacz dopisek na końcu dziennika: sama
  zasada „więcej kolorów, gdy w klatce leży obraz” obowiązuje, ale sposób ich
  dobierania był błędny.
- **Dwie osobne pamięci podręczne.** Wynik pinga jest pamiętany w warstwie
  aplikacji (klucz: ścieżka, rozmiar pliku i wysokość pasa), a przeskalowana
  bitmapa w `ThumbnailService` (klucz dodatkowo z czasem modyfikacji). Bez nich
  pętla rysująca 20 razy na sekundę otwierałaby i dekodowała plik na każdą
  klatkę.
- **Podpowiedź `jpeg:size` przed odczytem** — dekoder JPEG rozpakowuje obraz od
  razu w zmniejszonej skali. To ta zmiana zrobiła różnicę 387 ms → 102 ms.
- **Pliki otwierane jako uchwyt, nie po nazwie.** Dla ImageMagicka nazwa bywa
  poleceniem (prefiks kodera, selektor klatki w nawiasach), a nazwy plików
  w cudzym katalogu nie są pod naszą kontrolą.
- **Wysokość pasa (8 wierszy) i próg jego zniknięcia (16 wierszy okna)** nie
  były przedmiotem pytania — wybrane przy implementacji, obie wartości to
  stałe w `RenderCurrentFrameUseCase`.
- **Pas bez zaznaczonego obrazu jest niewidoczny** — wiersze są zarezerwowane,
  żeby lista nie skakała, ale kreska rysuje się tylko wtedy, gdy jest co
  podpisać.

Weryfikacja na XTermie z Sixelem obejmowała cztery przypadki: PNG 1200×800,
JPEG 4000×3000, obraz pionowy 600×1400 (proporcje zachowane) i plik
`uszkodzony.png`. Testy: 261 przechodzi, PHPStan `max` bez błędów,
PHP-CS-Fixer bez zmian.

**2026-08-09 (poprawka po zgłoszeniu błędu, [00-decyzje.md](../00-decyzje.md),
D37).** Najechanie kursorem na plik graficzny zmieniało kolory całej aplikacji.
Przyczyną było rozwiązanie przyjęte właśnie w tym kroku: „klatka z bitmapą
dostaje 256 kolorów” znaczyło **kwantyzację adaptacyjną całego płótna**, czyli
paletę liczoną z zawartości zdjęcia. Barwy zdjęcia decydowały więc o tym, jakie
kolory dostanie interfejs — akcent Grafitu wychodził na `#b15f0d` zamiast
`#d9a441`, a tło na `#020203` zamiast `#16181c`.

Wada była obecna od pierwszego dnia tego kroku, ale zobaczyć ją dało się dopiero
po kroku 13, który dał aplikacji motyw wart oglądania, i po kroku 17, który tę
samą pułapkę usunął z klatek bez bitmapy — od tego momentu ścieżka podglądu
odstawała widocznie od reszty.

Sama zasada „więcej kolorów, gdy w klatce leży obraz” zostaje. Zmienia się to,
skąd te kolory pochodzą: paleta jest dziś **hybrydowa** — wpisy motywu wchodzą
do niej bez zmiany i żaden nie ustępuje kolorowi zdjęcia, a resztę sufitu 256
wpisów wypełniają barwy policzone z samej miniatury. Zdjęcie jest kwantyzowane
w `ThumbnailService`, zanim trafi na płótno, więc koszt płacony jest raz na
najechany plik, a nie raz na klatkę; przy domyślnych ustawieniach (Grafit,
`paletteColors` 64) zostaje mu 202 barwy. Powstał obiekt wartości
`Infrastructure/Imagick/Thumbnail`
(obraz plus jego kolory), a `ThumbnailService::thumbnail()` przyjmuje sufit
kolorów i zwraca go zamiast gołego `Imagick`.

Skutek uboczny na plus: klatka z podglądem staniała z 60,0 do 26,8 ms, czyli
zmieściła się w budżecie taktu 30 kl./s (rozliczenie w kroku 17). Limity 32 MB
i 50 Mpx, dwustopniowe rozpoznawanie, obie pamięci podręczne i zachowanie przy
błędzie odczytu — bez zmian.
