<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAddressBook;
use LightManager\Tests\Support\StubDockerState;
use LightManager\Tests\Support\StubRegistryApi;
use PHPUnit\Framework\TestCase;

/**
 * Rejestry obrazów całą drogą użytkownika (krok 61, etap 1).
 *
 * Etap pierwszy ma trzy rzeczy do udowodnienia i każda jest zdaniem
 * o zachowaniu, a nie o klasie: **rozdział rejestrów istnieje i przyjmuje
 * wpisy**, **trzy pozycje ustawień przenoszą się bez gubienia poświadczeń**
 * i **token nie wychodzi w wierszach spisu**.
 *
 * Ostatnie jest granicą bezpieczeństwa, nie szczegółem, więc ma osobną asercję
 * — tak, jak żąda plan kroku. Granica ta **nie jest przegrodą** i D107 mówi to
 * wprost: `address-book.value` odda ten sam token każdemu, kto zapyta. Test
 * pilnuje więc tego, czego pilnować się da: że token nie trafia tam, gdzie nikt
 * o niego nie prosił.
 *
 * **Żaden przebieg nie rozmawia z prawdziwym rejestrem.**
 */
final class RegistryFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const TOKEN = 'ghp_tajnytokenktoryniema prawa wyjsc';

    /**
     * **Rozdział rejestrów wchodzi do książki** — dwiema komendami w takcie,
     * jak każdy deklarujący (D104).
     */
    public function testTheRegistryChapterIsDeclared(): void
    {
        $app = $this->fixture();
        $app->ticker->tick($app->state, self::NOW);

        $chapters = $app->state->queries()->ask('address-book.chapters')->rows();
        $names = array_map(static fn (array $row): mixed => $row['chapter'] ?? null, $chapters);

        self::assertContains('registry', $names, 'rozdział rejestrów');
        self::assertContains('docker', $names, 'a rozdział środowisk zostaje obok');
    }

    /**
     * **Trzy pozycje ustawień przenoszą się do książki i nie gubią
     * poświadczeń** — kryterium ukończenia kroku.
     */
    public function testTheThreeSettingsMigrateWithoutLosingCredentials(): void
    {
        $app = $this->fixture($this->settingsWithRegistry());
        $app->ticker->tick($app->state, self::NOW);

        $row = $this->registryRow($app);

        self::assertNotNull($row, 'wpis rejestru powstał');
        self::assertSame('rejestr.example.com', $row['address'] ?? null);
        self::assertSame('anna', $row['user'] ?? null);
        self::assertTrue($row['credentials'] ?? false, 'token przeniesiony');
        self::assertTrue($row['default'] ?? false, 'jedyny rejestr staje się domyślny');

        // Wartość jest **cała**: przeniesienie, które ucina token, gubi
        // poświadczenie równie skutecznie, co przeniesienie, które go pomija.
        self::assertSame(self::TOKEN, $this->tokenOf($app, is_string($row['id'] ?? null) ? $row['id'] : ''));
    }

    /**
     * **Token nie pojawia się w wierszach spisu rejestrów** — osobna asercja,
     * bo to granica bezpieczeństwa, a nie szczegół.
     */
    public function testTheTokenNeverAppearsInTheRegistryListing(): void
    {
        $app = $this->fixture($this->settingsWithRegistry());
        $app->ticker->tick($app->state, self::NOW);

        $rows = $app->state->queries()->ask('docker.registries')->rows();

        self::assertNotSame([], $rows);
        self::assertStringNotContainsString(
            self::TOKEN,
            json_encode($rows, JSON_THROW_ON_ERROR),
            'token w wierszach kwerendy',
        );

        foreach ($rows as $row) {
            self::assertArrayNotHasKey('token', $row);
            self::assertIsBool($row['credentials'] ?? null, 'poświadczenie opisuje się wartością logiczną');
        }
    }

    /**
     * **Migracja nie zakłada wpisu, którego nikt nie zamawiał.** Sam adres
     * domyślny bez użytkownika i bez tokenu jest wartością deklaracji, a nie
     * wyborem — książka po pierwszym uruchomieniu ma zostać pusta.
     */
    public function testNothingIsMigratedWhenThereWasNothingToMigrate(): void
    {
        $state = new StubDockerState();
        $app = $this->fixture(new Settings(), $state);
        $app->ticker->tick($app->state, self::NOW);

        self::assertNull($this->registryRow($app));
        self::assertTrue($state->isRegistryMigrated(), 'a pytanie nie wraca przy każdym takcie');
    }

    /** Migracja pada **raz** — drugi takt nie mnoży wpisów. */
    public function testTheMigrationRunsOnlyOnce(): void
    {
        $app = $this->fixture($this->settingsWithRegistry());
        $app->ticker->tick($app->state, self::NOW);
        $app->ticker->tick($app->state, self::NOW + 1.0);

        $rows = $app->state->queries()->ask('docker.registries')->rows();

        self::assertCount(1, $rows);
    }


    /**
     * **Rejestr bez katalogu nie wygląda na zepsuty** — kryterium ukończenia
     * kroku i trzecia z trzech trudności strukturalnych.
     *
     * `404` na `/v2/_catalog` jest odpowiedzią „ten rejestr spisu nie wystawia",
     * a nie awarią: widok przechodzi w tryb „podaj nazwę obrazu", a kwerenda
     * mówi `none`, nie niesie powodu niepowodzenia.
     */
    public function testARegistryWithoutACatalogSwitchesToAskingForANameInstead(): void
    {
        $api = new StubRegistryApi();
        $api->answer(404, '{"errors":[{"code":"UNSUPPORTED"}]}');

        $app = $this->fixture($this->settingsWithRegistry(), registryApi: $api);
        $this->tickTwice($app);

        $this->openCatalog($app);

        $rows = $app->state->queries()->ask('docker.catalog')->rows();

        self::assertSame('none', $rows[0]['kind'] ?? null, 'tryb „podaj nazwę"');
        self::assertSame('', $rows[0]['problem'] ?? null, 'i ani słowa o błędzie');
        self::assertSame('done', $rows[0]['stage'] ?? null);
    }

    /** Katalog, który jest: wiersze niosą nazwy repozytoriów i etap. */
    public function testACatalogIsListedWithTheStageInEveryRow(): void
    {
        $api = new StubRegistryApi();
        $api->answer(200, '{"repositories":["proba/alpine","zespol/api"]}');

        $app = $this->fixture($this->settingsWithRegistry(), registryApi: $api);
        $this->tickTwice($app);
        $this->openCatalog($app);

        $rows = $app->state->queries()->ask('docker.catalog')->rows();
        $names = array_map(static fn (array $row): mixed => $row['name'] ?? null, $rows);

        self::assertSame(['proba/alpine', 'zespol/api'], $names);

        foreach ($rows as $row) {
            self::assertSame('repository', $row['kind'] ?? null);
            self::assertSame('done', $row['stage'] ?? null, 'etap stoi w każdym wierszu (11w)');
        }
    }

    /**
     * **Spis pusty dostaje wiersz z samym etapem** — inaczej „czytam", „nie ma
     * nic" i „nikt jeszcze nie pytał" wyglądają dla obcego identycznie.
     */
    public function testAnEmptyAnswerStillCarriesTheStage(): void
    {
        $app = $this->fixture($this->settingsWithRegistry(), registryApi: new StubRegistryApi());
        $this->tickTwice($app);

        $rows = $app->state->queries()->ask('docker.catalog')->rows();

        self::assertCount(1, $rows);
        self::assertSame('', $rows[0]['name'] ?? null);
        self::assertSame('idle', $rows[0]['stage'] ?? null, 'nikt jeszcze nie pytał');
    }

    /** Złe poświadczenia mają **własne zdanie**, inne niż „nie odpowiada". */
    public function testBadCredentialsSayWhatIsWrong(): void
    {
        $api = new StubRegistryApi();
        $api->fail('module.docker.registry.denied');

        $app = $this->fixture($this->settingsWithRegistry(), registryApi: $api);
        $this->tickTwice($app);
        $this->openCatalog($app);

        $rows = $app->state->queries()->ask('docker.catalog')->rows();

        self::assertSame('module.docker.registry.denied', $rows[0]['problem'] ?? null);
        self::assertSame('failed', $rows[0]['stage'] ?? null);
    }

    /**
     * Katalog pobiera się **klawiszami użytkownika**, a nie wywołaniem
     * koordynatora: `r` wchodzi w zawartość rejestru, `Ctrl`+`R` pyta.
     *
     * Droga przez klawisze jest tu treścią, a nie ceremonią — kryterium kroku
     * mówi „rozmowa **nie pada** przy wejściu w widok", a sprawdzić to da się
     * wyłącznie wtedy, gdy w widok naprawdę się wchodzi.
     */
    private function openCatalog(ScreenFixture $app): void
    {
        $app->dockerScreen->handle(KeyPress::character('r'));
        $app->dockerScreen->handle(KeyPress::ctrl('r'));
        $app->ticker->tick($app->state, self::NOW + 2.0);
    }

    private function tickTwice(ScreenFixture $app): void
    {
        $app->ticker->tick($app->state, self::NOW);
        $app->ticker->tick($app->state, self::NOW + 1.0);
    }


    /**
     * **Token czyta się przy zmianie rejestru, a nie co takt.**
     *
     * Takt modułu pada trzydzieści razy na sekundę, a `address-book.value`
     * oddaje materiał uwierzytelnienia. Pierwsza wersja tego kroku pytała
     * o token przy **każdym** takcie i odrzucała odpowiedź przy wszystkich poza
     * tym jednym, w którym rejestr się zmienił — ta sama pułapka, którą krok 59
     * zapłacił, pytając klaster o wersję serwera co klatkę.
     *
     * Test liczy **wywołania**, bo inaczej tego nie widać: moduł pytający raz
     * wygląda dokładnie tak samo, jak moduł pytający bez końca.
     */
    public function testTheTokenIsReadWhenTheRegistryChangesAndNotOnEveryTick(): void
    {
        $api = new StubRegistryApi();
        $app = $this->fixture($this->settingsWithRegistry(), registryApi: $api);

        $app->ticker->tick($app->state, self::NOW);
        $app->ticker->tick($app->state, self::NOW + 1.0);

        $first = $api->endpoint;

        self::assertNotNull($first, 'punkt końcowy powstał');
        self::assertSame(self::TOKEN, $first->token, 'token doszedł, gdy rejestr się pojawił');

        for ($tick = 2; $tick < 40; ++$tick) {
            $app->ticker->tick($app->state, self::NOW + $tick);
        }

        self::assertSame(1, $api->endpointChanges, 'punkt końcowy ustawiono **raz**');
    }

    /** @return array<string, string|int|bool>|null */
    private function registryRow(ScreenFixture $app): ?array
    {
        $rows = $app->state->queries()->ask('docker.registries')->rows();

        return $rows[0] ?? null;
    }

    private function tokenOf(ScreenFixture $app, string $entry): string
    {
        $rows = $app->state->queries()->ask('address-book.value', new CommandInput([
            'entry' => $entry,
            'chapter' => 'registry',
            'field' => 'token',
        ]))->rows();

        $value = $rows[0]['value'] ?? '';

        return is_string($value) ? $value : '';
    }

    private function settingsWithRegistry(): Settings
    {
        return (new Settings())
            ->withModuleValue(DockerSettings::ID, DockerSettings::REGISTRY, 'rejestr.example.com')
            ->withModuleValue(DockerSettings::ID, DockerSettings::REGISTRY_USER, 'anna')
            ->withModuleValue(DockerSettings::ID, DockerSettings::REGISTRY_TOKEN, self::TOKEN);
    }

    private function fixture(
        ?Settings $settings = null,
        ?StubDockerState $state = null,
        ?StubRegistryApi $registryApi = null,
    ): ScreenFixture {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);
        $app = new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            dockerState: $state ?? new StubDockerState(),
            addressBook: new StubAddressBook([]),
            registryApi: $registryApi ?? new StubRegistryApi(),
        );

        if ($settings !== null) {
            $app->state->applySettings($settings);
        }

        return $app;
    }
}
