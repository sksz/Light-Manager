<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Jedno zdanie, które budowa powiedziała o sobie (krok 51).
 *
 * Trzy rodzaje i każdy znaczy co innego dla tego, kto patrzy: **krok** jest
 * wierszem wypisu (widać go w oknie postępu), **obraz** jest skrótem gotowej
 * treści (to po niego cała budowa była), a **niepowodzenie** jest jedynym
 * miejscem, w którym o nim usłyszymy — odpowiedź HTTP nieudanej budowy ma kod
 * 200, bo z punktu widzenia protokołu wszystko poszło dobrze.
 */
final readonly class BuildMessage
{
    private function __construct(
        public BuildMessageKind $kind,
        /** Treść zdania: wiersz wypisu, skrót obrazu albo powód niepowodzenia. */
        public string $text,
    ) {
    }

    public static function step(string $text): self
    {
        return new self(BuildMessageKind::Step, $text);
    }

    public static function built(string $imageId): self
    {
        return new self(BuildMessageKind::Built, $imageId);
    }

    public static function failure(string $reason): self
    {
        return new self(BuildMessageKind::Failure, $reason);
    }
}
