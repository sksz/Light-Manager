<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Presentation\Overlay\UndoOverlay;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * **Miejsca, w których stoi ognisko — po jednym na sekcję spisu klawiszy**
 * (krok 66).
 *
 * `bindings()` zależy od **stanu ekranu**, a nie od jego klasy: ekran Dockera
 * obiecuje co innego przy liście kontenerów, co innego przy obrazach, a co
 * innego przy logach. Podręcznik rozbija to na sekcje wedle tych właśnie
 * stanów — więc test zgodności musi umieć ekran **do stanu doprowadzić**,
 * inaczej porównywałby tabelę z pustym miejscem.
 *
 * Miara jest w tym kroku policzona: atrapa w stanie zastanym wystawia **71 ze
 * 178** udokumentowanych wierszy. Pozostałe 107 mieszka za danymi w atrapach
 * (kontenery, obrazy, osiągalny klaster, otwarta sesja) i za sekwencją
 * klawiszy — i to jest cały powód, dla którego ten plik istnieje.
 *
 * Ładunki atrap są **najmniejsze, jakie stawiają ekran we właściwym stanie**:
 * jeden kontener, jeden obraz, jeden rodzaj zasobu. Test nie sprawdza, co widać
 * w wierszach — sprawdza, co ekran obiecuje klawiszami.
 */
final class DocumentedPlaces
{
    public const NOW = 1000.0;

    private const CONTAINERS = '[{"Id":"aaaaaaaaaaaa","Names":["/sklep-api"],"Image":"sklep:1.2",'
        . '"State":"running","Status":"Up 2 hours","Created":1,"Ports":[],"Labels":{}}]';

    private const IMAGES = '[{"Id":"sha256:cccccccccccc","RepoTags":["sklep:1.2"],"Size":104857600,'
        . '"Created":1,"Containers":1}]';

    private const KUBE_CONFIG = '{"contexts":[{"name":"minikube","context":{"cluster":"minikube",'
        . '"namespace":"default"}}],"current-context":"minikube"}';

    private const KUBE_VERSIONS = '{"clientVersion":{"gitVersion":"v1.25.2"},'
        . '"serverVersion":{"gitVersion":"v1.25.3"}}';

    private const KUBE_RESOURCES = "pods  po v1 true Pod [create delete get list patch update watch] all\n"
        . 'secrets     v1 true Secret [create delete get list patch update watch]';

    private const KUBE_PODS = '{"apiVersion":"v1","kind":"List","items":[{"metadata":{"name":"web-1",'
        . '"namespace":"default","creationTimestamp":"2026-08-16T07:00:00Z"},"spec":{"nodeName":"node-1"},'
        . '"status":{"phase":"Running","containerStatuses":[{"ready":true,"restartCount":0,'
        . '"state":{"running":{}}}]}}]}';

    private const KUBE_POD = '{"apiVersion":"v1","kind":"Pod","metadata":{"name":"web-1","namespace":"default",'
        . '"creationTimestamp":"2026-08-16T07:00:00Z"},"spec":{"nodeName":"node-1","containers":[{"name":"web"}]},'
        . '"status":{"phase":"Running","containerStatuses":[{"name":"web","ready":true,"restartCount":0,'
        . '"state":{"running":{}}}]}}';

    private const HOST_ENTRY = 'a1b2c3d4e5f6';

    private function __construct()
    {
    }

    /**
     * Nazwa miejsca → wiązania, które ono obiecuje.
     *
     * @return array<string, Closure(): list<KeyBinding>>
     */
    public static function all(): array
    {
        return [
            // Tryb okienkowy, bo podręcznik wymienia `F11` wraz z zastrzeżeniem
            // „tylko w trybie okienkowym" — spis ma go zawierać, a nie pomijać.
            'globalne' => static fn (): array => InputHandler::globalBindings(true),
            'moduly' => static fn (): array => InputHandler::moduleBindings(self::app()->modules->shortcuts()),
            'lista-plikow' => static function (): array {
                $plain = self::app();
                $plain->browser->handle(KeyPress::character(' '));
                $split = self::split();
                $filtered = self::app();
                self::press($filtered, KeyPress::character('/'));
                self::press($filtered, KeyPress::character('d'));
                self::press($filtered, KeyPress::special(Key::Enter, "\r"));

                return [
                    ...$plain->browser->bindings(),
                    ...$split->browser->bindings(),
                    ...$filtered->browser->bindings(),
                ];
            },
            'drzewo' => static function (): array {
                $app = self::app();
                $app->browser->handle(KeyPress::ctrl('t'));

                return $app->browser->bindings();
            },
            'filtr' => static function (): array {
                $app = self::app();
                self::press($app, KeyPress::character('/'));

                return self::overlay($app);
            },
            'cofniecia' => static fn (): array => (new UndoOverlay(
                [new ListRow('kopiowanie')],
                [true],
                static fn (int $index): OverlayOutcome => OverlayOutcome::close(),
                new StubTranslator(),
            ))->bindings(),
            'opis-pliku' => static function (): array {
                [$app, $directory] = self::withTextFile();

                // Podgląd treści powstaje dopiero przy szerokiej klatce (próg
                // z kroku 24), a `bindings()` mówi o nim dopiero, gdy jest.
                $app->fileInfo->draw(new Rect(0, 0, 40, 120));
                $bindings = $app->fileInfo->bindings();

                $app->fileInfo->handle(KeyPress::special(Key::Tab, "\t"));
                $app->fileInfo->draw(new Rect(0, 0, 40, 120));
                $bindings = [...$bindings, ...$app->fileInfo->bindings()];

                self::forget($directory);

                return $bindings;
            },
            'playlista' => static fn (): array => self::withTracks()->audioScreen->bindings(),
            'efekty' => static function (): array {
                $app = self::withEffects();
                self::tick($app);
                $app->audioScreen->handle(KeyPress::special(Key::Tab, "\t"));

                $bindings = [];

                // Wyciszenie i zabranie pliku obiecuje się **przy zdarzeniu,
                // które plik ma** — więc ognisko musi po spisie przejść.
                foreach (range(0, 12) as $step) {
                    $bindings = [...$bindings, ...$app->audioScreen->bindings()];
                    $app->audioScreen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
                }

                return $bindings;
            },
            'ksiazka' => static fn (): array => self::app()->addressBookScreen->bindings(),
            'hosty' => static fn (): array => self::app()->sshScreen->bindings(),
            'zdalny-katalog' => static function (): array {
                $app = self::remoteApp();
                self::press($app, KeyPress::ctrl('s'));
                $app->sessions->settleConnected(new HostProfile(self::HOST_ENTRY, 'biuro', 'example.com', 22, 'anna'));
                self::tick($app);

                $bindings = $app->sshScreen->bindings();

                self::press($app, KeyPress::character('/'));
                self::press($app, KeyPress::character('r'));
                self::press($app, KeyPress::special(Key::Enter, "\r"));

                return [...$bindings, ...$app->sshScreen->bindings()];
            },
            'docker-kontenery' => static fn (): array => self::docker()->dockerScreen->bindings(),
            'docker-obrazy' => static function (): array {
                $app = self::docker();
                $app->docker->willReturn(self::IMAGES);
                self::press($app, KeyPress::special(Key::F3, "\eOR"));
                self::tick($app);

                return $app->dockerScreen->bindings();
            },
            'docker-logi' => static function (): array {
                $app = self::docker();
                self::press($app, KeyPress::special(Key::Enter, "\r"));
                self::tick($app);

                return $app->dockerScreen->bindings();
            },
            'docker-srodowiska' => static function (): array {
                $app = self::docker();
                self::press($app, KeyPress::character('e'));
                self::tick($app);

                return $app->dockerScreen->bindings();
            },
            'docker-rejestr' => static function (): array {
                $app = self::docker();
                self::press($app, KeyPress::character('r'));
                self::tick($app);

                return $app->dockerScreen->bindings();
            },
            'k8s-nieosiagalny' => static fn (): array => self::app()->kubernetesScreen->bindings(),
            'k8s-zasoby' => static fn (): array => self::cluster()->kubernetesScreen->bindings(),
            'k8s-logi' => static function (): array {
                // Logi obiecuje się **przy podzie**, więc drzewo trzeba rozwinąć,
                // wczytać zasoby i stanąć na jednym z nich. Ile kroków w dół
                // dzieli rodzaj od zasobu, zależy od tego, co klaster oddał —
                // więc szuka się położenia, z którego `l` naprawdę otwiera logi.
                foreach (range(0, 4) as $steps) {
                    $app = self::cluster();
                    self::press($app, KeyPress::special(Key::Enter, "\r"));
                    self::tick($app);

                    $app->kubectl->willReturn(self::KUBE_PODS);
                    self::press($app, KeyPress::special(Key::ArrowDown, "\eOB"));
                    self::press($app, KeyPress::special(Key::Enter, "\r"));
                    self::tick($app, 2);

                    for ($index = 0; $index < $steps; ++$index) {
                        self::press($app, KeyPress::special(Key::ArrowDown, "\eOB"));
                    }

                    // `Enter` na **zasobie** otwiera panel opisu, a dopiero opis
                    // wie, że stoi na podzie — bez tego `l` odpowiada zdaniem
                    // „to nie jest pod".
                    $app->kubectl->willReturn(self::KUBE_POD);
                    self::press($app, KeyPress::special(Key::Enter, "\r"));
                    self::tick($app, 2);

                    $app->kubectl->willReturn('pierwszy wiersz logu');
                    self::press($app, KeyPress::character('l'));
                    self::tick($app, 2);

                    $bindings = $app->kubernetesScreen->bindings();

                    foreach ($bindings as $binding) {
                        if (str_ends_with($binding->descriptionKey, '.key.follow')) {
                            return $bindings;
                        }
                    }
                }

                return [];
            },
            'k8s-klastry' => static function (): array {
                $app = self::cluster();
                self::press($app, KeyPress::character('c'));
                self::tick($app);

                return $app->kubernetesScreen->bindings();
            },
            'ustawienia' => static function (): array {
                $bindings = [];
                $app = self::app();

                foreach (range(0, 4) as $tab) {
                    foreach (range(0, 14) as $steps) {
                        $screen = self::app()->settings;

                        for ($index = 0; $index < $tab; ++$index) {
                            $screen->handle(KeyPress::special(Key::ArrowRight, "\e[C"));
                        }

                        for ($index = 0; $index < $steps; ++$index) {
                            $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
                        }

                        $bindings = [...$bindings, ...$screen->bindings()];
                    }
                }

                unset($app);

                return $bindings;
            },
            'pomoc' => static fn (): array => self::app()->help->bindings(),
            'okno-komend' => static fn (): array => self::app()->commands->bindings(),
            'menu' => static fn (): array => self::app()->menu->bindings(),
            'pytanie' => static fn (): array => (new ConfirmOverlay(
                'question',
                [],
                static fn (): OverlayOutcome => OverlayOutcome::close(),
                new StubTranslator(),
            ))->bindings(),
            'wybor' => static fn (): array => (new ChoiceOverlay(
                'question',
                [],
                [],
                static fn (string $choice): OverlayOutcome => OverlayOutcome::close(),
                new StubTranslator(),
            ))->bindings(),
            'wpisanie' => static fn (): array => (new PromptOverlay(
                'question',
                [],
                '',
                static fn (string $text): OverlayOutcome => OverlayOutcome::close(),
                new StubTranslator(),
            ))->bindings(),
            'postep' => static fn (): array => (new ProgressOverlay(
                'question',
                [],
                new WorkProgress(true, '', 0, 1),
                static fn (): WorkProgress => new WorkProgress(false, '', 1, 1),
                static fn (WorkProgress $progress): OverlayOutcome => OverlayOutcome::close(),
                static fn (WorkProgress $progress): ?Message => null,
                new StubTranslator(),
            ))->bindings(),
        ];
    }

    /** Zestaw z przypisanym dźwiękiem zdarzenia — panel efektów obiecuje wtedy wyciszenie i zabranie pliku. */
    private static function withEffects(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/home', [Entry::file('klik.ogg', 512)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            effects: new StubEffectStorage(['browser.cursor.moved' => new EffectAssignment('/home/klik.ogg')]),
        );
    }

    /**
     * Zestaw z **prawdziwym** plikiem tekstowym na dysku: podgląd treści czyta
     * z dysku, więc atrapa katalogu tu nie wystarczy.
     *
     * @return array{ScreenFixture, string}
     */
    private static function withTextFile(): array
    {
        $directory = sys_get_temp_dir() . '/lm-docs-' . bin2hex(random_bytes(6));

        mkdir($directory);
        file_put_contents($directory . '/notatka.txt', str_repeat("wiersz treści\n", 200));

        $directories = (new InMemoryDirectoryRepository())->add($directory, [Entry::file('notatka.txt', 2800)]);

        return [new ScreenFixture($directories->get(new DirectoryPath($directory), false), $directories), $directory];
    }

    private static function forget(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }

    /** Zestaw z włączonym podziałem na dwa panele — `Tab` obiecuje się dopiero wtedy. */
    private static function split(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 4096)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            new InMemorySettings(new Settings(modules: ['browser' => ['split' => true]])),
        );
    }

    /** Zestaw z playlistą — ekran dźwięku bez utworów obiecuje mniej, niż opisuje podręcznik. */
    private static function withTracks(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/home', [Entry::file('utwor.ogg', 2048)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            playlist: new StubPlaylistStorage([new PlaylistEntry('/home/utwor.ogg', 'utwor')]),
        );
    }

    /** Zestaw z jednym plikiem i jednym katalogiem — tyle, ile trzeba, żeby lista nie była pusta. */
    public static function app(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 4096)]);

        return new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    private static function remoteApp(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/home', [Entry::file('notatka.txt', 12)]);
        $book = new StubAddressBook([
            new \LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry(self::HOST_ENTRY, 'biuro', [
                'ssh' => ['host' => 'example.com', 'port' => 22, 'user' => 'anna'],
            ]),
        ]);
        $remote = new StubRemoteDirectory([
            '/home/anna' => [
                new \LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry(
                    'raport.txt',
                    \LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType::File,
                    2048,
                    1_786_795_200,
                    0o644,
                ),
            ],
        ]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            remote: $remote,
            addressBook: $book,
        );
    }

    /** Ekran Dockera przy liście kontenerów — punkt wyjścia czterech pozostałych stanów. */
    private static function docker(): ScreenFixture
    {
        $app = self::app();
        $app->docker->willReturn(self::CONTAINERS);
        self::press($app, KeyPress::ctrl('o'));
        self::tick($app, 2);

        return $app;
    }

    /**
     * Ekran klastra po odpowiedzi `kubectl` — czyli przy drzewie zasobów.
     *
     * Katalog domowy jest podmieniany na tymczasowy z pustym `~/.kube/config`,
     * bo od kroku 59 miejsce ma **dwie współrzędne** i pierwszą z nich jest
     * pytanie do dysku: bez pliku moduł nie ma czego wskazać jako kontekst.
     */
    private static function cluster(): ScreenFixture
    {
        $home = sys_get_temp_dir() . '/lm-docs-kube-' . bin2hex(random_bytes(6));

        mkdir($home . '/.kube', 0o700, true);
        touch($home . '/.kube/config');
        putenv('HOME=' . $home);

        $app = self::app();
        $app->kubectl
            ->willReturn(self::KUBE_CONFIG)
            ->willReturn(self::KUBE_VERSIONS)
            ->willReturn(self::KUBE_RESOURCES);

        self::press($app, KeyPress::ctrl('k'));
        self::tick($app, 4);

        return $app;
    }

    /** @return list<KeyBinding> */
    private static function overlay(ScreenFixture $app): array
    {
        $overlay = $app->state->overlays()->current();

        return $overlay === null ? [] : $overlay->bindings();
    }

    private static function press(ScreenFixture $app, KeyPress $key): void
    {
        $app->input->handle($key, $app->state, self::NOW);
    }

    private static function tick(ScreenFixture $app, int $times = 1): void
    {
        for ($index = 0; $index < $times; ++$index) {
            $app->ticker->tick($app->state, self::NOW);
            $app->input->advanceWork($app->state, self::NOW);
        }
    }
}
