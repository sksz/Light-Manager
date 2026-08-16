<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\PushProgress;
use LightManager\Module\Docker\Application\PushWork;

/**
 * `docker.push` — stan wypychania obrazu do rejestru (krok 54).
 *
 * Piąta kwerenda modułu i **jedyna, której plan kroku nie przewidywał**: weszła
 * razem z `docker push`, którego do rozstrzygnięcia D94 nr 1 w module nie było.
 * Jest zarazem czwartym etapem czynności `k8s.deploy-image` widzianym od strony
 * pytającego — tamten moduł zamawia wypchnięcie komendą, a stąd czyta, czy już.
 *
 * Reguła D92 nr 1 („kwerendę dostaje wszystko, co da się przeczytać") jest tu
 * spełniona nie z obowiązku, tylko z potrzeby: bez tej kwerendy czynność
 * przechodząca przez dwa moduły nie miałaby jak się dowiedzieć, że jej środkowy
 * etap dobiegł końca.
 *
 * Ulotna, bo zdanie o postępie zmienia się w każdym takcie.
 */
final class PushQuery implements QueryInterface
{
    public function __construct(
        private readonly PushWork $work,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.push';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.push';
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
        $progress = new PushProgress(
            $this->work->stage(),
            $this->work->target(),
            $this->work->note(),
            $this->work->problemKey(),
            $this->work->problemParameters(),
        );

        return QueryResult::owned(DockerSettings::ID, $progress, static fn (): array => [[
            'stage' => strtolower($progress->stage->name),
            'target' => $progress->target->value ?? '',
            'note' => $progress->note,
            'working' => $progress->isWorking(),
            'done' => $progress->isDone(),
            'problem' => $progress->problemKey ?? '',
            // **Powód idzie osobnym polem, jako gotowy napis** — i to jest
            // poprawka z klatki, nie z projektu. Wiersz niesie `problem` jako
            // **klucz katalogu**, a obcy moduł nie ma jak go przetłumaczyć:
            // parametrów cudzego klucza nie zna i znać nie ma prawa, więc
            // `translate('module.docker.push.rejected')` wypisywało użytkownikowi
            // surowe `{reason}`. Zdanie rejestru jest daną, nie tekstem
            // interfejsu, więc wolno je podać wprost (reguła 7 mówi o napisach
            // aplikacji, a nie o tym, co powiedział cudzy serwer).
            'reason' => self::reasonOf($progress->problemParameters),
        ]]);
    }

    /**
     * Powód wyjęty z parametrów — pusty napis, gdy praca się udała.
     *
     * @param array<string, string|int|float> $parameters
     */
    private static function reasonOf(array $parameters): string
    {
        foreach (['reason', 'target', 'source'] as $field) {
            if (isset($parameters[$field]) && $parameters[$field] !== '') {
                return (string) $parameters[$field];
            }
        }

        return '';
    }
}
