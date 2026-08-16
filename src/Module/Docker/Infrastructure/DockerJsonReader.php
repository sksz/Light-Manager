<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Module\Docker\Application\Port\DockerCatalogPort;
use LightManager\Module\Docker\Domain\Exception\InvalidContainerIdException;
use LightManager\Module\Docker\Domain\Exception\InvalidImageRefException;
use LightManager\Module\Docker\Domain\ValueObject\Container;
use LightManager\Module\Docker\Domain\ValueObject\ContainerId;
use LightManager\Module\Docker\Domain\ValueObject\ContainerState;
use LightManager\Module\Docker\Domain\ValueObject\Image;
use LightManager\Module\Docker\Domain\ValueObject\ImageRef;

/**
 * Odpowiedzi demona zamienione na obiekty domeny (krok 51).
 *
 * Cała wiedza o **kształcie JSON-a** siedzi tutaj i nigdzie indziej: nazwy pól
 * (`Names`, `RepoTags`, `Labels`), ich osobliwości i to, czego demon nie
 * gwarantuje. Panel dostaje gotowe obiekty i o `RepoDigests` nie słyszał.
 *
 * Cztery osobliwości odpowiedzi, wszystkie sprawdzone na żywym demonie przed
 * napisaniem tej klasy:
 *
 * - **nazwa kontenera przychodzi listą i z wiodącym ukośnikiem** (`/nazwa`) —
 *   lista, bo w czasach starych powiązań kontener miewał ich kilka; ukośnik, bo
 *   nazwa jest ścieżką w przestrzeni nazw demona;
 * - **`Containers` w liście obrazów jest zawsze `-1`** i znaczy „nie liczyłem”,
 *   a nie „żaden”; sprawdzone trzema wariantami zapytania, więc pola tego **nie
 *   czytamy wcale** — powód stoi przy `Image`;
 * - **obraz bez `RepoTags`** jest zwykłym stanem (osierocony przez nowszą
 *   budowę), a nie uszkodzeniem;
 * - **etykieta `com.docker.compose.project`** przychodzi razem z listą, więc
 *   przynależność do projektu nie kosztuje ani jednego pytania więcej.
 *
 * **Wpis, którego nie da się rozczytać, wypada z listy — i nie przerywa jej
 * czytania.** Kontener bez identyfikatora nie jest kontenerem, ale reszta listy
 * jest w porządku; wyjątek rzucony w środku pętli zabrałby użytkownikowi
 * dwadzieścia dziewięć zdrowych wpisów z powodu jednego chorego.
 */
final class DockerJsonReader implements DockerCatalogPort
{
    /**
     * Lista kontenerów z `GET /containers/json`.
     *
     * @return list<Container>
     */
    public function containers(string $body): array
    {
        $containers = [];

        foreach (self::decodeList($body) as $entry) {
            $container = self::container($entry);

            if ($container !== null) {
                $containers[] = $container;
            }
        }

        return $containers;
    }

    /**
     * Lista obrazów z `GET /images/json`.
     *
     * @return list<Image>
     */
    public function images(string $body): array
    {
        $images = [];

        foreach (self::decodeList($body) as $entry) {
            $image = self::image($entry);

            if ($image !== null) {
                $images[] = $image;
            }
        }

        return $images;
    }

    /**
     * Zdanie, którym demon tłumaczy odmowę.
     *
     * Odpowiedź o kodzie 4xx niesie `{"message":"…"}` i jest to **jedyne
     * miejsce**, w którym demon mówi, czego mu się nie spodobało. Zdanie jest po
     * angielsku i takie zostaje: to cytat z cudzego programu, a nie napis
     * aplikacji (ta sama granica, co przy strumieniu błędów `sftp` w kroku 49).
     */
    public function problem(string $body): ?string
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        $message = $decoded['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }

    /** @param array<string, mixed> $entry */
    private static function container(array $entry): ?Container
    {
        $id = $entry['Id'] ?? null;

        if (!is_string($id)) {
            return null;
        }

        try {
            $containerId = ContainerId::of($id);
            $image = ImageRef::of(is_string($entry['Image'] ?? null) ? $entry['Image'] : $id);
        } catch (InvalidContainerIdException|InvalidImageRefException) {
            return null;
        }

        $labels = is_array($entry['Labels'] ?? null) ? $entry['Labels'] : [];
        $project = $labels['com.docker.compose.project'] ?? null;

        return new Container(
            $containerId,
            self::name($entry['Names'] ?? null) ?? $containerId->short(),
            $image,
            ContainerState::of(is_string($entry['State'] ?? null) ? $entry['State'] : ''),
            is_string($entry['Status'] ?? null) ? $entry['Status'] : '',
            self::ports($entry['Ports'] ?? null),
            is_int($entry['Created'] ?? null) ? $entry['Created'] : null,
            is_string($project) && $project !== '' ? $project : null,
        );
    }

    /** @param array<string, mixed> $entry */
    private static function image(array $entry): ?Image
    {
        $id = $entry['Id'] ?? null;

        if (!is_string($id)) {
            return null;
        }

        try {
            $reference = ImageRef::of($id);
        } catch (InvalidImageRefException) {
            return null;
        }

        return new Image(
            $reference,
            self::tags($entry['RepoTags'] ?? null),
            is_int($entry['Size'] ?? null) ? $entry['Size'] : null,
            is_int($entry['Created'] ?? null) ? $entry['Created'] : null,
        );
    }

    /** Pierwsza nazwa bez wiodącego ukośnika; `null`, gdy demon jej nie podał. */
    private static function name(mixed $names): ?string
    {
        if (!is_array($names)) {
            return null;
        }

        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                return ltrim($name, '/');
            }
        }

        return null;
    }

    /**
     * Wypisy portów gotowe do pokazania.
     *
     * Port bez `PublicPort` **nie jest wystawiony na zewnątrz** i pokazujemy go
     * samą liczbą wewnętrzną: to różnica, którą użytkownik chce widzieć, bo
     * rozstrzyga, czy da się tam wejść przeglądarką.
     *
     * @return list<string>
     */
    private static function ports(mixed $ports): array
    {
        if (!is_array($ports)) {
            return [];
        }

        $printed = [];

        foreach ($ports as $port) {
            if (!is_array($port) || !is_int($port['PrivatePort'] ?? null)) {
                continue;
            }

            $protocol = is_string($port['Type'] ?? null) ? $port['Type'] : 'tcp';
            $public = $port['PublicPort'] ?? null;
            $printed[] = is_int($public)
                ? $public . '->' . $port['PrivatePort'] . '/' . $protocol
                : $port['PrivatePort'] . '/' . $protocol;
        }

        // Ten sam port bywa wystawiony na kilku adresach (IPv4 i IPv6), a wypis
        // powtórzony dwa razy nie mówi nic ponad wypis podany raz.
        return array_values(array_unique($printed));
    }

    /** @return list<string> */
    private static function tags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $named = [];

        foreach ($tags as $tag) {
            // `<none>:<none>` jest sposobem, w jaki demon mówi „bez nazwy”, a nie
            // nazwą — pokazany wprost wyglądałby jak obraz o dziwacznej etykiecie.
            if (is_string($tag) && $tag !== '' && !str_contains($tag, '<none>')) {
                $named[] = $tag;
            }
        }

        return $named;
    }

    /**
     * Odpowiedź rozłożona na listę wpisów; pusta lista, gdy nie da się jej
     * rozczytać.
     *
     * @return list<array<string, mixed>>
     */
    private static function decodeList(string $body): array
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return [];
        }

        $entries = [];

        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
