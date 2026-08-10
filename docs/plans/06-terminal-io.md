# Krok 06 — Terminal I/O: tryb surowy, odczyt klawiszy, bezpieczne wyjście

## Status

Ukończony

## Zależności

Krok 05.

## Model i wysiłek

Opus / high — najbardziej podatny na błędy krok fundamentu: niskopoziomowa
manipulacja terminalem, brak natywnego wsparcia w PHP (konieczne wywołania
zewnętrzne, np. `stty`), obsługa sygnałów. Błąd tutaj = zepsuty terminal
użytkownika po każdym uruchomieniu.

## Cel

Dostarczyć fundament wejścia dla pętli gry: terminal w trybie surowym (bez
buforowania liniowego, bez echo), nieblokujący odczyt pojedynczych klawiszy
(w tym sekwencji escape dla strzałek), oraz **gwarantowane** przywrócenie
oryginalnego stanu terminala przy każdym zakończeniu procesu — normalnym,
przez wyjątek, i przez sygnał (SIGINT/SIGTERM).

## Zakres

- Odczyt i zapamiętanie oryginalnych ustawień terminala (`stty -g` lub
  równoważne) przed przełączeniem w tryb raw.
- Przełączenie w tryb raw (m.in. `-icanon -echo`) na czas działania
  aplikacji.
- Nieblokujący odczyt pojedynczego bajtu/klawisza z STDIN (np. przez
  `stream_set_blocking` + `stream_select`), w tym rozpoznawanie
  wieloznakowych sekwencji escape (strzałki itp.) na potrzeby przyszłej
  nawigacji (krok 10).
- Rejestracja obsługi sygnałów (`pcntl_signal` dla SIGINT/SIGTERM +
  `pcntl_async_signals(true)`) wywołującej przywrócenie terminala przed
  zakończeniem procesu.
- Mechanizm „zawsze przywróć terminal” niezależny od ścieżki wyjścia (np.
  `register_shutdown_function` jako siatka bezpieczeństwa, dodatkowo do
  obsługi sygnałów).
- Ręczny test: zabicie procesu (Ctrl+C) w trakcie działania nie zostawia
  terminala w trybie raw/bez echo.

## Poza zakresem tego kroku

Interpretacja klawiszy jako komend aplikacji (to robi krok 09/10) — tu tylko
surowy strumień klawiszy/bajtów.

## Ryzyka

- Zależność od dostępności `pcntl` (może nie być domyślnie włączone we
  wszystkich buildach PHP CLI) i od zewnętrznego binarki `stty` (zależność
  od systemu, brak przenośności na Windows — do udokumentowania jako
  założenie: Linux/macOS).
- SIGKILL nie da się obsłużyć (terminal nie zostanie przywrócony) — do
  udokumentowania jako znane ograniczenie, nie błąd do „naprawienia”.

## Kryteria ukończenia

- Aplikacja przechodzi w tryb raw, odczytuje pojedyncze klawisze bez
  potrzeby Enter.
- Ctrl+C oraz normalne zakończenie procesu zawsze przywracają terminal do
  stanu sprzed uruchomienia.
- Sekwencje escape (strzałki) są rozpoznawane jako pojedyncze zdarzenia
  klawiszowe, nie jako seria pojedynczych znaków.

## Specyfikacja zrealizowana

### Powstałe pliki

| Plik | Warstwa | Rola |
|---|---|---|
| `Infrastructure/Support/AbstractSingleton.php` | Infrastructure | Klasa bazowa Singletonów wg kroku 02 (odroczona tam „do kroku 05”, ostatecznie utworzona tutaj — D14). |
| `Infrastructure/Support/InfrastructureException.php` | Infrastructure | Abstrakcyjna baza wyjątków warstwy, `extends \RuntimeException`. |
| `Infrastructure/Terminal/TerminalException.php` | Infrastructure | Konkretne błędy terminala, nazwane konstruktory statyczne. |
| `Infrastructure/Terminal/KeySequenceParser.php` | Infrastructure | Czysta (bez I/O) zamiana bajtów na zdarzenia klawiszowe. |
| `Infrastructure/Terminal/ParsedKey.php` | Infrastructure | Wynik parsera: `KeyPress` + liczba skonsumowanych bajtów. |
| `Infrastructure/Terminal/TerminalService.php` | Infrastructure | Singleton implementujący `TerminalPort`: tryb raw, sygnały, odczyt. |
| `Application/Port/TerminalPort.php` | Application | Kontrakt: `readKey(): ?KeyPress`. |
| `Application/Dto/Key.php` | Application | Enum klawiszy (strzałki, Home/End/PageUp/PageDown/Delete, Enter, Backspace, Tab, Escape, Character, Unknown). |
| `Application/Dto/KeyPress.php` | Application | Klawisz + surowe bajty, `equals()`. |
| `tests/Support/ResetsSingletons.php` | Testy | Reset Singletonów przez Reflection wg kroku 02. |
| `tests/Infrastructure/Support/AbstractSingletonTest.php` (+ 2 fikstury) | Testy | 6 testów mechaniki Singletona. |
| `tests/Infrastructure/Terminal/KeySequenceParserTest.php` | Testy | 37 testów parsowania sekwencji. |

`Domain` pozostaje nietknięty, zgodnie z tabelą warstw z kroku 01.

### Kluczowe rozstrzygnięcia techniczne

- **Tryb raw:** `stty -icanon -echo -ixon min 1 time 0`. Świadomie **nie**
  używamy pełnego `stty raw`, bo ten wyłącza też `isig` — Ctrl+C przestałby
  generować SIGINT i stałby się zwykłym bajtem, przez co kryterium
  „Ctrl+C przywraca terminal” nie dałoby się spełnić przed krokiem 09.
  `-ixon` dołożone ponad literalny zakres kroku: bez niego Ctrl+S zamraża
  wyjście terminala, co w aplikacji pełnoekranowej wygląda jak zawieszenie.
- **Trzy niezależne ścieżki przywrócenia terminala:** obsługa sygnałów
  (SIGINT, SIGTERM, SIGHUP, SIGQUIT → `restore()` + `exit(128 + sygnał)`),
  `register_shutdown_function()` jako siatka bezpieczeństwa (łapie też
  niezłapany wyjątek) oraz publiczne, idempotentne `restore()`.

  > **Zmienione w kroku 09:** handler sygnału nie kończy już procesu — ustawia
  > znacznik, który pętla gry sprawdza i wychodzi przez `break`
  > ([00-decyzje.md](00-decyzje.md), D19). Pozostałe dwie ścieżki działają bez
  > zmian, a gwarancję przywrócenia terminala trzyma od tego momentu funkcja
  > zamknięcia procesu. Skutek uboczny: sygnał zamyka wyłącznie kod, który ten
  > znacznik sprawdza.
- **Rozbrojenie EINTR:** sygnał doręczony w trakcie `stream_select()` przerywa
  wywołanie systemowe, a PHP wypisuje wtedy ostrzeżenie wprost na terminal —
  w aplikacji rysującej pełne klatki zniszczyłoby to obraz. Ostrzeżenie jest
  wyciszone (`@`) z komentarzem, a przerwane oczekiwanie traktowane jak brak
  wejścia. Defekt wykryty dopiero w teście pod realnym PTY, nie przez
  narzędzia statyczne.
- **Niejednoznaczność klawisza Escape:** `ESC` jest prefiksem dłuższych
  sekwencji, więc parser ma dwie metody — `parse()` zwraca `null`, gdy bufor
  może jeszcze urosnąć, a `parseAfterTimeout()` rozstrzyga po upływie okna
  20 ms na dosłanie reszty przez terminal.
- **Wymóg `ext-pcntl`** dopisany do `require` w `composer.json` (obok
  `ext-imagick`) — bez obsługi sygnałów nie da się spełnić kryterium
  przywracania terminala.
- **PHPStan:** `new static()` w `AbstractSingleton` rozwiązane adnotacją
  `@phpstan-consistent-constructor` na klasie bazowej, nie przez
  `@phpstan-ignore`. Adnotacja jest przy okazji strażnikiem kontraktu z D11:
  klasa pochodna nie może dodać parametrów konstruktora, bo `getInstance()`
  nie miałaby ich skąd wziąć.

### Weryfikacja pod realnym PTY

Kryteria wymagające interaktywnego terminala sprawdzono automatycznie,
uruchamiając skrypty próbne pod pseudoterminalem (`script -qec …`):

| Scenariusz | Wynik |
|---|---|
| Wejście w tryb raw | `stty -g` różni się od pierwotnego; `-echo` i `-icanon` aktywne |
| Odczyt klawiszy bez Enter | `a b ↑ ↓ → ← Enter Tab Backspace ↑(SS3) PageUp Escape ą q` — każda sekwencja jako **jedno** zdarzenie |
| Znak wielobajtowy UTF-8 | `ą` (`c485`) zwrócone jako jeden `Character`, nie dwa bajty |
| Ctrl+C (bajt `0x03` przez PTY) | terminal przywrócony, kod wyjścia 130, brak ostrzeżeń (od kroku 09: kod 0, wyjście przez `break` pętli) |
| SIGTERM z zewnątrz | terminal przywrócony |
| Niezłapany wyjątek | terminal przywrócony przez funkcję zamknięcia |
| STDIN nie jest terminalem | `TerminalException` z czytelnym komunikatem |
| Tożsamość Singletona | `getInstance()` zwraca tę samą instancję, `instanceof TerminalPort` |

Scenariusze wymagające wymuszonego zakończenia (sygnały, awaria) sprawdzono
skryptami próbnymi z katalogu roboczego sesji. Ręczny podgląd klawiszy został
natomiast utrwalony w repozytorium jako `bin/terminal-probe` — wypisuje nazwę
klawisza i jego bajty, kończy się na `q` albo Ctrl+C. Nie jest wpisany do
sekcji `bin` w `composer.json`: to narzędzie diagnostyczne, a nie punkt
wejścia aplikacji, którym pozostaje `bin/light-manager` (pętla gry dojdzie do
niego w kroku 09).

## Dziennik realizacji

- **2026-08-07** — Ukończono. Zrealizowano pełny zakres kroku: tryb surowy,
  nieblokujący odczyt pojedynczych klawiszy z rozpoznawaniem sekwencji
  escape, obsługa sygnałów i gwarantowane przywrócenie terminala. Powstało
  12 plików (6 produkcyjnych w `Infrastructure`, 3 w `Application`, 3 w
  `tests/` plus 2 fikstury); 43 testy jednostkowe, PHPStan `max` bez błędów,
  PHP-CS-Fixer bez uwag.

  **Decyzje użytkownika podjęte na starcie kroku** (zobacz
  [00-decyzje.md](00-decyzje.md), D16): reprezentacja klawisza jako DTO
  `KeyPress` + enum `Key` w `Application/Dto`; własna hierarchia wyjątków w
  `Infrastructure`; wydzielony, testowalny `KeySequenceParser`; brak TTY
  kończy się wyjątkiem.

  **Odstępstwa i uzupełnienia względem literalnego zakresu:** dodano wariant
  `Key::Unknown` (kompletna, ale nieobsługiwana sekwencja jest konsumowana i
  ignorowana, zamiast rozsypywać się na pojedyncze znaki); dodano `-ixon` do
  ustawień trybu raw; dopisano `ext-pcntl` do `require`. `Key` jest enumem,
  czyli formalnie obiektem wartości, a mimo to leży w `Application/Dto`, a
  nie w `Domain/ValueObject` — świadomie, bo tabela warstw z kroku 01
  przewiduje dla kroku 06 „`Domain` nietknięty”, a klawisz terminala nie jest
  pojęciem domenowym menadżera plików.

  **Poza zakresem, celowo:** `TerminalPort` ma tylko `readKey()` — zapis na
  terminal dojdzie dopiero, gdy będzie potrzebny (krok 07 wysyła DA1, krok 08
  wypycha Sixel); `bin/light-manager` nie został podpięty pod usługę, bo
  interaktywny punkt wejścia powstaje w kroku 09.

  **Uzupełnienie po zamknięciu kroku (na życzenie użytkownika):** dodano
  `bin/terminal-probe` — stały, wykonywalny skrypt diagnostyczny do ręcznego
  sprawdzenia trybu surowego i rozpoznawania klawiszy. Zweryfikowany pod PTY
  (strzałki, Home, PageUp, Escape, znak wielobajtowy, Enter, Backspace, wyjście
  na `q`, kod 0) oraz bez TTY (komunikat `TerminalException`, kod 1). Opisany
  w `README.md`.

  **Znane ograniczenie** (zgodnie z „Ryzykami” tego kroku, nie do
  naprawienia): SIGKILL nie da się przechwycić — po `kill -9` terminal
  zostaje w trybie surowym i wymaga `stty sane`. Odnotowane w `README.md`.
