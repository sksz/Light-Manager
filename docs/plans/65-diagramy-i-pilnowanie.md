# Krok 65 — Diagramy i pilnowanie: dokumentacja, która czerwieni bramkę, gdy kłamie

> **Skąd ten krok.** Powstał 2026-08-16 jako piąty i ostatni krok **Fazy XXI**
> ([00-decyzje.md](00-decyzje.md), D97). Zamyka fazę dwiema rzeczami, których
> nie da się zrobić wcześniej: **kompletem diagramów przekrojowych** (bo
> potrzebują wszystkich dokumentów, żeby wiedzieć, gdzie stanąć) i **testami
> zgodności** (bo nie mają czego pilnować, dopóki nie ma tekstu).

## Status

**Nie rozpoczęty.** Rozstrzygnięcia startowe: [00-decyzje.md](00-decyzje.md),
D97 (nr 2, 4 i 5).

## Cel

Dokumentacja przestaje być obietnicą. **Spisy w niej porównują się z kodem,
przykłady się kompilują, odnośniki prowadzą do istniejących miejsc, a para
językowa nie rozjeżdża się w ciszy** — a gdy któraś z tych rzeczy przestaje być
prawdą, czerwieni się `make qa`.

Miarą powodzenia jest zdanie: **usunięcie jednego `KeyBinding`u z kodu psuje
bramkę jakości, dopóki podręcznik nie zostanie poprawiony.**

Miarą drugą: **komplet ośmiu diagramów, z których każdy ma zdanie mówiące to
samo słowami** — bo aplikacja sama jest programem terminalowym i czytelnik ma
prawo czytać dokumentację w `less`.

## Dlaczego to jest krok o kodzie, a nie o tekście

To jedyny krok tej fazy, w którym **powstaje kod produkcyjny testów**, i to on
decyduje, czy pozostałe cztery mają wartość za pół roku. Projekt broni swoich
reguł maszynowo od kroku 21 — `CoreKnowsNothingAboutFilesTest`,
`QueryIsTheOnlyReadPathTest`, `NoModuleKnowsAnotherModuleTest`,
`PrimitiveTranslationTableTest`, `StatusHintsFlowTest` — i **dokumentacja
dostaje tę samą obronę**, bo bez niej podlega tej samej erozji, co każda
niepilnowana reguła.

Rachunek jest wymierny: dziś w kodzie stoi **167 wywołań `KeyBinding::`**,
**41 kwerend**, około **32 komend**, **6 modułów** i **29 celów `make`**. Każda
z tych liczb rośnie co krok planu, a podręcznik i przewodnik wymieniają je
z nazwy. Rozjazd nie jest ryzykiem — jest terminem.

## Stan zastany (policzony 2026-08-16)

| Element | Stan |
|---|---|
| Strażnicy reguł w testach | Sześciu; wzorzec dobrze znany i sprawdzony (chodzą po przestrzeniach nazw, po `use`, po katalogach napisów). |
| Katalogi napisów | Już dziś pilnowane testem czytającym **oba** języki — precedens dla pary językowej dokumentacji. |
| `KeyBinding` | 167 wywołań; `FocusHint` i `StatusHints` znają je wedle miejsca (krok 40). |
| Rejestry | `CommandRegistry`, `QueryRegistry`, `EventRegistry`, `ModuleRegistry` — każdy umie wymienić swoje pozycje. |
| `Makefile` | 29 celów; `make` bez argumentów wypisuje spis. |
| Diagramy | Po krokach 62–64 istnieją cztery (układ klatki, mapa ekranów, droga klawisza, stany połączenia), jeden (cykl klatki) i jeden (start aplikacji). Przekrojowych — brak. |
| Przykłady | `examples/` objęte PHPStanem od kroku 61; wskazywane z dokumentów ścieżką i zakresem wierszy. |

## Zależności

- **Kroki 61–64** — wszystkie; ten krok pilnuje tego, co one napisały, i nie ma
  sensu przed nimi.
- **Krok 40** — `KeyBinding` i `StatusHints` jako źródło prawdy o klawiszach;
  `StatusHintsFlowTest` jako wzór testu, który już dziś porównuje deklarację
  z rzeczywistością.
- **Kroki 53, 54** — rejestry komend i kwerend jako źródło spisów.
- **Krok 15** — test czytający oba katalogi napisów jako precedens pilnowania
  pary językowej.
- **Krok 38** — katalog przebiegów i reguła, że przebieg użytkownika ma nazwę.

## Model i wysiłek

**Opus / high.** Powstaje kod testowy, ale nie dotyka on ani ścieżki klatki, ani
słownika prymitywów, ani wejścia — więc warunek `Fable` nie zachodzi, a pomiar
wydajności nie ma przedmiotu. Wysiłek trzyma **projekt formatu spisów**: tabela
w dokumencie musi dać się przeczytać maszynowo, nie przestając być czytelną dla
człowieka, a to jest rozstrzygnięcie do podjęcia raz i na trwałe.

## Zakres

### 1. Osiem diagramów przekrojowych

Wszystkie mermaidem, każdy ze zdaniem opisowym (konwencja z kroku 61), nazwy
węzłów **z kodu**:

| Diagram | Co pokazuje | Gdzie stoi |
|---|---|---|
| Warstwy i strzałki zależności | `Domain` ← `Application` ← `Infrastructure`/`Presentation` ← `Module`; strzałki tylko do środka | architektura, rozdz. 1 |
| Anatomia modułu | Kontrakt, zdolności deklarowane osobno, własne warstwy, jedna linia w `Bootstrapie` | przewodnik |
| Cykl pętli | Wejście → stan → klatka → renderer, z fazami i tym, co w której wolno | przewodnik (krok 63) |
| Trzy tory wyjścia | Prymitywy → trzej tłumacze (Sixel, tekst, OpenGL) | architektura |
| Praca tłowa jako maszyna stanu | `begin` → `Running` → `Done`/`Failed`, pompowanie w pętli, sprzątanie dwiema drogami | przewodnik |
| Trzy rejestry | Komendy, kwerendy, zdarzenia — kto publikuje, kto pyta, co wraca | przewodnik |
| Współpraca modułów | `k8s.deploy-image` jako jedyna czynność przez dwa moduły: komenda → zdarzenie → kwerenda | przewodnik |
| Kolejność sprzątania przy wyjściu | `Bootstrap::shutdown()`, `register_shutdown_function`, zasoby GL przed kontekstem | przewodnik |

Diagram **pokazuje mechanizm, nie hierarchię plików** — drzewa katalogów
zostają ASCII-em (krok 61, punkt 5).

### 2. Spisy w formacie nadającym się do porównania

Rozstrzygnięcie techniczne kroku: **tabela markdown z ustaloną liczbą kolumn**
jest formatem spisu — czytelna dla człowieka, rozbieralna wyrażeniem dla testu.
Każdy spis dostaje **znacznik początku i końca** w komentarzu HTML, żeby test
wiedział, gdzie patrzeć, a autor — czego nie ruszać ręcznie w oderwaniu od kodu.

Cztery spisy pod pilnowaniem: **klawisze**, **komendy i kwerendy**, **moduły**,
**pozycje ustawień**.

### 3. Sześć testów zgodności

`tests/Documentation/` — nowy katalog obok `Unit`, `Functional` i `Golden`:

1. **`DocumentationLinksTest`** — każdy odnośnik względny w `docs/`, `README.md`
   i `CLAUDE.md` prowadzi do istniejącego pliku; każda kotwica — do istniejącego
   nagłówka. To ten test, który po kroku 61 pilnuje pięćdziesięciu odnośników do
   dokumentu źródłowego.
2. **`DocumentedKeysMatchBindingsTest`** — spis klawiszy w podręczniku zgadza się
   z `KeyBinding`ami w kodzie: co do klawisza, co do miejsca i co do klucza
   opisu. Klawisz w kodzie bez wiersza w podręczniku i wiersz bez klawisza są
   **tym samym błędem**.
3. **`DocumentedRegistriesMatchTest`** — spisy komend i kwerend w dokumentacji
   zgadzają się z `CommandRegistry` i `QueryRegistry`; spis modułów —
   z `ModuleRegistry::declared()`.
4. **`DocumentedSettingsMatchTest`** — pozycje ustawień w podręczniku zgadzają
   się z `SettingKey` i z deklaracjami modułów, wraz z wartościami domyślnymi.
5. **`DocumentationExamplesTest`** — każdy przykład wskazywany z dokumentu
   istnieje, a wskazany zakres wierszy mieści się w pliku; `examples/` przechodzi
   PHPStan `max` i `cs-check` razem z `src/` (to już stoi od kroku 61 — test
   pilnuje **wskazań**, nie samej analizy).
6. **`DocumentationLanguagePairTest`** — drzewa `docs/pl/` i `docs/en/` mają te
   same pliki i te same nagłówki co do liczby i poziomu; każdy blok ```mermaid ma
   przed sobą akapit opisowy. Rozjazd pary językowej przestaje być niewidoczny.

Ponadto **`SkillMatchesArchitectureTest`** — każdy numer reguły w `SKILL.md` ma
rozdział w architekturze i odwrotnie (zobowiązanie z kroku 61, punkt 3).

### 4. Reguła, którą testy wprowadzają

Do `SKILL.md` i do mapy dokumentacji wchodzi zdanie: **spis w dokumentacji jest
kopią stanu kodu i jest pilnowany maszynowo — zmiana klawisza, komendy, kwerendy,
modułu albo pozycji ustawień jest niekompletna, dopóki bramka nie jest zielona.**

To jest ta sama reguła, którą projekt stosuje do napisów od kroku 15 — i ten sam
powód: rozjazd jest niewidoczny, bo wiersz, do którego nic nie dochodzi, wygląda
tak samo jak wiersz, do którego nic nie przypisano.

### 5. Wejście w `Makefile`

`make docs-check` — osobny cel wołający wyłącznie `tests/Documentation`, żeby dało
się sprawdzić dokumentację bez pełnego przebiegu. Cel **dokłada się do**
`Makefile`, a `make qa` woła go razem z resztą testów (reguła 18: zawężenie wolno
wołać wprost, równoległej drogi się nie dorabia).

### 6. Dokumentacja tego, co powstało

Rozdział w przewodniku: **jak dopisać spis pod pilnowanie** i **co zrobić, gdy
test zgodności się czerwieni** — bo pierwsza reakcja na czerwony test bywa
„wyłączę go", a to jest dokładnie ta droga, którą projekt zamknął dla reguł
warstw.

## Poza zakresem

- **Sprawdzanie poprawności składni diagramów mermaid** — wymagałoby renderera
  w bramce; test pilnuje **obecności zdania opisowego** i domknięcia bloku, a nie
  tego, czy diagram się narysuje.
- **Sprawdzanie stylu i ortografii tekstu** — narzędzie językowe to osobna
  zależność i osobna decyzja.
- **Automatyczne tłumaczenie wersji angielskiej** — test wykrywa rozjazd
  kształtu, tłumaczy człowiek.
- **Generowanie dokumentacji z kodu** — odrzucone w kroku 63 wraz z powodem.
- **Zrzuty ekranu odnawiane maszynowo** — wykluczone z kroku 62; jeśli wrócą, to
  z własnym mechanizmem odnawiania i własną decyzją.
- **Pilnowanie dziennika decyzji i planów** — to dokumenty historyczne; test
  sprawdza w nich wyłącznie odnośniki.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `tests/Documentation/DocumentationLinksTest.php` | Testy | Nowe — odnośniki i kotwice. |
| `tests/Documentation/DocumentedKeysMatchBindingsTest.php` | Testy | Nowe — 167 wiązań wobec spisu. |
| `tests/Documentation/DocumentedRegistriesMatchTest.php` | Testy | Nowe — komendy, kwerendy, moduły. |
| `tests/Documentation/DocumentedSettingsMatchTest.php` | Testy | Nowe — pozycje ustawień i domyślne. |
| `tests/Documentation/DocumentationExamplesTest.php` | Testy | Nowe — wskazania na `examples/`. |
| `tests/Documentation/DocumentationLanguagePairTest.php` | Testy | Nowe — kształt pary językowej, zdania przy diagramach. |
| `tests/Documentation/SkillMatchesArchitectureTest.php` | Testy | Nowe — numery reguł wobec rozdziałów. |
| `tests/Support/DocumentationTree.php` | Testy | Nowe — wspólny odczyt drzewa dokumentów i spisów (jeden rachunek, nie siedem). |
| `docs/architektura/*.md`, `docs/pl/…`, `docs/en/…` | Dokumentacja | Osiem diagramów wraz ze zdaniami; znaczniki spisów. |
| `.claude/skills/…/SKILL.md`, `docs/README.md` | Dokumentacja | Reguła z punktu 4. |
| `Makefile`, `phpunit.xml.dist` | Narzędzia | Cel `docs-check`; nowy zestaw testów. |

## Kryteria ukończenia

- **Usunięcie jednego `KeyBinding`u z kodu czerwieni bramkę** — sprawdzone
  próbą, nie założeniem; to jest miara całego kroku.
- **Dopisanie kwerendy bez wiersza w dokumentacji czerwieni bramkę.**
- **Zepsuty odnośnik czerwieni bramkę** — łącznie z kotwicami po podziale
  dokumentu źródłowego.
- **Rozjazd pary językowej czerwieni bramkę.**
- **Osiem diagramów przekrojowych istnieje, każdy ze zdaniem opisowym.**
- **`make docs-check` działa osobno i wchodzi do `make qa`.**
- **Siedem testów przechodzi na dzisiejszym stanie repozytorium** — a jeśli
  któryś wykryje rozjazd istniejący przed tym krokiem, jest to **wynik do
  naprawienia w tym kroku**, nie do wyciszenia.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
