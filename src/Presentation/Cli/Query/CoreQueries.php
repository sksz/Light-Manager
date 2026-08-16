<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Port\ThemePort;
use LightManager\Application\Port\ViewportPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Presentation\Cli\LoopState;

/**
 * **Spis źródeł danych rdzenia** — jedno miejsce, w którym są wyliczone
 * (krok 53).
 *
 * Wyliczenie stoi tutaj, a nie w `Bootstrapie`, z tego samego powodu, dla
 * którego `AppEvent::declarations()` stoi przy enumie zdarzeń: spis czytają
 * **dwa** miejsca — aplikacja i zestaw testowy — a dwa wyliczenia rozjechałyby
 * się przy pierwszej dołożonej kwerendzie, i to w sposób niewidoczny (test
 * przechodziłby na spisie starszym o jedną pozycję).
 *
 * Dwie usługi bierze się tu z Singletonów, a nie z argumentów, i jest to
 * świadome: tłumacz i port pracy tłowej są w tej aplikacji jedyne, a ich
 * przekazywanie przez trzy warstwy wywołań tylko po to, żeby stanęły w tej
 * liście, byłoby ceremonią bez odbiorcy.
 */
final class CoreQueries
{
    private function __construct()
    {
    }

    /** @return list<QueryInterface> */
    public static function all(
        LoopState $state,
        ModuleRegistry $modules,
        CommandRegistry $commands,
        ThemePort $themes,
        ViewportPort $viewport,
        RendererMode $mode,
        string $version,
    ): array {
        return [
            new SettingsQuery($state),
            new ModuleSettingsQuery($state, $modules),
            new ModulesQuery($modules),
            new CommandsQuery($commands),
            new QueriesQuery($state->queries()),
            new EventsQuery($state->events()),
            new JobsQuery(BackgroundProcessService::getInstance()),
            new ViewportQuery($viewport, $mode),
            new ThemeQuery($state, $themes),
            new LanguageQuery(TranslatorService::getInstance()),
            new VersionQuery($version),
            new StatusQuery($state),
            new ContextQuery($state),
        ];
    }
}
