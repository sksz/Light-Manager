<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Port\DockerStatePort;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Presentation\Cli\LoopState;

/**
 * Zapowiedź użycia rozdziału `docker` wraz z przeniesieniem starej książki
 * środowisk (krok 60, etap 2).
 *
 * Bliźniacza wobec `SshChapter` i celowo napisana tak samo: **cała wiedza tego
 * modułu o książce adresowej mieści się tutaj i w `DockerQueries`**, a składa
 * się z samych nazw komend i kwerend (15g).
 *
 * Rozdział niesie **to, co mówi, gdzie stoi demon i czym się przedstawiamy**:
 * rodzaj połączenia, gniazdo, cel tunelu, port i trzy ścieżki materiału TLS.
 * Cel tunelu jest polem rodzaju **`entry`** — odniesieniem do wpisu książki,
 * a nie nazwą — i to jest jedyne miejsce w całym kroku, w którym ten rodzaj ma
 * odbiorcę. Zysk widać dopiero przy zmianie nazwy hosta: przed krokiem 60 wpis
 * tunelowy trzymał napis, za którym książka nie umiała pójść.
 */
final class DockerChapter
{
    public const ID = DockerSettings::ID;

    private const DECLARE_CHAPTER = 'address-book.chapter';

    private const DECLARE_FIELD = 'address-book.field';

    private const ADD_ENTRY = 'address-book.add';

    private const SET_VALUE = 'address-book.set';

    /** Pola rozdziału, w kolejności kolumn i pytań łańcucha okien. */
    private const FIELDS = ['kind', 'socket', 'target', 'port', 'cert', 'key', 'ca'];

    private bool $declared = false;

    public function __construct(
        private readonly LoopState $state,
        private readonly DockerQueries $reader,
        private readonly DockerStatePort $storage,
    ) {
    }

    public function tick(): void
    {
        if ($this->declared) {
            return;
        }

        $commands = $this->state->commands();

        if ($commands->find(self::DECLARE_CHAPTER) === null || $commands->find(self::DECLARE_FIELD) === null) {
            return;
        }

        $this->declared = true;
        $this->declare($commands);
        $this->migrate($commands);
    }

    private function declare(CommandRegistry $commands): void
    {
        $commands->find(self::DECLARE_CHAPTER)?->execute(new CommandInput([
            'chapter' => self::ID,
            'title' => 'module.' . self::ID . '.name',
        ]));

        $field = $commands->find(self::DECLARE_FIELD);

        foreach (self::declarations() as $declaration) {
            $field?->execute(new CommandInput($declaration));
        }
    }

    /** @return list<array<string, string>> */
    private static function declarations(): array
    {
        $label = static fn (string $field): string => 'module.' . self::ID . '.field.' . $field;

        return [
            [
                'chapter' => self::ID,
                'field' => 'kind',
                'label' => $label('kind'),
                'kind' => 'choice',
                'default' => 'local',
                'choices' => 'local,tunnel,tcp',
            ],
            [
                'chapter' => self::ID,
                'field' => 'socket',
                'label' => $label('socket'),
                'kind' => 'text',
                'default' => DockerEnvironment::DEFAULT_SOCKET,
            ],
            ['chapter' => self::ID, 'field' => 'target', 'label' => $label('target'), 'kind' => 'entry'],
            [
                'chapter' => self::ID,
                'field' => 'port',
                'label' => $label('port'),
                'kind' => 'number',
                'default' => (string) DockerEnvironment::DEFAULT_TUNNEL_PORT,
            ],
            ['chapter' => self::ID, 'field' => 'cert', 'label' => $label('cert'), 'kind' => 'secret'],
            ['chapter' => self::ID, 'field' => 'key', 'label' => $label('key'), 'kind' => 'secret'],
            ['chapter' => self::ID, 'field' => 'ca', 'label' => $label('ca'), 'kind' => 'secret'],
        ];
    }

    /**
     * Przenosi stary spis środowisk do książki — **raz, komendami,
     * nieniszcząco**.
     *
     * Cel tunelu przelicza się przy okazji z **nazwy wpisu książki hostów** na
     * **odniesienie do wpisu**: stara wartość wskazywała host po nazwie, a po
     * migracji obie rzeczy stoją w jednej książce, więc nazwę da się na
     * identyfikator zamienić. Nazwa, której w książce nie ma (adres wpisany
     * wprost), zostaje **pusta** — pole rodzaju `entry` nie przyjmuje czegoś,
     * co nie jest wpisem, a ekran powie wtedy, co wybrać.
     */
    private function migrate(CommandRegistry $commands): void
    {
        if ($this->storage->isMigrated()) {
            return;
        }

        $add = $commands->find(self::ADD_ENTRY);
        $set = $commands->find(self::SET_VALUE);

        if ($add === null || $set === null) {
            return;
        }

        foreach ($this->storage->legacyEnvironments() as $environment) {
            $name = (string) ($environment['name'] ?? '');
            $add->execute(new CommandInput(['name' => $name]));
            $id = $this->reader->lastAddedEntry();

            if ($id === '') {
                continue;
            }

            foreach (self::FIELDS as $field) {
                $value = $this->valueOf($environment, $field);

                if ($value !== '') {
                    $set->execute(new CommandInput([
                        'entry' => $id,
                        'chapter' => self::ID,
                        'field' => $field,
                        'value' => $value,
                    ]));
                }
            }

            if ($this->storage->current() === $name) {
                $this->storage->makeCurrent($id);
            }
        }

        $this->storage->markMigrated();
    }

    /** @param array<string, string|int> $environment */
    private function valueOf(array $environment, string $field): string
    {
        $value = (string) ($environment[$field] ?? '');

        if ($field !== 'target' || $value === '') {
            return $value;
        }

        // Cel tunelu był nazwą wpisu książki hostów; odniesienie przyjmuje
        // wyłącznie identyfikator, więc nazwę trzeba na niego zamienić.
        return $this->reader->hostEntryNamed($value);
    }
}
