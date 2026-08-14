# Krok 36 — Odtwarzanie muzyki przez `GL\Audio`

> **Skąd ten krok.** Powstał 2026-08-11, na polecenie użytkownika (D55).
> Aplikacja ma umieć odegrać muzykę — domyślnie i na początek **„Smoke On
> The Water”** — używając modułu audio rozszerzenia PHP-GLFW (`GL\Audio`).
> Rozpoznanie z dnia planowania: rozszerzenie `glfw` **jest załadowane**
> w środowisku projektu, klasy `GL\Audio\Engine` i `GL\Audio\Sound` są
> dostępne. Dla środowisk bez rozszerzenia krok przewiduje jawną degradację
> i listę rozwiązań zastępczych do wyboru (lista startowa, punkt 4).

## Status

**Ukończony z zastrzeżeniem** (2026-08-14) — muzyka rusza **komendą, nie
autostartem**; powód i cena: [00-decyzje.md](../00-decyzje.md), D70,
rozstrzygnięcie 5.

## Cel

Aplikacja przy starcie **odtwarza domyślny utwór — riff „Smoke On The
Water”** — a użytkownik może muzykę zatrzymać i wznowić komendą; wyjście
z aplikacji każdą z trzech ścieżek (normalną, wyjątkiem, sygnałem) ucisza
dźwięk czysto. Dźwięk **nie wchodzi do ścieżki klatki**: silnik audio gra we
własnym wątku (miniaudio), a pętla główna, renderery i komponenty nie wiedzą,
że cokolwiek gra — koszt klatki nie ma prawa drgnąć w żadnym trybie.

Brak rozszerzenia `glfw` nie jest błędem: muzyka jest możliwością, nie
wymogiem (wzorem `ext-glfw` w kroku 34 i `intl` z D20). Aplikacja bez niego
startuje jak dotąd, a próba użycia muzyki kończy się czytelnym komunikatem —
chyba że użytkownik wybierze któreś rozwiązanie zastępcze z listy startowej.

## Zależności

- **Krok 06** (terminal I/O) — trzy ścieżki wyjścia; zatrzymanie silnika
  audio wchodzi do sprzątania każdą z nich, wzorem przywracania terminala.
- **Krok 09** (pętla główna) — autostart muzyki staje w bootstrapie obok
  pozostałych efektów ubocznych; pętla pozostaje nietknięta — silnik gra we
  własnym wątku i nie potrzebuje miejsca w takcie (do potwierdzenia na
  starcie, patrz „Stan zastany”).
- **Krok 14** (konfiguracja) — włącznik autostartu, głośność, zapętlenie
  i ścieżka utworu to klucze ustawień, nie stałe.
- **Krok 15** (wielojęzyczność) — komunikaty i etykiety komend idą przez
  katalog napisów od pierwszego dnia.
- **Krok 19** (okno komend) — sterowanie muzyką to komendy w
  `CommandRegistry`; osobnego interfejsu krok nie buduje.
- **Krok 26** (proces tłowy) — wyłącznie jeśli padnie wybór na zastępcze
  odtwarzanie procesem zewnętrznym (lista startowa, punkt 4a): wzorzec
  procesu potomnego pochodzi stamtąd.

Od Fazy VII, kroku 33 i **Fazy IX krok nie zależy** i one nie zależą od
niego — `GL\Audio` nie potrzebuje okna ani kontekstu OpenGL (do potwierdzenia
na starcie). Jedyny punkt styku z Fazą IX: stuby `GL\*` do analizy statycznej
dowozi ten z kroków 34/36, który wykona się **pierwszy**; drugi je przejmuje
i co najwyżej rozszerza.

## Model i wysiłek

**Opus / high.**

Kodu będzie niewiele i prawie cały stanie w nowych plikach `Infrastructure`,
ale krok ma trzy miejsca, w których łatwo o błąd rozlewający się poza niego:
sprzątanie wątku audio na ścieżce sygnału, czas życia obiektu `Sound`
(referencja musi przeżyć całą grę utworu — zebranie przez GC w trakcie gry
to błąd, którego test jednostkowy nie złapie) oraz syntezator riffu, którego
wynik musi być deterministyczny, żeby dało się go testować.

## Stan zastany (sprawdzone przy planowaniu / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| Rozszerzenie `glfw` | **Załadowane** w środowisku projektu (sprawdzone 2026-08-11); `GL\Audio\Engine` i `GL\Audio\Sound` dostępne przez refleksję |
| `GL\Audio\Engine` | `__construct(array $options = [])`, `start()`, `stop()`, `soundFromDisk(string $path): Sound`, `set/getMasterVolume(float)`, pozycjonowanie słuchacza 3D (nieużywane w tym kroku) |
| `GL\Audio\Sound` | `play()`, `stop()`, `isPlaying()`, `setLoop()`, `setVolume()`, `setPitch()`, `fadeIn/fadeOut/setFade`, `seekTo/seekToPCM`, `readFrames()`; właściwości `sampleRate`, `channels`, `length`, `lengthPCM` |
| Ładowanie dźwięku | **Wyłącznie z dysku** (`soundFromDisk`) — nie ma konstrukcji z bufora pamięci, więc utwór syntezowany musi najpierw stanąć jako plik (WAV) |
| Zależność od okna | Do potwierdzenia: silnik (miniaudio) najpewniej nie wymaga `glfwInit()` ani kontekstu GL — wtedy muzyka gra także w trybach terminalowych |
| Wątek audio a sygnały | Do potwierdzenia: zachowanie `Engine::stop()` po SIGINT/SIGTERM i to, czy proces kończy się czysto, gdy silnik gra |
| `Presentation/Cli/Bootstrap` | Sekwencje efektów ubocznych (po kroku 34 — dwie); autostart muzyki musi stanąć w obu i nie wywrócić żadnej, gdy rozszerzenia brak |
| `Application/Port/*` | Brak portu audio; wzorzec portu z resztą projektu ustala kształt nowego |
| `composer.json` | `ext-glfw` co najwyżej w `suggest` (granica z D52) — muzyka tego nie zmienia |
| Stuby `GL\*` dla PHPStan | Nieobecne, jeśli krok 34 jeszcze nie wykonany — wtedy dowozi je ten krok (patrz Zależności) |

## Zakres

### 1. Port i usługa audio

Nowy port w `Application` (nazwa — lista startowa, punkt 5) o minimalnym
kontrakcie: odegraj domyślny utwór, zatrzymaj, wznów, czy gra, głośność.
W `Infrastructure` usługa-Singleton (wzorem pozostałych usług z efektem
ubocznym) opakowująca `GL\Audio\Engine`: start silnika przy pierwszym użyciu,
`soundFromDisk()` dla utworu, **trzymana referencja** do `Sound` przez cały
czas gry, zatrzymanie silnika przy wyjściu wszystkimi trzema ścieżkami
z kroku 06. Druga implementacja portu — pusta (null object) — obsługuje
środowiska bez rozszerzenia: metody nic nie robią, stan mówi „niedostępne”.

### 2. Domyślny utwór: „Smoke On The Water”

Nagranie zespołu Deep Purple **nie wchodzi do repozytorium** — to cudze
nagranie i cudze prawa. Zamiast niego (rekomendacja; ostateczny wybór —
lista startowa, punkt 2) krok dowozi **własny syntezator riffu**: czysty,
deterministyczny generator PHP, który z zapisanej w kodzie sekwencji nut
(riff: G–B♭–C · G–B♭–D♭–C · G–B♭–C · B♭–G, w oryginalnym stroju i tempie)
buduje próbki PCM i zapisuje plik WAV do katalogu pamięci podręcznej
aplikacji. Plik powstaje raz i jest używany przy kolejnych startach; klucz
ustawień pozwala wskazać własny plik (WAV/MP3/FLAC — formaty miniaudio)
zamiast syntezy. Generator nie dotyka `GL\*` — jest testowalny w PHPUnit
bez rozszerzenia, co czyni go najlepiej pokrytą częścią kroku.

### 3. Autostart i sterowanie

Przy starcie, po wczytaniu ustawień, muzyka rusza sama, jeśli włącznik
autostartu na to pozwala (wartość domyślna — lista startowa, punkt 3).
Sterowanie wyłącznie przez komendy w `CommandRegistry` (np. zatrzymaj/wznów
i głośność; dokładne nazwy — punkt 5), z etykietami przez katalog napisów.
Żadnego nowego ekranu, komponentu ani skrótu klawiszowego — okno komend
już umie wszystko, czego tu trzeba.

### 4. Degradacja bez rozszerzenia i rozwiązania zastępcze

Bez `ext-glfw` aplikacja startuje bez muzyki, a komendy muzyczne odpowiadają
komunikatem o niedostępności (przez katalog napisów) — to zachowanie
podstawowe. Ponadto do wyboru użytkownika (lista startowa, punkt 4) trzy
rozwiązania zastępcze:

- **(a) odtwarzacz zewnętrzny jako proces potomny** — wzorzec kroku 26;
  pierwszy znaleziony z listy preferencji (`paplay`, `aplay`, `ffplay`,
  `mpv`), zero nowych rozszerzeń, działa we wszystkich trybach; koszt:
  słabsza kontrola (głośność/wznowienie zależne od odtwarzacza) i proces
  do pilnowania;
- **(b) FFI do miniaudio/OpenAL** — pełna kontrola bez `ext-glfw`, ale
  `ext-ffi`, własne wiązania i utrzymanie — najcięższa opcja, odradzana;
- **(c) świadoma rezygnacja** — sam komunikat o niedostępności, zero kodu
  zastępczego; najtańsza i uczciwa, jeśli środowisk bez rozszerzenia
  w praktyce nie ma.

### 5. Analiza statyczna i granice

PHPStan `max` musi przejść **bez** załadowanego rozszerzenia: stuby `GL\Audio\*`
(wspólne z krokiem 34 — patrz Zależności) są częścią kroku, nie
usprawiedliwieniem dla `@phpstan-ignore`. `composer.json` bez zmian w `require`
(co najwyżej dopisek w `suggest`). Preflight w `bin/light-manager` **nie**
sprawdza rozszerzenia dla muzyki — brak obsługuje null object, nie kod wyjścia.

## Poza zakresem

- **Moduł odtwarzacza muzyki** — playlisty, przeglądanie plików audio,
  pasek postępu utworu; jeśli kiedyś powstanie, będzie modułem na kontrakcie
  z kroku 20, a ten krok da mu gotowy port.
- **Dźwięki interfejsu** (klik, błąd, powiadomienie) — inny problem
  (krótkie próbki, wiele naraz); osobna decyzja, jeśli w ogóle.
- **Audio 3D** — `GL\Audio` je oddaje, aplikacja nie ma czym go uzasadnić.
- **Wizualizacja dźwięku w klatce** — `readFrames()` kusi, ale to praca
  w ścieżce klatki, której ten krok świadomie nie dotyka.
- **Zmiany w pętli, rendererach i komponentach** — dźwięk gra obok klatki,
  nie w niej; jeśli krok czegoś tam wymaga, to znak, że stoi źle.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/…AudioPort.php` | Application | **Nowy** — kontrakt: graj/zatrzymaj/wznów/stan/głośność (nazwa: punkt 5). |
| `Infrastructure/Audio/…AudioService.php` | Infrastructure | **Nowy** — Singleton na `GL\Audio\Engine`, cykl życia `Sound`, sprzątanie. |
| `Infrastructure/Audio/…NullAudioService.php` | Infrastructure | **Nowy** — pusta implementacja portu dla środowisk bez rozszerzenia. |
| `Infrastructure/Audio/…RiffSynthesizer.php` | Infrastructure | **Nowy** — czysty generator: sekwencja nut → PCM → plik WAV, bez `GL\*`. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Autostart muzyki po ustawieniach; wybór implementacji portu wg dostępności. |
| konfiguracja (krok 14) | — | Klucze: autostart, głośność, zapętlenie, ścieżka utworu. |
| katalog napisów (krok 15) | — | Etykiety komend i komunikat niedostępności, pl + en. |
| `composer.json` | — | `suggest: ext-glfw` (jeśli krok 34 jeszcze tego nie dopisał). |
| stuby PHPStan `GL\Audio\*` | — | Jeśli krok 34 jeszcze nie wykonany — dowozi ten krok. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Warstwa dźwięku: port, null object, granica „poza ścieżką klatki”. |
| testy | Testy | Syntezator (deterministyczne próbki i nagłówek WAV), null object, komendy za dublerem portu, bootstrap bez rozszerzenia bez regresji. |

Nazwy `Audio/…` są zapowiedzią, nie decyzją — jak w kroku 34 (punkt 5 listy).

## Do rozstrzygnięcia na starcie kroku

1. **Czy muzyka gra we wszystkich trybach** (Sixel, tekst, okno) — zależnie
   od potwierdzenia, że `GL\Audio` nie wymaga okna; jeśli wymaga, muzyka
   staje się właściwością trybu okienkowego i zależność od kroku 34
   twardnieje.
2. **Źródło domyślnego utworu** — syntezator riffu (rekomendacja: bez cudzego
   nagrania w repozytorium, deterministyczny, testowalny) czy wyłącznie klucz
   ustawień ze ścieżką do pliku użytkownika (wtedy „domyślnie Smoke On The
   Water” znaczy tylko tyle, ile plik, który użytkownik podłoży).
3. **Autostart domyślnie włączony czy wyłączony** — polecenie mówi
   „domyślnie i na początek”, co wskazuje na włączony; do potwierdzenia
   razem z głośnością startową i zapętleniem.
4. **Rozwiązanie zastępcze bez rozszerzenia** — (a) proces zewnętrzny,
   (b) FFI, (c) sama degradacja z komunikatem (rekomendacja: c, dopóki
   jedyne środowisko projektu ma rozszerzenie załadowane).
5. **Nazewnictwo** — port (`AudioPort` / `MusicPort`), katalog usług
   (`Infrastructure/Audio` po roli czy `Infrastructure/Glfw` po bibliotece,
   precedens dwuznaczny: Imagick nazwany po bibliotece, ale silnik audio
   z GLFW dzieli tylko rozszerzenie) i nazwy komend.

## Kryteria ukończenia

- Start aplikacji (przy włączonym autostarcie) odtwarza riff „Smoke On The
  Water”; komendy zatrzymują i wznawiają muzykę; ustawienia głośności
  i zapętlenia działają.
- Wyjście każdą z trzech ścieżek (klawisz, wyjątek, sygnał) ucisza dźwięk
  i kończy proces czysto — bez wiszącego wątku audio.
- Bez rozszerzenia `glfw`: aplikacja startuje i działa jak przed krokiem,
  komendy muzyczne odpowiadają komunikatem; zero kodu wyjścia 1, zero zmian
  w `require`.
- Syntezator pokryty testami: te same nuty → te same bajty; poprawny
  nagłówek WAV; plik powstaje raz i jest wielokrotnie używany.
- Koszt klatki bez zmian we wszystkich trybach — `bin/render-bench --compare`
  bez regresji (muzyka gra poza ścieżką klatki).
- PHPStan `max` bez błędów (ze stubami, bez załadowanego rozszerzenia),
  PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-14 — krok wykonany

**Krok wyszedł inny, niż go zaplanowano, i to dwa razy.** Plan przewidywał
rozbudowę rdzenia (port w `Application`, usługa w `Infrastructure`, autostart
w `Bootstrapie`, cztery klucze rdzenia, komendy `core.*`) oraz **własny
syntezator riffu**, bo utworu nie było. Pierwsze odwrócenie przyniósł
użytkownik, kładąc w `assets/audio/` plik MIDI, a po nim wersję **MP3** — czyli
format, który silnik czyta wprost; syntezator stał się przez to niepotrzebny
w całości. Drugie odwrócenie przyszło z pytania o ścieżkę utworu: byłaby ona
**pierwszym kluczem rdzenia z wartością tekstową**, a ekran ustawień umie
edytować tekst wyłącznie w pozycjach modułu — więc dźwięk poszedł tam, gdzie
wedle reguły 15 należał od początku. **Krok jest modułem.** Pełna treść
rozstrzygnięć: [00-decyzje.md](../00-decyzje.md), **D70**.

**Co rozstrzygnął pomiar, zanim padło pierwsze pytanie.** `GL\Audio\Engine`
startuje **bez okna i bez `glfwInit()`** (muzyka gra we wszystkich trzech
torach), MIDI-a miniaudio **nie przyjmuje** (WAV, MP3, FLAC), a `Sound::stop()`
jest **pauzą, nie przewinięciem** (stąd jedna komenda-przełącznik).

**Co powstało.**

| Plik | Rola |
|---|---|
| `Module/Audio/Application/AudioSettings.php` | Trzy pozycje: utwór (tekst), głośność (liczba z przystanków), zapętlenie |
| `Module/Audio/Application/Port/AudioPort.php` | Kontrakt: dostępność, granie, pauza, głośność, sprzątanie — bez wyjątków przekraczających granicę |
| `Module/Audio/Application/UseCase/ChangeVolumeUseCase.php` | Zapis głośności **liczbą**, czego rdzeniowy zapis pozycji tekstowej nie umie |
| `Module/Audio/Infrastructure/GlAudioService.php` | Silnik leniwy, referencja do `Sound` w polu, sprzątanie `register_shutdown_function` |
| `Module/Audio/Infrastructure/SilentAudioService.php` | Pusty obiekt: brak rozszerzenia odpowiada zdaniem, a nie milczeniem |
| `Module/Audio/Presentation/AudioModule.php` | Moduł **bez ekranu i bez skrótu** — pierwszy taki w projekcie |
| `Module/Audio/Presentation/Command/MusicCommand.php` | `audio.music` — przełącznik |
| `Module/Audio/Presentation/Command/VolumeCommand.php` | `audio.volume <0–100 co 10>` — pierwsza komenda z wartością **liczbową** |
| `Module/Audio/lang/{pl,en}.php` | Napisy modułu, wraz z trzema wierszami zakładki pomocy |
| `tests/Support/StubAudio.php` | Atrapa portu — testy **nie uruchamiają silnika w ogóle** |
| `tests/Module/Audio/AudioModuleTest.php` | Kontrakt modułu, przełącznik, ustawienia, odrzucona głośność |
| `tests/Module/Audio/AudioServicesTest.php` | Obie implementacje portu — wyłącznie tam, gdzie nie zaczyna się dźwięk |

W rdzeniu zmieniła się **jedna linia** (pozycja na liście modułów
w `Bootstrapie`) i dopisek w `composer.json` przy `suggest`. Reszta to
dokumentacja.

**Odstępstwa od planu — trzy i wszystkie nazwane.**

1. **Autostartu nie ma** (zastrzeżenie w statusie). Kontrakt modułu nie zna cyklu
   życia; dokładanie mu zdolności „obudź mnie” dla jednego modułu byłoby
   rozszerzaniem rdzenia dla wygody modułu. Polecenie z D55 („domyślnie i na
   początek”) jest przez to spełnione **w połowie**: muzyka jest domyślnym
   utworem, ale rusza komendą.
2. **Syntezatora riffu nie ma** — użytkownik dowiózł plik MP3, więc generator
   PCM nie miał czego generować. Zniknęła wraz z nim najbogaciej pokryta testami
   część planowanego kroku i to widać w liczbach: testów modułu jest piętnaście,
   a nie pięćdziesiąt.
3. **Pierwsze w projekcie punktowe wyciszenie analizy** (reguła 14) —
   `Sound::isPlaying()` istnieje w rozszerzeniu 2.2.0, ale nie w stubach
   `phpgl/ide-stubs`. Alternatywą było liczenie stanu własną flagą, czyli
   obejście analizy **kosztem zachowania**: utwór kończy się sam, więc flaga
   kłamałaby przy wyłączonym zapętleniu.

**Sprawdzone na prawdziwym silniku** (poza PHPUnit, skryptem roboczym): komendy
grają i pauzują, głośność zmienia się w trakcie grania, wartość spoza listy
zostawia okno otwarte, brakujący plik i **plik MIDI** kończą się komunikatem
wymieniającym formaty, a sprzątanie zatrzymuje silnik.

**Pomiar.** Bez zmian w potoku rysowania: moduł nie wnosi ani jednego komponentu,
a jedyny jego ślad w klatce to trzy wiersze zakładki ustawień — treść mierzona
już przez scenariusz `settings`. Powód pominięcia zapisany
w `docs/pomiary/README.md` wraz ze wskazaniem, czym **dałoby się** zmierzyć rzecz
naprawdę interesującą: taktem pętli (`--loop`) przy graniu i bez.

**Bramka jakości:** PHPStan `max` bez błędów (z jednym wyciszeniem opisanym
wyżej), PHP-CS-Fixer bez uwag, testy zielone — 1428 testów, 3739 asercji
(przybyło 15 testów w dwóch plikach).

**Kryteria ukończenia — rozliczenie.**

| Kryterium | Stan |
|---|---|
| Start aplikacji odtwarza riff | **nie** — muzyka rusza komendą `audio.music` (D70, rozstrzygnięcie 5) |
| Komendy zatrzymują i wznawiają; głośność i zapętlenie działają | tak |
| Wyjście każdą z trzech ścieżek ucisza dźwięk | tak — `register_shutdown_function` w usłudze modułu; wyjście sygnałem idzie przez normalne zakończenie pętli |
| Bez rozszerzenia: aplikacja działa, komendy mówią, zero zmian w `require` | tak — `SilentAudioService`, `suggest` bez zmian w `require` |
| Syntezator pokryty testami | **bezprzedmiotowe** — syntezatora nie ma, utwór jest plikiem |
| Koszt klatki bez zmian | tak z konstrukcji — dźwięk nie wchodzi do ścieżki klatki, a moduł nie wnosi komponentu |
| PHPStan `max`, CS-Fixer, testy | tak |
