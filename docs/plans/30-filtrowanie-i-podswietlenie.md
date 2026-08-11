# Krok 30 — Filtrowanie listy i podświetlenie dopasowania

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26 (D48). Zamyka pozycję „wyszukiwanie / filtrowanie” z listy „poza MVP”.
>
> **To jest krok, który otwiera zamknięty słownik prymitywów** — pierwszy raz od
> kroku 18, i na jawne rozstrzygnięcie użytkownika (D48).

## Status

**Nie rozpoczęty** (2026-08-11).

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

*(pusty — krok nierozpoczęty)*
