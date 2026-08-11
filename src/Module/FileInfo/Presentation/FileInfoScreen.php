<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\FileInfo\Application\Dto\ChecksumStage;
use LightManager\Module\FileInfo\Application\Dto\DescriptionSection;
use LightManager\Module\FileInfo\Application\Dto\DiskUsageStage;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\SizeText;
use LightManager\Module\FileInfo\Presentation\Component\PreviewPane;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SectionState;
use LightManager\Presentation\Ui\SplitAxis;

/**
 * Ekran modułu: pełny obraz stanu zaznaczonego wpisu.
 *
 * Do kroku 20 ten sam opis pokazywało okno modalne otwierane `Enter`em na pliku.
 * Przeprowadzka nie była kosmetyczna: `Enter` stał się w całej aplikacji
 * klawiszem **zatwierdzania**, a opis pliku — pierwszym dowodem na to, że
 * kontrakt modułu wystarcza do wyprowadzenia z rdzenia działającej funkcji.
 *
 * Krok 25 składa go z **trzech klocków rdzenia dowiezionych osobno**: zwijanych
 * sekcji (krok 22), paska postępu (krok 23) i podziału ekranu (krok 24). To jest
 * ten moment, w którym trzy komponenty powstałe wcześniej dostają wspólnego
 * użytkownika — i dopiero tutaj widać, czy pasują do siebie.
 *
 * Ekran implementuje `ReadsContext`, bo bez wiedzy o zaznaczeniu nie miałby czego
 * opisać. Kontekst przychodzi co klatkę, ale opis **nie liczy się co klatkę**:
 * `FileInfoState` pilnuje, żeby proces `file` ruszał wyłącznie przy zmianie
 * ścieżki. Stąd też bierze się jedyne wywołanie robiące pracę w `draw()` —
 * kawałek sumy kontrolnej przypadający na tę klatkę.
 *
 * Krok 26 dokłada `NeedsTime`, i to nie z powodu zmiany wyglądu: pasek postępu
 * pokazujący pracę `du` nie zna postępu, więc jego wypełnienie **wędruje**, a do
 * wędrowania potrzebny jest zegar. Ekran bierze go z pętli, nie z `microtime()` —
 * tą samą drogą, którą od kroku 19 chodzi do karetki w polu tekstowym (reguła 11b).
 */
final class FileInfoScreen implements ScreenInterface, ReadsContext, Resettable, DrawsOwnFrame, NeedsTime
{
    private readonly ScrollWindow $window;

    private readonly SectionState $sections;

    /** Czas bieżącej klatki — dla paska postępu, który nie zna postępu. */
    private float $now = 0.0;

    private readonly SizeText $sizes;

    public function __construct(
        private readonly FileInfoState $state,
        private readonly PreviewPane $preview,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->sections = new SectionState();
        $this->sizes = new SizeText($translator);
    }

    public function id(): string
    {
        return 'file-info';
    }

    public function labelKey(): string
    {
        return 'module.file-info.name';
    }

    /**
     * Górny pas: katalog, z którego pochodzi opisywany wpis.
     *
     * Ta sama treść, którą do kroku 20 stawiał tam rdzeń — zmienił się wyłącznie
     * ten, kto ją zamawia.
     */
    public function header(): ScreenZone
    {
        return new ScreenZone('layout.zone.path', new Label($this->state->context()->path));
    }

    /**
     * Pasa podglądu ten ekran nie zamawia i po kroku 25 tym bardziej: miniatura
     * stoi w **prawym panelu**, obok sekcji, a nie pod nimi.
     */
    public function preview(): ?ScreenZone
    {
        return null;
    }

    public function reset(): void
    {
        $this->state->reset();
        $this->window->scrollBy(-PHP_INT_MAX);
    }

    public function useTime(float $now): void
    {
        $this->now = $now;
    }

    public function useContext(ModuleContext $context): void
    {
        $this->state->useContext($context);
        $this->window->useContext($context->selectionPath() ?? '');
        $this->sections->useContext($context->selectionPath() ?? '');
    }

    /**
     * Przy podziale ekran oprawia się sam — dwie obwódki zamiast jednej, bo rdzeń
     * wie o jednej strefie środkowej (krok 24, `DrawsOwnFrame`). Prymitywy wracają
     * do rdzenia, a nie są rysowane tutaj: rdzeń kładzie je na płaszczyźnie
     * pamiętanej między klatkami, więc obwódki nie kosztują ani jednej klatki
     * ponad pierwszą.
     */
    public function ownFrame(Rect $zone): array
    {
        if (!$this->splitsIn($zone)) {
            return [];
        }

        $primitives = [];
        $labels = ['module.file-info.name', 'layout.zone.preview'];

        foreach (Split::halves($zone, SplitAxis::Vertical) as $index => $bounds) {
            $panel = new Panel($this->translator->translate($labels[$index]));

            foreach ($panel->draw($bounds) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    public function draw(Rect $bounds): array
    {
        // Kawałek sumy kontrolnej przypadający na tę klatkę. Stoi tutaj, bo
        // `draw()` jest jedynym wywołaniem, które na pewno przychodzi w każdej
        // klatce **i tylko wtedy, gdy ekran jest widoczny** — praca nie posuwa
        // się do przodu, gdy użytkownik patrzy na co innego.
        $this->state->advance();

        if ($this->state->description() === null) {
            return (new Label($this->translator->translate('module.file-info.nothing')))->draw($bounds);
        }

        if (!$this->splitsIn($bounds)) {
            return $this->body($bounds);
        }

        [$left, $right] = Split::halves($bounds, SplitAxis::Vertical);
        $primitives = $this->body(Panel::inner($left));

        foreach ($this->preview->draw(Panel::inner($right)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Sekcje, a pod nimi pasek postępu — ale **tylko wtedy, gdy coś trwa**.
     *
     * Wiersz paska odbiera się sekcjom, a nie dokłada do okna: gdyby układ
     * przesuwał się w chwili naciśnięcia klawisza, treść uciekłaby spod kursora
     * przewijania. Tak samo rozstrzygnął to krok 12 dla pasa podglądu.
     *
     * @return list<Primitive>
     */
    private function body(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $bar = $this->workBar();
        $list = $bar === null ? $bounds : $bounds->rowsFrom(0, $bounds->rows - 1);
        $primitives = $this->sectionList($list->rows)->draw($list);

        if ($bar === null) {
            return $primitives;
        }

        foreach ($bar->draw($bounds->line($bounds->rows - 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Pasek trwającej pracy albo `null`, gdy nic nie trwa.
     *
     * Praca jest najwyżej jedna, więc pasek też: sumę liczymy tylko dla zwykłych
     * plików, zajętość tylko dla katalogów, a jedno wyklucza drugie. Kolejność
     * `if`ów jest mimo to rozstrzygnięciem, nie przypadkiem — gdyby kiedyś obie
     * prace mogły trwać naraz, pierwszeństwo ma ta, która **zna postęp**, bo
     * pasek z prawdziwym wypełnieniem mówi więcej niż wędrujący.
     */
    private function workBar(): ?ProgressBar
    {
        $checksum = $this->state->checksum();

        if ($checksum->isRunning()) {
            return new ProgressBar(
                $checksum->fraction,
                $this->translator->translate('module.file-info.checksum.working'),
                translator: $this->translator,
            );
        }

        if (!$this->state->diskUsage()->isRunning()) {
            return null;
        }

        // Postępu nie ma i nie ma go skąd wziąć: `du` milczy, aż skończy. Pasek
        // dostaje `null` zamiast zmyślonego ułamka i wędruje — pierwszy raz
        // w aplikacji, bo od kroku 23 ten tryb miał wyłącznie test i pomiar.
        return new ProgressBar(
            null,
            $this->translator->translate('module.file-info.diskUsage.working'),
            $this->now,
            $this->translator,
        );
    }

    /**
     * Spis sekcji wraz z oknem przewijania podążającym za kursorem.
     *
     * Rachunek jest ten sam, co w oknie pomocy od kroku 22, i **ma taki
     * zostać**: dwa wywołania `keepVisible()`, z których pierwsze ściąga okno do
     * końca sekcji pod kursorem, a drugie pilnuje jej nagłówka i wygrywa.
     */
    private function sectionList(int $capacity): SectionList
    {
        $sections = $this->sections();
        $this->sections->moveBy(0, count($sections));

        $cursor = $this->sections->cursor();
        $total = SectionList::rowCount($sections);
        $current = $sections[$cursor] ?? null;

        if ($current !== null) {
            $first = SectionList::rowOf($sections, $cursor);
            $this->window->keepVisible($first + $current->height() - 1, $total, $capacity);
            $this->window->keepVisible($first, $total, $capacity);
        }

        return new SectionList(
            $sections,
            $this->window->offset(),
            $current === null ? null : $cursor,
            $this->window->position($total, $capacity),
        );
    }

    /**
     * Sekcje opisu wraz z **dołożonym wierszem sumy kontrolnej**.
     *
     * Wiersz dokłada ekran, a nie przypadek użycia, i to jest rozstrzygnięcie,
     * nie skrót: opis liczy się raz na zaznaczenie, a stan sumy zmienia się co
     * klatkę. Gdyby siedział w `EntryDescription`, obiekt liczony raz musiałby
     * być przeliczany trzydzieści razy na sekundę — albo kłamać.
     *
     * @return list<Section>
     */
    private function sections(): array
    {
        $description = $this->state->description();

        if ($description === null) {
            return [];
        }

        $sections = [];

        foreach ($description->sections as $section) {
            $rows = $this->rowsOf($section);

            if ($section->key === 'size') {
                if ($description->kind === EntryKind::Directory) {
                    $rows[] = $this->diskUsageRow();
                }

                $rows[] = $this->checksumRow();
            }

            $sections[] = new Section(
                $section->key,
                $this->translator->translate($section->labelKey),
                $rows,
                $this->sections->isCollapsed($section->key),
            );
        }

        return $sections;
    }

    /** @return list<ListRow> */
    private function rowsOf(DescriptionSection $section): array
    {
        $rows = [];

        foreach ($section->rows as $row) {
            $rows[] = new ListRow($this->translator->translate($row->labelKey), $row->value, Role::Muted);
        }

        return $rows;
    }

    /**
     * Wiersz sumy kontrolnej w czterech odsłonach: nie zaczęto, trwa, gotowe,
     * nie da się. Każda mówi swoje — pusty wiersz nie mówi nic, a to jest ten
     * jeden wiersz, przy którym użytkownik musi wiedzieć, czy coś się dzieje.
     */
    private function checksumRow(): ListRow
    {
        $checksum = $this->state->checksum();

        return match ($checksum->stage) {
            ChecksumStage::Running => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $this->translator->translate('module.file-info.checksum.working'),
                Role::Muted,
            ),
            ChecksumStage::Done => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $checksum->digest ?? '',
                Role::Muted,
            ),
            ChecksumStage::Failed => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $this->translator->translate($checksum->problemKey ?? '', $checksum->problemParameters),
                Role::Warning,
            ),
            ChecksumStage::Idle => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $this->translator->translate('module.file-info.checksum.idle'),
                Role::Muted,
            ),
        };
    }

    /**
     * Wiersz zajętości na dysku — w tych samych czterech odsłonach, co suma
     * kontrolna, bo to ta sama praca widziana z drugiej strony.
     *
     * Wiersz powstaje **tylko dla katalogu** i stoi **przed** sumą kontrolną,
     * zaraz po blokach i-węzła, bo to z nimi rozmawia: bloki mówią, ile waży sam
     * katalog, ten wiersz — ile waży wszystko, co w nim leży.
     */
    private function diskUsageRow(): ListRow
    {
        $usage = $this->state->diskUsage();
        $label = $this->translator->translate('module.file-info.row.diskUsage');

        return match ($usage->stage) {
            DiskUsageStage::Running => new ListRow(
                $label,
                $this->translator->translate('module.file-info.diskUsage.working'),
                Role::Muted,
            ),
            DiskUsageStage::Done => new ListRow(
                $label,
                $this->sizes->format($usage->bytes ?? 0),
                Role::Muted,
            ),
            DiskUsageStage::Failed => new ListRow(
                $label,
                $this->translator->translate($usage->problemKey ?? '', $usage->problemParameters),
                Role::Warning,
            ),
            DiskUsageStage::Idle => new ListRow(
                $label,
                $this->translator->translate('module.file-info.diskUsage.idle'),
                Role::Muted,
            ),
        };
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of([Key::Enter], 'help.key.collapse'),
            KeyBinding::character('s', 'module.file-info.help.checksum'),
            KeyBinding::character('d', 'module.file-info.help.diskUsage'),
            KeyBinding::of([Key::Escape], 'help.key.back'),
        ];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        switch (true) {
            case $key->key === Key::Escape:
                return ScreenOutcome::close();
            case $key->key === Key::ArrowUp:
                $this->sections->moveBy(-1, count($this->sections()));

                return ScreenOutcome::stay();
            case $key->key === Key::ArrowDown:
                $this->sections->moveBy(1, count($this->sections()));

                return ScreenOutcome::stay();
            case $key->key === Key::Enter:
                return $this->toggleSection();
            case $key->key === Key::Character && $key->raw === 's':
                return $this->startChecksum();
            case $key->key === Key::Character && $key->raw === 'd':
                return $this->startDiskUsage();
            default:
                return ScreenOutcome::stay();
        }
    }

    private function toggleSection(): ScreenOutcome
    {
        $sections = $this->sections();
        $current = $sections[$this->sections->cursor()] ?? null;

        if ($current !== null) {
            $this->sections->toggle($current->key);
        }

        return ScreenOutcome::stay();
    }

    private function startChecksum(): ScreenOutcome
    {
        return $this->started($this->state->startChecksum());
    }

    private function startDiskUsage(): ScreenOutcome
    {
        return $this->started($this->state->startDiskUsage());
    }

    /** Odmowa idzie na pasek stanu, zgoda nie mówi nic — mówi za nią pasek postępu. */
    private function started(?string $refusal): ScreenOutcome
    {
        return $refusal === null
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::warning($this->translator->translate($refusal)));
    }

    /** Podział powstaje wtedy i tylko wtedy, gdy mieści się w oknie (krok 24). */
    private function splitsIn(Rect $zone): bool
    {
        return $zone->rows >= 3 && Split::fits($zone, SplitAxis::Vertical);
    }
}
