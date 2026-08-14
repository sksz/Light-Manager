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
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\StatusHints;

/**
 * Składa klatkę z aktywnego ekranu i oddaje ją rendererowi.
 *
 * Następca trzech przypadków użycia `Render*FrameUseCase`, z których każdy
 * budował całą klatkę od zera i miał własny rachunek przewijania. Tutaj zostaje
 * to, co **wspólne**: podział okna na strefy, oprawa paneli i pasek stanu.
 * Treść wszystkich trzech stref rysuje ekran i tylko on.
 *
 * Krok 21 zabrał stąd ścieżkę i pas podglądu — nie dlatego, że lepiej im
 * w ekranie, tylko dlatego, że rdzeń przestał mieć z czego je narysować: katalog
 * zszedł do modułu przeglądarki, a wraz z nim jedyne źródło obu tych treści.
 * Zostało to, co naprawdę należy do powłoki: oprawa stref wraz z etykietami
 * i pasek stanu z komunikatem oraz podpowiedziami klawiszy globalnych.
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

    /**
     * @param list<KeyBinding> $global wiązania działające wszędzie: klawisze
     *                                 rdzenia wraz ze skrótami modułów. Skróty
     *                                 dokłada `Bootstrap`, bo powstają dopiero
     *                                 z rejestru modułów — do kroku 40 stopka
     *                                 nigdy o nich nie mówiła
     */
    public function __construct(
        private readonly FrameRendererPort $renderer,
        private readonly ViewportPort $viewport,
        private readonly TranslatorPort $translator,
        private readonly array $global = [],
    ) {
    }

    /**
     * Strefy pytamy **raz na klatkę**, przed podziałem okna: ich istnienie
     * rozstrzyga o wysokościach, a treść trafia potem na płaszczyznę treści.
     * Pytanie ekranu po raz drugi kazałoby mu złożyć te same komponenty jeszcze
     * raz — i pozwoliłoby obu odpowiedziom się rozjechać.
     */
    public function render(ScreenInterface $screen, LoopState $state): void
    {
        // Ekran modułu dostaje kontekst sesji **przed** wszystkim innym: strefy
        // składa już z niego. Kontekst jest niezmienny i płytki, więc podanie go
        // co klatkę kosztuje samo wywołanie, a rdzeń nie musi wiedzieć, że coś
        // się zmieniło. To ta sama ścieżka, którą od kroku 19 chodzi zegar dla
        // karetki (`NeedsTime`).
        if ($screen instanceof ReadsContext) {
            $screen->useContext($state->context());
        }

        // Zegar tą samą drogą i z tego samego powodu. Do kroku 23 dostawały go
        // wyłącznie okna nakładane, bo tylko tam było coś, co zmienia się samo
        // z siebie — karetka. Pasek postępu w trybie „nie wiadomo ile jeszcze”
        // jest drugą taką rzeczą i stoi na ekranie, nie nad nim. Kontrakt
        // `ScreenInterface` zostaje nietknięty: to osobny interfejs, jak
        // `Resettable`, więc ekran bez ruchomej treści niczego nie deklaruje.
        if ($screen instanceof NeedsTime) {
            $screen->useTime($state->now());
        }

        $header = $screen->header();

        $rows = $this->viewport->rows();
        $columns = max(self::MINIMUM_COLUMNS, $this->viewport->columns());

        // Okno nakładane pytamy **przed** podziałem okna, bo od kroku 40 wypiera
        // ekran ze stopki, a stopka rozstrzyga o wysokości swojej strefy.
        $overlay = $state->overlays()->current();
        $message = $state->message();
        $hints = $this->hints($overlay ?? $screen);

        $layout = new HudLayout(
            $rows,
            $columns,
            $header !== null,
            // Pasek rośnie **z potrzeby**, nie z samej wysokości okna
            // (rozstrzygnięcie nr 6 kroku 40): pytamy o miejsce w wierszu, który
            // dzieli się z komunikatem, bo to on jest wąskim gardłem.
            !$hints->fitInOneRow(StatusBar::hintColumns(
                HudLayout::contentColumns($columns),
                $message === null ? '' : $message->marked(),
            )),
        );

        // Ekran podzielony na dwa panele oprawia się sam: rdzeń wie o **jednej**
        // strefie środkowej i nie wie, który panel jest czynny, więc nie ma czym
        // pokazać ogniska. Pytamy raz na klatkę, bo odpowiedź rozstrzyga i o
        // oprawie, i o prostokącie treści — a te dwie rzeczy nie mają prawa się
        // rozjechać. Pusta lista znaczy „oprawiaj jak zawsze”.
        $ownFrame = $screen instanceof DrawsOwnFrame ? $screen->ownFrame($layout->list) : [];

        $planes = [
            $this->chrome($layout, $screen, $header, $ownFrame),
            $this->content($layout, $screen, $state, $header, $ownFrame !== [], $hints),
        ];

        if ($overlay !== null) {
            $planes[] = $this->overlay($overlay, $rows, $columns, $state->now());
        }

        $this->renderer->render(new Frame($planes));
    }

    /**
     * Oprawa: obwódki stref wraz z etykietami i nawiasami narożnymi.
     *
     * @param list<Primitive> $ownFrame oprawa narysowana przez sam ekran; niepusta
     *                                  odbiera rdzeniowi strefę środkową
     */
    private function chrome(
        HudLayout $layout,
        ScreenInterface $screen,
        ?ScreenZone $header,
        array $ownFrame,
    ): Plane {
        $primitives = $ownFrame;
        $panels = [
            [$layout->header, $layout->headerIsPanel(), $header->labelKey ?? ''],
            [$layout->list, $layout->listIsPanel() && $ownFrame === [], $screen->labelKey()],
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

    /** Treść: dwie strefy oddane ekranowi i pasek stanu, który zostaje rdzeniowi. */
    private function content(
        HudLayout $layout,
        ScreenInterface $screen,
        LoopState $state,
        ?ScreenZone $header,
        bool $ownFrame,
        StatusHints $hints,
    ): Plane {
        $primitives = [];

        foreach ($this->zoneOf($header, $layout->header, $layout->headerIsPanel()) as $primitive) {
            $primitives[] = $primitive;
        }

        // Ekran oprawiający się sam dostaje **cały** prostokąt strefy, wraz
        // z wierszami i kolumnami, które normalnie zjada obwódka: skoro rysuje
        // własne panele, to on musi mieć na nie miejsce.
        $list = $ownFrame ? $layout->list : HudLayout::contentOf($layout->list, $layout->listIsPanel());

        foreach ($screen->draw($list) as $primitive) {
            $primitives[] = $primitive;
        }

        $status = HudLayout::contentOf($layout->status, $layout->statusIsPanel());
        $message = $state->message();

        foreach ((new StatusBar(
            $message === null ? '' : $message->marked(),
            $message === null ? Role::Info : self::roleOf($message->tone),
            $hints,
        ))->draw($status) as $primitive) {
            $primitives[] = $primitive;
        }

        return new Plane('content', new Rect(0, 0, $layout->status->bottom() + 1, $layout->header->columns), $primitives);
    }

    /**
     * Treść strefy skrajnej. Strefa niezamówiona przez ekran nie dostaje wierszy
     * już od `HudLayout`, więc drugie sprawdzenie dotyczy wyłącznie okna zbyt
     * niskiego, by strefa się w nim zmieściła.
     *
     * @return list<Primitive>
     */
    private function zoneOf(?ScreenZone $zone, Rect $bounds, bool $isPanel): array
    {
        if ($zone === null || $bounds->isEmpty()) {
            return [];
        }

        return $zone->content->draw(HudLayout::contentOf($bounds, $isPanel));
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
     * Podpowiedzi w pasku stanu: **co da się zrobić tu i teraz**, od miejsca pod
     * kursorem po klawisze działające wszędzie.
     *
     * Do kroku 40 stała tu jedna pętla po wiązaniach rdzenia wraz ze zdaniem
     * „stopka nie jest ściągawką, tylko wskazaniem, gdzie ściągawka leży”. Zdanie
     * zostało odwołane co do **zasięgu** i tylko co do niego: klawisze ekranu
     * i miejsca z ogniskiem wchodzą odtąd do stopki, ale nadal pochodzą
     * z `KeyBinding`, a nie z napisu w katalogu — napis potrafił skłamać po
     * zmianie wiązania, wiązanie nie ma jak. Okno pomocy zostaje **pełnym**
     * spisem i dlatego `F1` nie znika ze stopki nawet wtedy, gdy ustępują jej
     * wszystkie pozostałe pozycje.
     *
     * Ognisko jest **zadeklarowane, nie odkryte**, i to jest jedyna droga, jaką
     * może być: komponent powstaje w `draw()` i ginie razem z klatką, więc drzewa,
     * po którym dałoby się chodzić w poszukiwaniu kursora, nie ma w żadnym
     * momencie poza tą jedną chwilą, gdy klatka się składa.
     */
    private function hints(ScreenInterface|OverlayInterface $top): StatusHints
    {
        return StatusHints::compose(
            $top instanceof DeclaresFocus ? $top->focus() : null,
            $top->bindings(),
            $this->global,
            $this->translator,
        );
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
