<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\Component\TreeView;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Okno modułu dźwięku: playlista, którą widać (krok 45).
 *
 * **Komponentu rdzenia nie dokłada ani jednego** i to jest sprawdzian tego kroku,
 * ten sam, który przeszło menu kontekstowe w kroku 32: całość składa się
 * z `ListView`, `Label`, `TextInput` i `ScrollWindow`, czyli z rzeczy, które
 * stały w rdzeniu, zanim ten krok się zaczął. Gdyby playlista czegoś tu
 * potrzebowała, znaczyłoby to, że komponentów brakuje — a nie że trzeba dopisać
 * kolejny.
 *
 * Ekran jest **drugim sprawdzianem kontraktu modułu w tę samą stronę, co krok
 * 21**, z jedną różnicą: tamten pytał o moduł rysujący główną funkcję aplikacji,
 * ten — o moduł, który **pracuje, gdy go nie widać**. Praca dzieje się w takcie
 * (`AudioModule::tick()`); okno jest wyłącznie widokiem na jej stan i kompletem
 * klawiszy do niej.
 *
 * Utwory wchodzą **trzema drogami** (D82 nr 2) i dwie z nich są tutaj: `F5`
 * bierze wpis zaznaczony w przeglądarce (przez `ReadsContext` — moduł nie poznaje
 * cudzego modułu, tylko ścieżkę), `F7` otwiera pole na ścieżkę wpisaną z ręki.
 * Trzecią jest komenda `audio.add`, która działa także wtedy, gdy tego okna nie
 * widać.
 *
 * Klawisze czynności powtarzają układ znany z przeglądarki (`F5` weź, `F7` nowa
 * pozycja, `F8` usuń) i to jest jedyny powód, dla którego akurat te: numer
 * czytelny w stopce jest lepszy od litery, którą trzeba pamiętać, a menadżer
 * dwupanelowy uczy tych trzech numerów od pierwszego uruchomienia.
 */
final class AudioScreen implements ScreenInterface, ReadsContext, Resettable, NeedsTime, DeclaresFocus
{
    /** Znacznik utworu granego — ten sam trójkąt, którym drzewo znaczy gałąź zwiniętą. */
    private const PLAYING_MARK = TreeView::CLOSED . ' ';

    /** Wcięcie pozostałych pozycji, żeby nazwy stały w jednej kolumnie. */
    private const IDLE_MARK = '  ';

    private readonly ScrollWindow $window;

    private int $selected = 0;

    /** Pole na ścieżkę wpisywaną z ręki; `null`, gdy nikt niczego nie wpisuje. */
    private ?TextInput $input = null;

    private float $now = 0.0;

    private ModuleContext $context;

    public function __construct(
        private readonly PlaylistPlayer $player,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->context = new ModuleContext();
    }

    public function id(): string
    {
        return AudioSettings::ID;
    }

    public function labelKey(): string
    {
        return 'module.' . AudioSettings::ID . '.name';
    }

    /**
     * Górny pas: co gra teraz i w jakim trybie.
     *
     * Tryb stoi po prawej, bo odpowiada na pytanie zadawane rzadziej („co się
     * stanie, gdy ten utwór się skończy”), a nazwa utworu ma dostać całą resztę
     * wiersza — ścieżki bywają długie.
     *
     * Stany są **trzy, a nie dwa**, i różnicę widać dopiero w używaniu: utwór
     * zatrzymany pauzą wciąż jest tym, do którego wróci `Enter` albo spacja, więc
     * pas ma o nim mówić — ale nie słowem „gra”, bo nie gra.
     */
    public function header(): ScreenZone
    {
        $playing = $this->player->nowPlaying();
        $left = match (true) {
            $playing === null => $this->text('nothing'),
            $this->player->isPlaying() => $this->text('nowPlaying', ['track' => $playing->name]),
            default => $this->text('paused', ['track' => $playing->name]),
        };

        return new ScreenZone(
            'module.' . AudioSettings::ID . '.zone.now',
            new Label($left, $this->text('mode.' . $this->player->mode()->value)),
        );
    }

    /**
     * Wejście na ekran przelicza dostępność plików.
     *
     * Jest to jedyny moment, w którym wolno o nią zapytać dysk: takt nie ma prawa
     * dotknąć wejścia-wyjścia, a rysowanie robiłoby to trzydzieści razy na
     * sekundę. Playlista, na którą właśnie patrzymy, jest za to warta jednego
     * sprawdzenia.
     */
    public function reset(): void
    {
        $this->player->refresh();
        $this->input = null;
        $this->clampSelection();
    }

    public function useTime(float $now): void
    {
        $this->now = $now;
    }

    public function useContext(ModuleContext $context): void
    {
        $this->context = $context;
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $list = $this->input === null ? $bounds : $bounds->rowsFrom(0, $bounds->rows - 1);
        $primitives = $this->body($list);

        if ($this->input !== null) {
            $this->input->useTime($this->now);

            foreach ($this->input->draw($bounds->line($bounds->rows - 1)) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    /** @return list<Primitive> */
    private function body(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $playlist = $this->player->playlist();

        if ($playlist->isEmpty()) {
            // Powód prawdziwy wyprzedza ogólny: playlista pusta dlatego, że jej
            // pliku nie dało się przeczytać, ma o tym powiedzieć wprost.
            return (new Label($this->player->problem() ?? $this->text('playlist.empty'), '', Role::Muted))
                ->draw($bounds->line(0));
        }

        $this->clampSelection();
        $total = $playlist->count();
        $offset = $this->window->keepVisible($this->selected, $total, $bounds->rows);

        return (new ListView(
            array_slice($this->rows(), $offset, $bounds->rows),
            $this->selected - $offset,
            $this->window->position($total, min($bounds->rows, $total)),
        ))->draw($bounds);
    }

    /**
     * Wiersze playlisty: grany w akcencie ze znacznikiem, brakujący wyszarzony
     * i podpisany powodem.
     *
     * @return list<ListRow>
     */
    private function rows(): array
    {
        $playlist = $this->player->playlist();
        $playing = $playlist->playing();
        $rows = [];

        foreach ($playlist->entries() as $index => $entry) {
            $rows[] = new ListRow(
                ($index === $playing ? self::PLAYING_MARK : self::IDLE_MARK) . $entry->name,
                $entry->missing ? $this->text('playlist.missing') : '',
                match (true) {
                    $entry->missing => Role::Muted,
                    $index === $playing => Role::Accent,
                    default => Role::Text,
                },
            );
        }

        return $rows;
    }

    public function bindings(): array
    {
        $bindings = $this->focus()->bindings;

        if ($this->input !== null) {
            return $bindings;
        }

        $bindings[] = KeyBinding::of([Key::F5], 'module.' . AudioSettings::ID . '.key.take', 'module.' . AudioSettings::ID . '.key.take.short');
        $bindings[] = KeyBinding::of([Key::F7], 'module.' . AudioSettings::ID . '.key.type', 'module.' . AudioSettings::ID . '.key.type.short');

        if (!$this->player->playlist()->isEmpty()) {
            $bindings[] = KeyBinding::of([Key::F8, Key::Delete], 'module.' . AudioSettings::ID . '.key.remove', 'module.' . AudioSettings::ID . '.key.remove.short');
            $bindings[] = KeyBinding::shifted([Key::ArrowUp, Key::ArrowDown], 'module.' . AudioSettings::ID . '.key.move', 'module.' . AudioSettings::ID . '.key.move.short');
        }

        $bindings[] = KeyBinding::of([Key::Escape], 'help.key.back', 'help.key.back.short');

        return $bindings;
    }

    /**
     * Dwa miejsca ogniska: lista i pole na ścieżkę.
     *
     * Pole jest osobnym miejscem, bo zużywa **każdy znak** — dopóki stoi, żaden
     * klawisz listy nie działa, a stopka obiecująca wtedy `F8` kłamałaby.
     */
    public function focus(): FocusHint
    {
        if ($this->input !== null) {
            $bindings = $this->input->bindings();
            $bindings[] = KeyBinding::of([Key::Enter], 'module.' . AudioSettings::ID . '.key.confirm');
            // Własny klucz, a nie `command.key.close` z okna komend: ten sam
            // klawisz znaczy tu co innego, a stopka obiecywała przez to
            // „zamknij okno komend” nad polem, które oknem komend nie jest
            // (wyszło dopiero z klatki pod XTermem).
            $bindings[] = KeyBinding::of([Key::Escape], 'module.' . AudioSettings::ID . '.key.cancel');

            return new FocusHint('module.' . AudioSettings::ID . '.focus.path', $bindings);
        }

        $bindings = [KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short')];

        if (!$this->player->playlist()->isEmpty()) {
            $bindings[] = KeyBinding::of([Key::Enter], 'module.' . AudioSettings::ID . '.key.play', 'module.' . AudioSettings::ID . '.key.play.short');
            $bindings[] = KeyBinding::character(' ', 'module.' . AudioSettings::ID . '.key.pause', 'module.' . AudioSettings::ID . '.key.pause.short');
        }

        return new FocusHint('module.' . AudioSettings::ID . '.focus.playlist', $bindings);
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($this->input !== null) {
            return $this->toInput($key);
        }

        // `Shift` rozstrzyga się **przed** gałęziami klawiszy, wzorem
        // `BrowserScreen::shifted()`: goła strzałka nie ma prawa złapać
        // przestawienia, a przestawienie — ruchu kursora.
        if ($key->shift && ($key->key === Key::ArrowUp || $key->key === Key::ArrowDown)) {
            return $this->moved($key->key === Key::ArrowUp ? -1 : 1);
        }

        return match (true) {
            $key->key === Key::Escape => ScreenOutcome::close(),
            $key->key === Key::ArrowUp => $this->select(-1),
            $key->key === Key::ArrowDown => $this->select(1),
            $key->key === Key::Home => $this->select(-PHP_INT_MAX),
            $key->key === Key::End => $this->select(PHP_INT_MAX),
            $key->key === Key::Enter => $this->play(),
            $key->key === Key::F5 => $this->takeFromContext(),
            $key->key === Key::F7 => $this->askForPath(),
            $key->key === Key::F8, $key->key === Key::Delete => $this->remove(),
            $key->key === Key::Character && $key->raw === ' ' && !$key->ctrl && !$key->alt => $this->pause(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Klawisze pola na ścieżkę. Pole zużywa **każdy znak**, więc ekran dostaje
     * z powrotem wyłącznie `Enter` i `Esc` — dokładnie tak, jak w trybie edycji
     * pozycji tekstowej na ekranie ustawień (krok 20).
     */
    private function toInput(KeyPress $key): ScreenOutcome
    {
        $input = $this->input;

        if ($input === null) {
            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Escape) {
            $this->input = null;

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Enter) {
            $path = $input->value();
            $this->input = null;

            return $this->added($path);
        }

        $input->handle($key);

        return ScreenOutcome::stay();
    }

    private function select(int $delta): ScreenOutcome
    {
        $total = $this->player->playlist()->count();

        if ($total === 0) {
            return ScreenOutcome::stay();
        }

        $this->selected = max(0, min($total - 1, $this->selected + $delta));

        return ScreenOutcome::stay();
    }

    /** Przestawienie pozycji — kursor wędruje **razem z nią**, bo wskazuje utwór. */
    private function moved(int $direction): ScreenOutcome
    {
        $this->selected = $this->player->move($this->selected, $direction);

        return ScreenOutcome::stay();
    }

    private function play(): ScreenOutcome
    {
        $problem = $this->player->play($this->selected);

        return $problem === null
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::error($problem));
    }

    /**
     * Pauza i wznowienie na jednym klawiszu — bo silnik **pauzuje**, a nie
     * przewija (krok 36), więc rozdzielenie ich obiecywałoby różnicę, której pod
     * spodem nie ma.
     */
    private function pause(): ScreenOutcome
    {
        if ($this->player->isPlaying()) {
            $this->player->pause();

            return ScreenOutcome::stay();
        }

        $problem = $this->player->resume();

        return $problem === null
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::error($problem));
    }

    private function remove(): ScreenOutcome
    {
        if (!$this->player->remove($this->selected)) {
            return ScreenOutcome::stay();
        }

        $this->clampSelection();

        return ScreenOutcome::stay();
    }

    /**
     * Wpis zaznaczony w przeglądarce — **przez kontekst sesji**, a nie przez
     * poznanie cudzego modułu.
     *
     * Kontekst pusty jest zwykłym stanem (katalog pusty albo nieczytelny), więc
     * kończy się zdaniem, a nie milczeniem: klawisz, który nic nie robi i nic nie
     * mówi, wygląda jak zepsuty.
     */
    private function takeFromContext(): ScreenOutcome
    {
        $path = $this->context->selectionPath();

        if ($path === null) {
            return ScreenOutcome::stay(Message::warning($this->text('playlist.noSelection')));
        }

        return $this->added($path);
    }

    private function askForPath(): ScreenOutcome
    {
        $this->input = new TextInput($this->text('playlist.path') . ': ');

        return ScreenOutcome::stay();
    }

    /** Nowa pozycja staje na końcu listy i **kursor idzie za nią** — widać, co się dodało. */
    private function added(string $path): ScreenOutcome
    {
        $problem = $this->player->add($path);

        if ($problem !== null) {
            return ScreenOutcome::stay(Message::error($problem));
        }

        $this->selected = max(0, $this->player->playlist()->count() - 1);

        return ScreenOutcome::stay(Message::info($this->text('playlist.added', ['track' => basename($path)])));
    }

    private function clampSelection(): void
    {
        $total = $this->player->playlist()->count();
        $this->selected = $total === 0 ? 0 : max(0, min($total - 1, $this->selected));
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
