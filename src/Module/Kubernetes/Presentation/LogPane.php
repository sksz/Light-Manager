<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\LogStream;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\TextView;

/**
 * Logi poda pokazane `TextView`em (krok 52).
 *
 * Bliźniak `LogPane` z modułu Dockera i **świadome powtórzenie** w granicach
 * reguły 15e: powtarza się tu *pojęcie* (widok strumienia wierszy, podążanie za
 * dnem, wiersz o pominiętych), a nie mechanizm rdzenia — komponent, praca tłowa
 * i przewijanie pochodzą stamtąd, gdzie stały. Wyniesienie tego do rdzenia
 * byłoby dziś wspólnym miejscem dla dwóch modułów, a granica ilościowa z D88
 * mówi wprost: **trzeci** taki użytkownik uruchamia przegląd, nie drugi.
 *
 * **Widok trzyma się dna, dopóki użytkownik go stamtąd nie zabierze** — log
 * płynący ma sens wtedy, gdy widać jego koniec, ale przewinięcie w górę znaczy
 * „chcę popatrzeć tutaj”. `End` przywraca podążanie i dlatego jest klawiszem,
 * który coś realnie robi.
 *
 * Bajty utracone przez przesunięcie bufora mają **własny wiersz na górze**.
 * Różnica wobec modułu Dockera jest tu wymowna: tam liczyło się pominięte
 * **wiersze**, bo bufor był wierszowy; tutaj rdzeń przesuwa bufor bajtowy, więc
 * uczciwie umiemy powiedzieć wyłącznie, ile **bajtów** przepadło — i tak właśnie
 * mówimy, zamiast zgadywać liczbę wierszy.
 */
final class LogPane
{
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

    public function toEnd(): void
    {
        $this->following = true;
    }

    public function toStart(): void
    {
        $this->following = false;
        $this->offset = 0;
    }

    public function isFollowing(): bool
    {
        return $this->following;
    }

    public function reset(): void
    {
        $this->offset = 0;
        $this->following = true;
    }

    /** @return list<string> */
    private function lines(): array
    {
        $lines = $this->logs->lines();
        $lost = $this->logs->lostBytes();

        if ($lost === 0) {
            return $lines;
        }

        return [
            $this->translator->plural('module.' . KubernetesSettings::ID . '.logs.lost', $lost, ['count' => $lost]),
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
            'module.' . KubernetesSettings::ID . '.logs.' . ($this->logs->isOpen() ? 'waiting' : 'closed'),
        );
    }
}
