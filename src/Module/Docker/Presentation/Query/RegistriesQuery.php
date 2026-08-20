<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Registries;
use LightManager\Module\Docker\Domain\ValueObject\ImageRegistry;

/**
 * `docker.registries` — spis rejestrów obrazów (krok 61, etap 1).
 *
 * **Tokenu w wierszach nie ma i nie będzie.** Stoi w ich miejsce wartość
 * logiczna „poświadczenie ustawione", dokładnie tą samą granicą, którą
 * `ssh.hosts` trzyma dla odcisku klucza, a `docker.environments` dla ścieżek
 * TLS (11w). Kto potrzebuje treści — a od kroku 61 potrzebuje jej moduł
 * Kubernetesa, żeby założyć sekret — pyta **osobnej kwerendy**, i to jest
 * rozdział zamierzony: spis ogląda się często, poświadczenie bierze się raz.
 *
 * **Przegrodą to nie jest i krok 61 mówi to wprost** (D107 nr 1): rejestr
 * kwerend nie zna wołającego, a `address-book.value` odda ten sam token
 * każdemu, kto o niego zapyta. Rozdział broni więc przed **przypadkowym**
 * wyciekiem do cudzej tabeli, a nie przed odczytem — i tylko tak należy go
 * czytać.
 *
 * **Czyta koordynatora, a nie książkę** — i to nie jest wybór stylu, tylko
 * reguła 11w: *kwerenda nie woła kwerendy*. Rejestry mieszkają u obcego, więc
 * pytanie zadane stąd padłoby w trakcie odpowiadania, a strażnik
 * `QueryRegistry` oddaje wtedy pustkę **po cichu**. Wpisy podaje takt modułu.
 */
final class RegistriesQuery implements QueryInterface
{
    public function __construct(
        private readonly Registries $registries,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.registries';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.registries';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->registries->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = $this->registries->view();

        return QueryResult::owned(DockerSettings::ID, $view, static function () use ($view): array {
            $rows = [];

            foreach ($view->registries as $registry) {
                $rows[] = self::rowOf($registry);
            }

            return $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function rowOf(ImageRegistry $registry): array
    {
        return [
            'id' => $registry->id,
            'name' => $registry->name === '' ? $registry->address : $registry->name,
            'address' => $registry->address,
            'user' => $registry->user,
            'default' => $registry->isDefault,
            'insecure' => $registry->insecure,
            // **Czy**, nigdy jaki — patrz opis klasy.
            'credentials' => $registry->hasToken,
        ];
    }
}
