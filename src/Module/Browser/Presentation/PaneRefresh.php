<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Module\Browser\Application\UseCase\OpenStartingDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\ReloadDirectoryUseCase;
use LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;

/**
 * Odświeżenie paneli po **własnej** zmianie na dysku (krok 41, wydzielone
 * w kroku 42).
 *
 * Rachunek powstał razem z pierwszymi czynnościami zapisu i mieszkał w
 * `EntryOperations`, dopóki miał jednego wołającego. Kopiowanie jest drugim — i to
 * takim, który zmienia **dwa** katalogi naraz — więc reguła z kroku 31 („dwa
 * rachunki tej samej rzeczy rozjadą się przy pierwszej poprawce”, `EntrySize`)
 * każe wyjąć go do osobnej klasy, zamiast przepisywać.
 *
 * Panel odświeża się w dwóch przypadkach: patrzy dokładnie na zmieniony katalog
 * albo leży **w środku** niego. Drugi jest tym, o którym łatwo zapomnieć:
 * przeniesienie katalogu wyciąga panelowi ziemię spod nóg, a wtedy ponowny odczyt
 * się nie udaje i panel wchodzi do najbliższego czytelnego wyżej — tą samą drogą,
 * którą aplikacja otwiera katalog startowy.
 *
 * Drzewa tracą zapamiętane gałęzie bezwarunkowo: zmiana mogła dotyczyć dowolnego
 * poziomu, a gałęzie wracają po jednej na takt (D46), więc zapomnienie ich
 * kosztuje najwyżej kilka odczytów rozwiniętych węzłów.
 */
final class PaneRefresh
{
    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly ReloadDirectoryUseCase $reload,
        private readonly OpenStartingDirectoryUseCase $fallback,
    ) {
    }

    /**
     * @param ?string $select nazwa, na której ma stanąć kursor w panelu patrzącym
     *                        na zmieniony katalog; `null` znaczy „ta, która jest”
     */
    public function after(DirectoryPath $changed, ?string $select = null): void
    {
        foreach ($this->panes->all() as $pane) {
            $path = $pane->directory()->path();

            if ($path->equals($changed)) {
                $this->reloadPane($pane, $select);

                continue;
            }

            if (self::liesInside($path, $changed)) {
                $this->reloadPane($pane, null);
            }
        }

        $this->panes->forgetBranches();
    }

    /**
     * Dwa katalogi naraz — dla przeniesienia, które ubywa z jednego i przybywa
     * w drugim.
     *
     * Kursor idzie **za przeniesionym wpisem**, czyli do katalogu docelowego;
     * w źródłowym zostaje tam, gdzie stał, a gdy wpisu już nie ma, `Directory`
     * sprowadza go na początek listy.
     */
    public function afterBoth(DirectoryPath $from, DirectoryPath $to, ?string $select = null): void
    {
        $this->after($from);
        $this->after($to, $select);
    }

    private function reloadPane(BrowserState $pane, ?string $select): void
    {
        $hidden = $pane->showsHiddenEntries();

        try {
            $pane->refresh($this->reload->execute($pane->directory(), $hidden, $select), $select);
        } catch (DirectoryNotReadableException) {
            $pane->enter($this->fallback->execute($pane->directory()->path(), $hidden));
        }
    }

    /** Czy ścieżka leży wewnątrz katalogu — rachunek tekstowy, bez pytania dysku. */
    private static function liesInside(DirectoryPath $path, DirectoryPath $root): bool
    {
        return str_starts_with($path->value, $root->isRoot() ? '/' : $root->value . '/');
    }
}
