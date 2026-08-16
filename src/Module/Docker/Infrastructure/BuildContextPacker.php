<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use FilesystemIterator;
use LightManager\Module\Docker\Application\PackState;
use LightManager\Module\Docker\Application\Port\BuildContextPort;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Katalog projektu spakowany do archiwum tar — **po kawałku, nie naraz**
 * (krok 51).
 *
 * Demon nie zna pojęcia „zbuduj to, co leży w tym katalogu”: budowa dostaje
 * **kontekst** przesłany w treści żądania jako archiwum tar, a rozpakowuje go po
 * swojej stronie. Kontekst prawdziwego projektu bywa przy tym duży, więc
 * pakowanie jest pracą **dłuższą od klatki** i podlega wzorcowi z D46: port mówi
 * o pracy, nie o wyniku; stan jest daną oglądaną co klatkę; praca ma
 * właściciela, który ją przerywa.
 *
 * `PharData` pakuje **na dysk, a nie do pamięci**, i to jest jedyny powód, dla
 * którego archiwum w ogóle da się tu zbudować bez ryzyka: kontekst
 * kilkusetmegabajtowy trzymany w napisie PHP byłby kilkusetmegabajtowym napisem.
 * Ograniczenie `phar.readonly` tej klasy nie dotyczy — sprawdzone: dotyczy
 * archiwów wykonywalnych, a nie zwykłego tara.
 *
 * **`.dockerignore` jest czytany i to nie jest ozdoba**, tylko warunek
 * używalności: bez niego pierwszy lepszy projekt Node.js wysłałby demonowi
 * `node_modules`, czyli setki megabajtów, których budowa i tak nie użyje.
 * Obsługiwany jest podzbiór składni — komentarze, puste wiersze, wzorce
 * `fnmatch` i wyjątki z `!` — a nie pełna semantyka Dockera z jej regułami
 * kolejności. Podzbiór jest tu uczciwszy niż udawanie kompletności: różnica
 * widoczna jest w rozmiarze kontekstu, a nie w wyniku budowy.
 */
final class BuildContextPacker implements BuildContextPort
{
    /** Ile wpisów wkładamy do archiwum w jednym takcie. */
    private const FILES_PER_TICK = 200;

    /**
     * Ile najwyżej bajtów wolno mieć kontekstowi.
     *
     * Granica istnieje dla pomyłki, nie dla złej woli: budowa zamówiona
     * w katalogu domowym spakowałaby wszystko, co użytkownik ma na dysku.
     * Pół gibibajta jest ponad każdy sensowny kontekst i poniżej rozmiaru, przy
     * którym pakowanie trwałoby minutami.
     */
    private const MAX_CONTEXT_BYTES = 512 * 1024 * 1024;

    private PackState $state;

    private ?PharData $archive = null;

    private ?string $archivePath = null;

    /**
     * Pliki czekające na spakowanie — ścieżka bezwzględna → nazwa w archiwum.
     *
     * @var list<array{0: string, 1: string}>
     */
    private array $pending = [];

    private int $packed = 0;

    public function __construct()
    {
        $this->state = PackState::idle();
    }

    public function state(): PackState
    {
        return $this->state;
    }

    /**
     * Zaczyna pakowanie: **spis plików powstaje tu, treść archiwum — po kawałku**.
     *
     * Spis jest robiony naraz i to jest świadome odstępstwo od pracy kawałkowej:
     * przejście `RecursiveDirectoryIterator`iem po katalogu kosztuje `lstat` na
     * wpis, czyli tyle, co odczyt katalogu w przeglądarce, a **mianownik paska
     * postępu musi być znany od pierwszej klatki** (D79: praca liczy przed
     * pracą, żeby pasek się nie cofał). Kosztem jest jedna klatka na starcie
     * budowy wielkiego projektu.
     */
    public function begin(string $directory): void
    {
        $this->stop();

        $path = realpath($directory);

        if ($path === false || !is_dir($path)) {
            $this->state = PackState::failed('module.docker.build.noContext', ['path' => $directory]);

            return;
        }

        if (!is_file($path . '/Dockerfile')) {
            $this->state = PackState::failed('module.docker.build.noDockerfile', ['path' => $path]);

            return;
        }

        $ignore = DockerIgnore::readFrom($path);
        $bytes = 0;

        foreach (self::walk($path) as $file) {
            $relative = substr($file->getPathname(), strlen($path) + 1);

            if ($ignore->excludes($relative)) {
                continue;
            }

            $bytes += $file->getSize();

            if ($bytes > self::MAX_CONTEXT_BYTES) {
                $this->state = PackState::failed('module.docker.build.tooLarge', [
                    'limit' => (int) (self::MAX_CONTEXT_BYTES / (1024 * 1024)),
                ]);
                $this->pending = [];

                return;
            }

            $this->pending[] = [$file->getPathname(), $relative];
        }

        if ($this->pending === []) {
            $this->state = PackState::failed('module.docker.build.emptyContext', ['path' => $path]);

            return;
        }

        $this->archivePath = self::temporaryPath();
        $this->packed = 0;
        $this->state = PackState::packing(0, count($this->pending));
    }

    /**
     * Pakuje kolejny kawałek. **Nigdy nie blokuje na dłużej niż kawałek.**
     *
     * Archiwum powstaje **leniwie, przy pierwszym kawałku**: `PharData` tworzy
     * plik już w konstruktorze, a praca odrzucona jeszcze przed pierwszym taktem
     * zostawiałaby wtedy pusty plik w katalogu tymczasowym.
     */
    public function advance(): void
    {
        if (!$this->state->isPacking() || $this->archivePath === null) {
            return;
        }

        try {
            $archive = $this->archive ??= new PharData($this->archivePath);

            for ($index = 0; $index < self::FILES_PER_TICK && $this->pending !== []; ++$index) {
                [$absolute, $relative] = array_shift($this->pending);
                $archive->addFile($absolute, $relative);
                ++$this->packed;
            }
        } catch (Throwable $exception) {
            // `PharData` rzuca przy pliku zniknionym w trakcie pakowania i przy
            // braku miejsca. Wyjątek nie ma prawa wyjść poza port (reguła 8):
            // budowa nie jest awarią aplikacji, tylko pracą, która się nie udała.
            $this->state = PackState::failed('module.docker.build.packFailed', [
                'reason' => $exception->getMessage(),
            ]);
            $this->cleanUp();

            return;
        }

        $this->state = $this->pending === []
            ? PackState::packed($this->archivePath, $this->packed)
            : PackState::packing($this->packed, $this->packed + count($this->pending));
    }

    public function stop(): void
    {
        $this->cleanUp();
        $this->pending = [];
        $this->packed = 0;
        $this->state = PackState::idle();
    }

    /**
     * Zapomina o gotowym archiwum **bez kasowania go** — wołane, gdy treść
     * poszła już do demona i to on odpowiada za jej los.
     */
    public function forget(): void
    {
        $this->archive = null;
        $this->archivePath = null;
        $this->pending = [];
        $this->state = PackState::idle();
    }

    /** Kasuje archiwum, jeśli zdążyło powstać. */
    private function cleanUp(): void
    {
        $path = $this->archivePath;
        $this->archive = null;

        if ($path !== null && is_file($path)) {
            @unlink($path);
        }

        $this->archivePath = null;
    }

    /**
     * Wpisy katalogu w kolejności przejścia w głąb, bez dowiązań i katalogów.
     *
     * Katalogów nie pakujemy z rozmysłem: tar odtwarza je z nazw plików, a pusty
     * katalog nie ma dla budowy żadnego znaczenia. Dowiązań nie pakujemy, bo
     * `PharData::addFile()` poszedłby za nimi i wsadził do kontekstu treść
     * leżącą poza katalogiem projektu.
     *
     * @return list<SplFileInfo>
     */
    private static function walk(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private static function temporaryPath(): string
    {
        return sys_get_temp_dir() . '/light-manager-build-' . getmypid() . '-' . uniqid() . '.tar';
    }
}
