<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Kontekst klienta `docker` — jeden wiersz z `docker context ls` (krok 58).
 *
 * Dana czytana z cudzego narzędzia, więc niesie **dokładnie to, co tamto
 * wypisuje**, bez oceny: adres bywa gniazdem (`unix://`), demonem po sieci
 * (`tcp://`) albo drogą, którą moduł świadomie odrzucił (`ssh://` — D96 nr 2,
 * odrzucone twardo). Czy da się z tym rozmawiać, rozstrzyga dopiero wybór
 * wpisu, nie odczyt.
 */
final readonly class ContextEntry
{
    public function __construct(
        public string $name,
        public string $endpoint,
        public bool $current,
    ) {
    }

    /** Ścieżka gniazda, gdy adres jest gniazdem unixowym; inaczej `null`. */
    public function socketPath(): ?string
    {
        return str_starts_with($this->endpoint, 'unix://')
            ? substr($this->endpoint, strlen('unix://'))
            : null;
    }
}
