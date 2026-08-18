<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Docker\Application\BuildProgress;
use LightManager\Module\Docker\Application\ComposeState;
use LightManager\Module\Docker\Application\ContainerView;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\EnvironmentBookView;
use LightManager\Module\Docker\Application\ImageView;

/**
 * Odczyt danych modułu Dockera — **przez rejestr kwerend, jak każdy inny**
 * (krok 53, D92 nr 3; ten moduł dostał go w kroku 54).
 *
 * Piąta fasada modułowa. Wzorzec jest ten sam, co w czterech poprzednich:
 * `payloadFor()` pada **w jednym miejscu**, bo oddaje `?object`, a bez fasady
 * każde miejsce odczytu powtarzałoby `instanceof`.
 *
 * **Wszystkie cztery ładunki są migawkami, a nie obiektami roboczymi**, i to jest
 * rozstrzygnięcie warte zapisania. Fasada oddająca `ImageList` musiałaby oddawać
 * go jako `?ImageList`, bo przy module wyłączonym nie ma czego oddać — a wtedy
 * **każde** miejsce odczytu powtarzałoby obsługę `null`a. Migawka ma zawsze
 * postać pustą, więc pytający pisze jedną linię zamiast dwóch. Ta sama zasada,
 * którą reguła 15g stosuje do samego `ask()`.
 */
final readonly class DockerQueries
{
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function images(): ImageView
    {
        $payload = $this->ask('images');

        return $payload instanceof ImageView ? $payload : ImageView::empty();
    }

    public function containers(): ContainerView
    {
        $payload = $this->ask('containers');

        return $payload instanceof ContainerView ? $payload : ContainerView::empty();
    }

    public function compose(): ComposeState
    {
        $payload = $this->ask('compose');

        return $payload instanceof ComposeState ? $payload : ComposeState::idle();
    }

    public function build(): BuildProgress
    {
        $payload = $this->ask('build');

        return $payload instanceof BuildProgress ? $payload : BuildProgress::empty();
    }

    public function environments(): EnvironmentBookView
    {
        $payload = $this->ask('environments');

        return $payload instanceof EnvironmentBookView ? $payload : EnvironmentBookView::empty();
    }

    private function ask(string $name): ?object
    {
        return $this->queries->ask(DockerSettings::ID . '.' . $name)->payloadFor(DockerSettings::ID);
    }
}
