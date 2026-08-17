<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Infrastructure\Terminal\TerminalClipboardService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Odmowy schowka terminalowego — **jedyne, co da się tu sprawdzić bez terminala**
 * (krok 57).
 *
 * Test dotyka wyłącznie gałęzi, które kończą się **przed** zapisem na STDOUT,
 * i jest to granica postawiona świadomie: `put()` wysyła sekwencję `OSC 52`, czyli
 * podmienia zawartość schowka osoby uruchamiającej testy. Przebieg sprawdzający
 * zapis podmieniłby go bez śladu, więc zapisu tu nie ma ani razu — a zachowanie
 * przy treści, którą **wolno** położyć, sprawdza `ClipboardFlowTest` przez atrapę
 * portu i ręczne sprawdzenie pod XTermem (kryteria ukończenia kroku).
 */
final class TerminalClipboardServiceTest extends TestCase
{
    use ResetsSingletons;

    /** Próg jest po stronie aplikacji, bo terminal swojego nie podaje. */
    private const OVER_THRESHOLD = 65537;

    protected function tearDown(): void
    {
        $this->resetSingleton(TerminalClipboardService::class);
    }

    /** Pustej treści nie ma po co kopiować — i nie ma po co pytać o nią terminala. */
    public function testEmptyContentIsRefusedBeforeAnythingIsWritten(): void
    {
        self::assertSame('clipboard.problem.empty', TerminalClipboardService::getInstance()->put(''));
    }

    /**
     * **Treść za długa kończy się odmową, nigdy cichym obcięciem.**
     *
     * To jest cały powód istnienia progu: przekroczenie limitu łańcucha `OSC 52`
     * kończy się u terminala obcięciem **bez błędu**, więc bez własnego progu
     * użytkownik dostawałby połowę zawartości i ani jednego słowa o tym.
     */
    public function testContentOverTheThresholdIsRefusedInsteadOfTruncated(): void
    {
        self::assertSame(
            'clipboard.problem.too-long',
            TerminalClipboardService::getInstance()->put(str_repeat('A', self::OVER_THRESHOLD)),
        );
    }

    /** Próg liczy się w **bajtach treści**, nie w znakach: base64 koduje bajty. */
    public function testTheThresholdCountsBytesNotCharacters(): void
    {
        // Trzydzieści trzy tysiące znaków po dwa bajty każdy to 66 000 bajtów —
        // czyli o połowę mniej znaków niż próg i więcej bajtów niż próg.
        self::assertSame(
            'clipboard.problem.too-long',
            TerminalClipboardService::getInstance()->put(str_repeat('ą', 33000)),
        );
    }
}
