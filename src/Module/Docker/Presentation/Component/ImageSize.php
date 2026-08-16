<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\Docker\Application\DockerSettings;

/**
 * Rozmiar obrazu w postaci „1,4 GB" (wydzielony w kroku 54).
 *
 * Trzeci taki rachunek w projekcie, po `EntrySize` (krok 31) i `RemoteSize`
 * (krok 50), i wydzielony z tego samego powodu, co tamte dwa: **drugi
 * użytkownik**. Do kroku 54 liczył to jeden `ImagePane`, więc metoda prywatna
 * wystarczała; kwerenda `docker.images` jest drugim i dwa zapisy tej samej
 * liczby rozjechałyby się przy pierwszej zmianie progu albo przecinka.
 *
 * Powtórzeniem wobec `EntrySize` i `RemoteSize` **jest** i jest to powtórzenie
 * świadome — granica 15e: powtarzamy **pojęcie** (rozmiar dla oka), tanie i bez
 * skutków ubocznych, a nie mechanizm rdzenia. Różnica wobec tamtych dwóch jest
 * przy tym prawdziwa, a nie tylko formalna: jednostki idą tu **przez katalog
 * napisów**, bo tak liczył je moduł Dockera od kroku 51.
 */
final class ImageSize
{
    private const UNIT_STEP = 1024;

    /** @var list<string> */
    private const UNITS = ['size.bytes', 'size.kib', 'size.mib', 'size.gib'];

    /** Rozmiar nieznany albo ujemny oddaje **pusty napis**, a nie „0 B". */
    public static function of(TranslatorPort $translator, ?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return '';
        }

        $value = (float) $bytes;
        $unit = 0;

        while ($value >= self::UNIT_STEP && $unit < count(self::UNITS) - 1) {
            $value /= self::UNIT_STEP;
            ++$unit;
        }

        return $translator->translate(
            'module.' . DockerSettings::ID . '.' . self::UNITS[$unit],
            ['value' => $translator->number($value, $unit === 0 ? 0 : 1)],
        );
    }
}
