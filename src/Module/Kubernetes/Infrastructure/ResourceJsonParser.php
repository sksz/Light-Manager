<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Module\Kubernetes\Application\ResourceRow;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * Wypis `kubectl get … -o json` rozczytany na wiersze listy (krok 52).
 *
 * Leży w `Infrastructure` i **za portem**, bo rozczytywanie cudzych formatów
 * należy do tej warstwy (reguła 11t, ustalona przy JSON-ie demona Dockera).
 * Oddaje wyłącznie dane warstwy aplikacji — `ResourceRow` — więc stan listy nie
 * ma jak poznać ani jednej klasy stąd.
 *
 * **Wypis pusty nie jest awarią.** Klaster odpowiada `{"items":[]}` na pytanie
 * o rodzaj, którego akurat nie ma w tej przestrzeni nazw, i jest to najzwyklejsza
 * odpowiedź świata — pusty katalog też nie jest błędem odczytu.
 */
final class ResourceJsonParser
{
    /**
     * Wiersze listy.
     *
     * Kolejność zostaje **taka, jaką podał klaster** — a podaje alfabetycznie po
     * nazwie w obrębie przestrzeni. Sortowanie własne byłoby drugą odpowiedzią na
     * pytanie, na które serwer już odpowiedział, i rozjeżdżałoby się z tym, co
     * użytkownik widzi w terminalu obok.
     *
     * @return list<ResourceRow>
     */
    public static function rows(string $json, ResourceKind $kind): array
    {
        $decoded = ClusterJson::decode($json);

        if ($decoded === null) {
            return [];
        }

        $rows = [];

        foreach (self::itemsOf($decoded) as $item) {
            $name = ClusterJson::text($item, 'metadata', 'name');

            if ($name === null || $name === '') {
                continue;
            }

            $created = ClusterJson::text($item, 'metadata', 'creationTimestamp');

            $rows[] = new ResourceRow(
                $name,
                ClusterJson::text($item, 'metadata', 'namespace'),
                $created === null ? null : ClusterJson::timestamp($created),
                ResourceColumnPacks::valuesFor($kind, $item),
                ResourceColumnPacks::toneFor($kind, $item),
            );
        }

        return $rows;
    }

    /**
     * Jeden zasób w całości — treść prawego panelu.
     *
     * @return array<string, mixed>|null
     */
    public static function object(string $json): ?array
    {
        return ClusterJson::decode($json);
    }

    /**
     * Nazwy kontenerów poda — wybór przy otwieraniu logów.
     *
     * Kontenery inicjujące idą **razem ze zwykłymi**, bo to właśnie ich logów
     * szuka się, gdy pod nie wstaje; kolejność zostaje ta z zasobu, czyli
     * inicjujące przed zwykłymi.
     *
     * @param  array<string, mixed> $item
     * @return list<string>
     */
    public static function containersOf(array $item): array
    {
        $names = [];

        foreach (['initContainers', 'containers'] as $section) {
            foreach (ClusterJson::objects($item, 'spec', $section) as $container) {
                $name = ClusterJson::text($container, 'name');

                if ($name !== null && $name !== '') {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Klucze sekretu wraz z **rozmiarem wartości**, nigdy z wartością.
     *
     * Rozmiar liczymy po odkodowaniu base64, bo to on mówi coś użytkownikowi
     * („hasło ma 12 bajtów”), a nie długość zapisu. Wartość zostaje w zasobie
     * i wychodzi stamtąd wyłącznie na jawny klawisz (D91 nr 10).
     *
     * @param  array<string, mixed> $item
     * @return array<string, int>
     */
    public static function secretSizesOf(array $item): array
    {
        $sizes = [];

        foreach (ClusterJson::map($item, 'data') as $key => $encoded) {
            $decoded = base64_decode($encoded, true);
            $sizes[$key] = $decoded === false ? strlen($encoded) : strlen($decoded);
        }

        return $sizes;
    }

    /**
     * Wartość jednego klucza sekretu, odkodowana.
     *
     * `null` znaczy „takiego klucza nie ma”; wartość, której nie da się
     * odkodować, oddajemy **taką, jaka przyszła** — sekret założony spoza
     * `kubectl` bywa zapisany inaczej, niż każe schemat, a pokazanie surowego
     * zapisu mówi więcej niż odmowa.
     *
     * @param array<string, mixed> $item
     */
    public static function secretValueOf(array $item, string $key): ?string
    {
        $encoded = ClusterJson::map($item, 'data')[$key] ?? null;

        if ($encoded === null) {
            return null;
        }

        $decoded = base64_decode($encoded, true);

        return $decoded === false ? $encoded : $decoded;
    }

    /**
     * Pozycje listy — także wtedy, gdy klaster oddał **jeden** zasób.
     *
     * `kubectl get pods` oddaje listę, `kubectl get pods/web` — sam zasób.
     * Rozróżnia je obecność klucza `items`, a nie pole `kind`, bo `kind` przy
     * liście bywa `List`, `PodList` albo `Table`, zależnie od wersji klienta.
     *
     * @param  array<string, mixed>       $decoded
     * @return list<array<string, mixed>>
     */
    private static function itemsOf(array $decoded): array
    {
        if (array_key_exists('items', $decoded)) {
            return ClusterJson::objects($decoded, 'items');
        }

        return ClusterJson::text($decoded, 'metadata', 'name') === null ? [] : [$decoded];
    }
}
