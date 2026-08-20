# 4. Standardy PHP i narzędzia

> Rozdział 4 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

- **PHP `^8.3`** (zgodne z lokalnym środowiskiem deweloperskim).
- `declare(strict_types=1)` obowiązkowe w każdym pliku, wymuszane przez
  PHP-CS-Fixer.
- **PHP-CS-Fixer**: baza `@PSR12` + `declare_strict_types`,
  `strict_comparison`, `strict_param`, `single_quote`,
  `trailing_comma_in_multiline`, `ordered_imports`, `no_unused_imports`,
  `void_return`, `binary_operator_spaces`.
- **PHPStan**: poziom `max` od startu. Punktowe, uzasadnione
  `@phpstan-ignore` zamiast obniżania poziomu globalnie.
- **PHPUnit**: `tests/` odzwierciedla `src/` 1:1, klasa testowa
  `{Nazwa}Test`. Testy jednostkowe obowiązkowe dla `Domain`/`Application`
  (zero I/O). `Infrastructure`/`Presentation` — testy automatyczne w miarę
  możliwości, reszta do weryfikacji manualnej. Preferuj
  `self::assertSame()` nad `assertEquals()`.
- **Dwie grupy testów od kroku 38** (`phpunit.xml.dist`): `unit` — klasy;
  `functional` — **przebiegi użytkownika** w `tests/Functional/`, nazwane
  `{Przebieg}FlowTest`. Przebieg to sekwencja klawiszy przez `ScreenFixture`
  (komplet ekranów i prawdziwych modułów bez FS, terminala i Imagicka)
  z asercjami w punktach kontrolnych; start aplikacji i zmiana rozmiaru okna
  idą dodatkowo przez `GameLoop` ze `ScriptedTerminal`, bo taktu bez pętli
  sprawdzić się nie da. Katalog jest **spisem zachowań**, a nie zbiorem
  skutków ubocznych kolejnych kroków — brak przebiegu w spisie jest luką do
  uzupełnienia, a nie stanem naturalnym.
- **Złote klatki**: `tests/Golden/<scenariusz>.txt` — serializacja prymitywów
  klatek `ScenarioFactory`, porównywana niezależnie od renderera. Odnawia je
  **wyłącznie** `./bin/render-bench --golden-save`, po przeczytaniu różnicy;
  plik regenerowany automatem przestaje być testem.
- Konfiguracje (`.php-cs-fixer.dist.php`, `phpstan.neon.dist`,
  `phpunit.xml.dist`) — zaprojektowane w
  [docs/plans/archiwum/03-standardy-stylu-kodowania.md](../plans/archiwum/03-standardy-stylu-kodowania.md),
  utworzone w korzeniu repozytorium w kroku 05 (bez zmian względem planu).
- **Definicje poleceń jakości mieszkają w `composer.json`** (`cs`, `cs:check`,
  `stan`, `test`) i to je wołają cele `make cs`, `cs-check`, `stan`, `test` —
  Makefile jest cienką warstwą, nie drugą definicją (krok 39, D72). Wejściem do
  procesu jest cel `make`: pełny spis w rozdz. 8 „Procesy projektu”.

## Diagnostyka i pomiar (kroki 16, 35, 38)

Jedyną drogą do pomiaru jest `bin/render-bench` — doraźna pętla `microtime()`
daje wynik, którego nie da się z niczym porównać, nie niesie metryczki
środowiska i nie zostawia śladu. Narzędzie mierzy **cztery tory**, każdy
odpowiadający na inne pytanie:

| Tor | Wywołanie | Fazy |
|---|---|---|
| sixelowy | *(domyślny)* | rysowanie → kwantyzacja → kodowanie |
| okienkowy | `--window` | rysowanie → zamiana buforów |
| tekstowy | `--text` | prymitywy → bufor komórek → bajty ANSI |
| takt pętli | `--loop` | wejście → stan → złożenie klatki (bez renderera) |

Zasady, których nie wolno cicho odwrócić:

- **Zero wywołań pomiarowych w kodzie produkcyjnym** (D28). Zegar stoi po
  stronie narzędzia; renderery są rozbite na **publiczne kroki**
  (`SixelFrameEncoder` w kroku 16, `TextFrameRenderer` w kroku 38) i to jedyne
  przyznane szwy.
- **Każdy element interfejsu ma scenariusz albo zapisany powód pominięcia** —
  spis w [docs/pomiary/README.md](../pomiary/README.md); nowy scenariusz musi dać
  się rozliczyć **w parze** z istniejącym.
- **Zimna klatka jedzie obok mediany, nie zamiast niej** i nigdy nie podnosi
  alarmu regresji.
- **Obciążenie maszyny wchodzi do metryczki wzorca**; przy zapisie ostrzega,
  ale nie odmawia — odmowa zostaje strażnikowi rozrzutu (1,35×).
- **Regresję wizualną wykrywa porównanie zrzutów** (`--png-compare`, metryka
  AE), a nie oko; wzorcowe PNG leżą w `docs/pomiary/wzorce-png/`.
- **Zrzut z żywej aplikacji** robi komenda `core.dump` — prymitywy i obraz
  **wierny torowi** (płótno, bufor karty albo rasteryzacja bufora ANSI).
