<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

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
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\CopiesContent;
use LightManager\Presentation\Ui\CopyContent;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Ekran książki adresowej (krok 60).
 *
 * Wzorem `HostsScreen` z kroku 48 i `EnvironmentScreen` z kroku 58 — spis,
 * dodanie, zmiana, usunięcie — i tak samo **bez ani jednego nowego komponentu**.
 * Różni się od obu jedną rzeczą: **nie ma tu czynności „użyj"**. Książka mówi,
 * gdzie coś stoi; łączy się z tym ten, kto potrafi, a on ma własny ekran.
 *
 * Kolumny są trzy — nazwa, adres, identyfikator — i identyfikator jest wśród
 * nich **z powodu, nie dla porządku**: to on wchodzi do komend i do cudzych
 * zapisów, więc użytkownik musi mieć go skąd przepisać (D105 nr 4). Wartości
 * rozdziałów w tabeli nie stoją: jest ich tyle, ile modułów, a wpis ma je
 * widoczne w łańcuchu zmiany i w ekranie tego, kto je zadeklarował.
 */
final class AddressBookScreen implements
    ScreenInterface,
    DeclaresFocus,
    Resettable,
    AcceptsPointer,
    CopiesContent
{
    public const ID = 'address-book';

    /** Szerokość kolumny identyfikatora — dokładnie tyle, ile ma identyfikator. */
    private const ID_COLUMN = AddressEntry::ID_LENGTH;

    /** Litera zawężenia — ta sama, którą deklaruje `bindings()`. */
    private const FILTER_KEY = 'f';

    private const NAME_MINIMUM = 8;

    private const ADDRESS_MINIMUM = 12;

    private int $selected = 0;

    private string $filter = '';

    private ?Rect $lastBounds = null;

    private readonly ScrollWindow $window;

    public function __construct(
        private readonly Addresses $addresses,
        private readonly EntryFlow $flow,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->window->useContext(self::ID);
    }

    public function id(): string
    {
        return self::ID;
    }

    public function labelKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.screen';
    }

    /**
     * Górny pas: gdzie książka mieszka, a przy nałożonym zawężeniu — czym jest
     * zawężona.
     *
     * Powód, dla którego zawężenie **musi** tu stać: spis pokazujący trzy wpisy
     * z dwudziestu wygląda identycznie jak książka, w której są trzy wpisy —
     * a różnica jest dla użytkownika zasadnicza.
     */
    public function header(): ScreenZone
    {
        $view = $this->reader->view();
        $problem = $view->problemKey;
        $left = $problem !== null ? $this->translator->translate($problem) : $view->location;

        return new ScreenZone(
            $this->labelKey(),
            new Label($left, $this->filter === '' ? '' : $this->text('filter.active', ['filter' => $this->filter])),
        );
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $this->reader->refreshChapters();
        $entries = $this->entries();

        if ($entries === []) {
            return (new Label($this->text($this->filter === '' ? 'empty' : 'empty.filtered'), '', Role::Muted))
                ->draw($bounds);
        }

        $this->clampSelection();
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $this->window->keepVisible($this->selected, count($entries), $capacity);

        return (new Table(
            $this->columns(),
            $this->rows($entries),
            $this->selected,
            $this->window->position(count($entries), $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    /** @return list<KeyBinding> */
    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::F4], $this->key('key.edit'), $this->key('key.edit.short')),
            KeyBinding::of([Key::F7], $this->key('key.add'), $this->key('key.add.short')),
            KeyBinding::of([Key::F8, Key::Delete], $this->key('key.remove'), $this->key('key.remove.short')),
            KeyBinding::ctrl(self::FILTER_KEY, $this->key('key.filter'), $this->key('key.filter.short')),
        ];
    }

    public function focus(): FocusHint
    {
        return new FocusHint($this->labelKey(), $this->bindings());
    }

    public function reset(): void
    {
        $this->selected = 0;
        $this->filter = '';
        $this->window->useContext(self::ID);
    }

    /** `Alt`+`c` na wpisie kopiuje **adres**, bo po to się go w książce trzyma. */
    public function copyable(): ?CopyContent
    {
        $entry = $this->underCursor();

        if ($entry === null || $entry->address === '') {
            return null;
        }

        return new CopyContent(
            $entry->address,
            Message::info($this->text('copied', ['address' => $entry->address])),
        );
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
            total: count($this->entries()),
        );

        return $row === null ? ScreenOutcome::stay() : $this->putSelection($row);
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        // Litera z `Ctrl` nie jest treścią (reguła 11j): albo zawęża spis, albo
        // nie znaczy w tym ekranie nic.
        if ($key->key === Key::Character && $key->ctrl) {
            return $key->raw === self::FILTER_KEY ? ScreenOutcome::opens($this->filterPrompt()) : ScreenOutcome::stay();
        }

        return match ($key->key) {
            Key::ArrowUp => $this->putSelection($this->selected - 1),
            Key::ArrowDown => $this->putSelection($this->selected + 1),
            Key::Home => $this->putSelection(0),
            Key::End => $this->putSelection(count($this->entries()) - 1),
            Key::F4 => $this->askToEdit(),
            Key::F7 => ScreenOutcome::opens($this->flow->add()),
            Key::F8, Key::Delete => $this->askToRemove(),
            Key::Escape => $this->back(),
            default => ScreenOutcome::stay(),
        };
    }

    /** `Esc` zdejmuje wpierw zawężenie, a dopiero potem zamyka ekran. */
    private function back(): ScreenOutcome
    {
        if ($this->filter === '') {
            return ScreenOutcome::close();
        }

        $this->filter = '';
        $this->selected = 0;

        return ScreenOutcome::stay();
    }

    private function filterPrompt(): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('prompt.filter'),
            [],
            $this->filter,
            function (string $needle): OverlayOutcome {
                $this->filter = trim($needle);
                $this->selected = 0;

                return OverlayOutcome::close();
            },
            $this->translator,
            $this->key('prompt.filter.field'),
        );
    }

    private function askToEdit(): ScreenOutcome
    {
        $entry = $this->underCursor();

        return $entry === null ? ScreenOutcome::stay() : ScreenOutcome::opens($this->flow->edit($entry));
    }

    private function askToRemove(): ScreenOutcome
    {
        $entry = $this->underCursor();

        if ($entry === null) {
            return ScreenOutcome::stay();
        }

        $id = $entry->id;
        $label = $entry->label();

        return ScreenOutcome::opens(new ConfirmOverlay(
            $this->key('confirm.remove'),
            ['name' => $label],
            function () use ($id, $label): OverlayOutcome {
                $this->addresses->remove($id);
                $this->clampSelection();

                return OverlayOutcome::close(Message::info($this->text('removed', ['name' => $label])));
            },
            $this->translator,
        ));
    }

    /** @return list<AddressEntry> wpisy po zawężeniu, w kolejności z ustawień */
    private function entries(): array
    {
        $entries = $this->reader->view()->entries;

        if ($this->filter === '') {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            fn (AddressEntry $entry): bool => $entry->matches($this->filter),
        ));
    }

    private function underCursor(): ?AddressEntry
    {
        return $this->entries()[$this->selected] ?? null;
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::flexible(self::NAME_MINIMUM, label: $this->text('column.name')),
            Column::flexible(self::ADDRESS_MINIMUM, label: $this->text('column.address')),
            Column::fixed(self::ID_COLUMN, yieldOrder: 1, label: $this->text('column.id')),
        ];
    }

    /**
     * @param list<AddressEntry> $entries
     *
     * @return list<TableRow>
     */
    private function rows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = new TableRow(
                [
                    $entry->name === '' ? $this->text('unnamed') : $entry->name,
                    $entry->address === '' ? $this->text('noAddress') : $entry->address,
                    $entry->id,
                ],
                $entry->name === '' || $entry->address === '' ? Role::Muted : Role::Text,
            );
        }

        return $rows;
    }

    private function putSelection(int $index): ScreenOutcome
    {
        $count = count($this->entries());

        if ($count > 0) {
            $this->selected = max(0, min($index, $count - 1));
        }

        return ScreenOutcome::stay();
    }

    private function clampSelection(): void
    {
        $count = count($this->entries());
        $this->selected = $count === 0 ? 0 : max(0, min($this->selected, $count - 1));
    }

    private function key(string $suffix): string
    {
        return 'module.' . AddressBookSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string> $parameters */
    private function text(string $suffix, array $parameters = []): string
    {
        return $this->translator->translate($this->key($suffix), $parameters);
    }
}
