# Krok 66 — Moduł `git`: repozytorium przestaje być niewidzialne

> **Skąd ten krok.** Powstał 2026-08-16 jako pierwszy krok **Fazy XXII**
> ([00-decyzje.md](00-decyzje.md), D98), z przeglądu braków w zarządzaniu
> projektem zamówionego przez użytkownika. Jest **zarysem, a nie planem**:
> unieruchamia fakty i wypisuje pytania, a rozstrzygnięć startowych nie ma ani
> jednego.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**; pytania
czekają w sekcji „Pytania do rozstrzygnięcia". Pełny plan — zakres w punktach,
tabela plików i kryteria ukończenia — powstaje przy rozpisaniu kroku.

## Cel

Aplikacja pokazuje **stan repozytorium katalogu, w którym stoi użytkownik**:
zmiany robocze, gałęzie, historię i różnicę. Zatwierdzenie, przełączenie gałęzi
i schowek zmian są czynnościami modułu, a nie powodem do wyjścia do powłoki.

Miarą powodzenia jest zdanie: **wejście do katalogu z repozytorium pokazuje jego
gałąź i liczbę zmian, a żadne wywołanie `git` nie pada w rysowaniu klatki.**

## Dlaczego ten krok stoi pierwszy

Nie z sympatii, tylko z zależności: **dwie inne pozycje tej fazy i faz
następnych stoją na jego kwerendach.** Moduł `team` (krok 74) nie ma bez niego
z czego liczyć wkładu, moduł `forge` (krok 69) potrzebuje wiedzieć, na jakiej
gałęzi i na jakim zatwierdzeniu stoi katalog, a znakowanie obrazu wersją źródła
— `docker.build` sięgające po `git.head` — jest dokładnie łańcuchem z kroku 54,
tylko odwróconym: tam moduł Kubernetesa zamawiał czynność u Dockera, tu Docker
pytałby o daną moduł, który powstaje teraz.

## Zarys zakresu

- **Stan roboczy** — pliki zmienione, dodane, nieśledzone i skonfliktowane;
  `Table` z kolumną znaczników, wzorem panelu kontenerów.
- **Gałęzie i zdalne** — `TreeView` (lokalne, zdalne, znaczniki), przełączenie
  i utworzenie gałęzi za `ConfirmOverlay`.
- **Historia** — `Table` z zatwierdzeniami; filtr z kroku 30 działa po autorze
  i treści opisu.
- **Różnica** — `TextView` z kroku 29; podświetlenie dodanych i usuniętych
  wierszy prymitywem `TextMark` z kroku 30.
- **Czynności** — zatwierdzenie z opisem, schowek zmian (`stash`) i jego
  przywrócenie, pobranie i wypchnięcie pracą tłową.
- **Kwerendy** — `git.head`, `git.status`, `git.branches`, `git.log`; pierwsza
  z nich jest tą, po którą sięgną moduły spoza tej fazy.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja na liście w `Bootstrapie` (reguła 15). Wszystko,
czego moduł potrzebuje, stoi w rdzeniu od dawna: praca tłowa równoległa
(kroki 26 i 51), `Table` (27), `TreeView` (31), `TextView` (29), filtr (30),
okna (28, 32) i kwerendy (53).

## Pytania do rozstrzygnięcia

1. **Skąd moduł bierze katalog** — z kwerendy `browser.cwd` przy każdym
   otwarciu, czy z własnej książki repozytoriów prowadzonej przez użytkownika?
   Pierwsze jest tańsze i zgodne z `file-info`; drugie pozwala patrzeć na
   repozytorium, w którym się nie stoi.
2. **Czy moduł pisze** — czy zatwierdzenie, przełączenie gałęzi i wypchnięcie
   wchodzą od razu, czy pierwszy krok tylko czyta? Zapis wymaga okna edycji
   opisu, czyli komponentu wielowierszowego, którego `TextInput` dziś nie ma.
3. **Czy przeglądarka koloruje wpisy stanem gita** — jeśli tak, pyta **raz na
   katalog**, a pokolenie kwerendy wiąże ze znacznikiem czasu `.git/index`;
   pytanie raz na wiersz przekreśliłoby routing z kroku 53.
4. **Repozytorium bez zatwierdzeń, katalog bez repozytorium i katalog wewnątrz
   podmodułu** — trzy stany, z których każdy ma powiedzieć **co jest**, a nie
   „nie udało się".
5. **Czy moduł odrzuca się bez `git`** (`RequiresEnvironment`, precedens
   kroku 48), czy pokazuje pusty ekran z powodem, jak Docker bez demona po
   kroku 58?

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Klient | `git` 2.43.0 w `PATH`. |
| Repozytorium projektu | Gałąź `main`, zdalne `origin` (GitHub, SSH), 25 zatwierdzeń, jeden autor. |
| Formaty maszynowe | `status --porcelain=v2 --branch`, `log --format=…%x00`, `for-each-ref` — wszystkie sprawdzone jako stabilne wejście parsera. |
| Reguła 15f | Obowiązuje wprost: polecenie, którego wyjściem jest treść (`diff`, `show`), **nie scala strumieni**. |

## Zależności

- **Kroki 20 i 21** — kontrakt modułu i przeglądarka jako źródło katalogu.
- **Kroki 26 i 51** — praca tłowa, w tym kilka prac naraz.
- **Kroki 24, 27, 28, 29, 30, 31, 32** — dwa panele, tabela, pytanie, widok
  tekstu, filtr, drzewo i menu.
- **Kroki 53 i 54** — kwerendy jako jedyna droga odczytu oraz wzór na czynność
  przechodzącą przez dwa moduły.

## Model i wysiłek (wstępnie)

**Opus / xhigh.** Warunek `Fable` z przypisów ¹ i ² nie zachodzi: prymitywów nie
przybywa, słownik wejścia nie rośnie, trzej tłumacze zostają nietknięci. Wysiłek
trzyma **rozmiar powierzchni** — cztery widoki i sześć czynności w jednym module,
czyli skala kroku 51 — oraz to, że moduł jest **pierwszym dostawcą danych dla
innych** i jego nazwy kwerend są zobowiązaniem na dalsze kroki.

## Poza zarysem

- Scalanie, przestawianie i rozwiązywanie konfliktów — osobna praca o rozmiarze
  kroku, wymagająca edytora trójstronnego.
- `blame` i przeszukiwanie historii treścią (`log -S`) — dopiero z odbiorcą.
- Podpisywanie zatwierdzeń i klucze GPG.
- Repozytoria inne niż `git`.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
