<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Application\ChapterView;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;
use LightManager\Module\AddressBook\Presentation\Command\AddCommand;
use LightManager\Module\AddressBook\Presentation\Command\RemoveCommand;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Component\Tabs;
use LightManager\Presentation\Ui\CopiesContent;
use LightManager\Presentation\Ui\CopyContent;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Ekran książki adresowej: **zakładki rozdziałów nad tabelą wpisów**
 * (krok 60, D105 nr 5).
 *
 * Dwie rzeczy odróżniają go od ekranu z książki usuniętej w kroku poprzednim
 * i obie były jej wadami.
 *
 * **Pierwsza: rozdziały widać.** Tamten ekran był tabelą `nazwa | adres | opis`,
 * a rozdziały pokazywał dopiero łańcuch okien pojedynczego wpisu — czyli
 * mechanizm istniał, a użytkownik go nie widział. Tu zakładka **jest**
 * rozdziałem, kolumny tabeli biorą się z **deklaracji jego pól**, a pierwsza
 * zakładka („wszystkie") mówi, w których rozdziałach wpis ma cokolwiek.
 * Zakładki rysuje rdzeniowy `Tabs` — ten sam, co na ekranie ustawień i pomocy —
 * więc wygląd i klawisze są te, które użytkownik zna z reszty aplikacji.
 *
 * **Druga: ekran nie ma referencji do modelu.** Czyta przez fasadę (czyli przez
 * rejestr kwerend), a **zmienia wyłącznie komendami** — tymi samymi, które
 * wpisałby użytkownik w oknie komend. `F7` tutaj i `address-book.add` w oknie
 * komend to **jedno wywołanie**, a nie dwie drogi do tej samej czynności (11n).
 *
 * Wyszukiwanie i sortowanie należą do tabeli: `Ctrl`+`F` zawęża spis po
 * nazwie i po widocznych wartościach, `F6` przestawia kolumnę porządkującą,
 * a kolejne naciśnięcie odwraca kierunek. **Sortowanie mieszka tutaj, nie
 * w `Table`**: wiersze porządkuje ekran, zanim poda je komponentowi, więc
 * pozycja „sortowanie listy po kolumnie", wyłączona z kroku 27, zostaje tam,
 * gdzie była, a rdzeń nie rośnie o nic.
 */
final class AddressBookScreen implements ScreenInterface, DeclaresFocus, Resettable, AcceptsPointer, CopiesContent
{
    public const ID = 'address-book';

    /** Zakładka „wszystkie" — nie jest rozdziałem i dlatego jej nazwa jest pusta. */
    public const ALL = '';

    /** Pasek zakładek plus odstęp pod nim. */
    private const CHROME_ROWS = 2;

    /** Litera zawężenia spisu — `Ctrl`+`F`, jak filtr przeglądarki (krok 30). */
    private const FILTER_KEY = 'f';

    private const ID_COLUMN = 14;

    private const NAME_MINIMUM = 10;

    private const VALUE_MINIMUM = 8;

    private string $chapter = self::ALL;

    private int $selected = 0;

    private string $filter = '';

    private int $sortColumn = 0;

    private bool $descending = false;

    private ?Rect $lastBounds = null;

    private ?Rect $tabsBounds = null;

    private readonly ScrollWindow $window;

    /**
     * @param LoopState $state **wyłącznie po rejestr komend, w chwili użycia** —
     *                         powód opisany w `EntryFlow`
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly AddressBookQueries $reader,
        private readonly EntryFlow $flow,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
    }

    public function id(): string
    {
        return self::ID;
    }

    public function labelKey(): string
    {
        return AddressBookSettings::key('screen');
    }

    /** Zakładkę wskazuje komenda `address-book.show <rozdział>` — także z cudzego ekranu. */
    public function useChapter(string $chapter): void
    {
        $this->chapter = $chapter;
        $this->selected = 0;
        $this->sortColumn = 0;
    }

    /**
     * Górny pas mówi, **skąd biorą się wpisy i czy spis jest zawężony**.
     *
     * Rozdział bez deklaracji dostaje tu zdanie osobne: jego wartości są
     * widoczne i zmienialne, brakuje wyłącznie opisu pól — a to jest różnica,
     * której nie widać po samej tabeli z surowymi kluczami.
     */
    public function header(): ScreenZone
    {
        $book = $this->reader->book();
        $text = match (true) {
            $book->problemKey !== null => $this->text($book->problemKey),
            $this->filter !== '' => $this->text(AddressBookSettings::key('header.filter'), [
                'filter' => $this->filter,
                'count' => (string) count($this->rows()),
            ]),
            $this->chapter !== self::ALL && !$this->reader->fields($this->chapter)->declared => $this->text(
                AddressBookSettings::key('header.undeclared'),
                ['chapter' => $this->chapter],
            ),
            default => $book->location,
        };

        return new ScreenZone($this->labelKey(), new Label($text));
    }

    public function reset(): void
    {
        $this->selected = 0;
        $this->filter = '';
        $this->window->useContext(self::ID . $this->chapter);
    }

    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $this->tabsBounds = $bounds->line(0);
        $labels = $this->tabLabels();
        $primitives = (new Tabs($labels, $this->activeTab()))->draw($this->tabsBounds);

        if ($bounds->rows <= self::CHROME_ROWS) {
            return $primitives;
        }

        $content = $bounds->rowsFrom(self::CHROME_ROWS, $bounds->rows - self::CHROME_ROWS);
        $rows = $this->rows();

        if ($rows === []) {
            return array_merge($primitives, (new Label($this->emptyText(), '', Role::Muted))->draw($content));
        }

        $this->clampSelection();
        $capacity = Table::capacityOf($content, withHeader: true);
        $this->window->useContext(self::ID . $this->chapter);
        $this->window->keepVisible($this->selected, count($rows), $capacity);

        return array_merge($primitives, (new Table(
            $this->columns(),
            $this->tableRows($rows),
            $this->selected,
            $this->window->position(count($rows), $capacity),
            withHeader: true,
        ))->draw($content));
    }

    public function bindings(): array
    {
        $key = AddressBookSettings::key(...);

        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], $key('key.move')),
            KeyBinding::of([Key::ArrowLeft, Key::ArrowRight], $key('key.chapter'), $key('key.chapter.short')),
            // `Enter` i `F4` to **jedno** wiązanie o dwóch klawiszach, a nie dwa
            // o tym samym opisie — inaczej stopka wypisuje „Enter zmień · F4
            // zmień" i wygląda, jakby to były dwie różne czynności (reguła 11p).
            // Widać to wyłącznie w klatce; złapane przy oglądaniu ekranu pod
            // XTermem.
            KeyBinding::of([Key::Enter, Key::F4], $key('key.edit'), $key('key.edit.short')),
            KeyBinding::of([Key::F6], $key('key.sort'), $key('key.sort.short')),
            KeyBinding::of([Key::F7], $key('key.add'), $key('key.add.short')),
            KeyBinding::of([Key::F8], $key('key.remove'), $key('key.remove.short')),
            KeyBinding::ctrl(self::FILTER_KEY, $key('key.filter'), $key('key.filter.short')),
        ];
    }

    public function focus(): FocusHint
    {
        return new FocusHint(AddressBookSettings::key('focus.entries'), $this->bindings());
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Character && $key->ctrl && $key->raw === self::FILTER_KEY) {
            return $this->askForFilter();
        }

        return match ($key->key) {
            Key::ArrowUp => $this->select(-1),
            Key::ArrowDown => $this->select(1),
            Key::ArrowLeft => $this->switchTab(-1),
            Key::ArrowRight => $this->switchTab(1),
            Key::Home => $this->putSelection(0),
            Key::End => $this->putSelection(count($this->rows()) - 1),
            Key::Enter, Key::F4 => $this->edit(),
            Key::F6 => $this->sort(),
            Key::F7 => $this->add(),
            Key::F8 => $this->remove(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Wskaźnik: kółko przewija, kliknięcie w zakładkę ją zmienia, kliknięcie
     * w wiersz stawia kursor (krok 55).
     *
     * Zakładki liczy `Tabs::at()` — **ten sam rachunek, co rysowanie**; drugi
     * u wołającego rozjechałby się przy pierwszej zmianie odstępu i byłby
     * niewidoczny do chwili, gdy ktoś kliknie w sąsiednią zakładkę.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        if ($this->tabsBounds !== null && $event->action === PointerAction::Press) {
            $tab = Tabs::at($this->tabLabels(), $this->tabsBounds, $event);

            if ($tab !== null) {
                return $this->putTab($tab);
            }
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
            $bounds->rowsFrom(self::CHROME_ROWS, max(0, $bounds->rows - self::CHROME_ROWS)),
            $this->window->offset(),
            withHeader: true,
            total: count($this->rows()),
        );

        return $row === null ? ScreenOutcome::stay() : $this->putSelection($row);
    }

    /** Kopiuje wiersz pod kursorem — wartości rozdzielone tabulatorem, jak z arkusza. */
    public function copyable(): ?CopyContent
    {
        $row = $this->rows()[$this->selected] ?? null;

        if ($row === null) {
            return null;
        }

        $text = implode("\t", array_map(ChapterField::asText(...), array_values($row)));

        return new CopyContent($text, Message::info(
            $this->text(AddressBookSettings::key('message.copied'), ['entry' => ChapterField::asText($row['name'] ?? '')]),
        ));
    }

    /**
     * Wiersze spisu: **wiersze kwerendy**, zawężone filtrem i uporządkowane.
     *
     * Ekran rysuje z wierszy, a nie z ładunku, i to nie jest ceremonia: dzięki
     * temu tabela pokazuje dokładnie to, co pokaże okno kwerend, a zasłona pola
     * maskowanego jest jedna dla obu.
     *
     * @return list<array<string, string|int|bool>>
     */
    private function rows(): array
    {
        $rows = $this->reader->rows($this->chapter);
        $rows = $this->filter === '' ? $rows : array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matches($row),
        ));

        return $this->sorted($rows);
    }

    /** @param array<string, string|int|bool> $row */
    private function matches(array $row): bool
    {
        foreach ($row as $value) {
            if (stripos(ChapterField::asText($value), $this->filter) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, string|int|bool>> $rows
     *
     * @return list<array<string, string|int|bool>>
     */
    private function sorted(array $rows): array
    {
        $keys = $this->columnKeys();
        $key = $keys[$this->sortColumn] ?? null;

        if ($key === null) {
            return $rows;
        }

        usort($rows, function (array $left, array $right) use ($key): int {
            $order = strnatcasecmp(
                ChapterField::asText($left[$key] ?? ''),
                ChapterField::asText($right[$key] ?? ''),
            );

            return $this->descending ? -$order : $order;
        });

        return $rows;
    }

    /**
     * Klucze kolumn w kolejności pokazywania — **jedno źródło dla nagłówków,
     * komórek i sortowania**.
     *
     * @return list<string>
     */
    private function columnKeys(): array
    {
        if ($this->chapter === self::ALL) {
            return ['name', 'chapters', 'id'];
        }

        $fields = $this->reader->fields($this->chapter);

        if ($fields->fields !== []) {
            return array_merge(['name'], $fields->keys(), ['id']);
        }

        // Rozdział bez deklaracji: kolumnami są **surowe klucze**, które stoją
        // w danych. Kolejność bierze się z pierwszego wiersza, bo innej nie ma.
        $first = $this->reader->rows($this->chapter)[0] ?? [];

        return array_values(array_unique(array_merge(
            ['name'],
            array_values(array_diff(array_keys($first), ['id', 'name'])),
            ['id'],
        )));
    }

    /** @return list<Column> */
    private function columns(): array
    {
        $fields = $this->chapter === self::ALL ? null : $this->reader->fields($this->chapter);
        $columns = [];

        foreach ($this->columnKeys() as $position => $key) {
            $columns[] = $key === 'id'
                ? Column::fixed(self::ID_COLUMN, yieldOrder: 1, label: $this->headerOf($position, $this->text(AddressBookSettings::key('column.id'))))
                : Column::flexible(
                    $key === 'name' ? self::NAME_MINIMUM : self::VALUE_MINIMUM,
                    label: $this->headerOf($position, $this->labelOf($key, $fields)),
                );
        }

        return $columns;
    }

    private function labelOf(string $key, ?ChapterView $fields): string
    {
        if ($key === 'name') {
            return $this->text(AddressBookSettings::key('column.name'));
        }

        if ($key === 'chapters') {
            return $this->text(AddressBookSettings::key('column.chapters'));
        }

        $declared = $fields?->field($key);

        return $declared === null ? $key : $this->text($declared->labelKey);
    }

    /** Nagłówek kolumny porządkującej niesie znacznik kierunku — inaczej porządek jest niewidzialny. */
    private function headerOf(int $position, string $label): string
    {
        if ($position !== $this->sortColumn) {
            return $label;
        }

        return $label . ($this->descending ? ' ▼' : ' ▲');
    }

    /**
     * @param list<array<string, string|int|bool>> $rows
     *
     * @return list<TableRow>
     */
    private function tableRows(array $rows): array
    {
        $keys = $this->columnKeys();
        $fields = $this->chapter === self::ALL ? null : $this->reader->fields($this->chapter);
        $table = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($keys as $key) {
                $cells[] = $this->cell($fields?->field($key), $row[$key] ?? '');
            }

            $table[] = new TableRow($cells);
        }

        return $table;
    }

    /**
     * Komórka tabeli — z **wartością wyboru przetłumaczoną tak samo, jak
     * w łańcuchu okien**.
     *
     * Konwencja jest jedna dla obu miejsc (`<etykieta pola>.<wartość>`) i to
     * jest cały powód, dla którego ta metoda istnieje: bez niej tabela
     * pokazywała `key`, a okno pytające o to samo pole — „klucz z pliku".
     * Widać to było wyłącznie w klatce, obok siebie.
     *
     * Wartość spoza deklaracji zostaje **taka, jaka jest** — bo klucza dla niej
     * nikt nie zapowiedział, a wymyślony przez książkę byłby zgadywaniem.
     */
    private function cell(?ChapterField $field, string|int|bool $value): string
    {
        $text = ChapterField::asText($value);

        if ($text === '' || $field === null) {
            return $text;
        }

        // Odniesienie pokazuje **nazwę wskazywanego wpisu**, a nie jego
        // identyfikator: identyfikatorem się wskazuje, nazwą się mówi. Wpis,
        // którego już nie ma, zostaje identyfikatorem — bo to jedyne, co o nim
        // wiadomo, a udawanie nazwy byłoby zmyślaniem.
        if ($field->kind === FieldKind::Entry) {
            return $this->reader->entry($text)?->label() ?? $text;
        }

        if ($field->kind !== FieldKind::Choice || !in_array($text, $field->choices, true)) {
            return $text;
        }

        return $this->text($field->labelKey . '.' . $text);
    }

    /** @return list<string> */
    private function tabLabels(): array
    {
        $labels = [$this->text(AddressBookSettings::key('tab.all'))];

        foreach ($this->reader->chapters()->chapters as $chapter) {
            $labels[] = $chapter->titleKey === '' ? $chapter->id : $this->text($chapter->titleKey);
        }

        return $labels;
    }

    private function activeTab(): int
    {
        if ($this->chapter === self::ALL) {
            return 0;
        }

        $position = array_search($this->chapter, $this->reader->chapters()->names(), true);

        return is_int($position) ? $position + 1 : 0;
    }

    private function switchTab(int $delta): ScreenOutcome
    {
        return $this->putTab($this->activeTab() + $delta);
    }

    private function putTab(int $index): ScreenOutcome
    {
        $names = $this->reader->chapters()->names();
        $count = count($names) + 1;
        $index = max(0, min($index, $count - 1));
        $this->useChapter($index === 0 ? self::ALL : $names[$index - 1]);

        return ScreenOutcome::stay();
    }

    /** `F6`: następna kolumna porządkująca, a po ostatniej — kierunek przeciwny. */
    private function sort(): ScreenOutcome
    {
        $columns = count($this->columnKeys());

        if ($columns === 0) {
            return ScreenOutcome::stay();
        }

        if ($this->sortColumn + 1 < $columns) {
            ++$this->sortColumn;

            return ScreenOutcome::stay();
        }

        $this->sortColumn = 0;
        $this->descending = !$this->descending;

        return ScreenOutcome::stay();
    }

    private function askForFilter(): ScreenOutcome
    {
        return ScreenOutcome::opens(new PromptOverlay(
            AddressBookSettings::key('prompt.filter'),
            [],
            $this->filter,
            function (string $value): OverlayOutcome {
                $this->filter = trim($value);
                $this->selected = 0;

                return OverlayOutcome::close();
            },
            $this->translator,
            AddressBookSettings::key('prompt.filter.field'),
        ));
    }

    /**
     * `F7`: nowy wpis — **komendą**, bo ekran nie ma innej drogi do zmiany.
     *
     * Na zakładce rozdziału komenda dostaje **jego nazwę** i prowadzi dalej po
     * jego polach: wpis bez ani jednej wartości do rozdziału nie należy, więc
     * dopisany bez tego zniknąłby z zakładki, na której go dopisano.
     */
    private function add(): ScreenOutcome
    {
        return $this->order(AddressBookSettings::ID . '.add', [
            AddCommand::NAME => '',
            AddCommand::CHAPTER => $this->chapter,
        ]);
    }

    private function remove(): ScreenOutcome
    {
        $id = $this->idUnderCursor();

        return $id === '' ? ScreenOutcome::stay() : $this->order(
            AddressBookSettings::ID . '.remove',
            [RemoveCommand::ENTRY => $id],
        );
    }

    /**
     * `Enter` i `F4`: łańcuch okien po polach **bieżącej zakładki**.
     *
     * Na zakładce „wszystkie" pyta o samą nazwę, bo pól wspólnych nie ma —
     * i to jest jedyne miejsce, w którym ekran otwiera okno sam, zamiast prosić
     * o nie komendę: `address-book.rename` nie ma zdolności `OpensOverlay`, bo
     * z wiersza poleceń nowa nazwa przychodzi argumentem.
     */
    private function edit(): ScreenOutcome
    {
        $id = $this->idUnderCursor();

        if ($id === '') {
            return ScreenOutcome::stay();
        }

        if ($this->chapter === self::ALL) {
            return $this->askForName($id);
        }

        $overlay = $this->flow->begin($id, $this->chapter);

        return $overlay === null
            ? ScreenOutcome::stay(Message::warning($this->text(AddressBookSettings::key('message.noFields'), [
                'chapter' => $this->chapter,
            ])))
            : ScreenOutcome::opens($overlay);
    }

    private function askForName(string $id): ScreenOutcome
    {
        $entry = $this->reader->entry($id);

        return ScreenOutcome::opens(new PromptOverlay(
            AddressBookSettings::key('prompt.name'),
            [],
            $entry === null ? '' : $entry->name,
            fn (string $name): OverlayOutcome => OverlayOutcome::close(
                $this->orderMessage(AddressBookSettings::ID . '.rename', ['entry' => $id, 'name' => $name]),
            ),
            $this->translator,
            AddressBookSettings::key('prompt.name.field'),
        ));
    }

    /**
     * Zamówienie komendy — **jedyna droga, którą ten ekran cokolwiek zmienia**.
     *
     * O okno pyta się **zdolności komendy** (`OpensOverlay`), a nie rejestru —
     * tak samo, jak robią to okno komend i menu `F9` (krok 47). Dzięki temu
     * `F7` i wpisanie `address-book.add` prowadzą to samo pytanie o nazwę.
     *
     * @param array<string, string> $arguments
     */
    private function order(string $name, array $arguments): ScreenOutcome
    {
        $command = $this->state->commands()->find($name);

        if ($command === null) {
            return ScreenOutcome::stay(Message::warning($this->text(AddressBookSettings::key('message.noCommand'))));
        }

        $input = new CommandInput($arguments);

        if ($command instanceof OpensOverlay) {
            $outcome = $command->overlayFor($input);

            if ($outcome?->next !== null) {
                return ScreenOutcome::opens($outcome->next);
            }
        }

        return ScreenOutcome::stay($command->execute($input)->message);
    }

    /** @param array<string, string> $arguments */
    private function orderMessage(string $name, array $arguments): ?Message
    {
        $command = $this->state->commands()->find($name);

        return $command?->execute(new CommandInput($arguments))->message;
    }

    private function idUnderCursor(): string
    {
        $this->clampSelection();
        $row = $this->rows()[$this->selected] ?? null;

        return ChapterField::asText($row['id'] ?? '');
    }

    private function emptyText(): string
    {
        return $this->text(AddressBookSettings::key($this->filter === '' ? 'empty' : 'empty.filter'));
    }

    private function select(int $delta): ScreenOutcome
    {
        return $this->putSelection($this->selected + $delta);
    }

    private function putSelection(int $index): ScreenOutcome
    {
        $count = count($this->rows());
        $this->selected = $count === 0 ? 0 : max(0, min($index, $count - 1));

        return ScreenOutcome::stay();
    }

    private function clampSelection(): void
    {
        $count = count($this->rows());
        $this->selected = $count === 0 ? 0 : max(0, min($this->selected, $count - 1));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters);
    }
}
