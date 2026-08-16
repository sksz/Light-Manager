<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\Prefix;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ProvidesQueries;
use Throwable;

/**
 * Wszystkie źródła danych tego uruchomienia — i **jedyna droga, którą się je
 * czyta** (krok 53, D92 nr 3).
 *
 * Rejestr powtarza konstrukcję `CommandRegistry` co do joty: przestrzeń nazw
 * wymuszona (rdzeń wnosi `core.*`, moduł `<własne id>.*`), odrzucenie z powodem
 * jako dana, zbiór globalny i niezależny od tego, który ekran stoi na wierzchu.
 * Różni się **jedną rzeczą**, za to przesądzającą o wydajności całej aplikacji:
 * prowadzi **pamięć wyników**.
 *
 * **Routing z pamięcią pokoleń.** Kwerenda oddaje tani `generation()`, a rejestr
 * trzyma ostatnią odpowiedź pod kluczem `nazwa + argumenty`. Dopóki pokolenie się
 * nie zmieniło, odczyt kosztuje **jedno wyszukanie w tablicy** — a to jest cena,
 * którą trzeba było zapłacić, żeby „rejestr jedyną drogą odczytu" nie oznaczało
 * przeliczania stanu trzydzieści razy na sekundę dla każdego pytającego.
 *
 * **Kwerendy `VOLATILE` nie są pamiętane w ogóle** — i to jest poprawka, którą
 * wymusił test, a nie projekt. Pierwsza wersja pamiętała je **na jedną klatkę**,
 * co wyglądało na oszczędność, a było pułapką: odczyt **po zmianie w tej samej
 * klatce** oddawał stan sprzed niej, więc komenda przełączająca muzykę widziała
 * po pauzie, że nadal gra. Cena wycofania jest przy tym mała i wynika z leniwych
 * wierszy: `ask()` kwerendy ulotnej zbiera kilka pól i wraca, a budowa wierszy
 * — jedyna droga rzecz — i tak liczy się raz, bo pamięta ją sam `QueryResult`.
 *
 * Trzy reguły wykonane w `ask()`, żadna nie zostawiona dobrej woli wołającego:
 *
 * - **kwerenda nie rzuca** — wyjątek ginie tutaj i zamienia się w powód
 *   w wyniku, bo odczyt stoi w ścieżce rysowania klatki, a ta nie ma dokąd
 *   zgłosić cudzego kłopotu;
 * - **kwerenda nie woła kwerendy** — pytanie zadane w trakcie odpowiadania
 *   zostaje odmówione, wzorem „zdarzenie nie rodzi zdarzenia" (krok 46);
 * - **brak wykonawcy jest zwykłym stanem** — nieznana nazwa oddaje wynik
 *   z powodem, a nie `null` do obsłużenia w każdym miejscu z osobna.
 */
final class QueryRegistry
{
    /** Przestrzeń nazw kwerend rdzenia — ta sama, co w rejestrze komend. */
    public const CORE = 'core';

    /** @var array<string, QueryInterface> */
    private array $queries = [];

    /** @var list<QueryRejection> */
    private array $rejections = [];

    /** @var array<string, array{int, QueryResult}> klucz → [pokolenie, wynik] */
    private array $memory = [];

    /** Czy właśnie trwa odpowiadanie — strażnik reguły „kwerenda nie woła kwerendy". */
    private bool $asking = false;

    /**
     * Dokłada kwerendy jednego właściciela, odsiewając te, które wyszły poza jego
     * przestrzeń nazw albo powtarzają nazwę już zajętą.
     *
     * @param list<QueryInterface> $queries
     */
    public function add(string $owner, array $queries): void
    {
        $prefix = $owner . '.';

        foreach ($queries as $query) {
            $name = $query->name();

            if (!str_starts_with($name, $prefix) || $name === $prefix) {
                $this->rejections[] = new QueryRejection($owner, $name, 'query.rejected.namespace');

                continue;
            }

            if (isset($this->queries[$name])) {
                $this->rejections[] = new QueryRejection($owner, $name, 'query.rejected.duplicate');

                continue;
            }

            $this->queries[$name] = $query;
        }

        ksort($this->queries);
    }

    /**
     * Kwerendy modułów przyjętych — jedna linia
     * w `Bootstrapie`, wzorem `EventRegistry::useModules()`.
     *
     * Moduł wyłączony i odrzucony nie wnosi niczego, więc jego kwerenda nie stoi
     * ani w oknie, ani w rejestrze: spis jest **widokiem na rejestr**, a nie drugą
     * listą (wzorem menu z kroku 32).
     *
     * @param list<ModuleInterface> $modules
     */
    public function useModules(array $modules): void
    {
        foreach ($modules as $module) {
            if ($module instanceof ProvidesQueries) {
                $this->add($module->id(), $module->queries());
            }
        }
    }

    public function find(string $name): ?QueryInterface
    {
        return $this->queries[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->queries[$name]);
    }

    /**
     * Odpowiedź na pytanie — **jedyne wejście do danych w tej aplikacji**.
     *
     * Wołający nie sprawdza, czy jest kogo pytać: nieznana nazwa oddaje wynik
     * z powodem `query.problem.unknown`, bo moduł bywa wyłączony, odrzucony albo
     * nieobecny, a każdy pytający miałby wtedy powtarzać to samo rozgałęzienie.
     */
    public function ask(string $name, ?CommandInput $input = null): QueryResult
    {
        $query = $this->queries[$name] ?? null;

        if ($query === null) {
            return QueryResult::failed('query.problem.unknown');
        }

        if ($this->asking) {
            return QueryResult::failed('query.problem.nested');
        }

        $input ??= new CommandInput();
        $key = $name . "\0" . $input->signature();
        $generation = $query->generation();
        $remembered = $this->memory[$key] ?? null;

        if ($remembered !== null && $generation !== QueryInterface::VOLATILE && $remembered[0] === $generation) {
            return $remembered[1];
        }

        $this->asking = true;

        try {
            $result = $query->ask($input);
        } catch (Throwable) {
            // Kwerenda nie rzuca (reguła 8) — a gdy autor o tym zapomni, cena
            // nie może być klatką aplikacji. Powód idzie do wyniku, bo odczyt
            // stoi w ścieżce rysowania i nie ma dokąd zgłosić wyjątku.
            $result = QueryResult::failed('query.problem.failed');
        } finally {
            $this->asking = false;
        }

        if ($generation !== QueryInterface::VOLATILE) {
            $this->memory[$key] = [$generation, $result];
        }

        return $result;
    }

    /** @return list<QueryInterface> w kolejności alfabetycznej */
    public function all(): array
    {
        return array_values($this->queries);
    }

    /** @return list<QueryInterface> kwerendy, których nazwa zaczyna się od przedrostka */
    public function matching(string $prefix): array
    {
        if ($prefix === '') {
            return $this->all();
        }

        return array_values(array_filter(
            $this->queries,
            static fn (QueryInterface $query): bool => str_starts_with($query->name(), $prefix),
        ));
    }

    /** Najdłuższy wspólny przedrostek pasujących nazw — to, co dopisuje `Tab`. */
    public function commonPrefix(string $prefix): string
    {
        $matching = $this->matching($prefix);

        if ($matching === []) {
            return $prefix;
        }

        return Prefix::shared(array_map(
            static fn (QueryInterface $query): string => $query->name(),
            $matching,
        ));
    }

    /** @return list<QueryRejection> */
    public function rejections(): array
    {
        return $this->rejections;
    }
}
