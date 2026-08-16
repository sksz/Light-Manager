<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

/**
 * Projekt compose widziany ze spisu `docker compose ls` (krok 51).
 *
 * Trzy pola, bo tyle oddaje wtyczka: nazwa, zdanie o stanie (`running(3)`)
 * i ścieżki plików, z których projekt powstał. Ścieżka jest tu najważniejsza
 * i nie dla ozdoby: to **ona pozwala położyć projekt bez pytania użytkownika,
 * gdzie leży jego plik** — a bez niej `down` na projekcie z listy wymagałby
 * wpisania ścieżki, którą aplikacja przed chwilą przeczytała.
 *
 * Pliku compose ten obiekt **nie czyta i nie sprawdza**, czy nadal istnieje.
 * Sprawdzenie w chwili pokazania byłoby odczytem dysku w rysowaniu klatki,
 * a odpowiedź i tak zdążyłaby się zestarzeć.
 */
final readonly class ComposeProject
{
    public function __construct(
        public string $name,
        /** Zdanie wtyczki o stanie projektu, np. `running(3)`; puste — nie podała. */
        public string $status = '',
        /** Ścieżka pliku, z którego projekt powstał; `null` — wtyczka jej nie podała. */
        public ?string $configPath = null,
    ) {
    }

    public function isRunning(): bool
    {
        return str_starts_with($this->status, 'running');
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }
}
