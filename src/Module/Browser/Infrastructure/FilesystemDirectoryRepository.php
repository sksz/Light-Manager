<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Infrastructure;

use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;

final class FilesystemDirectoryRepository implements DirectoryRepositoryInterface
{
    /** Bity rodzaju wpisu w polu `mode`. */
    private const TYPE_MASK = 0170000;

    private const DIRECTORY_TYPE = 0040000;

    private const PERMISSION_MASK = 0007777;

    public function __construct(
        private readonly EntryComparator $comparator,
    ) {
    }

    public function get(DirectoryPath $path, bool $includeHidden): Directory
    {
        // Wyciszone ostrzeżenia: katalog może zniknąć albo stracić uprawnienia
        // między jednym a drugim wywołaniem, a komunikat PHP trafiłby wprost na
        // rysowaną klatkę. Interesuje nas wyłącznie fakt niepowodzenia.
        $names = @scandir($path->value);

        if ($names === false) {
            throw DirectoryNotReadableException::forPath($path);
        }

        $entries = [];

        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if (!$includeHidden && str_starts_with($name, '.')) {
                continue;
            }

            $entries[] = $this->toEntry($path, $name);
        }

        return new Directory($path, $this->comparator->sort($entries));
    }

    /**
     * Jeden wpis wraz z tym, co system o nim mówi.
     *
     * **Jedno pytanie do systemu zamiast dwóch** (krok 27): do tego kroku metoda
     * wołała `is_dir()`, a potem `filesize()`, czyli dwa razy to samo `stat`.
     * Odkąd lista pokazuje datę i prawa, potrzebne jest całe `stat` — i wychodzi
     * na tym **taniej**, a nie drożej. Kolumny nie kosztowały więc ani jednego
     * dodatkowego wywołania systemowego, i to jest liczba, którą warto znać przy
     * katalogu o dziesięciu tysiącach wpisów.
     *
     * `stat()`, a nie `lstat()`, bo tak było i tak ma zostać: dowiązanie do
     * katalogu zachowuje się w przeglądarce jak katalog, a rozmiar dowiązania do
     * pliku to rozmiar pliku. Opisem samego dowiązania zajmuje się moduł
     * `FileInfo`, który pyta `lstat`em — i to jest właściwy podział.
     */
    private function toEntry(DirectoryPath $path, string $name): Entry
    {
        // Wyciszone: wpis może zniknąć między `scandir()` a `stat()`, a zerwane
        // dowiązanie nie ma celu, o który dałoby się zapytać.
        $stat = @stat($path->child($name)->value);

        if ($stat === false) {
            // Zerwane dowiązanie albo plik zniknięty w międzyczasie. Rozmiar 0
            // i brak daty są lepsze niż wywrócenie się całego odczytu katalogu.
            return Entry::file($name, 0);
        }

        $modifiedAt = (int) $stat['mtime'];
        $permissions = (int) $stat['mode'] & self::PERMISSION_MASK;

        if (((int) $stat['mode'] & self::TYPE_MASK) === self::DIRECTORY_TYPE) {
            return Entry::directory($name, $modifiedAt, $permissions);
        }

        return Entry::file($name, max(0, (int) $stat['size']), $modifiedAt, $permissions);
    }
}
