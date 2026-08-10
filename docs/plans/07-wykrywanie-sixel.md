# Krok 07 — Wykrywanie obsługi Sixela (DA1) i tryb fallback

## Status

Ukończony

## Zależności

Krok 06 (potrzebny surowy, nieblokujący odczyt STDIN, żeby odebrać
odpowiedź terminala).

## Model i wysiłek

Opus / high — protokół terminalowy niskiego poziomu, parsowanie
niestandaryzowanej odpowiedzi, konieczność timeoutu (terminal może w ogóle
nie odpowiedzieć).

## Cel

Ustalić w czasie startu aplikacji, czy bieżący terminal obsługuje Sixel, i
wybrać na tej podstawie tryb renderowania (Sixel albo fallback tekstowy)
używany przez krok 08.

## Zakres

- Wysłanie zapytania Primary Device Attributes (`\033[c`) na STDOUT.
- Odczyt odpowiedzi terminala z surowego STDIN (na bazie fundamentu z kroku
  06) z limitem czasu (np. kilkaset ms) — brak odpowiedzi w tym czasie =
  traktujemy jako brak wsparcia.
- Parsowanie odpowiedzi (`\033[?...c`) i sprawdzenie obecności parametru `4`
  (Sixel) w liście zwróconych możliwości.
- Wystawienie prostego wyniku dla reszty aplikacji: enum/flaga
  `RendererMode::Sixel` albo `RendererMode::TextFallback`.
- Uwzględnienie wyniku sprawdzenia z kroku 05 (czy Imagick w ogóle ma
  skompilowany koder SIXEL) — brak kodera też wymusza fallback, niezależnie
  od odpowiedzi terminala.

## Poza zakresem tego kroku

Sama implementacja renderera tekstowego fallback ani renderera Sixel — to
robi krok 08. Tu tylko decyzja, którego użyć.

## Ryzyka

- Niektóre terminale mogą odpowiadać na DA1 nietypowo albo z opóźnieniem —
  timeout musi być na tyle długi, by nie dawać fałszywych negatywów na
  wolniejszych połączeniach (np. SSH), ale na tyle krótki, by nie opóźniać
  startu odczuwalnie.
- Terminale multiplexowane (tmux/screen) mogą filtrować lub modyfikować
  odpowiedzi DA1 — do zanotowania jako znane ograniczenie w dokumentacji,
  nie musi być rozwiązane w MVP.

## Kryteria ukończenia

- Na terminalu ze wsparciem Sixela aplikacja poprawnie wykrywa
  `RendererMode::Sixel`.
- Na terminalu bez wsparcia aplikacja w rozsądnym czasie (bez zauważalnego
  zawieszenia) przełącza się na `RendererMode::TextFallback`.

## Specyfikacja zrealizowana

### Powstałe pliki

| Plik | Warstwa | Rola |
|---|---|---|
| `Domain/ValueObject/RendererMode.php` | Domain | Enum `Sixel` \| `TextFallback` — **pierwszy plik w warstwie domenowej**. |
| `Application/Port/RendererModeDetectorPort.php` | Application | Kontrakt: `detect(): RendererMode`. |
| `Infrastructure/Terminal/DeviceAttributesParser.php` | Infrastructure | Czysta (bez I/O) analiza odpowiedzi DA1. |
| `Infrastructure/Terminal/SixelCapabilityService.php` | Infrastructure | Singleton implementujący port; łączy oba warunki trybu Sixel. |
| `Infrastructure/Imagick/ImagickCapabilityService.php` | Infrastructure | Singleton odpowiadający na pytania o możliwości ImageMagick. |
| `tests/Domain/ValueObject/RendererModeTest.php` | Testy | 2 testy enuma. |
| `tests/Infrastructure/Terminal/DeviceAttributesParserTest.php` | Testy | 19 testów analizy odpowiedzi DA1. |

Rozszerzony: `TerminalService` o dwie publiczne metody poza kontraktem portu —
`write()` i `readRawBytes()`.

### Kluczowe rozstrzygnięcia techniczne

- **Dwa niezależne warunki trybu Sixel**, sprawdzane w kolejności od
  najtańszego: najpierw koder `SIXEL` w Imagicku (pytanie lokalne, bez I/O na
  terminalu), dopiero potem zapytanie DA1. Brak kodera oznacza, że zapytanie
  **w ogóle nie jest wysyłane** — nie ma po co zaczepiać terminala pytaniem,
  którego wynik i tak niczego nie zmieni.
- **Surowy odczyt zamiast `readKey()`**: odpowiedź DA1 to sekwencja escape,
  którą `KeySequenceParser` rozbiłby na `Escape` + pojedyncze znaki. Stąd
  `TerminalService::readRawBytes()` — omija parser klawiszy, ale zabiera ze
  sobą bajty zebrane wcześniej przez `readKey()`, żeby nic nie zostało
  uwięzione w buforze.
- **`TerminalPort` pozostaje minimalny** — `write()` i `readRawBytes()` są
  publiczne na usłudze, nie w kontrakcie portu. `Application` nie dostaje
  dostępu do surowych bajtów, bo ich nie potrzebuje; `Infrastructure` rozmawia
  z `Infrastructure` bezpośrednio, co reguła zależności dopuszcza.
- **Wykrywanie wzorca w całym buforze, nie na jego początku** (`preg_match` na
  `\e\[\?([0-9;]*)c`) — w oknie oczekiwania mogą wylądować bajty wpisane przez
  użytkownika przed odpowiedzią lub po niej.
- **Wynik cache'owany w konstruktorze** — zgodnie z zasadą z kroku 02
  (usługa z efektem ubocznym wymaganym przed pętlą gry trafia do
  `Bootstrap::boot()`). `detect()` tylko zwraca ustaloną wartość, więc kolejne
  wywołania nie odpytują terminala ponownie.
- **Timeout 300 ms**, odpytywanie co 5 ms. Odpowiedź lokalnego terminala
  przychodzi w kilkadziesiąt ms; 300 ms zostawia zapas na SSH, a start na
  terminalu, który nigdy nie odpowie, wydłuża się o ledwie zauważalną chwilę.

### Weryfikacja pod realnym PTY

Zamiast szukać terminala z Sixelem, odpowiedzi DA1 wstrzykiwano do
pseudoterminala, symulując różne terminale:

| Scenariusz | Wstrzyknięta odpowiedź | Wynik | Czas |
|---|---|---|---|
| Terminal z Sixelem, odpowiedź przed zapytaniem | `ESC [ ? 62 ; 4 c` | `Sixel` | 31 ms |
| Terminal bez Sixela (VT100) | `ESC [ ? 1 ; 2 c` | `TextFallback` | 31 ms |
| Terminal milczący | — | `TextFallback` | 331 ms |
| Odpowiedź po zapytaniu, w oknie | `ESC [ ? 64 ; 1 ; 2 ; 4 ; 6 ; 9 c` | `Sixel` | 187 ms |
| Brak rozszerzenia `imagick`, terminal z Sixelem | `ESC [ ? 62 ; 4 c` | `TextFallback` | 0 ms, zapytanie DA1 **nie** wysłane |
| Odczyt klawiszy po detekcji | — | `↑ ą Enter spacja q` rozpoznane normalnie | — |

Ostatni wiersz to sprawdzenie, że okno detekcji nie psuje późniejszego
strumienia wejścia.

## Dziennik realizacji

- **2026-08-07** — Ukończono. Zrealizowano pełny zakres kroku: zapytanie DA1,
  odczyt odpowiedzi z limitem czasu, analiza parametrów, uwzględnienie kodera
  Imagick i wystawienie wyniku jako `RendererMode`. Powstało 5 plików
  produkcyjnych i 2 pliki testów (21 nowych testów, łącznie 64); PHPStan `max`
  bez błędów, PHP-CS-Fixer bez uwag.

  **Decyzje użytkownika podjęte na starcie kroku** (zobacz
  [00-decyzje.md](00-decyzje.md), D17): sprawdzenie kodera Imagick jako osobny
  Singleton w `Infrastructure/Imagick`; surowe I/O jako publiczne metody
  `TerminalService` poza kontraktem portu; timeout 300 ms.

  **Uzupełnienie poza literalnym zakresem:** `bin/terminal-probe` wypisuje
  teraz wykryty tryb renderowania wraz z dostępnością kodera — krok 07 nie ma
  własnego punktu wejścia, a bez tego nie dałoby się sprawdzić wykrywania
  ręcznie przed krokiem 09. `bin/light-manager` celowo pozostał nietknięty:
  podłączenie go pod usługi wymagałoby terminala interaktywnego, a to zakres
  kroku 09.

  **Znane ograniczenia** (zgodne z „Ryzykami” tego kroku): multipleksery
  (tmux, screen) potrafią odfiltrować odpowiedź DA1 — aplikacja zejdzie wtedy
  do trybu tekstowego mimo terminala obsługującego Sixel; bajty wpisane w
  oknie detekcji, które nie są częścią odpowiedzi DA1, są odrzucane razem z
  nią.
