<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\ImageRegistry;

/**
 * Migawka spisu rejestrów — ładunek kwerendy `docker.registries` (krok 61).
 *
 * Istnieje z powodu zapisanego w regule 11w: **fasada oddaje migawkę, nie obiekt
 * roboczy**, bo obiekt roboczy musiałby być `null`owalny i rozsiewałby obsługę
 * braku po wszystkich odczytach. Rodzeństwo `EnvironmentBookView`, tylko krótsze
 * — rejestr nie ma stanu poza tym, co stoi we wpisie książki.
 *
 * **Książką to nie jest** (D104, D107): spis mieszka w rozdziale książki
 * adresowej, a ta klasa jest wyłącznie tym, co z niego zostało po przejściu
 * przez port kwerendy.
 */
final readonly class RegistryView
{
    /** @param list<ImageRegistry> $registries */
    private function __construct(
        public array $registries,
    ) {
    }

    /** @param list<ImageRegistry> $registries */
    public static function of(array $registries): self
    {
        return new self($registries);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->registries === [];
    }

    /** Rejestr o podanym identyfikatorze albo `null`. */
    public function byId(string $id): ?ImageRegistry
    {
        foreach ($this->registries as $registry) {
            if ($registry->id === $id) {
                return $registry;
            }
        }

        return null;
    }
}
