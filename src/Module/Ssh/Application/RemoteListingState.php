<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Module\Ssh\Domain\Aggregate\RemoteDirectory;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Stan odczytu zdalnego katalogu w tej chwili — **dana oglądana co klatkę, nie
 * proces** (krok 49).
 *
 * Druga część wzorca pracy kawałkowej (D46), ta sama, którą niosą
 * `ChecksumState`, `BackgroundState` i `SessionState`: ekran pyta o to co klatkę
 * i rysuje, co zastanie. Niczego nie uruchamia i nie zawiera ani jednego uchwytu
 * do procesu — od tego jest port.
 *
 * **Ścieżkę niesie także wtedy, gdy odczyt trwa**, i to nie jest ozdoba: górny
 * pas klatki ma pokazać, dokąd użytkownik właśnie wszedł, zanim serwer
 * odpowie. Bez tego wejście w katalog wyglądałoby przez pół sekundy jak brak
 * reakcji.
 *
 * **Powód niepowodzenia jest kluczem katalogu, nie zdaniem** — port nie rzuca
 * przez granicę (reguła 8), bo odczyt nie udaje się **rutynowo**: sesja bywa
 * zerwana, katalog bywa bez prawa wejścia i nie jest to awaria aplikacji.
 */
final readonly class RemoteListingState
{
    /**
     * @param array<string, string|int|float> $problemParameters
     */
    private function __construct(
        public ListingStage $stage,
        public ?RemotePath $path = null,
        public ?RemoteDirectory $directory = null,
        public ?string $problemKey = null,
        public array $problemParameters = [],
    ) {
    }

    public static function idle(): self
    {
        return new self(ListingStage::Idle);
    }

    /** Ścieżka bywa tu `null` — przy odczycie katalogu domowego nie znamy jej, dopóki serwer nie powie. */
    public static function listing(?RemotePath $path): self
    {
        return new self(ListingStage::Listing, $path);
    }

    public static function ready(RemoteDirectory $directory): self
    {
        return new self(ListingStage::Ready, $directory->path, $directory);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(?RemotePath $path, string $problemKey, array $parameters = []): self
    {
        return new self(ListingStage::Failed, $path, null, $problemKey, $parameters);
    }

    public function isWorking(): bool
    {
        return $this->stage->isWorking();
    }

    public function isReady(): bool
    {
        return $this->stage === ListingStage::Ready;
    }
}
