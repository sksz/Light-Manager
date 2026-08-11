# Krok 29 — Widok tekstu jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26. Wybrany przez użytkownika jako trzeci z tych, których odbiorca już siedzi
> w kodzie (D48). Zamyka zarazem pozycję „podgląd plików tekstowych” z listy
> „poza MVP”, otwartą od pierwszej iteracji planu.

## Status

**Nie rozpoczęty** (2026-08-11).

## Cel

Pokazać treść pliku tekstowego tam, gdzie dziś stoi napis **„(brak podglądu)”**.

Miarą powodzenia jest zdanie: **prawy panel opisu pliku pokazuje pierwsze wiersze
zaznaczonego pliku tekstowego, da się go przewinąć, a plik o wielkości pół
gigabajta nie zatrzymuje ani jednej klatki.**

## Zależności

- **Krok 18** (komponenty) — `ComponentInterface` i `ScrollWindow`.
- **Krok 25** (pełny obraz pliku) — tam powstał `PreviewPane`, czyli odbiorca,
  i tam zapadła decyzja o dwóch panelach opisu.
- **D46** (praca kawałkowa) twardo — czytanie pliku w klatce to dokładnie ta
  klasa robót, którą tamten wzorzec obejmuje. Krok **nie powtarza** mechanizmu
  z kroku 26: czytanie nagłówka pliku jest odczytem własnym, nie procesem.
- **Krok 12** (podgląd miniatur) — stamtąd pochodzi zasada, że podgląd liczy się
  leniwie, przy rysowaniu, a nie przy zmianie zaznaczenia.

## Model i wysiłek

**Opus / high.**

Trudność nie leży w rysowaniu, tylko w **wejściu-wyjściu i w kodowaniu znaków**.
Plik tekstowy to napis o nieznanej długości, nieznanym kodowaniu i nieznanej
zawartości — potrafi mieć wiersz o długości miliona znaków, bajty spoza UTF-8,
znaki sterujące i tabulatory, których szerokość zależy od tego, gdzie stoją.
Każda z tych rzeczy ma prostą odpowiedź i każda źle odpowiedziana psuje klatkę
albo ją zatrzymuje.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Module/FileInfo/Presentation/Component/PreviewPane` | Miniatura albo `Label` z kluczem `preview.none` — **miejsce, w które wchodzi ten krok** |
| `Presentation/Ui/Component/Label` | Jeden napis, bez zawijania |
| `Presentation/Ui/Component/ListView` | Wiersze bez zawijania, z suwakiem i paskiem zaznaczenia |
| `Presentation/Ui/ScrollWindow` | Wycinek listy wraz z `useContext()` — **wystarcza, czwarta klasa stanu nie powstaje** |
| `Module/FileInfo/Application/UseCase/PreviewEntryUseCase` | Rozstrzyga, czy plik nadaje się na podgląd, i pilnuje limitu rozmiaru |

## Zakres

### 1. Komponent

```php
final class TextView implements ComponentInterface
{
    /** @param list<string> $lines wiersze **już** przygotowane do pokazania */
    public function __construct(
        array $lines,
        int $offset = 0,
        bool $wrap = true,
        ?ScrollPosition $scroll = null,
    ) {}
}
```

Komponent dostaje **gotowe wiersze**, a nie ścieżkę — i to jest cała jego
granica: nie czyta, nie dekoduje, nie zna pliku. Wszystko, co ma z wejściem-wyjściem
wspólnego, zostaje po stronie modułu, bo tam mieszka wiedza o tym, co wolno
przeczytać i jak długo.

### 2. Zawijanie

Wiersz dłuższy od panelu zawija się na kolejne wiersze siatki albo zostaje
przycięty — wedle przełącznika. Zawijanie **łamie po znaku, nie po słowie**,
i to jest decyzja: podgląd kodu ma pokazywać wcięcia i strukturę wiersza, a nie
akapit prozy. Do rozstrzygnięcia na starcie, czy wyjątkiem nie powinien być
podgląd pliku bez rozszerzenia kodu.

### 3. Odczyt: to, co widać, i ani bajta więcej

- Czytamy **nagłówek pliku**, a nie plik: tyle wierszy, ile panel pomieści,
  plus zapas na przewinięcie.
- Plik przewijany w dół dobiera kolejne kawałki — **na klatkę po kawałku**, wedle
  D46.
- Bajty spoza kodowania i znaki sterujące zamieniają się w znak zastępczy;
  tabulator na ustaloną liczbę spacji.
- Plik binarny nie trafia tu wcale — rozstrzyga o tym moduł, tak jak dziś
  rozstrzyga o miniaturze.

### 4. Odbiorca: prawy panel opisu pliku

`PreviewPane` zyskuje trzecią odpowiedź: obraz, **tekst**, albo powód, dla którego
nie ma ani jednego. Wybór należy do modułu i idzie tą samą drogą, co dziś wybór
między miniaturą a napisem.

### 5. Pomiar

Osobny scenariusz `text-view` w `bin/render-bench`: panel wypełniony wierszami
kodu o zmiennej długości. Różnica wobec `chrome-text` jest ceną podglądu tekstu,
a osobny scenariusz jest tu potrzebny z konkretnego powodu — **wiersze podglądu
zmieniają się przy każdym przewinięciu**, więc pamięć podręczna wierszy (D34)
trafia w nie rzadziej niż w listę plików.

## Poza zakresem

- **Kolorowanie składni** — wymaga słownika języków i rozbioru treści; to osobny
  krok, jeśli w ogóle.
- **Edycja** — podgląd jest do czytania.
- **Wyszukiwanie w treści** — czeka na krok 30, który wnosi podświetlanie.
- **Numery wierszy** — do rozstrzygnięcia, ale domyślnie ich nie ma: w panelu
  o połowie szerokości okna kosztują kolumny, których nie ma skąd wziąć.
- **Zwijanie długich wierszy w „…”** — przycinanie tak, zwijanie z wielokropkiem
  w środku nie.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Component/TextView.php` | Presentation | Nowy — wiersze, zawijanie, wycinek, suwak. |
| `Module/FileInfo/Application/Port/TextPreviewPort.php` | Moduł | Nowy — odczyt nagłówka pliku po kawałku. |
| `Module/FileInfo/Infrastructure/TextPreviewService.php` | Moduł | Nowy — odczyt, kodowanie, znaki sterujące. |
| `Module/FileInfo/Presentation/Component/PreviewPane.php` | Moduł | Trzecia odpowiedź: tekst. |
| `Module/FileInfo/Application/FileInfoSettings.php` | Moduł | Przełącznik podglądu tekstu i limit wierszy. |
| `Infrastructure/Diagnostics/**` | Infrastructure | Scenariusz `text-view`. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Podgląd tekstu i granica „komponent nie czyta”. |
| testy | Testy | Zawijanie, przycinanie, tabulatory, bajt spoza kodowania, pusty plik, plik o jednym długim wierszu, przewijanie. |

## Do rozstrzygnięcia na starcie kroku

1. **Zawijanie czy przycinanie jako domyślne** — i czy to ustawienie modułu.
2. **Ile wierszy czytamy z zapasem** i czy przewijanie sięga poza ten zapas.
3. **Co z plikiem bez końca wiersza** — jeden wiersz o milionie znaków jest
   przypadkiem realnym (zrzut JSON), a nie złośliwym.
4. **Kodowanie**: zakładamy UTF-8 i zastępujemy, czy próbujemy rozpoznać.
5. **Kto rozstrzyga, że plik jest tekstowy** — rozszerzenie, wynik polecenia
   `file` (moduł już je ma!) czy podejrzenie pierwszych bajtów.
6. **Czy numery wierszy w ogóle są.**

## Kryteria ukończenia

- Prawy panel opisu pokazuje treść pliku tekstowego zamiast „(brak podglądu)”.
- Plik o wielkości pół gigabajta **nie zatrzymuje klatki** — sprawdza to test,
  nie wrażenie.
- Bajt spoza kodowania nie psuje klatki ani nie wywraca aplikacji.
- Czwarta klasa stanu między klatkami **nie powstała** — `ScrollWindow` wystarczył.
- Scenariusz `text-view` rozlicza koszt podglądu liczbą.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
