<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Ekran, który przy każdym otwarciu zaczyna od początku.
 *
 * Dotyczy okien, do których się zagląda — ustawień i pomocy: wejście na nie ma
 * postawić kursor na pierwszej pozycji, a nie tam, gdzie stał poprzednio.
 * Przeglądarka plików celowo tego nie robi, bo powrót do niej ma pokazać listę
 * dokładnie tam, gdzie ją zostawiono.
 */
interface Resettable
{
    public function reset(): void;
}
