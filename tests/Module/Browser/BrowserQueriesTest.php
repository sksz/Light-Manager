<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\MarkedEntries;
use LightManager\Module\Browser\Presentation\BrowserModule;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubFileOperations;
use LightManager\Tests\Support\StubFileTransfers;
use LightManager\Tests\Support\StubTranslator;
use LightManager\Tests\Support\StubTrash;
use PHPUnit\Framework\TestCase;

/**
 * Kwerendy przeglądarki — **wiersze dla obcych, ładunek dla właściciela**
 * (krok 53).
 *
 * Testy pilnują tu tego, czego nie widać ani w rejestrze, ani w oknie: kształtu
 * odpowiedzi. To jest kontrakt, na którym oprze się moduł Kubernetesa w kroku 54,
 * a kontraktu nie sprawdza się okiem w klatce.
 */
final class BrowserQueriesTest extends TestCase
{
    /** 2026-08-09 09:14:24 czasu lokalnego — data z listy oglądanej pod XTermem. */
    private const MODIFIED_AT = 1786266864;

    private LoopState $state;

    private QueryRegistry $queries;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [
                Entry::directory('dokumenty', modifiedAt: self::MODIFIED_AT),
                Entry::file('notatka.txt', 12, modifiedAt: self::MODIFIED_AT),
                Entry::file('bez-czasu.txt', 3),
                Entry::file('duzy.bin', 1234, modifiedAt: self::MODIFIED_AT),
                Entry::file('obraz.iso', 2469606195, modifiedAt: self::MODIFIED_AT),
            ])
            ->add('/home/dokumenty', []);

        $settings = new InMemorySettings();
        $this->state = new LoopState($settings->current());

        $module = new BrowserModule(
            $this->state,
            new StubTranslator(),
            $settings,
            new StubFileOperations(),
            new StubFileTransfers(),
            new StubTrash(),
            $directories,
            new DirectoryPath('/home'),
        );

        // Kwerendy w rejestrze — tą samą linią, co w `Bootstrapie`.
        $this->state->queries()->useModules([$module]);
        $module->screen();
        $this->queries = $this->state->queries();
    }

    /**
     * **Czas zmiany wraca jako data i godzina** — `2026-08-09 09:14:24`.
     *
     * Sekundy epoki są prawdziwe i nieczytelne naraz: moduł pytający je porówna,
     * a użytkownik w oknie kwerend zobaczy dziesięć cyfr. Napis odpowiada obu —
     * i porównuje się leksykograficznie tak samo, jak liczba numerycznie.
     * Litery `T` ani strefy w zapisie nie ma: tak samo podaje czas kolumna
     * „Zmieniony" na liście wpisów, a jedna aplikacja mówi o czasie jednym
     * głosem.
     */
    public function testEntriesGiveTheModificationTimeAsDateAndTime(): void
    {
        $notes = $this->rowNamed($this->rowsOf('entries'), 'notatka.txt');

        self::assertSame(date('Y-m-d H:i:s', self::MODIFIED_AT), $notes['modified']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $notes['modified'],
            'data i godzina rozdzielone spacją, bez litery T i bez strefy',
        );
    }

    /** Nieznany czas to **pusty napis**, a nie zero ani `-1`. */
    public function testAnUnknownModificationTimeIsAnEmptyString(): void
    {
        $rows = $this->rowsOf('entries');

        self::assertSame('', $this->rowNamed($rows, 'bez-czasu.txt')['modified']);
    }

    /**
     * **Rozmiar wraca z jednostką**, a nie jako liczba bajtów.
     *
     * Przy gigabajtach liczba bajtów jest nie do przeczytania, a wiersz kwerendy
     * ogląda także człowiek. Zapis liczy `EntrySize` — ta sama klasa, którą
     * podają rozmiar lista wpisów i drzewo.
     */
    public function testEntriesGiveTheSizeWithAUnit(): void
    {
        $rows = $this->rowsOf('entries');

        // Separator dziesiętny pochodzi od tłumacza — atrapa daje kropkę,
        // polski katalog przecinek (`2,3 GB` na klatce pod XTermem). Jednostka
        // jest międzynarodowa i przez katalog nie idzie.
        self::assertSame('12 B', $this->rowNamed($rows, 'notatka.txt')['size']);
        self::assertSame('1.2 kB', $this->rowNamed($rows, 'duzy.bin')['size']);
        self::assertSame('2.3 GB', $this->rowNamed($rows, 'obraz.iso')['size']);
    }

    /** Katalog nie ma rozmiaru, dopóki nie policzy go `du` — więc pole jest puste. */
    public function testADirectoryHasNoSize(): void
    {
        self::assertSame('', $this->rowNamed($this->rowsOf('entries'), 'dokumenty')['size']);
    }

    /** Pozostałe pola wiersza zostają danymi pierwotnymi — bez formatowania. */
    public function testTheOtherFieldsStayPrimitive(): void
    {
        $notes = $this->rowNamed($this->rowsOf('entries'), 'notatka.txt');

        self::assertSame('file', $notes['kind']);
        self::assertFalse($notes['hidden']);
        self::assertFalse($notes['selected']);
    }

    /** Właściciel dostaje katalog obiektem, a nie wierszami. */
    public function testTheOwnerGetsTheDirectoryItself(): void
    {
        $payload = $this->queries->ask(BrowserSettings::ID . '.entries')->payloadFor(BrowserSettings::ID);

        self::assertInstanceOf(Directory::class, $payload);
        self::assertSame('/home', $payload->path()->value);
    }

    /** Obcy modułowi ładunek nie przysługuje — zostają mu wiersze. */
    public function testAForeignModuleGetsRowsAndNoPayload(): void
    {
        $result = $this->queries->ask(BrowserSettings::ID . '.entries');

        self::assertNull($result->payloadFor('k8s'));
        self::assertNotSame([], $result->rows());
    }

    /** Pusty zbiór zaznaczonych to pusta odpowiedź, a nie wpis pod kursorem. */
    public function testMarkedIsEmptyWhenNothingIsMarked(): void
    {
        $result = $this->queries->ask(BrowserSettings::ID . '.marked');

        self::assertTrue($result->isEmpty());
        self::assertInstanceOf(MarkedEntries::class, $result->payloadFor(BrowserSettings::ID));
    }

    /**
     * @return list<array<string, string|int|bool>>
     */
    private function rowsOf(string $query): array
    {
        return $this->queries->ask(BrowserSettings::ID . '.' . $query, new CommandInput())->rows();
    }

    /**
     * @param list<array<string, string|int|bool>> $rows
     *
     * @return array<string, string|int|bool>
     */
    private function rowNamed(array $rows, string $name): array
    {
        foreach ($rows as $row) {
            if (($row['name'] ?? '') === $name) {
                return $row;
            }
        }

        self::fail('brak wiersza ' . $name);
    }
}
