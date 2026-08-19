<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * **Wartość pola maskowanego nie wychodzi wierszami** (krok 60, D104 nr 6).
 *
 * Test wkłada do książki sekret o rozpoznawalnej treści, po czym **przeszukuje
 * spłaszczone wiersze wszystkich kwerend aplikacji** — wzorem strażnika
 * z kroku 58. Jedynym miejscem, w którym wolno mu się pojawić, jest kwerenda
 * `address-book.value`, która istnieje właśnie po to.
 *
 * Czego ten test **nie** sprawdza i nie ma sprawdzać: że sekretu nie da się
 * przeczytać. Da się — rejestr kwerend nie zna wołającego, a dostęp do
 * rozdziałów jest jednakowy. Zasłona broni przed **przypadkiem i hałasem**:
 * ścieżka klucza prywatnego nie ma się wyświetlać w spisie, którego nikt o nią
 * nie pytał.
 */
final class SecretFieldsStayOutOfRowsTest extends TestCase
{
    private const SECRET = '/home/anna/.ssh/rozpoznawalny_klucz';

    public function testNoQueryLeaksAMaskedValueInItsRows(): void
    {
        $app = $this->fixture();
        $commands = $app->commandRegistry;

        $commands->find('address-book.field')?->execute(new CommandInput([
            'chapter' => 'ssh',
            'field' => 'keyPath',
            'label' => 'module.ssh.field.key',
            'kind' => 'secret',
        ]));
        $commands->find('address-book.add')?->execute(new CommandInput(['name' => 'biuro']));

        $id = $app->state->queries()->ask('address-book.last')->rows()[0]['id'] ?? '';
        self::assertIsString($id);

        $commands->find('address-book.set')?->execute(new CommandInput([
            'entry' => $id,
            'chapter' => 'ssh',
            'field' => 'keyPath',
            'value' => self::SECRET,
        ]));

        $leaking = [];

        foreach ($app->state->queries()->all() as $query) {
            if ($query->name() === 'address-book.value') {
                continue;
            }

            if (self::leaks($app, $query, $id)) {
                $leaking[] = $query->name();
            }
        }

        self::assertSame([], $leaking, "Sekret wyszedł wierszami kwerend:\n" . implode("\n", $leaking));
    }

    /** Kwerenda przeznaczona do tego wprost **oddaje** wartość — i to jest jej rola. */
    public function testTheDedicatedQueryDoesHandItOver(): void
    {
        $app = $this->fixture();
        $commands = $app->commandRegistry;

        $commands->find('address-book.add')?->execute(new CommandInput(['name' => 'biuro']));
        $id = (string) ($app->state->queries()->ask('address-book.last')->rows()[0]['id'] ?? '');
        $commands->find('address-book.set')?->execute(new CommandInput([
            'entry' => $id,
            'chapter' => 'ssh',
            'field' => 'keyPath',
            'value' => self::SECRET,
        ]));

        $rows = $app->state->queries()->ask('address-book.value', new CommandInput([
            'entry' => $id,
            'chapter' => 'ssh',
            'field' => 'keyPath',
        ]))->rows();

        self::assertSame(self::SECRET, $rows[0]['value'] ?? null);
    }

    private static function leaks(ScreenFixture $app, QueryInterface $query, string $id): bool
    {
        // Kwerendy pyta się z argumentami, które akurat mają sens — a te
        // wskazujące rozdział dostają ten, w którym sekret leży. Inaczej test
        // przechodziłby dlatego, że nikt o właściwy rozdział nie zapytał.
        $input = new CommandInput([
            'chapter' => 'ssh',
            'entry' => $id,
            'field' => 'keyPath',
        ]);

        $encoded = json_encode($app->state->queries()->ask($query->name(), $input)->rows(), JSON_THROW_ON_ERROR);

        return str_contains($encoded, 'rozpoznawalny_klucz');
    }

    private function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);

        return new ScreenFixture($directories->get(new DirectoryPath('/'), false), $directories);
    }
}
