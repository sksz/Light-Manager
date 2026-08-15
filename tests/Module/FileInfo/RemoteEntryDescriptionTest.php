<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\FileInfo;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ContextOrigin;
use LightManager\Application\Module\ModuleContext;
use LightManager\Module\FileInfo\Application\Dto\DescriptionSection;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubFileInspector;
use LightManager\Tests\Support\StubFileStat;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Opis wpisu leżącego **na innej maszynie** (krok 49).
 *
 * To jest odbiorca, bez którego pochodzenie kontekstu byłoby mechanizmem
 * rdzenia bez użytkownika (reguła 13). Test pilnuje dwóch rzeczy naraz i obie
 * są treścią zmiany: **co opis mówi** o wpisie zdalnym oraz — ważniejsze —
 * **czego przy nim nie robi**.
 *
 * Rzecz, której ten test broni najmocniej, jest cicha: obie ścieżki istnieją
 * i obie się czytają, więc pomyłka nie skończyłaby się błędem, tylko opisem
 * **lokalnego** pliku pokazanym jako opis zdalnego.
 */
final class RemoteEntryDescriptionTest extends TestCase
{
    public function testARemoteEntryIsDescribedWithoutTouchingTheDisk(): void
    {
        $stats = new StubFileStat();
        $inspector = new StubFileInspector('ASCII text');

        $description = $this->inspect($stats, $inspector)->execute($this->remoteContext());

        self::assertNotNull($description);
        self::assertSame([], $stats->requestedPaths, 'lstat opisałby lokalny plik o tej samej nazwie');
        self::assertSame([], $inspector->inspectedPaths, 'polecenie `file` też czyta dysk lokalny');
    }

    /** Sekcja „Miejsce" stoi pierwsza — odpowiada na pytanie zadawane przed innymi. */
    public function testLocationComesFirstAndNamesTheHost(): void
    {
        $description = $this->inspect()->execute($this->remoteContext());

        self::assertNotNull($description);
        self::assertSame('remote', $description->sections[0]->key);

        $rows = $description->sections[0]->rows;

        self::assertSame('anna@example.com', $rows[0]->value);
        self::assertSame('/var/log', $rows[1]->value);
    }

    /** Opis niesie to, co przyszło w kontekście — i tyle. */
    public function testTheDescriptionCarriesWhatTheListingKnew(): void
    {
        $description = $this->inspect()->execute($this->remoteContext());

        self::assertNotNull($description);
        self::assertSame('syslog', $description->name);
        self::assertSame(EntryKind::File, $description->kind);
        self::assertSame(2048, $description->sizeInBytes);
        self::assertSame(
            ['remote', 'identity', 'size', 'permissions', 'times'],
            array_map(static fn ($section): string => $section->key, $description->sections),
        );
    }

    public function testPermissionsAreShownInBothForms(): void
    {
        $description = $this->inspect()->execute($this->remoteContext());

        self::assertNotNull($description);

        $permissions = self::sectionOf($description->sections, 'permissions');

        self::assertNotNull($permissions);
        self::assertSame('rw-r--r--  0644', $permissions->rows[0]->value);
    }

    /**
     * Atrybut, którego wydawca nie znał, **nie dostaje wiersza** — pusty wiersz
     * z napisem „nie wiadomo" mówi to samo i zajmuje ekran.
     */
    public function testUnknownAttributesLeaveTheirSectionsOut(): void
    {
        $context = new ModuleContext(
            '/var/log',
            'syslog',
            ContextEntryKind::File,
            origin: ContextOrigin::Remote,
            originLabel: 'anna@example.com',
        );

        $description = $this->inspect()->execute($context);

        self::assertNotNull($description);
        self::assertSame(
            ['remote', 'identity'],
            array_map(static fn ($section): string => $section->key, $description->sections),
        );
    }

    /** Katalog zdalny nie pokazuje rozmiaru — tak samo, jak w liście. */
    public function testARemoteDirectoryHasNoSizeSection(): void
    {
        $context = new ModuleContext(
            '/var',
            'log',
            ContextEntryKind::Directory,
            origin: ContextOrigin::Remote,
            originLabel: 'anna@example.com',
            selectionBytes: 4096,
            selectionPermissions: 0o755,
        );

        $description = $this->inspect()->execute($context);

        self::assertNotNull($description);
        self::assertNull(self::sectionOf($description->sections, 'size'));
        self::assertSame(EntryKind::Directory, $description->kind);
    }

    /** Kontekst lokalny idzie starą drogą — zmiana nie ruszyła niczego, co działało. */
    public function testALocalContextStillGoesThroughStat(): void
    {
        $stats = new StubFileStat();

        $this->inspect($stats)->execute(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File));

        self::assertSame(['/home/notatka.txt'], $stats->requestedPaths);
    }

    private function remoteContext(): ModuleContext
    {
        return new ModuleContext(
            '/var/log',
            'syslog',
            ContextEntryKind::File,
            origin: ContextOrigin::Remote,
            originLabel: 'anna@example.com',
            selectionBytes: 2048,
            selectionModifiedAt: 1_786_795_200,
            selectionPermissions: 0o644,
        );
    }

    /**
     * @param list<DescriptionSection> $sections
     */
    private static function sectionOf(array $sections, string $key): ?DescriptionSection
    {
        foreach ($sections as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    private function inspect(
        ?StubFileStat $stats = null,
        ?StubFileInspector $inspector = null,
    ): InspectSelectedEntryUseCase {
        return new InspectSelectedEntryUseCase(
            $inspector ?? new StubFileInspector('ASCII text'),
            $stats ?? new StubFileStat(),
            new InMemorySettings(),
            new StubTranslator(),
        );
    }
}
