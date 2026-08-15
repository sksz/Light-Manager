<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Dto\RemovalStage;
use LightManager\Application\Dto\RemovalState;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\EntryName;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Trzy czynności zmieniające dysk: zmiana nazwy, nowy katalog, usunięcie
 * (krok 41).
 *
 * Klasa istnieje z tego samego powodu, co `HiddenEntries` w kroku 32: **czynność
 * o dwóch wejściach mieszka w jednym miejscu**. Zmianę nazwy i nowy katalog
 * wywołuje klawisz (`F6`, `F7`) i komenda (`browser.rename`, `browser.mkdir`),
 * a dwie implementacje rozjechałyby się przy pierwszej poprawce — tym razem
 * z ceną wyższą niż rozjechany komunikat, bo po drugiej stronie jest `unlink()`.
 *
 * Leży w warstwie `Presentation` modułu, bo składa okna nakładane i zna
 * `LoopState` — ta sama zasada, która postawiła tu komendy modułu (D41).
 *
 * **Trzy różne drogi niepowodzenia** i każda ma powód:
 *
 * 1. Zmiana nazwy i nowy katalog **wypuszczają wyjątek** — `InputHandler` łapie
 *    go i zostawia okno nazwy otwarte wraz z tym, co użytkownik wpisał, bo po
 *    zdaniu „nazwa jest już zajęta” ma on dokładnie jedną rzecz do zrobienia:
 *    poprawić nazwę.
 * 2. Usunięcie pojedynczego wpisu **łapie wyjątek i oddaje zdanie** — pytanie
 *    zamyka się, bo w oknie potwierdzenia nie ma czego poprawiać.
 * 3. Usuwanie rekurencyjne **niepowodzenia nie rzuca wcale**: jest pracą
 *    kawałkową, a powód niesie w swoim stanie (`RemovalState`).
 */
final class EntryOperations
{
    /**
     * Ile wpisów policzyć w jednym takcie.
     *
     * Liczby wzięte z budżetu klatki (33 ms przy 30 kl./s), nie z okrągłości:
     * `scandir()` jest tani, więc liczenie bierze więcej, a `unlink()` bywa
     * kilkudziesięciomikrosekundowy, więc usuwanie mniej — 256 wywołań to
     * kilka milisekund, czyli ułamek taktu.
     */
    private const SCAN_PER_TICK = 512;

    private const DELETE_PER_TICK = 256;

    /**
     * Nazwa, na której ma stanąć kursor po skończonym usuwaniu — policzona
     * **przed** czynnością, bo potem tego wpisu już nie ma.
     *
     * Pole, a nie parametr wleczony przez cztery metody: praca jest jedna naraz
     * (reguła 11d), więc jest to stan tej jednej pracy — jak `successor` w liście,
     * na którego kursor ma spaść.
     */
    private ?string $successor = null;

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly FileOperationsPort $operations,
        private readonly PaneRefresh $refreshPanes,
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** `F6` — okno z nazwą bieżącą jako treścią początkową. */
    public function renamePrompt(): ScreenOutcome
    {
        return self::forScreen($this->renameRequest());
    }

    /** To samo dla komendy `browser.rename` wywołanej bez nazwy (krok 47, D78). */
    public function renameRequest(): OverlayOutcome
    {
        $selection = $this->selection();

        if ($selection === null) {
            return OverlayOutcome::close($this->info('module.browser.problem.noSelection'));
        }

        [, $entry] = $selection;

        return OverlayOutcome::replace(new PromptOverlay(
            'module.browser.rename.title',
            ['name' => $entry->name],
            $entry->name,
            fn (string $value): OverlayOutcome => OverlayOutcome::close($this->rename($value)),
            $this->translator,
        ));
    }

    /**
     * `F7` — okno z pustą treścią.
     *
     * Katalog powstaje w **katalogu panelu**, także wtedy, gdy panel pokazuje
     * drzewo i kursor stoi kilka poziomów niżej: „nowy katalog” dotyczy miejsca,
     * na które użytkownik patrzy jako na swoje bieżące, a nie węzła pod kursorem.
     *
     * Ścieżki w tytule **nie ma** i to jest poprawka po obejrzeniu okna
     * w prawdziwym terminalu: katalog panelu czynnego stoi w górnym pasie klatki,
     * więc tytuł powtarzałby to, co widać — a przy długiej ścieżce rozdmuchiwał
     * okno na całą szerokość.
     */
    public function directoryPrompt(): ScreenOutcome
    {
        return self::forScreen($this->directoryRequest());
    }

    /** To samo dla komendy `browser.mkdir` wywołanej bez nazwy (krok 47, D78). */
    public function directoryRequest(): OverlayOutcome
    {
        return OverlayOutcome::replace(new PromptOverlay(
            'module.browser.mkdir.title',
            [],
            '',
            fn (string $value): OverlayOutcome => OverlayOutcome::close($this->createDirectory($value)),
            $this->translator,
        ));
    }

    /**
     * Zmiana nazwy wpisu pod kursorem — wejście dla klawisza i dla komendy.
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\InvalidEntryNameException
     * @throws FileOperationException
     * @throws DirectoryNotReadableException
     */
    public function rename(string $value): ?Message
    {
        $selection = $this->selection();

        if ($selection === null) {
            return $this->info('module.browser.problem.noSelection');
        }

        [$directory, $entry] = $selection;
        $name = new EntryName($value);

        // Nazwa ta sama co dotąd nie jest błędem, tylko brakiem zmiany: `rename()`
        // na siebie samego udałby się i zostawiłby po sobie zdanie o czynności,
        // której nie było.
        if ($name->value === $entry->name) {
            return null;
        }

        $this->operations->rename($directory->path()->child($entry->name)->value, $name->value);
        $this->refresh($directory->path(), $name->value);

        return $this->info('module.browser.rename.done', ['name' => $name->value]);
    }

    /**
     * Nowy katalog w katalogu panelu czynnego — wejście dla klawisza i dla komendy.
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\InvalidEntryNameException
     * @throws FileOperationException
     * @throws DirectoryNotReadableException
     */
    public function createDirectory(string $value): Message
    {
        $name = new EntryName($value);
        $path = $this->panes->focused()->directory()->path();

        $this->operations->createDirectory($path->child($name->value)->value);
        $this->refresh($path, $name->value);

        return $this->info('module.browser.mkdir.done', ['name' => $name->value]);
    }

    /**
     * `F8` albo `Delete` — usunięcie **zaznaczonych wpisów**, a gdy zbiór jest
     * pusty, wpisu pod kursorem (krok 43).
     *
     * Pojedynczy plik idzie **krótszą drogą**: pytanie i jedno wywołanie. Katalog
     * i każdy zbiór wymagają policzenia zawartości, bo pytanie ma podać liczbę
     * wpisów, które znikną — a policzenie bywa dłuższe od klatki (D75,
     * rozstrzygnięcia 4 i 10).
     */
    public function deletePrompt(?string $name = null): ScreenOutcome
    {
        return self::forScreen($this->deleteRequest($name));
    }

    /**
     * To samo dla komendy `browser.delete [nazwa]` (krok 47, D78).
     *
     * Nazwa jest **opcjonalna**: bez niej idzie zbiór zaznaczonych albo wpis pod
     * kursorem, z nią — wskazany wpis, po sprawdzeniu, że w katalogu panelu
     * w ogóle jest. Sprawdzenie należy tutaj, a nie do komendy: to jest jedyne
     * miejsce, które wie, na co patrzy panel czynny.
     *
     * **Nazwa podana wprost wyprzedza zaznaczenie** i nie jest to niedopatrzenie:
     * `browser.delete raport.pdf` mówi, co usunąć, więc zbiór nie ma tu nic do
     * powiedzenia — inaczej komenda z argumentem robiłaby co innego, niż w niej
     * napisano.
     */
    public function deleteRequest(?string $name = null): OverlayOutcome
    {
        if ($name !== null) {
            $target = $this->entryNamed($name);

            return $target === null
                ? OverlayOutcome::close(Message::error($this->translator->translate(
                    'module.browser.problem.noEntry',
                    ['name' => $name],
                )))
                : $this->deleting($target[0], [$name]);
        }

        $operands = $this->panes->focusedOperands();

        if ($operands === null) {
            return OverlayOutcome::close($this->info('module.browser.problem.noSelection'));
        }

        [$directory, $names] = $operands;

        return $this->deleting($directory, $names);
    }

    /**
     * Wybór drogi: jeden plik znika krótszą, wszystko inne — pracą kawałkową.
     *
     * Warunek krótkiej drogi zwęził się w kroku 43 o jedno słowo („jeden”) i to
     * jest cała zmiana, jakiej wymagał zbiór: dwanaście plików bez katalogu dałoby
     * się wprawdzie usunąć dwunastoma wywołaniami w jednej klatce, ale wtedy
     * pytanie musiałoby podać liczbę policzoną osobno, a przerwanie w połowie nie
     * miałoby gdzie się zameldować.
     *
     * @param list<string> $names nazwy wpisów w tym katalogu; nigdy pusta
     */
    private function deleting(Directory $directory, array $names): OverlayOutcome
    {
        $this->successor = self::successorOf($directory, $names);
        $single = count($names) === 1 ? self::entryIn($directory, $names[0]) : null;

        if ($single !== null && !$single->isDirectory()) {
            if (!$this->asks()) {
                return OverlayOutcome::close($this->deleteOne($directory, $single->name));
            }

            return OverlayOutcome::replace(new ConfirmOverlay(
                'module.browser.delete.confirm.file',
                ['name' => $single->name],
                fn (): OverlayOutcome => OverlayOutcome::close($this->deleteOne($directory, $single->name)),
                $this->translator,
                true,
            ));
        }

        return $this->counted($directory, $names);
    }

    /**
     * Liczenie zawartości katalogu — z oknem albo bez.
     *
     * Pierwszy kawałek idzie **od razu**: katalog o kilku wpisach policzy się
     * w całości, a wtedy okno liczenia nie ma czego pokazać i nie powstaje wcale.
     * Reguła wynika z rozstrzygnięcia nr 10 („plik i pusty katalog bez okien
     * pracy”) rozciągniętego na przypadek, którego z góry rozpoznać nie sposób.
     */
    /**
     * @param list<string> $names
     */
    private function counted(Directory $directory, array $names): OverlayOutcome
    {
        $state = $this->operations->beginRemoval($this->pathsIn($directory, $names));

        if ($state->stage === RemovalStage::Scanning) {
            $state = $this->operations->advanceRemoval(self::SCAN_PER_TICK);
        }

        if ($state->stage === RemovalStage::Scanning) {
            return OverlayOutcome::replace($this->countingOverlay($directory, $names, $state));
        }

        [$overlay, $message] = $this->afterCounting($directory, $names, $state);

        return $overlay === null
            ? OverlayOutcome::close($message)
            : OverlayOutcome::replace($overlay, $message);
    }

    /**
     * Okno liczenia: nazwa wpisu (albo ich liczba) dokładana do listy, bez paska
     * — nie ma jeszcze z czego.
     *
     * @param list<string> $names
     */
    private function countingOverlay(Directory $directory, array $names, RemovalState $state): ProgressOverlay
    {
        [$key, $parameters] = $this->titleOf('module.browser.delete.counting', $names);

        return new ProgressOverlay(
            $key,
            $parameters,
            $state->progress(),
            fn (): WorkProgress => $this->operations->advanceRemoval(self::SCAN_PER_TICK)->progress(),
            function () use ($directory, $names): OverlayOutcome {
                [$overlay, $message] = $this->afterCounting($directory, $names, $this->operations->removalState());

                return $overlay === null
                    ? OverlayOutcome::close($message)
                    : OverlayOutcome::replace($overlay, $message);
            },
            function (): Message {
                // Przerwane liczenie nie dotknęło dysku i to jest cała treść
                // zdania: użytkownik ma wiedzieć, że nic się nie stało.
                $this->operations->stopRemoval();

                return $this->info('module.browser.delete.abandoned');
            },
            $this->translator,
        );
    }

    /**
     * Tytuł okna pracy: nazwa wpisu przy jednym, ich liczba przy zbiorze.
     *
     * Przyrostek `.many` zamiast dopisywania liczby do tego samego napisu, bo
     * „Usuwanie »raport.pdf«” i „Usuwanie 12 wpisów” to dwa różne zdania, a nie
     * jedno z parametrem — i drugie z nich odmienia się przez przypadki.
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

        return [
            $key . '.many',
            ['count' => $this->translator->number((float) count($names))],
        ];
    }

    /**
     * Ścieżki bezwzględne wpisów — jedyne, co port o nich wie (reguła 15b).
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function pathsIn(Directory $directory, array $names): array
    {
        $paths = [];

        foreach ($names as $name) {
            $paths[] = $directory->path()->child($name)->value;
        }

        return $paths;
    }

    /**
     * Co po policzeniu: pytanie z liczbą, od razu usuwanie (gdy pytać nie trzeba)
     * albo zdanie o niepowodzeniu.
     *
     * @param list<string> $names
     *
     * @return array{?OverlayInterface, ?Message} okno, które ma stanąć, albo samo zdanie
     */
    private function afterCounting(Directory $directory, array $names, RemovalState $state): array
    {
        if ($state->stage !== RemovalStage::Ready) {
            $failure = $state->stage === RemovalStage::Failed ? $this->reason($state) : null;
            $this->operations->stopRemoval();

            return [null, $failure];
        }

        if (!$this->asks()) {
            return $this->started($directory, $names);
        }

        [$key, $parameters, $count] = $this->question($names, $state->total ?? 1);

        return [new ConfirmOverlay(
            $key,
            $parameters,
            function () use ($directory, $names): OverlayOutcome {
                [$overlay, $message] = $this->started($directory, $names);

                return $overlay === null
                    ? OverlayOutcome::close($message)
                    : OverlayOutcome::replace($overlay, $message);
            },
            $this->translator,
            true,
            function (): void {
                // Odmowa porzuca policzoną listę — przy drzewie o stu tysiącach
                // wpisów jest to megabajt pamięci trzymany bez powodu.
                $this->operations->stopRemoval();
            },
            $count,
        ), null];
    }

    /**
     * Pytanie przed usunięciem — **liczbą, gdy wpisów jest wiele** (krok 43).
     *
     * Trzy zdania i każde odpowiada na inne pytanie użytkownika. Jeden wpis: „co
     * znika” (nazwa). Jeden katalog z zawartością: „co znika i ile tego jest”.
     * Zbiór: „**ile** znika” — nazwa pierwszego z dwunastu nie mówi o tamtych
     * jedenastu nic, a użytkownik i tak patrzy na listę, na której widzi
     * znaczniki.
     *
     * Liczba wpisów do usunięcia (`{total}`) jest **większa** od liczby
     * zaznaczonych, gdy w zbiorze są katalogi — i wtedy właśnie zdanie ją podaje.
     * Przy samych plikach obie liczby są równe, więc drugą pomijamy: „usunąć 12
     * wpisów, do usunięcia 12” czyta się jak usterka.
     *
     * @param list<string> $names
     *
     * @return array{string, array<string, string>, ?int} klucz, parametry i liczba
     *                                                    dla form mnogich
     */
    private function question(array $names, int $total): array
    {
        $count = count($names);

        if ($count === 1) {
            return [
                // Jeden wpis do usunięcia znaczy „sam katalog” — pusty albo
                // dowiązanie. Pytanie o zawartość byłoby wtedy pytaniem o coś,
                // czego nie ma.
                $total > 1 ? 'module.browser.delete.confirm.tree' : 'module.browser.delete.confirm.file',
                ['name' => $names[0], 'count' => $this->translator->number((float) $total)],
                null,
            ];
        }

        if ($total > $count) {
            return [
                'module.browser.delete.confirm.manyTrees',
                [
                    'count' => $this->translator->number((float) $count),
                    'total' => $this->translator->number((float) $total),
                ],
                $count,
            ];
        }

        return [
            'module.browser.delete.confirm.many',
            ['count' => $this->translator->number((float) $count)],
            $count,
        ];
    }

    /**
     * Zgoda: praca przechodzi do usuwania — z oknem postępu albo bez, tą samą
     * regułą pierwszego kawałka, co liczenie.
     *
     * @param list<string> $names
     *
     * @return array{?OverlayInterface, ?Message}
     */
    private function started(Directory $directory, array $names): array
    {
        $state = $this->operations->confirmRemoval();

        if ($state->stage === RemovalStage::Deleting) {
            $state = $this->operations->advanceRemoval(self::DELETE_PER_TICK);
        }

        if ($state->stage === RemovalStage::Deleting) {
            return [$this->deletingOverlay($directory, $names, $state), null];
        }

        return [null, $this->finished($directory, $state)];
    }

    /**
     * Okno usuwania: nazwa (albo liczba wpisów), licznik „N z M” w pasku i sam
     * pasek.
     *
     * @param list<string> $names
     */
    private function deletingOverlay(Directory $directory, array $names, RemovalState $state): ProgressOverlay
    {
        [$key, $parameters] = $this->titleOf('module.browser.delete.deleting', $names);

        return new ProgressOverlay(
            $key,
            $parameters,
            $state->progress(),
            fn (): WorkProgress => $this->operations->advanceRemoval(self::DELETE_PER_TICK)->progress(),
            fn (): OverlayOutcome => OverlayOutcome::close(
                $this->finished($directory, $this->operations->removalState()),
            ),
            fn (): Message => $this->stopped($directory),
            $this->translator,
        );
    }

    /** Koniec pracy: zdanie o skutku i odświeżenie paneli. */
    private function finished(Directory $directory, RemovalState $state): Message
    {
        $message = $state->stage === RemovalStage::Failed
            ? $this->reason($state)
            : Message::info($this->translator->plural('module.browser.delete.done', $state->done));

        $this->operations->stopRemoval();
        $this->refreshAfterRemoval($directory);

        return $message;
    }

    /**
     * `Esc` w trakcie usuwania: praca staje na najbliższym kawałku, a zdanie mówi
     * **ile z ilu** zniknęło.
     *
     * To jest jedyne miejsce, w którym cel kroku („operacja dzieje się w całości
     * albo wcale”) ma zapisany wyjątek — usunięcia połowy drzewa nie da się
     * cofnąć, więc pozostaje powiedzieć prawdę (D75, rozstrzygnięcie 13).
     */
    private function stopped(Directory $directory): Message
    {
        $state = $this->operations->removalState();
        $this->operations->stopRemoval();
        $this->refreshAfterRemoval($directory);

        return Message::info($this->translator->plural(
            'module.browser.delete.stopped',
            $state->done,
            ['total' => $this->translator->number((float) ($state->total ?? 0))],
        ));
    }

    private function refreshAfterRemoval(Directory $directory): void
    {
        $successor = $this->successor;
        $this->successor = null;

        $this->refresh($directory->path(), $successor);
    }

    /** Usunięcie jednego wpisu — pliku, dowiązania albo pustego katalogu. */
    private function deleteOne(Directory $directory, string $name): Message
    {
        $successor = $this->successor;
        $this->successor = null;

        try {
            $this->operations->delete($directory->path()->child($name)->value);
        } catch (FileOperationException $problem) {
            return $this->problem($problem);
        }

        $this->refresh($directory->path(), $successor);

        return $this->info('module.browser.delete.doneOne', ['name' => $name]);
    }

    /**
     * Odświeżenie po zmianie na dysku — **obu paneli**, jeśli oba jej dotyczą.
     *
     * Rachunek wyprowadził się w kroku 42 do `PaneRefresh`, bo doszedł drugi
     * wołający (kopiowanie), a ten zmienia dwa katalogi naraz.
     */
    private function refresh(DirectoryPath $changed, ?string $select): void
    {
        $this->refreshPanes->after($changed, $select);
    }

    /**
     * Wpis, na który ma spaść kursor po usunięciu wskazanych: pierwszy **nieusuwany**
     * poniżej ostatniego z nich, a gdy takiego nie ma — pierwszy nieusuwany powyżej.
     * `null` znaczy „nie zostanie nic”.
     *
     * Przy jednym wpisie rachunek daje dokładnie to, co przed krokiem 43 — następny
     * na liście albo poprzedni. Przy zbiorze zmienia się jedno: kursor **przeskakuje
     * resztę zaznaczonych**, bo one też znikną, a kursor postawiony na wpisie,
     * którego za chwilę nie będzie, i tak spadłby na początek listy.
     *
     * @param list<string> $names
     */
    private static function successorOf(Directory $directory, array $names): ?string
    {
        $removed = array_fill_keys($names, true);
        $entries = $directory->entries();
        $last = null;

        foreach ($entries as $index => $entry) {
            if (isset($removed[$entry->name])) {
                $last = $index;
            }
        }

        if ($last === null) {
            return null;
        }

        for ($index = $last + 1; $index < count($entries); ++$index) {
            if (!isset($removed[$entries[$index]->name])) {
                return $entries[$index]->name;
            }
        }

        for ($index = $last - 1; $index >= 0; --$index) {
            if (!isset($removed[$entries[$index]->name])) {
                return $entries[$index]->name;
            }
        }

        return null;
    }

    /** Wpis o tej nazwie w tym katalogu albo `null`, gdy go tam nie ma. */
    private static function entryIn(Directory $directory, string $name): ?Entry
    {
        foreach ($directory->entries() as $entry) {
            if ($entry->name === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Katalog i wpis, na który wskazuje panel czynny — z listy albo z drzewa.
     *
     * @return ?array{Directory, Entry}
     */
    private function selection(): ?array
    {
        return $this->panes->focusedSelection();
    }

    /**
     * Katalog i wpis o podanej nazwie — dla komendy z argumentem (krok 47).
     *
     * Szuka **wyłącznie w katalogu panelu czynnego**: nazwa jest nazwą, nie
     * ścieżką, tą samą regułą, którą kieruje się `EntryName` przy tworzeniu
     * i zmianie nazwy. Wpisu ukrytego przed listą nie znajdzie i tak ma być —
     * usunąć wolno to, co widać.
     *
     * @return ?array{Directory, Entry}
     */
    private function entryNamed(string $name): ?array
    {
        $directory = $this->panes->focusedDirectory();

        foreach ($directory->entries() as $entry) {
            if ($entry->name === $name) {
                return [$directory, $entry];
            }
        }

        return null;
    }

    /**
     * Skutek okna przełożony na skutek ekranu — jedno miejsce dla obu wejść.
     *
     * `EntryOperations` istnieje po to, żeby klawisz i komenda prowadziły w to
     * samo (wzorzec `HiddenEntries`), więc i przekład typów należy tutaj, a nie
     * do trzech komend osobno. Okno wchodzi jako `replace()`, bo dla komendy
     * znaczy „ustąp mi miejsca”; ekran żadnego okna nie zamyka, więc `closes`
     * jest przy tłumaczeniu bez znaczenia.
     */
    private static function forScreen(OverlayOutcome $outcome): ScreenOutcome
    {
        return $outcome->next === null
            ? ScreenOutcome::stay($outcome->message)
            : ScreenOutcome::opens($outcome->next);
    }

    private function asks(): bool
    {
        return BrowserSettings::asksBeforeDelete($this->state->settings());
    }

    /** @param array<string, string> $parameters */
    private function info(string $key, array $parameters = []): Message
    {
        return Message::info($this->translator->translate($key, $parameters));
    }

    private function reason(RemovalState $state): Message
    {
        return Message::error($this->translator->translate(
            $state->problemKey ?? 'problem.unexpected',
            $state->problemParameters,
        ));
    }

    /** Zdanie, które wyjątek podaje o sobie sam (`DescribesProblem`, D42). */
    private function problem(DescribesProblem $problem): Message
    {
        return Message::error($this->translator->translate(
            $problem->problemKey(),
            $problem->problemParameters(),
        ));
    }
}
