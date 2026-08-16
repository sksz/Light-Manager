<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\LogStream;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\TextView;

/**
 * Logi kontenera pokazane `TextView`em (krok 51).
 *
 * **Komponent nie czyta** (reguła 11i) i tutaj jest to widać najlepiej:
 * `TextView` dostaje `list<string>` wierszy już zdekodowanych, z zamienionymi
 * znakami sterującymi, i o gnieździe demona ani o ramkach multipleksera nie wie
 * nic. Strumień rozbiera moduł — w `LogStream` i `LogFrameReader`.
 *
 * **Widok trzyma się dna, dopóki użytkownik go stamtąd nie zabierze.** Log
 * płynący ma sens wtedy, gdy widać jego koniec; przewinięcie w górę znaczy
 * jednak „chcę popatrzeć na to miejsce”, więc dopóki użytkownik tam stoi, nowe
 * wiersze nie przeciągają widoku. Powrót na dno przywraca podążanie —
 * i dlatego `End` jest tu klawiszem, który coś realnie robi, a nie skrótem do
 * ostatniego wiersza.
 *
 * Wiersze pominięte z powodu granicy bufora mają **własny wiersz na górze**
 * (D90 nr 3): bufor ucinający po cichu wygląda dokładnie tak samo, jak kontener,
 * który zamilkł.
 */
final class LogPane
{
    /** Ile wierszy przeskakuje `PgUp`/`PgDn` poza wysokością panelu. */
    private const PAGE_MARGIN = 1;

    private int $offset = 0;

    private bool $following = true;

    private int $lastCapacity = 1;

    public function __construct(
        private readonly LogStream $logs,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $lines = $this->lines();
        $this->lastCapacity = max(1, $bounds->rows);

        if ($lines === []) {
            return (new Label($this->emptySentence()))->draw($bounds);
        }

        $count = count($lines);
        $capacity = $this->lastCapacity;

        if ($this->following) {
            $this->offset = max(0, $count - $capacity);
        }

        $this->offset = max(0, min($this->offset, max(0, $count - $capacity)));

        return (new TextView(
            array_slice($lines, $this->offset, $capacity),
            wrap: false,
            // Okno **nie może wystawać poza listę** — `ScrollPosition` pilnuje
            // tego wyjątkiem, a wierszy bywa mniej niż wysokości panelu.
            position: new ScrollPosition($this->offset, min($capacity, $count - $this->offset), $count),
        ))->draw($bounds);
    }

    public function scrollBy(int $delta): void
    {
        $this->offset = max(0, $this->offset + $delta);
        $this->following = false;
    }

    public function pageBy(int $direction): void
    {
        $this->scrollBy($direction * max(1, $this->lastCapacity - self::PAGE_MARGIN));
    }

    /**
     * Powrót na dno przywraca podążanie za strumieniem.
     *
     * Przesunięcia nie liczymy tutaj i nie mamy z czego: liczba widocznych
     * wierszy jest własnością prostokąta, który zna dopiero `draw()`. Podążanie
     * ustawia je samo w najbliższej klatce — ta sama zależność, którą krok 29
     * nazwał „zamówienie przewinięcia czeka na rozliczenie”.
     */
    public function toEnd(): void
    {
        $this->following = true;
    }

    public function toStart(): void
    {
        $this->following = false;
        $this->offset = 0;
    }

    /** Czy widok stoi na dnie — stopka mówi to wprost, bo różnica jest niewidoczna. */
    public function isFollowing(): bool
    {
        return $this->following;
    }

    public function reset(): void
    {
        $this->offset = 0;
        $this->following = true;
    }

    /**
     * Wiersze do pokazania — z wierszem o pominiętych na czele.
     *
     * @return list<string>
     */
    private function lines(): array
    {
        $lines = $this->logs->lines();
        $dropped = $this->logs->droppedLines();

        if ($dropped === 0) {
            return $lines;
        }

        return [
            $this->translator->plural(
                'module.' . DockerSettings::ID . '.logs.dropped',
                $dropped,
            ),
            ...$lines,
        ];
    }

    private function emptySentence(): string
    {
        $problem = $this->logs->problemKey();

        if ($problem !== null) {
            return $this->translator->translate($problem);
        }

        return $this->translator->translate(
            'module.' . DockerSettings::ID . '.logs.' . ($this->logs->isFinished() ? 'ended' : 'waiting'),
        );
    }
}
