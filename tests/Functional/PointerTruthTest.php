<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Zobowiązanie obustronne wskaźnika (krok 55, reguła 11z) — wzorem
 * `StatusHintsTruthTest` z kroku 40.
 *
 * Jedno zdanie, dwie połowy, obie sprawdzane **dla wszystkich ekranów naraz**:
 *
 * 1. **każdy ekran przyjmuje wskaźnik** — ekran bez `AcceptsPointer` jest
 *    miejscem, w którym mysz przestaje działać bez słowa;
 * 2. **każde miejsce, które ekran deklaruje w `focus()`, da się kliknąć** —
 *    miejsce osiągalne klawiszem, a nieosiągalne myszą, znaczy mysz działającą
 *    w połowie ekranu, i nie widać tego, dopóki ktoś nie kliknie.
 *
 * Druga połowa jest sprawdzana **przez skutek**: ognisko postawione kliknięciem
 * ma dać tę samą nazwę miejsca, którą daje ognisko przeniesione klawiszem.
 * Testu nie obchodzi, jak ekran to liczy — obchodzi go, że da się tam dojść
 * obiema drogami.
 */
final class PointerTruthTest extends TestCase
{
    private const COLUMNS = 160;

    private const ROWS = 30;

    private const NOW = 1000.0;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->app = self::fixture();
        $this->app->state->applySettings(
            $this->app->state->settings()->withModuleValue(BrowserSettings::ID, BrowserSettings::SPLIT, true),
        );
    }

    /** @return array<string, array{string}> */
    public static function screens(): array
    {
        return [
            'przeglądarka' => ['browser'],
            'opis pliku' => ['fileInfo'],
            'dźwięk' => ['audioScreen'],
            'sesja zdalna' => ['sshScreen'],
            'docker' => ['dockerScreen'],
            'kubernetes' => ['kubernetesScreen'],
            'ustawienia' => ['settings'],
            'pomoc' => ['help'],
        ];
    }

    /** Pierwsza połowa zobowiązania: nie ma ekranu, na którym mysz milczy. */
    #[DataProvider('screens')]
    public function testEveryScreenAcceptsThePointer(string $name): void
    {
        self::assertInstanceOf(AcceptsPointer::class, $this->screenNamed($name));
    }

    /**
     * Druga połowa: każde miejsce osiągalne klawiszem jest osiągalne myszą.
     *
     * Miejsca zbiera się `Tab`em — bo to on przenosi ognisko w każdym ekranie,
     * który ma więcej niż jedno — a potem próbuje dojść do każdego z nich
     * kliknięciem w kilkanaście punktów rozłożonych po prostokącie ekranu.
     */
    #[DataProvider('screens')]
    public function testEveryDeclaredPlaceCanBeReachedByClicking(string $name): void
    {
        $screen = $this->screenNamed($name);

        // Ekran o jednym miejscu ogniska (albo bez deklaracji) nie ma czego
        // przenosić kliknięciem — zdanie zobowiązania jest wtedy spełnione
        // pusto, i tak też się je liczy.
        $byKeyboard = $screen instanceof DeclaresFocus ? $this->placesByKeyboard($screen) : [];
        $missing = count($byKeyboard) < 2
            ? []
            : array_values(array_diff($byKeyboard, $this->placesByPointer($screen)));

        self::assertSame(
            [],
            $missing,
            'miejsce osiągalne klawiszem, a nieosiągalne myszą, znaczy mysz działającą w połowie ekranu',
        );
    }

    /**
     * Zabezpieczenie samego testu: przynajmniej jeden ekran **naprawdę** ma
     * więcej niż jedno miejsce ogniska.
     *
     * Bez niego cały sprawdzian mógłby przejść na samych wyjściach „jedno
     * miejsce — nie ma czego porównywać”, a wtedy zobowiązania nie pilnowałby
     * nikt.
     */
    public function testAtLeastOneScreenHasMoreThanOnePlace(): void
    {
        $browser = $this->screenNamed('browser');

        self::assertInstanceOf(DeclaresFocus::class, $browser);
        self::assertGreaterThan(1, count($this->placesByKeyboard($browser)));
    }

    /**
     * Nazwy miejsc, do których prowadzi `Tab` — najwyżej cztery naciśnięcia, bo
     * żaden ekran aplikacji nie ma więcej miejsc.
     *
     * @return list<string>
     */
    private function placesByKeyboard(ScreenInterface&DeclaresFocus $screen): array
    {
        $places = [];

        for ($step = 0; $step < 4; ++$step) {
            $this->frame($screen);
            $label = $screen->focus()?->labelKey;

            if ($label !== null && !in_array($label, $places, true)) {
                $places[] = $label;
            }

            $screen->handle(KeyPress::special(Key::Tab, "\t"));
        }

        return $places;
    }

    /**
     * Nazwy miejsc, do których prowadzi kliknięcie — punkty rozłożone po całym
     * prostokącie, bo test nie ma prawa wiedzieć, gdzie ekran narysował co.
     *
     * @return list<string>
     */
    private function placesByPointer(ScreenInterface&DeclaresFocus $screen): array
    {
        $bounds = $this->zone();
        $places = [];

        foreach ([0.1, 0.3, 0.5, 0.7, 0.9] as $across) {
            foreach ([0, 2, intdiv($bounds->rows, 2)] as $down) {
                $this->frame($screen);
                $this->app->input->pointer(
                    PointerEvent::press(
                        $bounds->row + $down,
                        $bounds->column + (int) round($bounds->columns * $across),
                    ),
                    $this->app->state,
                    self::NOW,
                );

                $this->frame($screen);
                $label = $screen->focus()?->labelKey;

                if ($label !== null && !in_array($label, $places, true)) {
                    $places[] = $label;
                }
            }
        }

        return $places;
    }

    private function screenNamed(string $name): ScreenInterface
    {
        /** @var ScreenInterface $screen */
        $screen = match ($name) {
            'browser' => $this->app->browser,
            'fileInfo' => $this->app->fileInfo,
            'audioScreen' => $this->app->audioScreen,
            'sshScreen' => $this->app->sshScreen,
            'dockerScreen' => $this->app->dockerScreen,
            'kubernetesScreen' => $this->app->kubernetesScreen,
            'settings' => $this->app->settings,
            'help' => $this->app->help,
            default => self::fail('nieznany ekran w spisie: ' . $name),
        };

        $this->app->screens->open($screen);

        return $screen;
    }

    private function zone(): Rect
    {
        return new Rect(3, 0, self::ROWS - 7, self::COLUMNS);
    }

    private function frame(ScreenInterface $screen): void
    {
        (new FrameComposer(
            new RecordingRenderer(),
            new FixedViewport(self::ROWS, self::COLUMNS),
            new StubTranslator(),
            [
                ...InputHandler::globalBindings(),
                ...InputHandler::moduleBindings($this->app->modules->shortcuts()),
            ],
        ))->render($screen, $this->app->state);
    }

    private static function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::file('alfa.txt', 12), Entry::directory('dane')])
            ->add('/home/dane', [Entry::file('spis.csv', 64)]);

        return new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }
}
