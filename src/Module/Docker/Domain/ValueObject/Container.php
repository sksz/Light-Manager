<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

/**
 * Kontener widziany z listy (krok 51).
 *
 * Niesie dokładnie to, co pokazuje panel i co rozstrzyga o dostępnych
 * czynnościach — ani pola więcej. Demon oddaje na kontener trzynaście pól,
 * z czego sieci, montowania i konfiguracja hosta nie mają w tym kroku odbiorcy
 * (reguła 13), a wpisane „na wszelki wypadek” byłyby obietnicą, że aplikacja
 * coś z nimi robi.
 *
 * **Projekt compose jest tu polem zwykłym, a nie osobnym pojęciem**, i to jest
 * warte zapamiętania: przynależność kontenera do projektu przychodzi etykietą
 * `com.docker.compose.project` w tej samej odpowiedzi, co reszta listy. Zawężenie
 * listy do projektu nie kosztuje przez to ani jednego pytania więcej — a gdyby
 * projekt był osobnym bytem czytanym z `docker compose ps`, kosztowałoby proces
 * potomny na każde odświeżenie.
 */
final readonly class Container
{
    /**
     * @param list<string> $ports wypisy postaci `8080->80/tcp`, gotowe do pokazania
     */
    public function __construct(
        public ContainerId $id,
        public string $name,
        public ImageRef $image,
        public ContainerState $state,
        /** Zdanie demona o stanie („Up 3 hours”, „Exited (0) 5 weeks ago”) — do pokazania, nie do rozbioru. */
        public string $status,
        public array $ports = [],
        /** Czas utworzenia w sekundach epoki; `null` — demon go nie podał. */
        public ?int $createdAt = null,
        /** Projekt compose, do którego kontener należy; `null` — nie należy do żadnego. */
        public ?string $composeProject = null,
    ) {
    }

    public function isRunning(): bool
    {
        return $this->state->isRunning();
    }

    /** Czy kontener należy do wskazanego projektu compose. */
    public function belongsTo(string $project): bool
    {
        return $this->composeProject === $project;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
