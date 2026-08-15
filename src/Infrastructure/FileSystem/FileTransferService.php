<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\FileSystem;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\TransferStage;
use LightManager\Application\Dto\TransferState;
use LightManager\Application\Port\FileTransferPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Kopiowanie i przenoszenie po kawałku, przez wiele klatek (krok 42).
 *
 * Druga usługa rdzenia pisząca po dysku, obok `FileOperationsService`, i tym
 * samym miejsce, w którym granica wyjątku od reguły 15 przestaje być jedną klasą,
 * a staje się **katalogiem** (D79, rozstrzygnięcie 1). Zasada zostaje nietknięta:
 * wszystko, co pisze po dysku, idzie przez port rdzenia.
 *
 * Praca ma dwa etapy i to jest jej najważniejsza właściwość. **Najpierw liczy,
 * potem kopiuje** — bo pasek postępu ma mówić prawdę od pierwszego bajtu, a nie
 * dopiero od chwili, w której chodzenie po drzewie się skończy. Oba etapy są
 * kawałkowe (D46), oba mają własną miarę budżetu: liczenie mierzy się wpisami,
 * kopiowanie bajtami.
 *
 * Cztery reguły, których pilnuje kod poniżej — i każda ma cenę zapisaną wprost:
 *
 * 1. **Źródło znika dopiero po potwierdzonym zapisaniu celu.** Przy przenoszeniu
 *    plik kasuje się jako ostatnia czynność swojego własnego kawałka, a katalogi
 *    źródłowe na samym końcu, w kolejności odwrotnej do odkrycia. Przerwanie
 *    w połowie zostawia źródło nietknięte — z wyjątkiem tego, co już przeniesione.
 * 2. **Przeniesienie w obrębie jednego systemu plików nie kopiuje ani bajtu.**
 *    Rozpoznanie idzie przez **numer urządzenia**, a nie przez próbę `rename()`
 *    z odczytaniem błędu — bo PHP obsługuje `EXDEV` dla zwykłych plików sam,
 *    kopiując je w środku wywołania. `rename()` w PHP **nie zawsze jest operacją
 *    na metadanych** i jest to pułapka, którą łatwo przeoczyć: wywołane na
 *    pliku wielkości płyty leżącym na innym dysku zatrzymałoby pętlę na minutę.
 * 3. **Dowiązanie kopiuje się jako dowiązanie**, nigdy jego treść — `is_link()`
 *    sprawdzane **przed** `is_dir()`, tak samo jak przy usuwaniu w kroku 41.
 *    Chodzenie po drzewie w dowiązania nie wchodzi, więc pętli w drzewie nie ma
 *    czego wykrywać.
 * 4. **Prawa i czas zmiany nadaje się katalogowi na końcu**, osobnymi pozycjami
 *    kolejki: katalog o prawach `0555` nie przyjąłby ani jednego pliku, gdyby
 *    dostał je przy tworzeniu.
 *
 * **Cena zapisu wprost** (D79, rozstrzygnięcie 5), którą trzeba znać: przerwanie
 * usuwa plik zapisany w połowie — także wtedy, gdy w połowie zapisywał się plik
 * **nadpisywany**. Oryginał ginie wtedy bezpowrotnie, ale ginie już w chwili
 * otwarcia celu do zapisu, a nie przy przerwaniu; zostawienie go w połowie byłoby
 * zostawieniem pliku wyglądającego na gotowy, czyli rzeczy, której ten krok
 * zabrania.
 */
final class FileTransferService extends AbstractSingleton implements FileTransferPort
{
    private TransferState $state;

    private bool $move = false;

    /**
     * Katalogi, których zawartości jeszcze nie przeczytano: źródło i cel.
     *
     * @var list<array{string, string}>
     */
    private array $toScan = [];

    /**
     * Katalogi w kolejności odkrycia — rodzic zawsze przed dzieckiem.
     *
     * @var list<array{string, string}>
     */
    private array $dirs = [];

    /** @var list<TransferItem> pliki i dowiązania w kolejności odkrycia */
    private array $files = [];

    private int $foundBytes = 0;

    /** @var list<TransferItem> kolejka ułożona w kolejności wykonania */
    private array $queue = [];

    private int $index = 0;

    private int $doneBytes = 0;

    private int $doneEntries = 0;

    private int $totalBytes = 0;

    private int $totalEntries = 0;

    private string $current = '';

    /** @var resource|null uchwyt czytanego pliku */
    private $in = null;

    /** @var resource|null uchwyt pisanego pliku */
    private $out = null;

    /** Plik zapisany w połowie — jedyna rzecz, którą przerwanie ma po sobie sprzątnąć. */
    private ?string $partial = null;

    /** Czy pozycja, na której praca stoi, ma już zgodę na nadpisanie. */
    private bool $approved = false;

    private bool $overwriteAll = false;

    private bool $skipAll = false;

    protected function __construct()
    {
        parent::__construct();

        $this->state = TransferState::idle();
    }

    public function begin(array $sources, string $target, bool $move): TransferState
    {
        $this->stop();
        $this->move = $move;

        $root = realpath($target);

        if ($root === false || !is_dir($target)) {
            return $this->state = $this->refuse('problem.transfer.noTarget', basename($target));
        }

        if (!is_writable($target)) {
            return $this->state = $this->refuse('problem.fileops.denied', basename($target));
        }

        foreach ($sources as $source) {
            $refusal = $this->check($source, $root);

            if ($refusal !== null) {
                return $this->state = $refusal;
            }
        }

        foreach ($sources as $source) {
            $this->seed($source, self::child($root, basename($source)));
        }

        return $this->state = $this->toScan === []
            ? $this->started()
            : TransferState::scanning(count($this->dirs) + count($this->files), $this->foundBytes, $this->current);
    }

    public function advance(int $budget): TransferState
    {
        $budget = max(1, $budget);

        return match ($this->state->stage) {
            TransferStage::Scanning => $this->scan($budget),
            TransferStage::Working => $this->work($budget),
            default => $this->state,
        };
    }

    public function resolve(TransferChoice $choice, ?string $newName = null): TransferState
    {
        $item = $this->queue[$this->index] ?? null;

        if ($this->state->stage !== TransferStage::Colliding || $item === null) {
            return $this->state;
        }

        if ($choice === TransferChoice::Abort) {
            $this->release();

            return $this->state = TransferState::done(
                $this->doneBytes,
                $this->totalBytes,
                $this->doneEntries,
                $this->totalEntries,
            );
        }

        if ($choice === TransferChoice::Rename) {
            // Nazwa jest **treścią** odpowiedzi, a nie jej ozdobą: bez niej nie ma
            // czego rozstrzygnąć, więc praca zostaje na przystanku.
            if ($newName === null || $newName === '') {
                return $this->state;
            }

            $this->retarget($item, $newName);
        }

        if ($choice === TransferChoice::OverwriteAll || $choice === TransferChoice::SkipAll) {
            $this->overwriteAll = $choice === TransferChoice::OverwriteAll;
            $this->skipAll = $choice === TransferChoice::SkipAll;
        }

        if ($choice === TransferChoice::Overwrite || $choice === TransferChoice::OverwriteAll) {
            $this->approved = true;
        }

        if ($choice === TransferChoice::Skip || $choice === TransferChoice::SkipAll) {
            $this->skipItem($item);
        }

        return $this->state = $this->working();
    }

    public function state(): TransferState
    {
        return $this->state;
    }

    public function stop(): void
    {
        $this->release();

        $this->move = false;
        $this->toScan = [];
        $this->dirs = [];
        $this->files = [];
        $this->queue = [];
        $this->foundBytes = 0;
        $this->index = 0;
        $this->doneBytes = 0;
        $this->doneEntries = 0;
        $this->totalBytes = 0;
        $this->totalEntries = 0;
        $this->current = '';
        $this->approved = false;
        $this->overwriteAll = false;
        $this->skipAll = false;
        $this->state = TransferState::idle();
    }

    /**
     * Trzy drogi do pętli nieskończonej, zamknięte sprawdzeniem, a nie limitem:
     * cel równy źródłu, cel w środku kopiowanego katalogu i cel równy katalogowi,
     * w którym źródło już leży.
     */
    private function check(string $source, string $root): ?TransferState
    {
        if (!file_exists($source) && !is_link($source)) {
            return $this->refuse('problem.fileops.missing', basename($source));
        }

        if (dirname($source) === $root) {
            return $this->refuse('problem.transfer.sameDirectory', basename($source));
        }

        $real = is_link($source) ? false : realpath($source);

        if ($real !== false && is_dir($real) && ($root === $real || str_starts_with($root . '/', $real . '/'))) {
            return $this->refuse('problem.transfer.intoItself', basename($source));
        }

        return null;
    }

    /**
     * Wpis najwyższego poziomu.
     *
     * Trzy drogi w kolejności rozstrzygania: przeniesienie na tym samym systemie
     * plików (jedna pozycja, bez liczenia), plik albo dowiązanie (wprost do
     * kolejki) i katalog (na stos do przejścia). Cel **zajęty** wyklucza drogę
     * pierwszą, bo nadpisanie jest pytaniem, a nie skutkiem ubocznym `rename()`.
     */
    private function seed(string $source, string $target): void
    {
        $this->current = basename($source);

        if ($this->move && !file_exists($target) && !is_link($target) && self::sameDevice($source, dirname($target))) {
            $this->files[] = new TransferItem(TransferItemKind::Shift, $source, $target);

            return;
        }

        if (is_link($source) || !is_dir($source)) {
            $this->collect($source, $target);

            return;
        }

        $this->dirs[] = [$source, $target];
        $this->toScan[] = [$source, $target];
    }

    private function collect(string $source, string $target): void
    {
        if (is_link($source)) {
            $this->files[] = new TransferItem(TransferItemKind::Link, $source, $target);

            return;
        }

        $size = @filesize($source);
        $size = $size === false ? 0 : $size;
        $this->foundBytes += $size;
        $this->files[] = new TransferItem(TransferItemKind::File, $source, $target, $size);
    }

    /**
     * Kawałek liczenia — ten sam kształt, co przy usuwaniu w kroku 41, wraz z tą
     * samą uwagą: **jeden katalog czyta się w całości**, bo `scandir()` i tak
     * oddaje całą zawartość naraz. Kawałków przybywa od katalogów, nie od plików.
     */
    private function scan(int $budget): TransferState
    {
        while ($budget > 0) {
            $next = array_pop($this->toScan);

            if ($next === null) {
                break;
            }

            [$source, $target] = $next;
            $names = @scandir($source);

            if ($names === false) {
                // Gałąź nieczytelna zatrzymuje pracę **przed** skopiowaniem
                // czegokolwiek: kopia drzewa, którego nie da się przejść do końca,
                // wyglądałaby na kompletną.
                return $this->state = $this->refuse('problem.transfer.unreadable', basename($source));
            }

            foreach ($names as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $child = self::child($source, $name);
                $this->current = $name;
                --$budget;

                if (!is_link($child) && is_dir($child)) {
                    $this->dirs[] = [$child, self::child($target, $name)];
                    $this->toScan[] = [$child, self::child($target, $name)];

                    continue;
                }

                $this->collect($child, self::child($target, $name));
            }
        }

        if ($this->toScan === []) {
            return $this->state = $this->started();
        }

        return $this->state = TransferState::scanning(
            count($this->dirs) + count($this->files),
            $this->foundBytes,
            $this->current,
        );
    }

    /**
     * Koniec liczenia: kolejka układa się w kolejności wymuszonej przez system
     * plików — katalogi, zawartość, pieczątki, sprzątanie po przeniesieniu.
     */
    private function started(): TransferState
    {
        $this->queue = [];

        foreach ($this->dirs as [$source, $target]) {
            $this->queue[] = new TransferItem(TransferItemKind::Directory, $source, $target);
        }

        foreach ($this->files as $item) {
            $this->queue[] = $item;
        }

        foreach (array_reverse($this->dirs) as [$source, $target]) {
            $this->queue[] = new TransferItem(TransferItemKind::Stamp, $source, $target);
        }

        if ($this->move) {
            foreach (array_reverse($this->dirs) as [$source, $target]) {
                $this->queue[] = new TransferItem(TransferItemKind::Drop, $source, $target);
            }
        }

        $this->totalBytes = $this->foundBytes;
        $this->totalEntries = count($this->dirs) + count($this->files);
        $this->index = 0;

        return $this->working();
    }

    /** Kawałek kopiowania: zdejmuje z kolejki, dopóki starczy budżetu w bajtach. */
    private function work(int $budget): TransferState
    {
        $total = count($this->queue);

        while ($budget > 0 && $this->index < $total) {
            $item = $this->queue[$this->index];
            $this->current = basename($item->kind === TransferItemKind::Drop ? $item->source : $item->target);

            $approval = $this->approval($item);

            if ($approval === null) {
                return $this->state;
            }

            if ($approval === false) {
                $this->skipItem($item);
                --$budget;

                continue;
            }

            $budget -= max(1, match ($item->kind) {
                TransferItemKind::Shift => $this->shift($item),
                TransferItemKind::Directory => $this->makeDirectory($item),
                TransferItemKind::File => $this->copyChunk($item, $budget),
                TransferItemKind::Link => $this->makeLink($item),
                TransferItemKind::Stamp => $this->stamp($item),
                TransferItemKind::Drop => $this->drop($item),
            });

            if ($this->state->stage === TransferStage::Failed) {
                return $this->state;
            }
        }

        if ($this->index >= $total) {
            $this->release();

            return $this->state = TransferState::done(
                $this->doneBytes,
                $this->totalBytes,
                $this->doneEntries,
                $this->totalEntries,
            );
        }

        return $this->state = $this->working();
    }

    /**
     * Czy wolno wykonać pozycję: `true` — tak, `false` — pomiń, `null` — trzeba
     * zapytać (stan stoi już na `Colliding`).
     *
     * **Katalog o tej samej nazwie nie jest kolizją, tylko scaleniem**: wejście do
     * istniejącego katalogu niczego nie niszczy, więc pytanie o nie byłoby
     * pytaniem o nic. Kolizją jest wyłącznie wpis, którego zawartość zniknęłaby
     * pod nowym.
     */
    private function approval(TransferItem $item): ?bool
    {
        if ($this->approved || $item->kind === TransferItemKind::Stamp || $item->kind === TransferItemKind::Drop) {
            return true;
        }

        if ($this->in !== null) {
            // Plik zaczęty w poprzednim takcie ma zgodę sprzed pierwszego bajtu.
            return true;
        }

        $target = $item->target;
        $exists = file_exists($target) || is_link($target);

        if (!$exists) {
            return true;
        }

        if ($item->kind === TransferItemKind::Directory && is_dir($target) && !is_link($target)) {
            return true;
        }

        if ($this->skipAll) {
            return false;
        }

        if ($this->overwriteAll) {
            return true;
        }

        $this->state = TransferState::colliding(
            basename($target),
            $this->doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
        );

        return null;
    }

    /**
     * Przeniesienie jedną zmianą nazwy — cała praca w jednym wywołaniu.
     *
     * Odmowa tutaj nie jest już przewidywalna sprawdzeniem: numer urządzenia
     * zgadzał się przy liczeniu, więc `rename()` zawodzi wyłącznie z powodu,
     * o którym system musi powiedzieć sam.
     */
    private function shift(TransferItem $item): int
    {
        error_clear_last();

        if (!$this->clearTarget($item->target)) {
            return 1;
        }

        if (!@rename($item->source, $item->target)) {
            $this->fail($item->target);

            return 1;
        }

        $this->nextItem($item);

        return 1;
    }

    private function makeDirectory(TransferItem $item): int
    {
        if (is_dir($item->target) && !is_link($item->target)) {
            $this->nextItem($item);

            return 1;
        }

        if (!$this->clearTarget($item->target)) {
            return 1;
        }

        error_clear_last();

        if (!@mkdir($item->target)) {
            $this->fail($item->target);

            return 1;
        }

        $this->nextItem($item);

        return 1;
    }

    /**
     * Kawałek pliku. Uchwyty przeżywają takt, więc plik wielkości płyty kopiuje
     * się przez wiele klatek, a każda oddaje pętli tyle samo czasu.
     */
    private function copyChunk(TransferItem $item, int $budget): int
    {
        if ($this->in === null && !$this->open($item)) {
            return 1;
        }

        if (!is_resource($this->in) || !is_resource($this->out)) {
            return 1;
        }

        error_clear_last();
        $copied = @stream_copy_to_stream($this->in, $this->out, max(1, $budget));

        if ($copied === false) {
            $this->release();
            $this->fail($item->target);

            return 1;
        }

        $this->doneBytes += $copied;

        if ($copied > 0 && !feof($this->in)) {
            return $copied;
        }

        return max(1, $copied) + $this->closeFile($item);
    }

    /** Otwarcie obu uchwytów; od tej chwili cel jest plikiem „w połowie”. */
    private function open(TransferItem $item): bool
    {
        error_clear_last();
        $in = @fopen($item->source, 'rb');

        if ($in === false) {
            $this->fail($item->source);

            return false;
        }

        // Dowiązanie w celu trzeba **zdjąć**, a nie pisać przez nie: zapis przez
        // dowiązanie zmieniłby plik leżący zupełnie gdzie indziej. Zwykły plik
        // ucina się sam otwarciem do zapisu.
        if (is_link($item->target) && !$this->clearTarget($item->target)) {
            fclose($in);

            return false;
        }

        $out = @fopen($item->target, 'wb');

        if ($out === false) {
            fclose($in);
            $this->fail($item->target);

            return false;
        }

        $this->in = $in;
        $this->out = $out;
        $this->partial = $item->target;

        return true;
    }

    /** Koniec pliku: prawa, czas, a przy przenoszeniu — usunięcie źródła. */
    private function closeFile(TransferItem $item): int
    {
        if (is_resource($this->in)) {
            fclose($this->in);
        }

        if (is_resource($this->out)) {
            fclose($this->out);
        }

        $this->in = null;
        $this->out = null;
        $this->partial = null;

        self::stampOf($item->source, $item->target);

        if ($this->move) {
            error_clear_last();

            if (!@unlink($item->source)) {
                $this->fail($item->source);

                return 1;
            }
        }

        $this->nextItem($item);

        return 1;
    }

    private function makeLink(TransferItem $item): int
    {
        error_clear_last();
        $to = @readlink($item->source);

        if ($to === false || (!$this->clearTarget($item->target)) || !@symlink($to, $item->target)) {
            if ($this->state->stage !== TransferStage::Failed) {
                $this->fail($item->target);
            }

            return 1;
        }

        if ($this->move && !@unlink($item->source)) {
            $this->fail($item->source);

            return 1;
        }

        $this->nextItem($item);

        return 1;
    }

    /**
     * Prawa i czas katalogu — na końcu pracy, gdy zawartość jest już w środku.
     *
     * Niepowodzenie **przemilczamy** i to jest jedyne takie miejsce w usłudze:
     * kopia jest kompletna, a system plików bez praw dostępu (choćby FAT)
     * odmawiałby tu przy każdym katalogu. Zatrzymanie skończonej pracy z powodu
     * nienadanego bitu byłoby gorsze od nienadanego bitu.
     */
    private function stamp(TransferItem $item): int
    {
        self::stampOf($item->source, $item->target);
        $this->nextItem($item);

        return 1;
    }

    /** Pusty już katalog źródłowy znika — wyłącznie przy przenoszeniu. */
    private function drop(TransferItem $item): int
    {
        error_clear_last();

        if (@rmdir($item->source)) {
            $this->nextItem($item);

            return 1;
        }

        $names = @scandir($item->source);

        // Katalog niepusty po przeniesieniu znaczy, że coś w nim **pominięto** —
        // i wtedy zostaje z tym, co zostało. To nie jest niepowodzenie, tylko
        // skutek odpowiedzi użytkownika.
        if ($names !== false && count($names) > 2) {
            $this->nextItem($item);

            return 1;
        }

        $this->fail($item->source);

        return 1;
    }

    /** Usuwa to, co stoi w celu: plik, dowiązanie albo pusty katalog. */
    private function clearTarget(string $path): bool
    {
        if (!file_exists($path) && !is_link($path)) {
            return true;
        }

        error_clear_last();

        if (is_link($path) || !is_dir($path)) {
            if (@unlink($path)) {
                return true;
            }

            $this->fail($path);

            return false;
        }

        if (@rmdir($path)) {
            return true;
        }

        // Nadpisanie **niepustego** katalogu byłoby usuwaniem drzewa, a to jest
        // czynność z osobnym pytaniem i osobnym portem (krok 41). Praca mówi
        // dlaczego i staje.
        $this->state = $this->refuse('problem.transfer.targetDirectory', basename($path));

        return false;
    }

    /** Pozycja obsłużona: kursor kolejki idzie dalej, zgoda na nadpisanie gaśnie. */
    private function nextItem(TransferItem $item): void
    {
        if ($item->isEntry()) {
            ++$this->doneEntries;
        }

        ++$this->index;
        $this->approved = false;
    }

    /**
     * Pozycja pominięta liczy się jako obsłużona — **wraz ze swoimi bajtami**.
     *
     * Bez tego pasek stanąłby przed końcem i wyglądał na zawieszony: mianownik
     * powstał przy liczeniu, gdy nikt jeszcze nie wiedział, że użytkownik część
     * plików pominie.
     */
    private function skipItem(TransferItem $item): void
    {
        $this->doneBytes += $item->size;
        $this->nextItem($item);
    }

    private function working(): TransferState
    {
        return TransferState::working(
            $this->current,
            $this->doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
        );
    }

    /** @param array<string, string> $parameters */
    private function refuse(string $problemKey, string $name, array $parameters = []): TransferState
    {
        return TransferState::failed(
            $problemKey,
            ['name' => $name, ...$parameters],
            $this->doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
        );
    }

    /** Niepowodzenie z powodem podanym przez system — bez przedrostka z nazwą funkcji. */
    private function fail(string $path): void
    {
        $this->release();
        $this->state = $this->refuse('problem.fileops.failed', basename($path), ['detail' => self::lastError()]);
    }

    /**
     * Zamknięcie uchwytów wraz z **usunięciem pliku zapisanego w połowie**.
     *
     * Jedyne miejsce, które pilnuje, żeby przerwanie nie zostawiło na dysku pliku
     * wyglądającego na gotowy (D79, rozstrzygnięcie 5).
     */
    private function release(): void
    {
        if (is_resource($this->in)) {
            fclose($this->in);
        }

        if (is_resource($this->out)) {
            fclose($this->out);
        }

        $this->in = null;
        $this->out = null;

        if ($this->partial !== null) {
            @unlink($this->partial);
            $this->partial = null;
        }
    }

    /**
     * Zmiana celu pozycji po odpowiedzi „zmień nazwę”.
     *
     * Przepisuje **także wszystko, co leży w środku** zmienianego katalogu: cele
     * pozycji policzono przy liczeniu, więc bez tego zawartość poszłaby pod starą
     * ścieżkę, której nikt już nie tworzy.
     */
    private function retarget(TransferItem $item, string $newName): void
    {
        $old = $item->target;
        $new = self::child(dirname($old), $newName);
        $prefix = $old . '/';
        $queue = [];

        foreach ($this->queue as $position => $next) {
            if ($position === $this->index) {
                $queue[] = $item->toTarget($new);

                continue;
            }

            $queue[] = $position > $this->index && str_starts_with($next->target, $prefix)
                ? $next->toTarget($new . substr($next->target, strlen($old)))
                : $next;
        }

        $this->queue = $queue;
    }

    /** Prawa dostępu i czas zmiany oryginału; właściciela nie — na to trzeba uprawnień, których aplikacja nie ma. */
    private static function stampOf(string $source, string $target): void
    {
        $permissions = @fileperms($source);
        $time = @filemtime($source);

        if ($permissions !== false) {
            @chmod($target, $permissions & 0777);
        }

        if ($time !== false) {
            @touch($target, $time);
        }
    }

    /** Czy wpis i katalog docelowy leżą na tym samym systemie plików. */
    private static function sameDevice(string $source, string $target): bool
    {
        $from = @lstat($source);
        $to = @stat($target);

        return $from !== false && $to !== false && $from['dev'] === $to['dev'];
    }

    private static function child(string $directory, string $name): string
    {
        return ($directory === '/' ? '' : $directory) . '/' . $name;
    }

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
