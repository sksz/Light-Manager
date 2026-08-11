<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

/**
 * Wyjątek, który sam mówi, jakim zdaniem opisać go użytkownikowi.
 *
 * Do kroku 20 zdanie dobierał wyłącznie `Presentation\Cli\ProblemPresenter`,
 * rozpoznając wyjątek **po klasie**. Reguła działała dopóty, dopóki wszystkie
 * wyjątki domenowe należały do rdzenia; w kroku 21 cztery z nich zeszły do modułu
 * przeglądarki, a presenter rozpoznający je dalej znaczyłby, że rdzeń wciąż wie,
 * czym jest katalog — czyli że główne kryterium kroku nie zostało spełnione.
 *
 * Od tej pory obowiązują **dwie drogi**: wyjątek rdzenia rozpoznaje się po klasie,
 * a wyjątek, który zadeklarował ten interfejs — po jego własnej deklaracji.
 * Deklaracja niesie **klucz katalogu napisów i parametry**, nigdy gotowy napis:
 * `Domain` nie sięga po napisy w ogóle (krok 15), a klucz jest daną jak każda inna.
 *
 * Interfejs leży w `Domain`, bo implementują go wyjątki domenowe, a nie
 * w `Presentation`, bo wtedy domena modułu musiałaby zobaczyć warstwę leżącą na
 * zewnątrz niej.
 */
interface DescribesProblem
{
    /** Klucz katalogu napisów ze zdaniem dla użytkownika. */
    public function problemKey(): string;

    /** @return array<string, string> parametry podstawiane w zdaniu */
    public function problemParameters(): array;
}
