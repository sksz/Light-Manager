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
 * **Wyjście to wyłącznie standardowe wyjście.** Strumień błędów jest czytany
 * (inaczej potomek, który go zapełni, stanąłby na zawsze), ale nie trafia tutaj:
 * `du` na katalogu domowym wypisuje na nim dziesiątki wierszy „brak dostępu”,
 * a mimo to podaje na standardowym wyjściu prawidłową sumę tego, co przeczytał.
 * Sklejenie obu strumieni zamieniłoby liczbę do odczytania w stertę do
 * przeszukania.
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
        return new self(BackgroundStage::Idle, '', null, null, []);
    }

    public static function running(): self
    {
        return new self(BackgroundStage::Running, '', null, null, []);
    }

    public static function done(string $output, int $exitCode): self
    {
        return new self(BackgroundStage::Done, $output, $exitCode, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(BackgroundStage::Failed, '', null, $problemKey, $parameters);
    }

    public function isRunning(): bool
    {
        return $this->stage === BackgroundStage::Running;
    }
}
