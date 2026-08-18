# Krok 63 — Podręcznik użytkownika: od pierwszego uruchomienia do scenariuszy

> **Skąd ten krok.** Powstał 2026-08-16 jako drugi krok **Fazy XXI**
> ([00-decyzje.md](00-decyzje.md), D97). Bierze z `README.md` to, co jest
> podręcznikiem, i daje temu własne miejsce w dwóch językach — bo dziś opis
> sterowania stoi w tym samym pliku, co instrukcja budowy paczki, i czytelnik
> musi przewinąć jedno, żeby trafić na drugie.

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D97 (nr 1, 3 i 5).

## Cel

Aplikacja dostaje **podręcznik użytkownika**: co to jest, jak to uruchomić, co
widać na ekranie, co robią klawisze, co potrafi każdy z sześciu modułów, jak to
skonfigurować — i **scenariusze**, czyli opisy prawdziwych zadań od początku do
końca.

Miarą powodzenia jest zdanie: **ktoś, kto nigdy nie widział tej aplikacji,
wykonuje pierwszy scenariusz do końca, nie zaglądając do kodu ani nie pytając
autora.**

Miarą drugą: **README chudnie do wizytówki** — czym to jest, jak zainstalować,
dokąd iść dalej. Wszystko, co dziś stoi między „Sterowaniem" a „Zasobami
XTerma", przenosi się do podręcznika.

## Dlaczego to nie jest przepisanie README

`README.md` ma 1242 wiersze i **odpowiada na dwa pytania naraz**: jak używać
(sterowanie, zaznaczenie, operacje, drzewo, okno komend, menu, ustawienia,
moduły, język, plik konfiguracyjny) i jak rozwijać (struktura, narzędzia
deweloperskie, pomiar, budowa). Pierwsza połowa jest materiałem na podręcznik
i **wymaga przestawienia porządku**, nie przeklejenia: dziś jest ułożona wedle
tego, jak funkcje przybywały, a podręcznik układa się wedle tego, **czego
czytelnik szuka**.

Druga rzecz, której README nie ma w ogóle: **scenariusza**. Są opisy klawiszy
i opisy okien — nie ma ani jednego ciągu „chcę zrobić X, robię 1, 2, 3, widzę
Y". A materiał na nie jest gotowy i sprawdzony: **26 przebiegów funkcjonalnych**
w `tests/Functional/` to zapisane drogi użytkownika, każda z nazwą
(`FileOperationsFlowTest`, `SshSessionFlowTest`, `DeployImageFlowTest`…).
Scenariusz w podręczniku i przebieg w testach mają opisywać **tę samą drogę** —
i to jest jedyna droga, żeby podręcznik nie skłamał.

## Stan zastany (policzony 2026-08-16)

| Element | Stan |
|---|---|
| `README.md` | 1242 wiersze, 21 sekcji; sekcje użytkownika: Wymagania, Instalacja, Uruchomienie, Tryb okienkowy, Sterowanie, Zaznaczenie wielokrotne, Operacje na plikach, Drzewo, Okno komend, Menu, Ustawienia, Moduły, Język, Plik konfiguracyjny, Zasoby XTerma, Znane ograniczenia. |
| Klawisze | **167 wywołań `KeyBinding::`** w `src/` — źródło prawdy dla każdego spisu klawiszy. |
| Moduły | Sześć: `browser`, `file-info`, `audio`, `ssh`, `docker`, `k8s`. |
| Komendy i kwerendy | ~32 komendy, **41 kwerend** — obie widoczne dla użytkownika (`F12`, `Tab` przy pustym polu). |
| Przebiegi funkcjonalne | **26 plików** `*FlowTest.php` — gotowe drogi użytkownika. |
| Tory | Trzy: sixel, tekstowy, okienkowy (`--window`). |
| Ograniczenia opisane | DA1 filtrowany przez multipleksery, zasoby XTerma, skala treści, ikona okna. |
| Podręcznik | **Nie istnieje.** Ani po polsku, ani po angielsku. |

## Zależności

- **Krok 62** — mapa dokumentacji, drzewa `pl`/`en`, konwencja diagramu
  i przykładu; bez niego nie wiadomo, gdzie ten tekst ma leżeć.
- **Kroki 21, 25, 36, 45, 48–52, 58–61** — **siedem** modułów (siódmym jest
  książka adresowa z kroku 60), o których podręcznik
  ma opowiedzieć; kroki Fazy XX zmieniają dwa z nich, więc **kolejność wykonania
  wobec Fazy XX ma znaczenie** (patrz „Poza zakresem").
- **Kroki 14, 15** — ustawienia i języki interfejsu.
- **Krok 40** — podpowiedzi stopki: to one są dziś jedynym spisem klawiszy, jaki
  użytkownik widzi w działaniu, i podręcznik ma się z nimi zgadzać co do klucza.
- **Krok 38** — katalog nazwanych przebiegów jako materiał na scenariusze.
- **Krok 66** — testy zgodności; podręcznik jest ich pierwszym przedmiotem.

## Model i wysiłek

**Opus / xhigh.** Kodu nie dotyka, pomiaru nie potrzebuje — wysiłek trzyma
**objętość podwojona przez parę językową** (D97 nr 3) i to, że spis klawiszy ma
się zgadzać ze 167 miejscami w kodzie, a scenariusze — z 26 przebiegami.
Warunek `Fable` nie zachodzi w żadnym punkcie.

## Zakres

### 1. Układ podręcznika

`docs/pl/podrecznik/` i `docs/en/podrecznik/` — po siedem plików, lustrzanie:

1. **Czym to jest** — jednoekranowe wprowadzenie z rysunkiem układu klatki
   (strefy: nagłówek, treść, pasek stanu) i zdaniem o trzech torach.
2. **Instalacja i pierwsze uruchomienie** — wymagania, `make check-env`,
   `make install`, `make run`; co zrobić, gdy terminal nie umie Sixela.
3. **Ekran i sterowanie** — układ, ognisko, podział paneli, pasek stanu jako
   ściągawka; **spis klawiszy pogrupowany wedle miejsca**, nie alfabetycznie.
4. **Praca z plikami** — nawigacja, drzewo, filtr, zaznaczenie wielokrotne,
   operacje, kosz i cofanie.
5. **Moduły** — po jednej sekcji na każdy z sześciu: po co jest, jak go otworzyć,
   co w nim widać, czego wymaga od maszyny.
6. **Ustawienia i konfiguracja** — zakładki, plik `settings.json`, pliki stanu
   modułów, język interfejsu.
7. **Scenariusze** — punkt 3 poniżej.

### 2. Spis klawiszy wywiedziony z kodu

Klawisze wypisuje się **z `KeyBinding`ów**, a nie z pamięci: 167 miejsc w `src/`
jest źródłem prawdy, a stopka (krok 40) już dziś pokazuje ich podzbiór wedle
miejsca. Podręcznik powtarza **ten sam podział na miejsca** — inaczej czytelnik
porównujący stopkę z podręcznikiem zobaczy dwa różne układy tej samej wiedzy.

Spis ma postać nadającą się do porównania maszynowego (krok 66): tabela
`klawisz | miejsce | co robi`, jeden wiersz na wiązanie.

### 3. Scenariusze — sześć dróg od początku do końca

Każdy scenariusz: **po co**, **kroki z klawiszami**, **co widać**, **co może
pójść nie tak**. Sześć, po jednym na obszar, wszystkie wywiedzione z przebiegów
funkcjonalnych:

| Scenariusz | Przebieg, z którego pochodzi |
|---|---|
| Skopiuj zaznaczone pliki do drugiego panelu i cofnij pomyłkę | `FileOperationsFlowTest`, `MarkedEntriesFlowTest`, `UndoFlowTest` |
| Znajdź plik filtrem, obejrzyj go i sprawdź jego opis | `FilterFlowTest`, `TextPreviewFlowTest`, `FileDescriptionFlowTest` |
| Połącz się z hostem i pobierz z niego katalog | `SshSessionFlowTest`, `RemoteDirectoryFlowTest`, `FileTransferFlowTest` |
| Podnieś projekt compose i obejrzyj logi kontenera | `DockerFlowTest` |
| Zbuduj obraz i wdroż go w klastrze | `DeployImageFlowTest`, `ClusterFlowTest` |
| Zapytaj aplikację o jej własny stan (okno kwerend) | `QueryWindowFlowTest`, `QueryCatalogueTest` |

**Zasada wiążąca:** scenariusz opisuje drogę, którą przechodzi przebieg. Gdy
przebieg się zmieni, scenariusz kłamie — i dlatego oba wymienia się w tym samym
kroku planu (reguła utrzymania z kroku 62, punkt 7).

### 4. Diagramy podręcznika

Cztery, wszystkie mermaidem wraz ze zdaniem opisowym (konwencja z kroku 62):
**układ klatki** (strefy i ich granice), **mapa ekranów i skrótów modułów**
(co dokąd prowadzi), **droga klawisza** (co przechwytuje okno, co ekran, co
zostaje globalne), **stany połączenia** dla modułów sieciowych.

### 5. Rozwiązywanie problemów

Sekcja pisana **objawem, nie przyczyną** — bo czytelnik zna objaw. Materiał
istnieje i jest rozsypany po README, kodzie i dziennikach: obraz nie wchodzi
(DA1 odfiltrowany przez multiplekser), migotanie i zasoby XTerma, brak
`ext-glfw` w trybie okienkowym, moduł nieobecny na liście (`RequiresEnvironment`
i jego pięć powodów), demon Dockera leżący wobec nieobecnego, `kubeconfig` bez
bieżącego kontekstu, terminal nieodpowiadający na pytanie o schowek (po Fazie
XIX).

### 6. README chudnie

Zostaje: czym to jest, wymagania, instalacja, jedno uruchomienie, odnośnik do
podręcznika i do mapy dokumentacji, licencja i ograniczenia środowiska. Sekcje
deweloperskie (struktura, narzędzia, pomiar, budowa) idą do przewodnika
w kroku 64 — **do tego czasu zostają w README** i nie ma tu luki: krok 64 je
zabiera, a mapa z kroku 62 mówi, gdzie ich szukać.

### 7. Wersja angielska

Pełne tłumaczenie siedmiu plików podręcznika. **Polski jest źródłem**
(krok 62, punkt 4), więc wersja angielska powstaje po ustaleniu treści, a nie
równolegle — inaczej dwie wersje rozjeżdżają się już w trakcie pisania.

Nazwy własne interfejsu w tłumaczeniu biorą się **z katalogu `lang/en.php`**,
a nie z tłumaczenia na nowo: podręcznik ma nazywać rzeczy tak, jak nazywa je
aplikacja po angielsku.

## Poza zakresem

- **Zrzuty ekranu** — aplikacja ma `core.dump` i `bin/render-bench --png-compare`,
  ale zrzut w dokumentacji starzeje się po cichu przy każdej zmianie motywu;
  wchodzi wyłącznie wtedy, gdy da się go odnawiać maszynowo, i jest to osobna
  decyzja.
- **Nagrania (asciinema) i film** — inne narzędzie i inny cykl utrzymania.
- **Tłumaczenia na trzeci język** — para `pl`/`en` odpowiada temu, co ma
  aplikacja.
- **Opis funkcji, których jeszcze nie ma** — podręcznik opisuje stan po Fazie
  XX; mysz, zaznaczanie i schowek (Faza XIX) oraz wszystko późniejsze dopisują
  **swoje kroki**, wedle reguły utrzymania z kroku 62.
- **Instrukcja dla dewelopera** — krok 64.

## Planowane zmiany w plikach

| Plik | Zmiana |
|---|---|
| `docs/pl/podrecznik/01-czym-to-jest.md` … `07-scenariusze.md` | Nowe — siedem plików podręcznika. |
| `docs/en/manual/01-what-is-it.md` … `07-scenarios.md` | Nowe — lustro angielskie. |
| `README.md` | Chudnie do wizytówki; sekcje użytkownika przenoszą się do podręcznika. |
| `docs/README.md` | Mapa wskazuje podręcznik w obu językach. |
| `examples/` | Przykładowy `settings.json` i przykładowy plik stanu modułu — jako pliki, nie bloki. |
| `docs/plans/00-index.md` | Status kroku. |

## Kryteria ukończenia

- **Pierwszy scenariusz da się wykonać do końca**, sprawdzone na osobie, która
  aplikacji nie zna (reguła 17 — o zwolnienie maszyny prosi się przed pokazem
  w prawdziwym terminalu).
- **Spis klawiszy zgadza się ze 167 `KeyBinding`ami** — porównanie maszynowe
  wchodzi w kroku 66, ale rozbieżność wykryta wcześniej jest usterką tego kroku.
- **Sześć scenariuszy wskazuje sześć przebiegów** i opisuje tę samą drogę.
- **Cztery diagramy mają zdania opisowe** — treść nie ginie w `less`.
- **README ma mniej niż 200 wierszy** i ani jednej sekcji deweloperskiej ponad
  odnośnik.
- **Drzewa `pl` i `en` mają ten sam kształt**; nazwy interfejsu w wersji
  angielskiej pochodzą z `lang/en.php`.
- `make qa` zielone (przykłady w `examples/` przechodzą analizę).

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
