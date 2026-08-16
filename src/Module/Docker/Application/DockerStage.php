<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Etap pojedynczego pytania do demona (krok 51).
 *
 * Cztery wartości, te same, co w pracy tłowej rdzenia — i to nie jest zbieg
 * okoliczności: rozmowa z demonem jest z punktu widzenia klatki **tą samą
 * rzeczą, co proces potomny**. Trwa dłużej niż klatka, nie wolno na nią czekać,
 * a jedyne, co o niej wiadomo w danej chwili, to etap.
 */
enum DockerStage
{
    /** Nikt o nic nie pytał albo pytanie już posprzątano. */
    case Idle;

    /** Pytanie w drodze — odpowiedź jeszcze nie doszła w całości. */
    case Running;

    /** Odpowiedź doszła; treść i kod stanu HTTP są do odczytania. */
    case Done;

    /** Rozmowy nie było albo się urwała — powód stoi w kluczu katalogu. */
    case Failed;
}
