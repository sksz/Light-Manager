<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Wynik porównania jednego zrzutu z wzorcem.
 *
 * Cztery odpowiedzi zamiast dwóch, bo „niezgodny” i „nie ma z czym porównać”
 * znaczą co innego, a rozmiar inny niż we wzorcu nie jest regresją wyglądu,
 * tylko innym pomiarem. Zwijanie tego do `bool` kazałoby czytelnikowi zgadywać.
 */
enum SnapshotVerdict: string
{
    /** Różnica mieści się w progu. */
    case Match = 'match';

    /** Różnica przekracza próg — obraz różnicy zapisany obok wzorca. */
    case Differs = 'differs';

    /** Wzorca nie ma; zapisz go `--png-save`. */
    case Missing = 'missing';

    /** Wzorzec ma inny rozmiar płótna — porównywać nie ma czego z czym. */
    case Resized = 'resized';

    /** Wzorzec powstał przy innej konfiguracji (motyw, paleta, siatka). */
    case Incomparable = 'incomparable';

    public function labelKey(): string
    {
        return 'bench.image.verdict.' . $this->value;
    }

    /**
     * Czy narzędzie ma zakończyć się niepowodzeniem.
     *
     * Brak wzorca nim **nie jest** — to prośba o `--png-save`, a nie regresja.
     * Inna konfiguracja też nie: wzorzec liczbowy w tej samej sytuacji mówi
     * „nieporównywalne” i też nie krzyczy o regresji.
     */
    public function isFailure(): bool
    {
        return $this === self::Differs || $this === self::Resized;
    }
}
