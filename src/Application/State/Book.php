<?php

declare(strict_types=1);

namespace LightManager\Application\State;

/**
 * Książka wpisów — porządek i tożsamość, nic więcej (krok 59, D103).
 *
 * Wzorzec książki stanął w projekcie trzy razy (`HostBook`, `EnvironmentBook`,
 * a krok 59 przynosił czwartą — `ClusterBook`), więc przegląd z reguły 15e
 * musiał paść i padł: użytkownik rozstrzygnął **wspólną książkę w rdzeniu**.
 * Rdzeń zna o wpisie dokładnie dwie rzeczy: **nazwa własna jest tożsamością**
 * (dopisanie pod zajętą nazwą zastępuje wpis, zachowując jego miejsce)
 * i **kolejność jest kolejnością dopisywania**. Ładunek wpisu jest
 * nieprzezroczysty — typowanie i walidacja zostają w module, bo pola trzech
 * książek są rozłączne i rdzeń nie ma czego o nich wiedzieć (D42 zostaje
 * w mocy: rdzeń nadal nie wie, czym jest host, środowisko ani klaster).
 *
 * Kolekcja **mutowalna w miejscu**, jak jej modułowe poprzedniczki — opisuje
 * spis, który użytkownik prowadzi, a nie wartość. Obie gwarancje niesie tu
 * tablica asocjacyjna PHP sama z siebie: klucz zachowuje kolejność wstawienia,
 * a nadpisanie istniejącego klucza nie zmienia jego miejsca.
 */
final class Book
{
    /** @var array<string, mixed> ładunki pod nazwami, w kolejności dopisywania */
    private array $entries = [];

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->entries);
    }

    /** @return array<string, mixed> ładunki pod nazwami, w kolejności dopisywania */
    public function all(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->entries);
    }

    /** Ładunek wpisu — `null`, gdy wpisu nie ma (ładunek `null` nie istnieje, patrz `put()`). */
    public function find(string $name): mixed
    {
        return $this->entries[$name] ?? null;
    }

    /**
     * Dopisuje albo **zastępuje** wpis o tej samej nazwie, zachowując jego
     * miejsce.
     *
     * Pusta nazwa i ładunek `null` wypadają w ciszy: wpis bez tożsamości nie
     * jest wpisem, a `null` jest odpowiedzią `find()` na „nie ma takiego" —
     * przyjęcie go tutaj uczyniłoby tę odpowiedź dwuznaczną.
     */
    public function put(string $name, mixed $payload): void
    {
        if ($name === '' || $payload === null) {
            return;
        }

        $this->entries[$name] = $payload;
    }

    public function remove(string $name): bool
    {
        if (!array_key_exists($name, $this->entries)) {
            return false;
        }

        unset($this->entries[$name]);

        return true;
    }
}
