<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Config;

use LightManager\Application\Command\CommandHistory;
use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\Config\CommandHistoryService;
use LightManager\Tests\Support\PinsLanguage;
use PHPUnit\Framework\TestCase;

/**
 * Historia na dysku — drugi po konfiguracji test, który naprawdę pisze po nim,
 * bo cała rzecz polega na tym, że wpisany wiersz przeżywa proces.
 *
 * Katalog domowy jest podmieniany na tymczasowy, więc test nie ma jak dotknąć
 * historii osoby, która go uruchamia.
 */
final class CommandHistoryServiceTest extends TestCase
{
    use PinsLanguage;

    private CommandHistoryService $service;

    protected function setUp(): void
    {
        $this->pinLanguage(Language::Polish);
        $this->resetSingleton(CommandHistoryService::class);

        $this->service = CommandHistoryService::getInstance();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(CommandHistoryService::class);
    }

    public function testStartWithoutFileGivesEmptyHistoryInSilence(): void
    {
        self::assertSame([], $this->service->load());
        self::assertFileDoesNotExist($this->service->location(), 'sam odczyt niczego nie tworzy');
    }

    public function testWhatWasSavedComesBack(): void
    {
        $this->service->save(['core.help', 'core.theme grafit']);

        self::assertSame(['core.help', 'core.theme grafit'], $this->service->load());
    }

    public function testFileHoldsAtMostAsMuchAsTheBuffer(): void
    {
        $entries = array_map(static fn (int $index): string => 'core.help ' . $index, range(1, 30));

        $this->service->save($entries);

        self::assertCount(CommandHistory::CAPACITY, $this->service->load());
        self::assertSame('core.help 30', $this->service->load()[CommandHistory::CAPACITY - 1]);
    }

    public function testSavingOverwritesInsteadOfAppending(): void
    {
        $this->service->save(['core.help']);
        $this->service->save(['core.quit']);

        self::assertSame(['core.quit'], $this->service->load());
    }

    public function testBlankLinesInTheFileAreIgnored(): void
    {
        $this->service->save(['core.help']);
        file_put_contents($this->service->location(), "\ncore.help\n\n   \ncore.quit\n");

        self::assertSame(['core.help', 'core.quit'], $this->service->load());
    }

    /** Plik bywa ścieżkami — nikt poza właścicielem nie ma powodu go czytać. */
    public function testFileIsReadableOnlyByItsOwner(): void
    {
        $this->service->save(['core.jump /home/sksz']);

        self::assertSame('0600', substr(sprintf('%o', fileperms($this->service->location())), -4));
    }
}
