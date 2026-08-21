<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Kubernetes\Application\ClusterRow;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Spis klastrów — czwarta postać ekranu modułu (krok 59).
 *
 * Wzorem `EnvironmentScreen` z kroku 58 i `HostsScreen` z kroku 48: spis,
 * dodanie, zmiana, usunięcie, wybór bieżącego — i tak samo bez ani jednego
 * nowego komponentu. Postacią ekranu, a nie osobnym ekranem, bo `ScreenStack`
 * liczy ekrany po tożsamości (rozstrzygnięcie odziedziczone po krokach 49
 * i 51).
 *
 * **Wpis czytany z `kubeconfig` pokazuje się w spisie, ale zmiany i usunięcia
 * nie przyjmuje** (D96 nr 3) — należy do cudzego pliku, a moduł do `kubeconfig`
 * nie pisze. Odmowa jest zdaniem, nie ciszą. Wybrać go za to wolno: wybór jest
 * czynnością aplikacji i mieszka w jej własnej książce.
 */
final class ClusterBookScreen
{
    /** Szerokość kolumny kontekstu — mieści typowe `gke_projekt_region_nazwa` z zapasem. */
    private const CONTEXT_COLUMN = 24;

    /** Szerokość kolumny pochodzenia — mieści „plik domyślny". */
    private const ORIGIN_COLUMN = 16;

    /** Szerokość kolumny stanu — najdłuższe zdanie stanu plus oddech. */
    private const STATE_COLUMN = 12;

    private const NAME_MINIMUM = 8;

    private const CONFIG_MINIMUM = 12;

    private int $selected = 0;

    private ?Rect $lastBounds = null;

    private readonly ScrollWindow $window;

    public function __construct(
        private readonly Clusters $clusters,
        private readonly TranslatorPort $translator,
        private readonly KubernetesQueries $reader,
    ) {
        $this->window = new ScrollWindow();
        $this->window->useContext(KubernetesSettings::ID . ':clusters');
    }

    /** Wołane przy wejściu w postać: świeży odczyt wszystkich plików. */
    public function refresh(): void
    {
        $this->clusters->refresh();
    }

    public function reset(): void
    {
        $this->selected = 0;
        $this->window->useContext(KubernetesSettings::ID . ':clusters');
    }

    /** Zdanie górnego pasa: gdzie leży dokument z książką. */
    public function headerText(): string
    {
        return $this->reader->clusters()->location;
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $view = $this->reader->clusters();

        if ($view->count() === 0) {
            return (new Label($this->text($view->reading ? 'cluster.reading' : 'cluster.empty'), '', Role::Muted))
                ->draw($bounds);
        }

        $this->clampSelection();
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $this->window->keepVisible($this->selected, $view->count(), $capacity);

        return (new Table(
            $this->columns(),
            $this->rows(),
            $this->selected,
            $this->window->position($view->count(), $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    /** @return list<KeyBinding> */
    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::Enter], $this->key('cluster.key.select'), $this->key('cluster.key.select.short')),
            KeyBinding::ctrl('r', $this->key('cluster.key.refresh'), $this->key('key.refresh.short')),
            KeyBinding::of([Key::Escape], $this->key('cluster.key.back'), $this->key('cluster.key.back.short')),
        ];
    }

    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        if ($event->isScroll()) {
            $this->window->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $row = PointerRow::of(
            $event,
            $bounds,
            $this->window->offset(),
            withHeader: true,
            total: $this->reader->clusters()->count(),
        );

        return $row === null ? ScreenOutcome::stay() : $this->putSelection($row);
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        return match ($key->key) {
            Key::ArrowUp => $this->putSelection($this->selected - 1),
            Key::ArrowDown => $this->putSelection($this->selected + 1),
            Key::Home => $this->putSelection(0),
            Key::End => $this->putSelection($this->reader->clusters()->count() - 1),
            Key::Enter => $this->activate(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Enter`: wybór klastra bieżącego.
     *
     * Wybór **unieważnia wszystko, co przyszło z poprzedniego** — drzewo,
     * listy, opis i logi — bo tożsamością miejsca jest nazwa wiersza, a ta
     * właśnie się zmieniła. Robi to sesja przez pokolenie, więc ekran nie musi
     * niczego kasować ręcznie.
     */
    private function activate(): ScreenOutcome
    {
        $row = $this->underCursor();

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $problem = $this->clusters->select($row->name);

        if ($problem !== null) {
            return ScreenOutcome::stay(Message::error(
                $this->translator->translate($problem, ['name' => $row->name]),
            ));
        }

        return ScreenOutcome::stay(Message::info($this->text('cluster.switched', ['name' => $row->name])));
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::flexible(self::NAME_MINIMUM, label: $this->text('cluster.column.name')),
            Column::fixed(self::CONTEXT_COLUMN, yieldOrder: 2, label: $this->text('cluster.column.context')),
            Column::flexible(self::CONFIG_MINIMUM, label: $this->text('cluster.column.kubeconfig')),
            Column::fixed(self::ORIGIN_COLUMN, yieldOrder: 1, label: $this->text('cluster.column.origin')),
            Column::fixed(self::STATE_COLUMN, yieldOrder: 3, label: $this->text('cluster.column.state')),
        ];
    }

    /** @return list<TableRow> */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->reader->clusters()->rows as $row) {
            $rows[] = new TableRow(
                [
                    $row->name,
                    $row->context,
                    $row->kubeconfig,
                    $this->translator->translate($row->origin->labelKey()),
                    $this->stateOf($row),
                ],
                self::roleOf($row),
            );
        }

        return $rows;
    }

    private function stateOf(ClusterRow $row): string
    {
        if ($row->shadowed) {
            return $this->text('cluster.state.shadowed');
        }

        return $row->current ? $this->text('cluster.state.current') : '';
    }

    /**
     * Kolor wiersza mówi o stanie, zanim przeczyta się kolumnę — role wedle
     * lekcji z kroku 43: `Marked` dla wybranego, `Muted` dla przysłoniętego.
     */
    private static function roleOf(ClusterRow $row): Role
    {
        if ($row->shadowed) {
            return Role::Muted;
        }

        return $row->current ? Role::Marked : Role::Text;
    }

    private function underCursor(): ?ClusterRow
    {
        $this->clampSelection();

        return $this->reader->clusters()->at($this->selected);
    }

    private function putSelection(int $index): ScreenOutcome
    {
        $count = $this->reader->clusters()->count();

        if ($count === 0) {
            $this->selected = 0;

            return ScreenOutcome::stay();
        }

        $this->selected = max(0, min($index, $count - 1));

        return ScreenOutcome::stay();
    }

    private function clampSelection(): void
    {
        $count = $this->reader->clusters()->count();
        $this->selected = $count === 0 ? 0 : max(0, min($this->selected, $count - 1));
    }

    private function key(string $suffix): string
    {
        return 'module.' . KubernetesSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . KubernetesSettings::ID . '.' . $key, $parameters);
    }
}
