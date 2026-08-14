<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * W którym miejscu jest usuwanie katalogu wraz z zawartością (krok 41).
 *
 * Sześć etapów, bo tyle ich widzi użytkownik i tyle rozróżnia wołający.
 * `Ready` jest przy tym etapem, którego nie ma żadna inna praca w projekcie:
 * praca **staje** i czeka na decyzję, bo pytanie „usunąć N wpisów?” wymaga
 * liczby, a liczba jest znana dopiero po policzeniu. Bez tego etapu pytanie
 * musiałoby padać przed liczeniem — czyli bez liczby (D75, rozstrzygnięcie 10).
 */
enum RemovalStage
{
    case Idle;

    /** Chodzenie po drzewie i dokładanie wpisów do listy. */
    case Scanning;

    /** Lista gotowa, liczba znana — czeka na zgodę użytkownika. */
    case Ready;

    case Deleting;

    case Done;

    /** Nie da się dokończyć: brak praw, nieczytelna gałąź, pełny dysk. */
    case Failed;
}
