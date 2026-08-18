<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Cztery postacie tunelu SSH (krok 58): nie ma / wstaje / stoi / nie wstał.
 *
 * Liczba jest częścią planu kroku, nie przypadkiem: bez osobnej postaci
 * „nie wstał z powodem" zdania „demon nie odpowiada" i „tunel nie wstał"
 * wyglądałyby identycznie, a wymagają dwóch różnych czynności użytkownika.
 */
enum TunnelStage: string
{
    case None = 'none';

    case Starting = 'starting';

    case Up = 'up';

    case Failed = 'failed';

    public function labelKey(): string
    {
        return 'module.docker.tunnel.' . $this->value;
    }
}
