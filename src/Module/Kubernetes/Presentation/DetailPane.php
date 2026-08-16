<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\ResourceDetail;
use LightManager\Module\Kubernetes\Infrastructure\ClusterJson;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use LightManager\Presentation\Ui\Component\TextView;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SectionState;

/**
 * Opis zasobu w zwijanych sekcjach — i ten sam zasób w surowym YAML-u
 * (krok 52, D91 nr 5).
 *
 * **Sekcje są widokiem domyślnym, a YAML czeka pod klawiszem** — bo sekcja
 * pokazuje wybór autora, a zasoby Kubernetesa mają pola, których żaden spis nie
 * obejmie. Pole nieprzewidziane w sekcjach nie znika przez to bez śladu: `y`
 * pokazuje wszystko, co klaster o zasobie wie.
 *
 * **Sekcje są ogólne, nie rodzajowe** — tożsamość, etykiety, adnotacje, stan,
 * kontenery, dane. Rodzaj wpływa wyłącznie na to, które z nich w ogóle powstaną
 * (pod ma kontenery, sekret ma dane), a nie na ich układ. Inaczej wracalibyśmy
 * do tego, czego rozstrzygnięcie D91 nr 2 kazało uniknąć: kodu pisanego osobno
 * dla każdego z kilkudziesięciu rodzajów.
 *
 * **Wartości sekretu są zamaskowane** i to jest jedyne miejsce, w którym ta
 * klasa zna słowo „sekret”. Maskowanie jest domyślne, odsłania jeden klucz
 * `x` (D91 nr 10), a powód jest wymierny: `core.dump` z kroku 38 zapisuje
 * klatkę na dysk.
 */
final class DetailPane
{
    /** Czym zastępujemy niepokazaną wartość — długość stała, żeby nie zdradzała długości hasła. */
    private const MASK = '••••••••';

    public function __construct(
        private readonly ResourceDetail $detail,
        private readonly TranslatorPort $translator,
        private readonly SectionState $sections,
        private readonly ScrollWindow $yamlWindow,
    ) {
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds, bool $raw): array
    {
        if ($this->detail->reference() === null) {
            return (new Label($this->text('detail.none')))->draw($bounds);
        }

        if ($raw) {
            return $this->drawYaml($bounds);
        }

        $object = $this->detail->object();

        if ($object === null) {
            return (new Label($this->text($this->detail->isWorking() ? 'detail.reading' : 'detail.missing')))
                ->draw($bounds);
        }

        return $this->drawSections($bounds, $object);
    }

    /**
     * Sekcje tej klatki — potrzebne ekranowi, bo to on porusza kursorem.
     *
     * @return list<Section>
     */
    public function sections(): array
    {
        $object = $this->detail->object();

        return $object === null ? [] : $this->build($object);
    }

    /**
     * @param  array<string, mixed> $object
     * @return list<Primitive>
     */
    private function drawSections(Rect $bounds, array $object): array
    {
        $sections = $this->build($object);
        $total = SectionList::rowCount($sections);
        $cursor = min($this->sections->cursor(), max(0, count($sections) - 1));
        $offset = $this->yamlWindow->keepVisible(
            SectionList::rowOf($sections, $cursor),
            $total,
            $bounds->rows,
        );

        return (new SectionList(
            $sections,
            $offset,
            $cursor,
            $this->yamlWindow->position($total, $bounds->rows),
        ))->draw($bounds);
    }

    /** @return list<Primitive> */
    private function drawYaml(Rect $bounds): array
    {
        if (!$this->detail->hasYaml()) {
            return (new Label($this->text('detail.reading')))->draw($bounds);
        }

        $lines = explode("\n", $this->maskedYaml());
        $offset = $this->yamlWindow->clamp(count($lines), $bounds->rows);

        // YAML **nie zawija się**: wcięcie niesie w nim znaczenie, a wiersz
        // zawinięty do lewej krawędzi wygląda jak nowy klucz na innym poziomie.
        return (new TextView(
            array_slice($lines, $offset, $bounds->rows),
            wrap: false,
            position: $this->yamlWindow->position(count($lines), $bounds->rows),
        ))->draw($bounds);
    }

    /**
     * YAML z zamaskowanymi wartościami sekretu.
     *
     * Maskujemy **w widoku, a nie w danych**, bo te same dane odsłania klawisz
     * `x` w sekcjach. Wzorzec łapie wiersze z sekcji `data:` — czyli dokładnie
     * to, co w sekrecie jest tajemnicą; `metadata` i `spec` zostają nietknięte,
     * bo bez nich YAML przestałby odpowiadać na pytania, dla których się go
     * otwiera.
     */
    private function maskedYaml(): string
    {
        $yaml = $this->detail->yaml();

        if ($this->detail->secretSizes() === []) {
            return $yaml;
        }

        $lines = explode("\n", $yaml);
        $inData = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^(data|stringData):\s*$/', $line) === 1) {
                $inData = true;

                continue;
            }

            if ($inData && preg_match('/^\s/', $line) !== 1) {
                $inData = false;
            }

            if ($inData && preg_match('/^(\s+[^:]+:\s*)(\S.*)$/', $line, $matches) === 1) {
                $lines[$index] = $matches[1] . self::MASK;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed> $object
     * @return list<Section>
     */
    private function build(array $object): array
    {
        $sections = [$this->identity($object)];

        $labels = ClusterJson::map($object, 'metadata', 'labels');

        if ($labels !== []) {
            $sections[] = $this->fromMap('labels', $labels);
        }

        $annotations = ClusterJson::map($object, 'metadata', 'annotations');

        if ($annotations !== []) {
            $sections[] = $this->fromMap('annotations', $annotations);
        }

        $status = $this->status($object);

        if ($status !== null) {
            $sections[] = $status;
        }

        $containers = $this->containers($object);

        if ($containers !== null) {
            $sections[] = $containers;
        }

        $data = $this->data();

        if ($data !== null) {
            $sections[] = $data;
        }

        return $sections;
    }

    /** @param array<string, mixed> $object */
    private function identity(array $object): Section
    {
        $rows = [
            new ListRow($this->text('detail.name'), ClusterJson::text($object, 'metadata', 'name') ?? ''),
            new ListRow($this->text('detail.kind'), ClusterJson::text($object, 'kind') ?? ''),
            new ListRow($this->text('detail.apiVersion'), ClusterJson::text($object, 'apiVersion') ?? ''),
        ];

        $namespace = ClusterJson::text($object, 'metadata', 'namespace');

        if ($namespace !== null) {
            $rows[] = new ListRow($this->text('detail.namespace'), $namespace);
        }

        $created = ClusterJson::text($object, 'metadata', 'creationTimestamp');

        if ($created !== null) {
            $rows[] = new ListRow($this->text('detail.created'), $created);
        }

        return new Section('identity', $this->text('detail.section.identity'), $rows);
    }

    /**
     * Stan: faza i warunki.
     *
     * Warunki pokazujemy **wszystkie**, a nie sam `Ready`, bo przy zasobie, który
     * nie wstaje, odpowiedź siedzi zwykle w tym, który nikogo nie interesuje na
     * co dzień (`PodScheduled`, `ContainersReady`).
     *
     * @param array<string, mixed> $object
     */
    private function status(array $object): ?Section
    {
        $rows = [];
        $phase = ClusterJson::text($object, 'status', 'phase');

        if ($phase !== null) {
            $rows[] = new ListRow($this->text('detail.phase'), $phase);
        }

        foreach (ClusterJson::objects($object, 'status', 'conditions') as $condition) {
            $type = ClusterJson::text($condition, 'type');

            if ($type === null) {
                continue;
            }

            $value = ClusterJson::text($condition, 'status') ?? '';
            $rows[] = new ListRow(
                $type,
                $value . self::reasonSuffix(ClusterJson::text($condition, 'reason')),
                $value === 'True' ? Role::Text : Role::Warning,
            );
        }

        return $rows === [] ? null : new Section('status', $this->text('detail.section.status'), $rows);
    }

    /** @param array<string, mixed> $object */
    private function containers(array $object): ?Section
    {
        $rows = [];

        foreach (['initContainers', 'containers'] as $group) {
            foreach (ClusterJson::objects($object, 'spec', $group) as $container) {
                $name = ClusterJson::text($container, 'name');

                if ($name !== null) {
                    $rows[] = new ListRow($name, ClusterJson::text($container, 'image') ?? '');
                }
            }
        }

        return $rows === [] ? null : new Section('containers', $this->text('detail.section.containers'), $rows);
    }

    /**
     * Klucze sekretu albo mapy konfiguracji.
     *
     * Sekret pokazuje **rozmiar wartości i maskę**, a odsłonięty klucz — swoją
     * wartość. Mapa konfiguracji nie jest tajemnicą, więc jej wartości idą
     * wprost, przycięte do jednego wiersza.
     */
    private function data(): ?Section
    {
        $sizes = $this->detail->secretSizes();

        if ($sizes === []) {
            return null;
        }

        $revealed = $this->detail->revealed();
        $rows = [];

        foreach ($sizes as $key => $size) {
            $rows[] = new ListRow(
                $key,
                $key === $revealed
                    ? ($this->detail->secretValue($key) ?? '')
                    : self::MASK . ' ' . $this->plural('detail.bytes', $size),
                $key === $revealed ? Role::Warning : Role::Muted,
            );
        }

        return new Section('data', $this->text('detail.section.data'), $rows);
    }

    /**
     * @param  array<string, string> $map
     */
    private function fromMap(string $key, array $map): Section
    {
        $rows = [];

        foreach ($map as $name => $value) {
            $rows[] = new ListRow($name, $value);
        }

        // Etykiety i adnotacje wchodzą **zwinięte**: adnotacja
        // `kubectl.kubernetes.io/last-applied-configuration` bywa dłuższa niż
        // cała reszta zasobu razem wzięta i rozwinięta zepchnęłaby stan poza
        // ekran.
        return new Section($key, $this->text('detail.section.' . $key), $rows, collapsed: true);
    }

    private static function reasonSuffix(?string $reason): string
    {
        return $reason === null || $reason === '' ? '' : ' (' . $reason . ')';
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
