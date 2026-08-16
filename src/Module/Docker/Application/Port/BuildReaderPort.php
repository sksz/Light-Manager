<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\BuildMessage;

/**
 * Strumień postępu budowy rozbierany na zdania (krok 51).
 *
 * Trzeci kontrakt tego modułu istniejący **wyłącznie po to, żeby warstwa
 * aplikacji nie znała cudzego formatu** — po katalogu odpowiedzi i czytniku
 * logów. Budowa oddaje strumień obiektów JSON, po jednym na wiersz, i wiedza
 * o tym należy do `Infrastructure`.
 */
interface BuildReaderPort
{
    /**
     * Dokłada porcję bajtów i oddaje zdania, które się z niej domknęły.
     *
     * @return list<BuildMessage>
     */
    public function push(string $chunk): array;
}
