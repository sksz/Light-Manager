<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use LightManager\Infrastructure\Glfw\GlfwViewportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Usługi nie da się uruchomić w teście — pytania o wiersze i kolumny wymagają
 * otwartego okna i zmierzonej komórki. Sprawdzalne bez okna są dwie rzeczy,
 * z których każda niesie połowę kontraktu: arytmetyka komórek (framebuffer →
 * siatka) i **brak jakiegokolwiek stanu**, przez który rozmiar mógłby się
 * zestarzeć — to on gwarantuje, że przeciągnięcie rogu okna widać od
 * następnego pytania.
 *
 * Od kroku 35 komórka pochodzi z metryk fontu (`VgContextService`), więc test
 * podaje ją literałem — arytmetyka jest od źródła komórki niezależna.
 */
final class GlfwViewportServiceTest extends TestCase
{
    public function testDividesFramebufferIntoWholeCells(): void
    {
        self::assertSame(30, GlfwViewportService::cells(630, 21));
        self::assertSame(100, GlfwViewportService::cells(1000, 10));

        // Niepełna komórka nie liczy się wcale — wiersz, który się nie mieści,
        // nie istnieje.
        self::assertSame(29, GlfwViewportService::cells(629, 21));
    }

    public function testResizedFramebufferChangesTheGridFromTheNextQuestion(): void
    {
        self::assertSame(100, GlfwViewportService::cells(1000, 10));
        self::assertSame(144, GlfwViewportService::cells(1440, 10));
    }

    /** Okno ściśnięte poniżej jednej komórki dalej rysuje, co się zmieści (reguła kroku 33). */
    public function testNeverReportsLessThanOneCell(): void
    {
        self::assertSame(1, GlfwViewportService::cells(0, 21));
        self::assertSame(1, GlfwViewportService::cells(7, 21));
    }

    /**
     * Świeżość odpowiedzi stoi na braku pamięci: usługa bez pól nie ma czego
     * zapamiętać, więc nie potrzebuje ani znacznika, ani unieważniania.
     */
    public function testServiceHoldsNoStateBetweenQuestions(): void
    {
        self::assertSame([], (new ReflectionClass(GlfwViewportService::class))->getProperties());
    }
}
