<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Kubernetes\Application\ClusterBook;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubClusterBook;
use LightManager\Tests\Support\StubKubectl;
use PHPUnit\Framework\TestCase;

/**
 * Spis klastrów całą drogą użytkownika (krok 59).
 *
 * Przebieg sprawdza kryteria ukończenia kroku, które widać z zewnątrz:
 * **dwa klastry z dwóch plików `kubeconfig` stoją na jednej liście**,
 * przełączenie między kontekstami tej samej nazwy **nie miesza danych**,
 * `--kubeconfig` jedzie w każdym wywołaniu, wpis wskazujący nieistniejący plik
 * ma własne zdanie, a wpisu czytanego z cudzego pliku nie da się skasować.
 *
 * **Klastra nie ma tu ani przez chwilę i `kubectl` nie powstaje jako proces** —
 * kryterium odziedziczone po kroku 52. Pliki `kubeconfig` są za to prawdziwe
 * (puste, w katalogu tymczasowym), bo brak pliku rozstrzyga się `is_file()`,
 * a nie odpowiedzią klienta.
 */
final class ClusterBookFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    /** Plik domyślny: dwa konteksty, `default` wśród nich. */
    private const CONFIG_DEFAULT = '{"contexts":[{"name":"default","context":{}},{"name":"minikube","context":{}}],'
        . '"current-context":"minikube"}';

    /** Plik klienta: kontekst **tej samej nazwy**, co w domyślnym. */
    private const CONFIG_CLIENT = '{"contexts":[{"name":"default","context":{}}],"current-context":"default"}';

    private const VERSIONS = '{"clientVersion":{"gitVersion":"v1.25.2"},'
        . '"serverVersion":{"gitVersion":"v1.25.3"}}';

    private const RESOURCES = 'pods  po v1 true Pod [create delete get list patch update watch] all';

    private const PODS_HERE = '{"apiVersion":"v1","kind":"List","items":[{"metadata":{"name":"tutejszy",'
        . '"namespace":"default","creationTimestamp":"2026-08-18T07:00:00Z"},"spec":{"nodeName":"node-1"},'
        . '"status":{"phase":"Running","containerStatuses":[{"ready":true,"restartCount":0,'
        . '"state":{"running":{}}}]}}]}';

    private const PODS_THERE = '{"apiVersion":"v1","kind":"List","items":[{"metadata":{"name":"tamtejszy",'
        . '"namespace":"default","creationTimestamp":"2026-08-18T07:00:00Z"},"spec":{"nodeName":"node-9"},'
        . '"status":{"phase":"Running","containerStatuses":[{"ready":true,"restartCount":0,'
        . '"state":{"running":{}}}]}}]}';

    private StubKubectl $kubectl;

    private StubClusterBook $book;

    /** Numer wiersza kursora w spisie — prowadzony równolegle do klatki. */
    private int $cursor = 0;

    private ScreenFixture $app;

    private string $home = '';

    private string|false $previousHome = false;

    private string|false $previousKubeconfig = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->previousKubeconfig = getenv('KUBECONFIG');
        $this->home = sys_get_temp_dir() . '/lm-book-flow-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home . '/.kube', 0o700, true);
        touch($this->home . '/.kube/config');
        touch($this->home . '/klient.yaml');
        putenv('HOME=' . $this->home);
        putenv('KUBECONFIG');

        $this->kubectl = new StubKubectl();
        $this->book = new StubClusterBook();
        $this->app = $this->fixture();
    }

    protected function tearDown(): void
    {
        foreach ([$this->home . '/.kube/config', $this->home . '/klient.yaml'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->home . '/.kube');
        rmdir($this->home);
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);
        putenv($this->previousKubeconfig === false ? 'KUBECONFIG' : 'KUBECONFIG=' . $this->previousKubeconfig);
    }

    /**
     * **Miara pierwsza kroku**: dwa klastry z dwóch różnych plików stoją na
     * jednej liście, a pochodzenie każdego widać.
     */
    public function testTwoClustersFromTwoFilesStandOnOneList(): void
    {
        $this->withTwoPlaces();
        $this->openClusterList();

        $texts = $this->drawCurrent();

        self::assertContains('klient', $texts, 'wpis własny wskazujący plik spoza standardowych ścieżek');
        self::assertContains('minikube', $texts, 'kontekst czytany z pliku domyślnego');
        $rows = $this->app->state->queries()->ask('k8s.clusters')->rows();
        $paths = [];
        $origins = [];

        foreach ($rows as $row) {
            $name = $row['name'] ?? null;
            $paths[] = $row['kubeconfig'] ?? null;

            if (is_string($name)) {
                $origins[$name] = $row['origin'] ?? null;
            }
        }

        self::assertContains(
            $this->home . '/klient.yaml',
            $paths,
            'ścieżka pliku wychodzi wierszami kwerendy (plan, punkt 8)',
        );
        self::assertSame('own', $origins['klient'] ?? null, 'pochodzenie wpisu własnego');
        self::assertSame('config', $origins['minikube'] ?? null, 'pochodzenie wpisu czytanego z pliku domyślnego');
    }

    /**
     * **Miara druga kroku, ta wymierna**: dwa wpisy o kontekstach tej samej
     * nazwy w dwóch plikach są dwoma miejscami, a przełączenie tam i z powrotem
     * **nie miesza list**.
     *
     * To jest ten warunek, który kod sprzed kroku 59 łamał po cichu: klucz stanu
     * brał się z nazwy kontekstu, więc `default` z dwóch plików dzielił drzewo,
     * pamięć podręczną i otwarty opis.
     */
    public function testTwoContextsOfTheSameNameDoNotMixTheirResources(): void
    {
        $this->withTwoPlaces();

        // Pierwsze miejsce: wpis własny wskazujący plik klienta.
        $this->openClusterList();
        $this->kubectl->willReturn(self::VERSIONS)->willReturn(self::RESOURCES)->willReturn(self::PODS_THERE);
        $this->selectRow('klient');
        $this->openPods();

        self::assertStringContainsString('tamtejszy', $this->joined(), 'pod z pliku klienta');
        self::assertStringContainsString(
            "'--kubeconfig' '" . $this->home . "/klient.yaml'",
            $this->lastCommandArguments(),
            'każde wywołanie niesie plik, nie tylko kontekst',
        );

        // Drugie miejsce: kontekst `default` z pliku domyślnego — ta sama nazwa
        // kontekstu, inny plik, więc inne miejsce.
        $this->openClusterList();
        $this->kubectl->willReturn(self::VERSIONS)->willReturn(self::RESOURCES)->willReturn(self::PODS_HERE);
        $this->selectRow('default');
        $this->openPods();

        $texts = $this->joined();

        self::assertStringContainsString('tutejszy', $texts, 'pod z pliku domyślnego');
        self::assertStringNotContainsString('tamtejszy', $texts, 'listy dwóch miejsc nie mają prawa się zmieszać');
        self::assertStringContainsString(
            "'--kubeconfig' '" . $this->home . "/.kube/config'",
            $this->lastCommandArguments(),
        );
    }

    /**
     * **Wpis wskazujący nieistniejący plik ma własne zdanie** — odróżnialne od
     * „klaster nie odpowiada", bo pod tamtym nie widać literówki w ścieżce.
     */
    public function testAnEntryPointingAtAMissingFileSaysWhichFileIsMissing(): void
    {
        $this->bookWith(ClusterProfile::of('zdalny', $this->home . '/nie-ma-mnie.yaml', 'prod'));
        $this->kubectl->willReturn(self::CONFIG_DEFAULT);

        $this->press(KeyPress::ctrl('k'));
        $this->pump(4);

        $texts = $this->joined();

        self::assertStringContainsString('module.k8s.stage.missingFile', $texts);
        self::assertStringContainsString(
            $this->home . '/nie-ma-mnie.yaml',
            $this->flattened(),
            'zdanie mówi, czego nie ma',
        );
        self::assertStringNotContainsString('module.k8s.stage.unreachable', $texts, 'to nie jest cisza klastra');
    }

    /** **Wpis z kontekstem, którego w pliku nie ma**, też ma własne zdanie. */
    public function testAnEntryWithAnUnknownContextSaysSo(): void
    {
        $this->bookWith(ClusterProfile::of('literowka', $this->home . '/.kube/config', 'minikub'));
        $this->kubectl->willReturn(self::CONFIG_DEFAULT);

        $this->press(KeyPress::ctrl('k'));
        $this->pump(4);

        $texts = $this->joined();

        self::assertStringContainsString('module.k8s.stage.unknownContext', $texts);
        self::assertStringContainsString('minikub', $texts, 'zdanie mówi, którego kontekstu nie ma');
    }

    /**
     * **Moduł nadal nie pisze do `kubeconfig`** — wpisu czytanego nie da się ani
     * skasować, ani zmienić, a odmowa jest zdaniem, nie ciszą.
     */
    public function testAReadEntryRefusesRemovalAndEditingWithASentence(): void
    {
        $this->withTwoPlaces();
        $this->openClusterList();
        $this->moveTo('minikube');

        $this->press(KeyPress::special(Key::F8, "\e[19~"));

        self::assertNull($this->app->state->overlays()->current(), 'okna pytania nie ma — nie ma czego pytać');
        self::assertSame(
            'module.k8s.cluster.readEntry(name=minikube)',
            $this->app->state->message()?->text,
        );

        $this->press(KeyPress::special(Key::F4, "\e[14~"));

        self::assertNull($this->app->state->overlays()->current(), 'zmiany też nie przyjmuje');
        self::assertNull($this->book->saved?->find('minikube'), 'wpis czytany nie wchodzi do książki');

        $config = (string) file_get_contents($this->home . '/.kube/config');

        self::assertSame('', $config, 'plik kubeconfig zostaje pusty — moduł do niego nie pisze');
    }

    /** `F7` prowadzi łańcuchem okien, a zapisany wpis staje w spisie. */
    public function testANewEntryIsAddedThroughTheChainOfPrompts(): void
    {
        $this->kubectl->willReturn(self::CONFIG_DEFAULT);
        $this->openClusterList();

        $this->press(KeyPress::special(Key::F7, "\e[18~"));

        foreach (['nazwa' => 'produkcja', 'plik' => null, 'kontekst' => 'minikube'] as $value) {
            self::assertSame('prompt', $this->app->state->overlays()->current()?->id());

            if (is_string($value)) {
                $this->clearField();
                $this->type($value);
            }

            $this->press(KeyPress::special(Key::Enter, "\r"));
        }

        self::assertSame('module.k8s.cluster.saved(name=produkcja)', $this->app->state->message()?->text);

        $saved = $this->book->saved;

        self::assertNotNull($saved, 'książka została zapisana');
        $entry = $saved->find('produkcja');

        self::assertNotNull($entry, 'wpis trafił do książki');
        self::assertSame(
            $this->home . '/.kube/config',
            $entry->kubeconfig,
            'plik domyślny był wartością domyślną okna',
        );
    }

    /**
     * **Migracja z ustawień nie gubi zapamiętanego miejsca** (plan, punkt 7):
     * kontekst zapamiętany pozycją ustawień staje się wpisem książki przy
     * pierwszym takcie modułu.
     */
    public function testTheRememberedPlaceMigratesFromSettingsIntoTheBook(): void
    {
        $this->book = new StubClusterBook(new ClusterBook(), null, fresh: true);
        $this->app = $this->fixture();
        $settings = $this->app->settingsStore;
        $settings->save(
            $settings->current()
                ->withModuleValue('k8s', 'context', 'minikube')
                ->withModuleValue('k8s', 'namespace', 'produkcja'),
        );

        $this->kubectl->willReturn(self::CONFIG_DEFAULT);
        $this->press(KeyPress::ctrl('k'));
        $this->pump(2);

        $saved = $this->book->saved;

        self::assertNotNull($saved, 'migracja zapisała książkę');
        $entry = $saved->find('minikube');

        self::assertNotNull($entry, 'zapamiętany kontekst stał się wpisem książki');
        self::assertSame($this->home . '/.kube/config', $entry->kubeconfig);
        self::assertSame('produkcja', $entry->namespace, 'przestrzeń nazw też przeżyła');
        self::assertSame('minikube', $saved->current(), 'i pozostaje wyborem bieżącym');
    }

    /** Dwa pliki i wpis własny wskazujący ten drugi — punkt wyjścia większości prób. */
    private function withTwoPlaces(): void
    {
        $this->bookWith(ClusterProfile::of('klient', $this->home . '/klient.yaml', 'default'));
    }

    private function bookWith(ClusterProfile $entry): void
    {
        $book = new ClusterBook();
        $book->add($entry);
        $book->makeCurrent($entry->name);
        $this->book = new StubClusterBook($book);
        $this->app = $this->fixture();
    }

    /**
     * Wejście na spis **czyta pliki od nowa** (jak `Ctrl`+`R`), więc atrapa
     * dostaje przy każdym wejściu komplet odpowiedzi na wszystkie znane pliki.
     */
    private function openClusterList(): void
    {
        if ($this->app->screens->current()->id() !== 'k8s') {
            $this->answerConfigs();
            $this->press(KeyPress::ctrl('k'));
            $this->pump(6);
        }

        $this->answerConfigs();
        $this->press(KeyPress::character('c'));
        $this->pump(6);
    }

    /** Odpowiedzi na odczyt obu plików, w kolejności: domyślny, potem klienta. */
    private function answerConfigs(): void
    {
        $this->kubectl->willReturn(self::CONFIG_DEFAULT)->willReturn(self::CONFIG_CLIENT);
    }

    /** Ustawia kursor na wierszu o podanej nazwie — spis jest tabelą, więc idziemy strzałkami. */
    private function moveTo(string $name): void
    {
        $this->press(KeyPress::special(Key::Home, "\e[H"));

        for ($step = 0; $step < 12; ++$step) {
            if ($this->rowUnderCursor() === $name) {
                return;
            }

            $this->press(KeyPress::special(Key::ArrowDown, "\e[B"));
        }

        self::fail('nie ma wiersza o nazwie ' . $name);
    }

    private function selectRow(string $name): void
    {
        $this->moveTo($name);
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->press(KeyPress::special(Key::Escape, "\e"));
        $this->pump(4);
    }

    /** Rozwija grupę `core`, potem rodzaj `pods` — dwa `Enter`y w drzewie. */
    private function openPods(): void
    {
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->pump(2);
        $this->press(KeyPress::special(Key::ArrowDown, "\eOB"));
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->pump(2);
    }

    /**
     * Nazwa wiersza pod kursorem — z kwerendy, a nie z klatki.
     *
     * Tabela ucina kolumny do ich szerokości, więc nazwa narysowana bywa
     * krótsza od nazwy wpisu; numer wiersza test prowadzi sam, tą samą
     * arytmetyką, co postać ekranu.
     */
    private function rowUnderCursor(): ?string
    {
        $names = [];

        foreach ($this->app->state->queries()->ask('k8s.clusters')->rows() as $row) {
            $name = $row['name'] ?? null;
            $names[] = is_string($name) ? $name : '';
        }

        return $names[$this->cursor] ?? null;
    }

    private function press(KeyPress $key): void
    {
        // Kursor spisu liczymy równolegle, bo klatka nie mówi wprost, na którym
        // wierszu stoi — a tabela ucina nazwy do szerokości kolumny.
        if ($key->key === Key::Home) {
            $this->cursor = 0;
        } elseif ($key->key === Key::ArrowDown) {
            ++$this->cursor;
        } elseif ($key->key === Key::ArrowUp) {
            $this->cursor = max(0, $this->cursor - 1);
        }

        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    private function type(string $text): void
    {
        foreach (str_split($text) as $letter) {
            $this->app->input->handle(KeyPress::character($letter), $this->app->state, self::NOW);
        }
    }

    /** Czyści pole tekstowe okna — wartości domyślne bywają wypełnione. */
    private function clearField(): void
    {
        for ($i = 0; $i < 120; ++$i) {
            $this->app->input->handle(KeyPress::special(Key::Backspace, "\x7f"), $this->app->state, self::NOW);
        }
    }

    private function pump(int $times): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->app->ticker->tick($this->app->state, self::NOW);
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }
    }

    private function lastCommandArguments(): string
    {
        $call = $this->kubectl->calls === [] ? null : $this->kubectl->calls[count($this->kubectl->calls) - 1];

        if ($call === null) {
            return '';
        }

        $parts = [];

        foreach ([...$call->arguments, '--kubeconfig', $call->place->kubeconfig ?? ''] as $argument) {
            $parts[] = escapeshellarg($argument);
        }

        return implode(' ', $parts);
    }

    /** @return list<string> */
    private function drawCurrent(): array
    {
        return self::textsOf($this->app->screens->current()->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
    }

    private function joined(): string
    {
        return implode("\n", $this->drawCurrent());
    }

    /**
     * Klatka bez łamań wierszy — zdanie stanu **zawija się** w wąskim panelu,
     * więc ścieżki szuka się w tekście sklejonym z powrotem.
     */
    private function flattened(): string
    {
        return implode('', $this->drawCurrent());
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = trim($primitive->text);
            }
        }

        return $texts;
    }

    private function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            kubectl: $this->kubectl,
            clusterBook: $this->book,
        );
    }
}
