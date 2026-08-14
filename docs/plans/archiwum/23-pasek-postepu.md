# Krok 23 — Pasek postępu z tekstem jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-10, przy otwieraniu kroku o pełnym obrazie
> stanu pliku. Rozstrzygając koszt `sha256`, użytkownik dopisał: *„Myślę, że warto
> dodać pasek postępu z tekstem jako nowy komponent rdzenia i użyć go do
> prezentacji postępu `du` czy wyliczania `sha256`”.* Klocek dostał własny krok,
> bo jest rdzeniowy, a jego pierwszy odbiorca — modułowy.

## Status

**Ukończony** (2026-08-10). Kod, testy i dokumentacja gotowe: PHPStan `max` bez
błędów, PHP-CS-Fixer bez uwag, **880 testów** (2039 asercji) zielone, klatka
zmierzona i rozliczona „przed i po”, wzorzec zapisany za pierwszym podejściem —
host był zwolniony przed pomiarem, zgodnie z regułą 17.

Pomiar znalazł przy okazji **błąd starszy od tego kroku** (`Weight::Fill` przez
`drawImage()`) i krok go naprawił: scenariusz paska spadł z 85,3 ms do 23,5 ms.
Szczegóły w sekcji „Pomiar” i w [00-decyzje.md](../00-decyzje.md), D44.

## Cel

Dać rdzeniowi **jeden sposób pokazywania, że coś trwa**: wypełniany pasek
z tekstem w środku, działający zarówno wtedy, gdy postęp da się policzyć, jak
i wtedy, gdy nie da się go policzyć wcale.

Miarą powodzenia jest zdanie: **od tego kroku żadna praca trwająca dłużej niż
klatka nie musi wymyślać własnego sposobu, żeby o sobie powiedzieć.**

## Zależności

- **Krok 18** (komponenty i płaszczyzny) — twardo: `ComponentInterface`, `Panel`,
  `Label`, prymitywy `Bar` i `TextRun`. Pasek to prostokąt wypełnienia plus napis,
  więc powstaje wprost z tego, co tam jest.
- **Krok 13** (motyw) — wypełnienie i tło rysują się rolami motywu.
- **Krok 17** (optymalizacja) — pasek zmienia się **co klatkę**, więc jest
  pierwszym komponentem, który z założenia nie trafi do pamięci podręcznej
  segmentów (D34). To trzeba sprawdzić pomiarem, a nie założyć.

Od kroku 22 nie zależy — sekcja i pasek nie dotykają się nawzajem; stoi za nim
wyłącznie w kolejce.

## Model i wysiłek

**Opus / high.**

Sam rysunek jest prosty. Trudne jest to, czego pasek dotyka: **rytm klatki**.
Element zmieniający wygląd trzydzieści razy na sekundę to dokładnie ta klasa
zmian, którą krok 17 rozliczał pomiarem, i pierwszy w projekcie element, który
**musi** wymusić przerysowanie, choć stan aplikacji się nie zmienił.

## Stan zastany (sprawdzony w kodzie 2026-08-10, po kroku 21)

| Element | Stan |
|---|---|
| `Application/Ui/Primitive/Bar` | Prymityw wypełnionego prostokąta — gotowy budulec wypełnienia paska |
| `Application/Ui/Primitive/{TextRun,Weight}` | Napis wraz z grubością; `Label` składa z nich wiersz z treścią po lewej i wartością po prawej |
| `Application/Ui/Primitive/Scrollbar` | Jedyny dzisiejszy element „ile z ilu” — rysowany z `ScrollPosition`; **postępu nie umie**, bo mówi o oknie, nie o pracy |
| `Application/Ui/Role` | Role motywu; brak roli „postęp” |
| Potok klatki | Klatka rysuje się na zdarzenie wejścia i na takt pętli; **nie ma dziś powodu do przerysowania „bo coś się liczy”** |
| Pamięć podręczna segmentów (D34) | Wiersz niezmieniony między klatkami nie jest rysowany ponownie — pasek postępu będzie zmieniony zawsze |

Ostatnie dwie pozycje są treścią tego kroku. Rysunek paska to jeden plik; jego
**wpływ na takt** to reszta.

## Zakres

### 1. Komponent

```php
final class ProgressBar implements ComponentInterface
{
    /**
     * @param ?float $fraction 0.0–1.0; `null` — postępu nie da się policzyć
     * @param string $text     napis w środku paska (już przetłumaczony)
     */
    public function __construct(
        private readonly ?float $fraction,
        private readonly string $text = '',
    ) {}

    public function draw(Rect $bounds): array;
}
```

Bezstanowy, jak każdy komponent: powstaje co klatkę z wartości podanej z zewnątrz.
Pamięć — o ile w ogóle potrzebna — należy do tego, kto pracę zleca.

### 2. Dwa tryby, jeden komponent

- **Postęp znany** (`fraction` podany): pasek wypełnia się od lewej.
- **Postęp nieznany** (`null`): pasek nie może udawać, że wie. Warianty do wyboru
  na starcie kroku — wypełnienie wędrujące tam i z powrotem, pulsujące tło albo
  sam tekst bez wypełnienia.

Rozróżnienie jest ważne, bo **`du` postępu nie zna**. Polecenie nie mówi, ile
z drzewa już przeszło, a licznik oparty na czasie kłamałby tym bardziej, im
większe drzewo. `sha256` postęp zna — bajty przeczytane wobec rozmiaru pliku —
i to on będzie pierwszym prawdziwym użytkownikiem trybu z liczbą.

### 3. Tekst w pasku

Napis wchodzi **do środka** paska, a nie obok niego: pasek na całą szerokość
panelu z pustym środkiem marnuje wiersz, którego w wąskim oknie nie ma.

Do przesądzenia: co się dzieje, gdy tekst jest szerszy od paska (ucięcie
z wielokropkiem jak `Label::fit()`) i czy napis zmienia kolor tam, gdzie
przechodzi przez wypełnienie.

### 4. Takt: kto każe przerysować klatkę

Najważniejsza część kroku i jedyna, która wychodzi poza warstwę `Ui`.

Pętla przerysowuje klatkę na zdarzenie wejścia i na takt. Pasek postępu wymaga,
by klatka powstała **także wtedy, gdy użytkownik niczego nie nacisnął** —
inaczej postęp stanie i ruszy dopiero przy pierwszym klawiszu. Trzy drogi,
rozstrzygnięcie na starcie kroku:

| Droga | Rzecz do zaakceptowania |
|---|---|
| Pętla rysuje zawsze w takcie | Najprościej — ale jeśli dziś oszczędza klatki przy bezruchu, to przestanie |
| Ekran mówi „jestem żywy” (`ScreenInterface` zyskuje metodę) | Kontrakt ekranu rośnie po raz drugi od kroku 18 |
| Pasek nie wymusza nic, a odświeża się przy okazji | Zero zmian w rdzeniu; cena: postęp rusza szarpnięciami |

Którą z nich wybrać, przesądza **stan faktyczny pętli**, a ten trzeba przeczytać
z kodu na starcie kroku, nie z pamięci.

### 5. Pierwszy użytkownik

Prawdziwym odbiorcą jest krok 25 (`du` i `sha256`) i to jest **słabość tego
kroku, którą trzeba nazwać wprost**: komponent powstaje przed swoim użytkownikiem,
a plan projektu mówi, że taki projektuje się na domysł.

Osłona jest jedna i krok ma ją dowieźć: **scenariusz w `bin/render-bench`
i zestaw testów mają obejmować oba tryby oraz przypadki brzegowe** (0%, 100%,
wartość spoza zakresu, pasek węższy od tekstu, pusty prostokąt) — czyli wszystko,
czego krok 25 od paska zażąda, zanim go zażąda.

## Poza zakresem

- **Kolejka wielu pasków naraz** — jeden pasek, jedno miejsce.
- **Czas pozostały** („zostało 3 s”) — wymaga historii pomiarów, a przy `du`
  i tak byłby zgadywaniem.
- **Anulowanie pracy klawiszem** — należy do tego, kto pracę zleca, czyli do
  kroku 25.
- **Pasek postępu w pasku stanu** — pasek stanu ma dziś komunikat i podpowiedzi
  klawiszy; dokładanie mu trzeciej roli to osobna decyzja.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Component/ProgressBar.php` | Presentation | Nowy — dwa tryby, tekst w środku. |
| ~~`Application/Ui/Role.php`~~ | Application | **Nietknięte** — `Surface`, `Accent`, `Background` i `Text` wystarczyły. |
| `Infrastructure/Imagick/SixelFrameEncoder.php` | Infrastructure | **Poza planem** — `Weight::Fill` przez pamięć podręczną bitmap zamiast `drawImage()`. Powód i liczby niżej. |
| ~~`Presentation/Cli/GameLoop.php` *(lub `ScreenInterface`)*~~ | Presentation | **Nietknięte** — pętla rysuje w takcie od kroku 09. Zamiast tego sześć linijek w `FrameComposer`: zegar do ekranu przez `NeedsTime`. |
| `lang/pl.php`, `lang/en.php` | Napisy | Klucze stanu pracy, jeśli któryś okaże się rdzeniowy. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | Scenariusz „pasek postępu” do `bin/render-bench`. |
| `docs/architecture.md` | Dokumentacja | Pasek postępu; reguła „element żyjący własnym rytmem a takt pętli”. |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | To samo w skrócie operacyjnym — **w tym samym kroku**. |
| testy | Testy | Oba tryby, 0%, 100%, wartość spoza zakresu, tekst szerszy od paska, pusty prostokąt, wpływ na takt klatki. |

## Rozstrzygnięcia wykonawcze ze startu kroku (2026-08-10)

Pięć pytań planu, z czego jedno rozstrzygnął kod, zanim ktokolwiek zdążył wybrać,
plus szóste, które pojawiło się przy okazji.

| # | Pytanie | Wybór |
|---|---|---|
| 1 | Jak wygląda postęp nieznany | **Wędrujące wypełnienie** — ¼ toru, trójkątna fala, 1,2 s w jedną stronę |
| 2 | Kto każe przerysować klatkę | **Nikt — pętla rysuje w każdym takcie od kroku 09.** `GameLoop` nietknięty |
| 2a | Skąd ekran bierze zegar | **Istniejący `NeedsTime`**, o który `FrameComposer` pyta także ekran. `ScreenInterface` nietknięty |
| 3 | Rola motywu wypełnienia | Tor `Surface`, wypełnienie `Accent`, napis `Background`/`Text`. **Paleta nietknięta** |
| 4 | Tekst szerszy od paska | **Ucięcie wielokropkiem** przez istniejące `Label::fit()` |
| 5 | Wysokość paska | **Wypełnia prostokąt, który dostał** — bez `height()` |
| 6 | Kto dokleja procent | **Pasek**, przez wstrzyknięty `TranslatorPort` i klucz `format.percent` |

Pytanie nr 2 jest tu najważniejsze i **rozstrzygnął je stan faktyczny, a nie
wybór**, dokładnie tak, jak plan nakazywał: pętla przerysowuje klatkę
w każdym takcie niezależnie od tego, czy coś się zmieniło, więc pierwsza z trzech
dróg z tabeli obowiązuje od kroku 09. Wymuszanie nie ma czego wymuszać. Zostało
pytanie 2a, którego plan nie zadał — i to ono okazało się całą treścią „taktu”
w tym kroku.

Wybór nr 6 czyni `ProgressBar` **pierwszym komponentem znającym tłumacza**.
Dotąd napisy tłumaczyły ekrany i okna, a komponenty dostawały gotowe.

## Odstępstwa od planu

Cztery, każde z powodem.

1. **`GameLoop` nietknięty, bo nie było czego tykać.** Plan traktował „kto każe
   przerysować klatkę” jako najważniejszą część kroku i przewidywał trzy drogi.
   Kod pokazał, że pierwsza z nich obowiązuje od kroku 09: pętla rysuje w każdym
   takcie, bez względu na zmiany. Cała „część wychodząca poza warstwę `Ui`”
   sprowadziła się więc do **sześciu linijek w `FrameComposer`** — zegar dla
   ekranu tą samą drogą, którą idzie kontekst modułu.
2. **Zegar niesie istniejący `NeedsTime`, a nie nowa metoda kontraktu.** Plan
   dopuszczał wzrost `ScreenInterface`. Okazał się zbędny: `NeedsTime` powstał
   w kroku 19 dla karetki i jest interfejsem deklarowanym osobno, więc ekran bez
   ruchomej treści nadal nie deklaruje niczego. Kontrakt ekranu **nie urósł po
   raz drugi od kroku 18**.
3. **Krok naprawił `Weight::Fill` w enkoderze Sixela** — plik spoza listy
   planowanych zmian. To nie było rozszerzanie zakresu, tylko warunek dowiezienia
   kryterium „klatka zmierzona i rozliczona”: pierwszy przebieg dał 85,3 ms,
   a przyczyną był `drawImage()` w gałęzi, która do tego kroku **nie miała ani
   jednego użytkownika**. Szczegóły w sekcji „Pomiar”.
4. **Pasek zna tłumacza.** Plan przewidywał napis „już przetłumaczony”, a wybór
   nr 6 kazał paskowi doklejać procent — więc port musiał wejść do konstruktora.
   Jest opcjonalny i jego brak znaczy zapis surowy: dokładnie dla `ScenarioFactory`,
   której treść nie przechodzi przez katalog z rozmysłu (D33).

**Czego nie zrobiono:** paska nie widać w aplikacji, bo nie ma go kto pokazać —
to jest ta słabość kroku, którą plan nazwał wprost i której nie da się usunąć
przed krokiem 25. Zrzut klatki z `bin/render-bench --png --scenario=progress`
jest dziś jedynym sposobem obejrzenia go na oczy.

## Kryteria ukończenia

- Pasek rysuje się w obu trybach i **żaden z nich nie udaje drugiego**: postęp
  nieznany nie pokazuje liczby.
- Przypadki brzegowe pokryte testami, zanim pojawi się pierwszy prawdziwy
  użytkownik: 0%, 100%, wartość spoza zakresu, tekst szerszy od paska, pusty
  prostokąt.
- Rozstrzygnięcie o takcie klatki jest **zapisane wraz z ceną**, a jeśli pętla
  zmieniła zachowanie — dziennik mówi, co dokładnie i ile to kosztuje w klatkach.
- Klatka zmierzona `bin/render-bench` i rozliczona „przed i po”, ze scenariuszem
  zawierającym pasek — również wtedy, gdy wynik jest niekorzystny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` i `SKILL.md` opisują pasek i regułę taktu — zgodnie ze
  sobą.

## Pomiar

Wzorce: [2026-08-10-po-kroku-22.json](../../pomiary/2026-08-10-po-kroku-22.json)
i [2026-08-10-po-kroku-23.json](../../pomiary/2026-08-10-po-kroku-23.json).

| Scenariusz | Przed | Po | Zmiana |
|---|---|---|---|
| puste płótno | 6,5 ms | 6,8 ms | +5,3% |
| sam tekst | 11,1 ms | 11,0 ms | −0,9% |
| same ramki | 10,4 ms | 10,3 ms | −1,4% |
| ramki z tekstem | 17,8 ms | 17,0 ms | −4,4% |
| zaznaczenie | 18,8 ms | 18,7 ms | −0,5% |
| suwak | 13,8 ms | 13,7 ms | −0,8% |
| klatka z miniaturą | 26,4 ms | 26,3 ms | −0,5% |
| klatka z okienkiem | 21,6 ms | 21,5 ms | −0,4% |
| okno komend | 27,3 ms | 26,3 ms | −3,9% |
| zwijane sekcje | 16,7 ms | 15,9 ms | −4,9% |
| **paski postępu** | — | **23,5 ms** | nowy scenariusz |

**Bez regresji powyżej progu.** Dziewięć starych scenariuszy stoi w miejscu i tak
ma być: żaden z nich nie zawiera paska postępu, a jedyna zmiana, która ich
dotyczy — `Weight::Fill` w enkoderze — nie występuje w żadnym z nich, bo do tego
kroku nie występowała **nigdzie**. Wzorce powstały tym razem na hoście zwolnionym
w obu przypadkach, więc kolumny są porównywalne; różnice rzędu procenta to szum.

### Pierwszy przebieg i błąd, który znalazł

Krok miał zmierzyć koszt paska. Zmierzył i wynik był nie do przyjęcia:

| Scenariusz | Rysowanie | Kwantyzacja | Kodowanie | Razem |
|---|---|---|---|---|
| paski postępu (pierwszy przebieg) | **73,4 ms** | 7,4 ms | 4,4 ms | **85,3 ms** |
| paski postępu (po naprawie) | 11,5 ms | 7,5 ms | 4,4 ms | 23,5 ms |

Przyczyną nie był pasek, tylko `Weight::Fill` w `SixelFrameEncoder`: wypełnienie
szło przez `ImagickDraw::drawImage()`, którego koszt zależy od **wielkości
płótna, a nie kształtu**. Scenariusz rysuje 46 pasków, czyli 92 wypełnienia, więc
92 razy przepłacał całe płótno.

Najciekawsze jest to, **dlaczego nikt tego wcześniej nie zauważył**. Pułapka jest
opisana trzy metody niżej w tym samym pliku — złapała pasek zaznaczenia w kroku 17
(dźwignia 5) i jego krawędź w kroku 18 (+17 ms na klatkę) — a mimo to `Fill`
został przy `drawImage()`, bo **nie miał w aplikacji ani jednego użytkownika**:
pasek zaznaczenia rysuje się `RoundRect`iem, przegroda w pasku stanu włosem,
krawędź zaznaczenia krawędzią. Pasek postępu jest pierwszym użytkownikiem tej
gałęzi w całym projekcie.

Naprawa jest tą samą, którą projekt stosuje od kroku 17: bitmapa z
`RowBitmapCache` składana przez `compositeImage()`. Zrzuty klatki przed naprawą
i po niej są **piksel w piksel takie same** — zmienił się koszt, nie obraz.

Wniosek na przyszłość, szerszy niż jeden prymityw: **gałąź kodu bez użytkownika
nie jest zmierzona, choćby leżała w najlepiej rozliczonym pliku projektu.**
Reguła 13 mówiła dotąd o projektowaniu API na domysł; to jest jej druga strona.

### Ile kosztuje jeden pasek

Scenariusz mierzy przypadek skrajny — 46 pasków naraz, choć plan wyklucza kolejkę
pasków i mówi „jeden pasek, jedno miejsce”. Po podzieleniu: **około 0,25 ms na
pasek**, z czego prawie wszystko to dwa wypełnienia i do trzech napisów.
Pojedynczy pasek w prawdziwej klatce kosztuje więc mniej niż jeden wiersz listy
i **budżetu taktu nie rusza**. Cena, którą trzeba znać, jest inna niż liczbowa:
jego wiersz jest w każdej klatce inny, więc z pamięci podręcznej wierszy nie
skorzysta nigdy.

## Dziennik realizacji

**2026-08-10 — krok wykonany.**

Co powstało:

- **`Presentation/Ui/Component/ProgressBar`** — komponent w dwóch trybach.
  Bezstanowy; czas i ułamek dostaje w konstruktorze. Napis tnie się na krawędzi
  wypełnienia na **co najwyżej trzy** odcinki (odcinek wędrujący tnie z obu
  stron), bo wypełnienie jest jednym przedziałem kolumn — cięcie po kolumnach,
  nie po znakach.
- **Zegar dla ekranu** — sześć linijek w `FrameComposer`, obok pytania
  o `ReadsContext`. Jedyna zmiana w rdzeniu wynikająca z planu.
- **`format.percent`** w obu katalogach — odstęp przed znakiem procenta jest
  sprawą języka, nie komponentu.
- **Scenariusz `progress`** w `bin/render-bench` wraz z etykietami. Chwila dla
  wędrującego wypełnienia bierze się z numeru wiersza, a nie z zegara, bo klatka
  musi być powtarzalna co do bajtu.
- **Naprawa `Weight::Fill`** w enkoderze Sixela — poza planem, wymuszona przez
  pomiar.

**Co sprawdziło się samo z siebie i warto to zapisać.** Najtrudniejsza część
kroku — „takt: kto każe przerysować klatkę”, opisana w planie jako jedyna
wychodząca poza warstwę `Ui` — **okazała się nie istnieć**. Pętla rysuje w takcie
od kroku 09 i nigdy nie oszczędzała klatek przy bezruchu, więc pasek nie miał
czego wymuszać. Plan kazał przeczytać stan pętli z kodu, nie z pamięci, i to była
dobra instrukcja: gdyby ktoś odtworzył ją z pamięci, dołożyłby metodę do
`ScreenInterface` albo warunek do `GameLoop` — jedno i drugie bez powodu.

W zamian krok wykonał pracę, której plan nie przewidywał: **znalazł i naprawił
błąd wydajnościowy w kodzie z kroku 17**. Jest w tym symetria warta odnotowania —
plan zakładał, że pasek postępu będzie pierwszym elementem świadomie stojącym
poza pamięcią podręczną segmentów, a okazał się przy okazji pierwszym
użytkownikiem prymitywu, którego nikt nigdy nie zmierzył.

**Testy:** 23 nowe (`ProgressBarTest` — 21, `ScreenClockTest` — 2) plus 2
w `ScenarioFactoryTest`, razem **880** zielonych. `ProgressBarTest` jest
świadomie przesadzony wobec dzisiejszych potrzeb, bo do kroku 25 jest **jedynym**
sprawdzianem tego komponentu: przechodzi po kolei wszystko, czego krok 25 od
paska zażąda. `ScreenClockTest` sprawdza całą drogę zegara, a nie samo wywołanie
— podaje dwie chwile i patrzy, czy wypełnienie w klatce naprawdę stoi w dwóch
różnych miejscach; test na samo `useTime()` przeszedłby także wtedy, gdyby
składanie klatki wołało je **po** narysowaniu ekranu.
