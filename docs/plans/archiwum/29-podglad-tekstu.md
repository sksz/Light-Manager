# Krok 29 — Widok tekstu jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26. Wybrany przez użytkownika jako trzeci z tych, których odbiorca już siedzi
> w kodzie (D48). Zamyka zarazem pozycję „podgląd plików tekstowych” z listy
> „poza MVP”, otwartą od pierwszej iteracji planu.

## Status

**Ukończony** (2026-08-12).

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

## Rozstrzygnięcia ze startu kroku (2026-08-12)

Sześć pytań z sekcji powyżej rozstrzygnął użytkownik przed pierwszą linią kodu.
Trzy z nich zmieniły kształt kroku wobec planu — i to na tyle, że mają własny
wpis w dzienniku decyzji ([00-decyzje.md](../00-decyzje.md), D58).

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Zawijanie czy przycinanie | **Zawijanie domyślnie**; wiersz, który nie zmieściłby się nawet zawinięty, zostaje w jednej linijce przycięty. Przełącznik jest **klawiszem**, nie pozycją ustawień. |
| 2 | Ile wierszy z zapasem | **Bez limitu i bez zapasu** — czytamy wyłącznie to, co pokazujemy, a przewinięcie porzuca poprzednie wiersze i doczytuje następne. **Jak w edytorach.** |
| 3 | Plik bez końca wiersza | Zostaje w jednym wierszu; czytamy tyle znaków, ile pokażemy. Zawijanie przełącza się klawiszem `Alt`+`z`. |
| 4 | Kodowanie | **Rozpoznajemy i konwertujemy**; brak jednoznacznej odpowiedzi to UTF-8 z podmianą. |
| 5 | Kto rozstrzyga o tekstowości | **Wszystkie trzy metody kaskadą**: rozszerzenie → opis od `file` → podejrzenie bajtów. |
| 6 | Numery wierszy | **Są**, sterowane przełącznikiem modułu, domyślnie wyłączone. |

Odpowiedź 2 jest tą, która przestawiła krok najmocniej: plan zakładał wczytanie
nagłówka pliku z zapasem i wycinanie z niego okna `ScrollWindow`em, a wyszedł
**bufor przesuwny po bajtach**. Odpowiedź 3 wymusiła z kolei `Alt` w warstwie
wejścia, której plan nie przewidywał w ogóle.

## Dziennik realizacji

### 2026-08-12 — krok wykonany

**Komponent** (`Presentation/Ui/Component/TextView.php`, 175 wierszy): wiersze,
zawijanie po znaku, numery i suwak. Wobec szkicu z planu **nie ma parametru
`offset`** — przy buforze przesuwnym nie ma czego wycinać, bo lista przychodząca
do komponentu *jest* widocznym wycinkiem. Dwie reguły rysowania: zawijanie łamie
po znaku (wcięcia w kodzie mają zostać wcięciami), a wiersz dłuższy niż cały
prostokąt zostaje przycięty do jednej linijki. Próg liczy się z **pełnej**
wysokości prostokąta, nie z wierszy pozostałych do dołu — inaczej ten sam wiersz
zawijałby się albo nie, zależnie od tego, gdzie wypadł przy przewijaniu.
Jedno odstępstwo od `ListView`: **suwak dostaje własną kolumnę**, zamiast leżeć
na treści. W liście plików pod szyną wypada koniec nazwy, którą widać z lewej;
w podglądzie kodu przykryty byłby znak treści, po którą się tu przyszło.

**Odczyt** (`Module/FileInfo/Infrastructure/TextPreviewService.php`): cała
trudność kroku. Okno czytane od kotwicy w bajtach, budżet liczony z geometrii
panelu, wiersz przycinany do tego, co panel pokaże. Kaskada rozpoznania działa
tak, jak rozstrzygnął użytkownik, z jednym dopowiedzeniem: **dwa pierwsze stopnie
rozstrzygają wyłącznie twierdząco**, bo `README` nie ma rozszerzenia, a `file`
bywa nieobecne — więc ich milczenie znaczy „nie wiem”, a nie „binarny”.

**Warstwa wejścia**: `Alt` wszedł do słownika (`KeyPress::alt()`), do obu źródeł
(`ESC`+litera w `KeySequenceParser`, bit `GLFW_MOD_ALT` w `GlfwKeyMapper`) i do
`KeyBinding`. Trzy rzeczy, które przy tym wyszły i są warte zapisania:

1. **`Esc` przed literą jest nieodróżnialny od `Alt`+litery** — te same dwa
   bajty. Cena znana i zapisana przy parserze; tak samo rozstrzygają to emulatory
   terminala od czasów VT100.
2. **`TextInput` wpisywałby skrót do pola.** Warunek `Key::Character && !ctrl`
   przepuszczał `Alt`+literę jako zwykły znak — poprawione o `&& !alt`.
3. **Ekran modułu odpowiadałby na `Alt`+`s`.** Porównania `raw === 's'` nie
   patrzyły na modyfikatory; do kroku 29 uchodziło to na sucho, bo `Ctrl`+litera
   nie docierała do ekranu (przechwytywały ją skróty modułów). Porównanie jest
   teraz jawne (`isLetter()`).

**Odbiorca**: `PreviewPane` ma trzecią odpowiedź. Kolejność pytań jest
rozstrzygnięciem: **obraz przed tekstem**, bo `.svg` jest jednym i drugim naraz,
a w panelu podglądu chce się widzieć rysunek, nie jego zapis XML.

**Klawisze**: `PgUp`/`PgDn` przewijają podgląd o panel, `Home` wraca na początek,
`Alt`+`z` przełącza zawijanie. Osobnego przełącznika ogniska między panelami
**nie ma i nie był potrzebny**: strzałki należą do sekcji, a te trzy klawisze do
podglądu — zbiory rozłączne, więc `Tab` zostaje wolny.

**Pomiar** (host zwolniony przez użytkownika, `--compare` wobec
`2026-08-11-po-kroku-27.json`, wzorzec zapisany jako
[2026-08-12-po-kroku-29.json](../../pomiary/2026-08-12-po-kroku-29.json)):

| Scenariusz | Wzorzec | Teraz | Zmiana |
|---|---|---|---|
| ramki z tekstem | 17,5 ms | 17,5 ms | −0,2% |
| lista w kolumnach | 21,3 ms | 20,5 ms | −3,6% |
| **podgląd tekstu** | — | **23,8 ms** | *nowy* |

Bez regresji powyżej progu; wszystkie czternaście wspólnych scenariuszy mieści
się w ±4,8%. **Cena podglądu tekstu to 6,3 ms wobec `chrome-text`** — i widać,
skąd się bierze, po rozmiarze bloba: 80,5 kB wobec 23,6 kB. Zawinięty wiersz to
kilka napisów zamiast jednego, a napisy podglądu są w każdej klatce inne, więc
pamięć podręczna wierszy (D34) trafia w nie rzadko. Klatka mieści się w budżecie
30 kl./s z zapasem 9 ms.

Klatkę obejrzano zrzutem `--png` scenariusza `text-view`: zawijanie łamie po
znaku, wcięcia zostają wcięciami, suwak stoi w swojej kolumnie przy prawej
krawędzi.

**Tor okienkowy** (`--compare` wobec [2026-08-12-window.json](../../pomiary/2026-08-12-window.json)):
`text-view` kosztuje **0,7 ms**, czyli tyle samo co lista w kolumnach, a wszystkie
scenariusze zastane mieszczą się w ±7,4% przy wartościach rzędu pół milisekundy.
Komponent nie wnosi nowego prymitywu, więc trzeci tłumacz słownika (krok 35)
nie potrzebował ani jednej linii — i pomiar to potwierdza.

**Powtórka pomiaru po dopisku o UTF-16** wypadła w granicach szumu (podgląd
tekstu 23,5 ms wobec 23,8 ms we wzorcu, cała tabela w ±5,1%), co było do
przewidzenia: zmiana siedzi w usłudze odczytu, poza ścieżką klatki. Pierwsze
podejście do tej powtórki trzeba było **odrzucić w całości** — host miał wtedy
uruchomioną przeglądarkę, siedem wierszy dostało „!”, a „puste płótno”
spowolniło o 56%, choć nie rysuje ani jednego prymitywu. Kolejny przykład
zjawiska opisanego w [docs/pomiary/README.md](../../pomiary/README.md): przy
obciążonym hoście przesuwa się **cała** tabela, także tam, gdzie kod nie ma jak
niczego zmienić.

**Jakość**: 1171 testów zielonych (o 56 więcej), PHPStan `max` czysty,
PHP-CS-Fixer bez uwag. Nowe sprawdziany: 15 na komponencie, 20 na odczycie
(w tym plik półgigabajtowy, bajt spoza kodowania, ISO-8859-2, BOM, tabulatory,
pusty plik, plik o jednym wierszu na 200 tys. znaków), 10 na drodze przez ekran
modułu i 8 na `Alt` w obu źródłach wejścia.

**Trzy poprawki w testach zastanych**, wszystkie będące skutkiem zmiany, a nie
usterkami: parser sekwencji (`ESC`+litera znaczy teraz co innego), lista pozycji
ustawień modułu (dwie nowe) i **wysokość okna w teście ekranu ustawień** — przy
dziesięciu pozycjach zakładka `file-info` przestała się mieścić w oknie
dziesięciowierszowym, a ekran ustawień składa się z `Slot::fixed()`, więc przy
braku miejsca pozycje **znikają zamiast się przewijać** (reguła 11e). To zachowanie
jest starsze od tego kroku i dotyczy każdej zakładki dostatecznie długiej —
zapisane tutaj jako obserwacja do rozważenia, nie naprawione po drodze.

### Odstępstwa od planu

1. **`ScrollWindow` nie wystarczył — i nie miał prawa.** Kryterium ukończenia
   („czwarta klasa stanu nie powstała”) zapisano przy założeniu, że podgląd
   wycina okno z wczytanej listy wierszy. Bufor przesuwny (rozstrzygnięcie 2)
   liczy w **bajtach** i nie zna liczby wierszy pliku, więc `ScrollWindow`,
   któremu trzeba podać `total`, nie ma tu czego pilnować. Czwarta klasa stanu
   rdzenia mimo to **nie powstała**: kotwica mieszka w `FileInfoState`, czyli
   w klasie stanu, którą moduł już miał. Duch kryterium jest więc dotrzymany,
   litera nie.
2. **Limitu wierszy w ustawieniach nie ma.** Plan przewidywał „przełącznik
   podglądu tekstu i limit wierszy”; przy odczycie okna limit wierszy nie ma
   czego ograniczać — czytamy tyle, ile widać. Zamiast niego weszły numery
   wierszy (rozstrzygnięcie 6).
3. **`Alt` w warstwie wejścia** — praca, której plan kroku nie przewidywał
   w ogóle, dołożona na rozstrzygnięcie 3. Dotknęła kodu kroków 06, 19 i 34.
4. **Zawijanie jest przełącznikiem dwustanowym.** Rozstrzygnięcie 3 wspominało
   o wymuszeniu zawinięcia także na wierszu monstrualnym; zrezygnowano z tego
   świadomie, bo zawinięty zrzut JSON-a o milionie znaków to dwadzieścia pięć
   tysięcy wierszy siatki w panelu przewijanym po jednym panelu — funkcja, której
   nie da się użyć. `Alt`+`z` przełącza więc zawijanie i wyłącznie je, a wiersz
   monstrualny zostaje przycięty w obu stanach.
5. ~~UTF-16/32 odmawia się z powodem~~ — **wycofane tego samego dnia na polecenie
   użytkownika**; patrz dopisek poniżej.

### 2026-08-12 (dopisek) — UTF-16 i UTF-32 wchodzą do obsługi

Pierwsza wersja kroku odmawiała podglądu plikom w kodowaniu szerokim: bajt zerowy
co drugi znak wywracał podział na wiersze i rachunek bajtów, na którym stoją
kotwice. Użytkownik polecił dowieźć obsługę zamiast wyłączenia — i miał rację
w rzeczy, która przy pisaniu umknęła: **znacznik kolejności bajtów jest dowodem,
że plik jest tekstem**, więc odmawianie mu podglądu było najsłabszym punktem
całego kroku.

**Co się zmieniło w odczycie.** Podział na wiersze przestał być `explode("\n")`
na bajtach, a stał się szukaniem znaku nowej linii **w kodowaniu źródła
i wyłącznie na granicy jednostki kodowej**. To drugie jest tu całą trudnością
i nie jest teoretyczne: w UTF-16LE bajty `0A 00` wypadają także w środku pary
znaków — na przykład U+0A39 (`39 0A`) obok U+2500 (`00 25`) daje ciąg
`39 0A 00 25` z parą `0A 00` na nieparzystym przesunięciu. Wzięta za koniec
wiersza przesunęłaby kotwicę o bajt, czyli o pół znaku, i wszystko dalej byłoby
śmieciem. Pilnuje tego osobny test.

Wyrównania wymagają odtąd wszystkie trzy miejsca: kotwica przewijania w górę
(ścinana w dół do jednostki), bufor urwany budżetem (docinany do jednostki) oraz
samo szukanie. Ogon pliku zostaje nietknięty — jeśli plik kończy się połówką
jednostki, jest to cecha pliku, a nie odczytu.

**Rozpoznanie.** BOM (`UTF-32LE/BE`, `UTF-16LE/BE`, `UTF-8`) przerywa kaskadę od
razu i jest jedynym rozpoznaniem bez zgadywania — dłuższe znaczniki sprawdzane
przed krótszymi, bo BOM UTF-32LE zaczyna się dokładnie tak, jak cały BOM
UTF-16LE. Plik bez BOM-u dostaje **ciasny** wzorzec zer: żadna jednostka nie
może być parą zer (to odsiewa pliki wykonywalne i formaty z wyrównywanymi
nagłówkami), zera muszą stać zawsze po tej samej stronie jednostki, a wzorzec ma
obejmować cztery piąte jednostek nagłówka — nie wszystkie, bo „ż” w polskim
tekście zera nie zawiera. UTF-32 bez BOM-u pozostaje nieobsługiwany świadomie:
jest rzadki jak rzadko co, a jego wzorzec trafiałby w pliki, które tekstem nie są.

**Rozpoznanie kodowania stoi przed kaskadą**, a nie po niej, i to jest poprawka
kolejności, nie kosmetyka: gdyby szło po, plik `.txt` w UTF-16 przeszedłby
pierwszym stopniem jako tekst i pokazał się jako śmieci, bo czytano by go jak
UTF-8.

Klucz `module.file-info.preview.encoding` zniknął z obu katalogów — martwy klucz
jest gorszy niż jego brak.

**Jakość po dopisku**: 1178 testów zielonych (+7): cztery kodowania szerokie
w komplecie z przewijaniem w obie strony, znacznik nieodbijający się w treści,
pułapka nieparzystego przesunięcia, UTF-16 bez BOM-u i plik binarny gęsty od
zer, który binariem zostaje. PHPStan `max` czysty, PHP-CS-Fixer bez uwag.
Pomiaru nie powtarzano: zmiana nie dotyka ani jednego prymitywu ani ścieżki
rysowania — całość siedzi w usłudze odczytu, poza klatką.

### 2026-08-12 (poprawka) — zawijanie nie działało tam, gdzie było potrzebne

Zgłoszenie użytkownika: *„zawijanie wierszy w ogóle nie działa… Sprawdzone na np.
pliku `.php-cs-fixer.cache`. Skrót `Alt+Z` zdaje się nic nie robić.”* Zgłoszenie
było trafne, a diagnoza „skrót nie działa” — nie: skrót przełączał flagę
bez zarzutu, tylko flaga nie miała dla tego pliku żadnego skutku.

**Usterka pierwsza: próg zawijania odwracał sens funkcji.** `TextView::pieces()`
miał warunek „wiersz dłuższy niż cały prostokąt (wiersze × kolumny) **nie zawija
się wcale**, tylko zostaje przycięty do jednej linijki”. Uzasadnienie z docblocka
brzmiało: żeby zrzut JSON-a o milionie znaków nie zamienił panelu w wiersz
rozciągnięty na dwadzieścia pięć tysięcy linijek. **Obawa była nieprawdziwa** —
pętla w `rows()` przerywa na `$row >= $content->rows`, więc więcej niż wysokość
panelu nie powstałoby tak czy owak. Skutek był za to prawdziwy i dokładnie
odwrotny do zamierzonego: **jedyne wiersze, które nigdy się nie zawijały, to te
najdłuższe** — czyli te, dla których zawijanie w ogóle istnieje. Plik o jednej
długiej linii pokazywał jedną linijkę i pusty panel pod nią, w obu stanach
przełącznika.

Poprawka: próg znika, a wysokość prostokąta zostaje **sufitem liczby kawałków**
(`count($pieces) < $rows`), czyli ogranicznikiem pracy, a nie warunkiem
zawinięcia. Reguła ogólna warta zapamiętania: **próg chroniący przed pracą, którą
i tak ucina pętla rysująca, nie chroni przed niczym.**

**Dlaczego nie złapał tego test.** `TextPreviewPaneTest` sprawdzał `Alt`+`Z` na
wierszu **osiemdziesięcioznakowym** — a taki mieścił się pod progiem i zawijał
poprawnie. Test pokrywał przypadek, który działał. Dopisany
`testALineLongerThanTheWholePanelWrapsAndFillsIt` idzie na czterdzieści tysięcy
znaków, czyli w to samo miejsce, w które trafił użytkownik.

**Usterka druga: `Label` nie przycinał wartości po prawej.** Opis od polecenia
`file` — dla zdjęcia potrafi mieć sto dwadzieścia osiem znaków — szedł na płótno
w całości: w czterdziestokolumnowym prostokącie rysował się jako napis kończący
się na kolumnie 129, czyli **osiemdziesiąt osiem kolumn za krawędzią panelu**,
po sąsiednim. Przycinała się wyłącznie treść po lewej. Poprawka jest
jednolinijkowa (`self::fit()` także dla wartości), ale dotyczy komponentu, którego
używa cała aplikacja — stąd osobny test pilnujący, że **żaden** napis `Label`a nie
wychodzi poza prostokąt.

**Zawijanie długich wartości w sekcjach opisu** (rozstrzygnięcie użytkownika przy
zgłoszeniu): wartość dłuższa niż miejsce obok etykiety rozkłada się na kolejne
wiersze sekcji, wyrównane pod pierwszym kawałkiem. Zawijanie liczy `FileInfoScreen`,
bo tylko rysowanie zna szerokość panelu, i robi to **przed** `rowCount()`
i `rowOf()`, żeby przewijanie widziało wiersze po zawinięciu. Rdzeń (`Section`,
`SectionList`, `ListView`) został nietknięty. Skutek uboczny na plus: suma
`sha256`, dotąd przycinana w wąskim panelu, mieści się teraz w całości na dwóch
wierszach.

**Zawijanie stało się pozycją ustawień** (`textWrap`, domyślnie włączona) —
zgłoszony brak. `Alt`+`Z` zmienia **tę samą pozycję**, a nie prywatną flagę, więc
wybór przeżywa zamknięcie aplikacji; obie drogi kończą w jednym kluczu pliku,
tak jak `.` i „Pokazuj wpisy ukryte“ w przeglądarce (D40). Wymagało to podania
modułowi `LoopState` — tak, jak ma go `BrowserModule` od kroku 21 — bo ekran
ustawień czyta konfigurację stamtąd, a zapis pomijający go dawałby zakładkę
pokazującą starą wartość i cofającą nową przy najbliższej zmianie czegokolwiek
obok.

**Trzecia usterka, znaleziona przy sprawdzaniu poprawek:** zdanie „(nie zaznaczono
wpisu)” szło na **surowy prostokąt ekranu**, którego pierwszy wiersz jest linią
obwódki rysowanej przez `ownFrame()`. Napis siadał na kresce i nakładał się na
etykietę „Opis pliku”, litera na literę. Widać to było wyłącznie wtedy, gdy opisu
nie ma **i** okno jest dość szerokie na podział — do kroku 30 wymagało to pustego
katalogu, więc przypadek nie trafił pod oko przy kroku 25. Zdanie liczy teraz
geometrię tak samo, jak treść: wnętrze lewego panelu.

**Jakość**: 1235 testów zielonych, PHPStan `max` czysty, PHP-CS-Fixer bez uwag.
Sprawdzone w prawdziwym XTermie na `.php-cs-fixer.cache` (40 kB w jednej linii),
na `composer.lock` i na zdjęciu o stuznakowym opisie od `file`. Pomiaru nie
powtarzano: zawijanie nie dokłada prymitywów ponad te, które i tak wypełniają
panel, a scenariusz `text-view` mierzy wiersze krótsze od jego szerokości.

### 2026-08-12 (rozbudowa) — ognisko i przewijanie po linijkach

Na żądanie użytkownika podgląd dostał **kursor**, a przewijanie — jednostkę,
w której „o tyle, ile widać” znaczy to samo dla czytającego plik i dla
patrzącego na ekran. Pełne uzasadnienia: [00-decyzje.md](../00-decyzje.md), **D60**.

**Rozstrzygnięcie kroku 29 zostało odwołane.** Dziennik tego kroku zapisywał:
*„Osobnego przełącznika ogniska nie ma z rozmysłu… a `Tab` zostaje wolny.”*
Ognisko przenosi odtąd `Tab`, panel czynny ma akcent w obwódce, a spis klawiszy
zmienia się razem z ogniskiem. Uzasadnienie odwołania jest jednozdaniowe:
podgląd tekstu, który nie umie przewinąć się o wiersz, nie jest podglądem
tekstu, a strzałek nie da się mieć w dwóch panelach naraz.

| Klawisz | Ognisko na opisie | Ognisko na podglądzie |
|---|---|---|
| `↑` / `↓` | sekcja wyżej / niżej | **linijka** wyżej / niżej |
| `PgUp` / `PgDn` | — | panel wyżej / niżej |
| `Home` / `End` | pierwsza / ostatnia sekcja | początek / **koniec pliku** |
| `Enter` | zwiń lub rozwiń sekcję | — |
| `Tab` | przeniesienie ogniska (tylko przy dwóch panelach) | |
| `Alt`+`Z`, `s`, `d` | działają niezależnie od ogniska | |

**Jednostką jest linijka panelu, nie wiersz pliku**, i to była świadoma decyzja
użytkownika przeciw wariantowi tańszemu o połowę. Naprawiła przy okazji wadę,
o której nikt nie wiedział: `PgDn` przewijał o tyle **wierszy pliku**, ile panel
ma linijek, a przy zawijaniu wierszy widać mniej niż linijek — więc **gubił
treść**.

Kotwica została tam, gdzie była (na początku wiersza); ile jego linijek pominąć,
mówi osobne pole stanu. Wariant z kotwicą w środku wiersza wymagałby mapowania
znaków na bajty, a przy UTF-16 i rozwiniętych tabulatorach to nie jest ta sama
arytmetyka. Okno niesie za to **bajtowe początki wierszy**, bo bez nich każda
strzałka kosztowałaby osobny odczyt szukający początku wiersza.

**Dwie pomyłki popełnione po drodze, obie złapane dopiero na zrzucie z XTerma**
i obie tego samego rodzaju — geometria zależna od treści:

- kolumna suwaka znikała, gdy plik mieścił się w oknie, więc szerokość treści
  zmieniała się wraz z długością pliku;
- kolumna numerów liczyła się z liczby **wczytanych** wierszy, więc plik o jednej
  długiej linii miał ją węższą niż plik kodu — obraz przewijał się wtedy o dwa
  znaki na krok.

Obie biorą się dziś z prostokąta, a regułę trzyma jedno miejsce
(`TextView::contentColumns()`), bo potrzebuje jej także ten, kto czyta plik.
Reguła ogólna: **geometria, od której zależy odczyt, nie ma prawa zależeć od
tego, co odczytano.**

**Jakość**: 1245 testów zielonych (+11, w tym nowy `TextScrollTest` idący przez
prawdziwą usługę i prawdziwe pliki — atrapa portu liczy wiersze zamiast bajtów,
więc zawinięć nie widzi w ogóle). PHPStan `max` czysty, PHP-CS-Fixer bez uwag.
Sprawdzone w XTermie na `.php-cs-fixer.cache`: strzałka przesuwa obraz o jedną
linijkę wewnątrz jednej czterdziestokilobajtowej linii, a `End` pokazuje jej
koniec wraz z domykającymi nawiasami JSON-a.
