<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\ResourceCache;
use LightManager\Module\Kubernetes\Application\ResourceRow;
use LightManager\Module\Kubernetes\Application\RowTone;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Infrastructure\ResourceColumnPacks;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Container\Span;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Lista zasobów jednego rodzaju — prawy panel, gdy kursor stoi na gałęzi
 * rodzaju (krok 52).
 *
 * **Kolumny zależą od rodzaju i biorą się z pakietów** (D91 nr 4): nazwa i wiek
 * są zawsze, reszta pochodzi z `ResourceColumnPacks`. Rodzaj spoza pakietów —
 * czyli każdy CRD — pokazuje więc dwie kolumny i nie jest to usterka, tylko cena
 * odrzucenia tabeli drukowanej przez serwer, zapisana w rozstrzygnięciu.
 *
 * **Przestrzeni nazw w kolumnach nie ma**, choć jest w zasobie: lista pokazuje
 * jedną przestrzeń naraz (tę z sesji), więc kolumna powtarzałaby w każdym
 * wierszu to, co stoi w nagłówku ekranu. Kolumna, która nigdy się nie zmienia
 * w obrębie widoku, zabiera miejsce nazwie i nie odpowiada na żadne pytanie.
 */
final class ResourcePane
{
    private const NAME_MINIMUM = 18;

    private const AGE_WIDTH = 10;

    public function __construct(
        private readonly ResourceCache $cache,
        private readonly TranslatorPort $translator,
        private readonly ScrollWindow $window,
    ) {
    }

    /**
     * @param ?int $cursor numer wiersza pod kursorem; `null` — kursor stoi w drzewie
     *
     * @return list<Primitive>
     */
    public function draw(Rect $bounds, ResourceKind $kind, ?int $cursor = null): array
    {
        $rows = $this->cache->rowsOf($kind);

        if ($rows === []) {
            return (new Label($this->emptySentence($kind)))->draw($bounds);
        }

        $count = count($rows);
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $offset = $this->window->keepVisible($cursor, $count, $capacity);

        return (new Table(
            $this->columns($kind),
            $this->rows(array_slice($rows, $offset, max(1, $capacity)), $kind),
            $cursor === null ? null : $cursor - $offset,
            $this->window->position($count, $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    public function capacityOf(Rect $bounds): int
    {
        return max(1, Table::capacityOf($bounds, withHeader: true));
    }

    /** @return list<Column> */
    private function columns(ResourceKind $kind): array
    {
        $columns = [
            new Column(Span::flexible(self::NAME_MINIMUM), Align::Left, $this->text('column.name')),
        ];

        // Kolumny własne rodzaju ustępują **od prawej**: im dalej od nazwy, tym
        // chętniej znikają w wąskim oknie. Wiek odchodzi pierwszy, bo jest tym,
        // czego szuka się najrzadziej — ta sama kolejność, co w liście
        // kontenerów z kroku 51.
        $order = count(ResourceColumnPacks::columnsFor($kind)) + 1;

        foreach (ResourceColumnPacks::columnsFor($kind) as $column) {
            $columns[] = new Column(
                Span::rigid($column->width(), $order--),
                Align::Left,
                $this->translator->translate($column->labelKey()),
            );
        }

        $columns[] = new Column(Span::rigid(self::AGE_WIDTH, 0), Align::Right, $this->text('column.age'));

        return $columns;
    }

    /**
     * @param list<ResourceRow> $rows
     *
     * @return list<TableRow>
     */
    private function rows(array $rows, ResourceKind $kind): array
    {
        $columns = ResourceColumnPacks::columnsFor($kind);
        $table = [];

        foreach ($rows as $row) {
            $cells = [$row->name];

            foreach ($columns as $column) {
                $cells[] = $row->valueOf($column);
            }

            $cells[] = $this->age($row->createdAt);
            $table[] = new TableRow($cells, self::roleOf($row));
        }

        return $table;
    }

    /**
     * Zdanie zamiast pustej listy.
     *
     * Rozróżnia **„jeszcze nie wiemy”** od **„nie ma nic”**, bo to dwie różne
     * wiadomości: pierwsza znaczy „poczekaj”, druga — „szukaj gdzie indziej”.
     */
    private function emptySentence(ResourceKind $kind): string
    {
        return $this->text($this->cache->knows($kind) ? 'list.empty' : 'list.reading', ['kind' => $kind->name]);
    }

    /**
     * Wiek zasobu — minuty, godziny, dni.
     *
     * Formy mnogie idą przez katalog napisów, bo „2 dni” i „5 dni” to w polszczyźnie
     * dwie różne formy, a w angielszczyźnie jedna. Sekund nie pokazujemy: lista
     * odświeża się najwyżej co dziesięć sekund, więc liczba sekund byłaby
     * dokładnością udawaną.
     */
    private function age(?int $createdAt): string
    {
        if ($createdAt === null) {
            return '';
        }

        $seconds = max(0, time() - $createdAt);

        return match (true) {
            $seconds < 3600 => $this->plural('age.minutes', intdiv($seconds, 60)),
            $seconds < 86_400 => $this->plural('age.hours', intdiv($seconds, 3600)),
            default => $this->plural('age.days', intdiv($seconds, 86_400)),
        };
    }

    private static function roleOf(ResourceRow $row): Role
    {
        return match ($row->tone) {
            RowTone::Broken => Role::Danger,
            RowTone::Waiting => Role::Warning,
            RowTone::Normal => Role::Text,
        };
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . KubernetesSettings::ID . '.' . $key, $parameters);
    }

    private function plural(string $key, int $count): string
    {
        return $this->translator->plural(
            'module.' . KubernetesSettings::ID . '.' . $key,
            $count,
            ['count' => $count],
        );
    }
}
