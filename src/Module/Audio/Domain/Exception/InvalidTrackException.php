<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Ścieżka utworu, której nie da się wpisać na playlistę (krok 45).
 *
 * Wyjątek **przedstawia się sam** (`DescribesProblem`), bo mówi o dziedzinie
 * modułu: rdzeń nie wie, czym jest utwór, i nie ma prawa dobierać dla niego
 * zdania po klasie wyjątku (D42, reguła 8).
 *
 * Rzuca **wyłącznie obiekt wartości**, i to jest cała jego rola: pozycja
 * playlisty pilnuje swojej poprawności sama, a wołający sprawdzają ją wcześniej,
 * żeby użytkownik zobaczył zdanie zamiast śladu stosu. Odczyt pliku playlisty
 * wyjątku nigdy nie zobaczy — wpis bez ścieżki pomija, zamiast go budować
 * (zasada „port nie rzuca”).
 */
final class InvalidTrackException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function emptyPath(): self
    {
        return new self('Track path is empty.', 'module.audio.track.empty', []);
    }

    public function problemKey(): string
    {
        return $this->problemKey;
    }

    public function problemParameters(): array
    {
        return $this->problemParameters;
    }
}
