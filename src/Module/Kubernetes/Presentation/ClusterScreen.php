<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

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
use LightManager\Module\Kubernetes\Application\ActionOutcome;
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\ClusterActions;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\ClusterStage;
use LightManager\Module\Kubernetes\Application\ClusterState;
use LightManager\Module\Kubernetes\Application\KubernetesEvent;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\LogStream;
use LightManager\Module\Kubernetes\Application\ResourceCache;
use LightManager\Module\Kubernetes\Application\ResourceDetail;
use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\Component\TreeView;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\DragsOwnContent;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SectionState;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\SplitState;
use LightManager\Presentation\Ui\TreeState;

/**
 * Ekran klastra: drzewo rodzajów po lewej, treść po prawej (krok 52).
 *
 * Układ pochodzi wprost z rozstrzygnięcia użytkownika (D91 nr 3) i jest **jedyną
 * rzeczą w tym module, której plan kroku nie przewidywał w ogóle** — bo plan nie
 * przewidywał wszystkich rodzajów zasobów naraz. Lewy panel prowadzi **grupy API
 * → rodzaje → zasoby**, prawy pokazuje to, na czym stoi kursor: dla rodzaju —
 * jego listę, dla zasobu — jego opis.
 *
 * **Stan „nie ma klastra” rysuje się pierwszy** i to jest wymaganie planu, nie
 * ozdoba: maszyna projektu nie ma bieżącego kontekstu, więc *to* jest widok,
 * który zobaczy większość ludzi uruchamiających moduł po raz pierwszy. Ekran
 * mówi wtedy, co wybrać, i podaje klawisz — zamiast pokazywać puste drzewo
 * i „connection refused” w pasku stanu.
 *
 * **Ognisko deklaruje się, a nie odkrywa** (reguła 11p): ekran ma dwa miejsca
 * (drzewo i treść), więc `focus()` mówi, w którym stoi kursor, a `bindings()`
 * jest **pełnym** spisem klawiszy działających w tym miejscu — pilnuje tego
 * przebieg funkcjonalny wspólny dla wszystkich ekranów.
 */
final class ClusterScreen implements
    ScreenInterface,
    DeclaresFocus,
    DrawsOwnFrame,
    Resettable,
    ReadsContext,
    AcceptsPointer,
    DragsOwnContent
{
    public const ID = 'k8s';

    /**
     * Domyślny podział: drzewo węższe od listy (krok 55).
     *
     * Czterdzieści procent to liczba, którą krok 52 wpisał wprost w wywołania
     * `Split::halves()` — tutaj staje się wartością domyślną pozycji ustawień,
     * bo granicę da się odtąd przeciągnąć.
     */
    private const SPLIT_PERCENT = 40;

    /** Prostokąt z ostatniego rysowania — pamięć wymagana przez `AcceptsPointer` (krok 55). */
    private ?Rect $lastBounds = null;

    /** Okno przewijania drzewa — pole od kroku 55, patrz `drawTree()`. */
    private readonly ScrollWindow $treeWindow;



    /** Odświeżenie — ta sama litera, co w modułach Dockera i sesji zdalnej. */
    private const REFRESH_KEY = 'r';

    private ClusterView $view = ClusterView::Resources;

    private readonly ClusterTree $tree;

    private readonly ResourcePane $resources;

    private readonly DetailPane $details;

    private readonly LogPane $logPane;

    private readonly SecretFlow $secrets;

    private readonly TreeState $treeState;

    private readonly SectionState $sectionState;

    /**
     * Podział drzewa i treści.
     *
     * Ogniska ten stan **nie rozstrzyga** — robi to `$listCursor` — więc do
     * kroku 55 niósł wyłącznie `moveFocus()` wołane dla porządku. Od kroku 55
     * niesie proporcję granicy, jej przeciąganie i zapis w ustawieniach modułu.
     */
    private readonly SplitState $splitState;

    private readonly ScrollWindow $listWindow;

    /** Numer wiersza pod kursorem w prawym panelu — `null`, gdy ognisko jest w drzewie. */
    private ?int $listCursor = null;

    private int $lastTreeCapacity = 1;

    private int $lastListCapacity = 1;

    private float $now = 0.0;

    private bool $drawn = false;

    /** Katalog przeglądarki — propozycja ścieżki dla `apply`. */
    private string $contextPath = '';

    public function __construct(
        private readonly ClusterState $cluster,
        private readonly ApiCatalog $catalog,
        private readonly ResourceCache $cache,
        private readonly ResourceDetail $detail,
        private readonly LogStream $logs,
        private readonly ClusterActions $actions,
        private readonly ClusterSession $session,
        private readonly TranslatorPort $translator,
        private readonly LoopState $state,
        private readonly KubernetesQueries $reader,
        /** Odczyt ustawień rdzenia — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly CoreReader $core,
        /** Proporcja podziału z ustawień modułu wraz z jej zapisem (krok 55). */
        ?SplitState $split = null,
    ) {
        $this->treeState = new TreeState();
        $this->treeState->useContext(self::ID);
        $this->sectionState = new SectionState();
        $this->sectionState->useContext(self::ID);
        $this->splitState = $split ?? new SplitState();
        $this->listWindow = new ScrollWindow();
        $this->listWindow->useContext(self::ID . ':list');
        $this->treeWindow = new ScrollWindow();
        $this->treeWindow->useContext(self::ID . ':tree');

        $this->tree = new ClusterTree($cache, $this->treeState, $translator, $reader);
        $this->resources = new ResourcePane($translator, $this->listWindow, $reader);
        $this->details = new DetailPane($detail, $translator, $this->sectionState, new ScrollWindow());
        $this->logPane = new LogPane($logs, $translator);
        $this->secrets = new SecretFlow($detail, $actions, $translator);
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
     * Kontekst przychodzi **czytany, nie publikowany** — jak w module Dockera.
     *
     * Klaster nie jest miejscem w drzewie plików, więc nie ma czego o sobie
     * powiedzieć; czyta za to katalog przeglądarki, bo manifest do `apply` leży
     * zwykle właśnie tam.
     */
    public function useContext(ModuleContext $context): void
    {
        if (!$context->isRemote()) {
            $this->contextPath = $context->path;
        }
    }

    public function header(): ScreenZone
    {
        return new ScreenZone($this->labelKey(), new Label($this->headerText()));
    }

    /** @return list<Primitive> */
    public function ownFrame(Rect $zone): array
    {
        if (!$this->splitsIn($zone)) {
            return [];
        }

        $primitives = [];
        $focusedRight = $this->listCursor !== null;
        $labels = [$this->key('panel.tree'), $this->key('panel.content')];

        foreach (Split::halves($zone, SplitAxis::Vertical, $this->splitState->fraction()) as $index => $bounds) {
            $focused = ($index === 1) === $focusedRight;
            $panel = new Panel(
                $this->translator->translate($labels[$index]),
                Role::Border,
                $focused ? Role::Accent : Role::Border,
                $focused ? Role::Accent : Role::Muted,
            );

            foreach ($panel->draw($bounds) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $this->drawn = true;
        $this->lastBounds = $bounds;

        if ($this->view === ClusterView::Logs) {
            $this->lastListCapacity = max(1, $bounds->rows);

            return $this->logPane->draw($bounds);
        }

        if (!$this->reader->cluster()->stage->allowsQueries()) {
            return $this->drawStage($bounds);
        }

        if (!$this->splitsIn($bounds)) {
            $this->lastTreeCapacity = max(1, $bounds->rows);

            return $this->drawTree($bounds);
        }

        [$left, $right] = Split::halves($bounds, SplitAxis::Vertical, $this->splitState->fraction());
        $treeBounds = Panel::inner($left);
        $contentBounds = Panel::inner($right);
        $this->lastTreeCapacity = max(1, $treeBounds->rows);
        $this->lastListCapacity = max(1, $contentBounds->rows);

        $primitives = $this->drawTree($treeBounds);

        foreach ($this->drawContent($contentBounds) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /** @return list<KeyBinding> */
    public function bindings(): array
    {
        if ($this->view === ClusterView::Logs) {
            return $this->logBindings();
        }

        if (!$this->reader->cluster()->stage->allowsQueries()) {
            return $this->stageBindings();
        }

        return $this->listCursor === null ? $this->treeBindings() : $this->contentBindings();
    }

    public function focus(): FocusHint
    {
        if ($this->view === ClusterView::Logs) {
            return new FocusHint($this->key('panel.logs'), $this->bindings());
        }

        return new FocusHint(
            $this->listCursor === null ? $this->key('panel.tree') : $this->key('panel.content'),
            $this->bindings(),
        );
    }

    public function reset(): void
    {
        $this->drawn = true;

        // Pierwsze wejście na ekran zamawia konteksty; kolejne nie kosztują nic,
        // bo `ClusterState` pyta raz i pamięta.
        if ($this->reader->cluster()->stage === ClusterStage::Unknown) {
            $this->cluster->begin();
        }
    }

    /**
     * Takt: posunięcie wszystkiego, co trwa poza klatką.
     *
     * Kolejność ma znaczenie i jest ta sama, co w module Dockera: **najpierw
     * odbieramy to, co przyszło**, potem zgłaszamy wyniki. Logi płyną
     * **zawsze**, także gdy ich nie widać — strumień nieczytany zatrzymuje
     * nadawcę. Zegar odświeżania chodzi wyłącznie przy widocznym ekranie
     * (D91 nr 7).
     */
    public function tick(float $now, int $refreshSeconds): void
    {
        $this->now = $now;
        $visible = $this->drawn;

        $this->cluster->advance();
        $this->catalog->advance();
        $this->cache->advance($now);
        $this->detail->advance();
        $this->logs->advance();
        $this->actions->advance();

        if ($this->reader->cluster()->stage->allowsQueries()) {
            $this->catalog->begin();
        }

        if ($visible && $this->view !== ClusterView::Logs) {
            $this->cache->refreshDue($this->tree->focusedKind(), $now, $refreshSeconds);
        }

        $this->reportAction($this->actions->takeOutcome());
        $this->drawn = false;
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Character && $key->raw === self::REFRESH_KEY && $key->ctrl) {
            return $this->refresh();
        }

        if ($this->view === ClusterView::Logs) {
            return $this->handleLogs($key);
        }

        if (!$this->reader->cluster()->stage->allowsQueries()) {
            return $this->handleStage($key);
        }

        if ($key->key === Key::Tab) {
            return $this->switchFocus();
        }

        return $this->listCursor === null ? $this->handleTree($key) : $this->handleContent($key);
    }

    /** Postać widoczna w tej chwili — pyta o nią moduł, składając zdanie komendy. */
    public function view(): ClusterView
    {
        return $this->view;
    }

    /** Droga dla komend modułu: pokaż listę rodzaju, którego nazwę podano. */
    public function show(ResourceKind $kind): void
    {
        $this->view = ClusterView::Resources;
        $group = $kind->groupLabel();
        $this->treeState->expand($group);
        $this->treeState->expand($group . '/' . $kind->address());
        $this->treeState->moveTo($group . '/' . $kind->address());
        $this->cache->load($kind);
        $this->listCursor = null;
    }

    /** Droga dla komendy `k8s.apply` — okno pyta o ścieżkę, a zna ją stąd. */
    public function contextPath(): string
    {
        return $this->contextPath;
    }

    public function openContextChoice(): ChoiceOverlay
    {
        $options = [];

        foreach ($this->reader->contexts()->contexts as $context) {
            $options[$context->value] = $context->value;
        }

        $options["\0cancel"] = $this->key('context.cancel');

        return new ChoiceOverlay(
            $this->key('context.title'),
            [],
            $options,
            function (string $choice): OverlayOutcome {
                if ($choice === "\0cancel") {
                    return OverlayOutcome::close();
                }

                try {
                    $this->cluster->useContext(ContextName::of($choice));
                } catch (InvalidClusterNameException) {
                    return OverlayOutcome::close(Message::error($this->text('context.rejected')));
                }

                $this->forgetEverything();

                return OverlayOutcome::close(Message::info($this->text('context.chosen', ['name' => $choice])));
            },
            $this->translator,
        );
    }

    public function openNamespacePrompt(): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('namespace.title'),
            [],
            $this->session->namespace()->value ?? '',
            function (string $value): OverlayOutcome {
                try {
                    $this->session->useNamespace(NamespaceName::of($value));
                } catch (InvalidClusterNameException) {
                    return OverlayOutcome::close(Message::error($this->text('namespace.rejected')));
                }

                $this->forgetEverything();

                return OverlayOutcome::close(Message::info($this->text('namespace.chosen', ['name' => $value])));
            },
            $this->translator,
            $this->key('namespace.prompt'),
        );
    }

    public function openApplyPrompt(): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('apply.title'),
            [],
            $this->contextPath,
            function (string $path): OverlayOutcome {
                if (trim($path) === '') {
                    return OverlayOutcome::close();
                }

                $this->actions->apply(trim($path));

                return OverlayOutcome::close();
            },
            $this->translator,
            $this->key('apply.prompt'),
        );
    }

    /**
     * Stan bez klastra — zdanie **we wnętrzu panelu**, nie na całej strefie.
     *
     * Poprawka z 2026-08-16 i dwie rzeczy naraz. Ekran oprawia się sam
     * (`DrawsOwnFrame`, reguła 11c), więc dostaje **cały** prostokąt strefy wraz
     * z wierszami obwódki — a zdanie rysowane od jego pierwszego wiersza kładło
     * się dokładnie na ramce lewego panelu. Wnętrze bierze się stąd tym samym
     * rachunkiem, co przy drzewie: te dwa prostokąty nie mają prawa się rozjechać.
     *
     * Zdanie **się zawija**, bo panel przy podziale ma 0,4 szerokości strefy —
     * od 24 kolumn treści przy progu podziału — a każde zdanie stanu jest dłuższe;
     * jednowierszowa etykieta ucinała je wielokropkiem razem z podpowiedzią
     * klawisza, czyli z jedyną częścią, po którą się je czyta (reguła kroku 48:
     * zanim utniesz treść, sprawdź, czy użytkownik ma ją skąd wziąć).
     *
     * @return list<Primitive>
     */
    private function drawStage(Rect $bounds): array
    {
        $zone = $bounds;

        if ($this->splitsIn($bounds)) {
            [$left] = Split::halves($bounds, SplitAxis::Vertical, $this->splitState->fraction());
            $zone = Panel::inner($left);
        }

        if ($zone->isEmpty()) {
            return [];
        }

        $primitives = [];

        foreach (Label::wrap($this->stageSentence(), $zone->columns) as $offset => $line) {
            if ($offset >= $zone->rows) {
                break;
            }

            foreach ((new Label($line))->draw($zone->line($offset)) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    /** @return list<Primitive> */
    private function drawTree(Rect $bounds): array
    {
        $nodes = $this->tree->nodes();

        if ($nodes === []) {
            return (new Label($this->text($this->catalog->isWorking() ? 'tree.reading' : 'tree.none')))
                ->draw($bounds);
        }

        $keys = $this->tree->keys();
        $cursor = $this->treeState->indexIn($keys);

        // Okno przewijania drzewa jest **polem**, a nie zmienną lokalną, i to
        // jest zmiana kroku 55: do niego powstawało co klatkę na nowo, bo
        // biegło wyłącznie za kursorem — a kółko przewija bez ruszania kursora,
        // więc musi mieć co przesunąć (reguła 11a: co przeżywa klatkę, mieszka
        // obok komponentu).
        $offset = $this->treeWindow->keepVisible($cursor, count($nodes), max(1, $bounds->rows));

        return (new TreeView(
            $nodes,
            $offset,
            $cursor,
            $this->treeWindow->position(count($nodes), max(1, $bounds->rows)),
        ))->draw($bounds);
    }

    /** @return list<Primitive> */
    private function drawContent(Rect $bounds): array
    {
        if ($this->view === ClusterView::Yaml) {
            return $this->details->draw($bounds, raw: true);
        }

        $cursor = $this->treeState->cursor();

        if ($cursor === null) {
            return (new Label($this->text('content.none')))->draw($bounds);
        }

        if ($this->tree->resourceAt($cursor) !== null) {
            return $this->details->draw($bounds, raw: false);
        }

        $kind = $this->tree->kindAt($cursor);

        if ($kind === null) {
            return (new Label($this->text('content.group')))->draw($bounds);
        }

        return $this->resources->draw($bounds, $kind, $this->listCursor);
    }

    /**
     * Przeciągnięcie granicy podziału należy do ekranu, a nie do zaznaczania
     * treści (krok 56) — rdzeń pyta o to raz, w `InputHandler`.
     */
    public function isDraggingOwn(): bool
    {
        return $this->splitState->isDragging();
    }

    /**
     * Wskaźnik w module Kubernetesa: granica podziału, ognisko panelu, kursor
     * drzewa albo listy, kółko (krok 55).
     *
     * Ognisko jest tu **liczbą, a nie stroną podziału** (`$listCursor`), więc
     * kliknięcie w prawy panel stawia je na zero, a w lewy — gasi. Zdanie to
     * powtarza dokładnie `switchFocus()`, i to nie jest powtórzenie rachunku,
     * tylko ta sama para przypisań: gdyby stała w jednym miejscu, klawisz `Tab`
     * musiałby udawać kliknięcie albo odwrotnie.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        if ($this->view === ClusterView::Logs) {
            if ($event->isScroll()) {
                $this->logPane->scrollBy($event->scrollRows());
            }

            return ScreenOutcome::stay();
        }

        $split = $this->splitsIn($bounds);

        if ($split && $this->splitState->pointer($event, $bounds, SplitAxis::Vertical)) {
            return ScreenOutcome::stay();
        }

        [$second, $content] = $this->paneAt($event, $bounds, $split);

        if ($content === null) {
            return ScreenOutcome::stay();
        }

        return $second ? $this->pointerInContent($event, $content) : $this->pointerInTree($event, $content);
    }

    /**
     * Który panel wskazano wraz z prostokątem jego treści.
     *
     * @return array{bool, ?Rect}
     */
    private function paneAt(PointerEvent $event, Rect $bounds, bool $split): array
    {
        if (!$split) {
            // Bez podziału widać samo drzewo — tak samo, jak przy rysowaniu.
            return [false, $bounds];
        }

        foreach (Split::halves($bounds, SplitAxis::Vertical, $this->splitState->fraction()) as $index => $half) {
            if ($event->hits($half)) {
                return [$index === 1, Panel::inner($half)];
            }
        }

        return [false, null];
    }

    private function pointerInTree(PointerEvent $event, Rect $content): ScreenOutcome
    {
        if ($event->isScroll()) {
            $this->treeWindow->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $this->listCursor = null;
        $keys = $this->tree->keys();
        $row = PointerRow::of($event, $content, $this->treeWindow->offset(), false, count($keys));

        if ($row !== null) {
            $this->treeState->moveTo($keys[$row]);
        }

        return ScreenOutcome::stay();
    }

    private function pointerInContent(PointerEvent $event, Rect $content): ScreenOutcome
    {
        if ($event->isScroll()) {
            $this->listWindow->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $kind = $this->focusedKind();

        // Prawy panel bywa opisem zasobu albo zdaniem o grupie — wtedy nie ma
        // wiersza do wskazania, a kliknięcie samo przenosi ognisko.
        if ($kind === null || $this->openResource() !== null) {
            $this->listCursor ??= 0;

            return ScreenOutcome::stay();
        }

        $rows = count($this->reader->rowsOf($kind));
        $row = PointerRow::of($event, $content, $this->listWindow->offset(), true, $rows);

        $this->listCursor = $row ?? $this->listCursor ?? 0;

        return ScreenOutcome::stay();
    }

    private function handleTree(KeyPress $key): ScreenOutcome
    {
        $keys = $this->tree->keys();

        if ($key->key === Key::Enter) {
            return $this->enterNode();
        }

        $moved = $this->moveTree($key, $keys);

        if ($moved !== null) {
            return $moved;
        }

        return $this->handleShared($key);
    }

    private function handleContent(KeyPress $key): ScreenOutcome
    {
        $kind = $this->focusedKind();

        if ($kind !== null && $this->openResource() === null) {
            $rows = count($this->reader->rowsOf($kind));
            $moved = $this->moveList($key, $rows);

            if ($moved !== null) {
                return $moved;
            }

            if ($key->key === Key::Enter) {
                return $this->openSelectedRow($kind);
            }
        }

        if ($this->openResource() !== null) {
            $moved = $this->moveSections($key);

            if ($moved !== null) {
                return $moved;
            }
        }

        return $this->handleShared($key);
    }

    /**
     * Klawisze działające w obu miejscach ekranu.
     *
     * Wspólne, bo dotyczą **klastra albo zaznaczonego zasobu**, a nie miejsca,
     * w którym stoi kursor: wybór kontekstu jest tak samo sensowny z drzewa, jak
     * z listy. Spis w `bindings()` powtarza je dla obu miejsc — reguła 11p każe
     * wymienić wszystko, co tu działa.
     */
    private function handleShared(KeyPress $key): ScreenOutcome
    {
        return match (true) {
            $key->key === Key::Character && $key->raw === 'c' => ScreenOutcome::opens($this->openContextChoice()),
            $key->key === Key::Character && $key->raw === 'n' => ScreenOutcome::opens($this->openNamespacePrompt()),
            $key->key === Key::Character && $key->raw === 'y' => $this->toggleYaml(),
            $key->key === Key::Character && $key->raw === 'l' => $this->openLogs(),
            $key->key === Key::Character && $key->raw === 'x' => $this->revealSecret(),
            $key->key === Key::Character && $key->raw === 'e' => $this->editSecret(),
            $key->key === Key::F5 => ScreenOutcome::opens($this->openApplyPrompt()),
            $key->key === Key::F8, $key->key === Key::Delete => $this->confirmDeletion(),
            default => ScreenOutcome::stay(),
        };
    }

    private function handleLogs(KeyPress $key): ScreenOutcome
    {
        return match ($key->key) {
            Key::Escape => $this->closeLogs(),
            Key::ArrowUp => $this->scrollLogs(-1),
            Key::ArrowDown => $this->scrollLogs(1),
            Key::PageUp => $this->pageLogs(-1),
            Key::PageDown => $this->pageLogs(1),
            Key::Home => $this->jumpLogs(toStart: true),
            Key::End => $this->jumpLogs(toStart: false),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Klawisze stanu bez klastra — **wyboru kontekstu i ponowienia**.
     *
     * Osobna gałąź, bo w tym stanie nie ma drzewa ani listy, a klawisze
     * poruszające nimi nie mają czego poruszać. Ekran, który przyjmuje `Enter`
     * i nic nie robi, uczy użytkownika, że nie działa.
     */
    private function handleStage(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Character && $key->raw === 'c') {
            return ScreenOutcome::opens($this->openContextChoice());
        }

        if ($key->key === Key::Enter || $key->key === Key::F5) {
            return $this->refresh();
        }

        return ScreenOutcome::stay();
    }

    private function enterNode(): ScreenOutcome
    {
        $cursor = $this->treeState->cursor();

        if ($cursor === null) {
            return ScreenOutcome::stay();
        }

        $resource = $this->tree->resourceAt($cursor);

        if ($resource === null) {
            $this->tree->toggle($cursor);

            return ScreenOutcome::stay();
        }

        return $this->openDetail($cursor, $resource);
    }

    private function openDetail(string $cursor, string $name): ScreenOutcome
    {
        $kind = $this->tree->kindAt($cursor);

        if ($kind === null) {
            return ScreenOutcome::stay();
        }

        try {
            $this->detail->open(ResourceRef::of($kind, $this->session->namespace(), $name));
        } catch (InvalidClusterNameException) {
            return ScreenOutcome::stay(Message::error($this->text('detail.rejected', ['name' => $name])));
        }

        $this->sectionState->useContext(self::ID . ':' . $kind->address() . ':' . $name);
        $this->view = ClusterView::Resources;

        return ScreenOutcome::stay();
    }

    private function openSelectedRow(ResourceKind $kind): ScreenOutcome
    {
        $rows = $this->reader->rowsOf($kind);
        $row = $rows[$this->listCursor ?? 0] ?? null;

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $cursor = $this->treeState->cursor();

        if ($cursor === null) {
            return ScreenOutcome::stay();
        }

        // Kursor drzewa przenosi się na wybrany zasób, żeby oba panele mówiły
        // o tym samym — inaczej drzewo wskazywałoby rodzaj, a opis zasób, i nie
        // dałoby się powiedzieć, czego dotyczy `F8`.
        $this->treeState->expand($cursor);
        $this->treeState->moveTo($cursor . '/' . $row->name);

        return $this->openDetail($cursor . '/' . $row->name, $row->name);
    }

    private function toggleYaml(): ScreenOutcome
    {
        if ($this->openResource() === null) {
            return ScreenOutcome::stay(Message::info($this->text('yaml.none')));
        }

        if ($this->view === ClusterView::Yaml) {
            $this->view = ClusterView::Resources;

            return ScreenOutcome::stay();
        }

        $this->view = ClusterView::Yaml;
        $this->detail->askForYaml();

        return ScreenOutcome::stay();
    }

    /**
     * Logi zaznaczonego poda.
     *
     * Kontener wybiera się **tylko wtedy, gdy jest z czego wybierać** — pod
     * jednokontenerowy otwiera logi od razu, bo okno z jedną odpowiedzią jest
     * pytaniem bez treści.
     */
    private function openLogs(): ScreenOutcome
    {
        $reference = $this->detail->reference();

        if ($reference === null || $reference->kind->address() !== 'pods') {
            return ScreenOutcome::stay(Message::info($this->text('logs.notAPod')));
        }

        $containers = $this->detail->containers();

        if (count($containers) > 1) {
            return ScreenOutcome::opens($this->openContainerChoice($reference, $containers));
        }

        $this->startLogs($reference, $containers[0] ?? null);

        return ScreenOutcome::stay();
    }

    /** @param list<string> $containers */
    private function openContainerChoice(ResourceRef $reference, array $containers): ChoiceOverlay
    {
        $options = [];

        foreach ($containers as $container) {
            $options[$container] = $container;
        }

        $options["\0cancel"] = $this->key('logs.cancel');

        return new ChoiceOverlay(
            $this->key('logs.container'),
            [],
            $options,
            function (string $choice) use ($reference): OverlayOutcome {
                if ($choice !== "\0cancel") {
                    $this->startLogs($reference, $choice);
                }

                return OverlayOutcome::close();
            },
            $this->translator,
        );
    }

    private function startLogs(ResourceRef $reference, ?string $container): void
    {
        $this->logs->open($reference, $container, KubernetesSettings::DEFAULT_LOG_LINES, $this->session);
        $this->logPane->reset();
        $this->view = ClusterView::Logs;
    }

    private function closeLogs(): ScreenOutcome
    {
        $this->logs->close();
        $this->view = ClusterView::Resources;

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

    private function revealSecret(): ScreenOutcome
    {
        $overlay = $this->secrets->reveal();

        return $overlay === null
            ? ScreenOutcome::stay(Message::info($this->text('secret.none')))
            : ScreenOutcome::opens($overlay);
    }

    private function editSecret(): ScreenOutcome
    {
        $overlay = $this->secrets->edit();

        return $overlay === null
            ? ScreenOutcome::stay(Message::info($this->text('secret.none')))
            : ScreenOutcome::opens($overlay);
    }

    /**
     * Usunięcie zasobu — **zawsze pytaniem, zawsze w wariancie groźnym**.
     *
     * Kubernetes nie ma kosza: usunięty zasób nie wraca, a usunięty pod
     * zarządzany przez wdrożenie wraca *inny*, co bywa jeszcze gorszym
     * zaskoczeniem. Pytanie niesie adres w postaci `rodzaj/nazwa`, bo to jedyna
     * postać, w której widać, co dokładnie zniknie.
     */
    private function confirmDeletion(): ScreenOutcome
    {
        $reference = $this->detail->reference();

        if ($reference === null) {
            return ScreenOutcome::stay(Message::info($this->text('delete.none')));
        }

        if (!$reference->kind->isDeletable()) {
            return ScreenOutcome::stay(Message::warning(
                $this->text('delete.forbidden', ['kind' => $reference->kind->name]),
            ));
        }

        return ScreenOutcome::opens(new ConfirmOverlay(
            $this->key('delete.confirm'),
            ['name' => $reference->address()],
            function () use ($reference): OverlayOutcome {
                $this->actions->delete($reference);

                return OverlayOutcome::close();
            },
            $this->translator,
            dangerous: true,
        ));
    }

    private function refresh(): ScreenOutcome
    {
        if (!$this->reader->cluster()->stage->allowsQueries()) {
            $this->cluster->begin();

            return ScreenOutcome::stay();
        }

        $this->catalog->begin(force: true);
        $kind = $this->tree->focusedKind();

        if ($kind !== null) {
            $this->cache->load($kind, force: true);
        }

        return ScreenOutcome::stay();
    }

    private function switchFocus(): ScreenOutcome
    {
        $this->splitState->moveFocus();
        $this->listCursor = $this->listCursor === null ? 0 : null;

        return ScreenOutcome::stay();
    }

    /** @param list<string> $keys */
    private function moveTree(KeyPress $key, array $keys): ?ScreenOutcome
    {
        $page = max(1, $this->lastTreeCapacity - 1);

        $delta = match ($key->key) {
            Key::ArrowUp => -1,
            Key::ArrowDown => 1,
            Key::PageUp => -$page,
            Key::PageDown => $page,
            default => null,
        };

        if ($delta !== null) {
            $this->treeState->moveBy($delta, $keys);

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Home) {
            $this->treeState->moveTo($keys[0] ?? null);

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::End) {
            $this->treeState->moveTo($keys === [] ? null : $keys[count($keys) - 1]);

            return ScreenOutcome::stay();
        }

        return null;
    }

    private function moveList(KeyPress $key, int $count): ?ScreenOutcome
    {
        $page = max(1, $this->lastListCapacity - 1);
        $cursor = $this->listCursor ?? 0;

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
            return null;
        }

        $this->listCursor = max(0, min($target, max(0, $count - 1)));

        return ScreenOutcome::stay();
    }

    private function moveSections(KeyPress $key): ?ScreenOutcome
    {
        $sections = $this->details->sections();

        if ($sections === []) {
            return null;
        }

        if ($key->key === Key::ArrowUp || $key->key === Key::ArrowDown) {
            $this->sectionState->moveBy($key->key === Key::ArrowUp ? -1 : 1, count($sections));

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Enter) {
            $cursor = min($this->sectionState->cursor(), count($sections) - 1);
            $this->sectionState->toggle($sections[$cursor]->key);

            return ScreenOutcome::stay();
        }

        return null;
    }

    /** Rodzaj, na którego gałęzi stoi kursor drzewa. */
    private function focusedKind(): ?ResourceKind
    {
        return $this->tree->focusedKind();
    }

    /** Nazwa zasobu otwartego w opisie — `null`, gdy żaden nie jest otwarty. */
    private function openResource(): ?string
    {
        return $this->detail->reference()?->name;
    }

    private function forgetEverything(): void
    {
        $this->detail->stop();
        $this->logs->close();
        $this->treeState->useContext(self::ID . ':' . ($this->session->context()->value ?? ''));
        $this->listCursor = null;
        $this->view = ClusterView::Resources;
    }

    /**
     * Zdanie górnego pasa — **co się właśnie dzieje**, a gdy nic, to gdzie stoimy.
     *
     * Stanu rozmowy z klastrem **tu nie ma** i to jest poprawka z 2026-08-16:
     * pas wypisywał wtedy dokładnie to samo zdanie, co treść ekranu, więc przy
     * braku bieżącego kontekstu widać było dwie kopie jednej informacji. Mówi
     * o stanie **treść**, bo tam jest miejsce na zdanie z podpowiedzią klawisza;
     * pasowi zostaje jego własne pytanie — gdzie stoimy — na które przy braku
     * kontekstu odpowiedź brzmi „nigdzie”, a nie „kontekst , przestrzeń ”.
     */
    private function headerText(): string
    {
        $action = $this->actions->pending();

        if ($action !== null) {
            return $this->text('action.working.' . $action->value);
        }

        if ($this->view === ClusterView::Logs) {
            return $this->text('logs.header', [
                'name' => $this->logs->reference()->name ?? '',
                'container' => $this->logs->container() ?? '',
            ]);
        }

        $context = $this->session->context();

        if ($context === null) {
            return $this->text('header.noPlace');
        }

        $versions = $this->reader->cluster()->versions;
        $skew = $versions !== null && $versions->isSkewed()
            ? ' ' . $this->text('version.skew', [
                'client' => $versions->client,
                'server' => $versions->server ?? '',
            ])
            : '';

        return $this->text('header.place', [
            'context' => $context->value,
            'namespace' => $this->session->namespace()->value ?? '',
        ]) . $skew;
    }

    /** Zdanie zastępujące treść, gdy nie ma o co pytać klastra. */
    private function stageSentence(): string
    {
        $problem = $this->reader->cluster()->problemKey;

        if ($problem !== null) {
            return $this->translator->translate($problem, $this->reader->cluster()->problemParameters);
        }

        return $this->translator->translate($this->reader->cluster()->stage->labelKey());
    }

    /**
     * Zamienia wynik czynności na zdanie i zdarzenie.
     *
     * **Zdarzenie ogłasza `Presentation`** — rejestr zdarzeń mieszka w stanie
     * pętli, którego warstwa aplikacji nie zna (granica z kroku 46).
     */
    private function reportAction(?ActionOutcome $outcome): void
    {
        if ($outcome === null) {
            return;
        }

        if (!$outcome->successful) {
            $this->state->report(Message::error($this->translator->translate(
                $outcome->problemKey ?? $this->key('problem.action'),
                $outcome->problemParameters,
            )), $this->now);
            $this->state->events()->publish(KubernetesEvent::ConnectionLost->value);

            return;
        }

        $this->state->report(Message::info($this->translator->translate(
            $outcome->action->doneKey(),
            ['name' => $outcome->subject],
        )), $this->now);
        $this->state->events()->publish($outcome->action->event()->value);

        // Po własnej zmianie lista i opis są nieaktualne — odświeżamy je bez
        // pytania, bo użytkownik właśnie po to nacisnął klawisz (D91 nr 7).
        $kind = $this->tree->focusedKind();

        if ($kind !== null) {
            $this->cache->load($kind, force: true);
        }

        $this->detail->reload();
    }

    private function splitsIn(Rect $zone): bool
    {
        // Proporcja czytana co klatkę: tę samą pozycję zmienia zakładka ustawień,
        // a w trakcie przeciągania `SplitState` podaną wartość pomija (krok 55).
        $this->splitState->useFraction(
            SplitSetting::fraction($this->core->settings(), self::ID, self::SPLIT_PERCENT),
        );

        return $this->view !== ClusterView::Logs
            && $zone->rows >= 3
            && Split::fits($zone, SplitAxis::Vertical);
    }

    /** @return list<KeyBinding> */
    private function treeBindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::PageUp, Key::PageDown, Key::Home, Key::End], 'help.key.page', 'help.key.page.short'),
            KeyBinding::of([Key::Enter], $this->key('key.expand'), $this->key('key.expand.short')),
            KeyBinding::of([Key::Tab], $this->key('key.focus'), $this->key('key.focus.short')),
            ...$this->sharedBindings(),
        ];
    }

    /** @return list<KeyBinding> */
    private function contentBindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::PageUp, Key::PageDown, Key::Home, Key::End], 'help.key.page', 'help.key.page.short'),
            KeyBinding::of([Key::Enter], $this->key('key.open'), $this->key('key.open.short')),
            KeyBinding::of([Key::Tab], $this->key('key.focus'), $this->key('key.focus.short')),
            ...$this->sharedBindings(),
        ];
    }

    /** @return list<KeyBinding> */
    private function sharedBindings(): array
    {
        return [
            KeyBinding::character('c', $this->key('key.context'), $this->key('key.context.short')),
            KeyBinding::character('n', $this->key('key.namespace'), $this->key('key.namespace.short')),
            KeyBinding::character('y', $this->key('key.yaml'), $this->key('key.yaml.short')),
            KeyBinding::character('l', $this->key('key.logs'), $this->key('key.logs.short')),
            KeyBinding::character('x', $this->key('key.reveal'), $this->key('key.reveal.short')),
            KeyBinding::character('e', $this->key('key.edit'), $this->key('key.edit.short')),
            KeyBinding::of([Key::F5], $this->key('key.apply'), $this->key('key.apply.short')),
            KeyBinding::of([Key::F8, Key::Delete], $this->key('key.delete'), $this->key('key.delete.short')),
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
            KeyBinding::of([Key::Escape], $this->key('key.back'), $this->key('key.back.short')),
        ];
    }

    /** @return list<KeyBinding> */
    private function stageBindings(): array
    {
        return [
            KeyBinding::character('c', $this->key('key.context'), $this->key('key.context.short')),
            KeyBinding::of([Key::Enter, Key::F5], $this->key('key.retry'), $this->key('key.retry.short')),
        ];
    }

    private function key(string $suffix): string
    {
        return 'module.' . KubernetesSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . KubernetesSettings::ID . '.' . $key, $parameters);
    }
}
