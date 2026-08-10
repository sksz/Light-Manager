# Krok 01 — Warstwy DDD i struktura katalogów `src/`

## Status

Ukończony

## Zależności

Brak (pierwszy krok całego planu — od niego zależy wszystko, co powstanie
w `src/`).

## Model i wysiłek

Opus / high — najbardziej fundamentalna, architektura-krytyczna decyzja
całego projektu. Błąd tutaj (zła granica warstw, przeciek Infrastructure do
Domain) rzutuje na każdy kolejny krok, zarówno w tym planie, jak i w planie
wdrożenia (kroki 05–12).

## Cel

Ustalić pełny podział na warstwy Domain-Driven Design w `src/`, wspólny
język domenowy (ubiquitous language) oraz umiejscowienie poszczególnych
bloków taktycznych DDD (encje, obiekty wartości, agregaty, serwisy
domenowe, repozytoria-interfejsy, zdarzenia domenowe), tak by kroki 05–12
planu wdrożenia miały jednoznaczne miejsce na swój kod.

## Zakres

- Warstwy i katalogi najwyższego poziomu w `src/`:
  - `Domain/` — encje, obiekty wartości, agregaty, serwisy domenowe,
    interfejsy repozytoriów, zdarzenia domenowe, wyjątki domenowe. Zero
    zależności od reszty projektu i bibliotek zewnętrznych (bez Imagick,
    bez `pcntl`, bez wzorca Singleton z kroku 02).
  - `Application/` — przypadki użycia (use case'y) orkiestrujące obiekty
    Domain, DTO wejścia/wyjścia. Zależy wyłącznie od `Domain` (poprzez
    interfejsy repozytoriów/serwisów), nie zna szczegółów Infrastructure.
  - `Infrastructure/` — implementacje interfejsów z `Domain`/`Application`
    (dostęp do systemu plików, Imagick, terminal/`stty`/`pcntl`). Jedyna
    warstwa, której wolno zależeć od bibliotek zewnętrznych i systemu
    operacyjnego.
  - `Presentation/` — pętla gry (event loop), bootstrap aplikacji, obsługa
    klawiszy jako komend. Orkiestruje `Application` i korzysta z warstwy
    usług (Singletonów, krok 02) do złożenia całości.
- Podkatalogi per warstwa, np.:
  `Domain/Entity`, `Domain/ValueObject`, `Domain/Aggregate`,
  `Domain/Repository` (interfejsy), `Domain/Service`, `Domain/Event`,
  `Domain/Exception`; `Application/UseCase`, `Application/Dto`;
  `Infrastructure/Filesystem`, `Infrastructure/Terminal`,
  `Infrastructure/Rendering`, `Infrastructure/Imagick`; `Presentation/Cli`.
- Reguła zależności (Dependency Rule) zapisana jawnie: strzałki zależności
  mogą wskazywać tylko do środka (Presentation → Application → Domain,
  Infrastructure → Domain/Application poprzez implementację interfejsów),
  nigdy odwrotnie.
- Wstępny słownik pojęć domenowych (ubiquitous language) w zakresie
  wystarczającym dla MVP, np.: `Directory`/Katalog, `Entry`/Wpis,
  `Selection`/Zaznaczenie, `Frame`/Klatka, `Renderer`, `ThumbnailPreview`,
  `RendererMode` — nazwy mają być spójnie używane w kodzie, dokumentacji i
  kolejnych krokach planu.
- Konwencja nazewnictwa klas per warstwa (np. `...Repository` — interfejs w
  `Domain`; `...FilesystemRepository` — implementacja w `Infrastructure`;
  `...UseCase` w `Application`).
- Tabela mapująca kroki 05–12 planu wdrożenia na warstwy/katalogi z tego
  kroku, żeby uniknąć rozjazdu między tym planem a planem wdrożenia.

## Poza zakresem tego kroku

Dokładny kształt wzorca Singleton/bootstrap (krok 02), formatowanie i
narzędzia stylu kodu (krok 03), sam zapis do dokumentacji/Skill (krok 04).

## Ryzyka

- Pełne DDD w niewielkiej, jednodomenowej aplikacji CLI grozi nadmiarową
  abstrakcją (over-engineering) — do świadomego zaakceptowania jako
  konsekwencja decyzji o pełnym DDD (zobacz [00-decyzje.md](00-decyzje.md),
  D6), z dbałością, by podział pozostał czytelny mimo małej skali projektu.
- Zbyt sztywna reguła zależności może utrudnić krok 08 (renderowanie przez
  Imagick) — do zweryfikowania, czy warstwa Infrastructure daje wystarczająco
  wygodne API dla Application/Presentation bez przeciekania szczegółów
  Imagick w górę.

## Kryteria ukończenia

- Istnieje jednoznaczny, udokumentowany podział `src/Domain`,
  `src/Application`, `src/Infrastructure`, `src/Presentation` z opisem, co
  wolno, a czego nie wolno danej warstwie.
- Słownik pojęć domenowych jest spisany i wystarcza do jednoznacznego
  nazywania klas w krokach 05–12.
- Tabela „krok planu wdrożenia → warstwa/katalog” jest kompletna dla
  wszystkich kroków 05–12.

## Specyfikacja

Fizyczne utworzenie katalogów i `composer.json` (w tym ostateczny root
namespace) należy do kroku 05 „Szkielet projektu” — ten krok ustala
wyłącznie projekt struktury, którą krok 05 ma zastosować. Namespace
roboczy przyjęty poniżej: `LightManager\` (do potwierdzenia/zmiany w
kroku 05).

### Drzewo katalogów `src/`

```
src/
├── Domain/
│   ├── Aggregate/       # korzenie agregatów (encje z tożsamością)
│   ├── ValueObject/     # niemutowalne wartości, w tym natywne enum'y
│   ├── Repository/      # interfejsy repozytoriów (bez implementacji)
│   ├── Service/         # serwisy domenowe (czysta logika, obecnie: brak — zarezerwowane)
│   ├── Event/           # zdarzenia domenowe (obecnie: brak — zarezerwowane pod funkcje spoza MVP)
│   └── Exception/       # wyjątki domenowe
├── Application/
│   ├── UseCase/         # przypadki użycia orkiestrujące Domain przez Porty
│   ├── Dto/             # obiekty transferu danych wejście/wyjście use case'ów
│   └── Port/            # interfejsy portów wyjściowych (renderer, terminal, generator miniatur)
├── Infrastructure/
│   ├── Filesystem/      # implementacje repozytoriów Domain (odczyt systemu plików)
│   ├── Terminal/        # implementacje portów terminala (raw mode, DA1, sygnały)
│   ├── Rendering/       # implementacje portu renderowania (Sixel, fallback tekstowy)
│   ├── Imagick/         # adaptery na bibliotekę Imagick (miniatury, enkodowanie sixel)
│   └── Support/         # wspólna infrastruktura Infrastructure (np. AbstractSingleton — krok 02)
└── Presentation/
    └── Cli/             # bootstrap aplikacji, pętla gry, mapowanie klawiszy na use case'y
```

### Reguła zależności

Strzałki zależności (importów, `use`) mogą wskazywać wyłącznie „do środka”:

```
Presentation → Application → Domain
Infrastructure → Domain (implementuje Domain/Repository)
Infrastructure → Application (implementuje Application/Port)
```

`Domain` nie importuje niczego z pozostałych trzech warstw — ani Imagick,
ani `pcntl`/`stty`, ani klas-Singletonów z kroku 02. `Application` zna
wyłącznie interfejsy (`Domain/Repository`, `Application/Port`), nigdy
konkretnych klas z `Infrastructure`. Dowiązanie interfejs → implementacja
(które dokładnie `Infrastructure`-owe klasy realizują dany port) następuje
w bootstrapie `Presentation` (krok 09), zgodnie z kolejnością inicjalizacji
Singletonów ustaloną w kroku 02.

### Bounded context

Na potrzeby MVP i najbliższych iteracji (zobacz „Zakres poza MVP” w
[00-index.md](00-index.md)) przyjmujemy **jeden bounded context**:
przeglądanie i podgląd systemu plików w terminalu. Osobny kontekst dla
operacji modyfikujących (kopiuj/przenieś/usuń) rozważymy dopiero, gdy ten
zakres faktycznie trafi do planu — na razie nie ma potrzeby dzielić
domeny sztucznie.

### Słownik pojęć domenowych (ubiquitous language)

| Termin (PL) | Identyfikator | Blok DDD | Warstwa / katalog | Opis |
|---|---|---|---|---|
| Ścieżka katalogu | `DirectoryPath` | Value Object | `Domain/ValueObject` | Zwalidowana, bezwzględna ścieżka systemu plików; samowalidacja w konstruktorze, rzuca `InvalidDirectoryPathException`. |
| Wpis | `Entry` | Value Object | `Domain/ValueObject` | Niemutowalny opis jednego elementu katalogu (nazwa, `EntryType`, rozmiar). Odtwarzany od zera przy każdym odczycie katalogu — nie ma własnej tożsamości. |
| Rodzaj wpisu | `EntryType` | Value Object (natywny `enum`) | `Domain/ValueObject` | `Directory` \| `File`. |
| Zaznaczenie | `Selection` | Value Object | `Domain/ValueObject` | Nieujemny indeks aktualnie zaznaczonego `Entry` w obrębie `Directory`. |
| Katalog | `Directory` | **Agregat (korzeń), Encja** | `Domain/Aggregate` | Tożsamość = `DirectoryPath`. Agreguje listę `Entry` i bieżące `Selection`; pilnuje niezmiennika „indeks zaznaczenia mieści się w liczbie wpisów (albo brak zaznaczenia, gdy katalog pusty)”. Zachowania: `moveSelectionUp()`, `moveSelectionDown()`, `selectedEntry(): ?Entry`. W przeciwieństwie do Value Objects, `Directory` jest mutowalną encją (mutacja w miejscu) — to zgodne z klasycznym DDD (encje ≠ wartości) i pragmatyczne dla jednowątkowej pętli gry. |
| Tryb renderowania | `RendererMode` | Value Object (natywny `enum`) | `Domain/ValueObject` | `Sixel` \| `TextFallback`; wynik kroku 07. |
| Klatka | `Frame` | Value Object | `Domain/ValueObject` | Niemutowalny „zrzut” tego, co ma się znaleźć na ekranie w danej iteracji pętli — składany przez `Application` z bieżącego `Directory` i opcjonalnego `ThumbnailPreview`, przekazywany do `FrameRendererPort`. |
| Podgląd miniatury | `ThumbnailPreview` | Value Object | `Domain/ValueObject` | Reprezentuje istniejącą, wygenerowaną miniaturę (dane + wymiary) dla zaznaczonego pliku graficznego; brak instancji = brak podglądu (nullable na `Frame`). |

### Porty aplikacyjne i ich przyszłe implementacje

Porty to interfejsy w `Application/Port`, przez które `Application` i
`Presentation` korzystają z `Infrastructure`, nie znając konkretnych klas.
Kolumna „Singleton (krok 02)” pokazuje, które porty będą realizowane przez
klasy-Singletony ustalane w kroku 02 — to on ostatecznie przesądza pełną
listę usług i kolejność bootstrapu; poniższe mapowanie jest wejściem dla
tamtego kroku, nie jego zastąpieniem.

| Port (Application/Port) | Realizowany przez (Infrastructure) | Powstaje w kroku | Singleton (krok 02)? |
|---|---|---|---|
| `TerminalPort` | `Infrastructure/Terminal` | 06 | Tak — `TerminalService` |
| `RendererModeDetectorPort` | `Infrastructure/Terminal` | 07 | Tak — `SixelCapabilityService` |
| `FrameRendererPort` | `Infrastructure/Rendering` (+ `Infrastructure/Imagick`) | 08 | Tak — `RendererService` |
| `ThumbnailGeneratorPort` | `Infrastructure/Imagick` | 12 | Do potwierdzenia w kroku 02 |

Oraz interfejs repozytorium w `Domain/Repository` (nie jest portem
aplikacyjnym, lecz domenową abstrakcją dostępu do danych):

| Interfejs (Domain/Repository) | Realizowany przez (Infrastructure) | Powstaje w kroku |
|---|---|---|
| `DirectoryRepositoryInterface` | `Infrastructure/Filesystem` (`FilesystemDirectoryRepository`) | 10 |

### Konwencje nazewnictwa

- Segmenty przestrzeni nazw odpowiadają katalogom 1:1 (PSR-4): np.
  `LightManager\Domain\Aggregate\Directory`.
- **Value Objects** — rzeczownik bez sufiksu (`Entry`, `Selection`), klasa
  `final`, właściwości `readonly` tam, gdzie to możliwe, walidacja w
  konstruktorze. Natywne `enum` traktowane jako Value Object dla celów
  umiejscowienia w `Domain/ValueObject`.
- **Encje / agregaty** — rzeczownik bez sufiksu, jawna tożsamość
  (`Directory` identyfikowany przez `DirectoryPath`), mutowalne w miejscu.
- **Interfejsy repozytoriów** (`Domain/Repository`) — sufiks
  `RepositoryInterface`, przyjmują/zwracają wyłącznie obiekty `Domain`.
- **Implementacje repozytoriów** (`Infrastructure`) — sufiks `Repository`
  poprzedzony technologią (`FilesystemDirectoryRepository`).
- **Porty aplikacyjne** (`Application/Port`) — sufiks `Port`
  (`TerminalPort`, `FrameRendererPort`).
- **Implementacje portów będące Singletonami** (`Infrastructure`) — sufiks
  `Service` (`TerminalService` implementuje `TerminalPort`) — nazewnictwo
  spójne z tabelą usług, którą finalizuje krok 02.
- **Use case'y** (`Application/UseCase`) — czasownik + rzeczownik + sufiks
  `UseCase` (`NavigateIntoDirectoryUseCase`, `RenderCurrentFrameUseCase`).
- **Wyjątki domenowe** (`Domain/Exception`) — sufiks `Exception`
  (`InvalidDirectoryPathException`, `DirectoryNotReadableException`),
  dziedziczą po wspólnym lokalnym typie bazowym, nie po SPL-owych
  wyjątkach bezpośrednio.

### Tabela: kroki 05–12 planu wdrożenia → warstwy / katalogi

| Krok | Warstwy / katalogi | Uwagi |
|---|---|---|
| 05 — Szkielet projektu | Rusztowanie wszystkich warstw (`src/Domain`, `Application`, `Infrastructure`, `Presentation`) + `bin/` | Fizyczne utworzenie katalogów, `composer.json` i finalny namespace root. |
| 06 — Terminal I/O | `Infrastructure/Terminal`, `Application/Port` (`TerminalPort`) | `Domain` nietknięty. |
| 07 — Wykrywanie Sixela | `Infrastructure/Terminal`, `Application/Port` (`RendererModeDetectorPort`), `Domain/ValueObject` (`RendererMode`) | `RendererMode` to jedyny element `Domain` — czysta wartość. |
| 08 — Potok renderowania | `Infrastructure/Rendering`, `Infrastructure/Imagick`, `Application/Port` (`FrameRendererPort`), `Domain/ValueObject` (`Frame` — wersja wstępna) | `Frame` w tym kroku to jeszcze placeholder (zgodnie z zakresem kroku 08). |
| 09 — Pętla główna | `Presentation/Cli` (event loop, bootstrap Singletonów), `Application/UseCase` (np. `RenderCurrentFrameUseCase`, `HandleInputUseCase`) | Tu spinają się wszystkie porty ustalone powyżej. |
| 10 — Nawigacja po systemie plików | `Domain/Aggregate` (`Directory`), `Domain/ValueObject` (`Entry`, `EntryType`, `Selection`, `DirectoryPath`), `Domain/Repository`, `Domain/Exception`, `Infrastructure/Filesystem`, `Application/UseCase` (np. `NavigateIntoDirectoryUseCase`, `NavigateUpUseCase`, `MoveSelectionUseCase`) | Najbardziej „domenowy” krok całego planu. |
| 11 — Render listy plików | `Domain/ValueObject` (`Frame` — rozszerzenie o listę), `Infrastructure/Rendering` (rozszerzenie), `Application/UseCase` (składanie `Frame` ze stanu `Directory`) | |
| 12 — Podgląd miniatur | `Domain/ValueObject` (`ThumbnailPreview`), `Application/Port` (`ThumbnailGeneratorPort`), `Infrastructure/Imagick`, `Application/UseCase` (rozszerzenie składania `Frame`) | |

## Dziennik realizacji

- **2026-08-07** — Ukończono. Ustalono pełną strukturę katalogów `src/`
  (Domain/Application/Infrastructure/Presentation z podkatalogami),
  regułę zależności, jeden bounded context, słownik pojęć domenowych z
  klasyfikacją taktyczną DDD (Value Objects, agregat `Directory`, porty
  aplikacyjne), konwencje nazewnictwa oraz tabelę mapującą kroki 05–12 na
  warstwy. Bez odstępstw od zakresu kroku. Fizyczne katalogi i
  `composer.json` celowo **nie** zostały utworzone — to zakres kroku 05,
  zgodnie z jego już istniejącym opisem. Nietrywialne decyzje taktyczne
  (klasyfikacja `Directory` jako mutowalnego agregatu, wprowadzenie
  `Application/Port` jako miejsca na porty) odnotowane w
  [00-decyzje.md](00-decyzje.md), D10.
