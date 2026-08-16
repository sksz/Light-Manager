<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\Ssh\Application\Port\RemoteTransferPort;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\RemoteTransferItem;
use LightManager\Module\Ssh\Application\RemoteTransferProgress;
use LightManager\Module\Ssh\Application\RemoteTransferStage;
use LightManager\Module\Ssh\Application\RemoteTransferState;
use LightManager\Module\Ssh\Application\SshEvent;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\Exception\InvalidRemotePathException;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;
use LightManager\Module\Ssh\Presentation\Component\RemoteSize;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Dwie czynności dłuższe od klatki: pobranie i wysłanie (krok 50).
 *
 * Klasa jest bliźniakiem `EntryTransfer` z przeglądarki i istnieje z tego samego
 * powodu (11n): **czynność o dwóch wejściach mieszka w jednym miejscu**.
 * Pobranie wywołuje `F5` i komenda `ssh.get`, wysłanie — `F6` i `ssh.put`.
 *
 * Łańcuch okien jest ten sam, co przy kopiowaniu lokalnym, **bez ogniwa
 * liczenia**: rozmiary przychodzą razem z listą, więc pasek ma mianownik od
 * pierwszej klatki.
 *
 * ```
 * ścieżka celu → postęp ⇄ [kolizja → [nowa nazwa]] → zdanie w pasku
 * ```
 *
 * **Druga strona przesyłu bierze się z kontekstu sesji, nie z cudzego modułu**
 * (reguła 15). Przy pobieraniu cel podpowiada `LocalPlace` — zatrzask ostatniego
 * kontekstu z tej maszyny — a przy wysyłaniu stamtąd pochodzi **źródło**, i to
 * jest cała odpowiedź na pytanie „skąd moduł zna drugą stronę”. Dokładnie po to
 * kontekst istnieje (D40 P5).
 *
 * **Kolizję rozstrzyga strona, która wie za darmo.** Przy pobieraniu pyta się
 * dysku, przy wysyłaniu — listy, którą panel ma na ekranie. Gdy użytkownik wpisze
 * w oknie **inny** katalog zdalny niż otwarty, lista przestaje o nim cokolwiek
 * wiedzieć i pytanie nie pada; przed cichym nadpisaniem broni wtedy `rename -l`
 * po stronie serwera, który na zajętej nazwie po prostu odmawia.
 */
final class RemoteTransfer
{
    /** Odpowiedzi na kolizję — identyfikator okna wyboru na słownik rdzenia. */
    private const CHOICES = [
        'overwrite' => TransferChoice::Overwrite,
        'overwriteAll' => TransferChoice::OverwriteAll,
        'skip' => TransferChoice::Skip,
        'skipAll' => TransferChoice::SkipAll,
        'rename' => TransferChoice::Rename,
        'abort' => TransferChoice::Abort,
    ];

    /**
     * Czym jest prowadzona praca — pamięć na czas jej trwania.
     *
     * Pole, a nie parametr wleczony przez sześć metod: praca jest jedna naraz
     * (11d), więc jest to stan tej jednej pracy — tak samo, jak w `EntryTransfer`.
     */
    private TransferDirection $direction = TransferDirection::Download;

    private string $name = '';

    /**
     * @param RemoteBrowser $browser **wyłącznie do czynności** — odświeżenia listy po
     *                               udanym przesyle i odczytu agregatu przy kolizji nazw;
     *                               wszystko, co czytamy, idzie przez `$reader`
     */
    public function __construct(
        private readonly RemoteBrowser $browser,
        private readonly RemoteTransferPort $transfers,
        private readonly LocalPlace $local,
        private readonly TranslatorPort $translator,
        private readonly EventRegistry $events,
        private readonly SshQueries $reader,
    ) {
    }

    /**
     * Stan prowadzonej pracy wraz z jej kierunkiem — **źródło kwerendy
     * `ssh.transfer`** (krok 54).
     *
     * Kierunek mieszka tutaj, a nie w `RemoteTransferState`, i to nie zmieniło
     * się wraz z kwerendą: stan opisuje **postęp**, a postęp pobierania i wysyłania
     * liczy się tak samo. Dopisanie kierunku do stanu kazałoby go przewlec przez
     * osiem konstruktorów statycznych po to, żeby jedno miejsce mogło go
     * przeczytać.
     */
    public function snapshot(): RemoteTransferProgress
    {
        return new RemoteTransferProgress($this->transfers->state(), $this->direction, $this->name);
    }

    /** `F5` — okno ze ścieżką celu, wypełnione katalogiem, w którym stoi przeglądarka. */
    public function downloadPrompt(): ScreenOutcome
    {
        return self::forScreen($this->downloadRequest());
    }

    /** `F6` — to samo dla wysłania, z katalogiem zdalnym jako celem. */
    public function uploadPrompt(): ScreenOutcome
    {
        return self::forScreen($this->uploadRequest());
    }

    /**
     * Czy przesył ma dziś o czym mówić — pyta o to **menu**, nie ekran.
     *
     * Warunek jest jeden i wspólny dla obu kierunków: **stoi sesja i widać
     * zdalny katalog**. Bez tego pozycja w menu obiecywałaby czynność, która
     * skończy się zdaniem o braku połączenia — a menu pokazuje to, co da się
     * zrobić tu i teraz (11n). O kierunku rozstrzyga pochodzenie zaznaczenia
     * i robią to same komendy.
     */
    public function canTransfer(): bool
    {
        return $this->reader->host() !== null && $this->reader->hasListing();
    }

    /** Komenda `ssh.get [ścieżka]`: bez ścieżki pyta oknem, ze ścieżką rusza od razu. */
    public function downloadRequest(?string $path = null): OverlayOutcome
    {
        $entry = $this->reader->selected();
        $directory = $this->reader->path();

        if ($this->reader->host() === null) {
            return OverlayOutcome::close($this->problem('transfer.noSession'));
        }

        if ($entry === null || $directory === null) {
            return OverlayOutcome::close($this->problem('remote.noSelection'));
        }

        if ($entry->isDirectory()) {
            return OverlayOutcome::close($this->problem('transfer.onlyFiles'));
        }

        if ($path !== null) {
            return $this->startDownload($path);
        }

        return OverlayOutcome::replace(new PromptOverlay(
            $this->key('transfer.download.title'),
            ['name' => $entry->name],
            $this->local->path() ?? (getcwd() ?: RemotePath::ROOT),
            fn (string $value): OverlayOutcome => $this->startDownload($value),
            $this->translator,
            'prompt.path',
        ));
    }

    /** Komenda `ssh.put [ścieżka]`. */
    public function uploadRequest(?string $path = null): OverlayOutcome
    {
        $source = $this->local->filePath();
        $directory = $this->reader->path();

        if ($this->reader->host() === null) {
            return OverlayOutcome::close($this->problem('transfer.noSession'));
        }

        if ($source === null) {
            return OverlayOutcome::close($this->problem('transfer.noLocal'));
        }

        if ($path !== null) {
            return $this->startUpload($path);
        }

        return OverlayOutcome::replace(new PromptOverlay(
            $this->key('transfer.upload.title'),
            ['name' => $this->local->fileName() ?? ''],
            $directory->value ?? RemotePath::ROOT,
            fn (string $value): OverlayOutcome => $this->startUpload($value),
            $this->translator,
            'prompt.path',
        ));
    }

    /**
     * Początek pobierania: wpis pod kursorem staje się źródłem, wpisana ścieżka
     * — katalogiem docelowym na tej maszynie.
     */
    private function startDownload(string $target): OverlayOutcome
    {
        $entry = $this->reader->selected();
        $directory = $this->reader->path();
        $host = $this->reader->host();

        if ($entry === null || $directory === null || $host === null) {
            return OverlayOutcome::close($this->problem('remote.noSelection'));
        }

        $this->direction = TransferDirection::Download;
        $this->name = $entry->name;

        return $this->afterStep($this->transfers->begin(
            $host,
            [new RemoteTransferItem($directory->child($entry->name)->value, $entry->name, $entry->sizeInBytes ?? 0)],
            $target,
            TransferDirection::Download,
        ));
    }

    /**
     * Początek wysyłania: źródłem jest plik zaznaczony w przeglądarce, celem —
     * wpisany katalog zdalny.
     *
     * Rozmiar bierze się z kontekstu, a gdy wydawca go nie znał — ze `stat`,
     * bo plik leży na tej samej maszynie i pytanie jest darmowe. Mianownik paska
     * musi być znany przed pracą i to jest jedyne miejsce, w którym można go
     * wziąć.
     */
    private function startUpload(string $target): OverlayOutcome
    {
        $source = $this->local->filePath();
        $name = $this->local->fileName();
        $host = $this->reader->host();

        if ($source === null || $name === null || $host === null) {
            return OverlayOutcome::close($this->problem('transfer.noLocal'));
        }


        try {
            $path = RemotePath::of($target);
        } catch (InvalidRemotePathException) {
            return OverlayOutcome::close($this->problem('transfer.badPath'));
        }

        $this->direction = TransferDirection::Upload;
        $this->name = $name;
        $size = $this->local->fileBytes() ?? (@filesize($source) ?: 0);

        return $this->afterStep($this->transfers->begin(
            $host,
            [new RemoteTransferItem($source, $name, $size)],
            $path->value,
            TransferDirection::Upload,
            $this->occupiedIn($path),
        ));
    }

    /**
     * Nazwy zajęte w katalogu docelowym — **wyłącznie z listy, którą panel ma na
     * ekranie**.
     *
     * Pytanie serwerowi kosztowałoby obieg, czyli tyle, co przesył małego pliku,
     * i to przed każdym plikiem z osobna. Katalog inny niż otwarty oddaje pustą
     * listę i jest to odpowiedź uczciwa: „nie wiem”, a nie „nic tam nie ma”.
     *
     * @return list<string>
     */
    private function occupiedIn(RemotePath $target): array
    {
        $view = $this->reader->remote();
        $path = $view->path;

        if (!$view->hasListing || $path === null || !$path->equals($target)) {
            return [];
        }

        $names = [];

        foreach ($view->entries as $entry) {
            $names[] = $entry->name;
        }

        return $names;
    }

    /**
     * Co po kawałku pracy: okno postępu, pytanie o zajętą nazwę albo zdanie
     * o skutku.
     *
     * Jedno miejsce dla wszystkich wejść (start, odpowiedź o kolizji, nowa
     * nazwa), bo odpowiedź na pytanie „co teraz” zależy wyłącznie od stanu pracy,
     * a nie od tego, kto o nią pyta.
     */
    private function afterStep(RemoteTransferState $state): OverlayOutcome
    {
        if ($state->stage === RemoteTransferStage::Working) {
            return OverlayOutcome::replace($this->workingOverlay($state));
        }

        if ($state->stage === RemoteTransferStage::Colliding) {
            return OverlayOutcome::replace($this->collisionOverlay($state));
        }

        return OverlayOutcome::close($this->finished($state));
    }

    /** Okno pracy: nazwa pliku, licznik w bajtach i pasek — o ile jest z czego. */
    private function workingOverlay(RemoteTransferState $state): ProgressOverlay
    {
        return new ProgressOverlay(
            $this->key($this->direction->isDownload() ? 'transfer.download.progress' : 'transfer.upload.progress'),
            ['name' => $this->name],
            $this->progress($state),
            fn (): WorkProgress => $this->progress($this->transfers->advance()),
            fn (): OverlayOutcome => $this->afterStep($this->transfers->state()),
            fn (): Message => $this->stopped(),
            $this->translator,
        );
    }

    /**
     * Okno zajętej nazwy — sześć odpowiedzi, tych samych, co przy kopiowaniu
     * lokalnym (krok 42), bo słownik odpowiedzi jest rdzeniowy.
     *
     * `abort` stoi ostatni, bo `ChoiceOverlay` znaczy `Esc` jako odpowiedź
     * ostatnią, a wycofanie się jest jedyną, która ma prawo paść przez przypadek.
     */
    private function collisionOverlay(RemoteTransferState $state): ChoiceOverlay
    {
        return new ChoiceOverlay(
            $this->key('transfer.collision'),
            ['name' => $state->current],
            [
                'overwrite' => $this->key('transfer.overwrite'),
                'overwriteAll' => $this->key('transfer.overwriteAll'),
                'skip' => $this->key('transfer.skip'),
                'skipAll' => $this->key('transfer.skipAll'),
                'rename' => $this->key('transfer.rename'),
                'abort' => $this->key('transfer.abort'),
            ],
            fn (string $id): OverlayOutcome => $this->answered($id, $state->current),
            $this->translator,
        );
    }

    /** Odpowiedź o kolizji: „zmień nazwę” pyta jeszcze o nią, reszta wraca do pracy. */
    private function answered(string $id, string $colliding): OverlayOutcome
    {
        $choice = self::CHOICES[$id] ?? TransferChoice::Abort;

        if ($choice !== TransferChoice::Rename) {
            return $this->afterStep($this->transfers->resolve($choice));
        }

        return OverlayOutcome::replace(new PromptOverlay(
            $this->key('transfer.newName'),
            ['name' => $colliding],
            $colliding,
            fn (string $value): OverlayOutcome => $this->afterStep(
                $this->transfers->resolve(TransferChoice::Rename, $value),
            ),
            $this->translator,
        ));
    }

    /** `Esc` w trakcie pracy: przerywa najbliższy kawałek i mówi, ile zdążyło. */
    private function stopped(): Message
    {
        return $this->finished($this->transfers->state());
    }

    /**
     * Koniec pracy: zdanie o skutku, zdarzenie i odświeżenie panelu.
     *
     * Praca przerwana jest pracą zakończoną (D66), więc idzie tędy razem
     * z pomyślną — różni je zdanie, a nie droga. Panel zdalny odświeża się
     * **wyłącznie po wysłaniu**, bo tylko wtedy zmieniło się to, co pokazuje;
     * po pobraniu zmienił się katalog lokalny, którego ten moduł nie rysuje.
     */
    private function finished(RemoteTransferState $state): Message
    {
        $message = $this->sentence($state);

        $this->transfers->stop();

        if (!$this->direction->isDownload() && $state->doneEntries > 0) {
            $this->browser->refresh();
        }

        // O skutku rozstrzyga **ton zdania**, które po pracy zostało — ta sama
        // reguła, co w `BrowserEvents` (krok 46). Drugi rachunek prowadzony obok
        // rozjechałby się z pierwszym przy pierwszej poprawce.
        $this->events->publish(($message->tone === MessageTone::Error
            ? SshEvent::TransferFailed
            : SshEvent::TransferDone)->value);

        return $message;
    }

    private function sentence(RemoteTransferState $state): Message
    {
        if ($state->stage === RemoteTransferStage::Failed) {
            return Message::error($this->text(
                $state->problemKey ?? $this->key('transfer.failed'),
                [...$state->problemParameters, 'name' => $this->name],
            ));
        }

        if ($state->doneEntries === 0) {
            return Message::warning($this->text($this->key('transfer.nothing'), ['name' => $this->name]));
        }

        if ($state->wasStoppedEarly()) {
            return Message::warning($this->text($this->key('transfer.stopped'), [
                'done' => $this->translator->number((float) $state->doneEntries),
                'total' => $this->translator->number((float) $state->totalEntries),
            ]));
        }

        return Message::info($this->translator->plural(
            $this->key($this->direction->isDownload() ? 'transfer.download.done' : 'transfer.upload.done'),
            $state->doneEntries,
            ['name' => $this->name],
        ));
    }

    /**
     * Stan pracy przełożony na język okna postępu — wraz z **gotowym licznikiem**
     * (krok 42, D79 rozstrzygnięcie 9).
     *
     * Pasek pokazuje się przy pobieraniu, bo tam bajty są znane; przy wysyłaniu
     * jednego pliku całość podaje się jako **nieznaną**, choć rozmiar znamy —
     * i jest to jedyne uczciwe wyjście. Pasek stojący na zerze przez minutę,
     * a potem skaczący na koniec, mówiłby o postępie coś, czego nikt nie wie
     * (D89 nr 2).
     */
    private function progress(RemoteTransferState $state): WorkProgress
    {
        $known = $this->direction->isDownload() || $state->totalEntries > 1;

        return new WorkProgress(
            $state->isRunning(),
            $state->current,
            $state->doneBytes,
            $known ? $state->totalBytes : null,
            $known ? $this->counter($state) : '',
        );
    }

    /** Licznik: rozmiar zawsze, „który plik z ilu” tylko przy wielu plikach. */
    private function counter(RemoteTransferState $state): string
    {
        $size = [
            'done' => RemoteSize::of($this->translator, $state->doneBytes),
            'total' => RemoteSize::of($this->translator, $state->totalBytes),
        ];

        if ($state->totalEntries < 2) {
            return $this->text($this->key('transfer.counter.size'), $size);
        }

        return $this->text($this->key('transfer.counter'), [
            ...$size,
            'entry' => $this->translator->number((float) ($state->doneEntries + 1)),
            'entries' => $this->translator->number((float) $state->totalEntries),
        ]);
    }

    /** Skutek okna przełożony na skutek ekranu — jedno miejsce dla obu wejść. */
    private static function forScreen(OverlayOutcome $outcome): ScreenOutcome
    {
        return $outcome->next === null
            ? ScreenOutcome::stay($outcome->message)
            : ScreenOutcome::opens($outcome->next);
    }

    private function problem(string $suffix): Message
    {
        return Message::warning($this->text($this->key($suffix)));
    }

    private function key(string $suffix): string
    {
        return 'module.' . SshSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters);
    }
}
