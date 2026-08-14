<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Language;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Przebieg: **ustawienia, język i przywrócenie wartości domyślnych** (D31, D32,
 * D56).
 *
 * Trzy zachowania, których nie da się sprawdzić osobno, bo dzieją się na
 * stykach: zmiana wartości musi obowiązywać **od następnej klatki** (idzie
 * przez stan pętli, nie przez plik), przywrócenie domyślnych przechodzi przez
 * **okno potwierdzenia** i wykonuje się dopiero w jego domknięciu, a `Esc`
 * znaczy w nim to samo, co odpowiedź „nie”.
 */
final class SettingsFlowTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::file('notatka.txt', 12)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    /** `F2` wchodzi w ustawienia i `Esc` z nich wychodzi — dno stosu zostaje pod spodem. */
    public function testSettingsOpenAndCloseOverTheModuleScreen(): void
    {
        $this->press(KeyPress::special(Key::F2, ''));
        self::assertSame('settings', $this->app->screens->current()->id());

        $this->press(KeyPress::special(Key::Escape, ''));
        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /**
     * Zmieniona wartość idzie **do stanu pętli**, więc obowiązuje od następnej
     * klatki, a nie od następnego uruchomienia (D31).
     */
    public function testChangingThemeTakesEffectImmediately(): void
    {
        $before = $this->app->state->settings()->theme;

        $this->press(KeyPress::special(Key::F2, ''));
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::ArrowRight, ''));

        self::assertNotSame($before, $this->app->state->settings()->theme);
    }

    /** Język jest ustawieniem jak każde inne i tą samą drogą wraca do stanu (D32). */
    public function testLanguageIsSwitchedFromTheSameScreen(): void
    {
        $this->press(KeyPress::special(Key::F2, ''));
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::ArrowRight, ''));

        self::assertNotSame(Language::Auto->value, $this->app->state->settings()->language);
    }

    /**
     * Przywrócenie domyślnych **pyta**, zanim skasuje — i to pytanie jest
     * osobnym oknem, a nie kolejnym ekranem (D56).
     */
    public function testRestoringDefaultsAsksFirst(): void
    {
        $this->press(KeyPress::special(Key::F2, ''));
        $this->openRestoreQuestion();

        $overlay = $this->app->state->overlays()->current();

        self::assertNotNull($overlay);
        self::assertSame('confirm', $overlay->id());
    }

    /**
     * `Esc` na pytaniu znaczy „nie”: okno znika, a ustawienia zostają takie,
     * jakie były. To jest cała różnica między pytaniem a ostrzeżeniem.
     */
    public function testEscapeOnTheQuestionMeansNo(): void
    {
        $this->press(KeyPress::special(Key::F2, ''));
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::ArrowRight, ''));
        $changed = $this->app->state->settings()->language;

        $this->openRestoreQuestion();
        $this->press(KeyPress::special(Key::Escape, ''));

        self::assertNull($this->app->state->overlays()->current());
        self::assertSame($changed, $this->app->state->settings()->language);
    }

    /**
     * Odpowiedź twierdząca wykonuje czynność w **domknięciu okna** — ustawienia
     * wracają do domyślnych, a okno zamyka się samo.
     */
    public function testAnsweringYesRestoresTheDefaults(): void
    {
        $this->press(KeyPress::special(Key::F2, ''));
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::ArrowRight, ''));

        self::assertNotSame(Language::Auto->value, $this->app->state->settings()->language);

        $this->openRestoreQuestion();
        // Ognisko startuje na odmowie (D56), więc „tak” wymaga ruchu w bok.
        $this->press(KeyPress::special(Key::ArrowLeft, ''));
        $this->press(KeyPress::special(Key::Enter, ''));

        self::assertNull($this->app->state->overlays()->current());
        self::assertSame(Language::Auto->value, $this->app->state->settings()->language);
    }

    /**
     * Wiersz czynności stoi pod pozycjami zakładki — schodzimy do niego z góry.
     *
     * Ekran ustawień musi być już otwarty: `F2` jest przełącznikiem, więc
     * wciśnięte po raz drugi **zamknęłoby** ekran, a dalsze strzałki poszłyby
     * do listy plików. Ta pomyłka przeszłaby bez śladu — okno potwierdzenia po
     * prostu by nie powstało, a asercja „nie ma okna” byłaby prawdziwa
     * z niewłaściwego powodu.
     */
    private function openRestoreQuestion(): void
    {
        self::assertSame('settings', $this->app->screens->current()->id());

        for ($step = 0; $step < 12; ++$step) {
            $this->press(KeyPress::special(Key::ArrowDown, ''));
        }

        $this->press(KeyPress::special(Key::Enter, ''));
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, 0.0);
    }
}
