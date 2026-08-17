<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

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
use LightManager\Module\FileInfo\Application\Dto\ChecksumStage;
use LightManager\Module\FileInfo\Application\Dto\DescriptionSection;
use LightManager\Module\FileInfo\Application\Dto\DiskUsageStage;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\SizeText;
use LightManager\Module\FileInfo\Presentation\Component\PreviewPane;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\DragsOwnContent;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SectionState;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\SplitState;

/**
 * Ekran modułu: pełny obraz stanu zaznaczonego wpisu.
 *
 * Do kroku 20 ten sam opis pokazywało okno modalne otwierane `Enter`em na pliku.
 * Przeprowadzka nie była kosmetyczna: `Enter` stał się w całej aplikacji
 * klawiszem **zatwierdzania**, a opis pliku — pierwszym dowodem na to, że
 * kontrakt modułu wystarcza do wyprowadzenia z rdzenia działającej funkcji.
 *
 * Krok 25 składa go z **trzech klocków rdzenia dowiezionych osobno**: zwijanych
 * sekcji (krok 22), paska postępu (krok 23) i podziału ekranu (krok 24). To jest
 * ten moment, w którym trzy komponenty powstałe wcześniej dostają wspólnego
 * użytkownika — i dopiero tutaj widać, czy pasują do siebie.
 *
 * Ekran implementuje `ReadsContext`, bo bez wiedzy o zaznaczeniu nie miałby czego
 * opisać. Kontekst przychodzi co klatkę, ale opis **nie liczy się co klatkę**:
 * `FileInfoState` pilnuje, żeby proces `file` ruszał wyłącznie przy zmianie
 * ścieżki. Stąd też bierze się jedyne wywołanie robiące pracę w `draw()` —
 * kawałek sumy kontrolnej przypadający na tę klatkę.
 *
 * Krok 26 dokłada `NeedsTime`, i to nie z powodu zmiany wyglądu: pasek postępu
 * pokazujący pracę `du` nie zna postępu, więc jego wypełnienie **wędruje**, a do
 * wędrowania potrzebny jest zegar. Ekran bierze go z pętli, nie z `microtime()` —
 * tą samą drogą, którą od kroku 19 chodzi do karetki w polu tekstowym (reguła 11b).
 */
final class FileInfoScreen implements
    ScreenInterface,
    ReadsContext,
    Resettable,
    DrawsOwnFrame,
    NeedsTime,
    DeclaresFocus,
    AcceptsPointer,
    DragsOwnContent
{
    /** Prostokąt z ostatniego rysowania — pamięć wymagana przez `AcceptsPointer` (krok 55). */
    private ?Rect $lastBounds = null;

    /**
     * Poniżej tylu kolumn wartość nie zawija się, tylko przycina.
     *
     * Blok węższy od kilkunastu znaków nie jest już zawinięciem, tylko pionowym
     * słupkiem sylab — a wtedy wielokropek mówi więcej. Ta sama zasada, którą
     * `TextView` stosuje do kolumny numerów: element ustępuje, gdy przestaje się
     * opłacać.
     */
    private const WRAP_MINIMUM_COLUMNS = 16;

    /** Domyślny podział: opis po lewej, podgląd po prawej, po połowie (krok 55). */
    private const SPLIT_PERCENT = 50;

    private readonly ScrollWindow $window;

    private readonly SectionState $sections;

    /**
     * Który z dwóch paneli przyjmuje klawisze.
     *
     * Krok 29 rozstrzygnął, że ogniska **nie ma**: strzałki należały do sekcji,
     * a `PgUp`/`PgDn` do podglądu, i miało to wystarczyć, bo klawisze są
     * rozłączne. Rozstrzygnięcie zostało odwołane na żądanie użytkownika
     * (2026-08-12) i powód jest prosty: podgląd tekstu, który nie umie przewinąć
     * się o wiersz, nie jest podglądem tekstu, a strzałki są jedyne, których
     * użytkownik szuka odruchowo.
     *
     * Klasa jest ta sama, którą podział przeglądarki ma od kroku 24 — wraz
     * z regułą „brak podziału sprowadza ognisko na pierwszy panel”, bez której
     * wąskie okno zostawiałoby klawisze u panelu, którego nie widać.
     *
     * Nazwa pola zmieniła się w kroku 40 z `$focus` na `$focusState`, żeby nie
     * czytało się jak metoda `focus()` z `DeclaresFocus`: jedno jest **stanem
     * między klatkami**, drugie **odpowiedzią dla paska stanu**.
     */
    private readonly SplitState $focusState;

    /**
     * Czy **ostatnia** klatka miała dwa panele.
     *
     * Klawisze przychodzą bez prostokąta, a o podziale rozstrzyga szerokość okna,
     * więc odpowiedź musi pochodzić z rysowania. Zapamiętanie jej nie łamie reguły
     * 11f („rozmiaru okna nie wolno pamiętać”): to nie jest rozmiar, tylko wynik
     * pytania zadanego przy ostatnim rysowaniu — a rysowanie poprzedza każdy
     * klawisz.
     */
    private bool $splits = false;

    /** Czas bieżącej klatki — dla paska postępu, który nie zna postępu. */
    private float $now = 0.0;

    private readonly SizeText $sizes;

    public function __construct(
        private readonly FileInfoState $state,
        private readonly PreviewPane $preview,
        private readonly FileInfoQueries $queries,
        /** Odczyt ustawień rdzenia — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly CoreReader $core,
        private readonly TranslatorPort $translator,
        /**
         * Stan podziału przychodzi z zewnątrz od kroku 55: niesie proporcję
         * wczytaną z ustawień modułu i zapis po przeciągnięciu granicy myszą.
         */
        ?SplitState $split = null,
    ) {
        $this->window = new ScrollWindow();
        $this->sections = new SectionState();
        $this->focusState = $split ?? new SplitState();
        $this->sizes = new SizeText($translator);
    }

    public function id(): string
    {
        return 'file-info';
    }

    public function labelKey(): string
    {
        return 'module.file-info.name';
    }

    /**
     * Górny pas: katalog, z którego pochodzi opisywany wpis — albo **zbiór
     * zaznaczonych**, jeśli przeglądarka jakiś ogłosiła (krok 43).
     *
     * Treść pasa jest ta sama, którą do kroku 20 stawiał tam rdzeń; zmienił się
     * wyłącznie ten, kto ją zamawia. Krok 43 dokłada jeden warunek i to on jest
     * **odbiorcą** zbioru w kontekście sesji (reguła 13): rdzeń urósł o pojęcie
     * zaznaczenia wielokrotnego (D80, rozstrzygnięcie 1), więc coś musi z tego
     * pojęcia korzystać — inaczej byłby to mechanizm na zapas.
     *
     * Zbiór **zastępuje** ścieżkę, a nie dopisuje się do niej: użytkownik, który
     * zaznaczył dwanaście plików, patrzy na ten ekran po to, żeby dowiedzieć się
     * o nich czegoś, a nie po to, żeby przeczytać ścieżkę, która stoi
     * niezmieniona w przeglądarce piętro niżej. Sam opis pod spodem zostaje przy
     * tym opisem **wpisu pod kursorem** — bo dwunastu plików naraz nie da się
     * opisać jednym zestawem sekcji, a kursor gdzieś przecież stoi.
     */
    public function header(): ScreenZone
    {
        $context = $this->core->context();

        if (!$context->hasMarked()) {
            return new ScreenZone('layout.zone.path', new Label($context->path));
        }

        return new ScreenZone('layout.zone.path', new Label($this->markedSummary($context)));
    }

    /**
     * Zdanie o zbiorze: ile wpisów i ile razem ważą.
     *
     * Suma pomija katalogi, bo ich rozmiaru nikt nie zna (zajętość liczy `du`,
     * krok 26) — i napis mówi to wprost, gdy w zbiorze jakiś katalog jest. To ta
     * sama reguła, którą kieruje się podsumowanie w pasie ścieżki przeglądarki:
     * suma milcząca o pominięciu jest sumą nieprawdziwą.
     */
    private function markedSummary(ModuleContext $context): string
    {
        $parameters = [
            'count' => $this->translator->number((float) $context->markedCount),
            'size' => $this->sizes->format($context->markedBytes),
        ];

        if ($context->markedDirectories === 0) {
            return $this->translator->plural('module.file-info.marked', $context->markedCount, $parameters);
        }

        $parameters['dirs'] = $this->translator->number((float) $context->markedDirectories);

        return $this->translator->plural('module.file-info.marked.dirs', $context->markedCount, $parameters);
    }

    public function reset(): void
    {
        $this->state->reset();
        $this->window->scrollBy(-PHP_INT_MAX);
    }

    public function useTime(float $now): void
    {
        $this->now = $now;
    }

    public function useContext(ModuleContext $context): void
    {
        $this->state->useContext($context);
        $this->window->useContext($context->selectionPath() ?? '');
        $this->sections->useContext($context->selectionPath() ?? '');
    }

    /**
     * Przy podziale ekran oprawia się sam — dwie obwódki zamiast jednej, bo rdzeń
     * wie o jednej strefie środkowej (krok 24, `DrawsOwnFrame`). Prymitywy wracają
     * do rdzenia, a nie są rysowane tutaj: rdzeń kładzie je na płaszczyźnie
     * pamiętanej między klatkami, więc obwódki nie kosztują ani jednej klatki
     * ponad pierwszą.
     */
    public function ownFrame(Rect $zone): array
    {
        if (!$this->splitsIn($zone)) {
            return [];
        }

        $primitives = [];
        $labels = ['module.file-info.name', 'layout.zone.preview'];

        foreach (Split::halves($zone, SplitAxis::Vertical, $this->focusState->fraction()) as $index => $bounds) {
            // Panel z ogniskiem poznaje się po akcencie w nawiasach i w etykiecie
            // — dokładnie tak, jak panel czynny w przeglądarce od kroku 24.
            // Bez tego przeniesienie ogniska byłoby ruchem bez śladu na ekranie.
            $focused = $this->focusState->focusesSecond() === ($index === 1);
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

    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;

        // Kawałek sumy kontrolnej przypadający na tę klatkę. Stoi tutaj, bo
        // `draw()` jest jedynym wywołaniem, które na pewno przychodzi w każdej
        // klatce **i tylko wtedy, gdy ekran jest widoczny** — praca nie posuwa
        // się do przodu, gdy użytkownik patrzy na co innego.
        $this->state->advance();

        if ($this->queries->description() === null) {
            return (new Label($this->translator->translate('module.file-info.nothing')))
                ->draw($this->sentenceArea($bounds));
        }

        if (!$this->splitsIn($bounds)) {
            return $this->body($bounds);
        }

        [$left, $right] = Split::halves($bounds, SplitAxis::Vertical, $this->focusState->fraction());
        $primitives = $this->body(Panel::inner($left));

        foreach ($this->preview->draw(Panel::inner($right)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Prostokąt na zdanie „nie zaznaczono wpisu“ — **wnętrze lewego panelu**, gdy
     * ekran rysuje własną oprawę.
     *
     * Do poprawki z 2026-08-12 zdanie szło na surowy prostokąt ekranu, którego
     * pierwszy wiersz jest **linią obwódki**: napis siadał na niej i nakładał się
     * na etykietę „Opis pliku“, litera na literę. Widać to było dopiero wtedy, gdy
     * opisu nie ma, a panele są dwa — czyli w przypadku, który krok 25 zostawił
     * bez zrzutu, bo wtedy zaznaczenie znikało wyłącznie w pustym katalogu.
     * Filtrowanie z kroku 30 dołożyło drugą drogę do tego stanu: lista zawężona
     * tak, że nie pasuje nic.
     *
     * Oprawę rysuje `ownFrame()` **niezależnie od tego, czy opis jest**, więc
     * zdanie musi liczyć geometrię tak samo, jak liczy ją treść trzy metody niżej.
     */
    private function sentenceArea(Rect $bounds): Rect
    {
        if (!$this->splitsIn($bounds)) {
            return $bounds;
        }

        return Panel::inner(Split::halves($bounds, SplitAxis::Vertical, $this->focusState->fraction())[0]);
    }

    /**
     * Sekcje, a pod nimi pasek postępu — ale **tylko wtedy, gdy coś trwa**.
     *
     * Wiersz paska odbiera się sekcjom, a nie dokłada do okna: gdyby układ
     * przesuwał się w chwili naciśnięcia klawisza, treść uciekłaby spod kursora
     * przewijania. Tak samo rozstrzygnął to krok 12 dla pasa podglądu.
     *
     * @return list<Primitive>
     */
    private function body(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $bar = $this->workBar();
        $list = $bar === null ? $bounds : $bounds->rowsFrom(0, $bounds->rows - 1);
        $primitives = $this->sectionList($list->rows, $list->columns)->draw($list);

        if ($bar === null) {
            return $primitives;
        }

        foreach ($bar->draw($bounds->line($bounds->rows - 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Pasek trwającej pracy albo `null`, gdy nic nie trwa.
     *
     * Praca jest najwyżej jedna, więc pasek też: sumę liczymy tylko dla zwykłych
     * plików, zajętość tylko dla katalogów, a jedno wyklucza drugie. Kolejność
     * `if`ów jest mimo to rozstrzygnięciem, nie przypadkiem — gdyby kiedyś obie
     * prace mogły trwać naraz, pierwszeństwo ma ta, która **zna postęp**, bo
     * pasek z prawdziwym wypełnieniem mówi więcej niż wędrujący.
     */
    private function workBar(): ?ProgressBar
    {
        $checksum = $this->queries->checksum();

        if ($checksum->isRunning()) {
            return new ProgressBar(
                $checksum->fraction,
                $this->translator->translate('module.file-info.checksum.working'),
                translator: $this->translator,
            );
        }

        if (!$this->queries->diskUsage()->isRunning()) {
            return null;
        }

        // Postępu nie ma i nie ma go skąd wziąć: `du` milczy, aż skończy. Pasek
        // dostaje `null` zamiast zmyślonego ułamka i wędruje — pierwszy raz
        // w aplikacji, bo od kroku 23 ten tryb miał wyłącznie test i pomiar.
        return new ProgressBar(
            null,
            $this->translator->translate('module.file-info.diskUsage.working'),
            $this->now,
            $this->translator,
        );
    }

    /**
     * Spis sekcji wraz z oknem przewijania podążającym za kursorem.
     *
     * Rachunek jest ten sam, co w oknie pomocy od kroku 22, i **ma taki
     * zostać**: dwa wywołania `keepVisible()`, z których pierwsze ściąga okno do
     * końca sekcji pod kursorem, a drugie pilnuje jej nagłówka i wygrywa.
     */
    private function sectionList(int $capacity, int $columns): SectionList
    {
        $sections = $this->wrapped($this->sections(), $columns);
        $this->sections->moveBy(0, count($sections));

        $cursor = $this->sections->cursor();
        $total = SectionList::rowCount($sections);
        $current = $sections[$cursor] ?? null;

        if ($current !== null) {
            $first = SectionList::rowOf($sections, $cursor);
            $this->window->keepVisible($first + $current->height() - 1, $total, $capacity);
            $this->window->keepVisible($first, $total, $capacity);
        }

        return new SectionList(
            $sections,
            $this->window->offset(),
            $current === null ? null : $cursor,
            $this->window->position($total, $capacity),
        );
    }

    /**
     * Sekcje po zawinięciu wartości, które nie mieszczą się obok swojej etykiety.
     *
     * Zawijanie dzieje się **tutaj, a nie w `sections()`**, i to jest jedyna
     * nieoczywistość tej pary metod: wymaga szerokości panelu, a tę zna dopiero
     * rysowanie. Wywołane stąd trafia jednak przed `rowCount()` i `rowOf()`, więc
     * przewijanie i suwak liczą wiersze **po** zawinięciu — inaczej sekcja
     * z długim opisem wystawałaby poniżej okna, a kursor przestałby trafiać
     * w nagłówki. Liczba **sekcji** się przy tym nie zmienia, więc `handle()`
     * może pytać o nie bez szerokości i pyta.
     *
     * Do poprawki z 2026-08-12 zawijania nie było wcale, a `Label` wartości nie
     * przycinał: opis od polecenia `file` — dla zdjęcia potrafi mieć sto
     * dwadzieścia osiem znaków — wychodził poza panel i rysował się po sąsiednim.
     *
     * @param list<Section> $sections
     *
     * @return list<Section>
     */
    private function wrapped(array $sections, int $columns): array
    {
        $wrapped = [];

        foreach ($sections as $section) {
            $rows = [];

            foreach ($section->rows as $row) {
                foreach ($this->wrappedRow($row, $columns) as $line) {
                    $rows[] = $line;
                }
            }

            $wrapped[] = new Section($section->key, $section->label, $rows, $section->collapsed);
        }

        return $wrapped;
    }

    /**
     * Jeden wiersz opisu rozłożony na tyle wierszy, ile trzeba.
     *
     * Kawałki mają **jednakową szerokość**, a ostatni jest dopełniony spacjami,
     * i to nie jest kosmetyka: `ListRow` dosuwa wartość do prawej krawędzi, więc
     * kawałki różnej długości ustawiłyby się w schodki. Dopełnienie sprawia, że
     * blok stoi równo pod pierwszym kawałkiem — czyli tam, gdzie zaczyna się
     * wartość obok etykiety.
     *
     * @return list<ListRow>
     */
    private function wrappedRow(ListRow $row, int $columns): array
    {
        // Miejsce na wartość obok etykiety, wraz z odstępem od niej. Poniżej
        // progu czytelności nie zawijamy wcale — wąski panel dostaje wartość
        // przyciętą, jak przed tą poprawką, bo blok o szerokości pięciu znaków
        // byłby gorszy od wielokropka.
        $room = $columns - mb_strlen($row->left) - 1;

        if ($room < self::WRAP_MINIMUM_COLUMNS || mb_strlen($row->right) <= $room) {
            return [$row];
        }

        $pieces = [];
        $length = mb_strlen($row->right);

        for ($offset = 0; $offset < $length; $offset += $room) {
            $piece = mb_substr($row->right, $offset, $room);
            $pieces[] = $offset === 0
                ? $piece
                : $piece . str_repeat(' ', $room - mb_strlen($piece));
        }

        $lines = [new ListRow($row->left, $pieces[0], $row->role)];

        foreach (array_slice($pieces, 1) as $piece) {
            $lines[] = new ListRow('', $piece, $row->role);
        }

        return $lines;
    }

    /**
     * Sekcje opisu wraz z **dołożonym wierszem sumy kontrolnej**.
     *
     * Wiersz dokłada ekran, a nie przypadek użycia, i to jest rozstrzygnięcie,
     * nie skrót: opis liczy się raz na zaznaczenie, a stan sumy zmienia się co
     * klatkę. Gdyby siedział w `EntryDescription`, obiekt liczony raz musiałby
     * być przeliczany trzydzieści razy na sekundę — albo kłamać.
     *
     * @return list<Section>
     */
    private function sections(): array
    {
        $description = $this->queries->description();

        if ($description === null) {
            return [];
        }

        $sections = [];

        foreach ($description->sections as $section) {
            $rows = $this->rowsOf($section);

            if ($section->key === 'size') {
                if ($description->kind === EntryKind::Directory) {
                    $rows[] = $this->diskUsageRow();
                }

                $rows[] = $this->checksumRow();
            }

            $sections[] = new Section(
                $section->key,
                $this->translator->translate($section->labelKey),
                $rows,
                $this->sections->isCollapsed($section->key),
            );
        }

        return $sections;
    }

    /** @return list<ListRow> */
    private function rowsOf(DescriptionSection $section): array
    {
        $rows = [];

        foreach ($section->rows as $row) {
            $rows[] = new ListRow($this->translator->translate($row->labelKey), $row->value, Role::Muted);
        }

        return $rows;
    }

    /**
     * Wiersz sumy kontrolnej w czterech odsłonach: nie zaczęto, trwa, gotowe,
     * nie da się. Każda mówi swoje — pusty wiersz nie mówi nic, a to jest ten
     * jeden wiersz, przy którym użytkownik musi wiedzieć, czy coś się dzieje.
     */
    private function checksumRow(): ListRow
    {
        $checksum = $this->queries->checksum();

        return match ($checksum->stage) {
            ChecksumStage::Running => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $this->translator->translate('module.file-info.checksum.working'),
                Role::Muted,
            ),
            ChecksumStage::Done => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $checksum->digest ?? '',
                Role::Muted,
            ),
            ChecksumStage::Failed => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $this->translator->translate($checksum->problemKey ?? '', $checksum->problemParameters),
                Role::Warning,
            ),
            ChecksumStage::Idle => new ListRow(
                $this->translator->translate('module.file-info.row.checksum'),
                $this->translator->translate('module.file-info.checksum.idle'),
                Role::Muted,
            ),
        };
    }

    /**
     * Wiersz zajętości na dysku — w tych samych czterech odsłonach, co suma
     * kontrolna, bo to ta sama praca widziana z drugiej strony.
     *
     * Wiersz powstaje **tylko dla katalogu** i stoi **przed** sumą kontrolną,
     * zaraz po blokach i-węzła, bo to z nimi rozmawia: bloki mówią, ile waży sam
     * katalog, ten wiersz — ile waży wszystko, co w nim leży.
     */
    private function diskUsageRow(): ListRow
    {
        $usage = $this->queries->diskUsage();
        $label = $this->translator->translate('module.file-info.row.diskUsage');

        return match ($usage->stage) {
            DiskUsageStage::Running => new ListRow(
                $label,
                $this->translator->translate('module.file-info.diskUsage.working'),
                Role::Muted,
            ),
            DiskUsageStage::Done => new ListRow(
                $label,
                $this->sizes->format($usage->bytes ?? 0),
                Role::Muted,
            ),
            DiskUsageStage::Failed => new ListRow(
                $label,
                $this->translator->translate($usage->problemKey ?? '', $usage->problemParameters),
                Role::Warning,
            ),
            DiskUsageStage::Idle => new ListRow(
                $label,
                $this->translator->translate('module.file-info.diskUsage.idle'),
                Role::Muted,
            ),
        };
    }

    /**
     * Klawisze ekranu — **zależne od tego, który panel ma ognisko**.
     *
     * Krok 29 rozdzielił je inaczej: strzałki należały na stałe do sekcji,
     * a `PgUp`/`PgDn`/`Home` na stałe do podglądu, bo „panele odpowiadają na
     * rozłączne klawisze, więc nie ma czego przełączać”. Rozstrzygnięcie zostało
     * odwołane na żądanie użytkownika (2026-08-12): podgląd bez strzałek nie jest
     * podglądem tekstu, a strzałek nie da się mieć w dwóch miejscach naraz.
     *
     * Spis pokazuje **wyłącznie to, co działa tu i teraz** — tą samą regułą, którą
     * przeglądarka pokazuje `Tab` dopiero przy włączonym podziale: podpowiedź
     * o klawiszu, który nic nie robi, jest kłamstwem, a pasek stanu i okno pomocy
     * mają jedno źródło.
     */
    public function bindings(): array
    {
        $bindings = $this->focus()->bindings;

        // Klawisz ogniska pokazujemy dopiero wtedy, gdy jest dokąd je przenieść —
        // w wąskim oknie prawego panelu nie ma.
        if ($this->splits) {
            $bindings[] = KeyBinding::of([Key::Tab], 'module.file-info.help.focus', 'module.file-info.help.focus.short');
        }

        $bindings[] = KeyBinding::alt('z', 'module.file-info.help.wrap', 'module.file-info.help.wrap.short');
        $bindings[] = KeyBinding::character(
            's',
            'module.file-info.help.checksum',
            'module.file-info.help.checksum.short',
        );
        $bindings[] = KeyBinding::character(
            'd',
            'module.file-info.help.diskUsage',
            'module.file-info.help.diskUsage.short',
        );
        $bindings[] = KeyBinding::of([Key::Escape], 'help.key.back', 'help.key.back.short');

        return $bindings;
    }

    /**
     * Ognisko: sekcje albo podgląd tekstu — **najbogatszy odbiorca kroku 40**
     * i jego właściwy sprawdzian.
     *
     * Ten ekran jest jedynym, w którym ten sam klawisz znaczy w dwóch miejscach
     * dwie różne rzeczy: `↑↓` przewija sekcje po lewej, a linijki podglądu po
     * prawej (D60). Do kroku 40 stopka milczała o obu naraz, więc jedyną drogą do
     * tej wiedzy było okno pomocy — a ono pokazuje **oba** panele i nie mówi,
     * który z nich odpowiada teraz.
     */
    public function focus(): FocusHint
    {
        if ($this->focusState->focusesSecond()) {
            return new FocusHint('module.file-info.focus.preview', [
                KeyBinding::of(
                    [Key::ArrowUp, Key::ArrowDown],
                    'module.file-info.help.scrollLine',
                    'module.file-info.help.scrollLine.short',
                ),
                KeyBinding::of(
                    [Key::PageUp, Key::PageDown],
                    'module.file-info.help.scrollPreview',
                    'module.file-info.help.scrollPreview.short',
                ),
                KeyBinding::of(
                    [Key::Home, Key::End],
                    'module.file-info.help.edges',
                    'module.file-info.help.edges.short',
                ),
            ]);
        }

        return new FocusHint('module.file-info.focus.sections', [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::Enter], 'help.key.collapse', 'help.key.collapse.short'),
            KeyBinding::of(
                [Key::Home, Key::End],
                'module.file-info.help.sectionEdges',
                'module.file-info.help.sectionEdges.short',
            ),
        ]);
    }

    /**
     * Przeciągnięcie granicy podziału należy do ekranu, a nie do zaznaczania
     * treści (krok 56) — rdzeń pyta o to raz, w `InputHandler`.
     */
    public function isDraggingOwn(): bool
    {
        return $this->focusState->isDragging();
    }

    /**
     * Wskaźnik w opisie pliku: granica podziału, ognisko panelu, kursor sekcji
     * i kółko (krok 55).
     *
     * Sekcje mają wysokość większą niż jeden wiersz, więc numer wiersza trzeba
     * przełożyć na numer sekcji — i robi to `sectionAt()`, tym samym rachunkiem
     * (`SectionList::rowOf()`), którym rysuje się listę. Drugi rachunek
     * rozjechałby się przy pierwszej sekcji, która zmieni wysokość.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        $split = $this->splitsIn($bounds);

        if ($split && $this->focusState->pointer($event, $bounds, SplitAxis::Vertical)) {
            return ScreenOutcome::stay();
        }

        [$second, $content] = $this->paneAt($event, $bounds, $split);

        if ($content === null) {
            return ScreenOutcome::stay();
        }

        $this->focusState->focus($second);

        if ($event->isScroll()) {
            // Podgląd tekstu przewija się **linijkami panelu**, a nie wierszami
            // pliku (reguła 11i), więc kółko woła tę samą drogę, co strzałka.
            $second
                ? $this->state->scrollTextRows($event->scrollRows())
                : $this->window->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($second || $event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $section = $this->sectionAt($event, $content);

        return $section === null
            ? ScreenOutcome::stay()
            : $this->movedSection($section - $this->sections->cursor(), count($this->sections()));
    }

    /**
     * Który panel wskazano wraz z prostokątem jego treści; przy jednym panelu
     * kliknięcie ogniska nie przenosi, bo nie ma dokąd.
     *
     * @return array{bool, ?Rect}
     */
    private function paneAt(PointerEvent $event, Rect $bounds, bool $split): array
    {
        if (!$split) {
            return [$this->focusState->focusesSecond(), $bounds];
        }

        foreach (Split::halves($bounds, SplitAxis::Vertical, $this->focusState->fraction()) as $index => $half) {
            if ($event->hits($half)) {
                return [$index === 1, Panel::inner($half)];
            }
        }

        return [false, null];
    }

    /** Numer sekcji pod wskaźnikiem — sekcje bywają wyższe niż wiersz. */
    private function sectionAt(PointerEvent $event, Rect $content): ?int
    {
        $list = $this->workBar() === null ? $content : $content->rowsFrom(0, $content->rows - 1);
        $row = PointerRow::of($event, $list, $this->window->offset());

        if ($row === null) {
            return null;
        }

        $sections = $this->wrapped($this->sections(), $list->columns);

        foreach ($sections as $index => $section) {
            $first = SectionList::rowOf($sections, $index);

            if ($row >= $first && $row < $first + $section->height()) {
                return $index;
            }
        }

        return null;
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Escape) {
            return ScreenOutcome::close();
        }

        if ($key->key === Key::Tab) {
            return $this->moveFocus();
        }

        // Klawisze niezwiązane z żadnym panelem — działają niezależnie od ogniska,
        // bo dotyczą **opisywanego pliku**, a nie tego, na co się patrzy.
        return match (true) {
            $this->isLetter($key, 'z', alt: true) => ScreenOutcome::stay($this->state->toggleTextWrap()),
            $this->isLetter($key, 's') => $this->startChecksum(),
            $this->isLetter($key, 'd') => $this->startDiskUsage(),
            $this->focusState->focusesSecond() => $this->toPreview($key),
            default => $this->toSections($key),
        };
    }

    /**
     * `Tab` przenosi ognisko, ale wyłącznie wtedy, gdy jest dokąd: przy jednym
     * panelu klawisz **nie jest zużyty** i wraca do rdzenia — tak samo, jak
     * w przeglądarce od kroku 24.
     */
    private function moveFocus(): ScreenOutcome
    {
        if ($this->splits) {
            $this->focusState->moveFocus();
        }

        return ScreenOutcome::stay();
    }

    /**
     * Klawisze podglądu. Strzałka to **linijka panelu**, nie wiersz pliku —
     * przy zawijaniu to nie to samo, a linijka jest tym, co użytkownik widzi.
     */
    private function toPreview(KeyPress $key): ScreenOutcome
    {
        match ($key->key) {
            Key::ArrowUp => $this->state->scrollTextRows(-1),
            Key::ArrowDown => $this->state->scrollTextRows(1),
            Key::PageUp => $this->state->scrollTextPanels(-1),
            Key::PageDown => $this->state->scrollTextPanels(1),
            Key::Home => $this->state->rewindText(),
            Key::End => $this->state->forwardTextToEnd(),
            default => null,
        };

        return ScreenOutcome::stay();
    }

    private function toSections(KeyPress $key): ScreenOutcome
    {
        $count = count($this->sections());

        return match ($key->key) {
            Key::ArrowUp => $this->movedSection(-1, $count),
            Key::ArrowDown => $this->movedSection(1, $count),
            Key::Home => $this->movedSection(-$count, $count),
            Key::End => $this->movedSection($count, $count),
            Key::Enter => $this->toggleSection(),
            default => ScreenOutcome::stay(),
        };
    }

    private function movedSection(int $delta, int $count): ScreenOutcome
    {
        $this->sections->moveBy($delta, $count);

        return ScreenOutcome::stay();
    }

    /**
     * Litera wraz z modyfikatorem — porównanie, które od kroku 29 musi być jawne.
     *
     * Do niego wystarczało `raw === 's'`, bo `Ctrl`+litera trafiała wcześniej do
     * skrótów modułów i tu nie docierała. `Alt`+litera dociera, więc czynność
     * pytana o samą literę odpowiadałaby także na `Alt`+tę literę — a to jest
     * dokładnie ten rodzaj pomyłki, którego nie widać w testach ekranu, tylko
     * w używaniu aplikacji.
     */
    private function isLetter(KeyPress $key, string $letter, bool $alt = false): bool
    {
        return $key->key === Key::Character
            && $key->raw === $letter
            && $key->alt === $alt
            && !$key->ctrl;
    }

    private function toggleSection(): ScreenOutcome
    {
        $sections = $this->sections();
        $current = $sections[$this->sections->cursor()] ?? null;

        if ($current !== null) {
            $this->sections->toggle($current->key);
        }

        return ScreenOutcome::stay();
    }

    private function startChecksum(): ScreenOutcome
    {
        return $this->started($this->state->startChecksum());
    }

    private function startDiskUsage(): ScreenOutcome
    {
        return $this->started($this->state->startDiskUsage());
    }

    /** Odmowa idzie na pasek stanu, zgoda nie mówi nic — mówi za nią pasek postępu. */
    private function started(?string $refusal): ScreenOutcome
    {
        return $refusal === null
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::warning($this->translator->translate($refusal)));
    }

    /** Podział powstaje wtedy i tylko wtedy, gdy mieści się w oknie (krok 24). */
    /**
     * Czy w tym prostokącie powstaną dwa panele — **wraz z uzgodnieniem ogniska**.
     *
     * Uzgodnienie jest tu z tego samego powodu, co w przeglądarce: okno zwężone
     * poniżej progu zostawiłoby klawisze u podglądu, którego już nie widać.
     */
    private function splitsIn(Rect $zone): bool
    {
        $this->splits = $zone->rows >= 3 && Split::fits($zone, SplitAxis::Vertical);
        $this->focusState->useSplit($this->splits);

        // Proporcja czytana co klatkę, bo tę samą pozycję zmienia też zakładka
        // ustawień; w trakcie przeciągania `SplitState` ją pomija (krok 55).
        $this->focusState->useFraction(
            SplitSetting::fraction($this->core->settings(), FileInfoSettings::ID, self::SPLIT_PERCENT),
        );

        return $this->splits;
    }
}
