# 2. Słownik domenowy — Praca poza klatką: dźwięk, takt modułu, praca tłowa

> Część rozdziału 2. Pojęcia i wstęp: [slownik.md](slownik.md).
> Spis rozdziałów: [docs/architecture.md](../../architecture.md).

Co dzieje się wtedy, gdy robota jest dłuższa niż jedna klatka, a pętla mimo to ma się kręcić trzydzieści razy na sekundę.

## Dźwięk gra obok klatki (od kroku 36)

Muzyka jest **modułem** (`src/Module/Audio/`), a nie rozbudową rdzenia, i to jest
rozstrzygnięcie użytkownika ze startu kroku (D70) zgodne z regułą 15: nowa
funkcja dopisuje się modułem. Rdzeń kosztuje przez to dokładnie tyle, ile reguła
przewiduje — **jedną pozycję na liście w `Bootstrapie`** — i nie wie o dźwięku
nic ponad to: ani że gra, ani czym.

Moduł jest przy tym **sprawdzianem kontraktu z drugiej strony niż krok 21**:
tamten pytał, czy kontrakt udźwignie główną funkcję aplikacji, ten — czy
udźwignie moduł, **który nic nie rysuje**. Udźwignął bez zmiany: `shortcut()`
wolno oddać `null`, zdolności deklaruje się osobno, a moduł bez ani jednej z nich
jest legalny. Zostają dwie komendy (`audio.music`, `audio.volume`), zakładka
ustawień, zakładka pomocy i własne napisy — bez ekranu, bez skrótu i **bez ani
jednego komponentu**.

Granica, której ten krok nie przekracza, brzmi: **dźwięk nie wchodzi do ścieżki
klatki**. Silnik miksuje we własnym wątku, więc pętla główna, renderery
i komponenty nie dowiadują się, że cokolwiek gra. Gdyby moduł czegokolwiek od
nich potrzebował, byłby to znak, że stoi źle.

Port (`Module/Audio/Application/Port/AudioPort`) ma **dwie implementacje i to jest
cały mechanizm degradacji**: `GlAudioService` na `GL\Audio\Engine` oraz
`SilentAudioService` — pusty obiekt dla środowisk bez rozszerzenia `glfw`. Wybór
zapada **raz**, przy składaniu modułu, więc brak rozszerzenia nie jest
rozgałęzieniem w kodzie komend. Silnik audio **nie potrzebuje okna** (sprawdzone
na starcie kroku: startuje bez `glfwInit()` i bez kontekstu OpenGL), więc muzyka
gra także w obu torach terminalowych — zależność od Fazy IX przez to nie
stwardniała.

Trzy rzeczy, które warto znać, zanim się ten kod ruszy. **Referencja do `Sound`
musi przeżyć całą grę** — obiekt zebrany przez odśmiecacz zabiera ze sobą dźwięk,
a testu na to napisać się nie da. **`Sound::stop()` jest pauzą, nie
przewinięciem** — stąd jedna komenda-przełącznik zamiast pary „graj”
i „zatrzymaj”. **Silnik miksuje kilka dźwięków naraz** (sprawdzone
2026-08-14) — fakt potrzebny dopiero krokowi 46, ale rozpoznany wcześniej.

Sprzątanie idzie **dwiema drogami naraz** (D47): jawnie i przez
`register_shutdown_function` rejestrowaną przy starcie silnika. Testy silnika nie
uruchamiają **w ogóle** — test, który go uruchomi, gra muzykę na maszynie ciągłej
integracji i zostawia po sobie wątek; sprawdzać wolno wszystko przed pierwszą
prośbą o granie.

## Takt modułu i playlista (od kroku 45)

Krok 45 **odwraca jedno zdanie kroku 36**: „autostartu nie ma, bo kontrakt modułu
nie zna cyklu życia”. Kontrakt zna go od tego kroku — a różnica, która na to
pozwoliła, jest jedna i musi być zapisana, bo bez niej wygląda to na zmianę
zdania (D71, D82 nr 1). W kroku 36 zdolność miała **jednego użytkownika
i wyłącznie dla wygody**: muzykę dało się uruchomić komendą, autostart był
udogodnieniem. Tutaj **bez wywołania spoza ekranu funkcja nie istnieje**:
playlista, która nie wie, że utwór się skończył, nie jest playlistą, tylko listą
ścieżek. `Presentation\Ui\NeedsTime` nie wystarcza i zostało to sprawdzone przed
pierwszą linią kodu: o czas klatki pyta `FrameComposer`, a pyta o niego **ekran
i okno nakładane**, czyli to, co akurat widać.

**Mechanizm rdzenia to jedna zdolność i jedna klasa.**
`Application\Module\NeedsTick` (`tick(float $now)`) deklaruje się osobno, jak
`ProvidesCommands`; `Presentation\Cli\ModuleTicker` odsiewa chętnych **raz, przy
składaniu aplikacji** i woła ich raz na klatkę z `GameLoop`, w fazie „aktualizuj
stan” — obok kawałka pracy okna (`RunsWork`), a nie w rysowaniu. Trzy reguły
obowiązują każdy przyszły moduł:

- **takt jest tani** — porównanie stanu, nigdy wejście-wyjście; praca dłuższa od
  klatki dzieli się na kawałki (D46);
- **takt niczego nie wymusza** — nie prosi o przerysowanie i nie zwraca skutku do
  pętli (reguła 11b w drugą stronę);
- **takt nie rzuca** — wyjątek modułu łapie `ModuleTicker` i stawia zdanie
  w pasku stanu, tą samą drogą, którą łapane są wyjątki ekranu; wyjątek jednego
  modułu nie zabiera taktu pozostałym.

Czas przychodzi **z zewnątrz**, i to nie jest ozdobnik: playlista mierzy nim
**karencję po starcie utworu** (pół sekundy), bo `play()` wraca, zanim wątek
miksujący odnotuje granie — takt tuż po starcie zobaczyłby „nie gram” i przeleciał
całą listę w ułamku sekundy.

**Playlista zastępuje klucz `track`.** Wybór utworu przestał być pozycją
ustawień, bo jednej ścieżki już nie wystarcza; dawna wartość **zasila playlistę
przy pierwszym uruchomieniu po zmianie** i dopiero wtedy zapisuje się plik.
Ustawienia modułu trzymają wyłącznie skalary, więc nośnikiem listy jest **własny
plik stanu modułu** `~/.light-manager/audio.json` (wzorem historii komend: zapis
przez plik tymczasowy i `rename()`, żadna ścieżka nie rzuca). Klucze, których ta
wersja nie zna, przeżywają zapis — krok 46 dołoży mapę hooków **kluczem, a nie
drugim plikiem**. Plik ruszony ręcznie daje pustą playlistę **wraz z powodem**,
pokazywanym w oknie modułu zamiast listy.

Przełącznik `loop` zamienił się w **tryb odtwarzania** (`PlaybackMode`: pętla
listy, zatrzymaj po utworze, powtarzaj utwór), a dawna wartość przekłada się na
nowy klucz bez pytania użytkownika o zdanie — do pierwszej zmiany w zakładce
rządzi nadal stary klucz, więc konfiguracja nie zmienia się bez jego udziału.
Powtarzanie utworu zapętla **silnik**, nie playlista. Pozycja wskazująca plik,
którego nie ma, **zostaje na liście** wyszarzona i podpisana, a wypada wyłącznie
z wyboru „co grać dalej” (D82 nr 6).

Okno modułu (`Ctrl`+`A`) **nie dokłada ani jednego komponentu rdzenia** i to jest
sprawdzian tego kroku, ten sam, który przeszło menu z kroku 32: całość to
`ListView`, `Label`, `TextInput` i `ScrollWindow`. Utwory wchodzą **trzema
drogami**: `F5` bierze wpis zaznaczony w przeglądarce przez `ReadsContext` (moduł
nie poznaje cudzego modułu, tylko ścieżkę), `F7` otwiera pole na ścieżkę, a
komenda `audio.add` działa także wtedy, gdy okna nie widać. Kolejność zmienia się
`Shift`+strzałkami — **nie `Alt`+strzałkami**, bo `Alt` jest w słowniku wejścia
dopuszczony wyłącznie przy literach (reguła 11j), a otwieranie słownika byłoby
drugą zmianą rdzenia w kroku, który ma ruszyć wyłącznie takt.

## Praca dłuższa od klatki (od kroku 25, proces potomny od 26)

Pętla główna nie ma prawa czekać. Wszystko, co trwa dłużej niż jedna klatka —
liczenie sumy kontrolnej, przejście po drzewie katalogów — dzieli się więc na
kawałki, a jeden kawałek przypada na klatkę. Wzorzec ma trzy części i wszystkie
trzy są obowiązkowe:

1. **Port mówi o pracy, a nie o wyniku.** Nie ma metody `checksum(path): string`
   — są `begin()`, `advance($bytes)` i `stop()`. Kształt kontraktu wymusza to,
   że wynik nie jest dostępny od razu.
2. **Stan pracy jest daną, którą ekran ogląda co klatkę** (`ChecksumState`:
   etap, ułamek, wynik albo powód niepowodzenia). To z niej bierze się wypełnienie
   paska postępu — i dlatego postęp jest **prawdziwy**, a nie udawany.
3. **Praca ma właściciela, który ją przerywa.** Stan modułu (`FileInfoState`)
   zatrzymuje ją przy zmianie zaznaczenia i przy `reset()`. Bez tego przewinięcie
   listy zostawiałoby za sobą tyle otwartych plików, ile pozycji minięto.

**Praca zaczyna się na żądanie, nie sama z siebie.** Zaznaczenie zmienia się przy
przewijaniu trzydzieści razy na sekundę, a praca uruchamiana odruchowo byłaby
wtedy trzydziestoma pracami przerwanymi w tej samej sekundzie. Wiersz stoi więc
od pierwszej klatki z podpowiedzią, którym klawiszem go policzyć.

Praca w **procesie potomnym** podlega tym samym trzem regułom i dokłada
**czwartą: sprzątanie przy wyjściu z aplikacji**. Od kroku 26 mechanizm jest
rdzeniowy — `Application\Port\BackgroundProcessPort` (start, doglądanie,
przerwanie) i `Infrastructure\Process\BackgroundProcessService` za nim. Moduł
sięga po niego tak samo, jak po `ImagePreviewPort`, a `Bootstrap` podaje go
w jednej linii.

**Stan pracy niesie oba strumienie — osobno** (od kroku 49). Do tamtego kroku
strumień błędów był czytany i wyrzucany, bo `du` zasypuje go wierszami „brak
dostępu”, a sklejenie zamieniłoby liczbę do odczytania w stertę do przeszukania.
Ta zasada **zostaje w mocy** i pola są rozdzielone właśnie po to; zmieniło się
co innego: polecenie, którego wyjściem jest **treść**, nie ma prawa scalać
strumieni w wierszu polecenia (`2>&1`), bo scalanie potrafi **zepsuć dane**
(reguła 15f w `SKILL.md`) — a mimo to musi mieć jak powiedzieć, co poszło nie tak.
**Ile wyjścia pamiętamy, mówi odtąd konfiguracja** (`backgroundOutputKib`,
domyślnie 1 MiB, zakładka „Zasoby”): dawna stała 64 KiB była dobrana pod
polecenia oddające jeden wiersz i urywała listę katalogu **po cichu**. Limit
obowiązuje każdy strumień z osobna i bierze się **raz, przy uruchomieniu
pracy** — praca mierzona w trakcie dwiema różnymi miarami nie miałaby miary
w ogóle.

Czwarta reguła brzmi: **potomek nie ma prawa przeżyć procesu, który go
uruchomił**, a ponieważ dróg wyjścia z aplikacji jest więcej niż jedna, drogi
sprzątania są dwie i obie obowiązują:

- **jawna** — `Bootstrap::shutdown()` woła `shutdown()` usługi przed zapisem
  historii i przywróceniem terminala, czyli tą samą ścieżką, którą terminal
  wraca do trybu normalnego;
- **gwarancja ostatniej szansy** — `register_shutdown_function` zarejestrowana
  leniwie przy pierwszym uruchomieniu pracy, dla wyjść, których pierwsza droga
  nie dosięga: błędu krytycznego i `exit()` z boku.

To jest ten sam układ, którym `TerminalService` broni trybu surowego, i z tego
samego powodu: jedna droga jest czytelna, druga nieomylna.

Trzy rzeczy ponadto, każda niosąca własną klasę błędów:

- **usługa prowadzi kilka prac naraz** (od kroku 51), każdą pod własnym uchwytem
  (`BackgroundHandle`), z **granicą braną z ustawień** (`backgroundJobs`,
  domyślnie osiem, zakładka „Zasoby”). Do tamtego kroku prowadziła **jedną** i była
  to decyzja z kroku 26, nie ograniczenie techniczne: przy jednym odbiorcy (`du`)
  nikomu nie przeszkadzała, ale odbiorców zrobiło się trzech — doszła sesja zdalna
  (kroki 48–50) i moduł Dockera, którego `compose up` trwa minutami. Uchwyt zmienił
  przez to **znaczenie, nie kształt**: przestał mówić „wyparto cię”, zaczął
  „prace da się rozróżnić”. **Przekroczenie granicy znaczy odmowę, nie wyparcie
  najstarszej** — wyparcie przywracałoby chorobę, którą rozbudowa leczy, a odmowa
  idzie tą samą drogą, co każda inna awaria startu: uchwyt wraca, powód odbiera
  pierwszy `poll()`;
- **potoki są nieblokujące i opróżniane co klatkę — dla każdej pracy naraz**.
  Do kroku 51 karmił je właściciel przy zaglądaniu; odkąd prac jest kilka, robi to
  **pętla** przez osobny port `Application\Port\BackgroundPumpPort` (`pump()`),
  wołany raz na klatkę w fazie „aktualizuj stan”. Port jest osobny **konstrukcyjnie,
  a nie z porządku**: pompowanie należy do pętli, nie do modułu — ta sama zasada,
  która w kroku 26 zostawiła `shutdown()` poza portem. Właściciel niezaglądający
  (ekran modułu zniknął, moduł ma usterkę) zatrzymałby inaczej swojego potomka na
  pełnym potoku, a jego limitu czasu też nie miałby kto sprawdzić. `poll()` jest
  odtąd **czystym odczytem stanu**;
- **kod wyjścia różny od zera nie jest sam z siebie niepowodzeniem** — `du`
  kończy się jedynką za każdy nieprzeczytany katalog, a mimo to podaje sumę tego,
  co przeczytać zdołało. Co z kodu wynika, rozstrzyga zamawiający; rdzeń go tylko
  podaje;
- **praca trwająca oddaje swój wypis, a zamawiający mówi, czym ten wypis jest**
  (od kroku 52, D91 nr 12). Do tamtego kroku `Running` znaczyło „nic ci jeszcze
  nie powiem”, co zamykało drogę każdemu poleceniu **niekończącemu się nigdy**:
  `kubectl logs -f` pisał wiersze do potoku, port je zbierał, a pierwszy raz
  oddałby je po śmierci potomka, czyli nigdy. Rozbudowa ma dwie części i druga
  jest ważniejsza od pierwszej. Samo oddawanie wypisu nie wystarcza, bo bufor
  **odrzucał nadmiar** po przekroczeniu granicy — log dobiłby do niej
  w kilkanaście sekund i zamilkł na zawsze. Wynik i strumień mają wobec granicy
  wymagania przeciwne, więc doszło `Application\Dto\OutputShape`: `Result`
  (domyślny, zachowanie sprzed kroku 52 co do bajtu) zbiera do granicy i odrzuca
  nadmiar, `Stream` **zapomina najstarsze**, a ile bajtów wypadło, mówi
  `BackgroundState::$droppedBytes`. Kształt podaje się przy `start()`, bo jest
  własnością **zamówienia, nie polecenia**: to samo `kubectl logs` bywa jednym
  i drugim, zależnie od `-f`. Dwie rzeczy zostają nietknięte i są warunkiem
  przyjęcia zmiany: **`poll()` pozostaje czystym odczytem** (stan powstaje
  w `pump()`) i **treść nadal odbiera się przy `Done`** — wypis w trakcie jest
  urwany w połowie wiersza, a pół JSON-a nie jest JSON-em.

Pierwszym odbiorcą jest wiersz „zajęte na dysku” w module `FileInfo` — liczony
poleceniem `du` **tylko dla katalogów** (dla zwykłego pliku tę samą liczbę podają
bloki i-węzła z `lstat`, bez uruchamiania czegokolwiek) i **na żądanie klawiszem
`d`**, jak suma kontrolna. Postępu `du` nie zna, więc pasek chodzi w trybie
„nieznany” — pierwsze prawdziwe użycie tego trybu od kroku 23.
