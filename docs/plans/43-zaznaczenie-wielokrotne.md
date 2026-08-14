# Krok 43 — Zaznaczenie wielokrotne jako mnożnik operacji

> **Skąd ten krok.** Powstał 2026-08-13 razem z całą Fazą XIV. Zamyka pozycję
> „Zaznaczenie wielokrotne”, którą **krok 32 wyłączył ze swojego zakresu**
> („zaznaczenie wielokrotne to osobna funkcja, której nie ma”) i która od tamtej
> pory stoi w „Zakresie poza MVP”. Pełne uzasadnienie fazy:
> [00-decyzje.md](00-decyzje.md), D66.

## Status

**Nie rozpoczęty** (2026-08-13).

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

*(pusty — krok nierozpoczęty)*
