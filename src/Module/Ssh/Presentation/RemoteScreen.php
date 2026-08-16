<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Ssh\Application\ListingStage;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Module\Ssh\Presentation\Component\RemoteSize;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Component\TextSpan;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Zdalny katalog — druga postać ekranu modułu (krok 49).
 *
 * **Nie dokłada ani jednego komponentu**, jak spis hostów w kroku 48: `Table`
 * z kroku 27, `ScrollWindow` z 18, zakresy dopasowania z 30 i pole filtra
 * złożone z `TextInput`. Nowy jest wyłącznie sposób złożenia.
 *
 * **Rysuje to, co zastanie, i nigdy nie czeka.** Klatka, w której odczyt trwa,
 * pokazuje poprzednią listę wraz ze zdaniem „czytam…" w górnym pasie — a nie
 * pustkę i nie klepsydrę. Wynika to wprost z reguły nadrzędnej fazy: praca
 * dzieje się w procesie potomnym, więc jedyne, co ekran o niej wie, to stan,
 * który ogląda co klatkę.
 *
 * **Kolumny są te same, co w liście lokalnej, i to jest zamierzone** — nazwa,
 * rozmiar, data, prawa, w tej samej kolejności ustępowania (11e: kolumna stała
 * ustępuje pierwsza). Różnica jest jedna i widać ją dopiero w treści: dane
 * pochodzą z wypisu `sftp`, więc data ma dokładność minuty, a właściciela nie
 * ma wcale — protokół oddaje go liczbą, której po drugiej stronie nikt nie
 * rozwiąże.
 */
final class RemoteScreen
{
    public const ID = 'ssh-remote';

    private const DATE_WIDTH = 17;

    private const SIZE_WIDTH = 9;

    private const PERMISSIONS_WIDTH = 9;

    private const NAME_MINIMUM = 20;

    /** Zapis daty ten sam, co w liście lokalnej — sortowalny wzrokiem i o stałej szerokości. */
    private const DATE_FORMAT = 'Y-m-d H:i';

    /**
     * Litera odświeżania — **przeprowadzka z `F5`** (krok 50, D89 nr 4).
     *
     * `F5` znaczy odtąd „pobierz", zgodnie z przeglądarką (`F5` kopiuj, `F6`
     * przenieś) i z nawykiem menadżerów dwupanelowych. Odświeżanie schodzi na
     * `Ctrl`+`R`, czyli w **przestrzeń skrótów modułów** (krok 19) — działa
     * dopóty, dopóki litery `r` nie zajmie żaden moduł, i pilnuje tego
     * `RemoteShortcutsTest`, wzorem `Ctrl`+`T` z kroku 31.
     */
    private const REFRESH_KEY = 'r';

    private readonly ScrollWindow $window;

    /**
     * Ile wierszy zmieściło się w ostatniej klatce — jedyna droga do „strona
     * w dół”.
     *
     * Wysokość panelu zna wyłącznie `draw()`, a `PageDown` przychodzi do
     * `handle()`, więc liczba musi przeżyć między jednym a drugim. To ta sama
     * zależność, którą krok 33 uczynił zwykłą: rozmiar okna nie jest stałą
     * uruchomienia, więc „strona” znaczy co klatkę coś innego.
     */
    private int $lastCapacity = 1;

    /**
     * @param RemoteBrowser $browser **wyłącznie do czynności** — wejścia do katalogu,
     *                               wyjścia wyżej, odświeżenia, filtra, kursora
     *                               i ukrytych. Odczyt idzie przez `$reader`
     *                               (krok 53, D92 nr 3)
     */
    public function __construct(
        private readonly RemoteBrowser $browser,
        private readonly TranslatorPort $translator,
        private readonly ChangeModuleSettingUseCase $changeSetting,
        private readonly LoopState $state,
        private readonly RemoteTransfer $transfers,
        private readonly SshQueries $reader,
        private readonly CoreReader $core,
    ) {
        $this->window = new ScrollWindow();
        $this->window->useContext(self::ID);
    }

    /**
     * Górny pas: **gdzie jestem i co się właśnie dzieje**.
     *
     * Ścieżka stoi tu nawet wtedy, gdy lista jeszcze nie przyszła — stan pracy
     * niesie ją od chwili zamówienia właśnie po to. Bez tego wejście w katalog
     * przez pół sekundy wyglądałoby jak brak reakcji.
     */
    public function header(): string
    {
        $state = $this->reader->remote();
        $path = $this->reader->path()->value ?? '';
        $host = $this->reader->host()?->label() ?? '';

        if ($state->stage === ListingStage::Listing) {
            return $this->text('module.' . SshSettings::ID . '.remote.reading', [
                'host' => $host,
                'path' => $path,
            ]);
        }

        return $this->text('module.' . SshSettings::ID . '.remote.header', [
            'host' => $host,
            'path' => $path,
        ]);
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $entries = $this->reader->remote()->entries;

        if ($entries === []) {
            return $this->nothing($bounds);
        }

        // Okno przewijania pamięta wycinek **pod kluczem katalogu**, więc wejście
        // w podkatalog i powrót zastają listę tam, gdzie się ją zostawiło —
        // ta sama zasada, co w liście lokalnej od kroku 18.
        $this->window->useContext(self::ID . ':' . ($this->reader->path()->value ?? ''));

        $count = count($entries);
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $cursor = $this->reader->remote()->cursor;
        $offset = $this->window->keepVisible($cursor, $count, $capacity);
        $this->lastCapacity = max(1, $capacity);

        return (new Table(
            $this->columns(),
            $this->rows(array_slice($entries, $offset, $capacity)),
            $cursor - $offset,
            $this->window->position($count, $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    /**
     * Panel bez wierszy — i **cztery różne powody**, dla których bywa pusty.
     *
     * Rozdzielone są dlatego, że użytkownik nie ma jak ich odróżnić z samego
     * braku wierszy: „czytam” i „nie udało się” wyglądają identycznie, a różnią
     * się tym, czy warto poczekać.
     *
     * @return list<Primitive>
     */
    private function nothing(Rect $bounds): array
    {
        $state = $this->reader->remote();

        $sentence = match (true) {
            $state->stage === ListingStage::Listing => $this->text('module.' . SshSettings::ID . '.remote.wait'),
            $state->stage === ListingStage::Failed => $this->text(
                $state->problemKey ?? 'module.' . SshSettings::ID . '.listing.failed',
                $state->problemParameters,
            ),
            !$this->reader->remote()->filter->isEmpty() => $this->text('module.' . SshSettings::ID . '.remote.noMatch'),
            default => $this->text('module.' . SshSettings::ID . '.remote.empty'),
        };

        $role = $state->stage === ListingStage::Failed ? Role::Danger : Role::Muted;
        $primitives = (new Table($this->columns(), [], null, null, true))->draw($bounds);

        if ($bounds->rows < 2) {
            return $primitives;
        }

        foreach ((new Label($sentence, '', $role))->draw($bounds->line(1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /** @return list<KeyBinding> */
    public function bindings(): array
    {
        $bindings = [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of(
                [Key::Enter],
                'module.' . SshSettings::ID . '.remote.key.enter',
                'module.' . SshSettings::ID . '.remote.key.enter.short',
            ),
            KeyBinding::of(
                [Key::Backspace],
                'module.' . SshSettings::ID . '.remote.key.up',
                'module.' . SshSettings::ID . '.remote.key.up.short',
            ),
            KeyBinding::of(
                [Key::F5],
                'module.' . SshSettings::ID . '.transfer.key.get',
                'module.' . SshSettings::ID . '.transfer.key.get.short',
            ),
            KeyBinding::of(
                [Key::F6],
                'module.' . SshSettings::ID . '.transfer.key.put',
                'module.' . SshSettings::ID . '.transfer.key.put.short',
            ),
            KeyBinding::ctrl(
                self::REFRESH_KEY,
                'module.' . SshSettings::ID . '.remote.key.refresh',
                'module.' . SshSettings::ID . '.remote.key.refresh.short',
            ),
            KeyBinding::character(
                '/',
                'module.' . SshSettings::ID . '.remote.key.filter',
                'module.' . SshSettings::ID . '.remote.key.filter.short',
            ),
            KeyBinding::ctrl(
                'h',
                'module.' . SshSettings::ID . '.remote.key.hidden',
                'module.' . SshSettings::ID . '.remote.key.hidden.short',
            ),

        ];

        if (!$this->reader->remote()->filter->isEmpty()) {
            $bindings[] = KeyBinding::of(
                [Key::Escape],
                'module.' . SshSettings::ID . '.remote.key.clear',
                'module.' . SshSettings::ID . '.remote.key.clear.short',
            );
        }

        return $bindings;
    }

    public function focus(): FocusHint
    {
        return new FocusHint('module.' . SshSettings::ID . '.focus.remote', $this->bindings());
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Character && $key->raw === '/' && !$key->ctrl && !$key->alt) {
            return ScreenOutcome::opens(new RemoteFilterOverlay($this->browser, $this->translator, $this->reader));
        }

        if ($key->key === Key::Character && $key->raw === 'h' && $key->ctrl) {
            return $this->toggleHidden();
        }

        if ($key->key === Key::Character && $key->raw === self::REFRESH_KEY && $key->ctrl) {
            return $this->refresh();
        }

        return match ($key->key) {
            Key::ArrowUp => $this->moved(-1),
            Key::ArrowDown => $this->moved(1),
            Key::PageUp => $this->moved(-$this->lastCapacity),
            Key::PageDown => $this->moved($this->lastCapacity),
            Key::Home => $this->put(0),
            Key::End => $this->put($this->reader->remote()->count() - 1),
            Key::Enter => $this->enter(),
            Key::Backspace => $this->goUp(),
            Key::F5 => $this->transfers->downloadPrompt(),
            Key::F6 => $this->transfers->uploadPrompt(),
            Key::Escape => $this->clearFilter(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Enter`: wchodzi w katalog albo mówi, że nie ma w co.
     *
     * Zdanie odmowy jest tu ważniejsze niż zwykle, bo dotyczy także dowiązania:
     * lista pokazuje, że wpis **jest** dowiązaniem, ale dokąd prowadzi, wie
     * dopiero serwer — i to on odmawia, a moduł jego odmowę pokazuje.
     */
    private function enter(): ScreenOutcome
    {
        if ($this->browser->enter()) {
            return ScreenOutcome::stay();
        }

        $entry = $this->reader->selected();

        return ScreenOutcome::stay($entry === null
            ? null
            : Message::info($this->text('module.' . SshSettings::ID . '.remote.notADirectory', [
                'name' => $entry->name,
            ])));
    }

    private function goUp(): ScreenOutcome
    {
        return $this->browser->goUp()
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::info($this->text('module.' . SshSettings::ID . '.remote.atRoot')));
    }

    private function refresh(): ScreenOutcome
    {
        $this->browser->refresh();

        return ScreenOutcome::stay();
    }

    /**
     * `Ctrl`+`H`: przełącza wpisy ukryte **i zapisuje wybór w ustawieniach**.
     *
     * Zapis jest tu z tego samego powodu, co w przeglądarce (krok 32): klawisz
     * i pozycja ustawień opisują **tę samą** rzecz, więc rozjechane znaczyłyby
     * dwie prawdy o jednym stanie. Kolejność jest odwrotna niż tam i wynika
     * z natury odczytu: przeglądarka czyta katalog **przed** zapisem, bo nieudany
     * odczyt rzuca; tutaj odczyt trwa w tle i o jego niepowodzeniu wiadomo dopiero
     * za kilka klatek, więc czekanie na nie znaczyłoby ustawienie zapisywane
     * z opóźnieniem albo wcale.
     */
    private function toggleHidden(): ScreenOutcome
    {
        $this->browser->toggleHidden();

        [$settings, $message] = $this->changeSetting->shift(
            $this->core->settings(),
            SshSettings::ID,
            self::hiddenDeclaration(),
            1,
        );
        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message ?? Message::info($this->text(
            $this->reader->remote()->showsHidden
                ? 'module.' . SshSettings::ID . '.remote.hidden.on'
                : 'module.' . SshSettings::ID . '.remote.hidden.off',
        )));
    }

    /** Deklaracja pozycji „wpisy ukryte" — ta sama, którą pokazuje zakładka ustawień. */
    private static function hiddenDeclaration(): ModuleSetting
    {
        return ModuleSetting::toggle(
            SshSettings::SHOW_HIDDEN,
            'module.' . SshSettings::ID . '.setting.' . SshSettings::SHOW_HIDDEN,
            SshSettings::DEFAULT_SHOW_HIDDEN,
        );
    }

    /** `Esc` zdejmuje filtr, a przy pustym filtrze **nie zużywa klawisza** — zamyka ekran. */
    private function clearFilter(): ScreenOutcome
    {
        if ($this->reader->remote()->filter->isEmpty()) {
            return ScreenOutcome::close();
        }

        $this->browser->clearFilter();

        return ScreenOutcome::stay();
    }

    private function moved(int $delta): ScreenOutcome
    {
        $this->browser->moveCursor($delta);

        return ScreenOutcome::stay();
    }

    private function put(int $index): ScreenOutcome
    {
        $this->browser->putCursor($index);

        return ScreenOutcome::stay();
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::flexible(
                self::NAME_MINIMUM,
                label: $this->text('module.' . SshSettings::ID . '.column.entry'),
            ),
            Column::fixed(
                self::SIZE_WIDTH,
                yieldOrder: 3,
                align: Align::Right,
                label: $this->text('module.' . SshSettings::ID . '.column.size'),
                role: Role::Muted,
            ),
            Column::fixed(
                self::DATE_WIDTH,
                yieldOrder: 2,
                label: $this->text('module.' . SshSettings::ID . '.column.modified'),
                role: Role::Muted,
            ),
            Column::fixed(
                self::PERMISSIONS_WIDTH,
                yieldOrder: 1,
                label: $this->text('module.' . SshSettings::ID . '.column.permissions'),
                role: Role::Muted,
            ),
        ];
    }

    /**
     * @param list<RemoteEntry> $entries
     *
     * @return list<TableRow>
     */
    private function rows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = new TableRow(
                [
                    $entry->name . ($entry->isDirectory() ? '/' : ''),
                    $this->sizeOf($entry),
                    $entry->modifiedAt === null ? '' : date(self::DATE_FORMAT, $entry->modifiedAt),
                    $entry->permissionsAsText(),
                ],
                self::roleOf($entry),
                $this->marksIn($entry),
            );
        }

        return $rows;
    }

    /**
     * Zakresy dopasowania filtra — w kolumnie nazwy i tylko w niej.
     *
     * Rachunek jest **rdzeniowy** (`TextSpan::occurrencesOf()` z kroku 30),
     * a nie powtórzony: powtarzamy w tym module pojęcia domeny, nie mechanizmy.
     *
     * @return array<int, list<TextSpan>>
     */
    private function marksIn(RemoteEntry $entry): array
    {
        $filter = $this->reader->remote()->filter;

        if ($filter->isEmpty()) {
            return [];
        }

        $spans = TextSpan::occurrencesOf($filter->value, $entry->name);

        return $spans === [] ? [] : [0 => $spans];
    }

    /**
     * Kolor wiersza mówi o rodzaju wpisu: katalog akcentem, dowiązanie
     * przygaszone, reszta zwykłym tekstem.
     *
     * Dowiązanie **nie dostaje ostrzeżenia**, choć nie wiadomo, dokąd prowadzi:
     * `Warning` jest w Grafitcie tym samym kolorem co akcent (wniosek z kroku 43),
     * więc dowiązanie wyglądałoby na katalog — czyli kolor kłamałby dokładnie
     * w tym jednym miejscu, w którym miał ostrzegać.
     */
    private static function roleOf(RemoteEntry $entry): Role
    {
        return match ($entry->type) {
            RemoteEntryType::Directory => Role::Accent,
            RemoteEntryType::Symlink => Role::Muted,
            default => Role::Text,
        };
    }

    /**
     * Rozmiar dla oka — katalog nie ma żadnego i pokazuje pustkę.
     *
     * Sam rachunek stoi od kroku 50 w `RemoteSize`, bo ma w tym module drugiego
     * użytkownika: licznik okna przesyłu. Tutaj zostaje wyłącznie to, co należy
     * do listy — że katalog rozmiaru nie pokazuje.
     */
    private function sizeOf(RemoteEntry $entry): string
    {
        $bytes = $entry->sizeInBytes;

        if ($entry->isDirectory() || $bytes === null) {
            return '';
        }

        return RemoteSize::of($this->translator, $bytes);
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters);
    }
}
