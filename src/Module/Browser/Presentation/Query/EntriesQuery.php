<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Presentation\BrowserPanes;
use LightManager\Module\Browser\Presentation\Component\EntrySize;

/**
 * `browser.entries [panel]` — zawartość katalogu widocznego w panelu.
 *
 * **Jedyna uczciwa droga do listy katalogu dla kogokolwiek spoza tego modułu.**
 * Rdzeń wypisu katalogu nie umie i umieć nie ma: `FileOperationsPort` zna zmianę
 * nazwy, nowy katalog i usunięcie, a czytanie katalogu należy do
 * `DirectoryRepositoryInterface` w dziedzinie przeglądarki (D42). Moduł, który
 * zapytałby dysk sam, byłby drugą implementacją wypisu — z własnym sortowaniem,
 * własnym ukrywaniem plików i własnym pojęciem tego, co jest wpisem.
 *
 * Kwerenda oddaje **to, co panel pokazuje**: po filtrze, po sortowaniu i wraz
 * z ukrytymi, jeśli użytkownik je włączył. Odpowiedź „wszystko, co leży
 * w katalogu” byłaby odpowiedzią na inne pytanie i wymagałaby drugiego odczytu
 * dysku w środku klatki.
 *
 * Pokolenie jest **ulotne** i to jest wybór, nie zaniedbanie: katalog zmienia
 * się przy wejściu, odświeżeniu, filtrze, zaznaczeniu i przy każdym ruchu
 * kursora, więc licznik trzeba by bić w kilkunastu miejscach — a przeoczone
 * uderzenie znaczy listę, która nie zauważyła zmiany. Cena ulotności jest tu
 * bliska zeru: `ask()` zapamiętuje wskaźnik na katalog, a wiersze — jedyna
 * kosztowna rzecz przy pięciu tysiącach wpisów — budują się **leniwie**, więc
 * właściciel czytający obiekt nie płaci za nie ani razu.
 */
final class EntriesQuery implements QueryInterface
{
    /**
     * **Rozmiar wraca jako napis z jednostką** — `78,9 kB`, nie `80847`
     * (poprawka z odbioru kroku 53).
     *
     * Liczba bajtów jest przy gigabajtach nie do przeczytania, a kwerendę czyta
     * także człowiek — w oknie kwerend. Zapis liczy **`EntrySize`**, czyli ta
     * sama klasa, którą podają rozmiar lista wpisów i drzewo (krok 31): trzeci
     * rachunek tej samej rzeczy rozjechałby się z tamtymi przy pierwszej zmianie
     * progu albo przecinka.
     *
     * **Cena jest zapisana, a nie przemilczana**: napisu nie da się porównać
     * liczbowo, więc moduł szukający „plików większych niż gigabajt" nie zrobi
     * tego wierszem tej kwerendy. Tak samo jak przy czasie, pierwszeństwo ma
     * czytelność — a odbiorca, który będzie potrzebował liczby, dostanie ją
     * osobnym polem wtedy, gdy się pojawi, a nie na zapas.
     *
     * Katalog oddaje **pusty napis**, a nie `0 B`: rozmiaru katalogu wraz
     * z zawartością nie zna nikt poza `du` (krok 26), a zero byłoby odpowiedzią
     * nieprawdziwą. Lista wpisów robi w tym miejscu dokładnie to samo.
     */
    /**
     * Czas zmiany jako **data i godzina czasu lokalnego** — `2026-08-09 09:14:24`
     * (poprawka z odbioru kroku 53).
     *
     * Do tej poprawki było to `modifiedAt` w sekundach epoki, czyli dana
     * pierwotna w najczystszej postaci — i dana, której człowiek w oknie kwerend
     * nie umie przeczytać. Napis zostaje daną pierwotną (D40 P5), a przy tym
     * porównuje się leksykograficznie tak samo, jak liczba numerycznie: pola idą
     * od największego do najmniejszego.
     *
     * Kształt jest **rozstrzygnięciem użytkownika** i ma dwie części: bez
     * przesunięcia strefy (aplikacja pokazuje czas maszyny, na której działa)
     * i **ze spacją zamiast litery `T`** — tak samo, jak podaje go kolumna
     * „Zmieniony" na liście wpisów obok. Jedna aplikacja mówi o czasie jednym
     * głosem.
     *
     * Nieznany czas oddaje **pusty napis**, a nie `-1`: skoro pole jest napisem,
     * pustka jest jego naturalnym „nie wiem" — tak samo, jak przy nazwie
     * zaznaczenia w kontekście sesji.
     */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.entries';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.entries';
    }

    public function arguments(): array
    {
        return [PaneArgument::declaration()];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $index = PaneArgument::from($input);
        $directory = $index === null
            ? $this->panes->focused()->directory()
            : $this->panes->pane($index)[0]->directory();
        $selected = $directory->selectedEntry();

        $translator = $this->translator;

        return QueryResult::owned(
            BrowserSettings::ID,
            $directory,
            static function () use ($directory, $selected, $translator): array {
                $rows = [];

                foreach ($directory->entries() as $entry) {
                    $rows[] = self::describe($entry, $selected !== null && $selected->name === $entry->name, $translator);
                }

                return $rows;
            },
        );
    }

    /** @return array<string, string|int|bool> */
    private static function describe(Entry $entry, bool $selected, TranslatorPort $translator): array
    {
        return [
            'name' => $entry->name,
            'kind' => strtolower($entry->type->name),
            'size' => $entry->isDirectory() ? '' : EntrySize::of($translator, $entry->sizeInBytes),
            'modified' => $entry->modifiedAt === null ? '' : date(self::TIMESTAMP_FORMAT, $entry->modifiedAt),
            'hidden' => $entry->isHidden(),
            'selected' => $selected,
        ];
    }
}
