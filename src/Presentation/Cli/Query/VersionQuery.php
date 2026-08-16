<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;

/**
 * `core.version` — wersja aplikacji, wersja PHP i obecność rozszerzeń.
 *
 * Rozszerzenia są tu razem z wersją, bo odpowiadają na to samo pytanie: **czym
 * ta maszyna dysponuje**. Moduł, który degraduje się przy braku rozszerzenia
 * (dźwięk bez `ext-glfw`, Docker bez `ext-curl`), rozstrzyga to u siebie raz,
 * przy składaniu — ale powiedzieć o tym użytkownikowi umiało dotąd wyłącznie
 * okno pomocy.
 *
 * Pokolenie **stałe**: rozszerzenia nie doładowują się w trakcie działania,
 * a wersja aplikacji tym bardziej.
 */
final class VersionQuery implements QueryInterface
{
    /** Rozszerzenia, o które aplikacja naprawdę pyta — po jednym na tor i na moduł. */
    private const EXTENSIONS = ['imagick', 'glfw', 'curl', 'mbstring', 'pcntl', 'posix'];

    public function __construct(
        private readonly string $version,
    ) {
    }

    public function name(): string
    {
        return 'core.version';
    }

    public function descriptionKey(): string
    {
        return 'query.core.version';
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
        $version = $this->version;

        return QueryResult::lazy(static function () use ($version): array {
            $row = ['application' => $version, 'php' => PHP_VERSION];

            foreach (self::EXTENSIONS as $extension) {
                $row[$extension] = extension_loaded($extension);
            }

            return [$row];
        });
    }
}
