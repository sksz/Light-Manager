<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Stan usuwania katalogu wraz z zawartością — dana, nie proces (krok 41, D46).
 *
 * Wołający ogląda ją co takt i z niej bierze wszystko: czy jeszcze liczyć, czy
 * już pytać, ile wpisów zniknie, gdzie stoi praca i dlaczego stanęła. Klasa jest
 * bliźniakiem `ChecksumState` z modułu opisu pliku i różni się od niej dwiema
 * rzeczami, obie wymuszone tym, że praca **zmienia dysk**: ma etap `Ready`
 * (przystanek na pytanie) oraz nosi liczbę wpisów już usuniętych także
 * w niepowodzeniu — bo po przerwanym usuwaniu użytkownik musi wiedzieć, ile
 * z drzewa zostało.
 *
 * `total` liczy **wszystko, co zniknie z dysku, wraz z samym katalogiem
 * wskazanym**. Dzięki temu liczba w pytaniu i liczba w pasku postępu są tą samą
 * liczbą; wariant „tylko zawartość” wymagałby odejmowania jedynki w dwóch
 * miejscach i rozjechałby się przy pierwszej poprawce.
 */
final class RemovalState
{
    private function __construct(
        public readonly RemovalStage $stage,
        /** Nazwa wpisu, na którym praca stoi — bez ścieżki, bo to wiersz do pokazania. */
        public readonly string $current,
        /** Wpisy policzone (przy `Scanning`) albo usunięte (dalej). */
        public readonly int $done,
        /** Ile zniknie w całości; `null`, dopóki liczenie nie skończy się. */
        public readonly ?int $total,
        /** Klucz katalogu z powodem — wyłącznie przy `Failed`. */
        public readonly ?string $problemKey,
        /** @var array<string, string> parametry do podstawienia w powodzie */
        public readonly array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(RemovalStage::Idle, '', 0, null, null, []);
    }

    public static function scanning(int $found, string $current): self
    {
        return new self(RemovalStage::Scanning, $current, $found, null, null, []);
    }

    public static function ready(int $total): self
    {
        return new self(RemovalStage::Ready, '', 0, $total, null, []);
    }

    public static function deleting(int $done, int $total, string $current): self
    {
        return new self(RemovalStage::Deleting, $current, $done, $total, null, []);
    }

    public static function done(int $total): self
    {
        return new self(RemovalStage::Done, '', $total, $total, null, []);
    }

    /** @param array<string, string> $parameters */
    public static function failed(string $problemKey, array $parameters, int $done, ?int $total): self
    {
        return new self(RemovalStage::Failed, '', $done, $total, $problemKey, $parameters);
    }

    /** Czy praca jeszcze się posuwa — `Ready` już nie, bo czeka na decyzję. */
    public function isRunning(): bool
    {
        return $this->stage === RemovalStage::Scanning || $this->stage === RemovalStage::Deleting;
    }

    /**
     * Stan przełożony na język okna postępu.
     *
     * Przekład należy tutaj, a nie do okna ani do modułu: to jedyne miejsce, które
     * zna oba słowniki, a przełożony w module musiałby powstać drugi raz w kroku 42.
     */
    public function progress(): WorkProgress
    {
        return new WorkProgress($this->isRunning(), $this->current, $this->done, $this->total);
    }
}
