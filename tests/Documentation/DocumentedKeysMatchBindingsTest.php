<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\I18n\Catalog;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Tests\Support\DocumentationTree;
use LightManager\Tests\Support\DocumentedCatalogues;
use LightManager\Tests\Support\DocumentedPlaces;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **Spis klawiszy zgadza się z wiązaniami — co do klawisza, miejsca i opisu**
 * (krok 66).
 *
 * To jest miara całego kroku: *usunięcie jednego `KeyBinding`u z kodu czerwieni
 * bramkę, dopóki podręcznik nie zostanie poprawiony*. Klawiszy jest w kodzie
 * blisko dwustu, a w podręczniku 178 wierszy w 29 tabelach — i jedno z drugim
 * rozjeżdżało się dotąd wyłącznie pod czyjąś uwagą.
 *
 * Sprawdzenie idzie **w dwie strony**, bo brak i nadmiar są tym samym błędem:
 *
 * 1. **Każdy wiersz tabeli jest obietnicą, którą miejsce naprawdę składa** —
 *    klawisz z tabeli musi być wśród wiązań tego miejsca, a opis musi się
 *    zgadzać z katalogiem napisów tego języka.
 * 2. **Każde wiązanie miejsca stoi w którejś tabeli jego grupy** — bo klawisz,
 *    który działa i nie jest opisany, jest dokładnie tym rozjazdem, którego
 *    `StatusHintsFlowTest` nie łapie (pilnuje klawiszy **ogłoszonych**, nie
 *    **obsługiwanych**; zobacz pułapkę 10 w przewodniku).
 *
 * Grupa jest tu potrzebna z powodu, który widać w podręczniku gołym okiem:
 * tabela „Panel przełączony na drzewo" wymienia **pięć** klawiszy, a nie
 * szesnaście, i mówi wprost, że reszta działa tak samo jak na liście. Sekcja
 * jest więc **podzbiorem** swojego miejsca, a **suma sekcji grupy** pokrywa sumę
 * jej wiązań.
 *
 * Opis porównuje się **zawieraniem**, bo podręcznik dopisuje do niego warunek
 * („— **gdy** podział jest włączony"), którego katalog nie niesie i nieść nie
 * powinien: pasek stanu pokazuje takie wiązanie tylko wtedy, gdy ono działa.
 */
final class DocumentedKeysMatchBindingsTest extends TestCase
{
    /** Najkrótszy człon opisu, który jeszcze coś znaczy. */
    private const CLAUSE = 8;

    /** Miejsca opisywane wspólnie — sekcja bywa podzbiorem, suma grupy nie. */
    private const GROUPS = [
        'browser' => ['lista-plikow', 'drzewo'],
        'docker' => ['docker-kontenery', 'docker-obrazy', 'docker-logi', 'docker-srodowiska', 'docker-rejestr'],
        'k8s' => ['k8s-zasoby', 'k8s-logi', 'k8s-klastry', 'k8s-nieosiagalny'],
        'ssh' => ['hosty', 'zdalny-katalog'],
        'audio' => ['playlista', 'efekty'],
    ];

    #[DataProvider('sections')]
    public function testEveryDocumentedKeyIsPromisedByItsPlace(string $code, string $document, string $place): void
    {
        $catalog = self::catalogue(Language::from($code));
        $promised = self::promisedBy($place, $catalog);
        $wrong = [];

        foreach (self::rowsOf($document, $place) as $row) {
            $key = $row[0];
            $description = $row[1];

            if ($key === '') {
                continue;
            }

            if (!isset($promised[$key])) {
                $wrong[] = sprintf('„%s" — miejsce nie obiecuje tego klawisza', $key);

                continue;
            }

            $matched = false;

            foreach ($promised[$key] as $text) {
                if (self::says($description, $text)) {
                    $matched = true;

                    break;
                }
            }

            if (!$matched) {
                $wrong[] = sprintf('„%s" — opis „%s" zamiast „%s"', $key, $description, implode(' / ', $promised[$key]));
            }
        }

        self::assertSame([], $wrong, $document . ', spis ' . $place . ': ' . implode('; ', $wrong));
    }

    #[DataProvider('placeGroups')]
    public function testEveryBindingOfAGroupIsDocumented(string $code, string $document, string $group): void
    {
        $catalog = self::catalogue(Language::from($code));
        $documented = [];

        foreach (self::placesOf($group) as $place) {
            foreach (self::rowsOf($document, $place) as $row) {
                $documented[$row[0]][] = $row[1];
            }
        }

        $missing = [];

        foreach (self::placesOf($group) as $place) {
            foreach (self::promisedBy($place, $catalog) as $key => $texts) {
                foreach ($texts as $text) {
                    if (self::mentions($documented[$key] ?? [], $text)) {
                        continue 2;
                    }
                }

                $missing[] = sprintf('%s: „%s" (%s)', $place, $key, implode(' / ', $texts));
            }
        }

        sort($missing);

        self::assertSame([], $missing, $document . ', grupa ' . $group . ' — klawisze bez wiersza: ' . implode('; ', $missing));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function sections(): iterable
    {
        foreach (self::documents() as $code => $document) {
            foreach (array_keys(DocumentedPlaces::all()) as $place) {
                yield $code . ': ' . $place => [$code, $document, $place];
            }
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function placeGroups(): iterable
    {
        foreach (self::documents() as $code => $document) {
            foreach (array_keys(DocumentedPlaces::all()) as $place) {
                $group = self::groupOf($place);

                if ($group === $place || $place === self::placesOf($group)[0]) {
                    yield $code . ': ' . $group => [$code, $document, $group];
                }
            }
        }
    }

    /** @return array<string, string> */
    private static function documents(): array
    {
        return [
            'pl' => 'docs/pl/podrecznik/03-ekran-i-sterowanie.md',
            'en' => 'docs/en/manual/03-screen-and-controls.md',
        ];
    }

    /**
     * Klawisz → opisy, które miejsce z nim wiąże.
     *
     * Opisów bywa dwa, bo ten sam klawisz robi w jednym miejscu dwie rzeczy
     * zależnie od stanu (`Esc` zdejmuje filtr albo zaznaczenie).
     *
     * @return array<string, list<string>>
     */
    private static function promisedBy(string $place, Catalog $catalog): array
    {
        $places = DocumentedPlaces::all();

        self::assertArrayHasKey($place, $places, 'nieznane miejsce: ' . $place);

        $promised = [];

        foreach ($places[$place]() as $binding) {
            self::assertInstanceOf(KeyBinding::class, $binding);

            $text = DocumentedCatalogues::text($catalog, $binding->descriptionKey);
            $promised[$binding->display()][$text] = $text;
        }

        return array_map(array_values(...), $promised);
    }

    /** @param list<string> $cells */
    private static function mentions(array $cells, string $text): bool
    {
        foreach ($cells as $cell) {
            if (self::says($cell, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Czy wiersz podręcznika mówi to, co katalog napisów.
     *
     * Zawieranie, a nie równość — i to w dwóch postaciach. Podręcznik **rozwija**
     * podpowiedź z paska stanu, bo ma na to miejsce: „uzupełnij nazwę; przy
     * pustym wierszu — przełącz na kwerendy i z powrotem" wobec „uzupełnij
     * nazwę, przy pustym wierszu: tryb". Wystarczy więc **pierwszy człon**
     * napisu z katalogu, byle był na tyle długi, żeby coś znaczył. Czego ta
     * reguła nie przepuści: klawisza opisanego czynnością, której nie robi —
     * a to jest rozjazd, o który w tym teście chodzi.
     */
    private static function says(string $documented, string $text): bool
    {
        if (mb_stripos($documented, $text) !== false) {
            return true;
        }

        $parts = preg_split('/[,;:—]/u', $text);
        $clause = trim($parts === false ? $text : ($parts[0] ?? $text));

        return mb_strlen($clause) >= self::CLAUSE && mb_stripos($documented, $clause) !== false;
    }

    /**
     * Wiersze sekcji: klawisz i opis, bez ozdobników markdowna.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function rowsOf(string $document, string $place): array
    {
        $lists = DocumentationTree::lists($document);
        $name = 'klawisze:' . $place;

        self::assertArrayHasKey($name, $lists, $document . ' — brak spisu ' . $name);

        return array_map(
            static fn (array $row): array => [
                // Wiersz opisujący **zwyczaj, a nie klawisz** („znaki: wpisywanie
                // nazwy") stoi w kursywie i nie ma czego porównywać z wiązaniem.
                preg_match('/^\*[^*]+\*$/u', trim($row[0])) === 1 ? '' : DocumentationTree::plain($row[0]),
                DocumentationTree::plain($row[1]),
            ],
            $lists[$name]['rows'],
        );
    }

    private static function groupOf(string $place): string
    {
        foreach (self::GROUPS as $group => $places) {
            if (in_array($place, $places, true)) {
                return $group;
            }
        }

        return $place;
    }

    /** @return list<string> */
    private static function placesOf(string $group): array
    {
        return self::GROUPS[$group] ?? [$group];
    }

    private static function catalogue(Language $language): Catalog
    {
        return DocumentedCatalogues::of($language, DocumentedPlaces::app()->modules->accepted());
    }
}
