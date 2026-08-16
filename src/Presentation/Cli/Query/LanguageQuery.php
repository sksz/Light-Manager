<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Language;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Infrastructure\I18n\TranslatorService;

/**
 * `core.language` — język, w którym aplikacja mówi, i to, co ustawiono.
 *
 * Dwie różne rzeczy w jednym wierszu, bo różnią się przy `auto`: ustawiony jest
 * wtedy `auto`, a mówi się wybranym ze środowiska. Moduł składający własny napis
 * poza katalogiem (a takich miejsc być nie powinno) i test sprawdzający katalogi
 * pytają o **czynny**, nie o zapisany.
 */
final class LanguageQuery implements QueryInterface
{
    private readonly Generation $generation;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return 'core.language';
    }

    public function descriptionKey(): string
    {
        return 'query.core.language';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->generation->of($this->translator->active()->value);
    }

    public function ask(CommandInput $input): QueryResult
    {
        $active = $this->translator->active();

        return QueryResult::lazy(static function () use ($active): array {
            $rows = [];

            foreach (Language::cases() as $language) {
                $rows[] = [
                    'code' => $language->value,
                    'active' => $language === $active,
                ];
            }

            return $rows;
        });
    }
}
