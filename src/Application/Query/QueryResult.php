<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

use Closure;

/**
 * Odpowiedź kwerendy: wiersze danych pierwotnych dla obcych, ładunek typowany
 * dla właściciela, ewentualnie powód, dla którego odpowiedzi nie ma.
 *
 * **Dwa oblicza, a nie dwa kanały** (krok 53, D92 nr 4). Rejestr jest jedyną
 * drogą odczytu — także dla właściciela danych — a właściciel nie ma powodu
 * rysować z tablic napisów tego, co zna jako obiekt. Wiersze widzi więc każdy,
 * ładunek wyłącznie ten, kto się pod kwerendą podpisał: `payloadFor()` porównuje
 * nazwę właściciela i cudzemu oddaje `null`. Reguła 15 zostaje przez to
 * nietknięta — moduł nadal nie widzi typu cudzego modułu.
 *
 * **Wiersze powstają leniwie** i to jest cała odpowiedź na polecenie „wydajny
 * routing" (D92 nr 3): właściciel czytający ładunek nie płaci za budowę pięciu
 * tysięcy tablic, których nikt nie obejrzy, a okno kwerend pytające o wiersze
 * płaci za nie **raz na pokolenie**, bo wynik zostaje w pamięci rejestru.
 *
 * Klasa jest przez to `final`, ale **nie `readonly`** — jedyne odstępstwo od
 * reguły 6 w tym kroku, świadome i nazwane w D92: obiekt pamięta zbudowane
 * wiersze. Z zewnątrz jest niezmienny (`rows()` zawsze oddaje to samo), więc
 * odstępstwo dotyczy techniki, nie zachowania.
 */
final class QueryResult
{
    /** @var ?list<array<string, string|int|bool>> zbudowane wiersze albo `null`, dopóki nikt nie pytał */
    private ?array $rows;

    /**
     * @param ?Closure(): list<array<string, string|int|bool>> $builder
     * @param ?list<array<string, string|int|bool>>            $rows
     */
    private function __construct(
        ?array $rows,
        private readonly ?Closure $builder,
        /** Klucz katalogu napisów z powodem; `null` — odpowiedź jest. */
        public readonly ?string $problem = null,
        /** Identyfikator właściciela, któremu wolno odebrać ładunek. */
        private readonly ?string $owner = null,
        private readonly ?object $payload = null,
    ) {
        $this->rows = $rows;
    }

    /** @param list<array<string, string|int|bool>> $rows */
    public static function of(array $rows): self
    {
        return new self($rows, null);
    }

    /** @param Closure(): list<array<string, string|int|bool>> $rows */
    public static function lazy(Closure $rows): self
    {
        return new self(null, $rows);
    }

    /**
     * Wynik z ładunkiem typowanym — dla źródeł, których właściciel czyta
     * obiektem, a obcy wierszami.
     *
     * @param Closure(): list<array<string, string|int|bool>> $rows
     */
    public static function owned(string $owner, object $payload, Closure $rows): self
    {
        return new self(null, $rows, null, $owner, $payload);
    }

    /** Jeden wiersz o jednym polu — odpowiedź na pytanie o pojedynczą wartość. */
    public static function value(string $field, string|int|bool $value): self
    {
        return new self([[$field => $value]], null);
    }

    public static function empty(): self
    {
        return new self([], null);
    }

    /** Odpowiedzi nie ma i to jest zwykły stan, nie wyjątek (reguła 8). */
    public static function failed(string $problemKey): self
    {
        return new self([], null, $problemKey);
    }

    /** @return list<array<string, string|int|bool>> */
    public function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return $this->rows = $this->builder === null ? [] : ($this->builder)();
    }

    /** @return ?array<string, string|int|bool> */
    public function first(): ?array
    {
        return $this->rows()[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->rows() === [];
    }

    public function hasProblem(): bool
    {
        return $this->problem !== null;
    }

    /**
     * Ładunek typowany — **wyłącznie dla właściciela**.
     *
     * Sprawdzenie w czasie działania, a nie sama umowa, bo cena pomyłki jest tu
     * asymetryczna: przeoczony odczyt cudzego ładunku byłby złamaniem reguły 15
     * niewidocznym w żadnym teście modułu, a jedno porównanie napisów kosztuje
     * tyle, co nic.
     */
    public function payloadFor(string $owner): ?object
    {
        return $this->owner === $owner ? $this->payload : null;
    }
}
