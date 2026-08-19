<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAddressBook;
use PHPUnit\Framework\TestCase;

/**
 * Książka adresowa całą drogą użytkownika (krok 60).
 *
 * Przebieg sprawdza **zasadę kroku**, a nie ekran: rozdział nie jest niczyją
 * własnością, jeden zestaw komend i kwerend obsługuje wszystkie rozdziały,
 * a książka używa go tak samo, jak moduły.
 *
 * Deklaracje idą tu **komendami z rejestru** — dokładnie tak, jak zrobiłby to
 * moduł w swoim takcie. Nie ma w tym udawania: to jest ta sama droga, ten sam
 * rejestr i te same argumenty; test nie zna ani jednego typu książki poza jej
 * wpisem, bo poza nim nic z niej nie wychodzi.
 */
final class AddressBookFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    private ScreenFixture $app;

    private StubAddressBook $storage;

    protected function setUp(): void
    {
        $this->storage = new StubAddressBook();
        $this->app = $this->fixture();
    }

    /** `Ctrl`+`W` otwiera książkę, a drugie naciśnięcie ją zamyka. */
    public function testTheShortcutOpensAndClosesTheBook(): void
    {
        $this->open();

        self::assertSame('address-book', $this->app->screens->current()->id());

        $this->press(KeyPress::ctrl('w'));

        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /**
     * **Książka używa własnego mechanizmu jak każdy inny** — w takcie zapowiada
     * użycie rozdziału `general` i jego pól.
     */
    public function testTheBookDeclaresItsOwnChapterThroughTheSameCommands(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);

        // Kolejność zakładek jest **kolejnością deklaracji**, a ta idzie za
        // kolejnością modułów — rozdział książki nie jest przez to pierwszy
        // i nie ma powodu, żeby był.
        $rows = $this->rowsOf('address-book.chapters');
        $general = null;

        foreach ($rows as $row) {
            if (($row['chapter'] ?? null) === 'general') {
                $general = $row;
            }
        }

        self::assertNotNull($general, 'książka zapowiada użycie własnego rozdziału');
        self::assertTrue($general['declared'] ?? false);
        self::assertSame(2, $general['fields'] ?? null);
    }

    /**
     * Deklaracja jest **jednostronna**: dwie komendy i ani jednego pytania
     * z powrotem. Zakładka pojawia się od razu, a kolumny biorą się z pól.
     */
    public function testDeclaredChapterBecomesATabWithItsOwnColumns(): void
    {
        $this->declareSshChapter();
        // Kolumny widać dopiero, gdy jest co pokazać — a **do rozdziału należy
        // się przez wartości**: wpis bez ani jednej wartości w `ssh` nie stanie
        // w jego zakładce (i o to chodzi).
        $id = $this->addEntry('biuro');
        $this->execute('address-book.set', ['entry' => $id, 'chapter' => 'ssh', 'field' => 'host', 'value' => '10.0.0.5']);
        $this->open();

        $texts = $this->drawCurrent();

        self::assertContains('module.ssh.name', $texts, 'rozdział ma zakładkę');

        $this->press(KeyPress::special(Key::ArrowRight, "\e[C"));
        $this->press(KeyPress::special(Key::ArrowRight, "\e[C"));
        // Nagłówki bywają **ucięte do szerokości kolumny**, a atrapa tłumacza
        // oddaje długi klucz zamiast krótkiego napisu — więc sprawdzamy
        // początek, tak jak zrobiłoby to oko patrzące na tabelę.
        self::assertTrue($this->shows('module.ssh.field.ho'), 'kolumna bierze się z deklaracji pola');
        self::assertTrue($this->shows('module.ssh.field.po'));
    }

    /** Deklaracja powtórzona nic nie zmienia, a sprzeczna nie przestawia pola. */
    public function testRepeatedDeclarationIsIdleAndConflictingOneIsRefused(): void
    {
        $this->declareSshChapter();
        $this->declareSshChapter();

        self::assertCount(3, $this->rowsOf('address-book.fields', ['chapter' => 'ssh']));

        $outcome = $this->execute('address-book.field', [
            'chapter' => 'ssh',
            'field' => 'port',
            'label' => 'inna.etykieta',
            'kind' => 'text',
        ]);

        self::assertNotNull($outcome);
        self::assertStringContainsString('module.address-book.field.conflict', $outcome);
        self::assertSame('number', $this->rowsOf('address-book.fields', ['chapter' => 'ssh'])[1]['kind'] ?? null);
    }

    /** `F7` na ekranie i `address-book.add` w oknie komend to **jedno** wywołanie. */
    public function testAddingAnEntryFromTheScreenGoesThroughTheCommand(): void
    {
        $this->open();
        $this->press(KeyPress::special(Key::F7, "\e[18~"));
        $this->type('biuro');
        $this->press(KeyPress::special(Key::Enter, "\r"));

        $entries = $this->rowsOf('address-book.entries');

        self::assertCount(1, $entries);
        self::assertSame('biuro', $entries[0]['name'] ?? null);
        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', (string) ($entries[0]['id'] ?? ''));
        self::assertSame(1, $this->storage->saveCount, 'wpis trafił do sekcji stanu');
    }

    /**
     * **Cudzy rozdział czyta się tą samą kwerendą, co własny** — i to jest cała
     * zasada tego kroku w jednym zdaniu.
     */
    public function testAnyoneReadsAnyChapterWithTheSameQuery(): void
    {
        $this->declareSshChapter();
        $id = $this->addEntry('biuro');
        $this->execute('address-book.set', ['entry' => $id, 'chapter' => 'ssh', 'field' => 'host', 'value' => '10.0.0.5']);

        $rows = $this->rowsOf('address-book.entries', ['chapter' => 'ssh']);

        self::assertSame('10.0.0.5', $rows[0]['host'] ?? null);
        self::assertSame('10.0.0.5', $this->rowsOf('address-book.entry', [
            'entry' => $id,
            'chapter' => 'ssh',
        ])[0]['host'] ?? null);
    }

    /**
     * Pole maskowane **nie wchodzi do wierszy spisu**, ale wychodzi kwerendą
     * przeznaczoną do tego wprost — bo dostęp jest jednakowy, a zasłona broni
     * przed hałasem, nie przed modułem (D104 nr 6).
     */
    public function testSecretStaysOutOfListRowsAndComesOutOfItsOwnQuery(): void
    {
        $this->declareSshChapter();
        $id = $this->addEntry('biuro');
        $this->execute('address-book.set', [
            'entry' => $id,
            'chapter' => 'ssh',
            'field' => 'keyPath',
            'value' => '/home/anna/.ssh/id_ed25519',
        ]);

        $rows = $this->rowsOf('address-book.entries', ['chapter' => 'ssh']);

        self::assertSame('set', $rows[0]['keyPath'] ?? null);
        self::assertStringNotContainsString('id_ed25519', json_encode($rows, JSON_THROW_ON_ERROR));

        $value = $this->rowsOf('address-book.value', [
            'entry' => $id,
            'chapter' => 'ssh',
            'field' => 'keyPath',
        ]);

        self::assertSame('/home/anna/.ssh/id_ed25519', $value[0]['value'] ?? null);
    }

    /**
     * Rozdział, którego dziś **nikt nie deklaruje**, nie znika: wartości są
     * widoczne surowymi kluczami i dają się zmienić.
     */
    public function testUndeclaredChapterKeepsItsValuesReadableAndWritable(): void
    {
        $id = $this->addEntry('biuro');
        $this->execute('address-book.set', ['entry' => $id, 'chapter' => 'stary', 'field' => 'cokolwiek', 'value' => 'tak']);

        $chapters = $this->rowsOf('address-book.chapters');
        $names = array_column($chapters, 'chapter');

        self::assertContains('stary', $names);
        self::assertFalse($chapters[(int) array_search('stary', $names, true)]['declared'] ?? true);
        self::assertSame('tak', $this->rowsOf('address-book.entries', ['chapter' => 'stary'])[0]['cokolwiek'] ?? null);
    }

    /**
     * **Zmiana nazwy nie psuje odniesienia** — powód, dla którego tożsamością
     * wpisu jest identyfikator, a nie nazwa.
     */
    public function testRenamingAnEntryLeavesReferencesToItIntact(): void
    {
        $this->execute('address-book.field', [
            'chapter' => 'docker',
            'field' => 'target',
            'label' => 'module.docker.field.target',
            'kind' => 'entry',
        ]);

        $host = $this->addEntry('biuro');
        $tunnel = $this->addEntry('tunel');
        $this->execute('address-book.set', [
            'entry' => $tunnel,
            'chapter' => 'docker',
            'field' => 'target',
            'value' => $host,
        ]);

        $this->execute('address-book.rename', ['entry' => $host, 'name' => 'biuro-nowe']);

        $rows = $this->rowsOf('address-book.entry', ['entry' => $tunnel, 'chapter' => 'docker']);

        self::assertSame($host, $rows[0]['target'] ?? null);
    }

    /** Odniesienie do wpisu, którego nie ma, jest odrzucane ze zdaniem. */
    public function testReferenceToAMissingEntryIsRefused(): void
    {
        $this->execute('address-book.field', [
            'chapter' => 'docker',
            'field' => 'target',
            'label' => 'module.docker.field.target',
            'kind' => 'entry',
        ]);
        $tunnel = $this->addEntry('tunel');

        $outcome = $this->execute('address-book.set', [
            'entry' => $tunnel,
            'chapter' => 'docker',
            'field' => 'target',
            'value' => 'a1b2c3d4e5f6',
        ]);

        self::assertNotNull($outcome);
        self::assertStringContainsString('module.address-book.value.entry', $outcome);
    }

    /** Wartość spoza rodzaju wraca zdaniem, a wpis zostaje nietknięty. */
    public function testValueOutsideTheKindIsRefused(): void
    {
        $this->declareSshChapter();
        $id = $this->addEntry('biuro');

        $outcome = $this->execute('address-book.set', [
            'entry' => $id,
            'chapter' => 'ssh',
            'field' => 'port',
            'value' => 'dwa tysiące',
        ]);

        self::assertNotNull($outcome);
        self::assertStringContainsString('module.address-book.value.number', $outcome);
    }

    /** Zawężenie spisu działa po nazwie i po widocznych wartościach. */
    public function testFilterNarrowsTheList(): void
    {
        $this->addEntry('biuro');
        $this->addEntry('dom');
        $this->open();

        self::assertCount(2, $this->rowsOf('address-book.entries'));

        $this->press(KeyPress::ctrl('f'));
        $this->type('biu');
        $this->press(KeyPress::special(Key::Enter, "\r"));

        $texts = $this->drawCurrent();

        self::assertContains('biuro', $texts);
        self::assertNotContains('dom', $texts, 'zawężenie zdejmuje wiersze, które nie pasują');
    }

    /** `F6` przestawia kolumnę porządkującą, a nagłówek mówi, którą i w którą stronę. */
    public function testSortingIsVisibleInTheHeader(): void
    {
        $this->addEntry('dom');
        $this->addEntry('biuro');
        $this->open();

        $texts = $this->drawCurrent();
        $names = array_values(array_filter($texts, static fn (string $text): bool => in_array($text, ['dom', 'biuro'], true)));

        self::assertSame(['biuro', 'dom'], $names, 'porządek alfabetyczny po nazwie');
        self::assertContains('module.address-book.column.name ▲', $texts);
    }

    /** `address-book.forget` czyści rozdział we wszystkich wpisach — i pyta oknem. */
    public function testForgettingAChapterSweepsEveryEntry(): void
    {
        $this->declareSshChapter();
        $first = $this->addEntry('biuro');
        $second = $this->addEntry('dom');
        $this->execute('address-book.set', ['entry' => $first, 'chapter' => 'ssh', 'field' => 'host', 'value' => '10.0.0.5']);
        $this->execute('address-book.set', ['entry' => $second, 'chapter' => 'ssh', 'field' => 'host', 'value' => '10.0.0.6']);

        $command = $this->app->commandRegistry->find('address-book.forget');
        self::assertInstanceOf(\LightManager\Presentation\Ui\Command\OpensOverlay::class, $command);

        $outcome = $command->overlayFor(new CommandInput(['chapter' => 'ssh']));
        self::assertNotNull($outcome?->next, 'usunięcie rozdziału pyta oknem');

        $this->app->state->overlays()->open($outcome->next);
        $this->press(KeyPress::special(Key::ArrowLeft, "\e[D"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        foreach ($this->rowsOf('address-book.entries') as $row) {
            self::assertSame('', $row['chapters'] ?? null);
        }
    }

    /**
     * **Stopka nie powtarza opisu** — dwa klawisze robiące to samo są jednym
     * wiązaniem (reguła 11p).
     *
     * Test powstał z usterki widocznej wyłącznie w klatce: stopka wypisywała
     * „Enter zmień · F4 zmień", jakby to były dwie różne czynności. Złapało to
     * dopiero obejrzenie ekranu pod XTermem, więc od tej pory pilnuje tego test.
     */
    public function testTheFooterNeverRepeatsADescription(): void
    {
        $this->open();

        $keys = [];

        foreach ($this->app->screens->current()->bindings() as $binding) {
            $keys[] = $binding->descriptionKey;
        }

        self::assertSame(array_unique($keys), $keys, 'ten sam opis stoi w stopce dwa razy');
    }

    /**
     * **Zdanie-miara całego kroku: jeden wpis, trzy rozdziały, trzej
     * czytelnicy** (krok 60).
     *
     * Ten sam wpis niesie naraz pola `ssh`, `docker` i `k8s`; każdy z modułów
     * widzi w nim **tylko swoje**, a adres poprawia się w jednym miejscu.
     * Rozdziały deklarują się same, w takcie — test nie zakłada ich ręcznie.
     */
    public function testOneEntryCarriesThreeChaptersAtOnce(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);

        $id = $this->addEntry('biuro');
        $this->execute('address-book.set', ['entry' => $id, 'chapter' => 'ssh', 'field' => 'host', 'value' => '10.0.0.5']);
        $this->execute('address-book.set', ['entry' => $id, 'chapter' => 'docker', 'field' => 'kind', 'value' => 'tunnel']);
        $this->execute('address-book.set', ['entry' => $id, 'chapter' => 'k8s', 'field' => 'context', 'value' => 'ca-dev']);

        $chapters = array_column($this->rowsOf('address-book.chapters'), 'chapter');

        self::assertContains('ssh', $chapters, 'moduł sesji zdalnej zapowiedział swój rozdział');
        self::assertContains('docker', $chapters);
        self::assertContains('k8s', $chapters);

        // Każdy rozdział widzi **swoje** pola tego samego wpisu — i ani jednego
        // cudzego, bo kolumny biorą się z deklaracji.
        $ssh = $this->rowsOf('address-book.entry', ['entry' => $id, 'chapter' => 'ssh'])[0] ?? [];
        $docker = $this->rowsOf('address-book.entry', ['entry' => $id, 'chapter' => 'docker'])[0] ?? [];
        $k8s = $this->rowsOf('address-book.entry', ['entry' => $id, 'chapter' => 'k8s'])[0] ?? [];

        self::assertSame('10.0.0.5', $ssh['host'] ?? null);
        self::assertArrayNotHasKey('kind', $ssh, 'rozdział ssh nie widzi pól Dockera');

        self::assertSame('tunnel', $docker['kind'] ?? null);
        self::assertArrayNotHasKey('host', $docker);

        self::assertSame('ca-dev', $k8s['context'] ?? null);
        self::assertArrayNotHasKey('kind', $k8s);

        // A z boku widać, że to **jeden** wpis, a nie trzy.
        self::assertSame(
            'ssh,docker,k8s',
            $this->rowsOf('address-book.entries')[0]['chapters'] ?? null,
        );
    }

    /**
     * **Zakładka rozdziału pokazuje wyłącznie wpisy tego rozdziału** — usterka
     * zgłoszona po etapie trzecim.
     *
     * Klaster `minikube` ma wartości w `k8s` i **nie ma czego szukać
     * w zakładce Dockera**; przed poprawką stał tam z pustymi kolumnami, bo
     * kwerenda z argumentem rozdziału oddawała całą książkę.
     */
    public function testAChapterTabShowsOnlyItsOwnEntries(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);

        $cluster = $this->addEntry('minikube');
        $this->execute('address-book.set', [
            'entry' => $cluster,
            'chapter' => 'k8s',
            'field' => 'context',
            'value' => 'minikube',
        ]);

        $daemon = $this->addEntry('serwerownia');
        $this->execute('address-book.set', [
            'entry' => $daemon,
            'chapter' => 'docker',
            'field' => 'kind',
            'value' => 'tunnel',
        ]);

        self::assertSame(['minikube'], $this->namesOf('k8s'));
        self::assertSame(['serwerownia'], $this->namesOf('docker'));
        self::assertSame([], $this->namesOf('ssh'), 'rozdział bez wartości nie ma wpisów');

        // Bez argumentu odpowiedzią jest **cała** książka — oba wpisy naraz.
        self::assertSame(['minikube', 'serwerownia'], $this->namesOf(''));
    }

    /**
     * Wpis dopisany **na zakładce rozdziału** trafia do niego, a nie znika:
     * łańcuch prowadzi od nazwy przez pola (poprawka do usterki wyżej).
     */
    public function testAnEntryAddedOnAChapterTabStaysThere(): void
    {
        $this->declareSshChapter();
        $this->open();
        $this->press(KeyPress::special(Key::ArrowRight, "\e[C"));

        $this->press(KeyPress::special(Key::F7, "\e[18~"));
        $this->type('serwerownia');
        $this->press(KeyPress::special(Key::Enter, "\r"));

        // Po nazwie łańcuch pyta o pierwsze pole rozdziału — i dopiero ono
        // wprowadza wpis do zakładki.
        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());
        $this->type('10.0.0.9');
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertContains('serwerownia', $this->namesOf('ssh'));
    }

    /**
     * **Odniesienie pokazuje nazwę wskazywanego wpisu, nie jego
     * identyfikator** — wyszło przy oglądaniu klatki po etapie trzecim.
     *
     * Identyfikatorem się wskazuje, nazwą się mówi; wpis, którego już nie ma,
     * zostaje identyfikatorem, bo to jedyne, co o nim wiadomo.
     */
    public function testAReferenceIsShownByTheNameOfItsTarget(): void
    {
        $this->execute('address-book.field', [
            'chapter' => 'docker',
            'field' => 'target',
            'label' => 'module.docker.field.target',
            'kind' => 'entry',
        ]);

        $host = $this->addEntry('biuro');
        $tunnel = $this->addEntry('serwer');
        $this->execute('address-book.set', [
            'entry' => $tunnel,
            'chapter' => 'docker',
            'field' => 'target',
            'value' => $host,
        ]);

        $this->open();
        $this->press(KeyPress::special(Key::ArrowRight, "\e[C"));

        $texts = $this->drawCurrent();

        self::assertContains('biuro', $texts, 'w kolumnie stoi nazwa wskazywanego wpisu');
        self::assertNotContains($host, $texts, 'a nie jego identyfikator');
    }

    /** Wpisów nie widać, gdy książka jest pusta — a ekran mówi, co zrobić. */
    public function testEmptyBookSaysWhatToDo(): void
    {
        $this->open();

        self::assertContains('module.address-book.empty', $this->drawCurrent());
    }

    /**
     * Nazwy wpisów widziane przez kwerendę spisu — pusty rozdział znaczy „cała
     * książka".
     *
     * @return list<string>
     */
    private function namesOf(string $chapter): array
    {
        $names = [];

        foreach ($this->rowsOf('address-book.entries', $chapter === '' ? [] : ['chapter' => $chapter]) as $row) {
            $names[] = (string) ($row['name'] ?? '');
        }

        return $names;
    }

    /** Czy na ekranie stoi napis zaczynający się tak — nagłówki bywają ucięte. */
    private function shows(string $prefix): bool
    {
        foreach ($this->drawCurrent() as $text) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function declareSshChapter(): void
    {
        $this->execute('address-book.chapter', ['chapter' => 'ssh', 'title' => 'module.ssh.name']);
        $this->execute('address-book.field', [
            'chapter' => 'ssh',
            'field' => 'host',
            'label' => 'module.ssh.field.host',
            'kind' => 'text',
        ]);
        $this->execute('address-book.field', [
            'chapter' => 'ssh',
            'field' => 'port',
            'label' => 'module.ssh.field.port',
            'kind' => 'number',
            'default' => '22',
        ]);
        $this->execute('address-book.field', [
            'chapter' => 'ssh',
            'field' => 'keyPath',
            'label' => 'module.ssh.field.key',
            'kind' => 'secret',
        ]);
    }

    /** Dopisanie wpisu drogą modułu: komenda plus kwerenda o jego identyfikator. */
    private function addEntry(string $name): string
    {
        $this->execute('address-book.add', ['name' => $name]);
        $rows = $this->rowsOf('address-book.last');

        return (string) ($rows[0]['id'] ?? '');
    }

    /**
     * @param array<string, string> $arguments
     *
     * @return string|null treść zdania, które komenda zostawiła
     */
    private function execute(string $name, array $arguments = []): ?string
    {
        $command = $this->app->commandRegistry->find($name);

        self::assertNotNull($command, 'nie ma komendy ' . $name);

        return $command->execute(new CommandInput($arguments))->message?->text;
    }

    /**
     * @param array<string, string> $arguments
     *
     * @return list<array<string, string|int|bool>>
     */
    private function rowsOf(string $name, array $arguments = []): array
    {
        return $this->app->state->queries()->ask($name, new CommandInput($arguments))->rows();
    }

    private function open(): void
    {
        $this->press(KeyPress::ctrl('w'));
    }

    private function type(string $text): void
    {
        foreach (str_split($text) as $character) {
            $this->press(KeyPress::character($character));
        }
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    /** @return list<string> */
    private function drawCurrent(): array
    {
        return self::textsOf($this->app->screens->current()->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
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
                $texts[] = $primitive->text;
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
            addressBook: $this->storage,
        );
    }
}
