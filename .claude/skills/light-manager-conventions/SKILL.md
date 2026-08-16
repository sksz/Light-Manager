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
    **Okno, które czegoś chce od wołającego, oddaje to domknięciem** (krok 28,
    D56): czynność przychodzi w konstruktorze jako `Closure` i **zwraca
    `?Message`**, który okno pakuje w `OverlayOutcome::close($message)` —
    kontrakt okna nie rośnie o żadne pole, a ekran otwiera je przez istniejące
    `ScreenOutcome::opens()`. Pytanie przed czynnością nieodwracalną: ognisko
    startuje na odmowie, `Esc` znaczy to samo co „nie”, klawisze globalne
    przechodzą, a wariant `dangerous` maluje oprawę rolą `Danger`.
    **Od kroku 41 okno umie dwie rzeczy więcej** (D75). Domknięcie oddaje
    `OverlayOutcome`, a nie sam `?Message` — więc pytanie może **ustąpić miejsca**
    kolejnemu oknu (`OverlayOutcome::replace()`; stos ma jedno piętro, więc
    „zamknij i otwórz” musi stać się naraz). Okno prowadzące pracę deklaruje
    `Presentation\Ui\RunsWork` i wtedy pętla pyta je **raz na takt**
    (`advance()`), więc posuwa pracę i **zamyka się samo**, kiedy ta się skończy.
    Pytanie pada w `GameLoop`, w fazie „aktualizuj stan”, a **nie** w rysowaniu:
    praca zmieniająca dysk nie ma prawa dziać się w środku składania klatki — i to
    jest cała różnica wobec pracy kawałkowej z reguły 11d, która **czyta**.
    `ConfirmOverlay` przyjmuje ponadto drugie, opcjonalne domknięcie: sprzątanie
    **po odmowie**, bo pytanie może stać po pracy, która już coś policzyła.
    Dwa okna rdzenia dowiezione tą drogą: `PromptOverlay` (jedno pole na nazwę;
    wpisanego napisu **nie ocenia**) i `ProgressOverlay` (nazwa, licznik, pasek;
    karmiony ogólną daną `Application\Dto\WorkProgress`, a pasek pokazuje
    dopiero wtedy, gdy praca zna swoją całość).
    **Krok 42 dokłada trzecie i przesuwa granicę domknięcia.** `PromptOverlay`
    oddaje odtąd `OverlayOutcome`, a nie `?Message` — tą samą drogą, którą krok 41
    przeprowadził `ConfirmOverlay`, i z tego samego powodu: okno stoi **w środku**
    łańcucha, bo wpisana ścieżka zaczyna pracę pokazywaną oknem postępu. Nowe okno
    to `ChoiceOverlay` — pytanie o **więcej niż dwie odpowiedzi**, złożone
    z `Dialog`u i `ListView`, z pozycjami podanymi jako klucze katalogu i `Esc`
    znaczącym odpowiedź **ostatnią** (praca czeka, więc okno zamknięte milczkiem
    zostawiłoby ją stojącą). Licznik okna pracy wolno **podać gotowym napisem**
    w `WorkProgress`, gdy praca liczy w czymś innym niż sztuki: `12914688`
    zapisane jako `12,3 MB` wymaga jednostek, a te idą przez katalog napisów.
    **Krok 48 zmienia w `ConfirmOverlay` jedną rzecz: długie pytanie się
    zawija, zamiast ucinać.** Okno rośnie o tyle wierszy, ile trzeba (najwyżej
    sześć), a szerokość zostaje ta sama. Dawne uzasadnienie ucinania — „nazwa
    ucięta w pytaniu widoczna jest piętro niżej, pod kursorem listy" — okazało się
    prawdziwe **tylko dla nazw wpisów**: pytanie o zaufanie nieznanemu kluczowi
    hosta niesie odcisk `SHA256:…`, którego nie widać nigdzie indziej, a odcisk
    ucięty w połowie nie jest odciskiem, tylko pytaniem bez treści do porównania.
    Reguła ogólna z tej poprawki: **zanim ucniesz treść pytania, sprawdź, czy
    użytkownik ma ją skąd wziąć**.
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
    błąd krytyczny. Ponadto: **kilka prac naraz** (krok 51, D90 nr 1 — do tamtego
    kroku była **jedna**, i to z decyzji, nie z techniki; granicę podaje
    ustawienie rdzenia `backgroundJobs`, domyślnie osiem, a jej przekroczenie
    znaczy **odmowę, nie wyparcie najstarszej** — uchwyt wraca, powód odbiera
    pierwszy `poll()`), **oba potoki opróżniane co klatkę dla każdej pracy**
    (nieczytany zatrzymuje potomka; robi to **pętla** przez osobny
    `Application\Port\BackgroundPumpPort::pump()`, bo pompowanie należy do pętli,
    nie do modułu — `poll()` jest odtąd czystym odczytem stanu), **wypis pracy
    trwającej** (krok 52, D91 nr 12 — `Running` niósł do tamtego kroku pusty
    napis, więc polecenie niekończące się nigdy nie miało jak powiedzieć ani
    słowa; `poll()` **zostaje czysty**, bo stan powstaje w `pump()`, a treść nadal
    odbiera się przy `Done` — wypis w trakcie jest urwany w połowie wiersza)
    i **kod wyjścia ≠ 0 nie jest sam z siebie niepowodzeniem** — `du` kończy się jedynką za nieprzeczytany katalog, a wynik
    mimo to podaje. Pierwszy odbiorca: wiersz „zajęte na dysku” w `FileInfo`,
    tylko dla katalogów i tylko na klawisz `d`; **odbiorców jest dziś czterech**
    (`du`, sesja zdalna, przesył plików, compose modułu Dockera) i to oni wymusili
    rozbudowę — `compose up` trwa minutami, więc przy dawnej regule podniesienie
    projektu ubijałoby liczenie katalogu. Uchwyt zmienił przez to **znaczenie, nie
    kształt**: przestał mówić „wyparto cię", zaczął „prace da się rozróżnić".
    Sposobu na zakończenie **cudzej** pracy w porcie nie ma i nie będzie —
    `stopAll()` zapowiadany planem kroku 51 nie wszedł, bo metoda dostępna każdemu
    modułowi jest dawną regułą na żądanie; sprzątanie całości ma drogę poza portem
    od kroku 26.
    **Trzecim i czwartym użytkownikiem wzorca są operacje na plikach** (kroki 41
    i 42) i one dokładają piątą regułę: praca, która **zmienia dysk**, posuwa się
    w `GameLoop` (przez `RunsWork`), a nie w `draw()` — rysowanie nie ma prawa mieć
    skutków ubocznych. Kopiowanie dokłada trzy rzeczy warte zapamiętania: **liczy
    przed pracą** (mianownik znany od pierwszego bajtu, więc pasek nie cofa się),
    **mierzy dwiema miarami naraz** (pasek w bajtach, licznik we wpisach) i ma
    **przystanek w środku pracy** (`Colliding` — pytanie o kolizję nazw, po którym
    praca rusza dalej). Pułapka, na którą uważaj przy plikach: **`rename()` w PHP
    nie zawsze jest operacją na metadanych** — dla zwykłych plików obsługuje
    `EXDEV` sam, kopiując je w środku wywołania, więc „ten sam system plików”
    sprawdza się **numerem urządzenia** (`lstat()['dev']`), a nie próbą
    i odczytaniem błędu.
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

11f. **Rozmiar okna terminala nie jest stałą uruchomienia** (krok 33). `SIGWINCH`
    ustawia w `TerminalService` znacznik (wzorem `shutdownRequested`), a
    `TerminalSizeService` zdejmuje go przy najbliższym odczycie i mierzy
    ponownie; kontrakty `ViewportPort` i `InputPort` są nietknięte, bo
    składanie klatki i renderer pytają o rozmiar **co klatkę**. Konsekwencje:
    nie zapamiętuj wierszy/kolumn/pikseli między klatkami; pamięć podręczna
    zależna od rozmiaru ma rozmiar w kluczu (D34) i unieważnia się sama;
    renderer sixelowy czyści ekran **raz** po zmianie (jawny wyjątek od
    „czyszczenie daje migotanie”); okno za małe rysuje, co się zmieści —
    planszy zastępczej nie ma.

11g. **Tryb okienkowy to te same porty z innymi implementacjami — i nic ponad
    to** (krok 34, D53). Flaga `--window` otwiera okno GLFW (kontekst OpenGL
    3.3 core) zamiast terminala: `InputPort` (dawny `TerminalPort`, nazwa
    przeszła na neutralną wraz z drugim źródłem) → `Glfw\GlfwInputService`,
    `ViewportPort` → `Glfw\GlfwViewportService` (framebuffer / komórka
    zastępcza — stała do kroku 35), `FrameRendererPort` →
    `OpenGlFrameRenderer` (do kroku 35 zastępczy: tło motywu + zamiana
    buforów). Pętla, ekrany, moduły i komponenty **nie wiedzą, że coś się
    zmieniło**. Zakazy, których pilnuje `WindowedModeTouchesNoTerminalTest`:
    kod toru okienkowego (`Infrastructure/Glfw`, `OpenGlFrameRenderer`) nie
    wymienia usług terminalowych, `STDIN`/`STDOUT` ani sekwencji sterujących;
    DA1 nie wychodzi, bo wybór trybu wyprzedza detekcję. Mapowanie klawiszy
    GLFW → `Key` żyje w czystym `GlfwKeyMapper` (bez wywołań GLFW, testowalne
    bez okna). Rozmiar startowy okna niosą klucze rdzenia
    `windowColumns`/`windowRows` (100×30 domyślnie), czytane **przed**
    otwarciem okna — od kroku 37 są zarazem pamięcią rozmiaru (11l). `ext-glfw` zostaje w `suggest`, nigdy w `require`;
    PHPStan przechodzi bez rozszerzenia dzięki stubom `phpgl/ide-stubs`
    (`scanFiles`) — dwie stałe stubów są błędne (`GLFW_TRUE`,
    `GLFW_RELEASE`), więc kod porównuje literały. Rytm klatek: stały takt
    pętli z `glfwSwapInterval(0)`, nie vsync.

11h. **Słownik prymitywów ma trzech tłumaczy** (krok 35, D54). Trzeci to
    `OpenGlFrameRenderer` — prymitywy wprost na API wektorowe rozszerzenia
    (`GL\VectorGraphics\VGContext`, NanoVG na GL3), **bez Imagicka w ścieżce
    klatki**, także w dekodowaniu podglądów (`Texture2D::fromDisk`). Nowy
    prymityw obowiązuje odtąd **trzy renderery naraz**, a kompletności tabeli
    tłumaczeń pilnuje `PrimitiveTranslationTableTest` (renderer okienkowy
    i sixelowy; tekstowy jest zwolniony, bo świadomie degraduje kształty).
    Geometria okienna jest **lustrem sixelowej**: `GlfwFrameMetrics` powtarza
    reguły `SixelFrameMetrics` (pismo jako udział wiersza, obwódka środkiem
    skrajnych wierszy, prawa krawędź od prawej strony framebuffera) — nie
    wymyślaj jej od nowa. Komórkę dyktuje font zmierzony przez
    `VgContextService` (preferencje ścieżek TTF + `fc-match`), więc okno rodzi
    się **ukryte** i pokazuje dopiero zwymiarowane. Tekstury podglądów
    w `VgTextureCache` (limit + LRU, klucz ze ścieżki, czasu i rozmiaru;
    nieudane dekodowanie też jest wpisem). Z przełączników jakości działa
    **tylko `strokeAntialias`**; `textAntialias` i `paletteColors` mają jawne
    „nie dotyczy”. Pomiar: `bin/render-bench --window`, z barierą `glFinish()`
    w pomiarze (bez niej mierzy się zlecenie klatki, nie klatkę) — w aplikacji
    tej bariery nie ma i mieć nie powinna.
    **Zasób OpenGL ginie przed kontekstem** (poprawka z kroku 39): obiekty
    rozszerzenia zwalniają zasoby GL w destruktorach, więc obiekt żyjący do
    końca procesu robi to **po** `glfwTerminate()` — i proces kończy się
    SIGSEGV już po ostatniej linii kodu. Twórca takiego obiektu zamawia
    zwolnienie przez `GlfwWindowService::releaseBeforeClose()`, a `close()`
    wykonuje zamówienia w odwrotnej kolejności, zanim zniszczy okno. Dziś
    zamawia jeden (`VgContextService`), a `VGImage` z pamięci tekstur przeżywa
    kontekst bez szkody. **Nowy długo żyjący obiekt rozszerzenia to nowe
    zamówienie** — nie zakładaj, że zdąży zginąć sam.

11i. **Komponent nie czyta** (krok 29, D58). `TextView` pokazuje treść pliku
    i **pliku nie zna**: dostaje `list<string>` wierszy już zdekodowanych,
    z rozwiniętymi tabulatorami i oznaczonymi znakami sterującymi. Wejście-wyjście
    zostaje w module (`TextPreviewPort` + `TextPreviewService` w `Module/FileInfo`),
    bo tam mieszka wiedza o tym, co wolno przeczytać. Odczyt idzie **przesuwnym
    oknem jak w edytorze**: w pamięci są tylko widoczne wiersze, przewinięcie
    porzuca poprzednie i doczytuje następne. Cztery konsekwencje: miejsce w pliku
    to **bajt** (`TextAnchor`), a nie numer wiersza — numer jedzie obok i liczy się
    przyrostowo; **ile czytać, wiadomo dopiero przy rysowaniu**, bo budżet bierze
    się z geometrii panelu, więc zamówienie przewinięcia czeka na rozliczenie
    (wzorem `ScrollWindow::scrollBy()`/`clamp()`) i rozlicza **jeden panel na
    klatkę**; **suwak liczy się w bajtach**, bo liczby wierszy pliku nie znamy
    i poznać jej nie chcemy; **D46 tu nie obowiązuje** — okno to kilkadziesiąt
    kilobajtów, a wzorzec pracy kawałkowej dotyczy prac, których w klatce zrobić
    **nie da się**. Zawijanie łamie **po znaku, nie po słowie** i **nie ma górnego
    progu** — wiersz dłuższy od całego prostokąta wypełnia panel, a wysokość jest
    sufitem liczby kawałków, nie warunkiem zawinięcia. Do poprawki z 2026-08-12
    było odwrotnie i skutek był dokładnie przeciwny do zamierzonego: **jedyne
    wiersze, które nigdy się nie zawijały, to te najdłuższe**, czyli te, dla
    których zawijanie istnieje. Reguła ogólna z tej pomyłki: **próg chroniący
    przed pracą, którą i tak ucina pętla rysująca, nie chroni przed niczym** —
    ogranicznik pętli jest tańszy i nie zmienia wyniku. Przełącznik zawijania jest
    **pozycją ustawień modułu**, a `Alt`+`Z` zmienia tę samą pozycję (D40, jedna
    droga — jeden klucz). **Przewijanie liczy się w linijkach panelu, nie
    w wierszach pliku** (D60): kotwica stoi zawsze na początku wiersza, a ile jego
    linijek pominąć, mówi osobne pole stanu — dzięki temu nie trzeba mapować
    znaków na bajty w kodowaniach szerokich. Szerokość linijki musi być **ta
    sama** po stronie czytającej plik i rysującej go, więc liczy ją jedno miejsce
    (`TextView::contentColumns()`), a kolumna suwaka i kolumna numerów są
    **niezależne od treści** — biorą się z prostokąta, nie z tego, co akurat
    wczytano. Inaczej obraz pełznie w bok przy przewijaniu. Rozpoznanie tekstowości
    to **kaskada trzech metod**: rozszerzenie → opis od `file` → podejrzenie
    pierwszych bajtów; dwie pierwsze rozstrzygają wyłącznie twierdząco, ostatnia
    zawsze. Kodowanie rozpoznajemy z nagłówka i konwertujemy — **łącznie z UTF-16
    i UTF-32**; brak jednoznacznej odpowiedzi to UTF-8 z podmianą. Przy kodowaniu
    szerokim **bajt to nie znak**: znaku nowej linii szukaj w kodowaniu źródła
    i wyłącznie na granicy jednostki kodowej, bo `0A 00` wypada w UTF-16LE także
    w środku pary innych znaków, a kotwica przesunięta o bajt to pół znaku.
11j. **Słownik wejścia zna trzy modyfikatory, rozłącznie** (kroki 29 i 44):
    `ctrl` (skróty modułów, krok 19) i `alt` (zawijanie w podglądzie, cofanie) —
    oba **wyłącznie przy literach** — oraz `shift` (druga droga usunięcia,
    zaznaczanie zakresem) **wyłącznie przy klawiszach nazwanych**: litera
    z `Shift`em przychodzi z obu torów jako inna litera, więc znacznik przy
    znaku nie miałby czego nieść. Kombinacji modyfikatorów nie ma i nie
    wprowadzaj ich bez użytkownika; `Ctrl`/`Alt` przy klawiszach nazwanych CSI
    pozostają odrzucane, a bit `Shift`a parser czyta z drugiego parametru
    (`ESC [ 3 ; 2 ~` = `Shift`+`Delete`). W terminalu `Alt` przychodzi jako
    `ESC`+litera, więc **jest nieodróżnialny od `Esc` naciśniętego tuż przed
    literą** — to znana cena, nie usterka. Każde miejsce porównujące literę
    musi porównać znaczniki (`KeyBinding::matches()` robi to samo); goła litera
    nie ma prawa łapać skrótu z modyfikatorem, a **goły klawisz nazwany nie ma
    prawa złapać `Shift`a** — `F8` i `Shift`+`F8` znaczą od kroku 44 dwie różne
    rzeczy, z których jedna jest nieodwracalna. W ekranie rozstrzygaj `Shift`
    **przed** gałęziami klawiszy (wzorzec `BrowserScreen::shifted()`).
11k. **Słownik prymitywów otwarto raz i ma osiem kształtów** (krok 30, D59).
    Ósmy to `TextMark` — **napis na własnym tle**, dla dopasowania filtra. Zgoda
    użytkownika (D48) dotyczyła otwarcia, nie kształtu, a kształt rozstrzygnął
    się dopiero przy rozpisaniu: „samo tło pod fragmentem” byłoby **synonimem**
    `Bar`a z `Weight::Fill`, więc nowy prymityw musiał związać pismo z tłem
    w jednej rzeczy. Zyski: jedna zapamiętana bitmapa i jeden `compositeImage`
    zamiast dwóch (Sixel), tło **i** kolor pisma tej samej komórki (tekst),
    `TextRun` nietknięty. Reguła na przyszłość: **zanim dołożysz kształt,
    sprawdź, czy nie jest którymś z siedmiu pod inną nazwą** — precedensem jest
    karetka `TextInput` z kroku 19, która podświetlenie udała parą istniejących
    prymitywów. Zakresy dopasowania niesie **wiersz** (`TableRow::$marks`, klucz
    = numer kolumny, pusto domyślnie), liczone **w znakach, nie w bajtach**;
    przycięcie do widocznej treści należy do komponentu, bo tylko on wie, ile
    z napisu zostało.
11l. **Okno pamięta, jak je ustawiono** (krok 37, D67). Klucze rdzenia
    `windowColumns`/`windowRows` są od tego kroku **zarazem pamięcią rozmiaru**:
    okno zapisuje pod nie siatkę nadaną przeciągnięciem rogu. Cztery reguły, które
    z tego wynikają. **Miara to komórki, nie piksele** — klucze zostają jedne,
    a `WINDOW_*_CHOICES` przestaje być zakresem dopuszczalnych wartości i jest
    wyłącznie przystankami strzałek (zakresu pilnują `WINDOW_*_MIN`/`MAX`,
    a strzałka z wartości spoza listy idzie do sąsiada w swoją stronę).
    **Zapis następuje po uspokojeniu zmian** — `WindowSizeSettle` (czysty, bez
    GLFW) odnotowuje chwilę, pytanie „czy już cisza” pada raz na takt zaraz po
    `glfwPollEvents()`, a niedokończoną zmianę dopisuje `Bootstrap::shutdown()`;
    zapis przy każdym zdarzeniu znaczyłby dziesiątki zapisów pliku na sekundę.
    **Pamiętanie włącza się jawnie** (`rememberSize()` za pokazaniem okna), bo
    `bin/render-bench --window` zmienia rozmiar okna ukrytego kilkanaście razy
    w przebiegu i żaden z nich nie jest wyborem użytkownika. **Rozmiar narzucony
    pełnym ekranem nie jest wyborem** i do ustawień nie trafia; wyjście z pełnego
    ekranu wraca w te same piksele, bo `glfwSetWindowMonitor()` ich nie
    przechowuje. `F11` jest pierwszym klawiszem rdzenia **zależnym od trybu**
    (`InputHandler::globalBindings(bool $windowed)`) — w terminalu pełny ekran nie
    znaczy nic, a spis klawiszy pokazuje to, co działa tu i teraz. Ikona okna idzie
    **okrężną drogą, bo prostej nie ma**: PHP-GLFW 2.2 nie wystawia
    `glfwSetWindowIcon`, więc okno przedstawia się klasą (`GLFW_X11_CLASS_NAME`),
    a ikonę z ról motywu i wpis `.desktop` zakłada `bin/install-desktop-entry` —
    `StartupWMClass` musi się zgadzać z `WM_CLASS`. Skala treści
    (`glfwGetWindowContentScale`) jest **czytana i pokazywana w pomocy, a nie
    stosowana**: maszyna projektu ma 1.0, więc przeliczenie komórki byłoby kodem
    bez sprawdzenia.
11m. **Komponent dostaje drzewo spłaszczone** (krok 31, D68). `TreeView` rysuje
    listę `TreeNode`ów — bez wskaźnika na rodzica, bez listy dzieci — bo komponent
    schodzący sam po gałęziach musiałby wiedzieć, skąd biorą się dzieci, a biorą
    się z odczytu katalogu (D42). Spłaszcza więc **moduł** (`BrowserTree`), tak jak
    w kroku 22 spłaszczał ekran, a węzeł niesie `guides` — po jednej wartości
    logicznej na przodka — bo z samej głębokości nie da się narysować prowadnicy:
    poziom przodka, który był ostatni, musi zostać pusty. `TreeState` jest
    **czwartą klasą stanu między klatkami** (po `ScrollWindow`, `SectionState`
    i `SplitState`) i różni się od `SectionState` trzema rzeczami naraz:
    trzyma **rozwinięcia** (domyślnie zwinięte, odwrotnie niż sekcja), jego kursor
    jest **kluczem, nie numerem** (numer wiersza zmienia każde rozwinięcie powyżej),
    a zwinięcie gałęzi **przenosi na nią kursor**. Odczyt gałęzi jest **na żądanie
    i najwyżej jeden na klatkę**: rozwinięcie klawiszem czyta od razu (kosztuje
    tyle, co `Enter` w liście), a gałęzie odtwarzane po powrocie do katalogu
    dochodzą po jednej na takt (D46). Widok panelu — lista albo drzewo — należy do
    **panelu**, nie do ustawień: przełącza go `Ctrl`+`T`, a ustawieniem modułu jest
    wyłącznie **głębokość** (`treeDepth`, z wartością `∞`). `Ctrl`+`T` mieszka przy
    tym w przestrzeni skrótów modułów (krok 19) i działa tylko dlatego, że litery
    `t` nie zajął żaden moduł — pilnuje tego `BrowserShortcutsTest`.
11n. **Menu kontekstowe jest widokiem na rejestr komend, nie drugim rejestrem**
    (krok 32, D69). `MenuOverlay` (`F9`) bierze pozycje z `CommandRegistry`
    i wywołuje `execute()` — tę samą linię, co okno komend; drugi zbiór działań
    byłby długiem z reguły 15. Zawężenie do zaznaczenia niesie zdolność
    `Application\Command\AppliesToSelection` (`appliesTo()` + `inputFor()`),
    doklejana **obok** kontraktu wzorem `SuggestsArguments` — nie w
    `CommandInterface`, bo komenda bez związku z zaznaczeniem ma o tym milczeć,
    a nie odpowiadać „nie”. Granica biegnie po **zaznaczeniu, nie po module**:
    `browser.hidden` i `browser.tree` są w rejestrze, ale w menu ich nie ma.
    Czynność mająca dwa wejścia (klawisz i komenda) mieszka w **jednym** miejscu
    (`HiddenEntries`, `EntryOperations`) — dwie implementacje rozjeżdżają się przy
    pierwszej poprawce.
    **Komenda potrzebująca okna deklaruje `Presentation\Ui\Command\OpensOverlay`**
    (krok 47, D78) — `overlayFor()` oddaje okno albo `null` („wykonaj mnie
    zwyczajnie”), a pytają o to obaj wołający komend: okno komend i menu. Granica
    warstw nie była tu nigdy przeszkodą i nie próbuj jej obchodzić
    identyfikatorem: **wszystkie** komendy leżą w `Presentation`, w `Application`
    leży sam kontrakt. Granica menu brzmi odtąd: pokazuje czynności zmieniające
    **zawartość miejsca**, a nie **sposób oglądania** go — dlatego `browser.mkdir`
    jest w menu mimo że nie dotyczy zaznaczenia, a `browser.hidden` i
    `browser.tree` nadal nie są. Kontekst przychodzi migawką przy otwarciu (`useContext()`, nie
    `Resettable`: stos woła `reset()` po otwarciu i skasowałby policzone
    pozycje), okno staje pośrodku jak `ConfirmOverlay`, a menu bez pozycji
    **nie otwiera się** i mówi zdaniem w pasku stanu.
11o. **Dźwięk jest modułem i gra poza ścieżką klatki** (krok 36, D70).
    `src/Module/Audio/` — dwie komendy (`audio.music`, `audio.volume`), zakładka
    ustawień, zakładka pomocy, **bez ekranu, bez skrótu i bez komponentu**; rdzeń
    kosztuje jedną pozycję na liście w `Bootstrapie` (reguła 15). Port modułu
    `AudioPort` ma dwie implementacje: `GlAudioService` (na `GL\Audio\Engine`)
    i `SilentAudioService` (pusty obiekt), a wybór zapada **raz**, przy składaniu
    modułu — brak `ext-glfw` nie jest rozgałęzieniem w komendach. Silnik **nie
    potrzebuje okna** (startuje bez `glfwInit()`), więc muzyka gra we wszystkich
    trzech torach. Trzy pułapki: referencja do `Sound` musi **przeżyć całą grę**
    (pole, nie zmienna lokalna), `Sound::stop()` jest **pauzą** (stąd jedna
    komenda-przełącznik), a **autostartu nie ma** — kontrakt modułu nie zna cyklu
    życia i nie wolno go rozszerzać dla wygody jednego modułu. Sprzątanie dwiema
    drogami (D47). **Testy nie uruchamiają silnika w ogóle**: sprawdza się
    wszystko przed pierwszą prośbą o granie, a resztę atrapą portu
    (`tests/Support/StubAudio`). Głośność jest **liczbą z listy przystanków**, bo
    `ModuleSetting::valueFrom()` sprowadza wartość spoza listy do domyślnej.
11o'. **Moduł dostaje takt, a playlista zastępuje klucz `track`** (krok 45, D82).
    Zdanie „autostartu nie ma, bo kontrakt nie zna cyklu życia” jest **odwołane**,
    i to jawnie: `Application\Module\NeedsTick` (`tick(float $now)`) deklaruje się
    osobno jak `ProvidesCommands`, a `Presentation\Cli\ModuleTicker` woła je raz
    na klatkę z `GameLoop`, w fazie „aktualizuj stan”, dla **każdego przyjętego**
    modułu, który o to poprosił. Warunek, pod którym wolno się na to powołać, jest
    ostry: zdolność wchodzi wtedy, gdy **bez niej funkcja nie istnieje** — nie
    wtedy, gdy jest wygodna (różnica wobec D70). Trzy reguły taktu: **tani**
    (porównanie stanu, nigdy wejście-wyjście — praca dłuższa od klatki idzie
    kawałkami wedle D46), **niczego nie wymusza** (nie prosi o przerysowanie, nie
    zwraca skutku), **nie rzuca** (łapie `ModuleTicker`, jak wyjątki ekranu; jeden
    zepsuty moduł nie zabiera taktu pozostałym). Czas idzie z zewnątrz i ma
    użytkownika: **karencja pół sekundy po starcie utworu**, bo `play()` wraca,
    zanim wątek miksujący odnotuje granie. Playlista mieszka w **pliku stanu
    modułu** `~/.light-manager/audio.json` (ustawienia biorą wyłącznie skalary),
    zapisywanym wzorem historii komend; nieznane klucze przeżywają zapis, bo krok
    46 dołoży do nich mapę hooków. Pozycja bez pliku **zostaje wyszarzona**
    i wypada tylko z wyboru „co dalej”; `loop` zamienił się w `PlaybackMode`,
    a `track` żyje wyłącznie jako źródło migracji. Okno (`Ctrl`+`A`) **nie dokłada
    komponentu**, kolejność zmienia `Shift`+strzałkami (`Alt` przy klawiszach
    nazwanych nie istnieje — 11j), a utwory wchodzą trzema drogami: `F5`
    (`ReadsContext`), `F7` (pole tekstowe), `audio.add` (komenda).
11o''. **Zdarzenia aplikacji: słownik zamknięty, rejestr zamiast szyny** (krok 46,
    D83). Rdzeń ogłasza **pięć** nazwanych momentów (`Application\Event\AppEvent`:
    trzy tony komunikatu z `LoopState::report()`, otwarcie okna nakładanego,
    wykonanie komendy), a moduł wnosi własne, deklarując `DeclaresEvents` —
    przeglądarka **siedemnaście** (`BrowserEvent`: kursor, wejście do katalogu,
    zaznaczenie i siedem czynności × udana/nieudana). Odbiera ten, kto zadeklaruje
    `ListensToEvents`; obie zdolności leżą w `Application/Module`, bo nie wymieniają
    typów z `Presentation`. **Zamknięcie jest konstrukcyjne**: nazwy pochodzą
    z enumów, a deklaracja katalogu powstaje z `cases()`, więc publikacja i spis
    u odbiorcy nie mają jak się rozjechać; rozszerzenie słownika wymaga **zgody
    użytkownika**, jak przy prymitywach (11k). Nazwa musi stać w przestrzeni
    publikującego (`core.*`, `browser.*`) — spoza niej odsiewa `EventRegistry`, jak
    `CommandRegistry` odsiewa komendy. Trzy reguły wykonane w `publish()`:
    **nie rzuca** (wyjątek odbiorcy ginie tam, bo publikacja stoi w środku
    `report()` i w środku czynności na plikach), **nie wie, kto słucha** (zero
    odbiorców = jedno sprawdzenie w tablicy), **zdarzenie nie rodzi zdarzenia**
    (publikacja w trakcie odbioru jest ignorowana — inaczej łańcuch zapętliłby
    pętlę). Zdarzenie niesie **wyłącznie tożsamość** (D40 P5); odbiór dostaje napis,
    niczego nie zwraca i **nie dostaje czasu** — kto go potrzebuje, bierze z taktu
    (11o'). Rejestr mieszka w `LoopState`, obok kontekstu sesji i z tego samego
    powodu: stan pętli dostaje każdy moduł, więc `Bootstrap` rośnie o **jedną
    linię**. O skutku czynności rozstrzyga **ton zdania**, które po niej zostało
    (`BrowserEvents::outcome()`), a nie drugi rachunek prowadzony obok.
    Pierwszy odbiorca — efekty modułu dźwięku: mapa „zdarzenie → plik" w kluczu
    `hooks` tego samego pliku stanu, drugi uchwyt `Sound` (efekt gra **na**
    muzyce, nowy przerywa poprzedni), **minimalny odstęp 100 ms na zdarzenie** po
    stronie odbiorcy, mapa wczytywana w takcie (**odbiór nie dotyka dysku**),
    przełącznik przy każdym przypisaniu (spacja) **plus** jeden globalny i własna
    głośność w zakładce. Okno rośnie do dwóch paneli (`Split`), a poniżej progu
    szerokości widać ten **z ogniskiem** — inaczej niż w przeglądarce, bo panele są
    dwiema różnymi rzeczami.
11p. **Ognisko deklaruje się, a nie odkrywa** (krok 40, D74). Aplikacja **nie ma
    zachowanego drzewa komponentów** (11a), więc „znajdź element z kursorem” nie
    jest wykonalne — pyta rdzeń, a odpowiada ten, kto ognisko trzyma:
    `Presentation\Ui\DeclaresFocus::focus()` oddaje `FocusHint`, czyli **klucz
    etykiety miejsca plus jego wiązania**. Zdolność deklaruje się osobno, jak
    `NeedsTime` i `DrawsOwnFrame`; ekran o jednym miejscu (pomoc) nie deklaruje
    nic. **Nowy ekran z więcej niż jednym miejscem ognisko deklaruje** — inaczej
    stopka będzie o nim milczeć. Pasek stanu składa `StatusHints`: trzy poziomy
    w kolejności **miejsce → ekran albo okno nakładane → globalne wraz ze skrótami
    modułów** (okno **wypiera** ekran, bo klawisze do niego nie schodzą),
    ustępowanie **od końca**, `F1` przypięty, powtórzenie = ten sam zestaw klawiszy
    **i** ten sam klucz opisu. Trzy zobowiązania przy dopisywaniu wiązania:
    `bindings()` zostaje **pełnym** spisem (składa się z wiązań miejsca **plus**
    własnych — okno pomocy się nie zawęża), opis dostaje **drugi, krótki klucz**
    (`<klucz>.short`; brak znaczy „użyj długiego”), a klawisz działający w danym
    miejscu **musi** tam stać w spisie — i odwrotnie. Pilnuje tego jeden test
    dla wszystkich ekranów i położeń (`tests/Functional/StatusHintsFlowTest.php`).
    Pasek wolno urosnąć do **dwóch wierszy**: to jedyna odpowiedź `HudLayout`
    zależna od treści, wiersz bierze się **liście** (jedynej szczelinie
    elastycznej) i tylko powyżej progu **dwudziestu wierszy** — przesuniętego tam
    z 28 w kroku 47, gdy zniknął pas podglądu, z którego był liczony. Dawne zdanie z kroków 14 i 18 — „stopka nie
    jest ściągawką, tylko wskazaniem, gdzie ściągawka leży” — jest **odwołane co do
    zasięgu**; źródłem podpowiedzi pozostaje `KeyBinding`, nigdy napis w katalogu.
11r. **Praca poza maszyną idzie procesem potomnym, nie rozszerzeniem** (krok 48,
    D87). Moduł `src/Module/Ssh/` utrzymuje sesję SSH przez **`ControlMaster`
    klienta OpenSSH**: `ssh -M -N -f` zestawia ją raz i demonizuje się sam, każda
    późniejsza operacja to krótki potomek wchodzący przez gniazdo **bez uścisku
    dłoni**. Odwraca to D84 nr 2 („dostęp w procesie, przez `ext-ssh2`"), i to
    jawnie: rozszerzenie nie ma ani jednego wywołania nieblokującego,
    a `ssh2_connect()` nie przyjmuje limitu czasu — host nieosiągalny zamroziłby
    **całą aplikację** na minutę. Reguła nadrzędna Fazy XVII: **żadne wywołanie
    sieciowe nie pada w rysowaniu klatki** — tu spełniona mocniej, bo żadne nie
    pada w procesie aplikacji w ogóle. Potomków uruchamia **rdzeniowy
    `BackgroundProcessPort`**, więc obowiązuje jego „jedna praca naraz": stan
    sesji odświeża się **wyłącznie na żądanie** (`F5`), bo pytanie co kilka sekund
    zabijałoby cudzą pracę tłową w kółko. Trzy pułapki warte zapamiętania przy
    każdym potomku rozmawiającym z siecią: **diagnostyka idzie na strumieniu
    błędów**, którego `BackgroundState` nie niesie — stąd `2>&1`, wolne tutaj, bo
    mistrz z `-N` na standardowym wyjściu milczy; **hasła nie da się podać
    wejściem** (`ssh` czyta je z terminala sterującego, a port potomkowi wejścia
    nie podaje) — idzie przez `SSH_ASKPASS` i zmienną środowiskową, **nigdy przez
    wiersz polecenia**; **wartość zaczynająca się od `-` jest opcją, nie
    argumentem**, i żadne `escapeshellarg()` przed tym nie chroni, więc pilnuje
    tego samowalidacja obiektu wartości. Zaufanie do klucza hosta dzieli się na
    pół: **czyta moduł** (`KnownHostsReader`, HMAC-SHA1 nazwy **solą jako
    kluczem**), **pisze `ssh`** (`StrictHostKeyChecking=accept-new`) — aplikacja
    nie dopisuje do `~/.ssh/known_hosts` ani razu. Brak klienta **odrzuca moduł**
    (`RequiresEnvironment`), a nie zostawia go na pustym obiekcie jak `ext-glfw`
    w module dźwięku: cisza jest sensowną postacią muzyki, spis hostów, z którymi
    nie da się połączyć — nie jest sensowną postacią sesji.
11r'. **Przesył plików: nazwa robocza, `rename -l` i postęp czytany `stat`em**
    (krok 50, D89). `F5` pobiera, `F6` wysyła, a odświeżanie listy przeprowadziło
    się na `Ctrl`+`R` (pilnuje `RemoteShortcutsTest`, wzorem `Ctrl`+`T` z kroku
    31). Pięć reguł, których nie wolno tu uprościć. **Treść ląduje pod nazwą
    roboczą** (`.<nazwa>.lm-part`), a zatwierdza ją zmiana nazwy — lokalnie
    `FileOperationsPort`, zdalnie `rename` w tym samym wsadzie; przerwanie
    zostawia więc plik, który nazwą mówi, czym jest, a nie plik wyglądający na
    gotowy. **Zdalne zatwierdzenie idzie `rename -l`**, bo zwykłe `rename` używa
    rozszerzenia `posix-rename@openssh.com` i **nadpisuje cicho** (zmierzone: kod
    zero na zajętej nazwie); cel zwalnia się jawnie (`-rm`) i tylko po zgodzie
    użytkownika. **Postępu nie da się wziąć od klienta** — `sftp` rysuje pasek
    wyłącznie na terminalu sterującym (`progressmeter.c`: `getpgrp()` vs
    `tcgetpgrp()`), więc na potoku milczy nawet po poleceniu `progress`; bajty
    czyta się **rosnącym plikiem roboczym**, co działa przy pobieraniu i nie
    działa przy wysyłaniu (tam pasek liczy pliki). Ten sam odczyt wykrywa
    **zastój** — brak przyrostu przez 30 s kończy pracę, bo zerwane łącze poznaje
    się po kodzie wyjścia, a `sftp` ginie wtedy od `SIGPIPE` z **pustym**
    strumieniem błędów. **Jeden potomek na plik**, bo wsad przerywa się na
    pierwszym błędzie; kolizję rozstrzyga strona, która wie za darmo (dysk przy
    pobieraniu, lista panelu przy wysyłaniu), a odpowiedzi bierze się z rdzenia
    (`TransferChoice`). Drugą stronę przesyłu daje **zatrzask kontekstu**
    (`LocalPlace`): ekran zdalny publikuje własny kontekst, więc lokalnej ścieżki
    nie ma czego zapytać, a `ReadsContext` podaje ją przed rysowaniem.
11s. **Moduł może odmówić startu, a rejestr ma na to zdolność** (krok 48, D87
    nr 11). `Application\Module\RequiresEnvironment::unavailableReason()` oddaje
    **klucz katalogu albo `null`**; `ModuleRegistry::admit()` pyta o to **przed**
    sprawdzeniem skrótu, żeby moduł, który i tak nie wejdzie, nie zabrał litery
    komuś, kto by działał. Jest to **piąty powód odrzucenia i pierwszy zależny od
    maszyny** — cztery poprzednie są błędami autora modułu i w wydanej aplikacji
    nie zdarzają się nigdy. Dwie reguły zdolności: **odpowiedź musi być tania**
    (`command -v`, `is_file()`, `extension_loaded()` — nigdy uruchomienie
    programu, bo pytanie pada w ścieżce startu) i **pada raz na uruchomienie**.
    Skutek uboczny naprawiony przy okazji: napisy do katalogu wchodzą odtąd
    z **`declared()`, nie `accepted()`** — spis na zakładce „Moduły" tłumaczy
    także moduł odrzucony, a przy `accepted()` wypisywał tam surowe klucze.
11t. **Docker rozmawia gniazdem, compose procesem potomnym** (krok 51, D90).
    Moduł `src/Module/Docker/` (`Ctrl`+`O`) idzie do demona przez
    `/var/run/docker.sock` — `ext-curl` z `CURLOPT_UNIX_SOCKET_PATH` i rodzina
    `curl_multi_*` w trybie nieblokującym, pompowana raz na takt (`NeedsTick`);
    `curl_multi_select()` nie pada ani razu, bo pytanie o gotowość deskryptorów
    kosztuje tyle, co samo posunięcie transferu. **Compose idzie osobnym portem
    i procesem potomnym**, bo demon nie ma dla wtyczki ani jednego zasobu w API —
    i jest to jedyna część modułu sięgająca po rdzeniowy port pracy tłowej. Dwie
    pułapki strumieniowe, obie dające „działa, ale wygląda na zepsute”: **logi bez
    TTY są multipleksowane** ośmiobajtową ramką (o tym, czy strumień jest
    ramkowany, rozstrzyga **treść**, i rozstrzyga się to dopiero z ósmym bajtem —
    porcja krótsza wygląda jak zwykły tekst, a odpowiedź raz udzielona obowiązuje
    do końca), a **niepowodzenie budowy przychodzi w treści, nie w kodzie
    odpowiedzi** (nieudana budowa kończy się HTTP 200). Kontekst budowy pakuje
    `PharData` pracą kawałkową, z pominięciem tego, co wyklucza `.dockerignore` —
    czytany w podzbiorze składni, którego różnica objawia się **rozmiarem
    kontekstu, a nie wynikiem budowy**. Brak `ext-curl` albo gniazda **odrzuca
    moduł** (11s), ale **leżący demon nie**: rozszerzenia nie da się doładować
    w trakcie działania, a demona da się podnieść. Rozczytywanie cudzych formatów
    (JSON demona, ramki, strumień budowy) leży w `Infrastructure` **za portami**
    (`DockerCatalogPort`, `LogReaderPort`, `BuildReaderPort`), bo stan listy jest
    daną warstwy `Application` i nie ma prawa znać ani jednej klasy stamtąd
    (reguła 4).
11u. **Zamawiając pracę tłową, powiedz, czym jest jej wypis** (krok 52, D91 nr 12).
    `Application\Dto\OutputShape` ma dwie wartości i **przeciwne** reguły wobec
    granicy bufora: `Result` (domyślny) zbiera do granicy i **odrzuca nadmiar** —
    tak jak port robił od kroku 26, bo suma `du` stoi w pierwszym wierszu;
    `Stream` **zapomina najstarsze**, bo inaczej log dobiłby do granicy
    w kilkanaście sekund i zamilkł na zawsze przy potomku, który nadal pisze.
    Ile bajtów wypadło, mówi `BackgroundState::$droppedBytes` — czytający
    strumień trzyma **własny licznik bajtów bezwzględnych** i z różnicy pozna
    dziurę, o której ma powiedzieć zdaniem (log ucięty po cichu wygląda tak samo,
    jak log, w którym nic się nie działo). Kształt jest własnością **zamówienia,
    nie polecenia**: to samo `kubectl logs` bywa jednym i drugim, zależnie od `-f`.
11v. **Klaster rozmawia procesem potomnym, a rodzaje zasobów przychodzą z niego**
    (krok 52, D91). Moduł `src/Module/Kubernetes/` (`Ctrl`+`K`) woła `kubectl`
    rdzeniowym portem pracy tłowej — **żadne wywołanie nie pada w rysowaniu
    klatki**, bo żadne nie pada w procesie aplikacji (reguła nadrzędna z kroku 48).
    Cztery rzeczy warte zapamiętania. **Limit czasu jest częścią każdego
    wywołania**, i to podwójny: `--request-timeout` (klient przestaje czekać na
    serwer) plus limit procesu — z jednym wyjątkiem, którym jest strumień logów,
    bo limit żądania zamknąłby go w chwili, gdy zaczyna działać. **Rodzajów
    zasobów nie ma w kodzie**: pochodzą z `api-resources`, więc CRD wchodzą do
    drzewa same, a `ResourceKind` jest **daną z klastra**, nie gałęzią `match`a;
    kolumny własne rodzaju mają za to pakiety pisane ręcznie (D91 nr 4) i rodzaj
    spoza spisu pokazuje trzy kolumny ogólne — to jest zapisana cena, nie usterka.
    **`api-resources` jest jedynym wywołaniem oddającym tekst** (klient 1.25 nie
    umie tam JSON-a), a wiersz rozbiera się wyrażeniem opartym na niezmiennikach,
    **nigdy podziałem po spacjach**: pusta kolumna `SHORTNAMES` przesuwa wtedy
    wszystkie pozostałe i `namespaced` czyta się z `APIVERSION`. **Sekrety są
    zamaskowane** w liście, w opisie i w YAML-u; odsłania jeden klucz `x`, a zmiana
    idzie `kubectl patch --type=merge -p` — argumentem, bo potomek nie dostaje
    wejścia (ta sama reguła unieważnia `apply -f -`).
11w. **Dane czyta się przez rejestr kwerend — i nie ma drugiej drogi** (krok 53,
    D92). `Application\Query\QueryRegistry` jest **jedynym** wejściem do danych:
    także dla rdzenia i także dla modułu czytającego **własny** stan. Zdanie
    trzymające podział: **komenda robi, kwerenda mówi** — co zmienia stan, jest
    komendą i wraca `CommandOutcome`; co go czyta, jest kwerendą i wraca
    `QueryResult`. Moduł wnosi swoje zdolnością `Application\Module\ProvidesQueries`
    (jedna linia w rdzeniu, wzorem zdarzeń), rdzeń wylicza swoje w `CoreQueries`.
    **Cztery reguły**: czyta i nie zmienia; nie zna wołającego; nie woła kwerendy
    (wzorem „zdarzenie nie rodzi zdarzenia"); odpowiada w klatce albo oddaje
    **stan pracy** (`ChecksumStage`, `DiskUsageStage`), nigdy nie czeka na jej
    koniec. **Wynik ma dwa oblicza, nie dwa kanały**: wiersze danych pierwotnych
    dla każdego i ładunek typowany wyłącznie dla właściciela
    (`QueryResult::payloadFor()`), więc ekran modułu nadal rysuje z własnych typów,
    a cudzy moduł dostaje napisy i liczby (reguła 15 nietknięta). **Rozpakowanie
    ładunku stoi w jednej klasie na moduł** — fasadzie (`BrowserQueries`,
    `AudioQueries`, `FileInfoQueries`); poza nią i poza samą kwerendą nikt nie
    czyta obiektu stanu. **Routing**: tani `generation(): int` (ten sam numer =
    ta sama odpowiedź) plus pamięć wyniku w rejestrze; wiersze budują się
    **leniwie**. Źródło bez naturalnego licznika deklaruje `VOLATILE` i **nie jest
    pamiętane w ogóle** — pamiętanie „na jedną klatkę" oddawało stan sprzed zmiany,
    która padła w tej samej klatce (wykrył test przełącznika muzyki). Kwerendy
    ogląda się w **drugim trybie okna komend** (`F12`, przełącza `Tab` przy pustym
    polu); opis idzie przez katalog napisów jak przy komendach, a pilnuje tego test
    czytający oba języki. Zdanie „kontekst mówi, gdzie użytkownik stoi; kwerenda
    mówi, co u mnie jest" rozważano i **odwołano** (D92 nr 8): skoro drugiej drogi
    do danej nie ma, `core.context` i `browser.cwd` są kanałem, a nie powtórką.
11. **Nowy element interfejsu to nowy komponent w `Presentation/Ui/Component`**,
    a nie nowa metoda w rendererze. Komponent oddaje prymitywy z ról motywu i
    prostokątów w siatce znakowej — pikseli nie zna. Słownik prymitywów jest
    **zamknięty**; jego rozszerzenie to obowiązek dla **trzech** rendererów naraz
    (od kroku 35) i wymaga zgody użytkownika. Komponent znający typ domeny
    **modułu** leży w `Presentation/Component` tego modułu, nie w katalogu
    rdzenia (krok 21: `PathLine`, `PreviewBox`); tą samą zasadą okno nakładane
    znające stan modułu leży w jego `Presentation/Overlay` (krok 30:
    `FilterOverlay`).
12. **Ekran rysuje dwie strefy, nie jedną** (krok 21, D42; krok 47, D78):
    `header()` oddaje `?ScreenZone` — klucz etykiety obwódki plus komponent
    z treścią — a `null` znaczy „strefa nie powstaje, jej wiersze idą do środka”.
    Rdzeniowi zostają **oprawa stref i pasek stanu**; ekran nie rysuje ramek —
    z jednym wyjątkiem, którym jest ekran podzielony (`DrawsOwnFrame`, reguła 11c).
    Zasada kroku 20 „moduł dostaje środkowy panel i nic poza nim” **nie
    obowiązuje**. `headerSuffix()` i `usesPreview()` nie istnieją.
    **Stref było trzy do kroku 47** — `preview()` (pas podglądu) wyszedł
    z kontraktu wraz ze strefą w `HudLayout`, jej progiem i płaszczyzną
    w `FrameComposer`, bo po wyprowadzeniu miniatury do modułu `FileInfo` (D76)
    nie zamawiał go **ani jeden** ekran, a mechanizm rdzenia bez odbiorcy łamie
    regułę 13. Skutek uboczny do zapamiętania: **próg dwuwierszowego paska stanu
    przesunął się z 28 na 20 wierszy**, bo był liczony jako `ROWS_FOR_PREVIEW + 2`
    — uzasadnienie zostało to samo, zmieniła się arytmetyka. Ekran, który znowu
    zechce strefę skrajną, dowozi ją **razem z odbiorcą**.
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
    **Reguła działa też wstecz** (krok 47, D78): mechanizm, który **stracił**
    ostatniego odbiorcę, wychodzi z rdzenia, a nie zostaje na zapas. Tak zniknął
    pas podglądu z kontraktu ekranu — decyzja D76 zostawiła go bez użytkownika
    i zapisała to jako dług, a nie jako wyjątek.
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
15g. **Moduł sięga po cudze dane wyłącznie nazwą kwerendy** (krok 53, D92).
    Trzy zdania graniczne dopowiedzenia reguły 15: moduł zna **nazwę** cudzej
    komendy i kwerendy (napis), nigdy jej typ; kwerenda oddaje obcym **dane
    pierwotne** (precedens `ModuleContext`, D40 P5), a ładunek typowany wyłącznie
    właścicielowi; **moduł pytający musi umieć żyć bez odpowiedzi**, bo ten drugi
    bywa wyłączony, odrzucony albo nieobecny — `QueryRegistry::ask()` oddaje wtedy
    wynik z powodem, nie `null` do obsłużenia w każdym miejscu z osobna. Moduł
    niezarejestrowany w rejestrze kwerend **nie widzi własnych danych** — dotyczy
    to także testów składających moduł samodzielnie, które muszą powtórzyć
    `useModules()` z `Bootstrapu`.

15b. **Reguła 15 ma dokładnie jeden wyjątek i jest on nazwany: zapis na dysk**
    (krok 41, D66/D75). Rdzeń ma port operacji na plikach
    (`Application\Port\FileOperationsPort` + `Infrastructure\FileSystem\FileOperationsService`),
    choć operacji potrzebuje dziś jeden moduł. Powodem jest **druga** reguła tej
    samej pary: „moduł nigdy nie sięga do innego modułu” znaczy przy dwóch
    odbiorcach (przeglądarka, opis pliku) dwie kopie kodu piszącego po dysku —
    a powtórzone `unlink()` kosztuje utratę danych w dwóch miejscach zamiast
    w jednym. Powtórzony rachunek `permissionsAsText()` wolno było zostawić, bo
    kosztował dziesięć linii bez skutków ubocznych.
    **Granicą jest katalog `Infrastructure/FileSystem`, a nie jedna klasa**
    (krok 42, D79 nr 1): kopiowanie dostało własny port i własną usługę, bo jego
    stan nie ma nic wspólnego ze stanem usuwania; kosz (krok 44, `TrashPort`
    + `XdgTrashService`) — trzecią parę z tego samego powodu. Zasada zostaje ta
    sama — wszystko, co pisze po dysku, idzie **przez port rdzenia**.
    **Granica wyjątku, poza którą nie wolno wyjść:** rdzeń zna *ścieżkę
    bezwzględną jako napis*, *nazwę jako napis* (bez oceny, czy jest poprawna),
    *dziewięć czynności* (zmiana nazwy, nowy katalog, usunięcie wpisu, usunięcie
    drzewa, kopiowanie i przeniesienie — trzy ostatnie pracą kawałkową — oraz
    przeniesienie do kosza, rezerwacja w nim nazwy i przywrócenie z niego)
    i *stan tej pracy*. Kosz jest przy tym **katalogiem podawanym w każdym
    wywołaniu**, bo jego wybór to pozycja ustawień modułu, a układ w środku —
    zawsze freedesktop.org, z plikiem informacyjnym **przed** przeniesieniem.
    Nie zna wpisu, katalogu,
    sortowania, ukrywania, zaznaczenia ani podglądu — `Entry`, `Directory`,
    `DirectoryPath` i `EntryType` nie mają prawa trafić do sygnatury niczego
    w `src/Application` ani `src/Domain` (pilnuje `CoreKnowsNothingAboutFilesTest`).
    Poprawność nazwy zna **moduł** (`Module\Browser\Domain\ValueObject\EntryName`),
    a rdzeń **nie rysuje** niczego z powodu operacji: okna, klawisze i komunikaty
    zamawia moduł. **Próba na przyszłość:** funkcja, która chce wejść do rdzenia
    tym samym argumentem, musi mieć **dwóch odbiorców** i powtórzenie o koszcie
    **nieodwracalnym**. Inaczej jest modułem, jak wszystko inne.
    **Krok 43 zmienia w tej granicy jedno słowo:** czynności biorą **listę
    ścieżek**, a nie jedną (`beginRemoval(list<string>)`, jak `begin()` w porcie
    kopiowania od kroku 42). Skąd ta lista się wzięła — z zaznaczenia czy
    z kursora — port nadal nie wie i wiedzieć nie ma prawa.
    **Krok 50 dokłada zdanie graniczne, którego wcześniej nie było:** pisanie
    **przez proces potomny** nie jest obejściem tej reguły, dopóki każda zmiana
    nazwy i każde skasowanie idą przez port rdzenia. Przesył plików pisze po
    dysku `sftp`-em uruchomionym rdzeniowym `BackgroundProcessPort`, a zatwierdza
    i sprząta `FileOperationsPort`em — więc wyjątek 15b zostaje przy **jednym**
    nazwanym przypadku, a moduł nie woła ani `rename()`, ani `unlink()`. Próba na
    przyszłość nie zmienia się: dwóch odbiorców i powtórzenie o koszcie
    nieodwracalnym.
15c. **Zaznaczenie wielokrotne jest własnością panelu, a operacje biorą je jako
    listę nazw** (krok 43, D80). `MarkedEntries` mieszka w `Domain` modułu
    przeglądarki, a jego właścicielem jest `BrowserState` — obok filtra i z tego
    samego powodu, co on (dwa panele na tym samym katalogu mają prawo zaznaczać co
    innego). Trzy reguły, których pilnuj przy każdej nowej czynności:
    **pusty zbiór znaczy „wpis pod kursorem”**, a nie „nic” — i rachunek stoi
    w jednym miejscu (`BrowserState::operands()`, `BrowserPanes::focusedOperands()`),
    nie w każdej czynności z osobna; **zbiór trzyma nazwy, nie numery**, więc
    przeżywa zawężenie filtrem, przycina się wyłącznie w `refresh()` i ginie
    razem z katalogiem w `enter()`; **po operacji zaznaczone zostaje to, czego
    nie dotknęła** (pominięte, nieudane) — jedyna droga, którą widać, co się nie
    udało. Zaznaczenie należy do **listy**: panel pokazujący drzewo ani go nie
    rysuje, ani na nim nie działa, choć zbiór przeżywa przełączenie widoku.
    Rdzeń urósł przy tym o trzy liczby w `ModuleContext` (liczba, suma rozmiarów,
    liczba katalogów) — **wbrew rekomendacji planu**, na rozstrzygnięcie
    użytkownika, i wyłącznie dlatego, że odbiorca wszedł razem z mechanizmem
    (moduł opisu pliku, reguła 13) — oraz o **dwunastą rolę motywu** (`Marked`,
    zieleń w czterech paletach). Ta druga wyszła z **obejrzenia klatki**, a nie
    z projektu: pierwsza wersja malowała wiersz rolą `Warning`, a ta jest
    w Grafitcie tym samym kolorem, co akcent (D25), więc zaznaczony plik wyglądał
    w domyślnym motywie jak katalog. Wniosek na przyszłość: **rola dobrana
    „znaczeniowo” bez sprawdzenia palety bywa rolą bez koloru** — cztery motywy
    trzeba przejrzeć, zanim uzna się sygnał za widoczny.
15d. **Usunięcie ma dwie drogi, a kosz ma drogę powrotną** (krok 44, D81).
    Klawisz domyślny (`F8`/`Delete`) robi to, co mówi pozycja modułu „usuwaj do
    kosza” (domyślnie: kosz), `Shift`+klawisz — zawsze to drugie; usunięcie
    trwałe **pyta zawsze**, oknem groźnym, a ustawienie „pytaj przed usunięciem”
    rządzi odtąd koszem. Kosz przenosi **zmianą nazwy, nigdy kopiowaniem**;
    wpis z innego systemu plików dostaje pytanie o trzech odpowiedziach
    (skopiuj pracą kawałkową pod nazwą zarezerwowaną mapą `targetNames` /
    usuń trwale / przerwij), a `.Trash-$uid` na wolumenie nie powstaje.
    **Stos cofnięć leży w module** (`Module/Browser/Application/Undo/`), nie
    w rdzeniu — jeden piszący, jeden czytający, reguła 15 — a spis operacji
    odwracalnych mieszka w `UndoEntry::reversible()`, nie w napisie. Cofnięcie
    nieudane mówi dlaczego i **nie zdejmuje zapisu**; zapis nie przeżywa
    zamknięcia aplikacji. `Alt`+`u` cofa najnowsze odwracalne, `F3` otwiera
    widok stosu (pozycje nieodwracalne wyszarzone, kursor je przeskakuje).
15e. **Dwa moduły mogą mieć własne pojęcie pliku — mechanizmu rdzenia nie wolno
    powtórzyć nigdy** (krok 49, D88). Moduł sesji zdalnej dostał własne
    `RemotePath`, `RemoteEntry`, `RemoteEntryType`, `RemoteNameFilter`
    i komparator — świadome powtórzenie wobec przeglądarki, bo reguła 15 zabrania
    sięgania do cudzego modułu, a wyniesienie ścieżki do rdzenia byłoby
    odwróceniem D42 („rdzeń nie wie, czym jest katalog ani wpis”).
    **Granica jest podwójna i obie jej połowy obowiązują naraz.**
    *Jakościowa:* wolno powtórzyć **pojęcia dziedziny** (ścieżka, wpis, rodzaj,
    filtr, porządek) — każde tanie, bez skutków ubocznych i o regule należącej do
    tego, kto pokazuje. Nie wolno powtórzyć **mechanizmu rdzenia**: praca
    kawałkowa, komponenty, zdarzenia, proces tłowy, zakresy dopasowania
    (`TextSpan`) i ustawienia biorą się **z rdzenia** albo nie biorą się wcale.
    *Ilościowa:* **trzeci** moduł z własną domeną plikową uruchamia przegląd
    „czy to nadal powtórzenie, czy już wspólne miejsce” — nie automatyczną
    przeprowadzkę do rdzenia, tylko obowiązek postawienia pytania.
15f. **Polecenie, którego wyjściem jest treść, nie scala strumieni** (krok 49,
    D88). `2>&1` w wierszu polecenia uruchamianego przez `BackgroundProcessPort`
    wolno dopisać wtedy i tylko wtedy, gdy wypis jest **krótki i diagnostyczny**
    (mistrz `ssh -M -N`, `ssh -O check`). Przy wypisie będącym daną jest to
    **błąd psujący dane, nie kwestia porządku**: `ssh` przy `ControlPath` jest
    klientem multipleksera i przekazuje swoje deskryptory mistrzowi połączenia,
    a ten ustawia im tryb nieblokujący (obsługuje wiele sesji w jednej pętli).
    Tryb jest własnością **opisu pliku**, więc scalony strumień błędów przenosi
    go na wyjście potomka — i odkąd potok się zapełni (pętla klatek opróżnia go
    raz na 33 ms), `write()` zwraca `EAGAIN`, a OpenSSH **porzuca porcję wypisu
    i kończy się kodem zero**. Zmierzone: 130 KB ze 419 KB, bez śladu w kodzie
    wyjścia. Powód niepowodzenia bierze się odtąd z osobnego pola
    `BackgroundState::$errorOutput`; zasada z kroku 26 („strumieni się nie
    skleja”) zostaje w mocy — pola są rozdzielone właśnie po to.
16. **Dno stosu ekranów wskazuje konfiguracja, nie kod** (krok 21, D42). Klucz
    rdzenia `startupModule` bierze wartości **z rejestru modułów**, a wybór robi
    `Presentation\Cli\StartupScreen`. `Bootstrap` podaje mu identyfikator
    **modułu ostatniej szansy** (`LAST_RESORT_MODULE = 'browser'`): sprawdzanego
    przez rejestr pierwszym, niewyłączalnego i przejmującego dno w czterech
    przypadkach — moduł domyślny wyłączony, odrzucony, nieobecny albo bez ekranu
    — każdy z własnym komunikatem. `Application/Module` nie zna nazwy żadnego
    konkretnego modułu i nie ma jej poznać.
16b. **Pomiar i testy mają swoje miejsca i swoje reguły** (krok 38, D64).
    Mierzy **wyłącznie** `bin/render-bench` — doraźna pętla `microtime()` daje
    liczbę nieporównywalną z niczym. Torów jest **cztery**: sixelowy
    (domyślny), `--window`, `--text` i `--loop` (takt pętli: wejście → stan →
    złożenie klatki, bez renderera); ich wyniki są nieporównywalne i pilnuje
    tego przyrostek w podpisie konfiguracji. Nowy element interfejsu dostaje
    **scenariusz albo zapisany powód pominięcia** (spis: `docs/pomiary/README.md`),
    a nowy scenariusz musi dać się rozliczyć **w parze** z istniejącym.
    D28 zostaje w mocy — zegar stoi po stronie narzędzia, a jedyne przyznane
    szwy w produkcji to publiczne kroki `SixelFrameEncoder` (krok 16)
    i `TextFrameRenderer` (krok 38). **Zimna klatka** (pierwsza próbka
    rozgrzewki) jedzie obok mediany i nigdy nie alarmuje o regresji;
    **obciążenie maszyny** wchodzi do metryczki wzorca i przy `--save`
    ostrzega, ale nie odmawia. **Regresję wizualną** wykrywa `--png-compare`
    (metryka AE, wzorce w `docs/pomiary/wzorce-png/`), a nie oko; zrzut z żywej
    aplikacji robi komenda `core.dump` — prymitywy plus obraz **wierny torowi**.
    Testy dzielą się na `unit` i `functional`: przebiegi użytkownika mieszkają
    w `tests/Functional/` jako `{Przebieg}FlowTest` (sekwencja klawiszy przez
    `ScreenFixture`; start i zmiana rozmiaru — przez `GameLoop`), a złote klatki
    w `tests/Golden/` odnawia wyłącznie `./bin/render-bench --golden-save`
    **po przeczytaniu różnicy**.
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
    trafiają do `docs/pomiary/` ani do dziennika kroku. Cele `make bench*` nie
    mają bariery technicznej — mają tę regułę.
18. **Procesy uruchamiaj celami `make`, a narzędzie projektu ma pierwszeństwo
    przed doraźnym zastępnikiem** (krok 39, D63/D72). Bramka jakości nazywa się
    **`make qa`** (`cs-check` → `stan` → `test`, stop na pierwszym błędzie;
    `make qa-full` przechodzi całość ze zbiorczym podsumowaniem) i to nią
    sprawdzasz, co właśnie napisałeś; testy osobno — `make test-unit`,
    `make test-functional`. Pozostałe wejścia (`check-env`, `install`,
    `coverage`, `bench*`, `run*`, `probe`, `build`, `clean`) wypisuje `make`
    bez argumentów, a pełny spis „proces → wejście” stoi w `docs/architecture.md`,
    rozdz. 8. **Druga połowa reguły jest ważniejsza**: nie dorabiaj zastępnika
    narzędzia, które projekt ma — pomiar to `bin/render-bench` (nigdy własna
    pętla `microtime()`, reguła 16b), wejście terminala to `bin/terminal-probe`,
    a scenariusz pomiarowy dokłada się **do** `ScenarioFactory`, nie obok niej.
    Granica: zawężenie przebiegu wolno wołać wprost (pojedynczy test filtrem
    PHPUnita, jedna oś `bin/render-bench`, `composer` przy pracy nad
    zależnościami) — zakazana jest **równoległa droga** do procesu, który
    wejście już ma. Makefile sam też jej nie dorabia: definicje poleceń jakości
    zostają w `composer.json`, podział testów w `phpunit.xml.dist`, zasoby
    XTerma w `bin/run*.sh`.

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
(zamówienie strefy skrajnej) — w `Presentation/Ui`, obok `ScreenInterface`.
Widok tekstu — `TextView` w `Presentation/Ui/Component`; jego dane wejściowe
(`TextAnchor`, `TextWindow`), port i usługa leżą w module, który czyta pliki. Klasa modułu ma
sufiks `Module` i leży w warstwie `Presentation` swojego katalogu; jego komenda,
skoro dostaje stan pętli, leży w `Presentation/Command` modułu. Komendy — kontrakt, argumenty, parser
wiersza, rejestr i historia — w `Application/Command`; komendy rdzenia
(`ScreenCommand`, `SettingCommand`, `QuitCommand`) w `Presentation/Cli/Command`,
bo dostają stan pętli i stos ekranów. `Domain/ValueObject` **nie zawiera już
niczego o rysowaniu** (krok 18, D36). Katalogi napisów i wybór języka — `Infrastructure/I18n`
(`TranslatorService`, `Catalog`, `PluralRule`), pliki napisów w `lang/`.
Usługi trybu okienkowego — `Infrastructure/Glfw` (katalog po bibliotece, jak
`Imagick`): `GlfwWindowService`, `GlfwInputService`, `GlfwViewportService`,
`GlfwKeyMapper`, `WindowSizeSettle`; renderer okienkowy `OpenGlFrameRenderer` leży
w `Infrastructure/Rendering`, obok pozostałych tłumaczy słownika prymitywów.
Ikona okna i wpis pulpitu — `Infrastructure/Desktop` (`DesktopEntryInstaller`),
uruchamiane wyłącznie z `bin/install-desktop-entry`, nigdy z pętli.

## Gdy coś tu nie pasuje do zadania

Jeśli zadanie wymaga odstępstwa od powyższego (np. nowa warstwa, inny
wzorzec DI) — zapytaj użytkownika zamiast cicho odstępować; to są
świadome decyzje architektoniczne z `docs/plans/00-decyzje.md`, nie
przypadkowe konwencje.
