<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Stan przesyłu w tej chwili — dana, nie proces (krok 50, wzorzec D46).
 *
 * Piąty stan pracy kawałkowej w projekcie i **drugi liczący dwiema miarami
 * naraz** (bajty i pliki), po `TransferState` z kroku 42. Różnica wobec tamtego
 * jest jedna, za to widoczna na ekranie: **mianownik jest znany od początku**,
 * bo rozmiary przychodzą razem z listą (`RemoteTransferItem`), więc etapu
 * liczenia nie ma i pasek nigdy się nie cofa.
 *
 * `doneBytes` znaczy przy pobieraniu **bajty naprawdę zapisane** — plik rośnie
 * na dysku i widać go zwykłym `stat`em — a przy wysyłaniu **bajty plików już
 * przeniesionych**, bo ile z bieżącego poszło w sieć, nie mówi nikt:
 * `sftp` pokazuje pasek wyłącznie na terminalu sterującym (zmierzone, D89 nr 2).
 * Asymetria jest własnością drogi, nie tej klasy, i dlatego stoi w polu, a nie
 * w komentarzu u odbiorcy.
 */
final class RemoteTransferState
{
    private function __construct(
        public readonly RemoteTransferStage $stage,
        /** Nazwa pliku, na którym praca stoi — bez ścieżki, bo to wiersz do pokazania. */
        public readonly string $current,
        /** Bajty przeniesione; przy wysyłaniu — bajty plików ukończonych. */
        public readonly int $doneBytes,
        /** Bajty w całości — znane od pierwszej klatki pracy. */
        public readonly int $totalBytes,
        /** Pliki przeniesione w całości. */
        public readonly int $doneEntries,
        /** Pliki, które praca wzięła — łącznie z pominiętymi. */
        public readonly int $totalEntries,
        /** Ile plików użytkownik kazał pominąć — do zdania po pracy. */
        public readonly int $skippedEntries,
        /** Klucz katalogu z powodem — wyłącznie przy `Failed`. */
        public readonly ?string $problemKey,
        /** @var array<string, string> parametry do podstawienia w powodzie */
        public readonly array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(RemoteTransferStage::Idle, '', 0, 0, 0, 0, 0, null, []);
    }

    public function working(string $current): self
    {
        return $this->with(RemoteTransferStage::Working, $current);
    }

    /** Przystanek na pytanie o zajętą nazwę; `current` niesie nazwę, o którą praca pyta. */
    public function colliding(string $name): self
    {
        return $this->with(RemoteTransferStage::Colliding, $name);
    }

    public function done(): self
    {
        return $this->with(RemoteTransferStage::Done, '');
    }

    /** @param array<string, string> $parameters */
    public function failed(string $problemKey, array $parameters = []): self
    {
        return new self(
            RemoteTransferStage::Failed,
            '',
            $this->doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
            $this->skippedEntries,
            $problemKey,
            $parameters,
        );
    }

    /**
     * Stan początkowy pracy: mianownik z rozmiarów, licznik na zerze.
     *
     * @param list<RemoteTransferItem> $items
     */
    public static function beginning(array $items): self
    {
        $bytes = 0;

        foreach ($items as $item) {
            $bytes += $item->sizeInBytes;
        }

        return new self(
            RemoteTransferStage::Working,
            $items === [] ? '' : $items[0]->name,
            0,
            $bytes,
            0,
            count($items),
            0,
            null,
            [],
        );
    }

    /** Postęp bieżącego pliku — wyłącznie przy pobieraniu (patrz opis klasy). */
    public function withBytes(int $doneBytes): self
    {
        return new self(
            $this->stage,
            $this->current,
            $doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
            $this->skippedEntries,
            $this->problemKey,
            $this->problemParameters,
        );
    }

    /** Plik przeniesiony w całości: licznik plików rośnie, bajty siadają na pewnej sumie. */
    public function withFinished(int $doneBytes): self
    {
        return new self(
            $this->stage,
            $this->current,
            $doneBytes,
            $this->totalBytes,
            $this->doneEntries + 1,
            $this->totalEntries,
            $this->skippedEntries,
            null,
            [],
        );
    }

    /** Plik pominięty na życzenie: nie liczy się jako przeniesiony, ale praca idzie dalej. */
    public function withSkipped(): self
    {
        return new self(
            $this->stage,
            $this->current,
            $this->doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
            $this->skippedEntries + 1,
            null,
            [],
        );
    }

    /** Czy praca się posuwa — `Colliding` już nie, bo czeka na człowieka. */
    public function isRunning(): bool
    {
        return $this->stage === RemoteTransferStage::Working;
    }

    /** Czy praca stanęła przed przeniesieniem wszystkiego, co wzięła. */
    public function wasStoppedEarly(): bool
    {
        return $this->doneEntries + $this->skippedEntries < $this->totalEntries;
    }

    private function with(RemoteTransferStage $stage, string $current): self
    {
        return new self(
            $stage,
            $current,
            $this->doneBytes,
            $this->totalBytes,
            $this->doneEntries,
            $this->totalEntries,
            $this->skippedEntries,
            $this->problemKey,
            $this->problemParameters,
        );
    }
}
