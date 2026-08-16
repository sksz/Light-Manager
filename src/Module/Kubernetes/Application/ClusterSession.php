<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;

/**
 * Gdzie pytamy i jak długo czekamy — **jedno miejsce na trzy odpowiedzi**
 * (krok 52).
 *
 * Kontekst, przestrzeń nazw i limit czasu potrzebne są przy **każdym** wywołaniu
 * `kubectl`, a wywołania składa pięć różnych stanów modułu. Bez wspólnego
 * miejsca każdy z nich musiałby dostać te trzy wartości w konstruktorze
 * i pilnować, żeby nie pracować na wartości sprzed zmiany kontekstu — a to
 * właśnie jest ten rodzaj rozjazdu, którego nie widać, dopóki ktoś nie zapyta
 * o pody z niewłaściwego klastra.
 *
 * Sesja jest **zmienna w miejscu** i to jest różnica wobec obiektów wartości
 * modułu: te opisują nazwy, ta opisuje **wybór użytkownika, który się zmienia**.
 * Zmiana kontekstu unieważnia przy tym wszystko, co z klastra przyszło — i to
 * jest cała jej treść poza przechowywaniem.
 *
 * Jeden klaster naraz, wzorem „jednej sesji” z kroku 48 — plan wyklucza wielu
 * klastrów naraz wprost.
 */
final class ClusterSession
{
    private ?ContextName $context = null;

    private ?NamespaceName $namespace = null;

    private int $timeoutSeconds = KubernetesSettings::DEFAULT_TIMEOUT;

    /**
     * Ile razy zmieniono miejsce.
     *
     * Licznik, a nie znacznik, bo pytających jest kilku i każdy musi poznać
     * zmianę **niezależnie od pozostałych** — znacznik zdejmowany przez
     * pierwszego, który zajrzał, zgubiłby ją dla reszty. Ta sama sztuczka, co
     * przy `useContext()` w klasach stanu rdzenia: zmiana kontekstu zaczyna
     * oglądanie od początku.
     */
    private int $generation = 0;

    public function context(): ?ContextName
    {
        return $this->context;
    }

    public function namespace(): ?NamespaceName
    {
        return $this->namespace;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    /** Numer pokolenia — kto go zapamiętał, pozna po nim, że miejsce się zmieniło. */
    public function generation(): int
    {
        return $this->generation;
    }

    public function useContext(?ContextName $context): void
    {
        if ($context?->value === $this->context?->value) {
            return;
        }

        $this->context = $context;
        // Przestrzeń nazw należy do kontekstu, więc przy jego zmianie przestaje
        // cokolwiek znaczyć: `produkcja` w jednym klastrze i `produkcja`
        // w drugim to dwie różne przestrzenie, a ta sama nazwa w nowym klastrze
        // bywa nieobecna w ogóle.
        $this->namespace = null;
        ++$this->generation;
    }

    public function useNamespace(?NamespaceName $namespace): void
    {
        if ($namespace?->value === $this->namespace?->value) {
            return;
        }

        $this->namespace = $namespace;
        ++$this->generation;
    }

    /** Limit czasu idzie z ustawień modułu i zmienia się bez unieważniania czegokolwiek. */
    public function useTimeout(int $seconds): void
    {
        $this->timeoutSeconds = max(1, $seconds);
    }

    /** Czy wiadomo, gdzie pytać — bez kontekstu nie ma o co pytać klastra. */
    public function isTargeted(): bool
    {
        return $this->context !== null;
    }
}
