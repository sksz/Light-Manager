<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterPlace;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;

/**
 * Gdzie pytamy i jak długo czekamy — **jedno miejsce na cztery odpowiedzi**
 * (krok 52; miejsce urosło o drugą współrzędną w kroku 59).
 *
 * Miejsce, przestrzeń nazw i limit czasu potrzebne są przy **każdym** wywołaniu
 * `kubectl`, a wywołania składa pięć różnych stanów modułu. Bez wspólnego
 * miejsca każdy z nich musiałby dostać te wartości w konstruktorze
 * i pilnować, żeby nie pracować na wartości sprzed zmiany miejsca — a to
 * właśnie jest ten rodzaj rozjazdu, którego nie widać, dopóki ktoś nie zapyta
 * o pody z niewłaściwego klastra.
 *
 * Sesja jest **zmienna w miejscu** i to jest różnica wobec obiektów wartości
 * modułu: te opisują nazwy, ta opisuje **wybór użytkownika, który się zmienia**.
 * Zmiana miejsca unieważnia przy tym wszystko, co z klastra przyszło — i to
 * jest cała jej treść poza przechowywaniem.
 *
 * **Tożsamością miejsca jest nazwa wpisu książki, nie nazwa kontekstu**
 * (krok 59, D96 nr 4): `default` w dwóch plikach to dwa różne klastry, a klucze
 * czterech klas stanu biorą się odtąd stąd — z `key()`, a nie z nazwy
 * kontekstu. Pokolenie zmienia się przy zmianie **którejkolwiek** współrzędnej.
 *
 * Jeden klaster naraz, wzorem „jednej sesji” z kroku 48 — plan wyklucza wielu
 * klastrów naraz wprost.
 */
final class ClusterSession
{
    private ?ClusterPlace $place = null;

    /** Nazwa wpisu książki — tożsamość miejsca, klucz stanu i pamięci podręcznej. */
    private string $entryName = '';

    private ?NamespaceName $namespace = null;

    private int $timeoutSeconds = KubernetesSettings::DEFAULT_TIMEOUT;

    /** Limit własny wpisu; `null` znaczy „ten z ustawień modułu". */
    private ?int $entryTimeout = null;

    /**
     * Ile razy zmieniono miejsce.
     *
     * Licznik, a nie znacznik, bo pytających jest kilku i każdy musi poznać
     * zmianę **niezależnie od pozostałych** — znacznik zdejmowany przez
     * pierwszego, który zajrzał, zgubiłby ją dla reszty. Ta sama sztuczka, co
     * przy `useContext()` w klasach stanu rdzenia: zmiana miejsca zaczyna
     * oglądanie od początku.
     */
    private int $generation = 0;

    public function place(): ?ClusterPlace
    {
        return $this->place;
    }

    public function context(): ?ContextName
    {
        return $this->place?->context;
    }

    /**
     * Tożsamość miejsca — **nazwa wpisu**, a w jej braku odcisk współrzędnych.
     *
     * Odcisk zamiast pustego napisu, bo miejsce wybrane zanim książka cokolwiek
     * o nim wie (pierwsze uruchomienie, kontekst czytany z pliku) też musi mieć
     * własny klucz stanu — inaczej dwa takie miejsca dzieliłyby drzewo.
     */
    public function key(): string
    {
        if ($this->entryName !== '') {
            return $this->entryName;
        }

        return $this->place?->fingerprint() ?? '';
    }

    public function namespace(): ?NamespaceName
    {
        return $this->namespace;
    }

    /** Limit obowiązujący teraz: własny wpisu, a w jego braku — z ustawień modułu. */
    public function timeoutSeconds(): int
    {
        return $this->entryTimeout ?? $this->timeoutSeconds;
    }

    /** Numer pokolenia — kto go zapamiętał, pozna po nim, że miejsce się zmieniło. */
    public function generation(): int
    {
        return $this->generation;
    }

    /**
     * Przestawia się na miejsce wskazane wpisem książki albo kontekstem
     * czytanym z pliku.
     *
     * Nazwa wpisu wchodzi razem z miejscem, bo to ona jest tożsamością:
     * przełączenie na inny wpis o tych samych współrzędnych **też** jest
     * zmianą miejsca — użytkownik prowadzi dwa wpisy właśnie po to, żeby stały
     * osobno.
     */
    public function usePlace(?ClusterPlace $place, string $entryName = '', ?int $entryTimeout = null): void
    {
        if ($this->isAt($place, $entryName, $entryTimeout)) {
            return;
        }

        $this->place = $place;
        $this->entryName = $entryName;
        $this->entryTimeout = $entryTimeout;
        // Przestrzeń nazw należy do miejsca, więc przy jego zmianie przestaje
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

    /** Czy wiadomo, gdzie pytać — bez pełnego miejsca nie ma o co pytać klastra. */
    public function isTargeted(): bool
    {
        return $this->place?->context !== null;
    }

    private function isAt(?ClusterPlace $place, string $entryName, ?int $entryTimeout): bool
    {
        $samePlace = $place === null
            ? $this->place === null
            : $this->place !== null && $this->place->equals($place);

        return $samePlace && $entryName === $this->entryName && $entryTimeout === $this->entryTimeout;
    }
}
