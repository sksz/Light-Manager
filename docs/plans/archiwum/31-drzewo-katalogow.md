# Krok 31 — Drzewo jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26 (D48). Największa z sześciu propozycji i jedyna, która wnosi **czwartą klasę
> stanu między klatkami**.

## Status

**Ukończony** (2026-08-14).

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

### 2026-08-14 — krok wykonany

**Rozstrzygnięcia startowe.** Pięć pytań z sekcji powyżej plus trzy, które
wynikły dopiero z odpowiedzi nr 3. Pełna treść wraz z uzasadnieniami:
[00-decyzje.md](../00-decyzje.md), **D68**. W skrócie: pełne prowadnice
(`├─`, `└─`, `│`), kursor pod kluczem z przeskokiem na zwijanego rodzica, widok
panelu na klawisz `Ctrl`+`T`, limit głębokości jako pozycja wyboru z wartością
`∞`, dwie osobne klasy stanu, `→` rozwija / `←` zwija / `Enter` wchodzi, a drzewo
pokazuje katalogi **i pliki**.

**Odstępstwo od planu — jedno i nazwane.** Plan zakładał drzewo jako zawartość
jednego panelu podziału, włączaną ustawieniem modułu. Rozstrzygnięcie nr 3
postawiło je inaczej: widok należy do **panelu** i przełącza go klawisz, także
bez podziału. Skutki, których plan przez to nie przewidywał — każdy panel ma
własny `BrowserTree` wraz z własnym oknem przewijania (lista i drzewo przewijają
się po czym innym), a ustawieniem modułu zostaje wyłącznie głębokość.

**Co powstało.**

| Plik | Rola |
|---|---|
| `Presentation/Ui/Component/TreeNode.php` | Węzeł jako **dana** — bez rodzica i bez dzieci; niesie `guides`, czyli po jednej wartości logicznej na przodka |
| `Presentation/Ui/Component/TreeView.php` | Wcięcie, prowadnice, znacznik gałęzi, wycinek okna; rysowanie oddane `ListView`owi |
| `Presentation/Ui/TreeState.php` | **Czwarta klasa stanu między klatkami** — rozwinięcia pod kluczem, kursor **też pod kluczem** |
| `Module/Browser/Presentation/BrowserTree.php` | Spłaszczanie, odczyt gałęzi na żądanie, limit głębokości, `cursorDirectory()` |
| `Module/Browser/Presentation/Component/EntryTree.php` | Drzewo w panelu modułu — bliźniak `EntryList` |
| `Module/Browser/Presentation/Component/EntrySize.php` | Zapis rozmiaru wyjęty z `EntryList`, bo pokazują go odtąd dwa widoki |
| `Module/Browser/Application/UseCase/ExpandBranchUseCase.php` | Odczyt gałęzi; katalog nieczytelny oddaje **pustą**, a nie wyjątek |
| `Infrastructure/Diagnostics/Scenario.php`, `ScenarioFactory.php` | Scenariusz `tree` |

**Trzy rzeczy wyszły dopiero z rozpisania** (opisane szerzej w D68): odczyt
gałęzi rozdzielił się na dwie drogi — rozwinięcie klawiszem czyta od razu (jeden
odczyt, tyle co `Enter`), a **odtwarzanie** po powrocie do katalogu dochodzi po
jednej gałęzi na klatkę (D46); pamięć odczytanych gałęzi jest trwalsza od
korzenia, więc wejście niżej i powrót nie kosztuje ani jednego sięgnięcia na
dysk; a `BrowserTree::cursorDirectory()` okazał się szwem całego kroku — oddaje
zwykły `Directory` z zaznaczeniem na węźle, dzięki czemu pas ścieżki, pas
podglądu, kontekst sesji i `Enter` **nie wiedzą, że drzewo istnieje**.

**Pomiar** (maszyna zwolniona, `loadPerCore` 0,09–0,12; wzorce
`2026-08-14-po-kroku-31{,-window,-text,-loop}.json`):

| Tor | `sections` | `tree` | Różnica | `columns` |
|---|---|---|---|---|
| sixelowy | 16,49 ms | 16,27 ms | **−0,2 ms** | 20,87 ms |
| okienkowy | 0,47 ms | 0,54 ms | +0,07 ms | 0,74 ms |
| tekstowy | 0,93 ms | 0,98 ms | +0,05 ms | 1,17 ms |

**Prowadnice nie kosztują nic mierzalnego** — w torze sixelowym różnica wobec
`sections` mieści się w rozrzucie przebiegu (15,8–18,1 ms), w dwóch pozostałych
wynosi setne części milisekundy. Powód jest ten sam, który krok 22 przewidział
dla znaczników sekcji, tylko działa tu na korzyść: prowadnica jest **tym samym
znakiem w każdym wierszu**, więc pamięć podręczna bitmap (D34) trafia w nią za
każdym razem. Rośnie natomiast blob sixela — 20 972 B wobec 19 053 B dla
`sections` (+10%), bo wierszy niepowtarzalnych jest więcej.

Para rozliczeniowa zmieniła się przy tym wobec zapisu w planie: właściwym
partnerem `tree` jest **`sections`**, a nie `chrome-text`. Obydwa wypełniają ten
sam prostokąt wierszami `ListView`, obydwa mają znak spoza ASCII w wierszu i żaden
nie rysuje pasa ścieżki ani paska stanu — różnicą jest **wyłącznie przedrostek**,
czyli dokładnie to, co scenariusz ma wycenić.

**Regresji nie ma w żadnym torze.** `--compare` wobec
`2026-08-13-po-poprawkach-podgladu.json`: bez przekroczenia progu, a cały
przebieg równomiernie o kilka procent wolniejszy — czyli obraz, który README
katalogu pomiarów opisuje jako różnicę środowiska, nie kodu. `--png-compare`:
**zero różniących się pikseli we wszystkich siedemnastu istniejących
scenariuszach**, co jest mocniejszym dowodem niż czasy — krok nie ruszył ani
jednego piksela w tym, co było.

**Sprawdzenie w prawdziwym kodzie, nie tylko w scenariuszu.** Skrypt roboczy
złożył `BrowserModule` z prawdziwym `FilesystemDirectoryRepository` na katalogu
tego projektu, wcisnął `Ctrl`+`T` i strzałki, po czym wypisał strefę treści jako
siatkę znaków. Drzewo `/home/sksz/Projects/lm` rozwinęło się poprawnie do trzech
poziomów (`src/` → `Application/` → jego podkatalogi), z prowadnicami i licznikiem
`5/23` w pasie ścieżki. To sprawdza to, czego nie sprawdza ani złota klatka
(prymitywy), ani wzorzec PNG (treść scenariusza pomiaru).

**Potknięcie warte zapisania.** `--save=nazwa` dokłada do nazwy pliku **datę,
ale nie przyrostek toru**, więc `--text --save=X` i `--loop --save=X` zapisały się
pod tę samą nazwę i nadpisały wzorzec sixelowy. Wzorce trzeba było odtworzyć
z jawnymi przyrostkami; reguła trafiła do
[docs/pomiary/README.md](../../pomiary/README.md), bo katalog opisywał dotąd tor
jako rozpoznawalny **po podpisie konfiguracji** — co jest prawdą przy czytaniu
i pułapką przy zapisywaniu.

**Testy.** 1385 testów, 3593 asercje, zielone; PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag. Nowe zestawy: `TreeStateTest` (11 testów — klucz,
kontekst, kursor po zwinięciu), `TreeViewTest` (12 — prowadnice, wcięcie, okno,
kursor poza oknem, ustępowanie wcięcia od lewej), `BrowserTreeTest` (14 —
spłaszczanie, jeden odczyt na rozwinięcie, limit, `cursorDirectory()`, gałąź
pusta i nieczytelna),
`DirectoryTreeFlowTest` (12 — przebieg użytkownika przez `ScreenFixture`)
i `BrowserShortcutsTest` (4 — strażnik kolizji `Ctrl`+`T` ze skrótami modułów).
Złota klatka `tests/Golden/tree.txt` oraz wzorce `sixel-tree.png`
i `window-tree.png`.

**Do rozstrzygnięcia z planu — wszystkie pięć zamknięte.** Odpowiedzi: (1) linie
łączące **są**, (2) kursor przechodzi na zwijanego rodzica, (3) drzewo jest
widokiem panelu przełączanym klawiszem, (4) limit ustawieniem, z `∞` jako jedną
z wartości, (5) `TreeState` i `SectionState` to **dwie** klasy.

**Czego krok nie dowiózł** — zgodnie z sekcją „Poza zakresem”: rozwijania
wszystkiego naraz, drzewa w obu panelach naraz, śledzenia zmian w systemie
plików. Dochodzi jedno wykluczenie spoza tamtej listy: **drzewo nie pokazuje
podświetlenia dopasowania filtra**, bo zakresy niesie `TableRow` (krok 30),
a wiersz drzewa jest `ListRow`em. Filtr obowiązuje w drzewie na jego pierwszym
poziomie, ale bez podświetlenia.
