# Krok 32 — Menu kontekstowe jako okno rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26 (D48). Jedyna z sześciu propozycji, którą **odradzałem**, i mimo to przyjęta
> — powód odrzucenia rekomendacji oraz warunek, na jakim krok ma sens, są zapisane
> niżej wprost.

## Status

**Nie rozpoczęty** (2026-08-11).

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

*(pusty — krok nierozpoczęty)*
