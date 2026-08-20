<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Docker\Application\BuildProgress;
use LightManager\Module\Docker\Application\ComposeState;
use LightManager\Module\Docker\Application\ContainerView;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\EnvironmentBookView;
use LightManager\Module\Docker\Application\ImageView;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\ImageRegistry;

/**
 * Odczyt danych modułu Dockera — **przez rejestr kwerend, jak każdy inny**
 * (krok 53, D92 nr 3; ten moduł dostał go w kroku 54).
 *
 * Piąta fasada modułowa. Wzorzec jest ten sam, co w czterech poprzednich:
 * `payloadFor()` pada **w jednym miejscu**, bo oddaje `?object`, a bez fasady
 * każde miejsce odczytu powtarzałoby `instanceof`.
 *
 * **Wszystkie cztery ładunki są migawkami, a nie obiektami roboczymi**, i to jest
 * rozstrzygnięcie warte zapisania. Fasada oddająca `ImageList` musiałaby oddawać
 * go jako `?ImageList`, bo przy module wyłączonym nie ma czego oddać — a wtedy
 * **każde** miejsce odczytu powtarzałoby obsługę `null`a. Migawka ma zawsze
 * postać pustą, więc pytający pisze jedną linię zamiast dwóch. Ta sama zasada,
 * którą reguła 15g stosuje do samego `ask()`.
 */
final readonly class DockerQueries
{
    /** Rozdział, którym ten moduł opisuje wpis książki (krok 60). */
    public const CHAPTER = DockerSettings::ID;

    /** Drugi rozdział: rejestry obrazów (krok 61). */
    public const REGISTRY_CHAPTER = DockerChapter::REGISTRY_CHAPTER;

    private const BOOK_ENTRIES = 'address-book.entries';

    private const BOOK_VALUE = 'address-book.value';

    private const BOOK_LAST = 'address-book.last';

    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    /**
     * Wpisy własne widziane oczami tego modułu — **cudza kwerenda, własne
     * pojęcie** (krok 60).
     *
     * Ścieżek TLS w wierszach nie ma (pola są maskowane); dokłada je
     * `environment()` w chwili, gdy trzeba zestawić połączenie.
     *
     * @return list<DockerEnvironment>
     */
    public function bookEntries(): array
    {
        $entries = [];

        foreach ($this->rowsOf(self::BOOK_ENTRIES, [], self::CHAPTER) as $row) {
            $entry = DockerEnvironment::fromRow($row);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Rejestry obrazów widziane oczami tego modułu — **drugi rozdział, ta sama
     * droga** (krok 61).
     *
     * Tokenów w wierszach nie ma (pole jest maskowane, więc niesie `set`/`unset`);
     * dokłada je `registryToken()` w chwili, gdy trzeba złożyć nagłówek.
     *
     * @return list<ImageRegistry>
     */
    public function registries(): array
    {
        $entries = [];

        foreach ($this->rowsOf(self::BOOK_ENTRIES, [], self::REGISTRY_CHAPTER) as $row) {
            $entry = ImageRegistry::fromRow($row);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /** Token wpisu rejestru — osobne pytanie o pole maskowane, jak przy TLS. */
    public function registryToken(string $entry): string
    {
        return $this->secret($entry, 'token', self::REGISTRY_CHAPTER) ?? '';
    }

    /** Ten sam wpis z dołożonym materiałem TLS — trzy osobne pytania o pola maskowane. */
    public function withTls(DockerEnvironment $entry): DockerEnvironment
    {
        return $entry->withTls(
            $this->secret($entry->id, 'cert'),
            $this->secret($entry->id, 'key'),
            $this->secret($entry->id, 'ca'),
        );
    }

    /**
     * Identyfikator wpisu **rozdziału `ssh`** o podanej nazwie; pusty, gdy
     * takiego nie ma.
     *
     * Jedyne miejsce, w którym ten moduł pyta o **cudzy rozdział** — i to jest
     * droga zamierzona, nie obejście (D104 nr 1). Potrzebne przy migracji: stary
     * wpis tunelowy wskazywał host nazwą, a odniesienie przyjmuje identyfikator.
     */
    public function hostEntryNamed(string $name): string
    {
        foreach ($this->rowsOf(self::BOOK_ENTRIES, [], 'ssh') as $row) {
            if (($row['name'] ?? null) === $name && ($row['host'] ?? '') !== '') {
                $id = $row['id'] ?? '';

                return is_string($id) ? $id : '';
            }
        }

        return '';
    }

    /** Identyfikator wpisu dopisanego przed chwilą — potrzebny migracji (D105 nr 6). */
    public function lastAddedEntry(): string
    {
        $id = $this->queries->ask(self::BOOK_LAST)->rows()[0]['id'] ?? '';

        return is_string($id) ? $id : '';
    }

    private function secret(string $entry, string $field, string $chapter = self::CHAPTER): ?string
    {
        $rows = $this->queries->ask(self::BOOK_VALUE, new CommandInput([
            'entry' => $entry,
            'chapter' => $chapter,
            'field' => $field,
        ]))->rows();

        $value = $rows[0]['value'] ?? '';

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, string> $arguments
     *
     * @return list<array<string, string|int|bool>>
     */
    private function rowsOf(string $query, array $arguments, string $chapter): array
    {
        $arguments['chapter'] = $chapter;

        return $this->queries->ask($query, new CommandInput($arguments))->rows();
    }

    public function images(): ImageView
    {
        $payload = $this->ask('images');

        return $payload instanceof ImageView ? $payload : ImageView::empty();
    }

    public function containers(): ContainerView
    {
        $payload = $this->ask('containers');

        return $payload instanceof ContainerView ? $payload : ContainerView::empty();
    }

    public function compose(): ComposeState
    {
        $payload = $this->ask('compose');

        return $payload instanceof ComposeState ? $payload : ComposeState::idle();
    }

    public function build(): BuildProgress
    {
        $payload = $this->ask('build');

        return $payload instanceof BuildProgress ? $payload : BuildProgress::empty();
    }

    public function environments(): EnvironmentBookView
    {
        $payload = $this->ask('environments');

        return $payload instanceof EnvironmentBookView ? $payload : EnvironmentBookView::empty();
    }

    private function ask(string $name): ?object
    {
        return $this->queries->ask(DockerSettings::ID . '.' . $name)->payloadFor(DockerSettings::ID);
    }
}
