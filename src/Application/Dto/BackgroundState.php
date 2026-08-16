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
 *
 * **Od kroku 52 wypis dociera także wtedy, gdy praca trwa** (D91 nr 12). Do tego
 * kroku `Running` znaczyło „nic ci jeszcze nie powiem” — a to zamykało drogę
 * każdemu poleceniu, które **nie kończy się nigdy**: `kubectl logs -f` wypisywał
 * wiersze do potoku, port je zbierał, ale pierwszy raz oddałby je dopiero po
 * śmierci potomka, czyli nigdy. Zmiana jest w jednym zdaniu kontraktu i nie
 * odwraca poprzedniego: **polecenie, którego wyjściem jest treść, nadal czyta ją
 * przy `Done`**, bo wypis trwającej pracy jest z definicji urwany w połowie —
 * pół JSON-a nie jest JSON-em. Czytają go ci, dla których wypis jest strumieniem
 * wierszy (`OutputShape::Stream`).
 */
final class BackgroundState
{
    private function __construct(
        public readonly BackgroundStage $stage,
        /**
         * Standardowe wyjście polecenia; ma sens przy `Done`, a przy `Running`
         * jest tym, co dotąd przyszło (krok 52).
         *
         * Przycięte z białych znaków przy `OutputShape::Result` — i **nieprzycięte
         * przy `Stream`**, bo tam czytający liczy pozycje w bajtach.
         */
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
        /**
         * Ile bajtów wypadło z **początku** wypisu, bo bufor się przesunął
         * (krok 52).
         *
         * Zero wszędzie poza pracą zamówioną jako `OutputShape::Stream`, bo tylko
         * tam bufor zapomina najstarsze. Czytający strumień trzyma własny licznik
         * bajtów już odczytanych i z tej liczby pozna dwie rzeczy naraz: gdzie
         * w buforze zaczyna się to, czego jeszcze nie widział, oraz **czy coś go
         * ominęło** — jeśli odczytał mniej, niż zdążyło wypaść, wierszy pomiędzy
         * nie ma już nigdzie. Milcząca dziura w logu jest gorsza od dziury
         * opisanej, więc rdzeń podaje liczbę, a odbiorca mówi o niej zdaniem.
         */
        public readonly int $droppedBytes = 0,
    ) {
    }

    public static function idle(): self
    {
        return new self(BackgroundStage::Idle, '', '', null, null, []);
    }

    /** @param string $output to, co praca zdążyła wypisać — puste, dopóki nic nie napisała */
    public static function running(string $output = '', string $errorOutput = '', int $droppedBytes = 0): self
    {
        return new self(BackgroundStage::Running, $output, $errorOutput, null, null, [], $droppedBytes);
    }

    public static function done(
        string $output,
        int $exitCode,
        string $errorOutput = '',
        int $droppedBytes = 0,
    ): self {
        return new self(BackgroundStage::Done, $output, $errorOutput, $exitCode, null, [], $droppedBytes);
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
