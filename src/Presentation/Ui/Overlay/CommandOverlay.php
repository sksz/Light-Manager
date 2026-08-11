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
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Panel;
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

    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly CommandLineParser $parser,
        private readonly CommandHistory $history,
        private readonly TranslatorPort $translator,
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
        foreach ($this->registry->all() as $command) {
            if (!$command instanceof SuggestsArguments) {
                continue;
            }

            foreach ($command->arguments() as $argument) {
                if ($argument->suggestions !== SuggestionSource::Fixed) {
                    continue;
                }

                $this->fixed[$command->name() . '/' . $argument->name] = $command->suggestions($argument->name, '');
            }
        }
    }

    public function id(): string
    {
        return self::ID;
    }

    /** Wejście do okna zaczyna je od pustego wiersza — jak każde otwarcie ekranu. */
    public function reset(): void
    {
        $this->input->clear();
        $this->selected = 0;
        $this->picked = false;
        $this->cache = null;
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
        // wysokość zależy wyłącznie od wysokości terminala, więc pas podglądu
        // niczego tu nie zmienia.
        $bottom = (new HudLayout($rows, $columns))->status->row - 1;

        if ($bottom < 0) {
            $bottom = max(0, $rows - 1);
        }

        $height = min(
            count($this->suggestions()) + self::CHROME_ROWS,
            max(1, intdiv($rows, 2)) + self::CHROME_ROWS,
            $bottom + 1,
        );

        return new Rect(max(0, $bottom - $height + 1), 0, $height, $columns);
    }

    public function draw(Rect $bounds): array
    {
        $primitives = (new Panel($this->translator->translate('layout.zone.command')))->draw($bounds);
        $inner = Panel::inner($bounds);

        if ($inner->isEmpty()) {
            return $primitives;
        }

        foreach ($this->drawSuggestions($inner->rowsFrom(0, $inner->rows - 1)) as $primitive) {
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

    /** @return list<Suggestion> */
    private function emptyLineSuggestions(): array
    {
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
        $command = $this->registry->find($name);

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

        return OverlayOutcome::stay();
    }

    private function pick(int $delta): OverlayOutcome
    {
        $count = count($this->suggestions());

        if ($count === 0) {
            return OverlayOutcome::stay();
        }

        $this->selected = max(0, min($this->selected + $delta, $count - 1));
        $this->picked = true;

        return OverlayOutcome::stay();
    }

    /**
     * `Tab` dopisuje najdłuższy wspólny przedrostek pasujących podpowiedzi —
     * jak w powłoce. Przy pustym polu nie robi nic: lista jest wtedy spisem
     * wszystkiego i wspólnego przedrostka nie ma.
     */
    private function complete(): OverlayOutcome
    {
        if ($this->input->isEmpty()) {
            return OverlayOutcome::stay();
        }

        $completion = $this->parser->completion($this->input->value());
        $common = $completion->completesName()
            ? $this->registry->commonPrefix($completion->prefix)
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

    private function run(): OverlayOutcome
    {
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
        $outcome = $command->execute($input);

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
