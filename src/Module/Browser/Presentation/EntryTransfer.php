<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use Closure;
use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\TransferStage;
use LightManager\Application\Dto\TransferState;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\FileTransferPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserEvent;
use LightManager\Module\Browser\Application\BrowserEvents;
use LightManager\Module\Browser\Application\Undo\UndoEntry;
use LightManager\Module\Browser\Application\Undo\UndoJournal;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Presentation\Component\EntrySize;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Dwie czynności dłuższe od klatki: kopiowanie i przeniesienie (krok 42).
 *
 * Klasa jest bliźniakiem `EntryOperations` i istnieje z tego samego powodu:
 * **czynność o dwóch wejściach mieszka w jednym miejscu** (wzorzec `HiddenEntries`
 * z kroku 32). Kopiowanie wywołuje `F5` i komenda `browser.copy`, przeniesienie —
 * `F6` i `browser.move`. Osobna od tamtej, bo tamta prowadzi czynności
 * **natychmiastowe** wraz z usuwaniem, a ta jedną pracę o zupełnie innym stanie:
 * z listą źródeł, celem, kolejką i pamięcią odpowiedzi o kolizjach.
 *
 * **Łańcuch okien jest tu najdłuższy w całym projekcie** i to jest właściwa treść
 * tej klasy. Krok 41 miał trzy okna po kolei (liczenie → pytanie → usuwanie);
 * tutaj bywa ich pięć, bo kolizja może przyjść w środku pracy i wrócić do niej:
 *
 * ```
 * ścieżka celu → [liczenie] → postęp ⇄ [kolizja → [nowa nazwa]] → zdanie w pasku
 * ```
 *
 * Okna w nawiasach kwadratowych powstają **tylko wtedy, gdy mają co pokazać**:
 * liczenie znika przy pliku i małym katalogu, postęp — przy pracy mieszczącej się
 * w pierwszym kawałku, a kolizja — gdy w celu nic nie stoi. Reguła jest ta sama,
 * co w kroku 41 („okno mignąwszy na klatkę czyta się jak usterka”) i dlatego
 * ścieżka kodu zostaje **jedna**: zawsze praca kawałkowa, nigdy drugi tryb.
 */
final class EntryTransfer
{
    /**
     * Ile wpisów policzyć i ile bajtów skopiować w jednym takcie.
     *
     * Obie liczby wzięte z budżetu klatki (33 ms przy 30 kl./s) i **zmierzone**
     * trybem `--loop`, a nie wybrane dla okrągłości (D79, rozstrzygnięcie 10):
     * liczenie jest tanim `scandir`em, więc bierze tyle samo, co przy usuwaniu,
     * a kopiowanie idzie strumieniem, więc mierzy się bajtami. 4 MiB na takt to
     * ~120 MB/s przy pełnej płynności — więcej niż odda większość dysków, a mniej
     * niż zdąży zjeść klatkę.
     */
    private const SCAN_PER_TICK = 512;

    private const BYTES_PER_TICK = 4 * 1024 * 1024;

    /** Odpowiedzi na kolizję — identyfikator okna wyboru na słownik portu. */
    private const CHOICES = [
        'overwrite' => TransferChoice::Overwrite,
        'overwriteAll' => TransferChoice::OverwriteAll,
        'skip' => TransferChoice::Skip,
        'skipAll' => TransferChoice::SkipAll,
        'rename' => TransferChoice::Rename,
        'abort' => TransferChoice::Abort,
    ];

    /**
     * Skąd, dokąd i co — zapamiętane na czas pracy.
     *
     * Pola, a nie parametry wleczone przez osiem metod: praca jest jedna naraz
     * (reguła 11d), więc jest to stan tej jednej pracy — tak samo, jak `successor`
     * w `EntryOperations`.
     */
    private ?DirectoryPath $source = null;

    private ?DirectoryPath $target = null;

    private string $name = '';

    /**
     * Ile wpisów niesie ta praca — `1` albo liczność zbioru (krok 43).
     *
     * Pole obok nazwy, a nie zamiast niej: nazwa jedzie do okien przy jednym
     * wpisie, liczba przy zbiorze, a kursor po pracy idzie **za pierwszym**
     * przeniesionym niezależnie od tego, ilu ich było.
     */
    private int $count = 1;

    private bool $moves = false;

    /**
     * Pełna lista nazw prowadzonej pracy — dla zapisu w stosie cofnięć
     * (krok 44). `name` i `count` nie wystarczą: cofnięcie przeniesienia musi
     * wiedzieć, **co** przenieść z powrotem, a nie tylko ile tego było.
     *
     * @var list<string>
     */
    private array $names = [];

    /**
     * Czy trwająca praca jest **cofnięciem** przeniesienia — wtedy nie zapisuje
     * się w stosie (cofnięcie cofnięcia to `redo`, a tego krok nie ma) i melduje
     * się po pełnym ukończeniu domknięciem poniżej.
     */
    private bool $undoing = false;

    private ?Closure $undoCompletion = null;

    public function __construct(
        private readonly BrowserPanes $panes,
        /** Odczyt danych przeglądarki — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly BrowserQueries $queries,
        private readonly FileTransferPort $transfers,
        private readonly PaneRefresh $refresh,
        private readonly TranslatorPort $translator,
        private readonly UndoJournal $journal,
        private readonly BrowserEvents $events,
    ) {
    }

    /** `F5` — okno ze ścieżką celu, wypełnione katalogiem drugiego panelu. */
    public function copyPrompt(): ScreenOutcome
    {
        return self::forScreen($this->copyRequest());
    }

    /** `F6` — to samo dla przeniesienia. */
    public function movePrompt(): ScreenOutcome
    {
        return self::forScreen($this->moveRequest());
    }

    /** Komenda `browser.copy [ścieżka]`: bez ścieżki pyta oknem, ze ścieżką rusza od razu. */
    public function copyRequest(?string $path = null): OverlayOutcome
    {
        return $this->request(false, $path);
    }

    /** Komenda `browser.move [ścieżka]`. */
    public function moveRequest(?string $path = null): OverlayOutcome
    {
        return $this->request(true, $path);
    }

    private function request(bool $moves, ?string $path): OverlayOutcome
    {
        $operands = $this->queries->operands();

        if ($operands === null) {
            return OverlayOutcome::close($this->info('module.browser.problem.noSelection'));
        }

        if ($path !== null) {
            return $this->start($moves, $path);
        }

        [, $names] = $operands;
        [$key, $parameters] = $this->titleOf($moves ? 'module.browser.move.title' : 'module.browser.copy.title', $names);

        return OverlayOutcome::replace(new PromptOverlay(
            $key,
            $parameters,
            $this->otherDirectory()->value,
            fn (string $value): OverlayOutcome => $this->start($moves, $value),
            $this->translator,
            'prompt.path',
        ));
    }

    /**
     * Tytuł okna: nazwa wpisu przy jednym, ich liczba przy zbiorze (krok 43).
     *
     * Bliźniak rachunku z `EntryOperations` i powtórzeniem **nie jest**: obie
     * klasy mają własne zestawy kluczy („Skopiuj …”, „Usuń …”), a wspólnego jest
     * tu wyłącznie zdanie „liczba zamiast nazwy, gdy wpisów jest wiele”, czyli
     * jedna instrukcja `if`. Wyjęcie jej do trzeciej klasy kosztowałoby więcej,
     * niż oszczędza.
     *
     * @param list<string> $names
     *
     * @return array{string, array<string, string>}
     */
    private function titleOf(string $key, array $names): array
    {
        if (count($names) === 1) {
            return [$key, ['name' => $names[0]]];
        }

        return [$key . '.many', ['count' => $this->translator->number((float) count($names))]];
    }

    /**
     * Początek pracy: ścieżka wpisana przez użytkownika staje się katalogiem
     * docelowym, a praca robi od razu pierwszy kawałek.
     *
     * Ścieżka **nie jest nazwą wpisu**, więc `EntryName` jej nie ocenia; ocenia ją
     * `DirectoryPath` (kształt) i port (istnienie, prawo zapisu, trzy drogi do
     * pętli). Wyjątek o kształcie wypuszczamy dalej: `InputHandler` złapie go
     * i **zostawi okno otwarte** wraz z tym, co użytkownik wpisał — bo po zdaniu
     * „to nie jest ścieżka katalogu” ma on dokładnie jedną rzecz do zrobienia.
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\InvalidDirectoryPathException
     */
    private function start(bool $moves, string $value): OverlayOutcome
    {
        $operands = $this->queries->operands();

        if ($operands === null) {
            return OverlayOutcome::close($this->info('module.browser.problem.noSelection'));
        }

        [$directory, $names] = $operands;
        $target = DirectoryPath::resolvedFrom($value, $directory->path());
        $sources = [];

        foreach ($names as $name) {
            $sources[] = $directory->path()->child($name)->value;
        }

        $this->moves = $moves;
        $this->source = $directory->path();
        $this->target = $target;
        $this->name = $names[0];
        $this->count = count($names);
        $this->names = $names;

        // Lista źródeł czekała na ten krok od kroku 42: port brał ją od pierwszego
        // dnia („lista, nie jeden wpis, także wtedy, gdy ma jeden element — krok 43
        // doda resztę”), więc zaznaczenie wielokrotne nie zmienia w pracy ani jednej
        // linii — wypełnia wyłącznie to, co tamten krok zostawił puste.
        return $this->launched($this->transfers->begin($sources, $target->value, $moves));
    }

    /**
     * Cofnięcie przeniesienia: ta sama praca w drugą stronę (krok 44, D81 nr 6).
     *
     * Wpisy stoją w `$from` (dokąd je przeniesiono), wracają do `$to` (skąd
     * przyszły) — wraz z liczeniem, oknem postępu i pytaniem o kolizję, bo
     * katalog źródłowy mógł w międzyczasie dostać nowe wpisy o tych nazwach.
     * Domknięcie `$onComplete` pada wyłącznie po pracy ukończonej **w całości**:
     * cofnięcie połowiczne nie zdejmuje zapisu, a zdanie mówi, ile wróciło.
     *
     * @param list<string>   $names
     * @param Closure(): void $onComplete zdjęcie zapisu ze stosu — robi wołający
     */
    public function beginRestore(DirectoryPath $from, DirectoryPath $to, array $names, Closure $onComplete): OverlayOutcome
    {
        $sources = [];

        foreach ($names as $name) {
            $sources[] = $from->child($name)->value;
        }

        $this->moves = true;
        $this->source = $from;
        $this->target = $to;
        $this->name = $names[0];
        $this->count = count($names);
        $this->names = $names;
        $this->undoing = true;
        $this->undoCompletion = $onComplete;

        return $this->launched($this->transfers->begin($sources, $to->value, true));
    }

    /** Wspólny ogon obu początków: pierwszy kawałek liczenia i wybór okna. */
    private function launched(TransferState $state): OverlayOutcome
    {
        if ($state->stage === TransferStage::Scanning) {
            $state = $this->transfers->advance(self::SCAN_PER_TICK);
        }

        if ($state->stage === TransferStage::Scanning) {
            return OverlayOutcome::replace($this->countingOverlay($state));
        }

        return $this->afterStep($state);
    }

    /**
     * Co po kawałku pracy: okno postępu, pytanie o kolizję albo zdanie o skutku.
     *
     * Jedno miejsce dla wszystkich czterech wejść (start, koniec liczenia,
     * odpowiedź na kolizję, nowa nazwa), bo odpowiedź na pytanie „co teraz” zależy
     * wyłącznie od stanu pracy — a nie od tego, kto o nią pyta.
     */
    private function afterStep(TransferState $state): OverlayOutcome
    {
        if ($state->stage === TransferStage::Working) {
            $state = $this->transfers->advance(self::BYTES_PER_TICK);
        }

        if ($state->stage === TransferStage::Working) {
            return OverlayOutcome::replace($this->workingOverlay($state));
        }

        if ($state->stage === TransferStage::Colliding) {
            return OverlayOutcome::replace($this->collisionOverlay($state));
        }

        return OverlayOutcome::close($this->finished($state));
    }

    /** Okno liczenia: sama nazwa wpisu dokładanego do listy, bez paska (nie ma z czego). */
    private function countingOverlay(TransferState $state): ProgressOverlay
    {
        [$key, $parameters] = $this->workTitle('module.browser.transfer.counting');

        return new ProgressOverlay(
            $key,
            $parameters,
            $this->progress($state),
            fn (): WorkProgress => $this->progress($this->transfers->advance(self::SCAN_PER_TICK)),
            fn (): OverlayOutcome => $this->afterStep($this->transfers->state()),
            function (): Message {
                // Przerwane liczenie nie dotknęło dysku i to jest cała treść
                // zdania: użytkownik ma wiedzieć, że nic się nie stało.
                $this->transfers->stop();

                return $this->info('module.browser.transfer.abandoned');
            },
            $this->translator,
        );
    }

    /** Okno pracy: nazwa wpisu, licznik w bajtach i pasek. */
    private function workingOverlay(TransferState $state): ProgressOverlay
    {
        [$key, $parameters] = $this->workTitle(
            $this->moves ? 'module.browser.move.progress' : 'module.browser.copy.progress',
        );

        return new ProgressOverlay(
            $key,
            $parameters,
            $this->progress($state),
            fn (): WorkProgress => $this->progress($this->transfers->advance(self::BYTES_PER_TICK)),
            fn (): OverlayOutcome => $this->afterStep($this->transfers->state()),
            fn (): Message => $this->stopped(),
            $this->translator,
        );
    }

    /**
     * Okno kolizji — sześć odpowiedzi, bo „do wszystkich” jest **inną
     * odpowiedzią**, a nie przełącznikiem przy tamtych czterech.
     *
     * `abort` stoi ostatni i to nie jest kolejność z gustu: `ChoiceOverlay` znaczy
     * `Esc` jako odpowiedź ostatnią, a wycofanie się jest jedyną, która ma prawo
     * paść przez przypadek.
     */
    private function collisionOverlay(TransferState $state): ChoiceOverlay
    {
        return new ChoiceOverlay(
            'module.browser.transfer.collision',
            ['name' => $state->current],
            [
                'overwrite' => 'module.browser.transfer.overwrite',
                'overwriteAll' => 'module.browser.transfer.overwriteAll',
                'skip' => 'module.browser.transfer.skip',
                'skipAll' => 'module.browser.transfer.skipAll',
                'rename' => 'module.browser.transfer.rename',
                'abort' => 'module.browser.transfer.abort',
            ],
            fn (string $id): OverlayOutcome => $this->answered($id, $state->current),
            $this->translator,
        );
    }

    /** Odpowiedź na kolizję: „zmień nazwę” pyta jeszcze o nią, reszta wraca do pracy. */
    private function answered(string $id, string $colliding): OverlayOutcome
    {
        $choice = self::CHOICES[$id] ?? TransferChoice::Abort;

        if ($choice !== TransferChoice::Rename) {
            return $this->afterStep($this->transfers->resolve($choice));
        }

        return OverlayOutcome::replace(new PromptOverlay(
            'module.browser.transfer.newName',
            ['name' => $colliding],
            $colliding,
            fn (string $value): OverlayOutcome => $this->afterStep(
                $this->transfers->resolve(TransferChoice::Rename, $value),
            ),
            $this->translator,
        ));
    }

    /**
     * Koniec pracy: zdanie o skutku, odświeżenie paneli i zapomnienie stanu.
     *
     * Praca przerwana jest pracą zakończoną (D66), więc idzie tędy razem
     * z pomyślną — różni je zdanie, a nie droga.
     */
    private function finished(TransferState $state): Message
    {
        $full = $state->stage !== TransferStage::Failed && !$state->wasStoppedEarly();

        $message = match (true) {
            $state->stage === TransferStage::Failed => $this->reason($state),
            $state->wasStoppedEarly() => Message::info($this->translator->plural(
                $this->moves ? 'module.browser.move.stopped' : 'module.browser.copy.stopped',
                $state->doneEntries,
                ['total' => $this->translator->number((float) $state->totalEntries)],
            )),
            $this->undoing => Message::info($this->translator->plural(
                'module.browser.undo.done.move',
                $state->doneEntries,
            )),
            default => Message::info($this->translator->plural(
                $this->moves ? 'module.browser.move.done' : 'module.browser.copy.done',
                $state->doneEntries,
            )),
        };

        // Trzy czynności wychodzą stąd jedną drogą, więc i zdarzenie dobiera się
        // tu, a nie w trzech miejscach (krok 46). Kolejność pytań jest ta sama, co
        // w zdaniu powyżej: cofnięcie przeniesienia jest **cofnięciem**, a nie
        // przeniesieniem, choć pod spodem robi dokładnie to samo.
        [$done, $failed] = match (true) {
            $this->undoing => [BrowserEvent::UndoDone, BrowserEvent::UndoFailed],
            $this->moves => [BrowserEvent::MoveDone, BrowserEvent::MoveFailed],
            default => [BrowserEvent::CopyDone, BrowserEvent::CopyFailed],
        };

        $this->record($full);
        $this->transfers->stop();
        $this->refreshPanes();
        $this->events->outcome($done, $failed, $message);

        return $message;
    }

    /**
     * Zapis w stosie cofnięć (krok 44) — wyłącznie po pracy ukończonej
     * **w całości**. Praca przerwana zostawiła część wpisów tu, część tam,
     * a zapis, który obiecuje cofnięcie połowy, kłamałby w drugiej połowie.
     * Kopiowanie zapisuje się jako **nieodwracalne** — jego cofnięciem byłoby
     * usunięcie kopii — a cofnięcie przeniesienia melduje się domknięciem
     * i samo się nie zapisuje (`redo` jest poza zakresem).
     */
    private function record(bool $full): void
    {
        if (!$full || $this->source === null || $this->target === null || $this->names === []) {
            return;
        }

        if ($this->undoing) {
            if ($this->undoCompletion !== null) {
                ($this->undoCompletion)();
            }

            return;
        }

        $this->journal->record($this->moves
            ? UndoEntry::moved($this->source->value, $this->target->value, $this->names)
            : UndoEntry::copied($this->target->value, $this->names));
    }

    /** `Esc` w trakcie pracy: staje na najbliższym kawałku i mówi, ile zdążyło. */
    private function stopped(): Message
    {
        return $this->finished($this->transfers->state());
    }

    /**
     * Odświeżenie po pracy: kopiowanie zmienia **jeden** katalog, przeniesienie
     * dwa. Kursor idzie za wpisem, czyli do celu.
     */
    private function refreshPanes(): void
    {
        $target = $this->target;
        $source = $this->source;
        $name = $this->name;

        $this->target = null;
        $this->source = null;
        $this->name = '';
        $this->count = 1;
        $this->names = [];
        $this->undoing = false;
        $this->undoCompletion = null;

        if ($target === null) {
            return;
        }

        if ($source === null || !$this->moves) {
            $this->refresh->after($target, $name);

            return;
        }

        $this->refresh->afterBoth($source, $target, $name);
    }

    /**
     * Stan pracy przełożony na język okna postępu — wraz z **gotowym licznikiem**
     * (D79, rozstrzygnięcie 9).
     *
     * Przekład stoi tutaj, a nie w `TransferState`, bo licznik niesie jednostki
     * i separator dziesiętny, czyli rzeczy z katalogu napisów; rozmiar zapisuje
     * `EntrySize`, ten sam, którym panel opisuje wpisy na liście.
     */
    private function progress(TransferState $state): WorkProgress
    {
        return new WorkProgress(
            $state->isRunning(),
            $state->current,
            $state->doneBytes,
            $state->totalBytes,
            $this->counter($state),
        );
    }

    /**
     * Licznik: rozmiar zawsze, „który wpis z ilu” **tylko przy wielu wpisach**.
     *
     * Drugą połowę odciął widok w prawdziwym terminalu: przy drzewie „5/20”
     * mówi dokładnie to, czego brakuje bajtom, ale przy jednym pliku „0/1”
     * czyta się jak usterka — przez całą minutę kopiowania stoi na zerze,
     * bo plik jest jeden i skończy się dopiero na końcu.
     */
    private function counter(TransferState $state): string
    {
        if ($state->totalBytes === null) {
            // Przy liczeniu paska nie ma, więc i licznika nie ma czym wypełnić.
            return '';
        }

        $size = [
            'done' => EntrySize::of($this->translator, $state->doneBytes),
            'total' => EntrySize::of($this->translator, $state->totalBytes),
        ];

        if ($state->totalEntries < 2) {
            return $this->translator->translate('module.browser.transfer.counter.size', $size);
        }

        return $this->translator->translate('module.browser.transfer.counter', [
            ...$size,
            'entry' => $this->translator->number((float) $state->doneEntries),
            'entries' => $this->translator->number((float) $state->totalEntries),
        ]);
    }

    /**
     * Tytuł okna **trwającej** pracy — z pamięci pracy, a nie z panelu.
     *
     * Osobno od `titleOf()`, bo tamten pyta o zbiór panelu **przed** rozpoczęciem
     * pracy, a ten mówi o pracy, która już trwa: panel wolno w jej trakcie
     * przewinąć, a nawet zmienić mu katalog komendą, i okno postępu ma dalej
     * mówić o tym, co naprawdę się kopiuje.
     *
     * @return array{string, array<string, string>}
     */
    private function workTitle(string $key): array
    {
        if ($this->count === 1) {
            return [$key, ['name' => $this->name]];
        }

        return [$key . '.many', ['count' => $this->translator->number((float) $this->count)]];
    }

    /** Katalog panelu **bez ogniska** — cel, którego użytkownik spodziewa się w oknie. */
    private function otherDirectory(): DirectoryPath
    {
        [$first, $second] = $this->panes->all();

        return ($this->queries->focusesSecond() ? $first : $second)->directory()->path();
    }

    /**
     * Skutek okna przełożony na skutek ekranu — jedno miejsce dla obu wejść,
     * wzorem `EntryOperations`.
     */
    private static function forScreen(OverlayOutcome $outcome): ScreenOutcome
    {
        return $outcome->next === null
            ? ScreenOutcome::stay($outcome->message)
            : ScreenOutcome::opens($outcome->next);
    }

    /** @param array<string, string> $parameters */
    private function info(string $key, array $parameters = []): Message
    {
        return Message::info($this->translator->translate($key, $parameters));
    }

    private function reason(TransferState $state): Message
    {
        return Message::error($this->translator->translate(
            $state->problemKey ?? 'problem.unexpected',
            $state->problemParameters,
        ));
    }
}
