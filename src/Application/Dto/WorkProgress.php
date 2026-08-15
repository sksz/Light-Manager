<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Stan pracy dłuższej od klatki — tyle, ile trzeba, żeby ją **pokazać** (krok 41).
 *
 * Dana jest **ogólna z rozmysłu**: okno postępu (`ProgressOverlay`) nie ma prawa
 * wiedzieć, czy patrzy na usuwanie, kopiowanie czy liczenie sumy kontrolnej.
 * Stąd trzy pola i ani jednego więcej — wiersz treści **już gotowy do
 * pokazania**, ile zrobiono i ile jest do zrobienia w całości.
 *
 * Dlaczego obok `RemovalState` istnieje jeszcze ta klasa: tamta jest językiem
 * **portu** (etapy pracy, powód niepowodzenia, decyzja użytkownika pomiędzy
 * etapami), a ta językiem **okna**. Bez tego podziału krok 42 musiałby karmić
 * okno postępu stanem usuwania, żeby pokazać kopiowanie — czyli okno rdzenia
 * poznałoby jedną konkretną czynność jako swoją miarę wszystkich.
 *
 * `total === null` znaczy „całości jeszcze nie znamy” i jest to stan prawdziwy,
 * nie zastępczy: przy liczeniu zawartości katalogu nie wiadomo, ile wpisów
 * zostało, dopóki się nie skończy. Okno pokazuje wtedy samą nazwę, bez paska —
 * pasek wypełniony „na oko” byłby zmyśleniem, a wędrujący (tryb `ProgressBar`
 * z kroku 23) mówiłby „coś się dzieje” w miejscu, gdzie zmieniająca się co
 * klatkę nazwa mówi to samo dokładniej.
 */
final class WorkProgress
{
    public function __construct(
        /** Czy praca nadal trwa — `false` znaczy „skończona albo przerwana”. */
        public readonly bool $running,
        /** Wiersz treści: nazwa wpisu, na którym praca właśnie stoi. Pusty, gdy nie ma czego pokazać. */
        public readonly string $current = '',
        public readonly int $done = 0,
        /** Ile w całości; `null` — jeszcze nie wiadomo. */
        public readonly ?int $total = null,
        /**
         * Licznik **już złożony i przetłumaczony** — albo pustka, gdy okno ma
         * złożyć go samo z `done` i `total` (krok 42, D79 rozstrzygnięcie 9).
         *
         * Pole doszło, bo druga praca w projekcie liczy w bajtach: „3840 z 30001”
         * czyta się dobrze, „12914688 z 734003200” nie czyta się wcale. Skoro
         * jednostki i separator dziesiętny idą przez katalog napisów, licznik
         * musi złożyć ten, kto ma tłumacza — a okno postępu przestaje przez to
         * wiedzieć, **co** właściwie liczy, co jest tu zaletą.
         */
        public readonly string $counter = '',
    ) {
    }

    public static function idle(): self
    {
        return new self(false);
    }

    /** Ułamek dla paska postępu; `null` — pasek się nie rysuje. */
    public function fraction(): ?float
    {
        if ($this->total === null || $this->total <= 0) {
            return null;
        }

        return max(0.0, min(1.0, $this->done / $this->total));
    }
}
