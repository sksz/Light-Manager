# Krok 31 — Drzewo jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26 (D48). Największa z sześciu propozycji i jedyna, która wnosi **czwartą klasę
> stanu między klatkami**.

## Status

**Nie rozpoczęty** (2026-08-11).

## Cel

Pokazać strukturę katalogów jako drzewo — z wcięciem, rozwijaniem i przewijaniem
— zamiast płaskiej listy jednego katalogu.

Miarą powodzenia jest zdanie: **lewy panel pokazuje drzewo, rozwinięcie gałęzi
o tysiącu wpisów nie gubi klatki, a to, co użytkownik rozwinął, wraca w tym
samym stanie po powrocie z innego ekranu.**

## Zależności

- **Krok 22** (zwijana sekcja) twardo i wzorcowo — `SectionState` już raz
  rozwiązał problem „co jest zwinięte, przeżywa klatkę i pamięta się **pod
  kluczem, nie pod numerem**”. Drzewo jest tym samym problemem o jeden wymiar
  głębiej i **nie ma prawa** rozwiązać go inaczej.
- **Krok 18** (komponenty) — `ComponentInterface`, `ListView`, `ScrollWindow`.
- **Krok 21** (przeglądarka jako moduł) twardo — cała domena plikowa leży
  w module, a rdzeń nie wie, czym jest katalog (D42). Drzewo w rdzeniu musi więc
  być drzewem **czegokolwiek**, nie drzewem katalogów.
- **Krok 24** (podział ekranu) — drzewo w jednym panelu i lista w drugim to
  najbardziej oczywisty jego użytek.
- **Krok 27** (wiersz wielokolumnowy) — wiersz drzewa to wiersz listy z wcięciem,
  więc kolumny mają już istnieć.

## Model i wysiłek

**Opus / xhigh.**

Trzy trudności naraz i każda innej natury. **Pierwsza — rdzeń nie wie, czym jest
katalog**, więc komponent musi opisywać drzewo danymi na tyle ogólnymi, żeby nie
wpuścić domeny plikowej z powrotem do rdzenia (reguła 1, D42). **Druga — stan
rozwinięć**: czwarta klasa po `ScrollWindow`, `SectionState` i `SplitState`,
z kluczem, który przeżywa zniknięcie i powrót gałęzi. **Trzecia — wejście-wyjście**:
rozwinięcie gałęzi to odczyt katalogu, więc podlega D46, a rozwinięcie dziesięciu
naraz to dziesięć odczytów, które nie mieszczą się w klatce.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Presentation/Ui/SectionState` | Zwinięcia pod kluczem sekcji plus kursor; `useContext()` zaczyna oglądanie od nowa — **wzorzec do powtórzenia o wymiar głębiej** |
| `Presentation/Ui/Component/Section` + `SectionList` | Para „dana + komponent”; `SectionList` spłaszcza i wycina okno, a rysowanie oddaje `ListView`owi — **wzorzec do powtórzenia wprost** |
| `Presentation/Ui/ScrollWindow` | Wycinek listy — wystarczy, bo drzewo po spłaszczeniu **jest** listą |
| `Module/Browser/Domain/**` | Katalog, wpisy, zaznaczenie — domena, która **nie ma prawa** wejść do rdzenia |

## Zakres

### 1. Para „dana i komponent”, dokładnie jak w kroku 22

- `TreeNode` — dana: etykieta, głębokość, czy ma dzieci, czy jest rozwinięty,
  klucz. **Bez** wskaźnika na rodzica i bez listy dzieci: drzewo przychodzi do
  komponentu **już spłaszczone**, tak jak sekcje.
- `TreeView` — komponent: liczy wcięcie i znacznik gałęzi, wycina okno,
  a rysowanie oddaje `ListView`owi.
- `TreeState` — czwarta klasa stanu: rozwinięcia **pod kluczem** i kursor.

Spłaszczenie po stronie modułu, a nie komponentu, ma ten sam powód, co w kroku
22: komponent, który sam schodzi po drzewie, musiałby wiedzieć, skąd biorą się
dzieci — a to jest wejście-wyjście, którego rdzeń nie zna.

### 2. Znacznik gałęzi

Trójkąt rozwinięcia i wcięcie o stałą liczbę kolumn na poziom. Linie łączące
(`├─`, `└─`) — do rozstrzygnięcia: są ładne, kosztują kolumnę na poziom
i **każda jest znakiem spoza podstawowej strony kodowej**, czyli osobną bitmapą
w pamięci podręcznej (krok 22 zmierzył to dla znaczników sekcji).

### 3. Odbiorca: lewy panel przeglądarki

- Drzewo katalogów w jednym panelu podziału, lista wpisów w drugim.
- Rozwinięcie gałęzi czyta katalog **na żądanie**, nie z góry.
- Głębokość rozwinięć i to, czy drzewo w ogóle powstaje, rozstrzyga **ustawienie
  modułu**.

### 4. Pomiar

Osobny scenariusz `tree`: panel wypełniony węzłami o zmiennej głębokości.
Rozliczyć trzeba wcięcie i znaczniki — czyli dokładnie to, czym drzewo różni się
od listy, którą krok 27 już zmierzył.

## Poza zakresem

- **Rozwijanie wszystkiego naraz** (`*` w innych menadżerach) — to dziesiątki
  odczytów katalogów; jeśli w ogóle, to jako praca kawałkowa w osobnym kroku.
- **Przeciąganie i upuszczanie** — rdzeń nie ma wskaźnika.
- **Drzewo w obu panelach naraz.**
- **Śledzenie zmian w systemie plików** — drzewo pokazuje to, co przeczytało.
- **Zastąpienie płaskiej listy drzewem** — drzewo jest **drugim** widokiem,
  a nie następcą pierwszego.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Component/TreeNode.php` | Presentation | Nowy — węzeł jako dana. |
| `Presentation/Ui/Component/TreeView.php` | Presentation | Nowy — wcięcie, znaczniki, okno. |
| `Presentation/Ui/TreeState.php` | Presentation | Nowy — **czwarta klasa stanu między klatkami**. |
| `Module/Browser/**` | Moduł | Spłaszczanie drzewa, odczyt gałęzi na żądanie, ustawienia. |
| `Infrastructure/Diagnostics/**` | Infrastructure | Scenariusz `tree`. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Czwarta klasa stanu i reguła „komponent dostaje drzewo spłaszczone”. |
| testy | Testy | Wcięcie, rozwijanie i zwijanie, klucz przeżywający zniknięcie gałęzi, kursor po zwinięciu rodzica, głębokie zagnieżdżenie, puste dziecko. |

## Do rozstrzygnięcia na starcie kroku

1. **Czy linie łączące są** — i czy ich koszt (znak spoza ASCII na poziom) jest
   tego wart.
2. **Co się dzieje z kursorem, gdy zwinie się rodzica wpisu pod kursorem.**
3. **Czy drzewo zastępuje listę w panelu, czy jest trzecim układem** obok listy
   pojedynczej i podziału.
4. **Jak głęboko wolno rozwinąć** i czy jest w ogóle limit.
5. **Czy `TreeState` i `SectionState` to naprawdę dwie klasy** — zwinięcie pod
   kluczem plus kursor to w obu dokładnie ta sama treść.

## Kryteria ukończenia

- Lewy panel pokazuje drzewo katalogów z wcięciem i rozwijaniem.
- Rozwinięcie gałęzi o tysiącu wpisów **nie gubi klatki**.
- Rozwinięcia wracają w tym samym stanie po powrocie z innego ekranu.
- Rdzeń **nadal nie wie, czym jest katalog** — pilnuje tego
  `CoreKnowsNothingAboutFilesTest`.
- Scenariusz `tree` rozlicza koszt wcięcia i znaczników.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
