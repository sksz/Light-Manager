<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

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
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Registry\RegistryMode;
use LightManager\Module\Docker\Application\Registry\RegistryStage;
use LightManager\Module\Docker\Application\RegistryBrowse;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Zawartość rejestru — **piąta postać ekranu modułu**, klawisz `r` (krok 61,
 * etap 2).
 *
 * Rodzeństwo `EnvironmentScreen` i napisane tak samo, z jedną różnicą, która
 * bierze się z rozmówcy: spis środowisk czyta się z książki i jest gotowy
 * natychmiast, a zawartość rejestru **przychodzi z sieci** — więc panel ma stan
 * „czekam”, i to nawet trzy różne (`RegistryStage`).
 *
 * **Dwa zachowania, jedna postać.** Rejestr wystawiający `/v2/_catalog` pokazuje
 * spis repozytoriów, a `Enter` schodzi w etykiety. Rejestr, który katalogu nie
 * ma — a nie mają go GHCR ani Docker Hub — pokazuje zaproszenie „podaj nazwę
 * obrazu” i tę samą listę etykiet po wpisaniu. **Nie jest to stan błędu** i cały
 * panel jest tak napisany, żeby nie wyglądał na zepsuty.
 *
 * Pytanie pada **na żądanie**: wejście w widok niczego nie ściąga, bo katalog
 * cudzego serwera to ruch, którego nikt nie zamawiał (`Ctrl`+`R` albo `Enter`).
 */
final class RegistryPane
{
    private int $selected = 0;

    private readonly ScrollWindow $window;

    private ?Rect $lastBounds = null;

    public function __construct(
        private readonly RegistryBrowse $browse,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->window->useContext('docker.registry');
    }

    public function reset(): void
    {
        $this->selected = 0;
        $this->window->useContext('docker.registry');
    }

    /** Zdanie górnego pasa: który rejestr i na czym stoi rozmowa. */
    public function headerText(): string
    {
        $view = $this->browse->view();

        if ($view->registry === '') {
            return $this->text('registry.header.none');
        }

        if ($view->stage->isWorking()) {
            return $this->text('registry.header.working', [
                'registry' => $view->registry,
                'stage' => $this->translator->translate($view->stage->labelKey()),
            ]);
        }

        return $view->mode === RegistryMode::Tags
            ? $this->text('registry.header.tags', ['registry' => $view->registry, 'image' => $view->image])
            : $this->text('registry.header.catalog', ['registry' => $view->registry]);
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $view = $this->browse->view();

        $notice = $this->noticeFor();

        if ($notice !== null) {
            return (new Label($notice, '', Role::Muted))->draw($bounds);
        }

        $this->clampSelection();
        $capacity = max(1, $bounds->rows);
        $count = count($view->rows);
        $this->window->keepVisible($this->selected, $count, $capacity);

        $rows = [];

        foreach ($view->rows as $name) {
            $rows[] = new ListRow($name);
        }

        return (new ListView($rows, $this->selected, $this->window->position($count, $capacity)))->draw($bounds);
    }

    /** @return list<KeyBinding> */
    public function bindings(): array
    {
        $needsName = $this->browse->mode() === RegistryMode::NeedsName;

        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of(
                [Key::Enter],
                $this->key($needsName ? 'registry.key.name' : 'registry.key.open'),
                $this->key($needsName ? 'registry.key.name.short' : 'registry.key.open.short'),
            ),
            KeyBinding::of([Key::F7], $this->key('registry.key.name'), $this->key('registry.key.name.short')),
            KeyBinding::ctrl('r', $this->key('registry.key.refresh'), $this->key('key.refresh.short')),
            KeyBinding::of([Key::Escape], $this->key('key.back'), $this->key('key.back.short')),
        ];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        $view = $this->browse->view();

        return match (true) {
            $key->key === Key::ArrowUp => $this->move(-1),
            $key->key === Key::ArrowDown => $this->move(1),
            $key->key === Key::F7 => ScreenOutcome::opens($this->namePrompt()),
            $key->key === Key::Enter => $this->open(),
            $key->key === Key::Character && $key->raw === 'r' && $key->ctrl => $this->refresh(),
            default => ScreenOutcome::stay(),
        };
    }

    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null) {
            return ScreenOutcome::stay();
        }

        if ($event->isScroll()) {
            $this->window->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button !== PointerButton::Left) {
            return ScreenOutcome::stay();
        }

        $row = $this->window->offset() + ($event->row - $bounds->row);
        $count = count($this->browse->view()->rows);

        if ($row < 0 || $row >= $count) {
            return ScreenOutcome::stay();
        }

        $this->selected = $row;

        return ScreenOutcome::stay();
    }

    /** Odświeżenie **na żądanie** — jedyna droga, którą pytanie w ogóle pada. */
    public function refresh(): ScreenOutcome
    {
        if ($this->browse->mode() === RegistryMode::Tags && $this->browse->view()->image !== '') {
            $this->browse->openTags($this->browse->view()->image);

            return ScreenOutcome::stay();
        }

        $this->browse->openCatalog();
        $this->selected = 0;

        return ScreenOutcome::stay();
    }

    /**
     * Zdanie zamiast listy — **i żadne z nich nie mówi o błędzie tam, gdzie
     * błędu nie ma**.
     */
    private function noticeFor(): ?string
    {
        $view = $this->browse->view();

        if ($view->registry === '') {
            return $this->text('registry.empty.none');
        }

        if ($view->problemKey !== null) {
            return $this->translator->translate($view->problemKey);
        }

        if ($view->mode === RegistryMode::NeedsName) {
            return $this->text('registry.empty.needsName');
        }

        if ($view->stage->isWorking()) {
            return $this->translator->translate($view->stage->labelKey());
        }

        if ($view->stage === RegistryStage::Idle) {
            return $this->text('registry.empty.idle');
        }

        return $view->isEmpty() ? $this->text('registry.empty.nothing') : null;
    }

    private function open(): ScreenOutcome
    {
        $view = $this->browse->view();

        if ($view->mode === RegistryMode::NeedsName || ($view->isEmpty() && $view->stage === RegistryStage::Idle)) {
            return ScreenOutcome::opens($this->namePrompt());
        }

        if ($view->mode !== RegistryMode::Catalog) {
            return ScreenOutcome::stay();
        }

        $name = $view->rows[$this->selected] ?? '';

        if ($name === '') {
            return ScreenOutcome::stay();
        }

        $this->browse->openTags($name);
        $this->selected = 0;

        return ScreenOutcome::stay();
    }

    private function namePrompt(): PromptOverlay
    {
        return new PromptOverlay(
            'module.' . DockerSettings::ID . '.registry.prompt',
            [],
            $this->browse->view()->image,
            function (string $value): OverlayOutcome {
                $value = trim($value);

                if ($value === '') {
                    return OverlayOutcome::close();
                }

                $this->browse->openTags($value);
                $this->selected = 0;

                return OverlayOutcome::close();
            },
            $this->translator,
        );
    }

    private function move(int $by): ScreenOutcome
    {
        $count = count($this->browse->view()->rows);

        if ($count === 0) {
            return ScreenOutcome::stay();
        }

        $this->selected = max(0, min($count - 1, $this->selected + $by));

        return ScreenOutcome::stay();
    }

    private function clampSelection(): void
    {
        $count = count($this->browse->view()->rows);
        $this->selected = $count === 0 ? 0 : max(0, min($count - 1, $this->selected));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $suffix, array $parameters = []): string
    {
        return $this->translator->translate($this->key($suffix), $parameters);
    }

    private function key(string $suffix): string
    {
        return 'module.' . DockerSettings::ID . '.' . $suffix;
    }

    /** Zdanie po nieudanej rozmowie — bierze je ekran, gdy chce je pokazać w pasku. */
    public function problem(): ?Message
    {
        $key = $this->browse->view()->problemKey;

        return $key === null ? null : Message::error($this->translator->translate($key));
    }
}
