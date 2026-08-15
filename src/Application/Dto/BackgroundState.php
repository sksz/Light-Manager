<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Stan pracy tłowej w tej chwili — dana, nie proces.
 *
 * Druga część wzorca pracy kawałkowej (D46): to, co ekran ogląda co klatkę
 * i z czego bierze się wypełnienie paska postępu. `ChecksumState` niesie ułamek
 * i sumę, ten niesie **wyjście polecenia i kod wyjścia** — i to jest cała
 * różnica między pracą własną a procesem potomnym widziana z warstwy aplikacji.
 *
 * **Strumienie są dwa i od kroku 49 oba tu docierają — osobno.** Do tamtego
 * kroku strumień błędów był czytany i wyrzucany: `du` na katalogu domowym
 * wypisuje na nim dziesiątki wierszy „brak dostępu”, a mimo to podaje na
 * standardowym wyjściu prawidłową sumę, więc sklejenie obu zamieniłoby liczbę
 * do odczytania w stertę do przeszukania. **Ta zasada zostaje w mocy** — pola są
 * rozdzielone właśnie po to, żeby nikt niczego nie sklejał.
 *
 * Zmieniło się co innego: okazało się, że sklejanie strumieni **w wierszu
 * polecenia** (`2>&1`) potrafi zepsuć dane, a nie tylko je zaśmiecić. Odkryto to
 * w kroku 49 na odczycie zdalnego katalogu: `ssh` przy `ControlPath` jest
 * klientem multipleksera i **przekazuje swoje deskryptory mistrzowi połączenia**,
 * a ten — obsługując wiele sesji w jednej pętli — ustawia im tryb nieblokujący.
 * Tryb ten jest własnością **opisu pliku**, więc scalony strumień błędów
 * przenosił go na wyjście potomka; odkąd potok się zapełnił, `write()` zwracał
 * `EAGAIN`, a OpenSSH **porzucał tę porcję wypisu i kończył się kodem zero**.
 * Z 419 KB listy dochodziło 130 KB, bez śladu w kodzie wyjścia.
 *
 * Stąd to pole: polecenie, którego wyjściem jest **treść**, nie ma prawa mieszać
 * do niej diagnostyki — a mimo to musi mieć jak powiedzieć, co poszło nie tak.
 *
 * Kod wyjścia jest przy `Done` i nie jest sam z siebie powodem niepowodzenia —
 * `du` kończy się jedynką za każdy nieprzeczytany katalog. Co z niego wynika,
 * rozstrzyga ten, kto zamówił pracę; rdzeń go tylko podaje.
 */
final class BackgroundState
{
    private function __construct(
        public readonly BackgroundStage $stage,
        /** Standardowe wyjście polecenia, przycięte z białych znaków; ma sens przy `Done`. */
        public readonly string $output,
        /**
         * Strumień błędów polecenia, przycięty z białych znaków; ma sens przy
         * `Done` (krok 49).
         *
         * **Pusty napis znaczy „polecenie nie narzekało”**, a nie „nie wiem”.
         * Czytają go ci, dla których diagnostyka jest treścią — odczyt zdalnego
         * katalogu odróżnia po nim „nie ma takiego katalogu” od „brak prawa
         * wejścia”. `du` go nie czyta i nic się dla niego nie zmieniło.
         */
        public readonly string $errorOutput,
        /** Kod wyjścia; `null` wszędzie poza `Done`. */
        public readonly ?int $exitCode,
        /** Klucz katalogu z powodem — wyłącznie przy `Failed`. */
        public readonly ?string $problemKey,
        /**
         * Parametry do podstawienia w powodzie.
         *
         * @var array<string, string|int|float>
         */
        public readonly array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(BackgroundStage::Idle, '', '', null, null, []);
    }

    public static function running(): self
    {
        return new self(BackgroundStage::Running, '', '', null, null, []);
    }

    public static function done(string $output, int $exitCode, string $errorOutput = ''): self
    {
        return new self(BackgroundStage::Done, $output, $errorOutput, $exitCode, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(BackgroundStage::Failed, '', '', null, $problemKey, $parameters);
    }

    public function isRunning(): bool
    {
        return $this->stage === BackgroundStage::Running;
    }
}
