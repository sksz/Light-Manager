<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidDirectoryPathException;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectoryPathTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function pathsToNormalise(): array
    {
        return [
            'zwykła ścieżka' => ['/home/uzytkownik', '/home/uzytkownik'],
            'końcowy ukośnik' => ['/home/uzytkownik/', '/home/uzytkownik'],
            'powtórzone ukośniki' => ['/home//uzytkownik///dokumenty', '/home/uzytkownik/dokumenty'],
            'kropka w środku' => ['/home/./uzytkownik', '/home/uzytkownik'],
            'wyjście w górę' => ['/home/uzytkownik/../inny', '/home/inny'],
            'wyjście ponad korzeń' => ['/../..', '/'],
            'sam korzeń' => ['/', '/'],
        ];
    }

    #[DataProvider('pathsToNormalise')]
    public function testNormalisesPath(string $input, string $expected): void
    {
        self::assertSame($expected, (new DirectoryPath($input))->value);
    }

    /** @return array<string, array{string}> */
    public static function invalidPaths(): array
    {
        return [
            'pusta' => [''],
            'względna' => ['home/uzytkownik'],
            'względna z kropką' => ['./dokumenty'],
        ];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsPathThatIsNotAbsolute(string $path): void
    {
        $this->expectException(InvalidDirectoryPathException::class);

        new DirectoryPath($path);
    }

    public function testKnowsItsParent(): void
    {
        $parent = (new DirectoryPath('/home/uzytkownik/dokumenty'))->parent();

        self::assertNotNull($parent);
        self::assertSame('/home/uzytkownik', $parent->value);
    }

    public function testParentOfDirectlyBelowRootIsRoot(): void
    {
        $parent = (new DirectoryPath('/home'))->parent();

        self::assertNotNull($parent);
        self::assertSame('/', $parent->value);
    }

    public function testRootHasNoParent(): void
    {
        self::assertNull(DirectoryPath::root()->parent());
        self::assertTrue(DirectoryPath::root()->isRoot());
    }

    public function testBuildsChildPath(): void
    {
        self::assertSame(
            '/home/uzytkownik/dokumenty',
            (new DirectoryPath('/home/uzytkownik'))->child('dokumenty')->value,
        );
    }

    public function testBuildsChildOfRootWithoutDoubledSlash(): void
    {
        self::assertSame('/home', DirectoryPath::root()->child('home')->value);
    }

    public function testReturnsOwnName(): void
    {
        self::assertSame('dokumenty', (new DirectoryPath('/home/uzytkownik/dokumenty'))->name());
        self::assertSame('/', DirectoryPath::root()->name());
    }

    public function testGoingIntoChildAndBackReturnsTheSamePath(): void
    {
        $start = new DirectoryPath('/home/uzytkownik');
        $back = $start->child('dokumenty')->parent();

        self::assertNotNull($back);
        self::assertTrue($start->equals($back));
    }

    public function testComparesByValue(): void
    {
        self::assertTrue((new DirectoryPath('/home/'))->equals(new DirectoryPath('/home')));
        self::assertFalse((new DirectoryPath('/home'))->equals(new DirectoryPath('/etc')));
    }
}
