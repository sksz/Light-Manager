<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Przebieg: **podróż po katalogach** — wejście, powrót, wpisy ukryte
 * i zaznaczenie odzyskane po powrocie (D20).
 *
 * Najstarsze zachowanie aplikacji i do kroku 38 jedyne bez własnego,
 * **nazwanego** przebiegu: sprawdzały je testy modułu, każdy z osobna,
 * z których żaden nie przechodził całej drogi „wejdź, wróć, znajdź się tam,
 * gdzie byłeś”. Właśnie ta droga jest tym, co użytkownik robi najczęściej.
 */
final class DirectoryTravelFlowTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [
                Entry::directory('projekty'),
                Entry::directory('.ukryty'),
                Entry::file('notatka.txt', 12),
            ])
            ->add('/home/projekty', [Entry::file('plan.md', 120), Entry::file('szkic.md', 80)]);

        // Katalog startowy czytamy **z wpisami ukrytymi**, bo zestaw rejestruje
        // w repozytorium dokładnie to, co dostał: podany bez nich straciłby
        // `.ukryty` na dobre i przełącznik nie miałby czego pokazać.
        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), true), $directories);
    }

    public function testEnteringADirectoryShowsItsContents(): void
    {
        $this->enter('projekty');

        self::assertSame('/home/projekty', $this->path());
        self::assertSame(['plan.md', 'szkic.md'], $this->names());
    }

    /**
     * Powrót stawia zaznaczenie **na katalogu, z którego się wyszło** (D20).
     * Bez tego użytkownik po każdym powrocie szuka wzrokiem miejsca, w którym
     * przed chwilą był.
     */
    public function testComingBackRestoresTheSelectionOnTheDirectoryLeft(): void
    {
        $this->enter('projekty');
        $this->press(KeyPress::special(Key::Backspace, ''));

        self::assertSame('/home', $this->path());
        self::assertSame('projekty', $this->app->state->context()->selection);
    }

    /** Kropka pokazuje i chowa wpisy ukryte — ta sama lista, inny zestaw. */
    public function testHiddenEntriesAppearAndDisappearOnTheDotKey(): void
    {
        self::assertNotContains('.ukryty', $this->names());

        $this->press(KeyPress::character('.'));
        self::assertContains('.ukryty', $this->names());

        $this->press(KeyPress::character('.'));
        self::assertNotContains('.ukryty', $this->names());
    }

    /** Plik nie jest katalogiem: `Enter` na nim nie zmienia ścieżki. */
    public function testEnterOnAFileDoesNotTravelAnywhere(): void
    {
        $this->enter('notatka.txt');

        self::assertSame('/home', $this->path());
    }

    /** Ustawia zaznaczenie na wpisie o podanej nazwie i wchodzi w niego. */
    private function enter(string $name): void
    {
        for ($step = 0; $step < count($this->names()); ++$step) {
            if ($this->app->state->context()->selection === $name) {
                break;
            }

            $this->press(KeyPress::special(Key::ArrowDown, ''));
        }

        $this->press(KeyPress::special(Key::Enter, ''));
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, 0.0);
    }

    private function path(): string
    {
        return $this->app->state->context()->path;
    }

    /**
     * Nazwy czytane z **klatki**, a nie z agregatu — tak widzi je użytkownik.
     *
     * @return list<string>
     */
    private function names(): array
    {
        $names = [];

        foreach ($this->app->browser->draw(ScreenFixture::panel(10, 60)) as $primitive) {
            if ($primitive instanceof TextRun && $primitive->column === ScreenFixture::panel()->column) {
                $names[] = rtrim($primitive->text, '/');
            }
        }

        return $names;
    }
}
