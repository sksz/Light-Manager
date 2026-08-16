<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\BuildProgress;
use LightManager\Module\Docker\Application\BuildWork;
use LightManager\Module\Docker\Application\DockerSettings;

/**
 * `docker.build` — stan budowy: etap, znacznik, ostatni komunikat demona.
 *
 * **To jest druga połowa odpowiedzi na pytanie Fazy XVIII**, po `docker.images`.
 * Budowa trwa minutami, więc moduł, który ją zamówił, nie ma jak czekać
 * w klatce — dowiaduje się o końcu **zdarzeniem** (`docker.build.finished`
 * albo `.failed`), a po wynik sięga **tutaj**. Stąd reguła kroku 54: *komenda
 * robi, zdarzenie ogłasza, kwerenda mówi co wyszło.*
 *
 * Pole `imageId` jest w tej parze najważniejsze i dlatego stoi w wierszu: po
 * udanej budowie wołający musi wiedzieć **co** zbudował, a nie tylko **że**
 * zbudował. Znacznik (`tag`) jest przy tym tym, co da się wdrożyć, a skrót
 * treści — tym, co da się sprawdzić.
 *
 * Ulotna: postęp pakowania zmienia się w każdym takcie.
 */
final class BuildQuery implements QueryInterface
{
    public function __construct(
        private readonly BuildWork $work,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.build';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.build';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $progress = new BuildProgress(
            $this->work->stage(),
            $this->work->tag(),
            $this->work->directory(),
            $this->work->imageId(),
            $this->work->note(),
            $this->work->fraction(),
            $this->work->problemKey(),
            $this->work->problemParameters(),
        );
        $fraction = $progress->fraction;

        return QueryResult::owned(DockerSettings::ID, $progress, static fn (): array => [[
            'stage' => strtolower($progress->stage->name),
            'tag' => $progress->tag->value ?? '',
            'directory' => $progress->directory,
            'imageId' => $progress->imageId ?? '',
            'note' => $progress->note,
            // Ułamek jako liczba całkowita procent, bo wiersz kwerendy niesie dane
            // pierwotne, a `float` w tablicy wyniku nie ma jak się w nich znaleźć
            // (`string|int|bool`). Przy budowie ułamka nie ma i wtedy jest to −1,
            // a nie zero: zero znaczyłoby „nic jeszcze nie zrobiono".
            'percent' => $fraction === null ? -1 : (int) round($fraction * 100),
            'working' => $progress->isWorking(),
            'problem' => $progress->problemKey ?? '',
        ]]);
    }
}
