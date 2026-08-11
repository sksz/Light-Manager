# Krok 27 — Wiersz wielokolumnowy jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, po ukończeniu kroku 26, przy przeglądzie
> braków rdzenia. Wybrany przez użytkownika z sześciu propozycji jako **pierwszy
> z trzech, których odbiorca już siedzi w kodzie** (D48).

## Status

**Ukończony** (2026-08-11).

## Cel

Zdjąć z listy sufit dwóch pól. `ListRow` niesie dziś dokładnie `left` i `right`;
wszystko, co ma stanąć w trzeciej kolumnie, trzeba dopychać spacjami w komponencie
modułu — czyli liczyć układ tam, gdzie się go nie liczy.

Miarą powodzenia jest zdanie: **lista plików pokazuje nazwę, rozmiar, datę
i prawa, w wąskim oknie gubi kolumny w ustalonej kolejności, a klatka nie
drożeje bardziej, niż wynika z liczby napisów.**

## Zależności

- **Krok 18** (komponenty i płaszczyzny) — stamtąd pochodzi `ComponentInterface`,
  `ListView`, `ListRow` i zasada, że komponent oddaje prymitywy z ról motywu
  i prostokątów w siatce znakowej.
- **Krok 17** (optymalizacja) twardo — wiersz o czterech napisach zamiast dwóch
  wchodzi wprost do pamięci podręcznej wierszy (D34), a jej klucz jest zbudowany
  z treści wiersza. Krok bez pomiaru „przed i po” byłby zakładem.
- **Krok 21** (przeglądarka jako moduł) — bo tam mieszka odbiorca.
- **Krok 24** (podział ekranu) — kolumny w panelu o połowie szerokości okna są
  właściwym sprawdzianem reguły ustępowania, a nie przypadkiem brzegowym.

## Model i wysiłek

**Opus / high.**

Krok jest większy, niż wygląda, i cała trudność leży w jednym miejscu:
**rozdziale szerokości**. Kolumny mają rozmiar żądany, minimalny i kolejność
ustępowania; wiersz ma jedną szerokość; a między jednym a drugim stoi rachunek,
który przy złym zaokrągleniu zostawia kolumnę o szerokości zero albo wychodzi
poza prostokąt. Do tego dochodzi pomiar — to pierwszy krok od 24, który zmienia
**treść każdego wiersza listy plików**, czyli najczęstszą klatkę w aplikacji.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Presentation/Ui/Component/ListRow` | Dwa pola (`left`, `right`) plus rola. Następca enuma `LineStyle`; od kroku 18 bez zmian |
| `Presentation/Ui/Component/ListView` | Rysuje wiersze, pasek zaznaczenia i suwak; wyrównuje `right` do prawej krawędzi |
| `Module/Browser/Presentation/Component/EntryList` | Skleja nazwę z ukośnikiem i rozmiar w dwa pola `ListRow` — **odbiorca tego kroku** |
| `Module/FileInfo/**` | Sekcje opisu też stoją na `ListRow` (etykieta + wartość) — **drugi użytkownik, którego krok nie ma prawa zepsuć** |
| `Presentation/Ui/Container/Slot` | Rozmiar minimalny, preferowany i kolejność ustępowania — **dla wierszy w pionie**. Wzorzec do powtórzenia w poziomie, ale nie klasa do użycia wprost |

## Zakres

### 1. Kolumna jako dana

Kolumna opisuje **czego chce**, a nie ile dostanie:

```php
final readonly class Column
{
    public function __construct(
        public string $text,
        public Align $align = Align::Left,
        public ?int $width = null,      // null — bierze, co zostanie
        public int $minWidth = 3,
        public int $priority = 0,       // wyższy ustępuje pierwszy
        public ?Role $role = null,      // null — rola całego wiersza
    ) {}
}
```

Wzorzec jest ten sam, co w `Container\Slot` z kroku 18, i to jest świadome:
**projekt ma już jedną odpowiedź na pytanie „jak dzielić miejsce”** i druga,
inaczej nazwana, byłaby długiem od pierwszego dnia. Różnica jest jedna i wynika
z osi: `Slot` dzieli wysokość między komponenty, `Column` — szerokość między
napisy w jednym wierszu.

### 2. Rachunek szerokości

Reguła w trzech zdaniach, bo dokładnie tyle jej jest:

1. kolumny o podanej szerokości biorą swoje,
2. reszta dzieli to, co zostało, proporcjonalnie,
3. gdy któraś zeszłaby poniżej `minWidth` — **wypada w całości**, wedle
   `priority`, a zwolnione miejsce wraca do puli.

Kolumna przycięta w połowie słowa jest gorsza od kolumny nieobecnej — to ta sama
zasada, którą pas podglądu kieruje się od kroku 12.

### 3. Zgodność wsteczna, i to jest połowa kroku

`ListRow` ma **dwóch** użytkowników: przeglądarkę i sekcje opisu pliku. Krok nie
ma prawa zmusić drugiego do przepisania się na kolumny — opis pliku to naprawdę
etykieta i wartość, nie tabela. Do rozstrzygnięcia na starcie: czy `ListRow`
zostaje obok `TableRow` jako osobna klasa, czy staje się jego przypadkiem
szczególnym o dwóch kolumnach.

### 4. Odbiorca: lista plików

- `EntryList` przechodzi na kolumny: **nazwa, rozmiar, data zmiany, prawa**.
- Kolejność ustępowania w wąskim oknie — do rozstrzygnięcia, ale kierunek jest
  oczywisty: nazwa nie wypada nigdy.
- Które kolumny widać, rozstrzyga **ustawienie modułu przeglądarki**, a nie kod.

### 5. Pomiar

Osobny scenariusz `columns` w `bin/render-bench`: pełna klatka listy o czterech
kolumnach. Różnica wobec `chrome-text` jest ceną kolumn — i to jest liczba, którą
krok ma podać, zamiast twierdzić, że „to tylko kilka napisów więcej”.

## Poza zakresem

- **Sortowanie po kolumnie** — to funkcja przeglądarki, nie komponentu rdzenia.
- **Zmiana szerokości kolumny myszą albo klawiszem** — rdzeń nie ma wskaźnika,
  a klawiszologia takiego trybu to osobny krok.
- **Nagłówek kolumn jako część `Table`** — nagłówek to wiersz jak każdy inny,
  narysowany rolą `Muted`; komponent nie musi o nim wiedzieć.
- **Przepisanie sekcji `FileInfo` na kolumny** — patrz punkt 3.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Component/Column.php` | Presentation | Nowy — kolumna jako dana. |
| `Presentation/Ui/Component/Table.php` | Presentation | Nowy — rachunek szerokości i rysowanie wiersza. |
| `Application/Ui/Align.php` | Application | Nowy albo istniejący — wyrównanie przechodzi przez port, więc leży po stronie `Application/Ui`. |
| `Presentation/Ui/Component/ListView.php` | Presentation | Przyjmuje wiersz wielokolumnowy obok dwupolowego. |
| `Module/Browser/**` | Moduł | `EntryList` na kolumnach, ustawienie widocznych kolumn. |
| `Infrastructure/Diagnostics/**` | Infrastructure | Scenariusz `columns`. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Rozdział szerokości jako druga oś reguły `Slot`. |
| testy | Testy | Rachunek szerokości, ustępowanie kolumn, wąskie okno, wyrównanie, zgodność wsteczna `ListRow`. |

## Do rozstrzygnięcia na starcie kroku

1. **`ListRow` zostaje czy staje się przypadkiem szczególnym `TableRow`.**
2. **Kolejność ustępowania kolumn** i czy jest wpisana w kod, czy w ustawienie.
3. **Wyrównanie liczb** — czy rozmiar równa się do prawej sam z siebie, czy
   trzeba to powiedzieć w kolumnie.
4. **Czy nagłówek kolumn w ogóle jest** — nazwy kolumn nad listą kosztują wiersz,
   a w wąskim panelu wiersz to towar deficytowy.
5. **Skąd bierze się szerokość kolumny „data”** — z formatu, który zna moduł,
   czy z pomiaru najdłuższej wartości w widocznym oknie.

## Kryteria ukończenia

- Lista plików pokazuje cztery kolumny, a w wąskim oknie gubi je w ustalonej,
  **przetestowanej** kolejności — nigdy nie przycinając w połowie słowa.
- Żadna kolumna nie wychodzi poza prostokąt i żadna nie ma szerokości zero.
- Sekcje opisu pliku wyglądają **dokładnie tak, jak przed krokiem**.
- Słownik prymitywów nie urósł.
- Scenariusz `columns` rozlicza koszt kolumn liczbą, nie wrażeniem.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Rozstrzygnięcia startu kroku

Wszystkie rozstrzygnął użytkownik 2026-08-11; uzasadnienia w
[00-decyzje.md](00-decyzje.md), D49. Dwa pytania z planu zostały **zastąpione
lepszymi**, bo stan zastany okazał się inny, niż zakładano przy planowaniu.

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | `ListRow` zostaje czy staje się `TableRow` | **`Table` obok `ListView`** — `ListRow` nietknięty |
| 2 | Rachunek szerokości: wspólny czy własny | **Wyprowadzony do `Distribution`** dla obu osi |
| 3 | Skąd dane na kolumny daty i praw | **`Entry` o dwa pola, jedno `stat()`** w repozytorium |
| 4 | Kolejność ustępowania i widoczność kolumn | **W kodzie**: prawa → data → rozmiar; nazwa nigdy. Jeden przełącznik |
| 5 | Nagłówek kolumn | **Przełącznik w ustawieniach modułu**, domyślnie wyłączony |
| 6 | Zapis daty | **`2026-08-11 18:45`** — szesnaście znaków |

Pytania „wyrównanie liczb” i „skąd szerokość kolumny daty” z planu odpadły jako
rozstrzygnięte same przez się: rozmiar równa się do prawej, a szerokość daty
wynika ze stałego zapisu.

## Dziennik realizacji

### 2026-08-11 — kolumny, wspólny rachunek podziału i dwie pułapki

**Stan zastany obalił dwa założenia planu, zanim padła pierwsza linia kodu.**
Pierwsze: `Slot` **już od kroku 18** deklarował w dokumentacji, że jego liczby są
bezwymiarowe — „wiersze w kontenerze pionowym, kolumny w poziomym”. Rachunek
podziału nie był więc do napisania, tylko do wyprowadzenia. Drugie: `Entry`
przeglądarki nie zna daty ani praw, ale `FilesystemDirectoryRepository` **i tak
wołał `is_dir()` i `filesize()`** na każdym wpisie — czyli dwa razy to samo
`stat`. Kolumny szczegółów okazały się przez to darmowe co do wywołań
systemowych.

**Co powstało w rdzeniu.** `Container\Span` (miara: minimum, rozmiar preferowany,
kolejność ustępowania) i `Container\Distribution` (rozdział, jedna reguła na obie
osie). `Slot` zyskał `span`, a `VStack::distribute()` stał się jedną linią wołającą
`Distribution` — **bez zmiany zachowania**, co potwierdziło 131 istniejących
testów `VStack` i `HudLayout` uruchomionych zaraz po wyprowadzeniu. Do tego
`Component\Column` (dana: czego chce kolumna), `Component\TableRow` (dana:
komórki), `Component\Table` (komponent: liczy szerokości raz na klatkę dla
wszystkich wierszy naraz), `Component\Align` oraz `Rect::columnsFrom()` —
bliźniak `rowsFrom()`, którego do tego kroku po prostu nie było czym pociąć.

**Dwie pułapki, obie znalezione przez testy, nie przez oglądanie.**

1. **`Span::fixed()` ma minimum zero**, więc uczestnik o „stałej” mierze kurczy
   się stopniowo. Dla pasa podglądu to jest właściwe — pas niższy o wiersz nadal
   jest pasem. Dla kolumny z datą to błąd: zwężona o trzy znaki nie jest „węższą
   datą”, tylko napisem `2026-08-…`, a przy okazji zabiera te znaki nazwie. Stąd
   **`Span::rigid()` — tyle albo nic** — i dwa sąsiadujące testy pokazujące
   różnicę obok siebie.
2. **Odstęp między kolumnami musi leżeć po prawej stronie komórki**, także przy
   dosunięciu do prawej. Pierwsza wersja dosuwała treść do brzegu kolumny, więc
   rozmiar sklejał się z datą dokładnie wtedy, gdy był najdłuższy — czyli
   w przypadku, w którym najbardziej potrzeba go odróżnić. Złapał to test
   `testNeighbouringCellsNeverTouch`.

Trzecia rzecz nie była pułapką, tylko odkryciem: **minimum kolumny nazwy jest
ważniejsze od szerokości kolumn stałych**. To ono rozstrzyga, kiedy szczegóły
zaczynają ustępować, bo dopóki suma minimów mieści się w prostokącie, nikt nie
ustępuje. Minimum równe czterem dawało układ, w którym nazwa kurczy się do pięciu
znaków, byle data została. Stąd `NAME_MINIMUM = 20` wraz z komentarzem, żeby
następny czytelnik nie wziął tej liczby za dowolną.

**Moduł przeglądarki.** `Entry` o `modifiedAt` i `permissions` (oba
`null`-owalne — wpis, o który nie da się zapytać, naprawdę nie ma daty),
`permissionsAsText()`, repozytorium na jednym `stat()`, `EntryList` na `Table`,
dwa przełączniki w ustawieniach i cztery napisy nagłówków. `stat()`, a nie
`lstat()`, i pilnuje tego osobny test: dowiązanie do katalogu ma się nadal
zachowywać jak katalog.

**Testy.** 41 nowych: `DistributionTest` (10, na czystym rachunku — pierwszy raz
bez rysowania czegokolwiek), `TableTest` (12, na prymitywach),
`EntryColumnsTest` (7, na decyzjach) plus cztery w repozytorium i poprawki
istniejących. Razem **982 zielone**, PHPStan `max` bez błędów, PHP-CS-Fixer bez
uwag.

### Pomiar — ile kosztują kolumny

Przebieg na zwolnionej maszynie (rozstrzygnięcie użytkownika, reguła 17),
15 przebiegów po 3 rozgrzewkowych, konfiguracja odniesienia 1000×600 px / 166×46
/ paleta 64 / motyw grafit. Wzorzec: `docs/pomiary/2026-08-11-po-kroku-27.json`.
**Ani jeden wiersz nie dostał znacznika rozrzutu.**

| Scenariusz | Rysowanie | Kwantyzacja | Kodowanie | Razem | Blob |
|---|---|---|---|---|---|
| ramki z tekstem (2 kolumny, `ListView`) | 5,7 ms | 7,4 ms | 4,4 ms | **17,5 ms** | 23,6 kB |
| lista w kolumnach (4 kolumny, `Table`) | 8,3 ms | 8,3 ms | 4,6 ms | **21,3 ms** | 40,1 kB |

**Kolumny kosztują 3,8 ms na klatkę** (+22%), z czego 2,6 ms to rysowanie, 0,9 ms
kwantyzacja, 0,2 ms kodowanie. Liczba jest tym, czym jest, i nie ma sensu jej
upiększać — ale ma sens ją zważyć: klatka niesie przy tym **o 70% więcej treści**
(blob 23,6 → 40,1 kB), bo w każdym wierszu stoją dwa napisy więcej. Przy budżecie
33 ms (30 kl./s) zostaje niecałe 12 ms zapasu, a użytkownik, któremu szczegóły
nie są potrzebne, wraca jednym przełącznikiem do kosztu sprzed kroku.

**Wyprowadzenie rozdziału miejsca nie kosztowało nic.** Porównanie z wzorcem
kroku 26 mieści wszystkie pozostałe scenariusze w ±3%, a `ramki z tekstem` —
czyli ten, który przechodzi przez `VStack` w każdej klatce — wypadł **co do
dziesiątej milisekundy tak samo** (17,5 vs 17,5 ms, −0,0%). To jest właściwy
dowód na to, że `Distribution` jest przeniesieniem, a nie przepisaniem.

### Oględziny klatki

Klatka obejrzana pod XTermem oraz jako zrzut płótna scenariusza `columns`.
Zgodne z zamierzeniem: kolumny wyrównane w pionie przez całą listę, rozmiar
dosunięty do prawej krawędzi swojej kolumny, katalogi w akcencie i bez rozmiaru,
pasek zaznaczenia przez całą szerokość wiersza, a **suwak w osobnej kolumnie** —
nie na ostatnim znaku praw dostępu, co było powodem, dla którego tabela odbiera
mu kolumnę z rozdziału zamiast kłaść go na treści, jak robi `ListView`.
