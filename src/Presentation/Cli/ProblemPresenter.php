<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Infrastructure\Glfw\GlfwException;
use LightManager\Infrastructure\Glfw\GlfwProblem;
use LightManager\Infrastructure\Terminal\TerminalException;
use LightManager\Infrastructure\Terminal\TerminalProblem;
use Throwable;

/**
 * Zamienia wyjątek na zdanie w języku interfejsu.
 *
 * Komunikaty samych wyjątków są techniczne i angielskie (krok 15) — pisane dla
 * osoby czytającej ślad stosu. Napis dla użytkownika dobiera się tutaj, po
 * klasie wyjątku, a konkrety (ścieżka, szczegół awarii) biorą się z typowanych
 * pól, nie z rozbierania treści komunikatu.
 *
 * Wyjątek nieprzewidziany dostaje zdanie ogólne, bo jego treść opisuje błąd
 * programu, nie sytuację użytkownika. Wyjątkiem od tej reguły jest awaria
 * startu: tam techniczny opis dopisuje się do zdania, bo nikt inny go już nie
 * zobaczy, a bez niego zgłoszenie błędu byłoby nie do odtworzenia.
 *
 * Klasa zna obie hierarchie wyjątków — także tę z `Infrastructure`. To ta sama
 * swoboda, z której korzysta [Bootstrap](Bootstrap.php): warstwa `Presentation`
 * jest miejscem, w którym aplikacja styka się z konkretami, i jedynym, które
 * może rozstrzygnąć, co pokazać użytkownikowi.
 *
 * Od kroku 21 drogi są **dwie**, i to jest cena za rdzeń, który nie wie, czym jest
 * katalog. Wyjątek modułu deklaruje `DescribesProblem` i sam podaje klucz zdania
 * wraz z parametrami; pytamy o to **najpierw**, bo rozpoznawanie po klasie działa
 * wyłącznie dla wyjątków, których nazwy wolno tu wymienić — czyli rdzeniowych.
 */
final class ProblemPresenter
{
    private const UNEXPECTED = 'problem.unexpected';

    public function __construct(
        private readonly TranslatorPort $translator,
    ) {
    }

    /** Zdanie do paska stanu — bez technicznych szczegółów, bo pasek ma jeden wiersz. */
    public function text(Throwable $problem): string
    {
        return $this->known($problem) ?? $this->translator->translate(self::UNEXPECTED);
    }

    /**
     * Zdanie na wyjście błędów przy nieudanym starcie. Nierozpoznany wyjątek
     * zostawia po sobie oryginalną treść — to jedyny ślad, jaki po nim będzie.
     */
    public function startupText(Throwable $problem): string
    {
        $known = $this->known($problem);

        if ($known !== null) {
            return $known;
        }

        return $this->translator->translate(self::UNEXPECTED) . ' (' . $problem->getMessage() . ')';
    }

    private function known(Throwable $problem): ?string
    {
        return match (true) {
            $problem instanceof DescribesProblem => $this->translator->translate(
                $problem->problemKey(),
                $problem->problemParameters(),
            ),
            $problem instanceof TerminalException => $this->terminal($problem),
            $problem instanceof GlfwException => $this->glfw($problem),
            default => null,
        };
    }

    private function glfw(GlfwException $problem): string
    {
        return match ($problem->problem) {
            GlfwProblem::MissingExtension => $this->translator->translate('problem.missingGlfw'),
            GlfwProblem::InitFailure => $this->translator->translate('problem.glfw.init'),
            GlfwProblem::WindowFailure => $this->translator->translate('problem.glfw.window'),
            GlfwProblem::MissingFont => $this->translator->translate('problem.glfw.font'),
        };
    }

    private function terminal(TerminalException $problem): string
    {
        return match ($problem->problem) {
            TerminalProblem::NonInteractiveStdin => $this->translator->translate('problem.terminal.notInteractive'),
            TerminalProblem::MissingPcntl => $this->translator->translate('problem.terminal.missingPcntl'),
            TerminalProblem::DisabledExec => $this->translator->translate('problem.terminal.disabledExec'),
            TerminalProblem::SttyFailure => $this->translator->translate(
                'problem.terminal.stty',
                ['detail' => $problem->detail],
            ),
        };
    }
}
