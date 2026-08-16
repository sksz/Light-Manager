<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Domain\ValueObject\Container;
use LightManager\Module\Docker\Domain\ValueObject\ContainerState;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Container\Span;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Lista kontenerów i opis wybranego (krok 51).
 *
 * **Nie dokłada ani jednego komponentu** — `Table` z kroku 27, `ListView` i
 * `ScrollWindow` z 18. Nowy jest wyłącznie sposób złożenia i to jest ta sama
 * miara, którą przeszedł spis hostów w kroku 48 i zdalny katalog w 49.
 *
 * **Pięć kolumn i ich kolejność ustępowania jest przemyślana, a nie
 * przypadkowa**: nazwa zostaje do końca (bez niej wiersz nie mówi nic), stan
 * ustępuje przedostatni (bo kolor wiersza powtarza go bez słowa), a porty i czas
 * odchodzą pierwsze — porty bywają puste, a czas jest tym, czego szuka się
 * najrzadziej.
 *
 * **Rola koloru bierze się ze stanu i to tutaj, a nie w domenie**: `Role` leży
 * w `Application/Ui`, więc enum stanu nie ma prawa jej znać (reguła 1). Kontener
 * działający dostaje akcent, martwy i restartujący się w kółko — ostrzeżenie,
 * reszta zwykły kolor tekstu.
 */
final class ContainerPane
{
    private const NAME_MINIMUM = 16;

    private const STATE_WIDTH = 11;

    private const PORTS_WIDTH = 22;

    private const AGE_WIDTH = 12;

    public function __construct(
        private readonly DockerQueries $reader,
        private readonly TranslatorPort $translator,
        private readonly ScrollWindow $window,
    ) {
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $entries = $this->reader->containers()->entries;

        if ($entries === []) {
            return (new Label($this->emptySentence()))->draw($bounds);
        }

        $count = count($entries);
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $cursor = $this->reader->containers()->cursor;
        $offset = $this->window->keepVisible($cursor, $count, $capacity);

        return (new Table(
            $this->columns(),
            $this->rows(array_slice($entries, $offset, max(1, $capacity))),
            $cursor - $offset,
            $this->window->position($count, $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    /**
     * Opis wybranego kontenera — pary „etykieta: wartość”.
     *
     * `ListRow` z dwoma polami, a nie `Table`, i jest to granica postawiona
     * w kroku 27: etykieta z wartością **nie jest tabelą**, bo kolumny nie
     * układają się tu w kratę.
     *
     * @return list<Primitive>
     */
    public function drawDetails(Rect $bounds): array
    {
        $container = $this->reader->containers()->selected();

        if ($container === null) {
            return [];
        }

        return (new ListView($this->detailRows($container)))->draw($bounds);
    }

    public function capacityOf(Rect $bounds): int
    {
        return max(1, Table::capacityOf($bounds, withHeader: true));
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            new Column(Span::flexible(self::NAME_MINIMUM), Align::Left, $this->text('column.name')),
            new Column(Span::flexible(self::NAME_MINIMUM), Align::Left, $this->text('column.image')),
            new Column(Span::rigid(self::STATE_WIDTH, 2), Align::Left, $this->text('column.state')),
            new Column(Span::rigid(self::PORTS_WIDTH, 1), Align::Left, $this->text('column.ports')),
            new Column(Span::rigid(self::AGE_WIDTH, 0), Align::Right, $this->text('column.created')),
        ];
    }

    /**
     * @param list<Container> $containers
     *
     * @return list<TableRow>
     */
    private function rows(array $containers): array
    {
        $rows = [];

        foreach ($containers as $container) {
            $rows[] = new TableRow(
                [
                    $container->name,
                    $container->image->short(),
                    $this->translator->translate($container->state->labelKey()),
                    implode(' ', $container->ports),
                    $this->age($container->createdAt),
                ],
                self::roleOf($container->state),
            );
        }

        return $rows;
    }

    /** @return list<ListRow> */
    private function detailRows(Container $container): array
    {
        $rows = [
            new ListRow($this->text('detail.name'), $container->name),
            new ListRow($this->text('detail.id'), $container->id->short()),
            new ListRow($this->text('detail.image'), $container->image->short()),
            new ListRow(
                $this->text('detail.state'),
                $container->status !== ''
                    ? $container->status
                    : $this->translator->translate($container->state->labelKey()),
                self::roleOf($container->state),
            ),
        ];

        if ($container->ports !== []) {
            $rows[] = new ListRow($this->text('detail.ports'), implode(', ', $container->ports));
        }

        if ($container->composeProject !== null) {
            $rows[] = new ListRow($this->text('detail.project'), $container->composeProject);
        }

        return $rows;
    }

    /**
     * Cztery różne powody, dla których lista bywa pusta — i **wszystkie cztery
     * trzeba rozdzielić**.
     *
     * Użytkownik nie ma jak ich odróżnić z samego braku wierszy: „jeszcze nie
     * przyszła” i „demon odmówił” wyglądają identycznie, a różnią się tym, czy
     * warto poczekać. Ta sama zasada, co przy pustym zdalnym katalogu w kroku 49.
     */
    private function emptySentence(): string
    {
        $problem = $this->reader->containers()->problemKey;

        if ($problem !== null) {
            return $this->translator->translate($problem);
        }

        if (!$this->reader->containers()->loaded) {
            return $this->text('containers.reading');
        }

        $project = $this->reader->containers()->project;

        return $project === null
            ? $this->text('containers.empty')
            : $this->translator->translate(
                'module.' . DockerSettings::ID . '.containers.emptyProject',
                ['project' => $project],
            );
    }

    /**
     * Wiek kontenera w postaci „3 dni”, „12 min”.
     *
     * Liczba idzie przez tłumacza (`TranslatorPort::number()`), a jednostka przez
     * formy mnogie katalogu — bo polski ma ich trzy, a angielski dwie.
     */
    private function age(?int $createdAt): string
    {
        if ($createdAt === null || $createdAt <= 0) {
            return '';
        }

        $seconds = max(0, time() - $createdAt);

        return match (true) {
            $seconds < 3600 => $this->plural('age.minutes', intdiv($seconds, 60)),
            $seconds < 86_400 => $this->plural('age.hours', intdiv($seconds, 3600)),
            default => $this->plural('age.days', intdiv($seconds, 86_400)),
        };
    }

    /** Liczbę wstawia sam tłumacz pod `{count}` — podana drugi raz nadpisałaby zapis języka. */
    private function plural(string $key, int $count): string
    {
        return $this->translator->plural('module.' . DockerSettings::ID . '.' . $key, $count);
    }

    private static function roleOf(ContainerState $state): Role
    {
        return match (true) {
            $state->isRunning() => Role::Accent,
            $state->isTroubled() => Role::Warning,
            default => Role::Text,
        };
    }

    private function text(string $key): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $key);
    }
}
