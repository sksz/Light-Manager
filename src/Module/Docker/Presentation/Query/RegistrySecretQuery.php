<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Registries;
use LightManager\Module\Docker\Infrastructure\RegistryAuth;
use LightManager\Module\Docker\Presentation\DockerQueries;

/**
 * `docker.registry-secret` — gotowa treść `.dockerconfigjson` dla wskazanego
 * rejestru (krok 61, etap 3).
 *
 * **Kwerenda oddaje materiał uwierzytelnienia i robi to świadomie** (D107 nr 1).
 * Plan kroku żądał pierwotnie, żeby token nie przechodził przez rejestr
 * kwerend — i przesłanka tego żądania **upadła wraz z krokiem 60**:
 * `address-book.value` oddaje pole rodzaju `secret` każdemu, kto zapyta, i mówi
 * to we własnej dokumentacji. Przegrody nie ma, więc choreografia z plikiem
 * `0600` wędrującym między modułami broniłaby czegoś, czego nie ma — a kosztem
 * byłaby nowa komenda, nowy plik i nowa ścieżka sprzątania.
 *
 * **Co zostało z tamtej ostrożności i obowiązuje nadal.** Kwerenda jest
 * **osobna** od `docker.registries`, żeby spis rejestrów — oglądany często —
 * nie niósł tokenu ani razu. Jest **`VOLATILE`**, więc odpowiedź nie leży
 * w pamięci rejestru kwerend do najbliższej zmiany książki; to ta sama reguła,
 * dla której `address-book.value` też jest `VOLATILE`. I odpowiada **wyłącznie
 * na pytanie o konkretny wpis**, a nie spisem — nie ma tu drogi, którą ktoś
 * zbierze wszystkie poświadczenia naraz.
 *
 * **Wierszem polecenia to nie idzie i nie pójdzie.** Zakaz jest twardy
 * i pochodzi z kroku 48: `ps` widzi wiersz polecenia, więc odbiorca zapisuje
 * treść do pliku o prawach `0600` i stosuje **plik**.
 *
 * Format składa moduł Dockera, bo `.dockerconfigjson` jest **pojęciem Dockera**
 * — moduł Kubernetesa dostaje napis i nie musi wiedzieć, co w nim stoi.
 */
final class RegistrySecretQuery implements QueryInterface
{
    public const REGISTRY = 'registry';

    public function __construct(
        private readonly Registries $registries,
        private readonly DockerQueries $reader,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.registry-secret';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.registrySecret';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::REGISTRY,
                'module.' . DockerSettings::ID . '.query.registrySecret.arg',
                required: false,
            ),
        ];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $id = $input->text(self::REGISTRY);
        $registry = $id === '' ? $this->registries->preferred() : null;

        foreach ($this->registries->all() as $candidate) {
            if ($candidate->id === $id || $candidate->name === $id || $candidate->address === $id) {
                $registry = $candidate;

                break;
            }
        }

        if ($registry === null) {
            return QueryResult::failed('module.' . DockerSettings::ID . '.registry.none');
        }

        $content = RegistryAuth::dockerConfigJson(
            $registry->address,
            $registry->user,
            $this->reader->registryToken($registry->id),
        );

        if ($content === '') {
            return QueryResult::failed('module.' . DockerSettings::ID . '.registry.noCredentials');
        }

        return QueryResult::of([[
            'registry' => $registry->address,
            'name' => $registry->name === '' ? $registry->address : $registry->name,
            'dockerconfigjson' => $content,
        ]]);
    }
}
