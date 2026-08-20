<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Migawka widoku zawartości rejestru — ładunek kwerendy `docker.catalog`
 * (krok 61, etap 2).
 *
 * Niesie **etap w każdej odpowiedzi**, a nie tylko wiersze, i jest to reguła
 * 11w zastosowana wprost: „czytam”, „nie ma nic” i „nikt jeszcze nie pytał”
 * nie mają prawa wyglądać dla obcego identycznie.
 */
final readonly class RegistryContentView
{
    /** @param list<string> $rows */
    private function __construct(
        public RegistryStage $stage,
        public RegistryMode $mode,
        public array $rows,
        /** Obraz, którego etykiety widać — pusty poza trybem `Tags`. */
        public string $image,
        /** Nazwa rejestru, którego to zawartość — pusta, gdy żadnego nie wybrano. */
        public string $registry,
        public ?string $problemKey,
    ) {
    }

    public static function empty(): self
    {
        return new self(RegistryStage::Idle, RegistryMode::Catalog, [], '', '', null);
    }

    /** @param list<string> $rows */
    public static function of(
        RegistryStage $stage,
        RegistryMode $mode,
        array $rows,
        string $image,
        string $registry,
        ?string $problemKey = null,
    ): self {
        return new self($stage, $mode, $rows, $image, $registry, $problemKey);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
