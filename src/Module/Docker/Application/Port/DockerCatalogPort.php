<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Domain\ValueObject\Container;
use LightManager\Module\Docker\Domain\ValueObject\Image;

/**
 * Odpowiedź demona rozczytana na obiekty domeny (krok 51).
 *
 * Port istnieje z powodu **granicy warstw, a nie wygody**: `DockerApiPort` jest
 * transportem i oddaje bajty, a stan listy — klasa warstwy `Application` — nie
 * ma prawa znać ani jednej klasy z `Infrastructure` (reguła 4). Bez tego
 * kontraktu `ContainerList` musiałby zawołać `DockerJsonReader` po nazwie, czyli
 * sięgnąć przez granicę po to, żeby dowiedzieć się, jak demon nazywa swoje pola.
 *
 * Rozdzielenie transportu od rozczytywania ma przy tym drugą zaletę, widoczną
 * w testach: stan listy da się sprawdzić na czytniku oddającym gotowe obiekty,
 * bez ani jednego bajtu JSON-a — a sam czytnik na próbkach bajtów, bez ani
 * jednego pytania do demona.
 */
interface DockerCatalogPort
{
    /**
     * Kontenery z odpowiedzi `GET /containers/json`.
     *
     * **Wpis, którego nie da się rozczytać, wypada z listy i nie przerywa
     * czytania** — reszta odpowiedzi jest w porządku, a wyjątek zabrałby
     * użytkownikowi wszystkie zdrowe wpisy z powodu jednego chorego.
     *
     * @return list<Container>
     */
    public function containers(string $body): array;

    /**
     * Obrazy z odpowiedzi `GET /images/json`.
     *
     * @return list<Image>
     */
    public function images(string $body): array;

    /**
     * Zdanie, którym demon tłumaczy odmowę — albo `null`, gdy nic nie powiedział.
     *
     * Zdanie jest po angielsku i takie zostaje: to **cytat z cudzego programu**,
     * a nie napis aplikacji. Ta sama granica, co przy strumieniu błędów `sftp`
     * w kroku 49.
     */
    public function problem(string $body): ?string;
}
