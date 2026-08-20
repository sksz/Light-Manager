# Krok 64 — Dokumentacja dewelopera: mapa kodu i przewodniki „jak dodać X"

> **Skąd ten krok.** Powstał 2026-08-16 jako trzeci krok **Fazy XXI**
> ([00-decyzje.md](../00-decyzje.md), D97). Odpowiada na pytanie, na które dziś
> odpowiedzi nie ma nigdzie: **jak dołożyć do tego projektu swoją rzecz.**
> Architektura mówi, jak jest; dziennik decyzji — dlaczego tak wyszło; ani jedno,
> ani drugie nie mówi, co kliknąć i w jakiej kolejności.

## Status

**Ukończony 2026-08-20.** Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md),
D97 (nr 1, 3 i 5) oraz **D110** — cztery pytania mają cztery odpowiedzi, a trzy
z nich rozstrzygały **sprzeczności wewnątrz samego planu**.

Wszystkie kryteria spełnione, w tym sprawdzane maszynowo: **przewodnik przeszedł
próbę wykonaniem** (czwarta rzecz dołożona wyłącznie wedle jego kroków — `make qa`
zielone za pierwszym razem), **README ma 161 wierszy** i ani jednej sekcji
deweloperskiej ponad odnośnik, para językowa zgodna w **16 plikach**, `examples/`
przechodzi PHPStan `max`, a 983 odnośniki względne prowadzą do istniejących
miejsc.

**Krok spłacił przy okazji zastrzeżenie kroku 63**: kryterium „README poniżej 200
wierszy” było tam odroczone właśnie do tego kroku i jest teraz spełnione.

## Rozstrzygnięcia startowe (2026-08-20)

Pełny zapis wraz z odrzuconymi wariantami: [00-decyzje.md](../00-decyzje.md), D110.

1. **Mikromoduł zostaje poza `Bootstrapem`, a sprawdza go skrypt sesji**
   budujący go tymi samymi rejestrami, którymi składa go aplikacja. Rozstrzyga
   sprzeczność między kryterium „sprawdzone wykonaniem” a §7, który umieszcza
   przykład tam, gdzie nigdy się nie uruchamia.
2. **Siedem plików wedle gatunku**: `03-` osiem przewodników, `04-` dwa
   ostrzegawcze, `05-` pułapki. Plan miał **siedem slotów na jedenaście rzeczy**.
3. **Każda sekcja README tam, gdzie jej gatunek** — struktura do mapy kodu,
   narzędzia i budowa do workflow, **pomiar do `docs/pomiary/README.md`**.
4. **Dziesięć pułapek zamiast siedmiu** — trzy dołożone przez Fazy XIX–XXI.

## Cel

Deweloper wie, **gdzie co leży** i **jak dołożyć rzecz każdego rodzaju** — nie
czytając 7405 wierszy dziennika decyzji ani 2054 wierszy architektury od deski
do deski.

Miarą powodzenia jest zdanie: **dołożenie nowej komendy, kwerendy i pozycji
ustawień da się wykonać z samego przewodnika, a wynik przechodzi `make qa` za
pierwszym razem.**

Miarą drugą, wymierną: **każdy przewodnik ma przykład, który się kompiluje** —
przykłady są plikami w `examples/`, objętymi PHPStanem `max`, a nie blokami
w markdownie (konwencja z kroku 62).

## Dlaczego to nie jest streszczenie architektury

Architektura odpowiada na pytanie **„jak jest i dlaczego"** — i odpowiada
dobrze; problem jest inny. Wiedza potrzebna do dołożenia jednej rzeczy leży
dziś **rozsypana po trzech dokumentach i po komentarzach w kodzie**: żeby dodać
moduł, trzeba znać regułę 15 (SKILL), rozdział o kontrakcie modułu
(architektura), decyzje D38 i D40 (dziennik) oraz to, że napisy modułu wchodzą
pod przedrostkiem `module.<id>.` — co stoi w komentarzu klasy. Przewodnik ma
**zebrać to w kolejność**, a nie powtórzyć.

Druga rzecz, której nie ma: **spisu pułapek**. Projekt zapłacił za nie
pomiarem, żywym serwerem i utraconymi danymi, a zapisane są w dziennikach kroków
— czyli tam, gdzie nikt nie zajrzy, zanim nie wpadnie w tę samą.

## Stan zastany (policzony 2026-08-16)

| Element | Stan |
|---|---|
| Reguły | 18 głównych + podreguły 11a–11y w `SKILL.md` (1006 wierszy); ich źródło — rozdziały architektury (po kroku 62). |
| Moduły | Sześć, każdy z własnymi warstwami; kontrakt: `ModuleInterface` + zdolności deklarowane osobno. |
| Rejestry | Komendy (~32), kwerendy (**41**), zdarzenia (**22** pozycje: 5 rdzenia + 17 przeglądarki), moduły. |
| Komponenty | 25 klas w `Presentation/Ui/Component/`. |
| Prymitywy | Słownik **zamknięty**, osiem kształtów, trzej tłumacze. |
| Porty rdzenia | Wejście, widok, renderer, ustawienia, praca tłowa, operacje na plikach, kopiowanie, kosz, podglądy. |
| Testy | `tests/Unit`, `tests/Functional` (**26 przebiegów**), `tests/Golden`; strażnicy reguł: `CoreKnowsNothingAboutFilesTest`, `QueryIsTheOnlyReadPathTest`, `NoModuleKnowsAnotherModuleTest`, `PrimitiveTranslationTableTest`, `StatusHintsFlowTest`. |
| Procesy | 29 celów `make`; bramka `make qa`; narzędzia `bin/render-bench`, `bin/terminal-probe`. |
| Przewodnik | **Nie istnieje.** Sekcje deweloperskie README (struktura, narzędzia, pomiar, budowa) są najbliższym, co jest. |

## Zależności

- **Krok 62** — mapa, drzewa językowe, konwencja przykładu (bez niej przewodnik
  byłby zbiorem bloków, które zgniją).
- **Krok 63** — README oddaje sekcje deweloperskie dopiero tutaj; kolejność
  62 → 63 jest po to, żeby żaden fragment nie zniknął po drodze.
- **Kroki 20, 21** — kontrakt modułu i pierwszy moduł jako wzór do naśladowania.
- **Kroki 19, 32, 47, 53, 54** — komendy, menu, okna z komend, kwerendy.
- **Kroki 26, 41, 51** — praca tłowa i jej reguły.
- **Kroki 18, 27–31, 35** — komponenty, prymitywy, trzej tłumacze.
- **Krok 38, 39** — testy, pomiary i wejścia procesów.
- **Krok 66** — testy zgodności; przewodniki są ich drugim przedmiotem.

## Model i wysiłek

**Opus / xhigh.** Kodu aplikacji nie dotyka (poza `examples/`), pomiaru nie
potrzebuje. Wysiłek trzyma **liczba przewodników razy dwa języki** oraz to, że
każdy z nich musi być **sprawdzony wykonaniem** — przewodnik, którego nikt nie
przeszedł krok po kroku, jest hipotezą, a nie instrukcją.

## Zakres

### 1. Mapa kodu

`docs/pl/przewodnik/01-mapa-kodu.md` — **gdzie co leży i dlaczego tam**:
cztery warstwy rdzenia, sześć modułów powtarzających ten sam podział, `bin/`,
`lang/`, `tests/`, `docs/`. Do tego trzy zdania graniczne, które w tym projekcie
rozstrzygają najwięcej sporów o miejsce:

- **rdzeń nie wie, czym jest plik** (reguła 1, D42),
- **moduł nie sięga do innego modułu** (reguła 15) — a to, co wygląda na wyjątek,
  idzie komendą, kwerendą albo zdarzeniem,
- **interfejs graficzny stoi po dwóch stronach granicy** — komponent wie, jak
  wyglądać; prymityw jest tym, co z tej wiedzy zostaje po przekroczeniu portu.

### 2. Cykl życia klatki

`02-cykl-klatki.md` — jedna droga od bajtu na wejściu do piksela: wejście →
stan → złożenie klatki → renderer, z zaznaczeniem, **co wolno robić w której
fazie**. To jest miejsce, w którym mieszka reguła najczęściej łamana przez
nowego: praca zmieniająca dysk posuwa się w `GameLoop`, a nie w `draw()`.
Diagram mermaidem, zdanie opisowe obowiązkowe.

### 3. Osiem przewodników „jak dodać X"

Każdy w tym samym układzie: **kiedy tego użyć / kroki / przykład w `examples/` /
co sprawdzi bramka / czego nie robić**.

| Przewodnik | Rzecz, której nie wolno przeoczyć |
|---|---|
| Nowy moduł | Jedna linia w `Bootstrapie` — jeśli kosztuje więcej, to jest błąd do naprawienia (reguła 15). |
| Nowa komenda | Kontrakt w `Application/Command`, komenda w `Presentation`; okno przez `OpensOverlay`, nie przez identyfikator ekranu. |
| Nowa kwerenda | Czyta i nie zmienia; odpowiada w klatce albo oddaje **stan pracy**; ładunek typowany tylko dla właściciela. |
| Nowa pozycja ustawień | Wyłącznie skalary; wartość spoza listy przystanków wraca do domyślnej. |
| Nowy komponent | Bezstanowy; co ma przeżyć klatkę, mieszka obok niego. Bez odbiorcy w aplikacji nie powstaje (reguła 13). |
| Nowe okno nakładane | Zużywa albo przepuszcza klawisz; czynność przychodzi domknięciem i oddaje `OverlayOutcome`. |
| Nowa praca tłowa | Port mówi o pracy, nie o wyniku; kształt wypisu deklaruje **zamówienie**; sprzątanie dwiema drogami. |
| Nowe napisy i drugi język | Żaden napis w kodzie; `Domain` nie sięga po napisy w ogóle. |

Dwie rzeczy dostają przewodniki **ostrzegawcze**, bo prawie zawsze odpowiedź
brzmi „nie": **nowy prymityw** (słownik zamknięty, obowiązek dla trzech
rendererów, zgoda użytkownika) i **zmiana rdzenia zamiast modułu** (reguła 15
wraz z jedynym nazwanym wyjątkiem 15b i próbą, którą trzeba przejść).

### 4. Spis pułapek — siedem rzeczy, które projekt już raz zapłacił

Osobny plik, bo to jest wiedza **najdroższa i najgorzej dostępna** — dziś leży
w dziennikach kroków. Każda pozycja: objaw, przyczyna, gdzie zapłacono.

1. **`2>&1` przy poleceniu, którego wyjściem jest treść** — tryb nieblokujący
   przechodzi na wyjście potomka, OpenSSH porzuca porcję wypisu i kończy się
   **kodem zero** (krok 49: 130 KB z 419 KB).
2. **`kubectl patch --type=merge` podmienia całą tablicę** — wdrożenie o dwóch
   kontenerach traci jeden (krok 54).
3. **`base64_encode()` w `X-Registry-Auth`** — demon odrzuca z `401`; trzeba
   base64 wedle URL i bez dopełnienia (krok 54).
4. **`rename()` nie zawsze jest operacją na metadanych** — PHP obsługuje `EXDEV`
   sam, kopiując w środku wywołania; „ten sam system plików" sprawdza się
   numerem urządzenia (krok 42).
5. **Tryb surowy zostawia włączone `isig` i `iexten`** — `^C` staje się SIGINT-em,
   zanim aplikacja przeczyta cokolwiek; `^V` połyka następny bajt (krok 55).
6. **Potomek nie dostaje wejścia** — `kubectl apply -f -` i podanie hasła
   wejściem są niewykonalne; stąd pliki, `SSH_ASKPASS` i przedrostki
   zmiennych (kroki 48, 52, 58).
7. **Kod wyjścia ≠ 0 nie jest sam z siebie niepowodzeniem** — `du` kończy się
   jedynką za nieprzeczytany katalog i mimo to podaje wynik (krok 26).

### 5. Workflow pracy

`06-workflow.md` — **wejścia procesów i kolejność**: `make check-env` →
`make install` → praca → `make qa` (cs-check → stan → test, stop na pierwszym
błędzie) → pomiar, jeśli krok dotyka ścieżki klatki → aktualizacja dokumentacji
w tym samym kroku. Do tego trzy reguły, które w tym projekcie są twarde:

- **wejściem do procesu jest cel `make`**, a tam, gdzie projekt ma własne
  narzędzie, używa się jego, zamiast dorabiać zastępnik (reguła 18);
- **przed pomiarem i przed oglądaniem klatki prosi się o zwolnienie maszyny**
  i czeka na potwierdzenie (reguła 17);
- **złote klatki odnawia się dopiero po przeczytaniu różnicy**.

Sekcje deweloperskie z `README.md` (struktura, narzędzia, pomiar, budowa)
przenoszą się tutaj — to jest ten krok, w którym README kończy chudnięcie
zaczęte w 62.

### 6. Jak czytać dziennik decyzji

Krótki plik o tym, **czym dziennik jest, a czym nie**: 96 wpisów, każdy
z odrzuconymi wariantami i powodem. Zdanie graniczne: **dziennik mówi, dlaczego
tak wyszło, a nie co obowiązuje dziś** — obowiązujące stoi w architekturze
i w `SKILL.md`. Do tego trzy przykłady wpisów, które warto przeczytać przed
pierwszą większą zmianą (D40 — dane pierwotne między modułami, D92 — jedyna
droga odczytu, D48 — zamknięty słownik prymitywów).

### 7. Przykłady

W `examples/` — po jednym kompletnym przykładzie na przewodnik, w formie
**działającego kodu objętego analizą**: mikromoduł z jedną komendą, jedną
kwerendą, jedną pozycją ustawień i jednym napisem w obu językach. Przewodniki
wskazują go ścieżką i zakresem wierszy.

### 8. Wersja angielska

Pełne lustro `docs/en/guide/`. Nazwy reguł i pojęć pozostają **w oryginale**
tam, gdzie są nazwami klas (`ScreenInterface`, `QueryRegistry`) — tłumaczy się
zdania, nie identyfikatory.

## Poza zakresem

- **Onboarding** — krok 65; przewodnik jest referencyjny, onboarding jest ścieżką.
- **Diagramy przekrojowe i testy zgodności** — krok 66; ten krok rysuje
  wyłącznie diagram cyklu klatki (punkt 2), bo bez niego przewodnik nie ma sensu.
- **Dokumentacja API generowana z docblocków** (phpDocumentor) — projekt ma
  komentarze pisane jako **uzasadnienia**, a nie jako opis sygnatur; generator
  zrobiłby z nich spis, który niczego nie tłumaczy.
- **Przewodnik po pisaniu dokumentacji** — konwencje stoją w kroku 62.
- **Tłumaczenie architektury i dziennika** — decyzja z kroku 62, punkt 4.

## Planowane zmiany w plikach

| Plik | Zmiana |
|---|---|
| `docs/pl/przewodnik/01-mapa-kodu.md` … `07-dziennik-jak-czytac.md` | Nowe — mapa, cykl klatki, osiem przewodników, pułapki, workflow. |
| `docs/en/guide/…` | Nowe — lustro angielskie. |
| `examples/modul-przykladowy/` | Nowe — mikromoduł: komenda, kwerenda, ustawienie, napisy w dwóch językach. |
| `README.md` | Sekcje deweloperskie przenoszą się do przewodnika; zostaje odnośnik. |
| `docs/README.md` | Mapa wskazuje przewodnik w obu językach. |
| `docs/plans/00-index.md` | Status kroku. |

## Kryteria ukończenia

- **Trzy rzeczy dołożone z samego przewodnika** (komenda, kwerenda, pozycja
  ustawień) przechodzą `make qa` za pierwszym razem — sprawdzone wykonaniem,
  nie przeczytaniem.
- **Każdy przewodnik ma przykład w `examples/`**, a `examples/` przechodzi
  PHPStan `max`.
- **Spis pułapek ma siedem pozycji, każdą ze wskazaniem kroku**, w którym
  projekt za nią zapłacił.
- **README nie ma już ani jednej sekcji deweloperskiej** ponad odnośnik.
- **Drzewa `pl` i `en` mają ten sam kształt.**
- Diagram cyklu klatki ma zdanie opisowe.
- `make qa` zielone.

## Dziennik realizacji

### 2026-08-20 — przewodnik w dwóch językach, mikromoduł, który naprawdę działa, i README skrócone dziesięciokrotnie

**Dowiezione:** szesnaście plików przewodnika —
[`docs/pl/przewodnik/`](../../pl/przewodnik/README.md) i lustro
[`docs/en/guide/`](../../en/guide/README.md), po siedem rozdziałów i spisie —
kompletny mikromoduł w
[`examples/modul-przykladowy/`](../../../examples/modul-przykladowy/), 160
wierszy opisu pomiaru przeniesione do
[`docs/pomiary/README.md`](../../pomiary/README.md) oraz `README.md` skrócone
z **421 do 161 wierszy**. `make qa` zielone: 2495 testów, 8468 asercji.

### Przewodnik sprawdzony wykonaniem — w obie strony

Kryterium kroku brzmiało: *trzy rzeczy dołożone z samego przewodnika przechodzą
`make qa` za pierwszym razem, sprawdzone wykonaniem, nie przeczytaniem.*
Sprawdzenie poszło dwiema drogami, bo jedna nie wystarczała.

**Droga pierwsza — czy mikromoduł naprawdę działa.** Skrypt sesji zbudował go
**tymi samymi rejestrami, którymi składa go aplikacja** (D110 nr 1) i pokazał
kolejno: moduł **przyjęty** przez `ModuleRegistry`; komendę w rejestrze wraz
z zadeklarowanym argumentem nieobowiązkowym; **wykonanie komendy zmieniające
odpowiedź po zmianie ustawienia** (`message.zwykle` → `message.glosne`);
kwerendę odpowiadającą wierszem i **bijące pokolenie**; zakładkę ustawień z jedną
pozycją `Choice` i wartością domyślną; oba katalogi napisów z **zerem kluczy bez
pary**. Ani jedna linia w `src/` nie została z tego tytułu ruszona.

**Droga druga — czy przewodnik da się wykonać.** Mikromoduł powstał **przed**
przewodnikiem i przewodnik z niego spisano, więc kolejność była odwrotna do tej,
którą kryterium zakłada. Sprawdzenie odwrotne: dołożenie **czwartej rzeczy —
drugiej komendy modułu — wyłącznie wedle sześciu kroków z rozdziału 3**, bez
zaglądania w kod pierwszej. **`make qa` przeszło za pierwszym razem**, a rejestr
komend pokazał obie wraz z argumentami. Sprawdzenie zostało po nim **wycofane**,
żeby przykład został przy jednej komendzie, jednej kwerendzie i jednej pozycji.

### Znalezisko: źródło mówiło co innego niż skrót

Przy pisaniu rozdziału o cyklu klatki wyszło, że **`SKILL.md` niósł sprostowanie,
którego rozdział architektury nie miał**. Skrót mówi „słownik prymitywów ma
**siedem** kształtów (liczba sprostowana w kroku 56)”, a rozdział nadal pisał
o **ósmym prymitywie**. Sprawdzenie zajęło jedno polecenie:
`grep -rl 'implements Primitive' src/` → **7**.

To jest dokładnie ten rozjazd, który krok 62 zamknął regułą pierwszeństwa:
**reguła powstaje w rozdziale, a skrót ją streszcza** — więc gdy oba mówią co
innego, poprawia się **rozdział**. Sprostowanie dopisane do
[`docs/architektura/02-slownik/klatka-i-komponenty.md`](../../architektura/02-slownik/klatka-i-komponenty.md)
jako ramka przy narracji kroku 30; reszta tamtego podrozdziału jest prawdziwa, bo
mówi o **otwarciu słownika**, a nie o liczbie. Przewodnik podaje przy okazji
polecenie, którym liczbę sprawdza się zamiast pamiętać.

### Trzy odstępstwa od planu, wszystkie z powodem

**1. Siedem slotów na jedenaście rzeczy** (D110 nr 2). Tabela plików planu
przewiduje `01`…`07`, §3 wymienia osiem przewodników plus dwa ostrzegawcze, §4
żąda dla pułapek osobnego pliku, a §5 przypina workflow do `06-`. Rozłożenie
poszło wedle gatunku, nie objętości.

**2. Pomiar wyszedł poza przewodnik** (D110 nr 3). 160 wierszy o osiach,
wzorcach, progach regresji, porównaniu PNG i złotych klatkach trafiło do
`docs/pomiary/README.md`, który **już był dokumentem o pomiarze**. Rozdział
o workflow wskazuje go jednym akapitem i zostawia u siebie to, co dotyczy
**kolejności pracy**: kiedy się mierzy, o co się prosi przed pomiarem i której
osi w tym projekcie brakuje. Przy scalaniu wyszedł duplikat — „Złote klatki”
stały w obu dokumentach — i został scalony do jednego rozdziału.

**3. `examples/modul-przykladowy/` dostał własny wpis `autoload-dev`.** Nazwa
z tabeli planu nie jest poprawnym segmentem PSR-4 (`LightManager\Examples\` →
`examples/` wymagałoby katalogu `ModulPrzykladowy`). Wybrano zachowanie nazwy
kosztem jednej linii w `composer.json`, zamiast przemianowania katalogu wbrew
planowi.

### Co ten krok zostawia następnym

- **Onboarding (krok 65) ma na co wskazywać.** `03-jak-dodac.md` jest referencją
  z ośmioma przewodnikami w jednym układzie, `05-pulapki.md` czyta się objawem,
  a `01-mapa-kodu.md` odpowiada na pierwsze pytanie nowego człowieka. Onboarding
  ma **wskazywać, nigdy nie powtarzać**.
- **Krok 66 dostaje szesnaście par językowych** o zgodnych nagłówkach co do
  liczby i poziomu oraz **dwadzieścia wskazań na `examples/` i `src/`**
  w postaci ustalonej konwencją z kroku 62 — jest co sprawdzać
  `DocumentationExamplesTest`.
- **Pułapka 10 jest zarazem zamówieniem na test.** `StatusHintsFlowTest` pilnuje
  klawiszy **ogłoszonych**, nie **obsługiwanych**; kierunek odwrotny nie ma
  strażnika i dlatego litera `r` przeżyła dwa kroki.
