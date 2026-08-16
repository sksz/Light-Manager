<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Czym skończyła się czynność — **zdanie do odebrania raz** (krok 51).
 *
 * Warstwa aplikacji nie mówi użytkownikowi nic i nie ogłasza zdarzeń: jedno
 * i drugie należy do `Presentation`, bo jedno idzie przez katalog napisów,
 * a drugie przez rejestr zdarzeń trzymany w stanie pętli. Ta klasa jest miejscem
 * spotkania: stan pracy zostawia tu wynik, a ekran zabiera go w najbliższej
 * klatce i zamienia na komunikat oraz zdarzenie.
 *
 * Odbiera się go **raz** i to jest cała reguła jego użycia — inaczej ten sam
 * komunikat pojawiałby się trzydzieści razy na sekundę.
 */
final readonly class ActionOutcome
{
    /** @param array<string, string|int|float> $problemParameters */
    private function __construct(
        public DockerAction $action,
        /** Nazwa kontenera albo obrazu — do wstawienia w zdanie dla użytkownika. */
        public string $subject,
        public bool $successful,
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function success(DockerAction $action, string $subject): self
    {
        return new self($action, $subject, true, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failure(DockerAction $action, string $subject, string $problemKey, array $parameters = []): self
    {
        return new self($action, $subject, false, $problemKey, $parameters);
    }
}
