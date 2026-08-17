<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\ActionOutcome;
use LightManager\Module\Docker\Application\ComposeAction;
use LightManager\Module\Docker\Application\ComposeStage;
use LightManager\Module\Docker\Application\ContainerList;
use LightManager\Module\Docker\Application\DockerAction;
use LightManager\Module\Docker\Application\DockerEvent;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\ImageList;
use LightManager\Module\Docker\Application\LogStream;
use LightManager\Module\Docker\Application\Port\ComposePort;
use LightManager\Module\Docker\Domain\ValueObject\Container;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\DragsOwnContent;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\SplitState;

/**
 * Ekran modułu Dockera — **jeden ekran w trzech postaciach** (krok 51).
 *
 * Rozstrzygnięcie odziedziczone po kroku 49 wraz z powodem: `ScreenStack` liczy
 * ekrany po tożsamości, a użytkownik widzi jedno miejsce, w którym zmienia się
 * treść. Kontenery i obrazy dzielą przy tym **układ**, a nie kod: obie postacie
 * to `Split` z listą po lewej i opisem po prawej, bo opis kontenera i opis
 * obrazu odpowiadają na to samo pytanie — „co to właściwie jest”.
 *
 * **Podział należy do modułu** (reguła 11c): rdzeń daje `Split` i `DrawsOwnFrame`,
 * a moduł rozstrzyga, czy i kiedy z nich skorzystać. Poniżej progu szerokości
 * podziału nie ma i widać samą listę — opis wybranego jest wtedy tym, co ustępuje,
 * bo lista bez opisu nadal mówi wszystko, co najważniejsze, a opis bez listy nie
 * mówi nic.
 *
 * **Logi zajmują ekran w całości** i nie jest to niekonsekwencja: log obok listy
 * miałby czterdzieści kolumn, czyli mniej niż wynosi typowy wiersz wypisu
 * serwera, i zawijałby każdy z nich dwa razy.
 */
final class DockerScreen implements
    ScreenInterface,
    DeclaresFocus,
    DrawsOwnFrame,
    Resettable,
    ReadsContext,
    AcceptsPointer,
    DragsOwnContent
{
    /** Prostokąt z ostatniego rysowania — pamięć wymagana przez `AcceptsPointer` (krok 55). */
    private ?Rect $lastBounds = null;

    /**
     * Proporcja podziału listy i opisu (krok 55).
     *
     * Ogniska ten stan **nie trzyma** — w module Dockera stoi ono zawsze na
     * liście — więc jedyne, po co tu jest, to granica dająca się przeciągnąć
     * i jej zapis w ustawieniach modułu.
     */
    private readonly SplitState $split;

    public const ID = 'docker';

    /** Domyślny podział: lista i opis po połowie (krok 55). */
    private const SPLIT_PERCENT = 50;

    /**
     * Litera odświeżania — ta sama, co w ekranie zdalnym z kroku 50.
     *
     * `Ctrl`+`R` mieszka w **przestrzeni skrótów modułów** (krok 19) i działa
     * dopóty, dopóki litery `r` nie zajmie żaden moduł — pilnuje tego
     * `DockerShortcutsTest`, wzorem `RemoteShortcutsTest`. Wspólna litera
     * w dwóch modułach kolizją nie jest: widoczny ekran jest jeden, a klawisz
     * dochodzi wyłącznie do niego.
     */
    private const REFRESH_KEY = 'r';

    private DockerView $view = DockerView::Containers;

    private readonly ContainerPane $containerPane;

    private readonly ImagePane $imagePane;

    private readonly LogPane $logPane;

    private readonly ScrollWindow $containerWindow;

    private readonly ScrollWindow $imageWindow;

    /** Ile wierszy zmieściło się w ostatniej klatce — jedyna droga do „strona w dół”. */
    private int $lastCapacity = 1;

    /** Katalog, w którym stoi użytkownik przeglądarki — propozycja dla budowy i compose. */
    private string $contextPath = '';

    /**
     * Czas ostatniego taktu — potrzebny komunikatom.
     *
     * `LoopState::report()` pyta o chwilę, bo od niej liczy, kiedy zdanie wolno
     * zgasić; ekran zna ją wyłącznie z taktu, a wyniki czynności odbiera właśnie
     * tam. Zegara **nie bierzemy z `microtime()`** — czas klatki podaje pętla
     * (reguła 11b).
     */
    private float $now = 0.0;

    /**
     * Czy ekran był widoczny w poprzedniej klatce — **jedyny sygnał, jakim
     * dysponuje**, i warunek zegara odświeżania.
     *
     * `ScreenStack` mówi ekranowi, że go otwarto (`reset()`), ale **nie mówi
     * nikomu, że go zasłonięto** — a takt modułu chodzi niezależnie od tego, na
     * co użytkownik patrzy. Bez tego znacznika listy odświeżałyby się co pięć
     * sekund przez cały czas działania aplikacji, także wtedy, gdy modułu nikt
     * nie ogląda (D90 nr 7). Ta sama sztuczka, co w `SshScreen` z kroku 49.
     *
     * Zapis w `draw()` **nie jest zmianą stanu aplikacji**: notuje, że ekran był
     * na ekranie, i nic poza tym.
     */
    private bool $drawn = false;

    public function __construct(
        private readonly ContainerList $containers,
        private readonly ImageList $images,
        private readonly LogStream $logs,
        private readonly ComposePort $compose,
        private readonly BuildFlow $builds,
        private readonly TranslatorPort $translator,
        private readonly LoopState $state,
        private readonly DockerQueries $reader,
        /** Odczyt ustawień rdzenia — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly CoreReader $core,
        /** Proporcja podziału z ustawień modułu wraz z jej zapisem (krok 55). */
        ?SplitState $split = null,
    ) {
        $this->split = $split ?? new SplitState();
        $this->containerWindow = new ScrollWindow();
        $this->containerWindow->useContext(self::ID . ':containers');
        $this->imageWindow = new ScrollWindow();
        $this->imageWindow->useContext(self::ID . ':images');

        $this->containerPane = new ContainerPane($reader, $translator, $this->containerWindow);
        $this->imagePane = new ImagePane($reader, $translator, $this->imageWindow);
        $this->logPane = new LogPane($logs, $translator);
    }

    public function id(): string
    {
        return self::ID;
    }

    public function labelKey(): string
    {
        return $this->view->labelKey();
    }

    /**
     * Kontekst przychodzi **czytany, nie publikowany**.
     *
     * Moduł Dockera nie jest miejscem w drzewie plików i nie ma czego o sobie
     * powiedzieć odbiorcy czekającemu na ścieżkę — publikacja własnego kontekstu
     * kazałaby modułowi opisu pliku pokazywać wpis, którego nikt nie wybierał.
     * Czyta za to katalog przeglądarki, bo `Dockerfile` i `compose.yaml` leżą
     * zwykle właśnie tam (D90 nr 5).
     */
    public function useContext(ModuleContext $context): void
    {
        if (!$context->isRemote()) {
            $this->contextPath = $context->path;
        }
    }

    /** Górny pas: gdzie jestem i co się właśnie dzieje. */
    public function header(): ScreenZone
    {
        return new ScreenZone($this->labelKey(), new Label($this->headerText()));
    }

    /**
     * Oprawa przy podziale — dwie obwódki zamiast jednej (reguła 11c).
     *
     * Prymitywy **wracają do rdzenia**, a nie są rysowane tutaj: rdzeń kładzie je
     * na płaszczyźnie pamiętanej między klatkami, więc obwódka z wygładzanym
     * obrysem nie kosztuje ani jednej klatki ponad pierwszą.
     */
    public function ownFrame(Rect $zone): array
    {
        if (!$this->splitsIn($zone)) {
            return [];
        }

        $primitives = [];
        $labels = [$this->labelKey(), 'module.' . DockerSettings::ID . '.detail.title'];

        foreach (Split::halves($zone, SplitAxis::Vertical, $this->split->fraction()) as $index => $bounds) {
            // Panel z ogniskiem poznaje się po akcencie — tu ognisko stoi zawsze
            // na liście, bo opis jest widokiem, a nie miejscem, w którym da się
            // cokolwiek zrobić.
            $panel = new Panel(
                $this->translator->translate($labels[$index]),
                Role::Border,
                $index === 0 ? Role::Accent : Role::Border,
                $index === 0 ? Role::Accent : Role::Muted,
            );

            foreach ($panel->draw($bounds) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    public function draw(Rect $bounds): array
    {
        $this->drawn = true;
        $this->lastBounds = $bounds;

        if ($this->view === DockerView::Logs) {
            $this->lastCapacity = max(1, $bounds->rows);

            return $this->logPane->draw($bounds);
        }

        if (!$this->splitsIn($bounds)) {
            $this->lastCapacity = $this->capacityOf($bounds);

            return $this->drawList($bounds);
        }

        [$left, $right] = Split::halves($bounds, SplitAxis::Vertical, $this->split->fraction());
        $list = Panel::inner($left);
        $this->lastCapacity = $this->capacityOf($list);
        $primitives = $this->drawList($list);

        foreach ($this->drawDetails(Panel::inner($right)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Wiązania widocznej postaci.
     *
     * Spis jest **pełny** (reguła 11p): klawisz działający w tym miejscu musi tu
     * stać, a klawisz stojący tu musi działać. Pilnuje tego przebieg
     * funkcjonalny wspólny dla wszystkich ekranów.
     *
     * @return list<KeyBinding>
     */
    public function bindings(): array
    {
        if ($this->view === DockerView::Logs) {
            return $this->logBindings();
        }

        return $this->view === DockerView::Containers ? $this->containerBindings() : $this->imageBindings();
    }

    public function focus(): FocusHint
    {
        return new FocusHint($this->labelKey(), $this->bindings());
    }

    public function reset(): void
    {
        $this->drawn = true;
        $this->logPane->reset();
    }

    /**
     * Takt: posunięcie obu list, strumienia logów i pracy compose.
     *
     * Kolejność ma znaczenie i **jest taka, a nie odwrotna**: najpierw odbieramy
     * to, co przyszło (bo od tego zależy, co widać), potem zgłaszamy wyniki (bo
     * mówią o tym, co właśnie odebraliśmy). Zegar odświeżania chodzi wyłącznie
     * wtedy, gdy ekran jest na wierzchu (D90 nr 7) — a logi płyną **zawsze**, bo
     * strumień nieczytany zatrzymuje nadawcę.
     */
    public function tick(float $now): void
    {
        $this->now = $now;
        $visible = $this->drawn;

        $this->containers->tick($now, $visible && $this->view !== DockerView::Logs);
        $this->images->tick($now, $visible && $this->view === DockerView::Images);
        $this->logs->tick();
        $this->compose->advance();

        $this->reportAction($this->containers->takeOutcome());
        $this->reportAction($this->images->takeOutcome());
        $this->reportCompose();

        $this->drawn = false;
    }

    /**
     * Przeciągnięcie granicy podziału należy do ekranu, a nie do zaznaczania
     * treści (krok 56) — rdzeń pyta o to raz, w `InputHandler`.
     */
    public function isDraggingOwn(): bool
    {
        return $this->split->isDragging();
    }

    /**
     * Wskaźnik w module Dockera: granica podziału, kursor listy i kółko
     * (krok 55).
     *
     * Ognisko stoi tu **zawsze na liście** — opis po prawej jest widokiem, a nie
     * miejscem, w którym da się cokolwiek zrobić (tak samo mówi `ownFrame()`) —
     * więc kliknięcie w prawy panel nie przenosi niczego. Postać logów jest
     * jednym panelem na całą szerokość i przewija się kółkiem.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        if ($this->view === DockerView::Logs) {
            if ($event->isScroll()) {
                $this->logPane->scrollBy($event->scrollRows());
            }

            return ScreenOutcome::stay();
        }

        $split = $this->splitsIn($bounds);

        if ($split && $this->split->pointer($event, $bounds, SplitAxis::Vertical)) {
            return ScreenOutcome::stay();
        }

        $list = $split ? Panel::inner(Split::halves($bounds, SplitAxis::Vertical, $this->split->fraction())[0]) : $bounds;
        $containers = $this->view === DockerView::Containers;
        $window = $containers ? $this->containerWindow : $this->imageWindow;

        if ($event->isScroll()) {
            $window->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $state = $containers ? $this->reader->containers() : $this->reader->images();
        $row = PointerRow::of($event, $list, $window->offset(), true, count($state->entries));

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $containers ? $this->containers->moveTo($row) : $this->images->moveTo($row);

        return ScreenOutcome::stay();
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Character && $key->raw === self::REFRESH_KEY && $key->ctrl) {
            $this->containers->refresh();
            $this->images->refresh();

            return ScreenOutcome::stay();
        }

        return match ($this->view) {
            DockerView::Logs => $this->handleLogs($key),
            DockerView::Images => $this->handleImages($key),
            DockerView::Containers => $this->handleContainers($key),
        };
    }

    /** Postać widoczna w tej chwili — pyta o nią moduł, składając zdanie komendy. */
    public function view(): DockerView
    {
        return $this->view;
    }

    /** Przełącza na wskazaną postać — droga dla komend (`docker.ps`, `docker.logs`). */
    public function show(DockerView $view): void
    {
        $this->view = $view;
    }

    /** Katalog z kontekstu przeglądarki — propozycja dla budowy i dla compose. */
    public function contextPath(): string
    {
        return $this->contextPath;
    }

    private function handleContainers(KeyPress $key): ScreenOutcome
    {
        $container = $this->reader->containers()->selected();

        if ($key->key === Key::Enter && $container !== null) {
            $this->logs->open($container);
            $this->logPane->reset();
            $this->view = DockerView::Logs;

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::F3) {
            $this->view = DockerView::Images;
            $this->images->refresh();

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::F5) {
            return $this->cycleProject();
        }

        if ($key->key === Key::F7) {
            return $this->builds->start($this->contextPath);
        }

        if ($container !== null && $key->key === Key::F4) {
            return $this->toggle($container, $key->shift);
        }

        if ($container !== null && ($key->key === Key::F8 || $key->key === Key::Delete)) {
            return $this->confirmRemoval($container);
        }

        return $this->moveCursor($key, $this->reader->containers()->cursor, count($this->reader->containers()->entries), true);
    }

    private function handleImages(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::F3) {
            $this->view = DockerView::Containers;

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::F7) {
            return $this->builds->start($this->contextPath);
        }

        $image = $this->reader->images()->selected();

        if ($image !== null && ($key->key === Key::F8 || $key->key === Key::Delete)) {
            $label = $image->label();

            return ScreenOutcome::opens(new ConfirmOverlay(
                'module.' . DockerSettings::ID . '.images.confirmRemoval',
                ['image' => $label],
                function () use ($image): OverlayOutcome {
                    $this->images->remove($image);

                    return OverlayOutcome::close();
                },
                $this->translator,
                dangerous: true,
            ));
        }

        return $this->moveCursor($key, $this->reader->images()->cursor, count($this->reader->images()->entries), false);
    }

    private function handleLogs(KeyPress $key): ScreenOutcome
    {
        return match ($key->key) {
            Key::Escape, Key::F3 => $this->closeLogs(),
            Key::ArrowUp => $this->scrollLogs(-1),
            Key::ArrowDown => $this->scrollLogs(1),
            Key::PageUp => $this->pageLogs(-1),
            Key::PageDown => $this->pageLogs(1),
            Key::Home => $this->jumpLogs(toStart: true),
            Key::End => $this->jumpLogs(toStart: false),
            default => ScreenOutcome::stay(),
        };
    }

    private function closeLogs(): ScreenOutcome
    {
        $this->logs->close();
        $this->view = DockerView::Containers;

        return ScreenOutcome::stay();
    }

    private function scrollLogs(int $delta): ScreenOutcome
    {
        $this->logPane->scrollBy($delta);

        return ScreenOutcome::stay();
    }

    private function pageLogs(int $direction): ScreenOutcome
    {
        $this->logPane->pageBy($direction);

        return ScreenOutcome::stay();
    }

    private function jumpLogs(bool $toStart): ScreenOutcome
    {
        $toStart ? $this->logPane->toStart() : $this->logPane->toEnd();

        return ScreenOutcome::stay();
    }

    /**
     * `F4` uruchamia albo zatrzymuje — zależnie od tego, co kontener właśnie
     * robi; `Shift`+`F4` zawsze restartuje.
     *
     * Jeden klawisz na dwie czynności, bo są **wzajemnie wykluczające się**:
     * kontener działający nie da się uruchomić, a zatrzymany — zatrzymać.
     * Drugi klawisz na to samo byłby klawiszem, który w połowie przypadków nie
     * robi nic. `Shift` przy klawiszu nazwanym jest przy tym jedyną dozwoloną
     * postacią tego modyfikatora (reguła 11j) i rozstrzyga się **przed**
     * gałęziami klawiszy, wzorem `BrowserScreen::shifted()`.
     */
    private function toggle(Container $container, bool $restart): ScreenOutcome
    {
        if ($restart) {
            $this->containers->begin(DockerAction::Restart, $container);

            return ScreenOutcome::stay();
        }

        if ($container->state->isStoppable()) {
            $this->containers->begin(DockerAction::Stop, $container);

            return ScreenOutcome::stay();
        }

        if ($container->state->isStartable()) {
            $this->containers->begin(DockerAction::Start, $container);

            return ScreenOutcome::stay();
        }

        return ScreenOutcome::stay(Message::warning(
            $this->text('action.impossible', ['name' => $container->name]),
        ));
    }

    private function confirmRemoval(Container $container): ScreenOutcome
    {
        return ScreenOutcome::opens(new ConfirmOverlay(
            'module.' . DockerSettings::ID . '.containers.confirmRemoval',
            ['name' => $container->name],
            function () use ($container): OverlayOutcome {
                $this->containers->begin(DockerAction::RemoveContainer, $container);

                return OverlayOutcome::close();
            },
            $this->translator,
            dangerous: true,
        ));
    }

    /**
     * `F5` przechodzi po projektach compose i wraca do „wszystkie”.
     *
     * Zawężenie idzie **etykietą, którą lista już ma** — kontener zna swój
     * projekt z `com.docker.compose.project`, więc nie kosztuje ono ani jednego
     * pytania do demona. Gdyby projekt trzeba było czytać `docker compose ps`,
     * ten klawisz uruchamiałby proces potomny przy każdym naciśnięciu.
     */
    private function cycleProject(): ScreenOutcome
    {
        $projects = $this->reader->containers()->projects;

        if ($projects === []) {
            return ScreenOutcome::stay(Message::info($this->text('compose.noProjects')));
        }

        $current = $this->reader->containers()->project;
        $position = $current === null ? -1 : (int) array_search($current, $projects, true);
        $next = $projects[$position + 1] ?? null;

        $this->containers->narrowTo($next);
        $this->containerWindow->useContext(self::ID . ':containers:' . ($next ?? ''));

        return ScreenOutcome::stay(Message::info(
            $next === null
                ? $this->text('compose.allProjects')
                : $this->text('compose.narrowed', ['project' => $next]),
        ));
    }

    private function moveCursor(KeyPress $key, int $cursor, int $count, bool $containers): ScreenOutcome
    {
        $page = max(1, $this->lastCapacity - 1);

        $target = match ($key->key) {
            Key::ArrowUp => $cursor - 1,
            Key::ArrowDown => $cursor + 1,
            Key::PageUp => $cursor - $page,
            Key::PageDown => $cursor + $page,
            Key::Home => 0,
            Key::End => max(0, $count - 1),
            default => null,
        };

        if ($target === null) {
            return ScreenOutcome::stay();
        }

        $containers ? $this->containers->moveTo($target) : $this->images->moveTo($target);

        return ScreenOutcome::stay();
    }

    /** @return list<Primitive> */
    private function drawList(Rect $bounds): array
    {
        return $this->view === DockerView::Containers
            ? $this->containerPane->draw($bounds)
            : $this->imagePane->draw($bounds);
    }

    /** @return list<Primitive> */
    private function drawDetails(Rect $bounds): array
    {
        return $this->view === DockerView::Containers
            ? $this->containerPane->drawDetails($bounds)
            : $this->imagePane->drawDetails($bounds);
    }

    private function capacityOf(Rect $bounds): int
    {
        return $this->view === DockerView::Containers
            ? $this->containerPane->capacityOf($bounds)
            : $this->imagePane->capacityOf($bounds);
    }

    private function splitsIn(Rect $zone): bool
    {
        // Proporcja czytana co klatkę: tę samą pozycję zmienia zakładka ustawień,
        // a w trakcie przeciągania `SplitState` podaną wartość pomija (krok 55).
        $this->split->useFraction(
            SplitSetting::fraction($this->core->settings(), DockerSettings::ID, self::SPLIT_PERCENT),
        );

        return $this->view !== DockerView::Logs
            && $zone->rows >= 3
            && Split::fits($zone, SplitAxis::Vertical);
    }

    /**
     * Zdanie górnego pasa — **co się właśnie dzieje**, a gdy nic, to co widać.
     *
     * Praca trwająca wypiera opis miejsca, bo jest tym, na co użytkownik czeka:
     * `compose up` trwa minutami i bez tego zdania ekran wyglądałby jak
     * zamrożony.
     */
    private function headerText(): string
    {
        $compose = $this->reader->compose();

        if ($compose->isWorking() && $compose->action !== null) {
            return $this->translator->translate($compose->action->labelKey());
        }

        if ($this->containers->isWorking() || $this->images->isWorking()) {
            return $this->text('action.working');
        }

        if ($this->view === DockerView::Logs) {
            return $this->text('logs.header', ['name' => $this->logs->containerName()]);
        }

        $project = $this->reader->containers()->project;

        if ($this->view === DockerView::Containers && $project !== null) {
            return $this->text('compose.narrowed', ['project' => $project]);
        }

        return $this->text($this->view === DockerView::Containers ? 'containers.header' : 'images.header');
    }

    /**
     * Zamienia wynik czynności na zdanie i zdarzenie.
     *
     * **Zdarzenie ogłasza `Presentation`, a nie warstwa aplikacji**, i to jest
     * granica z kroku 46: rejestr zdarzeń mieszka w stanie pętli, którego stan
     * listy nie zna i znać nie ma prawa.
     */
    private function reportAction(?ActionOutcome $outcome): void
    {
        if ($outcome === null) {
            return;
        }

        if (!$outcome->successful) {
            $this->state->report(Message::error($this->translator->translate(
                $outcome->problemKey ?? 'module.' . DockerSettings::ID . '.action.rejected',
                $outcome->problemParameters,
            )), $this->now);
            $this->state->events()->publish(DockerEvent::ActionFailed->value);

            return;
        }

        $this->state->report(Message::info($this->translator->translate(
            'module.' . DockerSettings::ID . '.action.done.' . $outcome->action->value,
            ['name' => $outcome->subject],
        )), $this->now);
        $this->state->events()->publish(
            $outcome->action->isDestructive() ? DockerEvent::Removed->value : DockerEvent::ContainerChanged->value,
        );
    }

    /** Zdanie i zdarzenie po pracy compose — wynik zabierany raz, jak przy czynności. */
    private function reportCompose(): void
    {
        $state = $this->reader->compose();

        if ($state->stage === ComposeStage::Idle || $state->isWorking() || $state->action === null) {
            return;
        }

        $action = $state->action;
        $this->compose->stop();

        if ($state->stage === ComposeStage::Failed) {
            $this->state->report(Message::error($this->translator->translate(
                $state->problemKey ?? 'module.' . DockerSettings::ID . '.compose.failed',
                $state->problemParameters,
            )), $this->now);
            $this->state->events()->publish(DockerEvent::ActionFailed->value);

            return;
        }

        if ($action === ComposeAction::ListProjects) {
            return;
        }

        $this->state->report(Message::info($this->text('compose.done.' . $action->value)), $this->now);
        $this->state->events()->publish(DockerEvent::ComposeChanged->value);
        $this->containers->refresh();
    }

    /** @return list<KeyBinding> */
    private function containerBindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::PageUp, Key::PageDown, Key::Home, Key::End], 'help.key.page', 'help.key.page.short'),
            KeyBinding::of([Key::Enter], $this->key('key.logs'), $this->key('key.logs.short')),
            KeyBinding::of([Key::F3], $this->key('key.images'), $this->key('key.images.short')),
            KeyBinding::of([Key::F4], $this->key('key.toggle'), $this->key('key.toggle.short')),
            KeyBinding::shifted([Key::F4], $this->key('key.restart'), $this->key('key.restart.short')),
            KeyBinding::of([Key::F5], $this->key('key.project'), $this->key('key.project.short')),
            KeyBinding::of([Key::F7], $this->key('key.build'), $this->key('key.build.short')),
            KeyBinding::of([Key::F8, Key::Delete], $this->key('key.remove'), $this->key('key.remove.short')),
            KeyBinding::ctrl(self::REFRESH_KEY, $this->key('key.refresh'), $this->key('key.refresh.short')),
        ];
    }

    /** @return list<KeyBinding> */
    private function imageBindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::PageUp, Key::PageDown, Key::Home, Key::End], 'help.key.page', 'help.key.page.short'),
            KeyBinding::of([Key::F3], $this->key('key.containers'), $this->key('key.containers.short')),
            KeyBinding::of([Key::F7], $this->key('key.build'), $this->key('key.build.short')),
            KeyBinding::of([Key::F8, Key::Delete], $this->key('key.removeImage'), $this->key('key.remove.short')),
            KeyBinding::ctrl(self::REFRESH_KEY, $this->key('key.refresh'), $this->key('key.refresh.short')),
        ];
    }

    /** @return list<KeyBinding> */
    private function logBindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.scroll'),
            KeyBinding::of([Key::PageUp, Key::PageDown, Key::Home], 'help.key.page', 'help.key.page.short'),
            KeyBinding::of([Key::End], $this->key('key.follow'), $this->key('key.follow.short')),
            KeyBinding::of([Key::Escape, Key::F3], $this->key('key.back'), $this->key('key.back.short')),
        ];
    }

    private function key(string $suffix): string
    {
        return 'module.' . DockerSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $key, $parameters);
    }
}
