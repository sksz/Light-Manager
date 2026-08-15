<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Strażnik kolizji, którą wprowadził krok 50: **`Ctrl`+`R` jest klawiszem
 * ekranu, a `Ctrl`+litera jest przestrzenią skrótów modułów.**
 *
 * Bliźniak `BrowserShortcutsTest` z kroku 31 i stoi tu z tego samego powodu:
 * `InputHandler` sprawdza skróty modułów **przed** ekranem (krok 19), a literę
 * bez zarejestrowanego modułu przepuszcza niżej — i tylko dlatego odświeżanie
 * listy w ogóle działa. Moduł ze skrótem `r` przejąłby ten klawisz po cichu.
 *
 * Cena jest tu świeża: do kroku 50 odświeżanie miało `F5`, którego nikt nie mógł
 * przejąć. Przeprowadzka na `Ctrl`+`R` była rozstrzygnięciem użytkownika
 * (D89 nr 4), więc jej koszt ma wyjść na testach, a nie na klawiaturze.
 */
final class RemoteShortcutsTest extends TestCase
{
    /** Ta sama litera, co `RemoteScreen::REFRESH_KEY` — stała ekranu jest prywatna. */
    private const REFRESH_KEY = 'r';

    public function testNoModuleClaimsTheLetterUsedByTheListingRefresh(): void
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

        self::assertArrayNotHasKey(
            self::REFRESH_KEY,
            $taken,
            'skrót modułu przejąłby Ctrl+R sprzed ekranu zdalnego — klawisz odświeżania trzeba wtedy zmienić',
        );
    }
}
