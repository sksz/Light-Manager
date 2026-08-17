<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Overlay;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\UseCase\MoveSelectionUseCase;
use LightManager\Module\Browser\Presentation\BrowserState;
use LightManager\Presentation\Ui\AcceptsPaste;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Pole filtra listy — wiersz wpisywania przy dolnej krawędzi (krok 30).
 *
 * Stoi tam, gdzie okno komend z kroku 19, i z tego samego powodu: pas nad
 * paskiem stanu zasłania najmniej treści, a oczy zostają tam, gdzie się pisze.
 * Różnica jest jedna, za to zasadnicza — **okno komend zasłania to, na co
 * użytkownik nie patrzy, a filtr zasłania to, na co patrzy właśnie najbardziej**.
 * Stąd trzy wiersze zamiast wysokiej listy podpowiedzi: lista pod spodem jest
 * treścią tego okna, tyle że rysuje ją ekran.
 *
 * Okno leży w katalogu modułu, a nie rdzenia, bo zna `BrowserState` — panel
 * przeglądarki (reguła 11, precedens `PathLine` i `PreviewBox` z kroku 21). Nie
 * uogólniamy go na rdzeń, dopóki nie ma drugiego użytkownika: rdzeniowe „pole,
 * które coś zawęża” zaprojektowane na jeden przypadek byłoby API z domysłu
 * (reguła 13).
 *
 * **Panel jest zapamiętany przy otwarciu i to jest bezpieczne**: `Tab` nie
 * należy do tego okna ani do klawiszy globalnych, więc ognisko nie ma jak się
 * przenieść, dopóki okno stoi.
 *
 * Rozstrzygnięcie ze startu kroku, którego plan nie miał na liście: **`Enter`
 * zatwierdza, `Esc` odmawia** — dokładnie tak, jak przy oknie potwierdzenia
 * (D56) i zgodnie z P3. Zatwierdzenie zostawia listę zawężoną i zaznaczenie tam,
 * dokąd użytkownik doszedł; odmowa przywraca pełną listę **i wpis sprzed
 * otwarcia**, jeśli nadal istnieje. Bez tego rozdziału „zaznaczenie przeżywa
 * filtr” znaczyłoby dwie sprzeczne rzeczy naraz.
 */
final class FilterOverlay implements OverlayInterface, NeedsTime, AcceptsPaste
{
    private const ID = 'browser.filter';

    /** Obwódka (dwa wiersze) plus wiersz wpisywania. */
    private const CHROME_ROWS = 3;

    private readonly TextInput $input;

    /**
     * Wpis zaznaczony w chwili otwarcia — cel powrotu przy odmowie.
     *
     * `null` znaczy „katalog był pusty”; wtedy nie ma dokąd wracać i odmowa
     * kończy się samym zdjęciem filtra.
     */
    private readonly ?string $restore;

    public function __construct(
        private readonly BrowserState $pane,
        private readonly MoveSelectionUseCase $moveSelection,
        private readonly TranslatorPort $translator,
    ) {
        $this->restore = $pane->directory()->selectedEntry()?->name;
        $this->input = new TextInput($translator->translate('module.browser.filter.prompt'));

        // Otwarcie przy filtrze już nałożonym wraca do jego treści, a nie kasuje
        // jej w milczeniu: `/` na zawężonej liście znaczy „popraw”, nie „zacznij
        // od nowa”.
        $this->input->useValue($pane->filter()->value);
    }

    public function id(): string
    {
        return self::ID;
    }

    /** Zegar dla karetki — okno przekazuje go dalej, bo to pole nią mruga. */
    public function useTime(float $now): void
    {
        $this->input->useTime($now);
    }

    /**
     * Pas nad paskiem stanu — ta sama zasada, co w oknie komend: pasek stanu
     * należy do rdzenia i okno nie ma prawa go zasłonić.
     */
    public function bounds(int $rows, int $columns): Rect
    {
        $bottom = (new HudLayout($rows, $columns))->status->row - 1;

        if ($bottom < 0) {
            $bottom = max(0, $rows - 1);
        }

        $height = min(self::CHROME_ROWS, $bottom + 1);

        return new Rect(max(0, $bottom - $height + 1), 0, $height, $columns);
    }

    public function draw(Rect $bounds): array
    {
        $primitives = (new Panel($this->translator->translate('module.browser.filter.zone')))->draw($bounds);
        $inner = Panel::inner($bounds);

        if ($inner->isEmpty()) {
            return $primitives;
        }

        foreach ($this->input->draw($inner->line($inner->rows - 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::Enter], 'module.browser.filter.key.accept'),
            KeyBinding::of([Key::Escape], 'module.browser.filter.key.cancel'),
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            ...$this->input->bindings(),
        ];
    }

    /**
     * Strzałki pionowe **zużywa okno i oddaje je liście pod spodem**, zamiast je
     * przepuścić.
     *
     * Przepuszczenie nie zadziałałoby: klawisz nieprzyjęty przez okno próbuje
     * jeszcze klawiszy globalnych, ale do ekranu **nigdy nie schodzi** (reguła
     * kroku 19). Bez tej ścieżki filtr byłby polem, w którym da się pisać, ale
     * nie da się wybrać tego, co się znalazło — czyli połową funkcji.
     */
    public function handle(KeyPress $key): OverlayOutcome
    {
        return match ($key->key) {
            Key::Enter => OverlayOutcome::close(),
            Key::Escape => $this->cancelled(),
            Key::ArrowUp => $this->moved(up: true),
            Key::ArrowDown => $this->moved(up: false),
            default => $this->toInput($key),
        };
    }

    /**
     * Treść schowka w polu filtra — **wraz z zawężeniem listy** (krok 57).
     *
     * Filtr zakłada się przy każdej zmianie treści, nie po `Enter`ze, więc
     * wklejenie musi zrobić to samo, co wpisanie ostatniego znaku wzorca.
     * Gdyby tego zabrakło, wklejony wzorzec stałby w polu, a lista pokazywałaby
     * poprzedni — rozjazd widoczny dopiero po dopisaniu litery.
     */
    public function paste(string $text): bool
    {
        if (!$this->input->paste($text)) {
            return false;
        }

        $this->pane->useFilter($this->input->value());

        return true;
    }

    private function cancelled(): OverlayOutcome
    {
        $this->pane->clearFilter($this->restore);

        return OverlayOutcome::close();
    }

    private function moved(bool $up): OverlayOutcome
    {
        $directory = $this->pane->directory();
        $up ? $this->moveSelection->up($directory) : $this->moveSelection->down($directory);
        $this->pane->selectionChanged();

        return OverlayOutcome::stay();
    }

    private function toInput(KeyPress $key): OverlayOutcome
    {
        if (!$this->input->handle($key)) {
            // Klawisz nie należy do pola — niech spróbują go klawisze globalne.
            return OverlayOutcome::ignored();
        }

        $this->pane->useFilter($this->input->value());

        return OverlayOutcome::stay();
    }
}
