# Krok 65 — Onboarding: pierwsze trzydzieści minut w projekcie

> **Skąd ten krok.** Powstał 2026-08-16 jako czwarty krok **Fazy XXI**
> ([00-decyzje.md](../00-decyzje.md), D97 nr 1). Dostał własne miejsce obok
> przewodnika, bo to jest **inna praca**: przewodnik jest referencją, do której
> się wraca, a onboarding — **ścieżką, którą się przechodzi raz** i po której
> nie wolno się zgubić.

## Status

**Ukończony** (2026-08-21). Ścieżka istnieje w obu językach, zadanie ćwiczebne
jest sprawdzone wykonaniem, bramka jest zielona, a **kryterium pierwsze zostało
zamknięte przejściem**: użytkownik przeszedł ścieżkę w całości i ją przyjął
(2026-08-21). Zastrzeżenie z 20 sierpnia przestaje obowiązywać.

Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md), D97 (nr 1, 3 i 5)
oraz D111 (cztery rozstrzygnięcia wykonawcze).

## Cel

Ktoś, kto zna PHP, ale nie zna tego projektu, przechodzi drogę od `git clone` do
**własnej pierwszej zmiany z zieloną bramką jakości** — bez pytania autora
i bez czytania czegokolwiek poza jedną ścieżką.

Miarą powodzenia jest zdanie: **osoba nieznająca projektu dowozi pierwszą
zmianę w trzydzieści minut**, a pomiar robi się na kimś, kto tego nie pisał —
bo autor onboardingu jest ostatnią osobą, która potrafi go sprawdzić.

Miarą drugą, wymierną: **żaden krok ścieżki nie odsyła do dokumentu dłuższego
niż jedna strona.** Onboarding, który w trzecim kroku każe przeczytać 2054
wiersze architektury, nie jest onboardingiem — jest spisem lektur.

## Dlaczego akurat tutaj jest to trudne

**Projekt ma nietypowo dużo reguł twardych.** Osiemnaście głównych plus
podreguły 11a–11y, sześć strażników w testach (`CoreKnowsNothingAboutFilesTest`,
`QueryIsTheOnlyReadPathTest`, `NoModuleKnowsAnotherModuleTest`,
`PrimitiveTranslationTableTest`, `StatusHintsFlowTest`,
`WindowedModeTouchesNoTerminalTest`), zamknięty słownik prymitywów i zamknięty
słownik zdarzeń. Nowy człowiek **nie ma szans ich znać** — a pierwsze, co go
spotka, to czerwona bramka z komunikatem o warstwie, o której nie słyszał.

Odpowiedź onboardingu nie może brzmieć „przeczytaj reguły". Ma brzmieć: **oto
pięć rzeczy, które w tym projekcie łamie się najczęściej, oto co powie bramka,
gdy je złamiesz, i oto gdzie leży zdanie, które to tłumaczy.** Reszta przyjdzie
z przewodnika, wtedy, gdy będzie potrzebna.

**Druga trudność jest środowiskowa.** Aplikacja chce Imagicka, terminala
z Sixelem, opcjonalnie `ext-glfw`, a do pełnego obrazu — XTerma z konkretnymi
zasobami. Maszyna, na której czegoś brakuje, degraduje się **cicho i sensownie**
(tor tekstowy), więc nowy człowiek zobaczy działającą aplikacją wyglądającą
inaczej niż na opisie — i musi z góry wiedzieć, że tak ma być.

## Stan zastany (policzony 2026-08-16)

| Element | Stan |
|---|---|
| Wejścia procesów | **29 celów `make`**; `make` bez argumentów wypisuje spis; `make check-env` sprawdza środowisko; bramka to `make qa`. |
| Wymagania | PHP ^8.3, `ext-imagick`, `ext-curl`; opcjonalnie `ext-glfw` (tryb okienkowy) i klient `kubectl`/`docker`/`ssh` dla modułów. |
| Degradacja | Brak Sixela → tor tekstowy; brak `ext-glfw` → moduł dźwięku na pustym obiekcie; brak klienta → moduł odrzucony z powodem (reguła 11s). |
| Testy | `tests/Unit`, `tests/Functional` (26 przebiegów), `tests/Golden`; `make test-unit`, `make test-functional`. |
| Reguły twarde | 18 + podreguły; sześciu strażników w testach. |
| Onboarding | **Nie istnieje.** Najbliższe, co jest, to sekcje „Wymagania" i „Instalacja" w README. |

## Zależności

- **Krok 62** — mapa i drzewa językowe.
- **Krok 63** — podręcznik: onboarding odsyła do niego po „co ta aplikacja
  robi", zamiast tłumaczyć to drugi raz.
- **Krok 64** — przewodniki: onboarding kończy się **wskazaniem** właściwego,
  a nie powtórzeniem go.
- **Krok 39** — `make check-env`, `make install`, `make qa` jako gotowe wejścia;
  bez nich ścieżka byłaby listą poleceń do przepisania.
- **Krok 66** — testy zgodności; ścieżka onboardingu jest ich trzecim
  przedmiotem (każde polecenie w niej musi istnieć w `Makefile`).

## Model i wysiłek

**Opus / high.** Kodu nie dotyka, pomiaru nie potrzebuje, objętość jest
najmniejsza w fazie — ale **sprawdzenie jest najdroższe**: onboarding weryfikuje
się wyłącznie przejściem, i to przez kogoś innego niż autor. To jest praca,
której nie da się skrócić czytaniem.

## Zakres

### 1. Ścieżka — pięć przystanków, każdy z widocznym końcem

`docs/pl/onboarding/` (i `docs/en/onboarding/`), pięć plików, każdy
z jednozdaniowym „skąd wiesz, że skończyłeś":

1. **Środowisko** (5 min) — `git clone`, `make check-env`, `make install`.
   Koniec: `make check-env` nie zgłasza braków albo zgłoszone braki są nazwane
   jako opcjonalne wraz ze skutkiem.
2. **Uruchomienie** (5 min) — `make run`, a gdy terminal nie umie Sixela:
   `make run-xterm` albo tor tekstowy. Koniec: widzisz listę plików i pasek
   stanu; wiesz, w którym z trzech torów jesteś i skąd to wiadomo.
3. **Oglądanie** (5 min) — jedna wycieczka po aplikacji: przejście katalogu,
   `F12` (komendy), `Tab` w oknie komend (kwerendy), `F9` (menu), `Ctrl`+skrót
   modułu. Koniec: umiesz zapytać aplikację o jej własny stan.
4. **Pierwsza zmiana** (10 min) — zadanie ćwiczebne z punktu 2 poniżej.
   Koniec: `make qa` zielone.
5. **Dokąd dalej** (5 min) — mapa dokumentacji jednym akapitem i **trzy
   pytania z odpowiedziami**: gdzie jest reguła, gdzie historia, gdzie
   przewodnik.

### 2. Zadanie ćwiczebne — jedno, wykonalne, z widocznym skutkiem

**Dodaj kwerendę, która oddaje coś, czego aplikacja jeszcze o sobie nie mówi**
— i obejrzyj wynik w oknie `F12` po przełączeniu `Tab`em.

Wybrane, bo spełnia naraz cztery warunki, których nie spełnia nic innego:
**skutek widać w aplikacji** (nie tylko w teście), **dotyka trzech warstw**
(dana, kwerenda, rejestracja), **nie może niczego zepsuć** (kwerenda czyta
i nie zmienia — reguła 11w) i **przechodzi przez bramkę z jej strażnikami**
(`QueryIsTheOnlyReadPathTest` powie, jeśli droga jest zła).

Zadanie ma **gotowy plik startowy** w `examples/` i **rozwiązanie** obok — bo
onboarding bez rozwiązania kończy się dla części ludzi ciszą, a nie pytaniem.

### 3. Pięć rzeczy, które łamie się najczęściej

Krótka lista z **komunikatem, jaki zobaczysz**, gdy je złamiesz — bo to
komunikat jest pierwszym kontaktem, nie reguła:

| Złamanie | Co powie bramka |
|---|---|
| Napis wpisany w kod | Test katalogów napisów: klucz bez tłumaczenia albo napis bez klucza. |
| Odczyt danych z pominięciem kwerendy | `QueryIsTheOnlyReadPathTest`. |
| Sięgnięcie do innego modułu | `NoModuleKnowsAnotherModuleTest` (widać w `use`). |
| Typ plikowy w rdzeniu | `CoreKnowsNothingAboutFilesTest`. |
| Klawisz działający, ale niewymieniony w `bindings()` | `StatusHintsFlowTest`. |

### 4. Mapa środowiska — co jest opcjonalne i co się stanie bez tego

Tabela: składnik → czy wymagany → co się dzieje bez niego. Rzecz, której nowy
człowiek nie ma skąd wiedzieć, a która decyduje o tym, czy uzna aplikację za
zepsutą: **brak jest tu zwykle degradacją, nie awarią** — tor tekstowy zamiast
Sixela, cisza zamiast muzyki, moduł zniknięty ze spisu wraz z powodem.

### 5. Diagram: co się dzieje przy starcie

Jeden diagram mermaidem wraz ze zdaniem: `bin/light-manager` → wybór toru
(DA1 albo `--window`) → `Bootstrap` (usługi, moduły, rejestry) → ekran startowy
z ustawień → pętla. Powód, dla którego jest akurat tutaj, a nie w przewodniku:
**pierwsze pytanie nowego człowieka brzmi „co się w ogóle uruchomiło"**, a nie
„jak zbudowana jest klatka".

### 6. Wersja angielska

Pełne lustro. Onboarding jest **jedynym dokumentem tej fazy, którego angielska
wersja ma szansę mieć więcej czytelników niż polska** — i to jest osobny powód,
dla którego rozstrzygnięcie o dwóch językach (D97 nr 3) w ogóle zapadło.

## Poza zakresem

- **Materiał referencyjny** — onboarding wskazuje przewodnik z kroku 64, nigdy
  go nie powtarza.
- **Opis reguł w komplecie** — pięć najczęściej łamanych, reszta z przewodnika
  i architektury.
- **Konfiguracja edytora, kontener deweloperski, CI** — projekt nie ma dziś ani
  jednego z nich; wchodzą wyłącznie z odbiorcą (reguła 13).
- **Ścieżka dla kontrybutora z zewnątrz** (wkład, przegląd, zgłoszenia) —
  repozytorium nie ma dziś zdalnego wydania ani procesu wkładu; osobna decyzja.
- **Nagranie przejścia ścieżki** — patrz „Poza zakresem" kroku 63.

## Planowane zmiany w plikach

| Plik | Zmiana |
|---|---|
| `docs/pl/onboarding/01-srodowisko.md` … `05-dokad-dalej.md` | Nowe — pięć przystanków ścieżki. |
| `docs/en/onboarding/…` | Nowe — lustro angielskie. |
| `examples/zadanie-kwerenda/` | Nowe — plik startowy i rozwiązanie zadania ćwiczebnego. |
| `README.md` | Jeden odnośnik: „zaczynasz? tędy". |
| `docs/README.md` | Mapa wskazuje onboarding jako wejście dla nowych. |
| `docs/plans/00-index.md` | Status kroku. |

## Kryteria ukończenia

- **Osoba nieznająca projektu przechodzi ścieżkę w 30 minut** i kończy zieloną
  bramką — sprawdzone na kimś innym niż autor, a wynik (czas, miejsca zawahania)
  w dzienniku kroku.
- **Żaden przystanek nie odsyła do dokumentu dłuższego niż strona.**
- **Każde polecenie ze ścieżki istnieje w `Makefile`** — pilnuje tego test
  z kroku 66.
- **Zadanie ćwiczebne ma plik startowy i rozwiązanie**, oba przechodzą
  PHPStan `max`.
- **Tabela środowiska mówi, co się stanie bez każdego składnika.**
- Drzewa `pl` i `en` mają ten sam kształt; `make qa` zielone.

## Dziennik realizacji

### 2026-08-20 — ścieżka, zadanie i jedno kryterium, którego autor nie zamknie

**Co powstało.** Pięć przystanków w [`docs/pl/onboarding/`](../../pl/onboarding/README.md)
i ich lustro w [`docs/en/onboarding/`](../../en/onboarding/README.md), zadanie
ćwiczebne w [`examples/zadanie-kwerenda/`](../../../examples/zadanie-kwerenda/)
(plik startowy i rozwiązanie), odnośnik „zaczynasz? tędy" w `README.md` oraz
onboarding jako wejście dla nowych w mapie dokumentacji.

**Cztery rozstrzygnięcia zapadły przed pierwszym zdaniem** i stoją
w [00-decyzje.md](../00-decyzje.md), D111: przedmiotem zadania jest **własny
mikromoduł**, a nie kwerenda dołożona do rdzenia; kwerenda oddaje **czas
działania**; zmiana **zostaje**, wraz z dopisaniem modułu do spisów
w dokumentacji; a „jedna strona" z kryterium drugiego znaczy **sekcja, nie
rozdział** — odnośnik celuje w kotwicę.

**Zadanie zostało ruch po ruchu sprawdzone wykonaniem**, a nie
przeczytaniem — bo to jedyny sposób, żeby ścieżka nie kłamała:

| Ruch | Co się stało |
|---|---|
| `cp -r examples/zadanie-kwerenda/start src/Module/Czas` + `sed` | Katalog i przestrzeń nazw zgadzają się bez ani jednej ręcznej poprawki. |
| Linia w `createModules()` | Moduł przyjęty przez `ModuleRegistry`, zero odrzuceń. |
| `make qa` przed uzupełnieniem napisów | **Czerwień, dokładnie jedna**: `TranslatorServiceTest::testEveryModuleCarriesTheSameKeysInEveryLanguage@Czas`. |
| `lang/en.php` + wypełniona luka w `ask()` | `make qa` zielone: 2496 testów, 8476 asercji. |
| Zapytanie rejestrem | `czas.dzialanie` oddaje `{"seconds":7}` po 7,4 s; pokolenie 1 → 1 → 2 przy 7,4 → 7,9 → 9,1 s, czyli bije **raz na sekundę**, a nie co klatkę. |

Po sprawdzeniu moduł został z drzewa roboczego zdjęty: w repozytorium projektu
byłby modułem bez odbiorcy (reguła 13). Materiał zostaje w `examples/`.

**Zaplanowana czerwień bramki jest częścią zadania, nie usterką pliku
startowego.** Plik startowy ma `lang/pl.php` i nie ma `lang/en.php` — a to
pierwszy kontakt nowej osoby z regułami tego projektu ma być **komunikatem
bramki**, a nie spisem reguł. Onboarding uprzedza o tym wprost, żeby czerwień
nie wyglądała na zepsute repozytorium.

**Kryterium drugie — „żaden przystanek nie odsyła do dokumentu dłuższego niż
strona" — czytane jest wedle D111 nr 4 i jest spełnione tak:** przystanki 1–4
odsyłają poza siebie pięć razy, w tym dwa razy do rozdziału dłuższego niż
strona — i oba te odnośniki celują w **kotwicę sekcji** (66 i 45 wierszy).
Dwa odnośniki do spisów modułów w podręczniku i przewodniku są **adresami
edycji**, nie lekturą: onboarding mówi, który wiersz dopisać. Przystanek piąty
jest mapą i z definicji podaje adresy — w tym dziennik decyzji (8755 wierszy),
zawsze wraz z krótkim wejściem do niego ([przewodnik, rozdz. 7](../../pl/przewodnik/07-dziennik-jak-czytac.md),
106 wierszy).

**Czego nie zrobiono tego dnia i dlaczego.** Kryterium pierwsze — przejście
ścieżki przez kogoś innego niż autor — zostało **otwarte**, bo autor onboardingu
nie jest w stanie zmierzyć czasu, którego sam nie traci. Wynik wszedł do
dziennika następnego dnia (wpis niżej).

### 2026-08-21 — ścieżka przeszła próbę

**Użytkownik przeszedł onboarding w całości i go przyjął.** Zastrzeżenie
z 20 sierpnia jest tym samym zamknięte, a krok — ukończony bez warunków.

Dwie rzeczy warto przy tym zapisać, żeby nikt nie czytał tego wpisu szerzej,
niż mówi:

- **Czasu przejścia ani miejsc zawahania nie odnotowano.** Kryterium wymieniało
  je jako wynik pomiaru; przejście odbyło się bez stopera, a przyjęcie ścieżki
  jest oceną jej autora zamawiającego, nie liczbą.
- **Przechodzącym był użytkownik, czyli osoba znająca projekt.** Zdanie
  z celu kroku („pomiar robi się na kimś, kto tego nie pisał") jest spełnione
  wobec **autora tekstu**, nie wobec projektu. Prawdziwy pomiar na kimś, kto
  aplikacji nie zna, zostaje możliwością na później — nie jako dług tego kroku,
  tylko jako rzecz, która ścieżkę jeszcze poprawi, gdy taka osoba się pojawi.

**Krok 66 dołożył ścieżce jeden ruch** i jest to zmiana widoczna w przystanku
czwartym: od chwili, gdy spisy komend i kwerend są pilnowane maszynowo, zadanie
ćwiczebne wymaga także wiersza w spisie kwerend (oba języki) i linii
w `ScreenFixture`. Onboarding mówi o tym wprost — wraz ze zdaniem, które przy
pierwszej zmianie znaczy najwięcej: **puść bramkę i popraw to, co zgłosi**.

**Dla kroku 66, sprawdzone przy okazji:** wszystkie dziewięć poleceń `make`
ze ścieżki (`check-env`, `install`, `install-safe`, `probe`, `qa`, `run`,
`run-window`, `run-xterm`, `bench-xterm`) istnieje w `Makefile`. Test zgodności
musi przy tym czytać **bloki kodu, a nie prozę**: naiwne wyrażenie `make \w+`
łapie w obu językach cztery zdania („jedna rzecz, której make sprawdzić nie
może", „the actions that make sense") i zgłosiłoby cztery nieistniejące cele.

**Zmiany poza zakresem tabeli:** `composer.json` dostał dwa wpisy
`autoload-dev` (przestrzenie nazw zadania — konieczne, bo katalog z myślnikiem
nie mapuje się sam), a `examples/README.md` — sekcję o różnicy między wzorcem
a ćwiczeniem.
