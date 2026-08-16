<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Query\QueryResult;
use LightManager\Presentation\Cli\LoopState;

/**
 * `core.context` — gdzie użytkownik stoi i co ma zaznaczone.
 *
 * **Kwerenda powstała z odwołania zastrzeżenia** (krok 53, D92 nr 8). Do tego
 * dnia obowiązywało zdanie z D86: „kontekst mówi, gdzie użytkownik stoi;
 * kwerenda mówi, co u mnie jest” — czyli kontekstu nie wolno było powtórzyć
 * kwerendą, bo rdzeń rozdaje go co klatkę za darmo i powstałyby **dwie drogi do
 * jednej danej**. Rozstrzygnięcie „rejestr jedyną drogą odczytu” tę drugą drogę
 * zniosło: skoro wszystko czyta się rejestrem, kontekst przestaje być wyjątkiem
 * od kanału i staje się jednym z jego źródeł.
 *
 * Wartości nieznane wydawcy oddają **`-1`, nie zero** — bo zero jest poprawnym
 * rozmiarem pustego pliku i poprawnym czasem, a odbiorca musi mieć jak odróżnić
 * „nie wiem” od „tyle”. Ta sama zasada, dla której pola `ModuleContext` są
 * `null`-owalne; wiersz danych pierwotnych `null`-a nie unosi.
 */
final class ContextQuery implements QueryInterface
{
    /** Wartość, którą wydawca oznajmia „nie wiem” — zero jest poprawną odpowiedzią. */
    private const UNKNOWN = -1;

    private readonly Generation $generation;

    public function __construct(
        private readonly LoopState $state,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return 'core.context';
    }

    public function descriptionKey(): string
    {
        return 'query.core.context';
    }

    public function arguments(): array
    {
        return [];
    }

    /** Kontekst jest wymieniany przy publikacji, więc wystarczy jego tożsamość. */
    public function generation(): int
    {
        return $this->generation->of($this->state->context());
    }

    public function ask(CommandInput $input): QueryResult
    {
        $context = $this->state->context();

        return QueryResult::owned(
            QueryRegistry::CORE,
            $context,
            static fn (): array => [self::describe($context)],
        );
    }

    /** @return array<string, string|int|bool> */
    private static function describe(ModuleContext $context): array
    {
        return [
            'path' => $context->path,
            'selection' => $context->selection ?? '',
            'kind' => strtolower($context->kind->name),
            'origin' => strtolower($context->origin->name),
            'originLabel' => $context->originLabel,
            'markedCount' => $context->markedCount,
            'markedBytes' => $context->markedBytes,
            'markedDirectories' => $context->markedDirectories,
            'selectionBytes' => $context->selectionBytes ?? self::UNKNOWN,
            'selectionModifiedAt' => $context->selectionModifiedAt ?? self::UNKNOWN,
            'selectionPermissions' => $context->selectionPermissions ?? self::UNKNOWN,
        ];
    }
}
