<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;

/**
 * `core.modules` — kto wszedł, kto odpadł i dlaczego.
 *
 * To jest odpowiedź na pytanie, które moduł pytający musi umieć sobie zadać
 * **przed** sięgnięciem po cudzą kwerendę: czy jest kogo pytać. Rejestr kwerend
 * odpowiada na brak wykonawcy powodem w wyniku, ale zdanie „modułu Dockera nie
 * ma, bo go wyłączono” da się złożyć wyłącznie stąd.
 *
 * Pokolenie jest **stałe**: rejestr modułów powstaje raz, przy składaniu
 * aplikacji, i przez całe uruchomienie się nie zmienia. Kwerenda o stałym
 * pokoleniu liczy się dokładnie raz — pierwszy pytający płaci, każdy następny
 * dostaje wynik z pamięci rejestru.
 */
final class ModulesQuery implements QueryInterface
{
    public function __construct(
        private readonly ModuleRegistry $modules,
    ) {
    }

    public function name(): string
    {
        return 'core.modules';
    }

    public function descriptionKey(): string
    {
        return 'query.core.modules';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return 0;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $modules = $this->modules;

        return QueryResult::lazy(static function () use ($modules): array {
            $accepted = [];

            foreach ($modules->accepted() as $module) {
                $accepted[$module->id()] = true;
            }

            $shortcuts = [];

            foreach ($modules->shortcuts() as $character => $module) {
                $shortcuts[$module->id()] = $character;
            }

            $rows = [];

            foreach ($modules->declared() as $module) {
                $rows[] = self::describe($module, $modules, isset($accepted[$module->id()]), $shortcuts);
            }

            return $rows;
        });
    }

    /**
     * @param array<string, string> $shortcuts
     *
     * @return array<string, string|int|bool>
     */
    private static function describe(
        ModuleInterface $module,
        ModuleRegistry $modules,
        bool $accepted,
        array $shortcuts,
    ): array {
        $id = $module->id();
        $rejection = $modules->rejectionOf($id);

        return [
            'id' => $id,
            'state' => match (true) {
                $accepted => 'accepted',
                !$modules->isEnabled($id) => 'disabled',
                default => 'rejected',
            },
            'shortcut' => $shortcuts[$id] ?? '',
            'reason' => $rejection === null ? '' : $rejection->reasonKey,
        ];
    }
}
