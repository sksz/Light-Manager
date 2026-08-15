<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Etapy przesyłu (krok 50).
 *
 * Cztery, a nie pięć, które ma rdzeniowy `TransferStage`, i różnica jest
 * dokładnie tam, gdzie różni się droga: **liczenia nie ma**, bo rozmiary są
 * znane przed pracą — zdalne z wypisu `sftp ls -l`, lokalne ze `stat`.
 *
 * Sprzątania zdalnej połówki tu **nie ma i być nie powinno**, choć jest kolejnym
 * procesem potomnym: użytkownik dostaje zdanie o przerwaniu od razu, a nie po
 * następnym obiegu sieci, więc stan mówi wtedy „przerwane" i mówi prawdę.
 * Sprzątanie posuwa takt modułu obok tego stanu (`RemoteTransferService`).
 */
enum RemoteTransferStage
{
    /** Nic nie trwa. */
    case Idle;

    /** Potomek przenosi plik; stan odświeża `advance()`. */
    case Working;

    /** Praca stanęła na pytaniu o zajętą nazwę i czeka na odpowiedź. */
    case Colliding;

    /** Praca skończona — w całości albo przerwana po którymś z plików. */
    case Done;

    /** Praca stanęła na niepowodzeniu, którego powód niesie stan. */
    case Failed;
}
