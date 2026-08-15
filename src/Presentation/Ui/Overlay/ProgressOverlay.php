<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\RunsWork;

/**
 * Okno pracy dłuższej od klatki: co się właśnie dzieje, ile z tego zrobiono
 * i pasek postępu (krok 41).
 *
 * Pierwsze okno w projekcie, które **działa samo** — deklaruje `RunsWork`, więc
 * pętla pyta je raz na takt, a ono posuwa pracę o kawałek i zamyka się, gdy
 * praca się skończy. Do kroku 41 każde okno czekało na klawisz.
 *
 * O tym, co robi, wie **wyłącznie tyle, ile widać**: dostaje klucz tytułu i daną
 * `WorkProgress` — wiersz treści, wykonane, całość. Nie zna ani plików, ani
 * usuwania, ani portu, z którego ta dana przyszła, i to jest warunek, na jakim
 * stoi w rdzeniu: kopiowanie z kroku 42 i kosz z kroku 44 mają wziąć to samo
 * okno, nie napisać drugiego.
 *
 * Trzy domknięcia zamiast trzech metod kontraktu (D56): **kawałek pracy**
 * (`$step`), **koniec** (`$onFinished` — i to on rozstrzyga, czy po pracy staje
 * kolejne okno, jak pytanie z policzoną liczbą wpisów) oraz **przerwanie**
 * (`$onCancelled`). Okno nie wie, co uruchamia; wie tylko, jak to pokazać.
 *
 * **Pasek pokazuje się dopiero wtedy, gdy jest co pokazać.** Praca, która nie zna
 * swojej całości (liczenie zawartości katalogu), dostaje samą nazwę zmieniającą
 * się co klatkę — a nie pasek wypełniony „na oko” ani wędrujący, który mówiłby
 * to samo mniej dokładnie (D75, rozstrzygnięcie 10).
 */
final class ProgressOverlay implements OverlayInterface, RunsWork
{
    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    private const PADDING_COLUMNS = 2;

    /** Obwódka, tytuł, wiersz treści, obwódka. */
    private const CHROME_ROWS = 4;

    /** Szerokość, na jaką okno się otwiera, gdy tytuł i treść są krótsze. */
    private const ROOM_COLUMNS = 44;

    private WorkProgress $progress;

    /**
     * @param string                              $titleKey    klucz katalogu, nie napis
     * @param array<string, string>               $parameters  dane do podstawienia w tytule
     * @param WorkProgress                        $progress    stan pracy w chwili otwarcia okna
     * @param Closure(): WorkProgress             $step        kawałek pracy; oddaje stan po nim
     * @param Closure(WorkProgress): OverlayOutcome $onFinished co zrobić po skończonej pracy
     * @param Closure(WorkProgress): ?Message     $onCancelled przerwanie klawiszem `Esc`
     */
    public function __construct(
        private readonly string $titleKey,
        private readonly array $parameters,
        WorkProgress $progress,
        private readonly Closure $step,
        private readonly Closure $onFinished,
        private readonly Closure $onCancelled,
        private readonly TranslatorPort $translator,
    ) {
        $this->progress = $progress;
    }

    public function id(): string
    {
        return 'progress';
    }

    public function bounds(int $rows, int $columns): Rect
    {
        $wanted = self::CHROME_ROWS + ($this->progress->fraction() === null ? 0 : 1);
        $height = min($wanted, max(1, $rows - self::MARGIN_ROWS));
        $width = min(
            max(mb_strlen($this->title()), self::ROOM_COLUMNS) + 2 * self::PADDING_COLUMNS,
            max(1, $columns - self::MARGIN_COLUMNS),
        );

        return new Rect(
            max(0, intdiv($rows - $height, 2)),
            max(0, intdiv($columns - $width, 2)),
            $height,
            $width,
        );
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->rows < 2 || $bounds->columns < 2) {
            return [];
        }

        $primitives = (new Dialog($this->title(), [$this->progress->current]))->draw($bounds);
        $fraction = $this->progress->fraction();
        $row = $bounds->row + 3;

        if ($fraction === null || $row >= $bounds->bottom()) {
            return $primitives;
        }

        // Licznik idzie **w środek paska**, nie obok niego: pasek na całą
        // szerokość okna z pustym środkiem marnowałby wiersz, a liczba procent
        // dokłada się sama (krok 23).
        $bar = new ProgressBar(
            $fraction,
            $this->counter(),
            translator: $this->translator,
        );

        foreach ($bar->draw($bounds->inset(0, self::PADDING_COLUMNS)->line(3)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::Escape], 'progress.key.cancel', 'progress.key.cancel.short'),
        ];
    }

    /**
     * Klawisz do tego okna prawie nie należy: `Esc` przerywa pracę, a wszystko
     * inne idzie do klawiszy globalnych. Pisanie w oknie, które właśnie zmienia
     * dysk, nie ma czego zmienić.
     */
    public function handle(KeyPress $key): OverlayOutcome
    {
        if ($key->key !== Key::Escape) {
            return OverlayOutcome::ignored();
        }

        return OverlayOutcome::close(($this->onCancelled)($this->progress));
    }

    public function advance(): OverlayOutcome
    {
        $this->progress = ($this->step)();

        if ($this->progress->running) {
            return OverlayOutcome::stay();
        }

        return ($this->onFinished)($this->progress);
    }

    /**
     * „7 z 120” — dwie liczby, bo procent dokłada sam pasek.
     *
     * Praca licząca w czymś innym niż sztuki podaje licznik **gotowym napisem**
     * (krok 42): okno nie ma jak wiedzieć, że 12914688 należy zapisać jako
     * „12,3 MB”, a zgadywanie jednostki z wielkości liczby byłoby zgadywaniem.
     */
    private function counter(): string
    {
        if ($this->progress->counter !== '') {
            return $this->progress->counter;
        }

        return $this->translator->translate('progress.counter', [
            'done' => $this->translator->number((float) $this->progress->done),
            'total' => $this->translator->number((float) ($this->progress->total ?? 0)),
        ]);
    }

    private function title(): string
    {
        return $this->translator->translate($this->titleKey, $this->parameters);
    }
}
