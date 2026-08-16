<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterVersion;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;

/**
 * Konteksty z `kubeconfig` i wersje obu stron (krok 52).
 *
 * Dwa wypisy, jedna klasa, bo odpowiadają na dwie połowy tego samego pytania:
 * **gdzie jestem i z czym rozmawiam**. Oba przychodzą jako JSON, więc obowiązuje
 * tu wszystko, co przy `ResourceJsonParser` — z jedną różnicą, która przesądziła
 * o kształcie tego kroku: `config view` **nie potrzebuje klastra**. Czyta plik
 * na dysku, więc odpowiada także wtedy, gdy nie ma czego zapytać po sieci — i to
 * dlatego stan „nie ma bieżącego kontekstu” daje się narysować jako miejsce
 * z wyborem, a nie jako błąd połączenia.
 */
final class ClusterInfoParser
{
    /**
     * Nazwy kontekstów z pliku konfiguracyjnego.
     *
     * Kontekst o nazwie, której nie da się podać z powrotem `kubectl`owi,
     * **wypada z listy w ciszy**. Nie jest to ukrywanie problemu: pozycja, po
     * której wybraniu polecenie i tak by nie powstało, byłaby ofertą bez pokrycia,
     * a plik konfiguracyjny należy do użytkownika i nie nam go poprawiać.
     *
     * @return list<ContextName>
     */
    public static function contexts(string $json): array
    {
        $decoded = ClusterJson::decode($json);

        if ($decoded === null) {
            return [];
        }

        $contexts = [];

        foreach (ClusterJson::objects($decoded, 'contexts') as $context) {
            $name = ClusterJson::text($context, 'name');

            if ($name === null) {
                continue;
            }

            try {
                $contexts[] = ContextName::of($name);
            } catch (InvalidClusterNameException) {
                continue;
            }
        }

        return $contexts;
    }

    /**
     * Kontekst wskazany w pliku jako bieżący — `null`, gdy żaden nim nie jest.
     *
     * **To nie jest stan awaryjny**, tylko zwykły: tak wygląda `kubeconfig`
     * maszyny projektu (jeden kontekst `ca-dev`, kolumna `CURRENT` pusta),
     * i tak samo wygląda plik zaraz po dopisaniu pierwszego klastra.
     */
    public static function currentContext(string $json): ?ContextName
    {
        $decoded = ClusterJson::decode($json);
        $current = $decoded === null ? null : ClusterJson::text($decoded, 'current-context');

        if ($current === null || $current === '') {
            return null;
        }

        try {
            return ContextName::of($current);
        } catch (InvalidClusterNameException) {
            return null;
        }
    }

    /**
     * Przestrzeń nazw przypisana do kontekstu w pliku konfiguracyjnym.
     *
     * Pusty napis znaczy „kontekst jej nie podaje”, a wtedy Kubernetes przyjmuje
     * `default` — ale rozstrzyga o tym warstwa aplikacji, nie parser. Tutaj
     * oddajemy **to, co w pliku stoi**.
     */
    public static function namespaceOf(string $json, ContextName $context): ?string
    {
        $decoded = ClusterJson::decode($json);

        if ($decoded === null) {
            return null;
        }

        foreach (ClusterJson::objects($decoded, 'contexts') as $entry) {
            if (ClusterJson::text($entry, 'name') !== $context->value) {
                continue;
            }

            $namespace = ClusterJson::text($entry, 'context', 'namespace');

            return $namespace === '' ? null : $namespace;
        }

        return null;
    }

    /**
     * Wersje klienta i serwera.
     *
     * Czytane **z wyjścia, nie z kodu wyjścia**, i to jest tu warunek
     * poprawności: bez osiągalnego klastra `kubectl version -o json` kończy się
     * kodem niezerowym, a mimo to wypisuje `clientVersion`. Traktowanie kodu jako
     * rozstrzygnięcia zabrałoby użytkownikowi połowę odpowiedzi dokładnie wtedy,
     * gdy jest mu potrzebna — przy klastrze, z którym coś jest nie tak.
     */
    public static function versions(string $json): ?ClusterVersion
    {
        $decoded = ClusterJson::decode($json);

        if ($decoded === null) {
            return null;
        }

        $client = ClusterJson::text($decoded, 'clientVersion', 'gitVersion');

        if ($client === null) {
            return null;
        }

        return ClusterVersion::of($client, ClusterJson::text($decoded, 'serverVersion', 'gitVersion'));
    }
}
