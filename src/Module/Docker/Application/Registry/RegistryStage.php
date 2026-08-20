<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Gdzie stoi rozmowa z rejestrem obrazów (krok 61, etap 2).
 *
 * Etapów jest więcej niż przy demonie i to jest cała różnica między tymi dwoma
 * rozmówcami: demon odpowiada **z jednego obiegu**, rejestr uwierzytelnia
 * **dwustopniowo**, więc „czekam” znaczy tu trzy różne rzeczy i użytkownik ma
 * prawo wiedzieć, na którą czeka — zwłaszcza gdy któraś się nie uda.
 */
enum RegistryStage: string
{
    /** Nikt jeszcze nie pytał. */
    case Idle = 'idle';

    /** Pierwszy obieg: pytanie bez tokenu (`GET /v2/…`). */
    case Asking = 'asking';

    /** Drugi obieg: pytanie o token pod adresem z `WWW-Authenticate`. */
    case Authenticating = 'authenticating';

    /** Trzeci obieg: to samo pytanie, tym razem podpisane tokenem. */
    case Retrying = 'retrying';

    case Done = 'done';

    case Failed = 'failed';

    public function isWorking(): bool
    {
        return $this === self::Asking || $this === self::Authenticating || $this === self::Retrying;
    }

    /** Klucz katalogu z nazwą etapu — kwerenda niesie go w każdym wierszu (11w). */
    public function labelKey(): string
    {
        return 'module.docker.registry.stage.' . $this->value;
    }
}
