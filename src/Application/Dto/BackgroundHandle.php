<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Pokwitowanie zamówionej pracy tłowej — tożsamość, nie proces.
 *
 * Usługa prowadzi **jedną pracę naraz** (rozstrzygnięcie nr 1 kroku 26), więc
 * uchwyt mógłby na pierwszy rzut oka nie istnieć wcale: port pytany bez
 * argumentu oddawałby stan jedynej pracy, dokładnie jak `ChecksumPort`. Uchwyt
 * jest tu mimo to i zarabia na siebie w jednej, konkretnej sytuacji:
 * **mechanizm jest rdzeniowy**, więc pracę zamawia dowolny moduł, a kolejne
 * zamówienie przerywa poprzednie. Bez uchwytu moduł, którego pracę wyparto,
 * zobaczyłby cudzy stan i wziąłby go za swój — pokazałby wynik `du`
 * w miejscu, w którym liczył coś zupełnie innego.
 *
 * Z uchwytem ta pomyłka jest niemożliwa: port pytany o uchwyt, który przestał
 * być bieżący, oddaje `Idle` — czyli „ta praca już nie trwa i nie ma wyniku”.
 *
 * Numer nadaje usługa i rośnie on w nieskończoność, bo zerowanie licznika
 * sprawiłoby, że stary uchwyt trafiłby kiedyś na nową pracę.
 */
final readonly class BackgroundHandle
{
    public function __construct(public int $id)
    {
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
