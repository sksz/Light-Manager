<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Postęp przesyłu wraz z tym, w którą stronę idzie (krok 54).
 *
 * Para „stan pracy + kierunek", bo kwerenda `ssh.transfer` ma powiedzieć jedno
 * i drugie, a `RemoteTransferState` z założenia mówi o **postępie**, który przy
 * pobieraniu i wysyłaniu liczy się tak samo. Migawka: powstaje przy pytaniu
 * i niczego nie posuwa.
 */
final readonly class RemoteTransferProgress
{
    public function __construct(
        public RemoteTransferState $state,
        public TransferDirection $direction,
        /** Nazwa pliku, o który toczy się praca; pusta, gdy nic nie trwa. */
        public string $name,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self(RemoteTransferState::idle(), TransferDirection::Download, '');
    }
}
