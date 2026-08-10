<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Najdłuższy wspólny początek napisów — to, co dopisuje `Tab`.
 *
 * Osobno, bo uzupełnianie działa na dwóch zbiorach: nazwach komend (pyta o nie
 * rejestr) i wartościach argumentów (pyta o nie sama komenda). Reguła jest w obu
 * ta sama i nie ma powodu istnieć dwa razy.
 */
final class Prefix
{
    private function __construct()
    {
    }

    /** @param list<string> $values */
    public static function shared(array $values): string
    {
        if ($values === []) {
            return '';
        }

        $shared = $values[0];

        foreach ($values as $value) {
            $shared = self::sharedStart($shared, $value);
        }

        return $shared;
    }

    private static function sharedStart(string $first, string $second): string
    {
        $limit = min(mb_strlen($first), mb_strlen($second));
        $shared = '';

        for ($position = 0; $position < $limit; ++$position) {
            $character = mb_substr($first, $position, 1);

            if ($character !== mb_substr($second, $position, 1)) {
                break;
            }

            $shared .= $character;
        }

        return $shared;
    }
}
