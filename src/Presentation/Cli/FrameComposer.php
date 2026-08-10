<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Port\ViewportPort;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Application\UseCase\PreviewSelectedEntryUseCase;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Składa klatkę z aktywnego ekranu i oddaje ją rendererowi.
 *
 * Następca trzech przypadków użycia `Render*FrameUseCase`, z których każdy
 * budował całą klatkę od zera i miał własny rachunek przewijania. Tutaj zostaje
 * to, co **wspólne**: podział okna na strefy, oprawa paneli, ścieżka u góry,
 * pas podglądu i pasek stanu. Środek panelu rysuje ekran i tylko on.
 *
 * Klatka powstaje z trzech płaszczyzn i ten podział ma powód wydajnościowy:
 *
 * 1. **oprawa** — panele, nawiasy i etykiety stref; nie zmienia się, dopóki nie
 *    zmieni się okno ani motyw, więc renderer podaje ją z pamięci podręcznej,
 *    zamiast rysować od nowa trzydzieści razy na sekundę (krok 17, dźwignia 4),
 * 2. **treść** — wszystko, co zmienia się między klatkami,
 * 3. **okno nakładane** — obecne tylko wtedy, gdy jest co pokazać.
 */
final class FrameComposer
{
    /** Poniżej tylu kolumn formatowanie wiersza przestaje mieć sens. */
    private const MINIMUM_COLUMNS = 20;

    /** @param list<KeyBinding> $global wiązania rdzenia — źródło podpowiedzi w stopce */
    public function __construct(
        private readonly FrameRendererPort $renderer,
        private readonly ViewportPort $viewport,
        private readonly TranslatorPort $translator,
        private readonly PreviewSelectedEntryUseCase $preview,
        private readonly array $global = [],
    ) {
    }

    public function render(ScreenInterface $screen, LoopState $state): void
    {
        $rows = $this->viewport->rows();
        $columns = max(self::MINIMUM_COLUMNS, $this->viewport->columns());
        $layout = new HudLayout($rows, $columns, $screen->usesPreview());

        $planes = [
            $this->chrome($layout, $screen),
            $this->content($layout, $screen, $state),
        ];

        $overlay = $state->overlays()->current();

        if ($overlay !== null) {
            $planes[] = $this->overlay($overlay, $rows, $columns, $state->now());
        }

        $this->renderer->render(new Frame($planes));
    }

    /** Oprawa: obwódki stref wraz z etykietami i nawiasami narożnymi. */
    private function chrome(HudLayout $layout, ScreenInterface $screen): Plane
    {
        $primitives = [];
        $panels = [
            [$layout->header, $layout->headerIsPanel(), 'layout.zone.path'],
            [$layout->list, $layout->listIsPanel(), $screen->labelKey()],
            [$layout->preview, $layout->previewIsPanel(), 'layout.zone.preview'],
            [$layout->status, $layout->statusIsPanel(), ''],
        ];

        foreach ($panels as [$zone, $isPanel, $labelKey]) {
            if (!$isPanel) {
                continue;
            }

            $label = $labelKey === '' ? '' : $this->translator->translate($labelKey);

            foreach ((new Panel($label))->draw($zone) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return new Plane('chrome', new Rect(0, 0, $layout->status->bottom() + 1, $layout->header->columns), $primitives);
    }

    /** Treść: ścieżka, środek oddany ekranowi, pas podglądu i pasek stanu. */
    private function content(HudLayout $layout, ScreenInterface $screen, LoopState $state): Plane
    {
        $primitives = [];
        $header = HudLayout::contentOf($layout->header, $layout->headerIsPanel());

        if (!$header->isEmpty()) {
            $suffix = $screen->headerSuffix();
            $path = $state->directory()->path()->shortenedTo(max(1, $header->columns - mb_strlen($suffix)));

            foreach ((new Label($path . $suffix))->draw($header) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        // Ekran modułu dostaje kontekst sesji **przed** rysowaniem i co klatkę:
        // kontekst jest niezmienny i płytki, więc podanie go kosztuje samo
        // wywołanie, a rdzeń nie musi wiedzieć, że coś się zmieniło. To ta sama
        // ścieżka, którą od kroku 19 chodzi zegar dla karetki (`NeedsTime`).
        if ($screen instanceof ReadsContext) {
            $screen->useContext($state->context());
        }

        foreach ($screen->draw(HudLayout::contentOf($layout->list, $layout->listIsPanel())) as $primitive) {
            $primitives[] = $primitive;
        }

        foreach ($this->previewOf($layout, $screen, $state) as $primitive) {
            $primitives[] = $primitive;
        }

        $status = HudLayout::contentOf($layout->status, $layout->statusIsPanel());
        $message = $state->message();

        foreach ((new StatusBar(
            $message === null ? '' : $message->marked(),
            $message === null ? Role::Info : self::roleOf($message->tone),
            $this->hints(),
        ))->draw($status) as $primitive) {
            $primitives[] = $primitive;
        }

        return new Plane('content', new Rect(0, 0, $layout->status->bottom() + 1, $layout->header->columns), $primitives);
    }

    /** @return list<Primitive> */
    private function previewOf(HudLayout $layout, ScreenInterface $screen, LoopState $state): array
    {
        if (!$screen->usesPreview() || $layout->preview->isEmpty()) {
            return [];
        }

        $preview = $this->preview->execute($state->directory());

        if ($preview === null) {
            return [];
        }

        return (new ImageBox($preview->path, $preview->caption))
            ->draw(HudLayout::contentOf($layout->preview, $layout->previewIsPanel()));
    }

    /**
     * Okno nakładane samo mówi, ile miejsca chce i gdzie: opis pliku staje
     * pośrodku, a wiersz komend przy dolnej krawędzi. Gdyby regułę umieszczania
     * trzymało składanie klatki, musiałoby znać wszystkie okna z osobna.
     */
    private function overlay(OverlayInterface $overlay, int $rows, int $columns, float $now): Plane
    {
        if ($overlay instanceof NeedsTime) {
            $overlay->useTime($now);
        }

        $bounds = $overlay->bounds($rows, $columns);

        // Okno nakładane **zakrywa** to, co pod nim: bez tego miniatura z pasa
        // podglądu przebijałaby się przez obwódkę okna komend, bo `Panel` rysuje
        // samą obwódkę, bez tła.
        return new Plane($overlay->id(), $bounds, $overlay->draw($bounds), opaque: true);
    }

    /**
     * Podpowiedzi w pasku stanu powstają z **wiązań rdzenia**, a nie z napisu
     * w katalogu: napis potrafił skłamać po zmianie wiązania, wiązania nie mają
     * jak. Klawisze aktywnego ekranu do stopki nie wchodzą i to jest ta sama
     * decyzja, którą podjął krok 14 — stopka nie jest ściągawką, tylko
     * wskazaniem, gdzie ściągawka leży. Pełny spis stoi pod `F1`.
     */
    private function hints(): string
    {
        $parts = [];

        foreach ($this->global as $binding) {
            $parts[] = $binding->display() . ' ' . $this->translator->translate($binding->descriptionKey);
        }

        return implode(' · ', $parts);
    }

    private static function roleOf(MessageTone $tone): Role
    {
        return match ($tone) {
            MessageTone::Info => Role::Info,
            MessageTone::Warning => Role::Warning,
            MessageTone::Error => Role::Danger,
        };
    }
}
