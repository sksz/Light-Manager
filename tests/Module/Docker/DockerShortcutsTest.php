<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Strażnik dwóch liter naraz (krok 51).
 *
 * Pierwsza: **`Ctrl`+`R` jest klawiszem ekranu, a `Ctrl`+litera jest przestrzenią
 * skrótów modułów** — bliźniak `RemoteShortcutsTest` z kroku 50 i
 * `BrowserShortcutsTest` z 31. `InputHandler` sprawdza skróty modułów **przed**
 * ekranem, więc moduł ze skrótem `r` przejąłby odświeżanie po cichu — i to
 * w dwóch modułach naraz, bo ekran zdalny używa tej samej litery.
 *
 * Druga: **litera `o` należy do modułu Dockera, a `k` ma zostać wolna dla kroku
 * 52** (D90 nr 2). Rozstrzygnięcie zapadło dla obu kroków naraz, więc test
 * pilnuje go z wyprzedzeniem — moduł k8s dopisany bez zajrzenia do dziennika
 * zabrałby literę, o której użytkownik już rozstrzygnął.
 */
final class DockerShortcutsTest extends TestCase
{
    /** Ta sama litera, co `DockerScreen::REFRESH_KEY` — stała ekranu jest prywatna. */
    private const REFRESH_KEY = 'r';

    /** Litera zamówiona dla modułu Kubernetesa z kroku 52 (D90 nr 2). */
    private const RESERVED_FOR_KUBERNETES = 'k';

    public function testDockerTakesTheLetterItWasGiven(): void
    {
        self::assertSame('docker', self::shortcuts()['o'] ?? null);
    }

    public function testNoModuleClaimsTheLetterUsedByTheListRefresh(): void
    {
        self::assertArrayNotHasKey(
            self::REFRESH_KEY,
            self::shortcuts(),
            'skrót modułu przejąłby Ctrl+R sprzed ekranu Dockera i ekranu zdalnego',
        );
    }

    public function testTheLetterReservedForKubernetesIsStillFree(): void
    {
        self::assertArrayNotHasKey(
            self::RESERVED_FOR_KUBERNETES,
            self::shortcuts(),
            'litera k jest zamówiona dla modułu k8s z kroku 52 (D90 nr 2)',
        );
    }

    /** @return array<string, string> litera → identyfikator modułu */
    private static function shortcuts(): array
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);
        $app = new ScreenFixture($directories->get(new DirectoryPath('/'), false), $directories);

        $taken = [];

        foreach ($app->modules->declared() as $module) {
            $shortcut = $module->shortcut();

            if ($shortcut !== null) {
                $taken[$shortcut->character] = $module->id();
            }
        }

        return $taken;
    }
}
