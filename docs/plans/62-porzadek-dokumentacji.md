# Krok 62 — Porządek: jedno źródło, dwa języki, jedna konwencja rysunku

> **Skąd ten krok.** Powstał 2026-08-16 na polecenie użytkownika, jako pierwszy
> krok **Fazy XXI** ([00-decyzje.md](00-decyzje.md), D97). Stoi przed czterema
> pozostałymi z powodu mechanicznego: **one muszą wiedzieć, gdzie pisać**.
> Dokumentacji jest dziś 12 797 wierszy i nie ma ani jednego zdania o tym, który
> dokument za co odpowiada — więc każdy nowy tekst miałby trzy równie dobre
> miejsca i wylądowałby w czwartym.

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D97 (nr 1, 2, 3 i 4).

## Cel

Dokumentacja dostaje **mapę i granice**: wiadomo, gdzie mieszka reguła, gdzie
historia, gdzie podręcznik, a gdzie przewodnik — i wiadomo, **czego w danym
miejscu pisać nie wolno**. Powstaje szkielet dwóch drzew językowych, konwencja
diagramu i konwencja przykładu.

Miarą powodzenia jest zdanie: **każdy z 51 dzisiejszych odnośników do
`docs/architecture.md` nadal działa, a czytelnik po przeczytaniu jednego pliku
wie, dokąd iść ze swoim pytaniem.**

Miarą drugą, wymierną: **żadna reguła nie ma po tym kroku dwóch źródeł.**
Dziś ma — architektura i `SKILL.md` mówią to samo dwa razy, a rozjazd między
nimi jest niewidoczny, bo nikt ich nie porównuje.

## Trudność strukturalna — trzy rzeczy, wszystkie w liczbach

**Pierwsza: 40 z 51 odnośników do dokumentu źródłowego leży w archiwum.**
Kroki ukończone przenoszą się do `docs/plans/archiwum/` i są **dokumentami
zamkniętymi** — indeks mówi wprost, że zastrzeżenie opisuje granicę
dowiezionego zakresu, a nie dług do spłacenia w tym pliku. Przepisywanie w nich
odnośników byłoby więc zmienianiem historii, a zostawienie ich zepsutymi —
zepsuciem pięćdziesięciu ścieżek naraz. Wniosek jest twardy i wyznacza kształt
punktu 2 zakresu: **`docs/architecture.md` musi zostać pod tym samym adresem.**

**Druga: dokument źródłowy jest w dwóch trzecich słownikiem.** Z 2054 wierszy
rozdział „Słownik domenowy" zajmuje około 1330 — czyli rzecz, do której się
zagląda, stanowi większość pliku, do którego się czyta. To nie jest wada tekstu,
tylko **dwa różne gatunki w jednej okładce**.

**Trzecia: reguły mają dziś dwa domy i żaden nie jest formalnie pierwszy.**
`architecture.md` (2054 wiersze) i `SKILL.md` (1006) opisują te same warstwy,
ten sam Singleton i te same 18 reguł z podregułami 11a–11y. `CLAUDE.md` wskazuje
**oba**, nazywając SKILL „operacyjnym skrótem" — czyli pierwszeństwo jest
zapisane w trzecim pliku, jednym zdaniem, i nikt tego nie pilnuje.

## Stan zastany (policzony 2026-08-16)

| Dokument | Wierszy | Rola dzisiejsza |
|---|---|---|
| `docs/plans/00-decyzje.md` | 7405 | Dziennik decyzji D1–D96 — historia, nie instrukcja. |
| `docs/architecture.md` | 2054 | Dokument źródłowy, 9 rozdziałów; rozdz. 2 (słownik) ≈ 1330 wierszy. |
| `docs/plans/00-index.md` | 1388 | Plan, statusy, graf zależności. |
| `README.md` | 1242 | **Podręcznik użytkownika i instrukcja dewelopera w jednym pliku.** |
| `.claude/skills/…/SKILL.md` | 1006 | Reguły operacyjne — te same, w skrócie. |
| `docs/pomiary/README.md` | 215 | Wzorce pomiarowe i spis scenariuszy. |
| `CLAUDE.md` | 31 | Wskaźnik na trzy powyższe. |
| Odnośniki do `architecture.md` | 51 plików | 40 w `docs/plans/archiwum`, 8 w `docs/plans`, po jednym w `README.md`, `SKILL.md`, `CLAUDE.md`. W `src/` i `bin/` — **ani jednego**. |
| Diagramy | 0 | Jedyne rysunki to 33 wiersze ramek ASCII w `architecture.md` (drzewa katalogów). |
| Przykłady kodu | w tekście | Wyłącznie bloki w markdownie — nikt ich nie kompiluje ani nie analizuje. |

## Zależności

- **Krok 04** — to on ustanowił dzisiejszy układ (architektura + Skill + wskaźnik
  w `CLAUDE.md`); ten krok go **nie odwołuje**, tylko dopisuje brakującą granicę.
- **Krok 39** — `Makefile` jako wejście do procesów i zdanie „plik, o którym
  dokumenty milczą, przegrywa z nawykiem"; mapa dokumentacji jest tym samym
  rozumowaniem zastosowanym do dokumentów.
- **Krok 15** — dwa katalogi napisów jako precedens pary językowej utrzymywanej
  w jednym repozytorium.
- **Kroki 63–66** — wszystkie zależą od tego kroku i żaden nie da się zacząć
  wcześniej.

## Model i wysiłek

**Opus / high.** Kodu aplikacji krok nie dotyka ani w jednym pliku, więc warunek
`Fable` nie zachodzi w żadnym punkcie i pomiar wydajności nie ma tu przedmiotu
(reguła 17 nie zachodzi — druga taka faza po XII). Wysiłek trzyma **operacja na
żywym organizmie**: dokument źródłowy jest wskazywany z pięćdziesięciu miejsc,
a rozdzielenie go bez zerwania ani jednego odnośnika wymaga rachunku, a nie
przeklejania.

## Zakres

### 1. Mapa dokumentacji — `docs/README.md`

Jeden plik, który mówi **cztery rzeczy i granicę każdej z nich**:

| Rodzaj | Gdzie | Odpowiada na pytanie | Czego tam nie ma |
|---|---|---|---|
| **Reguła** | `docs/architektura/` | jak jest i dlaczego tak | jak to zrobić krok po kroku |
| **Historia** | `docs/plans/` | dlaczego tak wyszło, co odrzucono | co obowiązuje dziś |
| **Podręcznik** | `docs/<jezyk>/podrecznik/` | jak tego użyć | dlaczego tak działa |
| **Przewodnik** | `docs/<jezyk>/przewodnik/` | jak dołożyć swoją rzecz | historia i uzasadnienia |

Zdanie graniczne całości, do zapamiętania i do powtórzenia w `SKILL.md`:
**architektura mówi, jak jest; przewodnik — jak to zrobić; podręcznik — jak tego
użyć; dziennik — dlaczego tak wyszło.** Tekst, który odpowiada na dwa z tych
pytań naraz, jest w złym miejscu.

### 2. Dokument źródłowy: ten sam adres, inna zawartość

`docs/architecture.md` **zostaje pod swoją ścieżką** (pięćdziesiąt odnośników,
w tym czterdzieści w archiwum) i staje się **spisem rozdziałów** — krótkim,
z jednozdaniowym opisem każdego i odnośnikiem. Rozdziały przenoszą się do
`docs/architektura/01-warstwy.md` … `09-co-dalej.md`, a słownik domenowy do
`docs/architektura/slownik.md`, bo jest **słownikiem, a nie rozdziałem**: zagląda
się do niego, a nie czyta go po kolei.

Odnośniki **wewnątrz** przenoszonych rozdziałów przeliczają się tą samą regułą,
którą indeks stosuje przy archiwizacji kroku. Kotwice (`#1-warstwy`) używane
w istniejących dokumentach dostają w spisie odpowiedniki — jeśli któraś nie da
się zachować, wchodzi do dziennika kroku jako **jawna strata**, a nie po cichu.

### 3. Granica wobec `SKILL.md` — jedno źródło, jeden skrót

`SKILL.md` zostaje **operacyjnym skrótem i przestaje być źródłem**: reguła
powstaje w rozdziale architektury, a Skill ją streszcza wraz z numerem. Krok
dopisuje to zdanie do obu plików i do `CLAUDE.md`.

Wariant proponowany: skrót **zostaje pisany ręcznie** (generowanie z rozdziałów
dałoby tekst dłuższy i gorszy — Skill jest streszczeniem, nie wyciągiem),
a broni go **test z kroku 66**: każdy numer reguły w `SKILL.md` ma rozdział
w architekturze i odwrotnie. Wariant odrzucony — generowanie skrótu — zapisany
w dzienniku z powodem.

### 4. Dwa drzewa językowe

`docs/pl/` i `docs/en/` — **lustrzane**. Wchodzą do nich trzy rodzaje
dokumentów: podręcznik (krok 63), przewodnik (64) i onboarding (65).

**Jednojęzyczne zostają** architektura, dziennik decyzji, plany i pomiary — wraz
z powodem zapisanym w mapie: to są dokumenty pracy nad projektem, nie dokumenty
projektu; ich czytelnikiem jest ten, kto go rozwija, a rozwija go dziś jedna
osoba pisząca po polsku. Zmiana tego jest osobną decyzją, nie dopiskiem.

**Polski jest źródłem, angielski tłumaczeniem** — i to ma konsekwencję, którą
trzeba nazwać: zmiana treści zaczyna się po polsku, a wersja angielska, która
została w tyle, jest **usterką widoczną w bramce jakości** (krok 66), a nie
stanem normalnym.

### 5. Konwencja diagramu

Diagramy są **blokami ```mermaid w plikach `.md`** (D97 nr 2) — tekstem
w repozytorium, więc wchodzą do `git diff` i do przeglądu jak kod. Trzy reguły,
wszystkie z powodem:

- **Każdy diagram ma zdanie, które mówi to samo słowami.** Aplikacja sama jest
  programem terminalowym, a w `cat` i `less` diagram widnieje jako źródło —
  czytelnik nie ma prawa na tym stracić treści. To samo zdanie służy czytnikom
  ekranu.
- **Nazwy węzłów pochodzą z kodu** (`FrameComposer`, `QueryRegistry`,
  `BackgroundProcessPort`), nie z opisu — inaczej diagram i kod rozjeżdżają się
  bez śladu.
- **Diagram pokazuje mechanizm, nie hierarchię plików.** Drzewo katalogów jest
  listą i zostaje ASCII-em, jak dziś.

### 6. Konwencja przykładu — przykład to plik, nie blok

Przykłady kodu **są prawdziwymi plikami PHP** w katalogu objętym analizą
(`examples/`), a dokument je **wskazuje ścieżką i zakresem wierszy**, zamiast
kopiować. Powód jest ten sam, dla którego projekt trzyma napisy w katalogach,
a nie w kodzie: kopia rozjeżdża się przy pierwszej poprawce, i to po cichu.
Przykłady przechodzą PHPStan `max` i PHP-CS-Fixer razem z resztą; test z kroku
66 pilnuje, że wskazywany zakres istnieje.

### 7. Utrzymanie — kto co aktualizuje przy kroku

Rozdział „Śledzenie postępu" w [00-index.md](00-index.md) rośnie o **punkt
o dokumentacji**: krok, który zmienia klawisz, ustawienie, komendę, kwerendę
albo moduł, aktualizuje podręcznik i przewodnik **w tym samym kroku** — bo dług
dokumentacyjny bez właściciela jest długiem, którego nikt nie spłaci (ta sama
reguła, którą Faza XVI stosowała do kodu).

## Poza zakresem

- **Pisanie samych treści** — podręcznik, przewodniki i onboarding należą do
  kroków 63–65; ten krok stawia szkielet i granice.
- **Generator statycznej strony dokumentacji** (MkDocs, Docusaurus) — nowe
  narzędzie w projekcie, który cały czyta się z repozytorium; wchodzi wyłącznie
  z odbiorcą (reguła 13).
- **Tłumaczenie architektury, decyzji i planów** — punkt 4 wraz z powodem.
- **Przenoszenie dziennika decyzji** — 7405 wierszy historii zostaje tam, gdzie
  jest; mapa mówi tylko, czym on jest, a czym nie.
- **Zmiana `CLAUDE.md` w coś więcej niż wskaźnik** — plik ma 31 wierszy
  i to jest jego wartość.

## Planowane zmiany w plikach

| Plik | Zmiana |
|---|---|
| `docs/README.md` | Nowe — mapa dokumentacji i cztery granice. |
| `docs/architecture.md` | Staje się **spisem rozdziałów** pod tym samym adresem. |
| `docs/architektura/01-warstwy.md` … `09-co-dalej.md`, `slownik.md` | Nowe — rozdziały wydzielone z dokumentu źródłowego. |
| `docs/pl/`, `docs/en/` | Nowe — szkielet drzew wraz z plikami-zaczątkami trzech dokumentów. |
| `docs/KONWENCJE.md` | Nowe — konwencja diagramu i konwencja przykładu (punkty 5 i 6). |
| `examples/` | Nowe — katalog przykładów objęty PHPStanem i `cs-check`. |
| `.claude/skills/…/SKILL.md` | Zdanie o pierwszeństwie źródła; odnośniki do rozdziałów. |
| `CLAUDE.md` | Wskaźnik na mapę dokumentacji obok dzisiejszych trzech. |
| `README.md` | Sekcja „Dokumentacja" wskazuje mapę, a nie trzy pliki z osobna. |
| `docs/plans/00-index.md` | „Śledzenie postępu" rośnie o punkt o dokumentacji. |
| `phpstan.neon.dist`, `composer.json` | `examples/` w analizowanym drzewie. |

## Kryteria ukończenia

- **Wszystkie 51 odnośników do `docs/architecture.md` działa** — sprawdzone
  maszynowo, nie na oko.
- **Mapa dokumentacji odpowiada na pytanie „gdzie to napisać" jednym akapitem.**
- **Żadna reguła nie ma dwóch źródeł** — `SKILL.md` mówi o sobie, że jest
  skrótem, a architektura, że jest źródłem.
- **Drzewa `pl` i `en` istnieją i mają ten sam kształt** (na razie z plikami
  zaczątkowymi).
- **`examples/` przechodzi PHPStan `max` i PHP-CS-Fixer** razem z `src/`.
- Kotwice utracone przy podziale — wypisane w dzienniku kroku.
- `make qa` zielone.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
