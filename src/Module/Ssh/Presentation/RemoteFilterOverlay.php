<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteNameFilter;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Pole filtra zdalnej listy (krok 49).
 *
 * Powtarza `FilterOverlay` przeglądarki co do zachowania — pas nad paskiem
 * stanu, `Enter` zatwierdza, `Esc` odmawia i przywraca wpis sprzed otwarcia,
 * strzałki zużywa okno i oddaje je liście pod spodem. Powtarza je **świadomie
 * i z tego samego powodu, co reszta modułu** (reguła 15): tamto okno zna
 * `BrowserState`, czyli typ cudzego modułu, więc nie da się go tu użyć, a
 * uogólnienie go na rdzeń byłoby API projektowanym z domysłu (reguła 13).
 *
 * Powtórzone jest **zachowanie, nie mechanizm**: pole tekstowe, obwódka, układ
 * i wiązania pochodzą z rdzenia, tak samo jak tam.
 */
final class RemoteFilterOverlay implements OverlayInterface, NeedsTime
{
    private const ID = 'ssh.filter';

    /** Obwódka (dwa wiersze) plus wiersz wpisywania. */
    private const CHROME_ROWS = 3;

    private readonly TextInput $input;

    /** Wpis zaznaczony w chwili otwarcia — cel powrotu przy odmowie. */
    private readonly ?string $restore;

    /**
     * @param RemoteBrowser $browser **wyłącznie do czynności** — nadania filtra,
     *                               wyczyszczenia go i przestawienia kursora
     */
    public function __construct(
        private readonly RemoteBrowser $browser,
        private readonly TranslatorPort $translator,
        private readonly SshQueries $reader,
    ) {
        $this->restore = $reader->selected()?->name;
        $this->input = new TextInput($translator->translate('module.' . SshSettings::ID . '.filter.prompt'));
        $this->input->useValue($reader->remote()->filter->value);
    }

    public function id(): string
    {
        return self::ID;
    }

    public function useTime(float $now): void
    {
        $this->input->useTime($now);
    }

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
        $primitives = (new Panel(
            $this->translator->translate('module.' . SshSettings::ID . '.filter.zone'),
        ))->draw($bounds);
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
            KeyBinding::of([Key::Enter], 'module.' . SshSettings::ID . '.filter.key.accept'),
            KeyBinding::of([Key::Escape], 'module.' . SshSettings::ID . '.filter.key.cancel'),
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            ...$this->input->bindings(),
        ];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        return match ($key->key) {
            Key::Enter => OverlayOutcome::close(),
            Key::Escape => $this->cancelled(),
            Key::ArrowUp => $this->moved(-1),
            Key::ArrowDown => $this->moved(1),
            default => $this->toInput($key),
        };
    }

    /**
     * Odmowa zdejmuje filtr **i wraca na wpis sprzed otwarcia**, o ile ten wpis
     * nadal jest na liście.
     */
    private function cancelled(): OverlayOutcome
    {
        $this->browser->clearFilter();
        $restore = $this->restore;

        if ($restore !== null) {
            $index = $this->reader->indexOf($restore);

            if ($index !== null) {
                $this->browser->putCursor($index);
            }
        }

        return OverlayOutcome::close();
    }

    private function moved(int $delta): OverlayOutcome
    {
        $this->browser->moveCursor($delta);

        return OverlayOutcome::stay();
    }

    private function toInput(KeyPress $key): OverlayOutcome
    {
        if (!$this->input->handle($key)) {
            return OverlayOutcome::ignored();
        }

        $this->browser->useFilter(new RemoteNameFilter($this->input->value()));

        return OverlayOutcome::stay();
    }
}
