# Krok 32 — Menu kontekstowe jako okno rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26 (D48). Jedyna z sześciu propozycji, którą **odradzałem**, i mimo to przyjęta
> — powód odrzucenia rekomendacji oraz warunek, na jakim krok ma sens, są zapisane
> niżej wprost.

## Status

**Ukończony** (2026-08-14).

## Cel

Dać listę działań dostępnych **dla tego, co jest zaznaczone** — bez konieczności
pamiętania klawisza ani nazwy komendy.

Miarą powodzenia jest zdanie: **naciśnięcie jednego klawisza na zaznaczonym pliku
pokazuje wyłącznie te działania, które da się na nim wykonać, a wybranie
któregokolwiek robi dokładnie to samo, co komenda o tej samej nazwie.**

## Zastrzeżenie do rozstrzygnięcia na starcie — najważniejsza treść tego pliku

**Menu kontekstowe nakłada się z oknem komend z kroku 19.** Tamto okno już jest
listą działań z podpowiedziami, filtrowaniem po wpisanym przedrostku i historią.
Menu wnosi ponad nie dokładnie dwie rzeczy — **wybór bez pisania** i **zawężenie
do tego, co dotyczy zaznaczenia** — a wszystko poza tym powtarza.

Z tego wynika warunek, pod którym ten krok ma sens, i trzeba go sprawdzić, zanim
powstanie pierwsza linia kodu: **menu ma być widokiem na rejestr komend, a nie
drugim rejestrem działań.** Jeśli po rozpisaniu okaże się, że menu potrzebuje
własnej listy pozycji, własnych etykiet i własnej obsługi — krok należy odłożyć
do czasu, aż powstaną operacje na plikach, bo dopiero one dadzą mu treść, której
okno komend nie ma.

Wariant do rozważenia zamiast osobnego okna: **okno komend otwarte w trybie
zawężonym do zaznaczenia**, czyli ta sama klasa z innym źródłem podpowiedzi.

## Zależności

- **Krok 19** (okno komend) twardo i podwójnie: stamtąd pochodzi `CommandRegistry`
  — czyli **jedyne uczciwe źródło pozycji menu** — oraz `OverlayInterface`
  z regułą „okno zużywa albo przepuszcza klawisz”.
- **Krok 28** (okno potwierdzenia) twardo — tam rozstrzyga się, **jak okno oddaje
  decyzję**, a menu jest drugim oknem, które czegoś chce od wołającego. Robienie
  tego kroku wcześniej znaczyłoby rozstrzygnąć tę samą rzecz dwa razy.
- **Krok 20** (moduły) — komendy wnoszą moduły, więc pozycje menu też.
- **Krok 21** (przeglądarka jako moduł) — bo tam jest zaznaczenie, którego menu
  dotyczy.

## Model i wysiłek

**Opus / high.**

Kodu niewiele, a cała trudność leży w **niepowtarzaniu okna komend**. Krok jest
z natury podatny na to, żeby wyjść z niego z dwoma równoległymi listami działań,
które trzeba potem uzgadniać przy każdej nowej komendzie — czyli z długiem
opisanym w regule 15 („dopisanie modułu ma kosztować jedną zmianę w rdzeniu”).

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Application/Command/CommandRegistry` | Rejestr komend wraz z przestrzeniami nazw modułów — **kandydat na jedyne źródło pozycji** |
| `Presentation/Ui/Overlay/CommandOverlay` | Okno komend: lista podpowiedzi, pole tekstowe, historia — **to, czego menu nie ma prawa powtórzyć** |
| `Presentation/Ui/Overlay/Suggestion` | Pozycja podpowiedzi — być może gotowa pozycja menu |
| `Application/Module/ModuleContext` | Ścieżka, nazwa i rodzaj zaznaczenia — **to, wedle czego menu zawęża listę** |
| `Application/Command/**` | Sprawdzić, czy kontrakt komendy potrafi dziś powiedzieć „dotyczę katalogów” — jeśli nie, **to jest prawdziwy zakres tego kroku** |

## Zakres

### 1. Zdolność komendy: „czego dotyczę”

Prawdopodobnie **główna część kroku**, mimo że nie jest komponentem. Komenda musi
umieć powiedzieć, dla jakiego zaznaczenia ma sens — inaczej menu pokaże wszystko,
czyli nie zawęzi niczego, czyli będzie oknem komend bez pola tekstowego.

Kształt do rozstrzygnięcia; kierunek: zdolność deklarowana osobnym interfejsem,
jak `ProvidesCommands` i `ReadsContext` z kroku 20 — a nie nowa metoda
w kontrakcie komendy, którą musiałyby wypełniać wszystkie.

### 2. Okno

Lista pozycji z kursorem, `Enter` wybiera, `Esc` zamyka, litera przeskakuje do
pozycji. Prostokąt przy zaznaczeniu albo na środku — do rozstrzygnięcia.
Zawartość składa się z `ListView` i `Dialog`, więc **komponent nie powstaje ani
jeden**.

### 3. Odbiorca

Zaznaczony wpis w przeglądarce: `browser.jump` dla katalogu, otwarcie opisu
pliku, przełączniki widoku. Lista jest dziś krótka i **to jest właśnie powód
zastrzeżenia** ze szczytu tego pliku.

### 4. Pomiar

Scenariusz `popup` z kroku 18 wystarcza — menu składa się z tych samych
prymitywów, co okno modalne i okno komend. Nowego scenariusza nie ma.

## Poza zakresem

- **Zagnieżdżone podmenu** — jeden poziom wystarcza; drugi jest funkcją
  nawigacji, nie listy.
- **Menu obsługiwane wskaźnikiem** — rdzeń go nie ma.
- **Menu dla wielu zaznaczonych wpisów naraz** — zaznaczenie wielokrotne to
  osobna funkcja, której nie ma.
- **Zastąpienie okna komend menu** — okno komend zostaje; menu jest **drugim
  wejściem do tego samego rejestru**.
- **Skróty klawiszowe pozycji menu** — od tego są wiązania klawiszy ekranu.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Command/**` | Application | Zdolność „czego dotyczę” — kształt wedle rozstrzygnięcia nr 1. |
| `Presentation/Ui/Overlay/MenuOverlay.php` | Presentation | Nowe — albo tryb `CommandOverlay`, wedle rozstrzygnięcia nr 2. |
| `Module/Browser/**` | Moduł | Klawisz otwierający menu; komendy deklarują, czego dotyczą. |
| `lang/pl.php`, `lang/en.php` | Napisy | Nagłówek menu, pusty stan. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Menu jako **drugie wejście do rejestru komend**, nie drugi rejestr. |
| testy | Testy | Zawężenie do rodzaju zaznaczenia, pusty stan, wybór wykonujący komendę, `Esc`, brak powielenia rejestru. |

## Do rozstrzygnięcia na starcie kroku

1. **Czy krok w ogóle wchodzi teraz** — patrz zastrzeżenie na szczycie. To
   rozstrzygnięcie zapada **pierwsze**, przed wszystkimi pozostałymi.
2. **Osobne okno czy tryb okna komend.**
3. **Kształt zdolności „czego dotyczę”** — interfejs zdolności czy metoda
   w kontrakcie komendy.
4. **Gdzie stoi prostokąt menu** — przy zaznaczeniu czy na środku.
5. **Co pokazuje menu, gdy pasuje zero komend** — pusty stan czy brak otwarcia.

## Kryteria ukończenia

- Menu pokazuje wyłącznie działania mające sens dla zaznaczenia.
- Wybranie pozycji robi **dokładnie to samo**, co komenda o tej nazwie —
  sprawdza to test wołający obie drogi.
- **Nie powstał drugi rejestr działań**: dopisanie komendy modułu nadal kosztuje
  jedną zmianę w module i zero w rdzeniu.
- Komponent rdzenia nie powstał ani jeden.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-14 — krok wykonany

**Zastrzeżenie ze szczytu pliku sprawdziło się co do słowa — i mimo to krok
wszedł.** Przegląd rejestru przed pierwszą linią kodu dał osiem komend, z których
z zaznaczeniem związana była **jedna** (`browser.jump`, i to tylko dla katalogów);
moduł `FileInfo` wnosił zero. Menu na takim rejestrze pokazałoby jedną pozycję na
katalogu i pustą listę na pliku. Rozstrzygnięcie użytkownika nie brzmiało jednak
„odłóż” ani „zrób wąsko”, tylko **„zrób z treścią”**: krok dowozi nazwy dla
czynności, które aplikacja miała wyłącznie pod klawiszem, i dopiero na nich stawia
menu. Pełna treść rozstrzygnięć: [00-decyzje.md](../00-decyzje.md), **D69**.

W skrócie: krok wchodzi z czterema nowymi komendami; osobne `MenuOverlay`
w rdzeniu (nie tryb okna komend); zdolność `AppliesToSelection` z **dwiema**
metodami; klawisz **`F9`**, globalny; prostokąt pośrodku; menu bez pozycji
**nie otwiera się** i mówi zdaniem; wiersz — nazwa po lewej, opis po prawej.

**Co powstało.**

| Plik | Rola |
|---|---|
| `Application/Command/AppliesToSelection.php` | Zdolność „czego dotyczę”: `appliesTo()` + `inputFor()`, doklejana obok kontraktu wzorem `SuggestsArguments` |
| `Presentation/Ui/Overlay/MenuOverlay.php` | Okno: `Dialog` z `ListView` w środku, pozycje z `CommandRegistry`, migawka kontekstu przy otwarciu |
| `Module/Browser/Presentation/HiddenEntries.php` | Przełączenie wpisów ukrytych — **jedno miejsce dla dwóch wejść**, wraz z kolejnością „odczyt przed zapisem” |
| `Module/Browser/Presentation/Command/OpenCommand.php` | `browser.open` — jedyna z czwórki ze zdolnością; działa też w drzewie |
| `Module/Browser/Presentation/Command/HiddenCommand.php` | `browser.hidden` — nazwa dla kropki; zdolności **nie** deklaruje |
| `Module/Browser/Presentation/Command/TreeCommand.php` | `browser.tree` — nazwa dla `Ctrl`+`T`; zdolności **nie** deklaruje |
| `Module/FileInfo/Presentation/Command/ShowCommand.php` | `file-info.show` — nazwa dla `Ctrl`+`D`; pierwsza komenda tego modułu |
| `tests/Presentation/Ui/Overlay/MenuOverlayTest.php` | Zawężenie, pusty stan, wykonanie, `Esc`/`F9`, przepuszczanie klawiszy, brak drugiego rejestru |
| `tests/Module/Browser/Presentation/BrowserCommandsTest.php` | **Obie drogi** dla każdej komendy: klawisz i nazwa kończą w tym samym miejscu |
| `tests/Functional/ContextMenuFlowTest.php` | Przebieg `F9` → wybór → skutek, wraz z porównaniem z drogą przez okno komend |

Zmienione: `InputHandler` (`F9` w klawiszach globalnych i w `globalBindings()`),
`Bootstrap` (oba okna z **jednego** rejestru, menu w spisie sekcji pomocy),
`BrowserScreen` (przełącznik ukrytych wyprowadzony, `pointed()` sprowadzone do
`focusedDirectory()`), `BrowserPanes` (`focusedDirectory()`), `BrowserModule`
i `FileInfoModule` (listy komend), katalogi napisów rdzenia i obu modułów,
`ScreenFixture`.

**Trzy rzeczy wyszły dopiero z rozpisania** (szerzej w D69): granica menu biegnie
po **zaznaczeniu, nie po module** — dwa nowe przełączniki widoku są w rejestrze,
ale w menu ich nie ma, bo dotyczą panelu; czynność o dwóch wejściach musiała
dostać **jedno miejsce w kodzie** (`HiddenEntries`), bo jej kolejność kroków jest
pułapką, której nie wolno przepisać drugi raz; a `useContext()` zastąpił
`Resettable`, bo stos okien woła `reset()` **wewnątrz** `open()` i skasowałby
dopiero co policzone pozycje.

**Odstępstwo od zakresu — jedno i nazwane.** Litera przeskakująca do pozycji
(sekcja „Zakres”, punkt 2) **nie powstała**. Powód nie leży w koszcie, tylko
w słowniku wiązań: `KeyBinding` wyraża klawisz albo konkretny znak, ale nie
„dowolną literę”, więc funkcja nie miałaby jak trafić ani do stopki, ani do okna
pomocy — a przy dwóch pozycjach nie ma czego przeskakiwać. Wraca do rozważenia,
gdy krok 41 wydłuży listę.

**Pomiar.** Bez zmian wobec planu: nowego scenariusza nie ma, bo **nie powstał
ani jeden komponent** — okno to `Dialog` (mierzy `popup`) z `ListView` w środku
(mierzy `text`), a wierszy jest mniej, niż niesie którykolwiek z tych dwóch.
Powód pominięcia zapisany w `docs/pomiary/README.md`, w miejscu, które od kroku
38 czekało na ten krok.

**Bramka jakości:** PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy
zielone — 1412 testów, 3674 asercje (przybyło 27 testów w trzech plikach).

**Kryteria ukończenia — rozliczenie.**

| Kryterium | Stan |
|---|---|
| Menu pokazuje wyłącznie działania mające sens dla zaznaczenia | tak — `MenuOverlayTest`, `ContextMenuFlowTest` |
| Wybranie pozycji robi to samo, co komenda o tej nazwie | tak — test woła **obie drogi** (`ContextMenuFlowTest::testChoosingAnItemDoesExactlyWhatTheNamedCommandDoes`) |
| Nie powstał drugi rejestr działań | tak — pozycje z `CommandRegistry`; test dopisuje komendę do rejestru i widzi ją w menu bez zmiany w oknie |
| Komponent rdzenia nie powstał ani jeden | tak — `Dialog` + `ListView` |
| PHPStan `max`, CS-Fixer, testy | tak |
