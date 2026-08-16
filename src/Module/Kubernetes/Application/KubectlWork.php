<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;

/**
 * Jedno wywołanie `kubectl` od zamówienia do wyniku (krok 52).
 *
 * Powstało dlatego, że modułowi tej samej rzeczy potrzeba **w sześciu
 * miejscach** — konteksty, wersje, katalog rodzajów, lista, opis i czynność —
 * a każde z nich robiłoby inaczej to samo: trzymało uchwyt, doglądało, odróżniało
 * „jeszcze trwa” od „skończyło się", zapominało uchwyt i pilnowało, żeby nie
 * odebrać cudzego wyniku. Sześć kopii tej sekwencji to sześć miejsc na tę samą
 * pomyłkę.
 *
 * **Mechanizmu rdzenia nie powtarza** (reguła 15e): uchwyty, doglądanie
 * i przerywanie zostają w porcie, tutaj jest wyłącznie **kolejność wywołań**.
 *
 * Wynik oddaje się **raz**. Praca skończona przestaje istnieć dla tej klasy
 * w chwili, w której ktoś jej wynik odebrał — inaczej ekran zgłaszałby to samo
 * zdanie trzydzieści razy na sekundę, dopóki nie zamówiono by następnej pracy.
 */
final class KubectlWork
{
    private ?BackgroundHandle $handle = null;

    public function __construct(private readonly KubectlPort $kubectl)
    {
    }

    /**
     * Zamawia wywołanie, **porzucając poprzednie**.
     *
     * Porzucenie jest tu prawidłowe, bo jedna praca tej klasy to jedno pytanie
     * użytkownika: gdy pyta o listę drugi raz, odpowiedź na pierwsze pytanie
     * przestaje go interesować. Cudzych prac to nie dotyka — uchwyt wskazuje
     * wyłącznie własną (rozbudowa portu z kroku 51).
     */
    public function begin(KubectlCall $call, int $timeoutSeconds): void
    {
        $this->stop();
        $this->handle = $this->kubectl->start($call, $timeoutSeconds);
    }

    /**
     * Posuwa pracę i oddaje wynik — **raz, i tylko gdy jest**.
     *
     * `null` znaczy „nic się nie zmieniło”: praca trwa, nie ma jej wcale albo
     * wynik już odebrano.
     */
    public function advance(): ?BackgroundState
    {
        if ($this->handle === null) {
            return null;
        }

        $state = $this->kubectl->poll($this->handle);

        if ($state->stage === BackgroundStage::Running) {
            return null;
        }

        $this->handle = null;

        // `Idle` na uchwyt, który trzymaliśmy, znaczy „pracy już nie ma i nie ma
        // wyniku” — zdjął ją ktoś inny albo wypadła z zapasu portu. Cudzej
        // ciszy nie wolno wziąć za własną odpowiedź.
        return $state->stage === BackgroundStage::Idle ? null : $state;
    }

    public function isWorking(): bool
    {
        return $this->handle !== null;
    }

    public function stop(): void
    {
        if ($this->handle === null) {
            return;
        }

        $this->kubectl->stop($this->handle);
        $this->handle = null;
    }
}
