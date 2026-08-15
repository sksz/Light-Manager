<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\UseCase;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\FileInfo\Application\Dto\DescriptionRow;
use LightManager\Module\FileInfo\Application\Dto\DescriptionSection;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\Dto\FileStat;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\FileInspectorPort;
use LightManager\Module\FileInfo\Application\Port\FileStatPort;
use LightManager\Module\FileInfo\Application\SizeText;

/**
 * Obraz stanu wpisu — treść ekranu modułu.
 *
 * Do kroku 20 przypadek użycia przyjmował `Directory` i sam wyciągał z niego
 * zaznaczenie. Dziś dostaje `ModuleContext`, czyli **dane pierwotne**: ścieżkę,
 * nazwę i rodzaj. Zmiana nie jest kosmetyczna — to ona sprawia, że moduł nie zna
 * agregatu, który w kroku 21 zszedł z rdzenia do modułu przeglądarki (D40, P5).
 *
 * Krok 25 zmienia w nim trzy rzeczy. **Katalogów już nie pomija** (P3): katalog
 * ma uprawnienia, właściciela, czasy i liczbę wpisów, a to wszystko jest opisem
 * tak samo dobrym jak dla pliku. **Składa sekcje, a nie wiersze** (P4). I
 * **rozdziela źródła po koszcie**: `lstat` idzie zawsze, bo kosztuje tyle, co nic;
 * `file` tylko dla zwykłych plików, bo za nim stoi proces potomny, a dla katalogu
 * powiedziałby wyłącznie, że jest katalogiem.
 *
 * Czego tu **nie ma**: sumy kontrolnej i zajętości na dysku. Obie liczą się
 * między klatkami i obie dokłada do sekcji ekran — pierwsza od kroku 25, druga
 * od 26. Przypadek użycia wykonuje się raz na zaznaczenie i wszystko, co w nim
 * stoi, musi być gotowe w tej jednej chwili; praca trwająca cztery sekundy nie
 * jest gotowa w żadnej.
 *
 * **Krok 49 dokłada drugą drogę** — wpis, którego ten proces nie może dotknąć.
 * Kontekst mówi odtąd, czyja jest ścieżka (`ContextOrigin`), a wpis zdalny idzie
 * przez `remote()`: opis powstaje **wyłącznie z kontekstu**, bez `lstat`, bez
 * `file` i bez sieci. Rozdział jest tu ostry z jednego powodu: obie ścieżki
 * istnieją i obie się czytają, więc pomyłka nie skończyłaby się błędem, tylko
 * opisem cudzego pliku pokazanym jako opis tego, na który użytkownik patrzy.
 */
final class InspectSelectedEntryUseCase
{
    private const SECONDS_PER_UNIT = [
        ['module.file-info.ago.years', 31_536_000],
        ['module.file-info.ago.months', 2_592_000],
        ['module.file-info.ago.days', 86_400],
        ['module.file-info.ago.hours', 3_600],
        ['module.file-info.ago.minutes', 60],
    ];

    private ?SizeText $sizes = null;

    public function __construct(
        private readonly FileInspectorPort $inspector,
        private readonly FileStatPort $stats,
        private readonly SettingsPort $settings,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function execute(ModuleContext $context): ?EntryDescription
    {
        $path = $context->selectionPath();

        if ($path === null) {
            return null;
        }

        if ($context->isRemote()) {
            return $this->remote($context);
        }

        $stat = $this->stats->stat($path);

        if ($stat === null) {
            return null;
        }

        $settings = $this->settings->current();
        $content = $this->content($path, $stat);
        $sections = [
            $this->identity($stat, $context->selection ?? '', $content),
            $this->size($stat),
            $this->permissions($stat, FileInfoSettings::inode($settings)),
            $this->times($stat, FileInfoSettings::relativeTime($settings), $this->now()),
        ];

        return new EntryDescription(
            $context->selection ?? '',
            $sections,
            $stat->kind,
            $stat->sizeInBytes,
            $content,
        );
    }

    /**
     * Opis wpisu leżącego **na innej maszynie** (krok 49) — z tego, co niesie
     * kontekst, i **z niczego więcej**.
     *
     * Metoda nie dotyka dysku ani sieci ani razu i to jest jej cała treść.
     * `lstat` powiedziałby o **lokalnym** pliku o tej samej ścieżce, a sieć nie
     * pada w rysowaniu klatki (reguła nadrzędna Fazy XVII) — więc pytanie
     * o cokolwiek ponad kontekst jest tu nie „drogie”, tylko niemożliwe.
     *
     * Sekcji są przez to trzy zamiast czterech, a w każdej stoi mniej wierszy:
     * nie ma właściciela ani grupy (protokół SFTP niesie je liczbami, a nazw po
     * drugiej stronie nikt nie rozwiąże), nie ma i-węzła, nie ma czasu dostępu
     * ani zmiany i-węzła — `sftp ls -l` pokazuje **jeden** czas. Pustych wierszy
     * z napisem „nie wiadomo” w ich miejsce nie ma: brak wiersza mówi to samo
     * i nie zajmuje ekranu.
     *
     * **Sekcja „Miejsce" jest pierwsza**, bo odpowiada na pytanie, które przy
     * zdalnym wpisie pada przed wszystkimi innymi: na czym ja właściwie patrzę.
     */
    private function remote(ModuleContext $context): EntryDescription
    {
        $name = $context->selection ?? '';
        $kind = self::remoteKind($context);
        $settings = $this->settings->current();
        $sections = [
            new DescriptionSection('remote', 'module.file-info.section.remote', [
                new DescriptionRow('module.file-info.row.host', $context->originLabel),
                new DescriptionRow('module.file-info.row.remotePath', $context->path),
            ]),
            $this->identityOfRemote($name, $kind),
        ];

        $bytes = $context->selectionBytes;

        if ($bytes !== null && $kind !== EntryKind::Directory) {
            $sections[] = new DescriptionSection('size', 'module.file-info.section.size', [
                new DescriptionRow('module.file-info.row.size', $this->formatSize($bytes)),
                new DescriptionRow(
                    'module.file-info.row.sizeExact',
                    $this->translator->plural('module.file-info.bytes', $bytes),
                ),
            ]);
        }

        $rows = $this->remoteFacts($context);

        if ($rows !== []) {
            $sections[] = new DescriptionSection('permissions', 'module.file-info.section.permissions', $rows);
        }

        $modifiedAt = $context->selectionModifiedAt;

        if ($modifiedAt !== null) {
            $sections[] = new DescriptionSection('times', 'module.file-info.section.times', [
                new DescriptionRow(
                    'module.file-info.row.modified',
                    $this->formatTime($modifiedAt, FileInfoSettings::relativeTime($settings), $this->now()),
                ),
            ]);
        }

        return new EntryDescription($name, $sections, $kind, $bytes ?? 0);
    }

    /** @return list<DescriptionRow> */
    private function remoteFacts(ModuleContext $context): array
    {
        $permissions = $context->selectionPermissions;

        if ($permissions === null) {
            return [];
        }

        return [
            new DescriptionRow(
                'module.file-info.row.mode',
                self::permissionsAsText($permissions) . '  ' . sprintf('%04o', $permissions),
            ),
        ];
    }

    /**
     * Wiersz „czym to jest" wraz ze zdaniem, dlaczego opis jest krótszy.
     *
     * Zdanie stoi w sekcji tożsamości, a nie w pasku stanu, bo dotyczy **tego
     * wpisu**, a nie tego, co użytkownik przed chwilą zrobił.
     */
    private function identityOfRemote(string $name, EntryKind $kind): DescriptionSection
    {
        return new DescriptionSection('identity', 'module.file-info.section.identity', [
            new DescriptionRow('module.file-info.row.name', $name),
            new DescriptionRow('module.file-info.row.kind', $this->translator->translate($kind->labelKey())),
            new DescriptionRow(
                'module.file-info.row.limits',
                $this->translator->translate('module.file-info.remote.limits'),
            ),
        ]);
    }

    /**
     * Rodzaj z kontekstu — z trzech przypadków, jakie zna rdzeń, na osiem, jakie
     * zna ten moduł.
     *
     * Dowiązanie zdalne dochodzi tu jako `Unknown`, a nie `Symlink`, i jest to
     * uczciwe: kontekst niesie `ContextEntryKind`, który dowiązania nie zna,
     * bo rdzeń ma o cudzym zaznaczeniu wiedzieć tyle, co nic (D40, P5).
     */
    private static function remoteKind(ModuleContext $context): EntryKind
    {
        return match ($context->kind) {
            ContextEntryKind::Directory => EntryKind::Directory,
            ContextEntryKind::File => EntryKind::File,
            ContextEntryKind::None => EntryKind::Unknown,
        };
    }

    /**
     * Prawa w postaci `rwxr-xr-x`.
     *
     * Rachunek powtarza `FileStat::permissionsAsText()` i **powtarza go
     * świadomie**: tamten liczy z `lstat`, ten z liczby przyniesionej
     * z kontekstu, a wyniesienie ich do wspólnego miejsca znaczyłoby wspólne
     * miejsce w rdzeniu — czyli rdzeń wiedzący, czym są prawa pliku (D42).
     */
    private static function permissionsAsText(int $permissions): string
    {
        $text = '';

        foreach ([6, 3, 0] as $shift) {
            $bits = ($permissions >> $shift) & 7;
            $text .= ($bits & 4) === 4 ? 'r' : '-';
            $text .= ($bits & 2) === 2 ? 'w' : '-';
            $text .= ($bits & 1) === 1 ? 'x' : '-';
        }

        return $text;
    }

    /**
     * Opis treści od polecenia `file` — **wyłącznie dla zwykłych plików**.
     *
     * Dla katalogu, gniazda czy kolejki nazwanej polecenie powtórzyłoby rodzaj,
     * który stoi wiersz wyżej, a kosztuje proces potomny wraz z limitem czasu.
     * Od kroku 29 wynik jedzie dalej niż do wiersza „Zawartość”: jest drugim
     * stopniem kaskady rozpoznającej plik tekstowy.
     */
    private function content(string $path, FileStat $stat): ?string
    {
        if ($stat->kind !== EntryKind::File) {
            return null;
        }

        $settings = $this->settings->current();

        return $this->inspector->describe(
            $path,
            FileInfoSettings::timeout($settings),
            FileInfoSettings::arguments($settings),
        );
    }

    /** Czym wpis jest: rodzaj z `lstat`, opis od `file` i cel dowiązania. */
    private function identity(FileStat $stat, string $name, ?string $content): DescriptionSection
    {
        $rows = [
            new DescriptionRow('module.file-info.row.name', $name),
            new DescriptionRow('module.file-info.row.kind', $this->translator->translate($stat->kind->labelKey())),
        ];

        if ($content !== null) {
            $rows[] = new DescriptionRow('module.file-info.row.content', $content);
        }

        if ($stat->linkTarget !== null) {
            $rows[] = new DescriptionRow('module.file-info.row.target', $stat->linkTarget);
            $rows[] = new DescriptionRow(
                'module.file-info.row.targetState',
                $this->translator->translate(
                    $stat->linkTargetExists === true
                        ? 'module.file-info.target.exists'
                        : 'module.file-info.target.missing',
                ),
            );
        }

        if ($stat->entryCount !== null) {
            $rows[] = new DescriptionRow(
                'module.file-info.row.entries',
                $this->translator->plural('module.file-info.entries', $stat->entryCount),
            );
        }

        return new DescriptionSection('identity', 'module.file-info.section.identity', $rows);
    }

    /**
     * Rozmiar w bajtach i w jednostkach — obie postacie naraz, bo pierwsza jest
     * dokładna, a druga czytelna, i żadna nie zastępuje drugiej.
     *
     * Zajętości na dysku tu **nie ma**: wymaga procesu potomnego doglądanego
     * między klatkami, a ten mechanizm dostał własny krok planu (26). Wiersz
     * pokazany z wartością „nie wiadomo” byłby gorszy niż jego brak.
     */
    private function size(FileStat $stat): DescriptionSection
    {
        $rows = [
            new DescriptionRow('module.file-info.row.size', $this->formatSize($stat->sizeInBytes)),
            new DescriptionRow(
                'module.file-info.row.sizeExact',
                $this->translator->plural('module.file-info.bytes', $stat->sizeInBytes),
            ),
        ];

        if ($stat->blocks !== null) {
            $rows[] = new DescriptionRow(
                'module.file-info.row.blocks',
                $this->formatSize($stat->blocks * 512),
            );
        }

        return new DescriptionSection('size', 'module.file-info.section.size', $rows);
    }

    private function permissions(FileStat $stat, bool $withInode): DescriptionSection
    {
        $rows = [
            new DescriptionRow(
                'module.file-info.row.mode',
                $stat->permissionsAsText() . '  ' . $stat->permissionsAsOctal(),
            ),
            new DescriptionRow('module.file-info.row.owner', $this->principal($stat->ownerName, $stat->ownerId)),
            new DescriptionRow('module.file-info.row.group', $this->principal($stat->groupName, $stat->groupId)),
        ];

        if ($withInode) {
            $rows[] = new DescriptionRow('module.file-info.row.inode', (string) $stat->inode);
            $rows[] = new DescriptionRow(
                'module.file-info.row.links',
                $this->translator->number($stat->links),
            );
        }

        return new DescriptionSection('permissions', 'module.file-info.section.permissions', $rows);
    }

    private function times(FileStat $stat, bool $relative, int $now): DescriptionSection
    {
        return new DescriptionSection('times', 'module.file-info.section.times', [
            new DescriptionRow('module.file-info.row.modified', $this->formatTime($stat->modifiedAt, $relative, $now)),
            new DescriptionRow('module.file-info.row.changed', $this->formatTime($stat->changedAt, $relative, $now)),
            new DescriptionRow('module.file-info.row.accessed', $this->formatTime($stat->accessedAt, $relative, $now)),
        ]);
    }

    /**
     * Nazwa właściciela, a przy jej braku sam numer wraz z powodem.
     *
     * Pustki nie pokazujemy nigdy: brak nazwy jest informacją o **systemie**
     * (nie ma rozszerzenia `posix`), a nie o pliku, więc ma być powiedziany
     * wprost, a nie zgadywany z pustego wiersza.
     */
    private function principal(?string $name, int $id): string
    {
        if ($name === null) {
            return $this->translator->translate('module.file-info.principal.numeric', ['id' => $id]);
        }

        return $this->translator->translate('module.file-info.principal', ['name' => $name, 'id' => $id]);
    }

    /**
     * Rachunek wyprowadzono w kroku 26 do `SizeText`, gdy wiersz „zajęte na
     * dysku” miał zostać jego trzecim wołającym w tym module. Zapis liczb się
     * nie zmienił — to było przeniesienie, nie poprawka.
     */
    private function formatSize(int $bytes): string
    {
        return ($this->sizes ??= new SizeText($this->translator))->format($bytes);
    }

    /**
     * Czas bezwzględny albo „ile temu” — wedle ustawienia modułu.
     *
     * Zapis bezwzględny idzie **stałym wzorem ISO**, a nie wzorem z katalogu
     * napisów: data w opisie pliku ma dać się porównać z tym, co pokazuje
     * powłoka, a nie czytać jak zdanie.
     */
    private function formatTime(int $timestamp, bool $relative, int $now): string
    {
        if (!$relative) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        $seconds = max(0, $now - $timestamp);

        foreach (self::SECONDS_PER_UNIT as [$key, $unit]) {
            if ($seconds >= $unit) {
                return $this->translator->plural($key, intdiv($seconds, $unit));
            }
        }

        return $this->translator->translate('module.file-info.ago.now');
    }

    /**
     * Chwila bieżąca — wyłącznie dla zapisu „ile temu”.
     *
     * Zegar klatki tu nie dociera i nie ma potrzeby, żeby docierał: różnica
     * jednej klatki nie zmienia zdania „3 dni temu”, a przeciąganie czasu przez
     * trzy warstwy tylko po to kosztowałoby więcej, niż jest warte.
     */
    private function now(): int
    {
        return time();
    }
}
