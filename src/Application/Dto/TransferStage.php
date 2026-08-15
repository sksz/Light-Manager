<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Etapy kopiowania i przenoszenia (krok 42).
 *
 * Bliźniak `RemovalStage` z kroku 41 i różni się od niego dokładnie jednym
 * etapem, wymuszonym przez to, że praca **tworzy** wpisy zamiast je kasować:
 * `Colliding` — przystanek na pytanie „w celu już coś takiego jest, co z tym
 * zrobić”. Tam `Ready` był przystankiem na jedno pytanie przed pracą, tu
 * `Colliding` bywa przystankiem wielokrotnym, w środku pracy.
 */
enum TransferStage
{
    /** Nic się nie dzieje — praca nie zaczęta albo zapomniana. */
    case Idle;

    /**
     * Chodzenie po drzewie: praca liczy, ile wpisów i ile bajtów przybędzie.
     *
     * Etap istnieje dlatego, że pasek postępu ma mówić prawdę od pierwszego
     * skopiowanego bajtu (D79, rozstrzygnięcie 3). Bez niego mianownik rósłby
     * w trakcie, a pasek potrafiłby się cofnąć.
     */
    case Scanning;

    /** Kopiowanie po kawałku; przy przenoszeniu — kopiowanie wraz z usuwaniem źródła. */
    case Working;

    /** W celu istnieje wpis o tej nazwie — praca stoi i czeka na odpowiedź (`resolve()`). */
    case Colliding;

    /** Skończone: w całości albo przerwane odpowiedzią „przerwij”. */
    case Done;

    /** Praca stanęła na przeszkodzie, której nie umie ominąć; powód jest w stanie. */
    case Failed;
}
