<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Stan kopiowania albo przenoszenia — dana, nie proces (krok 42, D46).
 *
 * Trzeci stan pracy kawałkowej w projekcie, po `ChecksumState` (krok 25)
 * i `RemovalState` (krok 41), i pierwszy, który liczy **dwiema miarami naraz**:
 * bajtami i wpisami. Powód jest widoczny na ekranie: pasek rośnie w bajtach, bo
 * inaczej kopiowanie jednego pliku wielkości płyty stałoby na zerze przez całą
 * minutę, a licznik mówi „plik 3 ze 120”, bo bajty same nie odpowiadają na
 * pytanie „daleko jeszcze”. Przy liczeniu obie miary znaczą **znalezione**,
 * a nie zrobione — tą samą regułą, którą `RemovalState` liczy wpisy przy
 * `Scanning`.
 *
 * `totalBytes === null` znaczy „jeszcze nie wiadomo” i trwa wyłącznie do końca
 * liczenia; od pierwszego skopiowanego bajtu całość jest znana (D79,
 * rozstrzygnięcie 3).
 *
 * **Metody `progress()` tu nie ma i to jest różnica wobec `RemovalState`.**
 * Tamten przekładał się na język okna sam, bo licznik „N z M” składał się z dwóch
 * liczb. Tutaj licznik jest napisem z jednostkami („12,3 MB z 700 MB — plik 3 ze
 * 120”), a jednostki i separator dziesiętny idą przez katalog napisów — więc
 * przekład należy do tego, kto ma tłumacza, czyli do modułu (D79,
 * rozstrzygnięcie 9).
 */
final class TransferState
{
    private function __construct(
        public readonly TransferStage $stage,
        /** Nazwa wpisu, na którym praca stoi — bez ścieżki, bo to wiersz do pokazania. */
        public readonly string $current,
        /** Bajty skopiowane; przy `Scanning` — bajty znalezione. */
        public readonly int $doneBytes,
        /** Ile bajtów w całości; `null`, dopóki liczenie się nie skończy. */
        public readonly ?int $totalBytes,
        /** Wpisy obsłużone; przy `Scanning` — wpisy znalezione. */
        public readonly int $doneEntries,
        /** Ile wpisów w całości; `0`, dopóki liczenie się nie skończy. */
        public readonly int $totalEntries,
        /** Klucz katalogu z powodem — wyłącznie przy `Failed`. */
        public readonly ?string $problemKey,
        /** @var array<string, string> parametry do podstawienia w powodzie */
        public readonly array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(TransferStage::Idle, '', 0, null, 0, 0, null, []);
    }

    public static function scanning(int $entries, int $bytes, string $current): self
    {
        return new self(TransferStage::Scanning, $current, $bytes, null, $entries, 0, null, []);
    }

    public static function working(string $current, int $doneBytes, int $totalBytes, int $doneEntries, int $totalEntries): self
    {
        return new self(TransferStage::Working, $current, $doneBytes, $totalBytes, $doneEntries, $totalEntries, null, []);
    }

    /** Przystanek na pytanie o kolizję; `current` niesie nazwę wpisu, o który praca pyta. */
    public static function colliding(string $name, int $doneBytes, int $totalBytes, int $doneEntries, int $totalEntries): self
    {
        return new self(TransferStage::Colliding, $name, $doneBytes, $totalBytes, $doneEntries, $totalEntries, null, []);
    }

    /**
     * Koniec pracy — w całości albo przerwanej.
     *
     * Rozróżnia je **wołający**, porównując `doneEntries` z `totalEntries`:
     * praca przerwana jest pracą zakończoną (D66), a stan nie ma powodu nieść
     * dwóch etapów na to samo.
     */
    public static function done(int $doneBytes, int $totalBytes, int $doneEntries, int $totalEntries): self
    {
        return new self(TransferStage::Done, '', $doneBytes, $totalBytes, $doneEntries, $totalEntries, null, []);
    }

    /**
     * Niepowodzenie wraz z tym, ile zdążyło się zrobić — bo po przerwanym
     * kopiowaniu użytkownik musi wiedzieć, co jest już w celu.
     *
     * @param array<string, string> $parameters
     */
    public static function failed(string $problemKey, array $parameters, int $doneBytes, ?int $totalBytes, int $doneEntries, int $totalEntries): self
    {
        return new self(
            TransferStage::Failed,
            '',
            $doneBytes,
            $totalBytes,
            $doneEntries,
            $totalEntries,
            $problemKey,
            $parameters,
        );
    }

    /** Czy praca jeszcze się posuwa — `Colliding` już nie, bo czeka na odpowiedź. */
    public function isRunning(): bool
    {
        return $this->stage === TransferStage::Scanning || $this->stage === TransferStage::Working;
    }

    /** Czy praca skończyła się przed czasem: przerwaniem albo odpowiedzią „przerwij”. */
    public function wasStoppedEarly(): bool
    {
        return $this->doneEntries < $this->totalEntries;
    }
}
