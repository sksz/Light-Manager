<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Audio\Application\Port\AudioPort;

/**
 * Odtwarzacz bez dźwięku: zapamiętuje, o co go poproszono.
 *
 * Powód jest ten sam, co przy `StubBackgroundProcess`, tylko stawka inna: test
 * uruchamiający prawdziwy silnik audio zacząłby **grać muzykę na maszynie, na
 * której akurat biegnie** — a przy okazji postawiłby wątek, którego PHPUnit nie
 * ma jak posprzątać. Prawdziwej usłudze zostaje to, czego atrapą sprawdzić się
 * nie da, i tego w testach nie ma wcale: samego grania.
 *
 * Stan „gra / nie gra” jest tu prawdziwy, bo na nim stoi cały przełącznik
 * `audio.music` — atrapa udająca zawsze ciszę nie odróżniłaby uruchomienia od
 * zatrzymania.
 */
final class StubAudio implements AudioPort
{
    /** @var list<array{path: string, volume: int, loop: bool}> prośby o granie, w kolejności */
    public array $played = [];

    /** @var list<int> głośności podane przez `useVolume()` */
    public array $volumes = [];

    public int $stopCount = 0;

    public int $shutdownCount = 0;

    private bool $playing = false;

    public function __construct(
        private readonly bool $available = true,
        /** Powód, dla którego granie się nie udaje; `null` — udaje się zawsze. */
        private readonly ?string $problem = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function play(string $path, int $volume, bool $loop): ?string
    {
        $this->played[] = ['path' => $path, 'volume' => $volume, 'loop' => $loop];

        if ($this->problem !== null) {
            return $this->problem;
        }

        $this->playing = true;

        return null;
    }

    public function stop(): void
    {
        ++$this->stopCount;
        $this->playing = false;
    }

    public function isPlaying(): bool
    {
        return $this->playing;
    }

    public function useVolume(int $volume): void
    {
        $this->volumes[] = $volume;
    }

    public function shutdown(): void
    {
        ++$this->shutdownCount;
        $this->playing = false;
    }

    /**
     * Utwór doszedł do końca — **sam z siebie, bez niczyjego udziału**.
     *
     * Od kroku 45 to jest jedyne zdarzenie, którego playlista wypatruje w takcie,
     * i zarazem jedyne, którego atrapa nie umiałaby udać przez `stop()`: tamto
     * znaczy pauzę, a to koniec grania. Rozróżnienia pilnuje `PlaylistPlayer`,
     * więc test musi umieć wywołać oba osobno.
     */
    public function finish(): void
    {
        $this->playing = false;
    }
}
