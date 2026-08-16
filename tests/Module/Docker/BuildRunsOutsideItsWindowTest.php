<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ListensToEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Module\Docker\Application\BuildStage;
use LightManager\Module\Docker\Application\BuildWork;
use LightManager\Module\Docker\Application\DockerEvent;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\PackState;
use LightManager\Module\Docker\Application\Port\BuildContextPort;
use LightManager\Module\Docker\Infrastructure\BuildProgressReader;
use LightManager\Tests\Support\StubDockerApi;
use PHPUnit\Framework\TestCase;

/**
 * **Budowa idzie taktem modułu, a nie własnym oknem** (krok 54, D94 nr 5).
 *
 * To jest test rozstrzygnięcia, nie funkcji, i dlatego stoi osobno. Do kroku 54
 * `BuildWork::tick()` wołało wyłącznie okno postępu przez `RunsWork`, a stos
 * okien ma jedno piętro — więc budowa **stawała**, gdy jakiekolwiek inne okno
 * zajęło ekran. Dla samego modułu Dockera było to niewidoczne (jego okno stoi,
 * dopóki praca nie skończy), ale czyniło niewykonalnym zdanie, na którym stoi
 * czynność `k8s.deploy-image`: *„`Esc` przerywa czekanie, nie budowę"*.
 *
 * Sprawdzane jest dokładnie to jedno: **budowa dobiega końca i ogłasza się
 * zdarzeniem, choć żadne okno budowy nie zostało otwarte**. Gdyby ktoś przeniósł
 * posuwanie z powrotem do okna, ten test zgaśnie — a tamta czynność zaczęłaby
 * czekać na zdarzenie, które nigdy nie pada.
 *
 * Demon jest atrapą — żaden test nie rozmawia z gniazdem.
 */
final class BuildRunsOutsideItsWindowTest extends TestCase
{
    /**
     * Odpowiedź strumienia budowy: jeden krok i potwierdzenie skrótu obrazu.
     *
     * Obiekty **rozdziela znak nowej linii i kończy go ostatni**, bo tak nadaje
     * je demon, a `BuildProgressReader` zatrzymuje wiersz niedomknięty — porcja
     * urwana w połowie obiektu nie jest obiektem.
     */
    private const STREAM = "{\"stream\":\"Step 1/1 : FROM alpine\"}\n"
        . "{\"aux\":{\"ID\":\"sha256:1111111111111111111111111111111111111111111111111111111111111111\"}}\n";

    private const IMAGE_ID = 'sha256:1111111111111111111111111111111111111111111111111111111111111111';

    public function testTheBuildFinishesAndAnnouncesItselfWithNoWindowOpen(): void
    {
        $api = (new StubDockerApi())->willReturn(self::STREAM);
        $work = new BuildWork($api, new PackedAtOnce(), new BuildProgressReader());
        $events = new EventRegistry();
        $listener = new RecordingBuildListener();
        $events->useModules([new DeclaringDockerStub(), $listener]);

        $work->begin('/projekt', 'lm/proba:1');

        // Takt modułu — i **ani jedno** okno w tej sekwencji.
        $work->tick();  // pakowanie skończone, archiwum idzie do demona
        $work->tick();  // odpowiedź demona przeczytana

        self::assertSame(BuildStage::Done, $work->stage(), 'budowa kończy się bez okna');
        self::assertSame(
            self::IMAGE_ID,
            $work->imageId(),
            'skrót zbudowanego obrazu jest tym, po co pyta k8s.deploy-image',
        );

        // Ogłoszenie: dokładnie to, co robi `BuildFlow::advance()` w takcie modułu.
        self::assertSame(BuildStage::Done, $work->takeFinished());
        $events->publish(DockerEvent::BuildFinished->value);

        self::assertSame([DockerEvent::BuildFinished->value], $listener->events);
    }

    /**
     * **Wynik odbiera się raz** — inaczej takt ogłaszałby koniec budowy
     * trzydzieści razy na sekundę.
     */
    public function testTheOutcomeIsTakenOnlyOnce(): void
    {
        $api = (new StubDockerApi())->willReturn(self::STREAM);
        $work = new BuildWork($api, new PackedAtOnce(), new BuildProgressReader());

        $work->begin('/projekt', 'lm/proba:1');
        $work->tick();
        $work->tick();

        self::assertNotNull($work->takeFinished());
        self::assertNull($work->takeFinished(), 'drugi odbiór milczy');
        self::assertNull($work->takeFinished());
    }
}

/**
 * Kontekst spakowany od razu — pakowanie nie jest przedmiotem tego testu.
 *
 * **Archiwum to plik roboczy, nigdy plik istniejący**, i jest to pułapka, na
 * którą ten test już raz wpadł: `BuildWork::send()` po wysłaniu robi
 * `@unlink($archivePath)`, więc atrapa podająca `__FILE__` kasowała **sam plik
 * testu**. Ścieżka idzie przez `tempnam()`, a plik znika razem z atrapą.
 */
final class PackedAtOnce implements BuildContextPort
{
    private PackState $state;

    private string $archive = '';

    public function __construct()
    {
        $this->state = PackState::idle();
    }

    public function __destruct()
    {
        if ($this->archive !== '' && is_file($this->archive)) {
            @unlink($this->archive);
        }
    }

    public function state(): PackState
    {
        return $this->state;
    }

    public function begin(string $directory): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lm-build-');
        $this->archive = $path === false ? '' : $path;
        $this->state = PackState::packed($this->archive, 1);
    }

    public function advance(): void
    {
    }

    public function stop(): void
    {
        $this->state = PackState::idle();
    }

    public function forget(): void
    {
        $this->state = PackState::idle();
    }
}

/** Moduł, który wyłącznie deklaruje zdarzenia Dockera — rejestr odsiewa nazwy spoza deklaracji. */
final class DeclaringDockerStub implements ModuleInterface, DeclaresEvents
{
    public function id(): string
    {
        return DockerSettings::ID;
    }

    public function nameKey(): string
    {
        return 'stub';
    }

    public function descriptionKey(): string
    {
        return 'stub';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }

    /** @return list<EventDeclaration> */
    public function events(): array
    {
        return DockerEvent::declarations();
    }
}

/** Odbiorca zdarzeń, który wyłącznie je zapisuje — po to, żeby dało się je policzyć. */
final class RecordingBuildListener implements ModuleInterface, ListensToEvents
{
    /** @var list<string> */
    public array $events = [];

    public function id(): string
    {
        return 'recorder';
    }

    public function nameKey(): string
    {
        return 'recorder';
    }

    public function descriptionKey(): string
    {
        return 'recorder';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }

    public function onEvent(string $event): void
    {
        $this->events[] = $event;
    }
}
