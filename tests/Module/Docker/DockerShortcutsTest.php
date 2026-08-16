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

    /**
     * **Zamówienie zostało zrealizowane w kroku 52**, więc test zmienił zdanie
     * na przeciwne — i to jest jego cała treść.
     *
     * Do tamtego kroku pilnował, żeby litery `k` nikt nie zajął, bo rozstrzygnięcie
     * D90 nr 2 przydzieliło ją modułowi, którego jeszcze nie było. Odkąd moduł
     * istnieje, „litera wolna” znaczyłoby, że rozstrzygnięcia nie wykonano —
     * a wcześniejsze brzmienie przeszłoby wtedy nadal.
     */
    public function testTheLetterReservedForKubernetesWentToIt(): void
    {
        self::assertSame(
            'k8s',
            self::shortcuts()[self::RESERVED_FOR_KUBERNETES] ?? null,
            'literę k rozstrzygnięto dla modułu k8s razem z literą Dockera (D90 nr 2)',
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
