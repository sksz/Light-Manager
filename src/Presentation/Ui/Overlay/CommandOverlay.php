<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

use LightManager\Application\Command\CommandHistory;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Command\CommandTransition;
use LightManager\Application\Command\Prefix;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Event\AppEvent;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryLineParser;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Query\QueryResult;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Okno komend: wiersz wpisywania przy dolnej krawędzi i lista podpowiedzi nad nim.
 *
 * Wzorzec pochodzi z wiersza poleceń `vima` i stąd też bierze się miejsce
 * w klatce: pas nad paskiem stanu zasłania najmniej treści, a oczy zostają tam,
 * gdzie się pisze. Lista **wyrasta w górę**, więc pole nie skacze przy każdym
 * znaku.
 *
 * Przy pustym polu lista pokazuje **najpierw historię, potem wszystkie komendy**.
 * Dzięki temu historia nie potrzebuje własnego klawisza, a użytkownik, który nie
 * zna nazw, widzi je wszystkie od razu po `F12`.
 *
 * **Od kroku 53 okno ma drugi tryb: kwerendy** (D92 nr 7). Przełącza go `Tab`
 * przy pustym polu — klawisz, który do tego kroku przy pustym polu nie znaczył
 * nic, bo wspólnego przedrostka wszystkich nazw nie ma. Słownik wejścia nie
 * rośnie przez to o pozycję, a scenariusz pomiarowy `command` mierzy dalej tę
 * samą klatkę.
 *
 * Czym różnią się tryby: rejestrem, historią i tym, co zostaje po `Enter`.
 * Komenda **robi** i okno się zamyka; kwerenda **mówi** i okno zostaje otwarte
 * z odpowiedzią — jednym wierszem pokazanym jako pary `pole: wartość`, wieloma
 * jako tabela z nagłówkiem. Historii kwerendy nie mają i mieć nie będą: historia
 * zapisuje czynność, a nie pytanie.
 */
final class CommandOverlay implements OverlayInterface, Resettable, NeedsTime
{
    private const ID = 'command';

    /** Obwódka (dwa wiersze) plus wiersz wpisywania. */
    private const CHROME_ROWS = 3;

    private readonly TextInput $input;

    private readonly ScrollWindow $window;

    private int $selected = 0;

    /**
     * Czy użytkownik **wskazał** pozycję strzałkami.
     *
     * Rozstrzyga, co uruchamia `Enter`: wpisany wiersz czy zaznaczoną
     * podpowiedź. Bez tego rozróżnienia strzałka w dół musiałaby albo od razu
     * przepisywać pozycję do pola (i gubić wpisany tekst), albo nie znaczyć nic.
     */
    private bool $picked = false;

    /** @var ?list<Suggestion> wynik dla bieżącej treści pola — liczony raz na klatkę */
    private ?array $cache = null;

    /** @var array<string, list<string>> podpowiedzi stałe: `komenda/argument` → wartości */
    private array $fixed = [];

    /** Tryb okna: czynności albo źródła danych (krok 53). */
    private CommandWindowMode $mode = CommandWindowMode::Commands;

    /**
     * Odpowiedź ostatniej kwerendy — pokazywana zamiast podpowiedzi, dopóki
     * użytkownik nie napisze czegoś nowego.
     */
    private ?QueryResult $result = null;

    /**
     * @param ?EventRegistry   $events      `null` znaczy „nikomu nie ogłaszaj" — dla
     *                                      testów składających okno samodzielnie
     * @param ?QueryRegistry   $queries     `null` znaczy „okno bez trybu kwerend"; ta sama
     *                                      furtka i z tego samego powodu, co przy zdarzeniach
     * @param ?QueryLineParser $queryParser rozbiór wiersza kwerendy; wymagany razem z rejestrem
     */
    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly CommandLineParser $parser,
        private readonly CommandHistory $history,
        private readonly TranslatorPort $translator,
        private readonly ?EventRegistry $events = null,
        private readonly ?QueryRegistry $queries = null,
        private readonly ?QueryLineParser $queryParser = null,
    ) {
        $this->input = new TextInput();
        $this->window = new ScrollWindow();
    }

    /**
     * Podpowiedzi stałe liczone **raz**, przy starcie.
     *
     * Lista motywów i lista języków nie zmieniają się przez całe uruchomienie,
     * więc pytanie o nie trzydzieści razy na sekundę byłoby czystą stratą.
     * Wartości liczone na żądanie (ścieżki, krok 20) tędy nie przechodzą.
     */
    public function prepare(): void
    {
        foreach ([...$this->registry->all(), ...($this->queries?->all() ?? [])] as $source) {
            if (!$source instanceof SuggestsArguments) {
                continue;
            }

            foreach ($source->arguments() as $argument) {
                if ($argument->suggestions !== SuggestionSource::Fixed) {
                    continue;
                }

                $this->fixed[$source->name() . '/' . $argument->name] = $source->suggestions($argument->name, '');
            }
        }
    }

    public function id(): string
    {
        return self::ID;
    }

    /**
     * Wejście do okna zaczyna je od pustego wiersza — jak każde otwarcie ekranu.
     *
     * Tryb wraca do czynności, bo `F12` ma znaczyć zawsze to samo: okno otwarte
     * w trybie, w którym ktoś je zostawił kwadrans temu, byłoby oknem
     * niespodzianką.
     */
    public function reset(): void
    {
        $this->input->clear();
        $this->selected = 0;
        $this->picked = false;
        $this->cache = null;
        $this->result = null;
        $this->mode = CommandWindowMode::Commands;
        $this->window->scrollBy(-PHP_INT_MAX);
    }

    /** Zegar dla karetki — okno przekazuje go dalej, bo to pole nią mruga. */
    public function useTime(float $now): void
    {
        $this->input->useTime($now);
    }

    public function bounds(int $rows, int $columns): Rect
    {
        // Pasek stanu należy do rdzenia i okno nie ma prawa go zasłonić; jego
        // wysokość zależy wyłącznie od wysokości terminala.
        $bottom = (new HudLayout($rows, $columns))->status->row - 1;

        if ($bottom < 0) {
            $bottom = max(0, $rows - 1);
        }

        $height = min(
            $this->visibleCount() + self::CHROME_ROWS,
            max(1, intdiv($rows, 2)) + self::CHROME_ROWS,
            $bottom + 1,
        );

        return new Rect(max(0, $bottom - $height + 1), 0, $height, $columns);
    }

    public function draw(Rect $bounds): array
    {
        $primitives = (new Panel($this->translator->translate($this->mode->titleKey())))->draw($bounds);
        $inner = Panel::inner($bounds);

        if ($inner->isEmpty()) {
            return $primitives;
        }

        $content = $inner->rowsFrom(0, $inner->rows - 1);

        foreach ($this->result === null ? $this->drawSuggestions($content) : $this->drawResult($content) as $primitive) {
            $primitives[] = $primitive;
        }

        foreach ($this->input->draw($inner->line($inner->rows - 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::Enter], 'command.key.run'),
            KeyBinding::of([Key::Tab], 'command.key.complete'),
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'command.key.pick'),
            KeyBinding::of([Key::Escape], 'command.key.close'),
            ...$this->input->bindings(),
        ];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        $this->cache = null;

        return match ($key->key) {
            Key::Escape, Key::F12 => $this->closed(),
            Key::Enter => $this->run(),
            Key::Tab => $this->complete(),
            Key::ArrowUp => $this->pick(-1),
            Key::ArrowDown => $this->pick(1),
            default => $this->toInput($key),
        };
    }

    /**
     * Ile wierszy ma dziś treść okna: podpowiedzi albo odpowiedź kwerendy.
     *
     * Wysokość okna liczy się z tego samego, z czego rysuje się jego wnętrze —
     * inaczej odpowiedź o dwunastu polach dostałaby prostokąt na trzy.
     */
    private function visibleCount(): int
    {
        if ($this->result === null) {
            return count($this->suggestions());
        }

        $rows = $this->result->rows();

        return count($rows) === 1 ? count($rows[0]) : count($rows) + 1;
    }

    /**
     * Wartość danej pierwotnej jako napis dla oka.
     *
     * Liczba idzie przez `number()`, a wartość logiczna przez katalog napisów —
     * bo „tak” i „nie” są **napisem widocznym dla użytkownika** (reguła 7),
     * w odróżnieniu od nazwy pola, która jest daną i zostaje taka, jaka przyszła.
     */
    private function asText(string|int|bool $value): string
    {
        if (is_bool($value)) {
            return $this->translator->translate($value ? 'settings.value.yes' : 'settings.value.no');
        }

        return is_int($value) ? $this->translator->number($value) : $value;
    }

    /**
     * Odpowiedź kwerendy w oknie.
     *
     * **Jeden rekord czyta się inaczej niż wiele** i to jest cała reguła tego
     * miejsca. Pojedynczy (kontekst, wersja, stan odtwarzania) ma kilkanaście
     * pól i w tabeli byłby nieczytelnym paskiem, więc rozkłada się go na pary
     * `pole: wartość` — czyli `ListRow`, dokładnie tak, jak opis pliku pokazuje
     * etykietę z wartością (krok 27: „`ListRow` z dwoma polami zostaje, bo
     * etykieta z wartością to nie tabela”). Wiele rekordów to `Table`
     * z nagłówkiem, bo tam pola powtarzają się w każdym wierszu i nazwa kolumny
     * wystarczy raz.
     *
     * @return list<\LightManager\Application\Ui\Primitive\Primitive>
     */
    private function drawResult(Rect $bounds): array
    {
        $rows = $this->result?->rows() ?? [];

        if ($bounds->isEmpty() || $rows === []) {
            return [];
        }

        return count($rows) === 1 ? $this->drawRecord($rows[0], $bounds) : $this->drawTable($rows, $bounds);
    }

    /**
     * @param array<string, string|int|bool> $record
     *
     * @return list<\LightManager\Application\Ui\Primitive\Primitive>
     */
    private function drawRecord(array $record, Rect $bounds): array
    {
        $lines = [];

        foreach ($record as $field => $value) {
            $lines[] = new ListRow($field, $this->asText($value));
        }

        $offset = $this->window->keepVisible($this->selected, count($lines), $bounds->rows);
        $visible = array_slice($lines, $offset, $bounds->rows);

        return (new ListView(
            $visible,
            $this->selected - $offset,
            $this->window->position(count($lines), min($bounds->rows, count($visible))),
        ))->draw($bounds);
    }

    /**
     * @param list<array<string, string|int|bool>> $rows
     *
     * @return list<\LightManager\Application\Ui\Primitive\Primitive>
     */
    private function drawTable(array $rows, Rect $bounds): array
    {
        $fields = array_keys($rows[0]);
        $capacity = Table::capacityOf($bounds, true);
        $offset = $this->window->keepVisible($this->selected, count($rows), $capacity);
        $visible = [];

        foreach (array_slice($rows, $offset, $capacity) as $row) {
            $cells = [];

            foreach ($fields as $field) {
                $cells[] = $this->asText($row[$field] ?? '');
            }

            $visible[] = new TableRow($cells);
        }

        $columns = array_map(
            // Nazwa pola jest **daną**, nie napisem interfejsu (reguła 7): to ten
            // sam napis, który dostanie moduł pytający, i tłumaczenie zamieniłoby
            // spis danych w spis etykiet nie do odszukania w kodzie.
            static fn (string $field): Column => Column::flexible(min(mb_strlen($field), 8), label: $field),
            $fields,
        );

        return (new Table(
            $columns,
            $visible,
            $this->selected - $offset,
            $this->window->position(count($rows), min($capacity, count($visible))),
            true,
        ))->draw($bounds);
    }

    /** @return list<\LightManager\Application\Ui\Primitive\Primitive> */
    private function drawSuggestions(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $suggestions = $this->suggestions();
        $offset = $this->window->keepVisible($this->selected, count($suggestions), $bounds->rows);
        $rows = [];

        foreach (array_slice($suggestions, $offset, $bounds->rows) as $suggestion) {
            $rows[] = new ListRow(
                $suggestion->value,
                $suggestion->descriptionKey === '' ? '' : $this->translator->translate($suggestion->descriptionKey),
                $suggestion->replacesLine ? Role::Muted : Role::Text,
            );
        }

        return (new ListView(
            $rows,
            $suggestions === [] ? null : $this->selected - $offset,
            $this->window->position(count($suggestions), min($bounds->rows, count($rows))),
        ))->draw($bounds);
    }

    /**
     * Podpowiedzi dla tego, co stoi w polu.
     *
     * Wynik jest zapamiętywany do najbliższego klawisza, bo pytają o niego dwa
     * miejsca w tej samej klatce: `bounds()` (żeby wiedzieć, jak wysokie ma być
     * okno) i `draw()`. Przy podpowiedziach liczonych na żądanie — od kroku 20
     * będą to ścieżki z dysku — druga odpowiedź kosztowałaby drugie zapytanie.
     *
     * @return list<Suggestion>
     */
    private function suggestions(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = $this->input->isEmpty() ? $this->emptyLineSuggestions() : $this->matchingSuggestions();
    }

    /**
     * @return list<Suggestion>
     */
    private function emptyLineSuggestions(): array
    {
        if ($this->mode === CommandWindowMode::Queries) {
            // Historii tu nie ma i nie będzie: zapisuje się **czynność**, a nie
            // pytanie. Zostaje sam spis źródeł — czyli to, po co użytkownik
            // przełączył tryb.
            return array_map(
                static fn (QueryInterface $query): Suggestion => new Suggestion(
                    $query->name(),
                    $query->descriptionKey(),
                ),
                $this->queries?->all() ?? [],
            );
        }

        $suggestions = [];

        foreach ($this->history->entries() as $entry) {
            $suggestions[] = new Suggestion($entry, 'command.history', true);
        }

        foreach ($this->registry->all() as $command) {
            $suggestions[] = new Suggestion($command->name(), $command->descriptionKey());
        }

        return $suggestions;
    }

    /** @return list<Suggestion> */
    private function matchingSuggestions(): array
    {
        $completion = $this->parser->completion($this->input->value());

        if ($completion->completesName()) {
            if ($this->mode === CommandWindowMode::Queries) {
                return array_map(
                    static fn (QueryInterface $query): Suggestion => new Suggestion(
                        $query->name(),
                        $query->descriptionKey(),
                    ),
                    $this->queries?->matching($completion->prefix) ?? [],
                );
            }

            return array_map(
                static fn (CommandInterface $command): Suggestion => new Suggestion(
                    $command->name(),
                    $command->descriptionKey(),
                ),
                $this->registry->matching($completion->prefix),
            );
        }

        return array_map(
            static fn (string $value): Suggestion => new Suggestion($value),
            $this->argumentValues($completion->name, $completion->argumentIndex, $completion->prefix),
        );
    }

    /**
     * Wartości podpowiadane dla argumentu — stałe z pamięci, liczone wprost od
     * komendy.
     *
     * @return list<string>
     */
    private function argumentValues(string $name, int $index, string $prefix): array
    {
        $command = $this->mode === CommandWindowMode::Queries
            ? $this->queries?->find($name)
            : $this->registry->find($name);

        if (!$command instanceof SuggestsArguments) {
            return [];
        }

        $argument = $command->arguments()[$index] ?? null;

        if ($argument === null || $argument->suggestions === SuggestionSource::None) {
            return [];
        }

        $values = $argument->suggestions === SuggestionSource::Fixed
            ? $this->fixed[$name . '/' . $argument->name] ?? []
            : $command->suggestions($argument->name, $prefix);

        if ($prefix === '') {
            return $values;
        }

        return array_values(array_filter(
            $values,
            static fn (string $value): bool => str_starts_with($value, $prefix),
        ));
    }

    private function toInput(KeyPress $key): OverlayOutcome
    {
        if (!$this->input->handle($key)) {
            // Klawisz nie należy do pola — niech spróbują go klawisze globalne.
            return OverlayOutcome::ignored();
        }

        // Zmiana treści zaczyna wskazywanie od nowa: lista jest już inna.
        $this->selected = 0;
        $this->picked = false;
        // Odpowiedź dotyczyła poprzedniego pytania, a nie tego, które właśnie
        // powstaje — zostawiona wisiałaby pod wierszem, do którego nie należy.
        $this->result = null;

        return OverlayOutcome::stay();
    }

    private function pick(int $delta): OverlayOutcome
    {
        $count = $this->visibleCount();

        if ($count === 0) {
            return OverlayOutcome::stay();
        }

        $this->selected = max(0, min($this->selected + $delta, $count - 1));
        $this->picked = true;

        return OverlayOutcome::stay();
    }

    /**
     * `Tab` dopisuje najdłuższy wspólny przedrostek pasujących podpowiedzi —
     * jak w powłoce. **Przy pustym polu przełącza tryb** (krok 53): do tamtego
     * kroku nie robił wtedy nic, bo lista jest spisem wszystkiego i wspólnego
     * przedrostka nie ma, więc klawisz był wolny i nie trzeba było otwierać
     * słownika wejścia.
     */
    private function complete(): OverlayOutcome
    {
        if ($this->input->isEmpty()) {
            return $this->switchMode();
        }

        $completion = $this->parser->completion($this->input->value());
        $common = $completion->completesName()
            ? $this->namesPrefix($completion->prefix)
            : Prefix::shared(array_map(
                static fn (Suggestion $suggestion): string => $suggestion->value,
                $this->suggestions(),
            ));

        if ($common === '' || $common === $completion->prefix) {
            return OverlayOutcome::stay();
        }

        $this->input->useValue($this->lineWith($common, false));
        $this->cache = null;

        return OverlayOutcome::stay();
    }

    /** Wspólny przedrostek nazw — z tego rejestru, w którego trybie stoi okno. */
    private function namesPrefix(string $prefix): string
    {
        return $this->mode === CommandWindowMode::Queries
            ? $this->queries?->commonPrefix($prefix) ?? $prefix
            : $this->registry->commonPrefix($prefix);
    }

    /**
     * Przełączenie trybu — czynności na źródła danych i z powrotem.
     *
     * Bez rejestru kwerend okno zostaje przy czynnościach i nie mówi o tym ani
     * słowa: to jest przypadek testu składającego okno samodzielnie, a nie stan,
     * w którym może się znaleźć użytkownik.
     */
    private function switchMode(): OverlayOutcome
    {
        if ($this->queries === null || $this->queryParser === null) {
            return OverlayOutcome::stay();
        }

        $this->mode = $this->mode->other();
        $this->selected = 0;
        $this->picked = false;
        $this->cache = null;
        $this->result = null;
        $this->window->scrollBy(-PHP_INT_MAX);

        return OverlayOutcome::stay();
    }

    private function run(): OverlayOutcome
    {
        if ($this->mode === CommandWindowMode::Queries) {
            return $this->askQuery();
        }

        $line = $this->chosenLine();

        if (trim($line) === '') {
            return OverlayOutcome::stay();
        }

        $parsed = $this->parser->parse($line, $this->registry);

        if (!$parsed->isValid()) {
            // Wiersz zostaje w polu — literówka nie ma kosztować przepisania go
            // od nowa.
            $this->input->useValue($line);

            return OverlayOutcome::stay($parsed->problem);
        }

        $this->history->remember($line);

        /** @var CommandInterface $command */
        $command = $parsed->command;
        /** @var \LightManager\Application\Command\CommandInput $input */
        $input = $parsed->input;

        // Komenda, która potrzebuje okna, dostaje je **przed** wykonaniem
        // (krok 47, D78): `browser.rename` bez nazwy otwiera pole zamiast
        // odmawiać, a `browser.delete` pytanie, bez którego usuwać nie wolno.
        // Okno komend ustępuje mu miejsca — stos ma jedno piętro.
        if ($command instanceof OpensOverlay) {
            $opened = $command->overlayFor($input);

            if ($opened !== null) {
                $this->reset();

                return $opened;
            }
        }

        // Trzeci moment ogłaszany przez rdzeń (krok 46): komenda **wykonała
        // się**. Zdarzenie pada za wykonaniem, a nie przed nim, i nie zależy od
        // tego, czy komenda się udała — od skutku jest ton komunikatu, który
        // zaraz przejdzie przez `LoopState::report()`. Komenda, która zamiast
        // wykonania otworzyła okno, wyszła stąd wyżej i ogłosiła się otwarciem.
        $outcome = $command->execute($input);
        $this->events?->publish(AppEvent::CommandExecuted->value);

        if ($outcome->transition === CommandTransition::Stay) {
            $this->input->useValue($line);

            return OverlayOutcome::stay($outcome->message);
        }

        $this->reset();

        if ($outcome->transition === CommandTransition::Quit) {
            return OverlayOutcome::quit();
        }

        return $outcome->screenId === null
            ? OverlayOutcome::close($outcome->message)
            : OverlayOutcome::opens($outcome->screenId, $outcome->message);
    }

    /**
     * Pytanie zadane rejestrowi kwerend — druga połowa zdania „komenda robi,
     * kwerenda mówi”.
     *
     * Trzy różnice wobec wykonania komendy, wszystkie wynikające z tego samego:
     * **okno zostaje otwarte**, bo odpowiedź ma być widoczna; **wiersz zostaje
     * w polu**, bo o to samo pyta się zwykle drugi raz; **historia się nie
     * zapisuje**, bo pytanie nie jest czynnością.
     */
    private function askQuery(): OverlayOutcome
    {
        $line = $this->chosenLine();

        if ($this->queries === null || $this->queryParser === null || trim($line) === '') {
            return OverlayOutcome::stay();
        }

        $parsed = $this->queryParser->parse($line, $this->queries);
        $this->input->useValue($line);
        $this->selected = 0;
        $this->picked = false;
        $this->cache = null;
        $this->window->scrollBy(-PHP_INT_MAX);

        if (!$parsed->isValid()) {
            $this->result = null;

            return OverlayOutcome::stay($parsed->problem);
        }

        /** @var QueryInterface $query */
        $query = $parsed->query;
        /** @var \LightManager\Application\Command\CommandInput $input */
        $input = $parsed->input;
        $result = $this->queries->ask($query->name(), $input);

        if ($result->hasProblem()) {
            $this->result = null;

            return OverlayOutcome::stay(Message::error($this->translator->translate((string) $result->problem)));
        }

        $this->result = $result;

        return $result->isEmpty()
            ? OverlayOutcome::stay(Message::info($this->translator->translate('query.result.empty')))
            : OverlayOutcome::stay();
    }

    /** Wiersz do uruchomienia: wskazana podpowiedź albo to, co wpisano. */
    private function chosenLine(): string
    {
        $suggestions = $this->suggestions();
        $chosen = $suggestions[$this->selected] ?? null;

        if (!$this->picked || $chosen === null) {
            return $this->input->value();
        }

        return $this->lineWith($chosen->value, $chosen->replacesLine);
    }

    /**
     * Wiersz po wstawieniu podpowiedzi: wpis historii podmienia całość, nazwa
     * komendy i wartość argumentu — samo uzupełniane słowo.
     */
    private function lineWith(string $value, bool $replacesLine): string
    {
        if ($replacesLine) {
            return $value;
        }

        $words = $this->parser->words($this->input->value());
        $completion = $this->parser->completion($this->input->value());
        $position = $completion->argumentIndex + 1;

        $words = array_slice($words, 0, $position);
        $words[] = $value;

        return implode(' ', array_map(self::quoted(...), $words));
    }

    /** Wartość ze spacją wraca do wiersza w cudzysłowie — tak, jak parser ją przyjmie. */
    private static function quoted(string $value): string
    {
        return str_contains($value, ' ') ? '"' . $value . '"' : $value;
    }

    private function closed(): OverlayOutcome
    {
        $this->reset();

        return OverlayOutcome::close();
    }
}
