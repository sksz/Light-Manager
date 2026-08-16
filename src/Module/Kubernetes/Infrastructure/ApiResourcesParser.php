<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * Katalog rodzajów zasobów rozczytany z wypisu `kubectl api-resources -o wide`
 * (krok 52).
 *
 * **Jedyne miejsce w module rozczytujące tekst** — i nie z wyboru: klient 1.25
 * nie umie tu JSON-a, bo `-o` przyjmuje wyłącznie `wide` i `name` (sprawdzone
 * w pomocy klienta przy rozstrzyganiu, D91). Wszystko inne w module idzie
 * `-o json`.
 *
 * **Wiersz rozbiera się wyrażeniem, a nie dzieleniem po spacjach**, i to jest
 * cała trudność tej klasy. Wypis jest wyrównany spacjami, a kolumna `SHORTNAMES`
 * **bywa pusta** — wtedy jej odstęp zlewa się z sąsiednim i podział po dwóch
 * spacjach daje o jedno pole mniej, przesuwając wszystkie pozostałe. Skutek
 * byłby cichy i paskudny: `namespaced` czytane z kolumny `APIVERSION`, czyli
 * połowa katalogu z odwróconą odpowiedzią na pytanie „czy ten zasób mieszka
 * w przestrzeni nazw”.
 *
 * Zamiast tego opieramy się na **niezmiennikach**: czasowniki stoją w nawiasach
 * kwadratowych, a przed nimi — zawsze w tej samej kolejności — nazwa rodzaju,
 * opcjonalne skróty, wersja API i słowo `true` albo `false`. Wyrażenie z leniwą
 * grupą skrótów rozstrzyga niejednoznaczność samo: gdy skrótów nie ma, próba bez
 * nich przechodzi od razu; gdy są, próba bez nich kończy się na `true|false`
 * i wyrażenie sięga po drugą.
 */
final class ApiResourcesParser
{
    /**
     * Wiersz katalogu: nazwa, opcjonalne skróty, wersja API, namespace'owość,
     * nazwa pojedyncza, czasowniki i opcjonalne kategorie.
     */
    private const ROW = '/^(\S+)\s+(?:(\S+)\s+)??(\S+)\s+(true|false)\s+(\S+)\s+\[([^\]]*)\](?:\s+(\S+))?\s*$/';

    /**
     * Rodzaje wyczytane z wypisu — **bez tych, których nie da się wypisać**.
     *
     * Rodzaj bez czasownika `list` (`bindings`, `tokenreviews`, cała rodzina
     * `*reviews`) trafiłby do drzewa jako gałąź, która po rozwinięciu zawsze
     * kończy się błędem. Gałąź, której nie ma, jest od takiej lepsza.
     *
     * @return list<ResourceKind>
     */
    public static function kinds(string $output): array
    {
        $kinds = [];

        foreach (explode("\n", $output) as $line) {
            $kind = self::kindFrom(rtrim($line));

            if ($kind !== null && $kind->isListable()) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }

    private static function kindFrom(string $line): ?ResourceKind
    {
        if (trim($line) === '' || str_starts_with($line, 'NAME')) {
            return null;
        }

        if (preg_match(self::ROW, $line, $matches) !== 1) {
            return null;
        }

        try {
            return ResourceKind::of(
                $matches[1],
                $matches[5],
                self::groupOf($matches[3]),
                $matches[4] === 'true',
                self::verbsOf($matches[6]),
                // Grupa skrótów jest leniwa i opcjonalna, więc przy jej braku
                // przychodzi pustym napisem, a nie brakiem klucza.
                self::shortNamesOf($matches[2]),
            );
        } catch (InvalidClusterNameException) {
            // Rodzaj, którego nazwy nie da się podać z powrotem, jest dla modułu
            // nieosiągalny — a wiersz katalogu nie jest powodem, żeby przerwać
            // czytanie pozostałych. Cisza jest tu prawidłowa: pozycja, której nie
            // pokażemy, nie ma jak wprowadzić w błąd.
            return null;
        }
    }

    /**
     * Grupa z wersji API: `apps/v1` → `apps`, `v1` → grupa pusta (rdzeń).
     *
     * Klienty starsze niż 1.24 wypisywały w tej kolumnie samą grupę, a nie wersję
     * — obie postacie rozbiera to samo cięcie po ukośniku, bo grupa bez wersji
     * ukośnika nie ma.
     */
    private static function groupOf(string $apiVersion): string
    {
        $slash = strpos($apiVersion, '/');

        if ($slash === false) {
            return $apiVersion === 'v1' ? ResourceKind::CORE_GROUP : $apiVersion;
        }

        return substr($apiVersion, 0, $slash);
    }

    /** @return list<string> */
    private static function verbsOf(string $verbs): array
    {
        $trimmed = trim($verbs);

        if ($trimmed === '') {
            return [];
        }

        // Klient rozdziela czasowniki spacją, ale w wypisach starszych wersji
        // trafiał się przecinek — rozbieramy oba, bo koszt jest zerowy.
        return array_values(array_filter(preg_split('/[\s,]+/', $trimmed) ?: []));
    }

    /** @return list<string> */
    private static function shortNamesOf(string $shortNames): array
    {
        $trimmed = trim($shortNames);

        return $trimmed === '' ? [] : array_values(array_filter(explode(',', $trimmed)));
    }
}
