<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Terminal\DeviceAttributesParser;
use LightManager\Infrastructure\Terminal\TerminalService;

/**
 * Mierzy wypchnięcie gotowej klatki na terminal.
 *
 * To jedyna faza potoku, której nie da się zmierzyć bez prawdziwego terminala,
 * i jedyna, przy której narzędzie **nie ma prawa podstawić namiastki**: zapis do
 * `/dev/null` albo do pliku zmierzyłby prędkość jądra, a nie odbiorcy. Gdy
 * terminala nie ma, `BenchmarkCli` pomija tę fazę i mówi o tym wprost, zamiast
 * pokazywać liczbę, która nic nie znaczy.
 *
 * Zapis idzie przez `TerminalService`, a nie wprost na `STDOUT` — ta sama
 * zasada, którą krok 07 narzucił wykrywaniu Sixela (D17): STDOUT ma jednego
 * właściciela. Stamtąd też bierze się liczba wywołań `fwrite()`.
 */
final class TransferMeter
{
    private const CURSOR_HOME = "\e[H";

    /** Primary Device Attributes — pytanie, na które terminal musi odpowiedzieć. */
    private const DEVICE_ATTRIBUTES_QUERY = "\e[c";

    private const RESPONSE_TIMEOUT_MICROSECONDS = 1000000;

    private const POLL_INTERVAL_MICROSECONDS = 1000;

    public function __construct(
        private readonly TerminalService $terminal,
    ) {
    }

    /**
     * @param int $iterations ile razy wypchnąć tę samą klatkę
     */
    public function measure(string $blob, int $iterations, bool $withRoundTrip): TransferResult
    {
        $writeSamples = [];
        $chunkSamples = [];
        $roundTripSamples = [];

        for ($index = 0; $index < max(1, $iterations); ++$index) {
            $started = microtime(true);
            $chunks = $this->terminal->write(self::CURSOR_HOME . $blob);
            $written = microtime(true);

            $writeSamples[] = ($written - $started) * 1000;
            $chunkSamples[] = $chunks;

            if (!$withRoundTrip) {
                continue;
            }

            $roundTrip = $this->roundTrip($started);

            if ($roundTrip !== null) {
                $roundTripSamples[] = $roundTrip;
            }
        }

        return new TransferResult(
            strlen($blob),
            Measurement::fromSamples($writeSamples),
            Measurement::fromIntegerSamples($chunkSamples),
            $roundTripSamples === [] ? null : Measurement::fromSamples($roundTripSamples),
        );
    }

    /**
     * Czas od rozpoczęcia zapisu klatki do odpowiedzi DA1 wysłanej zaraz po niej.
     *
     * Odpowiedź bywa poprzedzona bajtami, które nie należą do niej (klawisz
     * wciśnięty w trakcie pomiaru) — parser szuka wzorca w całym buforze, a
     * resztę oddajemy z powrotem, żeby nie zniknęła.
     *
     * @return float|null `null`, gdy terminal nie odpowiedział w oknie czasowym
     */
    private function roundTrip(float $startedAt): ?float
    {
        $parser = new DeviceAttributesParser();

        $this->terminal->write(self::DEVICE_ATTRIBUTES_QUERY);

        $deadline = microtime(true) + self::RESPONSE_TIMEOUT_MICROSECONDS / 1000000;
        $response = '';

        while (microtime(true) < $deadline) {
            $response .= $this->terminal->readRawBytes(self::POLL_INTERVAL_MICROSECONDS);

            if ($parser->isComplete($response)) {
                $answered = microtime(true);
                $this->terminal->pushBackBytes($parser->strip($response));

                return ($answered - $startedAt) * 1000;
            }
        }

        return null;
    }
}
