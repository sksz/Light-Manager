<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\Container;

/**
 * To, co widać na liście kontenerów w tej chwili — migawka (krok 54).
 *
 * Bliźniak `ImageView` i powstał z tego samego powodu; szczegóły stoją tam.
 * Różni się jedną rzeczą: niesie **zawężenie do projektu compose**, bo lista
 * kontenerów jest jedyną w tym module, którą da się zawęzić — a kwerenda
 * odpowiada o tym, co panel pokazuje, nie o tym, co demon zna.
 */
final readonly class ContainerView
{
    /**
     * @param list<Container> $entries  kontenery **po zawężeniu**, w kolejności pokazywania
     * @param list<string>    $projects nazwy projektów obecnych na pełnej liście
     */
    public function __construct(
        public array $entries,
        public int $cursor,
        public bool $loaded,
        public ?string $project = null,
        public array $projects = [],
        public ?string $problemKey = null,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self([], 0, false);
    }

    public function selected(): ?Container
    {
        return $this->entries[$this->cursor] ?? null;
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
