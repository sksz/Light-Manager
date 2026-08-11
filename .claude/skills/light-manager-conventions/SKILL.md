---
name: light-manager-conventions
description: Use whenever writing, reviewing, or reasoning about PHP code in this repository's src/ or tests/ — enforces DDD layer boundaries (Domain/Application/Infrastructure/Presentation), the per-service Singleton pattern, and PHP coding standards (strict_types, PSR-12, PHPStan max, DomainException hierarchy). Not needed for pure planning/docs-only discussion.
---

# Light Manager — konwencje kodu

Pełny dokument źródłowy: `docs/architecture.md` (i historia decyzji w
`docs/plans/00-decyzje.md`). Ten Skill to operacyjny skrót — jeśli
brakuje tu szczegółu, sprawdź `docs/architecture.md` zamiast zgadywać.

## Twarde reguły (nie łam bez jawnej zgody użytkownika)

1. **Warstwy**: `src/Domain`, `src/Application`, `src/Infrastructure`,
   `src/Presentation`. Zależności tylko „do środka”:
   `Presentation → Application → Domain`,
   `Infrastructure → Domain/Application` (przez implementację interfejsów).
   **Interfejs graficzny stoi po dwóch stronach tej granicy** (krok 18, D36):
   komponenty i kontenery w `Presentation/Ui`, a klatka, płaszczyzny i
   prymitywy w `Application/Ui`. Zasada: *komponent wie, jak wyglądać;
   prymityw jest tym, co z tej wiedzy zostaje po przekroczeniu portu*.
   Renderer nigdy nie widzi komponentu.
   **Kontrakt komendy leży w `Application/Command`** (krok 19, D39) i nie zna
   ani jednego typu z `Presentation`: ekran do otwarcia wskazuje
   **identyfikatorem** (`ScreenInterface::id()`), a nie obiektem.
   **Kontrakt modułu stoi po dwóch stronach tej samej granicy** (krok 20, D38,
   P2): dane i rejestr w `Application/Module`, zdolności wymieniające typy
   z `Presentation/Ui` — w `Presentation/Ui/Module`. Stąd `ModuleShortcut` jest
   daną, a nie `KeyBinding`iem: rejestr żyje w `Application`.
   **Rdzeń nie wie, czym jest katalog ani wpis w systemie plików** (krok 21, D42):
   cała domena plikowa leży w `src/Module/Browser/`, a pilnuje tego
   `CoreKnowsNothingAboutFilesTest`. `Domain/` rdzenia jest przez to chudy —
   `Message`, `MessageTone`, `Preview`, `RendererMode`, `ScrollPosition` plus
   hierarchia wyjątków — i **tak ma być**: to słownik powłoki terminalowej.
2. **`Domain` nigdy nie odwołuje się do Singletonów ani do żadnej biblioteki
   zewnętrznej** (Imagick, `pcntl`, terminal). Musi dać się testować bez
   I/O.
3. **Każda usługa poza `Domain` to osobny Singleton** — dziedziczy po
   `Infrastructure/Support/AbstractSingleton` (`getInstance()`, konstruktor
   `protected`, nie `private`). Żaden centralny kontener/rejestr usług.
4. **`Application` zna tylko interfejsy** (`Domain/Repository`,
   `Application/Port`), nigdy konkretnych klas `Infrastructure`.
5. `declare(strict_types=1)` w każdym pliku PHP. PHP `^8.3`.
6. **Value Objects**: `final`, `readonly`, samowalidacja w konstruktorze
   rzucająca wyjątek domenowy, metoda `equals()`. Encje/agregaty:
   `equals()` porównuje tylko tożsamość, mutowalne w miejscu jest OK.
7. **Napisy**: żaden tekst widoczny dla użytkownika nie jest wpisany w kod.
   Katalogi `lang/pl.php` i `lang/en.php` (płaskie klucze z kropką, parametry
   `{nazwa}`, listy = formy mnogie; angielski jest zapasowy).
   `Domain` **nie sięga po napisy w ogóle**; `Application` — wyłącznie przez
   wstrzyknięty `Application\Port\TranslatorPort`; `Infrastructure` i
   `Presentation` — przez `TranslatorService::getInstance()` albo wstrzyknięty
   port. `Application/Dto` trzyma **klucze** (`SettingKey::labelKey()`), nie
   napisy. Liczby formatuje `TranslatorPort::number()`.
   **Jeden jawny wyjątek** (D33): treść klatek budowanych przez
   `Infrastructure\Diagnostics\ScenarioFactory` i podpis konfiguracji
   `BenchmarkOptions::signature()` są nietłumaczone — to nie interfejs, tylko
   obciążenie pomiaru i identyfikator wzorca, a długość napisu w znakach wchodzi
   do wyniku. Nie „poprawiaj” ich przez katalog; napisy samego narzędzia
   (klucze `bench.*`) idą przez katalog normalnie.
8. **Wyjątki**: dwie rozdzielne hierarchie, obie abstrakcyjne i obie
   `extends \RuntimeException` —
   `Domain\Exception\DomainException` dla warstwy domenowej i
   `Infrastructure\Support\InfrastructureException` dla warstwy
   infrastruktury (np. `Terminal\TerminalException`). Nie dziedziczą po
   sobie nawzajem. Preferuj nazwane konstruktory statyczne
   (`InvalidDirectoryPathException::forPath($path)`,
   `TerminalException::forMissingPcntl()`), nie `new X("...")` ze
   sklejanym stringiem w miejscu użycia.
   **Komunikat wyjątku jest techniczny i po angielsku** — napis dla
   użytkownika dobiera `Presentation\Cli\ProblemPresenter` po klasie
   wyjątku, a dane bierze z jego publicznych, typowanych pól.
   **Wyjątek infrastruktury nie przekracza granicy portu** — jeśli
   `Application` ma się o awarii dowiedzieć, port oddaje ją opisem
   (`?string`, DTO wyniku), a nie rzuca (krok 14, `SettingsPort`).
   **Wyjątek modułu przedstawia się sam** (krok 21): dziedziczy po rdzeniowym
   `DomainException` i deklaruje `Domain\Exception\DescribesProblem` — klucz
   katalogu plus parametry. Rozpoznawanie po klasie w `ProblemPresenter` zostaje
   wyłącznie dla wyjątków rdzenia, bo rdzeń nie ma prawa znać nazw modułu.
9. Testy w `tests/`, lustrzane wobec `src/`. `Domain`/`Application`:
   testy jednostkowe obowiązkowe, zero I/O. Reset Singletonów w testach
   wyłącznie przez `tests/Support/ResetsSingletons` (Reflection) — nigdy
   przez publiczną metodę `resetInstance()` w klasie produkcyjnej.
   Testy `Application` dostają `tests/Support/StubTranslator` (oddaje klucz,
   nie napis); testy zależne od realnych napisów przypinają język przez
   `tests/Support/PinsLanguage`.
10. **Nowe okno „nad” ekranem to `OverlayInterface`** (`Presentation/Ui`), nie
    nowe pole w stanie pętli. Okno samo wyznacza swój prostokąt (`bounds()`)
    i **zużywa albo przepuszcza** klawisz; przepuszczony trafia wyłącznie do
    klawiszy globalnych, nigdy do ekranu pod spodem (krok 19). Płaszczyznę
    takiego okna składa się z `opaque: true` — **warstwa ma zakrywać to, co pod
    nią**, a `Panel` rysuje samą obwódkę, bez tła.
11a. **Komponent jest bezstanowy** — powstaje na nowo w każdej klatce, więc co ma
    przeżyć klatkę, mieszka **obok** niego, a właścicielem jest ekran. Dwie takie
    klasy: `Presentation\Ui\ScrollWindow` (wycinek listy, krok 18) i
    `Presentation\Ui\SectionState` (zwinięcia i kursor sekcji, krok 22). Obie mają
    `useContext(string)` — zmiana kontekstu zaczyna oglądanie od początku —
    a `SectionState` trzyma zwinięcie **pod kluczem sekcji, nie pod numerem**, żeby
    sekcja znikająca i wracająca wracała w tym samym stanie. Zwijana sekcja to
    para `Section` (dana, jak `ListRow`) i `SectionList` (komponent, spłaszcza
    i wycina okno, rysowanie oddaje `ListView`owi); `ListView` sekcji nie zna.
11b. **Element zmieniający się sam z siebie niczego nie wymusza** (krok 23) —
    pętla rysuje klatkę w **każdym** takcie (30 kl./s), niezależnie od tego, czy
    coś się zmieniło, więc wystarczy, że w następnej klatce narysuje się inaczej.
    Zegar bierze **z zewnątrz**, nigdy z `microtime()` w środku: czas klatki zna
    `LoopState`, a niesie go `Presentation\Ui\NeedsTime` — interfejs deklarowany
    osobno, jak `Resettable`, o który `FrameComposer` pyta **ekran i okno
    nakładane**, zawsze przed rysowaniem. Dwaj użytkownicy: karetka w `TextInput`
    i wędrujące wypełnienie `ProgressBar`. Cena, którą trzeba znać: taki element
    z założenia **nie trafia do pamięci podręcznej wierszy** (D34), więc każda
    zmiana z nim związana rozlicza się osobnym scenariuszem pomiaru.
11c. **Podział ekranu nie znosi zasady „jeden ekran naraz”** (krok 24, D45).
    `Split` (komponent, dwie osie) i `SplitState` (trzecia klasa stanu między
    klatkami, po `ScrollWindow` i `SectionState`) dzielą prostokąt **wewnątrz**
    ekranu; `ScreenStack`, `ScreenInterface` i `InputHandler` zostają nietknięte,
    a `F1`/`F2`/skrót modułu zastępują ekran razem z podziałem. **Podział należy
    do modułu**: rdzeń daje klocek, moduł rozstrzyga, czy i jak go użyć, a jego
    ustawienia leżą w podprzestrzeni `modules.<id>`. Jedyny wyłom w zasadzie
    „ekran nie rysuje ramek” to `Presentation\Ui\DrawsOwnFrame` — ekran podzielony
    potrzebuje dwóch obwódek, a rdzeń nie wie, który panel jest czynny; deklaracja
    jest osobnym interfejsem z **metodą** (odpowiedź zależy od ustawień i od
    szerokości okna), więc kontrakt ekranu nie rośnie po raz trzeci. Metoda
    **oddaje prymitywy, a nie rysuje**: rdzeń kładzie je na płaszczyźnie spodniej,
    bo obwódka z wygładzanym obrysem kosztuje ~13 ms i dwie ramki rysowane co
    klatkę zabierały 27 ms z 33 ms budżetu (zmierzone). Reguła ogólna: **co się
    między klatkami nie zmienia, należy do płaszczyzny spodniej — niezależnie od
    tego, kto to narysował.**
11d. **Praca dłuższa od klatki dzieli się na kawałki po jednym na klatkę**
    (krok 25, D46). Trzy części, wszystkie obowiązkowe: **port mówi o pracy, nie
    o wyniku** (`begin()`/`advance($bytes)`/`stop()`, nigdy `checksum(path)`);
    **stan pracy jest daną oglądaną co klatkę** (etap, ułamek, wynik albo powód —
    stąd bierze się wypełnienie `ProgressBar`); **praca ma właściciela, który ją
    przerywa** przy zmianie kontekstu i przy `reset()`. Praca zaczyna się
    **na żądanie**, bo zaznaczenie zmienia się przy przewijaniu 30 razy na
    sekundę. **Proces potomny dokłada czwartą regułę: sprzątanie przy wyjściu**
    (krok 26, D47). Mechanizm jest rdzeniowy — `Application\Port\BackgroundProcessPort`
    i `Infrastructure\Process\BackgroundProcessService` — i moduł sięga po niego
    jak po `ImagePreviewPort`. Sprzątanie idzie **dwiema drogami naraz**: jawnie
    w `Bootstrap::shutdown()` i przez `register_shutdown_function` rejestrowaną
    leniwie przy pierwszym uruchomieniu pracy; jedna jest czytelna, druga łapie
    błąd krytyczny. Ponadto: **jedna praca naraz** (uchwyt `BackgroundHandle` jest
    po to, żeby wyparty zamawiający zobaczył `Idle`, a nie cudzy stan), **oba
    potoki czytane co klatkę** (nieczytany zatrzymuje potomka; strumień błędów
    czytamy i wyrzucamy) i **kod wyjścia ≠ 0 nie jest sam z siebie
    niepowodzeniem** — `du` kończy się jedynką za nieprzeczytany katalog, a wynik
    mimo to podaje. Pierwszy odbiorca: wiersz „zajęte na dysku” w `FileInfo`,
    tylko dla katalogów i tylko na klawisz `d`.
11e. **Miejsce dzieli się jedną regułą na obie osie** (krok 27, D49):
    `Container\Span` niesie minimum, rozmiar preferowany i kolejność ustępowania,
    a `Container\Distribution` je dzieli — wiersze dla `VStack`, kolumny dla
    `Table`. Trzy zdania reguły: stałe biorą swoje i elastyczne dzielą resztę;
    brakuje — oddają wedle `yieldOrder`, każdy do swojego minimum; komu zostałoby
    mniej niż minimum, **znika w całości**. Dwie postacie stałej miary i różnią
    się dokładnie minimum: `Span::fixed()` **kurczy się stopniowo** (pas podglądu
    niższy o wiersz nadal jest pasem podglądu), `Span::rigid()` to **tyle albo
    nic** (data zwężona o trzy znaki nie jest węższą datą). Minimum uczestnika
    elastycznego jest **progiem ustępowania sąsiadów**, a nie obietnicą — dopóki
    suma minimów się mieści, nikt nie ustępuje. Wiersz o wielu kolumnach to
    `Column` (dana: czego chce) + `TableRow` (dana: komórki) + `Table`
    (komponent: liczy szerokości **raz na klatkę dla wszystkich wierszy naraz**).
    `Table` stoi **obok** `ListView`, nie zamiast niego: `ListRow` z dwoma polami
    zostaje dla opisu pliku, bo etykieta z wartością to nie tabela.

11. **Nowy element interfejsu to nowy komponent w `Presentation/Ui/Component`**,
    a nie nowa metoda w rendererze. Komponent oddaje prymitywy z ról motywu i
    prostokątów w siatce znakowej — pikseli nie zna. Słownik prymitywów jest
    **zamknięty**; jego rozszerzenie to obowiązek dla obu rendererów naraz i
    wymaga zgody użytkownika. Komponent znający typ domeny **modułu** leży
    w `Presentation/Component` tego modułu, nie w katalogu rdzenia (krok 21:
    `PathLine`, `PreviewBox`).
12. **Ekran rysuje trzy strefy, nie jedną** (krok 21, D42): `header()`
    i `preview()` oddają `?ScreenZone` — klucz etykiety obwódki plus komponent
    z treścią — a `null` znaczy „strefa nie powstaje, jej wiersze idą do środka”.
    Rdzeniowi zostają **oprawa stref i pasek stanu**; ekran nie rysuje ramek —
    z jednym wyjątkiem, którym jest ekran podzielony (`DrawsOwnFrame`, reguła 11c).
    Zasada kroku 20 „moduł dostaje środkowy panel i nic poza nim” **nie
    obowiązuje**. `headerSuffix()` i `usesPreview()` nie istnieją.
13. **Żaden komponent nie powstaje bez prawdziwego użytkownika w aplikacji**
    (krok 18, P5) — komponent pokryty samym testem to API zaprojektowane na
    domysł. Ta sama zasada odsunęła podpowiadanie ścieżek z kroku 19 do 20:
    rodzaj `SuggestionSource::OnDemand` jest zadeklarowany, ale pierwszą
    implementację wnosi komenda modułu.
    **Jeden jawny wyjątek, świadomy i nazwany w planie: `ProgressBar`** (krok 23)
    — jego prawdziwym odbiorcą był dopiero krok 25 (`sha256`), a **trybu „postęp
    nieznany” dopiero krok 26** (`du`). Dług jest odtąd **spłacony w całości**:
    oba tryby mają użytkownika w aplikacji. To **nie jest precedens**: następny
    komponent bez użytkownika wymaga takiej samej jawnej zgody, a nie powołania
    się na ten — a cena odroczenia wyniosła w tym wypadku trzy kroki planu.
14. PHPStan `level: max`. Zamiast obniżać poziom — punktowy
    `@phpstan-ignore-line` z komentarzem uzasadniającym.
15. **Nowa funkcja to moduł w `src/Module/`, nie zmiana w rdzeniu** (krok 20).
    Moduł powtarza wewnątrz podział na warstwy (katalog warstwy pustej nie
    powstaje) i **może mieć własną warstwę `Domain/`** (krok 21 — przeglądarka
    plików ma), a reguła zależności zyskuje jedną strzałkę:
    `Module → Presentation → Application → Domain`. Dwa zakazy ponad to: moduł
    **nigdy** nie sięga do `Infrastructure` rdzenia inaczej niż przez port
    i **nigdy** nie sięga do innego modułu. Klasa modułu to **zwykły obiekt**
    tworzony `new`-em w `Bootstrap` — nie Singleton; Singletonami zostają usługi
    w jego własnej warstwie `Infrastructure`. Napisy modułu leżą w jego katalogu
    `lang/` i wchodzą do katalogu **wyłącznie** pod przedrostkiem `module.<id>.`.
    Dopisanie modułu ma kosztować **jedną zmianę w rdzeniu**: pozycję na liście
    w `Bootstrap`. Jeśli kosztuje więcej — to jest błąd do naprawienia, a nie
    powód, żeby dotknąć rdzenia.
16. **Dno stosu ekranów wskazuje konfiguracja, nie kod** (krok 21, D42). Klucz
    rdzenia `startupModule` bierze wartości **z rejestru modułów**, a wybór robi
    `Presentation\Cli\StartupScreen`. `Bootstrap` podaje mu identyfikator
    **modułu ostatniej szansy** (`LAST_RESORT_MODULE = 'browser'`): sprawdzanego
    przez rejestr pierwszym, niewyłączalnego i przejmującego dno w czterech
    przypadkach — moduł domyślny wyłączony, odrzucony, nieobecny albo bez ekranu
    — każdy z własnym komunikatem. `Application/Module` nie zna nazwy żadnego
    konkretnego modułu i nie ma jej poznać.
17. **Przed pomiarem i przed oglądaniem klatki poproś o zwolnienie maszyny.**
    Każdy krok zmieniający potok rysowania rozlicza klatkę `bin/render-bench`
    „przed i po” (od kroku 16), a wygląd sprawdza się w prawdziwym terminalu.
    Jedno i drugie **wymaga spokojnej maszyny**: przed uruchomieniem
    `bin/render-bench` (zwłaszcza z `--save` albo `--compare`) oraz przed
    zrzutami ekranu i sprawdzeniem klatki pod XTermem poproś użytkownika
    o zatrzymanie zadań zjadających procesor — kompilacji, kontenerów,
    przeglądarki — i **poczekaj na potwierdzenie**. Narzędzie ma własnego
    strażnika: rozrzut powyżej 1,35× oznacza wiersz z „!” i **odmowę zapisu
    wzorca**. W kroku 22 odmówiło czterokrotnie i wzorzec trzeba było odłożyć,
    więc to nie jest ostrożność teoretyczna. Wyniki z obciążonej maszyny nie
    trafiają do `docs/pomiary/` ani do dziennika kroku.

## Nazewnictwo (skrót)

`...RepositoryInterface` (Domain) → `...Repository` z technologią
(Infrastructure, np. `FilesystemDirectoryRepository`). `...Port`
(Application) → `...Service` (Infrastructure, Singleton). `...UseCase`
(Application). `...Exception` (Domain oraz Infrastructure). DTO opisujące
zdarzenia wejściowe — `Application/Dto` (np. `KeyPress`, enum `Key`).
Wartości opisujące **obraz** (`Frame`, `Plane`, `Rect`, `Size`, `Role`,
`Corner` i prymitywy `TextRun`, `RoundRect`, `CornerBrackets`, `Bar`, `Bitmap`,
`Scrollbar`) leżą w `Application/Ui` — przechodzą przez `FrameRendererPort`,
więc muszą być widoczne dla `Infrastructure`. Wartości opisujące
**konfigurację** (`Settings`, `SettingKey`, `SettingsTab`, `SettingsTabKind`,
`SettingsCursor`, `Language`) leżą w `Application/Dto`. Kontrakt modułu —
`ModuleInterface`, `ModuleShortcut`, `ModuleContext`, `ModuleSetting`,
`ModuleRegistry` i spółka — w `Application/Module`; zdolności `ProvidesScreen`,
`ProvidesHelpTab` i `ReadsContext` w `Presentation/Ui/Module`. `ScreenZone`
(zamówienie strefy skrajnej) — w `Presentation/Ui`, obok `ScreenInterface`. Klasa modułu ma
sufiks `Module` i leży w warstwie `Presentation` swojego katalogu; jego komenda,
skoro dostaje stan pętli, leży w `Presentation/Command` modułu. Komendy — kontrakt, argumenty, parser
wiersza, rejestr i historia — w `Application/Command`; komendy rdzenia
(`ScreenCommand`, `SettingCommand`, `QuitCommand`) w `Presentation/Cli/Command`,
bo dostają stan pętli i stos ekranów. `Domain/ValueObject` **nie zawiera już
niczego o rysowaniu** (krok 18, D36). Katalogi napisów i wybór języka — `Infrastructure/I18n`
(`TranslatorService`, `Catalog`, `PluralRule`), pliki napisów w `lang/`.

## Gdy coś tu nie pasuje do zadania

Jeśli zadanie wymaga odstępstwa od powyższego (np. nowa warstwa, inny
wzorzec DI) — zapytaj użytkownika zamiast cicho odstępować; to są
świadome decyzje architektoniczne z `docs/plans/00-decyzje.md`, nie
przypadkowe konwencje.
