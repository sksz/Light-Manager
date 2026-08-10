# Krok 09 — Pętla główna (game loop)

## Status

Ukończony

## Zależności

Krok 06 (wejście), Krok 08 (renderowanie).

## Model i wysiłek

Sonnet / medium — integracja gotowych elementów z kroków 06 i 08;
złożoność umiarkowana, bo najtrudniejsze problemy techniczne są już
rozwiązane w zależnościach.

## Cel

Spiąć fundament wejścia i renderowania w faktyczną nieskończoną pętlę
aplikacji: odczytaj wejście → zaktualizuj stan → przerysuj całą klatkę →
powtórz, z wyjściem przez `break` na podstawie zdarzenia wejścia lub
sygnału.

## Zakres

- Struktura `while (true) { ... }` z jawnym `break` wywoływanym przez
  klawisz wyjścia (np. `q`) albo flagę ustawianą przez handler sygnału z
  kroku 06.
- Miejsce na stan aplikacji przekazywane między iteracjami (na razie
  pusty/placeholder — realny stan nawigacji dochodzi w kroku 10).
- Decyzja i udokumentowanie modelu odświeżania: przerysowanie po każdym
  odebranym wejściu (zdarzeniowo) vs. w stałym takcie (np. przez `usleep`
  między iteracjami) — do rozstrzygnięcia na bazie pomiaru wydajności z
  kroku 08.
- Wywołanie sprzątania (przywrócenie terminala z kroku 06) po wyjściu z
  pętli, niezależnie od przyczyny wyjścia.

## Poza zakresem tego kroku

Jakakolwiek logika biznesowa menadżera plików (krok 10+).

## Kryteria ukończenia

- Aplikacja startuje, wyświetla klatkę, reaguje na klawisz wyjścia
  zamykając się przez `break` (nie przez `exit()`/`die()` w środku pętli),
  terminal wraca do normalnego stanu.
- Udokumentowana decyzja o modelu odświeżania (zdarzeniowy vs takt) w
  dzienniku realizacji lub w [00-decyzje.md](00-decyzje.md), jeśli decyzja
  jest nietrywialna.

## Specyfikacja zrealizowana

### Powstałe pliki

| Plik | Warstwa | Rola |
|---|---|---|
| `Presentation/Cli/GameLoop.php` | Presentation | Pętla `while (true)` z wyjściem wyłącznie przez `break`. |
| `Presentation/Cli/LoopState.php` | Presentation | Stan przenoszony między iteracjami (treść zastępcza do kroku 10). |
| `Presentation/Cli/Bootstrap.php` | Presentation | Jawna kolejność usług + dowiązanie portów do implementacji (odroczone z kroku 02). |
| `Application/UseCase/RenderCurrentFrameUseCase.php` | Application | Składa `Frame` ze stanu i oddaje go portowi renderującemu. |
| `tests/Support/ScriptedTerminal.php`, `RecordingRenderer.php` | Testy | Atrapy portów — pętla testowalna bez terminala. |
| 3 pliki testów | Testy | 15 nowych testów (łącznie 106). |

Zmienione: `TerminalPort` (metoda `shutdownRequested()`), `TerminalService`
(handler sygnału ustawia znacznik zamiast kończyć proces; zapis dopisywany do
skutku), `bin/light-manager` (preflight → bootstrap → pętla),
`bin/terminal-probe` (sprawdza znacznik zamknięcia).

### Model odświeżania — decyzja i konsekwencje

Przyjęto **stały takt 20 klatek na sekundę w obu trybach**, z zachowaniem
„pętla się ślizga”: gdy klatka nie zmieści się w budżecie 50 ms, kolejna
iteracja rusza natychmiast, bez nadrabiania zaległości i bez pomijania klatek.

Zmierzone zachowanie:

| Tryb | Klatka | Realny takt | Strumień |
|---|---|---|---|
| Tekstowy | < 1 ms | **19,6 kl./s** (budżet trzymany) | ~2 kB/s |
| Sixel | ~66 ms (klatka zastępcza, 4 wiersze) | **15,2 kl./s** (budżet przekroczony) | ~142 kB/s |

W trybie Sixel pętla nie osiąga zadanego taktu i będzie zwalniać dalej wraz z
bogaceniem się klatki — pomiar z kroku 08 dla pełnej listy 22 wierszy to
112 ms, czyli ~9 kl./s. Rdzeń procesora jest wtedy zajęty w całości, bo
rysowanie idzie bez przerw także wtedy, gdy nic się nie zmieniło. To świadomy
wybór modelu, nie usterka — próg do ewentualnej rewizji w kroku 11, gdy klatka
urośnie o realną listę plików.

### Sygnały: znacznik zamiast natychmiastowego wyjścia

Handler sygnałów z kroku 06 **przestał kończyć proces**. Ustawia teraz
znacznik, który pętla sprawdza w każdej iteracji i wychodzi przez `break` — tą
samą ścieżką co po klawiszu `q`. Dzięki temu sprzątanie ma jedno miejsce,
niezależnie od powodu wyjścia.

Konsekwencja, o której trzeba pamiętać: **sygnał zamyka tylko ten kod, który
sprawdza znacznik**. Dlatego `bin/terminal-probe` dostał ten sam warunek w
swojej pętli. Kod, który wejdzie w długie oczekiwanie bez sprawdzania
znacznika, nie da się już przerwać Ctrl+C — stąd ograniczenie liczby prób w
pętli zapisu (patrz niżej). Siatka bezpieczeństwa z kroku 06 (funkcja
zamknięcia procesu przywracająca terminal) działa bez zmian.

### Defekt wykryty w trakcie: ucięty zapis klatki

Analiza surowego strumienia wykazała, że jedna klatka Sixel na 44 kończyła się
**dokładnie na 8192 bajtach**, bez terminatora — po czym od razu zaczynała się
następna. Przyczyna: `fwrite()` może przyjąć mniej bajtów, niż mu podano (tu:
rozmiar bufora potoku), a kod ignorował zwracaną liczbę. Klatka większa niż
bufor traciła ogon i wyświetliłaby się uszkodzona.

Naprawa: `TerminalService::write()` dopisuje w pętli aż do wyczerpania danych,
z obsługą zapisu zerowej długości (krótka przerwa, ograniczona liczba prób —
pętla nie może tu utknąć, bo sygnały nie ubijają już procesu) i cichym
poddaniem się przy `false` (terminal zniknął — nie ma komu zgłosić błędu, a
wyjątek popsułby ścieżkę przywracania).

Po naprawie: **61 ładunków, 0 uciętych** w przebiegu 4-sekundowym.

### Drugi defekt: sprzątanie w bloku `catch`

Pierwsza wersja punktu wejścia wołała `Bootstrap::shutdown()` przy
przechwyceniu wyjątku. Gdy zawiódł sam bootstrap (np. brak TTY),
`shutdown()` próbował pobrać usługę terminala, której konstruktor rzucał ten
sam wyjątek raz jeszcze — tym razem nieprzechwycony, ze śladem stosu na
ekranie zamiast czytelnego komunikatu. Sprzątanie zostało z bloku `catch`
usunięte; przy nieudanym bootstrapie nie ma czego przywracać, a przy udanym
robi to zarejestrowana funkcja zamknięcia procesu.

### Weryfikacja pod realnym PTY

| Scenariusz | Wynik |
|---|---|
| Start i rysowanie klatek | 15 klatek w ~0,85 s, treść zgodna ze stanem |
| Wyjście klawiszem `q` | kod 0, bufor ekranu 1/1, kursor przywrócony |
| Wyjście przez Ctrl+C (SIGINT) | kod 0, bufor 1/1, kursor przywrócony |
| Wyjście przez SIGTERM z zewnątrz | kod 0, bufor 1/1 |
| Ustawienia terminala po zakończeniu | identyczne ze stanem sprzed uruchomienia |
| Brak TTY | czytelny komunikat, kod 1, bez śladu stosu |
| Nadpisywanie klatek | między klatkami wyłącznie `ESC [ H` |
| `bin/terminal-probe` po zmianie sygnałów | Ctrl+C nadal zamyka i przywraca terminal |

## Dziennik realizacji

- **2026-08-07** — Ukończono. Aplikacja startuje, rysuje klatki, reaguje na
  klawisz wyjścia i sygnały, a terminal wraca do stanu sprzed uruchomienia na
  każdej ścieżce. Wyjście z pętli zawsze przez `break` — w środku pętli nie ma
  ani jednego `exit()`. Powstały 4 pliki produkcyjne i 5 plików testowych
  (15 nowych testów, łącznie 106); PHPStan `max` bez błędów, PHP-CS-Fixer bez
  uwag.

  **Decyzje użytkownika podjęte na starcie kroku** (zobacz
  [00-decyzje.md](00-decyzje.md), D19): stały takt 20 kl./s w obu trybach;
  „pętla się ślizga” przy przekroczeniu budżetu; sygnał ustawia znacznik, a
  wyjście idzie przez `break`; znacznik wystawiony w `TerminalPort`; jawny
  obiekt stanu w `Presentation`; punkt wejścia jako preflight → bootstrap →
  pętla; klawisz wyjścia wyłącznie `q`; sonda terminala też sprawdza znacznik.

  **Rozstrzygnięcie techniczne w trakcie:** pętla zbiera **wszystkie** klawisze
  z jednego taktu, a nie jeden na iterację — przy stałym takcie odczyt
  pojedynczego klawisza gubiłby wejście szybciej piszącego użytkownika.

  **Znane ograniczenia:** w trybie Sixel takt 20 kl./s jest nieosiągalny
  (realnie 15,2 kl./s dla klatki zastępczej, ~9 kl./s dla pełnej listy z
  kroku 11) i pętla zajmuje wtedy rdzeń w całości; strumień w trybie Sixel to
  ~142 kB/s, co przez SSH bywa odczuwalne.
