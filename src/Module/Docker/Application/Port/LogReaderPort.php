<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

/**
 * Strumień logów rozbierany na wiersze (krok 51).
 *
 * Kontrakt istnieje z tego samego powodu, co `DockerCatalogPort`: rozbieranie
 * ramek multipleksera jest wiedzą o **cudzym formacie**, czyli należy do
 * `Infrastructure`, a stan strumienia jest daną warstwy `Application` i nie ma
 * prawa znać ani jednej klasy stamtąd (reguła 4).
 *
 * Czytnik jest **stanowy z konieczności**: porcja przychodzi z gniazda
 * w kawałkach dowolnej wielkości, więc ramka bywa przecięta w połowie nagłówka,
 * a wiersz — w połowie zdania.
 */
interface LogReaderPort
{
    /**
     * Dokłada porcję bajtów i oddaje wiersze, które się z niej domknęły.
     *
     * @return list<string>
     */
    public function push(string $chunk): array;

    /**
     * Oddaje wiersz niedokończony i zapomina o nim — wołane, gdy strumień się
     * skończył.
     */
    public function flush(): ?string;
}
