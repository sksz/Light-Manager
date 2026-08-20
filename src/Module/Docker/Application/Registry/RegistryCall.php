<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Uchwyt jednej rozmowy z rejestrem — bliźniak `DockerCall` (krok 61, etap 2).
 *
 * Jest **liczbą, a nie obiektem stanu**, i to jest zamierzone: stan mieszka
 * w usłudze, a wołający ma go **czytać**, nie trzymać. Ta sama reguła, którą
 * krok 25 zapisał dla pracy kawałkowej — port mówi o pracy, nie o wyniku.
 */
final readonly class RegistryCall
{
    public function __construct(
        public int $id,
    ) {
    }
}
