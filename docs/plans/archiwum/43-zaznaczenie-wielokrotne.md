# Krok 43 — Zaznaczenie wielokrotne jako mnożnik operacji

> **Skąd ten krok.** Powstał 2026-08-13 razem z całą Fazą XIV. Zamyka pozycję
> „Zaznaczenie wielokrotne”, którą **krok 32 wyłączył ze swojego zakresu**
> („zaznaczenie wielokrotne to osobna funkcja, której nie ma”) i która od tamtej
> pory stoi w „Zakresie poza MVP”. Pełne uzasadnienie fazy:
> [00-decyzje.md](../00-decyzje.md), D66.

## Status

**Ukończony** (2026-08-15).

## Cel

Operacje przestają dotyczyć jednego wpisu. Zaznaczone wpisy są **zbiorem**, na
którym działa każda czynność z kroków 41 i 42 — a gdy zbiór jest pusty, czynność
dotyczy wpisu pod kursorem, dokładnie jak dziś.

Miarą powodzenia jest zdanie: **zaznaczenie dwunastu plików i naciśnięcie klawisza
kopiowania kopiuje dwanaście plików, a pytanie potwierdzenia mówi „12”, a nie
nazwę pierwszego z nich.**

## Trudność strukturalna — najważniejsza treść tego pliku

**Kontekst sesji zna jeden wpis.** `ModuleContext` niesie ścieżkę katalogu, nazwę
zaznaczonego wpisu i jego rodzaj — i na tym stoi cała współpraca modułów: moduł
opisu pliku pokazuje to, co przeglądarka ogłosiła. Zbiór do tego kontraktu nie
wchodzi.

Rozstrzygnięcie (pytanie 1) ma dwa kierunki i **oba są uczciwe**:

1. **Kontekst zostaje jednowpisowy.** Zaznaczenie wielokrotne jest wtedy
   własnością przeglądarki, o której inne moduły nie wiedzą — kontekst nadal
   ogłasza wpis **pod kursorem**, bo to on jest tym, na co użytkownik patrzy.
   Nic w rdzeniu nie rośnie. Ceną jest to, że moduł opisu pliku nigdy nie pokaże
   „12 zaznaczonych, razem 4,1 GB”.
2. **Kontekst rośnie o liczbę i rozmiar zaznaczenia.** Rdzeń dostaje pojęcie
   zbioru; każdy przyszły moduł może z niego skorzystać. Ceną jest zmiana
   kontraktu rdzenia dla funkcji, której dziś potrzebuje jeden moduł — czyli
   dokładnie to, przed czym stoi reguła 15.

Rekomendacja: **wariant 1**. Krok 41 wziął już jeden wyjątek od reguły 15 (port
operacji w rdzeniu) i wziął go z powodu, którego tutaj nie ma: tam chodziło
o niepowielanie kodu piszącego po dysku, tu o wygodę jednego przyszłego widoku.

Druga trudność jest mniejsza, ale realna: **zaznaczenie musi przeżyć filtr**.
Krok 30 ustalił, że filtr jest widokiem na katalog, a zaznaczenie przenosi się
**po nazwie**, nie po numerze. Zbiór trzyma się więc nazw i to samo rozstrzygnięcie
obowiązuje go bez zmian — z jednym dopiskiem, którego filtr nie potrzebował:
**wpis zaznaczony i niewidoczny (bo odfiltrowany) nadal należy do zbioru**, więc
operacja dotknie czegoś, czego nie widać. Pytanie 4 rozstrzyga, czy to jest
dopuszczalne, czy zawężenie filtrem czyści zaznaczenie.

## Zależności

- **Krok 41** twardo: zbiór jest mnożnikiem czynności, które powstają tam. Bez
  nich zaznaczanie jest zaznaczaniem dla samego zaznaczania.
- **Krok 30** — reguła „zaznaczenie przenosi się po nazwie” oraz `NameFilter`,
  czyli drugi widok na tę samą listę.
- **Krok 27** — `ListRow` i kolumny, czyli miejsce, w którym znacznik zaznaczenia
  ma się zmieścić bez zabierania nazwie kolumn.
- **Krok 21** — `ModuleContext` i reguła „publikacja jest tam, gdzie zmiana”.
- **Krok 28** — pytanie potwierdzenia, które przy zbiorze mówi liczbą.
- **Krok 38** — wzorce i przebiegi.

Z krokiem **42** łączy go zależność **miękka, w obie strony**: jeśli 42 wykona się
pierwszy, praca kawałkowa przyjmuje listę źródeł od pierwszego dnia (punkt 2 tamtego
kroku) i ten krok jedynie tę listę wypełnia. Jeśli 43 wykona się pierwszy, to on
przynosi zbiór, a 42 od razu dostaje prawdziwego użytkownika listy. **Zalecana
kolejność: 42 przed 43** — kopiowanie jednego pliku jest łatwiejsze do zmierzenia
niż kopiowanie dwunastu.

Od kroków **31, 32, 36, 37, 39 i 40** nie zależy i one nie zależą od niego.

## Model i wysiłek

**Opus / high.**

Zmiana jest skupiona — stan panelu, wiersz listy, pytania okien, przypadki użycia
— ale dotyka miejsca, przez które przechodzi każda klatka przeglądarki. Wysiłek
poniżej `xhigh`, bo w odróżnieniu od kroków 41 i 42 **nic tu nie jest
nieodwracalne**: zaznaczenie źle narysowane widać od razu.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Module/Browser/…/BrowserState` | Katalog pełny i katalog zawężony filtrem; zaznaczenie **jedno**, trzymane w agregacie `Directory`. |
| `Module/Browser/…/Directory::selectEntryNamed()` | Przenoszenie zaznaczenia po nazwie — gotowy wzorzec dla zbioru. |
| `Module/Browser/…/Component/EntryList` | Rysuje wiersze; kolumny szczegółów ustępują w wąskim panelu (krok 27). |
| `Application/Module/ModuleContext` | Ścieżka, **jedna** nazwa, rodzaj wpisu. |
| `Application/Dto/Key` | **Nie ma `Insert`**; spacja przychodzi jako `Character` z `raw === ' '`. |
| `BrowserScreen::character()` | Obsługuje `.` (ukryte) i `/` (filtr) — pozostałe znaki są dziś nieużywane. |
| `Module/Browser/…/Component/PathLine` | Pas ścieżki wraz z numerem zaznaczenia — miejsce na podsumowanie zbioru. |
| `tests/Golden/selection.txt` | Nazwa `selection` jest **zajęta** przez scenariusz kursora na liście — nowy scenariusz musi nazywać się inaczej. |

## Zakres

### 1. Zbiór zaznaczonych w stanie panelu

Zbiór nazw, obok filtra, w `BrowserState` — a nie w agregacie `Directory`. Powód
jest ten sam, który w kroku 30 postawił tam filtr: **dwa panele otwarte na tym
samym katalogu mają prawo mieć różne zaznaczenia**, a katalog na dysku jest jeden.

Zbiór znika przy wejściu do innego katalogu — tak samo jak filtr i z tego samego
powodu: nazwy zaznaczone tutaj w katalogu obok znaczą co innego albo nic.

### 2. Klawisze

- **Spacja** — przełącza zaznaczenie wpisu pod kursorem i przesuwa kursor
  w dół (klasyka menadżerów; pytanie 2 rozstrzyga, czy przesuwa).
- **`*`** — odwrócenie zaznaczenia w widocznej liście.
- **`Esc`** — czyści zaznaczenie; klawisz jest już w przeglądarce zajęty przez
  zdejmowanie filtra, więc kolejność ustępowania (najpierw filtr, potem
  zaznaczenie) jest **rozstrzygnięciem, nie szczegółem** (pytanie 3).

Zaznaczanie zakresem (`Shift`+strzałki) **odpada**: modyfikatora `Shift` nie ma
w słowniku wejścia w żadnym z trzech torów, a jego wprowadzenie jest osobną
robotą (opisaną w kroku 44).

### 3. Wygląd

Wiersz zaznaczony ma być rozpoznawalny **bez** kursora na nim i **przy** kursorze
na nim — to są dwa różne stany i oba muszą być widoczne naraz. Kierunek
(pytanie 5): znacznik w pierwszej kolumnie, inna rola napisu, albo jedno i drugie.
Rola `Accent` jest zajęta przez kursor, `Muted` przez wpisy ukryte.

Znacznik **nie zabiera kolumny nazwie**, gdy zbiór jest pusty — panel bez
zaznaczenia ma wyglądać co do znaku jak dziś. To jest wymóg wzorców, nie estetyki.

### 4. Podsumowanie w pasie ścieżki

„12 z 340 · 4,1 GB” — liczba zaznaczonych i suma ich rozmiarów. Rozmiary wpisów są
w `Entry` od kroku 27, więc suma nic nie kosztuje; **katalogi liczą się jako zero**
i to musi być widoczne w napisie, bo inaczej suma kłamie (zajętość katalogu umie
policzyć wyłącznie `du` z kroku 26).

### 5. Operacje biorą zbiór

Kopiowanie, przenoszenie i usuwanie działają na zbiorze; zmiana nazwy i nowy
katalog **zostają jednowpisowe** — nazwa jest jedna z definicji.

Reguła pustego zbioru: **brak zaznaczenia znaczy „wpis pod kursorem”**, a nie
„nic”. Inaczej każda operacja wymagałaby dwóch kroków tam, gdzie dziś wymaga
jednego.

Pytania potwierdzenia mówią liczbą: „Usunąć 12 wpisów?” zamiast nazwy pierwszego.
Napis ma **liczbę mnogą po polsku**, czyli trzy formy (1 / 2–4 / 5+) — katalog
napisów tego dziś nie umie i to jest jedyne miejsce, w którym ten krok sięga do
rdzenia (pytanie 6).

### 6. Zaznaczenie po operacji

Wpisy, które zniknęły, znikają ze zbioru. Wpisy, których operacja nie dotknęła
(pominięte przy kolizji, nieudane), **zostają zaznaczone** — to jest jedyna droga,
którą użytkownik dowie się, co się nie udało, bez czytania listy błędów, której
aplikacja nie ma.

### 7. Napisy, pomiar, wzorce

- napisy: znacznik, podsumowanie, pytania w liczbie mnogiej, opisy klawiszy;
- pomiar: **nowy scenariusz** — nazwa `selection` jest zajęta przez kursor, więc
  scenariusz zaznaczenia potrzebuje własnej (`marked`). Powstaje, bo wiersz
  z dodatkowym znacznikiem i drugą rolą napisu to inny koszt niż wiersz zwykły,
  a lista jest najczęściej rysowaną rzeczą w aplikacji;
- wzorce: złota klatka scenariusza oraz PNG w trzech torach;
- przebieg funkcjonalny: zaznaczenie, filtr, operacja na zbiorze, sprawdzenie, co
  zostało zaznaczone po jej wykonaniu.

Reguła 17 obowiązuje przed pomiarem.

## Poza zakresem

- **Zaznaczanie zakresem (`Shift`+strzałki)** — brak `Shift` w słowniku wejścia.
- **Zaznaczanie wzorcem (`+` i `-` z maską)** — to jest drugi filtr, a filtr
  aplikacja ma od kroku 30; jeśli ma powstać, to jako *zaznacz wszystko, co widać
  po zawężeniu*.
- **Zaznaczenie przeżywające zmianę katalogu** — zbiór ginie razem z katalogiem,
  jak filtr.
- **Zaznaczenie w obu panelach naraz jako jedno źródło operacji** — źródłem jest
  panel czynny; zbiór drugiego panelu jest jego własnym.
- **Kolejka „schowka”** (zaznacz tu, wklej gdzie indziej później) — to jest inna
  funkcja niż zaznaczenie i wymaga własnego stanu poza panelem.
- **Pokazywanie zaznaczenia w module opisu pliku** — wedle rekomendacji
  z rozstrzygnięcia 1 kontekst zostaje jednowpisowy.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Browser/Domain/ValueObject/MarkedEntries.php` | Moduł | Nowe — zbiór nazw wraz z liczbą i sumą rozmiarów; `final readonly`, samowalidacja. |
| `Module/Browser/Presentation/BrowserState.php` | Moduł | Zbiór obok filtra; czyszczenie przy `enter()`; przenoszenie po nazwie. |
| `Module/Browser/Presentation/Component/EntryList.php` | Moduł | Znacznik i rola wiersza zaznaczonego; brak kosztu przy pustym zbiorze. |
| `Module/Browser/Presentation/Component/PathLine.php` | Moduł | Podsumowanie zbioru. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | Spacja, `*`, kolejność ustępowania `Esc`; przekazanie zbioru do operacji. |
| `Module/Browser/Application/UseCase/*` | Moduł | Operacje przyjmują zbiór; pusty zbiór znaczy „wpis pod kursorem”. |
| `Application/Port/TranslatorPort.php` + `Infrastructure/I18n/*` | Rdzeń | Liczba mnoga w napisach — wedle rozstrzygnięcia 6. |
| `Module/Browser/lang/pl.php`, `lang/en.php` | Napisy | Znacznik, podsumowanie, pytania w liczbie mnogiej, opisy klawiszy. |
| `Infrastructure/Diagnostics/Scenario.php`, `ScenarioFactory.php` | Infrastructure | Scenariusz `marked`. |
| `tests/Golden/marked.txt`, `docs/pomiary/wzorce-png/` | Wzorce | Nowa złota klatka i PNG w trzech torach. |
| `tests/Functional/MarkedEntriesFlowTest.php` | Testy | Zaznaczenie, filtr, operacja na zbiorze, stan po niej. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Zbiór jako własność panelu; reguła pustego zbioru. |

## Do rozstrzygnięcia na starcie kroku

1. **Kontekst sesji** — zostaje jednowpisowy (rekomendacja) czy rośnie o zbiór.
2. **Spacja przesuwa kursor w dół** — tak (klasyka, szybkie zaznaczanie ciągu) czy
   nie (przewidywalność).
3. **Kolejność ustępowania `Esc`** — najpierw filtr, potem zaznaczenie, czy
   odwrotnie; czy `Esc` przy obu pustych nadal wraca do przeglądarki.
4. **Filtr a zbiór** — czy wpis zaznaczony i odfiltrowany nadal należy do zbioru
   (operacja dotknie niewidocznego), czy zawężenie czyści zaznaczenie.
5. **Wygląd wiersza zaznaczonego** — znacznik w kolumnie, inna rola napisu, czy
   jedno i drugie; jak wygląda wiersz zaznaczony **i** pod kursorem.
6. **Liczba mnoga w napisach** — trzy formy w katalogu (zmiana w rdzeniu), czy
   napis omijający odmianę („zaznaczonych: 12”).
7. **Zaznaczanie katalogów** — czy katalog wolno zaznaczyć na równi z plikiem
   (wtedy suma rozmiarów kłamie i musi to powiedzieć), czy zbiór bierze tylko pliki.
8. **`*` przy włączonym filtrze** — odwraca zaznaczenie w liście widocznej czy
   w pełnej.

## Kryteria ukończenia

- Zaznaczenie dwunastu wpisów i jedna czynność dotyczy dwunastu wpisów; pytanie
  potwierdzenia mówi liczbą.
- Pusty zbiór znaczy „wpis pod kursorem” — każda czynność działa jak przed krokiem.
- Panel bez zaznaczenia wygląda **co do znaku** jak przed krokiem; dowodzą tego
  niezmienione wzorce pozostałych scenariuszy.
- Zaznaczenie przeżywa zawężenie filtrem wedle rozstrzygnięcia 4 i przenosi się po
  nazwie, nie po numerze.
- Zbiór ginie przy wejściu do innego katalogu.
- Po operacji zaznaczone zostaje **to, czego nie dotknęła**.
- Nowy scenariusz `marked` zmierzony w trzech torach; wzorce przeliczone.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-15 — wykonanie

Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md), D80 (osiem pytań
planu, jedno dodatkowe wynikłe ze stanu zastanego i jedno — nr 5a — podjęte
w trakcie, po obejrzeniu wzorca PNG).

**1. Jedno założenie planu było nieaktualne, i to na korzyść.** Pytanie nr 6
opierało się na zdaniu „katalog napisów nie umie liczby mnogiej”. Umie od kroku
15 (`TranslatorPort::plural()`, `PluralRule::Slavic`), a kroki 41 i 42 z tego
korzystają. Bez drogi do form mnogich zostało **jedno miejsce**:
`ConfirmOverlay`, który zawsze wołał `translate()`. Zapowiadana „jedyna zmiana
w rdzeniu” zeszła przez to z przebudowy katalogu do **jednego opcjonalnego
parametru okna** (`?int $count`). Reszta tabeli stanu zastanego zgadzała się co
do wiersza.

**2. Kontekst sesji urósł — wbrew rekomendacji planu.** Użytkownik wybrał wariant
2 (D80 nr 1), więc `ModuleContext` niesie trzy liczby: liczbę zaznaczonych, sumę
rozmiarów plików i liczbę katalogów, o które ta suma milczy. Warunek postawiono
od razu i jest nim reguła 13: **odbiorca wszedł razem z mechanizmem** — moduł
opisu pliku pokazuje przy niepustym zbiorze „Zaznaczono 12 wpisów · razem 4,1 GB”
zamiast ścieżki. Punkt „Poza zakresem” („pokazywanie zaznaczenia w module opisu
pliku”) został przez to **odwołany**: był konsekwencją wariantu, który odpadł.

**3. Port usuwania bierze listę i to jest cała zmiana, jakiej wymagał od rdzenia
zbiór.** `beginRemoval(list<string>)` — dokładnie tak, jak `FileTransferPort::begin()`
od kroku 42, którego docblock zapowiadał „lista, nie jeden wpis (…) krok 43 doda
resztę”. Zapowiedź sprawdziła się co do słowa: `EntryTransfer` **nie zmienił ani
jednej linii pracy**, tylko wypełnił listę, którą tamten krok zostawił pustą.
Kopiowanie zbioru działało od pierwszego uruchomienia.

**4. Reguła pustego zbioru stanęła w jednym miejscu.** `BrowserState::operands()`
i `BrowserPanes::focusedOperands()` odpowiadają na pytanie „na czym mam działać”;
czynność nie wie, czy lista przyszła ze zbioru, czy z kursora. Bez tego każda
z pięciu czynności zadawałaby sobie to samo pytanie osobno — a rozjechałyby się
przy pierwszej poprawce, jak zawsze (precedens `HiddenEntries` z kroku 32).

**5. Zaznaczenie jest własnością listy i to rozstrzygnięcie miało skutki
w czterech miejscach.** Plan pisano przed sprawdzeniem, że panel ma od kroku 31
dwa widoki; drzewo zaznaczenia nie zna (D80 nr 9), więc zbiór **przeżywa**
przełączenie widoku, ale dopóki widać drzewo, nie istnieje dla nikogo: ani dla
znacznika, ani dla podsumowania w pasie ścieżki, ani dla czynności, ani dla
stopki. Inaczej `F8` w drzewie usuwałoby dwanaście wpisów, których nie widać.

**6. Rola `Warning` okazała się rolą bez koloru — i złapał to dopiero wzorzec
PNG.** Pierwsza wersja malowała wiersz zaznaczony `Warning`iem i przeszła
**wszystkie testy**, bo testy patrzą na rolę, nie na kolor. Zrzut wzorcowy
pokazał, czego w prymitywach nie widać: w motywie Grafit `warning` i `accent` to
ten sam `#d9a441` (jeden nasycony kolor jest tam zasadą, D25), więc zaznaczony
plik wyglądał w domyślnym motywie jak katalog. Rozstrzygnięcie użytkownika
(D80 nr 5a): **dwunasta rola motywu**, `Role::Marked`, zieleń w czterech
paletach — pierwsza rola dołożona od kroku 13. Cena: Grafit ma odtąd dwa
nasycone kolory. Wniosek ogólny wart więcej niż sama decyzja: **rola dobrana
znaczeniowo, bez przejrzenia czterech palet, bywa rolą bez koloru**.

**7. Wzorzec pomiarowy też trzeba było obejrzeć, a nie tylko zapisać.** Przy
zaznaczeniu „co trzeciej” pozycji **każdy katalog wypadał zaznaczony** (bo 6
dzieli się przez 3), więc z klatki nie dało się odczytać, czy rola zaznaczenia
odróżnia się od akcentu. Rytm zmieniony na trzy z siedmiu daje w jednym wzorcu
wszystkie cztery kombinacje: plik i katalog, zaznaczony i nie. Rytmu **katalogów**
ruszyć nie było wolno — dzielą go `columns` i `highlight`.

**8. Prawdziwa klatka wypatrzyła dwie rzeczy, których nie widział żaden test.**
Aplikacja uruchomiona pod XTermem (`bin/run.sh`), zaznaczone trzy wpisy, zrzut
projektowym narzędziem (`core.dump`, krok 38) — i na obrazie od razu widać:

- **stopka obiecywała klawisz, którego nie widać**: `·   zaznacz` zamiast
  `· Space zaznacz`, bo `KeyBinding::display()` wypisywał spację jako spację.
  Testy tego nie widziały, bo porównują **klucz opisu**, a nie napis z nazwą
  klawisza. Poprawka: `KeyBinding::NAMED_CHARACTERS` — znak, który sam z siebie
  nic nie rysuje, dostaje nazwę, tą samą drogą co `Esc` i `PgUp` (czyli **nie**
  przez katalog napisów: to napis z klawiatury, nie zdanie interfejsu);
- **stopka mierzona przestała być stopką aplikacji**: `ScenarioFactory::HINTS`
  nie znała dwóch nowych pozycji. Krok 40 przepisał tę stałą dokładnie z tego
  powodu, więc dopisanie ich tutaj jest wykonaniem tamtej reguły, a nie nową
  decyzją.

Trzecia rzecz, którą klatka **potwierdziła**: kolumna znacznika przeżywa
ustępowanie kolumn. W panelu lewym (węższym po podziale) zniknęły „Prawa”,
a znacznik został — czyli drabinka `MARK_YIELD_ORDER = 4` działa tak, jak ją
opisano.

### Pomiar

Reguła 17 dopełniona: użytkownik zwolnił maszynę przed przebiegami. Wzorce:
`2026-08-15-po-kroku-43{,-text,-window,-loop}.json`.

| Tor | `columns` (lista bez zaznaczenia) | `marked` (43 % wierszy zaznaczonych) | Różnica |
|---|---|---|---|
| Sixel | 20,6 ms | 27,7 ms | **+7,1 ms (+34 %)** |
| tekstowy | 1,1 ms | 1,2 ms | poniżej rozdzielczości |
| okienkowy | 0,7 ms | 0,7 ms | brak |

**Lista bez zaznaczenia nie zdrożała** — to jest główne kryterium kroku i jest
spełnione: `columns` −0,1 % wobec wzorca po kroku 42, czyli w rozrzucie. Takt
pętli (`--loop`) +0,2 %, tor tekstowy −6 % (całą kolumną, więc to środowisko,
nie kod).

**Zaznaczenie kosztuje natomiast w torze sixelowym — i to nie znacznik, tylko
kolor.** Rozbicie nie pozostawia wątpliwości: rysowanie +0,5 ms (piąta kolumna,
czyli tyle, ile powinna), **kwantyzacja +6,4 ms**. To jest cena **dwunastej roli
motywu** (D80 nr 5a): druga barwa nasycona znaczy drugą rampę półcieni, którą
kwantyzator musi zmieścić w palecie — dokładnie to, przed czym ostrzegał D25
(„akcent jest jedynym nasyconym kolorem, a paleta Sixela idzie na półcienie liter
zamiast na barwy”).

Trzy pomiary, które tę cenę **obwarowują**, bo sama liczba brzmiałaby groźniej,
niż jest:

| Ile wierszy zaznaczonych | `columns` | `marked` | Różnica |
|---|---|---|---|
| 2 z 40 (tyle, ile zaznacza się ręką) | 20,9 ms | 21,9 ms | +1,0 ms |
| 43 % (scenariusz, czyli sufit) | 20,6 ms | 27,7 ms | +7,1 ms |

Koszt **rośnie z liczbą zielonych pikseli**, a nie z samą obecnością nowej barwy
w palecie — realne zaznaczenie kilku plików kosztuje około milisekundy. Sufit
(ekran zaznaczony gwiazdką) mieści się w budżecie klatki: 27,7 ms wobec 33 ms,
czyli mniej więcej tyle, co klatka z miniaturą (28,1 ms), która jest
najdroższa w projekcie od kroku 12.

Sprawdzone ponadto, że **nie chodzi o ciasnotę palety**: przy 256 kolorach
różnica zostaje (+6,8 ms), a przy 16 maleje do +2,1 ms razem z całą kwantyzacją.
Podnoszenie `PALETTE_COLORS` nic by więc nie dało.

Dwie liczby w torze okienkowym przekroczyły próg przy pierwszym przebiegu
(`sections` +10,4 %, `text-view` +10,7 %) i **obie okazały się szumem**: to
różnice rzędu 0,05 ms przy medianach 0,5–0,7 ms, a powtórzony przebieg dał
−0,7 % i +2,5 %. Ta sama historia w torze sixelowym z `popup` (+20,6 % → +1,4 %).
Wzorzec sixelowy odmówił zapisu **trzykrotnie** (rozrzut powyżej 1,35× w jednym
wierszu za każdym razem, innym) — zapisał się za czwartym, dokładnie jak
w kroku 22.

**Kryterium „panel bez zaznaczenia wygląda co do znaku jak przed krokiem”
sprawdzone wprost i spełnione.** `--png-compare` przed dopisaniem pozycji do
`HINTS` dał **zero różniących się pikseli we wszystkich osiemnastu scenariuszach
sprzed kroku** w obu torach obrazowych — łącznie z `columns`, `highlight`
i `tree`, mimo dwunastej roli w motywie.

Potem wzorce **zmieniły się w sześciu scenariuszach** i wyłącznie z powodu
stopki: różnica to 785 pikseli (1,31 ‰) leżących w całości w dwóch wierszach
paska stanu, co widać na obrazie różnicy i w złotych klatkach (jedna linijka na
plik, `T28`). Scenariusze bez paska stanu — a więc wszystkie mierzące **listę** —
zostały nietknięte co do bajtu. Rozdzielenie tych dwóch przebiegów jest tu
istotne: gdyby wzorce przeliczyć raz, na końcu, nie dałoby się odróżnić „lista
się nie zmieniła” od „lista się zmieniła, ale niewiele”.

Uwaga praktyczna na przyszłość: **w torze okienkowym `--png-save` przepisuje
wszystkie pliki**, także te, których treść się nie zmieniła — renderer OpenGL nie
jest bajtowo powtarzalny (stąd próg 5 ‰ zamiast zera). Zapisując wzorce po
zmianie dotyczącej części scenariuszy, trzeba podać `--scenarios=`, inaczej
w repozytorium ląduje kilkanaście plików różniących się szumem.

Toru **tekstowego wzorców PNG nie ma i mieć nie może** — renderer tekstowy
oddaje ANSI, nie obraz. Zdanie planu o „PNG w trzech torach” opisuje stan, którego
projekt nigdy nie miał: tory obrazowe są dwa (`sixel-*`, `window-*`).

### Zmiany w rdzeniu, których plan nie przewidywał

Krok miał sięgnąć do rdzenia **raz** (formy mnogie w katalogu napisów) i to
jedno miejsce okazało się już zrobione. Sięgnął zamiast tego w cztery:

| Miejsce | Powód |
|---|---|
| `Application/Module/ModuleContext` | rozstrzygnięcie użytkownika wbrew rekomendacji planu (D80 nr 1), wraz z odbiorcą w module opisu pliku |
| `Presentation/Ui/Overlay/ConfirmOverlay` | jedyne miejsce bez drogi do form mnogich — jeden opcjonalny parametr |
| `Application/Port/FileOperationsPort` | lista ścieżek zamiast jednej, wzorem portu kopiowania z kroku 42 |
| `Application/Ui/Role` + `Infrastructure/Rendering/Theme` i trzy renderery | dwunasta rola motywu (D80 nr 5a), bo `Warning` okazał się w Grafitcie rolą bez własnego koloru |

Piąte miejsce — `Presentation/Ui/KeyBinding` — jest **poprawką usterki starszej
od kroku**, a nie zmianą na jego potrzeby: klawisz, którego znak nic nie rysuje,
nie miał jak się przedstawić. Zaznaczanie spacją było pierwszym takim klawiszem
w projekcie, więc usterka ujawniła się dopiero tutaj.

### Czego krok nie dowiózł

- **Zaznaczanie zakresem (`Shift`+strzałki)** — poza zakresem od początku, bo
  `Shift` nie istnieje w słowniku wejścia; czeka na krok 44.
- **Zaznaczenie w drzewie** — rozstrzygnięcie D80 nr 9, opisane wyżej.
- **Zaznaczanie wzorcem (`+`/`-` z maską)** i **kolejka schowka** — poza zakresem
  planu, bez zmian.
