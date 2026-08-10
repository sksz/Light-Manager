<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\UseCase;

use LightManager\Application\UseCase\OpenStartingDirectoryUseCase;
use LightManager\Domain\Exception\DirectoryNotReadableException;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use PHPUnit\Framework\TestCase;

final class OpenStartingDirectoryUseCaseTest extends TestCase
{
    private InMemoryDirectoryRepository $directories;

    private OpenStartingDirectoryUseCase $useCase;

    protected function setUp(): void
    {
        $this->directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('uzytkownik')])
            ->add('/home/uzytkownik', [Entry::directory('projekt')])
            ->add('/home/uzytkownik/projekt', [Entry::file('plik.txt', 1)]);

        $this->useCase = new OpenStartingDirectoryUseCase($this->directories);
    }

    public function testOpensRequestedDirectoryWhenItIsReadable(): void
    {
        $directory = $this->useCase->execute(new DirectoryPath('/home/uzytkownik/projekt'), false);

        self::assertSame('/home/uzytkownik/projekt', $directory->path()->value);
        self::assertSame(1, $this->directories->reads);
    }

    public function testFallsBackToTheNearestReadableAncestor(): void
    {
        $this->directories->makeUnreadable('/home/uzytkownik/projekt');

        $directory = $this->useCase->execute(new DirectoryPath('/home/uzytkownik/projekt'), false);

        self::assertSame('/home/uzytkownik', $directory->path()->value);
    }

    public function testWalksUpAsFarAsNeeded(): void
    {
        $this->directories
            ->makeUnreadable('/home/uzytkownik/projekt')
            ->makeUnreadable('/home/uzytkownik')
            ->makeUnreadable('/home');

        $directory = $this->useCase->execute(new DirectoryPath('/home/uzytkownik/projekt'), false);

        self::assertSame('/', $directory->path()->value);
    }

    public function testGivesUpWhenEvenRootIsUnreadable(): void
    {
        $this->directories
            ->makeUnreadable('/home/uzytkownik/projekt')
            ->makeUnreadable('/home/uzytkownik')
            ->makeUnreadable('/home')
            ->makeUnreadable('/');

        $this->expectException(DirectoryNotReadableException::class);

        $this->useCase->execute(new DirectoryPath('/home/uzytkownik/projekt'), false);
    }

    public function testDirectoryThatDoesNotExistIsTreatedLikeUnreadableOne(): void
    {
        $directory = $this->useCase->execute(new DirectoryPath('/home/uzytkownik/nie-ma-takiego'), false);

        self::assertSame('/home/uzytkownik', $directory->path()->value);
    }
}
