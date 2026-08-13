# Krok 30 — Filtrowanie listy i podświetlenie dopasowania

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26 (D48). Zamyka pozycję „wyszukiwanie / filtrowanie” z listy „poza MVP”.
>
> **To jest krok, który otwiera zamknięty słownik prymitywów** — pierwszy raz od
> kroku 18, i na jawne rozstrzygnięcie użytkownika (D48).

## Status

**Ukończony** (2026-08-12).

## Cel

Pozwolić zawęzić listę wpisów wpisanym fragmentem nazwy — i **pokazać, która
część nazwy pasuje**.

Miarą powodzenia jest zdanie: **wpisanie trzech liter zawęża listę w tej samej
klatce, dopasowany fragment jest widoczny na pierwszy rzut oka, a wyjście
z filtra przywraca listę wraz z zaznaczeniem, które było przed nim.**

## Zależności

- **Krok 27** (wiersz wielokolumnowy) twardo — podświetlenie jest własnością
  **wiersza**, a wiersz zmienia tam kształt. Robienie jednego przed drugim
  znaczyłoby przepisywanie tej samej klasy dwa razy.
- **Krok 19** (okno komend) — `TextInput` wraz z karetką i obsługą `Ctrl` to
  gotowe pole filtra; stamtąd pochodzi też reguła „okno zużywa albo przepuszcza
  klawisz”.
- **Krok 18** (komponenty i płaszczyzny) — bo tam słownik prymitywów został
  **zamknięty**, i to jego regułę ten krok świadomie łamie.
- **Krok 7 i 8** (oba renderery) twardo — nowy prymityw jest obowiązkiem dla
  `SixelFrameEncoder` **i** dla `TextFrameRenderer`. Renderer tekstowy musi umieć
  zdegradować to, czego nie potrafi narysować, i to jest cena, którą płaci ten
  krok.
- **Krok 21** (przeglądarka jako moduł) — filtrowanie jest funkcją modułu; rdzeń
  dostaje wyłącznie sposób pokazania dopasowania.

## Model i wysiłek

**Opus / xhigh.**

Wysiłek wyższy niż w pozostałych krokach tej fazy i ma to jeden powód: **krok
dotyka słownika, który do tej pory był nienaruszalny**. Prymityw przechodzi przez
port renderowania, więc obowiązuje oba renderery naraz, wchodzi do podpisu
płaszczyzny (`signature()`), a przez to do wszystkich pamięci podręcznych z D34.
Źle zaprojektowany podpis nie objawia się błędem, tylko klatką, która nie chce
się odświeżyć — albo pamięcią, która nigdy nie trafia.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Ui/Primitive/*` | Siedem kształtów: `TextRun`, `Bar`, `RoundRect`, `CornerBrackets`, `Scrollbar`, `Bitmap` plus `Weight`. **Zamknięte od kroku 18** |
| `Infrastructure/Imagick/SixelFrameEncoder` | Rozbiera prymitywy na rysowanie Imagickiem; pamięć podręczna wierszy z D34 |
| `Infrastructure/Rendering/TextFrameRenderer` | Tryb zapasowy — rozbiera **te same** prymitywy na komórki znakowe |
| `Presentation/Ui/Component/TextInput` | Pole tekstowe z karetką (krok 19) — gotowe pole filtra |
| `Presentation/Ui/Component/Highlight` | **Sprawdzić na starcie, co dokładnie robi** — nazwa sugeruje, że część pracy jest zrobiona |
| `Module/Browser/**` | Lista wpisów, `ScrollWindow`, zaznaczenie po indeksie — filtrowanie zmienia indeksy, więc zaznaczenie wymaga uwagi |

## Zakres

### 1. Prymityw podświetlenia

Kształt do zaprojektowania na starcie kroku; wyjściowa propozycja jest taka, żeby
**nie był nowym rodzajem napisu, tylko tłem pod jego fragmentem** — wtedy
`TextRun` zostaje nietknięty, a renderer tekstowy degraduje go do zmiany atrybutu
komórki, nie do zmiany treści.

Trzy rzeczy, których nowy prymityw musi dotrzymać:

1. **`signature()` obejmuje wszystko, co wpływa na piksele** — inaczej płaszczyzna
   z innym dopasowaniem podałaby się z pamięci jako ta sama;
2. **oba renderery umieją go narysować** — sixelowy naprawdę, tekstowy choćby
   przez odwrócenie atrybutów komórki;
3. **brak podświetlenia nie kosztuje nic** — wiersz bez dopasowania ma oddawać
   dokładnie te prymitywy, co dziś, żeby cena dotyczyła wyłącznie filtrowania.

### 2. Dopasowanie jako dana

Wiersz niesie **zakresy dopasowania** (przesunięcie i długość w znakach), a nie
gotowy podział na kawałki. Powód: przesunięcie liczy się w znakach, a rysuje
w kolumnach — i tylko komponent wie, w której kolumnie stoi napis po rozdziale
szerokości z kroku 27.

### 3. Filtrowanie w przeglądarce

- Klawisz otwiera **pole filtra** (`TextInput`) — do rozstrzygnięcia, czy jako
  okno nakładane, czy jako wiersz w panelu.
- Lista zawęża się **w tej samej klatce** — filtrowanie tablicy wpisów jest
  tanie i nie podlega D46.
- Dopasowanie: podciąg bez rozróżniania wielkości liter. Wzorce i wyrażenia
  regularne — poza zakresem.
- **Zaznaczenie przeżywa filtr**: wejście w filtr, zawężenie i wyjście mają
  wrócić do wpisu, który był zaznaczony, jeśli nadal istnieje.
- Filtr znika przy zmianie katalogu.

### 4. Pomiar

Osobny scenariusz `highlight`: pełna klatka listy, w której **każdy wiersz ma
dopasowanie**. To jest przypadek najgorszy z możliwych i właśnie dlatego jest
właściwy — pokazuje sufit ceny, a nie jej wartość typową. Rozliczyć trzeba dwie
liczby, nie jedną: klatkę z dopasowaniami **i** klatkę listy bez filtra, bo ta
druga nie ma prawa zdrożeć ani o milisekundę.

## Poza zakresem

- **Wyrażenia regularne i wzorce** — podciąg wystarcza; reszta to osobna decyzja.
- **Wyszukiwanie rekurencyjne po drzewie** — to zupełnie inna funkcja niż
  zawężanie widocznej listy, mimo podobnego odczucia.
- **Podświetlanie w podglądzie tekstu** (krok 29) — prymityw powstanie tak, żeby
  było możliwe, ale odbiorcą tego kroku jest lista.
- **Zapamiętywanie ostatniego filtra** między katalogami.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Ui/Primitive/*.php` | Application | **Nowy prymityw** — ósmy w słowniku. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | Rysowanie nowego prymitywu. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Infrastructure | Degradacja nowego prymitywu do atrybutu komórki. |
| `Presentation/Ui/Component/Table.php` (albo `ListRow`) | Presentation | Zakresy dopasowania w wierszu. |
| `Module/Browser/**` | Moduł | Pole filtra, zawężanie listy, zaznaczenie przeżywające filtr. |
| `Infrastructure/Diagnostics/**` | Infrastructure | Scenariusz `highlight`. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | **Ósmy prymityw i powód, dla którego słownik został otwarty.** |
| testy | Testy | Dopasowanie na początku, w środku i na końcu, wiele dopasowań w wierszu, brak dopasowania, znaki spoza ASCII, zaznaczenie po wyjściu z filtra, degradacja w rendererze tekstowym. |

## Do rozstrzygnięcia na starcie kroku

1. **Kształt nowego prymitywu** — tło pod fragmentem, wariant `TextRun`, czy coś
   trzeciego. Rozstrzygnięcie zapada **z oboma rendererami na stole**, bo oba je
   wykonują.
2. **Co robi `Presentation/Ui/Component/Highlight`, który już istnieje** — czy to
   jest fundament, czy zbieżność nazw.
3. **Pole filtra jako okno nakładane czy wiersz w panelu.**
4. **Czy filtr obowiązuje oba panele podziału naraz**, czy tylko czynny.
5. **Znaki spoza ASCII w dopasowaniu** — przesunięcie w bajtach czy w znakach
   (odpowiedź jest oczywista, ale musi paść przed pierwszą linią kodu).

## Kryteria ukończenia

- Wpisanie fragmentu zawęża listę w tej samej klatce, a dopasowanie widać.
- **Klatka listy bez filtra nie zdrożała** — sprawdza to pomiar, i to jest
  najważniejsze kryterium tego kroku.
- Oba renderery obsługują nowy prymityw; tekstowy przynajmniej przez degradację.
- Zaznaczenie przeżywa wejście i wyjście z filtra.
- Słownik prymitywów urósł o **jeden** kształt, opisany w `docs/architecture.md`
  wraz z powodem.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-12 — krok wykonany

**Rozstrzygnięcia ze startu kroku** (pełne uzasadnienia:
[00-decyzje.md](00-decyzje.md), D59):

1. **Kształt prymitywu — napis na własnym tle** (`Application/Ui/Primitive/TextMark`).
   Wyjściowa propozycja planu („samo tło pod fragmentem”) **nie przeszła
   przeglądu stanu zastanego**: prostokąt wypełniony rolą motywu jest w słowniku
   dwa razy — `Bar` z `Weight::Fill` i `RoundRect` bez obrysu — a karetka
   `TextInput` udaje nim podświetlenie fragmentu od kroku 19. Ósmy prymityw
   w tej postaci byłby synonimem siódmego. Użytkownik wybrał kształt, którego
   naprawdę nie ma: pismo związane z tłem w jednej rzeczy.
2. **`Presentation/Ui/Component/Highlight` to zbieżność nazw** — pasek pod
   zaznaczonym wierszem (`RoundRect` + `Bar`), używany przez `ListView`, `Table`
   i karetkę. Nie fundament, za to **precedens**: to on pokazał, że tło pod
   fragmentem tekstu daje się złożyć z istniejących kształtów.
3. **Pole filtra to okno nakładane** przy dolnej krawędzi, jak okno komend.
4. **Filtr dotyczy panelu z ogniskiem**, osobny dla każdego panelu.
5. **Przesunięcie w znakach, nie w bajtach** (`mb_stripos`, `mb_substr`).

Ponadto, poza listą planu: **`Enter` zatwierdza, `Esc` odmawia** — bo zdanie
planu „zaznaczenie przeżywa wejście i wyjście z filtra” znaczy dwie sprzeczne
rzeczy naraz. Rozdzielone wzorem okna potwierdzenia (D56).

**Co powstało:**

| Plik | Zmiana |
|---|---|
| `Application/Ui/Primitive/TextMark.php` | **Ósmy prymityw** — fragment, rola pisma, rola tła. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | `drawTextMark()` — jedna zapamiętana bitmapa (tło + litery), jeden `compositeImage`. |
| `Infrastructure/Rendering/TextFrameRenderer.php` | Degradacja do **dwóch atrybutów komórki**: tła i koloru pisma. |
| `Infrastructure/Rendering/OpenGlFrameRenderer.php` | Prostokąt i napis — trzeci tłumacz (krok 35 był już ukończony). |
| `Presentation/Ui/Component/TextSpan.php` | Zakres w znakach wraz z szukaniem wystąpień i przycinaniem. |
| `Presentation/Ui/Component/TableRow.php` | Pole `$marks` — zakresy wedle numeru kolumny, pusto domyślnie. |
| `Presentation/Ui/Component/Table.php` | Podświetlenia komórki, przycięte do treści **zachowanej** (nie do wielokropka). |
| `Module/Browser/Domain/ValueObject/NameFilter.php` | Reguła dopasowania: podciąg bez rozróżniania wielkości liter. |
| `Module/Browser/Presentation/BrowserState.php` | Dwa katalogi w panelu: z dysku i widoczny; zaznaczenie przenoszone **po nazwie**. |
| `Module/Browser/Presentation/Overlay/FilterOverlay.php` | Pole filtra; strzałki pionowe zużywa i oddaje liście pod spodem. |
| `Module/Browser/Presentation/BrowserScreen.php` | Klawisz `/`, `Esc` zdejmujący filtr, znacznik w pasie ścieżki. |
| `Module/Browser/Presentation/Component/EntryList.php` | Zakresy dopasowania w kolumnie nazwy; „nic nie pasuje” zamiast „katalog pusty”. |
| `Infrastructure/Diagnostics/**` | Scenariusz `highlight`. |

**Odstępstwa od planu — trzy, wszystkie na plus:**

- **Renderer tekstowy nie musiał niczego stracić.** Plan zakładał degradację
  „choćby przez odwrócenie atrybutów komórki”. Tło i kolor pisma to dokładnie te
  dwa atrybuty, które komórka ma, więc dopasowanie widać w trybie zapasowym co do
  znaku tak samo, jak w graficznym. To pierwszy kształt od kroku 18, którego tryb
  tekstowy **nie degraduje z ubytkiem**.
- **Renderery były trzy, nie dwa.** Plan pisano przed krokiem 35; jego uwaga
  z `00-index.md` sprawdziła się co do słowa, a `PrimitiveTranslationTableTest`
  wymusił wpis w rendererze okienkowym, zanim zdążyłem o nim pomyśleć.
- **Naprawa przy okazji:** porównanie klawisza `.` w `BrowserScreen` nie patrzyło
  na modyfikatory, więc `Ctrl`+`.` i `Alt`+`.` przełączały wpisy ukryte (reguła
  11j). Poprawione przy dokładaniu `/`.

**Pomiar** (`bin/render-bench`, maszyna zwolniona na prośbę, dwa przebiegi;
wzorzec: `docs/pomiary/2026-08-12-po-kroku-30.json`):

| Scenariusz | Wzorzec (po kroku 29) | Przebieg I | Przebieg II |
|---|---|---|---|
| `columns` — lista bez filtra | 20,5 ms | 20,7 ms (+0,8%) | 20,9 ms |
| `highlight` — dopasowanie w **każdym** wierszu | — | 21,3 ms | 20,7 ms |

**Główne kryterium kroku jest spełnione: lista bez filtra nie zdrożała** —
+0,8% mieści się w rozrzucie pojedynczego przebiegu (20,0–22,4 ms), a narzędzie
nie zgłosiło regresji w żadnym z szesnastu scenariuszy. Odpowiedź jest zresztą
wcześniejsza niż pomiar i strukturalna: wiersz bez zakresów oddaje **te same
podpisy prymitywów**, co przed krokiem, i pilnuje tego osobny test.

Cena samego podświetlenia okazała się **poniżej rozdzielczości pomiaru**: raz
wyszła o 0,6 ms wyżej od `columns`, raz o 0,2 ms niżej, przy rozrzucie rzędu
2 ms. Czterdzieści cztery dopasowania na klatkę to czterdzieści cztery
`compositeImage` z **jednego** wpisu pamięci podręcznej — bo filtr podświetla
wszędzie ten sam napis. Blob urósł o 0,4 kB (40,1 → 40,5 kB).

**Sprawdzone w prawdziwym XTermie** (`bin/run.sh`, podział na dwa panele,
filtr `in` w `src/`): zawęża się **tylko panel z ogniskiem**, dopasowanie widać
w obu wielkościach liter (`Doma`**`in`**`/` i **`In`**`frastructure/`),
podświetlenie jest czytelne także **na pasku zaznaczenia**, a po `Enter` pole
znika, zawężenie zostaje i widać je znacznikiem `• filtr: in` w pasie ścieżki.

**Weryfikacja:** PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, 1227 testów
zielonych (49 nowych: `TextSpanTest`, `TextFrameRendererTest`, `BrowserFilterTest`
oraz dopisane przypadki w `TableTest` i `SixelFrameEncoderTest`).

### 2026-08-12 (po kroku) — filtr odsłonił dwie usterki spoza swojego zakresu

Sprawdzanie poprawek zawijania (dziennik kroku 29) wprowadziło aplikację w stan,
którego wcześniej dawało się dosięgnąć wyłącznie w pustym katalogu: **lista
zawężona tak, że nie pasuje nic**. Filtr jest drugą, znacznie łatwiejszą drogą do
„nie ma zaznaczenia”, i obie usterki, które przy tym wyszły, są starsze od tego
kroku.

1. **Zdanie „(nie zaznaczono wpisu)” siadało na obwódce.** `FileInfoScreen::draw()`
   przy braku opisu wracało z napisem położonym na **surowym prostokącie ekranu**,
   pomijając to, że przy dwóch panelach oprawę rysuje sam ekran (`ownFrame()`),
   a pierwszy wiersz prostokąta jest kreską. Napis nakładał się na etykietę
   „Opis pliku”. Naprawione: zdanie liczy geometrię tak samo, jak treść.
2. **Panel bez wyników gubił nagłówek kolumn.** `EntryList` zwracał samo zdanie
   zamiast tabeli, więc lewa lista traciła wiersz „Nazwa · Rozmiar · Zmieniony ·
   Prawa”, a przy podziale obie listy przestawały się zgadzać w pionie. Pusto
   znaczy „nie ma wierszy”, a nie „nie ma kolumn”: nagłówek zostaje, a zdanie
   staje pod nim. Dotyczy to tak samo pustego katalogu, więc poprawka jest
   starsza niż filtr — filtr ją tylko upowszechnił.

Obie mają odtąd test i obie sprawdzone w prawdziwym XTermie. **Filtr nie znika po
powrocie z modułu opisu pliku** i to nie jest usterka, tylko rozstrzygnięcie D59:
zawężenie przeżywa zamknięcie pola, widać je znacznikiem `• filtr: …` w pasie
ścieżki, a zdejmuje je `Esc` na liście albo zmiana katalogu.
