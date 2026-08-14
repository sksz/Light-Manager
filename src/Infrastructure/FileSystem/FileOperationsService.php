<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\FileSystem;

use LightManager\Application\Dto\RemovalStage;
use LightManager\Application\Dto\RemovalState;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Jedyne miejsce w aplikacji, które zmienia zawartość dysku (krok 41).
 *
 * Do tego kroku rdzeń umiał zapisać wyłącznie własny plik konfiguracyjny
 * (`SettingsService`). Ta usługa jest drugim takim miejscem i **ostatnim, które
 * ma prawo powstać**: granica wyjątku od reguły 15 jest opisana w porcie i
 * w `SKILL.md`, a wszystko, co potrzebuje zapisu, ma odtąd iść przez ten port.
 *
 * Trzy zasady, których pilnuje kod poniżej:
 *
 * 1. **Sprawdzenie przed czynnością, nie po niej.** Istnienie, prawo zapisu
 *    w katalogu i kolizja nazwy sprawdzają się, zanim cokolwiek się stanie — bo
 *    ekran ma pokazać skutek, a nie zgadywać, czy da się go osiągnąć. Reszta
 *    (pełny dysk, plik zajęty, awaria nośnika) rozpoznaje się dopiero
 *    z niepowodzenia i idzie do użytkownika jako szczegół techniczny.
 * 2. **Ostrzeżenia PHP są wyciszane**, jak w repozytorium katalogów: komunikat
 *    wypisany na `STDOUT` trafiłby wprost na rysowaną klatkę. Treść ostrzeżenia
 *    czytamy z `error_get_last()`, więc nie ginie — a `error_clear_last()` przed
 *    każdą próbą pilnuje, żeby nie przypisać sobie cudzego, starego błędu.
 * 3. **Jedna praca naraz** (reguła 11d). Nowe usuwanie przerywa poprzednie —
 *    dwie listy usuwanych wpisów naraz byłyby dwoma sposobami na usunięcie
 *    czegoś, na co nikt już nie patrzy.
 */
final class FileOperationsService extends AbstractSingleton implements FileOperationsPort
{
    private RemovalState $removal;

    /**
     * Katalogi, których zawartości jeszcze nie przeczytano.
     *
     * @var list<string>
     */
    private array $toScan = [];

    /** @var list<string> wszystko, co nie jest katalogiem: pliki i dowiązania */
    private array $files = [];

    /** @var list<string> katalogi w kolejności odkrycia — rodzic zawsze przed dzieckiem */
    private array $dirs = [];

    /** @var list<string> lista do usunięcia, ułożona w kolejności wykonania */
    private array $queue = [];

    private int $index = 0;

    protected function __construct()
    {
        parent::__construct();

        $this->removal = RemovalState::idle();
    }

    public function rename(string $path, string $newName): void
    {
        $this->ensureExists($path);
        $this->ensureWritableParent($path);

        $target = self::sibling($path, $newName);
        $this->ensureFree($target);

        error_clear_last();

        if (!@rename($path, $target)) {
            throw FileOperationException::failed($path, self::lastError());
        }
    }

    public function createDirectory(string $path): void
    {
        $parent = dirname($path);

        if (!is_dir($parent)) {
            throw FileOperationException::missing($parent);
        }

        if (!is_writable($parent)) {
            throw FileOperationException::denied($path);
        }

        $this->ensureFree($path);

        error_clear_last();

        // Prawa zostawiamy systemowi: `0777` przycięte umaską daje to samo, co
        // `mkdir` w powłoce, a własne prawa byłyby decyzją, której użytkownik
        // nie zamawiał.
        if (!@mkdir($path)) {
            throw FileOperationException::failed($path, self::lastError());
        }
    }

    public function delete(string $path): void
    {
        $this->ensureExists($path);
        $this->ensureWritableParent($path);

        error_clear_last();

        // Dowiązanie sprawdzamy **przed** `is_dir()`, bo dowiązanie do katalogu
        // jest dla `is_dir()` katalogiem — a usuwa się je `unlink()`iem i znika
        // wtedy samo dowiązanie, nie cel.
        if (is_link($path) || !is_dir($path)) {
            if (!@unlink($path)) {
                throw FileOperationException::failed($path, self::lastError());
            }

            return;
        }

        if (@rmdir($path)) {
            return;
        }

        $detail = self::lastError();
        $names = @scandir($path);

        // Katalog niepusty rozpoznajemy **z zawartości**, a nie z treści
        // ostrzeżenia: napis systemowy zależy od systemu, a liczba wpisów nie.
        if ($names !== false && count($names) > 2) {
            throw FileOperationException::notEmpty($path);
        }

        throw FileOperationException::failed($path, $detail);
    }

    public function beginRemoval(string $path): RemovalState
    {
        $this->stopRemoval();

        if (!file_exists($path) && !is_link($path)) {
            return $this->removal = RemovalState::failed(
                'problem.fileops.missing',
                ['name' => basename($path)],
                0,
                null,
            );
        }

        if (!is_writable(dirname($path))) {
            return $this->removal = RemovalState::failed(
                'problem.fileops.denied',
                ['name' => basename($path)],
                0,
                null,
            );
        }

        // Dowiązanie i plik znikają **jednym** wywołaniem, więc praca nie ma czego
        // liczyć i staje od razu na przystanku. Droga zostaje przez to jedna dla
        // wszystkiego, co usuwa się pracą kawałkową — a `is_link()` idzie przed
        // `is_dir()`, bo dowiązanie do katalogu jest dla `is_dir()` katalogiem,
        // a usuwanie jego „zawartości” tknęłoby plików leżących gdzie indziej.
        if (is_link($path) || !is_dir($path)) {
            $this->files = [$path];

            return $this->removal = RemovalState::ready(1);
        }

        $this->toScan = [$path];
        $this->dirs = [$path];

        // Katalog wskazany liczy się od razu jako jeden wpis do usunięcia: on też
        // zniknie, a liczba w pytaniu ma być tą samą liczbą, co w pasku postępu.
        return $this->removal = RemovalState::scanning(1, basename($path));
    }

    public function advanceRemoval(int $entries): RemovalState
    {
        $budget = max(1, $entries);

        return match ($this->removal->stage) {
            RemovalStage::Scanning => $this->scan($budget),
            RemovalStage::Deleting => $this->remove($budget),
            default => $this->removal,
        };
    }

    public function confirmRemoval(): RemovalState
    {
        if ($this->removal->stage !== RemovalStage::Ready) {
            return $this->removal;
        }

        // Pliki naprzód, katalogi w kolejności **odwrotnej do odkrycia**: rodzic
        // stoi w liście przed dzieckiem, więc odwrócenie stawia dziecko przed
        // rodzicem — a katalog daje się usunąć wyłącznie pusty. Sortowania po
        // głębokości nie ma i nie trzeba: kolejność odkrycia już ją niesie.
        $this->queue = [...$this->files, ...array_reverse($this->dirs)];
        $this->index = 0;

        return $this->removal = RemovalState::deleting(
            0,
            count($this->queue),
            basename($this->queue[0] ?? ''),
        );
    }

    public function removalState(): RemovalState
    {
        return $this->removal;
    }

    public function stopRemoval(): void
    {
        $this->toScan = [];
        $this->files = [];
        $this->dirs = [];
        $this->queue = [];
        $this->index = 0;
        $this->removal = RemovalState::idle();
    }

    /**
     * Kawałek liczenia: czyta katalogi ze stosu, dopóki nie wyczerpie budżetu.
     *
     * Budżet liczy **wpisy**, a nie katalogi, bo koszt bierze się z ich liczby.
     * Jeden katalog czyta się mimo to w całości — `scandir()` i tak oddaje całą
     * zawartość naraz, więc przerwanie w środku nie oszczędziłoby ani jednej
     * operacji systemowej, a wymagałoby pamiętania miejsca w tablicy.
     */
    private function scan(int $budget): RemovalState
    {
        $current = $this->removal->current;

        while ($budget > 0 && $this->toScan !== []) {
            $directory = array_pop($this->toScan);

            $names = @scandir($directory);

            if ($names === false) {
                // Gałąź nieczytelna zatrzymuje pracę **przed** usunięciem
                // czegokolwiek: usuwanie drzewa, którego nie da się przejść do
                // końca, skończyłoby się w połowie.
                return $this->removal = RemovalState::failed(
                    'problem.fileops.denied',
                    ['name' => basename($directory)],
                    0,
                    null,
                );
            }

            foreach ($names as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $child = self::child($directory, $name);

                // Dowiązanie do katalogu jest wpisem do usunięcia, a nie gałęzią
                // do przejścia: usuwanie „zawartości” dowiązania usunęłoby pliki
                // leżące zupełnie gdzie indziej.
                if (!is_link($child) && is_dir($child)) {
                    $this->dirs[] = $child;
                    $this->toScan[] = $child;
                } else {
                    $this->files[] = $child;
                }

                --$budget;
                $current = $name;
            }
        }

        $found = count($this->files) + count($this->dirs);

        if ($this->toScan === []) {
            return $this->removal = RemovalState::ready($found);
        }

        return $this->removal = RemovalState::scanning($found, $current);
    }

    /** Kawałek usuwania: zdejmuje z listy tyle wpisów, ile pozwala budżet. */
    private function remove(int $budget): RemovalState
    {
        $total = count($this->queue);
        $current = $this->removal->current;

        while ($budget > 0 && $this->index < $total) {
            $path = $this->queue[$this->index];
            $current = basename($path);

            error_clear_last();
            $removed = is_link($path) || !is_dir($path) ? @unlink($path) : @rmdir($path);

            // Wpis, który zniknął cudzą ręką, jest wpisem usuniętym — praca ma
            // doprowadzić do stanu „tego nie ma”, a nie wykonać czynność za
            // wszelką cenę.
            if (!$removed && (file_exists($path) || is_link($path))) {
                return $this->removal = RemovalState::failed(
                    'problem.fileops.failed',
                    ['name' => $current, 'detail' => self::lastError()],
                    $this->index,
                    $total,
                );
            }

            ++$this->index;
            --$budget;
        }

        if ($this->index >= $total) {
            return $this->removal = RemovalState::done($total);
        }

        return $this->removal = RemovalState::deleting($this->index, $total, $current);
    }

    /** Zerwane dowiązanie **istnieje**, choć `file_exists()` mówi o nim „nie”. */
    private function ensureExists(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            throw FileOperationException::missing($path);
        }
    }

    /**
     * Prawo zapisu należy do **katalogu**, nie do wpisu: skasować plik wolno
     * temu, kto może zmienić katalog, w którym plik leży — nawet gdy sam plik
     * jest tylko do czytania.
     */
    private function ensureWritableParent(string $path): void
    {
        if (!is_writable(dirname($path))) {
            throw FileOperationException::denied($path);
        }
    }

    private function ensureFree(string $path): void
    {
        if (file_exists($path) || is_link($path)) {
            throw FileOperationException::nameTaken($path);
        }
    }

    /** Ścieżka rodzeństwa: ten sam katalog, inna nazwa. */
    private static function sibling(string $path, string $name): string
    {
        return self::child(dirname($path), $name);
    }

    private static function child(string $directory, string $name): string
    {
        return ($directory === '/' ? '' : $directory) . '/' . $name;
    }

    /**
     * Sam powód z ostatniego ostrzeżenia, bez przedrostka z nazwą funkcji.
     *
     * PHP mówi `unlink(/tmp/x): Permission denied`; ścieżkę i czynność wołający
     * zna, więc do zdania w pasku stanu idzie wyłącznie ogon.
     */
    private static function lastError(): string
    {
        $error = error_get_last();

        if ($error === null) {
            return 'unknown error';
        }

        $position = strrpos($error['message'], ': ');

        return $position === false ? $error['message'] : substr($error['message'], $position + 2);
    }
}
