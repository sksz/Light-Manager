<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\ComposeProject;

/**
 * Stan pracy wtyczki compose — dana oglądana co klatkę (krok 51).
 *
 * Druga część wzorca pracy kawałkowej (D46) w tym module, obok `DockerResult`.
 * Różni się od niego jedną rzeczą: pod spodem stoi **proces potomny**, a nie
 * gniazdo, więc powodzenie i niepowodzenie rozstrzyga kod wyjścia wraz
 * z osobnym strumieniem błędów (reguła 15f — strumieni nie scalamy, bo wyjściem
 * `compose ls` jest **treść**).
 */
final readonly class ComposeState
{
    /**
     * @param list<ComposeProject>            $projects   wynik `ls`; pusta lista przy pozostałych czynnościach
     * @param array<string, string|int|float> $problemParameters
     */
    private function __construct(
        public ComposeStage $stage,
        public ?ComposeAction $action,
        public array $projects,
        /** Ostatni wiersz wypisu — to, co wtyczka mówi o sobie w tej chwili. */
        public string $note,
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(ComposeStage::Idle, null, [], '', null, []);
    }

    public static function working(ComposeAction $action, string $note = ''): self
    {
        return new self(ComposeStage::Working, $action, [], $note, null, []);
    }

    /** @param list<ComposeProject> $projects */
    public static function done(ComposeAction $action, array $projects = [], string $note = ''): self
    {
        return new self(ComposeStage::Done, $action, $projects, $note, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(ComposeAction $action, string $problemKey, array $parameters = []): self
    {
        return new self(ComposeStage::Failed, $action, [], '', $problemKey, $parameters);
    }

    public function isWorking(): bool
    {
        return $this->stage === ComposeStage::Working;
    }
}
