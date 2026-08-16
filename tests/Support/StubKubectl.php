<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Module\Kubernetes\Application\KubectlCall;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;

/**
 * `kubectl`, który nigdy nie powstaje jako proces (krok 52).
 *
 * **Kryterium ukończenia kroku brzmi wprost: żaden test nie wywołuje `kubectl`.**
 * Powodów jest kilka i każdy wystarczyłby sam: maszyna uruchamiająca testy nie
 * musi mieć klienta, klaster bywa cudzy, a `kubectl delete` uruchomiony przez
 * pomyłkę w teście kasuje rzeczy, które do testu nie należą.
 *
 * Atrapa oddaje **odpowiedzi z góry ustawione**, a przy braku odpowiedzi —
 * `Done` z pustym wyjściem, bo tak właśnie wygląda `kubectl` pytany o rodzaj,
 * którego w klastrze nie ma. Zapamiętuje przy tym **wszystkie wywołania**, żeby
 * dało się sprawdzić rzecz, której inaczej sprawdzić się nie da: **że pytań jest
 * tyle, ile miało być**. Moduł pytający klaster co klatkę wygląda tak samo, jak
 * moduł pytający raz — dopóki nie policzy się wywołań.
 */
final class StubKubectl implements KubectlPort
{
    /** @var list<KubectlCall> wywołania w kolejności zamówienia */
    public array $calls = [];

    /** @var list<int> limity czasu podane przy zamawianiu */
    public array $timeouts = [];

    public int $stopCount = 0;

    /** @var array<int, BackgroundState> stan każdej pracy — numer uchwytu → stan */
    private array $states = [];

    /** @var list<BackgroundState> odpowiedzi ustawione z góry, po kolei */
    private array $scripted = [];

    private int $lastId = 0;

    /** Odpowiedź na kolejne wywołanie — kolejność zapisu jest kolejnością wydawania. */
    public function willAnswer(BackgroundState $state): self
    {
        $this->scripted[] = $state;

        return $this;
    }

    /** Wygodna postać najczęstszej odpowiedzi: powodzenie z podanym wyjściem. */
    public function willReturn(string $output, int $exitCode = 0, string $errorOutput = ''): self
    {
        return $this->willAnswer(BackgroundState::done($output, $exitCode, $errorOutput));
    }

    public function start(KubectlCall $call, int $timeoutSeconds): BackgroundHandle
    {
        $this->calls[] = $call;
        $this->timeouts[] = $timeoutSeconds;

        $handle = new BackgroundHandle(++$this->lastId);
        $this->states[$handle->id] = array_shift($this->scripted) ?? BackgroundState::done('', 0);

        return $handle;
    }

    public function poll(BackgroundHandle $handle): BackgroundState
    {
        return $this->states[$handle->id] ?? BackgroundState::idle();
    }

    public function stop(BackgroundHandle $handle): void
    {
        if (isset($this->states[$handle->id])) {
            ++$this->stopCount;
            unset($this->states[$handle->id]);
        }
    }

    /**
     * Podmienia stan trwającej pracy — atrapa strumienia logów.
     *
     * Bez tego nie da się sprawdzić arytmetyki `LogStream`: bajtów, które
     * wypadły z bufora, i wierszy doklejanych porcjami.
     */
    public function feed(BackgroundHandle $handle, BackgroundState $state): void
    {
        $this->states[$handle->id] = $state;
    }

    /** Ostatnie wywołanie w postaci argumentów — do sprawdzenia, o co zapytano. */
    public function lastArguments(): string
    {
        $call = $this->calls === [] ? null : $this->calls[count($this->calls) - 1];

        return $call === null ? '' : implode(' ', $call->arguments);
    }
}
